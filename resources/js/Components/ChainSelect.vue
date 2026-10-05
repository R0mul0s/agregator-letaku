<!--
    Výběr obchodu s logy (listbox podle WAI-ARIA) — nativní <select> obrázky v položkách neumí.
    Šipky a Home/End vybírají, Enter / mezera potvrdí, Escape zavře. S `multiple` (Všechny akce,
    R82) jde vybrat víc obchodů: klepnutí obchod přidá nebo odebere a seznam zůstane otevřený,
    „Všechny obchody“ výběr zruší; vybrané všechny obchody = bez omezení (prázdné pole).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { useTranslations } from '@/lib/i18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/** Kolik log vybraných obchodů se vejde do tlačítka; zbytek jako „+2“. */
const MAX_BUTTON_LOGOS = 3;

const props = defineProps({
    /** Atribut id tlačítka. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Obchody (hodnoty App\Enums\Chain) v pořadí nabídky. */
    chains: { type: Array, required: true },
    /** Text volby „bez omezení“ (hodnota '' nebo prázdné pole). */
    allLabel: { type: String, required: true },
    /** Výběr víc obchodů — model je pole hodnot. */
    multiple: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);

/** Jeden obchod ('' = všechny), s `multiple` pole obchodů ([] = všechny). */
const model = defineModel({ type: [String, Array], default: '' });

const t = useTranslations();

const open = ref(false);
const activeIndex = ref(0);
const root = ref(null);
const list = ref(null);

/** Volby: „všechny“ a pak obchody. */
const options = computed(() => ['', ...props.chains]);
const listId = computed(() => `${props.id}-list`);

/** Vybrané obchody jako pole (i u jednoho výběru). */
const selected = computed(() => {
    if (props.multiple) {
        return model.value;
    }

    return model.value ? [model.value] : [];
});

/** Loga do tlačítka a počet obchodů, které se nevešly. */
const buttonLogos = computed(() => selected.value.slice(0, MAX_BUTTON_LOGOS));
const hiddenCount = computed(() => Math.max(0, selected.value.length - MAX_BUTTON_LOGOS));

/**
 * Je volba vybraná? „Všechny“ při prázdném výběru.
 *
 * @param {string} value
 * @returns {boolean}
 */
function isSelected(value) {
    return value === '' ? selected.value.length === 0 : selected.value.includes(value);
}

/** Otevře seznam s aktivní vybranou volbou. */
async function show() {
    activeIndex.value = Math.max(0, options.value.indexOf(selected.value[0] ?? ''));
    open.value = true;
    await nextTick();
    list.value?.focus();
}

/**
 * Vybere volbu. Jeden obchod: nastaví ho a zavře seznam. Víc obchodů: obchod přidá nebo
 * odebere (seznam zůstane otevřený), „všechny“ výběr zruší a seznam zavře.
 *
 * @param {string} value
 */
function choose(value) {
    if (!props.multiple) {
        open.value = false;
        if (value !== model.value) {
            model.value = value;
            emit('change', value);
        }

        return;
    }

    let next = [];
    if (value === '') {
        open.value = false;
        if (selected.value.length === 0) {
            return;
        }
    } else {
        const chosen = isSelected(value) ? selected.value.filter((chain) => chain !== value) : [...selected.value, value];
        // Pořadí jako v nabídce; vybrané všechny obchody = bez omezení
        next = props.chains.filter((chain) => chosen.includes(chain));
        if (next.length === props.chains.length) {
            next = [];
        }
    }
    model.value = next;
    emit('change', next);
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
            <span v-if="selected.length === 0">{{ allLabel }}</span>
            <ChainLogo v-else-if="selected.length === 1" :chain="selected[0]" with-name />
            <span v-else class="chain-select__logos">
                <ChainLogo v-for="chain in buttonLogos" :key="chain" :chain="chain" />
                <span v-if="hiddenCount" class="chain-select__more">{{ t('offers.more_chains', { count: hiddenCount }) }}</span>
            </span>
            <span class="chain-select__arrow" aria-hidden="true">▾</span>
        </button>
        <ul
            v-show="open"
            :id="listId"
            ref="list"
            class="chain-select__list"
            role="listbox"
            tabindex="-1"
            :aria-multiselectable="multiple ? 'true' : undefined"
            :aria-labelledby="`${id}-label`"
            :aria-activedescendant="`${listId}-${activeIndex}`"
            @keydown="onKeydown"
        >
            <li
                v-for="(value, index) in options"
                :id="`${listId}-${index}`"
                :key="value || 'all'"
                class="chain-select__option"
                :class="{ 'chain-select__option--active': index === activeIndex, 'chain-select__option--check': multiple }"
                role="option"
                :aria-selected="isSelected(value) ? 'true' : 'false'"
                @click="choose(value)"
                @mouseenter="activeIndex = index"
            >
                <!-- Zaškrtávátko jen pro oko — stav nese aria-selected -->
                <span v-if="multiple" class="chain-select__check" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10" /></svg>
                </span>
                <ChainLogo v-if="value" :chain="value" with-name />
                <span v-else>{{ allLabel }}</span>
            </li>
        </ul>
    </div>
</template>
