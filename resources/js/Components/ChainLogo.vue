<!--
    Logo obchodu (public/images/chains) s názvem pro čtečky; volitelně i s viditelným názvem.
    Atributy width a height nesou poměr stran loga (chainInfo.logoSize) — místo je vyhrazené
    před načtením obrázku a řada log neposkočí (R123); velikost řídí CSS.
    Na kartě akce jde logo rozkliknout (R118) — malé logo nemusí každý poznat a title se na
    dotykovém displeji neukáže (R55), klepnutí ukáže název vedle loga.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Hodnota App\Enums\Chain („kaufland“). */
    chain: { type: String, required: true },
    /** Ukázat vedle loga i název (výběr obchodu, nastavení). */
    withName: { type: Boolean, default: false },
    /** Větší logo (nadpisy). */
    large: { type: Boolean, default: false },
    /** Logo jako tlačítko, které ukáže a schová název (karta akce, R118). */
    revealable: { type: Boolean, default: false },
});

const page = usePage();

/** Název a logo ze sdílených dat (HandleInertiaRequests). */
const info = computed(() => page.props.chainInfo[props.chain] ?? { name: props.chain, logo: null });

/** Název ukázaný klepnutím na logo. */
const nameShown = ref(false);
</script>

<template>
    <!-- Čtečka název přečte vždy (skrytý jen vizuálně), klepnutí ho ukáže i očima -->
    <button
        v-if="revealable && info.logo && !withName"
        type="button"
        class="chain-logo chain-logo--button"
        :class="{ 'chain-logo--large': large }"
        :title="info.name"
        :aria-expanded="nameShown ? 'true' : 'false'"
        @click="nameShown = !nameShown"
    >
        <img :src="info.logo" alt="" class="chain-logo__image" :width="info.logoSize?.width" :height="info.logoSize?.height" />
        <span class="chain-logo__name" :class="{ 'visually-hidden': !nameShown }">{{ info.name }}</span>
    </button>
    <span v-else class="chain-logo" :class="{ 'chain-logo--large': large }" :title="info.name">
        <img v-if="info.logo" :src="info.logo" :alt="withName ? '' : info.name" class="chain-logo__image" :width="info.logoSize?.width" :height="info.logoSize?.height" />
        <span v-if="withName || !info.logo" class="chain-logo__name">{{ info.name }}</span>
    </span>
</template>
