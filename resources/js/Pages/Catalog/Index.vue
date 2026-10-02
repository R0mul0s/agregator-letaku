<!--
    Katalog produktů pro admina (R29) — seznam produktů s počtem přiřazených akcí a nový produkt.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ProductForm from '@/Components/ProductForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    urls: { type: Object, required: true },
    /** Kategorie [{ id, label }] pro výběr. */
    categories: { type: Array, required: true },
    /** Produkty s počtem přiřazených neskončených akcí (matchCount, maybeCount). */
    products: { type: Array, required: true },
});

const t = useTranslations();
</script>

<template>
    <AppLayout>
        <Head :title="t('catalog.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('catalog.title') }}</h1>
            <p class="page__subtitle">{{ t('catalog.intro') }}</p>
        </header>

        <div class="watch-layout">
            <section class="watch-layout__list">
                <p v-if="!products.length" class="page__empty">{{ t('catalog.empty') }}</p>
                <article v-for="product in products" :key="product.id" class="card catalog-product">
                    <h2 class="card__title">
                        <Link :href="product.showUrl" class="link">{{ product.name }}</Link>
                    </h2>
                    <p v-if="product.categoryLabel" class="catalog-product__category">{{ product.categoryLabel }}</p>
                    <p class="catalog-product__counts">
                        {{ t('catalog.match_count', { count: product.matchCount }) }}
                        <span v-if="product.maybeCount" class="tag tag--warning">{{ t('catalog.maybe_count', { count: product.maybeCount }) }}</span>
                    </p>
                </article>
            </section>

            <section class="card watch-layout__new">
                <h2 class="card__title">{{ t('catalog.add_title') }}</h2>
                <ProductForm :url="urls.store" :categories="categories" :submit-label="t('catalog.add')" />
            </section>
        </div>
    </AppLayout>
</template>
