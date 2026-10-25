<!--
    Graf cen v akcích stejné položky u obchodu (R163) v okně s detailem akce — schodový: každá
    akce je vodorovný úsek ve výši své ceny po dobu platnosti, tato akce zvýrazněná, původní
    cena čárkovaně. Běžné ceny mimo akci neznáme, mezi akcemi je proto prázdno. Popisky jen
    u této a nejnižší dřívější ceny, ostatní v nápovědě úseku; čtečka dostane seznam akcí.

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

/** Rozměry kreslicí plochy (jednotky viewBoxu) a okraje pro popisky. */
const WIDTH = 320;
const HEIGHT = 132;
const PAD = { top: 18, right: 8, bottom: 22, left: 8 };
/** Rezerva nad nejvyšší cenou, ať popisek nenaráží na horní okraj. */
const HEADROOM = 1.15;
/** Spodní mez osy jako podíl nejnižší ceny — od nuly by rozdíl 8,90 a 9,90 Kč nebyl vidět (čára, ne sloupce). */
const FLOOR_RATIO = 0.75;
const DAY_MS = 86_400_000;

/** Den jako čas (UTC půlnoc) — úsek akce trvá do konce posledního dne. */
const day = (iso) => Date.parse(`${iso}T00:00:00Z`);

const domain = computed(() => {
    const start = Math.min(...props.points.map((point) => day(point.from)));
    const end = Math.max(...props.points.map((point) => day(point.to) + DAY_MS));
    const top = Math.max(...props.points.map((point) => Math.max(point.price, point.originalPrice ?? 0))) * HEADROOM;
    const bottom = Math.min(...props.points.map((point) => point.price)) * FLOOR_RATIO;

    return { start, end, top, bottom };
});

const x = (time) => PAD.left + ((time - domain.value.start) / (domain.value.end - domain.value.start)) * (WIDTH - PAD.left - PAD.right);
const y = (price) => PAD.top + (1 - (price - domain.value.bottom) / (domain.value.top - domain.value.bottom)) * (HEIGHT - PAD.top - PAD.bottom);

/** Nejnižší dřívější akce — popisek jen u ní a u této (výběrové popisky). */
const lowestEarlier = computed(() =>
    props.points.filter((point) => !point.current).reduce((lowest, point) => (lowest === null || point.price < lowest.price ? point : lowest), null),
);

const segments = computed(() =>
    props.points.map((point) => {
        const x1 = x(day(point.from));
        const x2 = x(day(point.to) + DAY_MS);
        const range = `${formatShortDate(point.from, locale.value)} – ${formatShortDate(point.to, locale.value)}`;

        return {
            ...point,
            x1,
            x2,
            y: y(point.price),
            yOriginal: point.originalPrice ? y(point.originalPrice) : null,
            label: formatPrice(point.price, locale.value),
            labelled: point.current || point === lowestEarlier.value,
            title: t('offer_detail.history_point', { range, price: formatPrice(point.price, locale.value) }),
        };
    }),
);

/** Popisky osy: začátek a konec období. */
const axis = computed(() => ({
    start: formatShortDate(new Date(domain.value.start).toISOString().slice(0, 10), locale.value),
    end: formatShortDate(new Date(domain.value.end - DAY_MS).toISOString().slice(0, 10), locale.value),
}));
</script>

<template>
    <figure class="price-history">
        <figcaption class="price-history__title">{{ t('offer_detail.history_title', { weeks }) }}</figcaption>
        <svg class="price-history__chart" :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" role="img" :aria-label="t('offer_detail.history_title', { weeks })">
            <line class="price-history__baseline" :x1="PAD.left" :x2="WIDTH - PAD.right" :y1="HEIGHT - PAD.bottom" :y2="HEIGHT - PAD.bottom" />
            <g v-for="segment in segments" :key="`${segment.from}-${segment.price}`">
                <title>{{ segment.title }}</title>
                <!-- Původní cena (přeškrtnutá) čárkovaně nad úsekem -->
                <line v-if="segment.yOriginal !== null" class="price-history__original" :x1="segment.x1" :x2="segment.x2" :y1="segment.yOriginal" :y2="segment.yOriginal" />
                <line
                    class="price-history__step"
                    :class="{ 'price-history__step--current': segment.current }"
                    :x1="segment.x1"
                    :x2="segment.x2"
                    :y1="segment.y"
                    :y2="segment.y"
                />
                <!-- Větší plocha pro nápovědu než tenká čára -->
                <rect class="price-history__hit" :x="segment.x1" :y="PAD.top" :width="Math.max(segment.x2 - segment.x1, 6)" :height="HEIGHT - PAD.top - PAD.bottom" />
                <text
                    v-if="segment.labelled"
                    class="price-history__label"
                    :class="{ 'price-history__label--current': segment.current }"
                    :x="(segment.x1 + segment.x2) / 2"
                    :y="segment.y - 6"
                    text-anchor="middle"
                >
                    {{ segment.label }}
                </text>
            </g>
            <text class="price-history__axis" :x="PAD.left" :y="HEIGHT - 6">{{ axis.start }}</text>
            <text class="price-history__axis" :x="WIDTH - PAD.right" :y="HEIGHT - 6" text-anchor="end">{{ axis.end }}</text>
        </svg>
        <!-- Totéž pro čtečku a bez grafiky -->
        <ul class="visually-hidden">
            <li v-for="segment in segments" :key="`${segment.from}-${segment.price}`">{{ segment.title }}</li>
        </ul>
    </figure>
</template>
