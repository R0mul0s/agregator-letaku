<!--
    Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
    jak to funguje a skutečné akce s nejvyšší slevou. Motivy z loga: maskot, cenovka, čárky.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import FeatureIcon from '@/Components/FeatureIcon.vue';
import OfferCard from '@/Components/OfferCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';

/** Přednosti v pořadí zobrazení (lang: ui.landing.features). */
const FEATURES = ['watch', 'unit_price', 'cards', 'mentions', 'digest', 'free'];

/** Kroky „Jak to funguje“ (lang: ui.landing.steps). */
const STEPS = ['chains', 'watch', 'hunt'];

/** Kolikrát denně se stahují akce (cron 5:00 a 13:00, deploy/DEPLOYMENT.md). */
const UPDATES_PER_DAY = 2;

defineProps({
    /** Adresy registrace, přihlášení a Všech akcí. */
    urls: { type: Object, required: true },
    /** Počty { offers, chains, products }. */
    stats: { type: Object, required: true },
    /** Obchody se zdrojem dat (hodnoty App\Enums\Chain). */
    chains: { type: Array, required: true },
    /** Akce s nejvyšší slevou z různých obchodů (OfferPresenter). */
    topOffers: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Číslo s oddělením tisíců („6 245“).
 *
 * @param {number} value
 * @returns {string}
 */
function formatNumber(value) {
    return value.toLocaleString(page.props.locale);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('landing.title')" />

        <section class="landing-hero">
            <div class="landing-hero__body">
                <p class="landing-hero__eyebrow">{{ t('landing.eyebrow') }}</p>
                <h1 class="landing-hero__title">{{ t('landing.headline') }}</h1>
                <p class="landing-hero__lead">{{ t('landing.lead') }}</p>
                <div class="landing-hero__actions">
                    <Link :href="urls.register" class="button button--primary landing-hero__cta">{{ t('landing.register') }}</Link>
                    <Link :href="urls.offers" class="button button--ghost">{{ t('landing.browse') }}</Link>
                </div>
                <p class="landing-hero__login">
                    {{ t('landing.login_hint') }} <Link :href="urls.login" class="link">{{ t('landing.login') }}</Link>
                </p>
                <ul class="landing-hero__chains">
                    <li v-for="chain in chains" :key="chain"><ChainLogo :chain="chain" large /></li>
                </ul>
            </div>
            <!-- Košík „jede“: poskakuje, za ním ubíhají čárky rychlosti (jen CSS, při omezení pohybu stojí) -->
            <div class="landing-hero__art" aria-hidden="true">
                <div class="landing-hero__circle">
                    <span class="landing-hero__streak"></span>
                    <span class="landing-hero__streak"></span>
                    <span class="landing-hero__streak"></span>
                    <div class="landing-hero__drive">
                        <img src="/images/brand/icon-512.png" alt="" class="landing-hero__mascot" />
                    </div>
                </div>
                <span class="landing-hero__sticker">−50 %</span>
            </div>
        </section>

        <ul class="landing-stats">
            <li class="landing-stats__item">
                <strong class="landing-stats__value">{{ formatNumber(stats.offers) }}</strong>
                {{ t('landing.stats.offers', { count: stats.offers }) }}
            </li>
            <li class="landing-stats__item">
                <strong class="landing-stats__value">{{ stats.chains }}</strong>
                {{ t('landing.stats.chains', { count: stats.chains }) }}
            </li>
            <li class="landing-stats__item">
                <strong class="landing-stats__value">{{ formatNumber(stats.products) }}</strong>
                {{ t('landing.stats.products', { count: stats.products }) }}
            </li>
            <li class="landing-stats__item">
                <strong class="landing-stats__value">{{ UPDATES_PER_DAY }}×</strong>
                {{ t('landing.stats.updates') }}
            </li>
        </ul>

        <section class="landing-section">
            <h2 class="landing-section__title">{{ t('landing.features_title') }}</h2>
            <div class="landing-features">
                <article v-for="feature in FEATURES" :key="feature" class="card landing-feature">
                    <span class="landing-feature__icon"><FeatureIcon :name="feature" /></span>
                    <h3 class="landing-feature__title">{{ t(`landing.features.${feature}.title`) }}</h3>
                    <p class="landing-feature__text">{{ t(`landing.features.${feature}.text`) }}</p>
                </article>
            </div>
        </section>

        <section class="landing-section">
            <h2 class="landing-section__title">{{ t('landing.steps_title') }}</h2>
            <ol class="landing-steps">
                <li v-for="(step, index) in STEPS" :key="step" class="landing-steps__item">
                    <span class="landing-steps__number" aria-hidden="true">{{ index + 1 }}</span>
                    <h3 class="landing-steps__title">{{ t(`landing.steps.${step}.title`) }}</h3>
                    <p class="landing-steps__text">{{ t(`landing.steps.${step}.text`) }}</p>
                </li>
            </ol>
        </section>

        <section v-if="topOffers.length" class="landing-section">
            <div class="landing-section__header">
                <h2 class="landing-section__title">{{ t('landing.top_title') }}</h2>
                <Link :href="urls.offers" class="link">{{ t('landing.top_more') }} →</Link>
            </div>
            <div class="offer-grid">
                <OfferCard v-for="offer in topOffers" :key="offer.id" :offer="offer" />
            </div>
        </section>

        <section class="landing-cta">
            <img src="/images/brand/icon-192.png" alt="" class="landing-cta__mascot" />
            <div class="landing-cta__body">
                <h2 class="landing-cta__title">{{ t('landing.cta_title') }}</h2>
                <p class="landing-cta__text">{{ t('landing.cta_text') }}</p>
            </div>
            <Link :href="urls.register" class="button landing-cta__button">{{ t('landing.register') }}</Link>
        </section>
    </AppLayout>
</template>
