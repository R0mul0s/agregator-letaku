<?php

/**
 * Nabídka připravená pro stránku — ceny v haléřích (formátuje frontend), názvy z lang.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Models\Offer;

final class OfferPresenter
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Data jedné nabídky pro Vue.
     *
     * @return array<string, mixed>
     */
    public function toPage(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'chain' => $offer->chain->value,
            'chainName' => $offer->chain->label(),
            'name' => $offer->name,
            'brand' => $offer->brand,
            'description' => $offer->description,
            'variantNote' => $offer->variant_note,
            'packageText' => $offer->package_text,
            'quantity' => $offer->quantity,
            'unit' => $offer->unit?->value,
            'price' => $offer->price,
            'originalPrice' => $offer->original_price,
            'loyaltyPrice' => $offer->loyalty_price,
            'loyaltyProgramName' => $offer->loyalty_program?->label(),
            'discountPercent' => $offer->discount_percent,
            'offerType' => $offer->offer_type->value,
            'promotionText' => $offer->promotion_text,
            'onlineOnly' => $offer->online_only,
            'storeFormatName' => $offer->store_format?->label(),
            'unitPrice' => UnitPrice::of($offer->price, $offer->quantity, $offer->unit),
            'loyaltyUnitPrice' => UnitPrice::of($offer->loyalty_price, $offer->quantity, $offer->unit),
            'unitPriceUnit' => $offer->unit?->unitPriceKey(),
            'validFrom' => $offer->valid_from->format(self::DATE_FORMAT),
            'validTo' => $offer->valid_to->format(self::DATE_FORMAT),
            'sourceUrl' => $offer->source_url,
        ];
    }
}
