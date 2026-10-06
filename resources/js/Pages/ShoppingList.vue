<!--
    Nákupní seznam (R61) — akce po obchodech, v obchodě se odškrtávají. Odškrtnuté jdou
    v obchodě na konec a po nákupu se smažou jedním tlačítkem; skončená akce zůstává označená.
    Akce, která ještě nezačala, je za platnými s „platí až od“ a odškrtnutí se potvrzuje (R76).

    V obchodě (R66): odškrtávat jde i bez signálu — odškrtnutí počká v prohlížeči a odešle se,
    až je připojení (lib/offlineChecks.js); mazání bez připojení nejde. Seznam jde poslat
    (sdílení systému, jinak zkopírovat) a displej při nakupování nemusí zhasínat.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/lib/clipboard';
import { confirmDialog } from '@/lib/confirm';
import { formatDate, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { packageLabel } from '@/lib/offer';
import { pendingChecks, queueCheck } from '@/lib/offlineChecks';
import { showToast } from '@/lib/toast';
import { useWakeLock } from '@/lib/wakeLock';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    /** Obchody s položkami [{ chain, chainName, items: [{ id, checked, expired, userPrice, offer, updateUrl, deleteUrl }] }]. */
    groups: { type: Array, required: true },
    clearCheckedUrl: { type: String, required: true },
    /** Je co mazat tlačítkem „Smazat odškrtnuté“. */
    hasChecked: { type: Boolean, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);
const wakeLock = useWakeLock();

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/** Je připojení? Bez něj jde jen odškrtávat. */
const online = ref(true);

/** Změna připojení. */
function updateOnline() {
    online.value = navigator.onLine;
}

onMounted(() => {
    updateOnline();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
});

/** Skupiny s odškrtnutím, které ještě čeká na odeslání (R66). */
const displayGroups = computed(() =>
    props.groups.map((group) => ({
        ...group,
        items: group.items.map((item) => (item.id in pendingChecks.value ? { ...item, checked: pendingChecks.value[item.id] } : item)),
    })),
);

/** Čekají odškrtnutí na připojení? */
const hasPending = computed(() => Object.keys(pendingChecks.value).length > 0);

/**
 * Odškrtne položku, nebo odškrtnutí zruší. Bez připojení (nebo když požadavek nedojde)
 * se odškrtnutí zapamatuje a odešle později. Akce, která ještě nezačala (R76), se odškrtne
 * až po potvrzení — za akční cenu ji v obchodě zatím nekoupíte.
 *
 * @param {object} item
 * @param {boolean} checked
 * @param {HTMLInputElement} input Zaškrtávátko — po zrušení potvrzení se vrátí
 */
async function check(item, checked, input) {
    if (checked && item.offer.startsInDays) {
        const confirmed = await confirmDialog({
            title: t('shopping.upcoming_confirm_title'),
            message: t('shopping.upcoming_confirm', { name: item.offer.name, date: formatDate(item.offer.validFrom, locale.value) }),
            confirmLabel: t('shopping.upcoming_confirm_label'),
        });
        if (!confirmed) {
            input.checked = false;

            return;
        }
    }

    if (!navigator.onLine) {
        queueCheck(item.id, checked);

        return;
    }

    router.patch(
        item.updateUrl,
        { checked },
        {
            ...KEEP_PAGE,
            onNetworkError: () => {
                queueCheck(item.id, checked);

                // Chybu sítě nehlásit toastem — odškrtnutí je zapamatované
                return false;
            },
        },
    );
}

/**
 * Smaže položku ze seznamu.
 *
 * @param {object} item
 */
function remove(item) {
    router.delete(item.deleteUrl, KEEP_PAGE);
}

/** Po potvrzení smaže všechny odškrtnuté položky. */
async function clearChecked(url) {
    const confirmed = await confirmDialog({
        title: t('shopping.clear_checked_confirm_title'),
        message: t('shopping.clear_checked_confirm'),
        confirmLabel: t('shopping.clear_checked_confirm_label'),
    });
    if (confirmed) {
        router.delete(url, KEEP_PAGE);
    }
}

/**
 * Cena položky: u akce na více kusů text akce („3 za cenu 2“), jinak cena, kterou uživatel zaplatí.
 *
 * @param {object} item
 * @returns {string}
 */
function priceLabel(item) {
    if (item.offer.offerType === 'multibuy' && item.offer.promotionText) {
        return item.offer.promotionText;
    }

    return formatPrice(item.userPrice ?? item.offer.price, locale.value);
}

/**
 * Kolik položek obchodu zbývá koupit.
 *
 * @param {object} group
 * @returns {number}
 */
function remaining(group) {
    return group.items.filter((item) => !item.checked).length;
}

/**
 * Řádek seznamu ke sdílení; u akce, která ještě nezačala, i od kdy platí (R76).
 *
 * @param {object} item
 * @returns {string}
 */
function shareLine(item) {
    const replace = { name: item.offer.name, price: priceLabel(item) };

    return item.offer.startsInDays
        ? t('shopping.share_line_upcoming', { ...replace, date: formatDate(item.offer.validFrom, locale.value) })
        : t('shopping.share_line', replace);
}

/**
 * Seznam jako text ke sdílení: co zbývá koupit, po obchodech, s cenou.
 *
 * @returns {string}
 */
function shareText() {
    return displayGroups.value
        .map((group) => ({ group, items: group.items.filter((item) => !item.checked && !item.expired) }))
        .filter(({ items }) => items.length)
        .map(({ group, items }) => [`${group.chainName}:`, ...items.map(shareLine)].join('\n'))
        .join('\n\n');
}

/** Pošle seznam sdílením systému (rodině do chatu); bez něj ho zkopíruje. */
async function share() {
    const text = shareText();
    if (!text) {
        showToast(t('shopping.share_empty'));

        return;
    }

    if (navigator.share) {
        try {
            await navigator.share({ title: t('shopping.title'), text });
        } catch {
            // Uživatel sdílení zavřel
        }

        return;
    }

    await copyText(text, t('shopping.share_copied'), t('shopping.share_failed'));
}
</script>

<template>
    <AppLayout>
        <Head :title="t('shopping.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('shopping.title') }}</h1>
            <p class="page__subtitle">{{ t('shopping.intro') }}</p>
        </header>

        <EmptyState v-if="!groups.length" :text="t('shopping.empty')" />

        <template v-else>
            <div class="watch-groups__toolbar shopping-toolbar">
                <button type="button" class="button button--ghost" @click="share">{{ t('shopping.share') }}</button>
                <button
                    v-if="wakeLock.supported"
                    type="button"
                    class="button button--ghost shopping-toolbar__wake"
                    :class="{ 'shopping-toolbar__wake--on': wakeLock.enabled.value }"
                    :aria-pressed="wakeLock.enabled.value ? 'true' : 'false'"
                    @click="wakeLock.toggle"
                >
                    {{ t('shopping.wake_lock') }}
                </button>
                <button v-if="hasChecked && online" type="button" class="button button--ghost" @click="clearChecked(clearCheckedUrl)">{{ t('shopping.clear_checked') }}</button>
            </div>

            <p v-if="hasPending" class="notice notice--warning" role="status">{{ t('shopping.pending') }}</p>

            <section v-for="group in displayGroups" :key="group.chain" class="card shopping-group">
                <h2 class="shopping-group__title">
                    <ChainLogo :chain="group.chain" with-name />
                    <span class="shopping-group__remaining">{{ t('shopping.remaining', { count: remaining(group) }) }}</span>
                </h2>

                <ul class="shopping-list">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        class="shopping-item"
                        :class="{ 'shopping-item--checked': item.checked, 'shopping-item--expired': item.expired }"
                    >
                        <label class="shopping-item__check">
                            <input type="checkbox" class="form-checkbox__input" :checked="item.checked" @change="check(item, $event.target.checked, $event.target)" />
                            <span class="visually-hidden">{{ t('shopping.check', { name: item.offer.name }) }}</span>
                        </label>
                        <div class="shopping-item__body">
                            <p class="shopping-item__name">{{ item.offer.name }}</p>
                            <p class="shopping-item__meta">
                                <span v-if="packageLabel(item.offer, locale, t)">{{ packageLabel(item.offer, locale, t) }} · </span>
                                <span v-if="item.expired" class="shopping-item__expired">{{ t('shopping.expired') }}</span>
                                <!-- Ještě nezačala (R76) — v obchodě zatím za akční cenu není -->
                                <span v-else-if="item.offer.startsInDays" class="shopping-item__upcoming">{{
                                    t('shopping.starts', { date: formatDate(item.offer.validFrom, locale) })
                                }}</span>
                                <span v-else>{{ t('shopping.valid_to', { date: formatDate(item.offer.validTo, locale) }) }}</span>
                            </p>
                        </div>
                        <span class="shopping-item__price">{{ priceLabel(item) }}</span>
                        <button
                            type="button"
                            class="icon-button icon-button--danger"
                            :disabled="!online"
                            :title="t('shopping.remove', { name: item.offer.name })"
                            @click="remove(item)"
                        >
                            <!-- Koš -->
                            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
                            <span class="visually-hidden">{{ t('shopping.remove', { name: item.offer.name }) }}</span>
                        </button>
                    </li>
                </ul>
            </section>
        </template>
    </AppLayout>
</template>
