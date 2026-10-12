<!--
    Zapnuté filtry na telefonu (R102, vzhled R144) — Moje slevy i Všechny akce. Uvnitř karty filtrů
    pod linkou: „Zapnuto (N)“ se „Zrušit vše“ a malé štítky v jednom posuvném řádku. Klepnutí filtr
    vypne (s křížkem), nebo otevře okno Filtry (bez křížku, např. vybrané obchody — ty mají loga).

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ScrollRow from '@/Components/ScrollRow.vue';
import { useTranslations } from '@/lib/i18n';

defineProps({
    /** [{ key, label, action, removable?, chains? }] — removable false = bez křížku, chains = loga obchodů. */
    chips: { type: Array, required: true },
    /** Je co zrušit tlačítkem „Zrušit vše“ (filtry, které jdou vypnout). */
    canClear: { type: Boolean, default: false },
});

defineEmits(['clear']);

const t = useTranslations();
</script>

<template>
    <div v-if="chips.length" class="active-filters" role="group" :aria-label="t('search.filters')">
        <div class="active-filters__head">
            <span class="active-filters__title">{{ t('search.active_filters', { count: chips.length }) }}</span>
            <button v-if="canClear" type="button" class="link-button active-filters__clear" @click="$emit('clear')">{{ t('search.clear_all') }}</button>
        </div>
        <ScrollRow class="active-filters__row">
            <button v-for="chip in chips" :key="chip.key" type="button" class="active-filters__chip" :class="{ 'active-filters__chip--logos': chip.chains?.length }" @click="chip.action">
                <span v-if="chip.chains?.length" class="active-filters__logos" aria-hidden="true">
                    <ChainLogo v-for="chain in chip.chains" :key="chain" :chain="chain" />
                </span>
                {{ chip.label }}
                <template v-if="chip.removable !== false">
                    <svg class="active-filters__remove" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    <span class="visually-hidden">{{ t('search.remove_filter') }}</span>
                </template>
            </button>
        </ScrollRow>
    </div>
</template>
