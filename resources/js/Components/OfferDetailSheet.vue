<!--
    Okno s detailem akce (R159) — jedno pro celou aplikaci (AppLayout), obsah řídí lib/offerDetail.js.
    Otevírá ho klepnutí na fotku nebo název akce na kartě a na název v řádku. Velká fotka, ceny
    jako na kartě, platnost a prodejny, odkaz do obchodu nebo letáku a co s akcí jde udělat:
    do nákupního seznamu, hlídat (Všechny akce), sdílet, „Tohle ne“ a hlášení chyby přes okno
    akcí (R125). Nepřihlášený místo toho dostane výzvu k hlídání zdarma.

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainLogo from '@/Components/ChainLogo.vue';
import InfoIcon from '@/Components/InfoIcon.vue';
import OfferPrices from '@/Components/OfferPrices.vue';
import OfferShareButton from '@/Components/OfferShareButton.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import WatchOfferButton from '@/Components/WatchOfferButton.vue';
import { formatDate, formatDiscount } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, MATCH_MAYBE, offerStoresLabel, packageLabel, startsLabel } from '@/lib/offer';
import { showOfferActions } from '@/lib/offerActions';
import { closeOfferDetail, offerDetailState } from '@/lib/offerDetail';
import { showStoresDialog } from '@/lib/storesDialog';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Otevřené okno — zavření křížkem, Esc nebo klepnutím vedle jde do sdíleného stavu. */
const open = computed({
    get: () => offerDetailState.open,
    set: (value) => {
        if (!value) {
            closeOfferDetail();
        }
    },
});

const offer = computed(() => offerDetailState.offer);
const isGuest = computed(() => !page.props.auth.user);

/** Obrázek z CDN obchodu se nenačetl — místo rozbité ikony silueta maskota jako na kartě. */
const imageBroken = ref(false);
watch(offer, () => {
    imageBroken.value = false;
});
const hasImage = computed(() => Boolean(offer.value?.imageUrl) && !imageBroken.value);

const discount = computed(() => (offer.value ? discountPercent(offer.value) : null));
const starts = computed(() => (offer.value ? startsLabel(offer.value, locale.value, t) : null));
const packageText = computed(() => (offer.value ? packageLabel(offer.value, locale.value, t) : null));
const storesLabel = computed(() => (offer.value ? offerStoresLabel(offer.value, t) : null));

/** „Hlídat“ jako na kartě ve Všech akcích — jen kde stránka posílá cíl a adresy (R60). */
const canWatch = computed(() => Boolean(offer.value?.watchTarget && page.props.watchUrls));

/** „Tohle ne“ a hlášení chyby (R125) jsou v okně akcí — jen přihlášený. */
function openActions() {
    const { offer: current, watchItem } = offerDetailState;
    closeOfferDetail();
    showOfferActions(current, watchItem);
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="offer?.name ?? ''">
        <div v-if="offer" class="offer-detail">
            <div v-if="hasImage" class="offer-detail__media">
                <img :src="offer.imageUrl" alt="" class="offer-detail__image" referrerpolicy="no-referrer" @error="imageBroken = true" />
                <span v-if="discount" class="offer-card__sticker" aria-hidden="true">{{ formatDiscount(discount) }}</span>
            </div>

            <div class="offer-detail__badges">
                <ChainLogo :chain="offer.chain" />
                <span class="offer-detail__chain">{{ offer.chainName }}</span>
                <span v-if="starts" class="tag tag--upcoming">{{ starts }}</span>
                <span v-if="offer.matchStatus === MATCH_MAYBE" class="tag tag--warning">{{ t('offers.maybe') }}</span>
                <span v-if="offer.storeFormatName" class="tag">{{ offer.storeFormatName }}</span>
                <span v-if="offer.onlineOnly" class="tag tag--warning">{{ t('offers.online_only') }}</span>
            </div>

            <p v-if="offer.description" class="offer-card__description">{{ offer.description }}</p>
            <p v-if="offer.variantNote" class="offer-card__variant">{{ offer.variantNote }}</p>
            <p v-if="packageText" class="offer-card__package">{{ packageText }}</p>

            <OfferPrices :offer="offer" />

            <p class="offer-detail__valid">{{ t('offers.valid', { from: formatDate(offer.validFrom, locale), to: formatDate(offer.validTo, locale) }) }}</p>
            <!-- Akce jen v některých prodejnách (R49) — klepnutím seznam prodejen -->
            <button v-if="offer.stores" type="button" class="tag tag--warning tag--info offer-detail__stores" @click="showStoresDialog(offer)">
                {{ storesLabel }}
                <InfoIcon />
            </button>

            <div class="offer-detail__actions">
                <a v-if="offer.sourceUrl" :href="offer.sourceUrl" class="button button--primary" target="_blank" rel="noopener noreferrer">
                    {{ t(offer.sourceIsLeaflet ? 'offers.source_leaflet' : 'offers.source') }}
                </a>
                <ShoppingToggle v-if="!offer.ended" :offer-id="offer.id" />
                <WatchOfferButton v-if="canWatch" :target="offer.watchTarget" :urls="page.props.watchUrls" />
                <OfferShareButton :offer="offer" />
            </div>

            <!-- Nepřihlášený bez „Hlídat“ u akce: proč se registrovat (R158, R159) -->
            <div v-if="isGuest && !canWatch" class="offer-detail__guest">
                <p class="offer-detail__guest-text">{{ t('offer_detail.guest_text') }}</p>
                <Link :href="page.props.auth.registerUrl" class="button button--highlight">{{ t('offer_detail.guest_cta') }}</Link>
            </div>

            <button v-if="page.props.offerReports" type="button" class="link offer-detail__more" @click="openActions">
                {{ offerDetailState.watchItem ? t('offer_detail.not_this') : t('offer_detail.report') }}
            </button>
        </div>
    </BottomSheet>
</template>
