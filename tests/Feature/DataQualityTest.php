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
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Data\TileStats;
use App\Domain\Offers\Import\RecordLeafletStats;
use App\Domain\Offers\Quality\DataQuality;
use App\Domain\Offers\Quality\QualityIssue;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\NotificationKind;
use App\Enums\OfferType;
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
function qualityStat(Leaflet $leaflet, int $offers, ?int $candidates = null, ?int $verified = null, int $covered = 0): LeafletStat
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
        'covered_offers' => $covered,
        'created_at' => CarbonImmutable::now(),
    ]);
}

/**
 * Akce ze zdroje s ID položky (platnost pro všechny stejná).
 *
 * @return list<OfferData>
 */
function qualityOffers(string ...$ids): array
{
    return array_map(fn (string $id): OfferData => new OfferData(
        externalId: $id,
        name: "Akce {$id}",
        offerType: OfferType::PromoPrice,
        validFrom: CarbonImmutable::parse('2026-10-08'),
        validTo: CarbonImmutable::parse('2026-10-14'),
        raw: [],
        price: 2990,
    ), $ids);
}

it('zapíše za každý leták stažení akce z dávek a ověřené ceny, víc dávek letáku sečte a leták bez akce má nulu', function (): void {
    $run = ScrapeRun::start(Chain::Penny);
    $pdf = qualityLeaflet();
    $empty = qualityLeaflet('15_10_2026');
    $web = Leaflet::factory()->create(['chain' => Chain::Penny, 'kind' => LeafletKind::Web, 'external_id' => 'web-2026-10-07']);

    $batch = fn (Leaflet $leaflet, array $offers, ?TileStats $tiles, int $covered = 0): SourceBatch => new SourceBatch(new LeafletData(kind: $leaflet->kind, externalId: $leaflet->external_id), $offers, tiles: $tiles, coveredOffers: $covered);
    app(RecordLeafletStats::class)->record(Chain::Penny, $run, [
        $batch($web, qualityOffers('w1', 'spolecna'), null),
        // Akce, kterou nese i web, patří i letáku — uloží ji jen první dávka, ale leták o ni nepřišel (R150);
        // stejná akce ve dvou dávkách jednoho letáku se počítá jednou
        $batch($pdf, qualityOffers('p1', 'spolecna'), new TileStats(40, 30)),
        $batch($pdf, qualityOffers('p1', 'p2'), new TileStats(10, 5), 4),
        $batch($empty, [], new TileStats(25, 0)),
    ]);

    $rows = LeafletStat::query()->orderBy('leaflet_id')->get()->map(fn (LeafletStat $stat): array => [
        $stat->leaflet_id, $stat->offers_count, $stat->covered_offers, $stat->tile_candidates, $stat->tiles_verified,
    ])->all();
    expect($rows)->toBe([
        [$pdf->id, 3, 4, 50, 35],
        [$empty->id, 0, 0, 25, 0],
        [$web->id, 2, 0, null, null],
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

it('akce, které z letáku převzal web, nejsou propad (R136)', function (): void {
    // Lidl 12. 10.: leták měl 165 akcí, pak 53 z nich zveřejnil i web
    $leaflet = qualityLeaflet('od-ctvrtka', Chain::Lidl);
    qualityStat($leaflet, 165);
    $covered = qualityStat($leaflet, 112, covered: 53);

    $quality = app(DataQuality::class);
    expect($quality->issues($covered))->toBe([])
        // Skutečný propad pod převzatými akcemi se pořád pozná
        ->and($quality->issues(qualityStat($leaflet, 40, covered: 53)))->toEqual([new QualityIssue(QualityIssue::OFFERS, 93, 165)]);
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

it('katalog porovná ověřené ceny jen se svými předchozími staženími a do mediánu ostatních se nepočítá (R176)', function (): void {
    // Albert 27. 10.: vánoční katalog 47 % proti mediánu týdenních letáků 74 %
    qualityStat(qualityLeaflet('3470647', Chain::Albert), 50, 100, 74);
    qualityStat(qualityLeaflet('3470643', Chain::Albert), 50, 100, 74);
    $catalog = Leaflet::factory()->create([
        'chain' => Chain::Albert,
        'kind' => LeafletKind::Leaflet,
        'external_id' => '3477471',
        'source_url' => 'https://letaky.albert.cz/44hm_akcni_katalog_xmass/',
    ]);

    $quality = app(DataQuality::class);
    expect($quality->issues(qualityStat($catalog, 40, 100, 47)))->toBe([])
        // Rozbitý parser se v katalogu pořád pozná podle jeho vlastní historie
        ->and($quality->issues(qualityStat($catalog, 40, 100, 20)))->toEqual([new QualityIssue(QualityIssue::VERIFIED, 20, 47)]);

    // Týdenní leták se s katalogem neporovnává: medián zůstal 74 %, ne 47 %
    expect($quality->issues(qualityStat(qualityLeaflet('3470648', Chain::Albert), 40, 100, 50)))
        ->toEqual([new QualityIssue(QualityIssue::VERIFIED, 50, 74)]);
});

it('v poslední den platnosti letáku propad akcí nehlásí (R176)', function (): void {
    // Lidl „Ovoce a zelenina“ 26.–27. 10.: v úterý zbylo na webu 8 z 16 akcí
    $leaflet = Leaflet::factory()->create([
        'chain' => Chain::Lidl,
        'kind' => LeafletKind::Web,
        'external_id' => 'a10080058-2026-10-26',
        'valid_from' => '2026-10-26',
        'valid_to' => '2026-10-27',
    ]);
    $this->travelTo('2026-10-26 08:00:00');
    qualityStat($leaflet, 16);

    $quality = app(DataQuality::class);
    $this->travelTo('2026-10-26 14:00:00');
    expect($quality->issues(qualityStat($leaflet, 8)))->toEqual([new QualityIssue(QualityIssue::OFFERS, 8, 16)]);

    // 23:30 UTC 26. 10. je už 27. 10. místního času
    $this->travelTo('2026-10-26 23:30:00');
    expect($quality->issues(qualityStat($leaflet, 8)))->toBe([]);
});

it('akce, které od porovnávaných stažení normálně skončily, nejsou propad (R178)', function (): void {
    // Globus 29. 10.: akční nabídka 795 akcí po 1 212 — zbytek skončil 28. 10. s minulým týdnem
    $leaflet = Leaflet::factory()->create(['chain' => Chain::Globus, 'kind' => LeafletKind::Web, 'external_id' => 'akcni-nabidka']);
    $this->travelTo('2026-10-28 13:00:00');
    qualityStat($leaflet, 100);
    Offer::factory()->count(30)->create(['chain' => Chain::Globus, 'leaflet_id' => $leaflet->id, 'valid_from' => '2026-10-22', 'valid_to' => '2026-10-28']);
    // Skončila dřív, než proběhlo porovnávané stažení — v jeho počtu nebyla
    Offer::factory()->count(20)->create(['chain' => Chain::Globus, 'leaflet_id' => $leaflet->id, 'valid_from' => '2026-10-15', 'valid_to' => '2026-10-21']);

    $this->travelTo('2026-10-29 05:00:00');
    $quality = app(DataQuality::class);
    expect($quality->issues(qualityStat($leaflet, 65)))->toBe([])
        // Akce, které zmizely během platnosti, se pořád poznají
        ->and($quality->issues(qualityStat($leaflet, 30)))->toEqual([new QualityIssue(QualityIssue::OFFERS, 30, 100)]);
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
