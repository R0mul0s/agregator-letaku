<!--
    Moje slevy — akce k hlídaným položkám ve sledovaných obchodech (R18, R19).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import MentionCard from '@/Components/MentionCard.vue';
import OfferCard from '@/Components/OfferCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    hasFollowedChains: { type: Boolean, required: true },
    urls: { type: Object, required: true },
    /** Hlídané položky s nabídkami od nejnižší ceny za jednotku a zmínkami v letácích (App\Domain\Matching\MyOffers). */
    watchItems: { type: Array, required: true },
});

const t = useTranslations();
</script>

<template>
    <AppLayout>
        <Head :title="t('home.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('home.title') }}</h1>
            <Link v-if="watchItems.length" :href="urls.watchItems" class="link">{{ t('home.edit_watch_items') }}</Link>
        </header>

        <p v-if="!hasFollowedChains" class="page__empty">
            {{ t('home.no_chains') }}
            <Link :href="urls.preferences" class="link">{{ t('home.no_chains_link') }}</Link>
        </p>
        <p v-else-if="!watchItems.length" class="page__empty">
            {{ t('home.no_watch_items') }}
            <Link :href="urls.watchItems" class="link">{{ t('home.no_watch_items_link') }}</Link>
        </p>

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
