<?php

/**
 * Sleva akce v procentech: bez karty (effectiveDiscountPercent, R113) a s kartou pro toho, kdo
 * kartu má (loyaltyDiscountPercent, R162).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\OfferType;
use App\Models\Offer;

/**
 * Akce s cenami v haléřích.
 */
function discountOffer(OfferType $type, ?int $price, ?int $original, ?int $loyalty): Offer
{
    return new Offer(['offer_type' => $type, 'price' => $price, 'original_price' => $original, 'loyalty_price' => $loyalty]);
}

it('slevu s kartou počítá z původní ceny jako slevu bez karty (Termix −47 %, s kartou −52 %)', function (): void {
    $offer = discountOffer(OfferType::Discount, 1090, 2090, 990);

    expect($offer->effectiveDiscountPercent())->toBe(48)
        // S kartou zaokrouhleno dolů jako v letáku Kauflandu (52,6 % → 52 %)
        ->and($offer->loyaltyDiscountPercent())->toBe(52);
});

it('slevu s kartou nemá akce bez původní ceny, bez ceny s kartou ani s cenou s kartou, která není nižší', function (OfferType $type, ?int $price, ?int $original, ?int $loyalty): void {
    expect(discountOffer($type, $price, $original, $loyalty)->loyaltyDiscountPercent())->toBeNull();
})->with([
    'akční cena bez původní' => [OfferType::Discount, 7490, null, 6990],
    'bez ceny s kartou' => [OfferType::Discount, 1090, 2090, null],
    'cena s kartou není nižší' => [OfferType::Discount, 1090, 2090, 1090],
    'jen s kartou (není sleva)' => [OfferType::LoyaltyOnly, 5490, null, 4990],
]);
