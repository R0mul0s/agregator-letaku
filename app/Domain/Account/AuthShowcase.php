<?php

/**
 * Data panelu vedle přihlášení a registrace (AuthShowcase.vue, R56) — skutečné akce
 * místo obecných slibů: kolik jich právě je, ze kterých obchodů a pár nejvyšších slev.
 * Sdílí ho stránky Fortify i dokončení registrace přes Google a Facebook (R96).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Offers\OfferHighlights;
use App\Domain\Offers\OfferPresenter;
use App\Enums\Chain;
use App\Models\Offer;

final class AuthShowcase
{
    public function __construct(
        private readonly OfferHighlights $highlights,
        private readonly OfferPresenter $presenter,
    ) {}

    /**
     * Počet akcí, obchody a akce s nejvyšší slevou pro stránku.
     *
     * @return array{offers: int, chains: list<string>, deals: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'offers' => $this->highlights->currentCount(),
            'chains' => array_map(fn (Chain $chain): string => $chain->value, $this->highlights->chains()),
            'deals' => array_map(
                fn (Offer $offer): array => $this->presenter->toPage($offer),
                $this->highlights->topDiscounts(config()->integer('letaky.auth.showcase_deals')),
            ),
        ];
    }
}
