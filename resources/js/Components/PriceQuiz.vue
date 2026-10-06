<!--
    Hra „Co je levnější?“ na úvodní stránce (R90) — dvě skutečné akce stejného produktu
    z různých obchodů a návštěvník klepnutím tipne, která vyjde levněji za kilo nebo litr.
    Po tipu se ukáže cena za jednotku obou a která je levnější; na konci skóre a výzva
    k registraci. Kola připravuje server (UnitPriceQuiz), komponenta nic nepočítá.

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { packageLabel } from '@/lib/offer';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    /** Kola [{ product, offers: [a, b], cheaperId }] z UnitPriceQuiz. */
    rounds: { type: Array, required: true },
    /** Adresa registrace (výzva na konci). */
    registerUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

const index = ref(0);
/** ID tipnuté akce v aktuálním kole, null = ještě netipnuto. */
const choice = ref(null);
const score = ref(0);
const finished = ref(false);

const nextButton = ref(null);
const question = ref(null);

const round = computed(() => props.rounds[index.value]);
const unit = computed(() => t(`unit_price_units.${round.value.offers[0].unitPriceUnit}`));
const correct = computed(() => choice.value === round.value.cheaperId);
const last = computed(() => index.value === props.rounds.length - 1);

/** Hodnocení na konci podle podílu uhodnutých kol. */
const verdict = computed(() => {
    if (score.value === props.rounds.length) {
        return 'perfect';
    }

    return score.value * 2 >= props.rounds.length ? 'good' : 'poor';
});

/**
 * Cena za jednotku akce („41,58 Kč / l“).
 *
 * @param {object} offer
 * @returns {string}
 */
function unitPrice(offer) {
    return t('offers.unit_price', { price: formatPrice(offer.unitPrice, locale.value), unit: unit.value });
}

/**
 * Tip návštěvníka; po něm se fokus přesune na tlačítko dalšího kola.
 *
 * @param {object} offer
 */
async function pick(offer) {
    if (choice.value !== null) {
        return;
    }
    choice.value = offer.id;
    if (offer.id === round.value.cheaperId) {
        score.value++;
    }
    await nextTick();
    nextButton.value?.focus();
}

/** Další kolo, nebo výsledek po posledním. */
async function next() {
    if (last.value) {
        finished.value = true;
    } else {
        index.value++;
        choice.value = null;
    }
    await nextTick();
    question.value?.focus();
}

/** Hra od začátku. */
async function restart() {
    index.value = 0;
    choice.value = null;
    score.value = 0;
    finished.value = false;
    await nextTick();
    question.value?.focus();
}
</script>

<template>
    <div class="card price-quiz">
        <template v-if="!finished">
            <div class="price-quiz__head">
                <p class="price-quiz__round">{{ t('landing.quiz.round', { current: index + 1, total: rounds.length }) }}</p>
                <ol class="price-quiz__dots" aria-hidden="true">
                    <li v-for="(item, dot) in rounds" :key="dot" class="price-quiz__dot" :class="{ 'price-quiz__dot--done': dot < index || (dot === index && choice !== null), 'price-quiz__dot--current': dot === index }"></li>
                </ol>
            </div>
            <h3 ref="question" class="price-quiz__question" tabindex="-1">{{ t('landing.quiz.question', { product: round.product, unit }) }}</h3>

            <div class="price-quiz__options">
                <button
                    v-for="offer in round.offers"
                    :key="offer.id"
                    type="button"
                    class="price-quiz__option"
                    :class="{
                        'price-quiz__option--cheaper': choice !== null && offer.id === round.cheaperId,
                        'price-quiz__option--picked': offer.id === choice,
                    }"
                    :aria-pressed="offer.id === choice ? 'true' : 'false'"
                    :disabled="choice !== null"
                    @click="pick(offer)"
                >
                    <span class="price-quiz__image">
                        <img v-if="offer.imageUrl" :src="offer.imageUrl" alt="" loading="lazy" referrerpolicy="no-referrer" />
                    </span>
                    <span class="price-quiz__body">
                        <ChainLogo :chain="offer.chain" />
                        <span class="price-quiz__name">{{ offer.name }}</span>
                        <span class="price-quiz__package">{{ packageLabel(offer, locale, t) }}</span>
                        <strong class="price-quiz__price">{{ formatPrice(offer.price, locale) }}</strong>
                        <span v-if="choice !== null" class="price-quiz__unit-price">{{ unitPrice(offer) }}</span>
                        <span v-if="choice !== null && offer.id === round.cheaperId" class="tag tag--success price-quiz__badge">{{ t('landing.quiz.cheaper', { unit }) }}</span>
                    </span>
                </button>
            </div>

            <div v-if="choice !== null" class="price-quiz__feedback" :class="correct ? 'price-quiz__feedback--correct' : 'price-quiz__feedback--wrong'">
                <p class="price-quiz__verdict" role="status">{{ correct ? t('landing.quiz.correct') : t('landing.quiz.wrong') }}</p>
                <button ref="nextButton" type="button" class="button button--primary" @click="next">
                    {{ last ? t('landing.quiz.results') : t('landing.quiz.next') }}
                </button>
            </div>
        </template>

        <div v-else class="price-quiz__end">
            <p ref="question" class="price-quiz__score" tabindex="-1">{{ t('landing.quiz.score', { score, total: rounds.length }) }}</p>
            <p class="price-quiz__end-text">{{ t(`landing.quiz.verdict.${verdict}`) }}</p>
            <div class="price-quiz__end-actions">
                <Link :href="registerUrl" class="button button--primary">{{ t('landing.quiz.cta') }}</Link>
                <button type="button" class="button button--ghost" @click="restart">{{ t('landing.quiz.again') }}</button>
            </div>
        </div>
    </div>
</template>
