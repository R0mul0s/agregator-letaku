<!--
    Kontakt (R72) — kdo Slevohlídku provozuje (jméno, IČO, sídlo, zápis v živnostenském
    rejstříku), velké odkazy na e-mail a telefon, rozcestník „S čím se ozvat“ s předvyplněným
    předmětem e-mailu a časté otázky (rozbalovací, bez JavaScriptu). Údaje posílá ContactController.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import ContactIcon from '@/Components/ContactIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';

/** Témata rozcestníku v pořadí zobrazení (lang: ui.contact.topics). */
const TOPICS = ['price', 'idea', 'chains', 'privacy', 'security'];

/** Časté otázky v pořadí zobrazení (lang: ui.contact.faq). */
const FAQ = ['free', 'source', 'different', 'chains', 'delete'];

const props = defineProps({
    /** { name, brand, companyId, address, tradeOffice, email, phone, phoneHref } */
    operator: { type: Object, required: true },
    /** Adresy { terms, privacy }. */
    urls: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

/**
 * Odkaz na e-mail s předvyplněným předmětem tématu.
 *
 * @param {string} topic
 * @returns {string}
 */
function mailto(topic) {
    return `mailto:${props.operator.email}?subject=${encodeURIComponent(t(`contact.topics.${topic}.subject`))}`;
}
</script>

<template>
    <AppLayout>
        <Head :title="page.props.seoTitle" />

        <header class="page__header contact-hero">
            <h1 class="page__title">{{ t('contact.title') }}</h1>
            <p class="page__subtitle contact-hero__lead">{{ t('contact.lead') }}</p>
        </header>

        <section class="contact-grid">
            <!-- Rychlý kontakt: velké klepací plochy pro e-mail a telefon -->
            <div class="contact-quick">
                <a :href="`mailto:${operator.email}`" class="card contact-quick__item">
                    <span class="contact-quick__icon"><ContactIcon name="email" /></span>
                    <span class="contact-quick__label">{{ t('contact.email') }}</span>
                    <span class="contact-quick__value">{{ operator.email }}</span>
                </a>
                <a v-if="operator.phoneHref" :href="`tel:${operator.phoneHref}`" class="card contact-quick__item">
                    <span class="contact-quick__icon"><ContactIcon name="phone" /></span>
                    <span class="contact-quick__label">{{ t('contact.phone') }}</span>
                    <span class="contact-quick__value">{{ operator.phone }}</span>
                </a>
                <p class="contact-quick__reply">{{ t('contact.reply') }}</p>
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
            <ul class="contact-topics">
                <li v-for="topic in TOPICS" :key="topic" class="card contact-topic">
                    <span class="contact-topic__icon"><ContactIcon :name="topic" /></span>
                    <h3 class="contact-topic__title">{{ t(`contact.topics.${topic}.title`) }}</h3>
                    <p class="contact-topic__text">{{ t(`contact.topics.${topic}.text`) }}</p>
                    <div class="contact-topic__actions">
                        <a :href="mailto(topic)" class="button button--ghost contact-topic__write">{{ t('contact.write') }}</a>
                        <Link v-if="topic === 'privacy'" :href="urls.privacy" class="link">{{ t('contact.topics.privacy.link') }}</Link>
                    </div>
                </li>
            </ul>
        </section>

        <section class="contact-section" aria-labelledby="contact-faq-title">
            <h2 id="contact-faq-title" class="contact-section__title">{{ t('contact.faq_title') }}</h2>
            <div class="contact-faq">
                <details v-for="item in FAQ" :key="item" class="card contact-faq__item">
                    <summary class="contact-faq__question">{{ t(`contact.faq.${item}.question`) }}</summary>
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
