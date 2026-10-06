<!--
    Dokončení registrace přes Google nebo Facebook (R96): jméno od poskytovatele jde upravit,
    e-mail je z účtu u poskytovatele, souhlas s podmínkami povinný a s obchodními sděleními
    dobrovolný a nezaškrtnutý (R51). Vzhled jako registrace (R56).

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import AuthShowcase from '@/Components/AuthShowcase.vue';
import CheckboxField from '@/Components/CheckboxField.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Kdo se u poskytovatele přihlásil { provider, name, email, emailVerified }. */
    identity: { type: Object, required: true },
    urls: { type: Object, required: true },
    /** Data panelu vedle formuláře (AuthShowcase.vue, R56). */
    showcase: { type: Object, required: true },
});

const t = useTranslations();

/** Název poskytovatele („Google“). */
const providerName = computed(() => t(`auth.social.providers.${props.identity.provider}`));

const form = useForm({
    name: props.identity.name,
    terms: false,
    marketing: false,
});

/** Odešle dokončení registrace. */
function submit() {
    form.post(props.urls.submit);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.social.register.title')" />

        <AuthShowcase :showcase="showcase">
            <section class="auth-card">
                <h1 class="auth-card__title auth-card__title--tight">{{ t('auth.social.register.heading') }}</h1>
                <p class="auth-card__intro">{{ t('auth.social.register.intro', { provider: providerName }) }}</p>

                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="name" v-model="form.name" :label="t('auth.name')" autocomplete="name" required autofocus :error="form.errors.name" />
                    <TextField
                        id="email"
                        :model-value="identity.email"
                        :label="t('auth.email')"
                        :hint="t(identity.emailVerified ? 'auth.social.register.email_verified' : 'auth.social.register.email_unverified', { provider: providerName })"
                        type="email"
                        readonly
                        :error="form.errors.email"
                    />

                    <CheckboxField id="terms" v-model="form.terms" :error="form.errors.terms">
                        {{ t('auth.register.terms_before') }}
                        <a :href="urls.terms" class="link" target="_blank" rel="noopener">{{ t('auth.register.terms_link') }}</a>.
                        {{ t('auth.register.privacy_before') }}
                        <a :href="urls.privacy" class="link" target="_blank" rel="noopener">{{ t('auth.register.privacy_link') }}</a>.
                    </CheckboxField>
                    <CheckboxField id="marketing" v-model="form.marketing" :label="t('auth.register.marketing')" :error="form.errors.marketing" />

                    <button type="submit" class="button button--primary auth-card__submit" :disabled="form.processing">
                        {{ t('auth.register.submit') }} <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="auth-card__footer">
                    {{ t('auth.social.register.other') }}
                    <Link :href="urls.register" class="link">{{ t('auth.social.register.other_link') }}</Link>
                </p>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
