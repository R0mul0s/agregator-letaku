<?php

/**
 * Zapíše do centra upozornění (R74, etapa 11b) akce z nákupního seznamu, které zítra končí —
 * jen neodškrtnuté, nestažené obchodem (R16) a ne z obchodů, kde je konec akce jen odhad
 * (Billa, `estimated_validity`). Jednou denně od `letaky.notifications.ending_soon.from_hour`
 * místního času; cron /cron/send-digests ho volá před upozorněním v telefonu, které záznam pošle.
 *
 * Po dávkách (R54): jedno volání zpracuje nejvýš `letaky.notifications.users_per_run` uživatelů;
 * kdo záznam dnes dostal, už se nevybere.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Chains\ChainCatalog;
use App\Domain\Notifications\EndingSoonNotification;
use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Enums\NotificationKind;
use App\Models\Offer;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class RecordEndingOffers
{
    /** Kolik dní před koncem akce upozornit — texty říkají „zítra“. */
    private const DAYS_BEFORE_END = 1;

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly ChainCatalog $chains,
    ) {}

    /**
     * Zpracuje jednu dávku uživatelů s končícími akcemi v seznamu; vrátí počet zapsaných záznamů.
     * Chyba u jednoho uživatele se zapíše do logu.
     */
    public function __invoke(): int
    {
        $localNow = CarbonImmutable::now(config()->string('letaky.display_timezone'));
        if ($localNow->hour < config()->integer('letaky.notifications.ending_soon.from_hour')) {
            return 0;
        }

        $endingOfferIds = $this->endingOffers($this->calendar->today()->addDays(self::DAYS_BEFORE_END))->select('id');
        $recorded = 0;

        $users = User::query()
            ->whereHas('shoppingListItems', fn (Builder $items) => $items->whereNull('checked_at')->whereIn('offer_id', $endingOfferIds))
            // Jednou denně: dnešní záznam už má (začátek místního dne v UTC jako created_at)
            ->whereDoesntHave('notifications', fn (Builder $query) => $query
                ->where('type', NotificationKind::EndingSoon->value)
                ->where('created_at', '>=', $localNow->startOfDay()->utc()))
            ->orderBy('id')
            ->limit(config()->integer('letaky.notifications.users_per_run'))
            ->get();

        foreach ($users as $user) {
            try {
                $offers = $user->shoppingListItems()
                    ->whereNull('checked_at')
                    ->whereIn('offer_id', $endingOfferIds)
                    ->with('offer')
                    ->get()
                    ->map(fn (ShoppingListItem $item): Offer => $item->offer);
                $user->notify(new EndingSoonNotification($this->groupsByChain(array_values($offers->all()))));
                $recorded++;
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $recorded;
    }

    /**
     * Akce, které v daný místní den končí a obchod je nestáhl, mimo obchody s odhadovanou platností.
     *
     * @return Builder<Offer>
     */
    private function endingOffers(CarbonImmutable $endsOn): Builder
    {
        $estimated = array_map(
            fn (Chain $chain): string => $chain->value,
            array_values(array_filter(Chain::cases(), $this->chains->hasEstimatedValidity(...))),
        );

        return Offer::query()->active()
            ->whereDate('valid_to', $endsOn->toDateString())
            ->whereNotIn('chain', $estimated);
    }

    /**
     * Akce po obchodech v pořadí výčtu obchodů (jako nákupní seznam).
     *
     * @param  list<Offer>  $offers
     * @return list<array{title: string, offerIds: list<int>}>
     */
    private function groupsByChain(array $offers): array
    {
        $groups = [];
        foreach (Chain::cases() as $chain) {
            $inChain = array_values(array_filter($offers, fn (Offer $offer): bool => $offer->chain === $chain));
            if ($inChain !== []) {
                $groups[] = ['title' => $chain->label(), 'offerIds' => array_map(fn (Offer $offer): int => $offer->id, $inChain)];
            }
        }

        return $groups;
    }
}
