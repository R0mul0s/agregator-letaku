<!--
    Toasty s potvrzením po uložení (R47) dole uprostřed obrazovky — obsah plní lib/toast.js
    podle stavu, který poslal server. Zmizí samy; najetí myší nebo fokus čekání zastaví.
    Toast může mít tlačítko akce („Vrátit“ po přidání hlídané položky, R71).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { dismissToast, pauseDismiss, scheduleDismiss, toasts } from '@/lib/toast';

const t = useTranslations();
</script>

<template>
    <!-- Čtečce zprávu oznamuje showToast přes trvalou oblast lib/a11y.js — tahle se s rozvržením
         stránky po přesměrování vytvoří znovu i s textem a čtečka by ji nepřečetla (R170) -->
    <div class="toaster">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast"
                @mouseenter="pauseDismiss(toast.id)"
                @mouseleave="scheduleDismiss(toast.id)"
                @focusin="pauseDismiss(toast.id)"
                @focusout="scheduleDismiss(toast.id)"
            >
                <span class="toast__icon" aria-hidden="true">
                    <!-- Fajfka -->
                    <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </span>
                <p class="toast__message">{{ toast.message }}</p>
                <button
                    v-if="toast.action"
                    type="button"
                    class="toast__action"
                    @click="
                        toast.action.run();
                        dismissToast(toast.id);
                    "
                >
                    {{ toast.action.label }}
                </button>
                <button type="button" class="toast__close" :title="t('toast.close')" @click="dismissToast(toast.id)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    <span class="visually-hidden">{{ t('toast.close') }}</span>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
