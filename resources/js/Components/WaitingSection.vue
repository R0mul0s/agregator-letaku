<!--
    Sekce „Zatím bez akce“ v Mých slevách (R100) — hlídané položky, které teď v akci nejsou ani
    v letáku bez ceny. Jsou pod ostatními, aby skupiny s akcemi byly na první obrazovce; sbalitelná
    jako skupiny (výchozí stav a zapamatování řídí stránka). Hlavička ukáže počet a názvy, po
    rozbalení řádky s akcemi upravit / přestat hlídat a jestli o akcích dáme vědět e-mailem.
    Položka s akcemi, které ještě nezačaly, vede klepnutím do sekce Brzy.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import WatchItemActions from '@/Components/WatchItemActions.vue';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

const props = defineProps({
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

/** Rozbalená sekce (řídí stránka — pamatuje si stav). */
const expanded = defineModel('expanded', { type: Boolean, default: false });

const t = useTranslations();
const bodyId = useId();

/** Názvy položek do hlavičky — co tu je, je vidět i ve sbalené sekci. */
const names = computed(() => props.items.map((item) => item.name).join(', '));
</script>

<template>
    <section class="watch-group watch-group--waiting" :class="{ 'watch-group--expanded': expanded }">
        <div class="watch-group__header">
            <h2 class="watch-group__heading">
                <button type="button" class="watch-group__toggle" :aria-expanded="expanded ? 'true' : 'false'" :aria-controls="bodyId" @click="expanded = !expanded">
                    <span class="watch-group__chevron" aria-hidden="true">▸</span>
                    <span class="watch-group__name">{{ t('home.waiting_title') }}</span>
                    <span class="watch-group__count">{{ t('home.waiting_count', { count: items.length }) }}</span>
                    <span class="watch-group__names">{{ names }}</span>
                </button>
            </h2>
        </div>

        <div :id="bodyId" class="watch-group__body" :hidden="!expanded">
            <div class="watch-group__empty">
                <span class="watch-group__empty-icon" aria-hidden="true">
                    <!-- Oko — Slevohlídka hlídá dál -->
                    <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
                </span>
                <p>
                    <span class="watch-group__empty-hint">{{ t('home.waiting_hint') }}</span>
                    <span v-if="digestFrequency" class="watch-group__empty-hint">{{ t('home.no_offers_digest_on', { frequency: digestFrequency }) }}</span>
                    <span v-else class="watch-group__empty-hint">
                        {{ t('home.no_offers_digest_off') }}
                        <Link :href="digestUrl" class="link">{{ t('home.no_offers_digest_link') }}</Link>
                    </span>
                </p>
            </div>
            <ul class="waiting-items">
                <!-- Kotva jako u skupiny — odkaz z Hlídám (#polozka-{id}) vede i sem -->
                <li v-for="item in items" :id="`polozka-${item.id}`" :key="item.id" class="waiting-items__item">
                    <!-- Akce, které ještě nezačaly (R76) jsou v sekci Brzy — klepnutí na název
                         nebo počet ji rozbalí a posune stránku na akce položky -->
                    <button v-if="item.upcoming.length && upcomingShown" type="button" class="waiting-items__name waiting-items__name--link" @click="emit('show-upcoming', item.id)">
                        <span class="waiting-items__label">{{ item.name }}</span>
                        <span class="watch-group__upcoming">{{ t('watch.upcoming_count', { count: item.upcoming.length }) }}</span>
                    </button>
                    <template v-else>
                        <span class="waiting-items__name">{{ item.name }}</span>
                        <span v-if="item.upcoming.length" class="watch-group__upcoming">{{ t('watch.upcoming_count', { count: item.upcoming.length }) }}</span>
                    </template>
                    <WatchItemActions :item="item" />
                </li>
            </ul>
        </div>
    </section>
</template>
