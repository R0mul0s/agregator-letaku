<!--
    Karta jedné akční nabídky — obchod, název, ceny podle typu akce (R8), cena za jednotku, platnost.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { formatDate, formatPackage, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Nabídka z App\Domain\Offers\OfferPresenter; v Mých slevách navíc matchStatus (match / maybe). */
    offer: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Akce platí jen s kartou — hlavní cena je cena s kartou, běžná cena vedle. */
const isLoyaltyOnly = computed(() => props.offer.offerType === 'loyalty_only');

/** Balení: text obchodu, jinak množství a jednotka z názvu Tesco („1 l“, „500 g“). */
const packageLabel = computed(() => {
    if (props.offer.packageText) {
        return props.offer.packageText;
    }

    return props.offer.quantity ? formatPackage(props.offer.quantity, props.offer.unit, locale.value, (unit) => t(`package_units.${unit}`)) : null;
});

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
        <div class="offer-card__badges">
            <span class="chain-badge" :class="`chain-badge--${offer.chain}`">{{ offer.chainName }}</span>
            <span v-if="offer.matchStatus === 'maybe'" class="tag tag--warning" :title="t('offers.maybe_hint')">{{ t('offers.maybe') }}</span>
            <span v-if="offer.storeFormatName" class="tag">{{ offer.storeFormatName }}</span>
            <span v-if="offer.onlineOnly" class="tag tag--warning">{{ t('offers.online_only') }}</span>
            <span class="tag" :class="{ 'tag--accent': offer.offerType === 'discount' }">{{ t(`offer_types.${offer.offerType}`) }}</span>
        </div>

        <!-- Obrázek z CDN obchodu, nestahuje se k nám (R22); název nese nadpis, obrázek je dekorativní -->
        <img v-if="offer.imageUrl" :src="offer.imageUrl" alt="" class="offer-card__image" loading="lazy" referrerpolicy="no-referrer" />
        <h2 class="offer-card__name">{{ offer.name }}</h2>
        <p v-if="offer.description" class="offer-card__description">{{ offer.description }}</p>
        <p v-if="offer.variantNote" class="offer-card__variant">{{ offer.variantNote }}</p>
        <p v-if="packageLabel" class="offer-card__package">{{ packageLabel }}</p>

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
                <span v-if="offer.discountPercent" class="offer-card__discount">−{{ offer.discountPercent }} %</span>
            </template>
        </div>

        <p v-if="!isLoyaltyOnly && offer.loyaltyPrice !== null" class="offer-card__loyalty">
            {{ formatPrice(offer.loyaltyPrice, locale) }} {{ t('offers.with_card', { program: offer.loyaltyProgramName }) }}
        </p>
        <p v-if="unitPrice !== null && offer.unitPriceUnit" class="offer-card__unit-price">{{ unitPriceLabel(unitPrice) }}</p>

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
