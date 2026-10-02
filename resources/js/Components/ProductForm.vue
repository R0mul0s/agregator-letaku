<!--
    Formulář produktu katalogu — název, kategorie a pravidla párování (R29).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import CategorySelect from '@/Components/CategorySelect.vue';
import TextField from '@/Components/TextField.vue';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/vue3';
import { useId } from 'vue';

const props = defineProps({
    /** Adresa pro odeslání. */
    url: { type: String, required: true },
    /** post = nový produkt, put = úprava. */
    method: { type: String, default: 'post' },
    /** Výchozí hodnoty (úprava). */
    product: { type: Object, default: () => ({}) },
    /** Kategorie [{ id, label }]. */
    categories: { type: Array, required: true },
    submitLabel: { type: String, required: true },
});

const t = useTranslations();
const idPrefix = useId();

const form = useForm({
    name: props.product.name ?? '',
    category_id: props.product.categoryId ?? null,
    keywords: props.product.keywords ?? '',
    variant_keywords: props.product.variantKeywords ?? '',
    exclude_keywords: props.product.excludeKeywords ?? '',
});

/** Odešle formulář; nový produkt se po uložení otevře v detailu (přesměruje server). */
function submit() {
    form.submit(props.method, props.url, { preserveScroll: true });
}
</script>

<template>
    <form class="form" novalidate @submit.prevent="submit">
        <TextField :id="`${idPrefix}-name`" v-model="form.name" :label="t('catalog.name')" :hint="t('catalog.name_hint')" required :error="form.errors.name" />
        <CategorySelect
            :id="`${idPrefix}-category`"
            v-model="form.category_id"
            :label="t('catalog.category')"
            :hint="t('catalog.category_hint')"
            :options="categories"
            :error="form.errors.category_id"
        />
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

        <div class="form__actions">
            <button type="submit" class="button button--primary" :disabled="form.processing">{{ submitLabel }}</button>
        </div>
    </form>
</template>
