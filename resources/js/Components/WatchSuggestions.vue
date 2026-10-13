<!--
    Návrhy, co hlídat (R148) — co si uživatel opakovaně dává do nákupního seznamu (server,
    WatchSuggestions) a v Hlídám i co opakovaně hledá (jen v prohlížeči, lib/search.js).
    Produkt katalogu jde začít hlídat jedním klepnutím; vlastní slova otevřou formulář
    s náhledem (v Hlídám přímo, odjinud přes ?pridat=). „Ne, díky“ návrh zavře natrvalo.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { dismissFrequentSearch, frequentSearches, normalizeSearch } from '@/lib/search';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    /**
     * Návrhy ze serveru a nastavení: { items: [{ id, name, productId, hits, dismissUrl }],
     * urls: { store, index, prefillParameter, stayField }, searchMinHits?, searchWindowDays?, maxShown? }.
     */
    suggestions: { type: Object, required: true },
    /** Produkty katalogu (Hlídám) — opakované hledání se k nim páruje podle názvu; prázdné = bez návrhů z hledání. */
    products: { type: Array, default: () => [] },
    /** Hlídané položky (Hlídám) — co už hlídá, se z hledání nenavrhne. */
    watchItems: { type: Array, default: () => [] },
    /** Vlastní slova otevře stránka sama (Hlídám, událost `own`); jinak přechod do Hlídám. */
    ownInPage: { type: Boolean, default: false },
});

/** Vlastní slova k doplnění ve formuláři Hlídám (text). */
const emit = defineEmits(['own']);

const t = useTranslations();

/** Opakovaná hledání z prohlížeče — čtou se po připojení komponenty (localStorage). */
const searches = ref([]);

onMounted(() => {
    if (props.products.length && props.suggestions.searchMinHits) {
        searches.value = frequentSearches(props.suggestions.searchMinHits, props.suggestions.searchWindowDays);
    }
});

/** Normalizované názvy a slova, která uživatel už hlídá. */
const watchedTerms = computed(() => new Set(props.watchItems.flatMap((item) => [item.name, item.keywords].filter(Boolean).map(normalizeSearch))));

/** Návrhy z hledání: produkt se stejným názvem, jinak vlastní slova; bez hlídaného a bez návrhu ze serveru. */
const searchItems = computed(() => {
    const serverProducts = new Set(props.suggestions.items.map((item) => item.productId).filter(Boolean));
    const serverNames = new Set(props.suggestions.items.map((item) => normalizeSearch(item.name)));

    return searches.value.flatMap(({ text, count }) => {
        const term = normalizeSearch(text);
        const product = props.products.find((candidate) => normalizeSearch(candidate.name) === term);
        if (product?.watched || watchedTerms.value.has(term) || serverNames.has(term) || (product && serverProducts.has(product.id))) {
            return [];
        }

        return [{ key: `q:${term}`, name: product?.name ?? text, productId: product?.id ?? null, reason: t('watch.suggestions.reason_search', { count }), search: text }];
    });
});

/** Všechny návrhy k ukázání — ze seznamu napřed, nejvýš maxShown. */
const items = computed(() => {
    const fromList = props.suggestions.items.map((item) => ({
        key: `s:${item.id}`,
        name: item.name,
        productId: item.productId,
        reason: t('watch.suggestions.reason_shopping', { count: item.hits }),
        dismissUrl: item.dismissUrl,
    }));
    const all = [...fromList, ...searchItems.value];

    return props.suggestions.maxShown ? all.slice(0, props.suggestions.maxShown) : all;
});

/**
 * Začne hlídat: produkt rovnou (stránka zůstane), vlastní slova ve formuláři s náhledem.
 *
 * @param {object} item
 */
function accept(item) {
    const { urls } = props.suggestions;
    if (item.productId) {
        router.post(urls.store, { product_id: item.productId, name: item.name, [urls.stayField]: true }, { preserveScroll: true, preserveState: true });
    } else if (props.ownInPage) {
        emit('own', item.name);
    } else {
        router.get(urls.index, { [urls.prefillParameter]: item.name });
    }
}

/**
 * „Ne, díky“ — návrh ze seznamu zavře server, z hledání si to pamatuje prohlížeč.
 *
 * @param {object} item
 */
function dismiss(item) {
    if (item.dismissUrl) {
        router.delete(item.dismissUrl, { preserveScroll: true, preserveState: true });

        return;
    }
    dismissFrequentSearch(item.search);
    searches.value = searches.value.filter((search) => normalizeSearch(search.text) !== normalizeSearch(item.search));
}
</script>

<template>
    <section v-if="items.length" class="card watch-suggestions" aria-labelledby="watch-suggestions-title">
        <h2 id="watch-suggestions-title" class="watch-suggestions__title">
            <!-- Žárovka — nápad -->
            <svg class="watch-suggestions__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z" /></svg>
            {{ t('watch.suggestions.title') }}
        </h2>
        <p class="watch-suggestions__hint">{{ t('watch.suggestions.hint') }}</p>
        <ul class="watch-suggestions__list">
            <li v-for="item in items" :key="item.key" class="watch-suggestions__item">
                <span class="watch-suggestions__body">
                    <span class="watch-suggestions__name">{{ item.name }}</span>
                    <span class="watch-suggestions__reason">{{ item.reason }}</span>
                </span>
                <span class="watch-suggestions__actions">
                    <button type="button" class="button button--soft" :aria-label="t('watch.suggestions.watch_label', { name: item.name })" @click="accept(item)">
                        {{ t('watch.suggestions.watch') }}
                    </button>
                    <button type="button" class="link-button" :aria-label="t('watch.suggestions.dismiss_label', { name: item.name })" @click="dismiss(item)">
                        {{ t('watch.suggestions.dismiss') }}
                    </button>
                </span>
            </li>
        </ul>
    </section>
</template>
