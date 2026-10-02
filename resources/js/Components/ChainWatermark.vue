<!--
    Logo obchodu jako vodoznak v pozadí karty — velké, šedé a průhledné, uříznuté rohem karty.
    Jen dekorace: obchod pro čtečky nese ChainLogo v kartě.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** Hodnota App\Enums\Chain („kaufland“). */
    chain: { type: String, required: true },
});

const page = usePage();

/** Adresa loga ze sdílených dat (HandleInertiaRequests). */
const logo = computed(() => page.props.chainInfo[props.chain]?.logo ?? null);
</script>

<template>
    <img v-if="logo" :src="logo" alt="" class="chain-watermark" aria-hidden="true" />
</template>
