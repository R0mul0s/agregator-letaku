<!--
    Souhlas s cookies (R52): lišta dole, dokud uživatel nevybere, a okno s nastavením kategorií
    (nezbytné vždy, analytické, marketingové). „Přijmout vše“ a „Odmítnout vše“ jsou stejně
    výrazné — odmítnout musí jít stejně snadno jako přijmout. Nastavení jde kdykoli otevřít
    odkazem v patičce. Stav a ukládání řídí lib/consent.js.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { closeConsentSettings, consentState, saveConsent } from '@/lib/consent';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { nextTick, reactive, ref, watch } from 'vue';

const t = useTranslations();
const page = usePage();

const dialog = ref(null);

/** Volby v okně nastavení — začínají uloženým stavem. */
const choice = reactive({ analytics: false, marketing: false });

watch(
    () => consentState.settingsOpen,
    async (open) => {
        if (open && !dialog.value?.open) {
            Object.assign(choice, { analytics: consentState.analytics, marketing: consentState.marketing });
            await nextTick();
            dialog.value?.showModal();
        } else if (!open && dialog.value?.open) {
            dialog.value.close();
        }
    },
);

/** Přijme všechny kategorie. */
function acceptAll() {
    saveConsent({ analytics: true, marketing: true });
}

/** Odmítne vše kromě nezbytných cookies. */
function rejectAll() {
    saveConsent({ analytics: false, marketing: false });
}

/** Uloží výběr z okna nastavení. */
function saveChoice() {
    saveConsent({ ...choice });
}

/** Otevře nastavení z lišty. */
function openSettings() {
    consentState.settingsOpen = true;
}

/**
 * Klik na ztmavené pozadí okno zavře (bez uložení).
 *
 * @param {MouseEvent} event
 */
function onDialogClick(event) {
    if (event.target === dialog.value) {
        closeConsentSettings();
    }
}
</script>

<template>
    <section v-if="!consentState.decided && !consentState.settingsOpen" class="cookie-bar" :aria-label="t('cookies.title')">
        <div class="cookie-bar__inner">
            <div class="cookie-bar__text">
                <h2 class="cookie-bar__title">{{ t('cookies.title') }}</h2>
                <p>
                    {{ t('cookies.intro') }}
                    <Link :href="page.props.cookieConsent.privacyUrl" class="link">{{ t('cookies.more') }}</Link>
                </p>
            </div>
            <div class="cookie-bar__actions">
                <button type="button" class="button button--ghost" @click="openSettings">{{ t('cookies.settings') }}</button>
                <button type="button" class="button button--primary" @click="rejectAll">{{ t('cookies.reject_all') }}</button>
                <button type="button" class="button button--primary" @click="acceptAll">{{ t('cookies.accept_all') }}</button>
            </div>
        </div>
    </section>

    <dialog ref="dialog" class="cookie-dialog" aria-labelledby="cookie-dialog-title" @cancel.prevent="closeConsentSettings" @click="onDialogClick">
        <h2 id="cookie-dialog-title" class="cookie-dialog__title">{{ t('cookies.settings_title') }}</h2>
        <p class="cookie-dialog__intro">{{ t('cookies.settings_intro') }}</p>

        <ul class="cookie-dialog__categories">
            <li class="cookie-dialog__category">
                <div class="cookie-dialog__category-text">
                    <h3 class="cookie-dialog__category-title">{{ t('cookies.necessary_title') }}</h3>
                    <p class="cookie-dialog__category-description">{{ t('cookies.necessary_text') }}</p>
                </div>
                <span class="cookie-dialog__always">{{ t('cookies.always_on') }}</span>
            </li>
            <li class="cookie-dialog__category">
                <label for="cookie-analytics" class="cookie-dialog__category-text">
                    <span class="cookie-dialog__category-title">{{ t('cookies.analytics_title') }}</span>
                    <span class="cookie-dialog__category-description">{{ t('cookies.analytics_text') }}</span>
                </label>
                <span class="form-switch">
                    <input id="cookie-analytics" v-model="choice.analytics" type="checkbox" role="switch" class="form-switch__input" />
                </span>
            </li>
            <li class="cookie-dialog__category">
                <label for="cookie-marketing" class="cookie-dialog__category-text">
                    <span class="cookie-dialog__category-title">{{ t('cookies.marketing_title') }}</span>
                    <span class="cookie-dialog__category-description">{{ t('cookies.marketing_text') }}</span>
                </label>
                <span class="form-switch">
                    <input id="cookie-marketing" v-model="choice.marketing" type="checkbox" role="switch" class="form-switch__input" />
                </span>
            </li>
        </ul>

        <div class="cookie-dialog__actions">
            <button type="button" class="button button--ghost" @click="saveChoice">{{ t('cookies.save') }}</button>
            <button type="button" class="button button--primary" @click="rejectAll">{{ t('cookies.reject_all') }}</button>
            <button type="button" class="button button--primary" @click="acceptAll">{{ t('cookies.accept_all') }}</button>
        </div>
    </dialog>
</template>
