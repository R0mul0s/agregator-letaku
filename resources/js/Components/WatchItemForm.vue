<!--
    Formulář hlídané položky s vlastními slovy (R18) — nová i úprava. Produkt z katalogu
    se přidává klepnutím v seznamu katalogu (R31), formulář nepotřebuje.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import TextField from '@/Components/TextField.vue';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/vue3';
import { useId } from 'vue';

const props = defineProps({
    /** Adresa pro odeslání. */
    url: { type: String, required: true },
    /** post = nová položka, put = úprava. */
    method: { type: String, default: 'post' },
    /** Výchozí hodnoty (úprava). */
    item: { type: Object, default: () => ({}) },
    submitLabel: { type: String, required: true },
});

const emit = defineEmits(['saved', 'cancel']);

const t = useTranslations();
// Více formulářů na stránce — id polí musí být jedinečná
const idPrefix = useId();

const form = useForm({
    name: props.item.name ?? '',
    keywords: props.item.keywords ?? '',
    variant_keywords: props.item.variantKeywords ?? '',
    exclude_keywords: props.item.excludeKeywords ?? '',
});

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
        <TextField :id="`${idPrefix}-name`" v-model="form.name" :label="t('watch.name')" :hint="t('watch.name_hint')" required :error="form.errors.name" />
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
            <button v-if="method !== 'post'" type="button" class="button button--ghost" @click="emit('cancel')">{{ t('watch.cancel') }}</button>
        </div>
    </form>
</template>
