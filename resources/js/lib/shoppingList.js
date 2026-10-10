/**
 * Položka nákupního seznamu (R61, R130) — cena a text ke sdílení; sdílí je stránka vlastníka
 * seznamu a seznam sdílený odkazem.
 *
 * @author Roman Hlaváček
 * @created 2026-10-10
 */
import { formatPrice } from '@/lib/format';
import { OFFER_TYPE } from '@/lib/offer';

/**
 * Je místo ceny text akce na více kusů? Zalamuje se v omezené šířce (R102).
 *
 * @param {object} item
 * @returns {boolean}
 */
export function isPromotionText(item) {
    return item.offer?.offerType === OFFER_TYPE.MULTIBUY && Boolean(item.offer.promotionText);
}

/**
 * Cena položky: u akce na více kusů text akce („3 za cenu 2“), jinak cena, kterou vlastník
 * seznamu zaplatí; vlastní položka bez akce (R130) cenu nemá.
 *
 * @param {object} item
 * @param {string} locale
 * @returns {string|null}
 */
export function itemPrice(item, locale) {
    if (!item.offer) {
        return null;
    }
    if (isPromotionText(item)) {
        return item.offer.promotionText;
    }

    return formatPrice(item.userPrice ?? item.offer.price, locale);
}
