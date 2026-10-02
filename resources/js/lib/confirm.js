/**
 * Potvrzovací okno místo window.confirm (R47) — jedno pro celou aplikaci (ConfirmDialog.vue
 * v AppLayout). Volání vrátí Promise: true = potvrzeno, false = zrušeno (tlačítko, Esc, klik vedle).
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { reactive } from 'vue';

/** Stav otevřeného okna; resolve dokončí čekající Promise. */
export const confirmState = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    resolve: null,
});

/**
 * Zeptá se uživatele na potvrzení nevratné akce.
 *
 * @param {{ title: string, message: string, confirmLabel: string }} options
 * @returns {Promise<boolean>}
 */
export function confirmDialog({ title, message, confirmLabel }) {
    // Předchozí otevřené okno (nemělo by nastat) se bere jako zrušené
    confirmState.resolve?.(false);

    return new Promise((resolve) => {
        Object.assign(confirmState, { open: true, title, message, confirmLabel, resolve });
    });
}

/**
 * Zavře okno s odpovědí.
 *
 * @param {boolean} confirmed
 */
export function answerConfirm(confirmed) {
    const resolve = confirmState.resolve;
    Object.assign(confirmState, { open: false, resolve: null });
    resolve?.(confirmed);
}
