<!--
    Štítek filtru, který jde zapnout a vypnout (R101) — Moje slevy i Všechny akce, na širokém
    displeji i v okně Filtry na telefonu (R102). Volitelně s počtem akcí, které by ukázal.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
defineProps({
    /** Je filtr zapnutý? */
    on: { type: Boolean, default: false },
    /** Počet akcí se zapnutým filtrem; null = bez počtu. */
    count: { type: Number, default: null },
});

defineEmits(['click']);
</script>

<template>
    <button type="button" class="search-chip" :class="{ 'search-chip--on': on }" :aria-pressed="on ? 'true' : 'false'" @click="$emit('click')">
        <slot />
        <span v-if="count !== null" class="search-chip__count">{{ count }}</span>
    </button>
</template>
