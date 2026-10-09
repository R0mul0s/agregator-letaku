/**
 * Lišta sekcí stránky na telefonu (Můj účet, právní texty, R116): drží se pod plovoucí
 * hlavičkou (`--app-header-height`, lib/appHeader.js) a posouvá se do stran tak, aby odkaz
 * zvýrazněné sekce byl vidět.
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { watch } from 'vue';
import { prefersReducedMotion } from './scroll';

/** Odkaz zvýrazněné sekce v liště. */
const ACTIVE_LINK_SELECTOR = '[aria-current="location"]';

/**
 * Posune lištu do stran, aby byl odkaz uprostřed; lišta, která se vejde celá (sloupec na
 * počítači), se nehne.
 *
 * @param {HTMLElement} nav
 * @param {HTMLElement} link
 */
function centerLink(nav, link) {
    if (nav.scrollWidth <= nav.clientWidth) {
        return;
    }

    // Jen lišta, ne stránka — scrollIntoView by v Chromiu zastavil plynulý skok na kotvu
    nav.scrollTo({
        left: link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
}

/**
 * Při změně aktivní sekce posune lištu k jejímu odkazu.
 *
 * @param {import('vue').Ref<HTMLElement|null>} navRef Lišta (musí být `position: sticky`, aby
 *     `offsetLeft` odkazů počítal od ní)
 * @param {import('vue').Ref<string|null>} activeId Aktivní sekce (useScrollSpy)
 */
export function useSectionNav(navRef, activeId) {
    // Až po vykreslení — aria-current se přesune na nový odkaz
    watch(
        activeId,
        () => {
            const nav = navRef.value;
            const link = nav?.querySelector(ACTIVE_LINK_SELECTOR);
            if (nav && link instanceof HTMLElement) {
                centerLink(nav, link);
            }
        },
        { flush: 'post' },
    );
}
