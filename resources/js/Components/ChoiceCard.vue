<!--
    Velký přepínač s logem obchodu, názvem a větou pod ním — průvodce prvním nastavením (R153):
    obchody, které uživatel sleduje, a jeho věrnostní karty. Celá karta je popisek přepínače
    (role="switch"), klepnutí kamkoli ho přepne.

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';

defineProps({
    /** Obchod (hodnota App\Enums\Chain) pro logo. */
    chain: { type: String, required: true },
    /** Název volby (obchod, karta). */
    title: { type: String, required: true },
    /** Věta pod názvem; null = žádná. */
    hint: { type: String, default: null },
    /** Věta pod názvem jako přínos (zeleně) — kolik akcí přibude. */
    gain: { type: Boolean, default: false },
});

const on = defineModel({ type: Boolean, required: true });
</script>

<template>
    <label class="choice-card" :class="{ 'choice-card--on': on }">
        <ChainLogo :chain="chain" large class="choice-card__logo" />
        <span class="choice-card__text">
            <span class="choice-card__title">{{ title }}</span>
            <span v-if="hint" class="choice-card__hint" :class="{ 'choice-card__hint--gain': gain }">{{ hint }}</span>
        </span>
        <span class="form-switch">
            <input v-model="on" type="checkbox" role="switch" class="form-switch__input" />
        </span>
    </label>
</template>
