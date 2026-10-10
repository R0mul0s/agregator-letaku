<?php

/**
 * Výběr akcí s nejvyšší slevou z kandidátů seřazených od nejvyšší slevy — z každého obchodu
 * nejdřív po jedné (ať výběr neukazuje šest jogurtů z jednoho letáku) a stejný název
 * stejného obchodu jen jednou (Kaufland má akci po prodejnách jako víc řádků, R49).
 * Úvodní stránka (R44) a Nejlepší slevy týdne (R128).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Models\Offer;

final class DiscountPicker
{
    /**
     * Nejvýš `$limit` akcí od nejvyšší slevy (effectiveDiscountPercent).
     *
     * @param  iterable<Offer>  $candidates  Seřazené od nejvyšší slevy
     * @param  int|null  $maxPerChain  Nejvýš tolik akcí jednoho obchodu (null = bez omezení)
     * @return list<Offer>
     */
    public function pick(iterable $candidates, int $limit, ?int $maxPerChain = null): array
    {
        $candidates = is_array($candidates) ? $candidates : iterator_to_array($candidates, false);
        $picked = [];
        $chainCounts = [];
        $usedNames = [];
        // Dvě kola: v prvním jen obchody, které ve výběru ještě nejsou, ve druhém kdokoli
        foreach ([true, false] as $distinctChains) {
            foreach ($candidates as $offer) {
                if (count($picked) >= $limit) {
                    break 2;
                }
                $chain = $offer->chain->value;
                $name = $chain.'|'.mb_strtolower($offer->name);
                $chainCount = $chainCounts[$chain] ?? 0;
                if (isset($picked[$offer->id]) || isset($usedNames[$name])
                    || ($distinctChains && $chainCount > 0)
                    || ($maxPerChain !== null && $chainCount >= $maxPerChain)) {
                    continue;
                }
                $picked[$offer->id] = $offer;
                $chainCounts[$chain] = $chainCount + 1;
                $usedNames[$name] = true;
            }
        }

        // Druhé kolo přidává až za první — bez seřazení by −66 % stálo pod −56 %
        $picked = array_values($picked);
        usort($picked, fn (Offer $a, Offer $b): int => [$b->effectiveDiscountPercent(), $a->id] <=> [$a->effectiveDiscountPercent(), $b->id]);

        return $picked;
    }
}
