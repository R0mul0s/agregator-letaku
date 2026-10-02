<?php

/**
 * Slevy k hlídaným položkám uživatele ve sledovaných obchodech (R18, R19).
 *
 * Párování se počítá při zobrazení — nabídek je řádově tisíce a hlídaných položek
 * jednotky, tabulka shod zatím není potřeba (R19). Kandidáty předvybere databáze
 * podle prvního slova položek, přesná pravidla pak vyhodnotí WatchItemMatcher.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\UnitPrice;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class MyOffers
{
    /** Sloupce, ve kterých databáze předvybírá kandidáty — stejné jako WatchItemMatcher. */
    private const SEARCHED_COLUMNS = ['name', 'brand', 'description'];

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly WatchItemMatcher $matcher,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Pro každou hlídanou položku nabídky seřazené od nejnižší ceny za jednotku,
     * shody před „možná“. Ceny s kartou počítá jen u karet, které uživatel má.
     *
     * @return list<array{watchItem: WatchItem, offers: list<array{offer: Offer, status: MatchStatus}>}>
     */
    public function forUser(User $user): array
    {
        $watchItems = $user->watchItems()->orderBy('name')->get();
        $followed = $user->followedChains()->get();

        $rules = [];
        foreach ($watchItems as $item) {
            $rules[$item->id] = WatchRule::fromWatchItem($item, $this->normalizer);
        }

        $candidates = $followed->isEmpty() || $rules === []
            ? new Collection
            : $this->candidates($followed, $rules);

        $groups = [];
        foreach ($watchItems as $item) {
            $groups[] = ['watchItem' => $item, 'offers' => $this->matches($user, $rules[$item->id], $candidates)];
        }

        return $groups;
    }

    /**
     * Nabídky položky podle pravidel; akce jen s kartou, kterou uživatel nemá, vynechá.
     *
     * @param  Collection<int, Offer>  $candidates
     * @return list<array{offer: Offer, status: MatchStatus}>
     */
    private function matches(User $user, WatchRule $rule, Collection $candidates): array
    {
        $matches = [];
        foreach ($candidates as $offer) {
            $status = $this->matcher->match($rule, $offer);
            if ($status !== null && $this->isAvailableTo($user, $offer)) {
                $matches[] = ['offer' => $offer, 'status' => $status];
            }
        }

        usort($matches, fn (array $a, array $b): int => $this->sortKey($user, $a) <=> $this->sortKey($user, $b));

        return $matches;
    }

    /**
     * Pořadí nabídky: shody před „možná“, akce s cenou před akcemi na více kusů (ty mají
     * jen běžnou cenu, jejich cena za jednotku slevu neukazuje), pak od nejnižší ceny.
     *
     * @param  array{offer: Offer, status: MatchStatus}  $match
     * @return array{bool, bool, int, int}
     */
    private function sortKey(User $user, array $match): array
    {
        return [
            $match['status'] === MatchStatus::Maybe,
            $match['offer']->offer_type === OfferType::Multibuy,
            ...$this->sortPrice($user, $match['offer']),
        ];
    }

    /**
     * Neskončené nabídky sledovaných obchodů podle jejich upřesnění, které obsahují
     * aspoň jednu alternativu prvního slova některé položky.
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  array<int, WatchRule>  $rules
     * @return Collection<int, Offer>
     */
    private function candidates(Collection $followed, array $rules): Collection
    {
        $firstWords = array_unique(array_merge(...array_map(fn (WatchRule $rule): array => $rule->keywords[0] ?? [], array_values($rules))));

        return Offer::query()
            ->active()
            ->notExpired($this->calendar->today())
            ->where(function (Builder $query) use ($followed): void {
                foreach ($followed as $chain) {
                    $query->orWhere(fn (Builder $query) => $this->whereFollowed($query, $chain));
                }
            })
            ->where(function (Builder $query) use ($firstWords): void {
                foreach ($firstWords as $word) {
                    foreach (self::SEARCHED_COLUMNS as $column) {
                        $query->orWhere($column, 'like', '%'.addcslashes($word, '%_\\').'%');
                    }
                }
            })
            ->get();
    }

    /**
     * Nabídky jednoho sledovaného obchodu: typ prodejny (nabídka bez typu platí všude)
     * a akce jen z e-shopu podle volby uživatele.
     *
     * @param  Builder<Offer>  $query
     */
    private function whereFollowed(Builder $query, FollowedChain $chain): void
    {
        $query->where('chain', $chain->chain);

        if ($chain->store_format !== null) {
            $query->where(fn (Builder $query) => $query->whereNull('store_format')->orWhere('store_format', $chain->store_format));
        }

        if (! $chain->include_online_only) {
            $query->where('online_only', false);
        }
    }

    /**
     * Je nabídka pro uživatele akcí? Akce jen s kartou bez karty není.
     */
    private function isAvailableTo(User $user, Offer $offer): bool
    {
        return $offer->offer_type !== OfferType::LoyaltyOnly
            || ($offer->loyalty_program !== null && $user->hasLoyaltyProgram($offer->loyalty_program));
    }

    /**
     * Klíč řazení: cena za jednotku, kterou uživatel zaplatí (s kartou, pokud ji má), jinak cena;
     * nabídky bez ceny na konec.
     *
     * @return array{int, int}
     */
    private function sortPrice(User $user, Offer $offer): array
    {
        $price = $this->userPrice($user, $offer);
        $unitPrice = UnitPrice::of($price, $offer->quantity, $offer->unit);

        return [$unitPrice ?? PHP_INT_MAX, $price ?? PHP_INT_MAX];
    }

    /**
     * Cena, kterou uživatel zaplatí: s kartou, pokud ji má a je nižší, jinak cena bez karty.
     */
    public function userPrice(User $user, Offer $offer): ?int
    {
        $hasCard = $offer->loyalty_program !== null && $user->hasLoyaltyProgram($offer->loyalty_program);

        if ($hasCard && $offer->loyalty_price !== null) {
            return $offer->price === null ? $offer->loyalty_price : min($offer->price, $offer->loyalty_price);
        }

        return $offer->price;
    }
}
