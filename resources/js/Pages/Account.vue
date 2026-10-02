<!--
    Účet — osobní údaje a změna hesla (Fortify, R12).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';

/** Kódy stavu, kterými Fortify potvrzuje uložení jednotlivých formulářů. */
const STATUS_PROFILE_UPDATED = 'profile-information-updated';
const STATUS_PASSWORD_UPDATED = 'password-updated';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Názvy sad chyb validace — každý formulář má vlastní, aby se chyby nepletly. */
    errorBags: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

const profileForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

/** Uloží jméno a e-mail. */
function updateProfile() {
    profileForm.put(props.urls.profile, {
        errorBag: props.errorBags.profile,
        preserveScroll: true,
    });
}

/** Změní heslo; pole s hesly se po odeslání vždy vymažou. */
function updatePassword() {
    passwordForm.put(props.urls.password, {
        errorBag: props.errorBags.password,
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('account.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('account.title') }}</h1>
        </header>

        <div class="account">
            <section class="card">
                <h2 class="card__title">{{ t('account.profile') }}</h2>
                <p v-if="page.props.status === STATUS_PROFILE_UPDATED" class="notice notice--success" role="status">
                    {{ t(`account.status.${STATUS_PROFILE_UPDATED}`) }}
                </p>

                <form class="form" novalidate @submit.prevent="updateProfile">
                    <TextField id="name" v-model="profileForm.name" :label="t('auth.name')" autocomplete="name" required :error="profileForm.errors.name" />
                    <TextField id="email" v-model="profileForm.email" :label="t('auth.email')" type="email" autocomplete="email" required :error="profileForm.errors.email" />

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="profileForm.processing">{{ t('account.save') }}</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <h2 class="card__title">{{ t('account.password') }}</h2>
                <p v-if="page.props.status === STATUS_PASSWORD_UPDATED" class="notice notice--success" role="status">
                    {{ t(`account.status.${STATUS_PASSWORD_UPDATED}`) }}
                </p>

                <form class="form" novalidate @submit.prevent="updatePassword">
                    <TextField
                        id="current_password"
                        v-model="passwordForm.current_password"
                        :label="t('account.current_password')"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="passwordForm.errors.current_password"
                    />
                    <TextField
                        id="password"
                        v-model="passwordForm.password"
                        :label="t('account.new_password')"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="passwordForm.errors.password"
                    />
                    <TextField
                        id="password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        :label="t('auth.password_confirmation')"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="passwordForm.errors.password_confirmation"
                    />

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="passwordForm.processing">{{ t('account.save') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
