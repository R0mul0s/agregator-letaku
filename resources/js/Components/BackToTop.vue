<!--
    Tlačítko „Nahoru“ vpravo dole — košík maskota s červenou šipkou v rohu (R92). Objeví se po
    odscrollování, klepnutím vyjede stránka na začátek (plynule, při omezení pohybu skokem).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { scrollToTop } from '@/lib/scroll';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Od kolika odscrollovaných pixelů se tlačítko ukáže (zhruba výška jedné obrazovky telefonu). */
const SHOW_AFTER_PX = 600;

const t = useTranslations();
const visible = ref(false);

/** Ukáže tlačítko, až je začátek stránky z dohledu. */
function onScroll() {
    visible.value = window.scrollY > SHOW_AFTER_PX;
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <button type="button" class="back-to-top" :class="{ 'back-to-top--visible': visible }" :tabindex="visible ? 0 : -1" :aria-hidden="visible ? undefined : 'true'" @click="scrollToTop">
        <img src="/images/brand/logo-mark.png" alt="" class="back-to-top__mascot" />
        <span class="back-to-top__badge" aria-hidden="true">
            <svg class="back-to-top__icon" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
        </span>
        <span class="visually-hidden">{{ t('back_to_top') }}</span>
    </button>
</template>
