<!--
    Aplikace v telefonu v Můj účet (R66) — přidání na plochu a upozornění na tomto zařízení
    (web push). Stav upozornění je vlastnost zařízení: prohlížeč zná svůj odběr, server
    seznam zařízení uživatele; zapnuté = odběr prohlížeče je mezi nimi. Ukládá se hned
    po přepnutí (R63) s toastem ze serveru. Dole verze aplikace a ruční kontrola aktualizací (R78).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import CheckboxField from '@/Components/CheckboxField.vue';
import { useTranslations } from '@/lib/i18n';
import { currentSubscription, isPushSupported, notificationPermission, subscribe } from '@/lib/push';
import { serviceWorkerRegistration } from '@/lib/pwa';
import { installState, isIos, promptInstall } from '@/lib/pwaInstall';
import { checkForUpdate } from '@/lib/pwaUpdates';
import { showToast } from '@/lib/toast';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    /** Upozornění v telefonu { publicKey, urls: { store, destroy, test }, devices: [{ endpoint, device }] }; null = vypnutá na serveru. */
    push: { type: Object, default: null },
    /** Nasazená verze aplikace (R78); null = vývoj. */
    appVersion: { type: String, default: null },
});

const t = useTranslations();

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/** Zjišťuje se až v prohlížeči. */
const ios = ref(false);
const pushSupported = ref(false);
const permission = ref('default');
/** Adresa odběru tohoto prohlížeče; null = neodebírá. */
const endpoint = ref(null);
const busy = ref(false);
/** Aktualizace jdou zkontrolovat (běží service worker — ne s dev serverem Vite). */
const updatesSupported = ref(false);
const checkingUpdate = ref(false);

/** Upozornění na tomto zařízení — odběr prohlížeče, který server zná. */
const enabled = computed(() => endpoint.value !== null && (props.push?.devices ?? []).some((device) => device.endpoint === endpoint.value));

/** Stav přepínače — po klepnutí hned nový (R62), po neúspěchu zpět podle skutečnosti. */
const checked = ref(false);
watch(enabled, (value) => (checked.value = value), { immediate: true });

/** Ostatní zařízení s upozorněním. */
const otherDevices = computed(() => (props.push?.devices ?? []).filter((device) => device.endpoint !== endpoint.value));

/** Proč upozornění na tomto zařízení zapnout nejde (klíč textu), null = jde. */
const unavailableReason = computed(() => {
    if (!pushSupported.value) {
        return ios.value && !installState.standalone ? 'push.ios_install_first' : 'push.unsupported';
    }

    return permission.value === 'denied' ? 'push.denied' : null;
});

onMounted(async () => {
    ios.value = isIos();
    updatesSupported.value = (await serviceWorkerRegistration()) !== null;
    pushSupported.value = isPushSupported() && updatesSupported.value;
    permission.value = notificationPermission();
    if (pushSupported.value) {
        endpoint.value = (await currentSubscription())?.endpoint ?? null;
    }
});

/**
 * Zapne nebo vypne upozornění na tomto zařízení.
 *
 * @param {boolean} on
 */
async function toggle(on) {
    busy.value = true;
    try {
        if (on) {
            await enable();
        } else {
            await disable();
        }
    } catch {
        showToast(t('push.failed'));
        busy.value = false;
        checked.value = enabled.value;
    }
}

/** Povolení, odběr u push služby prohlížeče a uložení na serveru. */
async function enable() {
    const subscription = await subscribe(props.push.publicKey);
    permission.value = notificationPermission();
    if (!subscription) {
        busy.value = false;
        checked.value = false;

        return;
    }

    endpoint.value = subscription.endpoint;
    router.post(props.push.urls.store, subscription.toJSON(), { ...KEEP_PAGE, onFinish: () => (busy.value = false) });
}

/** Zruší odběr v prohlížeči i na serveru. */
async function disable() {
    const subscription = await currentSubscription();
    const current = subscription?.endpoint ?? endpoint.value;
    await subscription?.unsubscribe();
    endpoint.value = null;
    router.delete(props.push.urls.destroy, { ...KEEP_PAGE, data: { endpoint: current }, onFinish: () => (busy.value = false) });
}

/** Pošle zkušební upozornění na toto zařízení. */
function sendTest() {
    router.post(props.push.urls.test, { endpoint: endpoint.value }, KEEP_PAGE);
}

/** Zeptá se na novou verzi aplikace; nalezená se hned načte (lib/pwa.js). */
async function checkUpdate() {
    checkingUpdate.value = true;
    const found = await checkForUpdate({ applyWhenReady: true });
    checkingUpdate.value = false;
    showToast(t(found ? 'pwa.update_found' : 'pwa.update_current'));
}
</script>

<template>
    <div id="telefon" class="account-section__part phone-app">
        <h3 class="account-section__subtitle">{{ t('pwa.settings_title') }}</h3>

        <p v-if="installState.standalone" class="form-field__hint">{{ t('pwa.installed') }}</p>
        <template v-else>
            <p class="form-field__hint">{{ t('pwa.install_text') }}</p>
            <button v-if="installState.promptEvent" type="button" class="button button--ghost account-section__action" @click="promptInstall">
                {{ t('pwa.install') }}
            </button>
            <p v-else class="form-field__hint">{{ ios ? t('pwa.install_ios_steps') : t('pwa.install_browser_menu') }}</p>
        </template>

        <template v-if="push">
            <h3 class="account-section__subtitle phone-app__push-title">{{ t('push.title') }}</h3>
            <p class="form-field__hint">{{ t('push.hint') }}</p>
            <p v-if="unavailableReason" class="account-section__warning">{{ t(unavailableReason) }}</p>
            <template v-else>
                <CheckboxField id="push_enabled" v-model="checked" :label="t('push.enable')" :disabled="busy" @update:model-value="toggle" />
                <button v-if="enabled" type="button" class="button button--ghost account-section__action" @click="sendTest">{{ t('push.test') }}</button>
            </template>
            <p v-if="otherDevices.length" class="form-field__hint">
                {{ t('push.other_devices', { devices: otherDevices.map((device) => device.device).join(', ') }) }}
            </p>
        </template>

        <div v-if="appVersion || updatesSupported" class="phone-app__version">
            <p v-if="appVersion" class="form-field__hint">{{ t('pwa.version', { version: appVersion }) }}</p>
            <button v-if="updatesSupported" type="button" class="button button--ghost account-section__action" :disabled="checkingUpdate" @click="checkUpdate">
                {{ checkingUpdate ? t('pwa.update_checking') : t('pwa.update_check') }}
            </button>
        </div>
    </div>
</template>
