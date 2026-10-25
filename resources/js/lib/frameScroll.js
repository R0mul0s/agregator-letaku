/**
 * Přepočet podle posunu stránky nejvýš jednou za snímek, jen na displeji podle media query —
 * plovoucí lišta filtrů (R119) a přilepená hlavička skupiny Mých slev (R117) měly každá vlastní
 * kopii posluchačů posouvání, změny velikosti a media query (R170).
 *
 * @author Roman Hlaváček
 * @created 2026-10-25
 */
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Zavolá `update(matches)` po připojení komponenty a pak při posouvání, změně velikosti okna
 * a změně media query — nejvýš jednou za snímek.
 *
 * @param {(matches: boolean) => void} update
 * @param {string} mediaQuery Na jakém displeji se přepočítává (lib/breakpoints.js)
 */
export function useFrameScroll(update, mediaQuery) {
    /** @type {MediaQueryList|null} */
    let media = null;
    let frame = null;

    /** Přepočet ve snímku. */
    function run() {
        frame = null;
        update(media?.matches ?? false);
    }

    /** Posunutí, změna velikosti — přepočet v příštím snímku. */
    function schedule() {
        frame ??= window.requestAnimationFrame(run);
    }

    onMounted(() => {
        media = window.matchMedia(mediaQuery);
        media.addEventListener('change', schedule);
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        run();
    });

    onBeforeUnmount(() => {
        media?.removeEventListener('change', schedule);
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
        }
    });
}
