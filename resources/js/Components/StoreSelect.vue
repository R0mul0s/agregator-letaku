<!--
    Výběr více prodejen obchodu (R49, R168) — pole se seznamem (combobox podle WAI-ARIA): klepnutí
    rozbalí všechny prodejny podle abecedy, psaní je filtruje podle názvu nebo města (bez diakritiky).
    Klepnutí nebo Enter prodejnu přidá nebo odebere a seznam zůstane otevřený; šipky vybírají,
    Escape zavře. Vybrané prodejny jsou nad polem jako štítky s křížkem. Nic nevybráno = všechny.
    Stejné ovládání pro 5 regionů COOP i 149 prodejen Kauflandu.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { useListbox } from '@/lib/listbox';
import { normalizeSearch } from '@/lib/search';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, useId, watch } from 'vue';

const props = defineProps({
    /** Prodejny obchodu [{ code, name, city, note? }] — poznámka u COOP prodejny bez sledovaného letáku. */
    stores: { type: Array, required: true },
    /** Nejvíc vybraných prodejen. */
    max: { type: Number, required: true },
});

/** Kódy vybraných prodejen. */
const selected = defineModel({ type: Array, required: true });

const t = useTranslations();
const page = usePage();
const query = ref('');
const open = ref(false);
const root = ref(null);
const input = ref(null);
const list = ref(null);
const listId = useId();

/** Prodejny podle české abecedy (server řadí podle databáze, „Ch“ tam nepatří za „H“). */
const sortedStores = computed(() => [...props.stores].sort((a, b) => a.name.localeCompare(b.name, page.props.locale)));
const storesByCode = computed(() => new Map(props.stores.map((store) => [store.code, store])));
const selectedStores = computed(() => selected.value.map((code) => storesByCode.value.get(code)).filter(Boolean));
const full = computed(() => selected.value.length >= props.max);

/** Prodejny odpovídající hledání (název nebo město); bez hledání všechny. */
const results = computed(() => {
    const needle = normalizeSearch(query.value.trim());

    return needle === '' ? sortedStores.value : sortedStores.value.filter((store) => normalizeSearch(`${store.name} ${store.city}`).includes(needle));
});

const { activeIndex, activeId, activeOption, moveByArrow } = useListbox(results, listId);

// Nové hledání začne od první nalezené prodejny
watch(query, () => {
    activeIndex.value = results.value.length ? 0 : -1;
});

// Vybraná volba musí být vidět i v dlouhém seznamu (Kaufland)
watch(activeIndex, async (index) => {
    await nextTick();
    list.value?.querySelector(`#${CSS.escape(`${listId}-${index}`)}`)?.scrollIntoView({ block: 'nearest' });
});

/**
 * Je prodejna vybraná?
 *
 * @param {string} code
 * @returns {boolean}
 */
function isSelected(code) {
    return selected.value.includes(code);
}

/**
 * Přidá nebo odebere prodejnu; nad limitem jde jen odebírat.
 *
 * @param {object} store
 */
function toggle(store) {
    if (isSelected(store.code)) {
        remove(store.code);
    } else if (!full.value) {
        selected.value = [...selected.value, store.code];
    }
}

/**
 * Odebere prodejnu.
 *
 * @param {string} code
 */
function remove(code) {
    selected.value = selected.value.filter((selectedCode) => selectedCode !== code);
}

/** Rozbalí seznam. */
function show() {
    open.value = true;
}

/** Šipka u pole: fokus do pole rozbalí seznam a klepnutí mimo ho pak zase sbalí. */
function focusInput() {
    input.value?.focus();
    show();
}

/** Sbalí seznam a zruší hledání. */
function close() {
    open.value = false;
    query.value = '';
    activeIndex.value = -1;
}

/**
 * Klávesnice v poli: šipky vybírají (a rozbalí seznam), Enter prodejnu přidá nebo odebere,
 * Escape sbalí seznam — v okně filtrů (<dialog>) ho nezavře.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (!open.value && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        show();

        return;
    }
    if (moveByArrow(event)) {
        return;
    }
    if (event.key === 'Enter') {
        // Enter neodešle formulář
        event.preventDefault();
        if (open.value && activeOption.value) {
            toggle(activeOption.value);
        }
    } else if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        event.stopPropagation();
        close();
    }
}

/**
 * Fokus mimo komponentu seznam sbalí (Tab dál, klepnutí jinam).
 *
 * @param {FocusEvent} event
 */
function onFocusOut(event) {
    if (!root.value?.contains(event.relatedTarget)) {
        close();
    }
}
</script>

<template>
    <div ref="root" class="store-select" @focusout="onFocusOut">
        <ul v-if="selectedStores.length" class="store-select__selected">
            <li v-for="store in selectedStores" :key="store.code" class="store-select__chip">
                {{ store.name }}
                <button type="button" class="store-select__remove" :title="t('preferences.stores_remove', { store: store.name })" @click="remove(store.code)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    <span class="visually-hidden">{{ t('preferences.stores_remove', { store: store.name }) }}</span>
                </button>
            </li>
        </ul>
        <p v-else class="store-select__all">{{ t('preferences.stores_all') }}</p>

        <div class="store-select__field">
            <input
                ref="input"
                v-model="query"
                type="search"
                class="form-field__input store-select__input"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="listId"
                :aria-activedescendant="open ? activeId : undefined"
                :placeholder="t('preferences.stores_pick')"
                :aria-label="t('preferences.stores_pick')"
                autocomplete="off"
                @focus="show"
                @click="show"
                @input="show"
                @keydown="onKeydown"
            />
            <!-- Šipka jen pro myš a dotyk — z klávesnice seznam rozbalí šipka dolů v poli -->
            <button type="button" class="store-select__toggle" tabindex="-1" aria-hidden="true" @mousedown.prevent @click="open ? close() : focusInput()">
                <svg viewBox="0 0 24 24" :class="{ 'store-select__toggle-icon--open': open }"><path d="M6 9l6 6 6-6" /></svg>
            </button>
        </div>
        <p v-if="full" class="form-field__hint">{{ t('preferences.stores_max', { count: max }) }}</p>

        <!-- Jen rozbalený — COOP má ~1 450 prodejen a každá karta obchodu jeden výběr -->
        <ul v-if="open" :id="listId" ref="list" class="store-select__results" role="listbox" aria-multiselectable="true" :aria-label="t('preferences.stores')">
            <li
                v-for="(store, index) in results"
                :id="`${listId}-${index}`"
                :key="store.code"
                class="store-select__result"
                :class="{ 'store-select__result--active': index === activeIndex }"
                role="option"
                :aria-selected="isSelected(store.code) ? 'true' : 'false'"
                :aria-disabled="full && !isSelected(store.code) ? 'true' : undefined"
                @mousedown.prevent
                @click="toggle(store)"
                @mouseenter="activeIndex = index"
            >
                <!-- Zaškrtávátko jen pro oko — stav nese aria-selected -->
                <span class="store-select__check" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10" /></svg>
                </span>
                <span class="store-select__result-text">
                    <span class="store-select__result-name">{{ store.name }}</span>
                    <span v-if="store.city !== store.name || store.note" class="store-select__result-city">
                        {{ [store.city !== store.name ? store.city : null, store.note].filter(Boolean).join(' · ') }}
                    </span>
                </span>
            </li>
            <li v-if="!results.length" class="store-select__empty" role="presentation">{{ t('preferences.stores_none_found') }}</li>
        </ul>
    </div>
</template>
