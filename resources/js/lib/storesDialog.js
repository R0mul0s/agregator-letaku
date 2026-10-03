/**
 * Okno se seznamem prodejen, ve kterých akce platí (R49) — jedno pro celou aplikaci
 * (StoresDialog.vue v AppLayout). Otevírá ho štítek „Jen …“ na kartě akce.
 *
 * @author Roman Hlaváček
 * @created 2026-10-03
 */
import { reactive } from 'vue';

/** Otevřené okno: název akce, obchod a prodejny [{ name, selected }]. */
export const storesDialogState = reactive({
    open: false,
    offerName: '',
    chainName: '',
    stores: [],
});

/**
 * Otevře seznam prodejen akce.
 *
 * @param {{ name: string, chainName: string, stores: { list: { name: string, selected: boolean }[] } }} offer
 */
export function showStoresDialog(offer) {
    Object.assign(storesDialogState, { open: true, offerName: offer.name, chainName: offer.chainName, stores: offer.stores.list });
}

/** Zavře okno. */
export function closeStoresDialog() {
    storesDialogState.open = false;
}
