<!--
    Přehled všech aktuálních akcí s hledáním a filtrem obchodu. Veřejný (R44) — nepřihlášený
    vidí nad výpisem výzvu k registraci. Hledání je živé (R71): výsledky se přepočítají
    po krátké pauze v psaní, bez tlačítka; štítky filtrů (produkt z našeptávače, jen akce,
    které ještě nezačaly — R76, bez e-shopu — R82) a upozornění na opravený překlep. Když nic
    není v akci, nabídne to pohlídat. Obchodů jde vybrat víc, přihlášený má předvybrané své
    sledované; akce jako karty, nebo kompaktní řádky (R82). Řazení na výběr a přihlášenému
    štítek „Podle Mých obchodů“ (prodejny, karty, e-shop) s počtem skrytých akcí (R100). Štítky
    Nové, Končí brzy, výběr Sleva od a Zrušit filtry; na telefonu v jednom řádku k posunutí (R101).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ActiveFilters from '@/Components/ActiveFilters.vue';
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainSelect from '@/Components/ChainSelect.vue';
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FilterBar from '@/Components/FilterBar.vue';
import FilterChip from '@/Components/FilterChip.vue';
import OfferCard from '@/Components/OfferCard.vue';
import OfferRow from '@/Components/OfferRow.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchSuggest from '@/Components/SearchSuggest.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import SortSelect from '@/Components/SortSelect.vue';
import SortSheet from '@/Components/SortSheet.vue';
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

/** Parametr nastavení Mých obchodů (OfferFilters::SHOPPING_PREFERENCES_PARAMETER, R100). */
const SHOPPING_PREFERENCES = 'moje-obchody';

/** Parametry filtrů (OfferFilters): nové a končí brzy (R101), brzy začnou (R76), sleva od (R101). */
const FRESH = 'nove';
const ENDING_SOON = 'konci-brzy';
const UPCOMING = 'brzy';
const MIN_DISCOUNT = 'sleva-od';

/** Parametr oddělení katalogu (OfferFilters::DEPARTMENT_PARAMETER, R102). */
const DEPARTMENT = 'kategorie';

const props = defineProps({
    /** Nadpis podle obchodu nebo produktu („Pivo v akci“), stejný jako pro vyhledávače (SeoMeta, R94). */
    heading: { type: String, required: true },
    searchUrl: { type: String, required: true },
    /** Adresa našeptávače a od kolika znaků se ptá. */
    suggestUrl: { type: String, required: true },
    suggestMinLength: { type: Number, required: true },
    /** Nabídky načteného rozsahu stránek { data (OfferPresenter), total }. */
    offers: { type: Object, required: true },
    /** Odkazy stránkování a „Načíst další“ (OffersController::pagination, R43). */
    pagination: { type: Object, required: true },
    /** { q, chain: [obchody] (prázdné = všechny), produkt, brzy, 'bez-eshopu', razeni ('' = podle situace), 'moje-obchody', nove, 'konci-brzy', 'sleva-od', kategorie } */
    filters: { type: Object, required: true },
    /** Řazení, podle kterého výpis řadí, a možnosti [{ value, label }] (OfferListSort, R100). */
    sort: { type: String, required: true },
    sortOptions: { type: Array, required: true },
    /** Hodnoty filtrů { endingSoonDays, freshDays, minDiscounts } (R101) a oddělení s akcemi { slug, name, icon } (R102). */
    filterOptions: { type: Object, required: true },
    /** Nastavení Mých obchodů přihlášeného { hidden: skryté akce, url } (R100); nepřihlášený null. */
    shoppingPreferences: { type: Object, default: null },
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

/**
 * Parametr nastavení Mých obchodů do adresy (R100): výchozí je zapnuté, do adresy jde jen
 * vypnutí jako 0; nepřihlášený nastavení nemá.
 */
const shoppingPreferencesParameter = computed(() => (props.shoppingPreferences && !filters[SHOPPING_PREFERENCES] ? 0 : ''));

/** Parametry našeptávače — návrhy ze stejných obchodů, bez e-shopu a podle Mých obchodů jako výsledky. */
const suggestParams = computed(() => ({
    chain: chainParameter.value,
    'bez-eshopu': filters['bez-eshopu'] ? 1 : '',
    [SHOPPING_PREFERENCES]: shoppingPreferencesParameter.value,
}));

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
        Object.entries({ ...filters, chain: chainParameter.value, [SHOPPING_PREFERENCES]: shoppingPreferencesParameter.value })
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

/** Filtry, které se navzájem vylučují — akce, která brzy končí, už platí, budoucí ještě ne. */
const EXCLUSIVE_FILTERS = { [ENDING_SOON]: UPCOMING, [UPCOMING]: ENDING_SOON };

/** Štítky platnosti (R101): nové, končí brzy, brzy začnou (R76) — oddíl Platnost v okně Filtry. */
const periodChips = computed(() => [
    { key: FRESH, label: t('search.fresh', { count: props.filterOptions.freshDays }) },
    { key: ENDING_SOON, label: t('search.ending_soon', { count: props.filterOptions.endingSoonDays }) },
    { key: UPCOMING, label: t('search.upcoming_only') },
]);

/** Štítky „kde koupit“: bez e-shopu (R82) a pro přihlášeného podle Mých obchodů (R100). */
const placeChips = computed(() => [
    { key: 'bez-eshopu', label: t('search.without_eshop') },
    ...(props.shoppingPreferences ? [{ key: SHOPPING_PREFERENCES, label: t('search.shopping_preferences') }] : []),
]);

/** Všechny štítky zapnuto / vypnuto v jednom řádku (široký displej). */
const toggleChips = computed(() => [...periodChips.value, ...placeChips.value]);

/** Otevřená okna řazení a filtrů na telefonu (R102). */
const sortOpen = ref(false);
const filtersOpen = ref(false);

/**
 * Přepne obchod ve výběru (okno Filtry, R102) — stejný výběr jako ChainSelect, v pořadí nabídky.
 *
 * @param {string} chain
 */
function toggleChain(chain) {
    filters.chain = filters.chain.includes(chain)
        ? filters.chain.filter((value) => value !== chain)
        : props.chains.map((option) => option.value).filter((value) => value === chain || filters.chain.includes(value));
    search();
}

/**
 * Nastaví filtr s jednou hodnotou (sleva od, oddělení); stejná hodnota ho vypne.
 *
 * @param {string} key Parametr adresy
 * @param {string|number} value
 */
function chooseFilter(key, value) {
    filters[key] = filters[key] === value ? '' : value;
    search();
}

/**
 * Zapnuté filtry jako štítky pod lištou na telefonu (R102) — klepnutí filtr vypne; vybrané
 * obchody otevřou okno Filtry.
 */
const activeFilterChips = computed(() => {
    const chips = [];
    if (filters.chain.length) {
        const names = filters.chain.map((value) => props.chains.find((chain) => chain.value === value)?.name ?? value);
        chips.push({ key: 'chain', label: names.join(', '), action: () => (filtersOpen.value = true), removable: false });
    }
    for (const chip of periodChips.value.filter((periodChip) => filters[periodChip.key])) {
        chips.push({ key: chip.key, label: chip.label, action: () => toggleFilter(chip.key), removable: true });
    }
    if (filters[MIN_DISCOUNT]) {
        chips.push({ key: MIN_DISCOUNT, label: t('search.min_discount', { percent: filters[MIN_DISCOUNT] }), action: () => chooseFilter(MIN_DISCOUNT, ''), removable: true });
    }
    const department = props.filterOptions.departments.find((option) => option.slug === filters[DEPARTMENT]);
    if (department) {
        chips.push({ key: DEPARTMENT, label: department.name, action: () => chooseFilter(DEPARTMENT, ''), removable: true });
    }
    if (filters['bez-eshopu']) {
        chips.push({ key: 'bez-eshopu', label: t('search.without_eshop'), action: () => toggleFilter('bez-eshopu'), removable: true });
    }
    if (props.product) {
        chips.push({ key: 'produkt', label: t('search.product_filter', { name: props.product }), action: clearProduct, removable: true });
    }

    return chips;
});

/**
 * Přepne štítek filtru; vylučující se filtr vypne.
 *
 * @param {string} key Parametr adresy filtru
 */
function toggleFilter(key) {
    filters[key] = !filters[key];
    if (filters[key] && EXCLUSIVE_FILTERS[key]) {
        filters[EXCLUSIVE_FILTERS[key]] = false;
    }
    search();
}

/** Přepne „Podle Mých obchodů“ — vybrané prodejny, karty a e-shop přihlášeného (R100). */
function toggleShoppingPreferences() {
    toggleFilter(SHOPPING_PREFERENCES);
}

/** Filtry, které „Zrušit filtry“ vypne — hledání, obchody a nastavení Mých obchodů zůstanou. */
const CLEARABLE_FILTERS = [FRESH, ENDING_SOON, UPCOMING, 'bez-eshopu', MIN_DISCOUNT, DEPARTMENT, 'produkt'];

/** Je zapnutý některý filtr, který jde zrušit? */
const hasActiveFilters = computed(() => CLEARABLE_FILTERS.some((key) => Boolean(filters[key])));

/** Zruší všechny filtry štítků (R101). */
function clearFilters() {
    for (const key of CLEARABLE_FILTERS) {
        filters[key] = typeof filters[key] === 'boolean' ? false : '';
    }
    search();
}

/**
 * Řazení ve výběru: podle kterého výpis opravdu řadí (server ho určí i bez volby). Vlastní
 * hodnota, aby výběr během načítání neskočil zpátky na předchozí.
 */
const sortValue = ref(props.sort);
watch(
    () => props.sort,
    (sort) => (sortValue.value = sort),
);

/** Název zvoleného řazení na tlačítku „Seřadit“ (telefon, R102). */
const sortLabel = computed(() => props.sortOptions.find((option) => option.value === sortValue.value)?.label ?? '');

/**
 * Zvolené řazení — platí i pro další hledání, dokud ho uživatel nezmění.
 *
 * @param {string} sort
 */
function changeSort(sort) {
    filters.razeni = sort;
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
            <h1 class="page__title">{{ heading }}</h1>
            <!-- Počet se při živém hledání a filtrech mění — čtečka ho oznámí (R99) -->
            <p class="page__subtitle" role="status">{{ t('offers.count', { count: offers.total }) }}</p>
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
                class="search-form__chains"
                :label="t('offers.chain')"
                :chains="chains.map((chain) => chain.value)"
                :all-label="t('offers.all_chains')"
                multiple
                @change="search()"
            />
        </form>

        <!-- Telefon (R102): Seřadit a Filtry v oknech zespodu, pod nimi zapnuté filtry -->
        <FilterBar :sort-label="sortLabel" :filter-count="activeFilterChips.length" @sort="sortOpen = true" @filters="filtersOpen = true">
            <ViewToggle v-model="compact" />
        </FilterBar>
        <ActiveFilters :chips="activeFilterChips" />
        <SortSheet v-model:open="sortOpen" :options="sortOptions" :value="sortValue" @change="changeSort" />
        <BottomSheet v-model:open="filtersOpen" :title="t('sheet.filters')">
            <div class="filter-sheet">
                <section class="filter-sheet__section">
                    <h3 class="filter-sheet__heading">{{ t('offers.chain') }}</h3>
                    <div class="filter-sheet__options">
                        <FilterChip
                            v-for="chain in chains"
                            :key="chain.value"
                            :on="filters.chain.includes(chain.value)"
                            @click="toggleChain(chain.value)"
                        >
                            <ChainLogo :chain="chain.value" with-name />
                        </FilterChip>
                    </div>
                    <p class="filter-sheet__hint">{{ t('sheet.chains_hint') }}</p>
                </section>
                <section class="filter-sheet__section">
                    <h3 class="filter-sheet__heading">{{ t('sheet.period') }}</h3>
                    <div class="filter-sheet__options">
                        <FilterChip
                            v-for="chip in periodChips"
                            :key="chip.key"
                            :on="filters[chip.key]"
                            @click="toggleFilter(chip.key)"
                        >
                            {{ chip.label }}
                        </FilterChip>
                    </div>
                </section>
                <section class="filter-sheet__section">
                    <h3 class="filter-sheet__heading">{{ t('sheet.discount') }}</h3>
                    <div class="filter-sheet__options">
                        <FilterChip
                            v-for="percent in filterOptions.minDiscounts"
                            :key="percent"
                            :on="filters[MIN_DISCOUNT] === percent"
                            @click="chooseFilter(MIN_DISCOUNT, percent)"
                        >
                            {{ t('search.min_discount', { percent }) }}
                        </FilterChip>
                    </div>
                </section>
                <section v-if="filterOptions.departments.length" class="filter-sheet__section">
                    <h3 class="filter-sheet__heading">{{ t('search.department_label') }}</h3>
                    <div class="filter-sheet__options">
                        <FilterChip
                            v-for="department in filterOptions.departments"
                            :key="department.slug"
                            :on="filters[DEPARTMENT] === department.slug"
                            @click="chooseFilter(DEPARTMENT, department.slug)"
                        >
                            <DepartmentIcon :name="department.icon" class="filter-sheet__icon" />
                            {{ department.name }}
                        </FilterChip>
                    </div>
                    <p class="filter-sheet__hint">{{ t('sheet.department_hint') }}</p>
                </section>
                <section class="filter-sheet__section">
                    <h3 class="filter-sheet__heading">{{ t('sheet.place') }}</h3>
                    <div class="filter-sheet__options">
                        <FilterChip
                            v-for="chip in placeChips"
                            :key="chip.key"
                            :on="filters[chip.key]"
                            @click="toggleFilter(chip.key)"
                        >
                            {{ chip.label }}
                        </FilterChip>
                    </div>
                    <p v-if="shoppingPreferences" class="filter-sheet__hint">{{ t('sheet.shopping_preferences_hint') }}</p>
                </section>
            </div>
            <template #footer>
                <button type="button" class="button button--ghost" :disabled="!hasActiveFilters" @click="clearFilters">{{ t('search.clear_filters') }}</button>
                <button type="button" class="button button--primary" :aria-busy="loading ? 'true' : 'false'" @click="filtersOpen = false">
                    {{ t('sheet.show', { count: offers.total }) }}
                </button>
            </template>
        </BottomSheet>

        <!-- Štítky filtrů (R71, R101) — široký displej; na telefonu lišta a okna výš -->
        <div class="search-chips search-chips--desktop">
            <div class="search-chips__filters" role="group" :aria-label="t('search.filters')">
                <FilterChip
                    v-for="chip in toggleChips"
                    :key="chip.key"
                    :on="filters[chip.key]"
                    @click="toggleFilter(chip.key)"
                >
                    {{ chip.label }}
                </FilterChip>
                <!-- Jen skutečné slevy od procent (R101) — výběr ve tvaru štítku -->
                <label class="search-chip search-chip--select" :class="{ 'search-chip--on': filters['sleva-od'] }">
                    <span class="visually-hidden">{{ t('search.min_discount_label') }}</span>
                    <select v-model="filters['sleva-od']" class="search-chip__select" @change="search()">
                        <option value="">{{ t('search.min_discount_any') }}</option>
                        <option v-for="percent in filterOptions.minDiscounts" :key="percent" :value="percent">{{ t('search.min_discount', { percent }) }}</option>
                    </select>
                </label>
                <!-- Oddělení katalogu (R102) — akce přiřazené k produktům katalogu -->
                <label v-if="filterOptions.departments.length" class="search-chip search-chip--select" :class="{ 'search-chip--on': filters.kategorie }">
                    <span class="visually-hidden">{{ t('search.department_label') }}</span>
                    <select v-model="filters.kategorie" class="search-chip__select" @change="search()">
                        <option value="">{{ t('search.department_any') }}</option>
                        <option v-for="department in filterOptions.departments" :key="department.slug" :value="department.slug">{{ department.name }}</option>
                    </select>
                </label>
                <span v-if="product" class="search-chip search-chip--on">
                    {{ t('search.product_filter', { name: product }) }}
                    <button type="button" class="search-chip__remove" :title="t('search.remove_filter')" @click="clearProduct">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
                    </button>
                </span>
                <button v-if="hasActiveFilters" type="button" class="link-button search-chips__clear" @click="clearFilters">{{ t('search.clear_filters') }}</button>
            </div>
            <!-- Řazení (R100) a karty, nebo kompaktní řádky (R82) -->
            <div class="search-chips__display">
                <SortSelect id="sort" v-model="sortValue" :label="t('offers.sort')" :options="sortOptions" @change="changeSort" />
                <ViewToggle v-model="compact" />
            </div>
        </div>

        <!-- Kolik akcí nastavení Mých obchodů skrylo — s nabídkou ukázat všechny (R100) -->
        <p v-if="shoppingPreferences && filters['moje-obchody'] && shoppingPreferences.hidden > 0" class="search-preferences">
            {{ t('search.shopping_preferences_hidden', { count: shoppingPreferences.hidden }) }}
            <button type="button" class="link-button" @click="toggleShoppingPreferences">{{ t('search.shopping_preferences_show_all') }}</button>
            ·
            <Link :href="shoppingPreferences.url" class="link">{{ t('search.shopping_preferences_edit') }}</Link>
        </p>

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
