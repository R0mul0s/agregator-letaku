<!--
    Registrace nového účtu (Fortify, R12).

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
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

/** Odešle registraci; hesla se po odeslání vždy vymažou. */
function submit() {
    form.post(props.urls.submit, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.register.title')" />

        <AuthShowcase>
            <section class="auth-card">
                <h1 class="auth-card__title">{{ t('auth.register.title') }}</h1>

                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="name" v-model="form.name" :label="t('auth.name')" autocomplete="name" required autofocus :error="form.errors.name" />
                    <TextField id="email" v-model="form.email" :label="t('auth.email')" type="email" autocomplete="email" required :error="form.errors.email" />
                    <TextField id="password" v-model="form.password" :label="t('auth.password')" type="password" autocomplete="new-password" required :error="form.errors.password" />
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
                        <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('auth.register.submit') }}</button>
                    </div>
                </form>

                <p class="auth-card__footer">
                    {{ t('auth.register.has_account') }}
                    <Link :href="urls.login" class="link">{{ t('auth.register.login') }}</Link>
                </p>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
