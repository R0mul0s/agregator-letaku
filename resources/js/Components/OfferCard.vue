<!--
    Karta jedné akční nabídky — obchod, název, ceny podle typu akce (R8), cena za jednotku, platnost.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainWatermark from '@/Components/ChainWatermark.vue';
import InfoIcon from '@/Components/InfoIcon.vue';
import { formatDate, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, packageLabel } from '@/lib/offer';
import { showStoresDialog } from '@/lib/storesDialog';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';

const props = defineProps({
    /**
     * Nabídka z App\Domain\Offers\OfferPresenter; v Mých slevách navíc matchStatus (match / maybe).
     * `stores` = { names, count } u akce, která neplatí ve všech prodejnách (R49), jinak null.
     */
    offer: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Vysvětlení štítku „Možná“ pod štítky — otevírá se klepnutím (R55). */
const maybeHintOpen = ref(false);
const maybeHintId = useId();

/** Sleva v procentech na cenovku přes obrázek. */
const discount = computed(() => discountPercent(props.offer));

/**
 * Štítek akce, která neplatí ve všech prodejnách (R49): vybrané prodejny, kde platí;
 * není-li v žádné vybrané, tak to; bez výběru názvy (pár prodejen) nebo počet.
 */
const storesLabel = computed(() => {
    const stores = props.offer.stores;
    if (!stores) {
        return null;
    }
    if (stores.names.length) {
        return t('offers.only_in_stores', { stores: stores.names.join(', ') });
    }

    return stores.elsewhere ? t('offers.not_in_my_stores') : t('offers.only_in_count', { count: stores.count });
});

/** Akce platí jen s kartou — hlavní cena je cena s kartou, běžná cena vedle. */
const isLoyaltyOnly = computed(() => props.offer.offerType === 'loyalty_only');

/** Balení: text obchodu, jinak množství a jednotka z názvu Tesco („1 l“, „500 g“). */
const packageText = computed(() => packageLabel(props.offer, locale.value, t));

/**
 * Cena za jednotku k hlavní ceně karty. U akce na více kusů by byla z běžné ceny,
 * v přehledu akcí by mátla — nezobrazuje se.
 */
const unitPrice = computed(() => {
    if (props.offer.offerType === 'multibuy') {
        return null;
    }

    return isLoyaltyOnly.value ? props.offer.loyaltyUnitPrice : props.offer.unitPrice;
});

/**
 * „Je to opravdu sleva?“ (R59): srovnání s dřívějšími akcemi stejné položky u obchodu
 * (App\Domain\Offers\PriceHistory); null = dřívější akce není.
 */
const historyLabel = computed(() => {
    const history = props.offer.priceHistory;
    if (!history) {
        return null;
    }

    return t(`offers.history.${history.status}`, {
        weeks: history.weeks,
        count: history.weeksAgo,
        price: formatPrice(history.price, locale.value),
    });
});

/**
 * Cena za jednotku jako „29,90 Kč / kg“.
 *
 * @param {number} halers
 * @returns {string}
 */
function unitPriceLabel(halers) {
    return t('offers.unit_price', { price: formatPrice(halers, locale.value), unit: t(`unit_price_units.${props.offer.unitPriceUnit}`) });
}
</script>

<template>
    <article class="offer-card">
        <ChainWatermark :chain="offer.chain" />
        <div class="offer-card__badges">
            <ChainLogo :chain="offer.chain" />
            <!-- Vysvětlení klepnutím — title se na dotykovém displeji neukáže (R55) -->
            <button
                v-if="offer.matchStatus === 'maybe'"
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
            <span class="tag" :class="{ 'tag--accent': offer.offerType === 'discount' }">{{ t(`offer_types.${offer.offerType}`) }}</span>
        </div>
        <p v-if="offer.matchStatus === 'maybe'" :id="maybeHintId" class="offer-card__hint" :hidden="!maybeHintOpen">{{ t('offers.maybe_hint') }}</p>

        <!-- Obrázek z CDN obchodu, nestahuje se k nám (R22); název nese nadpis, obrázek je dekorativní.
             Sleva jako červená cenovka přes obrázek (motiv z loga); čtečka ji má i u ceny. -->
        <div class="offer-card__media">
            <img v-if="offer.imageUrl" :src="offer.imageUrl" alt="" class="offer-card__image" loading="lazy" referrerpolicy="no-referrer" />
            <span v-if="discount" class="offer-card__sticker" aria-hidden="true">−{{ discount }} %</span>
        </div>
        <h2 class="offer-card__name">{{ offer.name }}</h2>
        <p v-if="offer.description" class="offer-card__description">{{ offer.description }}</p>
        <p v-if="offer.variantNote" class="offer-card__variant">{{ offer.variantNote }}</p>
        <p v-if="packageText" class="offer-card__package">{{ packageText }}</p>

        <div class="offer-card__prices">
            <template v-if="isLoyaltyOnly">
                <span class="offer-card__price">{{ formatPrice(offer.loyaltyPrice, locale) }}</span>
                <span class="offer-card__note">{{ t('offers.with_card', { program: offer.loyaltyProgramName }) }}</span>
                <span v-if="offer.price !== null" class="offer-card__note">{{ t('offers.regular_price', { price: formatPrice(offer.price, locale) }) }}</span>
            </template>
            <template v-else-if="offer.offerType === 'multibuy'">
                <span v-if="offer.promotionText" class="offer-card__price offer-card__price--text">{{ offer.promotionText }}</span>
                <span v-if="offer.price !== null" class="offer-card__note">{{ t('offers.regular_price', { price: formatPrice(offer.price, locale) }) }}</span>
            </template>
            <template v-else>
                <span class="offer-card__price">{{ formatPrice(offer.price, locale) }}</span>
                <s v-if="offer.originalPrice !== null" class="offer-card__original">{{ formatPrice(offer.originalPrice, locale) }}</s>
                <span v-if="discount" class="offer-card__discount">−{{ discount }} %</span>
            </template>
        </div>

        <p v-if="!isLoyaltyOnly && offer.loyaltyPrice !== null" class="offer-card__loyalty">
            {{ formatPrice(offer.loyaltyPrice, locale) }} {{ t('offers.with_card', { program: offer.loyaltyProgramName }) }}
        </p>
        <p v-if="unitPrice !== null && offer.unitPriceUnit" class="offer-card__unit-price">{{ unitPriceLabel(unitPrice) }}</p>
        <p v-if="historyLabel" class="offer-card__history" :class="`offer-card__history--${offer.priceHistory.status}`">{{ historyLabel }}</p>

        <footer class="offer-card__footer">
            <span>{{ t('offers.valid', { from: formatDate(offer.validFrom, locale), to: formatDate(offer.validTo, locale) }) }}</span>
            <a v-if="offer.sourceUrl" :href="offer.sourceUrl" class="link" target="_blank" rel="noopener noreferrer">{{ t('offers.source') }}</a>
        </footer>
        <!-- Akce ke kartě (oprava přiřazení v katalogu) -->
        <div v-if="$slots.default" class="offer-card__actions">
            <slot />
        </div>
    </article>
</template>
