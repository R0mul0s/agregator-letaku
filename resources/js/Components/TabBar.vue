<!--
    Spodní lišta záložek na telefonu (R66) — hlavní stránky přihlášeného na dosah palce,
    jako v nativní aplikaci; nahrazuje hamburger. Od středního displeje se neukazuje
    (navigace je v hlavičce). Položky jsou sdílená navigace s příznakem tab.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import NavIcon from '@/Components/NavIcon.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

const items = computed(() => page.props.navigation.filter((item) => item.tab));
</script>

<template>
    <nav v-if="items.length" class="tab-bar" :aria-label="t('nav.tabs_label')">
        <Link
            v-for="item in items"
            :key="item.url"
            :href="item.url"
            class="tab-bar__item"
            :class="{ 'tab-bar__item--active': item.active }"
            :aria-current="item.active ? 'page' : undefined"
        >
            <NavIcon :name="item.key" />
            <span class="tab-bar__label">{{ t(`nav.tabs.${item.key}`) }}</span>
        </Link>
    </nav>
</template>
