/**
 * Údaje odvozené z nabídky (App\Domain\Offers\OfferPresenter) pro více komponent.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

import { formatDate, formatPackage } from '@/lib/format';

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
