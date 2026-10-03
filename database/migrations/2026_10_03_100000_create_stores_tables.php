<?php

/**
 * Prodejny Kauflandu a v kterých platí akce (R49): seznam prodejen se seznamem jejich akcí,
 * vazba akce na prodejny (jen u akcí, které neplatí všude) a prodejny vybrané uživatelem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulky stores a offer_stores a sloupec followed_chains.store_codes.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->string('code', 20)->comment('kód prodejny obchodu (Kaufland CZ4400)');
            $table->string('name', 150)->comment('název bez názvu obchodu („Trutnov“, „Praha-Vypich“)');
            $table->string('city', 100);
            $table->json('offer_keys')->nullable()->comment('akce platné v prodejně jako klíč nabídky „id|od|do“ (OfferData::key)');
            $table->timestamp('offer_keys_fetched_at')->nullable()->comment('UTC, kdy se seznam akcí stáhl');
            $table->timestamps();

            $table->unique(['chain', 'code']);
        });

        Schema::create('offer_stores', function (Blueprint $table): void {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('store_code', 20)->comment('stores.code — akce platí jen v těchto prodejnách; bez řádků = všude');

            $table->primary(['offer_id', 'store_code']);
            $table->index('store_code');
        });

        Schema::table('followed_chains', function (Blueprint $table): void {
            $table->json('store_codes')->nullable()->after('include_online_only')->comment('vybrané prodejny (stores.code); null = všechny');
        });
    }

    /**
     * Odstraní tabulky prodejen a sloupec vybraných prodejen.
     */
    public function down(): void
    {
        Schema::table('followed_chains', function (Blueprint $table): void {
            $table->dropColumn('store_codes');
        });
        Schema::dropIfExists('offer_stores');
        Schema::dropIfExists('stores');
    }
};
