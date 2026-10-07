<!--
    Výběr řazení na telefonu (R102) — okno zespodu se seznamem; klepnutí hned seřadí a okno
    zavře. Na širokém displeji je místo něj SortSelect.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import { useTranslations } from '@/lib/i18n';

defineProps({
    /** Možnosti [{ value, label }]. */
    options: { type: Array, required: true },
    /** Hodnota zvoleného řazení. */
    value: { type: String, required: true },
});

/** Otevřené okno. */
const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['change']);

const t = useTranslations();

/**
 * Zvolí řazení a zavře okno.
 *
 * @param {string} sort
 */
function choose(sort) {
    open.value = false;
    emit('change', sort);
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="t('sheet.sort')">
        <ul class="sheet-options">
            <li v-for="option in options" :key="option.value">
                <button type="button" class="sheet-options__option" :aria-pressed="option.value === value ? 'true' : 'false'" @click="choose(option.value)">
                    {{ option.label }}
                    <!-- Fajfka u zvoleného -->
                    <svg v-if="option.value === value" class="sheet-options__check" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </button>
            </li>
        </ul>
    </BottomSheet>
</template>
