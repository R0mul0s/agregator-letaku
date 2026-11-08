<?php

/**
 * Import akcí e-shopu Rohlik.cz — ID akcí po odděleních, ceny s typy akcí a detaily produktů po dávkách
 * (R180, ZDROJE_DAT.md, Rohlík). Obchod je ve výchozím stavu vypnutý (obchodní podmínky), testy ho
 * zapínají přes `letaky.enabled_chains`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ROHLIK_NBSP = "\u{00A0}";

/**
 * Falešné odpovědi Rohlíku z fixtures: ID akcí oddělení podle čísla v adrese, ceny a detaily jen pro
 * produkty z dotazu (`?products=1&products=2`).
 *
 * @param  (Closure(array<mixed>, string): array<mixed>)|null  $modify  Úprava odpovědi (data, cesta)
 */
function fakeRohlik(?Closure $modify = null): void
{
    Http::fake(function (Request $request) use ($modify) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        parse_str(str_replace('products=', 'products[]=', (string) parse_url($request->url(), PHP_URL_QUERY)), $query);
        $ids = array_map(intval(...), (array) ($query['products'] ?? []));

        if (preg_match('#/categories/sales/(\d+)/products$#', $path, $m) === 1) {
            $data = jsonResponseFixture("rohlik/sales-{$m[1]}-2026-11-08.json");
            // Jediná stránka — další by API vrátilo prázdnou
            if ((int) ($query['page'] ?? 0) > 0) {
                $data['productIds'] = [];
            }
        } else {
            [$fixture, $key] = str_ends_with($path, '/prices') ? ['prices', 'productId'] : ['products', 'id'];
            $data = array_values(array_filter(jsonResponseFixture("rohlik/{$fixture}-2026-11-08.json"), fn (array $item): bool => in_array($item[$key], $ids, true)));
        }

        return Http::response($modify === null ? $data : $modify($data, $path));
    });
}

/**
 * Nabídka Rohlíku z databáze podle začátku názvu.
 */
function rohlikOffer(string $name): Offer
{
    return Offer::query()->where('chain', Chain::Rohlik)->where('name', 'like', $name.'%')->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-11-08 10:00:00');
    config([
        'letaky.enabled_chains' => ['rohlik'],
        // Fixtures: ovoce a zelenina (11), maso (7) a trvanlivé s ukázkami typů akcí (5)
        'letaky.sources.rohlik.departments' => [300102000 => 'Ovoce a zelenina', 300103000 => 'Maso a ryby', 300106000 => 'Trvanlivé'],
        'letaky.sources.rohlik.page_size' => 11,
    ]);
});

it('bez zapnutí v LETAKY_ENABLED_CHAINS se nestahuje', function (): void {
    config(['letaky.enabled_chains' => []]);

    expect(app(SourceRegistry::class)->chainsWithOffers())->not->toContain(Chain::Rohlik);
});

it('uloží akce pro všechny z oddělení a vynechá akce jen pro Xtra, při doručení dnes a skončené', function (): void {
    fakeRohlik();

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']])->assertSuccessful();

    $leaflet = Leaflet::query()->sole();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflet->kind)->toBe(LeafletKind::Eshop)
        ->and($leaflet->external_id)->toBe('akce')
        // 23 produktů: rybíz má jen slevu při doručení dnes, polévka jen Xtra; batát fialový končí dnes (8. 11.)
        ->and(Offer::query()->where('chain', Chain::Rohlik)->count())->toBe(21)
        ->and(Offer::query()->where('name', 'like', 'Rybíz%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', '%Frankfurtská%')->exists())->toBeFalse()
        ->and(rohlikOffer('Batát fialový')->valid_to->toDateString())->toBe('2026-11-08')
        ->and(Offer::query()->where('chain', Chain::Rohlik)->where('online_only', false)->exists())->toBeFalse();

    // Ceny a detaily po dávkách ve tvaru ?products=1&products=2
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/products/prices?products=1294755&products='));
});

it('týdenní akci uloží se slevou, balením, koncem z validTill a odkazy', function (): void {
    fakeRohlik();

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']]);

    expect(rohlikOffer('Citrony balené'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(2990)
        ->original_price->toBe(3990)
        ->package_text->toBe('500 g')
        ->quantity->toBe(500.0)
        ->unit->toBe(PackageUnit::Gram)
        ->source_category->toBe('Ovoce a zelenina')
        ->source_url->toBe('https://www.rohlik.cz/1331029-citrony-balene-sit')
        ->image_url->toStartWith('https://cdn.rohlik.cz/images/grocery/products/1331029/')
        ->valid_from->toDateString()->toBe('2026-11-08')
        ->valid_to->toDateString()->toBe('2026-11-09')
        ->and(rohlikOffer('Kofola Original'))
        // Kofola má týdenní akci 39,90 i dlouhodobou 49,90 — platí nižší
        ->price->toBe(3990)
        ->original_price->toBe(4990)
        ->brand->toBe('Kofola');
});

it('vážené zboží uloží s cenou za kilogram a hmotností kusu v popisu', function (): void {
    fakeRohlik();

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']]);

    // Husa cca 3,61 kg: 757,30 Kč z 1 081,86 Kč za kus = 209,93 z 299,90 Kč/kg
    expect(rohlikOffer('Svatomartinská husa'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(20993)
        ->original_price->toBe(29990)
        ->package_text->toBe('1 kg')
        ->quantity->toBe(1000.0)
        ->description->toBe('cca 3,61 kg');
});

it('akci na množství uloží s běžnou cenou kusu a výhodnou v textu, u dlouhodobé akce jen text', function (): void {
    fakeRohlik();

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']]);

    expect(rohlikOffer('Semix Müsli'))
        ->offer_type->toBe(OfferType::Multibuy)
        ->price->toBe(1190)
        ->original_price->toBeNull()
        ->promotion_text->toBe('od 3 ks: 9,90'.ROHLIK_NBSP.'Kč')
        ->valid_to->toDateString()->toBe('2026-12-31')
        // Jar: dlouhodobá akce 59,90 z 64,90 a „Kup 2“ za 49,90
        ->and(rohlikOffer('Jar Sensitive'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(5990)
        ->original_price->toBe(6490)
        ->promotion_text->toBe('od 2 ks: 49,90'.ROHLIK_NBSP.'Kč')
        // Máslo: dlouhodobá akce pro všechny, nižší cena jen pro Xtra se nebere
        ->and(rohlikOffer('Miil Máslo'))
        ->price->toBe(3690)
        ->original_price->toBe(4490)
        ->loyalty_price->toBeNull();
});

it('akce, která pokračuje se stejnou cenou, si nechá začátek z prvního stažení (R54)', function (): void {
    fakeRohlik();
    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']]);
    $lemons = rohlikOffer('Citrony balené');

    $this->travelTo('2026-11-09 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']])->assertSuccessful();

    expect(Offer::query()->where('chain', Chain::Rohlik)->count())->toBe(21)
        ->and(rohlikOffer('Citrony balené'))
        ->id->toBe($lemons->id)
        ->valid_from->toDateString()->toBe('2026-11-08')
        ->withdrawn_at->toBeNull();
});

it('chybějící detaily produktů stažení zastaví', function (): void {
    fakeRohlik(fn (array $data, string $path): array => str_ends_with($path, '/api/v1/products') ? [] : $data);

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('21 z 21 produktů v akci nejde převést');
});

it('změněný tvar odpovědi stažení zastaví', function (): void {
    Http::fake(['*' => Http::response(['items' => []])]);

    $this->artisan('letaky:import-offers', ['chain' => ['rohlik']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('nemají productIds');
});
