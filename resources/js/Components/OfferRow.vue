<!--
    Akce jako kompaktní řádek (R62) — „Jsem v obchodě“ v Mých slevách: název s balením, cena
    za jednotku, cena, kterou uživatel zaplatí, sleva a tlačítko do nákupního seznamu. Velká karta
    s obrázkem by v obchodě znamenala hodně posouvání; klepnutí na název otevře detail akce (R159). V centru upozornění (R74) i s obchodem;
    skončená akce je ztlumená a bez tlačítka do seznamu. Akce nejlevnější za sledované období
    (R59) má štítek, akce, která ještě nezačala, štítek s datem začátku (R76). Ve Všech akcích
    (kompaktní zobrazení, R82) bez ceny uživatele: akce jen s kartou ukáže cenu s kartou. U akce s cenou
    s kartou je vidět i druhá cena — s kartou, nebo bez ní (R161).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import OfferMenuButton from '@/Components/OfferMenuButton.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, MATCH_MAYBE, OFFER_TYPE, offerUnitPriceLabel, packageLabel, startsLabel, unitPriceLabel } from '@/lib/offer';
import { showOfferDetail } from '@/lib/offerDetail';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /**
     * Akce z OfferPresenter; v Mých slevách a centru upozornění navíc userPrice (cena, kterou
     * uživatel zaplatí) a matchStatus, v centru upozornění i ended.
     */
    offer: { type: Object, required: true },
    /** Vypsat i obchod — řádky nejsou rozdělené po obchodech (centrum upozornění). */
    withChain: { type: Boolean, default: false },
    /** Hlídaná položka v Mých slevách — tři tečky nabídnou i „Tohle ne“ (R125). */
    watchItem: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

const discount = computed(() => discountPercent(props.offer));
const packageText = computed(() => packageLabel(props.offer, locale.value, t));

/** Akce, která ještě nezačala (R76): „Od zítra“, „Od st 8. 10.“; null = už platí. */
const starts = computed(() => startsLabel(props.offer, locale.value, t));

/** Akce bez ceny uživatele (Všechny akce, R82) — cenu s kartou ukáže zvlášť. */
const withoutUserPrice = computed(() => props.offer.userPrice === undefined);

/** Akce jen s kartou bez ceny uživatele — hlavní cena je cena s kartou jako na kartě akce. */
const loyaltyOnly = computed(() => withoutUserPrice.value && props.offer.offerType === OFFER_TYPE.LOYALTY_ONLY);

/** Místo ceny text akce na více kusů („3 za cenu 2“) — zalamuje se v omezené šířce. */
const isPromotionText = computed(() => props.offer.offerType === OFFER_TYPE.MULTIBUY && Boolean(props.offer.promotionText));

/** Cena: u akce na více kusů text akce („3 za cenu 2“), jinak cena, kterou uživatel zaplatí. */
const price = computed(() => {
    if (isPromotionText.value) {
        return props.offer.promotionText;
    }
    if (loyaltyOnly.value) {
        return formatPrice(props.offer.loyaltyPrice, locale.value);
    }

    return formatPrice(props.offer.userPrice ?? props.offer.price, locale.value);
});

/**
 * Druhá cena u akce s cenou s kartou — obě ceny jako na kartě akce (R161). Bez ceny uživatele:
 * u akce jen s kartou „s kartou X, běžně 29,90 Kč“, jinak „19,90 Kč s kartou X“. S cenou uživatele ta, kterou
 * neplatí: s kartou „21,90 Kč bez karty“, bez ní „19,90 Kč s kartou X“.
 */
const loyaltyNote = computed(() => {
    const { loyaltyPrice, loyaltyProgramName, price: regularPrice, userPrice } = props.offer;
    if (loyaltyPrice === null || loyaltyPrice === undefined) {
        return null;
    }
    const withCard = t('offers.with_card', { program: loyaltyProgramName });
    if (withoutUserPrice.value) {
        if (!loyaltyOnly.value) {
            return `${formatPrice(loyaltyPrice, locale.value)} ${withCard}`;
        }

        // Akce jen s kartou: hlavní cena je s kartou, k ní běžná jako na kartě akce
        return regularPrice === null ? withCard : `${withCard}, ${t('offers.regular_price', { price: formatPrice(regularPrice, locale.value) })}`;
    }
    if (userPrice === loyaltyPrice) {
        return regularPrice === null || regularPrice === loyaltyPrice ? null : t('offers.without_card', { price: formatPrice(regularPrice, locale.value) });
    }

    return `${formatPrice(loyaltyPrice, locale.value)} ${withCard}`;
});

/**
 * Cena za jednotku k hlavní ceně; u akce na více kusů by byla z běžné ceny, neukazuje se.
 * Kdo platí cenou s kartou, má i cenu za jednotku s kartou (R161) — jinak by nesouhlasila s cenou.
 */
const unitPrice = computed(() => {
    const { loyaltyPrice, loyaltyUnitPrice, offerType, unitPriceUnit, userPrice } = props.offer;
    const paysWithCard = !withoutUserPrice.value && loyaltyPrice !== null && userPrice === loyaltyPrice;
    if (paysWithCard && offerType !== OFFER_TYPE.MULTIBUY && loyaltyUnitPrice !== null && loyaltyUnitPrice !== undefined && unitPriceUnit) {
        return unitPriceLabel(loyaltyUnitPrice, unitPriceUnit, locale.value, t);
    }

    return offerUnitPriceLabel(props.offer, locale.value, t);
});
</script>

<template>
    <li class="offer-row" :class="{ 'offer-row--ended': offer.ended }">
        <div class="offer-row__body">
            <!-- Popisek nad názvem — hlídaná položka v pohledu Podle obchodů (R102) -->
            <p v-if="$slots.label" class="offer-row__label"><slot name="label" /></p>
            <p class="offer-row__name">
                <!-- Klepnutí na název otevře okno s detailem akce (R159) -->
                <button type="button" class="offer-row__open" @click="showOfferDetail(offer, watchItem)">{{ offer.name }}</button>
                <span v-if="offer.matchStatus === MATCH_MAYBE" class="tag tag--warning">{{ t('offers.maybe') }}</span>
                <span v-if="offer.onlineOnly" class="tag tag--warning">{{ t('offers.online_only') }}</span>
                <span v-if="offer.ended" class="tag">{{ t('notifications.ended') }}</span>
                <!-- Ještě nezačala (R76) — upozornění na nové akce ji hlásí hned po zveřejnění -->
                <span v-else-if="starts" class="tag tag--upcoming">{{ starts }}</span>
                <!-- Nejlevněji za sledované období (R59, centrum upozornění 11c) -->
                <span v-if="!offer.ended && offer.priceHistory?.status === 'lowest'" class="tag tag--success">{{
                    t('offers.history.lowest', { weeks: offer.priceHistory.weeks })
                }}</span>
            </p>
            <p class="offer-row__meta">
                <span v-if="withChain" class="offer-row__chain">{{ offer.chainName }}</span>
                <span v-if="packageText">{{ packageText }}</span>
                <span v-if="unitPrice">{{ unitPrice }}</span>
                <span v-if="loyaltyNote">{{ loyaltyNote }}</span>
            </p>
        </div>
        <div class="offer-row__prices">
            <!-- Text akce na více kusů se zalomí v omezené šířce, jinak by roztáhl řádek přes displej -->
            <span class="offer-row__price" :class="{ 'offer-row__price--text': isPromotionText }">{{ price }}</span>
            <span v-if="discount" class="offer-row__discount">{{ formatDiscount(discount) }}</span>
        </div>
        <div v-if="!offer.ended" class="offer-row__actions">
            <ShoppingToggle :offer-id="offer.id" compact />
            <!-- Hlášení chyby, v Mých slevách i „Tohle ne“ (R125) -->
            <OfferMenuButton :offer="offer" :watch-item="watchItem" />
        </div>
    </li>
</template>
