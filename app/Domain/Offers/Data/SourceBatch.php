<?php

/**
 * Nabídky jednoho zdroje (letáku, stránky, e-shopu) — jednotka, kterou vrací zdroj obchodu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

final readonly class SourceBatch
{
    /**
     * @param  list<OfferData>  $offers
     * @param  list<LeafletPageData>  $pages  Text stránek letáku pro zmínky bez ceny (R27)
     */
    public function __construct(
        public LeafletData $leaflet,
        public array $offers,
        public array $pages = [],
    ) {}
}
