/**
 * Přidání aplikace na plochu (R66): vlastní tlačítko místo lišty prohlížeče (InstallPrompt.vue,
 * Můj účet), odložení výzvy a rozpoznání aplikace z plochy a iPhonu. Vyčleněno z pwa.js (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { readStored, writeStored } from '@/lib/storage';
import { reactive } from 'vue';

/** Klíč v localStorage: kdy uživatel zavřel výzvu k přidání na plochu (zásady, kap. 5). */
const INSTALL_DISMISSED_KEY = 'slevohlidka.install.dismissed_at';

/** Milisekund ve dni. */
const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Stav instalace pro výzvu a Můj účet: událost prohlížeče pro vlastní tlačítko „Přidat na
 * plochu“ (Chrome, Edge, Samsung Internet; Safari ji nemá), jestli aplikace běží z plochy
 * a na kolik dní se zavřená výzva odloží (nastavení pwa.installSnoozeDays, nastaví initPwa).
 */
export const installState = reactive({
    promptEvent: null,
    standalone: false,
    snoozeDays: 0,
});

// Výzva přijde, až má aplikace smysl (InstallPrompt.vue). Hned při načtení modulu: prohlížeč
// událost pošle jednou a může to být dřív, než se aplikace spustí.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installState.promptEvent = event;
});
window.addEventListener('appinstalled', () => {
    installState.promptEvent = null;
});

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

    return typeof dismissedAt === 'number' && Date.now() - dismissedAt < installState.snoozeDays * DAY_MS;
}

/** Výzvu k přidání na plochu na čas schová. */
export function snoozeInstallPrompt() {
    writeStored(INSTALL_DISMISSED_KEY, Date.now());
}
