<!--
    Přidání do nákupního seznamu (R130) — pole s našeptávačem aktuálních akcí (stejné návrhy
    jako hledání, OfferSuggestionsController) a volbou „Přidat jako vlastní položku“ pro zboží,
    které ve slevě není („Almette“). Vlastní položka může mít obchod, kde ji koupit; bez něj
    patří do skupiny „Kdekoli“. Combobox podle WAI-ARIA jako WatchAdd: šipky vybírají, Enter
    potvrdí (bez výběru vlastní položku), Escape zavře.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainSelect from '@/Components/ChainSelect.vue';
import HighlightText from '@/Components/HighlightText.vue';
import { debounce } from '@/lib/debounce';
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { ABORTED, createLatestRequest } from '@/lib/latestRequest';
import { useListbox } from '@/lib/listbox';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, useId } from 'vue';

/** Pauza v psaní, po které se načtou návrhy (ms) — jako hledání. */
const DEBOUNCE_MS = 200;

const props = defineProps({
    /** { customUrl, suggestionsUrl, suggestMinLength, nameMaxLength, chains: ['kaufland', …] } */
    add: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const id = useId();
const listId = `${id}-list`;

const text = ref('');
const chain = ref('');
const offers = ref([]);
const open = ref(false);
const request = createLatestRequest();

/** Napsaný text bez mezer navíc. */
const query = computed(() => text.value.trim());

/** Akce, které už v seznamu jsou (sdílená vlastnost shoppingList). */
const listed = computed(() => new Set(page.props.shoppingList?.offerIds ?? []));

/** Volby: akce z našeptávače a nakonec vlastní položka s napsaným textem. */
const options = computed(() => [
    ...offers.value.map((offer) => ({ type: 'offer', offer })),
    ...(query.value ? [{ type: 'own' }] : []),
]);

const { activeIndex, activeId, activeOption, moveByArrow } = useListbox(options, listId);

/** Načte návrhy akcí k napsanému textu. */
const loadSuggestions = debounce(async () => {
    if (query.value.length < props.add.suggestMinLength) {
        request.cancel();
        offers.value = [];

        return;
    }
    const data = await request.json(`${props.add.suggestionsUrl}?q=${encodeURIComponent(query.value)}`);
    if (data !== ABORTED) {
        offers.value = data?.offers ?? [];
    }
}, DEBOUNCE_MS);

onBeforeUnmount(() => {
    loadSuggestions.cancel();
    request.cancel();
});

/** Psaní: otevře návrhy a načte akce. */
function onInput() {
    open.value = query.value !== '';
    activeIndex.value = -1;
    loadSuggestions();
}

/** Zavře návrhy. */
function close() {
    open.value = false;
    activeIndex.value = -1;
}

/** Vyprázdní pole po přidání. */
function reset() {
    text.value = '';
    offers.value = [];
    close();
}

/**
 * Přidá volbu: akci (je-li v seznamu, nic), nebo vlastní položku s vybraným obchodem.
 *
 * @param {{ type: string, offer?: object }} option
 */
function choose(option) {
    if (option.type === 'offer') {
        if (!listed.value.has(option.offer.id)) {
            router.post(page.props.shoppingList.toggleUrl, { offer_id: option.offer.id }, { preserveScroll: true });
        }
    } else {
        router.post(props.add.customUrl, { name: query.value, chain: chain.value || null }, { preserveScroll: true });
    }
    reset();
}

/**
 * Ovládání klávesnicí. Enter bez vybrané volby přidá napsaný text jako vlastní položku.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (moveByArrow(event)) {
        open.value = true;
    } else if (event.key === 'Enter' && query.value) {
        event.preventDefault();
        choose(activeOption.value ?? { type: 'own' });
    } else if (event.key === 'Escape') {
        // Prohlížeč by pole typu search Escapem vymazal — Escape jen zavírá
        event.preventDefault();
        close();
    }
}

/**
 * Cena akce v návrhu: běžná, jinak s kartou.
 *
 * @param {object} offer
 * @returns {string}
 */
function offerPrice(offer) {
    return formatPrice(offer.price ?? offer.loyaltyPrice, page.props.locale);
}
</script>

<template>
    <div class="shopping-add">
        <div class="shopping-add__fields">
            <div class="shopping-add__search">
                <label :for="id" class="form-field__label">{{ t('shopping.add_field') }}</label>
                <div class="search-suggest">
                    <div class="search-suggest__control">
                        <span class="search-suggest__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                        </span>
                        <input
                            :id="id"
                            v-model="text"
                            type="search"
                            enterkeyhint="done"
                            class="form-field__input search-suggest__input"
                            :placeholder="t('shopping.add_placeholder')"
                            :maxlength="add.nameMaxLength"
                            role="combobox"
                            autocomplete="off"
                            aria-autocomplete="list"
                            :aria-expanded="open ? 'true' : 'false'"
                            :aria-controls="listId"
                            :aria-activedescendant="activeId"
                            :aria-describedby="`${id}-hint`"
                            @input="onInput"
                            @keydown="onKeydown"
                            @blur="close"
                        />
                    </div>
                    <!-- Počet návrhů pro čtečky (R99) -->
                    <p class="visually-hidden" role="status">{{ open ? t('search.status', { count: options.length }) : '' }}</p>
                    <div v-show="open && options.length" class="search-panel">
                        <ul :id="listId" class="search-panel__list" role="listbox" :aria-label="t('shopping.add_field')">
                            <!-- mousedown místo click — click by přišel až po blur pole, který seznam zavře -->
                            <li
                                v-for="(option, index) in options"
                                :id="`${listId}-${index}`"
                                :key="option.type === 'offer' ? option.offer.id : 'own'"
                                class="search-panel__option"
                                :class="{
                                    'search-panel__option--active': index === activeIndex,
                                    'search-panel__option--disabled': option.type === 'offer' && listed.has(option.offer.id),
                                    'search-panel__option--own': option.type === 'own',
                                }"
                                role="option"
                                :aria-selected="index === activeIndex ? 'true' : 'false'"
                                :aria-disabled="option.type === 'offer' && listed.has(option.offer.id) ? 'true' : undefined"
                                @mousedown.prevent="choose(option)"
                                @mousemove="activeIndex = index"
                            >
                                <template v-if="option.type === 'offer'">
                                    <span class="search-panel__badge"><ChainLogo :chain="option.offer.chain" /></span>
                                    <span class="search-panel__main">
                                        <span class="search-panel__name"><HighlightText :text="option.offer.name" :query="query" /></span>
                                        <span class="search-panel__meta">
                                            <template v-if="option.offer.packageText">{{ option.offer.packageText }} · </template>
                                            <span class="search-panel__hot">{{ offerPrice(option.offer) }}</span>
                                            <template v-if="option.offer.discountPercent"> · {{ formatDiscount(option.offer.discountPercent) }}</template>
                                        </span>
                                    </span>
                                    <span v-if="listed.has(option.offer.id)" class="tag tag--accent">{{ t('shopping.added') }}</span>
                                </template>
                                <template v-else>
                                    <span class="search-panel__badge search-panel__badge--accent" aria-hidden="true">+</span>
                                    <span class="search-panel__main">
                                        <span class="search-panel__name">{{ t('shopping.add_own', { name: query }) }}</span>
                                        <span class="search-panel__meta">{{ t('shopping.add_own_meta') }}</span>
                                    </span>
                                </template>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Výběr s logy obchodů jako v Mých slevách („Jsem v obchodě“) -->
            <ChainSelect :id="`${id}-chain`" v-model="chain" class="shopping-add__chain" :label="t('shopping.add_chain')" :chains="add.chains" :all-label="t('shopping.anywhere')" />
        </div>
        <p :id="`${id}-hint`" class="form-field__hint">{{ t('shopping.add_hint') }}</p>
    </div>
</template>
