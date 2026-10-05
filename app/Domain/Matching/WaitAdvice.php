<?php

/**
 * „Vyplatí se počkat“ (R76): hlídaná položka má akci, která ještě nezačala, a ta je za jednotku
 * výrazně levnější než nejlevnější akce, která platí dnes. Porovnává cenu, kterou uživatel
 * zaplatí (s kartou, pokud ji má), přepočtenou na stejnou jednotku — jiné balení by bez
 * přepočtu mátlo. Akce na více kusů se nepočítají (cena za jednotku je z běžné ceny).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Offers\UnitPrice;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\User;

final class WaitAdvice
{
    private const PERCENT = 100;

    public function __construct(private readonly MyOffers $myOffers) {}

    /**
     * Nejvýhodnější budoucí akce, na kterou se vyplatí počkat, nebo null. Bez dnešní akce
     * se stejnou jednotkou se nemá s čím porovnat — budoucí akce pak ukáže sekce „Brzy“.
     *
     * @param  list<Offer>  $current  Akce položky, které platí dnes
     * @param  list<Offer>  $upcoming  Akce položky, které ještě nezačaly
     * @return array{offerId: int, chain: string, chainName: string, validFrom: string, userPrice: int, unitPrice: int, unitPriceUnit: string, savingPercent: int}|null
     */
    public function for(User $user, array $current, array $upcoming): ?array
    {
        $cheapestNow = [];
        foreach ($current as $offer) {
            $comparable = $this->comparable($user, $offer);
            if ($comparable !== null) {
                $cheapestNow[$comparable['unit']] = min($cheapestNow[$comparable['unit']] ?? PHP_INT_MAX, $comparable['unitPrice']);
            }
        }

        $best = null;
        foreach ($upcoming as $offer) {
            $comparable = $this->comparable($user, $offer);
            $now = $comparable === null ? null : ($cheapestNow[$comparable['unit']] ?? null);
            if ($comparable === null || $now === null) {
                continue;
            }

            $saving = (int) floor((1 - $comparable['unitPrice'] / $now) * self::PERCENT);
            if ($saving >= config()->integer('letaky.upcoming.wait_min_saving_percent') && ($best === null || $saving > $best['savingPercent'])) {
                $best = [
                    'offerId' => $offer->id,
                    'chain' => $offer->chain->value,
                    'chainName' => $offer->chain->label(),
                    'validFrom' => $offer->valid_from->toDateString(),
                    'userPrice' => $comparable['price'],
                    'unitPrice' => $comparable['unitPrice'],
                    'unitPriceUnit' => $comparable['unit'],
                    'savingPercent' => $saving,
                ];
            }
        }

        return $best;
    }

    /**
     * Cena, kterou uživatel zaplatí, a cena za jednotku s klíčem jednotky („kg“, „l“, „ks“);
     * null u akce na více kusů nebo bez ceny či balení.
     *
     * @return array{price: int, unitPrice: int, unit: string}|null
     */
    private function comparable(User $user, Offer $offer): ?array
    {
        if ($offer->offer_type === OfferType::Multibuy || $offer->unit === null) {
            return null;
        }

        $price = $this->myOffers->userPrice($user, $offer);
        $unitPrice = UnitPrice::of($price, $offer->quantity, $offer->unit);

        return $price === null || $unitPrice === null || $unitPrice <= 0
            ? null
            : ['price' => $price, 'unitPrice' => $unitPrice, 'unit' => $offer->unit->unitPriceKey()];
    }
}
