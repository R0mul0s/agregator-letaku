/**
 * Údaje odvozené z nabídky (App\Domain\Offers\OfferPresenter) pro více komponent.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

/** Procenta = 100 — převod podílu na procenta slevy. */
const PERCENT = 100;

/**
 * Sleva v procentech: od obchodu, jinak dopočtená z původní ceny; jen u typu „sleva“ (R8).
 *
 * @param {object} offer
 * @returns {number|null}
 */
export function discountPercent(offer) {
    if (offer.discountPercent) {
        return offer.discountPercent;
    }

    const { price, originalPrice } = offer;

    return offer.offerType === 'discount' && price && originalPrice > price ? Math.round((1 - price / originalPrice) * PERCENT) : null;
}
