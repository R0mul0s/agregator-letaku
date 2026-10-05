<?php

/**
 * Zapíše do centra upozornění (R74) nové akce na hlídané zboží — každému uživateli, i bez
 * zapnutých upozornění v telefonu nebo e-mailu. Cron /cron/send-digests ho volá před
 * upozorněním v telefonu, které se ze záznamů skládá (SendPushNotifications). Akce jsou
 * stejné jako v Mých slevách a v e-mailu (NewOffers).
 *
 * Po dávkách jako souhrny (R54): jedno volání zpracuje nejvýš `letaky.notifications.users_per_run`
 * uživatelů, od nejdéle čekajících; kdo nové akce nemá, je zpracovaný taky (`notified_at`).
 * Nový účet (a každý účet po nasazení) začíná od prvního zpracování — dřívější akce nejsou nové.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Digest\NewOffers;
use App\Domain\Notifications\NewOffersNotification;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class RecordNewOffers
{
    public function __construct(private readonly NewOffers $newOffers) {}

    /**
     * Zpracuje jednu dávku uživatelů, kterým od posledního zpracování doběhlo stažení;
     * vrátí počet zapsaných záznamů. Chyba u jednoho uživatele se zapíše do logu.
     */
    public function __invoke(): int
    {
        // Bez stažení od posledního zpracování nové akce být nemůžou (R58)
        $lastImport = $this->newOffers->lastImportFinishedAt();
        if ($lastImport === null) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $recorded = 0;

        $users = User::query()
            ->where(fn (Builder $query) => $query->whereNull('notified_at')->orWhere('notified_at', '<', $lastImport))
            ->orderBy('notified_at')
            ->orderBy('id')
            ->limit(config()->integer('letaky.notifications.users_per_run'))
            ->get();

        foreach ($users as $user) {
            try {
                if ($user->notified_at !== null && $this->record($user, $user->notified_at)) {
                    $recorded++;
                }
                $user->forceFill(['notified_at' => $now])->save();
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $recorded;
    }

    /**
     * Zapíše uživateli záznam s akcemi, které obchody nabídly po `$since`; bez nich nic.
     */
    private function record(User $user, CarbonImmutable $since): bool
    {
        $groups = array_map(fn (array $group): array => [
            'watchItem' => $group['watchItem']->name,
            'offerIds' => array_map(fn (Offer $offer): int => $offer->id, $group['offers']),
        ], $this->newOffers->forUser($user, $since));

        if ($groups === []) {
            return false;
        }

        $user->notify(new NewOffersNotification($groups));

        return true;
    }
}
