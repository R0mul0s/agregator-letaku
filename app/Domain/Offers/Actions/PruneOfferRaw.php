<?php

/**
 * Úklid surových odpovědí obchodů (R113): akce se nemažou (R10), ale `offers.raw` u nich
 * aplikace nikdy nečte — slouží k ladění zdroje a přepočtu aktuálních akcí. U akcí skončených
 * před `letaky.offers.raw_retention_days` dny ho vyprázdní, jinak by tabulka na sdíleném
 * hostingu rostla o stovky MB ročně. Volá ho denní úklid (`/cron/prune-sessions`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\LocalCalendar;
use App\Models\Offer;

final readonly class PruneOfferRaw
{
    /** Vyprázdněná surová odpověď — sloupec je povinný JSON. */
    private const EMPTY_RAW = '[]';

    public function __construct(private LocalCalendar $calendar) {}

    /**
     * Vyprázdní `raw` starých akcí po dávkách; vrátí počet upravených akcí.
     */
    public function __invoke(): int
    {
        $endedBefore = $this->calendar->today()->subDays(config()->integer('letaky.offers.raw_retention_days'))->toDateString();
        $batch = config()->integer('letaky.offers.raw_prune_batch');

        $pruned = 0;
        do {
            $updated = Offer::query()
                ->where('valid_to', '<', $endedBefore)
                ->where('raw', '!=', self::EMPTY_RAW)
                ->limit($batch)
                ->toBase()
                ->update(['raw' => self::EMPTY_RAW]);
            $pruned += $updated;
        } while ($updated === $batch);

        return $pruned;
    }
}
