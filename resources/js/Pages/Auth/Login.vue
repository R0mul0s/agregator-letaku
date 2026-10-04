<!--
    Přihlášení (Fortify, R12) — vedle formuláře panel se skutečnými akcemi (R56).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AuthShowcase from '@/Components/AuthShowcase.vue';
import CheckboxField from '@/Components/CheckboxField.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Data panelu vedle formuláře (AuthShowcase.vue, R56). */
    showcase: { type: Object, required: true },
});

const t = useTranslations();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

/** Odešle přihlášení; heslo se po odeslání vždy vymaže. */
function submit() {
    form.post(props.urls.submit, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.login.title')" />

        <AuthShowcase :showcase="showcase">
            <section class="auth-card">
                <h1 class="auth-card__title">{{ t('auth.login.title') }}</h1>


                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="email" v-model="form.email" :label="t('auth.email')" type="email" autocomplete="username" required autofocus :error="form.errors.email" />
                    <TextField id="password" v-model="form.password" :label="t('auth.password')" type="password" autocomplete="current-password" required revealable :error="form.errors.password" />
                    <CheckboxField id="remember" v-model="form.remember" :label="t('auth.remember')" />

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('auth.login.submit') }}</button>
                        <Link :href="urls.forgotPassword" class="link">{{ t('auth.login.forgot') }}</Link>
                    </div>
                </form>

                <p class="auth-card__footer">
                    {{ t('auth.login.no_account') }}
                    <Link :href="urls.register" class="link">{{ t('auth.login.register') }}</Link>
                </p>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
