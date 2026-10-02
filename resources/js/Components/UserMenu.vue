<!--
    Menu účtu vpravo nahoře (R40) — avatar otevře nabídku se jménem, odkazem na účet,
    přepínačem vzhledu a odhlášením. Zavře se Escapem, klepnutím mimo a při přechodu na jinou stránku.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ThemeSwitch from '@/Components/ThemeSwitch.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useTranslations } from '@/lib/i18n';
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
    <div ref="root" class="user-menu" @keydown="onKeydown">
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
            <div class="user-menu__theme">
                <span class="user-menu__theme-label">{{ t('theme.label') }}</span>
                <ThemeSwitch />
            </div>
            <Link :href="page.props.auth.logoutUrl" method="post" as="button" class="user-menu__item">
                <!-- Dveře se šipkou ven -->
                <svg class="user-menu__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4H5v16h9M10 12h10M17 8l4 4-4 4" /></svg>
                {{ t('auth.logout') }}
            </Link>
        </div>
    </div>
</template>
