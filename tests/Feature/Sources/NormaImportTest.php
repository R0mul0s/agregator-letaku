<?php

/**
 * Import letáků Normy (R166): PDF tohoto a příštího týdne z odkazů na stránce nabídky na
 * norma-online.de/cz/. Program pdftotext se nespouští — výstup jsou fixtures skutečných stran.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\OfferType;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Falešné odpovědi Normy: stránka nabídky s odkazy na letáky 2026-44 a 2026-45 a jejich PDF.
 */
function fakeNorma(?string $page = null): void
{
    Http::fake(function (Request $request) use ($page) {
        if ($request->url() === 'https://www.norma-online.de/cz/angebote/') {
            return Http::response($page ?? responseFixture('norma/angebote-2026-10-25.html'));
        }

        return Http::response('%PDF-1.6 leták');
    });
}

/**
 * Výstup pdftotext: leták tohoto týdne (strana 6), pak příštího (strana 4).
 */
function fakeNormaPdfText(): void
{
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(responseFixture('norma/pdf-2026-44-2026-10-25-strana-6.html')))
        ->push(Process::result(responseFixture('norma/pdf-2026-45-2026-10-25-strana-4.html'))),
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    // Fixtures jsou jednotlivé strany (~20 akcí), celý leták jich má ~110
    config(['letaky.sources.norma.pdf_min_offers' => 10]);
});

it('uloží letáky tohoto a příštího týdne s platností týdne z názvu PDF', function (): void {
    fakeNorma();
    fakeNormaPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['norma']])->assertSuccessful();

    $leaflets = Leaflet::query()->where('chain', Chain::Norma)->orderBy('external_id')->get();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflets->pluck('external_id')->all())->toBe(['2026-44', '2026-45'])
        ->and($leaflets[0])
        ->kind->toBe(LeafletKind::Leaflet)
        ->source_url->toBe('https://www.norma-online.de/cz/angebote/online-prospekt/2026-44_CZ.pdf')
        ->and($leaflets[0]->valid_from?->toDateString())->toBe('2026-10-26')
        ->and($leaflets[0]->valid_to?->toDateString())->toBe('2026-11-01')
        ->and($leaflets[1]->valid_from?->toDateString())->toBe('2026-11-02');

    // Náhled i tlačítko odkazují na totéž PDF — stáhne se jednou
    Http::assertSentCount(3);
});

it('uloží slevu s původní cenou a odkazem na stránku s letáky', function (): void {
    fakeNorma();
    fakeNormaPdfText();

    $this->artisan('letaky:import-offers', ['chain' => ['norma']])->assertSuccessful();

    expect(Offer::query()->where('name', 'Primona Prací gel')->sole())
        ->chain->toBe(Chain::Norma)
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(9990)
        ->original_price->toBe(11490)
        ->source_url->toBe('https://www.norma-online.de/cz/angebote/')
        ->leaflet_id->toBe(Leaflet::query()->where('external_id', '2026-44')->value('id'));
});

it('stránka bez odkazu na PDF je změna odpovědi a stažení selže', function (): void {
    fakeNorma('<html><body><h3>Nabídka na týden</h3></body></html>');

    $this->artisan('letaky:import-offers', ['chain' => ['norma']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('bez odkazu na PDF');
});

it('leták s málo ověřenými akcemi ukončí stažení chybou a akce neoznačí jako stažené (R16)', function (): void {
    fakeNorma();
    fakeNormaPdfText();
    config(['letaky.sources.norma.pdf_min_offers' => 40]);
    $previous = Offer::factory()->create(['chain' => Chain::Norma, 'valid_from' => '2026-10-26', 'valid_to' => '2026-11-01']);

    $this->artisan('letaky:import-offers', ['chain' => ['norma']])->assertFailed();

    expect(ScrapeRun::query()->latest('id')->first())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('ověřených akcí')
        ->and($previous->fresh()?->withdrawn_at)->toBeNull();
});
