<?php

/**
 * Úklid osobních údajů po vypršení (R53): relace (session s IP a prohlížečem) starší
 * než `session.lifetime`, propadlé odkazy pro obnovu hesla a prošlé položky cache (R69) —
 * v cache jsou počítadla limitů požadavků s IP a e-mailem a databázová cache prošlý
 * řádek smaže, jen když se na stejný klíč znovu sáhne. Laravel relace maže jen náhodně
 * při požadavcích (loterie) — zásady slibují průběžné mazání, proto denní cron. Smaže i záznamy
 * centra upozornění starší než `letaky.notifications.retention_days` (R74, zásady kap. 3).
 * Volá ho artisan `letaky:prune-sessions` i cron URL `/cron/prune-sessions`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class PruneExpiredSessions
{
    /**
     * Smaže vypršelé relace, odkazy pro obnovu hesla, prošlou cache a staré záznamy centra
     * upozornění; vrátí počet smazaných relací.
     */
    public function __invoke(): int
    {
        Password::broker()->getRepository()->deleteExpired();
        $this->pruneCache();
        DatabaseNotification::query()
            ->where('created_at', '<', CarbonImmutable::now()->subDays(config()->integer('letaky.notifications.retention_days')))
            ->delete();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        $expiredBefore = CarbonImmutable::now()->subMinutes(config()->integer('session.lifetime'))->getTimestamp();

        return DB::table(config()->string('session.table'))->where('last_activity', '<', $expiredBefore)->delete();
    }

    /**
     * Smaže prošlé položky databázové cache (limity požadavků, zámky stažení). Platné nechá.
     */
    private function pruneCache(): void
    {
        $store = config()->string('cache.default');
        if (config("cache.stores.{$store}.driver") !== 'database') {
            return;
        }

        DB::connection(config("cache.stores.{$store}.connection"))
            ->table(config()->string("cache.stores.{$store}.table"))
            ->where('expiration', '<', CarbonImmutable::now()->getTimestamp())
            ->delete();
    }
}
