<!--
    Rámec stránek přihlášení a registrace — vedle formuláře (slot) barevný panel s maskotem,
    mottem a tím, co Slevohlídka umí. Na mobilu je panel nad formulářem a zkrácený.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';

const t = useTranslations();

/** Klíče textů s přednostmi aplikace (lang: ui.auth.showcase.benefits). */
const BENEFITS = ['chains', 'unit_price', 'mentions'];
</script>

<template>
    <div class="auth-showcase">
        <aside class="auth-showcase__panel">
            <img src="/images/brand/icon-512.png" alt="" class="auth-showcase__mascot" />
            <p class="auth-showcase__name">{{ t('app_name') }}</p>
            <p class="auth-showcase__tagline">{{ t('brand.tagline') }}</p>
            <ul class="auth-showcase__benefits">
                <li v-for="benefit in BENEFITS" :key="benefit" class="auth-showcase__benefit">{{ t(`auth.showcase.${benefit}`) }}</li>
            </ul>
        </aside>
        <div class="auth-showcase__form">
            <slot />
        </div>
    </div>
</template>
