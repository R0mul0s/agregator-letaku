<?php

/**
 * Denní úklid cronu: vypršelé relace a odkazy pro obnovu hesla (R53), surové odpovědi
 * starých akcí (R113), staré statistiky letáků (R129) a staré návrhy, co hlídat (R148). Volá ho cron URL `/cron/prune-sessions` i `letaky:prune-sessions`;
 * výsledek se zapíše pro hlídání `/health/tasks` (R115).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Offers\Actions\PruneLeafletStats;
use App\Domain\Offers\Actions\PruneOfferRaw;
use App\Domain\Shopping\WatchSuggestions;
use App\Enums\CronTask;
use App\Support\TaskHeartbeats;

final readonly class RunMaintenance
{
    public function __construct(
        private PruneExpiredSessions $pruneSessions,
        private PruneOfferRaw $pruneOfferRaw,
        private PruneLeafletStats $pruneLeafletStats,
        private WatchSuggestions $watchSuggestions,
    ) {}

    /**
     * Uklidí; vrátí počet smazaných relací, upravených akcí, smazaných statistik letáků
     * a smazaných návrhů, co hlídat.
     *
     * @return array{sessions: int, offer_raw: int, leaflet_stats: int, watch_suggestions: int}
     */
    public function __invoke(): array
    {
        return TaskHeartbeats::run(CronTask::Maintenance, fn (): array => [
            'sessions' => ($this->pruneSessions)(),
            'offer_raw' => ($this->pruneOfferRaw)(),
            'leaflet_stats' => ($this->pruneLeafletStats)(),
            'watch_suggestions' => $this->watchSuggestions->prune(),
        ]);
    }
}
