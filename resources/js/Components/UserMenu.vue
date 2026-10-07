<!--
    Menu účtu vpravo nahoře (R40) — avatar otevře nabídku se jménem, odkazem na účet (admin
    i na katalog, přehled uživatelů a zprávy uživatelům, R75, R84), přepínačem vzhledu a odhlášením. Zavře se Escapem, klepnutím mimo a při přechodu na jinou stránku.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useTranslations } from '@/lib/i18n';
import { unsubscribeThisDevice } from '@/lib/push';
import { Link, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

const t = useTranslations();
const page = usePage();
const menuId = useId();

const open = ref(false);
const root = ref(null);
const toggleButton = ref(null);

/** Zavře menu; s returnFocus vrátí fokus na avatar (Escape). */
function close(returnFocus = false) {
    open.value = false;
    if (returnFocus) {
        toggleButton.value?.focus();
    }
}

/**
 * Klepnutí mimo menu ho zavře.
 *
 * @param {PointerEvent} event
 */
function onDocumentPointerDown(event) {
    if (open.value && !root.value?.contains(event.target)) {
        close();
    }
}

/**
 * Escape menu zavře a vrátí fokus na avatar.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        close(true);
    }
}

/**
 * Odhlásí uživatele. Nejdřív zruší odběr upozornění v tomto prohlížeči a jeho adresu pošle
 * s odhlášením, server smaže záznam (R67, ForgetPushSubscriptionOnLogout).
 */
async function logout() {
    const endpoint = await unsubscribeThisDevice();

    router.post(page.props.auth.logoutUrl, endpoint ? { push_endpoint: endpoint } : {});
}

/**
 * Fokus odešel z menu (Tab za poslední položku) — menu zavře, jinak by zůstalo viset otevřené
 * nad stránkou (R99).
 *
 * @param {FocusEvent} event
 */
function onFocusout(event) {
    if (open.value && event.relatedTarget && !root.value?.contains(event.relatedTarget)) {
        close();
    }
}

let removeNavigateListener = null;

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    removeNavigateListener = router.on('navigate', () => close());
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    removeNavigateListener?.();
});
</script>

<template>
    <div ref="root" class="user-menu" @keydown="onKeydown" @focusout="onFocusout">
        <button
            ref="toggleButton"
            type="button"
            class="user-menu__toggle"
            :class="{ 'user-menu__toggle--active': page.props.auth.accountActive }"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="menuId"
            @click="open = !open"
        >
            <UserAvatar :name="page.props.auth.user.name" :url="page.props.auth.user.avatarUrl" />
            <span class="visually-hidden">{{ t('user_menu.label') }}</span>
        </button>

        <div v-show="open" :id="menuId" class="user-menu__panel">
            <div class="user-menu__identity">
                <strong class="user-menu__name">{{ page.props.auth.user.name }}</strong>
                <span class="user-menu__email">{{ page.props.auth.user.email }}</span>
            </div>
            <Link :href="page.props.auth.accountUrl" class="user-menu__item">
                <!-- Postava -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6" /></svg>
                {{ t('account.title') }}
            </Link>
            <!-- Jen admin: katalog produktů (R29, R75), přehled uživatelů (R84) a zprávy do centra upozornění (R74, 11d) -->
            <Link v-if="page.props.auth.catalogUrl" :href="page.props.auth.catalogUrl" class="user-menu__item">
                <!-- Kniha -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2V5z" /><path d="M4 19a2 2 0 0 1 2-2h14" />
                </svg>
                {{ t('nav.catalog') }}
            </Link>
            <Link v-if="page.props.auth.usersUrl" :href="page.props.auth.usersUrl" class="user-menu__item">
                <!-- Dvě postavy -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="9" cy="8" r="3.5" /><path d="M2 20c.8-3.5 3.6-5.5 7-5.5s6.2 2 7 5.5" /><path d="M15.5 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .8 3.4 2.6 4 5.2" />
                </svg>
                {{ t('user_menu.users') }}
            </Link>
            <Link v-if="page.props.auth.announcementsUrl" :href="page.props.auth.announcementsUrl" class="user-menu__item">
                <!-- Megafon -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10v4h3l7 4V6L6 10H3z" /><path d="M16 9a4 4 0 0 1 0 6" /></svg>
                {{ t('user_menu.announcements') }}
            </Link>
            <div class="user-menu__theme">
                <span class="user-menu__theme-label">{{ t('theme.label') }}</span>
                <ThemeSwitch />
            </div>
            <button type="button" class="user-menu__item" @click="logout">
                <!-- Dveře se šipkou ven -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4H5v16h9M10 12h10M17 8l4 4-4 4" /></svg>
                {{ t('auth.logout') }}
            </button>
        </div>
    </div>
</template>
