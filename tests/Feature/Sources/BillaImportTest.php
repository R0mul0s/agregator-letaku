<?php

/**
 * Import nabídky Billy — celý katalog z product-discovery API, akce vybere parser (R48, ZDROJE_DAT.md, Billa),
 * a akce letáků, které ještě nezačaly, z PDF spárované s katalogem (R89). Program pdftotext se nespouští —
 * výstup jsou fixtures skutečných stran.
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
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const BILLA_API_URL = 'https://www.billa.cz/api/product-discovery/products';

/** Stránka letáků, stránka velkého letáku od 7. 10. 2026 a jeho PDF (Publitas). */
const BILLA_LEAFLETS_URL = 'https://www.billa.cz/akcni-letaky';

const BILLA_NEXT_LEAFLET_URL = 'https://www.billa.cz/letaky-billa?tab=letaky-billa/velky-letak-nasledujici';

const BILLA_PDF_URL = 'https://view.publitas.com/64069/2700042/pdfs/4a505f87-b950-4bed-ad9f-208c63e4c139.pdf';

/** Nezlomitelná mezera v ceně v textu akce („9,93 Kč“). */
const BILLA_NBSP = "\u{00A0}";

/**
 * Falešné odpovědi Billy: dvě stránky katalogu (total = součet obou), stránka letáků, stránka
 * velkého letáku od 7. 10. a jeho PDF; pdftotext vrátí stranu 4 (česnek a petržel — v katalogu
 * z 2. 10. nejsou, takže akci z letáku nepřidají).
 */
function fakeBilla(): void
{
    Http::fake([
        BILLA_API_URL.'?page=0*' => Http::response(responseFixture('billa/products-page0-2026-10-02.json')),
        BILLA_API_URL.'?page=1*' => Http::response(responseFixture('billa/products-page1-2026-10-02.json')),
        ...billaLeafletResponses(),
    ]);
    Process::fake(['*' => Process::result(responseFixture('billa/pdf-41-2026-10-06-strana-4.html'))]);
}

/**
 * Falešné odpovědi stránky letáků, stránky letáku a PDF.
 *
 * @return array<string, mixed>
 */
function billaLeafletResponses(): array
{
    return [
        BILLA_LEAFLETS_URL => Http::response(responseFixture('billa/akcni-letaky-2026-10-06.html')),
        BILLA_NEXT_LEAFLET_URL => Http::response(responseFixture('billa/letak-velky-nasledujici-2026-10-06.html')),
        BILLA_PDF_URL => Http::response('%PDF-1.7 leták'),
    ];
}

/**
 * Falešné odpovědi pro akce z letáku: katalog s produkty ze stran 1, 22 a 23 letáku od 7. 10.
 * (jedna stránka), upravený podle dne, a výstup pdftotext těchto stran.
 *
 * @param  (callable(array<string, mixed>, CarbonImmutable): array<string, mixed>)|null  $product  Úprava produktu podle dne stažení
 */
function fakeBillaLeaflet(?callable $product = null): void
{
    Http::fake([
        BILLA_API_URL.'*' => function () use ($product) {
            $page = json_decode(responseFixture('billa/products-letak-2026-10-06.json'), true, flags: JSON_THROW_ON_ERROR);
            if ($product !== null) {
                $page['results'] = array_map(fn (array $item): array => $product($item, CarbonImmutable::now()), $page['results']);
            }

            return Http::response($page);
        },
        ...billaLeafletResponses(),
    ]);
    Process::fake(['*' => Process::result(responseFixture('billa/pdf-41-2026-10-06-strany-1-22-23.html'))]);
}

/**
 * Produkt v akci se slevou z běžné ceny, jak ho vrací API (`pt-aktion`, přeškrtnutá cena ve `standard`).
 *
 * @param  array<string, mixed>  $product
 * @return array<string, mixed>
 */
function billaOnPromotion(array $product, int $price, int $usual): array
{
    $product['price']['regular'] = ['tags' => ['pt-aktion'], 'value' => $price];
    $product['price']['standard'] = ['value' => $usual];
    $product['price']['crossed'] = $usual;

    return $product;
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
    // Fixtures mají jen pár stran letáku — práh ověřených dlaždic velkého letáku (~240 na celém letáku) snížit
    config(['letaky.sources.billa.pdf_main_min_items' => 1]);
});

it('uloží akce z obou stránek katalogu s platností akčního týdne', function (): void {
    fakeBilla();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    // PDF leták bez nové akce má prázdnou dávku se statistikou dlaždic (R129)
    $leaflet = Leaflet::query()->where('kind', LeafletKind::Web)->sole();
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

it('akce, která se stejnou cenou pokračuje do dalšího týdne, prodlouží svůj řádek (R54)', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeBilla();
    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);
    $campari = billaOffer('Campari');

    // Středa: nový akční týden, pak čtvrtek: další stažení téhož týdne
    $this->travelTo('2026-10-07 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();
    $this->travelTo('2026-10-08 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    expect(Offer::query()->where('chain', Chain::Billa)->count())->toBe(10)
        ->and(billaOffer('Campari'))
        ->id->toBe($campari->id)
        ->created_at->toDateTimeString()->toBe($campari->created_at->toDateTimeString())
        ->valid_from->toDateString()->toBe('2026-09-30')
        ->valid_to->toDateString()->toBe('2026-10-13')
        ->withdrawn_at->toBeNull();
});

it('akce se změněnou cenou v dalším týdnu je nová akce (R54)', function (): void {
    // Od středy 7. 10. stojí Campari 379,90 Kč místo 399,90 Kč
    Http::fake([
        ...billaLeafletResponses(),
        BILLA_API_URL.'*' => function (Request $request) {
            $page = responseFixture('billa/products-page'.$request['page'].'-2026-10-02.json');

            return Http::response(now()->lessThan('2026-10-07') ? $page : str_replace('"value":39990', '"value":37990', $page));
        },
    ]);
    Process::fake(['*' => Process::result(responseFixture('billa/pdf-41-2026-10-06-strana-4.html'))]);
    $this->travelTo('2026-10-06 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);
    $this->travelTo('2026-10-07 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    $campari = Offer::query()->where('chain', Chain::Billa)->where('name', 'like', 'Campari%')->orderBy('valid_from')->get();
    expect($campari)->toHaveCount(2)
        ->and($campari[0]->valid_to->toDateString())->toBe('2026-10-06')
        ->and($campari[1])
        ->price->toBe(37990)
        ->valid_from->toDateString()->toBe('2026-10-07');
});

it('akci letáku, který ještě nezačal, uloží pod kódem produktu z katalogu', function (): void {
    // Úterý 6. 10.: velký leták od 7. 10. ještě nezačal
    $this->travelTo('2026-10-06 10:00:00');
    fakeBillaLeaflet();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    $leaflet = Leaflet::query()->where('external_id', 'pdf-2700042')->sole();
    // „Olma Klasik jogurt bílý“ 150 g „-25%“ 8,90 z 11,90 — v katalogu dnes bez akce za 11,90
    expect(billaOffer('Olma Klasik'))
        ->external_id->toBe('82-234812')
        ->leaflet_id->toBe($leaflet->id)
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(890)
        ->original_price->toBe(1190)
        ->discount_percent->toBe(25)
        ->quantity->toBe(150.0)
        ->unit->toBe(PackageUnit::Gram)
        ->valid_from->toDateString()->toBe('2026-10-07')
        ->valid_to->toDateString()->toBe('2026-10-13')
        ->source_url->toBe('https://www.billa.cz/produkt/olma-klasik-original-bily-jogurt-150g-82234812')
        ->image_url->toStartWith('https://images.cdn.europe-west1.gcp.commercetools.com/')->toEndWith('-medium.jpg')
        ->and($leaflet)
        ->kind->toBe(LeafletKind::Leaflet)
        ->title->toBe('Velký leták')
        // „Pribináček 125 g více druhů“ — každý druh je v katalogu samostatný produkt
        ->and(Offer::query()->where('chain', Chain::Billa)->where('name', 'like', 'Pribináček%')->where('offer_type', OfferType::Multibuy)->count())->toBe(2)
        // „Kinder Maxi King“ 69,90 v letáku, v katalogu 71,90 — nespárovaná dlaždice se neuloží
        ->and(Offer::query()->where('name', 'like', 'Kinder%')->exists())->toBeFalse();

    // Jen leták, který ještě nezačal: speciál prodejny, katalog a leták od 30. 9. se nestahují
    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'special-') || str_contains($request->url(), 'katalog-'));
});

it('cenu s BILLA Klubem z letáku uloží vedle běžné ceny', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeBillaLeaflet();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);

    // „Gervais“ 36,90 s Klubem, „běžná cena 49,90“; dnes v API s Klubem 39,90 — jiná akce
    expect(Offer::query()->where('name', 'Gervais Original 150g')->where('valid_from', '2026-10-07')->sole())
        ->external_id->toBe('82-364770')
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(4990)
        ->loyalty_price->toBe(3690)
        ->loyalty_program->toBe(LoyaltyProgram::BillaKlub);
});

it('akci z letáku po začátku převezme akce z API se stejným klíčem — řádek se jen aktualizuje', function (): void {
    // Od středy 7. 10. má Olma v API akci 8,90 z 11,90
    fakeBillaLeaflet(fn (array $product, CarbonImmutable $now): array => $product['sku'] === '82-234812' && $now->greaterThanOrEqualTo('2026-10-07')
        ? billaOnPromotion($product, 890, 1190) : $product);
    $this->travelTo('2026-10-06 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);
    $olma = billaOffer('Olma Klasik');

    $this->travelTo('2026-10-07 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    expect(billaOffer('Olma Klasik'))
        ->id->toBe($olma->id)
        ->created_at->toDateTimeString()->toBe($olma->created_at->toDateTimeString())
        ->valid_from->toDateString()->toBe('2026-10-07')
        ->valid_to->toDateString()->toBe('2026-10-13')
        ->withdrawn_at->toBeNull()
        ->leaflet_id->toBe(Leaflet::query()->where('external_id', 'web-2026-10-07')->value('id'));
});

it('akci s jinou platností než akční týden uloží pod předběžným ID a API ji převezme', function (): void {
    // „SUPER STŘEDA 7. 10.“: Božkov 98,90 jen ve středu; dnes v API 119,90
    fakeBillaLeaflet(fn (array $product, CarbonImmutable $now): array => $product['sku'] === '82-354986' && $now->greaterThanOrEqualTo('2026-10-07')
        ? billaOnPromotion($product, 9890, 14990) : $product);
    $this->travelTo('2026-10-06 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']]);
    $provisional = Offer::query()->where('external_id', 'letak-82-354986')->sole();

    $this->travelTo('2026-10-07 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    expect($provisional)
        ->valid_from->toDateString()->toBe('2026-10-07')
        ->valid_to->toDateString()->toBe('2026-10-07')
        ->price->toBe(9890)
        ->and($provisional->fresh())
        ->external_id->toBe('82-354986')
        ->valid_from->toDateString()->toBe('2026-10-07')
        ->valid_to->toDateString()->toBe('2026-10-13')
        ->created_at->toDateTimeString()->toBe($provisional->created_at->toDateTimeString())
        ->and(Offer::query()->where('name', 'like', 'Božkov%')->where('valid_from', '>=', '2026-10-07')->count())->toBe(1);
});

it('akci, která pokračuje dnešní akcí se stejnou cenou, z letáku nezaloží (R54)', function (): void {
    // Olma už dnes stojí 8,90 — příští týden pokračuje; řádek prodlouží až API ve středu
    fakeBillaLeaflet(fn (array $product): array => $product['sku'] === '82-234812' ? billaOnPromotion($product, 890, 1190) : $product);
    $this->travelTo('2026-10-06 10:00:00');

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    expect(billaOffer('Olma Klasik'))
        ->valid_from->toDateString()->toBe('2026-09-30')
        ->valid_to->toDateString()->toBe('2026-10-06');

    $this->travelTo('2026-10-07 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertSuccessful();

    expect(billaOffer('Olma Klasik'))
        ->valid_from->toDateString()->toBe('2026-09-30')
        ->valid_to->toDateString()->toBe('2026-10-13');
});

it('chyba převodu PDF ukončí stažení Billy chybou', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeBillaLeaflet();
    Process::fake(['*' => Process::result(errorOutput: 'Syntax Error: Couldn\'t read xref table', exitCode: 1)]);

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('pdftotext')
        ->and(Offer::query()->where('chain', Chain::Billa)->exists())->toBeFalse();
});

it('nedostupné PDF ukončí stažení Billy chybou', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    Http::fake([BILLA_PDF_URL => Http::response('', 500)]);
    fakeBillaLeaflet();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed)
        ->and(Offer::query()->where('chain', Chain::Billa)->exists())->toBeFalse();
});

it('velký leták s podezřele málo ověřenými dlaždicemi je chyba', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    config(['letaky.sources.billa.pdf_main_min_items' => 100]);
    fakeBillaLeaflet();

    $this->artisan('letaky:import-offers', ['chain' => ['billa']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('méně než 100');
});
