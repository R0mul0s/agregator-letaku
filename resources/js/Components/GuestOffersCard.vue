<!--
    Výzva k registraci pro nepřihlášené uprostřed výpisu akcí (R44, vzhled R141) — karta přes celou
    šířku mřížky s maskotem, třemi důvody a žlutým tlačítkem. Všechny akce a Nejlepší slevy týdne
    (R143) ji vkládají po šesti akcích; přihlášenému se neukáže.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

/** Body karty (pole v překladech, :chains = počet sledovaných obchodů ze sdílených chainInfo). */
const points = computed(() => {
    const chains = Object.keys(page.props.chainInfo ?? {}).length;

    return (page.props.translations?.offers_guest?.points ?? []).map((point) => point.replace(':chains', String(chains)));
});
</script>

<template>
    <aside v-if="!page.props.auth.user" class="offers-guest">
        <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="offers-guest__mascot" loading="lazy" />
        <div class="offers-guest__body">
            <p class="offers-guest__eyebrow">{{ t('offers_guest.eyebrow') }}</p>
            <h2 class="offers-guest__title">{{ t('offers_guest.text') }}</h2>
            <ul class="offers-guest__points">
                <li v-for="(point, index) in points" :key="index">{{ point }}</li>
            </ul>
        </div>
        <div class="offers-guest__actions">
            <Link :href="page.props.auth.registerUrl" class="button button--highlight">{{ t('offers_guest.register') }}</Link>
            <small class="offers-guest__note">{{ t('offers_guest.note') }}</small>
        </div>
    </aside>
</template>
