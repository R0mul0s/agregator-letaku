<!--
    Položky nákupního seznamu po obchodech (R61) — zaškrtávátko, název, balení a platnost akce,
    cena a u vlastníka koš. Vlastní položka bez akce (R130) má jen název. Množství (R133) je
    štítek „2×“ před názvem; vlastník klepnutím na název otevře −/+ a cena ukáže „2 × 33,90 Kč“
    i cenu za celé množství. Akce, která ještě nezačala, se odškrtne až po potvrzení (R76). Sdílí ji stránka vlastníka (ShoppingList.vue)
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
import { itemPrice, itemTotal, isPromotionText } from '@/lib/shoppingList';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Klíč skupiny vlastních položek bez obchodu (App\Domain\Shopping\ShoppingListView::ANYWHERE). */
const ANYWHERE = 'anywhere';

const props = defineProps({
    /**
     * Skupiny [{ chain, chainName, items: [{ id, name, quantity, checked, expired, userPrice, offer|null,
     * updateUrl, deleteUrl|null, quantityUrl|null }] }].
     */
    groups: { type: Array, required: true },
    /** Jde mazat a měnit množství (bez připojení ne). */
    canEdit: { type: Boolean, default: false },
    /** Nejvyšší množství položky (R133). */
    maxQuantity: { type: Number, default: 1 },
});

const emit = defineEmits(['check', 'remove', 'quantity']);

/** Položka, u které jsou otevřená tlačítka −/+ (R133); null = žádná. */
const editing = ref(null);

/**
 * Napsané množství: omezí ho na 1 až maximum a pošle, jen když se změnilo; nesmysl vrátí
 * na dosavadní hodnotu.
 *
 * @param {object} item
 * @param {HTMLInputElement} input
 */
function setQuantity(item, input) {
    const value = Math.round(Number(input.value));
    if (!Number.isFinite(value) || input.value === '') {
        input.value = item.quantity;

        return;
    }
    const quantity = Math.min(Math.max(value, 1), props.maxQuantity);
    input.value = quantity;
    if (quantity !== item.quantity) {
        emit('quantity', item, quantity);
    }
}

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
                    <!-- Množství (R133): klepnutí na název ukáže −/+ ; bez adresy (sdílený odkaz) jen štítek -->
                    <p class="shopping-item__name">
                        <span v-if="item.quantity > 1" class="shopping-item__quantity">{{ t('shopping.quantity_badge', { count: item.quantity }) }}</span>
                        <button
                            v-if="item.quantityUrl"
                            type="button"
                            class="shopping-item__name-button"
                            :aria-expanded="editing === item.id ? 'true' : 'false'"
                            :aria-controls="`quantity-${item.id}`"
                            @click="editing = editing === item.id ? null : item.id"
                        >
                            {{ item.name }}
                        </button>
                        <template v-else>{{ item.name }}</template>
                    </p>
                    <div v-if="item.quantityUrl && editing === item.id" :id="`quantity-${item.id}`" class="shopping-item__stepper">
                        <button
                            type="button"
                            class="icon-button"
                            :disabled="!canEdit || item.quantity <= 1"
                            :aria-label="t('shopping.quantity_less', { name: item.name })"
                            @click="emit('quantity', item, item.quantity - 1)"
                        >
                            −
                        </button>
                        <!-- Číslo jde i napsat (uklepnutí „22“) — uloží se po potvrzení nebo opuštění pole -->
                        <label class="shopping-item__stepper-value">
                            <span class="visually-hidden">{{ t('shopping.quantity_label', { name: item.name }) }}</span>
                            <input
                                :key="item.quantity"
                                type="number"
                                inputmode="numeric"
                                class="form-field__input shopping-item__stepper-input"
                                min="1"
                                :max="maxQuantity"
                                :value="item.quantity"
                                :disabled="!canEdit"
                                @change="setQuantity(item, $event.target)"
                                @keydown.enter.prevent="$event.target.blur()"
                            />
                            <span aria-hidden="true">{{ t('shopping.quantity_unit') }}</span>
                        </label>
                        <button
                            type="button"
                            class="icon-button"
                            :disabled="!canEdit || item.quantity >= maxQuantity"
                            :aria-label="t('shopping.quantity_more', { name: item.name })"
                            @click="emit('quantity', item, item.quantity + 1)"
                        >
                            +
                        </button>
                    </div>
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
                <span v-if="item.offer" class="shopping-item__price" :class="{ 'shopping-item__price--text': isPromotionText(item) }">
                    {{ itemPrice(item, locale) }}
                    <!-- Za celé množství (R133) -->
                    <span v-if="itemTotal(item, locale)" class="shopping-item__total">{{ itemTotal(item, locale) }}</span>
                </span>
                <button
                    v-if="item.deleteUrl"
                    type="button"
                    class="icon-button icon-button--danger"
                    :disabled="!canEdit"
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
