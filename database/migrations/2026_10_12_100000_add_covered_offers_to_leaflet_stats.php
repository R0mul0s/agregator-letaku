<?php

/**
 * Akce letáku převzaté jiným zdrojem obchodu (R136): Lidl zveřejní akce z PDF letáku později
 * i na webu a z letáku se pak neuloží — bez jejich počtu by přehled kvality dat hlásil propad.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá sloupec covered_offers.
     */
    public function up(): void
    {
        Schema::table('leaflet_stats', function (Blueprint $table): void {
            $table->unsignedInteger('covered_offers')->default(0)->after('offers_count')
                ->comment('akce letáku, které nese jiný zdroj obchodu (Lidl: web) a z letáku se neuložily');
        });
    }

    /**
     * Odstraní sloupec covered_offers.
     */
    public function down(): void
    {
        Schema::table('leaflet_stats', function (Blueprint $table): void {
            $table->dropColumn('covered_offers');
        });
    }
};
