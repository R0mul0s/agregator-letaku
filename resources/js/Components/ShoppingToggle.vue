<!--
    Tlačítko „Do seznamu“ na kartě akce (R61) — přidá akci do nákupního seznamu, nebo ji odebere.
    Stav bere ze sdílené vlastnosti `shoppingList` (HandleInertiaRequests); nepřihlášený ho nevidí.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { showToast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** ID akce (OfferPresenter id). */
    offerId: { type: Number, required: true },
});

const t = useTranslations();
const page = usePage();
const processing = ref(false);

/** Seznam přihlášeného { offerIds, toggleUrl }, nepřihlášený null. */
const list = computed(() => page.props.shoppingList);

const inList = computed(() => list.value?.offerIds.includes(props.offerId) ?? false);

/** Přidá, nebo odebere; stránka zůstane, jak je (rozbalené skupiny, vybraný obchod). */
function toggle() {
    router.post(
        list.value.toggleUrl,
        { offer_id: props.offerId },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (errors) => showToast(Object.values(errors)[0]),
        },
    );
}
</script>

<template>
    <button
        v-if="list"
        type="button"
        class="button button--ghost shopping-toggle"
        :class="{ 'shopping-toggle--added': inList }"
        :aria-pressed="inList ? 'true' : 'false'"
        :disabled="processing"
        @click="toggle"
    >
        {{ inList ? t('shopping.added') : t('shopping.add') }}
    </button>
</template>
