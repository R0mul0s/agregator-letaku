<!--
    Tři tečky u akce (R125) — otevřou okno s hlášením chyby, v Mých slevách i se „Tohle ne“
    (OfferActionsSheet). Nepřihlášený je nevidí.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { showOfferActions } from '@/lib/offerActions';
import { usePage } from '@inertiajs/vue3';

defineProps({
    /** Akce z OfferPresenter. */
    offer: { type: Object, required: true },
    /** Hlídaná položka z HomeController, u které jde akci skrýt; null = jen hlášení chyby. */
    watchItem: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();
</script>

<template>
    <button v-if="page.props.offerReports" type="button" class="icon-button offer-menu-button" :title="t('offer_actions.menu')" @click="showOfferActions(offer, watchItem)">
        <!-- Tři tečky -->
        <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h.01M12 12h.01M19 12h.01" /></svg>
        <span class="visually-hidden">{{ t('offer_actions.menu') }} {{ offer.name }}</span>
    </button>
</template>
