<?php

/**
 * Akce Globusu po hypermarketech (R131): katalogy všech hypermarketů cenových pásem sloučené po
 * položce a ceně, akce, která neplatí všude nebo má jinou cenu, s prodejnami; PDF budoucích letáků
 * za každé cenové pásmo. Fixtures jsou zkrácené katalogy tří hypermarketů z 10. 10. 2026
 * (Čakovice a Štěrboholy v pražském pásmu, Ostrava v ostatním).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\ScrapeStatus;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const GLOBUS_HOUSES_URL = 'https://www.globus.cz/api/v1/gsoa/actionOffers/houses/';

/**
 * Falešné odpovědi Globusu pro hypermarkety 4005, 4026 a 4007: katalog každého z vlastní fixture,
 * položky letáku a seznam letáků (vybrané podle názvu) stejné pro všechny.
 *
 * @param  list<string>  $leaflets  Názvy letáků ze seznamu (`actionOfferName`)
 * @param  array<string, mixed>  $overrides  Vzor adresy => odpověď místo výchozí
 */
function fakeGlobusHouses(array $leaflets = [], array $overrides = []): void
{
    $list = json_decode(responseFixture('globus/action-offers-2026-10-06.json'), true, flags: JSON_THROW_ON_ERROR);
    $list['actionOffers'] = array_values(array_filter($list['actionOffers'], fn (array $leaflet): bool => in_array($leaflet['actionOfferName'], $leaflets, true)));

    $catalogs = [];
    foreach (['4005', '4026', '4007'] as $house) {
        $catalogs[GLOBUS_HOUSES_URL."{$house}/actionProductsCatalog*"] = Http::response(responseFixture("globus/houses-2026-10-10/catalog-{$house}.json"));
    }

    // Náhrada přepíše výchozí odpověď stejného vzoru
    Http::fake([
        ...array_replace($catalogs, $overrides),
        GLOBUS_HOUSES_URL.'*/actionProducts?*' => Http::response(responseFixture('globus/action-products-2026-10-02.json')),
        GLOBUS_HOUSES_URL.'*/actionOffers?*' => Http::response($list),
        'https://gapi.globus.cz/*' => Http::response('%PDF-1.7 leták'),
    ]);
}

/**
 * Kódy prodejen, ve kterých akce platí; prázdné = všude.
 *
 * @return list<string>
 */
function globusStoreCodes(Offer $offer): array
{
    return $offer->stores()->orderBy('store_code')->pluck('store_code')->all();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-10 10:00:00');
    config(['letaky.sources.globus.price_zones' => [4005 => [4005, 4026], 4007 => [4007]]]);
    // Fixtures mají jen pár stran letáku — práh ověřených akcí hlavního letáku snížit
    config(['letaky.sources.globus.pdf_main_min_offers' => 1]);
});

it('akci se stejnou cenou ve všech hypermarketech uloží jednou, všude a od nejdřívějšího začátku', function (): void {
    fakeGlobusHouses();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    // Štěrboholy mají Pilsner od 6. 10., ostatní od 7. 10.
    $pilsner = Offer::query()->where('name', 'like', 'Pilsner Urquell%')->sole();
    expect($pilsner)
        ->external_id->toBe('00088249001')
        ->price->toBe(2590)
        ->and($pilsner->valid_from->toDateString())->toBe('2026-10-06')
        ->and($pilsner->valid_to->toDateString())->toBe('2026-10-13')
        ->and(globusStoreCodes($pilsner))->toBe([]);
});

it('jinou cenu v části hypermarketů uloží jako samostatnou akci jen pro ně', function (): void {
    fakeGlobusHouses();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    // Praha 35,90, Ostrava 32,90 — ID bez přípony má cena výchozího hypermarketu (Čakovice)
    $prague = Offer::query()->where('external_id', '00689137004')->sole();
    $ostrava = Offer::query()->where('external_id', '00689137004-3290-5190-0')->sole();
    expect($prague->price)->toBe(3590)
        ->and(globusStoreCodes($prague))->toBe(['4005', '4026'])
        ->and($ostrava->price)->toBe(3290)
        ->and($ostrava->original_price)->toBe(5190)
        ->and(globusStoreCodes($ostrava))->toBe(['4007']);
});

it('akci, která v některém hypermarketu není, uloží jen pro hypermarkety, kde platí', function (): void {
    fakeGlobusHouses();

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $kettle = Offer::query()->where('name', 'like', 'Rohnson Varná konvice%')->sole();
    // Místní akce jen v Ostravě — ve výchozím hypermarketu není, ID zůstane bez přípony
    $mustang = Offer::query()->where('name', 'like', 'Ostravar Mustang%')->sole();
    expect(globusStoreCodes($kettle))->toBe(['4005', '4007'])
        ->and($mustang->external_id)->toBe('02198012001')
        ->and(globusStoreCodes($mustang))->toBe(['4007'])
        ->and(ScrapeRun::query()->sole()->offers_count)->toBe(5);
});

it('chyba katalogu jednoho hypermarketu stažení Globusu zastaví', function (): void {
    config(['letaky.http.retries' => 1]);
    fakeGlobusHouses(overrides: [GLOBUS_HOUSES_URL.'4026/actionProductsCatalog*' => Http::response('', 500)]);

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed)
        ->and(Offer::query()->count())->toBe(0);
});

it('akce z PDF budoucího letáku platí v hypermarketech cenového pásma, jehož PDF je má', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeGlobusHouses(leaflets: ['41_26_L1']);
    // Pražské pásmo dostane strany 1–3, ostravské stranu 16 — strany se liší zbožím
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(responseFixture('globus/pdf-41-2026-10-06-strany-1-3.html')))
        ->push(Process::result(responseFixture('globus/pdf-41-2026-10-06-strana-16.html')))]);

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    $leafletOffers = Offer::query()->whereHas('leaflet', fn ($query) => $query->where('kind', LeafletKind::Leaflet));
    expect(globusStoreCodes((clone $leafletOffers)->where('name', 'Olma Florian')->sole()))->toBe(['4005', '4026'])
        ->and(globusStoreCodes((clone $leafletOffers)->where('name', 'Veto Vegi Steak')->sole()))->toBe(['4007']);
    // Seznam letáků a PDF od zástupce každého pásma
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), GLOBUS_HOUSES_URL.'4007/actionOffers'));
    Http::assertSentCount(3 + 2 + 2 + 2);
});

it('akci, kterou mají PDF všech cenových pásem, nechá platit všude', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    fakeGlobusHouses(leaflets: ['41_26_L1']);
    Process::fake(['*' => Process::result(responseFixture('globus/pdf-41-2026-10-06-strany-1-3.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['globus']])->assertSuccessful();

    expect(globusStoreCodes(Offer::query()->where('name', 'Olma Florian')->sole()))->toBe([]);
});

it('hypermarkety cenových pásem jsou prodejny Globusu k výběru v Mých obchodech', function (): void {
    // Pásma z konfigurace, ne z beforeEach
    $zones = (require config_path('letaky.php'))['sources']['globus']['price_zones'];
    $codes = array_map(strval(...), array_merge(...array_values($zones)));
    sort($codes);

    $stores = array_column(app(ChainCatalog::class)->stores(Chain::Globus), 'code');
    sort($stores);

    expect($stores)->toBe($codes)
        ->and(Store::query()->where('chain', Chain::Globus)->where('code', '4005')->sole()->name)->toBe('Praha-Čakovice');
});
