<!--
    Akce hlídané položky v Mých slevách — upravit vlastní slova a přestat hlídat (s potvrzením).
    Sdílí je hlavička skupiny (WatchGroup) a řádek v sekci Zatím bez akce (WaitingSection, R100).
    Co uživatel u položky skryl („Tohle ne“, R125), ukáže okno s vrácením — i u položky, které
    po skrytí žádná akce nezbyla.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import BottomSheet from '@/Components/BottomSheet.vue';
import ChainLogo from '@/Components/ChainLogo.vue';
import { confirmDialog } from '@/lib/confirm';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Položka z HomeController ({ name, fromCatalog, editUrl, deleteUrl, hidden: { offers, words } }). */
    item: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

/** Okno se skrytými akcemi a slovy. */
const hiddenOpen = ref(false);

/** Počet skrytých akcí a vyloučených slov; 0 = tlačítko se neukáže. */
const hiddenCount = computed(() => (props.item.hidden ? props.item.hidden.offers.length + props.item.hidden.words.length : 0));

/** Po potvrzení položku přestane hlídat; stránka zůstane na Mých slevách. */
async function remove() {
    const confirmed = await confirmDialog({
        title: t('watch.delete_confirm_title'),
        message: t('watch.delete_confirm', { name: props.item.name }),
        confirmLabel: t('watch.stop'),
    });
    if (confirmed) {
        router.delete(props.item.deleteUrl, { preserveScroll: true });
    }
}

/**
 * Vrátí skrytou akci nebo vyloučené slovo; poslední vrácení okno zavře.
 *
 * @param {string} url
 */
function restore(url) {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (hiddenCount.value === 0) {
                hiddenOpen.value = false;
            }
        },
    });
}
</script>

<template>
    <div class="watch-group__actions">
        <button v-if="hiddenCount" type="button" class="icon-button watch-hidden__toggle" :title="t('offer_actions.hidden_title')" @click="hiddenOpen = true">
            <!-- Přeškrtnuté oko -->
            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            </svg>
            <span class="watch-hidden__count" aria-hidden="true">{{ hiddenCount }}</span>
            <span class="visually-hidden">{{ t('offer_actions.hidden_label', { name: item.name, count: hiddenCount }) }}</span>
        </button>
        <Link v-if="!item.fromCatalog" :href="item.editUrl" class="icon-button" :title="t('watch.edit')">
            <!-- Tužka -->
            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4" /></svg>
            <span class="visually-hidden">{{ t('watch.edit') }} {{ item.name }}</span>
        </Link>
        <button type="button" class="icon-button icon-button--danger" :title="t('watch.stop')" @click="remove">
            <!-- Koš -->
            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
            <span class="visually-hidden">{{ t('watch.stop') }} {{ item.name }}</span>
        </button>

        <BottomSheet v-if="hiddenCount" v-model:open="hiddenOpen" :title="t('offer_actions.hidden_sheet_title', { name: item.name })">
            <p class="form-field__hint">{{ t('offer_actions.hidden_hint') }}</p>
            <template v-if="item.hidden.offers.length">
                <h3 class="offer-actions__heading">{{ t('offer_actions.hidden_offers') }}</h3>
                <ul class="watch-hidden__list">
                    <li v-for="offer in item.hidden.offers" :key="offer.id" class="watch-hidden__row">
                        <ChainLogo :chain="offer.chain" />
                        <span class="watch-hidden__name">{{ offer.name }}</span>
                        <span v-if="offer.price !== null" class="watch-hidden__price">{{ formatPrice(offer.price, page.props.locale) }}</span>
                        <button type="button" class="button button--ghost" @click="restore(offer.restoreUrl)">
                            {{ t('offer_actions.restore') }}<span class="visually-hidden"> {{ offer.name }}</span>
                        </button>
                    </li>
                </ul>
            </template>
            <template v-if="item.hidden.words.length">
                <h3 class="offer-actions__heading">{{ t('offer_actions.hidden_words') }}</h3>
                <ul class="watch-hidden__list">
                    <li v-for="entry in item.hidden.words" :key="entry.word" class="watch-hidden__row">
                        <span class="watch-hidden__name">{{ entry.word }}</span>
                        <button type="button" class="button button--ghost" @click="restore(entry.restoreUrl)">
                            {{ t('offer_actions.restore') }}<span class="visually-hidden"> {{ entry.word }}</span>
                        </button>
                    </li>
                </ul>
            </template>
        </BottomSheet>
    </div>
</template>
