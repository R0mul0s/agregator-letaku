<!--
    Průvodce prvním nastavením nového účtu `/vitejte` (R153): červený pruh s postupem a nadpisem
    kroku, karta kroku přes oblý konec (1 co kupuje, 2 obchody, 3 karty, 4 upozornění) a přilepená
    spodní lišta s počítadlem akcí, Zpět a Pokračovat. Po dokončení obrazovka Hotovo se shrnutím.
    Hlavička bez navigace a spodní lišty záložek, jen Přeskočit (na Moje slevy, kde pak čeká
    karta „Dokončete nastavení“). Krok je v adrese (`?krok=2`).

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import OnboardingChains from '@/Components/OnboardingChains.vue';
import OnboardingNotify from '@/Components/OnboardingNotify.vue';
import OnboardingProducts from '@/Components/OnboardingProducts.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Kroky průvodce v pořadí (klíče textů onboarding.steps). */
const STEP_KEYS = ['items', 'chains', 'cards', 'notifications'];

const props = defineProps({
    /** Aktuální krok z adresy ('1'–'4', 'hotovo'). */
    step: { type: String, required: true },
    /** Kroky v adrese v pořadí; poslední je Hotovo. */
    steps: { type: Array, required: true },
    stepParameter: { type: String, required: true },
    /** Křestní jméno v 5. pádě („Romane“), prázdné = bez oslovení. */
    greetingName: { type: String, required: true },
    urls: { type: Object, required: true },
    /** Od kolika položek průvodce nepobízí, ať vybere víc. */
    minItems: { type: Number, required: true },
    watchItems: { type: Array, required: true },
    products: { type: Array, required: true },
    featured: { type: Array, required: true },
    chains: { type: Array, required: true },
    loyaltyPrograms: { type: Array, required: true },
    /** Kolik akcí s nastavením teď v Mých slevách uvidí. */
    offersCount: { type: Number, required: true },
    digest: { type: Object, required: true },
    emailVerified: { type: Boolean, required: true },
    email: { type: String, required: true },
    push: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();

/** Index kroku 0–3; Hotovo = počet kroků. */
const current = computed(() => props.steps.indexOf(props.step));
const isDone = computed(() => current.value >= STEP_KEYS.length);
const stepKey = computed(() => STEP_KEYS[current.value] ?? null);
const isLastStep = computed(() => current.value === STEP_KEYS.length - 1);

/** Nadpis a věta kroku v pruhu. */
const heading = computed(
    () =>
        ({
            items: { title: t('onboarding.items_title'), lead: t('onboarding.items_lead') },
            chains: { title: t('onboarding.chains_title'), lead: t('onboarding.chains_lead') },
            cards: { title: t('onboarding.cards_title'), lead: t('onboarding.cards_lead') },
            notifications: { title: t('onboarding.notify_title'), lead: t('onboarding.notify_lead') },
        })[stepKey.value] ?? null,
);

const followedChains = computed(() => props.chains.filter((chain) => chain.followed));

/**
 * Adresa kroku podle indexu.
 *
 * @param {number} index
 * @returns {string}
 */
function stepUrl(index) {
    return `${props.urls.step}?${new URLSearchParams({ [props.stepParameter]: props.steps[index] })}`;
}

/** Počítadlo dole: kolik akcí uvidí, nebo výzva vybrat víc. */
const countText = computed(() => (props.watchItems.length ? t('onboarding.count', { count: props.offersCount, number: formatNumber(props.offersCount, page.props.locale) }) : t('onboarding.count_none')));
const countHint = computed(() =>
    props.watchItems.length < props.minItems && stepKey.value === 'items'
        ? t('onboarding.count_pick_more', { count: props.minItems })
        : `${t('onboarding.count_items', { count: props.watchItems.length })} ${t('onboarding.count_chains', { count: followedChains.value.length })}`,
);

const finishing = ref(false);

/** Dokončí průvodce — server zapíše, že je za uživatelem, a ukáže Hotovo. */
function finish() {
    finishing.value = true;
    router.post(props.urls.finish, {}, { onFinish: () => (finishing.value = false) });
}

/** Shrnutí na obrazovce Hotovo: co nastavil v každém kroku. */
const summary = computed(() => {
    const cards = props.chains.filter((chain) => chain.loyaltyProgram && props.loyaltyPrograms.includes(chain.loyaltyProgram)).map((chain) => chain.loyaltyProgramName);
    const digestOption = props.digest.options.find((option) => option.value === props.digest.frequency);
    const notify = [
        props.digest.frequency !== 'off' && digestOption ? t('onboarding.done_notify_digest', { frequency: digestOption.label.toLocaleLowerCase(page.props.locale) }) : null,
        props.push?.devices.length ? t('onboarding.done_notify_push') : null,
    ].filter(Boolean);

    return {
        items: t('onboarding.count_items', { count: props.watchItems.length }),
        chains: followedChains.value.length === props.chains.length ? t('onboarding.done_chains_all', { count: props.chains.length }) : String(followedChains.value.length),
        cards: cards.length ? cards.join(', ') : t('onboarding.done_cards_none'),
        notifications: notify.length ? notify.join(', ') : t('onboarding.done_notify_off'),
    };
});
</script>

<template>
    <AppLayout focus>
        <Head :title="t('onboarding.title')" />

        <template #header-actions>
            <Link v-if="!isDone" :href="urls.home" class="onboarding-skip">{{ t('onboarding.skip') }}</Link>
        </template>

        <template v-if="!isDone">
            <header class="page__header page-hero onboarding-hero">
                <p v-if="stepKey === 'items'" class="onboarding-hero__eyebrow">
                    {{ greetingName ? t('onboarding.greeting', { name: greetingName }) : t('onboarding.greeting_plain') }}
                </p>
                <ol class="onboarding-progress" :aria-label="t('onboarding.progress_label')">
                    <li v-for="(key, index) in STEP_KEYS" :key="key">
                        <Link
                            :href="stepUrl(index)"
                            class="onboarding-progress__step"
                            :class="{ 'onboarding-progress__step--done': index < current, 'onboarding-progress__step--current': index === current }"
                            :aria-current="index === current ? 'step' : undefined"
                        >
                            <span class="onboarding-progress__number">
                                <svg v-if="index < current" class="onboarding-progress__check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span class="onboarding-progress__label">
                                {{ t(`onboarding.steps.${key}`) }}<span v-if="index < current" class="visually-hidden"> ({{ t('onboarding.step_done') }})</span>
                            </span>
                        </Link>
                    </li>
                </ol>
                <h1 class="page__title">{{ heading.title }}</h1>
                <p class="page__subtitle">{{ heading.lead }}</p>
            </header>

            <section class="card onboarding-card">
                <OnboardingProducts v-if="stepKey === 'items'" :products="products" :featured="featured" :watch-items="watchItems" :urls="urls" />
                <OnboardingChains v-else-if="stepKey === 'chains'" mode="chains" :chains="chains" :loyalty-programs="loyaltyPrograms" :url="urls.chains" />
                <OnboardingChains v-else-if="stepKey === 'cards'" mode="cards" :chains="chains" :loyalty-programs="loyaltyPrograms" :url="urls.cards" />
                <OnboardingNotify v-else :digest="digest" :digest-url="urls.digest" :email-verified="emailVerified" :email="email" :push="push" />
            </section>

            <!-- Přilepená lišta: Zpět, počítadlo akcí, Pokračovat / Dokončit -->
            <div class="onboarding-footbar">
                <div class="onboarding-footbar__inner">
                    <Link v-if="current > 0" :href="stepUrl(current - 1)" class="button button--ghost onboarding-footbar__back">
                        <svg class="onboarding-footbar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg>
                        <span class="onboarding-footbar__back-label">{{ t('onboarding.back') }}</span>
                    </Link>
                    <p class="onboarding-footbar__count" role="status">
                        <strong class="onboarding-footbar__count-value">{{ countText }}</strong>
                        <span class="onboarding-footbar__count-hint">{{ countHint }}</span>
                    </p>
                    <button v-if="isLastStep" type="button" class="button button--primary" :disabled="finishing" @click="finish">
                        {{ t('onboarding.finish') }}
                        <svg class="onboarding-footbar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                    </button>
                    <Link v-else :href="stepUrl(current + 1)" class="button button--primary">
                        {{ t('onboarding.next') }}
                        <svg class="onboarding-footbar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                    </Link>
                </div>
            </div>
        </template>

        <template v-else>
            <header class="page__header page-hero onboarding-hero onboarding-hero--done">
                <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="onboarding-hero__mascot" />
                <h1 class="page__title">{{ greetingName ? t('onboarding.done_title', { name: greetingName }) : t('onboarding.done_title_plain') }}</h1>
                <ul v-if="watchItems.length" class="page-hero__pills">
                    <li class="page-hero__pill">{{ t('onboarding.done_items', { count: watchItems.length }) }}</li>
                    <li class="page-hero__pill">{{ t('onboarding.done_chains', { count: followedChains.length }) }}</li>
                    <li class="page-hero__pill">{{ t('onboarding.done_offers', { count: offersCount, number: formatNumber(offersCount, page.props.locale) }) }}</li>
                </ul>
                <Link :href="urls.home" class="button button--highlight">{{ t('onboarding.done_show') }}</Link>
            </header>

            <section class="card onboarding-card onboarding-done">
                <p v-if="!watchItems.length" class="onboarding-done__empty">
                    {{ t('onboarding.done_empty') }}
                    <Link :href="stepUrl(0)" class="link">{{ t('onboarding.done_empty_link') }}</Link>
                </p>
                <ul class="onboarding-done__list">
                    <li v-for="(key, index) in STEP_KEYS" :key="key" class="onboarding-done__row">
                        <svg class="onboarding-done__check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
                        <span class="onboarding-done__text">
                            {{ t(`onboarding.steps.${key}`) }} — <strong>{{ summary[key] }}</strong>
                        </span>
                        <Link :href="stepUrl(index)" class="link">{{ t('onboarding.done_edit') }}</Link>
                    </li>
                </ul>
                <p class="onboarding-done__later">{{ t('onboarding.done_later') }}</p>
            </section>
        </template>
    </AppLayout>
</template>
