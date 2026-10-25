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
    Pohled Podle obchodů (kam jet nakoupit), čísla jako zkratky a na telefonu Seřadit / Filtry
    v oknech zespodu (R102). Vzhled R138: kompaktní červený pruh s pozdravem, počty, „Upravit
    hlídané“, odkazem na nákupní seznam a maskotem s nejvyšší slevou dne; filtry v bílé liště
    přes jeho oblý konec, „Rozbalit vše“ u nadpisu seznamu, Brzy v modrém pruhu, Zatím bez akce
    jako dlaždice (menší pruh při další návštěvě z R102 už není — pruh je malý vždy).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ActiveFilters from '@/Components/ActiveFilters.vue';
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainOverview from '@/Components/ChainOverview.vue';
import ChainSelect from '@/Components/ChainSelect.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FilterBar from '@/Components/FilterBar.vue';
import FilterChip from '@/Components/FilterChip.vue';
import FloatingFilterBar from '@/Components/FloatingFilterBar.vue';
import InstallPrompt from '@/Components/InstallPrompt.vue';
import OfferFilterChips from '@/Components/OfferFilterChips.vue';
import SetupChecklist from '@/Components/SetupChecklist.vue';
import SortSelect from '@/Components/SortSelect.vue';
import SortSheet from '@/Components/SortSheet.vue';
import UpcomingSection from '@/Components/UpcomingSection.vue';
import ViewToggle from '@/Components/ViewToggle.vue';
import WaitingSection from '@/Components/WaitingSection.vue';
import WatchGroup from '@/Components/WatchGroup.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { appHeaderHeight } from '@/lib/appHeader';
import { useExpandedGroups } from '@/lib/expandedGroups';
import { MEDIA_FROM_MD } from '@/lib/breakpoints';
import { formatDiscount, formatNumber, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent, MATCH_MAYBE, PRICE_LOWEST, upcomingAnchor } from '@/lib/offer';
import { scrollIntoViewGently } from '@/lib/scroll';
import { readStored, writeStored } from '@/lib/storage';
import { useCompactView } from '@/lib/viewMode';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

/** Klíč v localStorage s rozbalenými položkami — jen pohodlí prohlížeče, ne nastavení účtu. */
const EXPANDED_STORAGE_KEY = 'slevohlidka.home.expanded';

/** Sekce Brzy mezi rozbalenými (R76) — vedle ID položek ve stejném klíči localStorage. */
const UPCOMING_ID = 'brzy';

/** Klíč v localStorage: pohled Podle položek, nebo Podle obchodů (R102). */
const VIEW_STORAGE_KEY = 'slevohlidka.home.view';

/** Kotva panelu nad skupinami — čísla v úvodním pruhu k němu posunou stránku. */
const TOOLBAR_ID = 'moje-slevy-akce';

/** Kotva skupiny v adrese (odkaz z dlaždice v Hlídám): #polozka-{id}. */
const GROUP_HASH_PATTERN = /^#polozka-(\d+)$/;

const props = defineProps({
    hasFollowedChains: { type: Boolean, required: true },
    /** Křestní jméno v 5. pádě do pozdravu („Romane“, R47). */
    greetingName: { type: String, required: true },
    /** Karta „Dokončete nastavení“ { steps, dismissUrl } (R153); null = průvodce je za uživatelem. */
    setup: { type: Object, default: null },
    /** Adresy předvoleb, Hlídám, účtu a nákupního seznamu. */
    urls: { type: Object, required: true },
    /** Počet položek nákupního seznamu — odkaz v pruhu (R138). */
    shoppingCount: { type: Number, default: 0 },
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
    /** Volby štítků „Sleva od“ v procentech (R145) — jako hranice slevy v Mém účtu. */
    minDiscountOptions: { type: Array, required: true },
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

/** Klíč filtru „Sleva od“ (R145) — hodnota je počet procent, ne zapnuto / vypnuto. */
const MIN_DISCOUNT = 'minDiscount';

/**
 * Štítky filtrů akcí (R101): nové, brzy končící, jen jisté shody (bez „možná“, R9); nejnižší
 * cena za poslední týdny, bez e-shopu a sleva od (R145). Nepamatují se — po návratu na stránku
 * je zase vše.
 */
const offerFilters = reactive({ fresh: false, endingSoon: false, sure: false, lowest: false, withoutEshop: false, [MIN_DISCOUNT]: null });

/** Podmínka akce pro každý štítek zapnuto / vypnuto (příznaky z HomeController a OfferPresenter). */
const OFFER_FILTER_TESTS = {
    fresh: (offer) => offer.isNew,
    endingSoon: (offer) => offer.endsSoon,
    sure: (offer) => offer.matchStatus !== MATCH_MAYBE,
    // Štítek „Nejlevněji za N týdnů“ na kartě (R59)
    lowest: (offer) => offer.priceHistory?.status === PRICE_LOWEST,
    withoutEshop: (offer) => !offer.onlineOnly,
};

/**
 * Splní akce sleva od N procent (skutečná sleva, R113)?
 *
 * @param {object} offer
 * @param {number} percent
 * @returns {boolean}
 */
const hasDiscountOf = (offer, percent) => (discountPercent(offer) ?? 0) >= percent;

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

/** Akce, ze kterých štítky počítají. */
const allChipOffers = computed(() => props.watchItems.flatMap(chipOffers));

/** Kolik akcí by který štítek ukázal — štítek bez akcí se nenabízí. */
const offerFilterCounts = computed(() => Object.fromEntries(Object.entries(OFFER_FILTER_TESTS).map(([key, test]) => [key, allChipOffers.value.filter(test).length])));

/**
 * Štítky k zobrazení — bez akcí se nenabízí, zapnutý zůstane, aby šel vypnout. „Bez e-shopu“
 * jen když jsou mezi akcemi nějaké z e-shopu (jinak by nic neschoval).
 */
const offerFilterChips = computed(() => {
    const hasEshopOffers = offerFilterCounts.value.withoutEshop < allChipOffers.value.length;

    return [
        { key: 'fresh', label: t('search.fresh', { count: props.offerFilterDays.fresh }) },
        { key: 'endingSoon', label: t('search.ending_soon', { count: props.offerFilterDays.endingSoon }) },
        { key: 'lowest', label: t('home.filter_lowest') },
        { key: 'sure', label: t('home.filter_sure') },
        { key: 'withoutEshop', label: t('search.without_eshop'), available: hasEshopOffers },
    ].filter((chip) => offerFilters[chip.key] || (chip.available !== false && offerFilterCounts.value[chip.key] > 0));
});

/**
 * Volby „Sleva od“ (R145): jen nad hranicí slevy z Mého účtu (pod ní se akce na stránku
 * nedostanou) a jen ty, které by nějakou akci ukázaly; zvolená zůstane, aby šla vypnout.
 */
const minDiscountChips = computed(() =>
    props.minDiscountOptions
        .filter((percent) => percent > (props.offersPreferences.minDiscountPercent ?? 0))
        .map((percent) => ({ percent, count: allChipOffers.value.filter((offer) => hasDiscountOf(offer, percent)).length }))
        .filter((chip) => offerFilters[MIN_DISCOUNT] === chip.percent || chip.count > 0),
);

/**
 * Po změně, která výpis zkrátí (obchod, prodejny, filtr, Sbalit vše), vrátí stránku na začátek
 * seznamu — ale jen když je uživatel pod ním (plovoucí lišta, R120). Jinak by po zkrácení
 * stránky skončil na jejím konci, kam ho prohlížeč posune.
 */
async function returnToList() {
    await nextTick();
    const toolbar = document.getElementById(TOOLBAR_ID);
    if (toolbar && toolbar.getBoundingClientRect().top < appHeaderHeight()) {
        scrollIntoViewGently(toolbar);
    }
}

/**
 * Přepne štítek filtru a rozbalí skupiny jako nový výběr.
 *
 * @param {string} key fresh | endingSoon | sure | lowest | withoutEshop
 */
function toggleOfferFilter(key) {
    offerFilters[key] = !offerFilters[key];
    groups.resetFocus();
    returnToList();
}

/**
 * Nastaví slevu od N procent a rozbalí skupiny jako nový výběr.
 *
 * @param {number|string|null} percent prázdná hodnota výběru = vypnout
 */
function selectMinDiscount(percent) {
    offerFilters[MIN_DISCOUNT] = percent ? Number(percent) : null;
    groups.resetFocus();
    returnToList();
}

/**
 * Štítek „Sleva od“ v okně Filtry: stejná volba znovu filtr vypne (null = vypnout).
 *
 * @param {number|null} percent
 */
function chooseMinDiscount(percent) {
    selectMinDiscount(offerFilters[MIN_DISCOUNT] === percent ? null : percent);
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

    const passes = (offer) =>
        Object.entries(OFFER_FILTER_TESTS).every(([key, test]) => !offerFilters[key] || test(offer)) && (!offerFilters[MIN_DISCOUNT] || hasDiscountOf(offer, offerFilters[MIN_DISCOUNT]));
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
    router.get(url, all ? {} : { [parameter]: allValue }, { preserveScroll: true, preserveState: true, replace: true, onSuccess: returnToList });
}

/**
 * Rozbalené skupiny (lib/expandedGroups.js): zapamatované, po výběru obchodu nebo štítku
 * rozbalené všechny, dokud je uživatel nesbalí.
 */
const groups = useExpandedGroups({
    storageKey: EXPANDED_STORAGE_KEY,
    sectionIds: [UPCOMING_ID],
    focused,
    visibleIds: computed(() => visibleItems.value.map((item) => item.id)),
});
const { isExpanded, allExpanded, setExpanded, toggleAll } = groups;

/** Klíč v localStorage: v obchodě akce jako řádky (výchozí), nebo karty (R62). */
const ROWS_STORAGE_KEY = 'slevohlidka.home.rows';

/**
 * Po výběru obchodu akce jako kompaktní řádky — v obchodě se míň posouvá (R62). Dřív se
 * ukládalo jako „0“ / „1“, proto se nula bere jako karty.
 */
const rowsInStore = ref(![0, false].includes(readStored(ROWS_STORAGE_KEY, true)));

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
        writeStored(ROWS_STORAGE_KEY, value);
    },
});

/** Rozbalená sekce Brzy (R76). */
const upcomingExpanded = groups.section(UPCOMING_ID);

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

/** Položka s nejvyšší slevou — číslo v úvodním pruhu na ni skočí. */
const bestDiscountItem = computed(() => props.watchItems.find((item) => item.offers.some((offer) => discountPercent(offer) === summary.value.bestDiscount)) ?? null);

/** Akce s nejvyšší slevou — lístek u maskota v pruhu (R138), klepnutí skočí na její skupinu. */
const bestOffer = computed(() => bestDiscountItem.value?.offers.find((offer) => discountPercent(offer) === summary.value.bestDiscount) ?? null);

/** Fotka akce s nejvyšší slevou se nenačetla (CDN obchodu) — místo ní silueta maskota. */
const bestImageBroken = ref(false);
watch(bestOffer, () => (bestImageBroken.value = false));

/** Cena akce s nejvyšší slevou, kterou uživatel zaplatí. */
const bestOfferPrice = computed(() => (bestOffer.value ? formatPrice(bestOffer.value.userPrice ?? bestOffer.value.price, page.props.locale) : ''));

/**
 * Posune stránku ke skupině a rozbalí ji (číslo v úvodním pruhu).
 *
 * @param {number} id
 */
async function revealItem(id) {
    groupView.value = VIEW_ITEMS;
    setExpanded(id, true);
    await nextTick();
    scrollIntoViewGently(document.getElementById(`polozka-${id}`));
}

/**
 * „+1 brzy“ v sekci Zatím bez akce: rozbalí sekci Brzy, posune stránku na akce položky a přesune
 * na ně fokus (čtečka i klávesnice pokračují tam, kam se stránka posunula).
 *
 * @param {number} id ID hlídané položky
 */
async function showUpcoming(id) {
    upcomingExpanded.value = true;
    await nextTick();
    const target = document.getElementById(upcomingAnchor(id));
    scrollIntoViewGently(target);
    target?.focus({ preventScroll: true });
}

/** „28 akcí“ v úvodním pruhu: pohled Podle položek se vším rozbaleným. */
async function showAllOffers() {
    groupView.value = VIEW_ITEMS;
    if (!allExpanded.value) {
        toggleAll();
    }
    await nextTick();
    scrollIntoViewGently(document.getElementById(TOOLBAR_ID));
}

/** „5 nových“ v úvodním pruhu: štítek Nové. */
async function showFresh() {
    groupView.value = VIEW_ITEMS;
    if (!offerFilters.fresh) {
        toggleOfferFilter('fresh');
    }
    await nextTick();
    scrollIntoViewGently(document.getElementById(TOOLBAR_ID));
}

/** Otevřená okna řazení a filtrů na telefonu (R102). */
const sortOpen = ref(false);
const filtersOpen = ref(false);

/** Název zvoleného řazení na tlačítku „Seřadit“. */
const sortLabel = computed(() => props.offersPreferences.sortOptions.find((option) => option.value === sort.value)?.label ?? '');

/** Lišta Seřadit / Filtry — po odjetí pod hlavičku ji nahradí plovoucí lišta (R120). */
const filterBar = ref(null);

/** Okno „Jsem v obchodě“ z plovoucí lišty (R120) — stejný výběr jako ChainSelect nahoře. */
const chainSheetOpen = ref(false);

/** Možnosti okna: všechny obchody a obchody s akcí nebo zmínkou. */
const chainOptions = computed(() => [
    { value: '', label: t('offers.all_chains') },
    ...filterChains.value.map((chain) => ({ value: chain, label: page.props.chainInfo[chain]?.name ?? chain })),
]);

/** Název vybraného obchodu pro čtečku u ikony v plovoucí liště. */
const chainFilterLabel = computed(() => chainOptions.value.find((option) => option.value === chainFilter.value)?.label ?? '');

/**
 * Vybere obchod z okna — jako změna v ChainSelect skupiny rozbalí jako nový výběr.
 *
 * @param {string} chain '' = všechny
 */
function chooseChain(chain) {
    chainFilter.value = chain;
    groups.resetFocus();
    returnToList();
}

/** Rozbalit / Sbalit vše; po sbalení zpět na začátek seznamu — stránka se zkrátí. */
function toggleAllGroups() {
    const collapsing = allExpanded.value;
    toggleAll();
    if (collapsing) {
        returnToList();
    }
}

/** Zapnuté filtry pod lištou na telefonu — klepnutí filtr vypne; vypnuté prodejny se zapnou. */
const activeFilterChips = computed(() => [
    ...offerFilterChips.value.filter((chip) => offerFilters[chip.key]).map((chip) => ({ key: chip.key, label: chip.label, action: () => toggleOfferFilter(chip.key) })),
    ...(offerFilters[MIN_DISCOUNT] ? [{ key: MIN_DISCOUNT, label: t('search.min_discount', { percent: offerFilters[MIN_DISCOUNT] }), action: () => chooseMinDiscount(null) }] : []),
    ...(props.stores?.all ? [{ key: 'stores', label: t('home.all_stores'), action: toggleAllStores }] : []),
]);

/** Je zapnutý některý štítek filtru („Zrušit vše“ vypne štítky, prodejny nechá). */
const hasOfferFilters = computed(() => Object.values(offerFilters).some(Boolean));

/** Kolik akcí je se zvolenými filtry vidět — tlačítko „Zobrazit N akcí“ v okně. */
const visibleOffersCount = computed(() => visibleItems.value.reduce((sum, item) => sum + item.offers.length, 0));

/** Vypne štítky filtrů (okno Filtry); prodejny nechá, jak jsou. */
function clearOfferFilters() {
    for (const key of Object.keys(offerFilters)) {
        offerFilters[key] = key === MIN_DISCOUNT ? null : false;
    }
    returnToList();
}

onMounted(async () => {
    groups.restore();

    // Odkaz z Hlídám vede na konkrétní skupinu — rozbalit ji a posunout se k ní
    const match = window.location.hash.match(GROUP_HASH_PATTERN);
    if (match) {
        groupView.value = VIEW_ITEMS;
        // Položka bez akcí je v sekci Zatím bez akce — ta se nesbaluje (R138)
        if (!waitingItems.value.some((item) => item.id === Number(match[1]))) {
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

        <!-- Kompaktní červený pruh (R138): pozdrav, počty jako zkratky (R102), Upravit hlídané,
             nákupní seznam; na širším displeji maskot s nejvyšší slevou dne -->
        <section class="home-hero">
            <div class="page__wrap home-hero__grid">
                <div class="home-hero__body">
                    <h1 class="home-hero__title">{{ greetingName ? t('home.hello', { name: greetingName }) : t('home.title') }}</h1>
                    <p v-if="watchItems.length" class="home-hero__lead">
                        {{ t('home.hero_lead', { count: summary.offers, number: formatNumber(summary.offers, page.props.locale) }) }}
                        <template v-if="offerFilterCounts.fresh">{{ t('home.hero_lead_fresh', { fresh: offerFilterCounts.fresh }) }}</template>
                    </p>
                    <p v-else-if="setup" class="home-hero__lead">{{ t('home.setup_lead') }}</p>
                    <!-- Hranice slevy, která akce schovává (R41) — řazení je v liště pod pruhem (R100) -->
                    <p v-if="watchItems.length && offersPreferences.minDiscountPercent" class="home-hero__preferences">
                        {{ t('home.min_discount_filter', { percent: offersPreferences.minDiscountPercent }) }}
                        · <Link :href="urls.offersPreferences" class="home-hero__link">{{ t('home.change_preferences') }}</Link>
                    </p>
                    <!-- Souhrn — čísla jsou zkratky: hlídané položky (R159 — vypadaly jako ostatní a lidé
                         na ně klepali), všechny akce, nové, skok na nejvyšší slevu (R102) -->
                    <ul v-if="watchItems.length" class="home-hero__stats">
                        <li>
                            <Link :href="urls.watchItems" class="home-hero__stat home-hero__stat--button">
                                <strong class="home-hero__stat-value">{{ watchItems.length }}</strong>
                                {{ t('home.stat_items', { count: watchItems.length }) }}
                            </Link>
                        </li>
                        <li>
                            <button type="button" class="home-hero__stat home-hero__stat--button" @click="showAllOffers">
                                <strong class="home-hero__stat-value">{{ summary.offers }}</strong>
                                {{ t('home.stat_offers', { count: summary.offers }) }}
                            </button>
                        </li>
                        <li v-if="offerFilterCounts.fresh">
                            <button type="button" class="home-hero__stat home-hero__stat--button home-hero__stat--fresh" @click="showFresh">
                                <strong class="home-hero__stat-value">{{ offerFilterCounts.fresh }}</strong>
                                {{ t('home.stat_fresh', { count: offerFilterCounts.fresh }) }}
                            </button>
                        </li>
                        <li v-if="summary.bestDiscount && bestDiscountItem">
                            <button type="button" class="home-hero__stat home-hero__stat--button" @click="revealItem(bestDiscountItem.id)">
                                <strong class="home-hero__stat-value">{{ formatDiscount(summary.bestDiscount) }}</strong>
                                {{ t('home.stat_best_item', { name: bestDiscountItem.name }) }}
                            </button>
                        </li>
                    </ul>
                    <div v-if="watchItems.length" class="home-hero__actions">
                        <Link :href="urls.watchItems" class="button button--highlight">{{ t('home.edit_watch_items') }}</Link>
                        <Link :href="urls.shoppingList" class="home-hero__link">
                            {{ shoppingCount ? t('home.shopping_link', { count: shoppingCount }) : t('home.shopping_link_empty') }}
                        </Link>
                    </div>
                </div>

                <!-- Maskot s nejvyšší slevou dne — klepnutí na lístek skočí na skupinu -->
                <div v-if="bestOffer" class="home-hero__art">
                    <div class="home-hero__circle" aria-hidden="true">
                        <img
                            src="/images/brand/mascot-288.webp"
                            srcset="/images/brand/mascot-192.webp 192w, /images/brand/mascot-288.webp 288w"
                            :sizes="`${MEDIA_FROM_MD} 160px, 0px`"
                            width="288"
                            height="288"
                            alt=""
                            class="home-hero__mascot"
                        />
                    </div>
                    <button
                        type="button"
                        class="home-hero__ticket"
                        :aria-label="t('home.best_ticket_label', { name: bestOffer.name, price: bestOfferPrice })"
                        @click="revealItem(bestDiscountItem.id)"
                    >
                        <!-- Bez fotky (nebo když se nenačte) silueta maskota jako na kartě akce (R138) -->
                        <span class="home-hero__ticket-image" :class="{ 'home-hero__ticket-image--none': !bestOffer.imageUrl || bestImageBroken }">
                            <img v-if="bestOffer.imageUrl && !bestImageBroken" :src="bestOffer.imageUrl" alt="" referrerpolicy="no-referrer" @error="bestImageBroken = true" />
                            <img v-else src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="home-hero__ticket-placeholder" />
                        </span>
                        <span class="home-hero__ticket-body">
                            <span class="home-hero__ticket-label">{{ t('home.best_ticket') }}</span>
                            <span class="home-hero__ticket-price">{{ bestOfferPrice }}</span>
                        </span>
                        <span class="home-hero__ticket-sticker">{{ formatDiscount(summary.bestDiscount) }}</span>
                    </button>
                </div>
            </div>
        </section>

        <div v-if="!hasFollowedChains || !watchItems.length" class="page__wrap home-empty">
            <!-- Dokončete nastavení (R153) přes oblý konec pruhu místo lišty filtrů -->
            <SetupChecklist v-if="setup" :setup="setup" />
            <EmptyState v-if="!hasFollowedChains" :text="t('home.no_chains')">
                <Link :href="urls.preferences" class="button button--primary">{{ t('home.no_chains_link') }}</Link>
            </EmptyState>
            <EmptyState v-else :text="t('home.no_watch_items')">
                <Link :href="urls.watchItems" class="button button--primary">{{ t('home.no_watch_items_link') }}</Link>
                <!-- Karta Dokončete nastavení už zavřená — průvodce jde otevřít znovu (R153) -->
                <Link v-if="!setup" :href="urls.onboarding" class="button button--soft">{{ t('watch.empty_onboarding') }}</Link>
            </EmptyState>
        </div>

        <template v-else>
            <!-- Lišta filtrů v bílé kartě přes oblý konec pruhu (R138) -->
            <div class="page__wrap home-toolbar">
                <div :id="TOOLBAR_ID" class="home-toolbar__card">
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
                            @change="groups.resetFocus"
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
                        </template>
                    </div>
                    <template v-if="!showChains">
                        <!-- Telefon (R102): Seřadit a Filtry v oknech zespodu -->
                        <FilterBar ref="filterBar" :sort-label="sortLabel" :filter-count="activeFilterChips.length" @sort="sortOpen = true" @filters="filtersOpen = true">
                            <ViewToggle v-model="compact" />
                        </FilterBar>
                        <!-- Zapnuté filtry na telefonu v kartě pod linkou (R144) — klepnutí filtr vypne -->
                        <ActiveFilters :chips="activeFilterChips" :can-clear="hasOfferFilters" @clear="clearOfferFilters" />
                        <!-- Štítky filtrů akcí (R101) — široký displej; jen ty, které by něco ukázaly -->
                        <div v-if="offerFilterChips.length || minDiscountChips.length || stores" class="search-chips search-chips--desktop watch-groups__chips">
                            <div class="search-chips__filters" role="group" :aria-label="t('search.filters')">
                                <OfferFilterChips :chips="offerFilterChips" :active="offerFilters" :counts="offerFilterCounts" @toggle="toggleOfferFilter" />
                                <!-- Jen skutečné slevy od procent (R145) — výběr ve tvaru štítku jako ve Všech akcích -->
                                <label v-if="minDiscountChips.length" class="search-chip search-chip--select" :class="{ 'search-chip--on': offerFilters[MIN_DISCOUNT] }">
                                    <span class="visually-hidden">{{ t('search.min_discount_label') }}</span>
                                    <select :value="offerFilters[MIN_DISCOUNT] ?? ''" class="search-chip__select" @change="selectMinDiscount($event.target.value)">
                                        <option value="">{{ t('search.min_discount_any') }}</option>
                                        <option v-for="chip in minDiscountChips" :key="chip.percent" :value="chip.percent">{{ t('search.min_discount', { percent: chip.percent }) }}</option>
                                    </select>
                                </label>
                                <FilterChip v-if="stores" :on="!stores.all" @click="toggleAllStores">{{ t('home.my_stores', { count: stores.count }) }}</FilterChip>
                            </div>
                        </div>
                    </template>
                </div>
                <!-- Dokončete nastavení (R153) pod lištou filtrů, dokud průvodce není za uživatelem -->
                <SetupChecklist v-if="setup" :setup="setup" class="home-toolbar__setup" />
                <!-- Výzva k přidání na plochu pod lištou filtrů, ne nad červeným pruhem (R139) -->
                <InstallPrompt inline class="home-toolbar__install" />
            </div>

            <div class="page__wrap home-groups">
                <template v-if="showChains">
                    <ChainOverview :by-chain="byChain" :watch-items="watchItems" />
                </template>
                <template v-else>
                    <!-- Po odjetí lišty pod hlavičku plovoucí ikony (R120); Položky / Obchody zůstává nahoře -->
                    <FloatingFilterBar :anchor="filterBar" :sort-label="sortLabel" :filter-count="activeFilterChips.length" @sort="sortOpen = true" @filters="filtersOpen = true">
                        <template #start>
                            <button v-if="filterChains.length > 1" type="button" class="floating-filter-bar__button" :title="t('home.chain_filter')" @click="chainSheetOpen = true">
                                <!-- Vybraný obchod je vidět jeho logem — režim „v obchodě“ je zapnutý -->
                                <ChainLogo v-if="chainFilter" :chain="chainFilter" class="floating-filter-bar__logo" aria-hidden="true" />
                                <!-- Obchod s markýzou -->
                                <svg v-else class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 4h15L21 9H3zM3 9c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3M5 12v8h14v-8M10 20v-5h4v5" /></svg>
                                <span class="visually-hidden">{{ t('home.chain_filter') }}: {{ chainFilterLabel }}</span>
                            </button>
                        </template>
                        <ViewToggle v-model="compact" />
                        <button v-if="visibleItems.length" type="button" class="floating-filter-bar__button" :title="allExpanded ? t('home.collapse_all') : t('home.expand_all')" @click="toggleAllGroups">
                            <!-- Šipky k sobě (sbalit), nebo od sebe (rozbalit) -->
                            <svg v-if="allExpanded" class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 4 5 5 5-5M7 20l5-5 5 5" /></svg>
                            <svg v-else class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5-5 5 5M7 15l5 5 5-5" /></svg>
                            <span class="visually-hidden">{{ allExpanded ? t('home.collapse_all') : t('home.expand_all') }}</span>
                        </button>
                    </FloatingFilterBar>
                    <SortSheet v-model:open="chainSheetOpen" :title="t('home.chain_filter')" :options="chainOptions" :value="chainFilter" @change="chooseChain">
                        <template #option="{ option }">
                            <ChainLogo v-if="option.value" :chain="option.value" with-name />
                            <template v-else>{{ option.label }}</template>
                        </template>
                    </SortSheet>
                    <SortSheet v-model:open="sortOpen" :options="offersPreferences.sortOptions" :value="sort" @change="(value) => ((sort = value), saveSort(value))" />
                    <BottomSheet v-model:open="filtersOpen" :title="t('sheet.filters')">
                        <div class="filter-sheet">
                            <section class="filter-sheet__section">
                                <h3 class="filter-sheet__heading">{{ t('home.filter_offers') }}</h3>
                                <div class="filter-sheet__options">
                                    <OfferFilterChips :chips="offerFilterChips" :active="offerFilters" :counts="offerFilterCounts" @toggle="toggleOfferFilter" />
                                </div>
                                <p v-if="!offerFilterChips.length" class="filter-sheet__hint">{{ t('home.filter_none') }}</p>
                            </section>
                            <section v-if="minDiscountChips.length" class="filter-sheet__section">
                                <h3 class="filter-sheet__heading">{{ t('home.filter_discount') }}</h3>
                                <div class="filter-sheet__options">
                                    <FilterChip v-for="chip in minDiscountChips" :key="chip.percent" :on="offerFilters[MIN_DISCOUNT] === chip.percent" :count="chip.count" @click="chooseMinDiscount(chip.percent)">
                                        {{ t('search.min_discount', { percent: chip.percent }) }}
                                    </FilterChip>
                                </div>
                            </section>
                            <section v-if="stores" class="filter-sheet__section">
                                <h3 class="filter-sheet__heading">{{ t('home.filter_stores') }}</h3>
                                <div class="filter-sheet__options">
                                    <FilterChip :on="!stores.all" @click="toggleAllStores">{{ t('home.my_stores', { count: stores.count }) }}</FilterChip>
                                </div>
                                <p class="filter-sheet__hint">{{ t('home.my_stores_hint') }}</p>
                            </section>
                        </div>
                        <template #footer>
                            <button type="button" class="button button--ghost" :disabled="!offerFilterActive" @click="clearOfferFilters">{{ t('search.clear_filters') }}</button>
                            <button type="button" class="button button--primary" @click="filtersOpen = false">{{ t('sheet.show', { count: visibleOffersCount }) }}</button>
                        </template>
                    </BottomSheet>

                    <!-- Nadpis seznamu s „Rozbalit vše“ (dřív v liště filtrů, R138) -->
                    <div v-if="visibleItems.length" class="home-groups__head">
                        <h2 class="home-groups__title">
                            {{ t('home.items_title') }}
                            <span class="watch-group__count">{{ visibleItems.length }}</span>
                        </h2>
                        <button type="button" class="home-groups__expand" @click="toggleAllGroups">
                            <svg v-if="allExpanded" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 4 5 5 5-5M7 20l5-5 5 5" /></svg>
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5-5 5 5M7 15l5 5 5-5" /></svg>
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
                        @show-upcoming="showUpcoming"
                    />
                </template>
            </div>

            <template v-if="!showChains">
                <!-- Akce, které ještě nezačaly (R76) — v obchodě (výběr obchodu) se neukazují -->
                <UpcomingSection v-if="!focused && upcomingItems.length" v-model:expanded="upcomingExpanded" :items="upcomingItems" />
                <!-- Položky, které teď v akci nejsou (R100) — v obchodě se neukazují -->
                <div v-if="waitingItems.length" class="page__wrap">
                    <WaitingSection
                        :items="waitingItems"
                        :digest-frequency="digestFrequency"
                        :digest-url="urls.digest"
                        :upcoming-shown="!focused && upcomingItems.length > 0"
                        @show-upcoming="showUpcoming"
                    />
                </div>
            </template>
        </template>
    </AppLayout>
</template>
