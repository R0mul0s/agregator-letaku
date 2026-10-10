<!--
    Sdílení akce z karty (R130) — „pošli to partnerovi“: sdílení systému (chat, e-mail), bez něj
    zkopírování. Text s názvem, cenou, obchodem a platností a odkaz na akci u obchodu nebo do
    letáku; akce bez vlastního odkazu (Globus) odkáže na akce obchodu ve Slevohlídce.
    Vidí ho všichni, i nepřihlášení.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import { formatDate, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { OFFER_TYPE } from '@/lib/offer';
import { shareOrCopy } from '@/lib/share';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    /** Akce z OfferPresenter. */
    offer: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Cena do textu: u akce na více kusů text akce, jinak cena (bez běžné ceny cena s kartou).
 *
 * @returns {string}
 */
function priceText() {
    if (props.offer.offerType === OFFER_TYPE.MULTIBUY && props.offer.promotionText) {
        return props.offer.promotionText;
    }

    return formatPrice(props.offer.price ?? props.offer.loyaltyPrice, page.props.locale);
}

/**
 * Odkaz akce: u obchodu, jinak akce obchodu ve Slevohlídce (z patičky, siteFooter).
 *
 * @returns {string|undefined}
 */
function shareUrl() {
    if (props.offer.sourceUrl) {
        return props.offer.sourceUrl;
    }
    const chainUrl = page.props.siteFooter?.chains?.find((item) => item.chain === props.offer.chain)?.url;

    return chainUrl ? new URL(chainUrl, window.location.origin).href : undefined;
}

/** Pošle akci sdílením systému, bez něj ji zkopíruje. */
async function share() {
    const text = t('offers.share_text', {
        name: props.offer.name,
        price: priceText(),
        chain: props.offer.chainName,
        date: formatDate(props.offer.validTo, page.props.locale),
    });

    await shareOrCopy({ title: props.offer.name, text, url: shareUrl() }, t('offers.share_copied'), t('offers.share_failed'));
}
</script>

<template>
    <button type="button" class="icon-button" :title="t('offers.share')" @click="share">
        <!-- Sdílení: šipka ze schránky -->
        <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" /></svg>
        <span class="visually-hidden">{{ t('offers.share') }} {{ offer.name }}</span>
    </button>
</template>
