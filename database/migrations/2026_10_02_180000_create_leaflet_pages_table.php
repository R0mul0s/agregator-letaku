<?php

/**
 * Text stránek letáků pro zmínky bez ceny (R27) — kde se hlídaná věc v letáku objevuje.
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
     * Vytvoří tabulku leaflet_pages.
     */
    public function up(): void
    {
        Schema::create('leaflet_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leaflet_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number')->comment('číslo stránky od 1');
            $table->text('text')->comment('slova stránky (Lidl keyWords, Penny text vektorové vrstvy)');
            $table->string('image_url', 500)->nullable()->comment('náhled stránky na CDN obchodu (R22)');
            $table->string('page_url', 500)->nullable()->comment('stránka v prohlížeči letáku obchodu');
            $table->timestamps();

            $table->unique(['leaflet_id', 'number']);
        });
    }

    /**
     * Odstraní tabulku leaflet_pages.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaflet_pages');
    }
};
