/**
 * Modální okno `<dialog>` řízené stavem — otevře se `showModal()` (fokus, Escape, vrstva nad
 * stránkou, R99), zavře `close()`, klepnutí na ztmavené pozadí a odchod ze stránky ho zavřou.
 * Sdílí ho spodní okno, potvrzení, seznam prodejen a nastavení cookies (R113, dřív čtyři kopie).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { nextTick, onBeforeUnmount, onMounted, ref, toValue, watch } from 'vue';

/**
 * Okno podle stavu `isOpen`. Vrácený `dialog` patří do `ref="dialog"`, `onClick` na `@click` okna.
 *
 * @param {import('vue').MaybeRefOrGetter<boolean>} isOpen Má být okno otevřené?
 * @param {{ onBackdrop?: () => void, onDismiss?: () => void, beforeOpen?: () => (void|Promise<void>), focusOnOpen?: import('vue').MaybeRefOrGetter<HTMLElement|null> }} [options]
 *   onBackdrop — klepnutí na pozadí (zavřít, zrušit); onDismiss — okno zavřel odchod ze stránky (vrátit
 *   stav na „zavřeno“); beforeOpen — příprava obsahu před otevřením; focusOnOpen — prvek, který po otevření dostane fokus
 * @returns {{ dialog: import('vue').Ref<HTMLDialogElement|null>, onClick: (event: MouseEvent) => void }}
 */
export function useModalDialog(isOpen, { onBackdrop, onDismiss, beforeOpen, focusOnOpen } = {}) {
    const dialog = ref(null);

    /**
     * Otevře nebo zavře okno podle stavu.
     *
     * @param {boolean} open
     */
    async function sync(open) {
        if (open && !dialog.value?.open) {
            await beforeOpen?.();
            dialog.value?.showModal();
            await nextTick();
            toValue(focusOnOpen)?.focus();
        } else if (!open && dialog.value?.open) {
            dialog.value.close();
        }
    }

    watch(() => toValue(isOpen), sync);
    // Okno načtené až při prvním otevření (AppLayout, R170) se připojí s otevřeným stavem
    onMounted(() => toValue(isOpen) && sync(true));

    /**
     * Klepnutí na ztmavené pozadí (mimo obsah okna).
     *
     * @param {MouseEvent} event
     */
    function onClick(event) {
        if (event.target === dialog.value) {
            onBackdrop?.();
        }
    }

    // Přechod na jinou stránku s otevřeným oknem (i tlačítkem Zpět) — zavřít, jinak by zůstalo ve vrstvě
    // nad stránkou. Sdílený stav okna (potvrzení, nastavení cookies) se vrátí na „zavřeno“: nová
    // instance by jinak čekala na změnu, která nepřijde, a okno by už nešlo otevřít (R170)
    onBeforeUnmount(() => {
        if (dialog.value?.open) {
            dialog.value.close();
            onDismiss?.();
        }
    });

    return { dialog, onClick };
}
