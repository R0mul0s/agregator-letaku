<!--
    Lišta „Máme novou verzi“ pod hlavičkou (R78) — po nasazení, když uživatel v aplikaci zrovna
    něco dělá (jinak se nová verze načte sama, lib/pwa.js). Při dalším návratu do aplikace
    se načte sama i bez klepnutí.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { applyUpdate, updateState } from '@/lib/pwa';

const t = useTranslations();
</script>

<template>
    <div v-if="updateState.available" class="update-bar" role="status">
        <div class="update-bar__inner">
            <svg class="update-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" /></svg>
            <p class="update-bar__text">{{ t('pwa.update_available') }}</p>
            <button type="button" class="button button--ghost update-bar__button" @click="applyUpdate">{{ t('pwa.update_apply') }}</button>
        </div>
    </div>
</template>
