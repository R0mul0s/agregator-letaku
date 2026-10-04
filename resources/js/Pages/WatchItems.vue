<!--
    Hlídám — jedno pole „Co chcete hlídat?“ (produkt z katalogu, nebo vlastní slova), přehled
    hlídaných položek s tím, co je teď v akci, a katalog k procházení podle oddělení (R18, R31).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import WatchAdd from '@/Components/WatchAdd.vue';
import WatchBrowse from '@/Components/WatchBrowse.vue';
import WatchItemForm from '@/Components/WatchItemForm.vue';
import WatchItemTile from '@/Components/WatchItemTile.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    /** Adresy { store, home, preview }. */
    urls: { type: Object, required: true },
    /** Hlídané položky s počtem akcí, nejnižší cenou a zmínkami (WatchItemController::index). */
    watchItems: { type: Array, required: true },
    /** Produkty katalogu [{ id, name, categoryLabel, categoryName, department, icon, watched, offersCount, lowestPrice }]. */
    products: { type: Array, required: true },
    /** Katalog po odděleních a pododděleních (CatalogBrowseTree, R47). */
    catalogTree: { type: Array, required: true },
    /** Položka, jejíž úprava se má otevřít (odkaz z Mých slev), nebo null. */
    editId: { type: Number, default: null },
    /** Text z karty akce „Hlídat“ (R60) — otevře formulář vlastních slov, nebo null. */
    prefill: { type: String, default: null },
});

const t = useTranslations();
const page = usePage();

/** Formulář vlastních slov: null = zavřený, jinak výchozí hodnoty ({ name, keywords }). */
const ownForm = ref(null);
/** Při každém otevření nový formulář — výchozí hodnoty se berou jen při vytvoření. */
const ownFormKey = ref(0);
const ownFormElement = ref(null);

/** Chyba při přidání produktu (už hlídaný, limit položek). */
const addError = computed(() => (ownForm.value ? null : (page.props.errors?.product_id ?? page.props.errors?.name ?? null)));

/**
 * Začne hlídat produkt z katalogu.
 *
 * @param {object} product
 */
function watchProduct(product) {
    // preserveState: otevřené oddělení katalogu zůstane otevřené
    router.post(props.urls.store, { product_id: product.id, name: product.name }, { preserveScroll: true, preserveState: true });
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

/** ID položek, které už stránka ukázala — nové (právě přidané) se krátce zvýrazní (R71). */
const knownIds = new Set(props.watchItems.map((item) => item.id));
const freshIds = ref(new Set());

watch(
    () => props.watchItems,
    (items) => {
        freshIds.value = new Set(items.filter((item) => !knownIds.has(item.id)).map((item) => item.id));
        items.forEach((item) => knownIds.add(item.id));
    },
);

// „Hlídat“ u akce bez produktu katalogu (R60): formulář s názvem akce, slova jde upravit
onMounted(() => {
    if (props.prefill) {
        openOwnForm(props.prefill);
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="t('watch.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('watch.title') }}</h1>
            <p class="page__subtitle">{{ t('watch.intro') }}</p>
        </header>

        <section class="card watch-new">
            <WatchAdd :products="products" :preview-url="urls.preview" @product="watchProduct" @own="openOwnForm" />
            <p v-if="addError" class="form-field__error" role="alert">{{ addError }}</p>

            <div v-if="ownForm" ref="ownFormElement" class="watch-new__own">
                <h2 class="watch-new__title">{{ t('watch.own_title') }}</h2>
                <p class="form-field__hint watch-hint">{{ t('watch.own_hint') }}</p>
                <WatchItemForm
                    :key="ownFormKey"
                    :url="urls.store"
                    :item="ownForm"
                    :preview-url="urls.preview"
                    :submit-label="t('watch.add')"
                    cancelable
                    @saved="ownForm = null"
                    @cancel="ownForm = null"
                />
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
                    <WatchItemTile
                        v-for="item in watchItems"
                        :key="item.id"
                        :item="item"
                        :home-url="urls.home"
                        :preview-url="urls.preview"
                        :fresh="freshIds.has(item.id)"
                        :initially-editing="item.id === editId"
                    />
                </div>
            </template>
        </section>

        <WatchBrowse :tree="catalogTree" :products="products" @product="watchProduct" />
    </AppLayout>
</template>
