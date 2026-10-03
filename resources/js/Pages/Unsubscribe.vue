<!--
    Odhlášení z e-mailů bez přihlášení (R51) — z podepsaného odkazu v patičce e-mailu.
    Odhlásí až tlačítko (POST): odkazy v e-mailech otevírají i antivirové skenery.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    /** Název e-mailů („souhrn akcí“). */
    list: { type: String, required: true },
    email: { type: String, required: true },
    /** Chodí uživateli tyto e-maily ještě? */
    subscribed: { type: Boolean, required: true },
    /** Podepsaná adresa pro odeslání. */
    submitUrl: { type: String, required: true },
});

const t = useTranslations();

const form = useForm({});

/** Odhlásí z e-mailů. */
function unsubscribe() {
    form.post(props.submitUrl);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('unsubscribe.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('unsubscribe.title') }}</h1>
        </header>

        <section class="card unsubscribe">
            <template v-if="subscribed">
                <p>{{ t('unsubscribe.confirm', { list, email }) }}</p>
                <div class="unsubscribe__actions">
                    <button type="button" class="button button--primary" :disabled="form.processing" @click="unsubscribe">{{ t('unsubscribe.submit') }}</button>
                </div>
            </template>
            <template v-else>
                <p>{{ t('unsubscribe.done', { list, email }) }}</p>
                <div class="unsubscribe__actions">
                    <Link href="/" class="button button--ghost">{{ t('unsubscribe.home') }}</Link>
                </div>
            </template>
        </section>
    </AppLayout>
</template>
