<!--
    Zmínka hlídané položky na stránce letáku bez ceny (R27) — obchod, leták, stránka, náhled a odkaz.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import ChainWatermark from '@/Components/ChainWatermark.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    /** Zmínka z App\Domain\Offers\MentionPresenter. */
    mention: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);
</script>

<template>
    <article class="mention-card">
        <ChainWatermark :chain="mention.chain" />
        <!-- Náhled stránky z CDN obchodu (R22); obsah nese text karty, obrázek je dekorativní -->
        <img v-if="mention.imageUrl" :src="mention.imageUrl" alt="" class="mention-card__image" loading="lazy" referrerpolicy="no-referrer" />
        <div class="mention-card__body">
            <div class="offer-card__badges">
                <ChainLogo :chain="mention.chain" />
                <span v-if="mention.storeFormatName" class="tag">{{ mention.storeFormatName }}</span>
                <span v-if="mention.matchStatus === 'maybe'" class="tag tag--warning" :title="t('home.mention_maybe_hint')">{{ t('offers.maybe') }}</span>
            </div>
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
