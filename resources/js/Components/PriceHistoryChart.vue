<!--
    Ceny v akcích stejné položky u obchodu (R163) v okně s detailem akce — řádek na akci od
    nejnovější: datum, vodorovný pruh od nuly délkou podle ceny a cena; tato akce nahoře,
    zvýrazněná a označená „teď“. Časová osa se u pár krátkých akcí za 12 týdnů rozpadla na drobné čárky
    s překrývajícími se popisky, řádky mají popisek každý. Běžné ceny mimo akci neznáme.

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import { formatPrice, formatShortDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Akce od nejstarší [{ from, to, price, originalPrice, current }] (PriceHistory::timeline). */
    points: { type: Array, required: true },
    /** Sledované období v týdnech (do nadpisu). */
    weeks: { type: Number, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Nejvyšší cena — plná délka pruhu. */
const maxPrice = computed(() => Math.max(...props.points.map((point) => point.price)));

/** Od nejnovější — aktuální akce nahoře, starší pod ní (i při dvanácti řádcích je hned vidět). */
const rows = computed(() =>
    [...props.points].reverse().map((point) => ({
        ...point,
        range: `${formatShortDate(point.from, locale.value)} – ${formatShortDate(point.to, locale.value)}`,
        label: formatPrice(point.price, locale.value),
        // Délka pruhu v procentech šířky — přes vlastní vlastnost, ne inline styl
        width: `${(point.price / maxPrice.value) * 100}%`,
    })),
);
</script>

<template>
    <figure class="price-history">
        <figcaption class="price-history__title">{{ t('offer_detail.history_title', { weeks }) }}</figcaption>
        <ol class="price-history__rows">
            <li v-for="row in rows" :key="`${row.from}-${row.price}`" class="price-history__row" :class="{ 'price-history__row--current': row.current }">
                <span class="price-history__range">
                    {{ row.range }}
                    <span v-if="row.current" class="price-history__now">{{ t('offer_detail.history_now') }}</span>
                </span>
                <span class="price-history__track" aria-hidden="true">
                    <span class="price-history__bar" :style="{ '--price-history-bar': row.width }"></span>
                </span>
                <span class="price-history__price">{{ row.label }}</span>
            </li>
        </ol>
    </figure>
</template>
