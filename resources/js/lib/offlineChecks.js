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
 * Zapomene jen odeslaná odškrtnutí — co přibylo nebo se změnilo během odesílání, zůstane
 * na další pokus (R170).
 *
 * @param {Array<[string, boolean]>} sent Odeslané dvojice [id, odškrtnuto]
 */
function forgetSent(sent) {
    const rest = { ...pendingChecks.value };
    for (const [id, checked] of sent) {
        if (rest[id] === checked) {
            delete rest[id];
        }
    }
    pendingChecks.value = rest;
    writeStored(STORAGE_KEY, Object.keys(rest).length ? rest : null);
}

/**
 * Odešle neodeslaná odškrtnutí, když je připojení. Po úspěchu se zapomenou odeslaná; při chybě
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
            onSuccess: () => forgetSent(entries),
            onFinish: () => (syncing = false),
        },
    );
}
