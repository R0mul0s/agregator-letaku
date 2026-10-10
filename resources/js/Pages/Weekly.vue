<!--
    Nejlepší slevy týdne (R128) — veřejná stránka jednoho ISO týdne (po–ne): žebříček akcí
    s nejvyšší slevou napříč obchody, pod ním nejlepší slevy každého obchodu, přechod na
    sousední týdny a archiv. Týden, který skončil, nabídne přechod na aktuální týden. Odkaz
    na týden jde sdílet (sdílení systému, jinak zkopírování). Data posílá WeeklyDealsController.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OfferCard from '@/Components/OfferCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/lib/clipboard';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Nadpis stránky ze SeoMeta (stejný jako pro vyhledávače). */
    heading: { type: String, required: true },
    /** { slug, number, year, range, current } */
    week: { type: Object, required: true },
    /** Žebříček napříč obchody (OfferPresenter). */
    top: { type: Array, required: true },
    /** Nejlepší slevy po obchodech: [{ chain, url, genitive, offers }]. */
    chainSections: { type: Array, required: true },
    /** Adresa novějšího a staršího týdne v archivu; null = není. */
    newerUrl: { type: String, default: null },
    olderUrl: { type: String, default: null },
    /** Týdny od nejnovějšího (první je aktuální): [{ slug, number, year, range, url, active }]. */
    archive: { type: Array, required: true },
    /** Absolutní adresa týdne ke sdílení. */
    shareUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/** Na stránce není žádná sleva (aktuální týden před prvním stažením). */
const isEmpty = computed(() => !props.top.length && !props.chainSections.length);

/** Aktuální týden je v archivu první. */
const currentWeekUrl = computed(() => props.archive[0]?.url ?? null);

/** Pošle odkaz na týden sdílením systému (chat, sociální sítě); bez něj ho zkopíruje. */
async function share() {
    const text = t('weekly.share_text', { range: props.week.range });
    if (navigator.share) {
        try {
            await navigator.share({ title: props.heading, text, url: props.shareUrl });
        } catch {
            // Uživatel sdílení zavřel
        }

        return;
    }

    await copyText(props.shareUrl, t('weekly.share_copied'), t('weekly.share_failed'));
}
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <header class="weekly-hero">
            <p class="weekly-hero__eyebrow">
                {{ t('weekly.eyebrow', { number: week.number, year: week.year }) }}
                <span v-if="week.current" class="tag tag--accent">{{ t('weekly.current') }}</span>
            </p>
            <h1 class="page__title weekly-hero__title">{{ heading }}</h1>
            <p class="weekly-hero__lead">{{ week.current ? t('weekly.intro_current') : t('weekly.intro_past') }}</p>
            <div class="weekly-hero__actions">
                <Link v-if="!week.current && currentWeekUrl" :href="currentWeekUrl" class="button button--primary">{{ t('weekly.to_current') }}</Link>
                <button type="button" class="button button--ghost" @click="share">{{ t('weekly.share') }}</button>
            </div>
        </header>

        <nav v-if="olderUrl || newerUrl" class="weekly-pager" :aria-label="t('weekly.pager_label')">
            <Link v-if="olderUrl" :href="olderUrl" class="link weekly-pager__link">← {{ t('weekly.older') }}</Link>
            <Link v-if="newerUrl" :href="newerUrl" class="link weekly-pager__link weekly-pager__link--newer">{{ t('weekly.newer') }} →</Link>
        </nav>

        <EmptyState v-if="isEmpty" :text="t('weekly.empty')" />

        <section v-if="top.length" class="weekly-section">
            <h2 class="weekly-section__title">{{ t('weekly.top_title') }}</h2>
            <div class="offer-grid">
                <OfferCard v-for="offer in top" :key="offer.id" :offer="offer" :heading-level="3" />
            </div>
        </section>

        <section v-for="section in chainSections" :key="section.chain" class="weekly-section">
            <div class="weekly-section__header">
                <h2 class="weekly-section__title">
                    <!-- Logo je jen ozdoba, název obchodu je v textu nadpisu -->
                    <span class="weekly-section__logo" aria-hidden="true"><ChainLogo :chain="section.chain" large /></span>
                    {{ t('weekly.chain_title', { chain: page.props.chainInfo[section.chain]?.name ?? section.chain }) }}
                </h2>
                <Link :href="section.url" class="link">{{ t('weekly.chain_more', { chain: section.genitive }) }} →</Link>
            </div>
            <div class="offer-grid">
                <OfferCard v-for="offer in section.offers" :key="offer.id" :offer="offer" :heading-level="3" />
            </div>
        </section>

        <section v-if="archive.length > 1" class="weekly-section">
            <h2 class="weekly-section__title">{{ t('weekly.archive_title') }}</h2>
            <ul class="weekly-archive">
                <li v-for="item in archive" :key="item.slug">
                    <Link
                        :href="item.url"
                        class="weekly-archive__link"
                        :class="{ 'weekly-archive__link--active': item.active }"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        {{ t('weekly.eyebrow', { number: item.number, year: item.year }) }}
                        <span class="weekly-archive__range">{{ item.range }}</span>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
