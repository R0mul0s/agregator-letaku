/**
 * Zavolání až po pauze — při psaní do hledání a filtrů se požadavek pošle, až uživatel
 * přestane psát (R113, dřív holý časovač v sedmi komponentách).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */

/**
 * Funkce, která zavolá `fn` po `delayMs` od posledního zavolání; `cancel()` čekání zruší.
 *
 * @template {(...args: any[]) => void} T
 * @param {T} fn
 * @param {number} delayMs
 * @returns {T & { cancel: () => void }}
 */
export function debounce(fn, delayMs) {
    let timer = null;
    const debounced = (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), delayMs);
    };
    debounced.cancel = () => window.clearTimeout(timer);

    return debounced;
}
