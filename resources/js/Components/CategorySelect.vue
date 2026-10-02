<!--
    Výběr kategorie katalogu ze stromu Tesca (R28) — pole pro zúžení podle slov a seznam cest.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { computed, ref } from 'vue';

/** Kolik řádků seznamu je vidět najednou. */
const VISIBLE_ROWS = 8;

const props = defineProps({
    /** Atribut id i name seznamu. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Kategorie [{ id, label }] v pořadí stromu (App\Domain\Catalog\CategoryPaths). */
    options: { type: Array, required: true },
    hint: { type: String, default: undefined },
    error: { type: String, default: undefined },
});

const model = defineModel({ type: Number, default: null });

const t = useTranslations();
const filter = ref('');

/**
 * Text bez diakritiky a velkých písmen pro porovnání.
 *
 * @param {string} text
 * @returns {string}
 */
function normalize(text) {
    return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

/** Kategorie, jejichž cesta obsahuje všechna slova filtru; vybraná zůstává vždy. */
const filtered = computed(() => {
    const words = normalize(filter.value).split(/\s+/).filter(Boolean);
    if (!words.length) {
        return props.options;
    }

    return props.options.filter((option) => option.id === model.value || words.every((word) => normalize(option.label).includes(word)));
});

/** Celá cesta vybrané kategorie. */
const selectedLabel = computed(() => props.options.find((option) => option.id === model.value)?.label ?? null);

const describedBy = computed(() => [props.hint ? `${props.id}-hint` : null, props.error ? `${props.id}-error` : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="form-field">
        <label :for="id" class="form-field__label">{{ label }}</label>
        <input
            v-model="filter"
            type="search"
            class="form-field__input"
            :placeholder="t('catalog.category_filter')"
            :aria-label="t('catalog.category_filter')"
            :aria-controls="id"
        />
        <select
            :id="id"
            v-model="model"
            :name="id"
            :size="VISIBLE_ROWS"
            class="form-field__input category-select__list"
            :class="{ 'form-field__input--invalid': error }"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
        >
            <option :value="null">{{ t('catalog.no_category') }}</option>
            <option v-for="option in filtered" :key="option.id" :value="option.id">{{ option.label }}</option>
        </select>
        <!-- Dlouhá cesta se v úzkém seznamu ořízne — celá je vidět tady -->
        <p v-if="selectedLabel" class="category-select__selected">{{ selectedLabel }}</p>
        <p v-if="hint" :id="`${id}-hint`" class="form-field__hint">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="form-field__error">{{ error }}</p>
    </div>
</template>
