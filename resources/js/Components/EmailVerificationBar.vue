<!--
    Lišta pod hlavičkou pro přihlášeného s neověřeným e-mailem (R51): výzva k potvrzení
    a nový odkaz. Aplikace jde používat i bez ověření, e-maily ale chodí jen na ověřenou adresu.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/vue3';

const t = useTranslations();
const page = usePage();

const form = useForm({});

/** Pošle nový odkaz pro potvrzení e-mailu. */
function resend() {
    form.post(page.props.auth.verificationSendUrl, { preserveScroll: true });
}
</script>

<template>
    <div class="verify-bar" role="status">
        <div class="verify-bar__inner">
            <p class="verify-bar__text">{{ t('auth.verify.text', { email: page.props.auth.user.email }) }}</p>
            <button type="button" class="button button--ghost verify-bar__button" :disabled="form.processing" @click="resend">{{ t('auth.verify.resend') }}</button>
        </div>
    </div>
</template>
