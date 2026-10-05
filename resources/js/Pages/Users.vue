<!--
    Přehled uživatelů pro admina (R84) — souhrn podle aktivity a nastavení (dlaždice filtrují
    seznam), hledání, řazení a karty uživatelů: kdo je teď online a kdy byl kdo naposledy,
    sledované obchody, karty, hlídané položky, e-maily a upozornění v telefonu. Seznam
    a souhrn se každou minutu obnoví, „online“ tak odpovídá skutečnosti.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatRelativeTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Pauza v psaní, po které se načtou výsledky hledání (ms). */
const SEARCH_DEBOUNCE_MS = 300;

/** Jak často se seznam a souhrn obnoví (ms) — kdo je online, se mění. */
const REFRESH_MS = 60_000;

/** Výchozí řazení — do adresy se nepíše. */
const DEFAULT_SORT = 'last_seen';

/** Filtr podle aktivity za N dní (UsersIndexRequest::ACTIVE_PREFIX). */
const ACTIVE_PREFIX = 'aktivni-';

/** Filtry podle nastavení v pořadí souhrnu (UsersIndexRequest::FIXED_FILTERS bez „online“). */
const SETTINGS_FILTERS = ['telefon', 'souhrn', 'novinky', 'neovereni'];

/** E-mailový souhrn vypnutý (App\Enums\DigestFrequency::Off). */
const DIGEST_OFF = 'off';

const props = defineProps({
    indexUrl: { type: String, required: true },
    /** Hledání, filtr a řazení { q, filter, sort }. */
    filters: { type: Object, required: true },
    /** Možnosti řazení (UsersIndexRequest::SORTS). */
    sorts: { type: Array, required: true },
    /** Okna aktivity ve dnech (letaky.users.active_days). */
    activeDays: { type: Array, required: true },
    /** Počty uživatelů { all, online, 'aktivni-7', telefon, … }. */
    counts: { type: Object, required: true },
    /** Za kolik minut od poslední aktivity je uživatel online. */
    onlineMinutes: { type: Number, required: true },
    /** Počet uživatelů odpovídajících hledání a filtru. */
    total: { type: Number, required: true },
    /** Uživatelé načteného rozsahu stránek (UserDirectory::present). */
    users: { type: Array, required: true },
    /** Odkazy stránkování a „Načíst další“ (PaginationLinks, R43). */
    pagination: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

const query = ref(props.filters.q);
const sort = ref(props.filters.sort);
/** Teď (ms) pro relativní čas — posune se s každým obnovením. */
const now = ref(Date.now());
let searchTimer = null;
let refreshTimer = null;

/** Dlaždice aktivity: všichni, online, aktivní za N dní. */
const activityTiles = computed(() => [
    { filter: null, label: t('users.filters.all'), count: props.counts.all },
    { filter: 'online', label: t('users.filters.online'), count: props.counts.online, online: true },
    ...props.activeDays.map((days) => ({
        filter: `${ACTIVE_PREFIX}${days}`,
        label: t('users.filters.active', { count: days }),
        count: props.counts[`${ACTIVE_PREFIX}${days}`],
    })),
]);

/** Dlaždice nastavení. */
const settingsTiles = computed(() => SETTINGS_FILTERS.map((filter) => ({ filter, label: t(`users.filters.${filter}`), count: props.counts[filter] })));

/**
 * Načte seznam s hledáním, filtrem a řazením od první stránky; výchozí hodnoty do adresy nedává.
 *
 * @param {string|null} filter
 */
function reload(filter = props.filters.filter) {
    const params = {
        q: query.value.trim(),
        kdo: filter ?? '',
        razeni: sort.value === DEFAULT_SORT ? '' : sort.value,
    };

    router.get(props.indexUrl, Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '')), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/** Hledání se načte po pauze v psaní. */
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload(), SEARCH_DEBOUNCE_MS);
}

/**
 * Dlaždice souhrnu přepne filtr; klepnutí na zvolený filtr ho zruší.
 *
 * @param {string|null} filter
 */
function toggleFilter(filter) {
    reload(props.filters.filter === filter ? null : filter);
}

/** Zruší hledání i filtr. */
function clearFilters() {
    query.value = '';
    reload(null);
}

/**
 * Kdy byl uživatel naposledy: „Online“, „Naposledy před 3 hodinami“, nebo bez aktivity.
 *
 * @param {object} user
 * @returns {string}
 */
function lastSeenLabel(user) {
    if (user.online) {
        return t('users.online');
    }
    if (!user.lastSeenAt) {
        return t('users.never');
    }

    return t('users.last_seen', { time: formatRelativeTime(user.lastSeenAt, page.props.locale, page.props.timezone, now.value) });
}

/**
 * Datum registrace („čt 2. 10.“) v místním čase.
 *
 * @param {string} isoDateTime
 * @returns {string}
 */
function registeredLabel(isoDateTime) {
    const localDay = new Intl.DateTimeFormat('en-CA', { timeZone: page.props.timezone }).format(new Date(isoDateTime));

    return t('users.registered', { date: formatDate(localDay, page.props.locale) });
}

onMounted(() => {
    refreshTimer = setInterval(() => {
        router.reload({ only: ['users', 'counts', 'total', 'pagination'], onSuccess: () => (now.value = Date.now()) });
    }, REFRESH_MS);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout>
        <Head :title="t('users.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('users.title') }}</h1>
            <p class="page__subtitle">{{ t('users.intro', { minutes: onlineMinutes }) }}</p>
        </header>

        <section class="users-summary">
            <div v-for="group in [{ key: 'activity', tiles: activityTiles }, { key: 'settings', tiles: settingsTiles }]" :key="group.key" class="users-summary__group">
                <h2 class="users-summary__title">{{ t(`users.${group.key}_title`) }}</h2>
                <div class="users-summary__tiles">
                    <button
                        v-for="tile in group.tiles"
                        :key="tile.filter ?? 'all'"
                        type="button"
                        class="users-summary__tile"
                        :class="{ 'users-summary__tile--online': tile.online, 'users-summary__tile--selected': filters.filter === tile.filter }"
                        :aria-pressed="filters.filter === tile.filter ? 'true' : 'false'"
                        @click="toggleFilter(tile.filter)"
                    >
                        <span class="users-summary__value">
                            <span v-if="tile.online" class="presence-dot presence-dot--online presence-dot--pulse" aria-hidden="true"></span>
                            {{ tile.count }}
                        </span>
                        <span class="users-summary__label">{{ tile.label }}</span>
                    </button>
                </div>
            </div>
        </section>

        <div class="catalog-toolbar">
            <div class="form-field catalog-toolbar__search">
                <label for="users-search" class="form-field__label">{{ t('users.search') }}</label>
                <input id="users-search" v-model="query" type="search" class="form-field__input" :placeholder="t('users.search_placeholder')" @input="onSearchInput" />
            </div>
            <div class="form-field">
                <label for="users-sort" class="form-field__label">{{ t('users.sort') }}</label>
                <select id="users-sort" v-model="sort" class="form-field__input" @change="reload()">
                    <option v-for="option in sorts" :key="option" :value="option">{{ t(`users.sorts.${option}`) }}</option>
                </select>
            </div>
            <p class="catalog-toolbar__count" role="status">{{ t('users.count', { count: total }) }}</p>
        </div>

        <EmptyState v-if="!users.length" :text="t('users.empty')">
            <button v-if="filters.filter || filters.q" type="button" class="button" @click="clearFilters">{{ t('users.clear_filter') }}</button>
        </EmptyState>

        <ul v-else class="user-list">
            <li v-for="user in users" :key="user.id" class="card user-card" :class="{ 'user-card--online': user.online }">
                <div class="user-card__head">
                    <span class="user-card__avatar">
                        <UserAvatar :name="user.name" :url="user.avatarUrl" />
                        <span class="presence-dot user-card__presence" :class="{ 'presence-dot--online': user.online }" aria-hidden="true"></span>
                    </span>
                    <div class="user-card__identity">
                        <p class="user-card__name">
                            {{ user.name }}
                            <span v-if="user.isAdmin" class="tag tag--accent">{{ t('users.admin') }}</span>
                            <span v-if="!user.emailVerified" class="tag tag--warning">{{ t('users.unverified') }}</span>
                        </p>
                        <p class="user-card__email">{{ user.email }}</p>
                    </div>
                    <div class="user-card__seen">
                        <span class="user-card__last-seen" :class="{ 'user-card__last-seen--online': user.online }">
                            <time v-if="user.lastSeenAt" :datetime="user.lastSeenAt">{{ lastSeenLabel(user) }}</time>
                            <template v-else>{{ lastSeenLabel(user) }}</template>
                        </span>
                        <span class="user-card__registered">{{ registeredLabel(user.registeredAt) }}</span>
                    </div>
                </div>

                <div class="user-card__chains">
                    <span v-for="followed in user.chains" :key="followed.chain" class="user-card__chain">
                        <ChainLogo :chain="followed.chain" />
                        <span v-if="followed.detail" class="user-card__chain-detail">{{ followed.detail }}</span>
                    </span>
                    <span v-if="!user.chains.length" class="user-card__muted">{{ t('users.no_chains') }}</span>
                </div>

                <ul class="user-card__settings">
                    <li class="tag" :class="{ 'tag--accent': user.watchItems }">{{ t('users.watch_items', { count: user.watchItems }) }}</li>
                    <li v-if="user.shoppingListItems" class="tag">{{ t('users.shopping_list', { count: user.shoppingListItems }) }}</li>
                    <li class="tag" :class="{ 'tag--success': user.digestFrequency !== DIGEST_OFF }">
                        {{ t('users.digest', { frequency: t(`digest_frequency.${user.digestFrequency}`) }) }}
                    </li>
                    <li class="tag" :class="{ 'tag--success': user.pushDevices }">
                        {{ user.pushDevices ? t('users.push', { count: user.pushDevices }) : t('users.no_push') }}
                    </li>
                    <li class="tag" :class="{ 'tag--success': user.marketingConsent }">{{ user.marketingConsent ? t('users.marketing') : t('users.no_marketing') }}</li>
                    <li v-for="program in user.loyaltyPrograms" :key="program" class="tag">{{ program }}</li>
                    <li class="tag">
                        {{ t('users.offers_sort', { sort: t(`offers_sort.${user.offersSort}`) }) }}<template v-if="user.minDiscountPercent">,
                            {{ t('users.min_discount', { percent: user.minDiscountPercent }) }}</template>
                    </li>
                </ul>
            </li>
        </ul>

        <Pagination :pagination="pagination" :total="total" load-more-key="users.load_more" />
    </AppLayout>
</template>
