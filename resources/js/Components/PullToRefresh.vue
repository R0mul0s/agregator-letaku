<!--
    Stažení stránky dolů pro nové načtení v aplikaci z plochy (R112) — ukazatel vyjede zpod
    hlavičky, šipka se točí s prstem, za hranicí zčervená a po puštění se stránka načte znovu
    (data stránky přes Inertii, posun i rozbalení zůstanou). V prohlížeči se nevykreslí — tam
    to Chrome umí sám. Gesto řídí lib/pullToRefresh.js.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { usePullToRefresh } from '@/lib/pullToRefresh';
import { installState } from '@/lib/pwaInstall';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const t = useTranslations();
const indicator = ref(null);

const { distance, ready, refreshing } = usePullToRefresh({
    enabled: () => installState.standalone,
    refresh: () => new Promise((resolve) => router.reload({ onFinish: resolve })),
});

// Poloha ukazatele se mění s prstem — jako proměnná CSS, pohyb a otočení řeší stylesheet
watch(distance, (value) => indicator.value?.style.setProperty('--pull-distance', `${value}px`));
</script>

<template>
    <template v-if="installState.standalone">
        <div
            ref="indicator"
            class="pull-refresh"
            :class="{ 'pull-refresh--visible': distance > 0 || refreshing, 'pull-refresh--ready': ready, 'pull-refresh--refreshing': refreshing }"
            aria-hidden="true"
        >
            <svg class="pull-refresh__icon" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" /></svg>
        </div>
        <!-- Čtečka obrazovky se dozví, že se stránka načítá -->
        <p class="visually-hidden" role="status">{{ refreshing ? t('pwa.refreshing') : '' }}</p>
    </template>
</template>
