<!--
    Stránkování výpisu (R43) — „Načíst další“ připojí další stránku pod načtené a zapíše rozsah
    do adresy (?od=1&strana=2); čísla stránek, předchozí a další přejdou na jednu stránku.
    Odkazy staví server (OffersController::pagination), komponenta je jen vykreslí.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    /** { from, to, lastPage, pages: [{ number, url, current } | { gap }], previousUrl, nextUrl, loadMoreUrl, loadMoreCount, shownFrom, shownTo }. */
    pagination: { type: Object, required: true },
    /** Celkový počet položek. */
    total: { type: Number, required: true },
    /** Text tlačítka „Načíst další“ s počtem (akce, produkty…). */
    loadMoreKey: { type: String, default: 'offers.load_more' },
});

const t = useTranslations();
const page = usePage();

/**
 * Číslo s oddělením tisíců („6 245“).
 *
 * @param {number} value
 * @returns {string}
 */
function formatNumber(value) {
    return value.toLocaleString(page.props.locale);
}
</script>

<template>
    <div v-if="total" class="pagination">
        <!-- Načtené akce zůstanou, nová stránka se připojí pod ně a posun stránky se nezmění -->
        <Link v-if="pagination.loadMoreUrl" :href="pagination.loadMoreUrl" class="button button--primary" preserve-scroll preserve-state>
            {{ t(loadMoreKey, { count: pagination.loadMoreCount }) }}
        </Link>

        <p class="pagination__shown">
            {{ t('offers.shown', { from: formatNumber(pagination.shownFrom), to: formatNumber(pagination.shownTo), total: formatNumber(total) }) }}
        </p>

        <nav v-if="pagination.lastPage > 1" class="pagination__pages" :aria-label="t('offers.pagination')">
            <Link v-if="pagination.previousUrl" :href="pagination.previousUrl" class="pagination__page" preserve-state>
                <span aria-hidden="true">‹</span>
                <span class="visually-hidden">{{ t('offers.previous') }}</span>
            </Link>
            <template v-for="(link, index) in pagination.pages" :key="link.gap ? `gap-${index}` : link.number">
                <span v-if="link.gap" class="pagination__gap" aria-hidden="true">…</span>
                <Link
                    v-else
                    :href="link.url"
                    class="pagination__page"
                    :class="{ 'pagination__page--current': link.current }"
                    :aria-current="link.current ? 'page' : undefined"
                    preserve-state
                >
                    {{ link.number }}
                </Link>
            </template>
            <Link v-if="pagination.nextUrl" :href="pagination.nextUrl" class="pagination__page" preserve-state>
                <span aria-hidden="true">›</span>
                <span class="visually-hidden">{{ t('offers.next') }}</span>
            </Link>
        </nav>
    </div>
</template>
