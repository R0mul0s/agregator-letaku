<!--
    Patička webu (R51) — tmavá patička ve sloupcích: značka s krátkým popisem, navigace,
    informace (podmínky, zásady, kontakt, nastavení cookies — R52) a obchody, jejichž letáky
    hlídáme (loga s odkazy na jejich akce, R92). Údaje provozovatele jsou na /kontakt
    a v podmínkách (§ 435 OZ), patička na ně odkazuje.
    Pod popisem čerstvost akcí („Akce aktualizované před 2 hodinami“, R92) a nepřihlášený
    má „Začít zdarma“. Dole pruh s copyrightem a upozorněním, že nejde o web obchodů
    a závazná je cena v obchodě. Data sdílí HandleInertiaRequests (siteFooter, navigation).

    @author Roman Hlaváček
    @created 2026-10-03
-->
<script setup>
import ChainLogo from '@/Components/ChainLogo.vue';
import { openConsentSettings } from '@/lib/consent';
import { formatRelativeTime } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

const footer = computed(() => page.props.siteFooter);

/** Rok do copyrightu. */
const year = new Date().getFullYear();

/** Jak dávno se akce naposledy stáhly („před 2 hodinami“); null = ještě nikdy. */
const updatedAgo = computed(() => (footer.value.lastImportAt ? formatRelativeTime(footer.value.lastImportAt, page.props.locale, page.props.timezone) : null));
</script>

<template>
    <footer class="app-footer">
        <div class="app-footer__inner">
            <div class="app-footer__columns">
                <section class="app-footer__brand">
                    <Link href="/" class="app-footer__brand-link">
                        <img src="/images/brand/logo-mark-128.webp" width="128" height="128" alt="" class="app-footer__logo" />
                        <span class="app-footer__wordmark"
                            ><span class="app-footer__wordmark-first">{{ t('brand.first') }}</span>{{ t('brand.second') }}</span
                        >
                    </Link>
                    <p class="app-footer__about">{{ t('footer.about') }}</p>
                    <p v-if="updatedAgo" class="app-footer__fresh">
                        <span class="app-footer__fresh-dot" aria-hidden="true"></span>
                        {{ t('footer.updated', { when: updatedAgo }) }}
                    </p>
                    <Link v-if="!page.props.auth.user" :href="page.props.auth.registerUrl" class="button button--primary app-footer__cta">{{ t('footer.start') }}</Link>
                </section>

                <nav class="app-footer__column" :aria-label="t('footer.nav_title')">
                    <h2 class="app-footer__title">{{ t('footer.nav_title') }}</h2>
                    <ul class="app-footer__list">
                        <li v-for="item in page.props.navigation" :key="item.url">
                            <Link :href="item.url" class="app-footer__link">{{ t(item.label) }}</Link>
                        </li>
                        <!-- Nejlepší slevy týdne (R128): nepřihlášený je má v navigaci, přihlášený jen tady -->
                        <li v-if="page.props.auth.user">
                            <Link :href="footer.weeklyUrl" class="app-footer__link">{{ t('footer.weekly') }}</Link>
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
                            <Link :href="footer.contactUrl" class="app-footer__link">{{ t('footer.contact') }}</Link>
                        </li>
                        <li>
                            <button type="button" class="app-footer__link app-footer__link--button" @click="openConsentSettings">{{ t('footer.cookies') }}</button>
                        </li>
                    </ul>
                </nav>

                <nav v-if="footer.chains.length" class="app-footer__column" :aria-label="t('footer.chains_title')">
                    <h2 class="app-footer__title">{{ t('footer.chains_title') }}</h2>
                    <ul class="app-footer__chains">
                        <li v-for="item in footer.chains" :key="item.chain">
                            <Link :href="item.url" class="app-footer__chain">
                                <ChainLogo :chain="item.chain" large />
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="app-footer__bottom">
                <p class="app-footer__copyright">
                    {{ t('footer.copyright', { year }) }}
                    <span class="app-footer__made">{{ t('footer.made') }}</span>
                </p>
                <p class="app-footer__disclaimer">{{ t('footer.disclaimer') }}</p>
            </div>
        </div>
    </footer>
</template>
