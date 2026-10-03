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

use App\Domain\Account\Actions\PruneExpiredSessions;
use Illuminate\Console\Command;

class PruneSessionsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:prune-sessions';

    /** @var string */
    protected $description = 'Smaže vypršelé relace a propadlé odkazy pro obnovu hesla';

    /**
     * Uklidí a vypíše počet smazaných relací.
     */
    public function handle(PruneExpiredSessions $prune): int
    {
        $this->info(__('app.maintenance.sessions_pruned', ['count' => $prune()]));

        return self::SUCCESS;
    }
}
