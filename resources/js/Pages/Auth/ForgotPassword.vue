<!--
    Žádost o odkaz pro nastavení nového hesla (Fortify, R12).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AuthShowcase from '@/Components/AuthShowcase.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    urls: { type: Object, required: true },
});

const t = useTranslations();

const form = useForm({
    email: '',
});

/** Odešle žádost o odkaz na zadaný e-mail. */
function submit() {
    form.post(props.urls.submit);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.forgot.title')" />

        <AuthShowcase>
            <section class="auth-card">
                <h1 class="auth-card__title">{{ t('auth.forgot.title') }}</h1>
                <p class="auth-card__intro">{{ t('auth.forgot.intro') }}</p>


                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="email" v-model="form.email" :label="t('auth.email')" type="email" autocomplete="username" required autofocus :error="form.errors.email" />

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('auth.forgot.submit') }}</button>
                        <Link :href="urls.login" class="link">{{ t('auth.forgot.back') }}</Link>
                    </div>
                </form>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
