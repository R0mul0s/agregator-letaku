<?php

/**
 * Import letáků COOP (R167): RSS kanál letáků na coopclub.cz, stránka letáku s odkazem na PDF,
 * celostátní leták pro všechny prodejny a regionální leták jen pro svůj region (regiony jsou
 * prodejny obchodu, migrace 2026_10_25_100000). Program pdftotext se nespouští.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Falešné odpovědi COOP: RSS s letáky CSC 22, SČ 22, TUTY-TIP MENU 22 a skončeným CSC 21, stránky
 * letáků a PDF.
 */
function fakeCoop(?string $feed = null): void
{
    Http::fake(function (Request $request) use ($feed) {
        return match (true) {
            $request->url() === 'https://www.coopclub.cz/letaky/feed/' => Http::response($feed ?? responseFixture('coop/feed-2026-10-25.xml')),
            $request->url() === 'https://www.coopclub.cz/letaky/csc-aktualni-letak/' => Http::response(responseFixture('coop/letak-csc-2026-10-25.html')),
            $request->url() === 'https://www.coopclub.cz/letaky/sc-aktualni-letak/' => Http::response(responseFixture('coop/letak-sc-2026-10-25.html')),
            str_ends_with($request->url(), '.pdf') => Http::response('%PDF-1.7 leták'),
            default => Http::response('', 404),
        };
    });
}

/**
 * Výstup pdftotext: celostátní leták (strana 1), pak leták střední Čechy (strana 1).
 */
function fakeCoopPdfText(): void
{
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(responseFixture('coop/pdf-csc-22-2026-10-25-strana-1.html')))
        ->push(Process::result(responseFixture('coop/pdf-sc-22-2026-10-25-strana-1.html'))),
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    config(['letaky.sources.coop.pdf_min_offers' => 10]);
});

it('stáhne celostátní a regionální leták z RSS, moravský obrázkový a skončený vynechá', function (): void {
    fakeCoop();
    fakeCoopPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['coop']])->assertSuccessful();

    $leaflets = Leaflet::query()->where('chain', Chain::Coop)->orderBy('external_id')->get();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflets->pluck('external_id')->all())->toBe(['CSC 22/2026', 'SČ 22/2026'])
        ->and($leaflets[0]->source_url)->toBe('https://www.coopclub.cz/letaky/csc-aktualni-letak/')
        ->and($leaflets[0]->valid_from?->toDateString())->toBe('2026-10-21')
        ->and($leaflets[0]->valid_to?->toDateString())->toBe('2026-11-03');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'tuty-tip') || str_contains($request->url(), 'aktualni-letak44'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.coopclub.cz/wp-content/uploads/2026/09/4426022-STC-22-26.pdf');
});

it('akce celostátního letáku platí všude, akce jen z regionálního letáku jen v regionu', function (): void {
    fakeCoop();
    fakeCoopPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['coop']])->assertSuccessful();

    // Lipánek je v obou letácích se stejnou cenou — uloží ho celostátní leták bez omezení
    $lipanek = Offer::query()->where('chain', Chain::Coop)->where('price', 1690)->where('package_text', '130 g')->with('stores')->get();
    $staropramen = Offer::query()->where('name', 'Staropramen 10')->with('stores')->sole();
    expect($lipanek)->toHaveCount(1)
        ->and($lipanek[0]->stores)->toBeEmpty()
        ->and($lipanek[0]->leaflet_id)->toBe(Leaflet::query()->where('external_id', 'CSC 22/2026')->value('id'))
        ->and($staropramen->stores->pluck('store_code')->all())->toBe(['coop-sc'])
        ->and($staropramen->source_url)->toBe('https://www.coopclub.cz/letaky/sc-aktualni-letak/');
});

it('uloží cenu s kupónem v aplikaci COOP Česko', function (): void {
    fakeCoop();
    fakeCoopPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['coop']])->assertSuccessful();

    expect(Offer::query()->where('name', 'Ariel')->sole())
        ->price->toBe(34990)
        ->loyalty_price->toBe(29990)
        ->loyalty_program->toBe(LoyaltyProgram::CoopCesko);
});

it('RSS bez sledovaného letáku je změna odpovědi a stažení selže', function (): void {
    fakeCoop('<?xml version="1.0"?><rss version="2.0"><channel><title>Letáky</title></channel></rss>');

    $this->artisan('letaky:import-offers', ['chain' => ['coop']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('RSS');
});

it('leták s málo ověřenými akcemi ukončí stažení chybou a akce neoznačí jako stažené (R16)', function (): void {
    fakeCoop();
    fakeCoopPdfText();
    config(['letaky.sources.coop.pdf_min_offers' => 40]);
    $previous = Offer::factory()->create(['chain' => Chain::Coop, 'valid_from' => '2026-10-21', 'valid_to' => '2026-11-03']);

    $this->artisan('letaky:import-offers', ['chain' => ['coop']])->assertFailed();

    expect(ScrapeRun::query()->latest('id')->first())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('ověřených akcí')
        ->and($previous->fresh()?->withdrawn_at)->toBeNull();
});
