/**
 * Zavolání až po pauze — při psaní do hledání a filtrů se požadavek pošle, až uživatel
 * přestane psát (R113, dřív holý časovač v sedmi komponentách).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */

/**
 * Funkce, která zavolá `fn` po `delayMs` od posledního zavolání; `cancel()` čekání zruší,
 * `flush()` čekající zavolání provede hned (odchod ze stránky, R170).
 *
 * @template {(...args: any[]) => void} T
 * @param {T} fn
 * @param {number} delayMs
 * @returns {T & { cancel: () => void, flush: () => void }}
 */
export function debounce(fn, delayMs) {
    let timer = null;
    let pendingArgs = null;
    const debounced = (...args) => {
        window.clearTimeout(timer);
        pendingArgs = args;
        timer = window.setTimeout(() => {
            pendingArgs = null;
            fn(...args);
        }, delayMs);
    };
    debounced.cancel = () => {
        window.clearTimeout(timer);
        pendingArgs = null;
    };
    debounced.flush = () => {
        if (pendingArgs !== null) {
            const args = pendingArgs;
            debounced.cancel();
            fn(...args);
        }
    };

    return debounced;
}
