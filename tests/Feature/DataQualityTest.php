<?php

/**
 * Přehled kvality dat (R129): statistiky letáků za stažení, propady akcí a ověřených cen,
 * upozornění adminům, stránka pro admina a úklid starých statistik.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use App\Domain\Account\Actions\RunMaintenance;
use App\Domain\Notifications\Actions\RecordHealthAlerts;
use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Data\TileStats;
use App\Domain\Offers\Import\RecordLeafletStats;
use App\Domain\Offers\Quality\DataQuality;
use App\Domain\Offers\Quality\QualityIssue;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\NotificationKind;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\LeafletStat;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-10 10:00:00');
});

/**
 * Leták Penny z PDF/SVG.
 */
function qualityLeaflet(string $externalId = '08_10_2026', Chain $chain = Chain::Penny): Leaflet
{
    return Leaflet::factory()->create(['chain' => $chain, 'kind' => LeafletKind::Leaflet, 'external_id' => $externalId]);
}

/**
 * Úspěšné stažení obchodu se statistikou letáku.
 */
function qualityStat(Leaflet $leaflet, int $offers, ?int $candidates = null, ?int $verified = null): LeafletStat
{
    $run = ScrapeRun::query()->create([
        'chain' => $leaflet->chain,
        'status' => ScrapeStatus::Succeeded,
        'offers_count' => $offers,
        'started_at' => CarbonImmutable::now(),
        'finished_at' => CarbonImmutable::now(),
    ]);

    return LeafletStat::query()->create([
        'scrape_run_id' => $run->id,
        'leaflet_id' => $leaflet->id,
        'chain' => $leaflet->chain,
        'offers_count' => $offers,
        'tile_candidates' => $candidates,
        'tiles_verified' => $verified,
        'created_at' => CarbonImmutable::now(),
    ]);
}

it('zapíše za každý leták stažení uložené akce a ověřené ceny, víc dávek letáku sečte a leták bez akce má nulu', function (): void {
    $run = ScrapeRun::start(Chain::Penny);
    $pdf = qualityLeaflet();
    $empty = qualityLeaflet('15_10_2026');
    $web = Leaflet::factory()->create(['chain' => Chain::Penny, 'kind' => LeafletKind::Web, 'external_id' => 'web-2026-10-07']);
    Offer::factory()->count(3)->create(['chain' => Chain::Penny, 'leaflet_id' => $pdf->id, 'scrape_run_id' => $run->id]);
    Offer::factory()->count(2)->create(['chain' => Chain::Penny, 'leaflet_id' => $web->id, 'scrape_run_id' => $run->id]);
    // Akce jiného stažení se nepočítá
    Offer::factory()->create(['chain' => Chain::Penny, 'leaflet_id' => $pdf->id]);

    $batch = fn (Leaflet $leaflet, ?TileStats $tiles): SourceBatch => new SourceBatch(new LeafletData(kind: $leaflet->kind, externalId: $leaflet->external_id), [], tiles: $tiles);
    app(RecordLeafletStats::class)->record(Chain::Penny, $run, [
        $batch($web, null),
        $batch($pdf, new TileStats(40, 30)),
        $batch($pdf, new TileStats(10, 5)),
        $batch($empty, new TileStats(25, 0)),
    ]);

    $rows = LeafletStat::query()->orderBy('leaflet_id')->get()->map(fn (LeafletStat $stat): array => [
        $stat->leaflet_id, $stat->offers_count, $stat->tile_candidates, $stat->tiles_verified,
    ])->all();
    expect($rows)->toBe([
        [$pdf->id, 3, 50, 35],
        [$empty->id, 0, 25, 0],
        [$web->id, 2, null, null],
    ]);
});

it('propad akcí: méně akcí než nejvíc z předchozích stažení téhož letáku zmenšené o povolený propad', function (): void {
    $leaflet = qualityLeaflet();
    qualityStat($leaflet, 100);
    qualityStat($leaflet, 90);
    $ok = qualityStat($leaflet, 75);
    $drop = qualityStat($leaflet, 60);

    $quality = app(DataQuality::class);
    expect($quality->issues($ok))->toBe([])
        ->and($quality->issues($drop))->toEqual([new QualityIssue(QualityIssue::OFFERS, 60, 100)]);

    // Malý leták (pod min_offers) kolísá — propad se nehlásí
    $small = qualityLeaflet('maly');
    qualityStat($small, 8);
    expect($quality->issues(qualityStat($small, 2)))->toBe([]);
});

it('propad ověřených cen: proti mediánu ostatních letáků obchodu, bez nich proti předchozím stažením letáku', function (): void {
    qualityStat(qualityLeaflet('a'), 50, 100, 80);
    qualityStat(qualityLeaflet('b'), 50, 100, 70);
    qualityStat(qualityLeaflet('c'), 50, 100, 90);
    // Jiný obchod se nezapočítá
    qualityStat(qualityLeaflet('lidl', Chain::Lidl), 50, 100, 10);
    $new = qualityLeaflet('novy');

    $quality = app(DataQuality::class);
    expect($quality->issues(qualityStat($new, 40, 100, 60)))->toBe([])
        ->and($quality->issues(qualityStat($new, 40, 100, 50)))->toEqual([new QualityIssue(QualityIssue::VERIFIED, 50, 80)])
        // Leták s málo nalezenými cenami (titulní strana) se neposuzuje
        ->and($quality->issues(qualityStat(qualityLeaflet('maly'), 5, 10, 1)))->toBe([]);

    // Jediný leták obchodu: základ jsou jeho předchozí stažení
    $only = qualityLeaflet('jediny', Chain::Albert);
    qualityStat($only, 40, 100, 75);
    expect($quality->issues(qualityStat($only, 40, 100, 40)))->toEqual([new QualityIssue(QualityIssue::VERIFIED, 40, 75)]);
});

it('propad ohlásí adminům jako výpadek s odkazem na přehled kvality dat, trvající neopakuje', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $leaflet = qualityLeaflet();
    qualityStat($leaflet, 100);
    qualityStat($leaflet, 40);

    $alerts = app(RecordHealthAlerts::class);
    $alerts();
    $alerts();

    $notifications = $admin->notifications()->where('type', NotificationKind::SystemAlert->value)->get();
    $data = $notifications->sole()->data;
    expect($data['body'])->toContain('Penny – 08_10_2026: 40 akcí (předtím až 100)')
        ->and($data['url'])->toBe('/kvalita-dat');
});

it('přehled kvality dat vidí jen admin: obchody s posledním stažením a letáky s vývojem a propadem', function (): void {
    $leaflet = qualityLeaflet();
    qualityStat($leaflet, 100, 120, 90);
    qualityStat($leaflet, 40, 120, 30);

    $this->get('/kvalita-dat')->assertRedirect('/prihlaseni');
    $this->actingAs(User::factory()->create())->get('/kvalita-dat')->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/kvalita-dat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DataQuality')
            ->where('auth.dataQualityUrl', '/kvalita-dat')
            ->where('chainReports', fn ($reports): bool => collect($reports)->pluck('chain')->contains('penny'))
            ->where('chainReports', function ($reports): bool {
                $penny = collect($reports)->firstWhere('chain', 'penny');
                $row = $penny['leaflets'][0];

                return $penny['lastRun']['status'] === 'succeeded'
                    && $penny['offersHistory'] === [100, 40]
                    && $row['offers'] === 40
                    && $row['verified'] === ['percent' => 25, 'verified' => 30, 'candidates' => 120]
                    && $row['offersHistory'] === [100, 40]
                    && $row['verifiedHistory'] === [75, 25]
                    && collect($row['issues'])->pluck('kind')->all() === ['offers', 'verified'];
            }));
});

it('denní úklid smaže statistiky starší než nastavená doba', function (): void {
    $leaflet = qualityLeaflet();
    $old = qualityStat($leaflet, 10);
    $old->forceFill(['created_at' => CarbonImmutable::now()->subDays(91)])->save();
    $recent = qualityStat($leaflet, 10);

    expect(app(RunMaintenance::class)()['leaflet_stats'])->toBe(1)
        ->and(LeafletStat::query()->pluck('id')->all())->toBe([$recent->id]);
});
