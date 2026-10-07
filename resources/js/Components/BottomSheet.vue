<!--
    Okno vyjíždějící zespodu na telefonu (R102) — řazení a filtry Všech akcí a Mých slev, jako
    v e-shopech. Nativní <dialog> (fokus zůstane uvnitř, Esc zavře, po zavření se fokus vrátí na
    tlačítko, které ho otevřelo); klepnutí na ztmavené pozadí zavře. Obsah a patička (tlačítko
    „Zobrazit N akcí“) jsou sloty.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { onBeforeUnmount, ref, useId, watch } from 'vue';

defineProps({
    /** Nadpis okna („Filtry“, „Seřadit“). */
    title: { type: String, required: true },
});

/** Otevřené okno (řídí stránka). */
const open = defineModel('open', { type: Boolean, default: false });

const t = useTranslations();
const dialog = ref(null);
const titleId = useId();

watch(open, (value) => {
    if (value && !dialog.value?.open) {
        dialog.value?.showModal();
    } else if (!value && dialog.value?.open) {
        dialog.value.close();
    }
});

/**
 * Klepnutí na ztmavené pozadí (mimo obsah okna) okno zavře.
 *
 * @param {MouseEvent} event
 */
function onClick(event) {
    if (event.target === dialog.value) {
        open.value = false;
    }
}

// Přechod na jinou stránku s otevřeným oknem — zavřít, jinak by zůstalo ve vrstvě nad stránkou
onBeforeUnmount(() => dialog.value?.open && dialog.value.close());
</script>

<template>
    <dialog ref="dialog" class="bottom-sheet" :aria-labelledby="titleId" @cancel.prevent="open = false" @close="open = false" @click="onClick">
        <div class="bottom-sheet__header">
            <h2 :id="titleId" class="bottom-sheet__title">{{ title }}</h2>
            <button type="button" class="icon-button" :title="t('sheet.close')" @click="open = false">
                <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                <span class="visually-hidden">{{ t('sheet.close') }}</span>
            </button>
        </div>
        <div class="bottom-sheet__body">
            <slot />
        </div>
        <div v-if="$slots.footer" class="bottom-sheet__footer">
            <slot name="footer" />
        </div>
    </dialog>
</template>
