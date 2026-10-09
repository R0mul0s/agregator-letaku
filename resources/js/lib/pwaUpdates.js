/**
 * Nová verze aplikace po nasazení (R78). SPA se sama celá nenačítá a prohlížeč se na nový
 * service worker ptá jen při celém načtení — aplikace se proto ptá sama (návrat do aplikace,
 * pravidelně v popředí, ručně v Můj účet). Když se aktivuje service worker z jiného buildu,
 * než se kterým stránka běží, načte se znovu — sama, dokud uživatel od návratu do aplikace na
 * nic nesáhl, jinak nabídne lištu „Načíst“ (UpdateBar.vue). Vyčleněno z pwa.js (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { askWorker } from '@/lib/serviceWorker';
import { reactive } from 'vue';

/** Milisekund v minutě. */
const MINUTE_MS = 60 * 1000;

/** Nová verze aplikace je připravená (lišta s „Načíst“, UpdateBar.vue). */
export const updateState = reactive({
    available: false,
});

/** Adresa service workeru (null = dev server bez service workeru). */
let serviceWorkerUrl = null;

/** Verze assetů, se kterou stránka běží (Inertia, hash manifestu buildu). */
let pageVersion = null;

/** Sáhl uživatel od spuštění nebo návratu do aplikace na obrazovku? Dokud ne, nová verze se načte sama. */
let interacted = false;

/** Načíst novou verzi hned, jak se aktivuje (uživatel o ni požádal v Můj účet). */
let applyWhenReady = false;

/**
 * Verze assetů, se kterou běží aktivní service worker.
 *
 * @returns {Promise<string|null>} null = žádný service worker nebo neodpověděl
 */
function workerAssetVersion() {
    return askWorker({ type: 'asset-version' }, (reply) => reply?.assetVersion ?? null);
}

/**
 * Běží aktivní service worker z jiného buildu než stránka?
 *
 * @returns {Promise<boolean>}
 */
async function isNewerVersionActive() {
    const version = await workerAssetVersion();

    return version !== null && pageVersion !== null && version !== pageVersion;
}

/** Načte stránku znovu s novou verzí aplikace. */
export function applyUpdate() {
    window.location.reload();
}

/**
 * Aktivoval se nový service worker: z jiného buildu načte stránku znovu, když uživatel od
 * návratu do aplikace na nic nesáhl (nebo o to požádal), jinak ukáže lištu.
 */
async function onControllerChange() {
    if (!(await isNewerVersionActive())) {
        return;
    }

    if (applyWhenReady || (document.visibilityState === 'visible' && !interacted)) {
        applyUpdate();

        return;
    }
    updateState.available = true;
}

/**
 * Zeptá se serveru na nový service worker. Nový se nainstaluje sám a stránku načte znovu
 * přes controllerchange (onControllerChange).
 *
 * @param {{ applyWhenReady?: boolean }} [options] applyWhenReady: novou verzi načíst hned
 *     (ruční kontrola v Můj účet), ne až podle toho, jestli uživatel na něco sáhl
 * @returns {Promise<boolean>} Je k dispozici nová verze?
 */
export async function checkForUpdate({ applyWhenReady: apply = false } = {}) {
    if (!navigator.onLine || !serviceWorkerUrl || !('serviceWorker' in navigator)) {
        return false;
    }

    const registration = await navigator.serviceWorker.getRegistration();
    if (!registration) {
        return false;
    }

    try {
        await registration.update();
    } catch {
        return false;
    }

    // Nový service worker se instaluje (aktivuje se sám, skipWaiting) …
    if (registration.installing || registration.waiting) {
        applyWhenReady = applyWhenReady || apply;

        return true;
    }

    // … nebo už je aktivní a stránka běží se starým buildem (aktivoval se v jiném okně)
    if (await isNewerVersionActive()) {
        if (apply) {
            applyUpdate();
        } else {
            updateState.available = true;
        }

        return true;
    }

    return false;
}

/**
 * Verze, se kterou stránka běží — po každém přechodu Inertie (nová stránka může nést novou).
 *
 * @param {string|null|undefined} version
 */
export function setPageVersion(version) {
    pageVersion = version ?? pageVersion;
}

/**
 * Návrat do aplikace z pozadí: připravenou novou verzi načte (vrátí true), jinak se na ni
 * zeptá; doteky se počítají znovu od návratu.
 *
 * @returns {boolean} Načítá se nová verze
 */
export function onResume() {
    interacted = false;
    if (updateState.available) {
        applyUpdate();

        return true;
    }
    checkForUpdate();

    return false;
}

/**
 * Hlídá novou verzi aplikace: aktivace nového service workeru, pravidelná kontrola v popředí
 * a doteky uživatele (po nich se už stránka sama nenačte, jen nabídne lištu).
 *
 * @param {{ serviceWorkerUrl: string|null, updateCheckMinutes: number }} settings Sdílená vlastnost pwa
 * @param {string|null|undefined} version Verze, se kterou stránka běží
 */
export function watchForUpdates(settings, version) {
    serviceWorkerUrl = settings.serviceWorkerUrl;
    setPageVersion(version);
    if (!serviceWorkerUrl || !('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.addEventListener('controllerchange', onControllerChange);

    const markInteracted = () => (interacted = true);
    window.addEventListener('pointerdown', markInteracted, { capture: true, passive: true });
    window.addEventListener('keydown', markInteracted, { capture: true });

    setInterval(() => {
        if (document.visibilityState === 'visible') {
            checkForUpdate();
        }
    }, settings.updateCheckMinutes * MINUTE_MS);
}
