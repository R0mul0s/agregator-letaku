<?php

/**
 * Přehled kvality stažených dat pro admina (R129, `/kvalita-dat`): po obchodech poslední
 * stažení (stav, počet akcí, vývoj) a letáky posledního stažení — počet akcí a u letáků z PDF
 * nebo SVG podíl ověřených cen, s vývojem za poslední stažení a vyznačeným propadem.
 * Data skládá DataQuality.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\Quality\DataQuality;
use App\Domain\Offers\Quality\QualityIssue;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\LeafletStat;
use App\Models\ScrapeRun;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DataQualityController extends Controller
{
    /** Chybová zpráva stažení se zkrátí — celá je v `scrape_runs`. */
    private const ERROR_PREVIEW_LENGTH = 240;

    public function __construct(
        private readonly DataQuality $quality,
        private readonly SourceRegistry $sources,
    ) {}

    /**
     * Zobrazí přehled kvality dat po obchodech.
     */
    public function __invoke(): Response
    {
        return Inertia::render('DataQuality', [
            'chainReports' => array_map($this->chainReport(...), $this->sources->chainsWithOffers()),
            'historyRuns' => config()->integer('letaky.data_quality.history_runs'),
        ]);
    }

    /**
     * Obchod: poslední stažení s vývojem počtu akcí a letáky posledního dokončeného stažení.
     *
     * @return array<string, mixed>
     */
    private function chainReport(Chain $chain): array
    {
        $runs = ScrapeRun::query()
            ->where('chain', $chain)
            ->orderByDesc('id')
            ->limit(config()->integer('letaky.data_quality.history_runs'))
            ->get()
            ->reverse()
            ->values();
        $last = $runs->last();

        return [
            'chain' => $chain->value,
            'lastRun' => $last === null ? null : [
                'status' => $last->status->value,
                'startedAt' => $last->started_at->toIso8601String(),
                'offers' => $last->offers_count,
                'withdrawn' => $last->withdrawn_count,
                'error' => $last->error === null ? null : Str::limit($last->error, self::ERROR_PREVIEW_LENGTH),
            ],
            // Neúspěšné stažení nic neuloží — v grafu jako mezera, ne propad na nulu
            'offersHistory' => $runs->map(fn (ScrapeRun $run): ?int => in_array($run->status, [ScrapeStatus::Succeeded, ScrapeStatus::Partial], true) ? $run->offers_count : null)->all(),
            'leaflets' => array_map($this->leafletReport(...), $this->quality->currentStats($chain)),
        ];
    }

    /**
     * Leták posledního stažení: akce, ověřené ceny, vývoj a propady.
     *
     * @return array<string, mixed>
     */
    private function leafletReport(LeafletStat $stat): array
    {
        $history = $this->quality->history($stat->leaflet_id);

        return [
            'id' => $stat->leaflet_id,
            'label' => $this->quality->label($stat->leaflet),
            'url' => $stat->leaflet->source_url,
            'fromPdf' => $stat->tile_candidates !== null,
            'offers' => $stat->offers_count,
            'verified' => $stat->tile_candidates === null ? null : [
                'percent' => $stat->verifiedPercent(),
                'verified' => $stat->tiles_verified,
                'candidates' => $stat->tile_candidates,
            ],
            'offersHistory' => array_map(fn (LeafletStat $item): int => $item->offers_count, $history),
            'verifiedHistory' => array_map(fn (LeafletStat $item): ?int => $item->verifiedPercent(), $history),
            'issues' => array_map(fn (QualityIssue $issue): array => [
                'kind' => $issue->kind,
                'current' => $issue->current,
                'baseline' => $issue->baseline,
            ], $this->quality->issues($stat)),
        ];
    }
}
