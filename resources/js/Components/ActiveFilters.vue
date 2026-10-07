<!--
    Zapnuté filtry pod lištou Seřadit / Filtry na telefonu (R102) — Moje slevy i Všechny akce.
    Klepnutí filtr vypne (s křížkem), nebo otevře okno Filtry (bez křížku, např. vybrané obchody).

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import { useTranslations } from '@/lib/i18n';

defineProps({
    /** [{ key, label, action, removable? }] — removable false = bez křížku. */
    chips: { type: Array, required: true },
});

const t = useTranslations();
</script>

<template>
    <div v-if="chips.length" class="active-filters" role="group" :aria-label="t('search.filters')">
        <button v-for="chip in chips" :key="chip.key" type="button" class="search-chip search-chip--on" @click="chip.action">
            {{ chip.label }}
            <template v-if="chip.removable !== false">
                <svg class="search-chip__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
            </template>
        </button>
    </div>
</template>
