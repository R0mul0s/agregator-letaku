<!--
    Záznam centra upozornění (R74) — cíl klepnutí na upozornění v telefonu. Akce po skupinách
    (hlídané položky, u končících akcí ze seznamu obchody) jako kompaktní řádky s obchodem
    a tlačítkem do nákupního seznamu; skončené (nebo stažené obchodem) jsou označené.
    Zpráva od nás (11d) má místo akcí text a odkaz. Po zobrazení stránka záznam označí jako přečtený.

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

/** Druhy záznamu s tlačítkem do nákupního seznamu — akce se kupují teď. */
const SHOPPING_KINDS = ['ending_soon', 'starting_today'];

const props = defineProps({
    /** Shrnutí záznamu { id, kind, title, text, createdAt, unread } (NotificationPresenter). */
    notification: { type: Object, required: true },
    /** Akce po skupinách [{ title, offers: [{ ...OfferPresenter, userPrice, ended }] }]. */
    groups: { type: Array, required: true },
    indexUrl: { type: String, required: true },
    readUrl: { type: String, required: true },
    shoppingListUrl: { type: String, required: true },
    /** Zpráva od nás (11d) { body, url, external }; null u záznamů s akcemi. */
    announcement: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();

const createdAt = computed(() => formatDateTime(props.notification.createdAt, page.props.locale, page.props.timezone));

/** Skončily už všechny akce záznamu? Zpráva od nás akce nemá. */
const allEnded = computed(() => props.groups.length > 0 && props.groups.every((group) => group.offers.every((offer) => offer.ended)));

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
        <!-- Končící akce ze seznamu (11b) a dnes začínající akce (R76): nákup se odškrtává v seznamu -->
        <p v-else-if="SHOPPING_KINDS.includes(notification.kind)" class="notification-detail__actions">
            <Link :href="shoppingListUrl" class="button button--primary">{{ t('notifications.open_shopping_list') }}</Link>
        </p>

        <!-- Zpráva od nás (11d): text s řádky, jak je admin napsal, a odkaz -->
        <section v-if="announcement" class="card notification-detail__group">
            <p class="notification-detail__body">{{ announcement.body }}</p>
            <p v-if="announcement.url" class="notification-detail__actions">
                <a v-if="announcement.external" :href="announcement.url" target="_blank" rel="noopener noreferrer" class="button button--primary">{{
                    t('notifications.open_link')
                }}</a>
                <Link v-else :href="announcement.url" class="button button--primary">{{ t('notifications.open_link') }}</Link>
            </p>
        </section>

        <section v-for="group in groups" :key="group.title" class="card notification-detail__group">
            <h2 class="notification-detail__title">{{ group.title }}</h2>
            <ul class="offer-rows">
                <OfferRow v-for="offer in group.offers" :key="offer.id" :offer="offer" with-chain />
            </ul>
        </section>
    </AppLayout>
</template>
