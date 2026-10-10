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
        private readonly DiscountPicker $picker,
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

        return $this->picker->pick($candidates, $limit);
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
