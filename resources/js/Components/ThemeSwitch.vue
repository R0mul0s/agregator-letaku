<!--
    Přepínač vzhledu v hlavičce: světlý / tmavý / podle systému.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { THEMES, useTheme } from '@/lib/theme';

const t = useTranslations();
const { preference, setTheme } = useTheme();
</script>

<template>
    <div class="theme-switch" role="group" :aria-label="t('theme.label')">
        <button
            v-for="theme in THEMES"
            :key="theme"
            type="button"
            class="theme-switch__button"
            :class="{ 'theme-switch__button--active': preference === theme }"
            :aria-pressed="preference === theme"
            :title="t(`theme.${theme}`)"
            @click="setTheme(theme)"
        >
            <!-- Ikony: slunce / měsíc / monitor -->
            <svg class="theme-switch__icon" viewBox="0 0 24 24" aria-hidden="true">
                <template v-if="theme === 'light'">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                </template>
                <path v-else-if="theme === 'dark'" d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
                <template v-else>
                    <rect x="3" y="4" width="18" height="12" rx="2" />
                    <path d="M8 20h8M12 16v4" />
                </template>
            </svg>
            <span class="visually-hidden">{{ t(`theme.${theme}`) }}</span>
        </button>
    </div>
</template>
