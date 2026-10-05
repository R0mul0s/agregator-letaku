<!--
    Přehled všech aktuálních akcí s hledáním a filtrem obchodu. Veřejný (R44) — nepřihlášený
    vidí nad výpisem výzvu k registraci. Hledání je živé (R71): výsledky se přepočítají
    po krátké pauze v psaní, bez tlačítka; štítky filtrů (produkt z našeptávače, jen akce,
    které ještě nezačaly — R76, bez e-shopu — R82) a upozornění na opravený překlep. Když nic
    není v akci, nabídne to pohlídat. Obchodů jde vybrat víc, přihlášený má předvybrané své
    sledované; akce jako karty, nebo kompaktní řádky (R82).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainSelect from '@/Components/ChainSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OfferCard from '@/Components/OfferCard.vue';
import OfferRow from '@/Components/OfferRow.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchSuggest from '@/Components/SearchSuggest.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import ViewToggle from '@/Components/ViewToggle.vue';
import WatchOfferButton from '@/Components/WatchOfferButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { rememberSearch } from '@/lib/search';
import { useCompactView } from '@/lib/viewMode';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

/** Pauza v psaní, po které se přepočítají výsledky (ms) — delší než u návrhů, výsledky jsou dražší. */
const LIVE_SEARCH_DEBOUNCE_MS = 450;

/** Hodnota parametru obchodů: všechny obchody i pro přihlášeného se sledovanými (OfferFilters::ALL_CHAINS). */
const ALL_CHAINS = 'vse';

/** Oddělovač obchodů v parametru (OfferFilters::CHAIN_SEPARATOR). */
const CHAIN_SEPARATOR = ',';

const props = defineProps({
    searchUrl: { type: String, required: true },
    /** Adresa našeptávače a od kolika znaků se ptá. */
    suggestUrl: { type: String, required: true },
    suggestMinLength: { type: Number, required: true },
    /** Nabídky načteného rozsahu stránek { data (OfferPresenter), total }. */
    offers: { type: Object, required: true },
    /** Odkazy stránkování a „Načíst další“ (OffersController::pagination, R43). */
    pagination: { type: Object, required: true },
    /** { q, chain: [obchody] (prázdné = všechny), produkt, brzy, 'bez-eshopu' } */
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
/** Karty, nebo kompaktní řádky — volba společná s Mými slevami (R82). */
const compact = useCompactView();

/**
 * Parametr obchodů do adresy: vybrané oddělené čárkou; prázdný výběr = všechny — přihlášený
 * by bez parametru dostal své sledované obchody, proto „vse“.
 */
const chainParameter = computed(() => {
    if (filters.chain.length) {
        return filters.chain.join(CHAIN_SEPARATOR);
    }

    return page.props.auth.user ? ALL_CHAINS : '';
});

/** Parametry našeptávače — návrhy ze stejných obchodů a bez e-shopu jako výsledky. */
const suggestParams = computed(() => ({ chain: chainParameter.value, 'bez-eshopu': filters['bez-eshopu'] ? 1 : '' }));

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
        Object.entries({ ...filters, chain: chainParameter.value })
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

/** Přepne „Bez e-shopu“ — bez akcí, které platí jen v e-shopu (R82). */
function toggleWithoutEshop() {
    filters['bez-eshopu'] = !filters['bez-eshopu'];
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
                :params="suggestParams"
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
                multiple
                @change="search()"
            />
        </form>

        <!-- Štítky filtrů (R71) -->
        <div class="search-chips">
            <button type="button" class="search-chip" :class="{ 'search-chip--on': filters.brzy }" :aria-pressed="filters.brzy ? 'true' : 'false'" @click="toggleUpcoming">
                {{ t('search.upcoming_only') }}
            </button>
            <button
                type="button"
                class="search-chip"
                :class="{ 'search-chip--on': filters['bez-eshopu'] }"
                :aria-pressed="filters['bez-eshopu'] ? 'true' : 'false'"
                @click="toggleWithoutEshop"
            >
                {{ t('search.without_eshop') }}
            </button>
            <span v-if="product" class="search-chip search-chip--on">
                {{ t('search.product_filter', { name: product }) }}
                <button type="button" class="search-chip__remove" :title="t('search.remove_filter')" @click="clearProduct">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
                </button>
            </span>
            <!-- Karty, nebo kompaktní řádky (R82) -->
            <ViewToggle v-model="compact" class="search-chips__view" />
        </div>

        <p v-if="correction" class="search-correction" role="status">{{ t('search.correction', { original: correction.original, corrected: correction.corrected }) }}</p>

        <div v-if="offers.data.length === 0" class="search-empty">
            <EmptyState :text="filters.q ? t('search.empty_text', { text: filters.q }) : t('offers.empty')" />
            <!-- Co teď v akci není, jde pohlídat — upozorní, až bude (R71) -->
            <WatchOfferButton v-if="filters.q" :target="{ productId: null, name: filters.q.trim(), watched: false }" :urls="watchUrls" />
        </div>
        <!-- Kompaktní řádky (R82): víc akcí na obrazovku, obchod u každého řádku -->
        <ul v-else-if="compact" class="offer-rows offer-rows--listing" :class="{ 'offer-rows--loading': loading }" :aria-busy="loading ? 'true' : 'false'">
            <OfferRow v-for="offer in offers.data" :key="offer.id" :offer="offer" with-chain />
        </ul>
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
