<!--
    Malý graf vývoje hodnot (sparkline) bez knihovny — čára přes posledních pár hodnot, poslední
    bod zvýrazněný. Chybějící hodnota (null, např. neúspěšné stažení) přeruší čáru. Čtečka
    dostane popis s hodnotami. Přehled kvality dat (R129).

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import { computed } from 'vue';

/** Souřadnice grafu — skutečnou velikost určují styly (.sparkline). */
const WIDTH = 100;
const HEIGHT = 32;
/** Odsazení od okraje, ať tloušťka čáry a bod nejsou useknuté. */
const PADDING = 3;
/** Poloměr zvýrazněného posledního bodu. */
const DOT_RADIUS = 2.5;

const props = defineProps({
    /** Hodnoty od nejstarší; null = mezera. */
    values: { type: Array, required: true },
    /** Popis pro čtečku („Vývoj za posledních 14 stažení: …“). */
    label: { type: String, required: true },
    /** Zvýraznit propad (barva čáry). */
    alert: { type: Boolean, default: false },
    /** Pevné maximum osy (procenta = 100); jinak největší hodnota. */
    max: { type: Number, default: null },
});

/** Body [x, y] po úsecích bez mezer. */
const segments = computed(() => {
    const numbers = props.values.filter((value) => value !== null);
    const top = props.max ?? Math.max(1, ...numbers);
    const step = props.values.length > 1 ? (WIDTH - 2 * PADDING) / (props.values.length - 1) : 0;
    const result = [];
    let current = [];
    props.values.forEach((value, index) => {
        if (value === null) {
            if (current.length) {
                result.push(current);
            }
            current = [];

            return;
        }
        const x = PADDING + index * step;
        const y = HEIGHT - PADDING - (Math.min(value, top) / top) * (HEIGHT - 2 * PADDING);
        current.push([x, y]);
    });
    if (current.length) {
        result.push(current);
    }

    return result;
});

/** Poslední bod grafu. */
const lastPoint = computed(() => segments.value.at(-1)?.at(-1) ?? null);
</script>

<template>
    <svg class="sparkline" :class="{ 'sparkline--alert': alert }" :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" role="img" :aria-label="label">
        <polyline v-for="(points, index) in segments" :key="index" class="sparkline__line" :points="points.map((point) => point.join(',')).join(' ')" />
        <circle v-if="lastPoint" class="sparkline__dot" :cx="lastPoint[0]" :cy="lastPoint[1]" :r="DOT_RADIUS" />
    </svg>
</template>
