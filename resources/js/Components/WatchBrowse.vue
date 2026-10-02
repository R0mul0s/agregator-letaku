<!--
    Procházení katalogu v Hlídám jako v e-shopu (R47): nejdřív dlaždice oddělení s ikonou
    a počtem produktů, po klepnutí oddělení s pododděleními (nadpisy) a produkty pod nimi.
    Klepnutí na produkt ho začne hlídat; hlídaný produkt má fajfku a znovu přidat nejde.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import { useTranslations } from '@/lib/i18n';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    /** Oddělení [{ name, icon, aisles: [{ name, productIds }] }] (CatalogBrowseTree). */
    tree: { type: Array, required: true },
    /** Produkty katalogu [{ id, name, categoryLabel, watched }]. */
    products: { type: Array, required: true },
});

const emit = defineEmits({
    /** Uživatel klepl na produkt, který ještě nehlídá. */
    product: (product) => typeof product === 'object',
});

const t = useTranslations();

/** Otevřené oddělení (název); null = dlaždice všech oddělení. */
const openName = ref(null);
const headingElement = ref(null);
const titleElement = ref(null);

const productsById = computed(() => new Map(props.products.map((product) => [product.id, product])));

/** Oddělení s produkty a počty pro dlaždice. */
const departments = computed(() =>
    props.tree.map((department) => {
        const aisles = department.aisles.map((aisle) => ({
            name: aisle.name,
            products: aisle.productIds.map((id) => productsById.value.get(id)).filter(Boolean),
        }));
        const products = aisles.flatMap((aisle) => aisle.products);

        return { ...department, aisles, count: products.length, watchedCount: products.filter((product) => product.watched).length };
    }),
);

const openDepartment = computed(() => departments.value.find((department) => department.name === openName.value) ?? null);

/**
 * Otevře oddělení (null = zpět na dlaždice) a přesune na něj fokus, ať čtečka i klávesnice
 * pokračují v novém obsahu.
 *
 * @param {string|null} name
 */
async function open(name) {
    openName.value = name;
    await nextTick();
    (name === null ? titleElement : headingElement).value?.focus();
}
</script>

<template>
    <section class="card watch-browse" aria-labelledby="watch-browse-title">
        <header class="watch-browse__header">
            <h2 id="watch-browse-title" ref="titleElement" class="watch-browse__title" tabindex="-1">
                {{ t('watch.browse_title') }}
                <span class="watch-browse__count">{{ t('watch.browse_count', { count: products.length }) }}</span>
            </h2>
            <p class="watch-browse__hint">{{ t('watch.browse_hint') }}</p>
        </header>

        <!-- Dlaždice oddělení -->
        <ul v-if="!openDepartment" class="watch-browse__tiles">
            <li v-for="department in departments" :key="department.name">
                <button type="button" class="department-tile" @click="open(department.name)">
                    <span class="department-tile__icon"><DepartmentIcon :name="department.icon" /></span>
                    <span class="department-tile__name">{{ department.name }}</span>
                    <span class="department-tile__meta">
                        {{ t('watch.browse_count', { count: department.count }) }}
                        <span v-if="department.watchedCount" class="department-tile__watched">· {{ t('watch.watched_count', { count: department.watchedCount }) }}</span>
                    </span>
                </button>
            </li>
        </ul>

        <!-- Otevřené oddělení: pododdělení s produkty -->
        <div v-else class="watch-browse__department">
            <button type="button" class="watch-browse__back" @click="open(null)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                {{ t('watch.browse_back') }}
            </button>
            <h3 ref="headingElement" class="watch-browse__department-title" tabindex="-1">
                <span class="department-tile__icon"><DepartmentIcon :name="openDepartment.icon" /></span>
                {{ openDepartment.name }}
            </h3>

            <div v-for="aisle in openDepartment.aisles" :key="aisle.name" class="watch-browse__aisle">
                <h4 class="watch-browse__aisle-title">
                    {{ aisle.name }}
                    <span class="watch-browse__aisle-count">{{ aisle.products.length }}</span>
                </h4>
                <ul class="watch-browse__products">
                    <li v-for="product in aisle.products" :key="product.id">
                        <button
                            type="button"
                            class="watch-browse__product"
                            :disabled="product.watched"
                            :title="product.categoryLabel ?? undefined"
                            @click="emit('product', product)"
                        >
                            <span v-if="product.watched" aria-hidden="true">✓</span>
                            <span v-else aria-hidden="true">+</span>
                            {{ product.name }}
                            <span v-if="product.watched" class="visually-hidden">({{ t('watch.watching') }})</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
