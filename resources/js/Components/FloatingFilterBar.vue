<!--
    Plovoucí lišta hledání, řazení a filtrů na telefonu (R119): když lišta Seřadit / Filtry
    (FilterBar) odjede pod hlavičku webu, objeví se pod hlavičkou tenká lišta s ikonami —
    Hledat (dá fokus poli hledání — na telefonu se otevře přes celou obrazovku, stránka se
    neposune), Seřadit, Filtry s počtem zapnutých a přepínač karty / řádky ve slotu. Okna
    otevírá stránka stejně jako z FilterBar.

    V rozvržení nezabírá místo (position: fixed), skrytá je `inert` — klávesnice ani čtečka
    ji nenajdou, dokud není vidět. Od středního displeje se nezobrazuje (tam není FilterBar).

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { appHeaderHeight } from '@/lib/appHeader';
import { MEDIA_BELOW_MD } from '@/lib/breakpoints';
import { useTranslations } from '@/lib/i18n';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    /** Lišta Seřadit / Filtry ve stránce — plovoucí se ukáže, až ta odjede pod hlavičku. */
    anchor: { type: null, default: null },
    /** Název zvoleného řazení (pro čtečku u ikony). */
    sortLabel: { type: String, required: true },
    /** Kolik filtrů je zapnutých (0 = bez čísla). */
    filterCount: { type: Number, default: 0 },
});

const emit = defineEmits(['search', 'sort', 'filters']);

const t = useTranslations();
const visible = ref(false);

/** @type {MediaQueryList|null} */
let media = null;
/** Čeká na snímek, aby se při posouvání nepočítalo víckrát než jednou za vykreslení. */
let frame = null;

/** Lišta ve stránce (komponenta nebo prvek). */
function anchorElement() {
    const anchor = props.anchor;

    return anchor?.$el ?? anchor ?? null;
}

/** Vidět, když lišta ve stránce celá zajela pod hlavičku webu. */
function update() {
    frame = null;
    const anchor = anchorElement();
    visible.value = Boolean(media?.matches && anchor instanceof HTMLElement && anchor.getBoundingClientRect().bottom <= appHeaderHeight());
}

/** Posouvání a změna velikosti — přepočet nejvýš jednou za snímek. */
function onScroll() {
    frame ??= window.requestAnimationFrame(update);
}

onMounted(() => {
    media = window.matchMedia(MEDIA_BELOW_MD);
    media.addEventListener('change', onScroll);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    update();
});

onBeforeUnmount(() => {
    media?.removeEventListener('change', onScroll);
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    if (frame !== null) {
        window.cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <div class="floating-filter-bar" :class="{ 'floating-filter-bar--visible': visible }" :inert="!visible">
        <button type="button" class="floating-filter-bar__button" :title="t('offers.search')" @click="emit('search')">
            <!-- Lupa -->
            <svg class="filter-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.4-4.4" /></svg>
            <span class="visually-hidden">{{ t('offers.search') }}</span>
        </button>
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
        <!-- Karty / řádky vpravo -->
        <div class="floating-filter-bar__end">
            <slot />
        </div>
    </div>
</template>
