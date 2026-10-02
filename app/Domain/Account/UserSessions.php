<?php

/**
 * Přihlášení uživatele na různých zařízeních (R40). Session jsou v databázi (R13),
 * takže je jde vypsat i zrušit. S jiným ovladačem session (testy: array) je seznam prázdný.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UserSessions
{
    /**
     * Přihlášení od posledního použitého; aktuální zařízení označené.
     *
     * @return list<array{device: string, ipAddress: string|null, lastActiveAt: string, current: bool}>
     */
    public function forUser(User $user, string $currentSessionId): array
    {
        if (! $this->usesDatabase()) {
            return [];
        }

        // Vypršelé session zůstávají v tabulce, dokud je Laravel občas neuklidí — nejsou to přihlášení
        $activeSince = CarbonImmutable::now()->subMinutes(config()->integer('session.lifetime'))->getTimestamp();
        $sessions = $this->query($user)
            ->where('last_activity', '>=', $activeSince)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $result = [];
        foreach ($sessions as $session) {
            $result[] = [
                'device' => DeviceName::fromUserAgent(is_string($session->user_agent) ? $session->user_agent : null),
                'ipAddress' => is_string($session->ip_address) ? $session->ip_address : null,
                'lastActiveAt' => CarbonImmutable::createFromTimestampUTC((int) $session->last_activity)->toIso8601String(),
                'current' => $session->id === $currentSessionId,
            ];
        }

        return $result;
    }

    /**
     * Odhlásí uživatele všude kromě aktuálního zařízení: smaže ostatní session a změní
     * token „Zapamatovat si mě“, aby se jiná zařízení nepřihlásila znovu z cookie.
     */
    public function logoutOthers(User $user, string $currentSessionId): void
    {
        if ($this->usesDatabase()) {
            $this->query($user)->where('id', '!=', $currentSessionId)->delete();
        }

        $user->setRememberToken(Str::random(60));
        $user->save();
    }

    /**
     * Smaže všechny session uživatele (zrušení účtu).
     */
    public function deleteAll(User $user): void
    {
        if ($this->usesDatabase()) {
            $this->query($user)->delete();
        }
    }

    /**
     * Session uživatele v databázi.
     */
    private function query(User $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config()->string('session.table'))
            ->where('user_id', $user->id);
    }

    /**
     * Jsou session v databázi?
     */
    private function usesDatabase(): bool
    {
        return config('session.driver') === 'database';
    }
}
