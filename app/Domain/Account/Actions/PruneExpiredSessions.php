<?php

/**
 * Úklid osobních údajů po vypršení (R53): relace (session s IP a prohlížečem) starší
 * než `session.lifetime` a propadlé odkazy pro obnovu hesla. Laravel relace maže jen
 * náhodně při požadavcích (loterie) — zásady slibují průběžné mazání, proto denní cron.
 * Volá ho artisan `letaky:prune-sessions` i cron URL `/cron/prune-sessions`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class PruneExpiredSessions
{
    /**
     * Smaže vypršelé relace a odkazy pro obnovu hesla; vrátí počet smazaných relací.
     */
    public function __invoke(): int
    {
        Password::broker()->getRepository()->deleteExpired();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        $expiredBefore = CarbonImmutable::now()->subMinutes(config()->integer('session.lifetime'))->getTimestamp();

        return DB::table(config()->string('session.table'))->where('last_activity', '<', $expiredBefore)->delete();
    }
}
