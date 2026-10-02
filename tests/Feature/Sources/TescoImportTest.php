<?php

/**
 * Import nabídky Tesco — akce e-shopu a letáky HM a SM (ZDROJE_DAT.md, Tesco).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\ScrapeStatus;
use App\Enums\StoreFormat;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Falešné odpovědi Tesco: API letáků podle typu letáku v dotazu, xapi podle stránky.
 */
function fakeTesco(): void
{
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://api.prod.retail.tesco.com/')) {
            return Http::response(match ($request->data()['variables']['leafletType'] ?? null) {
                'HM' => responseFixture('tesco/leaflet-hm-2026-10-02.json'),
                'SM' => responseFixture('tesco/leaflet-sm-2026-10-02.json'),
                default => responseFixture('tesco/leaflets-2026-10-02.json'),
            });
        }

        $page = $request->data()[0]['variables']['page'];

        return Http::response(responseFixture("tesco/promotions-page{$page}-2026-10-02.json"));
    });
}

/**
 * Nabídka z databáze podle ID produktu v e-shopu.
 */
function tescoOffer(string $productId): Offer
{
    return Offer::query()->where('chain', Chain::Tesco)->where('external_id', $productId)->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    // Fixtures akcí jsou po 5 produktech na stránku
    config(['letaky.sources.tesco.eshop_page_size' => 5]);
});

it('stáhne letáky HM a SM a všechny stránky akcí e-shopu', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']])->assertSuccessful();

    expect(Offer::query()->count())->toBe(9)
        ->and(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded);

    // Katalog (CAT) se nesleduje
    expect(Leaflet::query()->orderBy('id')->get()->map(fn (Leaflet $leaflet): string => $leaflet->kind->value.':'.$leaflet->external_id)->all())
        ->toBe(['eshop:eshop', 'leaflet:708', 'leaflet:709']);

    // Seznam letáků, dva letáky, dvě stránky akcí; e-shop s veřejným klíčem
    Http::assertSentCount(5);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://xapi.tesco.com/')
        && $request->hasHeader('x-apikey', 'test-api-key'));
});

it('produkt jen z letáku hypermarketu platí jen v hypermarketech', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']]);

    $offer = tescoOffer('205389945');
    expect($offer)
        ->name->toBe('Kuřecí prsní řízky volné')
        ->store_format->toBe(StoreFormat::Hypermarket)
        ->online_only->toBeFalse()
        ->offer_type->toBe(OfferType::Discount)
        // Zboží na váhu: afterDiscount je cena za kg, ne price.actual
        ->price->toBe(11990)
        ->original_price->toBe(21990)
        ->discount_percent->toBe(45)
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Gram)
        ->and($offer->leaflet?->external_id)->toBe('708');
});

it('produkt z letáků HM i SM platí ve všech prodejnách', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']]);

    expect(tescoOffer('219279706'))
        ->store_format->toBeNull()
        ->online_only->toBeFalse()
        ->price->toBe(2990)
        ->original_price->toBe(5990)
        ->quantity->toBe(10.0)
        ->unit->toBe(PackageUnit::Piece);
});

it('Clubcard cenu vezme z popisu akce, afterDiscount je běžná cena (R8)', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']]);

    $milk = tescoOffer('211037571');
    expect($milk)
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(2190)
        ->loyalty_price->toBe(890)
        ->loyalty_program->toBe(LoyaltyProgram::Clubcard)
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Milliliter)
        ->and($milk->valid_from->toDateString())->toBe('2026-10-02')
        ->and($milk->valid_to->toDateString())->toBe('2026-10-04');

    expect(tescoOffer('212693462'))
        ->name->toBe('Česnek')
        ->price->toBe(14900)
        ->loyalty_price->toBe(9900)
        ->quantity->toBe(1000.0);
});

it('produkt, který není v žádném letáku, uloží jako jen online (R4)', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']]);

    $offer = tescoOffer('218272500');
    expect($offer)
        ->online_only->toBeTrue()
        ->loyalty_price->toBe(2490)
        ->and($offer->leaflet?->kind)->toBe(LeafletKind::Eshop)
        ->and($offer->valid_to->toDateString())->toBe('2026-10-13');
});

it('akci „3 za cenu 2“ uloží jako akci na více kusů s textem akce', function (): void {
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']]);

    $offer = tescoOffer('105029083');
    expect($offer)
        ->offer_type->toBe(OfferType::Multibuy)
        ->price->toBe(5490)
        ->loyalty_price->toBeNull()
        ->loyalty_program->toBe(LoyaltyProgram::Clubcard)
        ->promotion_text->toBe('3 za cenu 2 Clubcard cena - Nejlevnější produkt zdarma')
        ->quantity->toBe(90.0)
        // Konec v zimním čase: 2026-11-01T23:00:00Z = půlnoc 2. 11.
        ->and($offer->valid_to->toDateString())->toBe('2026-11-01');
});

it('bez API klíče stažení selže a nic nestáhne', function (): void {
    config(['letaky.sources.tesco.eshop_api_key' => null]);
    fakeTesco();

    $this->artisan('letaky:import-offers', ['chain' => ['tesco']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('TESCO_API_KEY');
    Http::assertNothingSent();
});
