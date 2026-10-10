<?php

/**
 * Kvalita stažených dat (R129): statistiky letáků posledního stažení obchodu, jejich historie
 * a propady — málo akcí proti předchozím stažením téhož letáku, nebo nízký podíl ověřených
 * cen z PDF/SVG proti ostatním letákům obchodu. Propady hlásí adminům `RecordHealthAlerts`
 * (jako výpadek, R126), přehled ukazuje stránka `/kvalita-dat`.
 *
 * Proč takové základy: nový leták s jiným rozvržením nemá vlastní historii, rozbitý parser
 * se ale pozná podle podílu ověřených cen, který na velikosti letáku nezávisí. Počet akcí se
 * porovnává jen v rámci letáku — letáky mají různou velikost.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers\Quality;

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\LeafletStat;
use App\Models\ScrapeRun;
use App\Support\ShortDate;
use Carbon\CarbonImmutable;

final class DataQuality
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly ShortDate $dates,
    ) {}

    /**
     * Statistiky letáků posledního dokončeného stažení obchodu (i částečného, R54).
     *
     * @return list<LeafletStat>
     */
    public function currentStats(Chain $chain): array
    {
        $runId = ScrapeRun::query()
            ->where('chain', $chain)
            ->whereIn('status', [ScrapeStatus::Succeeded, ScrapeStatus::Partial])
            ->max('id');
        if ($runId === null) {
            return [];
        }

        return array_values(LeafletStat::query()->with('leaflet')->where('scrape_run_id', $runId)->orderBy('leaflet_id')->get()->all());
    }

    /**
     * Posledních `history_runs` statistik letáku, od nejstarší.
     *
     * @return list<LeafletStat>
     */
    public function history(int $leafletId): array
    {
        return array_values(LeafletStat::query()
            ->where('leaflet_id', $leafletId)
            ->orderByDesc('id')
            ->limit(config()->integer('letaky.data_quality.history_runs'))
            ->get()
            ->reverse()
            ->all());
    }

    /**
     * Propady statistiky proti obvyklému stavu.
     *
     * @return list<QualityIssue>
     */
    public function issues(LeafletStat $stat): array
    {
        return array_values(array_filter([$this->offersIssue($stat), $this->verifiedIssue($stat)]));
    }

    /**
     * Propady všech obchodů pro upozornění adminům: stálý název (klíč, podle něj se pozná, že
     * propad trvá) => řádek s čísly.
     *
     * @return array<string, string>
     */
    public function drops(): array
    {
        $drops = [];
        foreach ($this->sources->chainsWithOffers() as $chain) {
            foreach ($this->currentStats($chain) as $stat) {
                $name = __('app.data_quality.alert.leaflet', ['chain' => $chain->label(), 'leaflet' => $this->label($stat->leaflet)]);
                foreach ($this->issues($stat) as $issue) {
                    $drops[__("app.data_quality.alert.key_{$issue->kind}", ['name' => $name])] = __("app.data_quality.alert.line_{$issue->kind}", [
                        'name' => $name,
                        'current' => $issue->current,
                        'baseline' => $issue->baseline,
                    ]);
                }
            }
        }

        return $drops;
    }

    /**
     * Název letáku: název od obchodu, jinak jeho ID, s platností („Leták 8. 10. – 14. 10.“).
     */
    public function label(Leaflet $leaflet): string
    {
        $name = $leaflet->title ?? $leaflet->external_id;
        if ($leaflet->valid_from === null || $leaflet->valid_to === null) {
            return $name;
        }

        return $name.' ('.$this->dates->format($leaflet->valid_from).' – '.$this->dates->format($leaflet->valid_to).')';
    }

    /**
     * Málo akcí: méně než nejvíc z předchozích stažení téhož letáku zmenšené o povolený propad.
     */
    private function offersIssue(LeafletStat $stat): ?QualityIssue
    {
        $baseline = (int) LeafletStat::query()
            ->where('leaflet_id', $stat->leaflet_id)
            ->where('id', '<', $stat->id)
            ->orderByDesc('id')
            ->limit(config()->integer('letaky.data_quality.offers_baseline_runs'))
            ->pluck('offers_count')
            ->max();

        $limit = $baseline * (1 - config()->float('letaky.data_quality.offers_drop_share'));

        return $baseline >= config()->integer('letaky.data_quality.min_offers') && $stat->offers_count < $limit
            ? new QualityIssue(QualityIssue::OFFERS, $stat->offers_count, $baseline)
            : null;
    }

    /**
     * Nízký podíl ověřených cen: proti mediánu ostatních letáků obchodu (poslední statistika
     * každého), bez nich proti nejvyššímu podílu předchozích stažení téhož letáku.
     */
    private function verifiedIssue(LeafletStat $stat): ?QualityIssue
    {
        $minCandidates = config()->integer('letaky.data_quality.min_candidates');
        $current = $stat->verifiedPercent();
        if ($current === null || $stat->tile_candidates < $minCandidates) {
            return null;
        }

        $others = LeafletStat::query()
            ->whereIn('id', LeafletStat::query()
                ->selectRaw('MAX(id)')
                ->where('chain', $stat->chain)
                ->where('leaflet_id', '!=', $stat->leaflet_id)
                ->where('tile_candidates', '>=', $minCandidates)
                ->where('created_at', '>=', CarbonImmutable::now()->subDays(config()->integer('letaky.data_quality.verified_baseline_days')))
                ->groupBy('leaflet_id'))
            ->get()
            ->map(fn (LeafletStat $other): ?int => $other->verifiedPercent())
            ->filter(fn (?int $percent): bool => $percent !== null)
            ->values();

        $baseline = $others->isNotEmpty()
            ? (int) round((float) $others->median())
            : (int) LeafletStat::query()
                ->where('leaflet_id', $stat->leaflet_id)
                ->where('id', '<', $stat->id)
                ->where('tile_candidates', '>=', $minCandidates)
                ->orderByDesc('id')
                ->limit(config()->integer('letaky.data_quality.offers_baseline_runs'))
                ->get()
                ->map(fn (LeafletStat $previous): ?int => $previous->verifiedPercent())
                ->max();

        return $baseline > 0 && $current < $baseline * (1 - config()->float('letaky.data_quality.verified_drop_share'))
            ? new QualityIssue(QualityIssue::VERIFIED, $current, $baseline)
            : null;
    }
}
