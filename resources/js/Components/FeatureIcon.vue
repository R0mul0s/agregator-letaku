<!--
    Ikona přednosti na úvodní stránce (R44) — jednoduché čárové SVG v barvě textu. Dekorativní,
    význam nese nadpis vedle.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** watch / unit_price / cards / mentions / digest / free */
    name: { type: String, required: true },
});
</script>

<template>
    <svg class="feature-icon" viewBox="0 0 24 24" aria-hidden="true">
        <!-- Lupa s fajfkou: hlídá, co kupujete -->
        <template v-if="name === 'watch'">
            <circle cx="10.5" cy="10.5" r="6.5" />
            <path d="M15.5 15.5 21 21M7.5 10.5l2 2 3.5-4" />
        </template>
        <!-- Váha: cena za kilo -->
        <template v-else-if="name === 'unit_price'">
            <path d="M12 3v3M5 21h14M7 21l1.5-9h7L17 21M8.5 12a3.5 3.5 0 0 1 7 0" />
        </template>
        <!-- Karta -->
        <template v-else-if="name === 'cards'">
            <rect x="3" y="5.5" width="18" height="13" rx="2" />
            <path d="M3 10h18M7 15h4" />
        </template>
        <!-- Leták s otazníkem ceny -->
        <template v-else-if="name === 'mentions'">
            <path d="M6 3h9l4 4v14H6zM15 3v4h4" />
            <path d="M10 11.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12.5 18h.01" />
        </template>
        <!-- Obálka -->
        <template v-else-if="name === 'digest'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
        </template>
        <!-- Cenovka s procentem -->
        <template v-else>
            <path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z" />
            <path d="M8 8h.01M9 15l6-6M14 15h.01" />
        </template>
    </svg>
</template>
