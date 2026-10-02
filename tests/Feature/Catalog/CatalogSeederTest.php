<?php

/**
 * Startovní sada katalogu (R33) — produkty se založí, opakované spuštění je nezdvojí
 * a kategorie se najdou podle cesty ve stromu Tesca.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('založí startovní produkty s kategorií a přiřadí k nim akce', function (): void {
    // Fixture stromu má větve mléčných výrobků, vajec a kolových nápojů
    Http::fake(['https://xapi.tesco.com/*' => Http::response(responseFixture('tesco/taxonomy-2026-10-02.json'))]);
    $this->artisan('letaky:import-categories');
    Offer::factory()->create(['name' => 'Čerstvá vejce M']);

    $this->seed(CatalogSeeder::class);
    $count = Product::query()->count();
    $this->seed(CatalogSeeder::class);

    expect(Product::query()->count())->toBe($count)
        ->and($count)->toBeGreaterThan(50)
        ->and(Product::query()->where('name', 'Vejce')->sole()->category?->name)->toBe('Vejce')
        ->and(Product::query()->where('name', 'Coca-Cola Zero')->sole()->category?->name)->toBe('Kolové nápoje bez cukru')
        ->and(Product::query()->where('name', 'Rýže')->sole()->category_id)->toBeNull()
        ->and(OfferProduct::query()->whereHas('product', fn ($query) => $query->where('name', 'Vejce'))->count())->toBe(1);
});
