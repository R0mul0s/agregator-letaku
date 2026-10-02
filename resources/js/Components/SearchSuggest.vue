<!--
    Pole hledání s našeptávačem (combobox podle WAI-ARIA) — návrhy ze serveru po krátké pauze
    v psaní; šipky vybírají, Enter potvrdí, Escape zavře. Výběr návrhu hned hledá.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { computed, onBeforeUnmount, ref } from 'vue';

/** Pauza v psaní, po které se načtou návrhy (ms). */
const DEBOUNCE_MS = 250;

const props = defineProps({
    /** Atribut id i name pole. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    placeholder: { type: String, default: undefined },
    /** Adresa návrhů (GET ?q=…, vrací { suggestions: [{ type, label }] }). */
    url: { type: String, required: true },
    /** Další parametry dotazu (zvolený obchod). */
    params: { type: Object, default: () => ({}) },
    /** Od kolika znaků se návrhy načítají. */
    minLength: { type: Number, required: true },
});

const emit = defineEmits(['select']);

const model = defineModel({ type: String, default: '' });

const t = useTranslations();
const suggestions = ref([]);
const open = ref(false);
const activeIndex = ref(-1);
let timer = null;
let controller = null;

const listId = computed(() => `${props.id}-suggestions`);
const activeId = computed(() => (activeIndex.value >= 0 ? `${listId.value}-${activeIndex.value}` : undefined));

/** Načte návrhy k aktuálnímu textu; předchozí nedokončený požadavek zruší. */
async function load() {
    const text = model.value.trim();
    if (text.length < props.minLength) {
        close();

        return;
    }

    controller?.abort();
    controller = new AbortController();
    const query = new URLSearchParams(Object.fromEntries(Object.entries({ ...props.params, q: text }).filter(([, value]) => value !== '')));

    try {
        const response = await fetch(`${props.url}?${query}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) {
            return;
        }
        suggestions.value = (await response.json()).suggestions;
        activeIndex.value = -1;
        open.value = suggestions.value.length > 0;
    } catch (error) {
        // Zrušený požadavek (psaní pokračuje) není chyba; jiné chyby jen schovají návrhy
        if (error.name !== 'AbortError') {
            close();
        }
    }
}

/** Načte návrhy po pauze v psaní. */
function onInput() {
    clearTimeout(timer);
    timer = setTimeout(load, DEBOUNCE_MS);
}

/** Zavře seznam návrhů. */
function close() {
    open.value = false;
    activeIndex.value = -1;
}

/**
 * Vybere návrh: doplní text a ohlásí výběr.
 *
 * @param {{ label: string }} suggestion
 */
function choose(suggestion) {
    model.value = suggestion.label;
    close();
    emit('select', suggestion.label);
}

/**
 * Ovládání klávesnicí.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (!open.value) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + step + suggestions.value.length) % suggestions.value.length;
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        choose(suggestions.value[activeIndex.value]);
    } else if (event.key === 'Escape') {
        close();
    }
}

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div class="form-field search-suggest">
        <label :for="id" class="form-field__label">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :name="id"
            type="search"
            class="form-field__input"
            :placeholder="placeholder"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="listId"
            :aria-activedescendant="activeId"
            @input="onInput"
            @keydown="onKeydown"
            @blur="close"
        />
        <ul v-show="open" :id="listId" class="search-suggest__list" role="listbox" :aria-label="label">
            <!-- mousedown místo click — click by přišel až po blur pole, který seznam zavře -->
            <li
                v-for="(suggestion, index) in suggestions"
                :id="`${listId}-${index}`"
                :key="`${suggestion.type}-${suggestion.label}`"
                class="search-suggest__option"
                :class="{ 'search-suggest__option--active': index === activeIndex }"
                role="option"
                :aria-selected="index === activeIndex ? 'true' : 'false'"
                @mousedown.prevent="choose(suggestion)"
            >
                <span>{{ suggestion.label }}</span>
                <span v-if="suggestion.type === 'product'" class="tag">{{ t('offers.suggestion_product') }}</span>
            </li>
        </ul>
    </div>
</template>
