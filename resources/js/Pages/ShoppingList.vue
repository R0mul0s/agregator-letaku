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
import InstallPrompt from '@/Components/InstallPrompt.vue';
import ShoppingAdd from '@/Components/ShoppingAdd.vue';
import ShoppingGroups from '@/Components/ShoppingGroups.vue';
import WakeLockSwitch from '@/Components/WakeLockSwitch.vue';
import WatchSuggestions from '@/Components/WatchSuggestions.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { confirmDialog } from '@/lib/confirm';
import { debounce } from '@/lib/debounce';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { pendingChecks, queueCheck } from '@/lib/offlineChecks';
import { canSystemShare, SHARE_CONTENT, shareOrCopy } from '@/lib/share';
import { itemPrice, nameWithQuantity } from '@/lib/shoppingList';
import { showToast } from '@/lib/toast';
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
    /** Co si sem dává opakovaně a nehlídá (R148): { items, urls }. */
    watchSuggestions: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);
/** Text seznamu se na dotykovém zařízení posílá sdílením systému, na počítači kopíruje (R146). */
const textShareLabel = computed(() => (canSystemShare() ? t('shopping.share') : t('shopping.share_copy')));

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/**
 * Odškrtnutí a množství jdou asynchronně — synchronní návštěvy Inertie se navzájem ruší a rychle
 * po sobě odškrtnutá položka by se ztratila (R170).
 */
const KEEP_PAGE_ASYNC = { ...KEEP_PAGE, async: true };

/** Pauza po posledním klepnutí na −/+, než se množství pošle — rychlá klepnutí jedním požadavkem. */
const QUANTITY_SEND_DELAY_MS = 400;

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
        // Asynchronně — nepřeruší obnovení celé stránky po delší pauze (lib/pwa.js, R170)
        router.reload({ only: RELOAD_PROPS, async: true });
    }
}

onMounted(() => {
    updateOnline();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    // Odchod ze stránky během pauzy — poslední množství se pošle hned, ať se neztratí
    quantitySenders.forEach((send) => send.flush());
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
    document.removeEventListener('visibilitychange', onVisible);
});

/** Množství změněné klepnutím, které ještě nepotvrdil server: ID položky → množství (R170). */
const pendingQuantities = ref({});

/** Skupiny s odškrtnutím, které ještě čeká na odeslání (R66), a s množstvím z klepnutí. */
const displayGroups = computed(() =>
    props.groups.map((group) => ({
        ...group,
        items: group.items.map((item) => ({
            ...item,
            ...(item.id in pendingChecks.value ? { checked: pendingChecks.value[item.id] } : {}),
            ...(item.id in pendingQuantities.value ? { quantity: pendingQuantities.value[item.id] } : {}),
        })),
    })),
);

/** Kolik položek zbývá koupit (štítek v červeném pruhu, R144) — bez odškrtnutých a skončených. */
const toBuyCount = computed(() => displayGroups.value.reduce((sum, group) => sum + group.items.filter((item) => !item.checked && !item.expired).length, 0));

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
            ...KEEP_PAGE_ASYNC,
            onNetworkError: () => {
                queueCheck(item.id, checked);

                // Chybu sítě nehlásit toastem — odškrtnutí je zapamatované
                return false;
            },
        },
    );
}

/** Odeslání množství po položkách (každá má vlastní pauzu). */
const quantitySenders = new Map();

/**
 * Změní množství položky (R133); jen s připojením (tlačítka bez něj nejdou). Hned ho ukáže
 * a pošle až po pauze — dvě rychlá „+“ dají o dva kusy víc, ne dvakrát stejné číslo (R170).
 *
 * @param {object} item
 * @param {number} quantity
 */
function changeQuantity(item, quantity) {
    pendingQuantities.value = { ...pendingQuantities.value, [item.id]: quantity };
    if (!quantitySenders.has(item.id)) {
        quantitySenders.set(
            item.id,
            debounce((url, value) => {
                router.patch(url, { quantity: value }, {
                    ...KEEP_PAGE_ASYNC,
                    // Po odpovědi platí množství ze serveru — jen když mezitím nepřišlo další klepnutí
                    onFinish: () => {
                        if (pendingQuantities.value[item.id] === value) {
                            const rest = { ...pendingQuantities.value };
                            delete rest[item.id];
                            pendingQuantities.value = rest;
                        }
                    },
                });
            }, QUANTITY_SEND_DELAY_MS),
        );
    }
    quantitySenders.get(item.id)(item.quantityUrl, quantity);
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

    await shareOrCopy({ title: t('shopping.title'), text }, t('shopping.share_copied'), t('shopping.share_failed'), SHARE_CONTENT.SHOPPING_LIST);
}

/** Pošle odkaz na seznam (R130) — partner ho otevře i bez účtu a odškrtává. */
async function shareLink() {
    await shareOrCopy({ title: t('shopping.title'), text: t('shopping.share_link_text'), url: props.share.url }, t('shopping.share_link_copied'), t('shopping.share_link_failed'), SHARE_CONTENT.SHOPPING_LIST_LINK);
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

        <header class="page__header page-hero">
            <h1 class="page__title">{{ t('shopping.title') }}</h1>
            <p class="page__subtitle">{{ t('shopping.intro') }}</p>
            <ul v-if="toBuyCount" class="page-hero__pills">
                <li class="page-hero__pill">{{ t('shopping.hero_to_buy', { count: toBuyCount }) }}</li>
            </ul>
        </header>

        <!-- Přidání jako karta přes oblý konec červeného pruhu (R144) -->
        <ShoppingAdd class="card" :add="add" :max-quantity="maxQuantity" />
        <!-- Výzva k přidání na plochu pod kartou přes červený pruh (R139, R144) -->
        <InstallPrompt inline />
        <!-- Kupuje to opakovaně? Nabídnout hlídání (R148) -->
        <WatchSuggestions :suggestions="watchSuggestions" />

        <EmptyState v-if="!groups.length" :text="t('shopping.empty')" />

        <template v-else>
            <!-- Sdílení a nezhasínání displeje v kartě (R146) — dřív šedé obrysové pilulky -->
            <section class="card shopping-share" aria-labelledby="shopping-share-title">
                <h2 id="shopping-share-title" class="shopping-share__title">{{ t('shopping.share_title') }}</h2>
                <div class="shopping-share__buttons">
                    <button type="button" class="button button--primary" @click="shareLink">
                        <svg class="button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1" /><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" /></svg>
                        {{ t('shopping.share_link') }}
                    </button>
                    <button type="button" class="button button--soft" @click="shareAsText">
                        <svg class="button__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></svg>
                        {{ textShareLabel }}
                    </button>
                </div>
                <p class="shopping-share__hint">
                    {{ t('shopping.share_hint') }}
                    <button type="button" class="link-button" :disabled="!online" @click="renewShare">{{ t('shopping.share_renew') }}</button>
                </p>
                <WakeLockSwitch class="shopping-share__wake" />
            </section>

            <!-- Nadpis seznamu s úklidem — jemně červená tlačítka, mažou (R146) -->
            <div class="shopping-list-head">
                <h2 class="shopping-list-head__title">{{ t('shopping.list_title') }}</h2>
                <div v-if="online && (hasChecked || hasExpired)" class="shopping-list-head__actions">
                    <button v-if="hasChecked" type="button" class="button button--danger-soft" @click="clearChecked">{{ t('shopping.clear_checked') }}</button>
                    <button v-if="hasExpired" type="button" class="button button--danger-soft" @click="clearExpired">{{ t('shopping.clear_expired') }}</button>
                </div>
            </div>

            <p v-if="hasPending" class="notice notice--warning" role="status">{{ t('shopping.pending') }}</p>

            <ShoppingGroups :groups="displayGroups" :can-edit="online" :max-quantity="maxQuantity" @check="check" @remove="remove" @quantity="changeQuantity" />
        </template>
    </AppLayout>
</template>
