<!--
    Logo obchodu (public/images/chains) s názvem pro čtečky; volitelně i s viditelným názvem.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Hodnota App\Enums\Chain („kaufland“). */
    chain: { type: String, required: true },
    /** Ukázat vedle loga i název (výběr obchodu, nastavení). */
    withName: { type: Boolean, default: false },
    /** Větší logo (nadpisy). */
    large: { type: Boolean, default: false },
});

const page = usePage();

/** Název a logo ze sdílených dat (HandleInertiaRequests). */
const info = computed(() => page.props.chainInfo[props.chain] ?? { name: props.chain, logo: null });
</script>

<template>
    <span class="chain-logo" :class="{ 'chain-logo--large': large }" :title="info.name">
        <img v-if="info.logo" :src="info.logo" :alt="withName ? '' : info.name" class="chain-logo__image" />
        <span v-if="withName || !info.logo" class="chain-logo__name">{{ info.name }}</span>
    </span>
</template>
