<?php

/**
 * Import letáků Albertu s textem stránek pro zmínky bez ceny (R27, R36).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\ScrapeStatus;
use App\Enums\StoreFormat;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Falešné odpovědi Albertu: seznam letáků podle typu prodejny a stránky letáků (stejná fixture
 * pro hypermarket i supermarket — tvar je stejný).
 */
function fakeAlbert(): void
{
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://www.albert.cz/api/v1/')) {
            $type = strtolower((string) ($request->data()['variables']['locationType'] ?? ''));

            return Http::response(responseFixture("albert/leaflets-{$type}-2026-10-02.json"));
        }

        return Http::response(responseFixture('albert/spreads-40sm-2026-10-02.json'));
    });
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží hlavní letáky hypermarketů a supermarketů s textem stránek, bez nabídek', function (): void {
    fakeAlbert();

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    $leaflets = Leaflet::query()->where('chain', Chain::Albert)->orderBy('external_id')->get();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and(Offer::query()->count())->toBe(0)
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

    $this->artisan('letaky:import-offers', ['chain' => ['albert']])->assertSuccessful();

    // UA s „https://“ Albert pošle přes prerender pro roboty a GraphQL vrátí 400
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->header('User-Agent')[0] ?? '', 'Slevohlidka/')
        && ! str_contains($request->header('User-Agent')[0] ?? '', '://'));
});
