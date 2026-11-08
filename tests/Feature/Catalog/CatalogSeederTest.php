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

use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchItemMatcher;
use App\Domain\Matching\WatchRule;
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

it('každý produkt katalogu má slovo dost dlouhé pro předvýběr (R54)', function (): void {
    $normalizer = app(TextNormalizer::class);
    $min = config()->integer('letaky.watch.min_search_word_length');

    $tooShort = array_filter(
        require database_path('seeders/data/catalog-products.php'),
        fn (array $product): bool => WatchRule::fromText($product['keywords'], null, null, $normalizer)->prefilterLength() < $min,
    );

    expect(array_column($tooShort, 'name'))->toBe([]);
});

it('energetický nápoj chytá Red Bull, ale ne „bullet“ ani kosmetiku Bull Dog', function (string $name, bool $matches): void {
    $normalizer = app(TextNormalizer::class);
    $product = collect(require database_path('seeders/data/catalog-products.php'))->firstWhere('name', 'Energetický nápoj');
    $rule = WatchRule::fromText($product['keywords'], $product['variant_keywords'], $product['exclude_keywords'], $normalizer);

    expect(app(WatchItemMatcher::class)->matchText($rule, $normalizer->normalize($name), false) !== null)->toBe($matches);
})->with([
    'Red Bull' => ['Red Bull Energy Drink 250 ml', true],
    'Red Bull bez slova energy' => ['Red Bull Sugarfree 250ml plech', true],
    // Párování hledá začátek slova — „bull“ našlo vibrátor z Košíku (8. 11. 2026)
    'vibrátor' => ['Durex Vibrátor Mini Delight bullet', false],
    'Bull Dog' => ['Bull Dog Original solný sprej pro styling vlasů 150ml', false],
    'Bulldog' => ['Bulldog Skincare Sensitive hydratační krém 100ml', false],
]);
