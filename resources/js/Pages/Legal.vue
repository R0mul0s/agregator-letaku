<!--
    Právní stránka (R51): podmínky užití nebo zásady zpracování osobních údajů. Vlevo lepivý
    obsah z kapitol (nadpisy ##), při posouvání se zvýrazní kapitola, ve které čtenář je;
    na telefonu je obsah nahoře jako rozbalovací blok. Text je náš Markdown z resources/legal,
    převedený na serveru (App\Support\Legal\LegalDocuments, syrové HTML zahozené) — proto
    v-html, který je jinak pro obsah od obchodů zakázaný.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    /** Text dokumentu jako HTML; nadpisy kapitol mají id. */
    html: { type: String, required: true },
    /** Kapitoly pro obsah [{ id, title }]. */
    sections: { type: Array, default: () => [] },
    /** Datum účinnosti YYYY-MM-DD, null = neuvádí se. */
    effectiveFrom: { type: String, default: null },
});

/**
 * Kapitola je aktivní, když její nadpis přešel tuhle čáru — podíl výšky okna od horního
 * okraje (pod plovoucí hlavičkou, ať se aktivní kapitola nemění až u samého okraje).
 */
const ACTIVE_LINE_RATIO = 0.3;

const t = useTranslations();
const page = usePage();

const activeId = ref(props.sections[0]?.id ?? null);
const tocOpen = ref(false);

let frame = null;

/**
 * Najde kapitolu, ve které čtenář je: poslední nadpis nad čarou; na konci stránky poslední
 * kapitola (krátká poslední kapitola by se jinak nikdy nezvýraznila).
 */
function updateActive() {
    frame = null;
    const line = window.innerHeight * ACTIVE_LINE_RATIO;
    const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 1;
    let current = props.sections[0]?.id ?? null;

    for (const section of props.sections) {
        const heading = document.getElementById(section.id);
        if (heading && heading.getBoundingClientRect().top <= line) {
            current = section.id;
        }
    }
    activeId.value = atBottom ? (props.sections.at(-1)?.id ?? current) : current;
}

/** Posouvání a změna velikosti — přepočet nejvýš jednou za snímek. */
function onScroll() {
    frame ??= window.requestAnimationFrame(updateActive);
}

/**
 * Plynule posune na kapitolu (s ohledem na omezení pohybu) a zapíše ji do adresy.
 *
 * @param {MouseEvent} event
 * @param {string} id
 */
function goTo(event, id) {
    const heading = document.getElementById(id);
    if (!heading) {
        return;
    }
    event.preventDefault();
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    heading.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    window.history.replaceState(window.history.state, '', `#${id}`);
    activeId.value = id;
    tocOpen.value = false;
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    // Odkaz zvenku na kapitolu (#…) — obsah vznikl až po načtení, prohlížeč na něj neposunul
    const target = window.location.hash ? document.getElementById(decodeURIComponent(window.location.hash.slice(1))) : null;
    target?.scrollIntoView({ block: 'start' });
    updateActive();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    if (frame !== null) {
        window.cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <header class="page__header">
            <h1 class="page__title">{{ title }}</h1>
        </header>

        <div class="legal" :class="{ 'legal--no-toc': !sections.length }">
            <nav v-if="sections.length" class="legal-toc" :aria-label="t('legal.toc')">
                <!-- Telefon: obsah se rozbalí tlačítkem; od tabletu je vidět vždy -->
                <button type="button" class="legal-toc__toggle" :aria-expanded="tocOpen ? 'true' : 'false'" @click="tocOpen = !tocOpen">
                    {{ t('legal.toc') }}
                    <svg class="legal-toc__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                </button>
                <p class="legal-toc__title">{{ t('legal.toc') }}</p>
                <ol class="legal-toc__list" :class="{ 'legal-toc__list--open': tocOpen }">
                    <li v-for="section in sections" :key="section.id">
                        <a
                            :href="`#${section.id}`"
                            class="legal-toc__link"
                            :class="{ 'legal-toc__link--active': section.id === activeId }"
                            :aria-current="section.id === activeId ? 'location' : undefined"
                            @click="goTo($event, section.id)"
                        >
                            {{ section.title }}
                        </a>
                    </li>
                </ol>
            </nav>

            <article class="legal__content">
                <p v-if="effectiveFrom" class="legal__effective">{{ t('legal.effective_from', { date: formatDate(effectiveFrom, page.props.locale) }) }}</p>
                <!-- v-html: vlastní text z resources/legal převedený na serveru, ne obsah od obchodu -->
                <div class="legal__body" v-html="html" />
            </article>
        </div>
    </AppLayout>
</template>
