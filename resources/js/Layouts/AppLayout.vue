<!--
    Společné rozvržení stránek — hlavička s navigací a menu účtu (nepřihlášený jen přepínač vzhledu), obsah.
    Přihlášený má na telefonu navigaci pod tlačítkem menu (hamburger); nepřihlášený má jen
    „Všechny akce“, ty se vejdou do řádku s přihlášením (R44).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import UserMenu from '@/Components/UserMenu.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** id navigace pro aria-controls tlačítka menu. */
const NAV_ID = 'app-navigation';

const t = useTranslations();
const page = usePage();

/** Hamburger má smysl jen s víc položkami navigace (přihlášený). */
const hasMenuButton = computed(() => page.props.navigation.length > 1);

/** Navigace rozbalená na telefonu. */
const navOpen = ref(false);

/**
 * Escape navigaci zavře.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key === 'Escape') {
        navOpen.value = false;
    }
}

let removeNavigateListener = null;

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    // Po přechodu na jinou stránku se menu zavře
    removeNavigateListener = router.on('navigate', () => (navOpen.value = false));
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    removeNavigateListener?.();
});
</script>

<template>
    <a href="#main" class="skip-link">{{ t('skip_to_content') }}</a>
    <!-- Nepřihlášený má na mobilu navigaci i přihlášení v jednom řádku s logem (R44) -->
    <!-- Na úvodní stránce logo na telefonu jede jako košík v hlavním pruhu (ten je na mobilu skrytý) -->
    <header
        class="app-header"
        :class="{
            'app-header--guest': !page.props.auth.user,
            'app-header--animated-logo': page.component === 'Landing',
            'app-header--with-menu': hasMenuButton,
            'app-header--menu-open': navOpen,
        }"
    >
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
            <nav v-if="page.props.navigation.length" :id="NAV_ID" class="app-header__nav" :aria-label="t('nav.label')">
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
                <!-- Hamburger jen na telefonu: rozbalí navigaci pod hlavičkou -->
                <button
                    v-if="hasMenuButton"
                    type="button"
                    class="app-header__menu-button"
                    :aria-expanded="navOpen ? 'true' : 'false'"
                    :aria-controls="NAV_ID"
                    @click="navOpen = !navOpen"
                >
                    <svg class="app-header__menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path v-if="navOpen" d="M6 6l12 12M18 6 6 18" />
                        <path v-else d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <span class="visually-hidden">{{ navOpen ? t('nav.close') : t('nav.open') }}</span>
                </button>
            </div>
        </div>
    </header>

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <slot />
    </main>
</template>
