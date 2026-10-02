<?php

/**
 * Import nabídky Lidlu z kampaní na lidl.cz (ZDROJE_DAT.md, Lidl).
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Falešné odpovědi lidl.cz: úvodní stránka a kampaně podle cesty.
 */
function fakeLidl(): void
{
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return Http::response(responseFixture(match ($path) {
            '/' => 'lidl/home-2026-10-02.html',
            '/c/ctvrtecni-nabidka/a10103788' => 'lidl/ctvrtecni-nabidka-2026-10-02.html',
            '/c/1-1-zdarma/a10103790' => 'lidl/1-1-zdarma-2026-10-02.html',
            '/c/vikendova-nabidka/a10103791' => 'lidl/vikendova-nabidka-2026-10-02.html',
            '/c/vdechni-latkam-zivot/a10103311' => 'lidl/vdechni-latkam-zivot-2026-10-02.html',
        }));
    });
}

/**
 * Nabídka z databáze podle ID produktu Lidlu.
 */
function lidlOffer(string $productId): Offer
{
    return Offer::query()->where('chain', Chain::Lidl)->where('external_id', $productId)->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('stáhne kampaně z úvodní stránky a uloží jen potraviny', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertSuccessful();

    // 7 + 2 + 3 potravin; kampaň s módou nemá potraviny, zdroj z ní nevznikne
    expect(Offer::query()->count())->toBe(12)
        ->and(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and(Leaflet::query()->pluck('external_id')->sort()->values()->all())->toBe(['a10103788', 'a10103790', 'a10103791'])
        ->and(Leaflet::query()->first()?->kind)->toBe(LeafletKind::Web);

    // úvodní stránka + 4 kampaně (čtvrteční je na úvodní stránce dvakrát, stáhne se jednou)
    Http::assertSentCount(5);
});

it('vyloučenou kampaň nestáhne', function (): void {
    config(['letaky.sources.lidl.excluded_campaigns' => ['vikendova-nabidka']]);
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    expect(Leaflet::query()->where('external_id', 'a10103791')->exists())->toBeFalse();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'vikendova-nabidka'));
});

it('akci s Lidl Plus bez ceny bez aplikace uloží jako akci jen s aplikací', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    expect(lidlOffer('10054110'))
        ->name->toBe('VITASIA Mochi')
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(7990)
        ->loyalty_price->toBe(5990)
        ->loyalty_program->toBe(LoyaltyProgram::LidlPlus)
        ->variant_note->toBe('různé druhy')
        ->quantity->toBe(210.0);
});

it('akci s Lidl Plus a cenou bez aplikace uloží jako slevu s cenou s aplikací', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    expect(lidlOffer('10052485'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(2990)
        ->original_price->toBe(4490)
        ->loyalty_price->toBe(2490)
        ->package_text->toBe('125 g - balení')
        ->quantity->toBe(125.0);
});

it('„Super cena“ a „Ušetřete*“ nejsou slevy, 1+1 zdarma je akce na více kusů (R8)', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    expect(lidlOffer('10052609'))
        ->offer_type->toBe(OfferType::PromoPrice)
        ->original_price->toBeNull()
        ->promotion_text->toBe('Ušetřete* 24%')
        ->quantity->toBe(8000.0)
        ->unit->toBe(PackageUnit::Milliliter);

    expect(lidlOffer('10055978'))
        ->offer_type->toBe(OfferType::PromoPrice)
        ->promotion_text->toBe('Super cena');

    expect(lidlOffer('10056756'))
        ->offer_type->toBe(OfferType::Multibuy)
        ->price->toBe(2790)
        ->promotion_text->toBe('1+1 zdarma');
});

it('údaj jen s cenou za jednotku nepovažuje za balení', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    // „1 kg = 64,95 Kč“ — balení neuvádí; „195 g, 100 g = 13,90 Kč/PP“ — balení 195 g
    expect(lidlOffer('10054191'))->package_text->toBeNull()->quantity->toBeNull()
        ->and(lidlOffer('10007489'))->package_text->toBe('195 g')->quantity->toBe(195.0);
});

it('položce bez data dá platnost ostatních akcí kampaně', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    $tokaji = lidlOffer('10028498');
    expect($tokaji->valid_from->toDateString())->toBe('2026-10-02')
        ->and($tokaji->valid_to->toDateString())->toBe('2026-10-04');
});
