<!--
    Lišta řazení a filtrů na telefonu (R102) — dvě tlačítka místo řádku štítků, jako v e-shopech:
    „Seřadit“ s právě zvoleným řazením a „Filtry“ s počtem zapnutých. Otevírají okna zespodu
    (BottomSheet), o obsah se stará stránka. Od středního displeje je skrytá, platí řádek štítků.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import { useTranslations } from '@/lib/i18n';

defineProps({
    /** Název zvoleného řazení na tlačítku. */
    sortLabel: { type: String, required: true },
    /** Kolik filtrů je zapnutých (0 = bez čísla). */
    filterCount: { type: Number, default: 0 },
});

const emit = defineEmits(['sort', 'filters']);

const t = useTranslations();
</script>

<template>
    <div class="filter-bar">
        <button type="button" class="filter-bar__button" @click="emit('sort')">
            <!-- Šipky nahoru a dolů -->
            <svg class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4v16M4 8l4-4 4 4M16 20V4M12 16l4 4 4-4" /></svg>
            <span class="visually-hidden">{{ t('sheet.sort') }}:</span>
            <span class="filter-bar__value">{{ sortLabel }}</span>
        </button>
        <button type="button" class="filter-bar__button" @click="emit('filters')">
            <!-- Trychtýř -->
            <svg class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7.5V19l-4 1.5v-8z" /></svg>
            {{ t('sheet.filters') }}
            <span v-if="filterCount" class="filter-bar__count">{{ filterCount }}</span>
        </button>
        <!-- Další ovládání v řádku (karty / řádky) -->
        <slot />
    </div>
</template>
