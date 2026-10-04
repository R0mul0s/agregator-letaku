<!--
    Ikona položky hlavní navigace (R66) — spodní lišta záložek na telefonu. Kresba podle klíče
    položky (HandleInertiaRequests::NAVIGATION); neznámý klíč nic nevykreslí.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
defineProps({
    /** Klíč položky navigace: home, watch_items, shopping_list, preferences, offers, catalog. */
    name: { type: String, required: true },
});

/** Cesty SVG (viewBox 24 × 24, tah bez výplně) podle klíče položky. */
const PATHS = {
    // Cenovka s procenty
    home: ['M3 12V4h8l10 10-8 8L3 12z', 'M7.5 8.5h.01', 'M15 11l-4 4'],
    // Oko
    watch_items: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
    // Seznam se zaškrtnutím
    shopping_list: ['M9 6h11', 'M9 12h11', 'M9 18h11', 'M3.5 6l1.5 1.5L7.5 5', 'M3.5 12l1.5 1.5L7.5 11', 'M4 18h2'],
    // Obchod s markýzou
    preferences: ['M4 10v10h16V10', 'M3 4h18l-1 6H4L3 4z', 'M10 20v-5h4v5'],
    // Leták — mřížka akcí
    offers: ['M4 4h7v7H4z', 'M13 4h7v7h-7z', 'M4 13h7v7H4z', 'M13 13h7v7h-7z'],
    // Kniha
    catalog: ['M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2V5z', 'M4 19a2 2 0 0 1 2-2h14'],
};
</script>

<template>
    <svg v-if="PATHS[name]" class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path v-for="path in PATHS[name]" :key="path" :d="path" />
    </svg>
</template>
