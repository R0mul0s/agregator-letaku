<?php

/**
 * Zapíše statistiku letáků stažení (R129): za každý leták z dávek počet akcí, které v něm zdroj
 * stáhl, akce převzaté jiným zdrojem obchodu (Lidl: web, R136) a u letáků z PDF nebo SVG nalezené
 * a ověřené ceny z parseru. Leták bez akce dostane řádek s nulou — rozbitý parser je vidět.
 *
 * Počet akcí je z dávek, ne z uložených řádků (R150): akci, kterou nese víc kampaní (Lidl: okurka
 * v „Ovoci a zelenině“ i ve „Čtvrteční nabídce“), uloží jen první dávka — podle `leaflet_id` by
 * ostatním kampaním chyběla a počet by skákal s pořadím kampaní na úvodní stránce.
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
        /** @var array<int, int> $covered ID letáku => akce převzaté jiným zdrojem obchodu (R136) */
        $covered = [];
        /** @var array<int, array<string, true>> $offers ID letáku => klíče jeho akcí (víc dávek jednoho letáku bez zdvojení) */
        $offers = [];
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
            $covered[$leafletId] = ($covered[$leafletId] ?? 0) + $batch->coveredOffers;
            $offers[$leafletId] ??= [];
            foreach ($batch->offers as $offer) {
                $offers[$leafletId][$offer->key()] = true;
            }
        }

        $now = CarbonImmutable::now();
        $rows = [];
        foreach (array_keys($offers) as $leafletId) {
            $stats = $tiles[$leafletId] ?? null;
            $rows[] = [
                'scrape_run_id' => $run->id,
                'leaflet_id' => $leafletId,
                'chain' => $chain->value,
                'offers_count' => count($offers[$leafletId]),
                'covered_offers' => $covered[$leafletId] ?? 0,
                'tile_candidates' => $stats?->candidates,
                'tiles_verified' => $stats?->verified,
                'created_at' => $now,
            ];
        }

        LeafletStat::query()->insert($rows);
    }
}
