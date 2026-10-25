<!--
    Co lidé hledají a nenašli (R171) — v katalogu pro admina: nejčastější hledání ve Všech akcích
    a v Hlídám, která nenašla nic. „Vytvořit produkt“ otevře formulář nového produktu s textem
    hledání, „Skrýt“ hledání z přehledu odebere.

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import { confirmDialog } from '@/lib/confirm';
import { formatShortDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** { items: [{ term, text, hits, sources, lastSeenAt }], dismissUrl, minHits, retentionDays }. */
    misses: { type: Object, required: true },
});

const emit = defineEmits(['create']);

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/**
 * Skryje hledání z přehledu — po potvrzení, zpět ho vrátit nejde.
 *
 * @param {{ term: string, text: string }} miss
 */
async function dismiss(miss) {
    const confirmed = await confirmDialog({
        title: t('catalog.search_misses.dismiss_confirm_title'),
        message: t('catalog.search_misses.dismiss_confirm', { text: miss.text }),
        confirmLabel: t('catalog.search_misses.dismiss'),
    });
    if (confirmed) {
        router.delete(props.misses.dismissUrl, { data: { term: miss.term }, preserveScroll: true });
    }
}
</script>

<template>
    <section class="card search-misses" aria-labelledby="search-misses-title">
        <h2 id="search-misses-title" class="card__title">{{ t('catalog.search_misses.title') }}</h2>
        <p class="search-misses__intro">{{ t('catalog.search_misses.intro', { hits: misses.minHits, days: misses.retentionDays }) }}</p>

        <p v-if="!misses.items.length" class="search-misses__empty">{{ t('catalog.search_misses.empty') }}</p>

        <ul v-else class="search-misses__list">
            <li v-for="miss in misses.items" :key="miss.term" class="search-misses__item">
                <div class="search-misses__main">
                    <strong class="search-misses__text">{{ miss.text }}</strong>
                    <span class="search-misses__meta">
                        {{ t('catalog.search_misses.hits', { count: miss.hits }) }} ·
                        {{ t('catalog.search_misses.last_seen', { date: formatShortDate(miss.lastSeenAt, locale) }) }}
                    </span>
                    <span class="search-misses__sources">
                        <span v-for="source in miss.sources" :key="source" class="tag">{{ source }}</span>
                    </span>
                </div>
                <div class="search-misses__actions">
                    <button type="button" class="button button--soft" @click="emit('create', miss)">{{ t('catalog.search_misses.create') }}</button>
                    <button type="button" class="button button--ghost" @click="dismiss(miss)">{{ t('catalog.search_misses.dismiss') }}</button>
                </div>
            </li>
        </ul>
    </section>
</template>
