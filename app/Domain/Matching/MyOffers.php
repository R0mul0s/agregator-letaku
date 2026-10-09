<?php

/**
 * Slevy k hlídaným položkám uživatele ve sledovaných obchodech (R18, R19).
 *
 * Položka z katalogu (R31) bere nabídky z uloženého přiřazení k produktu (`offer_product`).
 * Položka s vlastními slovy se páruje při zobrazení — nabídek je řádově tisíce a hlídaných
 * položek jednotky (R19). Kandidáty předvybere databáze podle nejdelšího slova položek
 * (WatchRule::prefilterTerm), přesná pravidla pak vyhodnotí WatchItemMatcher.
 *
 * K tomu zmínky na stránkách letáků bez ceny (R27, LeafletMentions).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Chains\ShoppingPreferencesScope;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\UserPricing;
use App\Enums\MatchStatus;
use App\Enums\OffersSort;
use App\Enums\OfferType;
use App\Models\FollowedChain;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\User;
use App\Models\WatchItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class MyOffers
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly WatchItemMatcher $matcher,
        private readonly LocalCalendar $calendar,
        private readonly ShoppingPreferencesScope $preferences,
        private readonly UserPricing $pricing,
        private readonly LeafletMentions $mentions,
    ) {}

    /**
     * Pro každou hlídanou položku nabídky seřazené od nejnižší ceny za jednotku,
     * shody před „možná“. Ceny s kartou počítá jen u karet, které uživatel má. Akce, které
     * ještě nezačaly, jsou zvlášť v `upcoming` (R76), seřazené stejně. K tomu zmínky
     * v letácích bez ceny.
     *
     * @param  bool  $withMentions  Hledat i zmínky v letácích (souhrn je nepotřebuje)
     * @param  bool  $allStores  Akce všech prodejen, ne jen vybraných (R49) — dočasné přepnutí
     *                           v Mých slevách, třeba na cestách (R101); nastavení se nemění
     * @return list<array{watchItem: WatchItem, offers: list<array{offer: Offer, status: MatchStatus}>, upcoming: list<array{offer: Offer, status: MatchStatus}>, mentions: list<array{page: LeafletPage, status: MatchStatus}>}>
     */
    public function forUser(User $user, bool $withMentions = true, bool $allStores = false): array
    {
        $watchItems = $user->watchItems()->with('product')->orderBy('name')->get();
        $followed = $user->followedChains()->get();
        if ($allStores) {
            // Jen v paměti pro tento výpis, neukládá se
            $followed->each(fn (FollowedChain $chain) => $chain->store_codes = null);
        }

        $rules = [];
        $keywordRules = [];
        foreach ($watchItems as $item) {
            $rules[$item->id] = WatchRule::fromWatchItem($item, $this->normalizer);
            if ($item->product_id === null) {
                $keywordRules[$item->id] = $rules[$item->id];
            }
        }
        $productIds = array_values(array_unique(array_filter(array_map(
            fn (WatchItem $item): ?int => $item->product_id,
            $watchItems->all(),
        ))));

        $searchable = ! $followed->isEmpty() && $rules !== [];
        $today = $this->calendar->today();
        $pages = $searchable && $withMentions ? $this->mentions->candidatePages($followed, $rules) : [];
        // Akce, které už skončily, ale platily v období letáku se zmínkou, zmínku taky skryjí
        // (R107) — „Banány 5.–7. 10.“ v letáku platném do 11. 10. už nejsou ve slevě
        $endedSince = $this->mentions->earliestLeafletStart($pages, $today);
        $candidates = $searchable && $keywordRules !== [] ? $this->candidates($followed, $keywordRules, $endedSince) : [];
        $assignments = $searchable && $productIds !== [] ? $this->assignments($followed, $productIds, $endedSince) : new Collection;

        $groups = [];
        foreach ($watchItems as $item) {
            $found = $item->product_id === null
                ? $this->matchByRule($rules[$item->id], $candidates)
                : $this->matchByProduct($item->product_id, $assignments);
            $all = $this->availableSorted($user, $found);
            $offers = array_values(array_filter($all, fn (array $match): bool => ! $match['offer']->valid_to->lessThan($today)));
            $isUpcoming = fn (array $match): bool => $match['offer']->isUpcoming($today);
            $groups[] = [
                'watchItem' => $item,
                'offers' => array_values(array_filter($offers, fn (array $match): bool => ! $isUpcoming($match))),
                'upcoming' => array_values(array_filter($offers, $isUpcoming)),
                'mentions' => $this->mentions->forRule($rules[$item->id], $pages, array_column($all, 'offer')),
            ];
        }

        return $groups;
    }

    /**
     * Pro každý produkt katalogu kolik akcí by uživatel v Mých slevách viděl a nejnižší cenu,
     * kterou zaplatí (R71) — našeptávač v Hlídám ukáže, jestli se hlídání vyplatí. Stejná
     * pravidla jako Moje slevy: sledované obchody a jejich upřesnění, karty, minimální sleva.
     *
     * @return array<int, array{count: int, lowestPrice: int|null}> Klíč je ID produktu; produkt bez akcí chybí
     */
    public function productStats(User $user): array
    {
        $followed = $user->followedChains()->get();
        if ($followed->isEmpty()) {
            return [];
        }

        $offersByProduct = [];
        $assignments = OfferProduct::query()
            ->whereHas('offer', fn (Builder $query) => $this->whereCurrentFollowed($query, $followed))
            ->with(['offer' => fn ($query) => $query->withoutRaw()])
            ->get();
        foreach ($assignments as $assignment) {
            if ($this->isAvailableTo($user, $assignment->offer) && $this->meetsMinDiscount($user, $assignment->offer)) {
                $offersByProduct[$assignment->product_id][$assignment->offer_id] = $assignment->offer;
            }
        }

        return array_map(fn (array $offers): array => [
            'count' => count($offers),
            'lowestPrice' => $this->pricing->lowest($user, array_values($offers)),
        ], $offersByProduct);
    }

    /**
     * Akce, které by našla hlídaná položka s vlastními slovy — náhled ve formuláři a v našeptávači
     * Hlídám (R71), než ji uživatel uloží. Stejná pravidla jako Moje slevy, seřazené jako v nich.
     *
     * @return list<array{offer: Offer, status: MatchStatus}>
     */
    public function preview(User $user, WatchRule $rule): array
    {
        $followed = $user->followedChains()->get();
        if ($followed->isEmpty() || $rule->keywords === []) {
            return [];
        }

        return $this->availableSorted($user, $this->matchByRule($rule, $this->candidates($followed, [$rule])));
    }

    /**
     * Nabídky položky s vlastními slovy podle jejích pravidel.
     *
     * @param  list<array{offer: Offer, prepared: array{text: string, isPetFood: bool}}>  $candidates
     * @return list<array{offer: Offer, status: MatchStatus}>
     */
    private function matchByRule(WatchRule $rule, array $candidates): array
    {
        $matches = [];
        foreach ($candidates as ['offer' => $offer, 'prepared' => $prepared]) {
            $status = $this->matcher->matchPrepared($rule, $offer, $prepared);
            if ($status !== null) {
                $matches[] = ['offer' => $offer, 'status' => $status];
            }
        }

        return $matches;
    }

    /**
     * Nabídky položky z katalogu — uložené přiřazení k produktu (R30, R31).
     *
     * @param  Collection<int, OfferProduct>  $assignments
     * @return list<array{offer: Offer, status: MatchStatus}>
     */
    private function matchByProduct(int $productId, Collection $assignments): array
    {
        return array_values($assignments
            ->where('product_id', $productId)
            ->map(fn (OfferProduct $assignment): array => ['offer' => $assignment->offer, 'status' => $assignment->status])
            ->all());
    }

    /**
     * Vynechá akce jen s kartou, kterou uživatel nemá, a akce pod jeho minimální slevou (R41);
     * zbytek seřadí podle jeho předvolby (sortKey).
     *
     * @param  list<array{offer: Offer, status: MatchStatus}>  $matches
     * @return list<array{offer: Offer, status: MatchStatus}>
     */
    private function availableSorted(User $user, array $matches): array
    {
        $available = array_values(array_filter(
            $matches,
            fn (array $match): bool => $this->isAvailableTo($user, $match['offer']) && $this->meetsMinDiscount($user, $match['offer']),
        ));
        usort($available, fn (array $a, array $b): int => $this->sortKey($user, $a) <=> $this->sortKey($user, $b));

        return $available;
    }

    /**
     * Splní akce minimální slevu uživatele? Bez nastavené hranice ano; akce bez známé slevy
     * (akční cena, akce na více kusů) hranici nesplní.
     */
    private function meetsMinDiscount(User $user, Offer $offer): bool
    {
        return $user->min_discount_percent === null
            || ($offer->effectiveDiscountPercent() ?? 0) >= $user->min_discount_percent;
    }

    /**
     * Přiřazení produktů k nabídkám sledovaných obchodů podle jejich upřesnění, které
     * neskončily před daným dnem.
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  list<int>  $productIds
     * @return Collection<int, OfferProduct>
     */
    private function assignments(Collection $followed, array $productIds, ?CarbonImmutable $endedSince = null): Collection
    {
        return OfferProduct::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('offer', fn (Builder $query) => $this->whereCurrentFollowed($query, $followed, $endedSince))
            ->with(['offer' => fn ($query) => $query->withoutRaw(), 'offer.stores'])
            ->get();
    }

    /**
     * Pořadí nabídky: shody před „možná“, pak podle předvolby uživatele (R41) — od nejnižší
     * ceny za jednotku (akce na více kusů na konec: mají jen běžnou cenu, jejich cena za
     * jednotku slevu neukazuje), od nejvyšší slevy, nebo od nejbližšího konce platnosti.
     * Při shodě rozhoduje cena za jednotku.
     *
     * @param  array{offer: Offer, status: MatchStatus}  $match
     * @return list<bool|int|string>
     */
    private function sortKey(User $user, array $match): array
    {
        $offer = $match['offer'];

        return [
            $match['status'] === MatchStatus::Maybe,
            ...match ($user->offers_sort) {
                OffersSort::UnitPrice => [$offer->offer_type === OfferType::Multibuy],
                OffersSort::Discount => [-($offer->effectiveDiscountPercent() ?? 0)],
                OffersSort::EndingSoon => [$offer->valid_to->toDateString()],
            },
            ...$this->pricing->sortKey($user, $offer),
        ];
    }

    /**
     * Nabídky sledovaných obchodů podle jejich upřesnění, které neskončily před daným dnem (bez
     * něj před dneškem) a obsahují
     * aspoň jednu alternativu slova pro předvýběr některé položky; s textem pro párování,
     * normalizovaným jednou pro všechny položky (R54).
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  array<int, WatchRule>  $rules
     * @return list<array{offer: Offer, prepared: array{text: string, isPetFood: bool}}>
     */
    private function candidates(Collection $followed, array $rules, ?CarbonImmutable $endedSince = null): array
    {
        $offers = Offer::query()
            ->withoutRaw()
            ->tap(fn (Builder $query) => $this->whereCurrentFollowed($query, $followed, $endedSince))
            ->tap(fn (Builder $query) => OfferPrefilter::containingAny($query, OfferPrefilter::wordsOf($rules)))
            ->with('stores')
            ->get();

        return array_map(fn (Offer $offer): array => ['offer' => $offer, 'prepared' => $this->matcher->prepare($offer)], array_values($offers->all()));
    }

    /**
     * Obchodem nestažené nabídky sledovaných obchodů podle jejich upřesnění, které neskončily
     * před daným dnem (bez něj před dneškem).
     *
     * @param  Builder<Offer>  $query
     * @param  Collection<int, FollowedChain>  $followed
     */
    private function whereCurrentFollowed(Builder $query, Collection $followed, ?CarbonImmutable $endedSince = null): void
    {
        $query->active()
            ->notExpired($endedSince ?? $this->calendar->today())
            ->where(function (Builder $query) use ($followed): void {
                foreach ($followed as $chain) {
                    $query->orWhere(fn (Builder $query) => $this->preferences->whereFollowed($query, $chain));
                }
            });
    }

    /**
     * Je nabídka pro uživatele akcí? Akce jen s kartou bez karty není.
     */
    private function isAvailableTo(User $user, Offer $offer): bool
    {
        return $offer->offer_type !== OfferType::LoyaltyOnly
            || ($offer->loyalty_program !== null && $user->hasLoyaltyProgram($offer->loyalty_program));
    }
}
