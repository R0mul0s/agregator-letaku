/**
 * Požadavky na JSON, ze kterých platí jen poslední: nový zruší předchozí (psaní pokračuje,
 * okno pro jinou akci). Dřív zkopírované v našeptávači, náhledu Hlídám, ukázce hlídání
 * a okně prodejen (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */

/** Výsledek požadavku, který zrušil novější (nebo `cancel`) — volající nemá nic měnit. */
export const ABORTED = Symbol('aborted');

/**
 * Nový zdroj požadavků, ze kterých platí jen poslední.
 *
 * @returns {{ json: (url: string) => Promise<object|null|typeof ABORTED>, cancel: () => void }}
 */
export function createLatestRequest() {
    let controller = null;

    return {
        /**
         * Načte JSON; předchozí nedokončený požadavek zruší.
         *
         * @param {string} url
         * @returns {Promise<object|null|typeof ABORTED>} Data, null při chybě (HTTP, bez signálu), ABORTED při zrušení
         */
        async json(url) {
            controller?.abort();
            const current = new AbortController();
            controller = current;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: current.signal });

                return response.ok ? await response.json() : null;
            } catch (error) {
                return error.name === 'AbortError' ? ABORTED : null;
            } finally {
                if (controller === current) {
                    controller = null;
                }
            }
        },

        /** Zruší rozběhnutý požadavek (zkrácení textu, zavření okna, odchod ze stránky). */
        cancel() {
            controller?.abort();
            controller = null;
        },
    };
}
