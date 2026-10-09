/**
 * Dotaz na aktivní service worker (resources/pwa/service-worker.js) s odpovědí přes
 * MessageChannel — stránka uložená offline, verze buildu (R66, R78). Vyčleněno z pwa.js (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */

/** Jak dlouho čekat na odpověď service workeru, než se vzdá (starý ji neumí poslat). */
const WORKER_REPLY_TIMEOUT_MS = 2000;

/**
 * Zpráva aktivnímu service workeru s odpovědí. Starý service worker, který zprávě nerozumí,
 * neodpoví — po WORKER_REPLY_TIMEOUT_MS je výsledek null (dřív jeden z dotazů čekal donekonečna).
 *
 * @template T
 * @param {object} message
 * @param {(reply: any) => T} pick Hodnota z odpovědi
 * @returns {Promise<T|null>} null = žádný service worker nebo neodpověděl
 */
export function askWorker(message, pick) {
    const worker = navigator.serviceWorker?.controller;
    if (!worker) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        const timer = setTimeout(() => resolve(null), WORKER_REPLY_TIMEOUT_MS);
        channel.port1.onmessage = (event) => {
            clearTimeout(timer);
            resolve(pick(event.data));
        };
        worker.postMessage(message, [channel.port2]);
    });
}
