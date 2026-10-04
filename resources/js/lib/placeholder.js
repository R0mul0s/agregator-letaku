/**
 * Příklady hledání, které se v prázdném poli pomalu střídají (R71) — „Zkuste: máslo“,
 * „Zkuste: pivo“. Při psaní a s omezeným pohybem (prefers-reduced-motion) stojí na prvním.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Jak dlouho jeden příklad svítí (ms). */
const ROTATE_MS = 3500;

/**
 * Text placeholderu, který střídá příklady.
 *
 * @param {() => string[]} examples Příklady (z překladů)
 * @param {import('vue').Ref<boolean>} paused Nestřídat (pole má fokus nebo text)
 * @param {(example: string) => string} format Věta s příkladem
 * @returns {import('vue').ComputedRef<string>}
 */
export function useRotatingPlaceholder(examples, paused, format) {
    const index = ref(0);
    let timer = null;

    onMounted(() => {
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reducedMotion || examples().length < 2) {
            return;
        }
        timer = window.setInterval(() => {
            if (!paused.value) {
                index.value = (index.value + 1) % examples().length;
            }
        }, ROTATE_MS);
    });

    onBeforeUnmount(() => window.clearInterval(timer));

    return computed(() => {
        const list = examples();

        return list.length ? format(list[index.value % list.length]) : '';
    });
}
