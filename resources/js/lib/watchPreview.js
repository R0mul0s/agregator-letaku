/**
 * Náhled vlastních slov v Hlídám (R71): kolik akcí by položka teď našla a pár příkladů,
 * průběžně při psaní (WatchItemController::preview). Uživatel hned vidí, že „rum“ chytá
 * i „Rump steak“, a slova upraví dřív, než položku uloží. Když slova teď nic nenajdou, náhled
 * řekne, kdy naposledy byla akce, nebo že jsme ji od začátku sledování neviděli (R104).
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { formatDate, formatShortDate } from '@/lib/format';
import { onBeforeUnmount, reactive, watch } from 'vue';

/** Pauza v psaní, po které se náhled načte (ms). */
const DEBOUNCE_MS = 400;

/** Od kolika znaků hledaných slov má náhled smysl (kratší server odmítne, R54). */
const MIN_LENGTH = 2;

/**
 * Náhled bez výsledku (nic se nehledá nebo požadavek selhal).
 *
 * @returns {{ count: null, examples: object[], lastSeen: null, trackingSince: null }}
 */
function emptyResult() {
    return { count: null, examples: [], lastSeen: null, trackingSince: null };
}

/**
 * Náhled k právě psaným slovům.
 *
 * @param {string|null} url Adresa náhledu; null = bez náhledu
 * @param {() => { keywords: string, variant_keywords?: string, exclude_keywords?: string }} fields
 * @returns {{ loading: boolean, count: number|null, examples: object[], lastSeen: object|null, trackingSince: string|null }}
 */
export function useWatchPreview(url, fields) {
    const state = reactive({ loading: false, ...emptyResult() });
    let timer = null;
    let controller = null;

    /** Načte náhled; předchozí nedokončený požadavek zruší. */
    async function load() {
        const values = fields();
        // Zrušit i při zkrácení slov — pozdní odpověď by jinak vrátila starý počet
        controller?.abort();
        if (!url || (values.keywords ?? '').trim().length < MIN_LENGTH) {
            Object.assign(state, { loading: false, ...emptyResult() });

            return;
        }

        controller = new AbortController();
        state.loading = true;
        const query = new URLSearchParams(Object.fromEntries(Object.entries(values).filter(([, value]) => (value ?? '').trim() !== '')));
        try {
            const response = await fetch(`${url}?${query}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = response.ok ? await response.json() : emptyResult();
            Object.assign(state, { loading: false, ...emptyResult(), ...data });
        } catch (error) {
            // Zrušený požadavek (psaní pokračuje) není chyba
            if (error.name !== 'AbortError') {
                Object.assign(state, { loading: false, ...emptyResult() });
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

/**
 * Texty náhledu, když slova teď nic nenajdou (R104): kdy naposledy byla akce, nebo od kdy ji
 * marně hledáme; bez historie obecné „hlídáme dál“.
 *
 * @param {{ lastSeen: { endedOn: string }|null, trackingSince: string|null }} preview
 * @param {string} locale
 * @param {(key: string, replace?: object) => string} t
 * @returns {{ title: string, short: string, hint: string|null }} title a hint pro formulář, short do řádku našeptávače
 */
export function emptyPreviewTexts(preview, locale, t) {
    if (preview.lastSeen) {
        const date = formatDate(preview.lastSeen.endedOn, locale);

        return { title: t('watch.preview_last_seen', { date }), short: t('watch.preview_last_seen_short', { date }), hint: t('watch.preview_last_seen_hint') };
    }

    if (preview.trackingSince) {
        const date = formatShortDate(preview.trackingSince, locale);

        return { title: t('watch.preview_never', { date }), short: t('watch.preview_never_short'), hint: t('watch.preview_never_hint') };
    }

    return { title: t('watch.preview_none'), short: t('watch.preview_none'), hint: null };
}
