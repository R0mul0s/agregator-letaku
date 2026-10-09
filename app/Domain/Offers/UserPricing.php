<?php

/**
 * Cena akce pro konkrétního uživatele (R19, R113): s kartou, pokud ji má a je nižší, jinak
 * cena bez karty. Jedno místo pro Moje slevy, souhrny, upozornění, nákupní seznam i Hlídám —
 * dřív byla v MyOffers a ostatní si ho kvůli ceně braly celé.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Models\Offer;
use App\Models\User;

final class UserPricing
{
    /**
     * Cena, kterou uživatel zaplatí: s kartou, pokud ji má a je nižší, jinak cena bez karty.
     */
    public function price(User $user, Offer $offer): ?int
    {
        $hasCard = $offer->loyalty_program !== null && $user->hasLoyaltyProgram($offer->loyalty_program);

        if ($hasCard && $offer->loyalty_price !== null) {
            return $offer->price === null ? $offer->loyalty_price : min($offer->price, $offer->loyalty_price);
        }

        return $offer->price;
    }

    /**
     * Klíč řazení: cena za jednotku, kterou uživatel zaplatí, jinak cena; nabídky bez ceny
     * na konec. Moje slevy i srovnání obchodů (ChainOverview, R102).
     *
     * @return array{int, int}
     */
    public function sortKey(User $user, Offer $offer): array
    {
        $price = $this->price($user, $offer);
        $unitPrice = UnitPrice::of($price, $offer->quantity, $offer->unit);

        return [$unitPrice ?? PHP_INT_MAX, $price ?? PHP_INT_MAX];
    }

    /**
     * Nejnižší cena, kterou uživatel za některou z akcí zaplatí; null, když žádná akce cenu
     * nemá. Přehled v Hlídám a hlavička skupiny v Mých slevách.
     *
     * @param  list<Offer>  $offers
     */
    public function lowest(User $user, array $offers): ?int
    {
        $prices = array_filter(array_map(fn (Offer $offer): ?int => $this->price($user, $offer), $offers), fn (?int $price): bool => $price !== null);

        return $prices === [] ? null : min($prices);
    }
}
