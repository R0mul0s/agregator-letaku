<?php

/**
 * Hlídaná položka z katalogu (R31): odkaz na produkt, vlastní slova jsou pak nepovinná.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá watch_items.product_id a uvolní povinnost slov.
     */
    public function up(): void
    {
        Schema::table('watch_items', function (Blueprint $table): void {
            // Smazání produktu: pravidla se nejdřív zkopírují do položky (CatalogController::destroy)
            $table->foreignId('product_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('keywords')->nullable()->comment('vlastní slova; null u položky z katalogu')->change();
        });
    }

    /**
     * Odstraní watch_items.product_id; položky z katalogu dostanou slova produktu.
     */
    public function down(): void
    {
        DB::statement('UPDATE watch_items w JOIN products p ON p.id = w.product_id SET w.keywords = p.keywords WHERE w.keywords IS NULL');

        Schema::table('watch_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->string('keywords')->nullable(false)->comment('všechna slova musí být v nabídce, alternativy přes |')->change();
        });
    }
};
