<!--
    Položky nákupního seznamu po obchodech (R61) — zaškrtávátko, název, balení a platnost akce,
    cena a u vlastníka koš. Vlastní položka bez akce (R130) má jen název. Akce, která ještě
    nezačala, se odškrtne až po potvrzení (R76). Sdílí ji stránka vlastníka (ShoppingList.vue)
    i seznam sdílený odkazem (SharedShoppingList.vue); co se po odškrtnutí stane, řeší stránka.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { confirmDialog } from '@/lib/confirm';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { packageLabel } from '@/lib/offer';
import { itemPrice, isPromotionText } from '@/lib/shoppingList';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Klíč skupiny vlastních položek bez obchodu (App\Domain\Shopping\ShoppingListView::ANYWHERE). */
const ANYWHERE = 'anywhere';

defineProps({
    /** Skupiny [{ chain, chainName, items: [{ id, name, checked, expired, userPrice, offer|null, updateUrl, deleteUrl|null }] }]. */
    groups: { type: Array, required: true },
    /** Jde mazat (bez připojení ne). */
    canRemove: { type: Boolean, default: false },
});

const emit = defineEmits(['check', 'remove']);

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/**
 * Odškrtnutí položky; akce, která ještě nezačala (R76), až po potvrzení — za akční cenu
 * ji v obchodě zatím nekoupíte.
 *
 * @param {object} item
 * @param {HTMLInputElement} input Zaškrtávátko — po zrušení potvrzení se vrátí
 */
async function onChange(item, input) {
    const checked = input.checked;
    if (checked && item.offer?.startsInDays) {
        const confirmed = await confirmDialog({
            title: t('shopping.upcoming_confirm_title'),
            message: t('shopping.upcoming_confirm', { name: item.name, date: formatDate(item.offer.validFrom, locale.value) }),
            confirmLabel: t('shopping.upcoming_confirm_label'),
        });
        if (!confirmed) {
            input.checked = false;

            return;
        }
    }

    emit('check', item, checked);
}

/**
 * Kolik položek skupiny zbývá koupit.
 *
 * @param {object} group
 * @returns {number}
 */
function remaining(group) {
    return group.items.filter((item) => !item.checked).length;
}
</script>

<template>
    <section v-for="group in groups" :key="group.chain" class="card shopping-group">
        <h2 class="shopping-group__title">
            <span v-if="group.chain === ANYWHERE" class="shopping-group__anywhere">{{ group.chainName }}</span>
            <ChainLogo v-else :chain="group.chain" with-name />
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
                    <input type="checkbox" class="form-checkbox__input" :checked="item.checked" @change="onChange(item, $event.target)" />
                    <span class="visually-hidden">{{ t('shopping.check', { name: item.name }) }}</span>
                </label>
                <div class="shopping-item__body">
                    <p class="shopping-item__name">{{ item.name }}</p>
                    <p v-if="item.offer" class="shopping-item__meta">
                        <span v-if="packageLabel(item.offer, locale, t)">{{ packageLabel(item.offer, locale, t) }} · </span>
                        <span v-if="item.expired" class="shopping-item__expired">{{ t('shopping.expired') }}</span>
                        <!-- Ještě nezačala (R76) — v obchodě zatím za akční cenu není -->
                        <span v-else-if="item.offer.startsInDays" class="shopping-item__upcoming">{{
                            t('shopping.starts', { date: formatDate(item.offer.validFrom, locale) })
                        }}</span>
                        <span v-else>{{ t('shopping.valid_to', { date: formatDate(item.offer.validTo, locale) }) }}</span>
                    </p>
                    <p v-else class="shopping-item__meta">{{ t('shopping.custom_item') }}</p>
                </div>
                <span v-if="item.offer" class="shopping-item__price" :class="{ 'shopping-item__price--text': isPromotionText(item) }">{{
                    itemPrice(item, locale)
                }}</span>
                <button
                    v-if="item.deleteUrl"
                    type="button"
                    class="icon-button icon-button--danger"
                    :disabled="!canRemove"
                    :title="t('shopping.remove', { name: item.name })"
                    @click="emit('remove', item)"
                >
                    <!-- Koš -->
                    <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
                    <span class="visually-hidden">{{ t('shopping.remove', { name: item.name }) }}</span>
                </button>
            </li>
        </ul>
    </section>
</template>
