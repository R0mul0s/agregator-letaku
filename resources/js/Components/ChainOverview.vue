<!--
    Moje slevy „Podle obchodů“ (R102) — kam jet nakoupit. Obchody od toho, kde je nejlevněji
    nejvíc hlídaných položek (App\Domain\Matching\ChainOverview); u každého nejvýhodnější akce
    každé položky jako řádek s názvem položky a štítkem „Nejlevněji“, když jinde levněji není.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import OfferRow from '@/Components/OfferRow.vue';
import { useTranslations } from '@/lib/i18n';
import { computed } from 'vue';

const props = defineProps({
    /** Obchody [{ chain, cheapestCount, items: [{ watchItemId, offerId, cheapest }] }] z HomeController. */
    byChain: { type: Array, required: true },
    /** Hlídané položky s akcemi (watchItems z HomeController) — odtud akce a názvy položek. */
    watchItems: { type: Array, required: true },
});

const t = useTranslations();

/**
 * Akce podle položky a ID — stejná akce může patřit ke dvěma položkám s jiným stavem shody
 * (Coca-Cola jistě, Coca-Cola Zero „možná“), proto klíč „položka:akce“. Názvy položek podle ID.
 */
const offersByItem = computed(() => new Map(props.watchItems.flatMap((item) => item.offers.map((offer) => [`${item.id}:${offer.id}`, offer]))));
const itemNames = computed(() => new Map(props.watchItems.map((item) => [item.id, item.name])));

/** Obchody s řádky: nejdřív položky, kde je obchod nejlevněji, pak podle názvu položky. */
const chains = computed(() =>
    props.byChain.map((entry) => ({
        ...entry,
        rows: entry.items
            .map((item) => ({ ...item, name: itemNames.value.get(item.watchItemId), offer: offersByItem.value.get(`${item.watchItemId}:${item.offerId}`) }))
            .filter((row) => row.offer)
            .sort((a, b) => Number(b.cheapest) - Number(a.cheapest) || a.name.localeCompare(b.name)),
    })),
);
</script>

<template>
    <div class="chain-overview">
        <section v-for="entry in chains" :key="entry.chain" class="chain-overview__chain card">
            <h2 class="chain-overview__header">
                <ChainLogo :chain="entry.chain" large />
                <span class="chain-overview__summary">{{ t('home.by_chain_summary', { cheapest: entry.cheapestCount, count: entry.rows.length }) }}</span>
            </h2>
            <ul class="offer-rows">
                <OfferRow v-for="row in entry.rows" :key="row.watchItemId" :offer="row.offer">
                    <template #label>
                        {{ row.name }}
                        <span v-if="row.cheapest" class="tag tag--success">{{ t('home.cheapest_here') }}</span>
                    </template>
                </OfferRow>
            </ul>
        </section>
    </div>
</template>
