/**
 * Modální okno `<dialog>` řízené stavem — otevře se `showModal()` (fokus, Escape, vrstva nad
 * stránkou, R99), zavře `close()`, klepnutí na ztmavené pozadí a odchod ze stránky ho zavřou.
 * Sdílí ho spodní okno, potvrzení, seznam prodejen a nastavení cookies (R113, dřív čtyři kopie).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { nextTick, onBeforeUnmount, onMounted, ref, toValue, watch } from 'vue';

/** Atribut okna během animace zavření (R182) — CSS podle něj spustí odjezd okna a pozadí. */
const CLOSING_ATTRIBUTE = 'data-closing';

/** Pojistka: nejdéle tolik ms se čeká na animaci zavření (delší je jen chyba ve stylech). */
const CLOSE_ANIMATION_MAX_MS = 500;

/**
 * Spustí animaci zavření okna a počká na její konec. Bez animace (omezený pohyb, starý
 * prohlížeč) skončí hned.
 *
 * @param {HTMLDialogElement} element
 * @returns {Promise<void>}
 */
async function playCloseAnimation(element) {
    element.setAttribute(CLOSING_ATTRIBUTE, '');
    const finished = Promise.allSettled(element.getAnimations().map((animation) => animation.finished));
    await Promise.race([finished, new Promise((resolve) => window.setTimeout(resolve, CLOSE_ANIMATION_MAX_MS))]);
}

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
    /** Probíhá animace zavření — další zavření se nespouští. */
    let closing = false;

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
        } else if (!open && dialog.value?.open && !closing) {
            // Okno odjede a pozadí zesvětlá (R182), teprve pak se zavře — close() by ho schoval hned
            const element = dialog.value;
            closing = true;
            await playCloseAnimation(element);
            closing = false;
            // Během odjezdu ho stránka mohla znovu otevřít (další potvrzení hned po prvním) — pak
            // zůstane, najede zpátky a připraví se jako při otevření
            if (toValue(isOpen)) {
                element.removeAttribute(CLOSING_ATTRIBUTE);
                await beforeOpen?.();
                toValue(focusOnOpen)?.focus();

                return;
            }
            element.close();
            element.removeAttribute(CLOSING_ATTRIBUTE);
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
