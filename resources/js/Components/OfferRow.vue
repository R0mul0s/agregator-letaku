<!--
    Akce jako kompaktní řádek (R62) — „Jsem v obchodě“ v Mých slevách: název s balením, cena
    za jednotku, cena, kterou uživatel zaplatí, sleva a tlačítko do nákupního seznamu. Velká karta
    s obrázkem by v obchodě znamenala hodně posouvání. V centru upozornění (R74) i s obchodem;
    skončená akce je ztlumená a bez tlačítka do seznamu. Akce nejlevnější za sledované období
    (R59) má štítek, akce, která ještě nezačala, štítek s datem začátku (R76).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, packageLabel, startsLabel } from '@/lib/offer';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Akce z HomeController (OfferPresenter + matchStatus, userPrice), v centru upozornění i ended. */
    offer: { type: Object, required: true },
    /** Vypsat i obchod — řádky nejsou rozdělené po obchodech (centrum upozornění). */
    withChain: { type: Boolean, default: false },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

const discount = computed(() => discountPercent(props.offer));
const packageText = computed(() => packageLabel(props.offer, locale.value, t));

/** Akce, která ještě nezačala (R76): „Od zítra“, „Od st 8. 10.“; null = už platí. */
const starts = computed(() => startsLabel(props.offer, locale.value, t));

/** Cena: u akce na více kusů text akce („3 za cenu 2“), jinak cena, kterou uživatel zaplatí. */
const price = computed(() => {
    if (props.offer.offerType === 'multibuy' && props.offer.promotionText) {
        return props.offer.promotionText;
    }

    return formatPrice(props.offer.userPrice ?? props.offer.price, locale.value);
});

/** Cena za jednotku k hlavní ceně; u akce na více kusů by byla z běžné ceny, neukazuje se. */
const unitPrice = computed(() => {
    const { offerType, unitPriceUnit } = props.offer;
    const value = offerType === 'loyalty_only' ? props.offer.loyaltyUnitPrice : props.offer.unitPrice;
    if (offerType === 'multibuy' || value === null || !unitPriceUnit) {
        return null;
    }

    return t('offers.unit_price', { price: formatPrice(value, locale.value), unit: t(`unit_price_units.${unitPriceUnit}`) });
});
</script>

<template>
    <li class="offer-row" :class="{ 'offer-row--ended': offer.ended }">
        <div class="offer-row__body">
            <p class="offer-row__name">
                {{ offer.name }}
                <span v-if="offer.matchStatus === 'maybe'" class="tag tag--warning">{{ t('offers.maybe') }}</span>
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
            </p>
        </div>
        <div class="offer-row__prices">
            <span class="offer-row__price">{{ price }}</span>
            <span v-if="discount" class="offer-row__discount">−{{ discount }} %</span>
        </div>
        <ShoppingToggle v-if="!offer.ended" :offer-id="offer.id" compact />
    </li>
</template>
