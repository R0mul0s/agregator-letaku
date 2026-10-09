<?php

/**
 * Denní úklid cronu: vypršelé relace a odkazy pro obnovu hesla (R53) a surové odpovědi
 * starých akcí (R113). Volá ho cron URL `/cron/prune-sessions` i `letaky:prune-sessions`;
 * výsledek se zapíše pro hlídání `/health/tasks` (R115).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Offers\Actions\PruneOfferRaw;
use App\Enums\CronTask;
use App\Support\TaskHeartbeats;

final readonly class RunMaintenance
{
    public function __construct(
        private PruneExpiredSessions $pruneSessions,
        private PruneOfferRaw $pruneOfferRaw,
    ) {}

    /**
     * Uklidí; vrátí počet smazaných relací a upravených akcí.
     *
     * @return array{sessions: int, offer_raw: int}
     */
    public function __invoke(): array
    {
        return TaskHeartbeats::run(CronTask::Maintenance, fn (): array => [
            'sessions' => ($this->pruneSessions)(),
            'offer_raw' => ($this->pruneOfferRaw)(),
        ]);
    }
}
