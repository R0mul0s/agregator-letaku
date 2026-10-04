<!--
    Lišta „Jste offline“ (R66) pod hlavičkou — bez připojení, nebo když service worker ukázal
    uloženou verzi stránky (pomalý signál v obchodě). S časem, kdy byla stránka uložená,
    ať uživatel ví, jak staré ceny vidí.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { formatDateTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { offlineFetchedAt } from '@/lib/pwa';
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const t = useTranslations();
const page = usePage();

const online = ref(true);
/** Kdy byla zobrazená stránka uložená (ISO 8601); null = je ze sítě nebo neznámo. */
const fetchedAt = ref(null);

/** Zjistí od service workeru, jestli je stránka z cache. */
async function updateFetchedAt() {
    fetchedAt.value = await offlineFetchedAt(window.location.pathname);
}

/** Změna připojení; po návratu signálu se uložená stránka nahradí aktuální. */
function updateOnline() {
    online.value = navigator.onLine;
    if (online.value && fetchedAt.value) {
        router.reload();
    }
}

let removeNavigateListener = null;

onMounted(() => {
    updateOnline();
    updateFetchedAt();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
    removeNavigateListener = router.on('navigate', updateFetchedAt);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
    removeNavigateListener?.();
});
</script>

<template>
    <div v-if="!online || fetchedAt" class="offline-bar" role="status">
        <svg class="offline-bar__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0M12 19h.01M3 3l18 18" /></svg>
        <span>
            {{ online ? t('pwa.stale') : t('pwa.offline') }}
            <template v-if="fetchedAt">{{ t('pwa.fetched_at', { at: formatDateTime(fetchedAt, page.props.locale, page.props.timezone) }) }}</template>
        </span>
    </div>
</template>
