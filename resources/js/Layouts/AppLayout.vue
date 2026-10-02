<!--
    Společné rozvržení stránek — hlavička s navigací a menu účtu (nepřihlášený jen přepínač vzhledu), obsah.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import UserMenu from '@/Components/UserMenu.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

const t = useTranslations();
const page = usePage();
</script>

<template>
    <a href="#main" class="skip-link">{{ t('skip_to_content') }}</a>
    <!-- Nepřihlášený má na mobilu navigaci i přihlášení v jednom řádku s logem (R44) -->
    <!-- Na úvodní stránce logo na telefonu jede jako košík v hlavním pruhu (ten je na mobilu skrytý) -->
    <header class="app-header" :class="{ 'app-header--guest': !page.props.auth.user, 'app-header--animated-logo': page.component === 'Landing' }">
        <div class="app-header__inner">
            <Link href="/" class="app-header__brand">
                <img src="/images/brand/logo-mark.png" alt="" class="app-header__logo" />
                <!-- Název ve dvou barvách jako v logu; části bez mezery, čtečka přečte jedno slovo -->
                <span class="app-header__name">
                    <span class="app-header__wordmark"
                        ><span class="app-header__wordmark-first">{{ t('brand.first') }}</span>{{ t('brand.second') }}</span
                    >
                    <span class="app-header__tagline">{{ t('brand.tagline') }}</span>
                </span>
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
                <!-- Přihlášený má vzhled i odhlášení v menu pod avatarem (R40) -->
                <UserMenu v-if="page.props.auth.user" />
                <template v-else>
                    <!-- Na mobilu by se vedle přihlášení nevešel — vzhled se tam řídí systémem -->
                    <ThemeSwitch class="app-header__guest-theme" />
                    <!-- Nepřihlášený (R44): přihlášení a registrace; na jejich stránkách se neopakují -->
                    <Link v-if="page.component !== 'Auth/Login'" :href="page.props.auth.loginUrl" class="button button--ghost">{{ t('auth.login.submit') }}</Link>
                    <Link v-if="page.component !== 'Auth/Register'" :href="page.props.auth.registerUrl" class="button button--primary app-header__register">
                        {{ t('auth.register.title') }}
                    </Link>
                </template>
            </div>
        </div>
    </header>

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <slot />
    </main>
</template>
