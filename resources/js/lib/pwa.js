/**
 * Aplikace v telefonu (PWA, R66): registrace service workeru (resources/pwa/service-worker.js),
 * výzva k přidání na plochu, obnovení stránky po návratu z pozadí, stránky uložené offline
 * a jejich smazání po odhlášení, číslo na ikoně aplikace (nepřečtená upozornění, R74).
 *
 * Nainstalovaná aplikace nemá lištu prohlížeče ani tlačítko obnovit a v telefonu běží
 * klidně dny — data se proto po návratu do aplikace načtou znovu, když jsou starší.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { clearPendingChecks, syncPendingChecks } from '@/lib/offlineChecks';
import { lookup } from '@/lib/i18n';
import { forgetSearch } from '@/lib/search';
import { readStored, writeStored } from '@/lib/storage';
import { showToast } from '@/lib/toast';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Cache stránek s daty uživatele — název musí sedět s resources/pwa/service-worker.js. */
const PAGES_CACHE = 'slevohlidka-pages';

/** Klíč v localStorage: kdy uživatel zavřel výzvu k přidání na plochu (zásady, kap. 5). */
const INSTALL_DISMISSED_KEY = 'slevohlidka.install.dismissed_at';

/** Milisekund v minutě a ve dni. */
const MINUTE_MS = 60 * 1000;
const DAY_MS = 24 * 60 * MINUTE_MS;

/**
 * Stav instalace pro výzvu a Můj účet: událost prohlížeče pro vlastní tlačítko „Přidat na
 * plochu“ (Chrome, Edge, Samsung Internet; Safari ji nemá) a jestli aplikace běží z plochy.
 */
export const installState = reactive({
    promptEvent: null,
    standalone: false,
});

// Vlastní tlačítko místo lišty prohlížeče — výzva přijde, až má aplikace smysl (InstallPrompt.vue).
// Hned při načtení modulu: prohlížeč událost pošle jednou a může to být dřív, než se aplikace spustí.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installState.promptEvent = event;
});
window.addEventListener('appinstalled', () => {
    installState.promptEvent = null;
});

/** Nastavení ze sdílené vlastnosti pwa (HandleInertiaRequests). */
let settings = { serviceWorkerUrl: null, refreshAfterMinutes: 0, installSnoozeDays: 0 };

/** Kdy stránka naposledy dostala data ze serveru. */
let lastLoadedAt = Date.now();

/**
 * Běží aplikace z plochy (bez lišty prohlížeče)?
 *
 * @returns {boolean}
 */
export function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

/**
 * iPhone nebo iPad — na plochu jde přidat jen přes Sdílet → Přidat na plochu a upozornění
 * fungují jen v aplikaci z plochy. iPad se hlásí jako Mac, pozná se podle dotyku.
 *
 * @returns {boolean}
 */
export function isIos() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/**
 * Nabídne přidání na plochu dialogem prohlížeče.
 *
 * @returns {Promise<boolean>} Přidal uživatel aplikaci?
 */
export async function promptInstall() {
    const event = installState.promptEvent;
    if (!event) {
        return false;
    }

    installState.promptEvent = null;
    event.prompt();
    const { outcome } = await event.userChoice;

    return outcome === 'accepted';
}

/**
 * Zavřel uživatel výzvu k přidání na plochu nedávno?
 *
 * @returns {boolean}
 */
export function isInstallPromptSnoozed() {
    const dismissedAt = readStored(INSTALL_DISMISSED_KEY, null);

    return typeof dismissedAt === 'number' && Date.now() - dismissedAt < settings.installSnoozeDays * DAY_MS;
}

/** Výzvu k přidání na plochu na čas schová. */
export function snoozeInstallPrompt() {
    writeStored(INSTALL_DISMISSED_KEY, Date.now());
}

/**
 * Registrace service workeru, nebo null (dev server, prohlížeč bez podpory). Při první
 * návštěvě registrace ještě nemusí existovat — počká na ni.
 *
 * @returns {Promise<ServiceWorkerRegistration|null>}
 */
export async function serviceWorkerRegistration() {
    if (!settings.serviceWorkerUrl || !('serviceWorker' in navigator)) {
        return null;
    }

    try {
        return (await navigator.serviceWorker.getRegistration()) ?? (await navigator.serviceWorker.register(settings.serviceWorkerUrl, { scope: '/' }));
    } catch {
        return null;
    }
}

/**
 * Zeptá se service workeru, jestli stránku na dané cestě ukázal z cache, a kdy byla uložená.
 *
 * @param {string} path
 * @returns {Promise<string|null>} Čas uložení (ISO 8601), null = ze sítě nebo neznámo
 */
export function offlineFetchedAt(path) {
    const worker = navigator.serviceWorker?.controller;
    if (!worker) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = (event) => resolve(event.data?.fetchedAt ?? null);
        worker.postMessage({ type: 'offline-status', path }, [channel.port2]);
    });
}

/** Smaže číslo na ikoně aplikace (odhlášení). */
export function clearAppBadge() {
    navigator.clearAppBadge?.().catch(() => undefined);
}

/**
 * Číslo na ikoně aplikace podle nepřečtených upozornění v centru (R74) — stejné jako u zvonku.
 * Upozornění v telefonu ho nastaví samo (service worker), tady se po přečtení sníží nebo smaže.
 *
 * @param {object|null|undefined} notificationCenter Sdílená vlastnost notificationCenter
 */
function syncAppBadge(notificationCenter) {
    if (!notificationCenter) {
        return;
    }
    if (notificationCenter.unread > 0) {
        navigator.setAppBadge?.(notificationCenter.unread).catch(() => undefined);
    } else {
        clearAppBadge();
    }
}

/**
 * Smaže data uživatele uložená pro offline režim — po odhlášení nesmí na sdíleném zařízení
 * zůstat jeho Moje slevy ani nákupní seznam.
 */
export function clearOfflineData() {
    clearPendingChecks();
    clearAppBadge();
    window.caches?.delete(PAGES_CACHE).catch(() => undefined);
}

/**
 * Požádá service worker, ať si uloží stránky dostupné offline předem (nákupní seznam je
 * pak v obchodě k dispozici, i když ho uživatel dnes neotevřel).
 */
async function warmOfflinePages() {
    if (!navigator.onLine || !settings.serviceWorkerUrl || !('serviceWorker' in navigator)) {
        return;
    }

    // ready počká, až je service worker po první instalaci aktivní
    (await navigator.serviceWorker.ready).active?.postMessage({ type: 'warm-pages' });
}

/**
 * Po návratu do aplikace z pozadí načte data znovu, když jsou starší než nastavení.
 */
function refreshOnResume() {
    document.addEventListener('visibilitychange', () => {
        const stale = Date.now() - lastLoadedAt > settings.refreshAfterMinutes * MINUTE_MS;
        if (document.visibilityState === 'visible' && stale && navigator.onLine) {
            router.reload();
        }
    });
}

/**
 * Napojí aplikaci v telefonu — volá se jednou při startu (resources/js/app.js).
 *
 * @param {object} initialPage Stránka Inertie (props.pwa, props.auth, props.shoppingList)
 */
export function initPwa(initialPage) {
    settings = { ...settings, ...initialPage.props.pwa };
    installState.standalone = isStandalone();

    if (settings.serviceWorkerUrl && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(settings.serviceWorkerUrl, { scope: '/' }).catch(() => undefined);
        // Klepnutí na upozornění v otevřené aplikaci: přechod bez nového načtení
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'navigate') {
                router.visit(event.data.url);
            }
        });
    }

    let user = initialPage.props.auth.user;
    let syncUrl = initialPage.props.shoppingList?.syncUrl;
    if (user) {
        warmOfflinePages();
        syncPendingChecks(syncUrl);
        syncAppBadge(initialPage.props.notificationCenter);
    } else {
        clearOfflineData();
    }

    router.on('success', () => (lastLoadedAt = Date.now()));
    // Odhlášení, zrušení účtu i vypršení přihlášení: stránky uložené offline pryč;
    // přihlášení: uložit je předem
    router.on('navigate', (event) => {
        const nextUser = event.detail.page.props.auth.user;
        if (user && !nextUser) {
            clearOfflineData();
            // Poslední hledání (R71) — po odhlášení by na sdíleném zařízení prozradila, co
            // uživatel hledal; nepřihlášenému zůstávají (při startu se nemažou)
            forgetSearch();
        } else if (!user && nextUser) {
            warmOfflinePages();
        }
        user = nextUser;
        syncUrl = event.detail.page.props.shoppingList?.syncUrl;
        syncAppBadge(event.detail.page.props.notificationCenter);
    });

    // Přechod bez připojení na stránku, kterou service worker nemá uloženou
    const offlineMessage = lookup(initialPage.props.translations, 'pwa.offline_navigation');
    router.on('networkError', () => showToast(offlineMessage));

    window.addEventListener('online', () => syncPendingChecks(syncUrl));
    refreshOnResume();
}
