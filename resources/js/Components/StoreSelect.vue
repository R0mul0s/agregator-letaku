<!--
    Výběr více prodejen obchodu (R49) — vybrané jako štítky s křížkem, pod nimi hledání podle
    názvu nebo města (bez diakritiky) a nabídka prodejen k přidání. Nic nevybráno = všechny.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { normalizeSearch } from '@/lib/search';
import { computed, ref, useId } from 'vue';

/** Kolik nalezených prodejen nabídnout najednou. */
const MAX_RESULTS = 8;

const props = defineProps({
    /** Prodejny obchodu [{ code, name, city }]. */
    stores: { type: Array, required: true },
    /** Nejvíc vybraných prodejen. */
    max: { type: Number, required: true },
});

/** Kódy vybraných prodejen. */
const selected = defineModel({ type: Array, required: true });

const t = useTranslations();
const query = ref('');
const listId = useId();

const storesByCode = computed(() => new Map(props.stores.map((store) => [store.code, store])));
const selectedStores = computed(() => selected.value.map((code) => storesByCode.value.get(code)).filter(Boolean));
const full = computed(() => selected.value.length >= props.max);

/** Nevybrané prodejny odpovídající hledání (název nebo město); bez hledání nic. */
const results = computed(() => {
    const needle = normalizeSearch(query.value.trim());
    if (needle === '') {
        return [];
    }

    return props.stores
        .filter((store) => !selected.value.includes(store.code) && normalizeSearch(`${store.name} ${store.city}`).includes(needle))
        .slice(0, MAX_RESULTS);
});

/**
 * Přidá prodejnu a vyčistí hledání.
 *
 * @param {object} store
 */
function add(store) {
    if (!full.value) {
        selected.value = [...selected.value, store.code];
        query.value = '';
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

/** Enter v hledání přidá první nalezenou prodejnu (a neodešle formulář). */
function addFirst() {
    if (results.value.length) {
        add(results.value[0]);
    }
}
</script>

<template>
    <div class="store-select">
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

        <input
            v-if="!full"
            v-model="query"
            type="search"
            class="form-field__input store-select__input"
            :placeholder="t('preferences.stores_search')"
            :aria-label="t('preferences.stores_search')"
            :aria-controls="listId"
            autocomplete="off"
            @keydown.enter.prevent="addFirst"
        />
        <p v-else class="form-field__hint">{{ t('preferences.stores_max', { count: max }) }}</p>

        <ul v-if="results.length" :id="listId" class="store-select__results">
            <li v-for="store in results" :key="store.code">
                <button type="button" class="store-select__result" @click="add(store)">
                    <span class="store-select__result-name">{{ store.name }}</span>
                    <span v-if="store.city !== store.name" class="store-select__result-city">{{ store.city }}</span>
                </button>
            </li>
        </ul>
        <p v-else-if="query.trim() !== ''" class="form-field__hint">{{ t('preferences.stores_none_found') }}</p>
    </div>
</template>
