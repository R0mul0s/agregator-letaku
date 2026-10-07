<!--
    Tlačítka „Pokračovat přes Google / Facebook“ (R96) nad formulářem přihlášení a registrace,
    pod nimi oddělovač „nebo e-mailem“. Obyčejné odkazy, ne Inertia: přesměrování
    k poskytovateli musí udělat prohlížeč (XHR Inertie by ho nedokončil, CSP form-action).

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import SocialLogo from '@/Components/SocialLogo.vue';
import { useTranslations } from '@/lib/i18n';
import { computed } from 'vue';

const props = defineProps({
    /** Poskytovatelé [{ provider, url, logo, tinted }] — jen ti s klíči na serveru. */
    providers: { type: Array, required: true },
    /** Přihlásit se „Zapamatovat si mě“ (stránka přihlášení); null = parametr nepřidávat. */
    remember: { type: Boolean, default: null },
    /** Název parametru adresy pro „Zapamatovat si mě“. */
    rememberParameter: { type: String, default: null },
});

const t = useTranslations();

/** Odkazy s parametrem „Zapamatovat si mě“, když je zaškrtnutý. */
const buttons = computed(() =>
    props.providers.map((item) => ({
        ...item,
        href: props.remember && props.rememberParameter ? `${item.url}?${props.rememberParameter}=1` : item.url,
        // Text tlačítka podle poskytovatele — Seznam má v manuálu „Přihlásit přes Seznam“ (R98)
        label: t(`auth.social.buttons.${item.provider}`),
    })),
);
</script>

<template>
    <div v-if="buttons.length" class="social-login">
        <a v-for="button in buttons" :key="button.provider" :href="button.href" class="button social-login__button" :class="`social-login__button--${button.provider}`">
            <SocialLogo :provider="button.provider" :logo="button.logo" :tinted="button.tinted" />
            {{ button.label }}
        </a>
        <p class="social-login__divider">{{ t('auth.social.divider') }}</p>
    </div>
</template>
