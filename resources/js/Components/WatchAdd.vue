<!--
    Pole „Co chcete hlídat?“ v Hlídám — našeptává produkty katalogu (R31) a poslední volbou
    nabídne hlídání vlastními slovy (R18). Combobox podle WAI-ARIA: šipky vybírají,
    Enter potvrdí, Escape zavře. Katalog má stránka celý, návrhy se filtrují v prohlížeči.

    R71: shoda od začátku slova v názvu (kategorie až když název nic nenajde), řazení podle
    toho, kolik akcí produkt teď má; u produktu ikona oddělení, kategorie a „12 akcí · od
    24,90 Kč“ — hned je vidět, jestli se hlídání vyplatí. Vlastní slova s živým náhledem,
    kolik akcí by našla. Překlep („pyzza“) nabídne nejpodobnější produkty.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import HighlightText from '@/Components/HighlightText.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { useRotatingPlaceholder } from '@/lib/placeholder';
import { editDistance, normalizeSearch, searchWords, startsWord } from '@/lib/search';
import { useWatchPreview } from '@/lib/watchPreview';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';

/** Kolik produktů katalogu našeptávač ukáže najednou. */
const MAX_SUGGESTIONS = 8;

/** Kolik podobných produktů nabídnout při překlepu. */
const MAX_TYPO_SUGGESTIONS = 3;

/** Od kolika znaků hledat překlep; do kolika znaků slova stačí 1 změna, delší 2 (jako server). */
const TYPO_MIN_LENGTH = 3;
const TYPO_SHORT_WORD_LENGTH = 3;

/** Kratší text se hledá jen jako začátek názvu produktu. */
const MIN_WORD_MATCH_LENGTH = 2;

const props = defineProps({
    /** Produkty katalogu [{ id, name, categoryName, icon, watched, offersCount, lowestPrice }]. */
    products: { type: Array, required: true },
    /** Adresa náhledu vlastních slov (WatchItemController::preview). */
    previewUrl: { type: String, default: null },
});

const emit = defineEmits(['product', 'own']);

const t = useTranslations();
const page = usePage();
const id = useId();
const listId = `${id}-suggestions`;

const text = ref('');
const open = ref(false);
const focused = ref(false);
const activeIndex = ref(-1);

const query = computed(() => text.value.trim());

/** Produkty s normalizovaným názvem a kategorií — normalizace jednou, ne při každém písmenu. */
const searchable = computed(() =>
    props.products.map((product) => ({ product, name: normalizeSearch(product.name), category: normalizeSearch(product.categoryName ?? '') })),
);

/**
 * Pořadí shody: 0 = název začíná celým textem, 1 = všechna slova začínají slova názvu,
 * 2 = slova jsou v názvu nebo kategorii; null = neodpovídá.
 */
function rank(item, words, normalizedQuery) {
    if (item.name.startsWith(normalizedQuery)) {
        return 0;
    }
    // Jedno písmeno jen jako začátek názvu — jinak najde každé „a“ v „Aperol a Campari“
    if (normalizedQuery.length < MIN_WORD_MATCH_LENGTH) {
        return null;
    }
    if (words.every((word) => startsWord(item.name, word))) {
        return 1;
    }

    return words.every((word) => startsWord(item.name, word) || startsWord(item.category, word)) ? 2 : null;
}

/** Produkty odpovídající textu: nejlepší shoda, pak víc akcí, pak abeceda (pořadí ze serveru). */
const matches = computed(() => {
    const words = searchWords(query.value);
    if (!words.length) {
        return [];
    }
    const normalizedQuery = words.join(' ');

    return searchable.value
        .map((item) => ({ item, rank: rank(item, words, normalizedQuery) }))
        .filter((entry) => entry.rank !== null)
        .sort((a, b) => a.rank - b.rank || b.item.product.offersCount - a.item.product.offersCount)
        .slice(0, MAX_SUGGESTIONS)
        .map((entry) => entry.item.product);
});

/** Při překlepu (nic neodpovídá) nejpodobnější produkty podle slov názvu. */
const typoMatches = computed(() => {
    const words = searchWords(query.value);
    if (matches.value.length || !words.length || query.value.length < TYPO_MIN_LENGTH) {
        return [];
    }

    return searchable.value
        .map((item) => {
            const nameWords = item.name.split(/[^a-z0-9]+/).filter(Boolean);
            const distance = words.reduce((sum, word) => {
                const limit = word.length <= TYPO_SHORT_WORD_LENGTH ? 1 : 2;
                // Překlep málokdy změní první písmeno — jiné první písmeno je změna navíc („pyza“ → Pizza, ne Rýže)
                const best = Math.min(
                    ...nameWords.map((nameWord) => editDistance(word, nameWord.slice(0, word.length + limit)) + (nameWord[0] === word[0] ? 0 : 1)),
                );

                return best <= limit ? sum + best : Infinity;
            }, 0);

            return { product: item.product, distance };
        })
        .filter((entry) => Number.isFinite(entry.distance))
        .sort((a, b) => a.distance - b.distance || b.product.offersCount - a.product.offersCount)
        .slice(0, MAX_TYPO_SUGGESTIONS)
        .map((entry) => entry.product);
});

/** Volby seznamu: produkty (nebo podobné při překlepu), pak vždy hlídání vlastními slovy. */
const options = computed(() => [
    ...(matches.value.length ? matches.value : typoMatches.value).map((product) => ({ type: 'product', product })),
    ...(query.value ? [{ type: 'own' }] : []),
]);

const activeId = computed(() => (activeIndex.value >= 0 ? `${listId}-${activeIndex.value}` : undefined));

const placeholder = useRotatingPlaceholder(
    () => page.props.translations?.watch?.examples ?? [],
    computed(() => focused.value || query.value !== ''),
    (example) => t('search.try', { example }),
);

// Kolik akcí by našla vlastní slova — náhled u poslední volby (R71)
const preview = useWatchPreview(props.previewUrl, () => ({ keywords: query.value.toLocaleLowerCase(page.props.locale) }));

/** Po změně textu otevře seznam od začátku. */
function onInput() {
    activeIndex.value = -1;
    open.value = options.value.length > 0;
}

/** Zavře seznam. */
function close() {
    open.value = false;
    activeIndex.value = -1;
}

/**
 * Potvrdí volbu: produkt začne hlídat, vlastní slova otevřou formulář s napsaným textem.
 *
 * @param {{ type: string, product?: object }} option
 */
function choose(option) {
    if (option.type === 'product') {
        if (option.product.watched) {
            return;
        }
        emit('product', option.product);
    } else {
        emit('own', query.value);
    }
    text.value = '';
    close();
}

/**
 * Ovládání klávesnicí. Enter bez vybrané volby vezme první produkt, jinak vlastní slova.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = options.value.length > 0;
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + step + options.value.length) % options.value.length;
    } else if (event.key === 'Enter' && options.value.length) {
        event.preventDefault();
        const firstAvailable = options.value.find((option) => option.type === 'own' || !option.product.watched);
        choose(activeIndex.value >= 0 ? options.value[activeIndex.value] : firstAvailable);
    } else if (event.key === 'Escape') {
        // Prohlížeč by pole typu search Escapem vymazal — Escape jen zavírá
        event.preventDefault();
        close();
    }
}

/**
 * „12 akcí · od 24,90 Kč“, nebo „teď bez akce“.
 *
 * @param {{ offersCount: number, lowestPrice: number|null }} product
 * @returns {string}
 */
function offersSummary(product) {
    if (!product.offersCount) {
        return t('watch.no_offers_now');
    }
    const count = t('watch.offers_count', { count: product.offersCount });

    return product.lowestPrice === null ? count : `${count} · ${t('watch.lowest_price', { price: formatPrice(product.lowestPrice, page.props.locale) })}`;
}
</script>

<template>
    <div class="watch-add">
        <label :for="id" class="watch-add__label">{{ t('watch.add_label') }}</label>
        <div class="search-suggest" :class="{ 'search-suggest--focused': focused }">
            <div class="search-suggest__control">
                <span class="search-suggest__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5L21 21" /></svg>
                </span>
                <input
                    :id="id"
                    v-model="text"
                    type="search"
                    enterkeyhint="go"
                    class="form-field__input search-suggest__input watch-add__input"
                    :placeholder="placeholder"
                    role="combobox"
                    autocomplete="off"
                    aria-autocomplete="list"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-controls="listId"
                    :aria-activedescendant="activeId"
                    :aria-describedby="`${id}-hint`"
                    @input="onInput"
                    @focus="
                        focused = true;
                        onInput();
                    "
                    @keydown="onKeydown"
                    @blur="
                        focused = false;
                        close();
                    "
                />
            </div>
            <div v-show="open" class="search-panel">
                <p v-if="!matches.length && options.length > 1" class="search-panel__notice">{{ t('watch.did_you_mean') }}</p>
                <ul :id="listId" class="search-panel__list" role="listbox" :aria-label="t('watch.add_label')">
                    <!-- mousedown místo click — click by přišel až po blur pole, který seznam zavře -->
                    <li
                        v-for="(option, index) in options"
                        :id="`${listId}-${index}`"
                        :key="option.type === 'product' ? option.product.id : 'own'"
                        class="search-panel__option"
                        :class="{
                            'search-panel__option--active': index === activeIndex,
                            'search-panel__option--disabled': option.type === 'product' && option.product.watched,
                            'search-panel__option--own': option.type === 'own',
                        }"
                        role="option"
                        :aria-selected="index === activeIndex ? 'true' : 'false'"
                        :aria-disabled="option.type === 'product' && option.product.watched ? 'true' : undefined"
                        @mousedown.prevent="choose(option)"
                        @mousemove="activeIndex = index"
                    >
                        <template v-if="option.type === 'product'">
                            <span class="search-panel__badge"><DepartmentIcon :name="option.product.icon" /></span>
                            <span class="search-panel__main">
                                <span class="search-panel__name"><HighlightText :text="option.product.name" :query="query" /></span>
                                <span class="search-panel__meta">
                                    <template v-if="option.product.categoryName">{{ option.product.categoryName }} · </template>
                                    <span :class="{ 'search-panel__hot': option.product.offersCount > 0 }">{{ offersSummary(option.product) }}</span>
                                </span>
                            </span>
                            <span v-if="option.product.watched" class="tag tag--accent">✓ {{ t('watch.watching') }}</span>
                        </template>
                        <template v-else>
                            <span class="search-panel__badge search-panel__badge--accent" aria-hidden="true">+</span>
                            <span class="search-panel__main">
                                <span class="search-panel__name">{{ t('watch.own_option', { text: query }) }}</span>
                                <span class="search-panel__meta">
                                    <template v-if="preview.loading">{{ t('watch.preview_loading') }}</template>
                                    <template v-else-if="preview.count === 0">{{ t('watch.preview_none') }}</template>
                                    <span v-else-if="preview.count !== null" class="search-panel__hot">{{ t('watch.preview_count', { count: preview.count }) }}</span>
                                    <template v-else>{{ t('watch.own_meta') }}</template>
                                </span>
                            </span>
                        </template>
                    </li>
                </ul>
            </div>
        </div>
        <p :id="`${id}-hint`" class="form-field__hint">
            {{ t('watch.add_hint') }}
            <button type="button" class="link-button" @click="emit('own', query)">{{ t('watch.own_link') }}</button>
        </p>
    </div>
</template>
