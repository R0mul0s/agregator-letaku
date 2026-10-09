<!--
    Hlášení pro admina (R125) — chyby v akcích, které nahlásili uživatelé (po akcích, s důvody
    a popisem, „Vyřešeno“), a co uživatelé skrývají u produktů katalogu („Tohle ne“): akce
    a vyloučená slova s počtem lidí. Odkaz vede do detailu produktu, kde jde pravidla opravit.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import OfferCard from '@/Components/OfferCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatRelativeTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    /** Otevřená hlášení po akcích [{ offer, reports: [{ id, reason, note, reportedAt }], resolveUrl }]. */
    reports: { type: Array, required: true },
    /** Akce skryté u produktů katalogu [{ product: { name, url }, offer, users }]. */
    hiddenOffers: { type: Array, required: true },
    /** Vyloučená slova u produktů katalogu [{ product: { name, url }, word, users }]. */
    excludedWords: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Označí hlášení akce jako vyřešená.
 *
 * @param {string} url
 */
function resolve(url) {
    router.patch(url, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('reports.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('reports.title') }}</h1>
            <p class="page__subtitle">{{ t('reports.intro') }}</p>
        </header>

        <section class="reports-section">
            <h2 class="watch-group__title">
                {{ t('reports.open_title') }}
                <span class="watch-group__count">{{ t('reports.open_count', { count: reports.length }) }}</span>
            </h2>
            <p class="watch-group__hint">{{ t('reports.resolve_hint') }}</p>
            <EmptyState v-if="!reports.length" :text="t('reports.open_empty')" />
            <div v-else class="offer-grid">
                <OfferCard v-for="entry in reports" :key="entry.offer.id" :offer="entry.offer" :heading-level="3" :with-menu="false">
                    <ul class="reports-list">
                        <li v-for="report in entry.reports" :key="report.id" class="reports-list__item">
                            <strong>{{ report.reason }}</strong>
                            <span class="reports-list__time">{{ formatRelativeTime(report.reportedAt, page.props.locale, page.props.timezone) }}</span>
                            <p v-if="report.note" class="reports-list__note">{{ report.note }}</p>
                        </li>
                    </ul>
                    <button type="button" class="button button--ghost" @click="resolve(entry.resolveUrl)">{{ t('reports.resolve') }}</button>
                </OfferCard>
            </div>
        </section>

        <section class="reports-section">
            <h2 class="watch-group__title">{{ t('reports.hidden_title') }}</h2>
            <p class="watch-group__hint">{{ t('reports.hidden_hint') }}</p>
            <p v-if="!hiddenOffers.length" class="page__empty">{{ t('reports.hidden_empty') }}</p>
            <div v-else class="data-table">
                <table class="data-table__table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('reports.columns.product') }}</th>
                            <th scope="col">{{ t('reports.columns.offer') }}</th>
                            <th scope="col" class="data-table__number">{{ t('reports.columns.users') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in hiddenOffers" :key="`${row.product.url}:${row.offer.id}`">
                            <td><Link :href="row.product.url" class="link">{{ row.product.name }}</Link></td>
                            <td>{{ row.offer.name }} <span class="reports-list__time">{{ row.offer.chainName }}</span></td>
                            <td class="data-table__number">{{ row.users }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="reports-section">
            <h2 class="watch-group__title">{{ t('reports.words_title') }}</h2>
            <p class="watch-group__hint">{{ t('reports.words_hint') }}</p>
            <p v-if="!excludedWords.length" class="page__empty">{{ t('reports.words_empty') }}</p>
            <div v-else class="data-table">
                <table class="data-table__table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('reports.columns.product') }}</th>
                            <th scope="col">{{ t('reports.columns.word') }}</th>
                            <th scope="col" class="data-table__number">{{ t('reports.columns.users') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in excludedWords" :key="`${row.product.url}:${row.word}`">
                            <td><Link :href="row.product.url" class="link">{{ row.product.name }}</Link></td>
                            <td>{{ row.word }}</td>
                            <td class="data-table__number">{{ row.users }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
