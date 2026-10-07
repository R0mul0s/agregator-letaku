<?php

/**
 * Slevy k hlídaným položkám uživatele ve sledovaných obchodech (R18, R19).
 *
 * Položka z katalogu (R31) bere nabídky z uloženého přiřazení k produktu (`offer_product`).
 * Položka s vlastními slovy se páruje při zobrazení — nabídek je řádově tisíce a hlídaných
 * položek jednotky (R19). Kandidáty předvybere databáze podle nejdelšího slova položek
 * (WatchRule::prefilterTerm), přesná pravidla pak vyhodnotí WatchItemMatcher.
 *
 * K tomu zmínky na stránkách letáků bez ceny (R27): položka je v letáku, ale cenu k ní
 * neznáme. Zmínka se vynechá, když stejný obchod má k položce akci s cenou ve stejném období.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Chains\ShoppingPreferencesScope;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\UnitPrice;
use App\Enums\LeafletKind;
use App\Enums\MatchStatus;
use App\Enums\OffersSort;
use App\Enums\OfferType;
use App\Models\FollowedChain;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class MyOffers
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly WatchItemMatcher $matcher,
        private readonly LocalCalendar $calendar,
        private readonly ShoppingPreferencesScope $preferences,
    ) {}

    /**
     * Pro každou hlídanou položku nabídky seřazené od nejnižší ceny za jednotku,
     * shody před „možná“. Ceny s kartou počítá jen u karet, které uživatel má. Akce, které
     * ještě nezačaly, jsou zvlášť v `upcoming` (R76), seřazené stejně. K tomu zmínky
     * v letácích bez ceny.
     *
     * @param  bool  $withMentions  Hledat i zmínky v letácích (souhrn je nepotřebuje)
     * @return list<array{watchItem: WatchItem, offers: list<array{offer: Offer, status: MatchStatus}>, upcoming: list<array{offer: Offer, status: MatchStatus}>, mentions: list<array{page: LeafletPage, status: MatchStatus}>}>
     */
    public function forUser(User $user, bool $withMentions = true): array
    {
        $watchItems = $user->watchItems()->with('product')->orderBy('name')->get();
        $followed = $user->followedChains()->get();

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
        $candidates = $searchable && $keywordRules !== [] ? $this->candidates($followed, $keywordRules) : [];
        $assignments = $searchable && $productIds !== [] ? $this->assignments($followed, $productIds) : new Collection;
        $pages = $searchable && $withMentions ? $this->candidatePages($followed, $rules) : [];

        $today = $this->calendar->today();
        $groups = [];
        foreach ($watchItems as $item) {
            $found = $item->product_id === null
                ? $this->matchByRule($rules[$item->id], $candidates)
                : $this->matchByProduct($item->product_id, $assignments);
            $offers = $this->availableSorted($user, $found);
            $isUpcoming = fn (array $match): bool => $match['offer']->isUpcoming($today);
            $groups[] = [
                'watchItem' => $item,
                'offers' => array_values(array_filter($offers, fn (array $match): bool => ! $isUpcoming($match))),
                'upcoming' => array_values(array_filter($offers, $isUpcoming)),
                'mentions' => $this->mentions($rules[$item->id], $pages, array_column($offers, 'offer')),
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
            ->with('offer')
            ->get();
        foreach ($assignments as $assignment) {
            if ($this->isAvailableTo($user, $assignment->offer) && $this->meetsMinDiscount($user, $assignment->offer)) {
                $offersByProduct[$assignment->product_id][$assignment->offer_id] = $assignment->offer;
            }
        }

        return array_map(fn (array $offers): array => [
            'count' => count($offers),
            'lowestPrice' => $this->lowestPrice($user, array_values($offers)),
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
     * Přiřazení produktů k neskončeným nabídkám sledovaných obchodů podle jejich upřesnění.
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  list<int>  $productIds
     * @return Collection<int, OfferProduct>
     */
    private function assignments(Collection $followed, array $productIds): Collection
    {
        return OfferProduct::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('offer', fn (Builder $query) => $this->whereCurrentFollowed($query, $followed))
            ->with('offer.stores')
            ->get();
    }

    /**
     * Zmínky položky na stránkách letáků, od nejbližšího letáku a po stránkách. Leták, ve kterém
     * má obchod k položce akci s cenou, se přeskočí — zmínka by jen opakovala tutéž akci.
     *
     * @param  list<array{page: LeafletPage, text: string}>  $pages
     * @param  list<Offer>  $offers  Akce s cenou k položce
     * @return list<array{page: LeafletPage, status: MatchStatus}>
     */
    private function mentions(WatchRule $rule, array $pages, array $offers): array
    {
        $mentions = [];
        foreach ($pages as ['page' => $page, 'text' => $text]) {
            $status = $this->matcher->mention($rule, $text);
            if ($status !== null && ! $this->hasPricedOffer($page->leaflet, $offers)) {
                $mentions[] = ['page' => $page, 'status' => $status];
            }
        }

        return $mentions;
    }

    /**
     * Má obchod letáku k položce akci s cenou, která platí v období letáku?
     *
     * @param  list<Offer>  $offers
     */
    private function hasPricedOffer(Leaflet $leaflet, array $offers): bool
    {
        return array_any($offers, fn (Offer $offer): bool => $offer->chain === $leaflet->chain
            && ($leaflet->valid_to === null || $offer->valid_from <= $leaflet->valid_to)
            && ($leaflet->valid_from === null || $offer->valid_to >= $leaflet->valid_from));
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
            ...$this->sortPrice($user, $offer),
        ];
    }

    /**
     * Neskončené nabídky sledovaných obchodů podle jejich upřesnění, které obsahují
     * aspoň jednu alternativu slova pro předvýběr některé položky; s textem pro párování,
     * normalizovaným jednou pro všechny položky (R54).
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  array<int, WatchRule>  $rules
     * @return list<array{offer: Offer, prepared: array{text: string, isPetFood: bool}}>
     */
    private function candidates(Collection $followed, array $rules): array
    {
        $offers = Offer::query()
            ->tap(fn (Builder $query) => $this->whereCurrentFollowed($query, $followed))
            ->tap(fn (Builder $query) => OfferPrefilter::containingAny($query, $this->prefilterWords($rules)))
            ->with('stores')
            ->get();

        return array_map(fn (Offer $offer): array => ['offer' => $offer, 'prepared' => $this->matcher->prepare($offer)], array_values($offers->all()));
    }

    /**
     * Neskončené a obchodem nestažené nabídky sledovaných obchodů podle jejich upřesnění.
     *
     * @param  Builder<Offer>  $query
     * @param  Collection<int, FollowedChain>  $followed
     */
    private function whereCurrentFollowed(Builder $query, Collection $followed): void
    {
        $query->active()
            ->notExpired($this->calendar->today())
            ->where(function (Builder $query) use ($followed): void {
                foreach ($followed as $chain) {
                    $query->orWhere(fn (Builder $query) => $this->preferences->whereFollowed($query, $chain));
                }
            });
    }

    /**
     * Stránky neskončených letáků sledovaných obchodů, které obsahují aspoň jednu alternativu
     * slova pro předvýběr některé položky; s normalizovaným textem a bez stránek s receptem.
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  array<int, WatchRule>  $rules
     * @return list<array{page: LeafletPage, text: string}>
     */
    private function candidatePages(Collection $followed, array $rules): array
    {
        $excluded = array_map(
            fn (mixed $phrase): string => $this->normalizer->normalize((string) $phrase),
            config()->array('letaky.mentions.excluded_page_phrases'),
        );

        $pages = LeafletPage::query()
            ->with('leaflet')
            ->whereHas('leaflet', function (Builder $query) use ($followed): void {
                $query->where('kind', LeafletKind::Leaflet)
                    ->whereDate('valid_to', '>=', $this->calendar->today()->toDateString())
                    ->where(function (Builder $query) use ($followed): void {
                        foreach ($followed as $chain) {
                            $query->orWhere(fn (Builder $query) => $this->whereLeafletFollowed($query, $chain));
                        }
                    });
            })
            ->where(function (Builder $query) use ($rules): void {
                foreach ($this->prefilterWords($rules) as $word) {
                    $query->orWhere('text', 'like', OfferPrefilter::likePattern($word));
                }
            })
            ->get()
            ->sortBy(fn (LeafletPage $page): array => [$page->leaflet->valid_from?->toDateString(), $page->leaflet_id, $page->number]);

        $result = [];
        foreach ($pages as $page) {
            $text = $this->normalizer->normalize($page->text);
            if (! array_any($excluded, fn (string $phrase): bool => str_contains($text, $phrase))) {
                $result[] = ['page' => $page, 'text' => $text];
            }
        }

        return $result;
    }

    /**
     * Letáky jednoho sledovaného obchodu podle typu prodejny (leták bez typu platí všude).
     *
     * @param  Builder<Leaflet>  $query
     */
    private function whereLeafletFollowed(Builder $query, FollowedChain $chain): void
    {
        $query->where('chain', $chain->chain);

        if ($chain->store_format !== null) {
            $query->where(fn (Builder $query) => $query->whereNull('format')->orWhere('format', $chain->store_format));
        }
    }

    /**
     * Slova všech položek pro předvýběr v databázi (WatchRule::prefilterTerm).
     *
     * @param  array<int, WatchRule>  $rules
     * @return list<string>
     */
    private function prefilterWords(array $rules): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (WatchRule $rule): array => $rule->prefilterTerm(), array_values($rules)))));
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
     * Nejnižší cena, kterou uživatel za některou z akcí zaplatí (s kartou, pokud ji má);
     * null, když žádná akce cenu nemá. Přehled v Hlídám a hlavička skupiny v Mých slevách.
     *
     * @param  list<Offer>  $offers
     */
    public function lowestPrice(User $user, array $offers): ?int
    {
        $prices = array_filter(array_map(fn (Offer $offer): ?int => $this->userPrice($user, $offer), $offers), fn (?int $price): bool => $price !== null);

        return $prices === [] ? null : min($prices);
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
