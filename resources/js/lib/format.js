/**
 * Formátování hodnot pro zobrazení — ceny (haléře → Kč), množství, data platnosti.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

const HALERS_PER_CROWN = 100;
const CURRENCY = 'CZK';
const MAX_QUANTITY_FRACTION_DIGITS = 3;

/** Zobrazení chybějící hodnoty. */
export const EMPTY_VALUE = '—';

/** Vytvořené formattery Intl — konstrukce je drahá, seznam nabídek volá formát stokrát. */
const formatters = new Map();

/**
 * Formatter Intl pro jazyk a volby, vytvořený jen jednou.
 *
 * @template T
 * @param {new (locale: string, options: object) => T} Formatter Intl.NumberFormat nebo Intl.DateTimeFormat
 * @param {string} locale
 * @param {object} options
 * @returns {T}
 */
function cachedFormatter(Formatter, locale, options) {
    const key = `${Formatter.name}|${locale}|${JSON.stringify(options)}`;
    if (!formatters.has(key)) {
        formatters.set(key, new Formatter(locale, options));
    }

    return formatters.get(key);
}

/**
 * Cena v haléřích jako „29,90 Kč“; chybějící jako pomlčka.
 *
 * @param {number|null|undefined} halers
 * @param {string} locale
 * @returns {string}
 */
export function formatPrice(halers, locale) {
    if (halers === null || halers === undefined) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.NumberFormat, locale, { style: 'currency', currency: CURRENCY }).format(halers / HALERS_PER_CROWN);
}

/** Základní jednotka balení => větší jednotka od tisíce (1 000 g = 1 kg). Kusy se nepřevádějí. */
const LARGER_UNITS = { g: 'kg', ml: 'l' };
const LARGER_UNIT_FACTOR = 1000;

/**
 * Balení z množství v základní jednotce (App\Enums\PackageUnit): „500 g“, „1 l“, „1,32 l“, „10 ks“.
 *
 * @param {number} quantity Množství v g, ml nebo ks
 * @param {string} unit g / ml / ks
 * @param {string} locale
 * @param {(unit: string) => string} unitLabel Text jednotky (překlad)
 * @returns {string}
 */
export function formatPackage(quantity, unit, locale, unitLabel) {
    const larger = LARGER_UNITS[unit];
    const [amount, shownUnit] = larger && quantity >= LARGER_UNIT_FACTOR ? [quantity / LARGER_UNIT_FACTOR, larger] : [quantity, unit];
    const number = cachedFormatter(Intl.NumberFormat, locale, { maximumFractionDigits: MAX_QUANTITY_FRACTION_DIGITS }).format(amount);

    return `${number} ${unitLabel(shownUnit)}`;
}

/**
 * Místní datum bez zóny („2026-09-30“) jako „st 30. 9.“. Čte se a formátuje jako UTC,
 * aby ho prohlížeč neposunul do své zóny.
 *
 * @param {string} date
 * @param {string} locale
 * @returns {string}
 */
export function formatDate(date, locale) {
    return cachedFormatter(Intl.DateTimeFormat, locale, { weekday: 'short', day: 'numeric', month: 'numeric', timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    );
}

/**
 * Okamžik (ISO 8601 v UTC) jako místní datum a čas: „2. 10. 20:15“.
 *
 * @param {string} isoDateTime
 * @param {string} locale
 * @param {string} timeZone Zóna zobrazení (sdílená vlastnost `timezone`)
 * @returns {string}
 */
export function formatDateTime(isoDateTime, locale, timeZone) {
    return cachedFormatter(Intl.DateTimeFormat, locale, { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit', timeZone }).format(
        new Date(isoDateTime),
    );
}
