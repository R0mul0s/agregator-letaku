<?php

/**
 * Import nabídky Penny — API a vektorová vrstva letáku bez LLM (R23, ZDROJE_DAT.md, Penny).
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
use App\Models\LeafletStat;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

const PENNY_LEAFLET_URL = 'https://files.rewe.co.at/PennyIntLeaflet/CZ/30_09_2026/';

/**
 * Falešné odpovědi Penny: API, stránka letáků, index letáku a stránky 1–3 (skutečné strany 1, 4
 * a 30); stránka 4 je jen obrázek bez textové vrstvy (404).
 */
function fakePenny(): void
{
    $pages = PENNY_LEAFLET_URL.'files/assets/common/page-vectorlayers/';

    Http::fake([
        'https://www.penny.cz/api/product-discovery/products*' => Http::response(responseFixture('penny/products-2026-10-02.json')),
        'https://www.penny.cz/nabidky/letaky' => Http::response(responseFixture('penny/letaky-2026-10-02.html')),
        $pages.'0001.svg' => Http::response(responseFixture('penny/page-0001-2026-10-02.svg')),
        $pages.'0002.svg' => Http::response(responseFixture('penny/page-0004-2026-10-02.svg')),
        $pages.'0003.svg' => Http::response(responseFixture('penny/page-0030-2026-10-02.svg')),
        $pages.'0004.svg' => Http::response('Not found', 404),
        PENNY_LEAFLET_URL => Http::response(responseFixture('penny/leaflet-index-2026-10-02.html')),
    ]);
}

/**
 * Nabídka z databáze podle začátku názvu.
 */
function pennyOffer(string $name): Offer
{
    return Offer::query()->where('chain', Chain::Penny)->where('name', 'like', $name.'%')->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží akce z API i z letáku a stránku letáku bez textu přeskočí', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']])->assertSuccessful();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and(Leaflet::query()->where('kind', LeafletKind::Web)->sole()->external_id)->toBe('web-2026-09-30')
        ->and(Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole()->external_id)->toBe('30_09_2026')
        ->and(Offer::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Web))->count())->toBe(33)
        // Fotky z API v menší variantě CDN (R108)
        ->and(Offer::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Web))->where('image_url', 'like', '%-medium.jpg')->count())->toBe(33)
        // Strany 1, 4 a 30 dají 44 dlaždic (R26, R85, R107 — kuřecí řízky „cena za 1 kg“); 17 z nich nese i API
        ->and(Offer::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Leaflet))->count())->toBe(27);
});

it('zapíše statistiku letáků pro přehled kvality dat: akce a nalezené a ověřené ceny z SVG (R129)', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']])->assertSuccessful();

    $leaflet = LeafletStat::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Leaflet))->sole();
    $web = LeafletStat::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Web))->sole();
    expect($leaflet->offers_count)->toBe(27)
        // 44 ověřených dlaždic ze stran 1, 4 a 30 (před vyřazením akcí, které nese API)
        ->and($leaflet->tiles_verified)->toBe(44)
        ->and($leaflet->tile_candidates)->toBeGreaterThanOrEqual(44)
        ->and($web->offers_count)->toBe(33)
        ->and($web->tile_candidates)->toBeNull();
});

it('najde i leták se složkou s příponou verze (07_10_2026_tl2)', function (): void {
    $folder = '07_10_2026_tl2';
    $leafletUrl = str_replace('30_09_2026', $folder, PENNY_LEAFLET_URL);
    $pages = $leafletUrl.'files/assets/common/page-vectorlayers/';

    Http::fake([
        'https://www.penny.cz/api/product-discovery/products*' => Http::response(responseFixture('penny/products-2026-10-02.json')),
        'https://www.penny.cz/nabidky/letaky' => Http::response(str_replace('30_09_2026', $folder, responseFixture('penny/letaky-2026-10-02.html'))),
        $pages.'0001.svg' => Http::response(responseFixture('penny/page-0001-2026-10-02.svg')),
        $pages.'0002.svg' => Http::response(responseFixture('penny/page-0004-2026-10-02.svg')),
        $pages.'0003.svg' => Http::response(responseFixture('penny/page-0030-2026-10-02.svg')),
        $pages.'0004.svg' => Http::response('Not found', 404),
        $leafletUrl => Http::response(responseFixture('penny/leaflet-index-2026-10-02.html')),
    ]);

    $this->artisan('letaky:import-offers', ['chain' => ['penny']])->assertSuccessful();

    expect(Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole()->external_id)->toBe($folder)
        ->and(Offer::query()->whereHas('leaflet', fn ($q) => $q->where('kind', LeafletKind::Leaflet))->count())->toBe(27);
});

it('uloží text stránek letáku s textovou vrstvou pro zmínky bez ceny (R27)', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    $pages = Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole()->pages()->orderBy('number')->get();
    expect($pages->pluck('number')->all())->toBe([1, 2, 3])
        ->and($pages[2]->page_url)->toBe(PENNY_LEAFLET_URL.'3/')
        ->and($pages[2]->image_url)->toBeNull()
        ->and($pages[2]->text)->toContain('MLÉKO* instantní, polotučné');
});

it('stránku, kterou leták při dalším stažení nemá, smaže — zmínky z ní by zůstaly (R113)', function (): void {
    fakePenny();
    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    // Strana 3 přestala mít textovou vrstvu — nové podvržené odpovědi (první vyhrává)
    Http::swap(new HttpFactory);
    Http::fake([PENNY_LEAFLET_URL.'files/assets/common/page-vectorlayers/0003.svg' => Http::response('Not found', 404)]);
    fakePenny();
    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    expect(Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole()->pages()->orderBy('number')->pluck('number')->all())->toBe([1, 2]);
});

it('cenu s PENNY kartou z API uloží vedle běžné ceny', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    expect(pennyOffer('Grissini San Fabio'))
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(3490)
        ->loyalty_price->toBe(2490)
        ->loyalty_program->toBe(LoyaltyProgram::PennyKarta)
        ->quantity->toBe(200.0);
});

it('položku letáku, kterou nese API, neuloží podruhé', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    // Vejce jsou v API i na první straně letáku
    expect(Offer::query()->where('chain', Chain::Penny)->where('name', 'like', '%vejce%')->count())->toBe(1)
        ->and(pennyOffer('Vejce čerstvá M'))
        ->price->toBe(2490)
        ->original_price->toBe(4990);
});

it('položku letáku na příští týden uloží, i když dnes API nese stejné zboží za stejnou cenu (R113)', function (): void {
    $this->travelTo('2026-09-28 10:00:00');
    // API nese akce tohoto týdne, leták platí od 30. 9. (první podvržená odpověď vyhrává)
    Http::fake(['https://www.penny.cz/api/product-discovery/products*' => Http::response(str_replace(
        ['"validityStart":"2026-09-30"', '"validityEnd":"2026-10-06"'],
        ['"validityStart":"2026-09-23"', '"validityEnd":"2026-09-29"'],
        responseFixture('penny/products-2026-10-02.json'),
    ))]);
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    expect(Offer::query()->where('chain', Chain::Penny)->where('name', 'like', '%vejce%')->orderBy('valid_from')->pluck('valid_from')->map->toDateString()->all())
        ->toBe(['2026-09-23', '2026-09-30']);
});

it('z letáku přečte název, balení, cenu a přeškrtnutou cenu ověřené cenou za jednotku', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    // „ODTUČNĚNÝ TVAROH KARLOVA KORUNA“, 250 g, 12,90 Kč (100 g 5,16 Kč), přeškrtnutá 17,90 Kč
    expect(pennyOffer('ODTUČNĚNÝ TVAROH'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(1290)
        ->original_price->toBe(1790)
        ->quantity->toBe(250.0)
        ->unit->toBe(PackageUnit::Gram);
});

it('akce ve víkendovém oddílu letáku má jeho platnost', function (): void {
    fakePenny();

    $this->artisan('letaky:import-offers', ['chain' => ['penny']]);

    // Trvanlivé mléko Madeta 9,90 Kč jen v letáku a jen pá–ne; balení 1 l bez ceny za jednotku
    $milk = pennyOffer('TRVANLIVÉ JIHOČESKÉ MLÉKO');
    expect($milk)
        ->price->toBe(990)
        ->offer_type->toBe(OfferType::PromoPrice)
        ->quantity->toBe(1000.0)
        ->and($milk->valid_from->toDateString())->toBe('2026-10-02')
        ->and($milk->valid_to->toDateString())->toBe('2026-10-04');
});

it('bez odkazu na leták stažení selže', function (): void {
    Http::fake([
        'https://www.penny.cz/api/product-discovery/products*' => Http::response(responseFixture('penny/products-2026-10-02.json')),
        'https://www.penny.cz/nabidky/letaky' => Http::response('<html><body>Letáky připravujeme</body></html>'),
    ]);

    $this->artisan('letaky:import-offers', ['chain' => ['penny']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('neodkazuje na žádný leták')
        ->and(Offer::query()->count())->toBe(0);
});
