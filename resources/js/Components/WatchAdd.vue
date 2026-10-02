<!--
    Pole „Co chcete hlídat?“ v Hlídám — našeptává produkty katalogu (R31) a poslední volbou
    nabídne hlídání vlastními slovy (R18). Combobox podle WAI-ARIA: šipky vybírají,
    Enter potvrdí, Escape zavře. Katalog má stránka celý, návrhy se filtrují v prohlížeči.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { normalizeSearch } from '@/lib/search';
import { computed, ref, useId } from 'vue';

/** Kolik produktů katalogu našeptávač ukáže najednou. */
const MAX_SUGGESTIONS = 8;

const props = defineProps({
    /** Produkty katalogu [{ id, name, categoryLabel, watched }]. */
    products: { type: Array, required: true },
});

const emit = defineEmits(['product', 'own']);

const t = useTranslations();
const id = useId();
const listId = `${id}-suggestions`;

const text = ref('');
const open = ref(false);
const activeIndex = ref(-1);

/** Produkty, jejichž název nebo kategorie obsahuje všechna slova; nejdřív ty, jejichž název slovem začíná. */
const matches = computed(() => {
    const query = normalizeSearch(text.value.trim());
    const words = query.split(/\s+/).filter(Boolean);
    if (!words.length) {
        return [];
    }

    return props.products
        .filter((product) => words.every((word) => normalizeSearch(`${product.name} ${product.categoryLabel ?? ''}`).includes(word)))
        .sort((a, b) => Number(!normalizeSearch(a.name).startsWith(query)) - Number(!normalizeSearch(b.name).startsWith(query)))
        .slice(0, MAX_SUGGESTIONS);
});

/** Volby seznamu: produkty, pak vždy hlídání vlastními slovy. */
const options = computed(() => [
    ...matches.value.map((product) => ({ type: 'product', product })),
    ...(text.value.trim() ? [{ type: 'own' }] : []),
]);

const activeId = computed(() => (activeIndex.value >= 0 ? `${listId}-${activeIndex.value}` : undefined));

/** Po změně textu otevře seznam od začátku. */
function onInput() {
    activeIndex.value = -1;
    open.value = options.value.length > 0;
}

/** Zavře seznam. */
function close() {
    open.value = false;
    activeIndex.value = -1;
}

/**
 * Potvrdí volbu: produkt začne hlídat, vlastní slova otevřou formulář s napsaným textem.
 *
 * @param {{ type: string, product?: object }} option
 */
function choose(option) {
    if (option.type === 'product') {
        if (option.product.watched) {
            return;
        }
        emit('product', option.product);
    } else {
        emit('own', text.value.trim());
    }
    text.value = '';
    close();
}

/**
 * Ovládání klávesnicí. Enter bez vybrané volby vezme první produkt, jinak vlastní slova.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = options.value.length > 0;
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + step + options.value.length) % options.value.length;
    } else if (event.key === 'Enter' && options.value.length) {
        event.preventDefault();
        const firstAvailable = options.value.find((option) => option.type === 'own' || !option.product.watched);
        choose(activeIndex.value >= 0 ? options.value[activeIndex.value] : firstAvailable);
    } else if (event.key === 'Escape') {
        close();
    }
}
</script>

<template>
    <div class="watch-add">
        <label :for="id" class="watch-add__label">{{ t('watch.add_label') }}</label>
        <div class="search-suggest">
            <input
                :id="id"
                v-model="text"
                type="search"
                class="form-field__input watch-add__input"
                :placeholder="t('watch.add_placeholder')"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="listId"
                :aria-activedescendant="activeId"
                :aria-describedby="`${id}-hint`"
                @input="onInput"
                @focus="onInput"
                @keydown="onKeydown"
                @blur="close"
            />
            <ul v-show="open" :id="listId" class="search-suggest__list" role="listbox" :aria-label="t('watch.add_label')">
                <!-- mousedown místo click — click by přišel až po blur pole, který seznam zavře -->
                <li
                    v-for="(option, index) in options"
                    :id="`${listId}-${index}`"
                    :key="option.type === 'product' ? option.product.id : 'own'"
                    class="search-suggest__option"
                    :class="{
                        'search-suggest__option--active': index === activeIndex,
                        'search-suggest__option--disabled': option.type === 'product' && option.product.watched,
                        'watch-add__own': option.type === 'own',
                    }"
                    role="option"
                    :aria-selected="index === activeIndex ? 'true' : 'false'"
                    :aria-disabled="option.type === 'product' && option.product.watched ? 'true' : undefined"
                    @mousedown.prevent="choose(option)"
                >
                    <template v-if="option.type === 'product'">
                        <span>{{ option.product.name }}</span>
                        <span v-if="option.product.watched" class="tag tag--accent">✓ {{ t('watch.watching') }}</span>
                        <span v-else-if="option.product.categoryLabel" class="watch-add__category">{{ option.product.categoryLabel }}</span>
                    </template>
                    <span v-else>+ {{ t('watch.own_option', { text: text.trim() }) }}</span>
                </li>
            </ul>
        </div>
        <p :id="`${id}-hint`" class="form-field__hint">
            {{ t('watch.add_hint') }}
            <button type="button" class="link-button" @click="emit('own', text.trim())">{{ t('watch.own_link') }}</button>
        </p>
    </div>
</template>
