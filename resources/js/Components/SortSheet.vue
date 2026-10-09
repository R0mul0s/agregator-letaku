<!--
    Výběr jedné možnosti na telefonu — okno zespodu se seznamem; klepnutí hned vybere a okno
    zavře. Řazení (R102; na širokém displeji je místo něj SortSelect) a „Jsem v obchodě“
    z plovoucí lišty Mých slev (R120, možnost s logem obchodu přes slot `option`).

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import { useTranslations } from '@/lib/i18n';

defineProps({
    /** Možnosti [{ value, label }]. */
    options: { type: Array, required: true },
    /** Hodnota zvolené možnosti. */
    value: { type: String, required: true },
    /** Nadpis okna; null = „Seřadit“. */
    title: { type: String, default: null },
});

/** Otevřené okno. */
const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['change']);

const t = useTranslations();

/**
 * Zvolí možnost a zavře okno.
 *
 * @param {string} value
 */
function choose(value) {
    open.value = false;
    emit('change', value);
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="title ?? t('sheet.sort')">
        <ul class="sheet-options">
            <li v-for="option in options" :key="option.value">
                <button type="button" class="sheet-options__option" :aria-pressed="option.value === value ? 'true' : 'false'" @click="choose(option.value)">
                    <!-- Obsah možnosti (logo obchodu s názvem), jinak text -->
                    <slot name="option" :option="option">{{ option.label }}</slot>
                    <!-- Fajfka u zvoleného -->
                    <svg v-if="option.value === value" class="sheet-options__check" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </button>
            </li>
        </ul>
    </BottomSheet>
</template>
