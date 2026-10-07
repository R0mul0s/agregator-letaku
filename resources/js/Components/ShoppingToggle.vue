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
    /** Jen ikona „+“ / „✓“ v kompaktním řádku (R62); popisek má čtečka. */
    compact: { type: Boolean, default: false },
});

const t = useTranslations();
const page = usePage();
const processing = ref(false);

/** Seznam přihlášeného { offerIds, toggleUrl }, nepřihlášený null. */
const list = computed(() => page.props.shoppingList);

/** Stav po klepnutí, než odpoví server — v obchodě na pomalém připojení tlačítko reaguje hned. */
const pending = ref(null);

const inList = computed(() => pending.value ?? list.value?.offerIds.includes(props.offerId) ?? false);

/** Přidá, nebo odebere; stránka zůstane, jak je (rozbalené skupiny, vybraný obchod). */
function toggle() {
    pending.value = !inList.value;
    router.post(
        list.value.toggleUrl,
        { offer_id: props.offerId },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            // Po odpovědi platí stav ze serveru (i při chybě, třeba limitu položek)
            onFinish: () => {
                processing.value = false;
                pending.value = null;
            },
            onError: (errors) => showToast(Object.values(errors)[0]),
        },
    );
}
</script>

<!-- Stav nese u ikony aria-pressed, u plné varianty text tlačítka („V seznamu“) — obojí by čtečka
     hlásila dvakrát (R99) -->
<template>
    <button
        v-if="list"
        type="button"
        class="button button--ghost shopping-toggle"
        :class="{ 'shopping-toggle--added': inList, 'shopping-toggle--compact': compact }"
        :aria-pressed="compact ? (inList ? 'true' : 'false') : undefined"
        :disabled="processing"
        @click="toggle"
    >
        <template v-if="compact">
            <span aria-hidden="true">{{ inList ? '✓' : '+' }}</span>
            <span class="visually-hidden">{{ t('shopping.add_label') }}</span>
        </template>
        <template v-else>{{ inList ? t('shopping.added') : t('shopping.add') }}</template>
    </button>
</template>
