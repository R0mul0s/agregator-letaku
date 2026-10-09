/**
 * Modální okno `<dialog>` řízené stavem — otevře se `showModal()` (fokus, Escape, vrstva nad
 * stránkou, R99), zavře `close()`, klepnutí na ztmavené pozadí a odchod ze stránky ho zavřou.
 * Sdílí ho spodní okno, potvrzení, seznam prodejen a nastavení cookies (R113, dřív čtyři kopie).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { nextTick, onBeforeUnmount, ref, toValue, watch } from 'vue';

/**
 * Okno podle stavu `isOpen`. Vrácený `dialog` patří do `ref="dialog"`, `onClick` na `@click` okna.
 *
 * @param {import('vue').MaybeRefOrGetter<boolean>} isOpen Má být okno otevřené?
 * @param {{ onBackdrop?: () => void, beforeOpen?: () => (void|Promise<void>), focusOnOpen?: import('vue').MaybeRefOrGetter<HTMLElement|null> }} [options]
 *   onBackdrop — klepnutí na pozadí (zavřít, zrušit); beforeOpen — příprava obsahu před otevřením; focusOnOpen — prvek, který po otevření dostane fokus
 * @returns {{ dialog: import('vue').Ref<HTMLDialogElement|null>, onClick: (event: MouseEvent) => void }}
 */
export function useModalDialog(isOpen, { onBackdrop, beforeOpen, focusOnOpen } = {}) {
    const dialog = ref(null);

    watch(
        () => toValue(isOpen),
        async (open) => {
            if (open && !dialog.value?.open) {
                await beforeOpen?.();
                dialog.value?.showModal();
                await nextTick();
                toValue(focusOnOpen)?.focus();
            } else if (!open && dialog.value?.open) {
                dialog.value.close();
            }
        },
    );

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

    // Přechod na jinou stránku s otevřeným oknem — zavřít, jinak by zůstalo ve vrstvě nad stránkou
    onBeforeUnmount(() => dialog.value?.open && dialog.value.close());

    return { dialog, onClick };
}
