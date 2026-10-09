/**
 * Lišta sekcí stránky na telefonu (Můj účet, R116): drží se pod plovoucí hlavičkou a posouvá
 * se do stran tak, aby odkaz zvýrazněné sekce byl vidět. Výšku hlavičky měří (na telefonu
 * a tabletu se liší podle toho, co v ní je) do `--section-nav-top` a spodní okraj lišty do
 * `--section-bar-bottom` — obojí na html, kde z něj počítá i scroll-padding (_reset.scss).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { prefersReducedMotion } from './scroll';

/** Plovoucí hlavička aplikace (AppLayout). */
const HEADER_SELECTOR = '.app-header';

/** Výška hlavičky — odsazení lišty shora. */
const NAV_TOP_PROPERTY = '--section-nav-top';

/** Spodní okraj lišty od horního okraje okna — kam dojedou kotvy. */
const BAR_BOTTOM_PROPERTY = '--section-bar-bottom';

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
 * Napojí lištu sekcí: odsazení pod hlavičkou a posun k odkazu aktivní sekce.
 *
 * @param {import('vue').Ref<HTMLElement|null>} navRef Lišta (musí být `position: sticky`, aby
 *     `offsetLeft` odkazů počítal od ní)
 * @param {import('vue').Ref<string|null>} activeId Aktivní sekce (useScrollSpy)
 */
export function useSectionNav(navRef, activeId) {
    /** @type {ResizeObserver|null} */
    let observer = null;
    const root = document.documentElement;

    onMounted(() => {
        const header = document.querySelector(HEADER_SELECTOR);
        const nav = navRef.value;
        if (!header || !nav || !('ResizeObserver' in window)) {
            return;
        }

        // Na html, ať je čte i scroll-padding stránky (_reset.scss) — kotva dojede těsně pod lištu
        observer = new ResizeObserver(() => {
            const headerHeight = header.getBoundingClientRect().height;
            root.style.setProperty(NAV_TOP_PROPERTY, `${headerHeight}px`);
            root.style.setProperty(BAR_BOTTOM_PROPERTY, `${headerHeight + nav.getBoundingClientRect().height}px`);
        });
        observer.observe(header);
        observer.observe(nav);
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        root.style.removeProperty(NAV_TOP_PROPERTY);
        root.style.removeProperty(BAR_BOTTOM_PROPERTY);
    });

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
