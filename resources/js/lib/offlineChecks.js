/**
 * Odškrtnutí v nákupním seznamu bez připojení (R66). V obchodě často není signál: odškrtnutí
 * se uloží v prohlížeči, seznam ho hned ukáže a až je zase připojení, odešlou se všechna
 * najednou (PATCH /seznam, ShoppingListController::sync) — z kterékoli stránky.
 * Background Sync umí jen Chromium, proto odeslání po události `online` a při startu aplikace.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { readStored, writeStored } from '@/lib/storage';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** Klíč v localStorage: { [id položky]: odškrtnuto } (zásady, kap. 5). */
const STORAGE_KEY = 'slevohlidka.shopping.pending';

/** Neodeslaná odškrtnutí { [id]: boolean } — seznam podle nich překreslí položky. */
export const pendingChecks = ref(readStored(STORAGE_KEY, {}));

/** Právě se odesílá — druhé odeslání by poslalo totéž znovu. */
let syncing = false;

/**
 * Zapamatuje odškrtnutí, které se nepodařilo odeslat.
 *
 * @param {number} id
 * @param {boolean} checked
 */
export function queueCheck(id, checked) {
    pendingChecks.value = { ...pendingChecks.value, [id]: checked };
    writeStored(STORAGE_KEY, pendingChecks.value);
}

/** Zapomene neodeslaná odškrtnutí (odesláno, nebo odhlášení). */
export function clearPendingChecks() {
    pendingChecks.value = {};
    writeStored(STORAGE_KEY, null);
}

/**
 * Odešle neodeslaná odškrtnutí, když je připojení. Po úspěchu se zapomenou; při chybě
 * zůstanou na další pokus.
 *
 * @param {string|undefined} url Adresa synchronizace ze sdílené vlastnosti shoppingList
 */
export function syncPendingChecks(url) {
    const entries = Object.entries(pendingChecks.value);
    if (!url || !entries.length || syncing || !navigator.onLine) {
        return;
    }

    syncing = true;
    router.patch(
        url,
        { checks: entries.map(([id, checked]) => ({ id: Number(id), checked })) },
        {
            preserveScroll: true,
            preserveState: true,
            // Nezruší přechod, který uživatel právě dělá
            async: true,
            showProgress: false,
            onSuccess: clearPendingChecks,
            onFinish: () => (syncing = false),
        },
    );
}
