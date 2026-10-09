<!--
    Detail produktu katalogu (R29, R30) — pravidla, přiřazené a vyřazené akce a ruční přiřazení.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import OfferCard from '@/Components/OfferCard.vue';
import ProductForm from '@/Components/ProductForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { confirmDialog } from '@/lib/confirm';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, required: true },
    /** Přiřazené neskončené akce s matchStatus, isManual, excludeUrl a hiddenByUsers (R125). */
    assigned: { type: Array, required: true },
    /** Slova, která u produktu vylučují uživatelé [{ word, users }] (R125). */
    excludedWords: { type: Array, required: true },
    /** Ručně vyřazené neskončené akce s restoreUrl. */
    excluded: { type: Array, required: true },
    /** Hledání akcí k ručnímu přiřazení: parameter, query, results (s includeUrl). */
    search: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const t = useTranslations();
const query = ref(props.search.query);

/** Najde akce k ručnímu přiřazení. */
function findOffers() {
    router.get(props.urls.show, query.value ? { [props.search.parameter]: query.value } : {}, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * Pošle ruční opravu přiřazení a zůstane na místě stránky.
 *
 * @param {'post'|'delete'} method
 * @param {string} url
 */
function correct(method, url) {
    router.visit(url, { method, preserveScroll: true, preserveState: true });
}

/** Po potvrzení smaže produkt. */
async function remove() {
    const confirmed = await confirmDialog({
        title: t('catalog.delete_confirm_title'),
        message: t('catalog.delete_confirm', { name: props.product.name }),
        confirmLabel: t('catalog.delete'),
    });
    if (confirmed) {
        router.delete(props.product.deleteUrl);
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="product.name" />

        <Link :href="urls.index" class="link catalog-back">{{ t('catalog.back') }}</Link>
        <header class="page__header">
            <h1 class="page__title">{{ product.name }}</h1>
            <p v-if="product.categoryLabel" class="page__subtitle">{{ product.categoryLabel }}</p>
        </header>

        <div class="catalog-detail">
            <section class="catalog-detail__offers">
                <h2 class="watch-group__title">
                    {{ t('catalog.assigned') }}
                    <span class="watch-group__count">{{ t('home.count', { count: assigned.length }) }}</span>
                </h2>
                <p v-if="!assigned.length" class="page__empty">{{ t('catalog.assigned_empty') }}</p>
                <div v-else class="offer-grid">
                    <OfferCard v-for="offer in assigned" :key="offer.id" :offer="offer" :heading-level="3" :with-menu="false">
                        <span v-if="offer.isManual" class="tag">{{ t('catalog.manual') }}</span>
                        <!-- Uživatelé akci u produktu skryli („Tohle ne“, R125) — nejspíš sem nepatří -->
                        <span v-if="offer.hiddenByUsers" class="tag tag--warning">{{ t('catalog.hidden_by_users', { count: offer.hiddenByUsers }) }}</span>
                        <button type="button" class="button button--ghost" @click="correct('delete', offer.excludeUrl)">{{ t('catalog.exclude') }}</button>
                    </OfferCard>
                </div>

                <template v-if="excluded.length">
                    <h2 class="watch-group__title catalog-section">{{ t('catalog.excluded') }}</h2>
                    <div class="offer-grid">
                        <OfferCard v-for="offer in excluded" :key="offer.id" :offer="offer" :heading-level="3" :with-menu="false">
                            <button type="button" class="button button--ghost" @click="correct('delete', offer.restoreUrl)">{{ t('catalog.restore') }}</button>
                        </OfferCard>
                    </div>
                </template>

                <h2 class="watch-group__title catalog-section">{{ t('catalog.add_offer') }}</h2>
                <form class="search-form" role="search" @submit.prevent="findOffers">
                    <div class="form-field search-form__text">
                        <label for="offer-search" class="form-field__label">{{ t('offers.search') }}</label>
                        <input id="offer-search" v-model="query" type="search" class="form-field__input" :placeholder="t('offers.search_placeholder')" />
                    </div>
                    <button type="submit" class="button button--primary">{{ t('offers.submit') }}</button>
                </form>
                <p v-if="search.query && !search.results.length" class="page__empty">{{ t('offers.empty') }}</p>
                <div v-if="search.results.length" class="offer-grid">
                    <OfferCard v-for="offer in search.results" :key="offer.id" :offer="offer" :heading-level="3" :with-menu="false">
                        <button type="button" class="button button--ghost" @click="correct('post', offer.includeUrl)">{{ t('catalog.include') }}</button>
                    </OfferCard>
                </div>
            </section>

            <section class="card catalog-detail__rules">
                <h2 class="card__title">{{ t('catalog.edit_title') }}</h2>
                <p class="form-field__hint">{{ t('catalog.edit_hint') }}</p>
                <ProductForm :url="product.updateUrl" method="put" :product="product" :categories="categories" :submit-label="t('catalog.save')" />
                <!-- Co u produktu vylučují uživatelé (R125) — kandidáti na vyloučená slova produktu -->
                <template v-if="excludedWords.length">
                    <h3 class="catalog-detail__subtitle">{{ t('catalog.user_words') }}</h3>
                    <p class="form-field__hint">{{ t('catalog.user_words_hint') }}</p>
                    <ul class="catalog-detail__words">
                        <li v-for="entry in excludedWords" :key="entry.word" class="tag">{{ t('catalog.user_word', { word: entry.word, count: entry.users }) }}</li>
                    </ul>
                </template>
                <div class="form__actions">
                    <button type="button" class="button button--ghost" @click="remove">{{ t('catalog.delete') }}</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
