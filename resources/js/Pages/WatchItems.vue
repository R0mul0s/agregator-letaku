<!--
    Hlídám — seznam hlídaných položek, jejich úpravy a nová položka z katalogu nebo se slovy (R18, R31).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import WatchItemForm from '@/Components/WatchItemForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    urls: { type: Object, required: true },
    watchItems: { type: Array, required: true },
    /** Produkty katalogu [{ id, name, categoryLabel }]. */
    products: { type: Array, required: true },
});

const t = useTranslations();

/** Položka, kterou uživatel právě upravuje (id), nebo null. */
const editingId = ref(null);

/** Předvyplnění formuláře nové položky (rychlý výběr produktu). */
const newItem = ref({});

/**
 * Předvyplní novou položku produktem z katalogu.
 *
 * @param {object} product
 */
function pickProduct(product) {
    newItem.value = { productId: product.id, name: product.name };
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
                        :products="products"
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
                            <button type="button" class="button button--ghost" @click="editingId = item.id">{{ t('watch.edit') }}</button>
                            <button type="button" class="button button--ghost" @click="remove(item)">{{ t('watch.delete') }}</button>
                        </div>
                    </template>
                </article>
            </section>

            <section class="card watch-layout__new">
                <h2 class="card__title">{{ t('watch.add_title') }}</h2>
                <template v-if="products.length">
                    <p class="form-field__label">{{ t('watch.quick_pick') }}</p>
                    <div class="watch-templates">
                        <button
                            v-for="product in products"
                            :key="product.id"
                            type="button"
                            class="tag tag--button"
                            :title="product.categoryLabel"
                            @click="pickProduct(product)"
                        >
                            {{ product.name }}
                        </button>
                    </div>
                </template>
                <WatchItemForm :url="urls.store" :item="newItem" :products="products" :submit-label="t('watch.add')" />
            </section>
        </div>
    </AppLayout>
</template>
