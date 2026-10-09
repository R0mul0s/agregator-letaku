<?php

/**
 * „Tohle ne“ (R125): akce, které uživatel skryl u své hlídané položky — Moje slevy, souhrny
 * ani upozornění je u ní už neukážou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku watch_item_offer_exclusions.
     */
    public function up(): void
    {
        Schema::create('watch_item_offer_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('watch_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['watch_item_id', 'offer_id']);
        });
    }

    /**
     * Odstraní tabulku watch_item_offer_exclusions.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_item_offer_exclusions');
    }
};
