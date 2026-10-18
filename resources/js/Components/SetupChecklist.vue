<!--
    Karta „Dokončete nastavení“ na Mých slevách (R153), dokud uživatel průvodce prvním nastavením
    nedokončí nebo kartu nezavře: čtyři body (co kupuje, obchody, karty, upozornění) se stavem
    podle skutečného nastavení účtu a odkazem na krok průvodce. Křížek kartu zavře natrvalo.

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import { formatNumber } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** { steps: [{ key, done, count, url }], dismissUrl } (HomeController::setup). */
    setup: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const dismissing = ref(false);

const doneCount = computed(() => props.setup.steps.filter((step) => step.done).length);

/**
 * Věta pod názvem bodu: hotový se shrnutím, nehotový s tím, co chybí.
 *
 * @param {{ key: string, done: boolean, count: number }} step
 * @returns {string}
 */
function stepText(step) {
    return t(`home.setup.steps.${step.key}.${step.done ? 'done' : 'todo'}`, { count: step.count, number: formatNumber(step.count, page.props.locale) });
}

/** Zavře kartu natrvalo (průvodce je za uživatelem). */
function dismiss() {
    dismissing.value = true;
    router.post(props.setup.dismissUrl, { dismissed: true }, { preserveScroll: true, onFinish: () => (dismissing.value = false) });
}
</script>

<template>
    <section class="setup-checklist" aria-labelledby="setup-checklist-title">
        <div class="setup-checklist__head">
            <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="setup-checklist__mascot" />
            <div class="setup-checklist__heading">
                <h2 id="setup-checklist-title" class="setup-checklist__title">{{ t('home.setup.title') }}</h2>
                <p class="setup-checklist__progress">{{ t('home.setup.progress', { done: doneCount, total: setup.steps.length }) }}</p>
            </div>
            <button type="button" class="icon-button setup-checklist__close" :title="t('home.setup.dismiss')" :disabled="dismissing" @click="dismiss">
                <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                <span class="visually-hidden">{{ t('home.setup.dismiss') }}</span>
            </button>
        </div>
        <progress class="setup-checklist__meter" :value="doneCount" :max="setup.steps.length" aria-hidden="true"></progress>
        <ol class="setup-checklist__steps">
            <li v-for="(step, index) in setup.steps" :key="step.key" class="setup-checklist__step" :class="{ 'setup-checklist__step--done': step.done }">
                <span class="setup-checklist__check" aria-hidden="true">
                    <svg v-if="step.done" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10" /></svg>
                </span>
                <span class="setup-checklist__text">
                    <span class="setup-checklist__step-title">{{ t(`home.setup.steps.${step.key}.title`) }}</span>
                    <span class="setup-checklist__step-hint">{{ stepText(step) }}</span>
                </span>
                <Link
                    v-if="!step.done"
                    :href="step.url"
                    class="button setup-checklist__action"
                    :class="index === 0 ? 'button--primary' : 'button--soft'"
                >
                    {{ t(`home.setup.steps.${step.key}.action`) }}
                </Link>
            </li>
        </ol>
    </section>
</template>
