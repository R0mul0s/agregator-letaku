/**
 * Zvýraznění sekce, ve které čtenář právě je — navigace Můj účet (R63) a obsah právních
 * textů (R51). Dřív dvě skoro stejné kopie (R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { onBeforeUnmount, onMounted, ref, toValue } from 'vue';

/** Tolerance (px) pro „posunuto až na konec stránky“ — zaokrouhlení výšek v prohlížeči. */
const BOTTOM_TOLERANCE_PX = 2;

/** Události, kterými uživatel posouvá sám (ne skok na kotvu po klepnutí). */
const USER_SCROLL_EVENTS = ['wheel', 'touchstart', 'keydown'];

/**
 * Aktuální sekce podle polohy stránky: poslední, jejíž začátek už je nad čarou; na konci
 * stránky poslední sekce (krátká by k čáře nikdy nedojela). Sekce vybraná klepnutím platí,
 * dokud uživatel sám neposune stránku — skok k sekci u konce stránky by jinak zvýraznil poslední.
 *
 * @param {import('vue').MaybeRefOrGetter<string[]>} ids ID sekcí v pořadí stránky
 * @param {() => number} line Čára od horního okraje okna (px), kterou musí začátek sekce minout
 * @returns {{ activeId: import('vue').Ref<string|null>, select: (id: string) => void }}
 */
export function useScrollSpy(ids, line) {
    const activeId = ref(toValue(ids)[0] ?? null);
    /** Sekce vybraná klepnutím, null = rozhoduje poloha stránky. */
    let selected = null;
    /** Čeká na snímek, aby se při posouvání nepočítalo víckrát než jednou za vykreslení. */
    let frame = null;

    /** Najde aktuální sekci. */
    function update() {
        frame = null;
        if (selected !== null) {
            return;
        }

        const sections = toValue(ids);
        const root = document.documentElement;
        if (window.innerHeight + window.scrollY >= root.scrollHeight - BOTTOM_TOLERANCE_PX) {
            activeId.value = sections.at(-1) ?? null;

            return;
        }

        const limit = line() + BOTTOM_TOLERANCE_PX;
        let current = sections[0] ?? null;
        for (const id of sections) {
            const element = document.getElementById(id);
            if (element && element.getBoundingClientRect().top <= limit) {
                current = id;
            }
        }
        activeId.value = current;
    }

    /** Posouvání a změna velikosti — přepočet nejvýš jednou za snímek. */
    function onScroll() {
        frame ??= window.requestAnimationFrame(update);
    }

    /** Uživatel posouvá sám — dál rozhoduje poloha stránky. */
    function release() {
        selected = null;
    }

    /**
     * Sekce vybraná klepnutím v navigaci.
     *
     * @param {string} id
     */
    function select(id) {
        activeId.value = id;
        selected = id;
    }

    onMounted(() => {
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        USER_SCROLL_EVENTS.forEach((event) => window.addEventListener(event, release, { passive: true }));
        update();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
        USER_SCROLL_EVENTS.forEach((event) => window.removeEventListener(event, release));
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
        }
    });

    return { activeId, select };
}
