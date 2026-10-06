<?php

/**
 * Import nabídky Lidlu z kampaní na lidl.cz a z PDF potravinového letáku (ZDROJE_DAT.md, Lidl).
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
use Illuminate\Support\Facades\Process;

/** Výstup pdftotext stran 1–3 letáku od 8. 10. 2026 (máslo, ořechy, sekt s Lidl Plus, vejce bez ceny za jednotku). */
const LIDL_LEAFLET_PAGES = 'lidl/leaflet-8-10-pages-1-3-2026-10-06.html';

/** Host PDF letáků (`pdfUrl` z API letáků). */
const LIDL_PDF_HOST = 'assets.leaflets.schwarz';

/**
 * Falešné odpovědi lidl.cz: úvodní stránka, kampaně podle cesty, stránka letáků, API letáků
 * a PDF letáku; pdftotext vrátí výstup skutečných stran letáku.
 *
 * @param  bool  $withoutTiles  Kampaně bez dlaždic akcí — jako když Lidl změní HTML (R54)
 * @param  string  $leafletPages  Fixture výstupu `pdftotext -bbox-layout`
 * @param  string  $thursdayCampaign  Fixture čtvrteční kampaně (6. 10. = akce od 8. 10. jako v letáku)
 * @param  int  $pdfStatus  HTTP status odpovědi s PDF letáku
 */
function fakeLidl(bool $withoutTiles = false, string $leafletPages = LIDL_LEAFLET_PAGES, string $thursdayCampaign = 'lidl/ctvrtecni-nabidka-2026-10-02.html', int $pdfStatus = 200): void
{
    Process::fake(['*' => Process::result(responseFixture($leafletPages))]);

    Http::fake(function (Request $request) use ($withoutTiles, $thursdayCampaign, $pdfStatus) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/v4/flyer') {
            return Http::response(responseFixture('lidl/flyer-'.$request['flyer_identifier'].'-2026-10-02.json'));
        }
        if (parse_url($request->url(), PHP_URL_HOST) === LIDL_PDF_HOST) {
            return Http::response($pdfStatus === 200 ? '%PDF-1.6 leták' : '', $pdfStatus);
        }

        $html = responseFixture(match ($path) {
            '/c/akcni-letak/s10008644' => 'lidl/letaky-2026-10-02.html',
            '/' => 'lidl/home-2026-10-02.html',
            '/c/ctvrtecni-nabidka/a10103788' => $thursdayCampaign,
            '/c/1-1-zdarma/a10103790' => 'lidl/1-1-zdarma-2026-10-02.html',
            '/c/vikendova-nabidka/a10103791' => 'lidl/vikendova-nabidka-2026-10-02.html',
            '/c/vdechni-latkam-zivot/a10103311' => 'lidl/vdechni-latkam-zivot-2026-10-02.html',
        });

        return Http::response($withoutTiles ? str_replace('data-grid-data', 'data-changed', $html) : $html);
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
    $webOffers = Offer::query()->whereHas('leaflet', fn ($leaflet) => $leaflet->where('kind', LeafletKind::Web));
    expect($webOffers->count())->toBe(12)
        ->and(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and(Leaflet::query()->where('kind', LeafletKind::Web)->pluck('external_id')->sort()->values()->all())->toBe(['a10103788', 'a10103790', 'a10103791']);

    // úvodní stránka + 4 kampaně (čtvrteční je na úvodní stránce dvakrát, stáhne se jednou)
    // + stránka letáků + 1 potravinový leták + jeho PDF
    Http::assertSentCount(8);
});

it('z PDF letáku uloží dlaždice, které ověří cena za jednotku (R86)', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertSuccessful();

    $leaflet = Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole();
    $butter = $leaflet->offers()->where('name', 'Máslo')->sole();

    // „250 g, 1 kg = 99,60 Kč“ ověří 24,90; „Super cena“ není sleva (R8)
    expect($butter)
        ->external_id->toStartWith('letak-')
        ->offer_type->toBe(OfferType::PromoPrice)
        ->price->toBe(2490)
        ->original_price->toBeNull()
        ->promotion_text->toBe('Super cena')
        ->package_text->toBe('250 g')
        ->quantity->toBe(250.0)
        ->source_url->toBe('https://www.lidl.cz/l/cs/letak/akcni-letak-od-ctvrtka-8-10-11-10-2026/view/flyer/page/1')
        ->and($butter->valid_from->toDateString())->toBe('2026-10-08')
        ->and($butter->valid_to->toDateString())->toBe('2026-10-11');

    // Text stránek pro zmínky bez ceny zůstává (R27)
    expect($leaflet->pages()->count())->toBe(5);

    Process::assertRan(fn ($process): bool => in_array('-bbox-layout', (array) $process->command, true));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Akcni-letak-OD-CTVRTKA-8-10-11-10-2026-00.pdf'));
});

it('slevu z letáku uloží s původní cenou, jen když sedí procento', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    // „-37% 79.90“ nad 49.90, „200 g, 100g = 24,95 Kč“
    expect(Offer::query()->where('name', 'ALESTO SELECTION Vlašské ořechy')->sole())
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(4990)
        ->original_price->toBe(7990)
        ->discount_percent->toBe(37);

    // „Ušetřete* 34%“ je úspora na ceně za jednotku, ne sleva (R8)
    expect(Offer::query()->where('name', 'VELKOPOPOVICKÝ KOZEL 10')->sole())
        ->offer_type->toBe(OfferType::PromoPrice)
        ->price->toBe(8990)
        ->promotion_text->toBe('Ušetřete* 34%')
        ->quantity->toBe(3000.0);
});

it('cenu s Lidl Plus z letáku uloží vedle běžné ceny', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    // „-33% 179.90**“, běžná cena 119.90, pod ní „S Lidl Plus“ „-38%“ a 109.90; 0,75 l, 1 l = 159,87 Kč
    expect(Offer::query()->where('name', 'BOHEMIA SEKT')->sole())
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(11990)
        ->original_price->toBe(17990)
        ->discount_percent->toBe(33)
        ->loyalty_price->toBe(10990)
        ->loyalty_program->toBe(LoyaltyProgram::LidlPlus);
});

it('dlaždici, kterou nejde ověřit, z letáku nevezme', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    // „Vejce M“ s „30 ks“ bez ceny za jednotku, mandarinky s názvem mimo sloupec ceny,
    // kapsle JAR s Lidl Plus bez ceny za jednotku — zůstanou zmínkami (R27)
    expect(Offer::query()->where('name', 'like', 'Vejce%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', '%Mandarinky%')->exists())->toBeFalse()
        ->and(Offer::query()->where('name', 'like', 'JAR%')->exists())->toBeFalse();
});

it('akci z letáku, kterou nese web, nezdvojí', function (): void {
    // Čtvrteční kampaň z 6. 10. má máslo a sekt od 8. 10. se stejnou cenou jako leták
    fakeLidl(thursdayCampaign: 'lidl/ctvrtecni-nabidka-2026-10-06.html');

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertSuccessful();

    expect(Offer::query()->where('name', 'Máslo')->pluck('external_id')->all())->toBe(['10055941'])
        ->and(Offer::query()->where('name', 'BOHEMIA SEKT')->pluck('external_id')->all())->toBe(['10034404'])
        ->and(Offer::query()->where('name', 'ALESTO SELECTION Vlašské ořechy')->sole()->external_id)->toStartWith('letak-');
});

it('stejná dlaždice dá při dalším stažení stejnou nabídku (R16)', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);
    $first = Offer::query()->pluck('external_id')->sort()->values()->all();
    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertSuccessful();

    expect(Offer::query()->pluck('external_id')->sort()->values()->all())->toBe($first)
        ->and(Offer::query()->whereNotNull('withdrawn_at')->exists())->toBeFalse();
});

it('chyba převodu nebo stažení PDF ukončí celé stažení a nic neuloží', function (): void {
    fakeLidl();
    Process::fake(['*' => Process::result(errorOutput: 'Syntax Error: Couldn\'t read xref table', exitCode: 1)]);

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('pdftotext')
        ->and(Offer::query()->count())->toBe(0)
        ->and(Leaflet::query()->count())->toBe(0);
});

it('nedostupné PDF ukončí stažení chybou', function (): void {
    config(['letaky.http.retries' => 1]);
    fakeLidl(pdfStatus: 503);

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed)
        ->and(Offer::query()->count())->toBe(0);
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

it('uloží text stránek potravinových letáků pro zmínky bez ceny (R27)', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertSuccessful();

    $leaflet = Leaflet::query()->where('kind', LeafletKind::Leaflet)->sole();
    expect($leaflet)
        ->external_id->toBe('akcni-letak-od-ctvrtka-8-10-11-10-2026')
        ->title->toBe('Akční leták OD ČTVRTKA 8. 10. - 11. 10. 2026')
        ->and($leaflet->valid_from?->toDateString())->toBe('2026-10-08')
        ->and($leaflet->valid_to?->toDateString())->toBe('2026-10-11')
        // Akce z PDF stran 1–3 (R86): šunka, řízky, máslo, ořechy, Kozel, med, švestky, Rama, Pringles, sekt
        ->and($leaflet->offers()->count())->toBe(10)
        ->and($leaflet->pages()->pluck('number')->all())->toBe([1, 10, 18, 28, 49]);

    // keyWords i altText stránky, odkaz na stránku v prohlížeči letáku
    expect($leaflet->pages()->where('number', 1)->sole())
        ->text->toContain('Vejce')->toContain('Akční nabídka potravin v Lidlu')
        ->page_url->toBe('https://www.lidl.cz/l/cs/letak/akcni-letak-od-ctvrtka-8-10-11-10-2026/view/flyer/page/1')
        ->image_url->toStartWith('https://imgproxy.leaflets.schwarz/');

    // Spotřební zboží a hity týdne nejsou potravinové letáky
    Http::assertNotSent(fn (Request $request): bool => in_array($request['flyer_identifier'] ?? null, ['spotrebni-zbozi-5-10-11-10-2026', 'hity-tydne-se-slevou-az-2000-kc-5-10-11-10-2026'], true));
});

it('položce bez data dá platnost ostatních akcí kampaně', function (): void {
    fakeLidl();

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']]);

    $tokaji = lidlOffer('10028498');
    expect($tokaji->valid_from->toDateString())->toBe('2026-10-02')
        ->and($tokaji->valid_to->toDateString())->toBe('2026-10-04');
});

it('bez akcí skončí chybou, i když leták vrátil stránky (R54)', function (): void {
    // Ani PDF letáku nic nedá — strana 4 („Ceny v klidu“) má velkou cenu bez dlaždice
    fakeLidl(withoutTiles: true, leafletPages: 'lidl/leaflet-8-10-page-4-2026-10-06.html');

    $this->artisan('letaky:import-offers', ['chain' => ['lidl']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('nevrátil žádnou nabídku')
        ->and(Leaflet::query()->count())->toBe(0);
});
