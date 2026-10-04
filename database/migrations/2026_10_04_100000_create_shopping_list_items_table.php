<?php

/**
 * Nákupní seznam (R61): akce, které si uživatel dal do seznamu, s odškrtnutím v obchodě.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku shopping_list_items.
     */
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->nullable()->comment('UTC, kdy uživatel položku v obchodě odškrtl; null = ještě koupit');
            $table->timestamps();

            $table->unique(['user_id', 'offer_id']);
        });
    }

    /**
     * Odstraní tabulku shopping_list_items.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
