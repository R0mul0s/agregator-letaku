/**
 * Přidání aplikace na plochu (R66): vlastní tlačítko místo lišty prohlížeče (InstallPrompt.vue,
 * Můj účet), odložení výzvy a rozpoznání aplikace z plochy a iPhonu. Vyčleněno z pwa.js (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { readStored, writeStored } from '@/lib/storage';
import { reactive } from 'vue';

/**
 * Klíč v localStorage: kdy a kolikrát uživatel zavřel výzvu k přidání na plochu (zásady, kap. 5).
 * Hodnota { at, count }; dřív jen čas zavření (číslo) — ten se bere jako poslední krok odkladu.
 */
const INSTALL_DISMISSED_KEY = 'slevohlidka.install.dismissed_at';

/** Milisekund ve dni. */
const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Stav instalace pro výzvu a Můj účet: událost prohlížeče pro vlastní tlačítko „Přidat na
 * plochu“ (Chrome, Edge, Samsung Internet; Safari ji nemá), jestli aplikace běží z plochy
 * a na kolik dní se zavřená výzva odloží po 1., 2., 3. … zavření (nastavení pwa.installSnoozeDays,
 * nastaví initPwa; R139 — odklad se prodlužuje, poslední krok platí dál).
 */
export const installState = reactive({
    promptEvent: null,
    standalone: false,
    snoozeDays: [],
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
 * Kdy a kolikrát uživatel výzvu zavřel; null = ještě nikdy. Starý zápis (jen čas) = poslední krok.
 *
 * @returns {{ at: number, count: number }|null}
 */
function dismissal() {
    const stored = readStored(INSTALL_DISMISSED_KEY, null);
    if (typeof stored === 'number') {
        return { at: stored, count: installState.snoozeDays.length };
    }

    return stored && typeof stored.at === 'number' && typeof stored.count === 'number' ? stored : null;
}

/**
 * Na kolik dní se výzva odloží po tolikátém zavření — poslední krok platí pro všechna další.
 *
 * @param {number} count Kolikrát už byla zavřená (od 1)
 * @returns {number}
 */
function snoozeDaysAfter(count) {
    const steps = installState.snoozeDays;

    return steps.length ? steps[Math.min(count, steps.length) - 1] : 0;
}

/**
 * Zavřel uživatel výzvu k přidání na plochu nedávno?
 *
 * @returns {boolean}
 */
export function isInstallPromptSnoozed() {
    const last = dismissal();

    return last !== null && Date.now() - last.at < snoozeDaysAfter(last.count) * DAY_MS;
}

/** Výzvu k přidání na plochu na čas schová — při každém dalším zavření na déle (R139). */
export function snoozeInstallPrompt() {
    writeStored(INSTALL_DISMISSED_KEY, { at: Date.now(), count: (dismissal()?.count ?? 0) + 1 });
}
