<?php

/**
 * Moje slevy „Podle obchodů“ (R102) — kam jet nakoupit. Pro každý obchod hlídané položky,
 * které tam jsou dnes v akci, s nejvýhodnější akcí položky v tom obchodě a příznakem, jestli
 * je tam položka nejlevněji ze všech obchodů. Obchody od toho, kde je nejlevněji nejvíc
 * položek, pak podle počtu položek v akci.
 *
 * Srovnává se jako řazení Mých slev: cena za jednotku, kterou uživatel zaplatí (s kartou,
 * pokud ji má), jinak cena (UserPricing::sortKey); akce na více kusů má jen běžnou cenu kusu,
 * proto je až za ostatními. Shoda „možná“ (R9) se do srovnání nepočítá — nemusí jít o hledanou
 * variantu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Offers\UserPricing;
use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;

final class ChainOverview
{
    public function __construct(private readonly UserPricing $pricing) {}

    /**
     * Obchody s položkami v akci, seřazené.
     *
     * @param  list<array{watchItem: WatchItem, offers: list<array{offer: Offer, status: MatchStatus}>}>  $groups  Výsledek MyOffers::forUser
     * @return list<array{chain: string, cheapestCount: int, items: list<array{watchItemId: int, offerId: int, cheapest: bool}>}>
     */
    public function build(User $user, array $groups): array
    {
        /** @var array<string, list<array{watchItemId: int, offerId: int, cheapest: bool}>> $byChain */
        $byChain = [];
        foreach ($groups as $group) {
            $best = $this->bestPerChain($user, $group['offers']);
            if ($best === []) {
                continue;
            }
            $lowest = min(array_column($best, 'key'));
            foreach ($best as $chain => ['offer' => $offer, 'key' => $key]) {
                $byChain[$chain][] = ['watchItemId' => $group['watchItem']->id, 'offerId' => $offer->id, 'cheapest' => $key === $lowest];
            }
        }

        $chainOrder = array_flip(array_map(fn (Chain $chain): string => $chain->value, Chain::cases()));
        $overview = [];
        foreach ($byChain as $chain => $items) {
            $overview[] = [
                'chain' => (string) $chain,
                'cheapestCount' => count(array_filter($items, fn (array $item): bool => $item['cheapest'])),
                'items' => $items,
            ];
        }
        usort($overview, fn (array $a, array $b): int => [-$a['cheapestCount'], -count($a['items']), $chainOrder[$a['chain']] ?? PHP_INT_MAX]
            <=> [-$b['cheapestCount'], -count($b['items']), $chainOrder[$b['chain']] ?? PHP_INT_MAX]);

        return $overview;
    }

    /**
     * Nejvýhodnější jistá akce položky v každém obchodě s klíčem srovnání.
     *
     * @param  list<array{offer: Offer, status: MatchStatus}>  $matches
     * @return array<string, array{offer: Offer, key: list<bool|int>}>
     */
    private function bestPerChain(User $user, array $matches): array
    {
        $best = [];
        foreach ($matches as ['offer' => $offer, 'status' => $status]) {
            if ($status === MatchStatus::Maybe) {
                continue;
            }
            $key = [$offer->offer_type === OfferType::Multibuy, ...$this->pricing->sortKey($user, $offer)];
            $chain = $offer->chain->value;
            if (! isset($best[$chain]) || $key < $best[$chain]['key']) {
                $best[$chain] = ['offer' => $offer, 'key' => $key];
            }
        }

        return $best;
    }
}
