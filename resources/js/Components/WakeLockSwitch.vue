<!--
    „Nezhasínat displej“ v nákupním seznamu (R66, vzhled R146) — řádek s ikonou, popisem a
    přepínačem místo šedého tlačítka, aby bylo vidět, jestli je zapnuté. Nákupní seznam i seznam
    sdílený odkazem; bez podpory prohlížeče se neukáže.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { useWakeLock } from '@/lib/wakeLock';

const t = useTranslations();
const wakeLock = useWakeLock();
</script>

<template>
    <label v-if="wakeLock.supported" class="wake-switch">
        <span class="wake-switch__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
        </span>
        <span class="wake-switch__text">
            <strong class="wake-switch__title">{{ t('shopping.wake_lock') }}</strong>
            <small class="wake-switch__hint">{{ t('shopping.wake_lock_hint') }}</small>
        </span>
        <span class="form-switch">
            <input :checked="wakeLock.enabled.value" type="checkbox" role="switch" class="form-switch__input" @change="wakeLock.toggle" />
        </span>
    </label>
</template>
