/**
 * Čtení a zápis localStorage bez pádu — v anonymním okně nebo s blokovaným úložištěm
 * localStorage vyhazuje výjimku; pohodlí prohlížeče (výzva k instalaci, nezhasínání displeje,
 * odškrtnutí bez připojení) pak platí jen do zavření stránky. Klíče jsou v zásadách
 * (resources/legal/privacy.md, kap. 5).
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */

/**
 * Přečte hodnotu uloženou jako JSON; chybějící, nečitelná nebo nedostupná = výchozí.
 *
 * @template T
 * @param {string} key
 * @param {T} fallback
 * @returns {T}
 */
export function readStored(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);

        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

/**
 * Uloží hodnotu jako JSON; null klíč smaže.
 *
 * @param {string} key
 * @param {unknown} value
 */
export function writeStored(key, value) {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, JSON.stringify(value));
        }
    } catch {
        // Nedostupné úložiště — hodnota platí jen do zavření stránky
    }
}
