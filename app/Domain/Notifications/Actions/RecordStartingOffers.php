<?php

/**
 * Zapíše do centra upozornění (R76) akce, které dnes začínají a obchod je zveřejnil dopředu —
 * hlídané akce z Mých slev (sekce „Brzy“) a neodškrtnuté akce z nákupního seznamu. Akce, kterou
 * obchod zveřejnil až dnes, sem nepatří: ohlásí ji záznam o nových akcích. Jednou denně
 * od `letaky.notifications.starting_today.from_hour` místního času; cron /cron/send-digests
 * ho volá před upozorněním v telefonu, které záznam pošle.
 *
 * Po dávkách (R54): jedno volání zpracuje nejvýš `letaky.notifications.users_per_run` uživatelů
 * s hlídanými položkami nebo s dnes začínající akcí v seznamu. Které hlídané akce začínají, se
 * pozná až párováním, proto dávky postupují podle ID uživatele (poslední zpracované je v cache
 * do konce dne) — uživatel bez začínajících akcí záznam nedostane, a přesto se znovu nevybere.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Matching\MyOffers;
use App\Domain\Notifications\StartingTodayNotification;
use App\Domain\Offers\LocalCalendar;
use App\Enums\NotificationKind;
use App\Models\Offer;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RecordStartingOffers
{
    /** Klíč cache s ID posledního zpracovaného uživatele; doplní se místní datum. */
    private const CURSOR_KEY = 'notifications.starting_today.after_user.';

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly MyOffers $myOffers,
    ) {}

    /**
     * Zpracuje jednu dávku uživatelů; vrátí počet zapsaných záznamů. Chyba u jednoho
     * uživatele se zapíše do logu.
     */
    public function __invoke(): int
    {
        $localNow = CarbonImmutable::now(config()->string('letaky.display_timezone'));
        if ($localNow->hour < config()->integer('letaky.notifications.starting_today.from_hour')) {
            return 0;
        }

        // Zveřejněné dřív než dnes (začátek místního dne v UTC jako created_at)
        $publishedBefore = $localNow->startOfDay()->utc();
        $startingOfferIds = array_values(array_map(intval(...), $this->startingOffers($publishedBefore)->pluck('id')->all()));
        if ($startingOfferIds === []) {
            return 0;
        }

        $cursorKey = self::CURSOR_KEY.$localNow->toDateString();
        $users = User::query()
            ->where('id', '>', Cache::integer($cursorKey, 0))
            ->where(fn (Builder $query) => $query
                ->whereHas('watchItems')
                ->orWhereHas('shoppingListItems', fn (Builder $items) => $items->whereNull('checked_at')->whereIn('offer_id', $startingOfferIds)))
            // Jednou denně: dnešní záznam už má (pojistka, kdyby se cache ztratila)
            ->whereDoesntHave('notifications', fn (Builder $query) => $query
                ->where('type', NotificationKind::StartingToday->value)
                ->where('created_at', '>=', $publishedBefore))
            ->orderBy('id')
            ->limit(config()->integer('letaky.notifications.users_per_run'))
            ->get();

        $recorded = 0;
        foreach ($users as $user) {
            try {
                if ($this->record($user, $startingOfferIds)) {
                    $recorded++;
                }
            } catch (Throwable $error) {
                report($error);
            }
        }

        if ($users->isNotEmpty()) {
            Cache::put($cursorKey, $users->last()->id, $localNow->endOfDay());
        }

        return $recorded;
    }

    /**
     * Zapíše uživateli záznam se začínajícími hlídanými akcemi (skupiny po hlídaných položkách)
     * a akcemi ze seznamu, které mezi nimi nejsou (skupina „Nákupní seznam“); bez nich nic.
     *
     * @param  list<int>  $startingOfferIds
     */
    private function record(User $user, array $startingOfferIds): bool
    {
        $isStarting = fn (Offer $offer): bool => in_array($offer->id, $startingOfferIds, true);
        $groups = [];
        $seen = [];
        foreach ($this->myOffers->forUser($user, withMentions: false) as $group) {
            $ids = array_values(array_diff(
                array_map(fn (Offer $offer): int => $offer->id, array_filter(array_column($group['offers'], 'offer'), $isStarting)),
                $seen,
            ));
            if ($ids !== []) {
                $groups[] = ['title' => $group['watchItem']->name, 'offerIds' => $ids];
                $seen = [...$seen, ...$ids];
            }
        }

        $listed = $user->shoppingListItems()
            ->whereNull('checked_at')
            ->whereIn('offer_id', $startingOfferIds)
            ->whereNotIn('offer_id', $seen)
            ->orderBy('id')
            ->get()
            ->map(fn (ShoppingListItem $item): int => $item->offer_id)
            ->all();
        if ($listed !== []) {
            $groups[] = ['title' => __('app.notifications.starting_today.shopping_list'), 'offerIds' => array_values($listed)];
        }

        if ($groups === []) {
            return false;
        }

        $user->notify(new StartingTodayNotification($groups));

        return true;
    }

    /**
     * Akce, které dnes začínají, obchod je nestáhl a zveřejnil je před daným okamžikem.
     *
     * @return Builder<Offer>
     */
    private function startingOffers(CarbonImmutable $publishedBefore): Builder
    {
        return Offer::query()->active()
            ->whereDate('valid_from', $this->calendar->today()->toDateString())
            ->where('created_at', '<', $publishedBefore);
    }
}
