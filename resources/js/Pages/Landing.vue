<!--
    Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
    jak to funguje a skutečné akce s nejvyšší slevou. Hravě (R90): hledání v hlavním pruhu,
    živá ukázka hlídání a hra „Co je levnější?“. Vzhled R137: červený pruh s jednou hlavní akcí
    (hledání), maskotem a dvěma skutečnými akcemi, přes jeho oblý konec lišta obchodů; oddíly
    se střídají na podkladu (akce, růžový pruh kroků s vodoznakem maskota, výhody s vykukujícím
    maskotem, tmavý kvíz, červená výzva).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import FeatureIcon from '@/Components/FeatureIcon.vue';
import MascotHunt from '@/Components/MascotHunt.vue';
import OfferCard from '@/Components/OfferCard.vue';
import PriceQuiz from '@/Components/PriceQuiz.vue';
import SearchSuggest from '@/Components/SearchSuggest.vue';
import WatchDemo from '@/Components/WatchDemo.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MEDIA_FROM_MD } from '@/lib/breakpoints';
import { formatDiscount, formatNumber, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { rememberSearch } from '@/lib/search';
import { updatedLabel } from '@/lib/updated';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Hlavní přednosti s ukázkou nahoře (lang: ui.landing.features). */
const MAIN_FEATURES = ['watch', 'unit_price', 'cards'];

/** Další přednosti jako seznam s ikonou (lang: ui.landing.features). */
const MORE_FEATURES = ['upcoming', 'history', 'shopping', 'notify', 'mentions', 'free'];

/** Kolik akcí visí u maskota v hlavním pruhu — z různých obchodů. */
const HERO_TICKETS = 2;

/** Kolik oblíbených produktů nabídnout pod hledáním. */
const POPULAR_SEARCHES = 4;

/** Kolik hlídaných produktů ukázat nad výhodou „Hlídá, co kupujete“. */
const WATCH_PREVIEW_ITEMS = 3;

const props = defineProps({
    /** Adresy registrace, přihlášení, Všech akcí, Nejlepších slev týdne a našeptávače. */
    urls: { type: Object, required: true },
    /** Od kolika znaků našeptávač hledá. */
    suggestMinLength: { type: Number, required: true },
    /** Počty { offers, chains, products, topDiscount, updatedAt } — updatedAt = konec posledního stažení. */
    stats: { type: Object, required: true },
    /** Obchody se zdrojem dat (hodnoty App\Enums\Chain). */
    chains: { type: Array, required: true },
    /** Adresy akcí obchodů { kaufland: '/akce/kaufland' } (R94). */
    chainUrls: { type: Object, default: () => ({}) },
    /** Akce s nejvyšší slevou z různých obchodů (OfferPresenter). */
    topOffers: { type: Array, required: true },
    /** Živá ukázka hlídání (WatchDemo); products mají i počet akcí. */
    demo: { type: Object, required: true },
    /** Kola hry „Co je levnější?“ (UnitPriceQuiz); prázdné = hra se neukáže. */
    quiz: { type: Array, default: () => [] },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

const searchText = ref('');

/** Akce u maskota: první s nejvyšší slevou z různých obchodů. */
const heroOffers = computed(() => {
    const picked = [];
    for (const offer of props.topOffers) {
        if (!picked.some((item) => item.chain === offer.chain)) {
            picked.push(offer);
        }
        if (picked.length === HERO_TICKETS) {
            break;
        }
    }

    return picked;
});

/** Oblíbené hledání pod polem — produkty s nejvíc akcemi. */
const popular = computed(() => props.demo.products.slice(0, POPULAR_SEARCHES));

/** „Aktualizováno dnes v 6:30“ podle konce posledního stažení, bez stažení nic. */
const updated = computed(() => updatedLabel(props.stats.updatedAt, locale.value, page.props.timezone, t));

/**
 * Cena akce, kterou zaplatí každý (u akce jen s kartou cena s kartou).
 *
 * @param {object} offer
 * @returns {string}
 */
function offerPrice(offer) {
    return formatPrice(offer.price ?? offer.loyaltyPrice, locale.value);
}

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
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <section class="landing-hero">
            <div class="page__wrap landing-hero__grid">
                <div class="landing-hero__body">
                    <p class="landing-hero__live">
                        {{ t('landing.live', { count: stats.offers, number: formatNumber(stats.offers, locale), chains: stats.chains }) }}
                    </p>
                    <h1 class="landing-hero__title">
                        {{ t('landing.headline_start') }} <em class="landing-hero__highlight">{{ t('landing.headline_highlight') }}</em>
                    </h1>
                    <p class="landing-hero__lead">{{ t('landing.lead') }}</p>
                    <SearchSuggest
                        id="landing-search"
                        v-model="searchText"
                        class="landing-hero__search"
                        :label="t('landing.search_label')"
                        :url="urls.suggestions"
                        :min-length="suggestMinLength"
                        :submit-label="t('landing.search_submit')"
                        hide-label
                        on-hero
                        @search="search"
                        @product="showProduct"
                    />
                    <p v-if="popular.length" class="landing-hero__popular">
                        {{ t('landing.popular') }}
                        <button v-for="product in popular" :key="product.id" type="button" class="landing-hero__chip" @click="search(product.name)">
                            {{ product.name }}
                        </button>
                    </p>
                    <div class="landing-hero__actions">
                        <Link :href="urls.register" class="button button--highlight landing-hero__cta">{{ t('landing.register') }}</Link>
                        <Link :href="urls.offers" class="landing-hero__browse">
                            <span class="landing-hero__browse-prefix">{{ t('landing.browse_prefix') }}</span> {{ t('landing.browse_all') }}
                        </Link>
                    </div>
                </div>

                <!-- Maskot ve světlém kruhu a dvě skutečné akce u okraje (dekorace — akce jsou i níž) -->
                <div class="landing-hero__art" aria-hidden="true">
                    <div class="landing-hero__circle">
                        <!-- Velikost podle displeje: na telefonu ~160 px, od středního ~280 px -->
                        <img
                            src="/images/brand/mascot-416.webp"
                            srcset="/images/brand/mascot-192.webp 192w, /images/brand/mascot-288.webp 288w, /images/brand/mascot-416.webp 416w"
                            :sizes="`${MEDIA_FROM_MD} 284px, 160px`"
                            width="416"
                            height="416"
                            alt=""
                            class="landing-hero__mascot"
                        />
                    </div>
                    <div v-for="(offer, index) in heroOffers" :key="offer.id" class="landing-ticket" :class="`landing-ticket--${index + 1}`">
                        <span class="landing-ticket__image">
                            <img v-if="offer.imageUrl" :src="offer.imageUrl" alt="" referrerpolicy="no-referrer" />
                        </span>
                        <span class="landing-ticket__body">
                            <span class="landing-ticket__name">{{ offer.name }} · {{ offer.chainName }}</span>
                            <span class="landing-ticket__price">
                                {{ offerPrice(offer) }}
                                <s v-if="offer.originalPrice">{{ formatPrice(offer.originalPrice, locale) }}</s>
                            </span>
                        </span>
                        <span v-if="discountPercent(offer)" class="landing-ticket__sticker">{{ formatDiscount(discountPercent(offer)) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Obchody a čerstvost dat v liště přes oblý konec pruhu -->
        <div class="landing-trust">
            <div class="page__wrap">
                <div class="landing-trust__card">
                    <div class="landing-trust__chains">
                        <span class="landing-trust__label">{{ t('landing.chains_label') }}</span>
                        <ul class="landing-trust__logos">
                            <li v-for="chain in chains" :key="chain">
                                <Link v-if="chainUrls[chain]" :href="chainUrls[chain]" class="landing-trust__logo">
                                    <ChainLogo :chain="chain" large />
                                </Link>
                                <ChainLogo v-else :chain="chain" large />
                            </li>
                        </ul>
                    </div>
                    <p v-if="updated" class="landing-trust__fresh">{{ updated }}</p>
                </div>
            </div>
        </div>

        <section v-if="topOffers.length" class="landing-section">
            <div class="page__wrap">
                <div class="landing-section__header">
                    <div>
                        <p class="landing-section__eyebrow">{{ t('landing.top_eyebrow') }}</p>
                        <h2 class="landing-section__title">{{ t('landing.top_title') }}</h2>
                    </div>
                    <span class="landing-section__links">
                        <!-- Nejlepší slevy týdne (R128) -->
                        <Link :href="urls.weekly" class="link">{{ t('landing.top_weekly') }} →</Link>
                        <Link :href="urls.offers" class="link">{{ t('landing.top_more') }} →</Link>
                    </span>
                </div>
                <div class="offer-grid landing-offers">
                    <OfferCard v-for="offer in topOffers" :key="offer.id" :offer="offer" :heading-level="3" />
                </div>
            </div>
        </section>

        <!-- Růžový pruh s oblými okraji a vodoznakem maskota — rozbije plochu stejného podkladu -->
        <section class="landing-section landing-band">
            <img src="/images/brand/mascot-416.webp" width="416" height="416" alt="" class="landing-band__watermark" loading="lazy" />
            <div class="page__wrap">
                <div class="landing-section__header">
                    <div>
                        <p class="landing-section__eyebrow">{{ t('landing.steps_eyebrow') }}</p>
                        <h2 class="landing-section__title">{{ t('landing.steps_title') }}</h2>
                        <p class="landing-section__lead">{{ t('landing.steps_lead') }}</p>
                    </div>
                </div>
                <WatchDemo :chains="chains" :demo="demo" :register-url="urls.register" />
            </div>
        </section>

        <section class="landing-section landing-section--peek">
            <MascotHunt :offers="topOffers" />
            <div class="page__wrap">
                <div class="landing-section__header">
                    <div>
                        <p class="landing-section__eyebrow">{{ t('landing.features_eyebrow') }}</p>
                        <h2 class="landing-section__title">{{ t('landing.features_title') }}</h2>
                    </div>
                </div>
                <div class="landing-features">
                    <article v-for="feature in MAIN_FEATURES" :key="feature" class="landing-feature">
                        <!-- Ukázka funkce: hlídané produkty skutečné, ceny ilustrace z překladů -->
                        <div class="landing-feature__preview" aria-hidden="true">
                            <template v-if="feature === 'watch'">
                                <span v-for="product in demo.products.slice(0, WATCH_PREVIEW_ITEMS)" :key="product.id" class="landing-preview-row">
                                    {{ product.name }}
                                    <span class="landing-preview-row__badge">{{ t('landing.preview.watch_count', { count: product.offersCount }) }}</span>
                                </span>
                            </template>
                            <template v-else-if="feature === 'unit_price'">
                                <span class="landing-preview-row landing-preview-row--worse">
                                    <span>
                                        {{ t('landing.preview.unit_price.promo.name') }}
                                        <small>{{ t('landing.preview.unit_price.promo.price') }}</small>
                                    </span>
                                    <b>{{ t('landing.preview.unit_price.promo.unit') }}</b>
                                </span>
                                <span class="landing-preview-row landing-preview-row--better">
                                    <span>
                                        {{ t('landing.preview.unit_price.regular.name') }}
                                        <small>{{ t('landing.preview.unit_price.regular.price') }}</small>
                                    </span>
                                    <b>{{ t('landing.preview.unit_price.regular.unit') }}</b>
                                </span>
                            </template>
                            <span v-else class="landing-preview-prices">
                                <span class="landing-preview-price">
                                    {{ t('landing.preview.cards.regular_label') }}
                                    <b>{{ t('landing.preview.cards.regular_price') }}</b>
                                </span>
                                <span class="landing-preview-price landing-preview-price--card">
                                    {{ t('landing.preview.cards.card_label') }}
                                    <b>{{ t('landing.preview.cards.card_price') }}</b>
                                </span>
                            </span>
                        </div>
                        <div class="landing-feature__body">
                            <h3 class="landing-feature__title">{{ t(`landing.features.${feature}.title`) }}</h3>
                            <p class="landing-feature__text">{{ t(`landing.features.${feature}.text`) }}</p>
                        </div>
                    </article>
                </div>
                <ul class="landing-more">
                    <li v-for="feature in MORE_FEATURES" :key="feature" class="landing-more__item">
                        <span class="landing-more__icon"><FeatureIcon :name="feature" /></span>
                        <span>
                            <strong class="landing-more__title">{{ t(`landing.features.${feature}.title`) }}</strong>
                            <span class="landing-more__text">{{ t(`landing.features.${feature}.text`) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Kvíz na tmavém pruhu — tmavá paleta tokenů i ve světlém režimu -->
        <section v-if="quiz.length" class="landing-section landing-quiz">
            <div class="page__wrap">
                <div class="landing-section__header">
                    <div>
                        <p class="landing-section__eyebrow">{{ t('landing.quiz.eyebrow') }}</p>
                        <h2 class="landing-section__title">{{ t('landing.quiz.title') }}</h2>
                        <p class="landing-section__lead">{{ t('landing.quiz.lead') }}</p>
                    </div>
                </div>
                <PriceQuiz :rounds="quiz" :register-url="urls.register" />
            </div>
        </section>

        <section class="landing-section">
            <div class="page__wrap">
                <div class="landing-cta">
                    <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="landing-cta__mascot" loading="lazy" />
                    <div>
                        <h2 class="landing-cta__title">{{ t('landing.cta_title') }}</h2>
                        <p class="landing-cta__text">{{ t('landing.cta_text') }}</p>
                    </div>
                    <Link :href="urls.register" class="button button--highlight landing-cta__button">{{ t('landing.register') }}</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
