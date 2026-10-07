/**
 * Krátká potvrzení po uložení jako toast (R47) — jedno místo pro celou aplikaci.
 *
 * Server potvrzuje uložení kódem v `session('status')` (sdílená vlastnost `status`, kódy posílá
 * i Fortify). Po každé odpovědi serveru se kód přeloží (`toast.messages.<kód>`) a ukáže; stav
 * bez překladu je věta (Fortify, zrušení účtu) a ukáže se tak, jak je.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { lookup } from '@/lib/i18n';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Jak dlouho toast svítí (ms), než sám zmizí; při najetí myší se čekání zastaví. */
export const TOAST_DURATION_MS = 5000;

/**
 * Jak dlouho svítí toast s tlačítkem („Vrátit“) — uživatel klávesnice nebo čtečky se k němu musí
 * stihnout dostat (toaster je na konci stránky, WCAG 2.2.1, R99).
 */
export const TOAST_ACTION_DURATION_MS = 15000;

/** Nejvíc toastů najednou — starší ustoupí. */
const MAX_TOASTS = 3;

/** Zobrazené toasty [{ id, message }]. */
export const toasts = reactive([]);

/** Časovače automatického zavření podle id toastu. */
const timers = new Map();

let nextId = 1;

/**
 * Zavře toast.
 *
 * @param {number} id
 */
export function dismissToast(id) {
    clearTimeout(timers.get(id));
    timers.delete(id);
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

/**
 * Spustí (znovu) odpočet zavření toastu.
 *
 * @param {number} id
 */
export function scheduleDismiss(id) {
    clearTimeout(timers.get(id));
    const withAction = Boolean(toasts.find((toast) => toast.id === id)?.action);
    timers.set(
        id,
        setTimeout(() => dismissToast(id), withAction ? TOAST_ACTION_DURATION_MS : TOAST_DURATION_MS),
    );
}

/**
 * Zastaví odpočet (uživatel na toast najel myší nebo do něj přešel klávesnicí).
 *
 * @param {number} id
 */
export function pauseDismiss(id) {
    clearTimeout(timers.get(id));
}

/**
 * Ukáže toast se zprávou. Stejná zpráva, která už svítí (ukládání hned po každé změně,
 * R63, R64), se neukáže podruhé — jen se jí znovu odpočítá čas.
 *
 * @param {string} message
 * @param {{ label: string, run: () => void }|null} [action] Tlačítko v toastu („Vrátit“, R71)
 */
export function showToast(message, action = null) {
    const shown = toasts.find((toast) => toast.message === message);
    if (shown) {
        shown.action = action;
        scheduleDismiss(shown.id);

        return;
    }

    const id = nextId++;
    toasts.push({ id, message, action });
    while (toasts.length > MAX_TOASTS) {
        dismissToast(toasts[0].id);
    }
    scheduleDismiss(id);
}

/**
 * Ukáže toast pro stav stránky ze serveru, když nějaký přišel.
 *
 * @param {object} page Stránka Inertie (props.status, props.translations)
 */
function showStatus(page) {
    const status = page?.props?.status;
    if (typeof status === 'string' && status !== '') {
        // „Vrátit“ (R71): server poslal adresu, která uložení vezme zpět (smazání přidané položky)
        const undoUrl = page.props.statusUndo;
        const action = undoUrl
            ? { label: lookup(page.props.translations, 'toast.undo') ?? '', run: () => router.delete(undoUrl, { preserveScroll: true, preserveState: true }) }
            : null;
        showToast(lookup(page.props.translations, `toast.messages.${status}`) ?? status, action);
    }
}

/**
 * Napojí toasty na odpovědi serveru — volá se jednou při startu aplikace.
 * Stav z první stránky (přesměrování po přihlášení, celé načtení) se ukáže hned.
 *
 * @param {object} initialPage
 */
export function installStatusToasts(initialPage) {
    showStatus(initialPage);
    router.on('success', (event) => showStatus(event.detail.page));
}
