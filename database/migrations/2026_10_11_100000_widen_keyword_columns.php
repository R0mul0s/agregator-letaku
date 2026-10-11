<?php

/**
 * Delší pravidla produktů katalogu a hlídaných položek (R135): 500 znaků místo 255. Víno podle
 * odrůd potřebuje seznam odrůd ve dvou skupinách slov (něco vinného a zároveň barva nebo odrůda);
 * hlídané položky stejnou délku kvůli kopii pravidel při smazání produktu (DeleteProduct).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nová délka pravidel (= letaky.watch.keywords_max_length). */
    private const LENGTH = 500;

    /** Původní délka sloupců. */
    private const PREVIOUS_LENGTH = 255;

    /**
     * Prodlouží sloupce pravidel.
     */
    public function up(): void
    {
        $this->resize(self::LENGTH);
    }

    /**
     * Vrátí původní délku (delší pravidla by se uřízla — MariaDB v přísném režimu změnu odmítne).
     */
    public function down(): void
    {
        $this->resize(self::PREVIOUS_LENGTH);
    }

    /**
     * Nastaví délku sloupců pravidel v products a watch_items, komentáře a povinnost zůstávají.
     */
    private function resize(int $length): void
    {
        Schema::table('products', function (Blueprint $table) use ($length): void {
            $table->string('keywords', $length)->comment('všechna slova musí být v nabídce, alternativy přes | (R18)')->change();
            $table->string('variant_keywords', $length)->nullable()->comment('chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)')->change();
            $table->string('exclude_keywords', $length)->nullable()->comment('kterékoli slovo nabídku vyřadí')->change();
        });

        Schema::table('watch_items', function (Blueprint $table) use ($length): void {
            $table->string('keywords', $length)->nullable()->comment('vlastní slova; null u položky z katalogu')->change();
            $table->string('variant_keywords', $length)->nullable()->comment('chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)')->change();
            $table->string('exclude_keywords', $length)->nullable()->comment('kterékoli slovo nabídku vyřadí')->change();
        });
    }
};
