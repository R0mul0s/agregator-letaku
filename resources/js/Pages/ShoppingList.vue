<!--
    Nákupní seznam (R61) — akce i vlastní položky bez akce (R130) po obchodech, v obchodě se
    odškrtávají. Nahoře pole s našeptávačem akcí, kterým jde přidat i zboží, které ve slevě
    není („Almette“). Odškrtnuté jdou v obchodě na konec a po nákupu se smažou jedním
    tlačítkem; skončená akce zůstává označená. Akce, která ještě nezačala, je za platnými
    s „platí až od“ a odškrtnutí se potvrzuje (R76).

    V obchodě (R66): odškrtávat jde i bez signálu — odškrtnutí počká v prohlížeči a odešle se,
    až je připojení (lib/offlineChecks.js); mazání bez připojení nejde. Seznam jde poslat jako
    text, nebo sdílet odkazem (R130) — kdo ho má, seznam vidí a odškrtává; po návratu na stránku
    se seznam načte znovu, ať jsou vidět jeho odškrtnutí. Displej při nakupování nemusí zhasínat.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import ShoppingAdd from '@/Components/ShoppingAdd.vue';
import ShoppingGroups from '@/Components/ShoppingGroups.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { confirmDialog } from '@/lib/confirm';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { pendingChecks, queueCheck } from '@/lib/offlineChecks';
import { shareOrCopy } from '@/lib/share';
import { itemPrice, nameWithQuantity } from '@/lib/shoppingList';
import { showToast } from '@/lib/toast';
import { useWakeLock } from '@/lib/wakeLock';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    /** Obchody s položkami [{ chain, chainName, items: [{ id, name, checked, expired, userPrice, offer|null, updateUrl, deleteUrl }] }]. */
    groups: { type: Array, required: true },
    clearCheckedUrl: { type: String, required: true },
    /** Je co mazat tlačítkem „Smazat odškrtnuté“. */
    hasChecked: { type: Boolean, required: true },
    /** Úklid skončených akcí (R130): adresa a jestli nějaká je. */
    clearExpiredUrl: { type: String, required: true },
    hasExpired: { type: Boolean, required: true },
    /** Nejvyšší množství položky (R133). */
    maxQuantity: { type: Number, required: true },
    /** Přidání (R130): { customUrl, suggestionsUrl, suggestMinLength, nameMaxLength, chains }. */
    add: { type: Object, required: true },
    /** Odkaz ke sdílení (R130): { url, renewUrl }. */
    share: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);
const wakeLock = useWakeLock();

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/** Props, které se po návratu na stránku načtou znovu — odškrtnutí ze sdíleného odkazu. */
const RELOAD_PROPS = ['groups', 'hasChecked', 'hasExpired'];

/** Je připojení? Bez něj jde jen odškrtávat. */
const online = ref(true);

/** Změna připojení. */
function updateOnline() {
    online.value = navigator.onLine;
}

/** Návrat do okna: načíst seznam znovu, ať jsou vidět odškrtnutí ze sdíleného odkazu (R130). */
function onVisible() {
    if (document.visibilityState === 'visible' && navigator.onLine) {
        router.reload({ only: RELOAD_PROPS });
    }
}

onMounted(() => {
    updateOnline();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
    document.removeEventListener('visibilitychange', onVisible);
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
 * se odškrtnutí zapamatuje a odešle později.
 *
 * @param {object} item
 * @param {boolean} checked
 */
function check(item, checked) {
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
 * Změní množství položky (R133); jen s připojením (tlačítka bez něj nejdou).
 *
 * @param {object} item
 * @param {number} quantity
 */
function changeQuantity(item, quantity) {
    router.patch(item.quantityUrl, { quantity }, KEEP_PAGE);
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
async function clearChecked() {
    const confirmed = await confirmDialog({
        title: t('shopping.clear_checked_confirm_title'),
        message: t('shopping.clear_checked_confirm'),
        confirmLabel: t('shopping.clear_checked_confirm_label'),
    });
    if (confirmed) {
        router.delete(props.clearCheckedUrl, KEEP_PAGE);
    }
}

/** Po potvrzení smaže akce, které skončily (R130); vlastní položky zůstanou. */
async function clearExpired() {
    const confirmed = await confirmDialog({
        title: t('shopping.clear_expired_confirm_title'),
        message: t('shopping.clear_expired_confirm'),
        confirmLabel: t('shopping.clear_expired_confirm_label'),
    });
    if (confirmed) {
        router.delete(props.clearExpiredUrl, KEEP_PAGE);
    }
}

/**
 * Řádek seznamu ke sdílení; u akce cena a u akce, která ještě nezačala, i od kdy platí (R76).
 * Vlastní položka (R130) jen názvem; množství před názvem („2× mléko“, R133).
 *
 * @param {object} item
 * @returns {string}
 */
function shareLine(item) {
    if (!item.offer) {
        return t('shopping.share_line_custom', { name: nameWithQuantity(item) });
    }
    const replace = { name: nameWithQuantity(item), price: itemPrice(item, locale.value, false) };

    return item.offer.startsInDays
        ? t('shopping.share_line_upcoming', { ...replace, date: formatDate(item.offer.validFrom, locale.value) })
        : t('shopping.share_line', replace);
}

/**
 * Seznam jako text ke sdílení: co zbývá koupit, po obchodech, s cenou; skončené akce ne
 * (akční cena neplatí — uklidí je „Smazat skončené akce“).
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

/** Pošle seznam jako text sdílením systému (rodině do chatu); bez něj ho zkopíruje. */
async function shareAsText() {
    const text = shareText();
    if (!text) {
        showToast(t('shopping.share_empty'));

        return;
    }

    await shareOrCopy({ title: t('shopping.title'), text }, t('shopping.share_copied'), t('shopping.share_failed'));
}

/** Pošle odkaz na seznam (R130) — partner ho otevře i bez účtu a odškrtává. */
async function shareLink() {
    await shareOrCopy({ title: t('shopping.title'), text: t('shopping.share_link_text'), url: props.share.url }, t('shopping.share_link_copied'), t('shopping.share_link_failed'));
}

/** Po potvrzení zruší dosud poslané odkazy — vznikne nový. */
async function renewShare() {
    const confirmed = await confirmDialog({
        title: t('shopping.share_renew_confirm_title'),
        message: t('shopping.share_renew_confirm'),
        confirmLabel: t('shopping.share_renew_confirm_label'),
    });
    if (confirmed) {
        router.post(props.share.renewUrl, {}, KEEP_PAGE);
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="t('shopping.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('shopping.title') }}</h1>
            <p class="page__subtitle">{{ t('shopping.intro') }}</p>
        </header>

        <ShoppingAdd :add="add" :max-quantity="maxQuantity" />

        <EmptyState v-if="!groups.length" :text="t('shopping.empty')" />

        <template v-else>
            <div class="watch-groups__toolbar shopping-toolbar">
                <button type="button" class="button button--ghost" @click="shareLink">{{ t('shopping.share_link') }}</button>
                <button type="button" class="button button--ghost" @click="shareAsText">{{ t('shopping.share') }}</button>
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
                <button v-if="hasChecked && online" type="button" class="button button--ghost" @click="clearChecked">{{ t('shopping.clear_checked') }}</button>
                <button v-if="hasExpired && online" type="button" class="button button--ghost" @click="clearExpired">{{ t('shopping.clear_expired') }}</button>
            </div>
            <p class="shopping-share-hint">
                {{ t('shopping.share_hint') }}
                <button type="button" class="link-button" :disabled="!online" @click="renewShare">{{ t('shopping.share_renew') }}</button>
            </p>

            <p v-if="hasPending" class="notice notice--warning" role="status">{{ t('shopping.pending') }}</p>

            <ShoppingGroups :groups="displayGroups" :can-edit="online" :max-quantity="maxQuantity" @check="check" @remove="remove" @quantity="changeQuantity" />
        </template>
    </AppLayout>
</template>
