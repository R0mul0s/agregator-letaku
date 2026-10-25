<!--
    Nejlepší slevy týdne (R128) — veřejná stránka jednoho ISO týdne (po–ne): žebříček akcí
    s nejvyšší slevou napříč obchody, pod ním nejlepší slevy každého obchodu, přechod na
    sousední týdny a archiv. Týden, který skončil, nabídne přechod na aktuální týden. Odkaz
    na týden jde sdílet (sdílení systému, jinak zkopírování). Data posílá WeeklyDealsController.
    Vzhled R143 (jako Všechny akce): červený pruh s týdnem, přepínáním týdnů, sdílením a stupni
    vítězů (první tři slevy), karta se zkratkami na obchody, žebříček s pořadím a výzvou pro
    nepřihlášené, obchody jako řady menších dlaždic na růžovém pruhu, archiv jako dlaždice.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
// Styly jen této stránky — Vite je dá do CSS stránky, ne do společného app.css (R170)
import '../../scss/pages/_weekly.scss';
import ChainLogo from '@/Components/ChainLogo.vue';
import EmptyState from '@/Components/EmptyState.vue';
import GuestOffersCard from '@/Components/GuestOffersCard.vue';
import InstallPrompt from '@/Components/InstallPrompt.vue';
import OfferCard from '@/Components/OfferCard.vue';
import ScrollRow from '@/Components/ScrollRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/lib/clipboard';
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { canSystemShare, SHARE_CONTENT, trackShare } from '@/lib/share';
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

/** Kolik akcí žebříčku stojí na stupních vítězů v pruhu (a má žlutý okraj v žebříčku). */
const PODIUM_SIZE = 3;

/** Po kolika akcích žebříčku je karta pro nepřihlášené — celé řádky při 1, 2 i 3 sloupcích. */
const GUEST_CARD_AFTER = 6;

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Stupně vítězů: pořadí 2, 1, 3 zleva — první uprostřed. */
const podium = computed(() => {
    const [first, second, third] = props.top.slice(0, PODIUM_SIZE);

    return [
        second && { offer: second, rank: 2 },
        first && { offer: first, rank: 1 },
        third && { offer: third, rank: 3 },
    ].filter(Boolean);
});

/** Archiv týdnů: zobrazený týden a jeho sousedé (pro popisky „← 41. týden“). */
const activeIndex = computed(() => props.archive.findIndex((item) => item.active));
const olderWeek = computed(() => (activeIndex.value < 0 ? null : (props.archive[activeIndex.value + 1] ?? null)));
const newerWeek = computed(() => (activeIndex.value > 0 ? props.archive[activeIndex.value - 1] : null));

/**
 * Nejvyšší sleva obchodu (zkratka „Penny až −70 %“), nebo null.
 *
 * @param {{ offers: object[] }} section
 * @returns {number|null}
 */
function bestDiscount(section) {
    const discounts = section.offers.map(discountPercent).filter(Boolean);

    return discounts.length ? Math.max(...discounts) : null;
}

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
 * Kotva oddílu obchodu — zkratky v kartě pod pruhem na něj sjedou.
 *
 * @param {string} chain
 * @returns {string}
 */
function chainAnchor(chain) {
    return `obchod-${chain}`;
}

/** Na stránce není žádná sleva (aktuální týden před prvním stažením). */
const isEmpty = computed(() => !props.top.length && !props.chainSections.length);

/** Aktuální týden je v archivu první. */
const currentWeekUrl = computed(() => props.archive[0]?.url ?? null);

/** Pošle odkaz na týden sdílením systému (chat, sociální sítě); na počítači ho zkopíruje. */
async function share() {
    const text = t('weekly.share_text', { range: props.week.range });
    if (canSystemShare()) {
        try {
            await navigator.share({ title: props.heading, text, url: props.shareUrl });
            trackShare(SHARE_CONTENT.WEEKLY, false);
        } catch {
            // Uživatel sdílení zavřel
        }

        return;
    }

    if (await copyText(props.shareUrl, t('weekly.share_copied'), t('weekly.share_failed'))) {
        trackShare(SHARE_CONTENT.WEEKLY, true);
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <!-- Červený pruh (R143): týden, přepínání týdnů, sdílení, stupně vítězů -->
        <section class="offers-hero weekly-hero">
            <div class="page__wrap offers-hero__grid">
                <div class="offers-hero__body">
                    <p class="weekly-hero__eyebrow">
                        {{ t('weekly.eyebrow', { number: week.number, year: week.year }) }}
                        <template v-if="week.current"> · {{ t('weekly.current') }}</template>
                    </p>
                    <h1 class="offers-hero__title">
                        {{ t('weekly.hero_title') }}
                        <span class="weekly-hero__range">{{ week.range }}</span>
                    </h1>
                    <p class="offers-hero__lead">{{ week.current ? t('weekly.intro_current') : t('weekly.intro_past') }}</p>
                    <div class="weekly-hero__actions">
                        <nav class="weekly-switch" :aria-label="t('weekly.pager_label')">
                            <Link v-if="olderUrl" :href="olderUrl" class="weekly-switch__item">
                                ← {{ olderWeek ? t('weekly.week_label', { number: olderWeek.number }) : t('weekly.older') }}
                            </Link>
                            <span class="weekly-switch__item weekly-switch__item--current" aria-current="page">{{ t('weekly.week_label', { number: week.number }) }}</span>
                            <Link v-if="newerUrl" :href="newerUrl" class="weekly-switch__item">
                                {{ newerWeek ? t('weekly.week_label', { number: newerWeek.number }) : t('weekly.newer') }} →
                            </Link>
                            <span v-else class="weekly-switch__item weekly-switch__item--disabled" aria-hidden="true">{{ t('weekly.newer') }} →</span>
                        </nav>
                        <Link v-if="!week.current && currentWeekUrl" :href="currentWeekUrl" class="button button--highlight">{{ t('weekly.to_current') }}</Link>
                        <button type="button" class="weekly-hero__share" @click="share">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4M5 13v7h14v-7" /></svg>
                            {{ t('weekly.share') }}
                        </button>
                    </div>
                </div>

                <!-- Stupně vítězů — první tři slevy, na širokém displeji (níž jsou v žebříčku) -->
                <div v-if="podium.length" class="weekly-podium" :aria-label="t('weekly.podium_label')" role="group">
                    <a
                        v-for="item in podium"
                        :key="item.offer.id"
                        :href="`#poradi-${item.rank}`"
                        class="weekly-podium__ticket"
                        :class="`weekly-podium__ticket--${item.rank}`"
                    >
                        <span class="weekly-podium__rank">{{ t('weekly.rank', { rank: item.rank }) }}</span>
                        <span class="weekly-podium__image" :class="{ 'weekly-podium__image--none': !item.offer.imageUrl }">
                            <img v-if="item.offer.imageUrl" :src="item.offer.imageUrl" alt="" referrerpolicy="no-referrer" />
                            <img v-else src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="weekly-podium__placeholder" />
                        </span>
                        <span class="weekly-podium__name">{{ item.offer.name }}</span>
                        <span class="weekly-podium__price">{{ offerPrice(item.offer) }}</span>
                        <span v-if="discountPercent(item.offer)" class="weekly-podium__sticker">{{ formatDiscount(discountPercent(item.offer)) }}</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Karta přes oblý konec: zkratky na obchody s nejvyšší slevou -->
        <div class="page__wrap offers-toolbar">
            <div v-if="chainSections.length" class="offers-toolbar__card weekly-jump">
                <span class="weekly-jump__label">{{ t('weekly.jump_label') }}</span>
                <ScrollRow class="weekly-jump__row">
                    <a v-for="section in chainSections" :key="section.chain" :href="`#${chainAnchor(section.chain)}`" class="weekly-jump__item">
                        <ChainLogo :chain="section.chain" with-name />
                        <small v-if="bestDiscount(section)" class="weekly-jump__discount">{{ t('weekly.jump_up_to', { discount: formatDiscount(bestDiscount(section)) }) }}</small>
                    </a>
                </ScrollRow>
            </div>
            <!-- Výzva k přidání na plochu (R139) pod kartou, ne nad červeným pruhem -->
            <InstallPrompt inline class="offers-toolbar__install" />
        </div>

        <div v-if="isEmpty" class="page__wrap weekly-empty">
            <EmptyState :text="t('weekly.empty')" />
        </div>

        <!-- Žebříček s pořadím; nepřihlášený má po šesti akcích výzvu k registraci -->
        <section v-if="top.length" class="page__wrap landing-section weekly-section">
            <div class="landing-section__header">
                <div>
                    <p class="landing-section__eyebrow">{{ t('weekly.ranking_eyebrow') }}</p>
                    <h2 class="landing-section__title">{{ t('weekly.top_title') }}</h2>
                </div>
                <p class="landing-section__lead">{{ t('weekly.ranking_lead', { count: top.length }) }}</p>
            </div>
            <div class="offer-grid weekly-ranking">
                <template v-for="(offer, index) in top" :key="offer.id">
                    <div :id="`poradi-${index + 1}`" class="weekly-ranking__item" :class="{ 'weekly-ranking__item--podium': index < PODIUM_SIZE }">
                        <span class="weekly-ranking__rank">{{ t('weekly.rank', { rank: index + 1 }) }}</span>
                        <OfferCard :offer="offer" :heading-level="3" />
                    </div>
                    <GuestOffersCard v-if="index === GUEST_CARD_AFTER - 1" />
                </template>
            </div>
        </section>

        <!-- Nejlepší slevy obchodů na růžovém pruhu — řady menších dlaždic -->
        <section v-if="chainSections.length" class="landing-band weekly-chains">
            <div class="page__wrap">
                <div class="landing-section__header">
                    <div>
                        <p class="landing-section__eyebrow">{{ t('weekly.chains_eyebrow') }}</p>
                        <h2 class="landing-section__title">{{ t('weekly.chains_title') }}</h2>
                    </div>
                </div>
                <article v-for="section in chainSections" :id="chainAnchor(section.chain)" :key="section.chain" class="weekly-chain">
                    <div class="weekly-chain__header">
                        <h3 class="weekly-chain__title">
                            <!-- Logo je jen ozdoba, název obchodu je v textu nadpisu -->
                            <span class="weekly-chain__logo" aria-hidden="true"><ChainLogo :chain="section.chain" large /></span>
                            {{ t('weekly.chain_title', { chain: page.props.chainInfo[section.chain]?.name ?? section.chain }) }}
                        </h3>
                        <Link :href="section.url" class="link">{{ t('weekly.chain_more', { chain: section.genitive }) }} →</Link>
                    </div>
                    <ScrollRow class="weekly-chain__row">
                        <component
                            :is="offer.sourceUrl ? 'a' : 'div'"
                            v-for="offer in section.offers"
                            :key="offer.id"
                            :href="offer.sourceUrl ?? undefined"
                            :target="offer.sourceUrl ? '_blank' : undefined"
                            :rel="offer.sourceUrl ? 'noopener noreferrer' : undefined"
                            class="weekly-mini"
                        >
                            <span class="weekly-mini__image" :class="{ 'weekly-mini__image--none': !offer.imageUrl }">
                                <img v-if="offer.imageUrl" :src="offer.imageUrl" alt="" loading="lazy" referrerpolicy="no-referrer" />
                                <img v-else src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="weekly-mini__placeholder" loading="lazy" />
                            </span>
                            <span v-if="discountPercent(offer)" class="weekly-mini__sticker">{{ formatDiscount(discountPercent(offer)) }}</span>
                            <span class="weekly-mini__name">{{ offer.name }}</span>
                            <span class="weekly-mini__price">
                                {{ offerPrice(offer) }}
                                <s v-if="offer.originalPrice">{{ formatPrice(offer.originalPrice, locale) }}</s>
                            </span>
                        </component>
                    </ScrollRow>
                </article>
            </div>
        </section>

        <!-- Archiv týdnů jako dlaždice -->
        <section v-if="archive.length > 1" class="page__wrap landing-section weekly-section weekly-section--archive">
            <div class="landing-section__header">
                <div>
                    <p class="landing-section__eyebrow">{{ t('weekly.archive_eyebrow') }}</p>
                    <h2 class="landing-section__title">{{ t('weekly.archive_title') }}</h2>
                </div>
            </div>
            <ul class="weekly-archive">
                <li v-for="item in archive" :key="item.slug">
                    <Link :href="item.url" class="weekly-archive__tile" :class="{ 'weekly-archive__tile--active': item.active }" :aria-current="item.active ? 'page' : undefined">
                        <strong class="weekly-archive__week">{{ t('weekly.week_label', { number: item.number }) }}</strong>
                        <span class="weekly-archive__range">{{ item.range }}</span>
                        <span v-if="item === archive[0]" class="weekly-archive__badge">{{ t('weekly.current') }}</span>
                        <span v-else class="weekly-archive__open">{{ t('weekly.archive_open') }}</span>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
