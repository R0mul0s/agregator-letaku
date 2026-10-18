<!--
    Aplikace v telefonu v Můj účet (R66) — přidání na plochu a upozornění na tomto zařízení
    (web push). Stav upozornění je vlastnost zařízení: prohlížeč zná svůj odběr, server
    seznam zařízení uživatele; zapnuté = odběr prohlížeče je mezi nimi. Ukládá se hned
    po přepnutí (R63) s toastem ze serveru. Dole verze aplikace a ruční kontrola aktualizací (R78).
    Stav a přepnutí upozornění jsou v lib/pushDevice.js (sdílí je průvodce prvním nastavením, R153).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import CheckboxField from '@/Components/CheckboxField.vue';
import { useTranslations } from '@/lib/i18n';
import { usePushDevice } from '@/lib/pushDevice';
import { installState, promptInstall } from '@/lib/pwaInstall';
import { checkForUpdate } from '@/lib/pwaUpdates';
import { showToast } from '@/lib/toast';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    /** Upozornění v telefonu { publicKey, urls: { store, destroy, test }, devices: [{ endpoint, device }] }; null = vypnutá na serveru. */
    push: { type: Object, default: null },
    /** Nasazená verze aplikace (R78); null = vývoj. */
    appVersion: { type: String, default: null },
});

const t = useTranslations();

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/** Upozornění na tomto zařízení (lib/pushDevice.js). */
const device = usePushDevice(() => props.push, t);
const enabled = device.enabled;
const unavailableReason = device.unavailableReason;
/** Aktualizace jdou zkontrolovat (běží service worker — ne s dev serverem Vite). */
const updatesSupported = computed(() => device.state.serviceWorker);
const ios = computed(() => device.state.ios);
const busy = computed(() => device.state.busy);
const checkingUpdate = ref(false);

/** Stav přepínače — po klepnutí hned nový (R62), po neúspěchu zpět podle skutečnosti. */
const checked = ref(false);
watch(enabled, (value) => (checked.value = value), { immediate: true });

/** Ostatní zařízení s upozorněním. */
const otherDevices = computed(() => (props.push?.devices ?? []).filter((item) => item.endpoint !== device.state.endpoint));

/**
 * Zapne nebo vypne upozornění na tomto zařízení; když se nepovede, přepínač vrátí.
 *
 * @param {boolean} on
 */
async function toggle(on) {
    if (!(await device.toggle(on))) {
        checked.value = enabled.value;
    }
}

/** Pošle zkušební upozornění na toto zařízení. */
function sendTest() {
    router.post(props.push.urls.test, { endpoint: device.state.endpoint }, KEEP_PAGE);
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
                {{ t('push.other_devices', { devices: otherDevices.map((item) => item.device).join(', ') }) }}
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
