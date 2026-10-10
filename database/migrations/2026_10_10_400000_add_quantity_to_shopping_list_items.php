<?php

/**
 * Množství položky nákupního seznamu (R133): počet kusů nebo balení, výchozí 1.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá sloupec quantity.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->unsignedTinyInteger('quantity')->default(1)->after('chain')->comment('počet kusů nebo balení');
        });
    }

    /**
     * Odebere sloupec quantity.
     */
    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
