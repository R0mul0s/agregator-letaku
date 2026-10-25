<!--
    Okno s detailem akce (R159) — jedno pro celou aplikaci (AppLayout), obsah řídí lib/offerDetail.js.
    Otevírá ho klepnutí na fotku nebo název akce na kartě a na název v řádku. Velká fotka, ceny
    jako na kartě, platnost a prodejny, odkaz do obchodu nebo letáku a co s akcí jde udělat:
    do nákupního seznamu, hlídat (Všechny akce), sdílet, „Tohle ne“ a hlášení chyby přes tři tečky
    (R125) a graf cen v akcích (R163). Nepřihlášený místo „Hlídat“ dostane výzvu k hlídání zdarma.

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainLogo from '@/Components/ChainLogo.vue';
import InfoIcon from '@/Components/InfoIcon.vue';
import OfferMenuButton from '@/Components/OfferMenuButton.vue';
import OfferPrices from '@/Components/OfferPrices.vue';
import OfferShareButton from '@/Components/OfferShareButton.vue';
import PriceHistoryChart from '@/Components/PriceHistoryChart.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import WatchOfferButton from '@/Components/WatchOfferButton.vue';
import { formatDate, formatDiscount } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { ABORTED, createLatestRequest } from '@/lib/latestRequest';
import { discountPercent, MATCH_MAYBE, offerStoresLabel, packageLabel, startsLabel } from '@/lib/offer';
import { closeOfferDetail, offerDetailState } from '@/lib/offerDetail';
import { showStoresDialog } from '@/lib/storesDialog';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/** Nejmenší počet akcí položky pro graf historie: tato a aspoň jedna dřívější. */
const MIN_HISTORY_POINTS = 2;

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

/** Historie cen položky (R163) — načte se po otevření okna; odpověď pro dřív otevřenou akci se zahodí. */
const history = ref(null);
const historyRequest = createLatestRequest();
watch(
    () => offerDetailState.open && offerDetailState.offer,
    async (current) => {
        history.value = null;
        if (!current?.historyUrl) {
            historyRequest.cancel();

            return;
        }
        const result = await historyRequest.json(current.historyUrl);
        if (result !== ABORTED && result !== null) {
            history.value = result;
        }
    },
);

/** Graf jen s aspoň jednou dřívější akcí — samotná tato akce by nic neřekla. */
const showHistory = computed(() => (history.value?.points?.length ?? 0) >= MIN_HISTORY_POINTS);
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
                <!-- „Tohle ne“ a hlášení chyby jako všude jinde — tři tečky (R125, R163) -->
                <OfferMenuButton :offer="offer" :watch-item="offerDetailState.watchItem" />
            </div>

            <!-- Ceny v akcích za sledované období (R163) — až po načtení, jen s dřívější akcí -->
            <PriceHistoryChart v-if="showHistory" :points="history.points" :weeks="history.weeks" />

            <!-- Nepřihlášený bez „Hlídat“ u akce: proč se registrovat (R158, R159) -->
            <div v-if="isGuest && !canWatch" class="offer-detail__guest">
                <p class="offer-detail__guest-text">{{ t('offer_detail.guest_text') }}</p>
                <Link :href="page.props.auth.registerUrl" class="button button--highlight">{{ t('offer_detail.guest_cta') }}</Link>
            </div>
        </div>
    </BottomSheet>
</template>
