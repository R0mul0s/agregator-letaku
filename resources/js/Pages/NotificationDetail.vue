<!--
    Záznam centra upozornění (R74) — cíl klepnutí na upozornění v telefonu. Akce po hlídaných
    položkách jako kompaktní řádky s obchodem a tlačítkem do nákupního seznamu; skončené
    (nebo stažené obchodem) jsou označené. Po zobrazení stránka záznam označí jako přečtený.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import OfferRow from '@/Components/OfferRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    /** Shrnutí záznamu { id, title, text, createdAt, unread } (NotificationPresenter). */
    notification: { type: Object, required: true },
    /** Akce po hlídaných položkách [{ watchItem, offers: [{ ...OfferPresenter, userPrice, ended }] }]. */
    groups: { type: Array, required: true },
    indexUrl: { type: String, required: true },
    readUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

const createdAt = computed(() => formatDateTime(props.notification.createdAt, page.props.locale, page.props.timezone));

/** Skončily už všechny akce záznamu? */
const allEnded = computed(() => props.groups.every((group) => group.offers.every((offer) => offer.ended)));

onMounted(() => {
    if (props.notification.unread) {
        router.post(props.readUrl, { ids: [props.notification.id] }, { preserveScroll: true, preserveState: true });
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="notification.title" />

        <Link :href="indexUrl" class="link notification-detail__back">← {{ t('notifications.back') }}</Link>

        <header class="page__header">
            <h1 class="page__title">{{ notification.title }}</h1>
            <p class="page__subtitle">
                <time :datetime="notification.createdAt">{{ createdAt }}</time>
            </p>
        </header>

        <p v-if="allEnded" class="notice">{{ t('notifications.all_ended') }}</p>

        <section v-for="group in groups" :key="group.watchItem" class="card notification-detail__group">
            <h2 class="notification-detail__title">{{ group.watchItem }}</h2>
            <ul class="offer-rows">
                <OfferRow v-for="offer in group.offers" :key="offer.id" :offer="offer" with-chain />
            </ul>
        </section>
    </AppLayout>
</template>
