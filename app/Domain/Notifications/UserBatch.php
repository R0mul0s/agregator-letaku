<?php

/**
 * Dávka uživatelů v cronu upozornění a souhrnů (R54, R106). Hlídá dvě věci, které by jinak
 * frontu zastavily:
 *
 * - **časový rozpočet** (Deadline) — dávka skončí, dokud je čas, a zbytek zpracuje další volání;
 *   jinak by hosting požadavek ukončil a kroky na konci cronu (souhrny, telefon) by nedoběhly,
 * - **uživatele, u kterého zpracování opakovaně padá** — dávka je řazená od nejdéle čekajících,
 *   takže by byl první v každé dávce navždy. Po `letaky.cron.user_failures.max_attempts` chybách
 *   se jeho čas posune dál (`$giveUp`), u kanálu bez vlastního času ho vrátí `givenUp()` k vynechání.
 *
 * Počty chyb jsou v cache po kanálech (jeden zápis na dávku, ne na uživatele).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use App\Support\Deadline;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class UserBatch
{
    /** Předpona klíče cache s počty chyb uživatelů jednoho kanálu. */
    private const CACHE_PREFIX = 'cron.user_failures.';

    /**
     * Zpracuje uživatele, dokud nevyprší rozpočet; vrátí, u kolika `$process` vrátil true.
     * Chyba u uživatele se zapíše do logu a ostatní se zpracují dál.
     *
     * @param  iterable<User>  $users
     * @param  Closure(User): bool  $process  Zpracuje uživatele včetně posunu jeho času; true = zapsal / poslal
     * @param  (Closure(User): mixed)|null  $giveUp  Posune čas uživatele, u kterého zpracování opakovaně padá
     * @param  list<class-string<Throwable>>  $stopOn  Chyby, které nejspíš potkají všechny (výpadek pošty) — dávka skončí
     */
    public function run(string $channel, iterable $users, Deadline $deadline, Closure $process, ?Closure $giveUp = null, array $stopOn = []): int
    {
        $failures = $this->failures($channel);
        $changed = false;
        $done = 0;

        foreach ($users as $user) {
            if ($deadline->passed()) {
                break;
            }

            try {
                if ($process($user)) {
                    $done++;
                }
                if (isset($failures[$user->id])) {
                    unset($failures[$user->id]);
                    $changed = true;
                }
            } catch (Throwable $error) {
                report($error);

                // Počítá se i chyba, která dávku ukončí — odmítnutá adresa je pro poštu stejná
                // chyba jako výpadek a uživatel s ní by jinak dávky zastavil navždy
                $failures[$user->id] = ($failures[$user->id] ?? 0) + 1;
                $changed = true;
                if ($giveUp !== null && $failures[$user->id] >= config()->integer('letaky.cron.user_failures.max_attempts')) {
                    $giveUp($user);
                    unset($failures[$user->id]);
                }

                if (array_any($stopOn, fn (string $class): bool => $error instanceof $class)) {
                    break;
                }
            }
        }

        if ($changed) {
            Cache::put(self::CACHE_PREFIX.$channel, $failures, now()->addHours(config()->integer('letaky.cron.user_failures.remember_hours')));
        }

        return $done;
    }

    /**
     * Uživatelé, u kterých zpracování v kanálu padlo tolikrát, že se mají vynechat
     * (kanál bez vlastního času uživatele, který by šlo posunout).
     *
     * @return list<int>
     */
    public function givenUp(string $channel): array
    {
        $max = config()->integer('letaky.cron.user_failures.max_attempts');

        return array_keys(array_filter($this->failures($channel), fn (int $count): bool => $count >= $max));
    }

    /**
     * Počty chyb uživatelů kanálu z cache.
     *
     * @return array<int, int> ID uživatele => počet chyb za sebou
     */
    private function failures(string $channel): array
    {
        $stored = Cache::get(self::CACHE_PREFIX.$channel);
        if (! is_array($stored)) {
            return [];
        }

        $failures = [];
        foreach ($stored as $userId => $count) {
            if (is_int($userId) && is_int($count)) {
                $failures[$userId] = $count;
            }
        }

        return $failures;
    }
}
