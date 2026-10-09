<!--
    Pole hledání ve Všech akcích s bohatým našeptávačem (R71, combobox podle WAI-ARIA).
    Prázdné pole: poslední hledání a oblíbené produkty. Při psaní: produkty katalogu s počtem
    akcí a cenou od (klepnutí = akce produktu, tlačítko Hlídat), první akce s obrázkem, obchodem
    a cenou, „Zobrazit všech N výsledků“; překlep opraví server. Shoda je zvýrazněná.
    Šipky vybírají, Enter potvrdí (bez výběru hledá text), Escape zavře, „/“ skočí do pole.
    Delete smaže vybrané poslední hledání, Shift + Delete všechna (R99) — tlačítka v panelu jsou
    jen pro myš a dotyk, fokus zůstává v poli (combobox). Počet návrhů oznamuje skrytý stav.
    Na telefonu se hledání otevře přes celou obrazovku.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import HighlightText from '@/Components/HighlightText.vue';
import WatchOfferButton from '@/Components/WatchOfferButton.vue';
import { debounce } from '@/lib/debounce';
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { ABORTED, createLatestRequest } from '@/lib/latestRequest';
import { useListbox } from '@/lib/listbox';
import { useRotatingPlaceholder } from '@/lib/placeholder';
import { forgetSearch, recentSearches } from '@/lib/search';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

/** Pauza v psaní, po které se načtou návrhy (ms). */
const DEBOUNCE_MS = 200;

const props = defineProps({
    /** Atribut id i name pole. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Adresa návrhů (GET ?q=…, OfferSuggestionsController). */
    url: { type: String, required: true },
    /** Další parametry dotazu (zvolený obchod). */
    params: { type: Object, default: () => ({}) },
    /** Od kolika znaků se návrhy načítají. */
    minLength: { type: Number, required: true },
    /** Načítají se výsledky stránky (živé hledání) — v poli se točí kolečko. */
    loading: { type: Boolean, default: false },
    /** Adresy pro tlačítko Hlídat u produktu (WatchOfferButton), null = bez tlačítka. */
    watchUrls: { type: Object, default: null },
});

const emit = defineEmits(['search', 'product']);

const model = defineModel({ type: String, default: '' });

const t = useTranslations();
const page = usePage();

const input = ref(null);
const data = reactive({ corrected: null, total: 0, products: [], offers: [], popular: false });
const open = ref(false);
const focused = ref(false);
const fetching = ref(false);
const recent = ref([]);
const request = createLatestRequest();

const listId = computed(() => `${props.id}-suggestions`);
const text = computed(() => model.value.trim());
/** Text, ke kterému se zvýrazňuje shoda (opravený, když server opravil překlep). */
const highlightQuery = computed(() => data.corrected ?? text.value);

const placeholder = useRotatingPlaceholder(
    () => page.props.translations?.search?.examples ?? [],
    computed(() => focused.value || text.value !== ''),
    (example) => t('search.try', { example }),
);

/** Skupiny návrhů s nadpisy; každá volba má index pro ovládání klávesnicí. */
const sections = computed(() => {
    const groups = [];
    if (text.value === '') {
        groups.push({ key: 'recent', title: t('search.recent'), options: recent.value.map((query) => ({ kind: 'recent', query })) });
        groups.push({ key: 'popular', title: t('search.popular'), options: data.products.map((product) => ({ kind: 'product', product })) });
    } else {
        groups.push({ key: 'products', title: t('search.products'), options: data.products.map((product) => ({ kind: 'product', product })) });
        groups.push({ key: 'offers', title: t('search.offers'), options: data.offers.map((offer) => ({ kind: 'offer', offer })) });
        if (data.total > 0) {
            groups.push({ key: 'all', title: null, options: [{ kind: 'all' }] });
        }
    }
    let index = 0;

    return groups.filter((group) => group.options.length).map((group) => ({ ...group, options: group.options.map((option) => ({ ...option, index: index++ })) }));
});

const options = computed(() => sections.value.flatMap((section) => section.options));
const { activeIndex, activeId, activeOption, moveByArrow } = useListbox(options, listId);
/** Text pro čtečky: počet návrhů, „nic jsme nenašli“ nebo opravený překlep (R99). */
const statusText = computed(() => {
    if (!panelVisible.value) {
        return '';
    }
    if (nothingFound.value) {
        return t('search.nothing', { text: text.value });
    }
    const count = t('search.status', { count: options.value.length });

    return data.corrected ? `${t('search.corrected', { text: data.corrected })}, ${count}` : count;
});
/** Jsou v panelu poslední hledání (mazání klávesou Delete)? */
const hasRecent = computed(() => text.value === '' && recent.value.length > 0);
const hintId = computed(() => `${props.id}-hint`);
const nothingFound = computed(() => text.value.length >= props.minLength && !fetching.value && options.value.length === 0 && data.popular === false);
const panelVisible = computed(() => open.value && (options.value.length > 0 || nothingFound.value));

/** Načte návrhy k aktuálnímu textu (prázdné pole = oblíbené); předchozí požadavek zruší. */
async function load() {
    // Zrušit i při zkrácení textu — pozdní odpověď by jinak ukázala návrhy ke starému slovu
    request.cancel();
    if (text.value !== '' && text.value.length < props.minLength) {
        Object.assign(data, { corrected: null, total: 0, products: [], offers: [], popular: false });
        fetching.value = false;

        return;
    }

    fetching.value = true;
    const query = new URLSearchParams(Object.fromEntries(Object.entries({ ...props.params, q: text.value }).filter(([, value]) => value !== '' && value !== null)));
    const result = await request.json(`${props.url}?${query}`);
    // Zrušený požadavek (psaní pokračuje) nic nemění — novější ještě běží
    if (result === ABORTED) {
        return;
    }
    if (result !== null) {
        Object.assign(data, result);
        activeIndex.value = -1;
    }
    fetching.value = false;
}

/** Načte návrhy po pauze v psaní. */
const loadLater = debounce(load, DEBOUNCE_MS);

/** Text se změnil: otevře panel a načte návrhy po pauze v psaní. */
function onInput() {
    open.value = true;
    loadLater();
}

/** Fokus: otevře panel s posledními hledáními a oblíbenými, nebo návrhy k textu. */
function onFocus() {
    focused.value = true;
    recent.value = recentSearches();
    open.value = true;
    load();
}

/** Zavře panel návrhů. */
function close() {
    open.value = false;
    activeIndex.value = -1;
}

/** Konec hledání: zavře panel a na telefonu i celou obrazovku hledání a klávesnici. */
function finish() {
    close();
    input.value?.blur();
}

/**
 * Potvrdí volbu.
 *
 * @param {{ kind: string, query?: string, product?: object, offer?: object }} option
 */
function choose(option) {
    if (option.kind === 'product') {
        emit('product', option.product);
    } else {
        const query = option.kind === 'recent' ? option.query : option.kind === 'offer' ? option.offer.name : (data.corrected ?? model.value);
        model.value = query;
        emit('search', query);
    }
    finish();
}

/**
 * Zapomene jedno poslední hledání, nebo všechna.
 *
 * @param {string} [query]
 */
function forget(query) {
    forgetSearch(query);
    recent.value = recentSearches();
    activeIndex.value = -1;
}

/**
 * Ovládání klávesnicí. Enter bez vybrané volby hledá napsaný text.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (moveByArrow(event)) {
        open.value = true;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (open.value && activeOption.value) {
            choose(activeOption.value);
        } else {
            emit('search', model.value);
            finish();
        }
    } else if (event.key === 'Escape') {
        // Prohlížeč by pole typu search Escapem vymazal — Escape jen zavírá; fokus zůstává
        // v poli (R99), shozený na začátek stránky by uživatel klávesnice ztratil
        event.preventDefault();
        close();
    } else if (event.key === 'Delete' && hasRecent.value) {
        forgetFromKeyboard(event);
    }
}

/**
 * Delete nad vybraným posledním hledáním ho smaže, Shift + Delete smaže všechna (R99) —
 * tlačítka v panelu jsou jen pro myš a dotyk.
 *
 * @param {KeyboardEvent} event
 */
function forgetFromKeyboard(event) {
    const option = options.value[activeIndex.value];
    if (event.shiftKey) {
        event.preventDefault();
        forget();
    } else if (option?.kind === 'recent') {
        event.preventDefault();
        const index = activeIndex.value;
        forget(option.query);
        // Výběr zůstane na stejném místě seznamu, ať jde mazat dál
        activeIndex.value = Math.min(index, options.value.length - 1);
    }
}

/**
 * Klávesa „/“ kdekoli na stránce skočí do pole (mimo jiná pole a úpravy textu).
 *
 * @param {KeyboardEvent} event
 */
function onShortcut(event) {
    const target = event.target;
    const typing = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
        event.preventDefault();
        input.value?.focus();
    }
}

// Jiný obchod = jiné návrhy (počty akcí, oblíbené)
watch(
    () => props.params,
    () => focused.value && load(),
    { deep: true },
);

// Celá obrazovka hledání na telefonu: stránka pod ní se nesmí posouvat
watch(focused, (value) => document.documentElement.classList.toggle('has-search-sheet', value));

onMounted(() => document.addEventListener('keydown', onShortcut));

onBeforeUnmount(() => {
    loadLater.cancel();
    request.cancel();
    document.removeEventListener('keydown', onShortcut);
    document.documentElement.classList.remove('has-search-sheet');
});
</script>

<template>
    <div class="form-field search-suggest" :class="{ 'search-suggest--focused': focused }">
        <div class="search-suggest__head">
            <label :for="id" class="form-field__label search-suggest__label">{{ label }}</label>
            <!-- Na telefonu přes celou obrazovku: zpět na výsledky -->
            <button type="button" class="link-button search-suggest__back" @mousedown.prevent @click="finish">{{ t('search.back') }}</button>
        </div>
        <div class="search-suggest__control">
            <span class="search-suggest__icon" :class="{ 'search-suggest__icon--busy': loading || fetching }" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5L21 21" /></svg>
            </span>
            <input
                :id="id"
                ref="input"
                v-model="model"
                :name="id"
                type="search"
                enterkeyhint="search"
                class="form-field__input search-suggest__input"
                :placeholder="placeholder"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="panelVisible ? 'true' : 'false'"
                :aria-controls="listId"
                :aria-activedescendant="activeId"
                :aria-busy="loading || fetching ? 'true' : 'false'"
                :aria-describedby="hasRecent ? hintId : undefined"
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeydown"
                @blur="
                    focused = false;
                    close();
                "
            />
            <kbd class="search-suggest__shortcut" aria-hidden="true">/</kbd>
        </div>
        <!-- Pro čtečky (R99): počet návrhů se oznámí, jak se změní; nápověda k mazání historie -->
        <p class="visually-hidden" role="status">{{ statusText }}</p>
        <p :id="hintId" class="visually-hidden">{{ t('search.forget_hint') }}</p>

        <div v-show="panelVisible" class="search-panel">
            <p v-if="data.corrected" class="search-panel__notice">{{ t('search.corrected', { text: data.corrected }) }}</p>
            <p v-if="nothingFound" class="search-panel__notice">{{ t('search.nothing', { text }) }}</p>

            <!-- mousedown.prevent — klepnutí nesmí vzít poli fokus (blur by panel zavřel dřív, než přijde click) -->
            <ul :id="listId" class="search-panel__list" role="listbox" :aria-label="label">
                <template v-for="section in sections" :key="section.key">
                    <li v-if="section.title" class="search-panel__heading" role="presentation">
                        <span>{{ section.title }}</span>
                        <!-- Tlačítka v panelu bez tabulátoru — fokus zůstává v poli, z klávesnice Delete (R99) -->
                        <button
                            v-if="section.key === 'recent'"
                            type="button"
                            class="link-button search-panel__clear"
                            tabindex="-1"
                            @mousedown.prevent
                            @click="forget()"
                        >
                            {{ t('search.clear_recent') }}
                        </button>
                    </li>
                    <li
                        v-for="option in section.options"
                        :id="`${listId}-${option.index}`"
                        :key="`${section.key}-${option.index}`"
                        class="search-panel__option"
                        :class="[`search-panel__option--${option.kind}`, { 'search-panel__option--active': option.index === activeIndex }]"
                        role="option"
                        :aria-selected="option.index === activeIndex ? 'true' : 'false'"
                        @mousedown.prevent="choose(option)"
                        @mousemove="activeIndex = option.index"
                    >
                        <template v-if="option.kind === 'recent'">
                            <svg class="search-panel__glyph" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>
                            <span class="search-panel__main">{{ option.query }}</span>
                            <button
                                type="button"
                                class="search-panel__remove"
                                tabindex="-1"
                                :title="t('search.forget')"
                                @mousedown.prevent.stop
                                @click.stop="forget(option.query)"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                                <span class="visually-hidden">{{ t('search.forget') }}</span>
                            </button>
                        </template>

                        <template v-else-if="option.kind === 'product'">
                            <span class="search-panel__badge"><DepartmentIcon :name="option.product.icon" /></span>
                            <span class="search-panel__main">
                                <span class="search-panel__name"><HighlightText :text="option.product.name" :query="highlightQuery" /></span>
                                <span class="search-panel__meta">
                                    {{ t('search.product_offers', { count: option.product.offersCount }) }}
                                    <template v-if="option.product.lowestPrice !== null">
                                        · {{ t('search.from', { price: formatPrice(option.product.lowestPrice, page.props.locale) }) }}
                                    </template>
                                </span>
                            </span>
                            <!-- Z klávesnice: Enter otevře akce produktu, tam je Hlídat na kartách (R99) -->
                            <span v-if="watchUrls" class="search-panel__action" @mousedown.prevent.stop>
                                <WatchOfferButton
                                    :target="{ productId: option.product.id, name: option.product.name, watched: option.product.watched }"
                                    :urls="watchUrls"
                                    tabindex="-1"
                                />
                            </span>
                        </template>

                        <template v-else-if="option.kind === 'offer'">
                            <span class="search-panel__thumb">
                                <img v-if="option.offer.imageUrl" :src="option.offer.imageUrl" alt="" loading="lazy" referrerpolicy="no-referrer" />
                            </span>
                            <span class="search-panel__main">
                                <span class="search-panel__name"><HighlightText :text="option.offer.name" :query="highlightQuery" /></span>
                                <span class="search-panel__meta">
                                    <ChainLogo :chain="option.offer.chain" />
                                    <span v-if="option.offer.packageText">{{ option.offer.packageText }}</span>
                                </span>
                            </span>
                            <span class="search-panel__price">
                                <span v-if="option.offer.discountPercent" class="search-panel__discount">{{ formatDiscount(option.offer.discountPercent) }}</span>
                                <strong>{{ formatPrice(option.offer.price ?? option.offer.loyaltyPrice, page.props.locale) }}</strong>
                            </span>
                        </template>

                        <template v-else>
                            <span class="search-panel__all">{{ t('search.show_all', { count: data.total, text: highlightQuery }) }} →</span>
                        </template>
                    </li>
                </template>
            </ul>

            <p class="search-panel__keys" aria-hidden="true">
                <span><kbd>↑</kbd><kbd>↓</kbd> {{ t('search.keys_move') }}</span>
                <span><kbd>Enter</kbd> {{ t('search.keys_choose') }}</span>
                <span><kbd>Esc</kbd> {{ t('search.keys_close') }}</span>
                <span v-if="hasRecent"><kbd>Del</kbd> {{ t('search.keys_forget') }}</span>
            </p>
        </div>
    </div>
</template>
