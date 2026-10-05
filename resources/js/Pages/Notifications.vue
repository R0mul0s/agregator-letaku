<!--
    Centrum upozornění (R74) — záznamy o nových akcích na hlídané zboží po dnech, od nejnovějšího.
    Nepřečtené jsou zvýrazněné; po zobrazení je stránka označí jako přečtené (zvonek i číslo
    na ikoně aplikace zmizí), zvýraznění ale zůstane, dokud uživatel stránku neopustí.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    /** Záznamy od nejnovějšího [{ id, kind, title, text, createdAt, unread, url }] (NotificationPresenter). */
    notifications: { type: Array, required: true },
    readUrl: { type: String, required: true },
    watchItemsUrl: { type: String, required: true },
    retentionDays: { type: Number, required: true },
});

/** Milisekund ve dni — pro „Včera“. */
const DAY_MS = 24 * 60 * 60 * 1000;

const t = useTranslations();
const page = usePage();

/** Nepřečtené při otevření stránky — zvýraznění vydrží i po označení jako přečtené. */
const unreadIds = new Set(props.notifications.filter((item) => item.unread).map((item) => item.id));

/**
 * Místní den (zóna zobrazení) jako klíč YYYY-MM-DD.
 *
 * @param {string|Date} value
 * @returns {string}
 */
function localDay(value) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: page.props.timezone }).format(new Date(value));
}

/**
 * Nadpis dne: Dnes, Včera, jinak den v týdnu s datem.
 *
 * @param {string} day YYYY-MM-DD
 * @returns {string}
 */
function dayLabel(day) {
    const now = Date.now();
    if (day === localDay(now)) {
        return t('notifications.today');
    }
    if (day === localDay(now - DAY_MS)) {
        return t('notifications.yesterday');
    }

    return new Intl.DateTimeFormat(page.props.locale, { weekday: 'long', day: 'numeric', month: 'numeric', timeZone: 'UTC' }).format(new Date(`${day}T00:00:00Z`));
}

/**
 * Čas záznamu: „14:05“.
 *
 * @param {string} createdAt ISO 8601
 * @returns {string}
 */
function timeLabel(createdAt) {
    return new Intl.DateTimeFormat(page.props.locale, { hour: '2-digit', minute: '2-digit', timeZone: page.props.timezone }).format(new Date(createdAt));
}

/** Záznamy po dnech [{ day, label, items }]. */
const days = computed(() => {
    const groups = new Map();
    for (const item of props.notifications) {
        const day = localDay(item.createdAt);
        groups.set(day, [...(groups.get(day) ?? []), item]);
    }

    return [...groups].map(([day, items]) => ({ day, label: dayLabel(day), items }));
});

onMounted(() => {
    if (unreadIds.size) {
        router.post(props.readUrl, { ids: [...unreadIds] }, { preserveScroll: true, preserveState: true });
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('notifications.title') }}</h1>
            <p class="page__subtitle">{{ t('notifications.intro', { days: retentionDays }) }}</p>
        </header>

        <EmptyState v-if="!notifications.length" :text="t('notifications.empty_title')">
            <p class="notifications__empty-hint">{{ t('notifications.empty') }}</p>
            <Link :href="watchItemsUrl" class="button button--primary">{{ t('notifications.empty_link') }}</Link>
        </EmptyState>

        <section v-for="group in days" :key="group.day" class="notifications__day">
            <h2 class="notifications__day-title">{{ group.label }}</h2>
            <ul class="card notifications__list">
                <li v-for="item in group.items" :key="item.id" class="notification-item" :class="{ 'notification-item--unread': unreadIds.has(item.id) }">
                    <Link :href="item.url" class="notification-item__link">
                        <span class="notification-item__body">
                            <span class="notification-item__title">
                                {{ item.title }}
                                <span v-if="unreadIds.has(item.id)" class="tag tag--accent">{{ t('notifications.unread') }}</span>
                            </span>
                            <span class="notification-item__text">{{ item.text }}</span>
                        </span>
                        <time class="notification-item__time" :datetime="item.createdAt">{{ timeLabel(item.createdAt) }}</time>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
