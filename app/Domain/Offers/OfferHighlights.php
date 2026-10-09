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
    /** Ukázka jen akcí se skutečnou slevou — aspoň jedno procento. */
    private const MIN_DISCOUNT_PERCENT = 1;

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
     * (ať ukázka neukazuje šest jogurtů z jednoho letáku); výsledek od nejvyšší slevy. Sleva
     * i dopočtená z přeškrtnuté ceny (Albert, Penny nemají procenta, R113).
     *
     * @return list<Offer>
     */
    public function topDiscounts(int $limit): array
    {
        $candidates = $this->currentOffers()
            ->where('offer_type', OfferType::Discount)
            ->withDiscountOf(self::MIN_DISCOUNT_PERCENT)
            ->whereNotNull('image_url')
            ->orderByDiscount()
            ->orderBy('id')
            ->limit($limit * config()->integer('letaky.landing.top_offers_candidates_factor'))
            ->get();

        $picked = [];
        $usedChains = [];
        $usedNames = [];
        // Dvě kola: v prvním jen obchody, které v ukázce ještě nejsou, ve druhém kdokoli. Stejný
        // název stejného obchodu jen jednou — Kaufland má akci po prodejnách jako víc řádků (R49)
        foreach ([true, false] as $distinctChains) {
            foreach ($candidates as $offer) {
                if (count($picked) >= $limit) {
                    break 2;
                }
                $name = $offer->chain->value.'|'.mb_strtolower($offer->name);
                if (isset($picked[$offer->id]) || isset($usedNames[$name]) || ($distinctChains && isset($usedChains[$offer->chain->value]))) {
                    continue;
                }
                $picked[$offer->id] = $offer;
                $usedChains[$offer->chain->value] = true;
                $usedNames[$name] = true;
            }
        }

        // Druhé kolo přidává až za první — bez seřazení by −66 % stálo pod −56 %
        $picked = array_values($picked);
        usort($picked, fn (Offer $a, Offer $b): int => [$b->effectiveDiscountPercent(), $a->id] <=> [$a->effectiveDiscountPercent(), $b->id]);

        return $picked;
    }

    /**
     * Neskončené a obchodem nestažené akce (R16).
     *
     * @return Builder<Offer>
     */
    private function currentOffers(): Builder
    {
        return Offer::query()->withoutRaw()->active()->notExpired($this->calendar->today())->with('stores');
    }
}
