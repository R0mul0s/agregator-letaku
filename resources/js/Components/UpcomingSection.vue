<!--
    Sekce „Brzy“ v Mých slevách (R76) — akce hlídaných položek, které obchody už zveřejnily,
    ale začnou až v příštích dnech. Sbalitelná jako skupiny hlídaných položek (výchozí stav
    a zapamatování řídí stránka); po rozbalení akce po hlídaných položkách. Vzhled R138: modrý
    pruh přes celou šířku s oblými okraji (modrá jako štítek „Od čt 15. 10.“). Nadpis s počtem
    a šipkou sbalí a rozbalí sekci jako hlavička skupiny, pod akcemi ještě „Sbalit Brzy“;
    „+3 brzy“ a odkaz ve skupině sem stránku posunou (Home.vue showUpcoming, R140).

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
import { computed, nextTick, ref, useId } from 'vue';

const props = defineProps({
    /** Hlídané položky s budoucími akcemi (HomeController, `upcoming` u každé položky). */
    items: { type: Array, required: true },
});

/** Rozbalená sekce (řídí stránka — pamatuje si stav). */
const expanded = defineModel('expanded', { type: Boolean, default: false });

const t = useTranslations();
const page = usePage();
const bodyId = useId();
const root = ref(null);

/** Počet budoucích akcí ve všech položkách. */
const count = computed(() => props.items.reduce((sum, item) => sum + item.upcoming.length, 0));

/** Nejbližší začátek budoucí akce (YYYY-MM-DD — řadí se jako text). */
const firstStart = computed(() =>
    props.items
        .flatMap((item) => item.upcoming.map((offer) => offer.validFrom))
        .sort()
        .at(0),
);

/** „1 akce, první od po 12. 10.“ — v přilepené liště na telefonu (R117). */
const summaryText = computed(() => t('home.upcoming_summary', { count: count.value, date: formatDate(firstStart.value, page.props.locale) }));

/** Sbalí sekci z tlačítka pod akcemi a vrátí stránku na její začátek — jinak by uživatel skončil pod ní. */
async function collapse() {
    expanded.value = false;
    await nextTick();
    root.value?.scrollIntoView({ block: 'start', behavior: 'instant' });
}
</script>

<template>
    <section ref="root" class="home-upcoming" :class="{ 'home-upcoming--expanded': expanded }">
        <!-- Na telefonu přilepená hlavička na jeden řádek, dokud je sekce vidět (R117) -->
        <WatchGroupBar v-if="expanded" :title="t('home.upcoming_title')" :count="summaryText" @collapse="expanded = false" />
        <div class="page__wrap">
            <!-- Třída hlavičky skupiny — podle ní se přilepená lišta ukáže (WatchGroupBar) -->
            <div class="watch-group__header home-upcoming__head">
                <p class="home-upcoming__eyebrow">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                    {{ t('home.upcoming_title') }}
                </p>
                <!-- Nadpis sbalí a rozbalí sekci jako hlavička skupiny (R140) -->
                <h2 class="home-upcoming__title">
                    <button type="button" class="home-upcoming__title-toggle" :aria-expanded="expanded ? 'true' : 'false'" :aria-controls="bodyId" @click="expanded = !expanded">
                        {{ t('home.upcoming_heading', { count }) }}
                        <svg class="home-upcoming__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                </h2>
                <p class="home-upcoming__lead">{{ t('home.upcoming_hint') }} {{ t('home.upcoming_first', { date: formatDate(firstStart, page.props.locale) }) }}</p>
                <!-- Sbalená: výrazná výzva k rozbalení — nadpis jako tlačítko není na první pohled poznat -->
                <button v-if="!expanded" type="button" class="home-upcoming__toggle" :aria-controls="bodyId" aria-expanded="false" @click="expanded = true">
                    {{ t('home.upcoming_show', { count }) }}
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                </button>
            </div>

            <!-- Karty se vykreslí až po rozbalení — sbalená sekce nenačítá obrázky -->
            <div :id="bodyId" class="home-upcoming__body" :hidden="!expanded">
                <template v-if="expanded">
                    <template v-for="item in items" :key="item.id">
                        <!-- Kotva pro „+3 brzy“ a radu „Vyplatí se počkat“ — fokus sem přesune stránka -->
                        <h3 :id="upcomingAnchor(item.id)" class="watch-group__subtitle" tabindex="-1">{{ item.name }}</h3>
                        <div class="offer-grid">
                            <OfferCard v-for="offer in item.upcoming" :key="offer.id" :offer="offer" :heading-level="4" :watch-item="item">
                                <ShoppingToggle :offer-id="offer.id" />
                                <!-- Akce sem nepatří — skrýt ji nebo podobné (R125) -->
                                <button type="button" class="button button--ghost offer-card__dismiss" @click="showOfferActions(offer, item)">{{ t('offer_actions.dismiss') }}</button>
                            </OfferCard>
                        </div>
                    </template>
                    <!-- Po prohlédnutí akcí sbalit bez posouvání zpět nahoru; stránka se vrátí na začátek sekce -->
                    <button type="button" class="home-upcoming__toggle home-upcoming__collapse" @click="collapse">
                        {{ t('home.upcoming_hide') }}
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6" /></svg>
                    </button>
                </template>
            </div>
        </div>
    </section>
</template>
