<!--
    Maskot vykukující z okraje oddílu výhod na úvodní stránce (R137). Po klepnutí prosviští
    s čárkami rychlosti přes celou šířku stránky, vrátí se z druhé strany a v bublině ukáže
    další akci z ukázky nejvyšších slev. Kdo má omezený pohyb, uvidí jen novou akci v bublině.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { MEDIA_FROM_MD } from '@/lib/breakpoints';
import { formatDiscount, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { prefersReducedMotion } from '@/lib/scroll';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Název animace jízdy v pages/_landing.scss — její konec vrátí maskota do klidu. */
const HUNT_ANIMATION = 'landing-hunt';

/** Šířka maskota podle displeje (--landing-peek-size a -mobile) pro výběr obrázku. */
const MASCOT_SIZES = `${MEDIA_FROM_MD} 136px, 96px`;

const props = defineProps({
    /** Akce s nejvyšší slevou (OfferPresenter), které maskot postupně „loví“. */
    offers: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

const hunting = ref(false);
/** Kolikátou akci maskot ukazuje; -1 = ještě nelovil, v bublině je výzva. */
const caught = ref(-1);

const bubble = computed(() => {
    const offer = props.offers[caught.value];
    if (!offer) {
        return t('landing.hunt.hint');
    }
    const percent = discountPercent(offer);
    const replacements = { name: offer.name, price: formatPrice(offer.price ?? offer.loyaltyPrice, page.props.locale), chain: offer.chainName };

    return percent ? t('landing.hunt.found', { ...replacements, discount: formatDiscount(percent) }) : t('landing.hunt.found_plain', replacements);
});

/** Pošle maskota na lov: další akce do bubliny, jízda přes stránku (bez omezení pohybu). */
function hunt() {
    if (hunting.value || props.offers.length === 0) {
        return;
    }
    caught.value = (caught.value + 1) % props.offers.length;
    hunting.value = !prefersReducedMotion();
}

/**
 * Konec jízdy — maskot je zpátky na okraji a jde poslat znovu.
 *
 * @param {AnimationEvent} event
 */
function onAnimationEnd(event) {
    if (event.animationName === HUNT_ANIMATION) {
        hunting.value = false;
    }
}
</script>

<template>
    <div class="mascot-hunt" :class="{ 'mascot-hunt--hunting': hunting }" @animationend="onAnimationEnd">
        <p class="mascot-hunt__bubble" role="status">{{ bubble }}</p>
        <button type="button" class="mascot-hunt__mascot" :aria-label="t('landing.hunt.button')" @click="hunt">
            <img
                src="/images/brand/mascot-192.webp"
                srcset="/images/brand/mascot-192.webp 192w, /images/brand/mascot-288.webp 288w"
                :sizes="MASCOT_SIZES"
                width="192"
                height="192"
                alt=""
            />
        </button>
    </div>
</template>
