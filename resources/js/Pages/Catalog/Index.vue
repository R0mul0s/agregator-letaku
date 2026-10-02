<!--
    Katalog produktů pro admina (R29) — tabulka produktů s hledáním, filtrem oddělení a řazením;
    formulář nového produktu se otevře tlačítkem.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import ProductForm from '@/Components/ProductForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { normalizeSearch } from '@/lib/search';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Kategorie [{ id, label }] pro výběr ve formuláři. */
    categories: { type: Array, required: true },
    /** Produkty s oddělením, kategorií, slovy a počty (matchCount, maybeCount, watchersCount). */
    products: { type: Array, required: true },
});

const t = useTranslations();

/** Sloupce, podle kterých jde řadit: klíč => hodnota produktu pro porovnání. */
const SORT_VALUES = {
    name: (product) => normalizeSearch(product.name),
    category: (product) => normalizeSearch(product.categoryLabel ?? '￿'),
    offers: (product) => product.matchCount + product.maybeCount,
    watchers: (product) => product.watchersCount,
};

/** Číselné sloupce se řadí nejdřív od největšího. */
const NUMERIC_SORTS = ['offers', 'watchers'];

const showForm = ref(false);
const query = ref('');
const department = ref('');
const sort = ref({ key: 'name', descending: false });

/** Oddělení, která v katalogu jsou, abecedně. */
const departments = computed(() => [...new Set(props.products.map((product) => product.department).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'cs')));

/** Produkty podle hledání (název, kategorie, slova) a oddělení, seřazené podle zvoleného sloupce. */
const rows = computed(() => {
    const words = normalizeSearch(query.value).split(/\s+/).filter(Boolean);
    const value = SORT_VALUES[sort.value.key];
    const direction = sort.value.descending ? -1 : 1;

    return props.products
        .filter((product) => !department.value || product.department === department.value)
        .filter((product) => {
            const text = normalizeSearch(`${product.name} ${product.categoryLabel ?? ''} ${product.keywords}`);

            return words.every((word) => text.includes(word));
        })
        .sort((a, b) => {
            const [x, y] = [value(a), value(b)];

            return (x < y ? -1 : x > y ? 1 : 0) * direction;
        });
});

/**
 * Seřadí podle sloupce; opakované klepnutí obrátí směr.
 *
 * @param {string} key
 */
function sortBy(key) {
    sort.value = sort.value.key === key ? { key, descending: !sort.value.descending } : { key, descending: NUMERIC_SORTS.includes(key) };
}

/**
 * Hodnota aria-sort pro hlavičku sloupce.
 *
 * @param {string} key
 * @returns {string}
 */
function ariaSort(key) {
    if (sort.value.key !== key) {
        return 'none';
    }

    return sort.value.descending ? 'descending' : 'ascending';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('catalog.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('catalog.title') }}</h1>
            <button type="button" class="button button--primary" :aria-expanded="showForm ? 'true' : 'false'" @click="showForm = !showForm">
                {{ showForm ? t('catalog.close_form') : t('catalog.add_title') }}
            </button>
            <p class="page__subtitle">{{ t('catalog.intro') }}</p>
        </header>

        <section v-if="showForm" class="card catalog-new">
            <h2 class="card__title">{{ t('catalog.add_title') }}</h2>
            <ProductForm :url="urls.store" :categories="categories" :submit-label="t('catalog.add')" />
        </section>

        <EmptyState v-if="!products.length" :text="t('catalog.empty')" />

        <template v-else>
            <div class="catalog-toolbar">
                <div class="form-field catalog-toolbar__search">
                    <label for="catalog-search" class="form-field__label">{{ t('catalog.search') }}</label>
                    <input id="catalog-search" v-model="query" type="search" class="form-field__input" :placeholder="t('catalog.search_placeholder')" />
                </div>
                <div class="form-field">
                    <label for="catalog-department" class="form-field__label">{{ t('catalog.department') }}</label>
                    <select id="catalog-department" v-model="department" class="form-field__input">
                        <option value="">{{ t('catalog.all_departments') }}</option>
                        <option v-for="name in departments" :key="name" :value="name">{{ name }}</option>
                    </select>
                </div>
                <p class="catalog-toolbar__count" role="status">{{ t('catalog.count', { count: rows.length }) }}</p>
            </div>

            <EmptyState v-if="!rows.length" :text="t('catalog.no_results')" />

            <!-- Široká tabulka se na mobilu posouvá vodorovně, stránka zůstává v šířce displeje -->
            <div v-else class="data-table">
                <table class="data-table__table">
                    <thead>
                        <tr>
                            <th v-for="column in ['name', 'category']" :key="column" scope="col" :aria-sort="ariaSort(column)">
                                <button type="button" class="data-table__sort" @click="sortBy(column)">
                                    {{ t(`catalog.columns.${column}`) }}
                                    <span class="data-table__sort-icon" aria-hidden="true">{{ sort.key === column ? (sort.descending ? '▼' : '▲') : '↕' }}</span>
                                </button>
                            </th>
                            <th scope="col">{{ t('catalog.columns.keywords') }}</th>
                            <th
                                v-for="column in ['offers', 'watchers']"
                                :key="column"
                                scope="col"
                                class="data-table__number"
                                :aria-sort="ariaSort(column)"
                            >
                                <button type="button" class="data-table__sort" @click="sortBy(column)">
                                    {{ t(`catalog.columns.${column}`) }}
                                    <span class="data-table__sort-icon" aria-hidden="true">{{ sort.key === column ? (sort.descending ? '▼' : '▲') : '↕' }}</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in rows" :key="product.id">
                            <th scope="row" class="data-table__primary">
                                <Link :href="product.showUrl" class="link">{{ product.name }}</Link>
                            </th>
                            <td :title="product.categoryLabel">
                                <span v-if="product.categoryName" class="catalog-category">
                                    <span class="catalog-category__department">{{ product.department }}</span>
                                    {{ product.categoryName }}
                                </span>
                                <span v-else class="data-table__muted">{{ t('catalog.no_category') }}</span>
                            </td>
                            <td>
                                <code class="catalog-rule">{{ product.keywords }}</code>
                                <span v-if="product.variantKeywords" class="catalog-rule catalog-rule--variant">+ {{ product.variantKeywords }}</span>
                                <span v-if="product.excludeKeywords" class="catalog-rule catalog-rule--exclude" :title="product.excludeKeywords">
                                    {{ t('catalog.exclude_count', { count: product.excludeKeywords.split(' ').length }) }}
                                </span>
                            </td>
                            <td class="data-table__number">
                                <strong>{{ product.matchCount }}</strong>
                                <span v-if="product.maybeCount" class="tag tag--warning">+{{ product.maybeCount }}</span>
                            </td>
                            <td class="data-table__number" :class="{ 'data-table__muted': !product.watchersCount }">{{ product.watchersCount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
