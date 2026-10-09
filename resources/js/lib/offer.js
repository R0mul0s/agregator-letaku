/**
 * Údaje odvozené z nabídky (App\Domain\Offers\OfferPresenter) pro více komponent.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

import { formatDate, formatPackage, formatPrice } from '@/lib/format';

/** Druhy akcí — hodnoty App\Enums\OfferType (R8). */
export const OFFER_TYPE = Object.freeze({
    DISCOUNT: 'discount',
    PROMO_PRICE: 'promo_price',
    LOYALTY_ONLY: 'loyalty_only',
    MULTIBUY: 'multibuy',
});

/** Shoda „možná“ u „různých druhů“ — hodnota App\Enums\MatchStatus::Maybe (R9). */
export const MATCH_MAYBE = 'maybe';

/**
 * Cena za jednotku jako „29,90 Kč / kg“.
 *
 * @param {number} halers
 * @param {string} unit Klíč jednotky (unitPriceUnit: kg, l, ks)
 * @param {string} locale
 * @param {(key: string, replace?: object) => string} t Překlad (useTranslations)
 * @returns {string}
 */
export function unitPriceLabel(halers, unit, locale, t) {
    return t('offers.unit_price', { price: formatPrice(halers, locale), unit: t(`unit_price_units.${unit}`) });
}

/**
 * Cena za jednotku k hlavní ceně akce (u akce jen s kartou z ceny s kartou); null bez balení.
 * U akce na více kusů by byla z běžné ceny a v přehledu by mátla — nezobrazuje se.
 *
 * @param {object} offer Akce z OfferPresenter
 * @param {string} locale
 * @param {(key: string, replace?: object) => string} t Překlad (useTranslations)
 * @returns {string|null}
 */
export function offerUnitPriceLabel(offer, locale, t) {
    const value = offer.offerType === OFFER_TYPE.LOYALTY_ONLY ? offer.loyaltyUnitPrice : offer.unitPrice;
    if (offer.offerType === OFFER_TYPE.MULTIBUY || value === null || value === undefined || !offer.unitPriceUnit) {
        return null;
    }

    return unitPriceLabel(value, offer.unitPriceUnit, locale, t);
}

/**
 * Sleva v procentech: od obchodu, jinak dopočtená z původní ceny; jen u typu „sleva“ (R8).
 * Počítá ji server (Offer::effectiveDiscountPercent, R113), ať se výpočet nerozejde.
 *
 * @param {object} offer
 * @returns {number|null}
 */
export function discountPercent(offer) {
    return offer.discountPercent || null;
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

/** Akce začíná zítra — štítek „Od zítra“ místo data. */
const STARTS_TOMORROW_DAYS = 1;

/**
 * Štítek akce nebo zmínky, která ještě nezačala (R76): „Od zítra“, „Od st 8. 10.“; null = už platí.
 * `startsInDays` a `validFrom` posílá OfferPresenter i MentionPresenter.
 *
 * @param {{ startsInDays: number|null, validFrom: string|null }} entry
 * @param {string} locale
 * @param {(key: string, replace?: object) => string} t Překlad (useTranslations)
 * @returns {string|null}
 */
export function startsLabel(entry, locale, t) {
    if (!entry.startsInDays || !entry.validFrom) {
        return null;
    }

    return entry.startsInDays === STARTS_TOMORROW_DAYS ? t('offers.starts_tomorrow') : t('offers.starts_on', { date: formatDate(entry.validFrom, locale) });
}

/**
 * Kotva položky v sekci Brzy (R76) — „+1 brzy“ v sekci Zatím bez akce na ni posune stránku.
 *
 * @param {number} itemId ID hlídané položky
 * @returns {string}
 */
export function upcomingAnchor(itemId) {
    return `brzy-polozka-${itemId}`;
}
