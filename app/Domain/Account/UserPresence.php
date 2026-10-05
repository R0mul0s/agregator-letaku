<?php

/**
 * Poslední aktivita uživatele (R84): zápis času posledního požadavku a kdo je „online“.
 * Zápis nejvýš jednou za `letaky.account.presence.touch_interval_seconds`, aby každý
 * požadavek nezapisoval do tabulky uživatelů; bez změny `updated_at` (není to úprava účtu).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\User;
use Carbon\CarbonImmutable;

final class UserPresence
{
    /**
     * Zapíše čas aktivity, pokud od posledního zápisu uběhl interval.
     */
    public function touch(User $user): void
    {
        $now = CarbonImmutable::now();
        $interval = config()->integer('letaky.account.presence.touch_interval_seconds');
        if ($user->last_seen_at !== null && $user->last_seen_at->gt($now->subSeconds($interval))) {
            return;
        }

        User::query()->whereKey($user->id)->toBase()->update(['last_seen_at' => $now]);
        $user->forceFill(['last_seen_at' => $now])->syncOriginalAttribute('last_seen_at');
    }

    /**
     * Od kdy počítá aktivita jako „online“ (UTC).
     */
    public function onlineSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes(config()->integer('letaky.account.presence.online_minutes'));
    }

    /**
     * Je uživatel teď online?
     */
    public function isOnline(User $user): bool
    {
        return $user->last_seen_at !== null && $user->last_seen_at->gte($this->onlineSince());
    }
}
