<!--
    Moje obchody — sledované obchody s upřesněním a věrnostní karty (R19, R21). Každý obchod
    je karta s přepínačem sledování; nastavení obchodu (typ prodejny, e-shop, karta) jsou řádky
    s popiskem vlevo a ovládáním vpravo. Uložení je v liště, která drží u spodního okraje (R47).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import StoreSelect from '@/Components/StoreSelect.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Všechny obchody; nedostupné (bez zdroje) jen jako připravované. */
    chains: { type: Array, required: true },
    storeFormats: { type: Array, required: true },
    loyaltyPrograms: { type: Array, required: true },
    /** Nejvíc vybraných prodejen u obchodu (R49). */
    maxSelectedStores: { type: Number, required: true },
});

const t = useTranslations();

/** Nastavení obchodů ve formuláři, podle hodnoty obchodu. */
const chainSettings = ref(
    Object.fromEntries(
        props.chains.map((chain) => [
            chain.value,
            {
                followed: chain.followed,
                storeFormat: chain.storeFormat ?? '',
                includeOnlineOnly: chain.includeOnlineOnly,
                storeCodes: [...chain.storeCodes],
            },
        ]),
    ),
);

const form = useForm({
    loyalty_programs: [...props.loyaltyPrograms],
});

/** Stav formuláře jako text — pro porovnání s uloženým. */
const snapshot = () => JSON.stringify([chainSettings.value, [...form.loyalty_programs].sort()]);
const savedSnapshot = snapshot();

/** Změnil uživatel něco od načtení stránky? */
const dirty = computed(() => snapshot() !== savedSnapshot);

const availableChains = computed(() => props.chains.filter((chain) => chain.available));
const followedCount = computed(() => availableChains.value.filter((chain) => chainSettings.value[chain.value].followed).length);

/** Uloží nastavení — sledované obchody z chainSettings, karty z formuláře. */
function submit() {
    form
        .transform((data) => ({
            ...data,
            chains: availableChains.value
                .filter((chain) => chainSettings.value[chain.value].followed)
                .map((chain) => ({
                    chain: chain.value,
                    store_format: chainSettings.value[chain.value].storeFormat || null,
                    include_online_only: chainSettings.value[chain.value].includeOnlineOnly,
                    store_codes: chain.stores.length ? chainSettings.value[chain.value].storeCodes : [],
                })),
        }))
        .put(props.urls.update, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('preferences.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('preferences.title') }}</h1>
            <p class="page__subtitle">{{ t('preferences.intro') }}</p>
        </header>

        <form class="preferences" novalidate @submit.prevent="submit">
            <p class="preferences__count">
                {{ t('preferences.followed_count', { count: followedCount, total: availableChains.length }) }}
            </p>

            <div class="preferences__grid">
                <section
                    v-for="chain in chains"
                    :key="chain.value"
                    class="chain-card"
                    :class="{
                        'chain-card--disabled': !chain.available,
                        'chain-card--off': chain.available && !chainSettings[chain.value].followed,
                    }"
                >
                    <div class="chain-card__header">
                        <ChainLogo :chain="chain.value" large />
                        <div class="chain-card__title">
                            <h2 class="chain-card__name">{{ chain.name }}</h2>
                            <p class="chain-card__state">
                                <template v-if="!chain.available">{{ t('preferences.coming_soon') }}</template>
                                <template v-else-if="chainSettings[chain.value].followed">{{ t('preferences.followed') }}</template>
                                <template v-else>{{ t('preferences.not_followed') }}</template>
                            </p>
                        </div>
                        <label v-if="chain.available" class="form-switch">
                            <input v-model="chainSettings[chain.value].followed" type="checkbox" role="switch" class="form-switch__input" />
                            <span class="visually-hidden">{{ t('preferences.follow', { chain: chain.name }) }}</span>
                        </label>
                    </div>

                    <div v-if="chain.available && chainSettings[chain.value].followed && (chain.hasStoreFormats || chain.hasEshop || chain.loyaltyProgram || chain.stores.length)" class="chain-card__settings">
                        <div v-if="chain.hasStoreFormats" class="chain-card__setting">
                            <label :for="`format-${chain.value}`" class="chain-card__setting-label">{{ t('preferences.store_format') }}</label>
                            <select :id="`format-${chain.value}`" v-model="chainSettings[chain.value].storeFormat" class="form-field__input chain-card__select">
                                <option value="">{{ t('preferences.all_formats') }}</option>
                                <option v-for="format in storeFormats" :key="format.value" :value="format.value">{{ format.name }}</option>
                            </select>
                        </div>

                        <div v-if="chain.stores.length" class="chain-card__setting chain-card__setting--block">
                            <span class="chain-card__setting-label">{{ t('preferences.stores') }}</span>
                            <p class="form-field__hint">{{ t('preferences.stores_hint') }}</p>
                            <StoreSelect v-model="chainSettings[chain.value].storeCodes" :stores="chain.stores" :max="maxSelectedStores" />
                        </div>

                        <label v-if="chain.hasEshop" class="chain-card__setting">
                            <span class="chain-card__setting-label">{{ t('preferences.include_online_only') }}</span>
                            <span class="form-switch">
                                <input v-model="chainSettings[chain.value].includeOnlineOnly" type="checkbox" role="switch" class="form-switch__input" />
                            </span>
                        </label>

                        <label v-if="chain.loyaltyProgram" class="chain-card__setting">
                            <span class="chain-card__setting-label">{{ t('preferences.loyalty', { program: chain.loyaltyProgramName }) }}</span>
                            <span class="form-switch">
                                <input v-model="form.loyalty_programs" type="checkbox" role="switch" :value="chain.loyaltyProgram" class="form-switch__input" />
                            </span>
                        </label>
                    </div>
                </section>
            </div>

            <div class="preferences__actions" :class="{ 'preferences__actions--dirty': dirty }">
                <p v-if="dirty" class="preferences__unsaved" role="status">{{ t('preferences.unsaved') }}</p>
                <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('preferences.save') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
