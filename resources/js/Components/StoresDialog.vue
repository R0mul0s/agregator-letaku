<!--
    Okno „Kde akce platí“ (R49) — prodejny, ve kterých akce platí, české řazení, vybrané
    prodejny uživatele nahoře s fajfkou; u delšího seznamu hledání podle názvu. Nativní
    <dialog>: Esc a klik vedle zavřou. Obsah (načtený až po otevření, R106) řídí lib/storesDialog.js.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { normalizeSearch } from '@/lib/search';
import { closeStoresDialog, storesDialogState } from '@/lib/storesDialog';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

/** Od kolika prodejen se nabídne hledání. */
const SEARCH_FROM = 10;

const t = useTranslations();
const page = usePage();
const dialog = ref(null);
const closeButton = ref(null);
const query = ref('');

/** Vybrané prodejny nahoře, jinak podle abecedy (české řazení). */
const sorted = computed(() =>
    [...storesDialogState.stores].sort((a, b) => Number(b.selected) - Number(a.selected) || a.name.localeCompare(b.name, page.props.locale)),
);

const filtered = computed(() => {
    const needle = normalizeSearch(query.value.trim());

    return needle === '' ? sorted.value : sorted.value.filter((store) => normalizeSearch(store.name).includes(needle));
});

watch(
    () => storesDialogState.open,
    async (open) => {
        if (open && !dialog.value?.open) {
            query.value = '';
            dialog.value?.showModal();
            await nextTick();
            closeButton.value?.focus();
        } else if (!open && dialog.value?.open) {
            dialog.value.close();
        }
    },
);

/**
 * Klik na ztmavené pozadí okno zavře.
 *
 * @param {MouseEvent} event
 */
function onClick(event) {
    if (event.target === dialog.value) {
        closeStoresDialog();
    }
}
</script>

<template>
    <dialog ref="dialog" class="stores-dialog" aria-labelledby="stores-dialog-title" @cancel.prevent="closeStoresDialog" @close="closeStoresDialog" @click="onClick">
        <header class="stores-dialog__header">
            <div>
                <h2 id="stores-dialog-title" class="stores-dialog__title">{{ t('offers.stores_dialog_title') }}</h2>
                <p class="stores-dialog__offer">{{ storesDialogState.offerName }}</p>
                <p class="stores-dialog__count">
                    {{ t('offers.stores_dialog_count', { count: storesDialogState.count, chain: storesDialogState.chainName }) }}
                </p>
            </div>
            <button ref="closeButton" type="button" class="stores-dialog__close" :title="t('offers.stores_dialog_close')" @click="closeStoresDialog">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                <span class="visually-hidden">{{ t('offers.stores_dialog_close') }}</span>
            </button>
        </header>

        <input
            v-if="storesDialogState.count >= SEARCH_FROM"
            v-model="query"
            type="search"
            class="form-field__input stores-dialog__search"
            :placeholder="t('preferences.stores_search')"
            :aria-label="t('preferences.stores_search')"
        />

        <p v-if="storesDialogState.loading" class="form-field__hint" role="status">{{ t('offers.stores_dialog_loading') }}</p>
        <p v-else-if="storesDialogState.failed" class="form-field__hint" role="status">{{ t('offers.stores_dialog_failed') }}</p>
        <ul v-else class="stores-dialog__list">
            <li v-for="store in filtered" :key="store.name" class="stores-dialog__store" :class="{ 'stores-dialog__store--selected': store.selected }">
                <svg v-if="store.selected" class="stores-dialog__check" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                {{ store.name }}
                <span v-if="store.selected" class="visually-hidden">({{ t('offers.stores_dialog_mine') }})</span>
            </li>
        </ul>
        <p v-if="!storesDialogState.loading && !storesDialogState.failed && !filtered.length" class="form-field__hint">{{ t('preferences.stores_none_found') }}</p>
    </dialog>
</template>
