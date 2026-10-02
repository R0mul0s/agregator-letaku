<!--
    Přehled všech aktuálních akcí s hledáním a filtrem obchodu.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import OfferCard from '@/Components/OfferCard.vue';
import Pagination from '@/Components/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    searchUrl: { type: String, required: true },
    /** Laravel paginator s nabídkami z OfferPresenter. */
    offers: { type: Object, required: true },
    filters: { type: Object, required: true },
    chains: { type: Array, required: true },
});

const t = useTranslations();

const filters = reactive({ ...props.filters });

/** Načte výsledky hledání; prázdné parametry do adresy nedává. */
function search() {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));

    router.get(props.searchUrl, query, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('offers.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('offers.title') }}</h1>
            <p class="page__subtitle">{{ t('offers.count', { count: offers.total }) }}</p>
        </header>

        <form class="search-form" role="search" @submit.prevent="search">
            <div class="form-field search-form__text">
                <label for="q" class="form-field__label">{{ t('offers.search') }}</label>
                <input id="q" v-model="filters.q" name="q" type="search" class="form-field__input" :placeholder="t('offers.search_placeholder')" />
            </div>
            <div class="form-field">
                <label for="chain" class="form-field__label">{{ t('offers.chain') }}</label>
                <select id="chain" v-model="filters.chain" name="chain" class="form-field__input" @change="search">
                    <option value="">{{ t('offers.all_chains') }}</option>
                    <option v-for="chain in chains" :key="chain.value" :value="chain.value">{{ chain.name }}</option>
                </select>
            </div>
            <button type="submit" class="button button--primary">{{ t('offers.submit') }}</button>
        </form>

        <p v-if="offers.data.length === 0" class="page__empty">{{ t('offers.empty') }}</p>
        <div v-else class="offer-grid">
            <OfferCard v-for="offer in offers.data" :key="offer.id" :offer="offer" />
        </div>

        <Pagination :paginator="offers" />
    </AppLayout>
</template>
