/**
 * Údaje odvozené z nabídky (App\Domain\Offers\OfferPresenter) pro více komponent.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

import { formatPackage } from '@/lib/format';

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

/**
 * Balení: text obchodu, jinak množství a jednotka z názvu Tesco („1 l“, „500 g“); null = neznámé.
 *
 * @param {object} offer
 * @param {string} locale
 * @param {(key: string) => string} t Překlad (useTranslations)
 * @returns {string|null}
 */
export function packageLabel(offer, locale, t) {
    if (offer.packageText) {
        return offer.packageText;
    }

    return offer.quantity ? formatPackage(offer.quantity, offer.unit, locale, (unit) => t(`package_units.${unit}`)) : null;
}
