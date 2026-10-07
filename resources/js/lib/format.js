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

/**
 * Celé číslo s oddělením tisíců („6 245“).
 *
 * @param {number} value
 * @param {string} locale
 * @returns {string}
 */
export function formatNumber(value, locale) {
    return cachedFormatter(Intl.NumberFormat, locale, {}).format(value);
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
 * Místní datum „2026-10-02“ bez dne v týdnu: „2. 10.“ (u dat dál v minulosti den v týdnu nepomáhá).
 *
 * @param {string} date
 * @param {string} locale
 * @returns {string}
 */
export function formatShortDate(date, locale) {
    return cachedFormatter(Intl.DateTimeFormat, locale, { day: 'numeric', month: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
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

/** Hranice relativního času: do hodiny v minutách, do dne v hodinách, do týdne ve dnech. */
const RELATIVE_UNITS = [
    { unit: 'minute', seconds: 60, below: 60 * 60 },
    { unit: 'hour', seconds: 60 * 60, below: 24 * 60 * 60 },
    { unit: 'day', seconds: 24 * 60 * 60, below: 7 * 24 * 60 * 60 },
];

/**
 * Okamžik v minulosti relativně k teď: „před 5 minutami“, „před 3 hodinami“, „včera“;
 * starší než týden jako datum a čas („2. 10. 20:15“).
 *
 * @param {string} isoDateTime
 * @param {string} locale
 * @param {string} timeZone Zóna zobrazení starších okamžiků (sdílená vlastnost `timezone`)
 * @param {number} [now] Teď v milisekundách
 * @returns {string}
 */
export function formatRelativeTime(isoDateTime, locale, timeZone, now = Date.now()) {
    const elapsedSeconds = Math.max(0, (now - new Date(isoDateTime).getTime()) / 1000);
    const range = RELATIVE_UNITS.find((candidate) => elapsedSeconds < candidate.below);
    if (!range) {
        return formatDateTime(isoDateTime, locale, timeZone);
    }

    // Aspoň minuta — „před 0 minutami“ by vypadalo jako chyba
    const amount = Math.max(1, Math.floor(elapsedSeconds / range.seconds));

    return cachedFormatter(Intl.RelativeTimeFormat, locale, { numeric: 'auto' }).format(-amount, range.unit);
}
