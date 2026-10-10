<!--
    Kvalita dat pro admina (R129) — po obchodech poslední stažení (stav, počet akcí, chyba)
    s vývojem za poslední stažení a tabulka letáků posledního stažení: kolik akcí přinesl,
    u letáků z PDF nebo SVG kolik nalezených cen parser ověřil, vývoj obojího a propad proti
    obvyklému stavu (ten se hlásí i v centru upozornění). Data posílá DataQualityController.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import Sparkline from '@/Components/Sparkline.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber, formatRelativeTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/vue3';

/** Osa grafu podílu ověřených cen je vždy do 100 %. */
const PERCENT_MAX = 100;

/** Štítek podle stavu stažení (App\Enums\ScrapeStatus). */
const STATUS_TAGS = { succeeded: 'tag--success', partial: 'tag--warning', failed: 'tag--accent', running: 'tag--upcoming' };

defineProps({
    /** Obchody [{ chain, lastRun: { status, startedAt, offers, withdrawn, error } | null, offersHistory, leaflets }]. */
    chainReports: { type: Array, required: true },
    /** Kolik posledních stažení graf ukazuje. */
    historyRuns: { type: Number, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Popis grafu pro čtečku s hodnotami.
 *
 * @param {Array<number|null>} values
 * @param {string} unit Přípona hodnoty („ %“ nebo prázdná)
 * @returns {string}
 */
function chartLabel(values, unit = '') {
    const text = values.map((value) => (value === null ? t('data_quality.chart_gap') : `${formatNumber(value, page.props.locale)}${unit}`)).join(', ');

    return t('data_quality.chart_label', { count: values.length, values: text });
}

/**
 * Propad letáku daného druhu, nebo null.
 *
 * @param {{ issues: Array<{ kind: string, current: number, baseline: number }> }} leaflet
 * @param {string} kind offers | verified
 */
function issueOf(leaflet, kind) {
    return leaflet.issues.find((issue) => issue.kind === kind) ?? null;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('data_quality.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('data_quality.title') }}</h1>
            <p class="page__subtitle">{{ t('data_quality.intro') }}</p>
        </header>

        <section v-for="report in chainReports" :key="report.chain" class="quality-chain">
            <header class="quality-chain__header">
                <h2 class="quality-chain__title">
                    <ChainLogo :chain="report.chain" large with-name />
                </h2>
                <p v-if="report.lastRun" class="quality-chain__run">
                    <span class="tag" :class="STATUS_TAGS[report.lastRun.status]">{{ t(`data_quality.statuses.${report.lastRun.status}`) }}</span>
                    {{
                        t('data_quality.last_run', {
                            when: formatRelativeTime(report.lastRun.startedAt, page.props.locale, page.props.timezone),
                            offers: formatNumber(report.lastRun.offers, page.props.locale),
                            withdrawn: formatNumber(report.lastRun.withdrawn, page.props.locale),
                        })
                    }}
                </p>
                <p v-else class="quality-chain__run">{{ t('data_quality.never') }}</p>
                <Sparkline
                    v-if="report.offersHistory.length > 1"
                    class="quality-chain__chart"
                    :values="report.offersHistory"
                    :label="chartLabel(report.offersHistory)"
                />
            </header>
            <p v-if="report.lastRun?.error" class="quality-chain__error">{{ report.lastRun.error }}</p>

            <p v-if="!report.leaflets.length" class="page__empty">{{ t('data_quality.no_stats') }}</p>
            <div v-else class="data-table">
                <table class="data-table__table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('data_quality.columns.leaflet') }}</th>
                            <th scope="col" class="data-table__number">{{ t('data_quality.columns.offers') }}</th>
                            <th scope="col">{{ t('data_quality.columns.offers_trend', { count: historyRuns }) }}</th>
                            <th scope="col" class="data-table__number">{{ t('data_quality.columns.verified') }}</th>
                            <th scope="col">{{ t('data_quality.columns.verified_trend', { count: historyRuns }) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="leaflet in report.leaflets" :key="leaflet.id" :class="{ 'quality-row--alert': leaflet.issues.length }">
                            <td>
                                <a v-if="leaflet.url" :href="leaflet.url" class="link" target="_blank" rel="noopener">{{ leaflet.label }}</a>
                                <span v-else>{{ leaflet.label }}</span>
                                <span class="data-table__muted quality-row__source">{{ leaflet.fromPdf ? t('data_quality.source_pdf') : t('data_quality.source_api') }}</span>
                                <span v-for="issue in leaflet.issues" :key="issue.kind" class="tag tag--warning quality-row__issue">
                                    {{ t(`data_quality.issues.${issue.kind}`, { current: issue.current, baseline: issue.baseline }) }}
                                </span>
                            </td>
                            <td class="data-table__number">{{ formatNumber(leaflet.offers, page.props.locale) }}</td>
                            <td>
                                <Sparkline
                                    v-if="leaflet.offersHistory.length > 1"
                                    :values="leaflet.offersHistory"
                                    :label="chartLabel(leaflet.offersHistory)"
                                    :alert="Boolean(issueOf(leaflet, 'offers'))"
                                />
                            </td>
                            <td class="data-table__number">
                                <template v-if="leaflet.verified && leaflet.verified.percent !== null">
                                    {{ t('data_quality.verified_value', { percent: leaflet.verified.percent }) }}
                                    <span class="data-table__muted quality-row__counts">{{
                                        t('data_quality.verified_counts', { verified: leaflet.verified.verified, candidates: leaflet.verified.candidates })
                                    }}</span>
                                </template>
                                <span v-else class="data-table__muted">{{ t('data_quality.not_applicable') }}</span>
                            </td>
                            <td>
                                <Sparkline
                                    v-if="leaflet.fromPdf && leaflet.verifiedHistory.length > 1"
                                    :values="leaflet.verifiedHistory"
                                    :label="chartLabel(leaflet.verifiedHistory, ' %')"
                                    :max="PERCENT_MAX"
                                    :alert="Boolean(issueOf(leaflet, 'verified'))"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
