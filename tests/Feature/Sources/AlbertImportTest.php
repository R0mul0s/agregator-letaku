<?php

/**
 * Import letáků Albertu: akce s cenou z PDF letáku (R86) a text stránek pro zmínky bez ceny
 * (R27, R36). Program pdftotext se nespouští — výstup jsou fixtures skutečných stran.
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
use App\Enums\ScrapeStatus;
use App\Enums\StoreFormat;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Falešné odpovědi Albertu: seznam letáků podle typu prodejny, stránky letáků (stejná fixture
 * pro hypermarket i supermarket — tvar je stejný), `data.json` s odkazem na PDF a PDF.
 *
 * @param  array<string, mixed>  $data  Odpověď `data.json`; prázdné pole = fixture
 */
function fakeAlbert(array $data = []): void
{
    Http::fake(function (Request $request) use ($data) {
        if (str_starts_with($request->url(), 'https://www.albert.cz/api/v1/')) {
            $type = strtolower((string) ($request->data()['variables']['locationType'] ?? ''));

            return Http::response(responseFixture("albert/leaflets-{$type}-2026-10-02.json"));
        }
        if (str_ends_with($request->url(), '/data.json')) {
            return Http::response($data === [] ? responseFixture('albert/data-40hm-2026-10-06.json') : $data);
        }
        if (str_starts_with($request->url(), 'https://view.publitas.com/')) {
            return Http::response('%PDF-1.6 leták');
        }

        return Http::response(responseFixture('albert/spreads-40sm-2026-10-02.json'));
    });
}

/**
 * Výstup pdftotext: nejdřív leták hypermarketů (strany 7 a 8), pak supermarketů (strana 6).
 */
function fakeAlbertPdfText(): void
{
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(responseFixture('albert/pdf-40hm-2026-10-06-strany-7-8.html')))
        ->push(Process::result(responseFixture('albert/pdf-40sm-2026-10-06-strana-6.html'))),
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží hlavní letáky hypermarketů a supermarketů s akcemi z PDF a textem stránek', function (): void {
    fakeAlbert();
    fakeAlbertPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    $leaflets = Leaflet::query()->where('chain', Chain::Albert)->orderBy('external_id')->get();
    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Succeeded)
        // 19 akcí HM + 11 SM, Bohemia Chips a Lipánek jsou v obou letácích
        ->offers_count->toBe(28)
        // Lokální varianta supermarketu (…_frenstat, isDefault=false) se nestáhne
        ->and($leaflets->pluck('external_id')->all())->toBe(['3382786', '3382787'])
        ->and($leaflets[0])
        ->kind->toBe(LeafletKind::Leaflet)
        ->format->toBe(StoreFormat::Hypermarket)
        ->and($leaflets[0]->valid_from?->toDateString())->toBe('2026-09-30')
        ->and($leaflets[0]->valid_to?->toDateString())->toBe('2026-10-06');

    $page = $leaflets[1]->pages()->where('number', 2)->sole();
    expect($leaflets[1]->format)->toBe(StoreFormat::Supermarket)
        ->and($leaflets[1]->pages()->pluck('number')->all())->toBe([2, 3, 31])
        ->and($page->text)->toContain('Kuře bez drobů')
        ->and($page->page_url)->toBe('https://letaky.albert.cz/40sm_akcni_letak/page/2')
        ->and($page->image_url)->toStartWith('https://letaky.albert.cz/resize/');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'frenstat'));
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://view.publitas.com/90263/3382786/pdfs/'));
});

it('uloží ověřenou akci se slevou s odkazem na stránku letáku a formátem prodejny', function (): void {
    fakeAlbert();
    fakeAlbertPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    $offer = Offer::query()->where('name', 'Fa Deodorant sprej')->sole();
    expect($offer)
        ->chain->toBe(Chain::Albert)
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(4990)
        ->original_price->toBe(6990)
        ->variant_note->toBe('vybrané druhy')
        ->store_format->toBe(StoreFormat::Hypermarket)
        ->source_url->toBe('https://letaky.albert.cz/40hm_akcni_letak/page/1')
        ->leaflet_id->toBe(Leaflet::query()->where('external_id', '3382786')->value('id'))
        // „platí do 13. 10. 2026“ v dlaždici
        ->and($offer->valid_from->toDateString())->toBe('2026-09-30')
        ->and($offer->valid_to->toDateString())->toBe('2026-10-13');
});

it('cenu s aplikací uloží jako loyalty_price a akci z obou letáků bez formátu prodejny', function (): void {
    fakeAlbert();
    fakeAlbertPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    expect(Offer::query()->where('name', 'Domestos WC čistič')->sole())
        ->price->toBe(3990)
        ->loyalty_price->toBe(3490)
        ->loyalty_program->toBe(LoyaltyProgram::MujAlbert);

    // Bohemia Chips se stejnou cenou v letáku HM i SM = všechny prodejny
    expect(Offer::query()->where('name', 'Bohemia Chips')->sole())
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->store_format->toBeNull()
        ->and(Offer::query()->where('name', 'Persil Prací gel')->sole()->store_format)->toBe(StoreFormat::Supermarket);
});

it('chyba pdftotext ukončí stažení chybou a akce neoznačí jako stažené (R16)', function (): void {
    fakeAlbert();
    $previous = Offer::factory()->create(['chain' => Chain::Albert, 'valid_from' => '2026-09-30', 'valid_to' => '2026-10-06']);
    Process::fake(['*' => Process::result(errorOutput: 'Syntax Error: Couldn\'t read xref table', exitCode: 1)]);

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertFailed();

    // Továrna akce založila i své stažení — poslední je to dnešní
    expect(ScrapeRun::query()->latest('id')->first())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('pdftotext')
        ->and($previous->fresh()?->withdrawn_at)->toBeNull();
});

it('data.json bez odkazu na PDF je změna odpovědi a stažení selže', function (): void {
    fakeAlbert(['config' => ['canonicalUrl' => 'https://letaky.albert.cz/40hm_akcni_letak/']]);
    fakeAlbertPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('bez odkazu na PDF');
});

it('leták bez ověřených akcí je chyba i se stránkami pro zmínky (R54)', function (): void {
    fakeAlbert();
    // Strana jiného letáku (Lidl) — dlaždice Albertu v ní nejsou
    Process::fake(['*' => Process::result(responseFixture('pdf/lidl-8-10-2026-page-22.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed)
        ->and(Offer::query()->count())->toBe(0);
});

it('leták bez stránek je změna odpovědi a stažení selže', function (): void {
    Http::fake([
        'https://www.albert.cz/*' => Http::response(responseFixture('albert/leaflets-hypermarket-2026-10-02.json')),
        'https://letaky.albert.cz/*' => Http::response([]),
    ]);

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed);
});

it('posílá identifikovatelný User-Agent bez adresy se schématem (R65)', function (): void {
    fakeAlbert();
    fakeAlbertPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    // UA s „https://“ Albert pošle přes prerender pro roboty a GraphQL vrátí 400
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->header('User-Agent')[0] ?? '', 'Slevohlidka/')
        && ! str_contains($request->header('User-Agent')[0] ?? '', '://'));
});
