<!--
    Dlaždice „A k tomu navíc“ na úvodní stránce (R90) — každá další přednost má nahoře miniaturu
    z rozhraní (štítek akce, která ještě nezačala, nejnižší cena, nákupní seznam k odškrtnutí,
    upozornění v telefonu, zmínka na stránce letáku, přeškrtnutá reklama). Miniatury jsou
    ilustrace z překladů, ne skutečné akce.

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import FeatureIcon from '@/Components/FeatureIcon.vue';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Přednosti v pořadí zobrazení (lang: ui.landing.features a ui.landing.extras). */
const EXTRAS = ['upcoming', 'history', 'shopping', 'notify', 'mentions', 'free'];

/** Body klesající křivky ceny v miniatuře „Je to opravdu sleva?“ (souřadnice viewBox 0 0 120 48). */
const HISTORY_POINTS = '4,14 24,10 44,20 64,12 84,24 104,18 116,40';

defineProps({
    /** Za kolik týdnů se hlídá nejnižší cena (letaky.price_history.weeks, R59). */
    historyWeeks: { type: Number, required: true },
});

const t = useTranslations();
const page = usePage();

/** Položky ukázkového nákupního seznamu (pole v překladech). */
const shoppingItems = computed(() => page.props.translations?.landing?.extras?.shopping?.items ?? []);

/** Odškrtnuté položky ukázkového seznamu — na začátku první. */
const checked = ref([0]);

/**
 * Odškrtne položku ukázkového seznamu, nebo odškrtnutí zruší.
 *
 * @param {number} index
 */
function toggleItem(index) {
    checked.value = checked.value.includes(index) ? checked.value.filter((item) => item !== index) : [...checked.value, index];
}
</script>

<template>
    <ul class="landing-extras">
        <li v-for="extra in EXTRAS" :key="extra" class="card landing-extra" :class="`landing-extra--${extra}`">
            <div class="landing-extra__visual" :aria-hidden="extra === 'shopping' ? undefined : 'true'">
                <!-- Akce, která ještě nezačala, se štítkem a radou počkat (R76) -->
                <div v-if="extra === 'upcoming'" class="landing-extra__offer">
                    <span class="landing-extra__offer-name">{{ t('landing.extras.upcoming.item') }}</span>
                    <span class="landing-extra__tags">
                        <span class="tag tag--upcoming">{{ t('landing.extras.upcoming.tag') }}</span>
                        <span class="tag tag--success">{{ t('landing.extras.upcoming.tip') }}</span>
                    </span>
                </div>

                <!-- Křivka ceny dřívějších akcí končící nejnižší cenou (R59) -->
                <div v-else-if="extra === 'history'" class="landing-extra__history">
                    <svg class="landing-extra__chart" viewBox="0 0 120 48">
                        <polyline :points="HISTORY_POINTS" />
                        <circle cx="116" cy="40" r="4" />
                    </svg>
                    <span class="tag tag--success">{{ t('offers.history.lowest', { weeks: historyWeeks }) }}</span>
                </div>

                <!-- Nákupní seznam, který jde opravdu odškrtávat -->
                <div v-else-if="extra === 'shopping'" class="landing-extra__list">
                    <button
                        v-for="(item, index) in shoppingItems"
                        :key="item"
                        type="button"
                        class="landing-extra__check"
                        :aria-pressed="checked.includes(index) ? 'true' : 'false'"
                        @click="toggleItem(index)"
                    >
                        <span class="landing-extra__box" aria-hidden="true">✓</span>
                        {{ item }}
                    </button>
                    <span class="landing-extra__hint" aria-hidden="true">{{ t('landing.extras.shopping.hint') }}</span>
                </div>

                <!-- Upozornění v telefonu (R66) -->
                <div v-else-if="extra === 'notify'" class="landing-extra__push">
                    <img src="/images/brand/logo-mark-128.webp" width="128" height="128" alt="" class="landing-extra__push-icon" />
                    <span class="landing-extra__push-body">
                        <span class="landing-extra__push-head">
                            <strong>{{ t('landing.extras.notify.app') }}</strong>
                            <span>{{ t('landing.extras.notify.time') }}</span>
                        </span>
                        <span>{{ t('landing.extras.notify.text') }}</span>
                    </span>
                </div>

                <!-- Stránka letáku se zvýrazněnou zmínkou (R27) -->
                <div v-else-if="extra === 'mentions'" class="landing-extra__leaflet">
                    <span class="landing-extra__leaflet-line"></span>
                    <span class="landing-extra__leaflet-text">
                        {{ t('landing.extras.mentions.before') }}
                        <mark class="landing-extra__mark">{{ t('landing.extras.mentions.word') }}</mark>
                        {{ t('landing.extras.mentions.after') }}
                    </span>
                    <span class="landing-extra__leaflet-link">{{ t('landing.extras.mentions.link') }}</span>
                </div>

                <!-- Přeškrtnutá reklama a cenovka „0 Kč“ -->
                <div v-else class="landing-extra__free">
                    <span class="landing-extra__banner">{{ t('landing.extras.free.banner') }}</span>
                    <span class="landing-extra__price-tag">{{ t('landing.extras.free.price') }}</span>
                </div>
            </div>

            <div class="landing-extra__body">
                <h4 class="landing-extra__title">
                    <span class="landing-extra__icon"><FeatureIcon :name="extra" /></span>
                    {{ t(`landing.features.${extra}.title`) }}
                </h4>
                <p class="landing-extra__text">{{ t(`landing.features.${extra}.text`) }}</p>
            </div>
        </li>
    </ul>
</template>
