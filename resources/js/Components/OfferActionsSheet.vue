<!--
    Okno „Tohle ne“ a hlášení chyby v akci (R125) — jedno pro celou aplikaci (AppLayout), obsah
    řídí lib/offerActions.js. S hlídanou položkou (Moje slevy): skrýt jen tuhle akci, nebo klepnutím
    na slova z názvu skrýt i podobné akce; pod tím sbalené hlášení chyby. Bez položky (tři tečky
    na kartě kdekoli jinde) rovnou formulář hlášení.

    Okno se po akci nezavírá — stránka se pod ním obnoví, okno potvrdí výsledek samo (bez toastu,
    ten by byl pod ztmaveným pozadím) a nabídne vrácení: skrytou akci tlačítkem „Vrátit“, slovo
    dalším klepnutím. Jde tak vyloučit víc slov za sebou nebo dopsat hlášení. Zavírá ho jen křížek,
    Esc nebo klepnutí vedle.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import { useTranslations } from '@/lib/i18n';
import { closeOfferActions, INLINE_FIELD, offerActionsState } from '@/lib/offerActions';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';

const t = useTranslations();
const page = usePage();
const idPrefix = useId();

/** Otevřené okno — zavření křížkem, Esc nebo klepnutím vedle jde do sdíleného stavu. */
const open = computed({
    get: () => offerActionsState.open,
    set: (value) => {
        if (!value) {
            closeOfferActions();
        }
    },
});

const offer = computed(() => offerActionsState.offer);
const watchItem = computed(() => offerActionsState.watchItem);

/** Hlášení chyb pro přihlášeného { storeUrl, reasons }, nepřihlášený null. */
const reports = computed(() => page.props.offerReports);

const title = computed(() => (watchItem.value ? t('offer_actions.title_watch') : t('offer_actions.title_report')));

/** Akce je v tomhle otevření okna skrytá. */
const offerHidden = ref(false);

/** Slova vyloučená v tomhle otevření okna — u slova ✓, další klepnutí ho vrátí. */
const excludedWords = ref([]);

/** Potvrzení poslední akce v okně (role="status" ho přečte čtečce). */
const status = ref('');
const processing = ref(false);

/** Formulář hlášení — v okně „Tohle ne“ sbalený pod tlačítkem, jinde rovnou otevřený. */
const reportOpen = ref(false);
const reportSent = ref(false);
const form = useForm({ offer_id: null, reason: '', note: '' });

// Každé otevření začíná znovu: nic skrytého, prázdný formulář pro otevřenou akci
watch(
    () => offerActionsState.open,
    (isOpen) => {
        if (isOpen) {
            offerHidden.value = false;
            excludedWords.value = [];
            status.value = '';
            reportOpen.value = !offerActionsState.watchItem;
            reportSent.value = false;
            form.reset();
            form.clearErrors();
            form.offer_id = offerActionsState.offer?.id ?? null;
        }
    },
);

/**
 * Pošle změnu z okna (bez toastu); po úspěchu zavolá `onDone`, chybu ukáže v okně.
 *
 * @param {'post'|'delete'} method
 * @param {string} url
 * @param {object} data
 * @param {() => void} onDone
 */
function send(method, url, data, onDone) {
    router.visit(url, {
        method,
        data: { ...data, [INLINE_FIELD]: true },
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: onDone,
        onError: (errors) => (status.value = Object.values(errors)[0]),
    });
}

/** Skryje jen tuhle akci u hlídané položky. */
function hideOffer() {
    send('post', watchItem.value.hideOfferUrl, { offer_id: offer.value.id }, () => {
        offerHidden.value = true;
        status.value = t('offer_actions.offer_hidden_status', { name: watchItem.value.name });
    });
}

/** Vrátí skrytou akci — adresa je adresa skrytí + ID akce (routa watch-items.hidden-offers.destroy). */
function restoreOffer() {
    send('delete', `${watchItem.value.hideOfferUrl}/${offer.value.id}`, {}, () => {
        offerHidden.value = false;
        status.value = t('offer_actions.offer_restored_status', { name: watchItem.value.name });
    });
}

/**
 * Vyloučí slovo (skryje i podobné akce), u vyloučeného ho vrátí — adresa vrácení je adresa
 * vyloučení + slovo (routa watch-items.excluded-words.destroy).
 *
 * @param {string} word
 */
function toggleWord(word) {
    const params = { word, name: watchItem.value.name };
    if (excludedWords.value.includes(word)) {
        send('delete', `${watchItem.value.excludeWordUrl}/${encodeURIComponent(word)}`, {}, () => {
            excludedWords.value = excludedWords.value.filter((excluded) => excluded !== word);
            status.value = t('offer_actions.word_restored_status', params);
        });

        return;
    }

    send('post', watchItem.value.excludeWordUrl, { word }, () => {
        excludedWords.value = [...excludedWords.value, word];
        status.value = t('offer_actions.word_excluded_status', params);
    });
}

/** Odešle hlášení chyby; chyby polí zůstanou ve formuláři, po odeslání poděkování v okně. */
function submitReport() {
    form.transform((data) => ({ ...data, [INLINE_FIELD]: true })).post(reports.value.storeUrl, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (reportSent.value = true),
    });
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="title">
        <template v-if="offer">
            <p class="offer-actions__offer">
                <strong>{{ offer.name }}</strong>
                <span class="offer-actions__chain">{{ offer.chainName }}</span>
            </p>

            <section v-if="watchItem" class="offer-actions__section">
                <p class="offer-actions__lead">{{ t('offer_actions.lead', { name: watchItem.name }) }}</p>
                <button v-if="!offerHidden" type="button" class="button button--soft" :disabled="processing" @click="hideOffer">
                    {{ t('offer_actions.hide_offer') }}
                </button>
                <p v-else class="offer-actions__done">
                    <span>✓ {{ t('offer_actions.offer_hidden') }}</span>
                    <button type="button" class="link offer-actions__link" :disabled="processing" @click="restoreOffer">{{ t('offer_actions.restore') }}</button>
                </p>

                <template v-if="offer.excludeWords?.length">
                    <h3 class="offer-actions__heading">{{ t('offer_actions.similar_title') }}</h3>
                    <p class="form-field__hint">{{ t('offer_actions.similar_hint', { name: watchItem.name }) }}</p>
                    <div class="offer-actions__words">
                        <button
                            v-for="word in offer.excludeWords"
                            :key="word"
                            type="button"
                            class="search-chip"
                            :class="{ 'search-chip--on': excludedWords.includes(word) }"
                            :aria-pressed="excludedWords.includes(word) ? 'true' : 'false'"
                            :aria-label="excludedWords.includes(word) ? t('offer_actions.excluded_word', { word }) : t('offer_actions.exclude_word', { word })"
                            :disabled="processing"
                            @click="toggleWord(word)"
                        >
                            <span v-if="excludedWords.includes(word)" aria-hidden="true">✓</span>
                            {{ word }}
                        </button>
                    </div>
                </template>
                <!-- Potvrzení poslední akce; prvek je tu vždy, aby čtečka změnu ohlásila -->
                <p class="offer-actions__status" role="status">{{ status }}</p>
            </section>

            <section v-if="reports" class="offer-actions__section offer-actions__section--report">
                <p v-if="reportSent" class="notice notice--success offer-actions__thanks" role="status">{{ t('offer_actions.report_sent') }}</p>
                <!-- Po klepnutí ho nahradí formulář -->
                <button v-else-if="watchItem && !reportOpen" type="button" class="link offer-actions__link" @click="reportOpen = true">
                    {{ t('offer_actions.report_toggle') }}
                </button>

                <form v-else class="form" novalidate @submit.prevent="submitReport">
                    <p class="form-field__hint">{{ t('offer_reports.intro') }}</p>
                    <fieldset class="account-choice">
                        <legend class="form-field__label">{{ t('offer_reports.reason') }}</legend>
                        <label
                            v-for="reason in reports.reasons"
                            :key="reason.value"
                            class="account-choice__option"
                            :class="{ 'account-choice__option--selected': form.reason === reason.value }"
                        >
                            <input
                                v-model="form.reason"
                                type="radio"
                                :name="`${idPrefix}-reason`"
                                :value="reason.value"
                                class="form-checkbox__input"
                                :aria-invalid="form.errors.reason ? 'true' : undefined"
                                :aria-describedby="form.errors.reason ? `${idPrefix}-reason-error` : undefined"
                            />
                            <span class="account-choice__label">{{ reason.label }}</span>
                        </label>
                        <p v-if="form.errors.reason" :id="`${idPrefix}-reason-error`" class="form-field__error" role="alert">{{ form.errors.reason }}</p>
                    </fieldset>

                    <div class="form-field">
                        <label :for="`${idPrefix}-note`" class="form-field__label">{{ t('offer_reports.note') }}</label>
                        <textarea
                            :id="`${idPrefix}-note`"
                            v-model="form.note"
                            class="form-field__input offer-actions__note"
                            :class="{ 'form-field__input--invalid': form.errors.note }"
                            :aria-invalid="form.errors.note ? 'true' : undefined"
                            :aria-describedby="`${idPrefix}-note-hint`"
                        ></textarea>
                        <p :id="`${idPrefix}-note-hint`" class="form-field__hint">{{ t('offer_reports.note_hint') }}</p>
                        <p v-if="form.errors.note" class="form-field__error" role="alert">{{ form.errors.note }}</p>
                    </div>

                    <div class="form__actions">
                        <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('offer_reports.submit') }}</button>
                    </div>
                </form>
            </section>
        </template>
    </BottomSheet>
</template>
