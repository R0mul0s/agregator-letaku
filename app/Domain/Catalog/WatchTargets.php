<?php

/**
 * Co hlídat u akce ve Všech akcích (R60): produkt katalogu, ke kterému je akce přiřazená
 * (shoda před „možná“), jinak název akce jako vlastní slova. Pro přihlášeného i to, jestli
 * produkt už hlídá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\User;

final class WatchTargets
{
    /**
     * Cíl hlídání pro každou akci; klíč je ID akce.
     *
     * @param  iterable<Offer>  $offers
     * @return array<int, array{productId: int|null, name: string, watched: bool}>
     */
    public function forOffers(iterable $offers, ?User $user): array
    {
        $byId = [];
        foreach ($offers as $offer) {
            $byId[$offer->id] = $offer;
        }
        if ($byId === []) {
            return [];
        }

        // Shoda před „možná“ — „Coca-Cola různé druhy“ je spíš Coca-Cola než Coca-Cola Zero
        $products = [];
        $assignments = OfferProduct::query()
            ->whereIn('offer_id', array_keys($byId))
            ->with('product:id,name')
            ->get()
            ->sortBy(fn (OfferProduct $assignment): array => [$assignment->status !== MatchStatus::Match, $assignment->product_id]);
        foreach ($assignments as $assignment) {
            $products[$assignment->offer_id] ??= $assignment->product;
        }

        $watched = $user === null ? [] : array_flip($user->watchItems()->whereNotNull('product_id')->pluck('product_id')->all());

        $targets = [];
        foreach ($byId as $id => $offer) {
            $product = $products[$id] ?? null;
            $targets[$id] = [
                'productId' => $product?->id,
                'name' => $product->name ?? $offer->name,
                'watched' => $product !== null && isset($watched[$product->id]),
            ];
        }

        return $targets;
    }
}
