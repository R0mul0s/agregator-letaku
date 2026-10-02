<?php

/**
 * Import nabídky Globusu — katalog akcí a položky letáku z REST API webu (R46, ZDROJE_DAT.md, Globus).
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

const GLOBUS_API_URL = 'https://www.globus.cz/api/v1/gsoa/actionOffers/houses/4005/';

/**
 * Falešné odpovědi Globusu: dvě stránky katalogu akcí a jedna stránka položek letáku.
 */
function fakeGlobus(): void
{
    Http::fake([
        GLOBUS_API_URL.'actionProductsCatalog?page=0*' => Http::response(responseFixture('globus/catalog-page0-2026-10-02.json')),
        GLOBUS_API_URL.'actionProductsCatalog?page=1*' => Http::response(responseFixture('globus/catalog-page1-2026-10-02.json')),
        GLOBUS_API_URL.'actionProducts?page=0*' => Http::response(responseFixture('globus/action-products-2026-10-02.json')),
    ]);
}

/**
 * Nabídka z databáze podle začátku názvu.
 */
function globusOffer(string $name): Offer
{
    return Offer::query()->where('chain', Chain::Globus)->where('name', 'like', $name.'%')->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží akce z obou stránek katalogu do průběžného zdroje a vynechá ceny, které nejsou akce, a oblečení', function (): void {
    fakeGlobus();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $leaflet = Leaflet::query()->sole();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflet->kind)->toBe(LeafletKind::Web)
        ->and($leaflet->external_id)->toBe('akcni-nabidka')
        ->and($leaflet->valid_from)->toBeNull()
        // 12 položek katalogu; špekáčky z pultu (VKP0) a doprodej (ZTP0) nejsou akce, svetr je oblečení
        ->and(Offer::query()->where('chain', Chain::Globus)->count())->toBe(9)
        ->and(Offer::query()->where('name', 'like', 'Špekáčky%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', 'Hamánek%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', 'Blue seven%')->exists())->toBeFalse();
});

it('slevu uloží s původní cenou a zboží na váhu za kilogram', function (): void {
    fakeGlobus();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']]);

    $schnitzel = globusOffer('Kuřecí stehenní řízek');
    expect($schnitzel)
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(8990)
        ->original_price->toBe(20390)
        ->discount_percent->toBe(55)
        ->package_text->toBe('1 kg')
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Gram)
        ->description->toBe('chlazené')
        ->and($schnitzel->valid_from->toDateString())->toBe('2026-09-30')
        ->and($schnitzel->valid_to->toDateString())->toBe('2026-10-06')
        // Eidam je na druhé stránce katalogu
        ->and(globusOffer('Sýr Eidam 30%'))->price->toBe(11900);
});

it('cenu s aplikací Můj Globus uloží vedle běžné, jen když je nižší', function (): void {
    fakeGlobus();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']]);

    expect(globusOffer('Milka Čokoláda Bubbly'))
        ->offer_type->toBe(OfferType::PromoPrice)
        ->price->toBe(2990)
        ->original_price->toBeNull()
        ->loyalty_price->toBe(2690)
        ->loyalty_program->toBe(LoyaltyProgram::MujGlobus)
        ->brand->toBe('Milka')
        ->quantity->toBe(97.0)
        // Cena s aplikací stejná jako bez ní není cena s kartou
        ->and(globusOffer('Milko Tolštejn'))
        ->loyalty_price->toBeNull()
        ->loyalty_program->toBeNull();
});

it('popis „různé druhy“ vezme z položky letáku podle EAN, ne reklamní text katalogu', function (): void {
    fakeGlobus();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']]);

    expect(globusOffer('Coca-Cola'))
        ->description->toBe('různé druhy')
        ->variant_note->toBe('různé druhy')
        ->quantity->toBe(1500.0)
        ->unit->toBe(PackageUnit::Milliliter)
        ->and(globusOffer('Milka Čokoláda Bubbly')->description)->toBe('různé druhy')
        // „40 dávek“ není balení, ze kterého by šla cena za jednotku
        ->and(globusOffer('ARIEL KAPSLE'))->quantity->toBeNull();
});

it('změněný tvar odpovědi katalogu stažení zastaví', function (): void {
    Http::fake([
        GLOBUS_API_URL.'actionProducts?page=0*' => Http::response(responseFixture('globus/action-products-2026-10-02.json')),
        GLOBUS_API_URL.'actionProductsCatalog*' => Http::response(['items' => []]),
    ]);

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('katalog akcí nemá products')
        ->and(Offer::query()->count())->toBe(0);
});
