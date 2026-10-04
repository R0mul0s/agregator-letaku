<!--
    Text se zvýrazněnými slovy hledání (R71) — shoda od začátku slova, bez ohledu na diakritiku.
    Bez v-html: text je často od obchodu (nedůvěryhodný vstup).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { highlightParts } from '@/lib/search';
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, required: true },
    /** Hledaný text; prázdný = nic nezvýrazní. */
    query: { type: String, default: '' },
});

const parts = computed(() => highlightParts(props.text, props.query));
</script>

<template>
    <template v-for="(part, index) in parts" :key="index">
        <mark v-if="part.match" class="highlight">{{ part.text }}</mark>
        <template v-else>{{ part.text }}</template>
    </template>
</template>
