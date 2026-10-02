<?php

/**
 * Import nabídky Billy — celý katalog z product-discovery API, akce vybere parser (R48, ZDROJE_DAT.md, Billa).
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
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Support\Facades\Http;

const BILLA_API_URL = 'https://www.billa.cz/api/product-discovery/products';

/** Nezlomitelná mezera v ceně v textu akce („9,93 Kč“). */
const BILLA_NBSP = "\u{00A0}";

/**
 * Falešné odpovědi Billy: dvě stránky katalogu (total = součet obou).
 */
function fakeBilla(): void
{
    Http::fake([
        BILLA_API_URL.'?page=0*' => Http::response(responseFixture('billa/products-page0-2026-10-02.json')),
        BILLA_API_URL.'?page=1*' => Http::response(responseFixture('billa/products-page1-2026-10-02.json')),
    ]);
}

/**
 * Nabídka z databáze podle začátku názvu.
 */
function billaOffer(string $name): Offer
{
    return Offer::query()->where('chain', Chain::Billa)->where('name', 'like', $name.'%')->sole();
}

beforeEach(function (): void {
    // Pátek — akční týden středa 30. 9. až úterý 6. 10.
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží akce z obou stránek katalogu s platností akčního týdne', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    $leaflet = Leaflet::query()->sole();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflet->kind)->toBe(LeafletKind::Web)
        ->and($leaflet->external_id)->toBe('web-2026-09-30')
        // 12 produktů: bez akce (čaj) a doprodej (příbory) se neuloží
        ->and(Offer::query()->where('chain', Chain::Billa)->count())->toBe(10)
        ->and(Offer::query()->where('name', 'like', 'TEEKANNE%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', 'Set příborů%')->exists())->toBeFalse()
        ->and(billaOffer('Campari')->valid_from->toDateString())->toBe('2026-09-30')
        ->and(billaOffer('Campari')->valid_to->toDateString())->toBe('2026-10-06');
});

it('akční týden začíná ve středu', function (string $today, string $from, string $to): void {
    $this->travelTo($today.' 10:00:00');
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    expect(billaOffer('Campari'))
        ->valid_from->toDateString()->toBe($from)
        ->valid_to->toDateString()->toBe($to);
})->with([
    'úterý' => ['2026-10-06', '2026-09-30', '2026-10-06'],
    'středa' => ['2026-10-07', '2026-10-07', '2026-10-13'],
]);

it('slevu uloží s původní cenou a cenu s Klubem jen nižší než běžnou', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    expect(billaOffer('Campari Bitter'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(39990)
        ->original_price->toBe(49990)
        ->discount_percent->toBe(20)
        ->quantity->toBe(700.0)
        ->unit->toBe(PackageUnit::Milliliter)
        ->source_url->toBe('https://www.billa.cz/produkt/campari-bitter-70cl-82100073')
        // Cena s Klubem 27,90 je vyšší než akční 26,90 — není to výhoda
        ->and(billaOffer('Bella For Teens'))
        ->price->toBe(2690)
        ->loyalty_price->toBeNull();
});

it('akci jen s BILLA Klubem pozná podle ceny s Klubem bez akčního štítku', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    expect(billaOffer('Hera Classic'))
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(2790)
        ->loyalty_price->toBe(2490)
        ->loyalty_program->toBe(LoyaltyProgram::BillaKlub);
});

it('akci na množství uloží s běžnou cenou kusu a výhodnou cenou v textu', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    expect(billaOffer('Zott Jogobella'))
        ->offer_type->toBe(OfferType::Multibuy)
        ->price->toBe(1490)
        ->promotion_text->toBe('cena 1ks při koupi 3ks: 9,93'.BILLA_NBSP.'Kč')
        ->and(billaOffer('Korunní'))
        ->offer_type->toBe(OfferType::Multibuy)
        ->promotion_text->toBe('od 2 ks: 13,90'.BILLA_NBSP.'Kč');
});

it('zboží na váhu uloží s cenou za kilogram', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    // Pomeranč na kusy: value je cena odhadovaného kusu (9,87 Kč), akce je 29,90 Kč/kg
    expect(billaOffer('Pomeranč'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(2990)
        ->original_price->toBe(4990)
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Gram)
        // Slanina vážená: value je cena za kg
        ->and(billaOffer('Anglická slanina'))
        ->price->toBe(19900)
        ->original_price->toBe(24900)
        ->quantity->toBe(1000.0);
});

it('akci jen z e-shopu označí a „přeškrtnutou“ cenu nižší než akční nebere jako slevu', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    expect(billaOffer('Barilla Pesto')->online_only)->toBeTrue()
        ->and(billaOffer('Campari')->online_only)->toBeFalse()
        ->and(billaOffer('Savo'))
        ->offer_type->toBe(OfferType::PromoPrice)
        ->price->toBe(10990)
        ->original_price->toBeNull();
});

it('změněný tvar odpovědi stažení zastaví', function (): void {
    Http::fake([BILLA_API_URL.'*' => Http::response(['items' => []])]);

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('chybí total nebo results');
});
