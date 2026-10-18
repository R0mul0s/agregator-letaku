/**
 * Upozornění v telefonu na tomto zařízení (R66) — stav a zapnutí / vypnutí pro Můj účet
 * (PhoneAppSettings.vue) i průvodce prvním nastavením (R153). Stav je vlastnost zařízení:
 * prohlížeč zná svůj odběr, server seznam zařízení uživatele; zapnuté = odběr prohlížeče
 * je mezi nimi. Vyčleněno z PhoneAppSettings.vue.
 *
 * @author Roman Hlaváček
 * @created 2026-10-18
 */
import { currentSubscription, isPushSupported, notificationPermission, subscribe } from '@/lib/push';
import { serviceWorkerRegistration } from '@/lib/pwa';
import { installState, isIos } from '@/lib/pwaInstall';
import { showToast } from '@/lib/toast';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, reactive } from 'vue';

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/**
 * Stav upozornění na zařízení a jejich přepnutí.
 *
 * @param {() => ({ publicKey: string, urls: { store: string, destroy: string }, devices: { endpoint: string }[] } | null)} push Nastavení ze serveru; null = vypnutá
 * @param {(key: string) => string} t
 */
export function usePushDevice(push, t) {
    /** Vše se zjišťuje až v prohlížeči; serviceWorker = běží (ne s dev serverem Vite). */
    const state = reactive({ ios: false, serviceWorker: false, supported: false, permission: 'default', endpoint: null, busy: false });

    /** Upozornění na tomto zařízení — odběr prohlížeče, který server zná. */
    const enabled = computed(() => state.endpoint !== null && (push()?.devices ?? []).some((device) => device.endpoint === state.endpoint));

    /** Proč upozornění na tomto zařízení zapnout nejde (klíč textu), null = jde. */
    const unavailableReason = computed(() => {
        if (!state.supported) {
            return state.ios && !installState.standalone ? 'push.ios_install_first' : 'push.unsupported';
        }

        return state.permission === 'denied' ? 'push.denied' : null;
    });

    onMounted(async () => {
        state.ios = isIos();
        state.serviceWorker = (await serviceWorkerRegistration()) !== null;
        state.supported = isPushSupported() && state.serviceWorker;
        state.permission = notificationPermission();
        if (state.supported) {
            state.endpoint = (await currentSubscription())?.endpoint ?? null;
        }
    });

    /**
     * Zapne nebo vypne upozornění na tomto zařízení; selhání ohlásí toastem.
     *
     * @param {boolean} on
     * @returns {Promise<boolean>} Odešel požadavek na server?
     */
    async function toggle(on) {
        state.busy = true;
        try {
            return on ? await enable() : await disable();
        } catch {
            showToast(t('push.failed'));
            state.busy = false;

            return false;
        }
    }

    /** Povolení, odběr u push služby prohlížeče a uložení na serveru. */
    async function enable() {
        const subscription = await subscribe(push().publicKey);
        state.permission = notificationPermission();
        if (!subscription) {
            state.busy = false;

            return false;
        }

        state.endpoint = subscription.endpoint;
        router.post(push().urls.store, subscription.toJSON(), { ...KEEP_PAGE, onFinish: () => (state.busy = false) });

        return true;
    }

    /** Zruší odběr v prohlížeči i na serveru. */
    async function disable() {
        const subscription = await currentSubscription();
        const current = subscription?.endpoint ?? state.endpoint;
        await subscription?.unsubscribe();
        state.endpoint = null;
        router.delete(push().urls.destroy, { ...KEEP_PAGE, data: { endpoint: current }, onFinish: () => (state.busy = false) });

        return true;
    }

    return { state, enabled, unavailableReason, toggle };
}
