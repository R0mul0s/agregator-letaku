<!--
    Seznam slev přihlášeného uživatele. Do etapy 3 (hlídání) jen prázdný stav.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head } from '@inertiajs/vue3';

const t = useTranslations();
</script>

<template>
    <AppLayout>
        <Head :title="t('home.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('home.title') }}</h1>
        </header>

        <p class="page__empty">{{ t('home.empty') }}</p>
    </AppLayout>
</template>
