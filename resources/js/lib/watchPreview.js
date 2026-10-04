/**
 * Náhled vlastních slov v Hlídám (R71): kolik akcí by položka teď našla a pár příkladů,
 * průběžně při psaní (WatchItemController::preview). Uživatel hned vidí, že „rum“ chytá
 * i „Rump steak“, a slova upraví dřív, než položku uloží.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { onBeforeUnmount, reactive, watch } from 'vue';

/** Pauza v psaní, po které se náhled načte (ms). */
const DEBOUNCE_MS = 400;

/** Od kolika znaků hledaných slov má náhled smysl (kratší server odmítne, R54). */
const MIN_LENGTH = 2;

/**
 * Náhled k právě psaným slovům.
 *
 * @param {string|null} url Adresa náhledu; null = bez náhledu
 * @param {() => { keywords: string, variant_keywords?: string, exclude_keywords?: string }} fields
 * @returns {{ loading: boolean, count: number|null, examples: object[] }}
 */
export function useWatchPreview(url, fields) {
    const state = reactive({ loading: false, count: null, examples: [] });
    let timer = null;
    let controller = null;

    /** Načte náhled; předchozí nedokončený požadavek zruší. */
    async function load() {
        const values = fields();
        if (!url || (values.keywords ?? '').trim().length < MIN_LENGTH) {
            Object.assign(state, { loading: false, count: null, examples: [] });

            return;
        }

        controller?.abort();
        controller = new AbortController();
        state.loading = true;
        const query = new URLSearchParams(Object.fromEntries(Object.entries(values).filter(([, value]) => (value ?? '').trim() !== '')));
        try {
            const response = await fetch(`${url}?${query}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = response.ok ? await response.json() : { count: null, examples: [] };
            Object.assign(state, { loading: false, count: data.count, examples: data.examples });
        } catch (error) {
            // Zrušený požadavek (psaní pokračuje) není chyba
            if (error.name !== 'AbortError') {
                Object.assign(state, { loading: false, count: null, examples: [] });
            }
        }
    }

    watch(
        fields,
        () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(load, DEBOUNCE_MS);
        },
        { deep: true, immediate: true },
    );

    onBeforeUnmount(() => {
        window.clearTimeout(timer);
        controller?.abort();
    });

    return state;
}
