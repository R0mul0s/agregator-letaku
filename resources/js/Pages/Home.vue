<!--
    Moje slevy — úvodní pruh s maskotem a souhrnem, akce k hlídaným položkám ve sledovaných
    obchodech (R18, R19) a zmínky v letácích bez ceny (R27). Skupiny jsou sbalené, rozbalené
    si prohlížeč pamatuje (R43).

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import WatchGroup from '@/Components/WatchGroup.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

/** Klíč v localStorage s rozbalenými položkami — jen pohodlí prohlížeče, ne nastavení účtu. */
const EXPANDED_STORAGE_KEY = 'slevohlidka.home.expanded';

/** Kotva skupiny v adrese (odkaz z dlaždice v Hlídám): #polozka-{id}. */
const GROUP_HASH_PATTERN = /^#polozka-(\d+)$/;

const props = defineProps({
    hasFollowedChains: { type: Boolean, required: true },
    urls: { type: Object, required: true },
    /** Předvolby uživatele { sortLabel, minDiscountPercent } (R41). */
    offersPreferences: { type: Object, required: true },
    /** Hlídané položky s nabídkami od nejnižší ceny za jednotku a zmínkami v letácích (App\Domain\Matching\MyOffers). */
    watchItems: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Křestní jméno do pozdravu (první slovo jména účtu). */
const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? '');

/** Souhrn do úvodního pruhu: počet akcí a nejvyšší sleva napříč hlídanými položkami. */
const summary = computed(() => {
    const offers = props.watchItems.flatMap((item) => item.offers);
    const discounts = offers.map(discountPercent).filter(Boolean);

    return { offers: offers.length, bestDiscount: discounts.length ? Math.max(...discounts) : null };
});

/** Rozbalené skupiny (id položek); ve výchozím stavu je vše sbalené. */
const expandedIds = ref(new Set());

const allExpanded = computed(() => props.watchItems.every((item) => expandedIds.value.has(item.id)));

/** Uloží rozbalené skupiny; bez přístupu k localStorage (anonymní okno) se stav jen nezapamatuje. */
function saveExpanded() {
    try {
        localStorage.setItem(EXPANDED_STORAGE_KEY, JSON.stringify([...expandedIds.value]));
    } catch {
        // stav platí jen do zavření stránky
    }
}

/**
 * Rozbalí nebo sbalí jednu skupinu.
 *
 * @param {number} id
 * @param {boolean} value
 */
function setExpanded(id, value) {
    const next = new Set(expandedIds.value);
    if (value) {
        next.add(id);
    } else {
        next.delete(id);
    }
    expandedIds.value = next;
    saveExpanded();
}

/** Rozbalí všechny skupiny, nebo — když už jsou všechny rozbalené — všechny sbalí. */
function toggleAll() {
    expandedIds.value = allExpanded.value ? new Set() : new Set(props.watchItems.map((item) => item.id));
    saveExpanded();
}

onMounted(async () => {
    try {
        expandedIds.value = new Set(JSON.parse(localStorage.getItem(EXPANDED_STORAGE_KEY) ?? '[]'));
    } catch {
        expandedIds.value = new Set();
    }

    // Odkaz z Hlídám vede na konkrétní skupinu — rozbalit ji a posunout se k ní
    const match = window.location.hash.match(GROUP_HASH_PATTERN);
    if (match) {
        setExpanded(Number(match[1]), true);
        await nextTick();
        document.getElementById(`polozka-${match[1]}`)?.scrollIntoView({ block: 'start' });
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="t('home.title')" />

        <!-- Úvodní pruh: maskot, pozdrav a co Slevohlídka právě ulovila -->
        <section class="home-hero">
            <img src="/images/brand/icon-192.png" alt="" class="home-hero__mascot" />
            <div class="home-hero__body">
                <h1 class="home-hero__title">{{ firstName ? t('home.hello', { name: firstName }) : t('home.title') }}</h1>
                <p class="home-hero__text">{{ t('home.hero_text') }}</p>
                <p v-if="watchItems.length" class="home-hero__preferences">
                    {{ t('home.sorted_by', { sort: offersPreferences.sortLabel }) }}<template v-if="offersPreferences.minDiscountPercent"
                        >, {{ t('home.min_discount_note', { percent: offersPreferences.minDiscountPercent }) }}</template
                    >
                    · <Link :href="urls.offersPreferences" class="link">{{ t('home.change_preferences') }}</Link>
                </p>
                <ul v-if="watchItems.length" class="home-hero__stats">
                    <li class="home-hero__stat">
                        <strong class="home-hero__stat-value">{{ watchItems.length }}</strong>
                        {{ t('home.stat_items', { count: watchItems.length }) }}
                    </li>
                    <li class="home-hero__stat">
                        <strong class="home-hero__stat-value">{{ summary.offers }}</strong>
                        {{ t('home.stat_offers', { count: summary.offers }) }}
                    </li>
                    <li v-if="summary.bestDiscount" class="home-hero__stat home-hero__stat--accent">
                        <strong class="home-hero__stat-value">−{{ summary.bestDiscount }} %</strong>
                        {{ t('home.stat_best') }}
                    </li>
                </ul>
            </div>
            <Link v-if="watchItems.length" :href="urls.watchItems" class="button button--primary home-hero__action">{{ t('home.edit_watch_items') }}</Link>
        </section>

        <EmptyState v-if="!hasFollowedChains" :text="t('home.no_chains')">
            <Link :href="urls.preferences" class="button button--primary">{{ t('home.no_chains_link') }}</Link>
        </EmptyState>
        <EmptyState v-else-if="!watchItems.length" :text="t('home.no_watch_items')">
            <Link :href="urls.watchItems" class="button button--primary">{{ t('home.no_watch_items_link') }}</Link>
        </EmptyState>

        <template v-else>
            <div class="watch-groups__toolbar">
                <button type="button" class="button button--ghost" @click="toggleAll">
                    {{ allExpanded ? t('home.collapse_all') : t('home.expand_all') }}
                </button>
            </div>
            <WatchGroup
                v-for="item in watchItems"
                :key="item.id"
                :item="item"
                :expanded="expandedIds.has(item.id)"
                @update:expanded="(value) => setExpanded(item.id, value)"
            />
        </template>
    </AppLayout>
</template>
