<!--
    Štítky filtrů akcí v Mých slevách (Nové, Končí brzy, Jen jisté, R101) s počtem akcí —
    stejné v okně Filtry na telefonu i v pruhu na širokém displeji (R113, dřív dvě kopie).

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import FilterChip from '@/Components/FilterChip.vue';

defineProps({
    /** Štítky k zobrazení: [{ key, label }]. */
    chips: { type: Array, required: true },
    /** Zapnuté filtry: { [key]: boolean }. */
    active: { type: Object, required: true },
    /** Počet akcí, které by štítek ukázal: { [key]: number }. */
    counts: { type: Object, required: true },
});

const emit = defineEmits(['toggle']);
</script>

<template>
    <FilterChip v-for="chip in chips" :key="chip.key" :on="active[chip.key]" :count="counts[chip.key]" @click="emit('toggle', chip.key)">
        {{ chip.label }}
    </FilterChip>
</template>
