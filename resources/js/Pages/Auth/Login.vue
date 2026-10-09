<!--
    Přihlášení (Fortify, R12) — vedle formuláře panel se skutečnými akcemi (R56),
    nad formulářem přihlášení přes Google a Facebook (R96).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AuthShowcase from '@/Components/AuthShowcase.vue';
import CheckboxField from '@/Components/CheckboxField.vue';
import SocialButtons from '@/Components/SocialButtons.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { isStandalone } from '@/lib/pwaInstall';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Data panelu vedle formuláře (AuthShowcase.vue, R56). */
    showcase: { type: Object, required: true },
    /** Přihlášení přes Google a Facebook [{ provider, url, logo }] (R96). */
    social: { type: Array, default: () => [] },
    /** Parametr adresy pro „Zapamatovat si mě“ u přihlášení přes poskytovatele. */
    rememberParameter: { type: String, default: null },
});

const t = useTranslations();
const page = usePage();

const form = useForm({
    email: '',
    password: '',
    // Aplikace z plochy (R66) je osobní telefon a na iPhonu nesdílí přihlášení se Safari —
    // bez zapamatování by se po dvou hodinách přihlašoval znovu
    remember: isStandalone(),
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

                <!-- Chyba přihlášení přes poskytovatele (SocialLoginController, R96) -->
                <p v-if="page.props.errors.social" class="notice notice--error" role="alert">{{ page.props.errors.social }}</p>
                <SocialButtons :providers="social" :remember="form.remember" :remember-parameter="rememberParameter" />

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
