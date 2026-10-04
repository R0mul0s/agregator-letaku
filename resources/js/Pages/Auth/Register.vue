<!--
    Registrace nového účtu (Fortify, R12) se souhlasem s podmínkami a dobrovolným
    souhlasem s obchodními sděleními (R51), ochrana proti botům skrytým polem a časem (R53).
    Karta ukazuje cestu ve třech krocích a co uživatele čeká; heslo jedno s tlačítkem
    „Ukázat heslo“ místo zadávání dvakrát (R56).

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
    /** Ochrana proti botům (R53): { tokenField, token, trapField }. */
    guard: { type: Object, required: true },
    /** Nejmenší délka hesla (letaky.auth.password.min_length) — nápověda u pole. */
    passwordMinLength: { type: Number, required: true },
    /** Data panelu vedle formuláře (AuthShowcase.vue, R56). */
    showcase: { type: Object, required: true },
});

const t = useTranslations();

/** Kroky cesty k prvním slevám — registrace je první z nich (lang: auth.register.steps). */
const STEPS = ['account', 'watch', 'hunt'];

/** Co uživatele čeká — pod tlačítkem (lang: auth.register.trust). */
const TRUST = ['free', 'no_ads', 'cancel'];

const form = useForm({
    name: '',
    email: '',
    password: '',
    terms: false,
    marketing: false,
    // Podepsaný čas načtení formuláře a skryté pole pro roboty (R53)
    [props.guard.tokenField]: props.guard.token,
    [props.guard.trapField]: '',
});

/** Odešle registraci; heslo se po odeslání vždy vymaže. */
function submit() {
    form.post(props.urls.submit, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('auth.register.title')" />

        <AuthShowcase :showcase="showcase">
            <section class="auth-card">
                <ol class="auth-steps" :aria-label="t('auth.register.steps_label')">
                    <li
                        v-for="(step, index) in STEPS"
                        :key="step"
                        class="auth-steps__item"
                        :class="{ 'auth-steps__item--current': index === 0 }"
                        :aria-current="index === 0 ? 'step' : undefined"
                    >
                        <span class="auth-steps__number" aria-hidden="true">{{ index + 1 }}</span>
                        {{ t(`auth.register.steps.${step}`) }}
                    </li>
                </ol>
                <h1 class="auth-card__title auth-card__title--tight">{{ t('auth.register.heading') }}</h1>
                <p class="auth-card__intro">{{ t('auth.register.intro') }}</p>

                <form class="form" novalidate @submit.prevent="submit">
                    <TextField id="name" v-model="form.name" :label="t('auth.name')" autocomplete="name" required autofocus :error="form.errors.name" />
                    <TextField id="email" v-model="form.email" :label="t('auth.email')" type="email" autocomplete="email" required :error="form.errors.email" />
                    <TextField
                        id="password"
                        v-model="form.password"
                        :label="t('auth.password')"
                        :hint="t('auth.register.password_hint', { min: passwordMinLength })"
                        type="password"
                        autocomplete="new-password"
                        required
                        revealable
                        :error="form.errors.password"
                    />

                    <CheckboxField id="terms" v-model="form.terms" :error="form.errors.terms">
                        {{ t('auth.register.terms_before') }}
                        <a :href="urls.terms" class="link" target="_blank" rel="noopener">{{ t('auth.register.terms_link') }}</a>.
                        {{ t('auth.register.privacy_before') }}
                        <a :href="urls.privacy" class="link" target="_blank" rel="noopener">{{ t('auth.register.privacy_link') }}</a>.
                    </CheckboxField>
                    <CheckboxField id="marketing" v-model="form.marketing" :label="t('auth.register.marketing')" :error="form.errors.marketing" />

                    <!-- Skryté pole: člověk ho nevidí ani do něj neskočí tabulátorem, robot ho vyplní (R53) -->
                    <div class="form__trap" aria-hidden="true">
                        <label :for="guard.trapField">{{ t('auth.register.trap') }}</label>
                        <input :id="guard.trapField" v-model="form[guard.trapField]" :name="guard.trapField" type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <button type="submit" class="button button--primary auth-card__submit" :disabled="form.processing">
                        {{ t('auth.register.submit') }} <span aria-hidden="true">→</span>
                    </button>
                    <p v-if="form.errors[guard.tokenField]" class="form-field__error" role="alert">{{ form.errors[guard.tokenField] }}</p>
                    <ul class="auth-card__trust">
                        <li v-for="item in TRUST" :key="item" class="auth-card__trust-item">{{ t(`auth.register.trust.${item}`) }}</li>
                    </ul>
                </form>

                <p class="auth-card__footer">
                    {{ t('auth.register.has_account') }}
                    <Link :href="urls.login" class="link">{{ t('auth.register.login') }}</Link>
                </p>
            </section>
        </AuthShowcase>
    </AppLayout>
</template>
