<?php

/**
 * Úklid statistik letáků (R129): řádky starší než `letaky.data_quality.retention_days` smaže —
 * přehled kvality dat ukazuje jen poslední stažení a propad se porovnává s nedávnými.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Models\LeafletStat;
use Carbon\CarbonImmutable;

final readonly class PruneLeafletStats
{
    /**
     * Smaže staré statistiky; vrátí počet smazaných řádků.
     */
    public function __invoke(): int
    {
        return LeafletStat::query()
            ->where('created_at', '<', CarbonImmutable::now()->subDays(config()->integer('letaky.data_quality.retention_days')))
            ->delete();
    }
}
