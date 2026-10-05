<!--
    Ikona tématu na stránce Kontakt (R72) — čárové SVG v barvě textu. Dekorativní, význam
    nese nadpis tématu vedle.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
defineProps({
    /** price / idea / chains / privacy / security / email / phone / place */
    name: { type: String, required: true },
});
</script>

<template>
    <svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true">
        <!-- Cenovka s otazníkem -->
        <template v-if="name === 'price'">
            <path d="M3 12V4h8l10 10-8 8z" />
            <path d="M9.5 9.5a1.5 1.5 0 1 1 1.5 1.5v1M11 15h.01" />
        </template>
        <!-- Žárovka -->
        <template v-else-if="name === 'idea'">
            <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z" />
        </template>
        <!-- Obchod s markýzou -->
        <template v-else-if="name === 'chains'">
            <path d="M4 10v10h16V10M3 6l1.5-3h15L21 6v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z" />
            <path d="M10 20v-5h4v5" />
        </template>
        <!-- Štít s postavou -->
        <template v-else-if="name === 'privacy'">
            <path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z" />
            <circle cx="12" cy="10" r="2.5" />
            <path d="M8 16.5c.8-1.6 2.3-2.5 4-2.5s3.2.9 4 2.5" />
        </template>
        <!-- Zámek -->
        <template v-else-if="name === 'security'">
            <rect x="5" y="11" width="14" height="10" rx="2" />
            <path d="M8 11V8a4 4 0 0 1 8 0v3M12 15v2" />
        </template>
        <!-- Obálka -->
        <template v-else-if="name === 'email'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3 7l9 6 9-6" />
        </template>
        <!-- Telefon -->
        <template v-else-if="name === 'phone'">
            <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z" />
        </template>
        <!-- Špendlík na mapě -->
        <template v-else>
            <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" />
            <circle cx="12" cy="9.5" r="2.5" />
        </template>
    </svg>
</template>
