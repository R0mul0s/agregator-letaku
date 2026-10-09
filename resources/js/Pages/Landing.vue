<!--
    Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
    jak to funguje a skutečné akce s nejvyšší slevou. Motivy z loga: maskot, cenovka, čárky.
    Hravě (R90): hledání v hlavním pruhu, napočítávané počty, cenovka se skutečnými slevami,
    živá ukázka hlídání místo kroků a hra „Co je levnější?“.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import FeatureIcon from '@/Components/FeatureIcon.vue';
import LandingExtras from '@/Components/LandingExtras.vue';
import OfferCard from '@/Components/OfferCard.vue';
import PriceQuiz from '@/Components/PriceQuiz.vue';
import SearchSuggest from '@/Components/SearchSuggest.vue';
import WatchDemo from '@/Components/WatchDemo.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MEDIA_FROM_MD } from '@/lib/breakpoints';
import { useCountUp } from '@/lib/countUp';
import { formatDiscount, formatNumber } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { prefersReducedMotion } from '@/lib/scroll';
import { rememberSearch } from '@/lib/search';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Hlavní přednosti ve velkých kartách (lang: ui.landing.features). */
const MAIN_FEATURES = ['watch', 'unit_price', 'cards'];

/** Jak dlouho svítí jedna sleva na cenovce u maskota (ms). */
const STICKER_ROTATE_MS = 2600;

/** Kolikrát se sleva na cenovce vystřídá, než zůstane stát (~10 s, WCAG 2.2.2, R99). */
const STICKER_MAX_ROTATIONS = 4;

const props = defineProps({
    /** Adresy registrace, přihlášení, Všech akcí a našeptávače. */
    urls: { type: Object, required: true },
    /** Od kolika znaků našeptávač hledá. */
    suggestMinLength: { type: Number, required: true },
    /** Počty { offers, chains, products, topDiscount }. */
    stats: { type: Object, required: true },
    /** Obchody se zdrojem dat (hodnoty App\Enums\Chain). */
    chains: { type: Array, required: true },
    /** Adresy akcí obchodů { kaufland: '/akce/kaufland' } (R94). */
    chainUrls: { type: Object, default: () => ({}) },
    /** Akce s nejvyšší slevou z různých obchodů (OfferPresenter). */
    topOffers: { type: Array, required: true },
    /** Živá ukázka hlídání (WatchDemo). */
    demo: { type: Object, required: true },
    /** Za kolik týdnů se hlídá nejnižší cena (štítek v dlaždicích „A k tomu navíc“). */
    historyWeeks: { type: Number, required: true },
    /** Kola hry „Co je levnější?“ (UnitPriceQuiz); prázdné = hra se neukáže. */
    quiz: { type: Array, default: () => [] },
});

const t = useTranslations();
const page = usePage();

const searchText = ref('');

const statsList = ref(null);
const countedStats = useCountUp(statsList, () => [props.stats.offers, props.stats.chains, props.stats.products, props.stats.topDiscount ?? 0]);

/** Slevy z ukázky akcí pro cenovku u maskota, od nejvyšší, bez opakování. */
const stickerDiscounts = computed(() => [...new Set(props.topOffers.map(discountPercent).filter(Boolean))]);
const stickerIndex = ref(0);
let stickerTimer = null;

/**
 * Hledání z hlavního pruhu: výsledky ve Všech akcích.
 *
 * @param {string} text
 */
function search(text) {
    const query = text.trim();
    if (query !== '') {
        rememberSearch(query);
    }
    router.get(props.urls.offers, query === '' ? {} : { q: query });
}

/**
 * Produkt z našeptávače: jeho akce ve Všech akcích.
 *
 * @param {{ name: string, url: string }} product
 */
function showProduct(product) {
    rememberSearch(product.name);
    router.visit(product.url);
}

onMounted(() => {
    if (!prefersReducedMotion() && stickerDiscounts.value.length > 1) {
        let rotations = 0;
        stickerTimer = window.setInterval(() => {
            stickerIndex.value = (stickerIndex.value + 1) % stickerDiscounts.value.length;
            rotations++;
            if (rotations >= STICKER_MAX_ROTATIONS) {
                window.clearInterval(stickerTimer);
            }
        }, STICKER_ROTATE_MS);
    }
});

onBeforeUnmount(() => window.clearInterval(stickerTimer));
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <section class="landing-hero">
            <div class="landing-hero__body">
                <p class="landing-hero__eyebrow">{{ t('landing.eyebrow') }}</p>
                <h1 class="landing-hero__title">{{ t('landing.headline') }}</h1>
                <p class="landing-hero__lead">{{ t('landing.lead') }}</p>
                <SearchSuggest
                    id="landing-search"
                    v-model="searchText"
                    class="landing-hero__search"
                    :label="t('landing.search_label')"
                    :url="urls.suggestions"
                    :min-length="suggestMinLength"
                    @search="search"
                    @product="showProduct"
                />
                <div class="landing-hero__actions">
                    <Link :href="urls.register" class="button button--primary landing-hero__cta">{{ t('landing.register') }}</Link>
                    <Link :href="urls.offers" class="button button--ghost">{{ t('landing.browse') }}</Link>
                </div>
                <p class="landing-hero__login">
                    {{ t('landing.login_hint') }} <Link :href="urls.login" class="link">{{ t('landing.login') }}</Link>
                </p>
                <ul class="landing-hero__chains">
                    <li v-for="chain in chains" :key="chain">
                        <Link v-if="chainUrls[chain]" :href="chainUrls[chain]" class="landing-hero__chain-link">
                            <ChainLogo :chain="chain" large />
                        </Link>
                        <ChainLogo v-else :chain="chain" large />
                    </li>
                </ul>
            </div>
            <!-- Košík „jede“: poskakuje, za ním ubíhají čárky rychlosti (jen CSS, při omezení pohybu stojí) -->
            <div class="landing-hero__art" aria-hidden="true">
                <div class="landing-hero__circle">
                    <span class="landing-hero__streak"></span>
                    <span class="landing-hero__streak"></span>
                    <span class="landing-hero__streak"></span>
                    <div class="landing-hero__drive">
                        <!-- Velikost podle displeje: na telefonu ~96 px, od středního 208 px (kruh minus okraj) -->
                        <img
                            src="/images/brand/mascot-416.webp"
                            srcset="/images/brand/mascot-192.webp 192w, /images/brand/mascot-288.webp 288w, /images/brand/mascot-416.webp 416w"
                            :sizes="`${MEDIA_FROM_MD} 208px, 96px`"
                            width="416"
                            height="416"
                            alt=""
                            class="landing-hero__mascot"
                        />
                    </div>
                </div>
                <!-- Cenovka střídá skutečné nejvyšší slevy z ukázky akcí -->
                <span v-if="stickerDiscounts.length" class="landing-hero__sticker">
                    <Transition name="landing-sticker-swap" mode="out-in">
                        <span :key="stickerIndex" class="landing-hero__sticker-value">{{ formatDiscount(stickerDiscounts[stickerIndex]) }}</span>
                    </Transition>
                </span>
            </div>
        </section>

        <ul ref="statsList" class="landing-stats">
            <li class="landing-stats__item">
                <strong class="landing-stats__value" aria-hidden="true">{{ formatNumber(countedStats[0], page.props.locale) }}</strong>
                <span class="visually-hidden">{{ formatNumber(stats.offers, page.props.locale) }}</span>
                {{ t('landing.stats.offers', { count: stats.offers }) }}
            </li>
            <li class="landing-stats__item">
                <strong class="landing-stats__value" aria-hidden="true">{{ countedStats[1] }}</strong>
                <span class="visually-hidden">{{ stats.chains }}</span>
                {{ t('landing.stats.chains', { count: stats.chains }) }}
            </li>
            <li class="landing-stats__item">
                <strong class="landing-stats__value" aria-hidden="true">{{ formatNumber(countedStats[2], page.props.locale) }}</strong>
                <span class="visually-hidden">{{ formatNumber(stats.products, page.props.locale) }}</span>
                {{ t('landing.stats.products', { count: stats.products }) }}
            </li>
            <li v-if="stats.topDiscount" class="landing-stats__item">
                <strong class="landing-stats__value" aria-hidden="true">{{ formatDiscount(countedStats[3]) }}</strong>
                <span class="visually-hidden">{{ formatDiscount(stats.topDiscount) }}</span>
                {{ t('landing.stats.top_discount') }}
            </li>
        </ul>

        <section class="landing-section">
            <h2 class="landing-section__title">{{ t('landing.steps_title') }}</h2>
            <p class="landing-section__lead">{{ t('landing.steps_lead') }}</p>
            <WatchDemo :chains="chains" :demo="demo" :register-url="urls.register" />
        </section>

        <section class="landing-section">
            <h2 class="landing-section__title">{{ t('landing.features_title') }}</h2>
            <div class="landing-features">
                <article v-for="feature in MAIN_FEATURES" :key="feature" class="card landing-feature">
                    <span class="landing-feature__icon"><FeatureIcon :name="feature" /></span>
                    <h3 class="landing-feature__title">{{ t(`landing.features.${feature}.title`) }}</h3>
                    <p class="landing-feature__text">{{ t(`landing.features.${feature}.text`) }}</p>
                </article>
            </div>
            <h3 class="landing-section__subtitle">{{ t('landing.features_more_title') }}</h3>
            <LandingExtras :history-weeks="historyWeeks" />
        </section>

        <section v-if="quiz.length" class="landing-section">
            <h2 class="landing-section__title">{{ t('landing.quiz.title') }}</h2>
            <p class="landing-section__lead">{{ t('landing.quiz.lead') }}</p>
            <PriceQuiz :rounds="quiz" :register-url="urls.register" />
        </section>

        <section v-if="topOffers.length" class="landing-section">
            <div class="landing-section__header">
                <h2 class="landing-section__title">{{ t('landing.top_title') }}</h2>
                <Link :href="urls.offers" class="link">{{ t('landing.top_more') }} →</Link>
            </div>
            <div class="offer-grid">
                <OfferCard v-for="offer in topOffers" :key="offer.id" :offer="offer" :heading-level="3" />
            </div>
        </section>

        <section class="landing-cta">
            <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="landing-cta__mascot" />
            <div class="landing-cta__body">
                <h2 class="landing-cta__title">{{ t('landing.cta_title') }}</h2>
                <p class="landing-cta__text">{{ t('landing.cta_text') }}</p>
            </div>
            <Link :href="urls.register" class="button landing-cta__button">{{ t('landing.register') }}</Link>
        </section>
    </AppLayout>
</template>
