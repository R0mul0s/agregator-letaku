<!--
    Průvodce prvním nastavením (R153), krok 1 Co kupujete: hledání v katalogu s vlastními slovy
    (WatchAdd jako v Hlídám), vybrané položky jako čipy a produkty běžného nákupu po odděleních
    jako dlaždice — klepnutí produkt začne nebo přestane hlídat. Dlaždice ukáže nový stav hned
    (R62), server ho potvrdí; po uložení se načtou jen položky, počty a počítadlo akcí.

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import WatchAdd from '@/Components/WatchAdd.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

/** Po uložení se načte jen tohle (částečné načtení Inertie) — i odložená událost pro GA (R134). */
const RELOAD = ['watchItems', 'products', 'offersCount', 'errors', 'analyticsEvents'];

const props = defineProps({
    /** Celý katalog pro hledání [{ id, name, categoryName, icon, watched, offersCount, lowestPrice }]. */
    products: { type: Array, required: true },
    /** Produkty běžného nákupu po odděleních [{ department, icon, productIds }]. */
    featured: { type: Array, required: true },
    /** Hlídané položky [{ id, name, productId, deleteUrl }]. */
    watchItems: { type: Array, required: true },
    /** Adresy { items, preview }. */
    urls: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

/** Produkty podle ID. */
const productsById = computed(() => new Map(props.products.map((product) => [product.id, product])));

/** Hlídaná položka podle produktu. */
const itemsByProduct = computed(() => new Map(props.watchItems.filter((item) => item.productId).map((item) => [item.productId, item])));

/** Klepnutí, na které server ještě neodpověděl: ID produktu → nový stav (R62). */
const pending = reactive(new Map());

/** Chyba uložení (už hlídaný produkt, limit položek, krátká slova). */
const error = computed(() => page.props.errors?.product_id ?? page.props.errors?.name ?? page.props.errors?.keywords ?? null);

/** Oddělení s produkty, které v katalogu jsou. */
const departments = computed(() =>
    props.featured.map((group, index) => ({
        ...group,
        anchor: `oddeleni-${index}`,
        products: group.productIds.map((id) => productsById.value.get(id)).filter(Boolean),
    })),
);

/** Volby požadavku: stránka zůstane, kde je, a načte jen to, co se změnilo. */
function options(productId = null) {
    return {
        preserveScroll: true,
        preserveState: true,
        only: RELOAD,
        onFinish: () => productId !== null && pending.delete(productId),
    };
}

/**
 * Hlídá uživatel produkt (včetně klepnutí, které ještě běží)?
 *
 * @param {number} productId
 * @returns {boolean}
 */
function isPicked(productId) {
    return pending.has(productId) ? pending.get(productId) : itemsByProduct.value.has(productId);
}

/**
 * Začne, nebo přestane hlídat produkt; během ukládání další klepnutí nic nedělá.
 *
 * @param {{ id: number, name: string }} product
 */
function toggle(product) {
    if (pending.has(product.id)) {
        return;
    }
    const item = itemsByProduct.value.get(product.id);
    pending.set(product.id, !item);
    if (item) {
        router.delete(item.deleteUrl, options(product.id));
    } else {
        router.post(props.urls.items, { product_id: product.id, name: product.name }, options(product.id));
    }
}

/**
 * Produkt z hledání — začne ho hlídat, pokud ještě nehlídá.
 *
 * @param {{ id: number, name: string }} product
 */
function pickProduct(product) {
    if (!isPicked(product.id)) {
        toggle(product);
    }
}

/**
 * Vlastní slova z hledání — napsaný text jako název i hledaná slova (jako v Hlídám).
 *
 * @param {string} text
 */
function pickOwn(text) {
    router.post(props.urls.items, { name: text, keywords: text.toLocaleLowerCase(page.props.locale) }, options());
}

/**
 * Přestane hlídat položku z čipu.
 *
 * @param {{ productId: number|null, deleteUrl: string }} item
 */
function remove(item) {
    if (item.productId) {
        pending.set(item.productId, false);
    }
    router.delete(item.deleteUrl, options(item.productId ?? null));
}

/**
 * Popis akcí produktu na dlaždici: „4 akce · od 32,90 Kč“, bez akce „teď bez akce · hlídáme“.
 *
 * @param {{ offersCount: number, lowestPrice: number|null }} product
 * @returns {{ count: string, price: string|null }|null}
 */
function offersLabel(product) {
    if (!product.offersCount) {
        return null;
    }

    return {
        count: t('watch.offers_count', { count: product.offersCount }),
        price: product.lowestPrice === null ? null : t('watch.lowest_price', { price: formatPrice(product.lowestPrice, page.props.locale) }),
    };
}
</script>

<template>
    <div class="onboarding-products">
        <WatchAdd :products="products" :preview-url="urls.preview" @product="pickProduct" @own="pickOwn" />
        <p v-if="error" class="form-field__error" role="alert">{{ error }}</p>

        <ul v-if="watchItems.length" class="onboarding-products__picked" :aria-label="t('onboarding.picked_label')">
            <li v-for="item in watchItems" :key="item.id" class="onboarding-products__chip" :class="{ 'onboarding-products__chip--own': !item.productId }">
                {{ item.name }}
                <button type="button" class="onboarding-products__chip-remove" :aria-label="t('onboarding.remove', { name: item.name })" @click="remove(item)">
                    <svg class="onboarding-products__chip-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </li>
        </ul>

        <nav class="onboarding-products__departments" :aria-label="t('onboarding.departments_label')">
            <a v-for="department in departments" :key="department.anchor" :href="`#${department.anchor}`" class="onboarding-products__department-link">
                {{ department.department }}
            </a>
        </nav>

        <section v-for="department in departments" :key="department.anchor" class="onboarding-products__group" :aria-labelledby="department.anchor">
            <h2 :id="department.anchor" class="onboarding-products__group-title">
                <span class="onboarding-products__group-icon"><DepartmentIcon :name="department.icon" /></span>
                {{ department.department }}
            </h2>
            <ul class="onboarding-products__tiles">
                <li v-for="product in department.products" :key="product.id">
                    <button type="button" class="pick-tile" :aria-pressed="isPicked(product.id) ? 'true' : 'false'" @click="toggle(product)">
                        <span class="pick-tile__check" aria-hidden="true">
                            <svg v-if="isPicked(product.id)" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10" /></svg>
                        </span>
                        <span class="pick-tile__name">{{ product.name }}</span>
                        <span v-if="offersLabel(product)" class="pick-tile__offers">
                            <span class="pick-tile__nowrap">{{ offersLabel(product).count }}</span>
                            <span v-if="offersLabel(product).price" class="pick-tile__nowrap">{{ offersLabel(product).price }}</span>
                        </span>
                        <span v-else class="pick-tile__offers pick-tile__offers--none">{{ t('onboarding.tile_none') }}</span>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
