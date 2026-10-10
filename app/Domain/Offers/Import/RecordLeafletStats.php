<?php

/**
 * Zapíše statistiku letáků stažení (R129): za každý leták z dávek počet akcí, které stažení
 * uložilo (akce s `scrape_run_id` tohoto stažení), a u letáků z PDF nebo SVG nalezené a ověřené
 * ceny z parseru. Leták bez akce dostane řádek s nulou — rozbitý parser je vidět.
 *
 * Běží v transakci importu po uložení dávek, jen u stažení, které nespadlo.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers\Import;

use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Data\TileStats;
use App\Enums\Chain;
use App\Models\Leaflet;
use App\Models\LeafletStat;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;

final class RecordLeafletStats
{
    /**
     * Zapíše řádek za každý leták stažení.
     *
     * @param  list<SourceBatch>  $batches
     */
    public function record(Chain $chain, ScrapeRun $run, array $batches): void
    {
        /** @var array<int, TileStats|null> $tiles ID letáku => ceny z parseru (null = leták z API) */
        $tiles = [];
        foreach ($batches as $batch) {
            $leafletId = Leaflet::query()
                ->where('chain', $chain)
                ->where('kind', $batch->leaflet->kind)
                ->where('external_id', $batch->leaflet->externalId)
                ->value('id');
            if (! is_int($leafletId)) {
                continue;
            }
            // Víc dávek jednoho letáku se sečte
            $previous = $tiles[$leafletId] ?? null;
            $tiles[$leafletId] = $batch->tiles === null ? $previous : ($previous ?? new TileStats)->plus($batch->tiles);
        }

        $counts = Offer::query()
            ->where('scrape_run_id', $run->id)
            ->groupBy('leaflet_id')
            ->selectRaw('leaflet_id, COUNT(*) AS offers_count')
            ->pluck('offers_count', 'leaflet_id');

        $now = CarbonImmutable::now();
        $rows = [];
        foreach (array_unique([...array_keys($tiles), ...$counts->keys()->map(intval(...))->all()]) as $leafletId) {
            $stats = $tiles[$leafletId] ?? null;
            $rows[] = [
                'scrape_run_id' => $run->id,
                'leaflet_id' => $leafletId,
                'chain' => $chain->value,
                'offers_count' => (int) ($counts[$leafletId] ?? 0),
                'tile_candidates' => $stats?->candidates,
                'tiles_verified' => $stats?->verified,
                'created_at' => $now,
            ];
        }

        LeafletStat::query()->insert($rows);
    }
}
