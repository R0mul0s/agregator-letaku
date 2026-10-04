<!--
    Formulář hlídané položky s vlastními slovy (R18) — nová i úprava. Produkt z katalogu
    se přidává z našeptávače nebo katalogu v Hlídám (R31), formulář nepotřebuje.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import TextField from '@/Components/TextField.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { useWatchPreview } from '@/lib/watchPreview';
import { useForm, usePage } from '@inertiajs/vue3';
import { useId } from 'vue';

const props = defineProps({
    /** Adresa pro odeslání. */
    url: { type: String, required: true },
    /** post = nová položka, put = úprava. */
    method: { type: String, default: 'post' },
    /** Výchozí hodnoty (úprava). */
    item: { type: Object, default: () => ({}) },
    submitLabel: { type: String, required: true },
    /** Tlačítko Zrušit i u nové položky (úprava ho má vždy). */
    cancelable: { type: Boolean, default: false },
    /** Adresa náhledu akcí, které by slova našla (R71); null = bez náhledu. */
    previewUrl: { type: String, default: null },
});

const emit = defineEmits(['saved', 'cancel']);

const t = useTranslations();
const page = usePage();
// Více formulářů na stránce — id polí musí být jedinečná
const idPrefix = useId();

const form = useForm({
    name: props.item.name ?? '',
    keywords: props.item.keywords ?? '',
    variant_keywords: props.item.variantKeywords ?? '',
    exclude_keywords: props.item.excludeKeywords ?? '',
});

// Co by slova teď našla (R71) — „rum“ chytá i „Rump steak“, poznat to jde ještě před uložením
const preview = useWatchPreview(props.previewUrl, () => ({
    keywords: form.keywords,
    variant_keywords: form.variant_keywords,
    exclude_keywords: form.exclude_keywords,
}));

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
        <div v-if="previewUrl && (preview.count !== null || preview.loading)" class="watch-preview" aria-live="polite">
            <p class="watch-preview__title">
                <template v-if="preview.loading && preview.count === null">{{ t('watch.preview_loading') }}</template>
                <template v-else-if="preview.count === 0">{{ t('watch.preview_none') }}</template>
                <template v-else>{{ t('watch.preview_count', { count: preview.count }) }}</template>
            </p>
            <ul v-if="preview.examples.length" class="watch-preview__list">
                <li v-for="(example, index) in preview.examples" :key="index" class="watch-preview__item">
                    <ChainLogo :chain="example.chain" />
                    <span class="watch-preview__name">{{ example.name }}</span>
                    <span v-if="example.maybe" class="tag">{{ t('offers.maybe') }}</span>
                    <strong v-if="example.price !== null" class="watch-preview__price">{{ formatPrice(example.price, page.props.locale) }}</strong>
                </li>
            </ul>
            <p v-if="preview.count" class="watch-preview__hint">{{ t('watch.preview_hint') }}</p>
        </div>
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
            <button v-if="cancelable || method !== 'post'" type="button" class="button button--ghost" @click="emit('cancel')">{{ t('watch.cancel') }}</button>
        </div>
    </form>
</template>
