<?php

/**
 * Výkladní skříň aktuálních akcí pro nepřihlášené — počet akcí, sledované obchody a ukázka
 * akcí s nejvyšší slevou. Úvodní stránka (R44) a panel vedle přihlášení a registrace (R56).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Builder;

final class OfferHighlights
{
    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly SourceRegistry $sources,
    ) {}

    /**
     * Počet neskončených a obchodem nestažených akcí (R16).
     */
    public function currentCount(): int
    {
        return $this->currentOffers()->count();
    }

    /**
     * Obchody, jejichž akce se stahují, v pořadí výčtu.
     *
     * @return list<Chain>
     */
    public function chains(): array
    {
        return $this->sources->chainsWithOffers();
    }

    /**
     * Akce s nejvyšší slevou a obrázkem, z každého obchodu nejdřív po jedné
     * (ať ukázka neukazuje šest jogurtů z jednoho letáku).
     *
     * @return list<Offer>
     */
    public function topDiscounts(int $limit): array
    {
        $candidates = $this->currentOffers()
            ->where('offer_type', OfferType::Discount)
            ->whereNotNull('discount_percent')
            ->whereNotNull('image_url')
            ->orderByDesc('discount_percent')
            ->orderBy('id')
            ->limit($limit * config()->integer('letaky.landing.top_offers_candidates_factor'))
            ->get();

        $picked = [];
        $usedChains = [];
        // Dvě kola: v prvním jen obchody, které v ukázce ještě nejsou, ve druhém kdokoli
        foreach ([true, false] as $distinctChains) {
            foreach ($candidates as $offer) {
                if (count($picked) >= $limit) {
                    break 2;
                }
                if (isset($picked[$offer->id]) || ($distinctChains && isset($usedChains[$offer->chain->value]))) {
                    continue;
                }
                $picked[$offer->id] = $offer;
                $usedChains[$offer->chain->value] = true;
            }
        }

        return array_values($picked);
    }

    /**
     * Neskončené a obchodem nestažené akce (R16).
     *
     * @return Builder<Offer>
     */
    private function currentOffers(): Builder
    {
        return Offer::query()->active()->notExpired($this->calendar->today())->with('stores');
    }
}
