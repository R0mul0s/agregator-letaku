<!--
    Prázdný stav s maskotem Slevohlídky — text a volitelná akce (odkaz) ve slotu.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** Hlavní věta („Zatím nic nehlídáte.“). */
    text: { type: String, required: true },
});
</script>

<template>
    <div class="empty-state">
        <!-- Maskot je dekorace, sdělení nese text -->
        <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="empty-state__mascot" />
        <p class="empty-state__text">{{ text }}</p>
        <div v-if="$slots.default" class="empty-state__action">
            <slot />
        </div>
    </div>
</template>
