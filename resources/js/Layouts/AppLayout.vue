<!--
    Společné rozvržení stránek — hlavička s navigací, přepínačem vzhledu a odhlášením, obsah.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

const t = useTranslations();
const page = usePage();
</script>

<template>
    <a href="#main" class="skip-link">{{ t('skip_to_content') }}</a>
    <header class="app-header">
        <div class="app-header__inner">
            <Link href="/" class="app-header__brand">
                <img src="/favicon.svg" alt="" class="app-header__logo" />
                <span class="app-header__name">{{ t('app_name') }}</span>
            </Link>
            <nav v-if="page.props.navigation.length" class="app-header__nav" :aria-label="t('nav.label')">
                <Link
                    v-for="item in page.props.navigation"
                    :key="item.url"
                    :href="item.url"
                    class="app-header__link"
                    :class="{ 'app-header__link--active': item.active }"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ t(item.label) }}
                </Link>
            </nav>
            <div class="app-header__actions">
                <ThemeSwitch />
                <Link
                    v-if="page.props.auth.user"
                    :href="page.props.auth.logoutUrl"
                    method="post"
                    as="button"
                    class="button button--ghost"
                >
                    {{ t('auth.logout') }}
                </Link>
            </div>
        </div>
    </header>

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <slot />
    </main>
</template>
