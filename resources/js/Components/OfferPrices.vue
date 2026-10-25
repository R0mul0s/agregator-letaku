<!--
    Ceny akce podle typu (R8) — hlavní cena, přeškrtnutá a sleva, cena s kartou, cena za jednotku
    a srovnání s dřívějšími akcemi (R59). Sdílí ji karta akce a okno s detailem akce (R159).

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, OFFER_TYPE, offerUnitPriceLabel } from '@/lib/offer';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Nabídka z App\Domain\Offers\OfferPresenter. */
    offer: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Sleva v procentech (počítá server, R113). */
const discount = computed(() => discountPercent(props.offer));

/** Akce platí jen s kartou — hlavní cena je cena s kartou, běžná cena vedle. */
const isLoyaltyOnly = computed(() => props.offer.offerType === OFFER_TYPE.LOYALTY_ONLY);

/** Cena za jednotku k hlavní ceně („29,90 Kč / kg“); null bez balení a u akce na více kusů. */
const unitPrice = computed(() => offerUnitPriceLabel(props.offer, locale.value, t));

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
</script>

<template>
    <div class="offer-card__prices">
        <template v-if="isLoyaltyOnly">
            <span class="offer-card__price">{{ formatPrice(offer.loyaltyPrice, locale) }}</span>
            <span class="offer-card__note">{{ t('offers.with_card', { program: offer.loyaltyProgramName }) }}</span>
            <span v-if="offer.price !== null" class="offer-card__note">{{ t('offers.regular_price', { price: formatPrice(offer.price, locale) }) }}</span>
        </template>
        <template v-else-if="offer.offerType === OFFER_TYPE.MULTIBUY">
            <span v-if="offer.promotionText" class="offer-card__price offer-card__price--text">{{ offer.promotionText }}</span>
            <span v-if="offer.price !== null" class="offer-card__note">{{ t('offers.regular_price', { price: formatPrice(offer.price, locale) }) }}</span>
        </template>
        <template v-else>
            <span class="offer-card__price">{{ formatPrice(offer.price, locale) }}</span>
            <!-- Přeškrtnutí čtečka obvykle neohlásí — bez slova „původně“ by zazněly dvě ceny za sebou -->
            <s v-if="offer.originalPrice !== null" class="offer-card__original"
                ><span class="visually-hidden">{{ t('offers.original_price_label') }} </span>{{ formatPrice(offer.originalPrice, locale) }}</s
            >
            <span v-if="discount" class="offer-card__discount">{{ formatDiscount(discount) }}</span>
        </template>
    </div>

    <p v-if="!isLoyaltyOnly && offer.loyaltyPrice !== null" class="offer-card__loyalty">
        {{ formatPrice(offer.loyaltyPrice, locale) }} {{ t('offers.with_card', { program: offer.loyaltyProgramName }) }}
    </p>
    <p v-if="unitPrice" class="offer-card__unit-price">{{ unitPrice }}</p>
    <p v-if="historyLabel" class="offer-card__history" :class="`offer-card__history--${offer.priceHistory.status}`">{{ historyLabel }}</p>
</template>
