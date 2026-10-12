<!--
    Sekce „Zatím bez akce“ v Mých slevách (R100) — hlídané položky, které teď v akci nejsou ani
    v letáku bez ceny. Jsou pod ostatními, aby skupiny s akcemi byly na první obrazovce. Položka
    s akcemi, které ještě nezačaly, vede klepnutím do sekce Brzy. Vzhled R138: klidná sekce na
    konci stránky — štítek s okem, nadpis s počtem, vysvětlení a jestli o akcích dáme vědět
    e-mailem; položky jako dlaždice s ikonou oddělení, „Hlídáme dál“ a akcemi upravit / přestat
    hlídat. Dlaždice jsou malé, sekce se proto nesbaluje.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import DepartmentIcon from '@/Components/DepartmentIcon.vue';
import WatchItemActions from '@/Components/WatchItemActions.vue';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';

defineProps({
    /** Hlídané položky bez akcí a zmínek (HomeController); `upcoming` = akce, které ještě nezačaly. */
    items: { type: Array, required: true },
    /** Jak často chodí e-mailový souhrn („denně“), null = vypnutý (R42). */
    digestFrequency: { type: String, default: null },
    /** Nastavení souhrnu v účtu. */
    digestUrl: { type: String, required: true },
    /** Je na stránce sekce Brzy? Ve výběru obchodu ne — „+1 brzy“ pak nikam nevede. */
    upcomingShown: { type: Boolean, default: true },
});

/** „+1 brzy“ u položky: stránka rozbalí sekci Brzy a posune se na položku (ID hlídané položky). */
const emit = defineEmits(['show-upcoming']);

const t = useTranslations();
</script>

<template>
    <section class="home-waiting">
        <p class="home-waiting__eyebrow">
            <!-- Oko — Slevohlídka hlídá dál -->
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
            {{ t('home.waiting_title') }}
        </p>
        <h2 class="home-waiting__title">{{ t('home.waiting_heading', { count: items.length }) }}</h2>
        <p class="home-waiting__lead">
            {{ t('home.waiting_hint') }}
            <template v-if="digestFrequency">{{ t('home.no_offers_digest_on', { frequency: digestFrequency }) }}</template>
            <template v-else>
                {{ t('home.no_offers_digest_off') }}
                <Link :href="digestUrl" class="link">{{ t('home.no_offers_digest_link') }}</Link>
            </template>
        </p>
        <ul class="waiting-items">
            <!-- Kotva jako u skupiny — odkaz z Hlídám (#polozka-{id}) vede i sem -->
            <li v-for="item in items" :id="`polozka-${item.id}`" :key="item.id" class="waiting-items__item">
                <span v-if="item.icon" class="waiting-items__icon" aria-hidden="true"><DepartmentIcon :name="item.icon" /></span>
                <span class="waiting-items__body">
                    <span class="waiting-items__name">{{ item.name }}</span>
                    <!-- Akce, které ještě nezačaly (R76) jsou v sekci Brzy — klepnutí ji rozbalí
                         a posune stránku na akce položky -->
                    <button v-if="item.upcoming.length && upcomingShown" type="button" class="waiting-items__upcoming" @click="emit('show-upcoming', item.id)">
                        {{ t('watch.upcoming_count', { count: item.upcoming.length }) }}
                    </button>
                    <span v-else-if="item.upcoming.length" class="watch-group__upcoming">{{ t('watch.upcoming_count', { count: item.upcoming.length }) }}</span>
                    <span v-else class="waiting-items__status">{{ t('home.waiting_status') }}</span>
                </span>
                <WatchItemActions :item="item" />
            </li>
        </ul>
    </section>
</template>
