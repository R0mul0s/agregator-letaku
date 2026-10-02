<!--
    Moje slevy — úvodní pruh s maskotem a souhrnem, akce k hlídaným položkám ve sledovaných
    obchodech (R18, R19) a zmínky v letácích bez ceny (R27).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import MentionCard from '@/Components/MentionCard.vue';
import OfferCard from '@/Components/OfferCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    hasFollowedChains: { type: Boolean, required: true },
    urls: { type: Object, required: true },
    /** Hlídané položky s nabídkami od nejnižší ceny za jednotku a zmínkami v letácích (App\Domain\Matching\MyOffers). */
    watchItems: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Křestní jméno do pozdravu (první slovo jména účtu). */
const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? '');

/** Souhrn do úvodního pruhu: počet akcí a nejvyšší sleva napříč hlídanými položkami. */
const summary = computed(() => {
    const offers = props.watchItems.flatMap((item) => item.offers);
    const discounts = offers.map(discountPercent).filter(Boolean);

    return { offers: offers.length, bestDiscount: discounts.length ? Math.max(...discounts) : null };
});
</script>

<template>
    <AppLayout>
        <Head :title="t('home.title')" />

        <!-- Úvodní pruh: maskot, pozdrav a co Slevohlídka právě ulovila -->
        <section class="home-hero">
            <img src="/images/brand/icon-192.png" alt="" class="home-hero__mascot" />
            <div class="home-hero__body">
                <h1 class="home-hero__title">{{ firstName ? t('home.hello', { name: firstName }) : t('home.title') }}</h1>
                <p class="home-hero__text">{{ t('home.hero_text') }}</p>
                <ul v-if="watchItems.length" class="home-hero__stats">
                    <li class="home-hero__stat">
                        <strong class="home-hero__stat-value">{{ watchItems.length }}</strong>
                        {{ t('home.stat_items', { count: watchItems.length }) }}
                    </li>
                    <li class="home-hero__stat">
                        <strong class="home-hero__stat-value">{{ summary.offers }}</strong>
                        {{ t('home.stat_offers', { count: summary.offers }) }}
                    </li>
                    <li v-if="summary.bestDiscount" class="home-hero__stat home-hero__stat--accent">
                        <strong class="home-hero__stat-value">−{{ summary.bestDiscount }} %</strong>
                        {{ t('home.stat_best') }}
                    </li>
                </ul>
            </div>
            <Link v-if="watchItems.length" :href="urls.watchItems" class="button button--primary home-hero__action">{{ t('home.edit_watch_items') }}</Link>
        </section>

        <EmptyState v-if="!hasFollowedChains" :text="t('home.no_chains')">
            <Link :href="urls.preferences" class="button button--primary">{{ t('home.no_chains_link') }}</Link>
        </EmptyState>
        <EmptyState v-else-if="!watchItems.length" :text="t('home.no_watch_items')">
            <Link :href="urls.watchItems" class="button button--primary">{{ t('home.no_watch_items_link') }}</Link>
        </EmptyState>

        <template v-else>
            <section v-for="item in watchItems" :key="item.id" class="watch-group">
                <h2 class="watch-group__title">
                    {{ item.name }}
                    <span class="watch-group__count">{{ t('home.count', { count: item.offers.length }) }}</span>
                </h2>
                <p v-if="!item.offers.length && !item.mentions.length" class="page__empty">{{ t('home.no_offers') }}</p>
                <div v-if="item.offers.length" class="offer-grid">
                    <OfferCard v-for="offer in item.offers" :key="offer.id" :offer="offer" />
                </div>

                <template v-if="item.mentions.length">
                    <h3 class="watch-group__subtitle">{{ t('home.mentions_title') }}</h3>
                    <p class="watch-group__hint">{{ t('home.mentions_hint') }}</p>
                    <div class="mention-grid">
                        <MentionCard v-for="mention in item.mentions" :key="mention.id" :mention="mention" />
                    </div>
                </template>
            </section>
        </template>
    </AppLayout>
</template>
