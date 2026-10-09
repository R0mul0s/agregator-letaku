/**
 * Čísla, která se „napočítají“ (úvodní stránka, R90): počty pod hlavním pruhem od nuly, když
 * se poprvé ukážou, a počet akcí v ukázce hlídání z původní hodnoty na novou. S omezeným
 * pohybem (prefers-reduced-motion) a bez IntersectionObserver hned konečná hodnota.
 *
 * @author Roman Hlaváček
 * @created 2026-10-06
 */
import { prefersReducedMotion } from '@/lib/scroll';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** Jak dlouho se čísla napočítávají při prvním zobrazení (ms). */
const COUNT_UP_MS = 1200;

/** Jak dlouho trvá přechod na novou hodnotu (ms). */
const CHANGE_MS = 400;

/**
 * Průběh animace se zpomalením na konci; volá onProgress s hodnotou 0–1 v každém snímku.
 *
 * @param {number} duration Délka (ms)
 * @param {(progress: number) => void} onProgress
 * @returns {() => void} Zrušení animace
 */
function tween(duration, onProgress) {
    const start = performance.now();
    let frame = 0;
    const step = (now) => {
        const linear = Math.min(1, (now - start) / duration);
        onProgress(1 - (1 - linear) ** 3);
        if (linear < 1) {
            frame = window.requestAnimationFrame(step);
        }
    };
    frame = window.requestAnimationFrame(step);

    return () => window.cancelAnimationFrame(frame);
}

/**
 * Hodnoty, které se napočítají od nuly, až se prvek poprvé objeví na obrazovce.
 *
 * @param {import('vue').Ref<HTMLElement|null>} element Prvek, jehož zobrazení animaci spustí
 * @param {() => number[]} targets Konečné hodnoty
 * @returns {import('vue').Ref<number[]>}
 */
export function useCountUp(element, targets) {
    const animated = !prefersReducedMotion() && 'IntersectionObserver' in window;
    const values = ref(animated ? targets().map(() => 0) : targets());
    let observer = null;
    let cancel = null;

    onMounted(() => {
        if (!animated || !element.value) {
            values.value = targets();

            return;
        }
        observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }
            observer.disconnect();
            const end = targets();
            cancel = tween(COUNT_UP_MS, (progress) => (values.value = end.map((value) => Math.round(value * progress))));
        });
        observer.observe(element.value);
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        cancel?.();
    });

    return values;
}

/**
 * Číslo, které při změně zdroje plynule přejde z původní hodnoty na novou.
 *
 * @param {() => number} source
 * @returns {import('vue').Ref<number>}
 */
export function useTweenedNumber(source) {
    const value = ref(source());
    let cancel = null;

    watch(source, (to) => {
        cancel?.();
        if (prefersReducedMotion()) {
            value.value = to;

            return;
        }
        const from = value.value;
        cancel = tween(CHANGE_MS, (progress) => (value.value = Math.round(from + (to - from) * progress)));
    });

    onBeforeUnmount(() => cancel?.());

    return value;
}
