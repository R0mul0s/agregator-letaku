<?php

/**
 * Statistika letáku za každé stažení (R129): kolik akcí stažení z letáku uložilo a u letáků
 * z PDF nebo SVG, kolik cen parser našel a kolik ověřil. Podklad přehledu kvality dat
 * a upozornění na propad; starší než `letaky.data_quality.retention_days` maže denní úklid.
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
     * Založí tabulku leaflet_stats.
     */
    public function up(): void
    {
        Schema::create('leaflet_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scrape_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leaflet_id')->constrained()->cascadeOnDelete();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->unsignedInteger('offers_count')->comment('akce letáku uložené tímto stažením');
            $table->unsignedInteger('tile_candidates')->nullable()->comment('ceny, ke kterým parser PDF/SVG hledal dlaždici; null = leták z API');
            $table->unsignedInteger('tiles_verified')->nullable()->comment('ceny ověřené jako akce');
            $table->timestamp('created_at')->comment('UTC, konec stažení');

            $table->index(['leaflet_id', 'created_at']);
            $table->index(['chain', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Odstraní tabulku leaflet_stats.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaflet_stats');
    }
};
