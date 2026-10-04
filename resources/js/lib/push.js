/**
 * Upozornění v telefonu — web push (R66): podpora prohlížeče, povolení a odběr u push
 * služby prohlížeče. Odběr (adresa a klíče) pak uloží server (PushSubscriptionController)
 * a upozornění posílá cron po stažení nových akcí.
 *
 * Na iPhonu fungují jen v aplikaci přidané na plochu (iOS 16.4+), Safari v prohlížeči je neumí.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { serviceWorkerRegistration } from '@/lib/pwa';

/**
 * Umí prohlížeč web push? (Service worker se na dev serveru neregistruje — tam ne.)
 *
 * @returns {boolean}
 */
export function isPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/**
 * Povolení upozornění pro web: 'default' (neptali jsme se), 'granted', 'denied'.
 *
 * @returns {NotificationPermission}
 */
export function notificationPermission() {
    return 'Notification' in window ? Notification.permission : 'denied';
}

/**
 * Odběr tohoto prohlížeče, nebo null.
 *
 * @returns {Promise<PushSubscription|null>}
 */
export async function currentSubscription() {
    const registration = await serviceWorkerRegistration();

    return registration ? registration.pushManager.getSubscription() : null;
}

/**
 * Požádá o povolení a přihlásí prohlížeč k odběru. Starý odběr s jiným klíčem serveru
 * (klíče VAPID se změnily) se nejdřív zruší — jinak prohlížeč nový odmítne.
 *
 * @param {string} publicKey Veřejný klíč VAPID (base64url)
 * @returns {Promise<PushSubscription|null>} null = uživatel upozornění nepovolil
 */
export async function subscribe(publicKey) {
    if ((await Notification.requestPermission()) !== 'granted') {
        return null;
    }

    if (!(await serviceWorkerRegistration())) {
        return null;
    }
    // Odebírat jde až s aktivním service workerem (po první instalaci chvíli trvá)
    const registration = await navigator.serviceWorker.ready;

    const options = { userVisibleOnly: true, applicationServerKey: base64UrlToBytes(publicKey) };
    try {
        return await registration.pushManager.subscribe(options);
    } catch (error) {
        const existing = await registration.pushManager.getSubscription();
        if (error?.name !== 'InvalidStateError' || !existing) {
            throw error;
        }
        await existing.unsubscribe();

        return registration.pushManager.subscribe(options);
    }
}

/**
 * Klíč z base64url na bajty (pushManager.subscribe chce BufferSource).
 *
 * @param {string} value
 * @returns {Uint8Array}
 */
function base64UrlToBytes(value) {
    const base64 = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(window.atob(base64), (char) => char.charCodeAt(0));
}
