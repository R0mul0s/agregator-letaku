<!--
    Stránkování — předchozí a další strana a pozice. Data z Laravel paginatoru.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';

defineProps({
    /** Laravel LengthAwarePaginator jako JSON (prev_page_url, next_page_url, current_page, last_page). */
    paginator: { type: Object, required: true },
});

const t = useTranslations();
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="pagination" :aria-label="t('offers.pagination')">
        <Link v-if="paginator.prev_page_url" :href="paginator.prev_page_url" class="button button--ghost" preserve-state>{{ t('offers.previous') }}</Link>
        <span class="pagination__position">{{ t('offers.page', { current: paginator.current_page, last: paginator.last_page }) }}</span>
        <Link v-if="paginator.next_page_url" :href="paginator.next_page_url" class="button button--ghost" preserve-state>{{ t('offers.next') }}</Link>
    </nav>
</template>
