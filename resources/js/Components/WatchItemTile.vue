<!--
    Dlaždice hlídané položky v Hlídám — název, odkud jsou pravidla (katalog / vlastní slova),
    co je teď v akci (počet, nejnižší cena, zmínky v letácích) a akce upravit / přestat hlídat.
    Úprava vlastních slov otevře formulář přímo v dlaždici.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import WatchItemForm from '@/Components/WatchItemForm.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    /** Položka z WatchItemController::index (pravidla, počty akcí, adresy). */
    item: { type: Object, required: true },
    /** Odkaz na Moje slevy — kotva vede na skupinu položky. */
    homeUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();
const editing = ref(false);

/** Po potvrzení položku smaže. */
function remove() {
    if (window.confirm(t('watch.delete_confirm', { name: props.item.name }))) {
        router.delete(props.item.deleteUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <article class="card watch-tile" :class="{ 'watch-tile--editing': editing, 'watch-tile--active': item.offersCount > 0 }">
        <WatchItemForm
            v-if="editing"
            :url="item.updateUrl"
            method="put"
            :item="item"
            :submit-label="t('watch.save')"
            @saved="editing = false"
            @cancel="editing = false"
        />
        <template v-else>
            <div class="watch-tile__header">
                <h3 class="watch-tile__name">{{ item.name }}</h3>
                <div class="watch-tile__actions">
                    <button v-if="!item.productId" type="button" class="icon-button" :title="t('watch.edit')" @click="editing = true">
                        <!-- Tužka -->
                        <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4" /></svg>
                        <span class="visually-hidden">{{ t('watch.edit') }} {{ item.name }}</span>
                    </button>
                    <button type="button" class="icon-button icon-button--danger" :title="item.productId ? t('watch.stop') : t('watch.delete')" @click="remove">
                        <!-- Koš -->
                        <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
                        <span class="visually-hidden">{{ item.productId ? t('watch.stop') : t('watch.delete') }} {{ item.name }}</span>
                    </button>
                </div>
            </div>

            <p class="watch-tile__source">
                <span class="tag">{{ item.productId ? t('watch.from_catalog') : t('watch.source_own') }}</span>
                <span v-if="!item.productId" class="watch-tile__keywords">{{ item.keywords }}</span>
            </p>

            <Link v-if="item.offersCount" :href="`${homeUrl}#polozka-${item.id}`" class="watch-tile__status watch-tile__status--offers">
                <strong>{{ t('watch.offers_count', { count: item.offersCount }) }}</strong>
                <span v-if="item.lowestPrice !== null">{{ t('watch.lowest_price', { price: formatPrice(item.lowestPrice, page.props.locale) }) }}</span>
            </Link>
            <p v-else class="watch-tile__status">{{ t('watch.no_offers') }}</p>
            <p v-if="item.mentionsCount" class="watch-tile__mentions">{{ t('watch.mentions_count', { count: item.mentionsCount }) }}</p>
        </template>
    </article>
</template>
