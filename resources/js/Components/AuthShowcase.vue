<!--
    Rámec stránek přihlášení a registrace — vedle formuláře (slot) červený panel se skutečnými
    akcemi (R56): kolik jich právě je, ze kterých obchodů a pár nejvyšších slev jako cenovky.
    Na mobilu je panel nad formulářem zkrácený na nadpis a obchody.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { formatNumber, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { discountPercent } from '@/lib/offer';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Data panelu z FortifyServiceProvider::showcase — { offers, chains, deals }. */
    showcase: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const locale = computed(() => page.props.locale);

/** Cenovky akcí s cenou a slevou (sleva od obchodu, jinak dopočtená z původní ceny). */
const deals = computed(() => props.showcase.deals.map((offer) => ({ ...offer, discount: discountPercent(offer) })));

/** Akce, jejichž obrázek se nenačetl (id). */
const brokenImages = ref(new Set());

/**
 * Zapamatuje si nenačtený obrázek akce.
 *
 * @param {number} id
 */
function markBroken(id) {
    brokenImages.value = new Set([...brokenImages.value, id]);
}
</script>

<template>
    <div class="auth-showcase">
        <aside class="auth-showcase__panel">
            <div class="auth-showcase__brand">
                <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="auth-showcase__mascot" />
                <p>
                    <span class="auth-showcase__name">{{ t('app_name') }}</span>
                    <span class="auth-showcase__tagline">{{ t('brand.tagline') }}</span>
                </p>
            </div>

            <h2 class="auth-showcase__headline">{{ t('auth.showcase.headline') }}</h2>
            <p class="auth-showcase__lead">{{ t('auth.showcase.lead') }}</p>

            <p class="auth-showcase__count">
                <strong class="auth-showcase__count-value">{{ formatNumber(showcase.offers, locale) }}</strong>
                {{ t('landing.stats.offers', { count: showcase.offers }) }}
            </p>
            <ul class="auth-showcase__chains">
                <li v-for="chain in showcase.chains" :key="chain"><ChainLogo :chain="chain" /></li>
            </ul>

            <!-- Cenovky skutečných akcí — obrázek z CDN obchodu (R22), jen ozdoba, text nese karta -->
            <div v-if="deals.length" class="auth-showcase__deals">
                <p class="auth-showcase__deals-title">{{ t('auth.showcase.deals_title') }}</p>
                <ul class="auth-deals">
                    <li v-for="deal in deals" :key="deal.id" class="auth-deal">
                        <!-- Obrázek, který CDN obchodu nevydá, nahradí prázdné bílé pole místo rozbité ikony -->
                        <img
                            v-if="!brokenImages.has(deal.id)"
                            :src="deal.imageUrl"
                            alt=""
                            class="auth-deal__image"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            @error="markBroken(deal.id)"
                        />
                        <span v-else class="auth-deal__image" aria-hidden="true"></span>
                        <div class="auth-deal__body">
                            <p class="auth-deal__name">{{ deal.name }}</p>
                            <p class="auth-deal__meta">
                                <ChainLogo :chain="deal.chain" />
                                <span class="auth-deal__price">{{ formatPrice(deal.price, locale) }}</span>
                            </p>
                        </div>
                        <span v-if="deal.discount" class="auth-deal__sticker">−{{ deal.discount }} %</span>
                    </li>
                </ul>
            </div>
        </aside>
        <div class="auth-showcase__form">
            <slot />
        </div>
    </div>
</template>
