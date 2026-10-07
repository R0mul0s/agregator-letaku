<!--
    Zprávy uživatelům (R74, etapa 11d) — admin napíše zprávu do centra upozornění: nadpis, text,
    nepovinný odkaz a druh. Zpráva o službě jde všem a smí i do telefonu, propagační jen
    uživatelům se souhlasem s novinkami a jen do centra. Před odesláním potvrzení s počtem
    příjemců; pod formulářem přehled odeslaných zpráv.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import CheckboxField from '@/Components/CheckboxField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { confirmDialog } from '@/lib/confirm';
import { formatDateTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    storeUrl: { type: String, required: true },
    /** Počet příjemců podle druhu zprávy { service, marketing }. */
    recipients: { type: Object, required: true },
    /** Nejvyšší délka nadpisu a textu { title, body }. */
    limits: { type: Object, required: true },
    /** Odeslané zprávy od nejnovější [{ id, title, category, push, recipients, createdAt }]. */
    announcements: { type: Array, required: true },
});

/** Druhy zprávy v pořadí na stránce (AnnouncementCategory). */
const CATEGORIES = ['service', 'marketing'];

/** Druh zprávy, který smí i do telefonu (AnnouncementCategory::allowsPush). */
const PUSH_CATEGORY = 'service';

const t = useTranslations();
const page = usePage();

const form = useForm({
    title: '',
    body: '',
    url: '',
    category: PUSH_CATEGORY,
    push: false,
});

// Propagační zpráva do telefonu nesmí — přepnutím druhu se volba zruší
watch(
    () => form.category,
    (category) => {
        if (category !== PUSH_CATEGORY) {
            form.push = false;
        }
    },
);

/** Po potvrzení s počtem příjemců zprávu odešle. */
async function submit() {
    const confirmed = await confirmDialog({
        title: t('announcements.confirm_title'),
        message: t('announcements.confirm', { count: props.recipients[form.category] }),
        confirmLabel: t('announcements.confirm_label'),
    });
    if (confirmed) {
        form.post(props.storeUrl, { preserveScroll: true, onSuccess: () => form.reset() });
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="t('announcements.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('announcements.title') }}</h1>
            <p class="page__subtitle">{{ t('announcements.intro') }}</p>
        </header>

        <form class="card form announcement-form" @submit.prevent="submit">
            <TextField id="title" v-model="form.title" :label="t('announcements.title_field')" required :error="form.errors.title" />

            <div class="form-field">
                <label for="body" class="form-field__label">{{ t('announcements.body') }}</label>
                <textarea
                    id="body"
                    v-model="form.body"
                    name="body"
                    required
                    :maxlength="limits.body"
                    class="form-field__input announcement-form__body"
                    :class="{ 'form-field__input--invalid': form.errors.body }"
                    :aria-invalid="form.errors.body ? 'true' : undefined"
                    :aria-describedby="form.errors.body ? 'body-hint body-error' : 'body-hint'"
                ></textarea>
                <p id="body-hint" class="form-field__hint">{{ t('announcements.chars', { count: form.body.length, max: limits.body }) }}</p>
                <p v-if="form.errors.body" id="body-error" class="form-field__error" role="alert">{{ form.errors.body }}</p>
            </div>

            <TextField id="url" v-model="form.url" :label="t('announcements.url')" :hint="t('announcements.url_hint')" :error="form.errors.url" />

            <fieldset class="account-choice">
                <legend class="form-field__label">{{ t('announcements.category') }}</legend>
                <label
                    v-for="category in CATEGORIES"
                    :key="category"
                    class="account-choice__option"
                    :class="{ 'account-choice__option--selected': form.category === category }"
                >
                    <input v-model="form.category" type="radio" name="category" :value="category" class="form-checkbox__input" />
                    <span>
                        <span class="account-choice__label">{{ t(`announcements.categories.${category}`) }}</span>
                        <span class="account-choice__hint">{{ t(`announcements.category_hints.${category}`, { count: recipients[category] }) }}</span>
                    </span>
                </label>
                <p v-if="form.errors.category" class="form-field__error" role="alert">{{ form.errors.category }}</p>
            </fieldset>

            <div>
                <CheckboxField id="push" v-model="form.push" :label="t('announcements.push')" :disabled="form.category !== PUSH_CATEGORY" :error="form.errors.push" />
                <p class="form-field__hint">{{ form.category === PUSH_CATEGORY ? t('announcements.push_hint') : t('announcements.push_marketing') }}</p>
            </div>

            <div class="form__actions">
                <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('announcements.submit') }}</button>
            </div>
        </form>

        <section class="announcements-sent">
            <h2 class="announcements-sent__title">{{ t('announcements.sent_title') }}</h2>
            <EmptyState v-if="!announcements.length" :text="t('announcements.sent_empty')" />
            <ul v-else class="card announcements-sent__list">
                <li v-for="announcement in announcements" :key="announcement.id" class="announcements-sent__item">
                    <span class="announcements-sent__name">{{ announcement.title }}</span>
                    <span class="announcements-sent__meta">
                        <span class="tag" :class="{ 'tag--accent': announcement.category === 'marketing' }">{{ t(`announcements.categories.${announcement.category}`) }}</span>
                        {{ t('announcements.recipients', { count: announcement.recipients }) }}
                        <template v-if="announcement.push">· {{ t('announcements.with_push') }}</template>
                        · <time :datetime="announcement.createdAt">{{ formatDateTime(announcement.createdAt, page.props.locale, page.props.timezone) }}</time>
                    </span>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
