<!--
    Karta jedné akční nabídky — obchod, název, ceny podle typu akce (R8), cena za jednotku, platnost.
    Akce, která ještě nezačala, má štítek s datem začátku a odlišený rámeček (R76). Klepnutí na
    fotku nebo název otevře okno s detailem akce (R159).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainWatermark from '@/Components/ChainWatermark.vue';
import InfoIcon from '@/Components/InfoIcon.vue';
import OfferMenuButton from '@/Components/OfferMenuButton.vue';
import OfferPrices from '@/Components/OfferPrices.vue';
import OfferShareButton from '@/Components/OfferShareButton.vue';
import { formatDate, formatDiscount } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, MATCH_MAYBE, OFFER_TYPE, offerStoresLabel, packageLabel, startsLabel } from '@/lib/offer';
import { showOfferDetail } from '@/lib/offerDetail';
import { showStoresDialog } from '@/lib/storesDialog';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';

const props = defineProps({
    /**
     * Nabídka z App\Domain\Offers\OfferPresenter; v Mých slevách navíc matchStatus (match / maybe).
     * `stores` = { names, count } u akce, která neplatí ve všech prodejnách (R49), jinak null.
     */
    offer: { type: Object, required: true },
    /** Úroveň nadpisu názvu akce podle místa na stránce (pod h2 skupiny h3, R99). */
    headingLevel: { type: Number, default: 2 },
    /** Hlídaná položka v Mých slevách — tři tečky nabídnou i „Tohle ne“ (R125). */
    watchItem: { type: Object, default: null },
    /** Tři tečky s hlášením chyby (R125) — na stránkách admina (katalog, Hlášení) ne. */
    withMenu: { type: Boolean, default: true },
});

const t = useTranslations();

/** Obrázek z CDN obchodu se nenačetl (R99) — karta se ukáže jako bez obrázku, ne s rozbitou ikonou. */
const imageBroken = ref(false);
watch(
    () => props.offer.imageUrl,
    () => {
        imageBroken.value = false;
    },
);

/** Obrázek, který jde ukázat. */
const hasImage = computed(() => Boolean(props.offer.imageUrl) && !imageBroken.value);
const page = usePage();
const locale = computed(() => page.props.locale);

/** Vysvětlení štítku „Možná“ pod štítky — otevírá se klepnutím (R55). */
const maybeHintOpen = ref(false);
const maybeHintId = useId();

/** Sleva v procentech na cenovku přes obrázek. */
const discount = computed(() => discountPercent(props.offer));

/** Akce, která ještě nezačala (R76): „Od zítra“, „Od st 8. 10.“; null = už platí. */
const starts = computed(() => startsLabel(props.offer, locale.value, t));

/** Štítek akce, která neplatí ve všech prodejnách (R49), jinak null. */
const storesLabel = computed(() => offerStoresLabel(props.offer, t));

/** Detail akce v okně (R159), v Mých slevách s hlídanou položkou kvůli „Tohle ne“. */
function openDetail() {
    showOfferDetail(props.offer, props.watchItem);
}

/** Balení: text obchodu, jinak množství a jednotka z názvu Tesco („1 l“, „500 g“). */
const packageText = computed(() => packageLabel(props.offer, locale.value, t));
</script>

<template>
    <article class="offer-card" :class="{ 'offer-card--upcoming': starts }">
        <ChainWatermark :chain="offer.chain" />
        <div class="offer-card__badges">
            <!-- Klepnutím název obchodu (R118) -->
            <ChainLogo :chain="offer.chain" revealable />
            <!-- Ještě nezačala (R76) — v obchodě zatím neplatí -->
            <span v-if="starts" class="tag tag--upcoming">{{ starts }}</span>
            <!-- Vysvětlení klepnutím — title se na dotykovém displeji neukáže (R55) -->
            <button
                v-if="offer.matchStatus === MATCH_MAYBE"
                type="button"
                class="tag tag--warning tag--info"
                :aria-expanded="maybeHintOpen ? 'true' : 'false'"
                :aria-controls="maybeHintId"
                @click="maybeHintOpen = !maybeHintOpen"
            >
                {{ t('offers.maybe') }}
                <InfoIcon />
            </button>
            <span v-if="offer.storeFormatName" class="tag">{{ offer.storeFormatName }}</span>
            <span v-if="offer.onlineOnly" class="tag tag--warning">{{ t('offers.online_only') }}</span>
            <!-- Akce jen v některých prodejnách (R49): vybrané prodejny, kde platí, jinak počet -->
            <button v-if="offer.stores" type="button" class="tag tag--warning tag--info" :title="t('offers.only_in_title')" @click="showStoresDialog(offer)">
                {{ storesLabel }}
                <!-- Klepnutím seznam prodejen -->
                <InfoIcon />
            </button>
            <span class="tag" :class="{ 'tag--accent': offer.offerType === OFFER_TYPE.DISCOUNT }">{{ t(`offer_types.${offer.offerType}`) }}</span>
        </div>
        <p v-if="offer.matchStatus === MATCH_MAYBE" :id="maybeHintId" class="offer-card__hint" :hidden="!maybeHintOpen">{{ t('offers.maybe_hint') }}</p>

        <!-- Obrázek z CDN obchodu, nestahuje se k nám (R22); název nese nadpis, obrázek je dekorativní.
             Sleva jako červená cenovka přes obrázek (motiv z loga); čtečka ji má i u ceny.
             Klepnutí na obrázek i název otevře detail akce (R159) — z klávesnice tlačítko v názvu. -->
        <div v-if="hasImage" class="offer-card__media offer-card__media--open" @click="openDetail">
            <img :src="offer.imageUrl" alt="" class="offer-card__image" loading="lazy" referrerpolicy="no-referrer" @error="imageBroken = true" />
            <span v-if="discount" class="offer-card__sticker" aria-hidden="true">{{ formatDiscount(discount) }}</span>
        </div>
        <!-- Bez fotky (Penny, Albert, letáky Lidlu a Globusu) jemný přechod se siluetou maskota —
             karty mají stejně vysoké pole a prázdná plocha nevypadá jako chyba (R138) -->
        <div v-else class="offer-card__media offer-card__media--none offer-card__media--open" aria-hidden="true" @click="openDetail">
            <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="offer-card__placeholder" loading="lazy" />
            <span v-if="discount" class="offer-card__sticker">{{ formatDiscount(discount) }}</span>
        </div>
        <component :is="`h${headingLevel}`" class="offer-card__name">
            <button type="button" class="offer-card__open" @click="openDetail">{{ offer.name }}</button>
        </component>
        <p v-if="offer.description" class="offer-card__description">{{ offer.description }}</p>
        <p v-if="offer.variantNote" class="offer-card__variant">{{ offer.variantNote }}</p>
        <p v-if="packageText" class="offer-card__package">{{ packageText }}</p>

        <OfferPrices :offer="offer" />

        <footer class="offer-card__footer">
            <span>{{ t('offers.valid', { from: formatDate(offer.validFrom, locale), to: formatDate(offer.validTo, locale) }) }}</span>
            <span class="offer-card__links">
                <a v-if="offer.sourceUrl" :href="offer.sourceUrl" class="link" target="_blank" rel="noopener noreferrer">{{ t(offer.sourceIsLeaflet ? 'offers.source_leaflet' : 'offers.source') }}</a>
                <!-- Poslat akci partnerovi (R130) -->
                <OfferShareButton :offer="offer" />
                <!-- Hlášení chyby, v Mých slevách i „Tohle ne“ (R125) -->
                <OfferMenuButton v-if="withMenu" :offer="offer" :watch-item="watchItem" />
            </span>
        </footer>
        <!-- Akce ke kartě (oprava přiřazení v katalogu) -->
        <div v-if="$slots.default" class="offer-card__actions">
            <slot />
        </div>
    </article>
</template>
