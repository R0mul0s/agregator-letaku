<!--
    Spodní lišta záložek na telefonu (R66) — hlavní stránky přihlášeného na dosah palce,
    jako v nativní aplikaci; nahrazuje hamburger. Od středního displeje se neukazuje
    (navigace je v hlavičce). Položky jsou sdílená navigace s příznakem tab.

    Klepnutí přejde na stránku už při puštění prstu: když stránka po posunutí ještě dojíždí
    setrvačností, telefon první klepnutí spotřebuje na zastavení posunu a odkaz nevyvolá — v liště
    se pak muselo klepat dvakrát. Myš a klávesnice jdou přes odkaz jako dřív.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import NavIcon from '@/Components/NavIcon.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

const items = computed(() => page.props.navigation.filter((item) => item.tab));

/** Kolik px smí prst mezi dotykem a puštěním ujet, aby šlo o klepnutí, ne o tah. */
const TAP_SLOP_PX = 10;

/** @type {{ x: number, y: number }|null} */
let touchStart = null;

/**
 * Začátek dotyku — jen jedním prstem.
 *
 * @param {TouchEvent} event
 */
function onTouchStart(event) {
    const touch = event.touches[0];
    touchStart = event.touches.length === 1 ? { x: touch.clientX, y: touch.clientY } : null;
}

/** Dotyk přerušil systém (gesto, upozornění) — nejde o klepnutí. */
function onTouchCancel() {
    touchStart = null;
}

/**
 * Puštění prstu bez tahu přejde na stránku záložky; zrušení výchozí akce zabrání druhému
 * přechodu z kliknutí, které po něm prohlížeč pošle.
 *
 * @param {TouchEvent} event
 * @param {string} url
 */
function onTouchEnd(event, url) {
    const touch = event.changedTouches[0];
    const start = touchStart;
    touchStart = null;
    if (!start || !touch || Math.hypot(touch.clientX - start.x, touch.clientY - start.y) > TAP_SLOP_PX) {
        return;
    }
    event.preventDefault();
    router.visit(url);
}
</script>

<template>
    <nav v-if="items.length" class="tab-bar" :aria-label="t('nav.tabs_label')">
        <Link
            v-for="item in items"
            :key="item.url"
            :href="item.url"
            class="tab-bar__item"
            :class="{ 'tab-bar__item--active': item.active }"
            :aria-current="item.active ? 'page' : undefined"
            @touchstart.passive="onTouchStart"
            @touchend="onTouchEnd($event, item.url)"
            @touchcancel="onTouchCancel"
        >
            <NavIcon :name="item.key" />
            <span class="tab-bar__label">{{ t(`nav.tabs.${item.key}`) }}</span>
        </Link>
    </nav>
</template>
