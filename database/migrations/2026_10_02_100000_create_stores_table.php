<?php

/**
 * Prodejny obchodů a jejich výběr uživateli (R3).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vytvoří tabulky prodejen a výběru prodejen uživatelem.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->string('external_id', 50)->comment('ID prodejny u obchodu (Kaufland CZ3300, Tesco storeId…)');
            $table->string('name');
            $table->string('format', 20)->nullable()->comment('App\Enums\StoreFormat; null = obchod formáty nerozlišuje');
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable()->comment('stupně');
            $table->decimal('longitude', 9, 6)->nullable()->comment('stupně');
            $table->timestamps();

            $table->unique(['chain', 'external_id']);
        });

        Schema::create('store_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'store_id']);
        });
    }

    /**
     * Odstraní tabulky prodejen.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('stores');
    }
};
