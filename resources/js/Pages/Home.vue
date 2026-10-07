<!--
    Moje slevy — úvodní pruh s maskotem a souhrnem, akce k hlídaným položkám ve sledovaných
    obchodech (R18, R19) a zmínky v letácích bez ceny (R27). Skupiny jsou sbalené, rozbalené
    si prohlížeč pamatuje (R43). Výběr obchodu ukáže jen jeho akce, rozbalené — „co z mého
    seznamu je teď v Lidlu“, když člověk stojí v obchodě (R55). Akce, které ještě nezačaly,
    jsou ve sbalené sekci Brzy pod skupinami; v obchodě se neukazují vůbec (R76). Akce jako
    karty, nebo kompaktní řádky — volba společná se Všemi akcemi, v obchodě vlastní (R62, R82).
    Položky bez akcí jsou ve sbalené sekci Zatím bez akce na konci a řazení jde změnit přímo
    nad skupinami, uloží se do účtu (R100).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainSelect from '@/Components/ChainSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SortSelect from '@/Components/SortSelect.vue';
import UpcomingSection from '@/Components/UpcomingSection.vue';
import ViewToggle from '@/Components/ViewToggle.vue';
import WaitingSection from '@/Components/WaitingSection.vue';
import WatchGroup from '@/Components/WatchGroup.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { useCompactView } from '@/lib/viewMode';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/** Klíč v localStorage s rozbalenými položkami — jen pohodlí prohlížeče, ne nastavení účtu. */
const EXPANDED_STORAGE_KEY = 'slevohlidka.home.expanded';

/** Sekce Brzy mezi rozbalenými (R76) — vedle ID položek ve stejném klíči localStorage. */
const UPCOMING_ID = 'brzy';

/** Sekce Zatím bez akce mezi rozbalenými (R100) — ve stejném klíči localStorage. */
const WAITING_ID = 'bez-akce';

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
const waitingItems = computed(() => (chainFilter.value ? [] : props.watchItems.filter((item) => !hasFinds(item))));

/**
 * Skupiny k zobrazení: položky s akcemi nebo zmínkami; po výběru obchodu jen ty s jeho akcemi
 * nebo zmínkami, a jen ty, které dnes platí — budoucí akce za akční cenu v obchodě zatím
 * nekoupíte (R76).
 */
const visibleItems = computed(() => {
    if (!chainFilter.value) {
        return props.watchItems.filter(hasFinds);
    }

    const inChain = (entry) => entry.chain === chainFilter.value;

    return props.watchItems
        .map((item) => ({
            ...item,
            offers: item.offers.filter(inChain),
            mentions: item.mentions.filter((mention) => inChain(mention) && isCurrentMention(mention)),
            upcoming: [],
            waitTip: null,
        }))
        .filter(hasFinds);
});

/** Položky s akcemi, které ještě nezačaly — sekce Brzy (R76). */
const upcomingItems = computed(() => props.watchItems.filter((item) => item.upcoming.length));

/** Rozbalené skupiny (id položek); ve výchozím stavu je vše sbalené. */
const expandedIds = ref(new Set());

/** Po výběru obchodu jsou skupiny rozbalené; sbalené klepnutím (bez zapamatování). */
const filterCollapsedIds = ref(new Set());

/**
 * Je skupina rozbalená? Po výběru obchodu ano, dokud ji uživatel nesbalí.
 *
 * @param {number} id
 * @returns {boolean}
 */
function isExpanded(id) {
    return chainFilter.value ? !filterCollapsedIds.value.has(id) : expandedIds.value.has(id);
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
    if (chainFilter.value) {
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
    if (chainFilter.value) {
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

onMounted(async () => {
    try {
        expandedIds.value = new Set(JSON.parse(localStorage.getItem(EXPANDED_STORAGE_KEY) ?? '[]'));
        rowsInStore.value = localStorage.getItem(ROWS_STORAGE_KEY) !== '0';
    } catch {
        expandedIds.value = new Set();
    }

    // Odkaz z Hlídám vede na konkrétní skupinu — rozbalit ji a posunout se k ní
    const match = window.location.hash.match(GROUP_HASH_PATTERN);
    if (match) {
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

        <!-- Úvodní pruh: maskot, pozdrav a co Slevohlídka právě ulovila -->
        <section class="home-hero">
            <!-- Košík jede jako na úvodní stránce (R44): popojíždí, poskakuje, za ním čárky rychlosti -->
            <div class="home-hero__art" aria-hidden="true">
                <span class="home-hero__streak"></span>
                <span class="home-hero__streak"></span>
                <span class="home-hero__streak"></span>
                <div class="home-hero__drive">
                    <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="home-hero__mascot" />
                </div>
            </div>
            <div class="home-hero__body">
                <h1 class="home-hero__title">{{ greetingName ? t('home.hello', { name: greetingName }) : t('home.title') }}</h1>
                <p class="home-hero__text">{{ t('home.hero_text') }}</p>
                <!-- Řazení je v panelu nad skupinami (R100); tady jen hranice slevy, která akce schovává -->
                <p v-if="watchItems.length && offersPreferences.minDiscountPercent" class="home-hero__preferences">
                    {{ t('home.min_discount_filter', { percent: offersPreferences.minDiscountPercent }) }}
                    · <Link :href="urls.offersPreferences" class="link">{{ t('home.change_preferences') }}</Link>
                </p>
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
            <div class="watch-groups__toolbar">
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
                <!-- Řazení akcí ve skupinách — uloží se hned do účtu (R41, R100) -->
                <SortSelect id="home-sort" v-model="sort" :label="t('home.sort')" :options="offersPreferences.sortOptions" @change="saveSort" />
                <!-- Karty s obrázkem, nebo řádky (R62, R82) -->
                <ViewToggle v-model="compact" />
                <button v-if="visibleItems.length" type="button" class="button button--ghost" @click="toggleAll">
                    {{ allExpanded ? t('home.collapse_all') : t('home.expand_all') }}
                </button>
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
            <UpcomingSection v-if="!chainFilter && upcomingItems.length" v-model:expanded="upcomingExpanded" :items="upcomingItems" />
            <!-- Položky, které teď v akci nejsou (R100) — v obchodě se neukazují -->
            <WaitingSection v-if="waitingItems.length" v-model:expanded="waitingExpanded" :items="waitingItems" :digest-frequency="digestFrequency" :digest-url="urls.digest" />
        </template>
    </AppLayout>
</template>
