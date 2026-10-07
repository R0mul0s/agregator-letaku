<!--
    Společné rozvržení stránek — plovoucí hlavička s navigací a menu účtu (nepřihlášený přihlášení,
    registraci a přepínač vzhledu), obsah. Na telefonu je navigace pod tlačítkem menu
    (hamburger); nepřihlášený má v hlavičce jen Registraci, přihlášení a vzhled jsou v menu (R44).
    Vpravo dole tlačítko Nahoru. Pod hlavičkou lišta pro neověřený e-mail, dole patička
    s provozovatelem a právními stránkami (R51), lišta souhlasu s cookies (R52).
    Aplikace v telefonu (R66): přihlášený má na telefonu hlavní stránky ve spodní liště záložek
    (hamburger jen pro položky mimo lištu — dnes žádné, katalog admina je v menu pod avatarem, R75),
    lišta „Jste offline“ a výzva k přidání na plochu; lišta „Máme novou verzi“ po nasazení (R78).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import AppFooter from '@/Components/AppFooter.vue';
import BackToTop from '@/Components/BackToTop.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import EmailVerificationBar from '@/Components/EmailVerificationBar.vue';
import InstallPrompt from '@/Components/InstallPrompt.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import OfflineBar from '@/Components/OfflineBar.vue';
import StoresDialog from '@/Components/StoresDialog.vue';
import TabBar from '@/Components/TabBar.vue';
import Toaster from '@/Components/Toaster.vue';
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import UpdateBar from '@/Components/UpdateBar.vue';
import UserMenu from '@/Components/UserMenu.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** id navigace pro aria-controls tlačítka menu. */
const NAV_ID = 'app-navigation';

const t = useTranslations();
const page = usePage();

/** Hamburger na telefonu — jen pro položky, které nejsou ve spodní liště záložek (R66). */
const hasMenuButton = computed(() => page.props.navigation.some((item) => !item.tab));

/** Spodní lišta záložek na telefonu (R66) — v hlavičce je pak místo na celé logo s názvem. */
const hasTabBar = computed(() => page.props.navigation.some((item) => item.tab));

/** Navigace rozbalená na telefonu. */
const navOpen = ref(false);
const menuButton = ref(null);

/**
 * Escape navigaci zavře; byl-li fokus v navigaci, vrátí ho na tlačítko menu (R99) — ve skryté
 * navigaci by se ztratil.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key !== 'Escape' || !navOpen.value) {
        return;
    }
    const focusInNav = document.getElementById(NAV_ID)?.contains(document.activeElement);
    navOpen.value = false;
    if (focusInNav) {
        menuButton.value?.focus();
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
    <!-- Na úvodní stránce logo na telefonu jede jako košík v hlavním pruhu (ten je na mobilu skrytý) -->
    <header
        class="app-header"
        :class="{
            'app-header--guest': !page.props.auth.user,
            'app-header--animated-logo': page.component === 'Landing',
            'app-header--with-menu': hasMenuButton,
            'app-header--with-tabs': hasTabBar,
            'app-header--menu-open': navOpen,
        }"
    >
        <div class="app-header__inner">
            <Link href="/" class="app-header__brand">
                <img src="/images/brand/logo-mark-128.webp" width="128" height="128" alt="" class="app-header__logo" />
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
                    :class="{ 'app-header__link--active': item.active, 'app-header__link--tab': item.tab }"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ t(item.label) }}
                </Link>
                <!-- Nepřihlášený na telefonu: přihlášení a vzhled v menu (v hlavičce se nevejdou) -->
                <div v-if="!page.props.auth.user" class="app-header__nav-extra">
                    <Link v-if="page.component !== 'Auth/Login'" :href="page.props.auth.loginUrl" class="link">{{ t('auth.login.submit') }}</Link>
                    <ThemeSwitch />
                </div>
            </nav>
            <div class="app-header__actions">
                <!-- Zvonek s nepřečtenými upozorněními (R74); vzhled i odhlášení v menu pod avatarem (R40) -->
                <NotificationBell v-if="page.props.auth.user" />
                <UserMenu v-if="page.props.auth.user" />
                <template v-else>
                    <!-- Na mobilu by se vedle přihlášení nevešel — vzhled se tam řídí systémem -->
                    <ThemeSwitch class="app-header__guest-theme" />
                    <!-- Nepřihlášený (R44): přihlášení a registrace; na jejich stránkách se neopakují -->
                    <Link v-if="page.component !== 'Auth/Login'" :href="page.props.auth.loginUrl" class="button button--ghost app-header__login">{{ t('auth.login.submit') }}</Link>
                    <Link v-if="page.component !== 'Auth/Register'" :href="page.props.auth.registerUrl" class="button button--primary app-header__register">
                        {{ t('auth.register.title') }}
                    </Link>
                </template>
                <!-- Hamburger jen na telefonu: rozbalí navigaci pod hlavičkou -->
                <button
                    v-if="hasMenuButton"
                    ref="menuButton"
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

    <EmailVerificationBar v-if="page.props.auth.user && !page.props.auth.user.emailVerified" />
    <OfflineBar />
    <UpdateBar />

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <InstallPrompt />
        <slot />
    </main>

    <AppFooter />
    <TabBar />

    <BackToTop />
    <Toaster />
    <ConfirmDialog />
    <CookieConsent />
    <StoresDialog />
</template>
