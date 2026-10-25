<?php

/**
 * Import letáků Hrušky (R165): PDF aktuálního letáku z odkazu na úvodní stránce a PDF letáku
 * dalšího týdne podle čísla týdne. Program pdftotext se nespouští — výstup jsou fixtures
 * skutečných stran (strana 1 letáků 43/26 a 44/26).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const HRUSKA_CURRENT_PDF = 'https://media.mojehruska.cz/2026/10/43_26.pdf';

const HRUSKA_NEXT_PDF = 'https://media.mojehruska.cz/2026/10/44_26.pdf';

/**
 * Falešné odpovědi Hrušky: úvodní stránka s odkazem na leták 43/26 a PDF; leták 44/26 je na
 * serveru, nebo ještě ne (404).
 */
function fakeHruska(bool $nextLeaflet = true, ?string $home = null): void
{
    Http::fake(function (Request $request) use ($nextLeaflet, $home) {
        if ($request->url() === 'https://mojehruska.cz/') {
            return Http::response($home ?? responseFixture('hruska/home-2026-10-25.html'));
        }
        if (str_starts_with($request->url(), 'https://media.mojehruska.cz/') && ($nextLeaflet || $request->url() === HRUSKA_CURRENT_PDF)) {
            return Http::response($request->method() === 'HEAD' ? '' : '%PDF-1.7 leták');
        }

        return Http::response('', 404);
    });
}

/**
 * Výstup pdftotext: nejdřív aktuální leták, pak leták dalšího týdne.
 */
function fakeHruskaPdfText(): void
{
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(responseFixture('hruska/pdf-43-2026-10-25-strana-1.html')))
        ->push(Process::result(responseFixture('hruska/pdf-44-2026-10-25-strana-1.html'))),
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    // Fixtures jsou jen titulní strany (13–15 akcí), celý leták jich má ~170
    config(['letaky.sources.hruska.pdf_min_offers' => 10]);
});

it('uloží aktuální leták a leták dalšího týdne s platností z hlavičky PDF', function (): void {
    fakeHruska();
    fakeHruskaPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['hruska']])->assertSuccessful();

    $leaflets = Leaflet::query()->where('chain', Chain::Hruska)->orderBy('external_id')->get();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflets->pluck('external_id')->all())->toBe(['43_26', '44_26'])
        ->and($leaflets[0])
        ->kind->toBe(LeafletKind::Leaflet)
        ->source_url->toBe(HRUSKA_CURRENT_PDF)
        ->and($leaflets[0]->valid_from?->toDateString())->toBe('2026-10-21')
        ->and($leaflets[1]->valid_from?->toDateString())->toBe('2026-10-29')
        ->and($leaflets[1]->valid_to?->toDateString())->toBe('2026-11-03');

    // Leták dalšího týdne: číslo podle ISO týdne začátku, složka podle měsíce nahrání
    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD' && $request->url() === HRUSKA_NEXT_PDF);
});

it('uloží akci s klubovou cenou vedle běžné a odkazem na stránku s letákem', function (): void {
    fakeHruska();
    fakeHruskaPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['hruska']])->assertSuccessful();

    expect(Offer::query()->where('name', 'Prantl Mistrovská šunka nejvyšší jakosti')->sole())
        ->chain->toBe(Chain::Hruska)
        ->price->toBe(2290)
        ->loyalty_price->toBe(2190)
        ->loyalty_program->toBe(LoyaltyProgram::HruskaKlub)
        ->source_url->toBe('https://mojehruska.cz/')
        ->leaflet_id->toBe(Leaflet::query()->where('external_id', '43_26')->value('id'))
        // Akce letáku dalšího týdne ještě nezačala
        ->and(Offer::query()->where('name', 'Kofola')->sole()->valid_from->toDateString())->toBe('2026-10-29');
});

it('bez letáku dalšího týdne na serveru uloží jen aktuální leták', function (): void {
    fakeHruska(nextLeaflet: false);
    fakeHruskaPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['hruska']])->assertSuccessful();

    expect(Leaflet::query()->where('chain', Chain::Hruska)->pluck('external_id')->all())->toBe(['43_26']);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === HRUSKA_NEXT_PDF);
});

it('úvodní stránka bez odkazu na PDF je změna odpovědi a stažení selže', function (): void {
    fakeHruska(home: '<html><body><h1>Aktuální leták</h1></body></html>');

    $this->artisan('letaky:import-offers', ['chain' => ['hruska']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('bez odkazu na PDF');
});

it('leták s málo ověřenými akcemi ukončí stažení chybou a akce neoznačí jako stažené (R16)', function (): void {
    fakeHruska();
    fakeHruskaPdfText();
    config(['letaky.sources.hruska.pdf_min_offers' => 60]);
    $previous = Offer::factory()->create(['chain' => Chain::Hruska, 'valid_from' => '2026-10-21', 'valid_to' => '2026-10-27']);

    $this->artisan('letaky:import-offers', ['chain' => ['hruska']])->assertFailed();

    expect(ScrapeRun::query()->latest('id')->first())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('ověřených akcí')
        ->and($previous->fresh()?->withdrawn_at)->toBeNull();
});
