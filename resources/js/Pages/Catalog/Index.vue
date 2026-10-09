<!--
    Katalog produktů pro admina (R29) — tabulka produktů s hledáním, filtrem oddělení, řazením
    a stránkováním (vše na serveru, R43); formulář nového produktu se otevře tlačítkem.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import ProductForm from '@/Components/ProductForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from '@/lib/debounce';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

/** Pauza v psaní, po které se načtou výsledky hledání (ms). */
const SEARCH_DEBOUNCE_MS = 300;

/** Výchozí řazení — do adresy se nepíše. */
const DEFAULT_SORT = 'name';

/** Číselné sloupce se po přepnutí řadí nejdřív od největšího. */
const NUMERIC_SORTS = ['offers', 'watchers'];

const props = defineProps({
    urls: { type: Object, required: true },
    /** Kategorie [{ id, label }] pro výběr ve formuláři. */
    categories: { type: Array, required: true },
    /** Je v katalogu aspoň jeden produkt (bez ohledu na hledání)? */
    hasProducts: { type: Boolean, required: true },
    /** Oddělení, ve kterých katalog má produkty, abecedně. */
    departments: { type: Array, required: true },
    /** Hledání, oddělení a řazení { q, department, sort, descending }. */
    filters: { type: Object, required: true },
    /** Počet produktů odpovídajících hledání. */
    total: { type: Number, required: true },
    /** Produkty načteného rozsahu stránek s oddělením, kategorií, slovy a počty. */
    products: { type: Array, required: true },
    /** Odkazy stránkování a „Načíst další“ (PaginationLinks, R43). */
    pagination: { type: Object, required: true },
});

const t = useTranslations();

const showForm = ref(false);
const query = ref(props.filters.q);
const department = ref(props.filters.department);

/**
 * Načte tabulku s hledáním, oddělením a řazením od první stránky; výchozí hodnoty do adresy nedává.
 *
 * @param {{ sort?: string, descending?: boolean }} sort
 */
function reload(sort = { sort: props.filters.sort, descending: props.filters.descending }) {
    const descendingByDefault = NUMERIC_SORTS.includes(sort.sort);
    const params = {
        q: query.value.trim(),
        oddeleni: department.value,
        razeni: sort.sort === DEFAULT_SORT ? '' : sort.sort,
        smer: sort.descending === descendingByDefault ? '' : sort.descending ? 'desc' : 'asc',
    };

    router.get(props.urls.index, Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '')), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/** Hledání se načte po pauze v psaní. */
const onSearchInput = debounce(() => reload(), SEARCH_DEBOUNCE_MS);

/**
 * Seřadí podle sloupce; opakované klepnutí obrátí směr.
 *
 * @param {string} key
 */
function sortBy(key) {
    reload(props.filters.sort === key ? { sort: key, descending: !props.filters.descending } : { sort: key, descending: NUMERIC_SORTS.includes(key) });
}

/**
 * Hodnota aria-sort pro hlavičku sloupce.
 *
 * @param {string} key
 * @returns {string}
 */
function ariaSort(key) {
    if (props.filters.sort !== key) {
        return 'none';
    }

    return props.filters.descending ? 'descending' : 'ascending';
}

onBeforeUnmount(() => onSearchInput.cancel());
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

        <EmptyState v-if="!hasProducts" :text="t('catalog.empty')" />

        <template v-else>
            <div class="catalog-toolbar">
                <div class="form-field catalog-toolbar__search">
                    <label for="catalog-search" class="form-field__label">{{ t('catalog.search') }}</label>
                    <input id="catalog-search" v-model="query" type="search" class="form-field__input" :placeholder="t('catalog.search_placeholder')" @input="onSearchInput" />
                </div>
                <div class="form-field">
                    <label for="catalog-department" class="form-field__label">{{ t('catalog.department') }}</label>
                    <select id="catalog-department" v-model="department" class="form-field__input" @change="reload()">
                        <option value="">{{ t('catalog.all_departments') }}</option>
                        <option v-for="name in departments" :key="name" :value="name">{{ name }}</option>
                    </select>
                </div>
                <p class="catalog-toolbar__count" role="status">{{ t('catalog.count', { count: total }) }}</p>
            </div>

            <EmptyState v-if="!products.length" :text="t('catalog.no_results')" />

            <!-- Široká tabulka se na mobilu posouvá vodorovně, stránka zůstává v šířce displeje -->
            <div v-else class="data-table">
                <table class="data-table__table">
                    <thead>
                        <tr>
                            <th v-for="column in ['name', 'category']" :key="column" scope="col" :aria-sort="ariaSort(column)">
                                <button type="button" class="data-table__sort" @click="sortBy(column)">
                                    {{ t(`catalog.columns.${column}`) }}
                                    <span class="data-table__sort-icon" aria-hidden="true">{{ filters.sort === column ? (filters.descending ? '▼' : '▲') : '↕' }}</span>
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
                                    <span class="data-table__sort-icon" aria-hidden="true">{{ filters.sort === column ? (filters.descending ? '▼' : '▲') : '↕' }}</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id">
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

            <Pagination :pagination="pagination" :total="total" load-more-key="catalog.load_more" />
        </template>
    </AppLayout>
</template>
