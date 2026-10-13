<?php

/**
 * Návrhy, co hlídat (R148): co si uživatel opakovaně dává do nákupního seznamu — vlastní
 * položka nebo produkt katalogu akce. Po `letaky.watch_suggestions.min_hits` zásazích se
 * nabídne v Hlídám a v seznamu; zavřený návrh („Ne, díky“) se nevrací.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku watch_suggestions.
     */
    public function up(): void
    {
        Schema::create('watch_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term', 100)->comment('název po TextNormalizer — klíč návrhu');
            $table->string('name', 100)->comment('název k zobrazení, jak ho uživatel naposledy napsal');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('hits')->default(1)->comment('kolikrát se položka do seznamu dostala v okně window_days');
            $table->timestamp('last_hit_at')->comment('UTC, poslední započítaný zásah');
            $table->timestamp('dismissed_at')->nullable()->comment('UTC, „Ne, díky“ nebo začátek hlídání; null = otevřený návrh');
            $table->timestamps();

            $table->unique(['user_id', 'term']);
            $table->index('last_hit_at');
        });
    }

    /**
     * Odstraní tabulku watch_suggestions.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_suggestions');
    }
};
