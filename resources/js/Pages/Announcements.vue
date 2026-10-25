<!--
    Zprávy uživatelům (R74, etapa 11d) — admin napíše zprávu do centra upozornění: nadpis, text
    s odkazy na označených slovech a náhledem (R175), nepovinný odkaz a druh. Zpráva o službě jde všem a smí i do telefonu, propagační jen
    uživatelům se souhlasem s novinkami a jen do centra. Před odesláním potvrzení s počtem
    příjemců; pod formulářem přehled odeslaných zpráv.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import AnnouncementBody from '@/Components/AnnouncementBody.vue';
import CheckboxField from '@/Components/CheckboxField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TextField from '@/Components/TextField.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { announcementLink, hasAnnouncementLink, isAnnouncementUrl } from '@/lib/announcementText';
import { confirmDialog } from '@/lib/confirm';
import { formatDateTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';

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

/** Pole textu zprávy — označená slova pro odkaz. */
const bodyInput = ref(null);

/** Otevřené pole adresy odkazu v textu (R175). */
const linkOpen = ref(false);
const linkUrl = ref('');
const linkError = ref(undefined);

/** Označená část textu při otevření pole adresy { start, end } — fokus pak textarea opustí. */
let linkSelection = { start: 0, end: 0 };

/** Otevře pole adresy a zapamatuje si označená slova. */
function openLink() {
    const input = bodyInput.value;
    linkSelection = { start: input?.selectionStart ?? form.body.length, end: input?.selectionEnd ?? form.body.length };
    linkUrl.value = '';
    linkError.value = undefined;
    linkOpen.value = true;
}

/** Zavře pole adresy a vrátí fokus do textu. */
function closeLink() {
    linkOpen.value = false;
    bodyInput.value?.focus();
}

/**
 * Obalí označená slova odkazem `[slova](adresa)`; bez označení vloží zástupný text a označí ho,
 * aby šel hned přepsat.
 */
async function insertLink() {
    const url = linkUrl.value.trim();
    if (!isAnnouncementUrl(url)) {
        linkError.value = t('announcements.url_invalid');
        return;
    }

    const { start, end } = linkSelection;
    const selected = form.body.slice(start, end);
    const placeholder = selected.trim() === '';
    const text = placeholder ? t('announcements.link_text') : selected;
    const link = announcementLink(text, url);
    form.body = form.body.slice(0, start) + link + form.body.slice(end);
    linkOpen.value = false;

    await nextTick();
    // Zástupný text označený k přepsání (za „[“), jinak kurzor za odkazem
    const [from, to] = placeholder ? [start + 1, start + 1 + text.length] : [start + link.length, start + link.length];
    bodyInput.value?.focus();
    bodyInput.value?.setSelectionRange(from, to);
}

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

        <header class="page__header page-hero">
            <h1 class="page__title">{{ t('announcements.title') }}</h1>
            <p class="page__subtitle">{{ t('announcements.intro') }}</p>
        </header>

        <form class="card form announcement-form" @submit.prevent="submit">
            <TextField id="title" v-model="form.title" :label="t('announcements.title_field')" required :error="form.errors.title" />

            <div class="form-field">
                <div class="announcement-form__body-head">
                    <label for="body" class="form-field__label">{{ t('announcements.body') }}</label>
                    <button type="button" class="button button--ghost" :aria-expanded="linkOpen ? 'true' : 'false'" aria-controls="link-editor" @click="openLink">
                        {{ t('announcements.link_add') }}
                    </button>
                </div>
                <!-- Odkaz v textu (R175): adresa k označeným slovům, Enter vloží, Escape zruší -->
                <div v-if="linkOpen" id="link-editor" class="announcement-form__link" @keydown.enter.prevent="insertLink" @keydown.esc.prevent="closeLink">
                    <TextField id="link-url" v-model="linkUrl" :label="t('announcements.link_url')" :hint="t('announcements.link_url_hint')" :error="linkError" autofocus />
                    <div class="form__actions">
                        <button type="button" class="button button--primary" @click="insertLink">{{ t('announcements.link_insert') }}</button>
                        <button type="button" class="button button--ghost" @click="closeLink">{{ t('announcements.link_cancel') }}</button>
                    </div>
                </div>
                <textarea
                    id="body"
                    ref="bodyInput"
                    v-model="form.body"
                    name="body"
                    required
                    :maxlength="limits.body"
                    class="form-field__input announcement-form__body"
                    :class="{ 'form-field__input--invalid': form.errors.body }"
                    :aria-invalid="form.errors.body ? 'true' : undefined"
                    :aria-describedby="form.errors.body ? 'body-hint body-error' : 'body-hint'"
                ></textarea>
                <p id="body-hint" class="form-field__hint">
                    {{ t('announcements.chars', { count: form.body.length, max: limits.body }) }} · {{ t('announcements.link_hint') }}
                </p>
                <p v-if="form.errors.body" id="body-error" class="form-field__error" role="alert">{{ form.errors.body }}</p>
            </div>

            <!-- Náhled textu s odkazy, jak ho uvidí uživatelé (R175) -->
            <div v-if="hasAnnouncementLink(form.body)" class="announcement-form__preview">
                <p class="form-field__label">{{ t('announcements.preview') }}</p>
                <AnnouncementBody :body="form.body" />
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
                    <input
                        v-model="form.category"
                        type="radio"
                        name="category"
                        :value="category"
                        class="form-checkbox__input"
                        :aria-invalid="form.errors.category ? 'true' : undefined"
                        :aria-describedby="form.errors.category ? 'category-error' : undefined"
                    />
                    <span>
                        <span class="account-choice__label">{{ t(`announcements.categories.${category}`) }}</span>
                        <span class="account-choice__hint">{{ t(`announcements.category_hints.${category}`, { count: recipients[category] }) }}</span>
                    </span>
                </label>
                <p v-if="form.errors.category" id="category-error" class="form-field__error" role="alert">{{ form.errors.category }}</p>
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
