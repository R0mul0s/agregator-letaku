<!--
    Můj účet (R12, R40) — profilový obrázek, jméno a e-mail, heslo (Fortify),
    přihlášená zařízení a zrušení účtu.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import TextField from '@/Components/TextField.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { squareImage } from '@/lib/image';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Kódy stavu, kterými server potvrzuje uložení: kód => oddíl stránky, kde se zpráva ukáže. */
const STATUS_SECTIONS = {
    'avatar-updated': 'profile',
    'profile-information-updated': 'profile',
    'password-updated': 'password',
    'other-devices-logged-out': 'devices',
};

const props = defineProps({
    urls: { type: Object, required: true },
    /** Názvy sad chyb validace — každý formulář má vlastní, aby se chyby nepletly. */
    errorBags: { type: Object, required: true },
    /** Rozměry profilového obrázku { sizePx, maxKilobytes }. */
    avatar: { type: Object, required: true },
    /** Přihlášení na zařízeních [{ device, ipAddress, lastActiveAt, current }]. */
    sessions: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

const user = computed(() => page.props.auth.user);

/** Zpráva o uložení pro oddíl stránky, nebo null. */
function statusFor(section) {
    const status = page.props.status;

    return status && STATUS_SECTIONS[status] === section ? t(`account.status.${status}`) : null;
}

const profileForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const devicesForm = useForm({ password: '' });
const deleteForm = useForm({ password: '' });

const avatarInput = ref(null);
const avatarError = ref(null);
const avatarUploading = ref(false);

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

/**
 * Vybraný obrázek ořízne na čtverec, zmenší a nahraje.
 *
 * @param {Event} event
 */
async function uploadAvatar(event) {
    const [file] = event.target.files;
    event.target.value = '';
    if (!file) {
        return;
    }

    avatarError.value = null;
    let squared;
    try {
        squared = await squareImage(file, props.avatar.sizePx);
    } catch {
        avatarError.value = t('account.avatar_unreadable');

        return;
    }

    router.post(
        props.urls.avatar,
        { avatar: squared },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (avatarUploading.value = true),
            onFinish: () => (avatarUploading.value = false),
            onError: (errors) => (avatarError.value = errors.avatar ?? null),
        },
    );
}

/** Odebere obrázek — ukážou se iniciály. */
function removeAvatar() {
    router.delete(props.urls.avatarDelete, { preserveScroll: true });
}

/** Odhlásí ostatní zařízení po zadání hesla. */
function logoutOtherDevices() {
    devicesForm.delete(props.urls.logoutOtherDevices, {
        errorBag: props.errorBags.devices,
        preserveScroll: true,
        onFinish: () => devicesForm.reset(),
    });
}

/** Po potvrzení zruší účet. */
function deleteAccount() {
    if (!window.confirm(t('account.delete_confirm'))) {
        return;
    }

    deleteForm.delete(props.urls.delete, {
        errorBag: props.errorBags.delete,
        preserveScroll: true,
        onFinish: () => deleteForm.reset(),
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
                <p v-if="statusFor('profile')" class="notice notice--success" role="status">{{ statusFor('profile') }}</p>

                <div class="account-avatar">
                    <UserAvatar :name="user.name" :url="user.avatarUrl" large />
                    <div class="account-avatar__body">
                        <p class="account-avatar__title">{{ t('account.avatar') }}</p>
                        <p class="form-field__hint">{{ t('account.avatar_hint') }}</p>
                        <div class="account-avatar__actions">
                            <input ref="avatarInput" type="file" accept="image/*" class="visually-hidden" tabindex="-1" @change="uploadAvatar" />
                            <button type="button" class="button button--ghost" :disabled="avatarUploading" @click="avatarInput.click()">
                                {{ user.avatarUrl ? t('account.avatar_change') : t('account.avatar_upload') }}
                            </button>
                            <button v-if="user.avatarUrl" type="button" class="button button--ghost" @click="removeAvatar">{{ t('account.avatar_remove') }}</button>
                        </div>
                        <p v-if="avatarError" class="form-field__error" role="alert">{{ avatarError }}</p>
                    </div>
                </div>

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
                <p v-if="statusFor('password')" class="notice notice--success" role="status">{{ statusFor('password') }}</p>

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

            <section class="card">
                <h2 class="card__title">{{ t('account.devices') }}</h2>
                <p v-if="statusFor('devices')" class="notice notice--success" role="status">{{ statusFor('devices') }}</p>
                <p class="form-field__hint">{{ t('account.devices_hint') }}</p>

                <ul v-if="sessions.length" class="device-list">
                    <li v-for="(session, index) in sessions" :key="index" class="device-list__item">
                        <span class="device-list__name">
                            {{ session.device }}
                            <span v-if="session.current" class="tag tag--accent">{{ t('account.this_device') }}</span>
                        </span>
                        <span class="device-list__meta">
                            {{ session.ipAddress }} · {{ t('account.last_active', { at: formatDateTime(session.lastActiveAt, page.props.locale, page.props.timezone) }) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="page__empty">{{ t('account.devices_empty') }}</p>

                <form class="form" novalidate @submit.prevent="logoutOtherDevices">
                    <TextField
                        id="devices_password"
                        v-model="devicesForm.password"
                        :label="t('account.confirm_password')"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="devicesForm.errors.password"
                    />
                    <div class="form__actions">
                        <button type="submit" class="button button--ghost" :disabled="devicesForm.processing">{{ t('account.logout_others') }}</button>
                    </div>
                </form>
            </section>

            <section class="card card--danger">
                <h2 class="card__title">{{ t('account.delete_title') }}</h2>
                <p class="form-field__hint">{{ t('account.delete_hint') }}</p>

                <form class="form" novalidate @submit.prevent="deleteAccount">
                    <TextField
                        id="delete_password"
                        v-model="deleteForm.password"
                        :label="t('account.confirm_password')"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="deleteForm.errors.password"
                    />
                    <div class="form__actions">
                        <button type="submit" class="button button--danger" :disabled="deleteForm.processing">{{ t('account.delete_submit') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
