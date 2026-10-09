<!--
    Potvrzovací okno nevratné akce (R47) — nativní <dialog> (fokus zůstane uvnitř, Esc zavře,
    pozadí ztmavne). Obsah a odpověď řídí lib/confirm.js. Fokus začíná na „Zpět“ — Enter
    omylem nic nesmaže.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { answerConfirm, confirmState } from '@/lib/confirm';
import { useTranslations } from '@/lib/i18n';
import { useModalDialog } from '@/lib/modalDialog';
import { ref } from 'vue';

const t = useTranslations();
const cancelButton = ref(null);
// Klik na ztmavené pozadí je zrušení; fokus na Zrušit — nevratná akce se omylem nepotvrdí
const { dialog, onClick } = useModalDialog(() => confirmState.open, { onBackdrop: () => answerConfirm(false), focusOnOpen: cancelButton });
</script>

<template>
    <dialog ref="dialog" class="confirm-dialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message" @cancel.prevent="answerConfirm(false)" @click="onClick">
        <div class="confirm-dialog__body">
            <span class="confirm-dialog__icon" aria-hidden="true">
                <!-- Vykřičník v trojúhelníku -->
                <svg viewBox="0 0 24 24"><path d="M12 4 2.5 20h19zM12 10v4.5M12 17.5h.01" /></svg>
            </span>
            <div class="confirm-dialog__text">
                <h2 id="confirm-dialog-title" class="confirm-dialog__title">{{ confirmState.title }}</h2>
                <p id="confirm-dialog-message" class="confirm-dialog__message">{{ confirmState.message }}</p>
            </div>
        </div>
        <div class="confirm-dialog__actions">
            <button ref="cancelButton" type="button" class="button button--ghost" @click="answerConfirm(false)">{{ t('confirm.cancel') }}</button>
            <button type="button" class="button button--danger" @click="answerConfirm(true)">{{ confirmState.confirmLabel }}</button>
        </div>
    </dialog>
</template>
