<!--
    Moje obchody — sledované obchody s upřesněním, prodejny a věrnostní karty (R19).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Kód stavu, kterým server potvrzuje uložení. */
const STATUS_SAVED = 'preferences-saved';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Všechny obchody; nedostupné (bez zdroje) jen jako připravované. */
    chains: { type: Array, required: true },
    storeFormats: { type: Array, required: true },
    /** Prodejny obchodů se seznamem prodejen. */
    stores: { type: Array, required: true },
    selectedStoreIds: { type: Array, required: true },
    loyaltyPrograms: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Nastavení obchodů ve formuláři, podle hodnoty obchodu. */
const chainSettings = ref(
    Object.fromEntries(
        props.chains.map((chain) => [
            chain.value,
            { followed: chain.followed, storeFormat: chain.storeFormat ?? '', includeOnlineOnly: chain.includeOnlineOnly },
        ]),
    ),
);

const form = useForm({
    store_ids: [...props.selectedStoreIds],
    loyalty_programs: [...props.loyaltyPrograms],
});

/** Text pro filtr prodejen podle obchodu. */
const storeFilters = ref({});

/**
 * Prodejny obchodu odpovídající filtru (město, název, adresa).
 *
 * @param {string} chain
 * @returns {object[]}
 */
function filteredStores(chain) {
    const filter = (storeFilters.value[chain] ?? '').trim().toLocaleLowerCase();

    return props.stores.filter(
        (store) => store.chain === chain && (filter === '' || [store.city, store.name, store.address].join(' ').toLocaleLowerCase().includes(filter)),
    );
}

/** Počet vybraných prodejen podle obchodu. */
const selectedCounts = computed(() =>
    Object.fromEntries(props.chains.map((chain) => [chain.value, props.stores.filter((store) => store.chain === chain.value && form.store_ids.includes(store.id)).length])),
);

/** Uloží nastavení — sledované obchody z chainSettings, prodejny a karty z formuláře. */
function submit() {
    form
        .transform((data) => ({
            ...data,
            chains: props.chains
                .filter((chain) => chain.available && chainSettings.value[chain.value].followed)
                .map((chain) => ({
                    chain: chain.value,
                    store_format: chainSettings.value[chain.value].storeFormat || null,
                    include_online_only: chainSettings.value[chain.value].includeOnlineOnly,
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

        <p v-if="page.props.status === STATUS_SAVED" class="notice notice--success" role="status">{{ t('preferences.saved') }}</p>

        <form class="preferences" novalidate @submit.prevent="submit">
            <section v-for="chain in chains" :key="chain.value" class="card chain-settings" :class="{ 'chain-settings--disabled': !chain.available }">
                <div class="chain-settings__header">
                    <span class="chain-badge" :class="`chain-badge--${chain.value}`">{{ chain.name }}</span>
                    <label v-if="chain.available" class="form-checkbox">
                        <input v-model="chainSettings[chain.value].followed" type="checkbox" class="form-checkbox__input" />
                        <span>{{ t('preferences.follow') }}</span>
                    </label>
                    <span v-else class="tag">{{ t('preferences.coming_soon') }}</span>
                </div>

                <div v-if="chain.available && chainSettings[chain.value].followed" class="form chain-settings__body">
                    <div v-if="chain.hasStoreFormats" class="form-field">
                        <label :for="`format-${chain.value}`" class="form-field__label">{{ t('preferences.store_format') }}</label>
                        <select :id="`format-${chain.value}`" v-model="chainSettings[chain.value].storeFormat" class="form-field__input">
                            <option value="">{{ t('preferences.all_formats') }}</option>
                            <option v-for="format in storeFormats" :key="format.value" :value="format.value">{{ format.name }}</option>
                        </select>
                    </div>

                    <label v-if="chain.hasEshop" class="form-checkbox">
                        <input v-model="chainSettings[chain.value].includeOnlineOnly" type="checkbox" class="form-checkbox__input" />
                        <span>{{ t('preferences.include_online_only') }}</span>
                    </label>

                    <label v-if="chain.loyaltyProgram" class="form-checkbox">
                        <input v-model="form.loyalty_programs" type="checkbox" :value="chain.loyaltyProgram" class="form-checkbox__input" />
                        <span>{{ t('preferences.loyalty', { program: chain.loyaltyProgramName }) }}</span>
                    </label>

                    <fieldset v-if="chain.hasStores" class="store-picker">
                        <legend class="form-field__label">
                            {{ t('preferences.stores') }}
                            <span class="store-picker__count">{{ t('preferences.stores_selected', { count: selectedCounts[chain.value] }) }}</span>
                        </legend>
                        <p class="form-field__hint">{{ t('preferences.stores_hint') }}</p>
                        <input
                            v-model="storeFilters[chain.value]"
                            type="search"
                            class="form-field__input"
                            :placeholder="t('preferences.stores_filter')"
                            :aria-label="t('preferences.stores_filter')"
                        />
                        <div class="store-picker__list">
                            <label v-for="store in filteredStores(chain.value)" :key="store.id" class="form-checkbox">
                                <input v-model="form.store_ids" type="checkbox" :value="store.id" class="form-checkbox__input" />
                                <span>{{ store.name }}<span class="store-picker__address">, {{ store.address }}</span></span>
                            </label>
                        </div>
                    </fieldset>
                </div>
            </section>

            <div class="form__actions">
                <button type="submit" class="button button--primary" :disabled="form.processing">{{ t('preferences.save') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
