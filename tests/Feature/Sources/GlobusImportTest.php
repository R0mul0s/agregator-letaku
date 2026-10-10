<?php

/**
 * Import nabídky Globusu — katalog akcí a položky letáku z REST API webu (R46, ZDROJE_DAT.md, Globus)
 * a akce letáků, které ještě nezačaly, z PDF (R88). Program pdftotext se nespouští — výstup jsou
 * fixtures skutečných stran.
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
use Illuminate\Support\Facades\Process;

const GLOBUS_API_URL = 'https://www.globus.cz/api/v1/gsoa/actionOffers/houses/4005/';

const GLOBUS_PDF_URL = 'https://gapi.globus.cz/OnlineAsset/3/asset?assetID=';

/** PDF hlavního letáku od 7. 10. 2026 (41_26_L1) a letáku od 30. 9. (40_26_L1). */
const GLOBUS_PDF_41 = GLOBUS_PDF_URL.'e06b9817-3ce1-49dc-9541-0455cb28b5f2';

const GLOBUS_PDF_40 = GLOBUS_PDF_URL.'95a2ab8a-f9d2-4115-85cf-353723570112';

/**
 * Falešné odpovědi Globusu: dvě stránky katalogu akcí, jedna stránka položek letáku, seznam letáků
 * (jen vybrané podle názvu) a PDF.
 *
 * @param  list<string>|null  $leaflets  Názvy letáků ze seznamu (`actionOfferName`); null = všechny
 * @param  bool  $catalogLater  true = první stažení dostane katalog bez akcí letáku (jen druhou stránku
 *                              fixture), až další celý katalog — leták mezitím začal platit
 */
function fakeGlobus(?array $leaflets = ['40_26_L1', '40_26_K2_Úklid'], bool $catalogLater = false): void
{
    $list = json_decode(responseFixture('globus/action-offers-2026-10-06.json'), true, flags: JSON_THROW_ON_ERROR);
    $list['actionOffers'] = array_values(array_filter($list['actionOffers'], fn (array $leaflet): bool => $leaflets === null || in_array($leaflet['actionOfferName'], $leaflets, true)));

    Http::fake([
        GLOBUS_API_URL.'actionProductsCatalog?page=0*' => $catalogLater
            ? Http::sequence()->push(responseFixture('globus/catalog-page1-2026-10-02.json'))->push(responseFixture('globus/catalog-page0-2026-10-02.json'))
            : Http::response(responseFixture('globus/catalog-page0-2026-10-02.json')),
        GLOBUS_API_URL.'actionProductsCatalog?page=1*' => Http::response(responseFixture('globus/catalog-page1-2026-10-02.json')),
        GLOBUS_API_URL.'actionProducts?page=0*' => Http::response(responseFixture('globus/action-products-2026-10-02.json')),
        GLOBUS_API_URL.'actionOffers?page=0*' => Http::response($list),
        GLOBUS_PDF_URL.'*' => Http::response('%PDF-1.7 leták'),
    ]);
}

/**
 * Výstup pdftotext pro stažené PDF.
 */
function fakeGlobusPdfText(string $fixture = 'globus/pdf-41-2026-10-06-strany-1-3.html'): void
{
    Process::fake(['*' => Process::result(responseFixture($fixture))]);
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
    // Fixtures mají jen pár stran letáku — práh ověřených akcí hlavního letáku (~200 na celém letáku) snížit
    config(['letaky.sources.globus.pdf_main_min_offers' => 5]);
    // Jen výchozí hypermarket — akce po hypermarketech (R131) zkouší GlobusStoresTest
    config(['letaky.sources.globus.price_zones' => [4005 => [4005]]]);
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

it('akce letáku, který ještě nezačal, uloží z PDF jako samostatný zdroj', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeGlobus(leaflets: null);
    fakeGlobusPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $leaflet = Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole();
    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Succeeded)
        // 9 akcí z API a 16 ověřených dlaždic stran 1–3 letáku od 7. 10.
        ->offers_count->toBe(25)
        ->and($leaflet)
        ->external_id->toBe('6abe9f02194391fb3f605e47')
        ->title->toBe('41_26_L1')
        ->and($leaflet->valid_from?->toDateString())->toBe('2026-10-07')
        ->and($leaflet->valid_to?->toDateString())->toBe('2026-10-13')
        ->and(globusOffer('Olma Florian'))
        ->leaflet_id->toBe($leaflet->id)
        ->price->toBe(990)
        ->loyalty_price->toBe(890)
        ->loyalty_program->toBe(LoyaltyProgram::MujGlobus)
        ->original_price->toBe(1790)
        ->source_url->toBe('https://www.globus.cz/globus/hypermarket/akcni-nabidka')
        ->and(globusOffer('Olma Florian')->valid_from->toDateString())->toBe('2026-10-07')
        // Neověřitelná dlaždice („Mléko čerstvé / 1 l“ bez ceny za jednotku) se neuloží
        ->and(Offer::query()->where('name', 'like', '%Mléko čerstvé%')->exists())->toBeFalse();

    // PDF jen hlavního letáku od 7. 10.: tematický leták je jeho část, katalog elektra se nestahuje
    // a leták od 30. 9. i katalog úklidu už platí (akce vrací API)
    Http::assertSent(fn ($request): bool => $request->url() === GLOBUS_PDF_41);
    Http::assertSentCount(5);
});

it('akce z PDF, které už platí (delší platnost z minulého letáku), nechá na API', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeGlobus(leaflets: ['41_26_L1']);
    // Strana 41: „od 30. 9. do 26. 10. 2026“
    fakeGlobusPdfText('globus/pdf-41-2026-10-06-strana-41.html');

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    expect(ScrapeRun::query()->sole()->offers_count)->toBe(9)
        ->and(Offer::query()->where('name', 'After Eight')->exists())->toBeFalse();
});

it('akci z PDF po začátku platnosti převezme akce z API — stejný řádek, žádná nová akce', function (): void {
    // 29. 9.: leták od 30. 9. ještě nezačal, API akce z něj nemá
    $this->travelTo('2026-09-29 10:00:00');
    fakeGlobus(leaflets: ['40_26_L1'], catalogLater: true);
    fakeGlobusPdfText('globus/pdf-40-2026-10-06-strany-1-2.html');
    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $fromLeaflet = Offer::query()->where('name', 'Milka Čokoláda')->sole();
    expect($fromLeaflet->external_id)->toStartWith('letak-')
        ->and($fromLeaflet->loyalty_price)->toBe(2690);

    // 30. 9.: leták platí, API vrací Milku pod vlastním ID a s názvem položky letáku „Milka Čokoláda“
    $this->travelTo('2026-09-30 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $milka = Offer::query()->where('chain', Chain::Globus)->where('name', 'like', 'Milka%')->sole();
    expect($milka)
        ->id->toBe($fromLeaflet->id)
        ->external_id->toBe('02391158001')
        ->name->toBe('Milka Čokoláda Bubbly kokosová 97g')
        ->withdrawn_at->toBeNull()
        // Zveřejněná 29. 9. — souhrn ani centrum upozornění ji neohlásí znovu jako novou (R74, R76)
        ->and($milka->created_at?->toDateString())->toBe('2026-09-29')
        // Pizza: platnost z API (od 29. 9.) nahradila platnost letáku (od 30. 9.)
        ->and(Offer::query()->where('chain', Chain::Globus)->where('name', 'like', '%Ristorante%')->count())->toBe(1)
        ->and(globusOffer('Dr.Oetker Ristorante')->valid_from->toDateString())->toBe('2026-09-29');
});

it('akci z PDF, kterou už vrací API, nezdvojí', function (): void {
    $this->travelTo('2026-09-29 10:00:00');
    fakeGlobus(leaflets: ['40_26_L1']);
    fakeGlobusPdfText('globus/pdf-40-2026-10-06-strany-1-2.html');

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    expect(Offer::query()->where('chain', Chain::Globus)->where('name', 'like', 'Milka%')->sole()->external_id)->toBe('02391158001')
        ->and(Offer::query()->where('name', 'Magnesia')->exists())->toBeTrue();
});

it('chyba převodu PDF stažení Globusu zastaví', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeGlobus(leaflets: null);
    Process::fake(['*' => Process::result(errorOutput: 'Syntax Error: Couldn\'t read xref table', exitCode: 1)]);

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('pdftotext')
        ->and(Offer::query()->count())->toBe(0);
});

it('hlavní leták s podezřele málo ověřenými akcemi stažení zastaví', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    config(['letaky.sources.globus.pdf_main_min_offers' => 50]);
    fakeGlobus(leaflets: null);
    fakeGlobusPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('ověřených akcí')
        ->and(Offer::query()->count())->toBe(0);
});
