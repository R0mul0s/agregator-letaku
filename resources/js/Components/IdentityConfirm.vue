<!--
    Potvrzení totožnosti účtu bez hesla (R96) místo pole s heslem: změnu e-mailu, nastavení
    hesla, odhlášení zařízení a zrušení účtu potvrdí přihlášení u propojeného poskytovatele
    (IdentityConfirmation). Po návratu je potvrzení chvíli platné a ukáže se „Potvrzeno“.

    @author Roman Hlaváček
    @created 2026-10-06
-->
<script setup>
import SocialLogo from '@/Components/SocialLogo.vue';
import { useTranslations } from '@/lib/i18n';
import { computed } from 'vue';

const props = defineProps({
    /** Přihlášení přes účty z Mého účtu { identityConfirmed, sectionParameter, providers }. */
    social: { type: Object, required: true },
    /** Kotva sekce Mého účtu, kam se po potvrzení vrátit („zruseni-uctu“). */
    section: { type: String, required: true },
    /** Chyba validace ze serveru (potvrzení chybí nebo vypršelo). */
    error: { type: String, default: undefined },
});

const t = useTranslations();

/** Tlačítka potvrzení — jen propojení poskytovatelé, jiným se potvrdit nejde. */
const buttons = computed(() =>
    props.social.providers
        .filter((provider) => provider.linked)
        .map((provider) => ({
            ...provider,
            href: `${provider.confirmUrl}?${props.social.sectionParameter}=${props.section}`,
            label: t('account.social.confirm', { provider: t(`auth.social.providers.${provider.provider}`) }),
        })),
);
</script>

<template>
    <div class="identity-confirm">
        <p v-if="social.identityConfirmed" class="identity-confirm__done">{{ t('account.social.confirmed') }}</p>
        <template v-else>
            <p class="form-field__hint">{{ t('account.social.confirm_hint') }}</p>
            <div class="identity-confirm__actions">
                <a v-for="button in buttons" :key="button.provider" :href="button.href" class="button social-login__button" :class="`social-login__button--${button.provider}`">
                    <SocialLogo :provider="button.provider" :logo="button.logo" :tinted="button.tinted" />
                    {{ button.label }}
                </a>
            </div>
        </template>
        <p v-if="error" class="form-field__error" role="alert">{{ error }}</p>
    </div>
</template>
