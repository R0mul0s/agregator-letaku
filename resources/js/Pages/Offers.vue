<!--
    Přehled všech aktuálních akcí s hledáním a filtrem obchodu. Veřejný (R44) — nepřihlášený
    vidí nad výpisem výzvu k registraci. Hledání je živé (R71): výsledky se přepočítají
    po krátké pauze v psaní, bez tlačítka; štítky filtrů (produkt z našeptávače, jen akce,
    které ještě nezačaly — R76) a upozornění na opravený překlep. Když nic není v akci, nabídne to pohlídat.

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
import { rememberSearch } from '@/lib/search';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive, ref, watch } from 'vue';

/** Pauza v psaní, po které se přepočítají výsledky (ms) — delší než u návrhů, výsledky jsou dražší. */
const LIVE_SEARCH_DEBOUNCE_MS = 450;

const props = defineProps({
    searchUrl: { type: String, required: true },
    /** Adresa našeptávače a od kolika znaků se ptá. */
    suggestUrl: { type: String, required: true },
    suggestMinLength: { type: Number, required: true },
    /** Nabídky načteného rozsahu stránek { data (OfferPresenter), total }. */
    offers: { type: Object, required: true },
    /** Odkazy stránkování a „Načíst další“ (OffersController::pagination, R43). */
    pagination: { type: Object, required: true },
    /** { q, chain, produkt, brzy } */
    filters: { type: Object, required: true },
    chains: { type: Array, required: true },
    /** Adresy pro „Hlídat“ z karty (R60, WatchOfferButton). */
    watchUrls: { type: Object, required: true },
    /** Název produktu katalogu, jehož akce výpis ukazuje (filtr z našeptávače), nebo null. */
    product: { type: String, default: null },
    /** Opravený překlep { original, corrected }, nebo null. */
    correction: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();

const filters = reactive({ ...props.filters });
const loading = ref(false);
let liveTimer = null;
/** Text posledního hledání — živé hledání se stejným textem nespouští znovu. */
let searchedText = (props.filters.q ?? '').trim();

/**
 * Načte výsledky podle filtrů; prázdné parametry do adresy nedává, zapnutý přepínač jako 1.
 *
 * @param {{ live?: boolean }} [options] live = během psaní (zůstat na místě stránky)
 */
function search({ live = false } = {}) {
    window.clearTimeout(liveTimer);
    searchedText = filters.q.trim();
    const query = Object.fromEntries(
        Object.entries(filters)
            .filter(([, value]) => value !== '' && value !== false && value !== null)
            .map(([key, value]) => [key, value === true ? 1 : value]),
    );

    router.get(props.searchUrl, query, {
        preserveState: true,
        preserveScroll: live,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

/**
 * Hledání potvrzené Enterem nebo volbou z našeptávače — zapamatuje se mezi posledními.
 *
 * @param {string} text
 */
function submitSearch(text) {
    filters.q = text;
    rememberSearch(text);
    search();
}

/**
 * Produkt z našeptávače: akce produktu místo textu.
 *
 * @param {{ id: number, name: string }} product
 */
function showProduct(product) {
    rememberSearch(product.name);
    filters.q = '';
    filters.produkt = product.id;
    search();
}

/** Zruší filtr produktu. */
function clearProduct() {
    filters.produkt = '';
    search();
}

/** Přepne „Brzy začnou“ — jen akce, které ještě nezačaly (R76). */
function toggleUpcoming() {
    filters.brzy = !filters.brzy;
    search();
}

// Živé hledání (R71): výsledky po pauze v psaní, od minimální délky nebo po smazání pole
watch(
    () => filters.q,
    (text) => {
        window.clearTimeout(liveTimer);
        const trimmed = text.trim();
        if (trimmed === searchedText || (trimmed !== '' && trimmed.length < props.suggestMinLength)) {
            return;
        }
        liveTimer = window.setTimeout(() => search({ live: true }), LIVE_SEARCH_DEBOUNCE_MS);
    },
);

onBeforeUnmount(() => window.clearTimeout(liveTimer));
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

        <form class="search-form" role="search" @submit.prevent="submitSearch(filters.q)">
            <SearchSuggest
                id="q"
                v-model="filters.q"
                class="search-form__text"
                :label="t('offers.search')"
                :url="suggestUrl"
                :params="{ chain: filters.chain }"
                :min-length="suggestMinLength"
                :loading="loading"
                :watch-urls="watchUrls"
                @search="submitSearch"
                @product="showProduct"
            />
            <ChainSelect
                id="chain"
                v-model="filters.chain"
                :label="t('offers.chain')"
                :chains="chains.map((chain) => chain.value)"
                :all-label="t('offers.all_chains')"
                @change="search()"
            />
        </form>

        <!-- Štítky filtrů (R71) -->
        <div class="search-chips">
            <button type="button" class="search-chip" :class="{ 'search-chip--on': filters.brzy }" :aria-pressed="filters.brzy ? 'true' : 'false'" @click="toggleUpcoming">
                {{ t('search.upcoming_only') }}
            </button>
            <span v-if="product" class="search-chip search-chip--on">
                {{ t('search.product_filter', { name: product }) }}
                <button type="button" class="search-chip__remove" :title="t('search.remove_filter')" @click="clearProduct">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
                </button>
            </span>
        </div>

        <p v-if="correction" class="search-correction" role="status">{{ t('search.correction', { original: correction.original, corrected: correction.corrected }) }}</p>

        <div v-if="offers.data.length === 0" class="search-empty">
            <EmptyState :text="filters.q ? t('search.empty_text', { text: filters.q }) : t('offers.empty')" />
            <!-- Co teď v akci není, jde pohlídat — upozorní, až bude (R71) -->
            <WatchOfferButton v-if="filters.q" :target="{ productId: null, name: filters.q.trim(), watched: false }" :urls="watchUrls" />
        </div>
        <div v-else class="offer-grid" :class="{ 'offer-grid--loading': loading }" :aria-busy="loading ? 'true' : 'false'">
            <OfferCard v-for="offer in offers.data" :key="offer.id" :offer="offer">
                <!-- „Hlídat“ přímo z karty (R60) a nákupní seznam (R61) -->
                <WatchOfferButton v-if="offer.watchTarget" :target="offer.watchTarget" :urls="watchUrls" />
                <ShoppingToggle :offer-id="offer.id" />
            </OfferCard>
        </div>

        <Pagination :pagination="pagination" :total="offers.total" />
    </AppLayout>
</template>
