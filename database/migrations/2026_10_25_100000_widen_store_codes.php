<?php

/**
 * Delší kód prodejny (R169): prodejny COOP nemají číselné ID, kódem je část adresy jejich stránky
 * na coopclub.cz („jednota-kamenice-nad-lipou-viteslava-novaka-550“, až 54 znaků). Sloupec měl 20
 * znaků podle Kauflandu („CZ4400“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nová délka kódu prodejny. */
    private const LENGTH = 80;

    /** Původní délka (Kaufland, Globus). */
    private const PREVIOUS_LENGTH = 20;

    /**
     * Prodlouží kód prodejny v tabulkách stores a offer_stores.
     */
    public function up(): void
    {
        $this->resize(self::LENGTH);
    }

    /**
     * Vrátí původní délku — prodejny s delším kódem (COOP) se smažou i s vazbami a výběrem uživatelů.
     */
    public function down(): void
    {
        DB::table('offer_stores')->whereRaw('CHAR_LENGTH(store_code) > ?', [self::PREVIOUS_LENGTH])->delete();
        DB::table('stores')->whereRaw('CHAR_LENGTH(code) > ?', [self::PREVIOUS_LENGTH])->delete();
        DB::table('followed_chains')->where('chain', 'coop')->update(['store_codes' => null]);
        $this->resize(self::PREVIOUS_LENGTH);
    }

    /**
     * Nastaví délku kódu prodejny v obou tabulkách.
     */
    private function resize(int $length): void
    {
        Schema::table('stores', function (Blueprint $table) use ($length): void {
            $table->string('code', $length)->comment('kód prodejny obchodu (Kaufland CZ4400, COOP část adresy stránky prodejny)')->change();
        });
        Schema::table('offer_stores', function (Blueprint $table) use ($length): void {
            $table->string('store_code', $length)->comment('stores.code — akce platí jen v těchto prodejnách; bez řádků = všude')->change();
        });
    }
};
