<!--
    Sekce „Brzy“ v Mých slevách (R76) — akce hlídaných položek, které obchody už zveřejnily,
    ale začnou až v příštích dnech. Sbalitelná jako skupiny hlídaných položek (výchozí stav
    a zapamatování řídí stránka); hlavička ukáže počet akcí a nejbližší začátek, po rozbalení
    akce po hlídaných položkách.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import OfferCard from '@/Components/OfferCard.vue';
import ShoppingToggle from '@/Components/ShoppingToggle.vue';
import WatchGroupBar from '@/Components/WatchGroupBar.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { upcomingAnchor } from '@/lib/offer';
import { showOfferActions } from '@/lib/offerActions';
import { usePage } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

const props = defineProps({
    /** Hlídané položky s budoucími akcemi (HomeController, `upcoming` u každé položky). */
    items: { type: Array, required: true },
});

/** Rozbalená sekce (řídí stránka — pamatuje si stav). */
const expanded = defineModel('expanded', { type: Boolean, default: false });

const t = useTranslations();
const page = usePage();
const bodyId = useId();

/** Počet budoucích akcí ve všech položkách. */
const count = computed(() => props.items.reduce((sum, item) => sum + item.upcoming.length, 0));

/** Nejbližší začátek budoucí akce (YYYY-MM-DD — řadí se jako text). */
const firstStart = computed(() =>
    props.items
        .flatMap((item) => item.upcoming.map((offer) => offer.validFrom))
        .sort()
        .at(0),
);

/** „1 akce, první od po 12. 10.“ — v hlavičce i v přilepené liště (R117). */
const summaryText = computed(() => t('home.upcoming_summary', { count: count.value, date: formatDate(firstStart.value, page.props.locale) }));
</script>

<template>
    <section class="watch-group watch-group--upcoming" :class="{ 'watch-group--expanded': expanded }">
        <!-- Na telefonu přilepená hlavička na jeden řádek, dokud je sekce vidět (R117) -->
        <WatchGroupBar v-if="expanded" :title="t('home.upcoming_title')" :count="summaryText" @collapse="expanded = false" />
        <div class="watch-group__header">
            <h2 class="watch-group__heading">
                <button type="button" class="watch-group__toggle" :aria-expanded="expanded ? 'true' : 'false'" :aria-controls="bodyId" @click="expanded = !expanded">
                    <span class="watch-group__chevron" aria-hidden="true">▸</span>
                    <span class="watch-group__name">{{ t('home.upcoming_title') }}</span>
                    <span class="watch-group__count">{{ summaryText }}</span>
                </button>
            </h2>
        </div>

        <!-- Karty se vykreslí až po rozbalení — sbalená sekce nenačítá obrázky -->
        <div :id="bodyId" class="watch-group__body" :hidden="!expanded">
            <template v-if="expanded">
                <p class="watch-group__hint">{{ t('home.upcoming_hint') }}</p>
                <template v-for="item in items" :key="item.id">
                    <!-- Kotva pro „+1 brzy“ ze sekce Zatím bez akce — fokus sem přesune stránka -->
                    <h3 :id="upcomingAnchor(item.id)" class="watch-group__subtitle" tabindex="-1">{{ item.name }}</h3>
                    <div class="offer-grid">
                        <OfferCard v-for="offer in item.upcoming" :key="offer.id" :offer="offer" :heading-level="4" :watch-item="item">
                            <ShoppingToggle :offer-id="offer.id" />
                            <!-- Akce sem nepatří — skrýt ji nebo podobné (R125) -->
                            <button type="button" class="button button--ghost offer-card__dismiss" @click="showOfferActions(offer, item)">{{ t('offer_actions.dismiss') }}</button>
                        </OfferCard>
                    </div>
                </template>
            </template>
        </div>
    </section>
</template>
