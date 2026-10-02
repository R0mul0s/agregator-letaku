<!--
    Hlídám — jedno pole „Co chcete hlídat?“ (produkt z katalogu, nebo vlastní slova), přehled
    hlídaných položek s tím, co je teď v akci, a katalog k procházení podle oddělení (R18, R31).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import WatchAdd from '@/Components/WatchAdd.vue';
import WatchItemForm from '@/Components/WatchItemForm.vue';
import WatchItemTile from '@/Components/WatchItemTile.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Hlídané položky s počtem akcí, nejnižší cenou a zmínkami (WatchItemController::index). */
    watchItems: { type: Array, required: true },
    /** Produkty katalogu [{ id, name, categoryLabel, department, watched }]. */
    products: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Formulář vlastních slov: null = zavřený, jinak výchozí hodnoty ({ name, keywords }). */
const ownForm = ref(null);
/** Při každém otevření nový formulář — výchozí hodnoty se berou jen při vytvoření. */
const ownFormKey = ref(0);
const ownFormElement = ref(null);

/** Zvolené oddělení v katalogu; '' = všechna. */
const department = ref('');

/** Oddělení katalogu podle abecedy (produkty bez kategorie jsou jen ve „Vše“). */
const departments = computed(() => [...new Set(props.products.map((product) => product.department).filter(Boolean))].sort((a, b) => a.localeCompare(b, page.props.locale)));

const browsedProducts = computed(() => (department.value ? props.products.filter((product) => product.department === department.value) : props.products));

/** Chyba při přidání produktu (už hlídaný, limit položek). */
const addError = computed(() => (ownForm.value ? null : (page.props.errors?.product_id ?? page.props.errors?.name ?? null)));

/**
 * Začne hlídat produkt z katalogu.
 *
 * @param {object} product
 */
function watchProduct(product) {
    router.post(props.urls.store, { product_id: product.id, name: product.name }, { preserveScroll: true });
}

/**
 * Otevře formulář vlastních slov s napsaným textem jako názvem i hledanými slovy.
 *
 * @param {string} text
 */
async function openOwnForm(text) {
    ownForm.value = { name: text, keywords: text.toLocaleLowerCase(page.props.locale) };
    ownFormKey.value++;
    await nextTick();
    ownFormElement.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('watch.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('watch.title') }}</h1>
            <p class="page__subtitle">{{ t('watch.intro') }}</p>
        </header>

        <section class="card watch-new">
            <WatchAdd :products="products" @product="watchProduct" @own="openOwnForm" />
            <p v-if="addError" class="form-field__error" role="alert">{{ addError }}</p>

            <div v-if="ownForm" ref="ownFormElement" class="watch-new__own">
                <h2 class="watch-new__title">{{ t('watch.own_title') }}</h2>
                <p class="form-field__hint watch-hint">{{ t('watch.own_hint') }}</p>
                <WatchItemForm :key="ownFormKey" :url="urls.store" :item="ownForm" :submit-label="t('watch.add')" cancelable @saved="ownForm = null" @cancel="ownForm = null" />
            </div>
        </section>

        <section class="watch-list" :aria-labelledby="watchItems.length ? 'watch-list-title' : undefined">
            <EmptyState v-if="!watchItems.length" :text="t('watch.empty')" />
            <template v-else>
                <h2 id="watch-list-title" class="watch-list__title">
                    {{ t('watch.list_title') }}
                    <span class="watch-group__count">{{ watchItems.length }}</span>
                </h2>
                <div class="watch-list__grid">
                    <WatchItemTile v-for="item in watchItems" :key="item.id" :item="item" :home-url="urls.home" />
                </div>
            </template>
        </section>

        <details class="card watch-browse">
            <summary class="watch-browse__summary">
                {{ t('watch.browse_title') }}
                <span class="watch-browse__count">{{ t('watch.browse_count', { count: products.length }) }}</span>
            </summary>

            <div class="watch-browse__departments" role="group" :aria-label="t('catalog.department')">
                <button type="button" class="chip" :aria-pressed="department === ''" @click="department = ''">{{ t('watch.all_departments') }}</button>
                <button v-for="name in departments" :key="name" type="button" class="chip" :aria-pressed="department === name" @click="department = name">
                    {{ name }}
                </button>
            </div>

            <ul class="watch-browse__products">
                <li v-for="product in browsedProducts" :key="product.id">
                    <button
                        type="button"
                        class="watch-browse__product"
                        :disabled="product.watched"
                        :title="product.categoryLabel ?? undefined"
                        @click="watchProduct(product)"
                    >
                        <span v-if="product.watched" aria-hidden="true">✓</span>
                        <span v-else aria-hidden="true">+</span>
                        {{ product.name }}
                        <span v-if="product.watched" class="visually-hidden">({{ t('watch.watching') }})</span>
                    </button>
                </li>
            </ul>
        </details>
    </AppLayout>
</template>
