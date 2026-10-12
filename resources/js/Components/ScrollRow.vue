<!--
    Řádek posuvný do strany s nápovědou, že pokračuje (R141): na okraji, kde je další obsah,
    jemné vyblednutí, a na zařízení s myší kulaté šipky (prstem se posouvá přímo, šipky by na
    telefonu jen braly místo). Bez přetečení (všechno se vejde) nic z toho není. Atributy
    a třída komponenty patří posuvnému řádku (např. role a aria-label skupiny).

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { scrollBehavior } from '@/lib/scroll';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });

/** Jakou část viditelné šířky posune jedno klepnutí na šipku. */
const SCROLL_PAGE_SHARE = 0.8;

/** Tolerance zaokrouhlení posunu (px) — na konci řádku se šipka nemá ukazovat. */
const EDGE_TOLERANCE_PX = 2;

const t = useTranslations();

const track = ref(null);
const canScrollBack = ref(false);
const canScrollForward = ref(false);

/** @type {ResizeObserver|null} */
let observer = null;

/** Je vlevo nebo vpravo ještě obsah? */
function update() {
    const element = track.value;
    if (!element) {
        return;
    }
    canScrollBack.value = element.scrollLeft > EDGE_TOLERANCE_PX;
    canScrollForward.value = element.scrollLeft + element.clientWidth < element.scrollWidth - EDGE_TOLERANCE_PX;
}

/**
 * Posune řádek o kus viditelné šířky.
 *
 * @param {number} direction -1 zpět, 1 dál
 */
function scrollBy(direction) {
    const element = track.value;
    element?.scrollBy({ left: direction * element.clientWidth * SCROLL_PAGE_SHARE, behavior: scrollBehavior() });
}

onMounted(() => {
    update();
    observer = new ResizeObserver(update);
    observer.observe(track.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="scroll-row" :class="{ 'scroll-row--back': canScrollBack, 'scroll-row--forward': canScrollForward }">
        <!-- Šipky jen pro myš — klávesnice a čtečka procházejí tlačítka v řádku přímo -->
        <button v-show="canScrollBack" type="button" class="scroll-row__arrow scroll-row__arrow--back" tabindex="-1" :aria-label="t('scroll_row.back')" @click="scrollBy(-1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg>
        </button>
        <div ref="track" v-bind="$attrs" @scroll.passive="update">
            <slot />
        </div>
        <button v-show="canScrollForward" type="button" class="scroll-row__arrow scroll-row__arrow--forward" tabindex="-1" :aria-label="t('scroll_row.forward')" @click="scrollBy(1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
        </button>
    </div>
</template>
