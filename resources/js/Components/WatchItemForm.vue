<!--
    Formulář hlídané položky — produkt z katalogu (R31), nebo vlastní slova (R18); nová i úprava.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import TextField from '@/Components/TextField.vue';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/vue3';
import { useId, watch } from 'vue';

const props = defineProps({
    /** Adresa pro odeslání. */
    url: { type: String, required: true },
    /** post = nová položka, put = úprava. */
    method: { type: String, default: 'post' },
    /** Výchozí hodnoty (úprava, nebo rychlý výběr produktu). */
    item: { type: Object, default: () => ({}) },
    /** Produkty katalogu [{ id, name, categoryLabel }]. */
    products: { type: Array, required: true },
    submitLabel: { type: String, required: true },
});

const emit = defineEmits(['saved', 'cancel']);

const t = useTranslations();
// Více formulářů na stránce — id polí musí být jedinečná
const idPrefix = useId();

/**
 * Hodnoty formuláře z položky.
 *
 * @param {object} item
 * @returns {object}
 */
function fields(item) {
    return {
        name: item.name ?? '',
        product_id: item.productId ?? null,
        keywords: item.keywords ?? '',
        variant_keywords: item.variantKeywords ?? '',
        exclude_keywords: item.excludeKeywords ?? '',
    };
}

const form = useForm(fields(props.item));

// Rychlý výběr nebo jiná položka předvyplní formulář znovu
watch(
    () => props.item,
    (item) => {
        Object.assign(form, fields(item));
        form.clearErrors();
    },
);

// Prázdný název převezme název vybraného produktu
watch(
    () => form.product_id,
    (productId) => {
        const product = props.products.find((candidate) => candidate.id === productId);
        if (product && !form.name) {
            form.name = product.name;
        }
    },
);

/** Odešle formulář; nová položka se po uložení vyprázdní. */
function submit() {
    form.submit(props.method, props.url, {
        preserveScroll: true,
        onSuccess: () => {
            if (props.method === 'post') {
                form.reset();
            }
            emit('saved');
        },
    });
}
</script>

<template>
    <form class="form" novalidate @submit.prevent="submit">
        <div class="form-field">
            <label :for="`${idPrefix}-product`" class="form-field__label">{{ t('watch.product') }}</label>
            <select
                :id="`${idPrefix}-product`"
                v-model="form.product_id"
                class="form-field__input"
                :class="{ 'form-field__input--invalid': form.errors.product_id }"
                :aria-describedby="`${idPrefix}-product-hint`"
            >
                <option :value="null">{{ t('watch.own_keywords') }}</option>
                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
            </select>
            <p :id="`${idPrefix}-product-hint`" class="form-field__hint">{{ t('watch.product_hint') }}</p>
            <p v-if="form.errors.product_id" class="form-field__error">{{ form.errors.product_id }}</p>
        </div>

        <TextField :id="`${idPrefix}-name`" v-model="form.name" :label="t('watch.name')" :hint="t('watch.name_hint')" required :error="form.errors.name" />

        <template v-if="form.product_id === null">
            <TextField
                :id="`${idPrefix}-keywords`"
                v-model="form.keywords"
                :label="t('watch.keywords')"
                :hint="t('watch.keywords_hint')"
                required
                :error="form.errors.keywords"
            />
            <TextField
                :id="`${idPrefix}-variant`"
                v-model="form.variant_keywords"
                :label="t('watch.variant_keywords')"
                :hint="t('watch.variant_keywords_hint')"
                :error="form.errors.variant_keywords"
            />
            <TextField
                :id="`${idPrefix}-exclude`"
                v-model="form.exclude_keywords"
                :label="t('watch.exclude_keywords')"
                :hint="t('watch.exclude_keywords_hint')"
                :error="form.errors.exclude_keywords"
            />
        </template>

        <div class="form__actions">
            <button type="submit" class="button button--primary" :disabled="form.processing">{{ submitLabel }}</button>
            <button v-if="method !== 'post'" type="button" class="button button--ghost" @click="emit('cancel')">{{ t('watch.cancel') }}</button>
        </div>
    </form>
</template>
