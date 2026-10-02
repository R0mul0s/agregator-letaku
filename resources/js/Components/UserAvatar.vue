<!--
    Avatar uživatele (R40) — vlastní obrázek, nebo iniciály ze jména (první písmena
    prvních dvou slov). Dekorativní: jméno uživatele nese okolní text nebo popisek tlačítka.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { computed } from 'vue';

/** Kolik slov jména dá iniciály („Roman Hlaváček“ → „RH“). */
const INITIALS_WORDS = 2;

const props = defineProps({
    name: { type: String, required: true },
    /** Adresa obrázku, null = iniciály. */
    url: { type: String, default: null },
    /** Velký avatar (stránka účtu). */
    large: { type: Boolean, default: false },
});

const initials = computed(() =>
    props.name
        .trim()
        .split(/\s+/)
        .slice(0, INITIALS_WORDS)
        .map((word) => word.charAt(0))
        .join('')
        .toLocaleUpperCase(),
);
</script>

<template>
    <span class="avatar" :class="{ 'avatar--large': large }" aria-hidden="true">
        <img v-if="url" :src="url" alt="" class="avatar__image" />
        <span v-else class="avatar__initials">{{ initials }}</span>
    </span>
</template>
