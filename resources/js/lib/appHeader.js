/**
 * Výška plovoucí hlavičky aplikace jako `--app-header-height` na html (R116, R117) — pod ni se
 * lepí lišta sekcí (Můj účet, právní texty) i hlavička rozbalené skupiny v Mých slevách. Na
 * telefonu a tabletu se liší podle obsahu hlavičky (nepřihlášený, otevřené menu), proto se měří.
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { onBeforeUnmount, onMounted } from 'vue';

/** Vlastnost na html s výškou hlavičky (px). */
export const APP_HEADER_HEIGHT_PROPERTY = '--app-header-height';

/**
 * Aktuální výška hlavičky z html (px); 0, dokud ji AppLayout nezměřil.
 *
 * @returns {number}
 */
export function appHeaderHeight() {
    return parseFloat(getComputedStyle(document.documentElement).getPropertyValue(APP_HEADER_HEIGHT_PROPERTY)) || 0;
}

/**
 * Měří hlavičku po celou dobu, kdy je layout připojený.
 *
 * @param {import('vue').Ref<HTMLElement|null>} headerRef
 */
export function useAppHeaderHeight(headerRef) {
    /** @type {ResizeObserver|null} */
    let observer = null;

    onMounted(() => {
        const header = headerRef.value;
        if (!header || !('ResizeObserver' in window)) {
            return;
        }

        observer = new ResizeObserver(() => {
            document.documentElement.style.setProperty(APP_HEADER_HEIGHT_PROPERTY, `${header.getBoundingClientRect().height}px`);
        });
        observer.observe(header);
    });

    // Vlastnost zůstává — další stránka ji hned přepíše vlastním měřením
    onBeforeUnmount(() => observer?.disconnect());
}
