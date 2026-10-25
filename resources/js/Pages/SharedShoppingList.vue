<!--
    Nákupní seznam sdílený odkazem (R130) — partner (i bez účtu) vidí aktuální seznam vlastníka
    a odškrtává, co koupil; vlastník to uvidí u sebe. Přidávat a mazat jde jen u vlastníka.
    Po návratu do okna se seznam načte znovu (vlastník mezitím mohl něco přidat). Nepřihlášený
    dole najde pozvánku k registraci. Data posílá SharedShoppingListController.

    @author Roman Hlaváček
    @created 2026-10-10
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import ShoppingGroups from '@/Components/ShoppingGroups.vue';
import WakeLockSwitch from '@/Components/WakeLockSwitch.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { showToast } from '@/lib/toast';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

defineProps({
    /** Křestní jméno vlastníka seznamu. */
    ownerName: { type: String, required: true },
    /** Skupiny po obchodech jako u vlastníka, bez mazání. */
    groups: { type: Array, required: true },
    registerUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Volby požadavku, po kterých stránka zůstane, kde je. Asynchronně — rychlé odškrtnutí další
 * položky by synchronní požadavek předchozí přerušilo (R170).
 */
const KEEP_PAGE = { preserveScroll: true, preserveState: true, async: true };

/** Návrat do okna: načíst seznam znovu — vlastník mezitím mohl něco přidat. */
function onVisible() {
    if (document.visibilityState === 'visible' && navigator.onLine) {
        // Asynchronně — nepřeruší obnovení celé stránky po delší pauze (lib/pwa.js, R170)
        router.reload({ only: ['groups'], async: true });
    }
}

onMounted(() => document.addEventListener('visibilitychange', onVisible));
onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible));

/**
 * Odškrtne položku, nebo odškrtnutí zruší. Bez připojení to řekne toast — fronta bez signálu
 * (R66) je jen pro vlastníka seznamu.
 *
 * @param {object} item
 * @param {boolean} checked
 */
function check(item, checked) {
    router.patch(
        item.updateUrl,
        { checked },
        {
            ...KEEP_PAGE,
            onNetworkError: () => {
                showToast(t('shared_shopping.check_failed'));
                router.reload({ only: ['groups'] });

                return false;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="t('shared_shopping.title')" />

        <header class="page__header page-hero">
            <h1 class="page__title">{{ t('shared_shopping.title') }}</h1>
            <p class="page__subtitle">{{ t('shared_shopping.intro', { name: ownerName }) }}</p>
        </header>

        <EmptyState v-if="!groups.length" :text="t('shared_shopping.empty')" />

        <template v-else>
            <!-- Nezhasínání displeje jako přepínač v kartě (R146) -->
            <WakeLockSwitch class="card shopping-wake" />
            <ShoppingGroups :groups="groups" @check="check" />
        </template>

        <!-- Pozvánka pro partnera bez účtu -->
        <section v-if="!page.props.auth.user" class="card shopping-shared-cta">
            <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="shopping-shared-cta__mascot" />
            <div class="shopping-shared-cta__body">
                <h2 class="shopping-shared-cta__title">{{ t('shared_shopping.cta_title') }}</h2>
                <p class="shopping-shared-cta__text">{{ t('shared_shopping.cta_text') }}</p>
            </div>
            <Link :href="registerUrl" class="button button--primary">{{ t('shared_shopping.cta_button') }}</Link>
        </section>
    </AppLayout>
</template>
