<!--
    Hlídám — seznam hlídaných položek, přidání produktu z katalogu jedním klepnutím (R31)
    a vlastní hledání slovy pro věci, které v katalogu nejsou (R18).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import WatchItemForm from '@/Components/WatchItemForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { normalizeSearch } from '@/lib/search';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    urls: { type: Object, required: true },
    watchItems: { type: Array, required: true },
    /** Produkty katalogu [{ id, name, categoryLabel, watched }]. */
    products: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Položka, kterou uživatel právě upravuje (id), nebo null. */
const editingId = ref(null);

/** Filtr seznamu katalogu. */
const catalogFilter = ref('');

/** Produkty katalogu, jejichž název nebo kategorie obsahuje všechna slova filtru. */
const filteredProducts = computed(() => {
    const words = normalizeSearch(catalogFilter.value).split(/\s+/).filter(Boolean);

    return props.products.filter((product) => words.every((word) => normalizeSearch(`${product.name} ${product.categoryLabel ?? ''}`).includes(word)));
});

/** Chyba při přidání produktu (už hlídaný, limit položek). */
const catalogError = computed(() => page.props.errors?.product_id ?? page.props.errors?.name ?? null);

/**
 * Začne hlídat produkt z katalogu.
 *
 * @param {object} product
 */
function watchProduct(product) {
    router.post(props.urls.store, { product_id: product.id, name: product.name }, { preserveScroll: true });
}

/**
 * Po potvrzení smaže položku.
 *
 * @param {object} item
 */
function remove(item) {
    if (window.confirm(t('watch.delete_confirm', { name: item.name }))) {
        router.delete(item.deleteUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="t('watch.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('watch.title') }}</h1>
            <p class="page__subtitle">{{ t('watch.intro') }}</p>
        </header>

        <div class="watch-layout">
            <section class="watch-layout__list">
                <p v-if="!watchItems.length" class="page__empty">{{ t('watch.empty') }}</p>
                <article v-for="item in watchItems" :key="item.id" class="card watch-item">
                    <WatchItemForm
                        v-if="editingId === item.id"
                        :url="item.updateUrl"
                        method="put"
                        :item="item"
                        :submit-label="t('watch.save')"
                        @saved="editingId = null"
                        @cancel="editingId = null"
                    />
                    <template v-else>
                        <h2 class="card__title">{{ item.name }}</h2>
                        <dl v-if="item.productId" class="watch-item__rules">
                            <dt class="watch-item__label">{{ t('watch.from_catalog') }}</dt>
                            <dd class="watch-item__value">{{ item.productName }}</dd>
                        </dl>
                        <dl v-else class="watch-item__rules">
                            <dt class="watch-item__label">{{ t('watch.keywords') }}</dt>
                            <dd class="watch-item__value">{{ item.keywords }}</dd>
                            <template v-if="item.variantKeywords">
                                <dt class="watch-item__label">{{ t('watch.variant_keywords') }}</dt>
                                <dd class="watch-item__value">{{ item.variantKeywords }}</dd>
                            </template>
                            <template v-if="item.excludeKeywords">
                                <dt class="watch-item__label">{{ t('watch.exclude_keywords') }}</dt>
                                <dd class="watch-item__value">{{ item.excludeKeywords }}</dd>
                            </template>
                        </dl>
                        <div class="form__actions">
                            <button v-if="!item.productId" type="button" class="button button--ghost" @click="editingId = item.id">{{ t('watch.edit') }}</button>
                            <button type="button" class="button button--ghost" @click="remove(item)">{{ item.productId ? t('watch.stop') : t('watch.delete') }}</button>
                        </div>
                    </template>
                </article>
            </section>

            <div class="watch-layout__new">
                <section class="card watch-catalog">
                    <h2 class="card__title">{{ t('watch.catalog_title') }}</h2>
                    <p class="form-field__hint">{{ t('watch.catalog_hint') }}</p>
                    <input
                        v-model="catalogFilter"
                        type="search"
                        class="form-field__input watch-catalog__filter"
                        :placeholder="t('watch.catalog_filter')"
                        :aria-label="t('watch.catalog_filter')"
                    />
                    <p v-if="catalogError" class="form-field__error" role="alert">{{ catalogError }}</p>
                    <ul class="watch-catalog__list">
                        <li v-for="product in filteredProducts" :key="product.id">
                            <button
                                type="button"
                                class="watch-catalog__product"
                                :class="{ 'watch-catalog__product--watched': product.watched }"
                                :disabled="product.watched"
                                @click="watchProduct(product)"
                            >
                                <span class="watch-catalog__name">{{ product.name }}</span>
                                <span v-if="product.watched" class="tag tag--accent">✓ {{ t('watch.watching') }}</span>
                                <span v-else-if="product.categoryLabel" class="watch-catalog__category">{{ product.categoryLabel }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-if="!filteredProducts.length" class="page__empty">{{ t('watch.catalog_empty') }}</p>
                </section>

                <section class="card">
                    <h2 class="card__title">{{ t('watch.own_title') }}</h2>
                    <p class="form-field__hint watch-hint">{{ t('watch.own_hint') }}</p>
                    <WatchItemForm :url="urls.store" :submit-label="t('watch.add')" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
