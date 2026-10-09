/**
 * Okno „Tohle ne“ a hlášení chyby v akci (R125) — jedno pro celou aplikaci
 * (OfferActionsSheet.vue v AppLayout). V Mých slevách ho otevírá tlačítko „Tohle ne“ s hlídanou
 * položkou (skrýt akci, vyloučit slovo, nahlásit chybu), jinde tři tečky na kartě akce jen
 * s hlášením chyby.
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { reactive } from 'vue';

/**
 * Pole požadavku z okna (WatchItemExclusionController::INLINE_FIELD) — server nepošle toast,
 * okno zůstane otevřené a potvrdí to samo.
 */
export const INLINE_FIELD = 'inline';

/**
 * Otevřené okno: akce z OfferPresenter (v Mých slevách s `excludeWords`) a hlídaná položka
 * z HomeController ({ name, hideOfferUrl, excludeWordUrl }), nebo null — pak jen hlášení chyby.
 */
export const offerActionsState = reactive({
    open: false,
    offer: null,
    watchItem: null,
});

/**
 * Otevře okno pro akci.
 *
 * @param {object} offer
 * @param {object|null} [watchItem] Hlídaná položka, u které jde akci skrýt
 */
export function showOfferActions(offer, watchItem = null) {
    Object.assign(offerActionsState, { open: true, offer, watchItem });
}

/** Zavře okno. */
export function closeOfferActions() {
    offerActionsState.open = false;
}
