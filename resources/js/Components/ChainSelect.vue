<!--
    Výběr obchodu s logy (listbox podle WAI-ARIA) — nativní <select> obrázky v položkách neumí.
    Šipky a Home/End vybírají, Enter / mezera potvrdí, Escape zavře.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    /** Atribut id tlačítka. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Obchody (hodnoty App\Enums\Chain) v pořadí nabídky. */
    chains: { type: Array, required: true },
    /** Text volby „bez omezení“ (hodnota ''). */
    allLabel: { type: String, required: true },
});

const emit = defineEmits(['change']);

const model = defineModel({ type: String, default: '' });

const open = ref(false);
const activeIndex = ref(0);
const root = ref(null);
const list = ref(null);

/** Volby: „všechny“ a pak obchody. */
const options = computed(() => ['', ...props.chains]);
const listId = computed(() => `${props.id}-list`);

/** Otevře seznam s aktivní vybranou volbou. */
async function show() {
    activeIndex.value = Math.max(0, options.value.indexOf(model.value));
    open.value = true;
    await nextTick();
    list.value?.focus();
}

/**
 * Vybere volbu a zavře seznam.
 *
 * @param {string} value
 */
function choose(value) {
    open.value = false;
    if (value !== model.value) {
        model.value = value;
        emit('change', value);
    }
}

/**
 * Ovládání seznamu klávesnicí.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    const last = options.value.length - 1;
    const moves = { ArrowDown: activeIndex.value + 1, ArrowUp: activeIndex.value - 1, Home: 0, End: last };

    if (event.key in moves) {
        event.preventDefault();
        activeIndex.value = Math.min(last, Math.max(0, moves[event.key]));
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        choose(options.value[activeIndex.value]);
    } else if (event.key === 'Escape' || event.key === 'Tab') {
        open.value = false;
    }
}

/**
 * Klepnutí mimo komponentu seznam zavře.
 *
 * @param {MouseEvent} event
 */
function onDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="form-field chain-select">
        <span :id="`${id}-label`" class="form-field__label">{{ label }}</span>
        <button
            :id="id"
            type="button"
            class="form-field__input chain-select__button"
            aria-haspopup="listbox"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-labelledby="`${id}-label ${id}`"
            @click="open ? (open = false) : show()"
        >
            <ChainLogo v-if="model" :chain="model" with-name />
            <span v-else>{{ allLabel }}</span>
            <span class="chain-select__arrow" aria-hidden="true">▾</span>
        </button>
        <ul
            v-show="open"
            :id="listId"
            ref="list"
            class="chain-select__list"
            role="listbox"
            tabindex="-1"
            :aria-labelledby="`${id}-label`"
            :aria-activedescendant="`${listId}-${activeIndex}`"
            @keydown="onKeydown"
        >
            <li
                v-for="(value, index) in options"
                :id="`${listId}-${index}`"
                :key="value || 'all'"
                class="chain-select__option"
                :class="{ 'chain-select__option--active': index === activeIndex }"
                role="option"
                :aria-selected="value === model ? 'true' : 'false'"
                @click="choose(value)"
                @mouseenter="activeIndex = index"
            >
                <ChainLogo v-if="value" :chain="value" with-name />
                <span v-else>{{ allLabel }}</span>
            </li>
        </ul>
    </div>
</template>
