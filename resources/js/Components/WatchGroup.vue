<!--
    Skupina hlídané položky s akcemi nebo zmínkami v Mých slevách — sbalitelná. Hlavička ukazuje
    souhrn (počet akcí, nejnižší cenu s obchodem — R100, nejvyšší slevu) a akce upravit / přestat
    hlídat; po rozbalení akce a zmínky. Akce, které ještě nezačaly, jsou v sekci Brzy
    (UpcomingSection) — tady jen jejich počet a „Vyplatí se počkat“, když je některá výrazně
    levnější (R76). Položky bez akcí jsou v sekci Zatím bez akce (WaitingSection, R100).
    Vzhled R138: skupina je karta s ikonou oddělení, otevřená má tónované tělo; rada počkat je
    bílá karta se zeleným rámečkem a lístkem budoucí akce, který vede do sekce Brzy.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from "@/Components/ChainLogo.vue";
import DepartmentIcon from "@/Components/DepartmentIcon.vue";
import MentionCard from "@/Components/MentionCard.vue";
import OfferCard from "@/Components/OfferCard.vue";
import OfferRow from "@/Components/OfferRow.vue";
import ShoppingToggle from "@/Components/ShoppingToggle.vue";
import WatchGroupBar from "@/Components/WatchGroupBar.vue";
import WatchItemActions from "@/Components/WatchItemActions.vue";
import { formatDate, formatDiscount, formatPrice } from "@/lib/format";
import { useTranslations } from "@/lib/i18n";
import { discountPercent, unitPriceLabel, upcomingAnchor } from "@/lib/offer";
import { showOfferActions } from "@/lib/offerActions";
import { usePage } from "@inertiajs/vue3";
import { computed, useId } from "vue";

const props = defineProps({
    /**
     * Položka z HomeController (ikona oddělení, akce s cenou pro uživatele, budoucí akce `upcoming`
     * a `waitTip`, zmínky, adresy úprav); po výběru obchodu jen jeho akce, bez budoucích.
     */
    item: { type: Object, required: true },
    /** Akce jako kompaktní řádky místo karet — „Jsem v obchodě“ (R62) nebo volba zobrazení (R82). */
    compact: { type: Boolean, default: false },
    /** U řádku i obchod — mimo „Jsem v obchodě“ jsou v řádcích akce víc obchodů (R82). */
    withChain: { type: Boolean, default: false },
});

/** Lístek budoucí akce v radě počkat: stránka rozbalí sekci Brzy a posune se na položku. */
const emit = defineEmits(["show-upcoming"]);

/** Rozbalená skupina (řídí stránka — pamatuje si stav a umí rozbalit vše). */
const expanded = defineModel("expanded", { type: Boolean, default: false });

const t = useTranslations();
const page = usePage();
const bodyId = useId();
const locale = computed(() => page.props.locale);

/**
 * Akce s nejnižší cenou, kterou uživatel zaplatí (userPrice z HomeController), nebo null — její
 * cena a obchod jsou v hlavičce („od 19,90 Kč“ s logem, R100).
 */
const cheapestOffer = computed(() =>
    props.item.offers
        .filter((offer) => offer.userPrice !== null)
        .reduce(
            (cheapest, offer) =>
                cheapest && cheapest.userPrice <= offer.userPrice
                    ? cheapest
                    : offer,
            null,
        ),
);

/** Počet akcí a nejnižší cena — v hlavičce i v přilepené liště (R117). */
const countText = computed(() =>
    t("home.count", { count: props.item.offers.length }),
);
const lowestPriceText = computed(() =>
    cheapestOffer.value
        ? t("watch.lowest_price", {
              price: formatPrice(cheapestOffer.value.userPrice, locale.value),
          })
        : null,
);

/**
 * „Vyplatí se počkat“ (R76, App\Domain\Matching\WaitAdvice): obchod, od kdy, za kolik a o kolik
 * levněji než nejlevnější akce dnes; null = nevyplatí. Texty lístku i pro čtečku.
 */
const waitTip = computed(() => {
    const tip = props.item.waitTip;
    if (!tip) {
        return null;
    }
    const replacements = {
        chain: tip.chainName,
        date: formatDate(tip.validFrom, locale.value),
        price: formatPrice(tip.userPrice, locale.value),
        unit_price: unitPriceLabel(
            tip.unitPrice,
            tip.unitPriceUnit,
            locale.value,
            t,
        ),
    };

    // Kód obchodu (logo) zůstává v `chain`, název je jen v textu pro čtečku
    return {
        chain: tip.chain,
        savingPercent: tip.savingPercent,
        price: replacements.price,
        unitPrice: replacements.unit_price,
        starts: t("offers.starts_on", { date: replacements.date }),
        text: t("watch.wait_tip_short", { percent: tip.savingPercent }),
        label: t("watch.wait_tip_offer", replacements),
    };
});

/** Nejvyšší sleva mezi akcemi položky, nebo null. */
const bestDiscount = computed(() => {
    const discounts = props.item.offers.map(discountPercent).filter(Boolean);

    return discounts.length ? Math.max(...discounts) : null;
});
</script>

<template>
    <section
        :id="`polozka-${item.id}`"
        class="watch-group"
        :class="{ 'watch-group--expanded': expanded }"
    >
        <!-- Na telefonu přilepená hlavička na jeden řádek, dokud je skupina vidět (R117) -->
        <WatchGroupBar
            v-if="expanded"
            :title="item.name"
            :count="countText"
            :summary="lowestPriceText"
            @collapse="expanded = false"
        />
        <div class="watch-group__header">
            <!-- Oddělení katalogu (R138) — vlastní slova bez produktu mají obecnou ikonu -->
            <span v-if="item.icon" class="watch-group__icon" aria-hidden="true"
                ><DepartmentIcon :name="item.icon"
            /></span>
            <h2 class="watch-group__heading">
                <button
                    type="button"
                    class="watch-group__toggle"
                    :aria-expanded="expanded ? 'true' : 'false'"
                    :aria-controls="bodyId"
                    @click="expanded = !expanded"
                >
                    <!-- Vlevo název a souhrn (smí se zalomit), vpravo sleva a šipka -->
                    <span class="watch-group__main">
                        <span class="watch-group__name">{{ item.name }}</span>
                        <span class="watch-group__count">{{ countText }}</span>
                        <!-- Akce, které ještě nezačaly (R76) — jsou v sekci Brzy -->
                        <span
                            v-if="item.upcoming.length"
                            class="watch-group__upcoming"
                            >{{
                                t("watch.upcoming_count", {
                                    count: item.upcoming.length,
                                })
                            }}</span
                        >
                        <span v-if="waitTip" class="watch-group__wait">{{
                            t("watch.wait_tip")
                        }}</span>
                        <span v-if="cheapestOffer" class="watch-group__summary">
                            {{ lowestPriceText }}
                            <!-- Kde je nejlevněji (R100) — v obchodě je obchod jasný -->
                            <ChainLogo
                                v-if="withChain"
                                :chain="cheapestOffer.chain"
                                class="watch-group__chain"
                            />
                        </span>
                        <span
                            v-if="!item.offers.length && item.mentions.length"
                            class="watch-group__summary"
                        >
                            {{
                                t("watch.mentions_count", {
                                    count: item.mentions.length,
                                })
                            }}
                        </span>
                    </span>
                    <span class="watch-group__end">
                        <span
                            v-if="bestDiscount"
                            class="watch-group__discount"
                            >{{ formatDiscount(bestDiscount) }}</span
                        >
                        <svg
                            class="watch-group__chevron"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </button>
            </h2>
            <WatchItemActions :item="item" />
        </div>

        <!-- Karty se vykreslí až po rozbalení — sbalené skupiny nenačítají obrázky -->
        <div :id="bodyId" class="watch-group__body" :hidden="!expanded">
            <template v-if="expanded">
                <!-- Vyplatí se počkat (R76): věta a lístek budoucí akce — klepnutí vede do sekce Brzy -->
                <div v-if="waitTip" class="wait-tip">
                    <span class="wait-tip__icon" aria-hidden="true">
                        <!-- Přesýpací hodiny -->
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M6 3h12M6 21h12M7 3c0 5 10 5 10 9s-10 4-10 9M17 3c0 5-10 5-10 9s10 4 10 9"
                            />
                        </svg>
                    </span>
                    <p class="wait-tip__text">
                        <strong class="wait-tip__title">{{
                            t("watch.wait_tip")
                        }}</strong>
                        {{ waitTip.text }}
                    </p>
                    <a
                        class="wait-tip__offer"
                        :href="`#${upcomingAnchor(item.id)}`"
                        :aria-label="waitTip.label"
                        @click.prevent="emit('show-upcoming', item.id)"
                    >
                        <ChainLogo :chain="waitTip.chain" aria-hidden="true" />
                        <span class="tag tag--upcoming" aria-hidden="true">{{
                            waitTip.starts
                        }}</span>
                        <strong class="wait-tip__price" aria-hidden="true">{{
                            waitTip.price
                        }}</strong>
                        <span class="wait-tip__unit" aria-hidden="true">{{
                            waitTip.unitPrice
                        }}</span>
                        <span class="wait-tip__save" aria-hidden="true">{{
                            formatDiscount(waitTip.savingPercent)
                        }}</span>
                    </a>
                </div>
                <ul v-if="item.offers.length && compact" class="offer-rows">
                    <OfferRow
                        v-for="offer in item.offers"
                        :key="offer.id"
                        :offer="offer"
                        :with-chain="withChain"
                        :watch-item="item"
                    />
                </ul>
                <div v-else-if="item.offers.length" class="offer-grid">
                    <OfferCard
                        v-for="offer in item.offers"
                        :key="offer.id"
                        :offer="offer"
                        :heading-level="3"
                        :watch-item="item"
                    >
                        <ShoppingToggle :offer-id="offer.id" />
                        <!-- Akce sem nepatří — skrýt ji nebo podobné (R125) -->
                        <button
                            type="button"
                            class="button button--ghost offer-card__dismiss"
                            @click="showOfferActions(offer, item)"
                        >
                            {{ t("offer_actions.dismiss") }}
                        </button>
                    </OfferCard>
                </div>

                <template v-if="item.mentions.length">
                    <h3 class="watch-group__subtitle">
                        {{ t("home.mentions_title") }}
                    </h3>
                    <p class="watch-group__hint">
                        {{ t("home.mentions_hint") }}
                    </p>
                    <div class="mention-grid">
                        <MentionCard
                            v-for="mention in item.mentions"
                            :key="mention.id"
                            :mention="mention"
                        />
                    </div>
                </template>
            </template>
        </div>
    </section>
</template>
