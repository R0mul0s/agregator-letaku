<!--
    Kontakt (R72) — kdo Slevohlídku provozuje (jméno, IČO, sídlo, zápis v živnostenském
    rejstříku), velké odkazy na e-mail a telefon, rozcestník „S čím se ozvat“ a časté otázky
    (rozbalovací, bez JavaScriptu). Údaje posílá ContactController.

    Hravě (R91): hlavní pruh s maskotem a cenovkou „Do 2 dnů“, holub z úvodní věty přeletí
    přes stránku, e-mail a telefon jdou zkopírovat a rozcestník je výběr tématu s osnovou
    e-mailu (předmět i text se předvyplní, osnovu jde i zkopírovat).

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import ContactIcon from '@/Components/ContactIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copyText } from '@/lib/clipboard';
import { useTranslations } from '@/lib/i18n';
import { showToast } from '@/lib/toast';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

/** Témata rozcestníku v pořadí zobrazení (lang: ui.contact.topics). */
const TOPICS = ['price', 'idea', 'chains', 'privacy', 'security'];

/** Časté otázky v pořadí zobrazení (lang: ui.contact.faq). */
const FAQ = ['free', 'source', 'upcoming', 'cards', 'stores', 'app', 'different', 'chains', 'delete'];

/** Konec řádku v těle e-mailu (mailto podle RFC 6068). */
const MAIL_LINE_BREAK = '\r\n';

const props = defineProps({
    /** { name, brand, companyId, address, tradeOffice, email, phone, phoneHref } */
    operator: { type: Object, required: true },
    /** Adresy { terms, privacy }. */
    urls: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

const activeTopic = ref(TOPICS[0]);
const tabs = ref([]);

/** Osnova e-mailu vybraného tématu (řádky „Obchod:“, „Cena u vás:“…). */
const template = computed(() => t(`contact.topics.${activeTopic.value}.template`));

/** Odkaz na e-mail s předvyplněným předmětem a osnovou vybraného tématu. */
const mailto = computed(() => {
    const subject = encodeURIComponent(t(`contact.topics.${activeTopic.value}.subject`));
    const body = encodeURIComponent(`${template.value}${MAIL_LINE_BREAK}`.replaceAll('\n', MAIL_LINE_BREAK));

    return `mailto:${props.operator.email}?subject=${subject}&body=${body}`;
});

/** Počítadlo letů holuba — každé klepnutí vypustí nového (klíč prvku). */
const pigeonFlights = ref(0);
const pigeonFlying = ref(false);

/**
 * Šipky, Home a End přepínají téma (vzor záložek WAI-ARIA) a přesunou na něj fokus.
 *
 * @param {KeyboardEvent} event
 * @param {number} index Pořadí tématu, na kterém je fokus
 */
async function onTabKeydown(event, index) {
    const moves = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: TOPICS.length - 1 };
    if (!(event.key in moves)) {
        return;
    }
    event.preventDefault();
    const next = (moves[event.key] + TOPICS.length) % TOPICS.length;
    activeTopic.value = TOPICS[next];
    await nextTick();
    tabs.value[next]?.focus();
}

/** Holub vyletí přes stránku a po přistání (nebo hned, s omezeným pohybem) toast zažertuje. */
function sendPigeon() {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        showToast(t('contact.pigeon_lost'));

        return;
    }
    pigeonFlights.value++;
    pigeonFlying.value = true;
}

/** Holub doletěl za okraj obrazovky. */
function pigeonLanded() {
    pigeonFlying.value = false;
    showToast(t('contact.pigeon_lost'));
}
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <section class="contact-hero">
            <div class="contact-hero__body">
                <h1 class="page__title contact-hero__title">{{ t('contact.title') }}</h1>
                <p class="contact-hero__lead">
                    {{ t('contact.lead_before') }}
                    <button type="button" class="link-button contact-hero__pigeon" @click="sendPigeon">{{ t('contact.pigeon') }}</button>{{ t('contact.lead_after') }}
                </p>
                <p class="contact-hero__reply">{{ t('contact.reply') }}</p>
            </div>
            <!-- Maskot s cenovkou, jak rychle odpovídáme -->
            <div class="contact-hero__art" aria-hidden="true">
                <div class="contact-hero__circle">
                    <img src="/images/brand/icon-512.png" alt="" class="contact-hero__mascot" />
                </div>
                <span class="contact-hero__sticker">{{ t('contact.reply_sticker') }}</span>
            </div>
        </section>

        <!-- Holub z úvodní věty — letí přes obrazovku zleva doprava a mává křídly -->
        <span v-if="pigeonFlying" :key="pigeonFlights" class="contact-pigeon" aria-hidden="true" @animationend.self="pigeonLanded">
            <svg class="contact-pigeon__bird" viewBox="0 0 64 48">
                <path class="contact-pigeon__tail" d="M6 28 0 22l2 12z" />
                <ellipse class="contact-pigeon__body" cx="26" cy="30" rx="20" ry="11" />
                <path class="contact-pigeon__neck" d="M38 22c3-5 8-7 12-5l-3 12z" />
                <circle class="contact-pigeon__body" cx="50" cy="17" r="7" />
                <circle class="contact-pigeon__eye" cx="52" cy="15" r="1.5" />
                <path class="contact-pigeon__beak" d="m56 17 7 2-7 2z" />
                <rect class="contact-pigeon__letter" x="55" y="20" width="9" height="6" rx="1" />
                <path class="contact-pigeon__wing" d="M16 26c6-14 18-22 26-20-4 10-12 18-26 20z" />
            </svg>
        </span>

        <section class="contact-grid">
            <!-- Rychlý kontakt: velké klepací plochy pro e-mail a telefon, vedle zkopírování -->
            <div class="contact-quick">
                <div class="card contact-quick__item">
                    <span class="contact-quick__icon"><ContactIcon name="email" /></span>
                    <span class="contact-quick__label">{{ t('contact.email') }}</span>
                    <a :href="`mailto:${operator.email}`" class="contact-quick__value">{{ operator.email }}</a>
                    <button
                        type="button"
                        class="button button--ghost contact-quick__copy"
                        :aria-label="t('contact.copy_email')"
                        @click="copyText(operator.email, t('contact.copied_email'), t('contact.copy_failed'))"
                    >
                        {{ t('contact.copy') }}
                    </button>
                </div>
                <div v-if="operator.phoneHref" class="card contact-quick__item">
                    <span class="contact-quick__icon"><ContactIcon name="phone" /></span>
                    <span class="contact-quick__label">{{ t('contact.phone') }}</span>
                    <a :href="`tel:${operator.phoneHref}`" class="contact-quick__value">{{ operator.phone }}</a>
                    <button
                        type="button"
                        class="button button--ghost contact-quick__copy"
                        :aria-label="t('contact.copy_phone')"
                        @click="copyText(operator.phone, t('contact.copied_phone'), t('contact.copy_failed'))"
                    >
                        {{ t('contact.copy') }}
                    </button>
                </div>
            </div>

            <section class="card contact-operator" aria-labelledby="contact-operator-title">
                <h2 id="contact-operator-title" class="contact-operator__title">
                    <ContactIcon name="place" />
                    {{ t('contact.operator_title') }}
                </h2>
                <address class="contact-operator__address">
                    <strong class="contact-operator__name">{{ operator.name }}</strong>
                    <span v-if="operator.brand" class="contact-operator__brand">{{ operator.brand }}</span>
                    <span>{{ t('contact.company_id', { id: operator.companyId }) }}</span>
                    <span v-if="operator.address">{{ t('contact.registered_office', { address: operator.address }) }}</span>
                    <span v-if="operator.tradeOffice" class="contact-operator__register">{{ t('contact.trade_register', { office: operator.tradeOffice }) }}</span>
                </address>
            </section>
        </section>

        <section class="contact-section" aria-labelledby="contact-topics-title">
            <h2 id="contact-topics-title" class="contact-section__title">{{ t('contact.topics_title') }}</h2>
            <div class="contact-topics" role="tablist" :aria-label="t('contact.topics_label')">
                <button
                    v-for="(topic, index) in TOPICS"
                    :id="`contact-tab-${topic}`"
                    :key="topic"
                    ref="tabs"
                    type="button"
                    role="tab"
                    class="contact-topics__tab"
                    :aria-selected="activeTopic === topic ? 'true' : 'false'"
                    aria-controls="contact-topic-panel"
                    :tabindex="activeTopic === topic ? 0 : -1"
                    @click="activeTopic = topic"
                    @keydown="onTabKeydown($event, index)"
                >
                    <ContactIcon :name="topic" />
                    {{ t(`contact.topics.${topic}.title`) }}
                </button>
            </div>

            <div id="contact-topic-panel" class="card contact-panel" role="tabpanel" :aria-labelledby="`contact-tab-${activeTopic}`">
                <Transition name="contact-panel-swap" mode="out-in">
                    <div :key="activeTopic" class="contact-panel__content">
                        <div class="contact-panel__intro">
                            <span class="contact-panel__icon"><ContactIcon :name="activeTopic" /></span>
                            <div>
                                <h3 class="contact-panel__title">{{ t(`contact.topics.${activeTopic}.title`) }}</h3>
                                <p class="contact-panel__text">{{ t(`contact.topics.${activeTopic}.text`) }}</p>
                                <Link v-if="activeTopic === 'privacy'" :href="urls.privacy" class="link contact-panel__link">{{ t('contact.topics.privacy.link') }}</Link>
                            </div>
                        </div>
                        <div class="contact-panel__letter">
                            <p class="contact-panel__letter-label">{{ t('contact.template_label') }}</p>
                            <p class="contact-panel__subject">{{ t(`contact.topics.${activeTopic}.subject`) }}</p>
                            <p class="contact-panel__template">{{ template }}</p>
                        </div>
                        <div class="contact-panel__actions">
                            <a :href="mailto" class="button button--primary">{{ t('contact.write') }}</a>
                            <button
                                type="button"
                                class="button button--ghost"
                                @click="copyText(template, t('contact.copied_template', { email: operator.email }), t('contact.copy_failed'))"
                            >
                                {{ t('contact.copy_template') }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </section>

        <section class="contact-section" aria-labelledby="contact-faq-title">
            <h2 id="contact-faq-title" class="contact-section__title">{{ t('contact.faq_title') }}</h2>
            <div class="contact-faq">
                <details v-for="item in FAQ" :key="item" class="card contact-faq__item">
                    <summary class="contact-faq__question">
                        <span class="contact-faq__mark" aria-hidden="true">?</span>
                        <span class="contact-faq__text">{{ t(`contact.faq.${item}.question`) }}</span>
                    </summary>
                    <p class="contact-faq__answer">{{ t(`contact.faq.${item}.answer`) }}</p>
                </details>
            </div>
            <p class="contact-documents">
                {{ t('contact.documents') }}:
                <Link :href="urls.terms" class="link">{{ t('footer.terms') }}</Link>
                ·
                <Link :href="urls.privacy" class="link">{{ t('footer.privacy') }}</Link>
            </p>
        </section>
    </AppLayout>
</template>
