<!--
    Živá ukázka hlídání na úvodní stránce (R90) místo tří kroků „Jak to funguje“ — návštěvník
    bez registrace zapíná obchody a produkty katalogu a hned vidí, kolik akcí by mu Slevohlídka
    hlídala a které jsou nejlevnější za jednotku (WatchDemoController). Po každé změně výběru
    dotaz s krátkou pauzou, předchozí se zruší.

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import OfferRow from '@/Components/OfferRow.vue';
import { useTweenedNumber } from '@/lib/countUp';
import { debounce } from '@/lib/debounce';
import { formatNumber } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { ABORTED, createLatestRequest } from '@/lib/latestRequest';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/** Pauza po změně výběru, než se načte výsledek (ms) — rychlé klepání pošle jeden dotaz. */
const DEBOUNCE_MS = 250;

/** Kroky ukázky (lang: ui.landing.steps). */
const STEPS = ['chains', 'watch', 'hunt'];

const props = defineProps({
    /** Obchody se zdrojem dat (hodnoty App\Enums\Chain), na začátku zapnuté všechny. */
    chains: { type: Array, required: true },
    /** { url, products: [{ id, name, icon }], preselected: [id], initial: { count, offers } } */
    demo: { type: Object, required: true },
    /** Adresa registrace (výzva pod výsledkem). */
    registerUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

const selectedChains = ref([...props.chains]);
const selectedProducts = ref([...props.demo.preselected]);
const result = ref(props.demo.initial);
const loading = ref(false);
const request = createLatestRequest();

/** Je co hledat? Bez obchodu nebo produktu se dotaz neposílá. */
const ready = computed(() => selectedChains.value.length > 0 && selectedProducts.value.length > 0);
const shownCount = useTweenedNumber(() => (ready.value ? result.value.count : 0));

/**
 * Zapne, nebo vypne hodnotu v seznamu výběru.
 *
 * @param {import('vue').Ref<Array>} list
 * @param {string|number} value
 */
function toggle(list, value) {
    list.value = list.value.includes(value) ? list.value.filter((item) => item !== value) : [...list.value, value];
}

/** Načte výsledek pro aktuální výběr; všechny obchody = bez parametru obchodů. */
async function load() {
    request.cancel();
    if (!ready.value) {
        loading.value = false;

        return;
    }

    loading.value = true;
    const query = new URLSearchParams({ produkty: selectedProducts.value.join(',') });
    if (selectedChains.value.length < props.chains.length) {
        query.set('chain', selectedChains.value.join(','));
    }

    const data = await request.json(`${props.demo.url}?${query}`);
    // Zrušený požadavek (další klepnutí) nic nemění — novější ještě běží
    if (data === ABORTED) {
        return;
    }
    if (data !== null) {
        result.value = data;
    }
    loading.value = false;
}

const loadLater = debounce(load, DEBOUNCE_MS);
watch([selectedChains, selectedProducts], () => loadLater());

onBeforeUnmount(() => {
    loadLater.cancel();
    request.cancel();
});
</script>

<template>
    <!-- Pískoviště s přerušovaným rámečkem a štítkem — kroky nesmí vypadat jako nastavení účtu,
         které se neuložilo -->
    <div class="watch-demo-sandbox">
        <p class="watch-demo-sandbox__head">
            <span class="watch-demo-sandbox__badge"><span class="watch-demo-sandbox__dot" aria-hidden="true"></span>{{ t('landing.demo.badge') }}</span>
            <span class="watch-demo-sandbox__text">{{ t('landing.demo.sandbox') }}</span>
        </p>
        <ol class="watch-demo">
            <li v-for="(step, index) in STEPS" :key="step" class="card watch-demo__step" :class="`watch-demo__step--${step}`">
                <div class="watch-demo__head">
                    <span class="watch-demo__number" aria-hidden="true">{{ index + 1 }}</span>
                    <div>
                        <h3 class="watch-demo__title">{{ t(`landing.steps.${step}.title`) }}</h3>
                        <p class="watch-demo__text">{{ t(`landing.steps.${step}.text`) }}</p>
                    </div>
                </div>

                <div v-if="step === 'chains'" class="watch-demo__chains" role="group" :aria-label="t('landing.demo.chains_label')">
                    <button
                        v-for="chain in chains"
                        :key="chain"
                        type="button"
                        class="watch-demo__chain"
                        :aria-pressed="selectedChains.includes(chain) ? 'true' : 'false'"
                        @click="toggle(selectedChains, chain)"
                    >
                        <ChainLogo :chain="chain" large />
                    </button>
                </div>

                <div v-else-if="step === 'watch'" class="watch-demo__products" role="group" :aria-label="t('landing.demo.products_label')">
                    <button
                        v-for="product in demo.products"
                        :key="product.id"
                        type="button"
                        class="chip watch-demo__product"
                        :aria-pressed="selectedProducts.includes(product.id) ? 'true' : 'false'"
                        @click="toggle(selectedProducts, product.id)"
                    >
                        <DepartmentIcon :name="product.icon" />
                        {{ product.name }}
                    </button>
                </div>

                <div v-else class="watch-demo__result">
                    <p v-if="!ready" class="watch-demo__hint">{{ t('landing.demo.pick_hint') }}</p>
                    <template v-else>
                        <!-- Napočítávané číslo čtečce nepředčítat po snímcích — čte jen konečný počet -->
                        <p class="watch-demo__count" aria-hidden="true">
                            {{ t('landing.demo.count_lead') }}
                            <strong class="watch-demo__count-value">{{ formatNumber(shownCount, page.props.locale) }}</strong>
                            {{ t('landing.demo.count_unit', { count: result.count }) }}
                        </p>
                        <p class="visually-hidden" aria-live="polite">
                            {{ t('landing.demo.count_lead') }} {{ formatNumber(result.count, page.props.locale) }} {{ t('landing.demo.count_unit', { count: result.count }) }}
                        </p>
                        <template v-if="result.offers.length">
                            <p class="watch-demo__subtitle">{{ t('landing.demo.cheapest') }}</p>
                            <ul class="offer-rows watch-demo__offers" :class="{ 'offer-rows--loading': loading }">
                                <OfferRow v-for="offer in result.offers" :key="offer.id" :offer="offer" with-chain />
                            </ul>
                        </template>
                        <p v-else-if="!loading" class="watch-demo__hint">{{ t('landing.demo.nothing') }}</p>
                    </template>
                    <div class="watch-demo__cta-row">
                        <Link :href="registerUrl" class="button button--primary">{{ t('landing.demo.cta') }}</Link>
                        <p class="watch-demo__cta-note">{{ t('landing.demo.cta_note') }}</p>
                    </div>
                </div>
            </li>
        </ol>
    </div>
</template>
