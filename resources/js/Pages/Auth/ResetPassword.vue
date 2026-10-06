<!--
    Nastavení nového hesla z odkazu v e-mailu (Fortify, R12).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AuthShowcase from '@/Components/AuthShowcase.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    /** Token z odkazu v e-mailu. */
    token: { type: String, required: true },
    email: { type: String, default: '' },
    urls: { type: Object, required: true },
    /** Data panelu vedle formuláře (AuthShowcase.vue, R56). */
    showcase: { type: Object, required: true },
});

const t = useTranslations();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

/** Odešle nové heslo; hesla se po odeslání vždy vymažou. */
function submit() {
    form.post(props.urls.submit, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.reset.title')" />

        <AuthShowcase :showcase="showcase">
            <section class="auth-card">
                <h1 class="auth-card__title">{{ t('auth.reset.title') }}</h1>

                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="email" v-model="form.email" :label="t('auth.email')" type="email" autocomplete="username" required :error="form.errors.email" />
                    <TextField id="password" v-model="form.password" :label="t('auth.password')" type="password" autocomplete="new-password" required autofocus :error="form.errors.password" />
                    <TextField
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :label="t('auth.password_confirmation')"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="form.errors.password_confirmation"
                    />

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('auth.reset.submit') }}</button>
                    </div>
                </form>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
