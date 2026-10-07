/**
 * Okno se seznamem prodejen, ve kterých akce platí (R49) — jedno pro celou aplikaci
 * (StoresDialog.vue v AppLayout). Otevírá ho štítek „Jen …“ na kartě akce. Seznam se načte
 * až po otevření okna z adresy `offer.stores.url` (R106), počet prodejen je známý hned.
 *
 * @author Roman Hlaváček
 * @created 2026-10-03
 */
import { reactive } from 'vue';

/** Otevřené okno: název akce, obchod, počet a prodejny [{ name, selected }] po načtení. */
export const storesDialogState = reactive({
    open: false,
    offerName: '',
    chainName: '',
    count: 0,
    stores: [],
    loading: false,
    failed: false,
});

/** Probíhající načítání — odpověď pro dřív otevřenou akci se zahodí. */
let request = null;

/**
 * Otevře seznam prodejen akce a načte ho.
 *
 * @param {{ name: string, chainName: string, stores: { count: number, url: string } }} offer
 */
export async function showStoresDialog(offer) {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    Object.assign(storesDialogState, {
        open: true,
        offerName: offer.name,
        chainName: offer.chainName,
        count: offer.stores.count,
        stores: [],
        loading: true,
        failed: false,
    });

    try {
        const response = await fetch(offer.stores.url, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        storesDialogState.stores = (await response.json()).stores;
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        // Bez signálu (aplikace v telefonu v obchodě) nebo chyba serveru
        storesDialogState.failed = true;
    }
    if (request === controller) {
        storesDialogState.loading = false;
        request = null;
    }
}

/** Zavře okno a zruší načítání. */
export function closeStoresDialog() {
    request?.abort();
    request = null;
    storesDialogState.open = false;
}
