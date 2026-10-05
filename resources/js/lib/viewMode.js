/**
 * Kompaktní zobrazení akcí (R82) — řádky místo karet s obrázkem ve Všech akcích a v Mých
 * slevách. Jedna volba pro obě stránky, pamatuje si ji prohlížeč (zásady, kap. 5).
 * „Jsem v obchodě“ v Mých slevách má vlastní volbu a začíná v řádcích (R62).
 *
 * @author Roman Hlaváček
 * @created 2026-10-05
 */
import { readStored, writeStored } from '@/lib/storage';
import { ref, watch } from 'vue';

/** Klíč v localStorage: akce jako řádky (true), nebo karty (výchozí). */
const COMPACT_VIEW_KEY = 'slevohlidka.view.compact';

/**
 * Volba kompaktního zobrazení; změna se hned uloží.
 *
 * @returns {import('vue').Ref<boolean>}
 */
export function useCompactView() {
    const compact = ref(readStored(COMPACT_VIEW_KEY, false) === true);
    watch(compact, (value) => writeStored(COMPACT_VIEW_KEY, value));

    return compact;
}
