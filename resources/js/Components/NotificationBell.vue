<!--
    Zvonek v hlavičce (R74) — odkaz do centra upozornění s počtem nepřečtených záznamů.
    Stejné číslo je na ikoně aplikace v telefonu (lib/pwa.js).

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Od kolika nepřečtených zvonek ukáže „9+“ — víc číslic se do kolečka nevejde. */
const MAX_SHOWN_COUNT = 9;

const t = useTranslations();
const page = usePage();

/** Sdílená vlastnost notificationCenter (HandleInertiaRequests): url, unread, active. */
const center = computed(() => page.props.notificationCenter);

const countLabel = computed(() => (center.value.unread > MAX_SHOWN_COUNT ? `${MAX_SHOWN_COUNT}+` : String(center.value.unread)));

const label = computed(() => (center.value.unread ? t('notifications.bell_unread', { count: center.value.unread }) : t('notifications.bell')));
</script>

<template>
    <Link
        v-if="center"
        :href="center.url"
        class="notification-bell"
        :class="{ 'notification-bell--active': center.active }"
        :aria-current="center.active ? 'page' : undefined"
        :aria-label="label"
        :title="label"
    >
        <!-- Zvonek -->
        <svg class="notification-bell__icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        <span v-if="center.unread" class="notification-bell__count" aria-hidden="true">{{ countLabel }}</span>
    </Link>
</template>
