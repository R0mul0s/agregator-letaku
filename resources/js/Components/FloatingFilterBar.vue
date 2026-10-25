<!--
    Plovoucí lišta řazení a filtrů na telefonu (R119, R120): když lišta Seřadit / Filtry
    (FilterBar) odjede pod hlavičku webu, objeví se pod hlavičkou tenká lišta s ikonami Seřadit
    a Filtry s počtem zapnutých. Další ikony dodá stránka — před ně slot `start` (Všechny akce:
    Hledat; Moje slevy: Jsem v obchodě), za ně výchozí slot vpravo (karty / řádky, Rozbalit vše).
    Okna otevírá stránka stejně jako z FilterBar; ikona ve slotu má třídu floating-filter-bar__button.

    V rozvržení nezabírá místo (position: fixed), skrytá je `inert` — klávesnice ani čtečka
    ji nenajdou, dokud není vidět. Od středního displeje se nezobrazuje (tam není FilterBar).
    Přilepená hlavička skupiny Mých slev (R117) a kotvy se na telefonu odsadí o její výšku (CSS).

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { appHeaderHeight } from '@/lib/appHeader';
import { MEDIA_BELOW_MD } from '@/lib/breakpoints';
import { useFrameScroll } from '@/lib/frameScroll';
import { useTranslations } from '@/lib/i18n';
import { ref } from 'vue';

const props = defineProps({
    /** Lišta Seřadit / Filtry ve stránce — plovoucí se ukáže, až ta odjede pod hlavičku. */
    anchor: { type: null, default: null },
    /** Název zvoleného řazení (pro čtečku u ikony). */
    sortLabel: { type: String, required: true },
    /** Kolik filtrů je zapnutých (0 = bez čísla). */
    filterCount: { type: Number, default: 0 },
});

const emit = defineEmits(['sort', 'filters']);

const t = useTranslations();
const visible = ref(false);

/** Lišta ve stránce (komponenta nebo prvek). */
function anchorElement() {
    const anchor = props.anchor;

    return anchor?.$el ?? anchor ?? null;
}

/**
 * Vidět, když lišta ve stránce celá zajela pod hlavičku webu.
 *
 * @param {boolean} matches Telefon
 */
function update(matches) {
    const anchor = anchorElement();
    visible.value = Boolean(matches && anchor instanceof HTMLElement && anchor.getBoundingClientRect().bottom <= appHeaderHeight());
}

useFrameScroll(update, MEDIA_BELOW_MD);
</script>

<template>
    <div class="floating-filter-bar" :class="{ 'floating-filter-bar--visible': visible }" :inert="!visible">
        <!-- Ikony stránky před řazením (Hledat, Jsem v obchodě) -->
        <slot name="start" />
        <button type="button" class="floating-filter-bar__button" :title="sortLabel" @click="emit('sort')">
            <!-- Šipky nahoru a dolů -->
            <svg class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4v16M4 8l4-4 4 4M16 20V4M12 16l4 4 4-4" /></svg>
            <span class="visually-hidden">{{ t('sheet.sort') }}: {{ sortLabel }}</span>
        </button>
        <button type="button" class="floating-filter-bar__button" :title="t('sheet.filters')" @click="emit('filters')">
            <!-- Trychtýř -->
            <svg class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7.5V19l-4 1.5v-8z" /></svg>
            <span class="visually-hidden">{{ t('sheet.filters') }}</span>
            <span v-if="filterCount" class="filter-bar__count floating-filter-bar__count">{{ filterCount }}</span>
        </button>
        <!-- Vpravo: karty / řádky, Rozbalit vše -->
        <div class="floating-filter-bar__end">
            <slot />
        </div>
    </div>
</template>
