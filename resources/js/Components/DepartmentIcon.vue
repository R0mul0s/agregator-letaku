<!--
    Ikona oddělení katalogu na dlaždici v Hlídám (R47) — čárové SVG v barvě textu. Klíč posílá
    server podle názvu oddělení (config letaky.catalog.department_icons). Dekorativní, význam
    nese název oddělení vedle.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** produce / dairy / bakery / meat / frozen / pantry / drinks / special / cleaning / drugstore / baby / pets / other */
    name: { type: String, required: true },
});
</script>

<template>
    <svg class="department-icon" viewBox="0 0 24 24" aria-hidden="true">
        <!-- Jablko -->
        <template v-if="name === 'produce'">
            <path d="M12 7.5c-2-1.5-6.5-1.2-7.3 3C4 14.5 6.5 19.5 9 20.3c1.2.4 2 0 3-.5 1 .5 1.8.9 3 .5 2.5-.8 5-5.8 4.3-9.8-.8-4.2-5.3-4.5-7.3-3z" />
            <path d="M12 7.5c0-2 .8-3.6 2.8-4.5" />
        </template>
        <!-- Krabice mléka -->
        <template v-else-if="name === 'dairy'">
            <path d="M8 2.5h8V5l2 3v12.5a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8l2-3z" />
            <path d="M6 8h12M10 13h4" />
        </template>
        <!-- Bochník chleba -->
        <template v-else-if="name === 'bakery'">
            <path d="M5 11.5A4 4 0 0 1 6 4c2-.6 4 .5 6 .5s4-1.1 6-.5a4 4 0 0 1 1 7.5v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z" />
            <path d="M9 9.5v3M12 9v3M15 9.5v3" />
        </template>
        <!-- Stehno -->
        <template v-else-if="name === 'meat'">
            <path d="M15.5 3a5.5 5.5 0 0 1 3.9 9.4c-1.6 1.6-3.8 2.1-5.6 1.6l-3.3 3.3a2 2 0 1 1-2.8 2.8 2 2 0 1 1-2.8-2.8 2 2 0 1 1 2.8-2.8l3.3-3.3c-.5-1.8 0-4 1.6-5.6A5.5 5.5 0 0 1 15.5 3z" />
        </template>
        <!-- Vločka -->
        <template v-else-if="name === 'frozen'">
            <path d="M12 2v20M3.5 7l17 10M20.5 7l-17 10M9 3.5l3 2 3-2M9 20.5l3-2 3 2" />
        </template>
        <!-- Zavařovačka -->
        <template v-else-if="name === 'pantry'">
            <path d="M8 3h8v3H8z" />
            <path d="M7 6h10a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zM6 11h12M6 16h12" />
        </template>
        <!-- Láhev -->
        <template v-else-if="name === 'drinks'">
            <path d="M10 2h4v4l2 3v12a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V9l2-3z" />
            <path d="M8 13h8" />
        </template>
        <!-- List -->
        <template v-else-if="name === 'special'">
            <path d="M5 19C5 10 11 5 20 4c-1 9-6 15-15 15z" />
            <path d="M5 19l8-8" />
        </template>
        <!-- Rozprašovač -->
        <template v-else-if="name === 'cleaning'">
            <path d="M8 10h7v11H8z" />
            <path d="M9.5 10V6h4l4.5 2M13.5 6V3.5H10" />
        </template>
        <!-- Kapka -->
        <template v-else-if="name === 'drugstore'">
            <path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z" />
            <path d="M9.5 14.5a2.5 2.5 0 0 0 2.5 2.5" />
        </template>
        <!-- Kojenecká láhev -->
        <template v-else-if="name === 'baby'">
            <path d="M10 2h4l1 4H9z" />
            <rect x="8" y="6" width="8" height="16" rx="2" />
            <path d="M8 11h3M8 15h3" />
        </template>
        <!-- Tlapka -->
        <template v-else-if="name === 'pets'">
            <circle cx="6.5" cy="10.5" r="1.6" />
            <circle cx="10" cy="6" r="1.6" />
            <circle cx="14" cy="6" r="1.6" />
            <circle cx="17.5" cy="10.5" r="1.6" />
            <path d="M12 12c-3 0-5 3.5-5 5.5s2 2.5 5 1.5c3 1 5 .5 5-1.5S15 12 12 12z" />
        </template>
        <!-- Košík -->
        <template v-else>
            <path d="M3 9h18l-2 11H5z" />
            <path d="M8 9l3-6M16 9l-3-6M9 13v4M15 13v4" />
        </template>
    </svg>
</template>
