<!--
    Nákupní seznam (R61) — akce po obchodech, v obchodě se odškrtávají. Odškrtnuté jdou
    v obchodě na konec a po nákupu se smažou jedním tlačítkem; skončená akce zůstává označená.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { confirmDialog } from '@/lib/confirm';
import { formatDate, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { packageLabel } from '@/lib/offer';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    /** Obchody s položkami [{ chain, chainName, items: [{ id, checked, expired, userPrice, offer, updateUrl, deleteUrl }] }]. */
    groups: { type: Array, required: true },
    clearCheckedUrl: { type: String, required: true },
    /** Je co mazat tlačítkem „Smazat odškrtnuté“. */
    hasChecked: { type: Boolean, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Volby požadavku, po kterých stránka zůstane, kde je. */
const KEEP_PAGE = { preserveScroll: true, preserveState: true };

/**
 * Odškrtne položku, nebo odškrtnutí zruší.
 *
 * @param {object} item
 * @param {boolean} checked
 */
function check(item, checked) {
    router.patch(item.updateUrl, { checked }, KEEP_PAGE);
}

/**
 * Smaže položku ze seznamu.
 *
 * @param {object} item
 */
function remove(item) {
    router.delete(item.deleteUrl, KEEP_PAGE);
}

/** Po potvrzení smaže všechny odškrtnuté položky. */
async function clearChecked(url) {
    const confirmed = await confirmDialog({
        title: t('shopping.clear_checked_confirm_title'),
        message: t('shopping.clear_checked_confirm'),
        confirmLabel: t('shopping.clear_checked_confirm_label'),
    });
    if (confirmed) {
        router.delete(url, KEEP_PAGE);
    }
}

/**
 * Cena položky: u akce na více kusů text akce („3 za cenu 2“), jinak cena, kterou uživatel zaplatí.
 *
 * @param {object} item
 * @returns {string}
 */
function priceLabel(item) {
    if (item.offer.offerType === 'multibuy' && item.offer.promotionText) {
        return item.offer.promotionText;
    }

    return formatPrice(item.userPrice ?? item.offer.price, locale.value);
}

/**
 * Kolik položek obchodu zbývá koupit.
 *
 * @param {object} group
 * @returns {number}
 */
function remaining(group) {
    return group.items.filter((item) => !item.checked).length;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('shopping.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('shopping.title') }}</h1>
            <p class="page__subtitle">{{ t('shopping.intro') }}</p>
        </header>

        <EmptyState v-if="!groups.length" :text="t('shopping.empty')" />

        <template v-else>
            <div v-if="hasChecked" class="watch-groups__toolbar">
                <button type="button" class="button button--ghost" @click="clearChecked(clearCheckedUrl)">{{ t('shopping.clear_checked') }}</button>
            </div>

            <section v-for="group in groups" :key="group.chain" class="card shopping-group">
                <h2 class="shopping-group__title">
                    <ChainLogo :chain="group.chain" with-name />
                    <span class="shopping-group__remaining">{{ t('shopping.remaining', { count: remaining(group) }) }}</span>
                </h2>

                <ul class="shopping-list">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        class="shopping-item"
                        :class="{ 'shopping-item--checked': item.checked, 'shopping-item--expired': item.expired }"
                    >
                        <label class="shopping-item__check">
                            <input type="checkbox" class="form-checkbox__input" :checked="item.checked" @change="check(item, $event.target.checked)" />
                            <span class="visually-hidden">{{ t('shopping.check', { name: item.offer.name }) }}</span>
                        </label>
                        <div class="shopping-item__body">
                            <p class="shopping-item__name">{{ item.offer.name }}</p>
                            <p class="shopping-item__meta">
                                <span v-if="packageLabel(item.offer, locale, t)">{{ packageLabel(item.offer, locale, t) }} · </span>
                                <span v-if="item.expired" class="shopping-item__expired">{{ t('shopping.expired') }}</span>
                                <span v-else>{{ t('shopping.valid_to', { date: formatDate(item.offer.validTo, locale) }) }}</span>
                            </p>
                        </div>
                        <span class="shopping-item__price">{{ priceLabel(item) }}</span>
                        <button type="button" class="icon-button icon-button--danger" :title="t('shopping.remove', { name: item.offer.name })" @click="remove(item)">
                            <!-- Koš -->
                            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
                            <span class="visually-hidden">{{ t('shopping.remove', { name: item.offer.name }) }}</span>
                        </button>
                    </li>
                </ul>
            </section>
        </template>
    </AppLayout>
</template>
