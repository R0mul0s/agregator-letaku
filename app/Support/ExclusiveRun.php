<?php

/**
 * Úloha cronu, která smí běžet jen jednou najednou (R113) — cron WebAdminu může URL zavolat
 * znovu, než předchozí volání doběhne, a ruční spuštění se může potkat s cronem. Zámek v cache
 * vyprší sám (`letaky.cron.lock_seconds`), kdyby hosting proces ukončil dřív, než ho uvolní.
 *
 * Stažení akcí obchodu má vlastní zámek se záznamem v `scrape_runs` (R57).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support;

use App\Support\Exceptions\AlreadyRunning;
use Closure;
use Illuminate\Support\Facades\Cache;

final class ExclusiveRun
{
    /** Předpona klíčů zámků v cache. */
    private const LOCK_PREFIX = 'cron.exclusive.';

    /**
     * Spustí úlohu pod zámkem a vrátí její výsledek.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $task
     * @return TResult
     *
     * @throws AlreadyRunning Úloha se stejným klíčem už běží
     */
    public static function run(string $key, Closure $task): mixed
    {
        $lock = Cache::lock(self::LOCK_PREFIX.$key, config()->integer('letaky.cron.lock_seconds'));
        if (! $lock->get()) {
            throw new AlreadyRunning($key);
        }

        try {
            return $task();
        } finally {
            $lock->release();
        }
    }
}
