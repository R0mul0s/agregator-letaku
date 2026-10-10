/**
 * Položka nákupního seznamu (R61, R130, R133) — cena s množstvím, rozpoznání množství
 * v napsaném textu a název do textu seznamu; sdílí je stránka vlastníka seznamu, seznam
 * sdílený odkazem a pole „Co koupit“.
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
 * seznamu zaplatí, s množstvím („2 × 33,90 Kč“, R133); vlastní položka bez akce (R130) cenu nemá.
 *
 * @param {object} item
 * @param {string} locale
 * @param {boolean} withQuantity Před cenu množství („2 × …“); text seznamu ho má u názvu
 * @returns {string|null}
 */
export function itemPrice(item, locale, withQuantity = true) {
    if (!item.offer) {
        return null;
    }
    if (isPromotionText(item)) {
        return item.offer.promotionText;
    }
    const price = formatPrice(item.userPrice ?? item.offer.price, locale);

    return withQuantity && item.quantity > 1 ? `${item.quantity} × ${price}` : price;
}

/**
 * Cena za celé množství („67,80 Kč“) — jen u akce s cenou a víc kusy (R133), jinak null.
 *
 * @param {object} item
 * @param {string} locale
 * @returns {string|null}
 */
export function itemTotal(item, locale) {
    const price = item.offer ? (item.userPrice ?? item.offer.price) : null;
    if (price === null || item.quantity <= 1 || isPromotionText(item)) {
        return null;
    }

    return formatPrice(price * item.quantity, locale);
}

/** Množství na začátku („2x mléko“, „2× mléko“) — za „x“ mezera, ať „4x0,5 l“ zůstane názvem. */
const QUANTITY_PREFIX = /^(\d{1,2})\s*[x×]\s+(.+)$/iu;

/** Množství na konci („mléko 2x“, „mléko x2“). */
const QUANTITY_SUFFIX = /^(.+?)\s+(?:(\d{1,2})\s*[x×]|[x×]\s*(\d{1,2}))$/iu;

/**
 * Rozdělí napsaný text na název a množství (R133): „2x mléko“ → { name: 'mléko', quantity: 2 }.
 * Bez množství 1; množství se omezí na 1 až max.
 *
 * @param {string} text
 * @param {number} max Nejvyšší množství
 * @returns {{ name: string, quantity: number }}
 */
export function parseQuantity(text, max) {
    const trimmed = text.trim();
    const prefix = trimmed.match(QUANTITY_PREFIX);
    const suffix = prefix ? null : trimmed.match(QUANTITY_SUFFIX);
    if (!prefix && !suffix) {
        return { name: trimmed, quantity: 1 };
    }
    const name = prefix ? prefix[2] : suffix[1];
    const quantity = Number(prefix ? prefix[1] : (suffix[2] ?? suffix[3]));

    return { name: name.trim(), quantity: Math.min(Math.max(quantity, 1), max) };
}

/**
 * Název s množstvím do textu seznamu („2× mléko“); jeden kus bez čísla.
 *
 * @param {object} item
 * @returns {string}
 */
export function nameWithQuantity(item) {
    return item.quantity > 1 ? `${item.quantity}× ${item.name}` : item.name;
}
