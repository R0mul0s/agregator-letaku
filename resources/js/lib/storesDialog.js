/**
 * Okno se seznamem prodejen, ve kterých akce platí (R49) — jedno pro celou aplikaci
 * (StoresDialog.vue v AppLayout). Otevírá ho štítek „Jen …“ na kartě akce. Seznam se načte
 * až po otevření okna z adresy `offer.stores.url` (R106), počet prodejen je známý hned.
 *
 * @author Roman Hlaváček
 * @created 2026-10-03
 */
import { ABORTED, createLatestRequest } from '@/lib/latestRequest';
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

/** Načítání seznamu — odpověď pro dřív otevřenou akci se zahodí. */
const request = createLatestRequest();

/**
 * Otevře seznam prodejen akce a načte ho.
 *
 * @param {{ name: string, chainName: string, stores: { count: number, url: string } }} offer
 */
export async function showStoresDialog(offer) {
    Object.assign(storesDialogState, {
        open: true,
        offerName: offer.name,
        chainName: offer.chainName,
        count: offer.stores.count,
        stores: [],
        loading: true,
        failed: false,
    });

    const result = await request.json(offer.stores.url);
    if (result === ABORTED) {
        return;
    }
    if (result === null) {
        // Bez signálu (aplikace v telefonu v obchodě) nebo chyba serveru
        storesDialogState.failed = true;
    } else {
        storesDialogState.stores = result.stores;
    }
    storesDialogState.loading = false;
}

/** Zavře okno a zruší načítání. */
export function closeStoresDialog() {
    request.cancel();
    storesDialogState.open = false;
}
