<?php

/**
 * Artisan: úklid vypršelých relací a odkazů pro obnovu hesla (R53) — obálka nad
 * PruneExpiredSessions. Na produkci totéž volá cron URL /cron/prune-sessions.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Actions\RunMaintenance;
use Illuminate\Console\Command;

class PruneSessionsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:prune-sessions';

    /** @var string */
    protected $description = 'Smaže vypršelé relace a propadlé odkazy pro obnovu hesla a vyprázdní surové odpovědi starých akcí';

    /**
     * Uklidí a vypíše počet smazaných relací a upravených akcí.
     */
    public function handle(RunMaintenance $maintenance): int
    {
        $result = $maintenance();
        $this->info(__('app.maintenance.sessions_pruned', ['count' => $result['sessions']]));
        $this->info(__('app.maintenance.offer_raw_pruned', ['count' => $result['offer_raw']]));

        return self::SUCCESS;
    }
}
