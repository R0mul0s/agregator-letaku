/**
 * Okno s detailem akce (R159) — jedno pro celou aplikaci (OfferDetailSheet.vue v AppLayout).
 * Otevírá ho klepnutí na fotku nebo název akce na kartě a na název v řádku: lidé tam klepali
 * a nic se nestalo (Clarity, mrtvá kliknutí).
 *
 * @author Roman Hlaváček
 * @created 2026-10-25
 */
import { trackEvent } from '@/lib/consent';
import { reactive } from 'vue';

/** Otevřené okno: akce z OfferPresenter a hlídaná položka z Mých slev (pro „Tohle ne“), nebo null. */
export const offerDetailState = reactive({
    open: false,
    offer: null,
    watchItem: null,
});

/**
 * Otevře okno pro akci a změří to (jen se souhlasem, parametr jen obchod).
 *
 * @param {object} offer
 * @param {object|null} [watchItem]
 */
export function showOfferDetail(offer, watchItem = null) {
    Object.assign(offerDetailState, { open: true, offer, watchItem });
    trackEvent('offer_detail', { chain: offer.chain });
}

/** Zavře okno. */
export function closeOfferDetail() {
    offerDetailState.open = false;
}
