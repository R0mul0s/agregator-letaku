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
use App\Domain\Offers\PriceHistory;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class RecordNewOffers
{
    public function __construct(
        private readonly NewOffers $newOffers,
        private readonly PriceHistory $priceHistory,
    ) {}

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
     * Zapíše uživateli záznam s akcemi, které obchody nabídly po `$since`, i s tím, které
     * z nich jsou nejlevnější za sledované období (etapa 11c); bez nových akcí nic.
     */
    private function record(User $user, CarbonImmutable $since): bool
    {
        $newGroups = $this->newOffers->forUser($user, $since);
        if ($newGroups === []) {
            return false;
        }

        $groups = array_map(fn (array $group): array => [
            'title' => $group['watchItem']->name,
            'offerIds' => array_map(fn (Offer $offer): int => $offer->id, $group['offers']),
        ], $newGroups);

        $history = $this->priceHistory->forOffers(array_merge(...array_column($newGroups, 'offers')));
        $lowest = array_keys(array_filter($history, fn (array $comparison): bool => $comparison['status'] === PriceHistory::LOWEST));

        $user->notify(new NewOffersNotification($groups, $lowest));

        return true;
    }
}
