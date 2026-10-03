<!--
    Patička webu (R51) — tradiční tmavá patička ve sloupcích: značka s krátkým popisem,
    navigace, informace (podmínky, zásady, kontakt, nastavení cookies — R52) a provozovatel se sídlem
    po řádcích (IČO je v podmínkách a zásadách, § 435 OZ).
    Dole pruh s copyrightem a upozorněním, že nejde o web obchodů a závazná je cena v obchodě.
    Data sdílí HandleInertiaRequests (siteFooter z config/letaky.php, navigation); chybějící
    údaj provozovatele se vynechá.

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import { openConsentSettings } from '@/lib/consent';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

const footer = computed(() => page.props.siteFooter);

/** Rok do copyrightu. */
const year = new Date().getFullYear();
</script>

<template>
    <footer class="app-footer">
        <div class="app-footer__inner">
            <div class="app-footer__columns">
                <section class="app-footer__brand">
                    <Link href="/" class="app-footer__brand-link">
                        <img src="/images/brand/logo-mark.png" alt="" class="app-footer__logo" />
                        <span class="app-footer__wordmark"
                            ><span class="app-footer__wordmark-first">{{ t('brand.first') }}</span>{{ t('brand.second') }}</span
                        >
                    </Link>
                    <p class="app-footer__about">{{ t('footer.about') }}</p>
                </section>

                <nav class="app-footer__column" :aria-label="t('footer.nav_title')">
                    <h2 class="app-footer__title">{{ t('footer.nav_title') }}</h2>
                    <ul class="app-footer__list">
                        <li v-for="item in page.props.navigation" :key="item.url">
                            <Link :href="item.url" class="app-footer__link">{{ t(item.label) }}</Link>
                        </li>
                        <template v-if="!page.props.auth.user">
                            <li>
                                <Link :href="page.props.auth.registerUrl" class="app-footer__link">{{ t('auth.register.title') }}</Link>
                            </li>
                            <li>
                                <Link :href="page.props.auth.loginUrl" class="app-footer__link">{{ t('auth.login.submit') }}</Link>
                            </li>
                        </template>
                    </ul>
                </nav>

                <nav class="app-footer__column" :aria-label="t('footer.info_title')">
                    <h2 class="app-footer__title">{{ t('footer.info_title') }}</h2>
                    <ul class="app-footer__list">
                        <li>
                            <Link :href="footer.termsUrl" class="app-footer__link">{{ t('footer.terms') }}</Link>
                        </li>
                        <li>
                            <Link :href="footer.privacyUrl" class="app-footer__link">{{ t('footer.privacy') }}</Link>
                        </li>
                        <li>
                            <a :href="`mailto:${footer.email}`" class="app-footer__link">{{ t('footer.contact') }}</a>
                        </li>
                        <li>
                            <button type="button" class="app-footer__link app-footer__link--button" @click="openConsentSettings">{{ t('footer.cookies') }}</button>
                        </li>
                    </ul>
                </nav>

                <section class="app-footer__column">
                    <h2 class="app-footer__title">{{ t('footer.operator_title') }}</h2>
                    <address class="app-footer__address">
                        <span>{{ footer.operator }}</span>
                        <span v-for="line in footer.addressLines" :key="line">{{ line }}</span>
                        <a :href="`mailto:${footer.email}`" class="app-footer__link">{{ footer.email }}</a>
                    </address>
                </section>
            </div>

            <div class="app-footer__bottom">
                <p class="app-footer__copyright">{{ t('footer.copyright', { year }) }}</p>
                <p class="app-footer__disclaimer">{{ t('footer.disclaimer') }}</p>
            </div>
        </div>
    </footer>
</template>
