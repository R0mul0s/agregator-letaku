<!--
    Skupina hlídané položky v Mých slevách — sbalitelná. Hlavička ukazuje souhrn (počet akcí,
    nejnižší cenu, nejvyšší slevu) a akce upravit / přestat hlídat; po rozbalení akce a zmínky.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import MentionCard from '@/Components/MentionCard.vue';
import OfferCard from '@/Components/OfferCard.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import { formatPrice } from '@/lib/format';
import { confirmDialog } from '@/lib/confirm';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

const props = defineProps({
    /** Položka z HomeController (akce s cenou pro uživatele, zmínky, adresy úprav); po výběru obchodu jen jeho akce. */
    item: { type: Object, required: true },
    /** Jak často chodí e-mailový souhrn („denně“), null = vypnutý (R42). */
    digestFrequency: { type: String, default: null },
    /** Nastavení souhrnu v účtu. */
    digestUrl: { type: String, required: true },
});

/** Rozbalená skupina (řídí stránka — pamatuje si stav a umí rozbalit vše). */
const expanded = defineModel('expanded', { type: Boolean, default: false });

const t = useTranslations();
const page = usePage();
const bodyId = useId();

/** Nejnižší cena, kterou uživatel za některou z akcí zaplatí (userPrice z HomeController), nebo null. */
const lowestPrice = computed(() => {
    const prices = props.item.offers.map((offer) => offer.userPrice).filter((price) => price !== null);

    return prices.length ? Math.min(...prices) : null;
});

/** Nejvyšší sleva mezi akcemi položky, nebo null. */
const bestDiscount = computed(() => {
    const discounts = props.item.offers.map(discountPercent).filter(Boolean);

    return discounts.length ? Math.max(...discounts) : null;
});

/** Po potvrzení položku přestane hlídat; stránka zůstane na Mých slevách. */
async function remove() {
    const confirmed = await confirmDialog({
        title: t('watch.delete_confirm_title'),
        message: t('watch.delete_confirm', { name: props.item.name }),
        confirmLabel: t('watch.stop'),
    });
    if (confirmed) {
        router.delete(props.item.deleteUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <section :id="`polozka-${item.id}`" class="watch-group" :class="{ 'watch-group--expanded': expanded }">
        <div class="watch-group__header">
            <h2 class="watch-group__heading">
                <button type="button" class="watch-group__toggle" :aria-expanded="expanded ? 'true' : 'false'" :aria-controls="bodyId" @click="expanded = !expanded">
                    <span class="watch-group__chevron" aria-hidden="true">▸</span>
                    <span class="watch-group__name">{{ item.name }}</span>
                    <span class="watch-group__count">{{ t('home.count', { count: item.offers.length }) }}</span>
                    <span v-if="lowestPrice !== null" class="watch-group__summary">
                        {{ t('watch.lowest_price', { price: formatPrice(lowestPrice, page.props.locale) }) }}
                    </span>
                    <span v-if="bestDiscount" class="watch-group__discount">−{{ bestDiscount }} %</span>
                    <span v-if="!item.offers.length && item.mentions.length" class="watch-group__summary">
                        {{ t('watch.mentions_count', { count: item.mentions.length }) }}
                    </span>
                </button>
            </h2>
            <div class="watch-group__actions">
                <Link v-if="!item.fromCatalog" :href="item.editUrl" class="icon-button" :title="t('watch.edit')">
                    <!-- Tužka -->
                    <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4" /></svg>
                    <span class="visually-hidden">{{ t('watch.edit') }} {{ item.name }}</span>
                </Link>
                <button type="button" class="icon-button icon-button--danger" :title="t('watch.stop')" @click="remove">
                    <!-- Koš -->
                    <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
                    <span class="visually-hidden">{{ t('watch.stop') }} {{ item.name }}</span>
                </button>
            </div>
        </div>

        <!-- Karty se vykreslí až po rozbalení — sbalené skupiny nenačítají obrázky -->
        <div :id="bodyId" class="watch-group__body" :hidden="!expanded">
            <template v-if="expanded">
                <div v-if="!item.offers.length && !item.mentions.length" class="watch-group__empty">
                    <span class="watch-group__empty-icon" aria-hidden="true">
                        <!-- Oko — Slevohlídka hlídá dál -->
                        <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
                    </span>
                    <p>
                        <strong class="watch-group__empty-title">{{ t('home.no_offers') }}</strong>
                        <span class="watch-group__empty-hint">{{ t('home.no_offers_hint') }}</span>
                        <span v-if="digestFrequency" class="watch-group__empty-hint">{{ t('home.no_offers_digest_on', { frequency: digestFrequency }) }}</span>
                        <span v-else class="watch-group__empty-hint">
                            {{ t('home.no_offers_digest_off') }}
                            <Link :href="digestUrl" class="link">{{ t('home.no_offers_digest_link') }}</Link>
                        </span>
                    </p>
                </div>
                <div v-if="item.offers.length" class="offer-grid">
                    <OfferCard v-for="offer in item.offers" :key="offer.id" :offer="offer">
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
