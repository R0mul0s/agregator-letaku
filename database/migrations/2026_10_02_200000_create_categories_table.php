<?php

/**
 * Strom kategorií katalogu produktů — snímek stromu e-shopu Tesco (R28).
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
     * Vytvoří tabulku categories.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('source_id', 400)->unique()->comment('ID uzlu ve stromu e-shopu Tesco (zakódovaná cesta)');
            $table->unsignedTinyInteger('depth')->comment('0 = oddělení, 1 = sekce, 2 = regál, 3 = police');
            $table->unsignedSmallInteger('position')->comment('pořadí mezi sourozenci podle Tesca');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku categories.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
