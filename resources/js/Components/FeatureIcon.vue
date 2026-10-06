<!--
    Ikona přednosti na úvodní stránce (R44, R90) — jednoduché čárové SVG v barvě textu.
    Dekorativní, význam nese nadpis vedle.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** watch / unit_price / cards / upcoming / history / shopping / notify / mentions / free */
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
        <!-- Kalendář s šipkou dopředu: akce, které ještě nezačaly -->
        <template v-else-if="name === 'upcoming'">
            <rect x="3.5" y="5" width="17" height="15" rx="2" />
            <path d="M3.5 10h17M8 3v4M16 3v4M10 15h5M13 13l2 2-2 2" />
        </template>
        <!-- Klesající graf: nejnižší cena za sledované období -->
        <template v-else-if="name === 'history'">
            <path d="M4 4v16h16" />
            <path d="m7 8 4 4 3-2 5 5M19 11v4h-4" />
        </template>
        <!-- Seznam s fajfkami -->
        <template v-else-if="name === 'shopping'">
            <path d="m4 6.5 1.5 1.5L8 5.5M4 12.5 5.5 14 8 11.5M4.5 18.5h2M11 7h9M11 13h9M11 19h9" />
        </template>
        <!-- Zvonek -->
        <template v-else-if="name === 'notify'">
            <path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15z" />
            <path d="M10 20.5a2 2 0 0 0 4 0" />
        </template>
        <!-- Leták s otazníkem ceny -->
        <template v-else-if="name === 'mentions'">
            <path d="M6 3h9l4 4v14H6zM15 3v4h4" />
            <path d="M10 11.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12.5 18h.01" />
        </template>
        <!-- Cenovka s procentem -->
        <template v-else>
            <path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z" />
            <path d="M8 8h.01M9 15l6-6M14 15h.01" />
        </template>
    </svg>
</template>
