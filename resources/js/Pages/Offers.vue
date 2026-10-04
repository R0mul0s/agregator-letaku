<!--
    Přehled všech aktuálních akcí s hledáním a filtrem obchodu. Veřejný (R44) — nepřihlášený
    vidí nad výpisem výzvu k registraci.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainSelect from '@/Components/ChainSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OfferCard from '@/Components/OfferCard.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchSuggest from '@/Components/SearchSuggest.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import WatchOfferButton from '@/Components/WatchOfferButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    searchUrl: { type: String, required: true },
    /** Adresa našeptávače a od kolika znaků se ptá. */
    suggestUrl: { type: String, required: true },
    suggestMinLength: { type: Number, required: true },
    /** Nabídky načteného rozsahu stránek { data (OfferPresenter), total }. */
    offers: { type: Object, required: true },
    /** Odkazy stránkování a „Načíst další“ (OffersController::pagination, R43). */
    pagination: { type: Object, required: true },
    filters: { type: Object, required: true },
    chains: { type: Array, required: true },
    /** Adresy pro „Hlídat“ z karty (R60, WatchOfferButton). */
    watchUrls: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

const filters = reactive({ ...props.filters });

/** Načte výsledky hledání; prázdné parametry do adresy nedává. */
function search() {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));

    router.get(props.searchUrl, query, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <header class="page__header">
            <h1 class="page__title">{{ t('offers.title') }}</h1>
            <p class="page__subtitle">{{ t('offers.count', { count: offers.total }) }}</p>
        </header>

        <!-- Nepřihlášený (R44): co získá registrací -->
        <aside v-if="!page.props.auth.user" class="guest-banner">
            <p class="guest-banner__text">{{ t('offers_guest.text') }}</p>
            <Link :href="page.props.auth.registerUrl" class="button button--primary">{{ t('offers_guest.register') }}</Link>
        </aside>

        <form class="search-form" role="search" @submit.prevent="search">
            <SearchSuggest
                id="q"
                v-model="filters.q"
                class="search-form__text"
                :label="t('offers.search')"
                :placeholder="t('offers.search_placeholder')"
                :url="suggestUrl"
                :params="{ chain: filters.chain }"
                :min-length="suggestMinLength"
                @select="search"
            />
            <ChainSelect
                id="chain"
                v-model="filters.chain"
                :label="t('offers.chain')"
                :chains="chains.map((chain) => chain.value)"
                :all-label="t('offers.all_chains')"
                @change="search"
            />
            <button type="submit" class="button button--primary">{{ t('offers.submit') }}</button>
        </form>

        <EmptyState v-if="offers.data.length === 0" :text="t('offers.empty')" />
        <div v-else class="offer-grid">
            <OfferCard v-for="offer in offers.data" :key="offer.id" :offer="offer">
                <!-- „Hlídat“ přímo z karty (R60) a nákupní seznam (R61) -->
                <WatchOfferButton v-if="offer.watchTarget" :target="offer.watchTarget" :urls="watchUrls" />
                <ShoppingToggle :offer-id="offer.id" />
            </OfferCard>
        </div>

        <Pagination :pagination="pagination" :total="offers.total" />
    </AppLayout>
</template>
