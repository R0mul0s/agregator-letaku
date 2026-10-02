<?php

/**
 * Jeden produkt z katalogu hlídá uživatel nejvýš jednou (R31). Dříve vzniklé duplicity
 * se smažou — ponechá se nejstarší položka.
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
     * Smaže duplicitní položky z katalogu a přidá unikátní index (user_id, product_id).
     */
    public function up(): void
    {
        DB::statement('DELETE w FROM watch_items w JOIN watch_items older
            ON older.user_id = w.user_id AND older.product_id = w.product_id AND older.id < w.id');

        Schema::table('watch_items', function (Blueprint $table): void {
            // Vlastní slova mají product_id null — MariaDB v unikátním indexu nully nepočítá
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Odstraní unikátní index.
     */
    public function down(): void
    {
        Schema::table('watch_items', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'product_id']);
        });
    }
};
