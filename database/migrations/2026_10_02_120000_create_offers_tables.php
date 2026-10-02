<?php

/**
 * Zdroje nabídek (letáky, akční stránky, e-shop), akční nabídky a záznam stahování.
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
     * Vytvoří tabulky leaflets, scrape_runs a offers.
     */
    public function up(): void
    {
        Schema::create('leaflets', function (Blueprint $table): void {
            $table->id();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->string('kind', 20)->comment('App\Enums\LeafletKind');
            $table->string('external_id', 100)->comment('ID u obchodu (Tesco 708, Kaufland nabidka-2026-09-30…)');
            $table->string('title')->nullable();
            $table->string('format', 20)->nullable()->comment('App\Enums\StoreFormat; null = všechny prodejny');
            $table->date('valid_from')->nullable()->comment('místní datum (R7); null = průběžné akce e-shopu');
            $table->date('valid_to')->nullable()->comment('místní datum (R7), včetně');
            $table->string('source_url', 500)->nullable();
            $table->timestamp('fetched_at')->comment('UTC, poslední stažení');
            $table->timestamps();

            $table->unique(['chain', 'kind', 'external_id']);
        });

        Schema::create('scrape_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->string('status', 20)->comment('App\Enums\ScrapeStatus');
            $table->unsignedInteger('offers_count')->default(0);
            $table->unsignedInteger('withdrawn_count')->default(0)->comment('nabídky, které v tomto stažení chyběly (R16)');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->comment('UTC');
            $table->timestamp('finished_at')->nullable()->comment('UTC');
            $table->timestamps();

            $table->index(['chain', 'started_at']);
        });

        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->foreignId('leaflet_id')->constrained()->restrictOnDelete();
            $table->foreignId('scrape_run_id')->comment('stažení, ve kterém se nabídka naposledy objevila (R16)')->constrained()->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable()->comment('UTC; obchod nabídku stáhl nebo změnil před koncem platnosti (R16)');
            $table->string('store_format', 20)->nullable()->comment('App\Enums\StoreFormat; null = všechny prodejny');
            $table->string('external_id', 64)->comment('ID položky u obchodu (Kaufland klNr, Tesco id produktu)');
            $table->string('name');
            $table->string('brand', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('variant_note', 100)->nullable()->comment('„různé druhy“ — párování „možná“ (R9)');
            $table->string('package_text', 100)->nullable()->comment('balení, jak ho uvádí obchod');
            $table->decimal('quantity', 10, 3)->nullable()->comment('množství v balení v jednotce unit');
            $table->string('unit', 5)->nullable()->comment('App\Enums\PackageUnit (g, ml, ks)');
            $table->unsignedInteger('price')->nullable()->comment('haléře; cena bez karty, null = obchod ji neuvádí');
            $table->unsignedInteger('original_price')->nullable()->comment('haléře; původní cena před slevou');
            $table->unsignedInteger('loyalty_price')->nullable()->comment('haléře; cena s kartou nebo aplikací');
            $table->string('loyalty_program', 20)->nullable()->comment('App\Enums\LoyaltyProgram');
            $table->unsignedTinyInteger('discount_percent')->nullable()->comment('% slevy podle obchodu');
            $table->string('offer_type', 20)->comment('App\Enums\OfferType (R8)');
            $table->string('promotion_text')->nullable()->comment('popis akce od obchodu („3 za cenu 2“)');
            $table->boolean('online_only')->default(false)->comment('jen e-shop (R4)');
            $table->date('valid_from')->comment('místní datum (R7)');
            $table->date('valid_to')->comment('místní datum (R7), včetně');
            $table->string('source_category', 150)->nullable()->comment('kategorie u obchodu');
            $table->string('image_url', 500)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->json('raw')->comment('původní položka od obchodu');
            $table->timestamps();

            $table->unique(['chain', 'external_id', 'valid_from', 'valid_to']);
            $table->index('valid_to');
        });
    }

    /**
     * Odstraní tabulky nabídek.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('scrape_runs');
        Schema::dropIfExists('leaflets');
    }
};
