<!--
    Moje slevy — úvodní pruh s maskotem a souhrnem, akce k hlídaným položkám ve sledovaných
    obchodech (R18, R19) a zmínky v letácích bez ceny (R27). Skupiny jsou sbalené, rozbalené
    si prohlížeč pamatuje (R43). Výběr obchodu ukáže jen jeho akce, rozbalené — „co z mého
    seznamu je teď v Lidlu“, když člověk stojí v obchodě (R55). Akce, které ještě nezačaly,
    jsou ve sbalené sekci Brzy pod skupinami; v obchodě se neukazují vůbec (R76). Akce jako
    karty, nebo kompaktní řádky — volba společná se Všemi akcemi, v obchodě vlastní (R62, R82).
    Položky bez akcí jsou ve sbalené sekci Zatím bez akce na konci a řazení jde změnit přímo
    nad skupinami, uloží se do účtu (R100). Štítky Nové, Končí brzy a Jen jisté shody ukážou jen
    odpovídající akce rozbalené jako v obchodě; „Jen moje prodejny“ jde dočasně vypnout (R101).
    Pohled Podle obchodů (kam jet nakoupit), menší úvodní pruh při další návštěvě s čísly jako
    zkratkami a na telefonu Seřadit / Filtry v oknech zespodu (R102).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainOverview from '@/Components/ChainOverview.vue';
import ChainSelect from '@/Components/ChainSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FilterBar from '@/Components/FilterBar.vue';
import SortSelect from '@/Components/SortSelect.vue';
import SortSheet from '@/Components/SortSheet.vue';
import UpcomingSection from '@/Components/UpcomingSection.vue';
import ViewToggle from '@/Components/ViewToggle.vue';
import WaitingSection from '@/Components/WaitingSection.vue';
import WatchGroup from '@/Components/WatchGroup.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { readStored, writeStored } from '@/lib/storage';
import { useCompactView } from '@/lib/viewMode';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

/** Klíč v localStorage s rozbalenými položkami — jen pohodlí prohlížeče, ne nastavení účtu. */
const EXPANDED_STORAGE_KEY = 'slevohlidka.home.expanded';

/** Sekce Brzy mezi rozbalenými (R76) — vedle ID položek ve stejném klíči localStorage. */
const UPCOMING_ID = 'brzy';

/** Sekce Zatím bez akce mezi rozbalenými (R100) — ve stejném klíči localStorage. */
const WAITING_ID = 'bez-akce';

/** Klíč v localStorage: pohled Podle položek, nebo Podle obchodů (R102). */
const VIEW_STORAGE_KEY = 'slevohlidka.home.view';

/** Klíč v localStorage: úvodní pruh už jednou viděl — dál menší (R102). */
const HERO_SEEN_KEY = 'slevohlidka.home.hero_seen';

/** Kotva panelu nad skupinami — čísla v úvodním pruhu k němu posunou stránku. */
const TOOLBAR_ID = 'moje-slevy-akce';

/** Stav shody „možná“ (App\Enums\MatchStatus, R9) — štítek „Jen jisté“ ho schová. */
const MATCH_MAYBE = 'maybe';

/** Kotva skupiny v adrese (odkaz z dlaždice v Hlídám): #polozka-{id}. */
const GROUP_HASH_PATTERN = /^#polozka-(\d+)$/;

const props = defineProps({
    hasFollowedChains: { type: Boolean, required: true },
    /** Křestní jméno v 5. pádě do pozdravu („Romane“, R47). */
    greetingName: { type: String, required: true },
    urls: { type: Object, required: true },
    /** Předvolby uživatele { sort, sortOptions, minDiscountPercent, updateUrl } (R41, řazení na stránce R100). */
    offersPreferences: { type: Object, required: true },
    /** Jak často chodí e-mailový souhrn („denně“), null = vypnutý (R42). */
    digestFrequency: { type: String, default: null },
    /** Vybrané prodejny { count, all, parameter, allValue, url } a dočasně všechny (R101); bez vybraných null. */
    stores: { type: Object, default: null },
    /** Pohled Podle obchodů [{ chain, cheapestCount, items }] (App\Domain\Matching\ChainOverview, R102). */
    byChain: { type: Array, required: true },
    /** Dny do popisků štítků { fresh, endingSoon } — stejné jako ve Všech akcích (R101). */
    offerFilterDays: { type: Object, required: true },
    /** Hlídané položky s nabídkami od nejnižší ceny za jednotku a zmínkami v letácích (App\Domain\Matching\MyOffers). */
    watchItems: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Souhrn do úvodního pruhu: počet akcí a nejvyšší sleva napříč hlídanými položkami. */
const summary = computed(() => {
    const offers = props.watchItems.flatMap((item) => item.offers);
    const discounts = offers.map(discountPercent).filter(Boolean);

    return { offers: offers.length, bestDiscount: discounts.length ? Math.max(...discounts) : null };
});

/** Vybraný obchod (R55); '' = všechny. Nepamatuje se — po návratu na stránku je zase vše. */
const chainFilter = ref('');

/**
 * Zmínka v letáku, který už platí — v obchodě se budoucí letáky neukazují (R76).
 *
 * @param {object} mention
 * @returns {boolean}
 */
const isCurrentMention = (mention) => !mention.startsInDays;

/** Obchody s akcí nebo zmínkou, které dnes platí, u některé položky, v pořadí výčtu obchodů (sdílené chainInfo). */
const filterChains = computed(() => {
    const present = new Set(props.watchItems.flatMap((item) => [...item.offers, ...item.mentions.filter(isCurrentMention)]).map((entry) => entry.chain));

    return Object.keys(page.props.chainInfo).filter((chain) => present.has(chain));
});

/**
 * Má položka akci nebo zmínku v letáku? Bez nich je v sekci Zatím bez akce (R100).
 *
 * @param {object} item
 * @returns {boolean}
 */
const hasFinds = (item) => item.offers.length > 0 || item.mentions.length > 0;

/** Položky bez akcí a zmínek — sbalená sekce pod ostatními (R100); v obchodě se neukazují. */
const waitingItems = computed(() => (focused.value ? [] : props.watchItems.filter((item) => !hasFinds(item))));

/**
 * Štítky filtrů akcí (R101): nové, brzy končící, jen jisté shody (bez „možná“, R9). Nepamatují
 * se — po návratu na stránku je zase vše.
 */
const offerFilters = reactive({ fresh: false, endingSoon: false, sure: false });

/** Podmínka akce pro každý štítek (příznaky z HomeController). */
const OFFER_FILTER_TESTS = {
    fresh: (offer) => offer.isNew,
    endingSoon: (offer) => offer.endsSoon,
    sure: (offer) => offer.matchStatus !== MATCH_MAYBE,
};

/** Je zapnutý některý štítek filtru? */
const offerFilterActive = computed(() => Object.values(offerFilters).some(Boolean));

/**
 * Výběr obchodu nebo štítek: skupiny rozbalené, jen akce, které výběru odpovídají, sekce Brzy
 * a Zatím bez akce schované.
 */
const focused = computed(() => Boolean(chainFilter.value) || offerFilterActive.value);

/**
 * Akce položky pro štítky: dnešní, bez výběru obchodu i ty, které teprve začnou — „Nové“ jsou
 * často z letáků zveřejněných dopředu (R76); v obchodě jen dnešní.
 *
 * @param {object} item
 * @returns {object[]}
 */
const chipOffers = (item) => (chainFilter.value ? item.offers.filter((offer) => offer.chain === chainFilter.value) : [...item.offers, ...item.upcoming]);

/** Kolik akcí by který štítek ukázal — štítek bez akcí se nenabízí. */
const offerFilterCounts = computed(() =>
    Object.fromEntries(Object.entries(OFFER_FILTER_TESTS).map(([key, test]) => [key, props.watchItems.flatMap(chipOffers).filter(test).length])),
);

/** Štítky k zobrazení — bez akcí se nenabízí, zapnutý zůstane, aby šel vypnout. */
const offerFilterChips = computed(() =>
    [
        { key: 'fresh', label: t('search.fresh', { count: props.offerFilterDays.fresh }) },
        { key: 'endingSoon', label: t('search.ending_soon', { count: props.offerFilterDays.endingSoon }) },
        { key: 'sure', label: t('home.filter_sure') },
    ].filter((chip) => offerFilters[chip.key] || offerFilterCounts.value[chip.key] > 0),
);

/**
 * Přepne štítek filtru a rozbalí skupiny jako nový výběr.
 *
 * @param {string} key fresh | endingSoon | sure
 */
function toggleOfferFilter(key) {
    offerFilters[key] = !offerFilters[key];
    filterCollapsedIds.value = new Set();
}

/**
 * Skupiny k zobrazení: položky s akcemi nebo zmínkami; po výběru obchodu jen ty s jeho akcemi
 * nebo zmínkami, a jen ty, které dnes platí — budoucí akce za akční cenu v obchodě zatím
 * nekoupíte (R76). Se štítkem jen akce, které mu odpovídají, bez zmínek (R101).
 */
const visibleItems = computed(() => {
    if (!focused.value) {
        return props.watchItems.filter(hasFinds);
    }

    const passes = (offer) => Object.entries(OFFER_FILTER_TESTS).every(([key, test]) => !offerFilters[key] || test(offer));
    const inChain = (entry) => !chainFilter.value || entry.chain === chainFilter.value;

    return props.watchItems
        .map((item) => ({
            ...item,
            offers: (offerFilterActive.value ? chipOffers(item) : item.offers.filter(inChain)).filter(passes),
            mentions: offerFilterActive.value ? [] : item.mentions.filter((mention) => inChain(mention) && isCurrentMention(mention)),
            upcoming: [],
            waitTip: null,
        }))
        .filter(hasFinds);
});

/** Položky s akcemi, které ještě nezačaly — sekce Brzy (R76). */
const upcomingItems = computed(() => props.watchItems.filter((item) => item.upcoming.length));

/**
 * Dočasně akce všech prodejen, ne jen vybraných (R101) — na cestách; server výpis složí znovu,
 * nastavení Mých obchodů se nemění.
 */
function toggleAllStores() {
    const { parameter, allValue, all, url } = props.stores;
    router.get(url, all ? {} : { [parameter]: allValue }, { preserveScroll: true, preserveState: true, replace: true });
}

/** Rozbalené skupiny (id položek); ve výchozím stavu je vše sbalené. */
const expandedIds = ref(new Set());

/** Po výběru obchodu jsou skupiny rozbalené; sbalené klepnutím (bez zapamatování). */
const filterCollapsedIds = ref(new Set());

/**
 * Je skupina rozbalená? Po výběru obchodu nebo štítku ano, dokud ji uživatel nesbalí.
 *
 * @param {number} id
 * @returns {boolean}
 */
function isExpanded(id) {
    return focused.value ? !filterCollapsedIds.value.has(id) : expandedIds.value.has(id);
}

const allExpanded = computed(() => visibleItems.value.every((item) => isExpanded(item.id)));

/** Nový výběr obchodu začne se vším rozbaleným. */
function onChainChange() {
    filterCollapsedIds.value = new Set();
}

/** Klíč v localStorage: v obchodě akce jako řádky (výchozí), nebo karty (R62). */
const ROWS_STORAGE_KEY = 'slevohlidka.home.rows';

/** Po výběru obchodu akce jako kompaktní řádky — v obchodě se míň posouvá (R62). */
const rowsInStore = ref(true);

/** Mimo obchod karty, nebo řádky podle volby společné se Všemi akcemi (R82). */
const compactView = useCompactView();

/**
 * Řádky, nebo karty: v obchodě vlastní volba (výchozí řádky), jinak společná. Změna se
 * zapamatuje; bez localStorage platí do zavření stránky.
 */
const compact = computed({
    get: () => (chainFilter.value ? rowsInStore.value : compactView.value),
    set: (value) => {
        if (!chainFilter.value) {
            compactView.value = value;

            return;
        }
        rowsInStore.value = value;
        try {
            localStorage.setItem(ROWS_STORAGE_KEY, value ? '1' : '0');
        } catch {
            // volba platí jen do zavření stránky
        }
    },
});

/** Uloží rozbalené skupiny; bez přístupu k localStorage (anonymní okno) se stav jen nezapamatuje. */
function saveExpanded() {
    try {
        localStorage.setItem(EXPANDED_STORAGE_KEY, JSON.stringify([...expandedIds.value]));
    } catch {
        // stav platí jen do zavření stránky
    }
}

/**
 * Rozbalí nebo sbalí jednu skupinu.
 *
 * @param {number} id
 * @param {boolean} value
 */
function setExpanded(id, value) {
    if (focused.value) {
        const collapsed = new Set(filterCollapsedIds.value);
        if (value) {
            collapsed.delete(id);
        } else {
            collapsed.add(id);
        }
        filterCollapsedIds.value = collapsed;

        return;
    }

    const next = new Set(expandedIds.value);
    if (value) {
        next.add(id);
    } else {
        next.delete(id);
    }
    expandedIds.value = next;
    saveExpanded();
}

/**
 * Rozbalí všechny skupiny s akcemi, nebo — když už jsou všechny rozbalené — všechny sbalí.
 * Sekce Brzy a Zatím bez akce zůstanou, jak jsou.
 */
function toggleAll() {
    if (focused.value) {
        filterCollapsedIds.value = allExpanded.value ? new Set(visibleItems.value.map((item) => item.id)) : new Set();

        return;
    }

    const next = new Set(allExpanded.value ? [] : visibleItems.value.map((item) => item.id));
    for (const sectionId of [UPCOMING_ID, WAITING_ID]) {
        if (expandedIds.value.has(sectionId)) {
            next.add(sectionId);
        }
    }
    expandedIds.value = next;
    saveExpanded();
}

/**
 * Rozbalení sekce pod skupinami (Brzy, Zatím bez akce); ve výchozím stavu sbalená, stav si
 * prohlížeč pamatuje. Sekce se v obchodě neukazují, proto vždy mezi zapamatovanými.
 *
 * @param {string} sectionId
 */
const sectionExpanded = (sectionId) =>
    computed({
        get: () => expandedIds.value.has(sectionId),
        set: (value) => {
            const next = new Set(expandedIds.value);
            if (value) {
                next.add(sectionId);
            } else {
                next.delete(sectionId);
            }
            expandedIds.value = next;
            saveExpanded();
        },
    });

/** Rozbalená sekce Brzy (R76). */
const upcomingExpanded = sectionExpanded(UPCOMING_ID);

/** Rozbalená sekce Zatím bez akce (R100). */
const waitingExpanded = sectionExpanded(WAITING_ID);

/** Řazení akcí ve skupinách (R41) — změna na stránce se hned uloží do účtu (R100). */
const sort = ref(props.offersPreferences.sort);
watch(
    () => props.offersPreferences.sort,
    (value) => (sort.value = value),
);

/**
 * Uloží řazení s celým stavem předvoleb (jako Můj účet, R63); server akce seřadí znovu.
 *
 * @param {string} value
 */
function saveSort(value) {
    router.put(
        props.offersPreferences.updateUrl,
        { offers_sort: value, min_discount_percent: props.offersPreferences.minDiscountPercent },
        { preserveScroll: true, preserveState: true },
    );
}

/** Pohled Podle položek (skupiny), nebo Podle obchodů (R102) — prohlížeč si volbu pamatuje. */
const VIEW_ITEMS = 'items';
const VIEW_CHAINS = 'chains';
const groupView = ref(readStored(VIEW_STORAGE_KEY, VIEW_ITEMS) === VIEW_CHAINS ? VIEW_CHAINS : VIEW_ITEMS);
watch(groupView, (value) => writeStored(VIEW_STORAGE_KEY, value));

/** Pohled Podle obchodů — ne v obchodě (výběr obchodu) a jen když je co srovnávat. */
const showChains = computed(() => groupView.value === VIEW_CHAINS && !chainFilter.value && props.byChain.length > 0);

/**
 * Menší úvodní pruh při další návštěvě (R102): bez maskota a úvodní věty, akce jsou výš.
 * Poprvé celý — vysvětlí, co stránka ukazuje.
 */
const heroCompact = readStored(HERO_SEEN_KEY, false) === true;

/** Položka s nejvyšší slevou — číslo v úvodním pruhu na ni skočí. */
const bestDiscountItem = computed(() => props.watchItems.find((item) => item.offers.some((offer) => discountPercent(offer) === summary.value.bestDiscount)) ?? null);

/**
 * Posune stránku ke skupině a rozbalí ji (číslo v úvodním pruhu).
 *
 * @param {number} id
 */
async function revealItem(id) {
    groupView.value = VIEW_ITEMS;
    setExpanded(id, true);
    await nextTick();
    document.getElementById(`polozka-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** „28 akcí“ v úvodním pruhu: pohled Podle položek se vším rozbaleným. */
async function showAllOffers() {
    groupView.value = VIEW_ITEMS;
    if (!allExpanded.value) {
        toggleAll();
    }
    await nextTick();
    document.getElementById(TOOLBAR_ID)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** „5 nových“ v úvodním pruhu: štítek Nové. */
async function showFresh() {
    groupView.value = VIEW_ITEMS;
    if (!offerFilters.fresh) {
        toggleOfferFilter('fresh');
    }
    await nextTick();
    document.getElementById(TOOLBAR_ID)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Otevřená okna řazení a filtrů na telefonu (R102). */
const sortOpen = ref(false);
const filtersOpen = ref(false);

/** Název zvoleného řazení na tlačítku „Seřadit“. */
const sortLabel = computed(() => props.offersPreferences.sortOptions.find((option) => option.value === sort.value)?.label ?? '');

/** Zapnuté filtry pod lištou na telefonu — klepnutí filtr vypne; vypnuté prodejny se zapnou. */
const activeFilterChips = computed(() => [
    ...offerFilterChips.value.filter((chip) => offerFilters[chip.key]).map((chip) => ({ key: chip.key, label: chip.label, action: () => toggleOfferFilter(chip.key) })),
    ...(props.stores?.all ? [{ key: 'stores', label: t('home.all_stores'), action: toggleAllStores }] : []),
]);

/** Kolik akcí je se zvolenými filtry vidět — tlačítko „Zobrazit N akcí“ v okně. */
const visibleOffersCount = computed(() => visibleItems.value.reduce((sum, item) => sum + item.offers.length, 0));

/** Vypne štítky filtrů (okno Filtry); prodejny nechá, jak jsou. */
function clearOfferFilters() {
    for (const key of Object.keys(offerFilters)) {
        offerFilters[key] = false;
    }
}

onMounted(async () => {
    writeStored(HERO_SEEN_KEY, true);
    try {
        expandedIds.value = new Set(JSON.parse(localStorage.getItem(EXPANDED_STORAGE_KEY) ?? '[]'));
        rowsInStore.value = localStorage.getItem(ROWS_STORAGE_KEY) !== '0';
    } catch {
        expandedIds.value = new Set();
    }

    // Odkaz z Hlídám vede na konkrétní skupinu — rozbalit ji a posunout se k ní
    const match = window.location.hash.match(GROUP_HASH_PATTERN);
    if (match) {
        groupView.value = VIEW_ITEMS;
        // Položka bez akcí je v sekci Zatím bez akce — rozbalit tu (R100)
        if (waitingItems.value.some((item) => item.id === Number(match[1]))) {
            waitingExpanded.value = true;
        } else {
            setExpanded(Number(match[1]), true);
        }
        await nextTick();
        document.getElementById(`polozka-${match[1]}`)?.scrollIntoView({ block: 'start' });
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="t('home.title')" />

        <!-- Úvodní pruh: maskot, pozdrav a co Slevohlídka právě ulovila; při další návštěvě menší (R102) -->
        <section class="home-hero" :class="{ 'home-hero--compact': heroCompact }">
            <!-- Košík jede jako na úvodní stránce (R44): popojíždí, poskakuje, za ním čárky rychlosti -->
            <div v-if="!heroCompact" class="home-hero__art" aria-hidden="true">
                <span class="home-hero__streak"></span>
                <span class="home-hero__streak"></span>
                <span class="home-hero__streak"></span>
                <div class="home-hero__drive">
                    <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="home-hero__mascot" />
                </div>
            </div>
            <div class="home-hero__body">
                <h1 class="home-hero__title">{{ greetingName ? t('home.hello', { name: greetingName }) : t('home.title') }}</h1>
                <p v-if="!heroCompact" class="home-hero__text">{{ t('home.hero_text') }}</p>
                <!-- Řazení je v panelu nad skupinami (R100); tady jen hranice slevy, která akce schovává -->
                <p v-if="watchItems.length && offersPreferences.minDiscountPercent" class="home-hero__preferences">
                    {{ t('home.min_discount_filter', { percent: offersPreferences.minDiscountPercent }) }}
                    · <Link :href="urls.offersPreferences" class="link">{{ t('home.change_preferences') }}</Link>
                </p>
                <!-- Souhrn — čísla jsou zkratky: všechny akce, nové, skok na nejvyšší slevu (R102) -->
                <ul v-if="watchItems.length" class="home-hero__stats">
                    <li class="home-hero__stat">
                        <strong class="home-hero__stat-value">{{ watchItems.length }}</strong>
                        {{ t('home.stat_items', { count: watchItems.length }) }}
                    </li>
                    <li>
                        <button type="button" class="home-hero__stat home-hero__stat--button" @click="showAllOffers">
                            <strong class="home-hero__stat-value">{{ summary.offers }}</strong>
                            {{ t('home.stat_offers', { count: summary.offers }) }}
                        </button>
                    </li>
                    <li v-if="offerFilterCounts.fresh">
                        <button type="button" class="home-hero__stat home-hero__stat--button" @click="showFresh">
                            <strong class="home-hero__stat-value">{{ offerFilterCounts.fresh }}</strong>
                            {{ t('home.stat_fresh', { count: offerFilterCounts.fresh }) }}
                        </button>
                    </li>
                    <li v-if="summary.bestDiscount && bestDiscountItem">
                        <button type="button" class="home-hero__stat home-hero__stat--accent home-hero__stat--button" @click="revealItem(bestDiscountItem.id)">
                            <strong class="home-hero__stat-value">−{{ summary.bestDiscount }} %</strong>
                            {{ t('home.stat_best_item', { name: bestDiscountItem.name }) }}
                        </button>
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
            <div :id="TOOLBAR_ID" class="watch-groups__toolbar">
                <!-- Jen když je z čeho vybírat — u jednoho obchodu by výběr nic neměnil -->
                <ChainSelect
                    v-if="filterChains.length > 1"
                    id="home-chain"
                    v-model="chainFilter"
                    class="watch-groups__chain"
                    :label="t('home.chain_filter')"
                    :chains="filterChains"
                    :all-label="t('offers.all_chains')"
                    @change="onChainChange"
                />
                <!-- Podle položek, nebo Podle obchodů — kam jet nakoupit (R102); v obchodě jen položky -->
                <div v-if="!chainFilter && byChain.length" class="view-toggle watch-groups__view" role="group" :aria-label="t('home.view_label')">
                    <button type="button" class="view-toggle__button" :aria-pressed="groupView === VIEW_ITEMS ? 'true' : 'false'" @click="groupView = VIEW_ITEMS">
                        {{ t('home.view_items') }}
                    </button>
                    <button type="button" class="view-toggle__button" :aria-pressed="groupView === VIEW_CHAINS ? 'true' : 'false'" @click="groupView = VIEW_CHAINS">
                        {{ t('home.view_chains') }}
                    </button>
                </div>
                <template v-if="!showChains">
                    <!-- Řazení akcí ve skupinách — uloží se hned do účtu (R41, R100); na telefonu v okně -->
                    <SortSelect id="home-sort" v-model="sort" class="sort-select--desktop" :label="t('home.sort')" :options="offersPreferences.sortOptions" @change="saveSort" />
                    <!-- Karty s obrázkem, nebo řádky (R62, R82); na telefonu v liště -->
                    <ViewToggle v-model="compact" class="view-toggle--desktop" />
                    <button v-if="visibleItems.length" type="button" class="button button--ghost" @click="toggleAll">
                        {{ allExpanded ? t('home.collapse_all') : t('home.expand_all') }}
                    </button>
                </template>
            </div>

            <template v-if="showChains">
                <ChainOverview :by-chain="byChain" :watch-items="watchItems" />
            </template>
            <template v-else>
                <!-- Telefon (R102): Seřadit a Filtry v oknech zespodu, pod nimi zapnuté filtry -->
                <FilterBar :sort-label="sortLabel" :filter-count="activeFilterChips.length" @sort="sortOpen = true" @filters="filtersOpen = true">
                    <ViewToggle v-model="compact" />
                </FilterBar>
                <div v-if="activeFilterChips.length" class="active-filters" role="group" :aria-label="t('search.filters')">
                    <button v-for="chip in activeFilterChips" :key="chip.key" type="button" class="search-chip search-chip--on" @click="chip.action">
                        {{ chip.label }}
                        <svg class="search-chip__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
                    </button>
                </div>
                <SortSheet v-model:open="sortOpen" :options="offersPreferences.sortOptions" :value="sort" @change="(value) => ((sort = value), saveSort(value))" />
                <BottomSheet v-model:open="filtersOpen" :title="t('sheet.filters')">
                    <div class="filter-sheet">
                        <section class="filter-sheet__section">
                            <h3 class="filter-sheet__heading">{{ t('home.filter_offers') }}</h3>
                            <div class="filter-sheet__options">
                                <button
                                    v-for="chip in offerFilterChips"
                                    :key="chip.key"
                                    type="button"
                                    class="search-chip"
                                    :class="{ 'search-chip--on': offerFilters[chip.key] }"
                                    :aria-pressed="offerFilters[chip.key] ? 'true' : 'false'"
                                    @click="toggleOfferFilter(chip.key)"
                                >
                                    {{ chip.label }}
                                    <span class="search-chip__count">{{ offerFilterCounts[chip.key] }}</span>
                                </button>
                            </div>
                            <p v-if="!offerFilterChips.length" class="filter-sheet__hint">{{ t('home.filter_none') }}</p>
                        </section>
                        <section v-if="stores" class="filter-sheet__section">
                            <h3 class="filter-sheet__heading">{{ t('home.filter_stores') }}</h3>
                            <div class="filter-sheet__options">
                                <button
                                    type="button"
                                    class="search-chip"
                                    :class="{ 'search-chip--on': !stores.all }"
                                    :aria-pressed="stores.all ? 'false' : 'true'"
                                    @click="toggleAllStores"
                                >
                                    {{ t('home.my_stores', { count: stores.count }) }}
                                </button>
                            </div>
                            <p class="filter-sheet__hint">{{ t('home.my_stores_hint') }}</p>
                        </section>
                    </div>
                    <template #footer>
                        <button type="button" class="button button--ghost" :disabled="!offerFilterActive" @click="clearOfferFilters">{{ t('search.clear_filters') }}</button>
                        <button type="button" class="button button--primary" @click="filtersOpen = false">{{ t('sheet.show', { count: visibleOffersCount }) }}</button>
                    </template>
                </BottomSheet>
                <!-- Štítky filtrů akcí (R101) — široký displej; jen ty, které by něco ukázaly -->
                <div v-if="offerFilterChips.length || stores" class="search-chips search-chips--desktop watch-groups__chips">
                    <div class="search-chips__filters" role="group" :aria-label="t('search.filters')">
                        <button
                            v-for="chip in offerFilterChips"
                            :key="chip.key"
                            type="button"
                            class="search-chip"
                            :class="{ 'search-chip--on': offerFilters[chip.key] }"
                            :aria-pressed="offerFilters[chip.key] ? 'true' : 'false'"
                            @click="toggleOfferFilter(chip.key)"
                        >
                            {{ chip.label }}
                            <span class="search-chip__count">{{ offerFilterCounts[chip.key] }}</span>
                        </button>
                        <button
                            v-if="stores"
                            type="button"
                            class="search-chip"
                            :class="{ 'search-chip--on': !stores.all }"
                            :aria-pressed="stores.all ? 'false' : 'true'"
                            @click="toggleAllStores"
                        >
                            {{ t('home.my_stores', { count: stores.count }) }}
                        </button>
                    </div>
                </div>
                <WatchGroup
                    v-for="item in visibleItems"
                    :key="item.id"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    :compact="compact"
                    :with-chain="!chainFilter"
                    @update:expanded="(value) => setExpanded(item.id, value)"
                />
                <!-- Akce, které ještě nezačaly (R76) — v obchodě (výběr obchodu) se neukazují -->
                <UpcomingSection v-if="!focused && upcomingItems.length" v-model:expanded="upcomingExpanded" :items="upcomingItems" />
                <!-- Položky, které teď v akci nejsou (R100) — v obchodě se neukazují -->
                <WaitingSection v-if="waitingItems.length" v-model:expanded="waitingExpanded" :items="waitingItems" :digest-frequency="digestFrequency" :digest-url="urls.digest" />
            </template>
        </template>
    </AppLayout>
</template>
