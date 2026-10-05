<!--
    Zmínka hlídané položky na stránce letáku bez ceny (R27) — obchod, leták, stránka, náhled a odkaz.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainWatermark from '@/Components/ChainWatermark.vue';
import InfoIcon from '@/Components/InfoIcon.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { startsLabel } from '@/lib/offer';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';

const props = defineProps({
    /** Zmínka z App\Domain\Offers\MentionPresenter. */
    mention: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Leták, který ještě nezačal (R76): „Od zítra“, „Od st 8. 10.“; null = už platí. */
const starts = computed(() => startsLabel(props.mention, locale.value, t));

/** Vysvětlení štítku „Možná“ pod štítky — otevírá se klepnutím (R55). */
const maybeHintOpen = ref(false);
const maybeHintId = useId();
</script>

<template>
    <article class="mention-card">
        <ChainWatermark :chain="mention.chain" />
        <!-- Náhled stránky z CDN obchodu (R22); obsah nese text karty, obrázek je dekorativní -->
        <img v-if="mention.imageUrl" :src="mention.imageUrl" alt="" class="mention-card__image" loading="lazy" referrerpolicy="no-referrer" />
        <div class="mention-card__body">
            <div class="offer-card__badges">
                <ChainLogo :chain="mention.chain" />
                <span v-if="starts" class="tag tag--upcoming">{{ starts }}</span>
                <span v-if="mention.storeFormatName" class="tag">{{ mention.storeFormatName }}</span>
                <!-- Vysvětlení klepnutím — title se na dotykovém displeji neukáže (R55) -->
                <button
                    v-if="mention.matchStatus === 'maybe'"
                    type="button"
                    class="tag tag--warning tag--info"
                    :aria-expanded="maybeHintOpen ? 'true' : 'false'"
                    :aria-controls="maybeHintId"
                    @click="maybeHintOpen = !maybeHintOpen"
                >
                    {{ t('offers.maybe') }}
                    <InfoIcon />
                </button>
            </div>
            <p v-if="mention.matchStatus === 'maybe'" :id="maybeHintId" class="offer-card__hint" :hidden="!maybeHintOpen">{{ t('home.mention_maybe_hint') }}</p>
            <p class="mention-card__title">{{ mention.leafletTitle || t('home.mention_leaflet') }}</p>
            <p v-if="mention.validFrom && mention.validTo" class="mention-card__meta">
                {{ t('offers.valid', { from: formatDate(mention.validFrom, locale), to: formatDate(mention.validTo, locale) }) }}
            </p>
            <a v-if="mention.pageUrl" :href="mention.pageUrl" class="link" target="_blank" rel="noopener noreferrer">
                {{ t('home.mention_page', { page: mention.pageNumber }) }}
            </a>
        </div>
    </article>
</template>
