<!--
    Právní stránka (R51): podmínky užití nebo zásady zpracování osobních údajů. Vlevo lepivý
    obsah z kapitol (nadpisy ##), při posouvání se zvýrazní kapitola, ve které čtenář je;
    na telefonu a tabletu je obsah lišta pod hlavičkou, která dojede k aktivní kapitole (R116). Text je náš Markdown z resources/legal,
    převedený na serveru (App\Support\Legal\LegalDocuments, syrové HTML zahozené) — proto
    v-html, který je jinak pro obsah od obchodů zakázaný.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { scrollIntoViewGently } from '@/lib/scroll';
import { useScrollSpy } from '@/lib/scrollSpy';
import { useSectionNav } from '@/lib/sectionNav';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

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

/** Kapitola, ve které čtenář je — zvýrazní se v obsahu. */
const { activeId, select } = useScrollSpy(
    () => props.sections.map((section) => section.id),
    () => window.innerHeight * ACTIVE_LINE_RATIO,
);

/** Obsah na telefonu jako lišta pod hlavičkou, posune se k aktivní kapitole (R116). */
const toc = ref(null);
useSectionNav(toc, activeId);

/**
 * Plynule posune na kapitolu (s ohledem na omezení pohybu), dá jí fokus a zapíše ji do adresy.
 * Fokus (R99): další Tab pokračuje v kapitole, ne zpátky v obsahu.
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
    scrollIntoViewGently(heading);
    // Nadpis z Markdownu není ovládací prvek — fokus jen programově, bez zastávky tabulátoru
    heading.setAttribute('tabindex', '-1');
    heading.focus({ preventScroll: true });
    window.history.replaceState(window.history.state, '', `#${id}`);
    select(id);
}

onMounted(() => {
    // Odkaz zvenku na kapitolu (#…) — obsah vznikl až po načtení, prohlížeč na něj neposunul
    const target = window.location.hash ? document.getElementById(decodeURIComponent(window.location.hash.slice(1))) : null;
    target?.scrollIntoView({ block: 'start' });
});
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <header class="page__header">
            <h1 class="page__title">{{ title }}</h1>
        </header>

        <div class="legal" :class="{ 'legal--no-toc': !sections.length }">
            <!-- Na počítači sloupec vlevo, na telefonu a tabletu lišta pod hlavičkou (R116) -->
            <nav v-if="sections.length" ref="toc" class="legal-toc" data-section-bar :aria-label="t('legal.toc')">
                <p class="legal-toc__title">{{ t('legal.toc') }}</p>
                <ol class="legal-toc__list">
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
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="legal__body" v-html="html" />
            </article>
        </div>
    </AppLayout>
</template>
