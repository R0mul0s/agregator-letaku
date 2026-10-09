<!--
    Skupina hlídané položky s akcemi nebo zmínkami v Mých slevách — sbalitelná. Hlavička ukazuje
    souhrn (počet akcí, nejnižší cenu s obchodem — R100, nejvyšší slevu) a akce upravit / přestat
    hlídat; po rozbalení akce a zmínky. Akce, které ještě nezačaly, jsou v sekci Brzy
    (UpcomingSection) — tady jen jejich počet a „Vyplatí se počkat“, když je některá výrazně
    levnější (R76). Položky bez akcí jsou v sekci Zatím bez akce (WaitingSection, R100).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import MentionCard from '@/Components/MentionCard.vue';
import OfferCard from '@/Components/OfferCard.vue';
import OfferRow from '@/Components/OfferRow.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import WatchItemActions from '@/Components/WatchItemActions.vue';
import { formatDate, formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, unitPriceLabel } from '@/lib/offer';
import { usePage } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

const props = defineProps({
    /**
     * Položka z HomeController (akce s cenou pro uživatele, budoucí akce `upcoming` a `waitTip`,
     * zmínky, adresy úprav); po výběru obchodu jen jeho akce, bez budoucích.
     */
    item: { type: Object, required: true },
    /** Akce jako kompaktní řádky místo karet — „Jsem v obchodě“ (R62) nebo volba zobrazení (R82). */
    compact: { type: Boolean, default: false },
    /** U řádku i obchod — mimo „Jsem v obchodě“ jsou v řádcích akce víc obchodů (R82). */
    withChain: { type: Boolean, default: false },
});

/** Rozbalená skupina (řídí stránka — pamatuje si stav a umí rozbalit vše). */
const expanded = defineModel('expanded', { type: Boolean, default: false });

const t = useTranslations();
const page = usePage();
const bodyId = useId();

/**
 * Akce s nejnižší cenou, kterou uživatel zaplatí (userPrice z HomeController), nebo null — její
 * cena a obchod jsou v hlavičce („od 19,90 Kč“ s logem, R100).
 */
const cheapestOffer = computed(() =>
    props.item.offers.filter((offer) => offer.userPrice !== null).reduce((cheapest, offer) => (cheapest && cheapest.userPrice <= offer.userPrice ? cheapest : offer), null),
);

/**
 * „Vyplatí se počkat“ (R76, App\Domain\Matching\WaitAdvice): obchod, od kdy, za kolik a o kolik
 * levněji než nejlevnější akce dnes; null = nevyplatí.
 */
const waitTipText = computed(() => {
    const tip = props.item.waitTip;
    if (!tip) {
        return null;
    }

    const locale = page.props.locale;

    return t('watch.wait_tip_text', {
        chain: tip.chainName,
        date: formatDate(tip.validFrom, locale),
        price: formatPrice(tip.userPrice, locale),
        unit_price: unitPriceLabel(tip.unitPrice, tip.unitPriceUnit, locale, t),
        percent: tip.savingPercent,
    });
});

/** Nejvyšší sleva mezi akcemi položky, nebo null. */
const bestDiscount = computed(() => {
    const discounts = props.item.offers.map(discountPercent).filter(Boolean);

    return discounts.length ? Math.max(...discounts) : null;
});

</script>

<template>
    <section :id="`polozka-${item.id}`" class="watch-group" :class="{ 'watch-group--expanded': expanded }">
        <div class="watch-group__header">
            <h2 class="watch-group__heading">
                <button type="button" class="watch-group__toggle" :aria-expanded="expanded ? 'true' : 'false'" :aria-controls="bodyId" @click="expanded = !expanded">
                    <span class="watch-group__chevron" aria-hidden="true">▸</span>
                    <span class="watch-group__name">{{ item.name }}</span>
                    <span class="watch-group__count">{{ t('home.count', { count: item.offers.length }) }}</span>
                    <span v-if="cheapestOffer" class="watch-group__summary">
                        {{ t('watch.lowest_price', { price: formatPrice(cheapestOffer.userPrice, page.props.locale) }) }}
                        <!-- Kde je nejlevněji (R100) — v obchodě je obchod jasný -->
                        <ChainLogo v-if="withChain" :chain="cheapestOffer.chain" class="watch-group__chain" />
                    </span>
                    <span v-if="bestDiscount" class="watch-group__discount">{{ formatDiscount(bestDiscount) }}</span>
                    <span v-if="!item.offers.length && item.mentions.length" class="watch-group__summary">
                        {{ t('watch.mentions_count', { count: item.mentions.length }) }}
                    </span>
                    <!-- Akce, které ještě nezačaly (R76) — jsou v sekci Brzy -->
                    <span v-if="item.upcoming.length" class="watch-group__upcoming">{{ t('watch.upcoming_count', { count: item.upcoming.length }) }}</span>
                    <span v-if="waitTipText" class="watch-group__wait">{{ t('watch.wait_tip') }}</span>
                </button>
            </h2>
            <WatchItemActions :item="item" />
        </div>

        <!-- Karty se vykreslí až po rozbalení — sbalené skupiny nenačítají obrázky -->
        <div :id="bodyId" class="watch-group__body" :hidden="!expanded">
            <template v-if="expanded">
                <p v-if="waitTipText" class="notice notice--success">
                    <strong>{{ t('watch.wait_tip') }}:</strong> {{ waitTipText }}
                </p>
                <ul v-if="item.offers.length && compact" class="offer-rows">
                    <OfferRow v-for="offer in item.offers" :key="offer.id" :offer="offer" :with-chain="withChain" />
                </ul>
                <div v-else-if="item.offers.length" class="offer-grid">
                    <OfferCard v-for="offer in item.offers" :key="offer.id" :offer="offer" :heading-level="3">
                        <ShoppingToggle :offer-id="offer.id" />
                    </OfferCard>
                </div>

                <template v-if="item.mentions.length">
                    <h3 class="watch-group__subtitle">{{ t('home.mentions_title') }}</h3>
                    <p class="watch-group__hint">{{ t('home.mentions_hint') }}</p>
                    <div class="mention-grid">
                        <MentionCard v-for="mention in item.mentions" :key="mention.id" :mention="mention" />
                    </div>
                </template>
            </template>
        </div>
    </section>
</template>
