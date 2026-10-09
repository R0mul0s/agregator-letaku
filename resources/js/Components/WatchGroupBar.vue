<!--
    Přilepená hlavička rozbalené skupiny v Mých slevách na telefonu a tabletu (R117): když se
    hlavička skupiny posune pod hlavičku webu, objeví se místo ní tenká lišta na jeden řádek
    (název, počet, nejnižší cena) a drží se nahoře, dokud skupina nekončí — pak ji odsune další
    položka. Klepnutí skupinu sbalí a stránku vrátí na její začátek.

    Patří jako první prvek do <section> skupiny (lepí se v ní). V rozvržení nezabírá místo
    (záporný spodní okraj), do té doby je skrytá. Pro čtečky a klávesnici je duplicitní —
    skupinu ovládá tlačítko ve skutečné hlavičce.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { appHeaderHeight } from '@/lib/appHeader';
import { MEDIA_BELOW_LG } from '@/lib/breakpoints';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    /** Štítek s počtem (text jako ve skutečné hlavičce). */
    count: { type: String, required: true },
    /** Nejnižší cena („od 22,90 Kč“), null = bez ceny. */
    summary: { type: String, default: null },
});

const emit = defineEmits(['collapse']);

/** Skutečná hlavička skupiny — lišta se ukáže, až ta zajede pod hlavičku webu. */
const GROUP_HEADER_SELECTOR = '.watch-group__header';

const root = ref(null);
const stuck = ref(false);

/** @type {MediaQueryList|null} */
let media = null;
/** Čeká na snímek, aby se při posouvání nepočítalo víckrát než jednou za vykreslení. */
let frame = null;

/**
 * Přilepená, když spodní okraj skutečné hlavičky skupiny přejel spodní okraj lišty a skupina
 * ještě pod hlavičkou webu pokračuje.
 */
function update() {
    frame = null;
    const bar = root.value;
    const section = bar?.parentElement;
    const header = section?.querySelector(GROUP_HEADER_SELECTOR);
    if (!bar || !section || !header || !media?.matches) {
        stuck.value = false;

        return;
    }

    const line = appHeaderHeight();
    stuck.value = header.getBoundingClientRect().bottom <= line + bar.offsetHeight && section.getBoundingClientRect().bottom > line;
}

/** Posouvání a změna velikosti — přepočet nejvýš jednou za snímek. */
function onScroll() {
    frame ??= window.requestAnimationFrame(update);
}

/**
 * Sbalí skupinu a posune stránku na její začátek — obsah pod lištou zmizí a bez posunu by
 * uživatel skončil kdesi v dalších položkách.
 */
function collapse() {
    const section = root.value?.parentElement;
    emit('collapse');
    nextTick(() => section?.scrollIntoView({ block: 'start', behavior: 'instant' }));
}

onMounted(() => {
    media = window.matchMedia(MEDIA_BELOW_LG);
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
    <div ref="root" class="watch-group__bar" :class="{ 'watch-group__bar--stuck': stuck }" aria-hidden="true">
        <button type="button" class="watch-group__bar-button" tabindex="-1" @click="collapse">
            <span class="watch-group__chevron">▸</span>
            <span class="watch-group__bar-name">{{ title }}</span>
            <span class="watch-group__count">{{ count }}</span>
            <span v-if="summary" class="watch-group__summary">{{ summary }}</span>
        </button>
    </div>
</template>
