/**
 * Přístupnost přechodů mezi stránkami a chyb formulářů (R99).
 *
 * SPA nenačítá stránku celou — čtečka obrazovky by po přechodu nic neohlásila a fokus by po
 * odkazu, který s hlavičkou zmizel, spadl na začátek dokumentu. Po přechodu na jinou stránku
 * se proto fokus přesune na hlavní obsah (#main) a titulek stránky se oznámí. Po odeslání
 * formuláře s chybami dostane fokus první chybné pole (aria-invalid), čtečka přečte chybu
 * (aria-describedby u TextField a dalších polí).
 *
 * @author Roman Hlaváček
 * @created 2026-10-07
 */
import { router } from '@inertiajs/vue3';

/** Hlavní obsah stránky (AppLayout, tabindex="-1"). */
const MAIN_SELECTOR = '#main';

/** Pole s chybou validace. */
const INVALID_SELECTOR = '[aria-invalid="true"]';

/** Třída skrytého textu pro čtečky (base/_reset.scss). */
const VISUALLY_HIDDEN_CLASS = 'visually-hidden';

/** Oblast, kterou čtečka přečte při změně textu (titulek nové stránky). */
let announcer = null;

/**
 * Spustí obsluhu přechodů a chyb formulářů.
 */
export function initAccessibility() {
    announcer = createAnnouncer();
    let lastPath = window.location.pathname;

    router.on('navigate', () => {
        const path = window.location.pathname;
        const pageChanged = path !== lastPath;
        lastPath = path;
        if (pageChanged) {
            afterRender(onPageChanged);
        }
    });

    router.on('error', () => afterRender(focusFirstInvalid));
}

/**
 * Po přechodu na jinou stránku: zůstal-li fokus na prvku, který na stránce zůstal (filtr,
 * pole hledání), nechá ho; jinak ho dá na hlavní obsah a oznámí titulek.
 */
function onPageChanged() {
    const active = document.activeElement;
    if (active && active !== document.body && active.isConnected) {
        return;
    }

    document.querySelector(MAIN_SELECTOR)?.focus({ preventScroll: true });
    announce(document.title);
}

/**
 * Fokus na první pole s chybou — po odeslání bez chyb na stránce ho tlačítko (vypnuté během
 * odesílání) zahodilo a čtečka by o chybě nevěděla.
 */
function focusFirstInvalid() {
    const field = document.querySelector(INVALID_SELECTOR);
    if (field instanceof HTMLElement) {
        field.focus();
    }
}

/**
 * Oznámí text čtečce (zdvořile, až domluví).
 *
 * @param {string} text
 */
function announce(text) {
    if (!announcer) {
        return;
    }
    // Stejný text dvakrát po sobě by čtečka nepřečetla — nejdřív vyprázdnit
    announcer.textContent = '';
    afterRender(() => {
        announcer.textContent = text;
    });
}

/**
 * Skrytá oblast pro oznámení, jednou na konci dokumentu (mimo aplikaci, přechody ji nemažou).
 *
 * @returns {HTMLElement}
 */
function createAnnouncer() {
    const element = document.createElement('p');
    element.className = VISUALLY_HIDDEN_CLASS;
    element.setAttribute('aria-live', 'polite');
    element.setAttribute('aria-atomic', 'true');
    document.body.append(element);

    return element;
}

/**
 * Spustí funkci po vykreslení — Vue i titulek z <Head> se aktualizují až po události routeru.
 *
 * @param {() => void} callback
 */
function afterRender(callback) {
    window.requestAnimationFrame(() => window.requestAnimationFrame(callback));
}
