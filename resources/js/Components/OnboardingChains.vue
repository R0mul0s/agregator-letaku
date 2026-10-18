<!--
    Průvodce prvním nastavením (R153), kroky 2 a 3: obchody, kde uživatel nakupuje (s počtem
    akcí, které dnes platí), nebo věrnostní karty zapnutých obchodů (s počtem akcí jen pro
    členy, R19). Přepínač ukáže nový stav hned a uloží se celý stav (R62, R64).

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import ChoiceCard from '@/Components/ChoiceCard.vue';
import { formatNumber } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/** Po uložení se načte jen tohle (částečné načtení Inertie). */
const RELOAD = ['chains', 'loyaltyPrograms', 'offersCount', 'errors'];

const props = defineProps({
    /** chains = obchody, cards = karty. */
    mode: { type: String, required: true, validator: (value) => ['chains', 'cards'].includes(value) },
    /** Obchody [{ value, followed, offersCount, loyaltyProgram, loyaltyProgramName, loyaltyOnlyCount }]. */
    chains: { type: Array, required: true },
    /** Karty, které uživatel má (hodnoty App\Enums\LoyaltyProgram). */
    loyaltyPrograms: { type: Array, required: true },
    /** Adresa uložení (onboarding.chains / onboarding.cards). */
    url: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/** Zapnuté volby — obchody, nebo karty; po klepnutí hned nový stav. */
const selected = ref(props.mode === 'chains' ? props.chains.filter((chain) => chain.followed).map((chain) => chain.value) : [...props.loyaltyPrograms]);

/** Karty jen u obchodů, které uživatel sleduje. */
const cardChains = computed(() => props.chains.filter((chain) => chain.followed && chain.loyaltyProgram));

/** Kolik akcí jen pro členy uživatel bez karty neuvidí. */
const hiddenCount = computed(() =>
    cardChains.value.filter((chain) => !selected.value.includes(chain.loyaltyProgram)).reduce((sum, chain) => sum + chain.loyaltyOnlyCount, 0),
);

/**
 * Je volba zapnutá?
 *
 * @param {string} value
 */
function isOn(value) {
    return selected.value.includes(value);
}

/**
 * Přepne volbu.
 *
 * @param {string} value
 * @param {boolean} on
 */
function set(value, on) {
    selected.value = on ? [...selected.value, value] : selected.value.filter((item) => item !== value);
}

// Uložení hned po každé změně, celý stav (R64)
watch(selected, (values) => {
    router.put(props.url, props.mode === 'chains' ? { chains: values } : { loyalty_programs: values }, { preserveScroll: true, preserveState: true, only: RELOAD });
});
</script>

<template>
    <div class="onboarding-choices">
        <template v-if="mode === 'chains'">
            <ul class="onboarding-choices__list">
                <li v-for="chain in chains" :key="chain.value">
                    <ChoiceCard
                        :model-value="isOn(chain.value)"
                        :chain="chain.value"
                        :title="page.props.chainInfo[chain.value]?.name ?? chain.value"
                        :hint="t('onboarding.chain_offers', { count: chain.offersCount, number: formatNumber(chain.offersCount, page.props.locale) })"
                        @update:model-value="set(chain.value, $event)"
                    />
                </li>
            </ul>
            <p v-if="!selected.length" class="onboarding-choices__warning" role="alert">{{ t('onboarding.chains_none') }}</p>
            <p class="onboarding-choices__hint">{{ t('onboarding.chains_stores_hint') }}</p>
        </template>

        <template v-else>
            <p v-if="hiddenCount" class="onboarding-choices__callout">
                <svg class="onboarding-choices__callout-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
                {{ t('onboarding.cards_hidden', { count: hiddenCount, number: formatNumber(hiddenCount, page.props.locale) }) }}
            </p>
            <ul v-if="cardChains.length" class="onboarding-choices__list">
                <li v-for="chain in cardChains" :key="chain.loyaltyProgram">
                    <ChoiceCard
                        :model-value="isOn(chain.loyaltyProgram)"
                        :chain="chain.value"
                        :title="chain.loyaltyProgramName"
                        :hint="chain.loyaltyOnlyCount ? t('onboarding.card_gain', { count: chain.loyaltyOnlyCount, number: formatNumber(chain.loyaltyOnlyCount, page.props.locale) }) : t('onboarding.card_price')"
                        :gain="chain.loyaltyOnlyCount > 0"
                        @update:model-value="set(chain.loyaltyProgram, $event)"
                    />
                </li>
            </ul>
            <p v-else class="onboarding-choices__warning">{{ t('onboarding.cards_no_chains') }}</p>
            <p class="onboarding-choices__hint">{{ t('onboarding.cards_hint') }}</p>
        </template>
    </div>
</template>
