<!--
    Výzva k přidání Slevohlídky na plochu telefonu (R66) — jen přihlášeným, až má aplikace smysl,
    ne hned při příchodu. Android a Chrome mají dialog prohlížeče (beforeinstallprompt), iPhone
    jen ruční postup přes Sdílet. Vzhled a chování R139: bílá karta se třemi výhodami, žlutým
    tlačítkem (na iPhonu dva kroky) a ilustrací telefonu s upozorněním; na hlavních stránkách
    přihlášeného (PAGES) a po „Teď ne“ se vrací za čím dál delší dobu
    (letaky.pwa.install_prompt_snooze_days). Stránky s oddíly přes celou šířku (Moje slevy) si ji
    vkládají samy na své místo (prop `inline`), jinak ji dává AppLayout nad obsah.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { installState, isInstallPromptSnoozed, isIos, promptInstall, snoozeInstallPrompt } from '@/lib/pwaInstall';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

/** Stránky přihlášeného, na kterých se výzva ukazuje (účet, upozornění a admin ne). */
const PAGES = ['Home', 'WatchItems', 'ShoppingList', 'Preferences', 'Offers', 'Weekly'];

/** Stránky, které si výzvu vkládají samy (oddíly přes celou šířku) — AppLayout ji tam nedává. */
const INLINE_PAGES = ['Home', 'Offers'];

/** Výhody na kartě (lang: ui.pwa.install_benefits) a jejich ikony. */
const BENEFITS = ['tap', 'offline', 'notify'];

/** Políčka plochy v ilustraci telefonu; naše ikona je šesté. */
const PHONE_APPS = 16;
const OUR_APP_INDEX = 5;

const props = defineProps({
    /** Vložená stránkou na vlastní místo (Moje slevy), ne nad obsah v AppLayout. */
    inline: { type: Boolean, default: false },
});

const t = useTranslations();
const page = usePage();

/** Zjišťuje se až v prohlížeči (localStorage, systém). */
const snoozed = ref(true);
const ios = ref(false);

onMounted(() => {
    snoozed.value = isInstallPromptSnoozed();
    ios.value = isIos();
});

/** Na stránce, kde ji vkládá stránka sama, ji AppLayout vynechá — a naopak. */
const placedHere = computed(() => INLINE_PAGES.includes(page.component) === props.inline);

const visible = computed(
    () =>
        placedHere.value &&
        page.props.auth.user &&
        PAGES.includes(page.component) &&
        !snoozed.value &&
        !installState.standalone &&
        (installState.promptEvent || ios.value),
);

/** Dialog prohlížeče; po přidání se výzva už neukáže (aplikace poběží z plochy). */
async function install() {
    if (await promptInstall()) {
        snoozed.value = true;
    }
}

/** „Teď ne“ a křížek — výzva se schová, při každém dalším zavření na déle (R139). */
function dismiss() {
    snoozeInstallPrompt();
    snoozed.value = true;
}
</script>

<template>
    <aside v-if="visible" class="install-prompt" :aria-label="t('pwa.install_title')">
        <button type="button" class="install-prompt__close" :aria-label="t('pwa.install_close')" @click="dismiss">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
        <div class="install-prompt__body">
            <img src="/images/brand/icon-192.png" width="192" height="192" alt="" class="install-prompt__icon" />
            <p class="install-prompt__eyebrow">{{ t('pwa.install_eyebrow') }}</p>
            <h2 class="install-prompt__title">{{ t('pwa.install_heading') }}</h2>
            <ul class="install-prompt__benefits">
                <li v-for="benefit in BENEFITS" :key="benefit" class="install-prompt__benefit">
                    <span class="install-prompt__benefit-icon" aria-hidden="true">
                        <!-- Telefon -->
                        <svg v-if="benefit === 'tap'" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2.5" /><path d="M11 18h2" /></svg>
                        <!-- Přeškrtnutý signál -->
                        <svg v-else-if="benefit === 'offline'" viewBox="0 0 24 24"><path d="M2 8.8a15 15 0 0 1 20 0M5 12.6a10 10 0 0 1 14 0M8.5 16.4a5 5 0 0 1 7 0M12 20h.01M3 3l18 18" /></svg>
                        <!-- Zvonek -->
                        <svg v-else viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4" /></svg>
                    </span>
                    <span>
                        <strong class="install-prompt__benefit-title">{{ t(`pwa.install_benefits.${benefit}.title`) }}</strong>
                        <span class="install-prompt__benefit-text">{{ t(`pwa.install_benefits.${benefit}.text`) }}</span>
                    </span>
                </li>
            </ul>
            <div v-if="installState.promptEvent" class="install-prompt__actions">
                <button type="button" class="button button--highlight" @click="install">{{ t('pwa.install') }}</button>
                <button type="button" class="install-prompt__later" @click="dismiss">{{ t('pwa.install_later') }}</button>
            </div>
            <!-- iPhone: přidat jde jen ručně přes Sdílet v Safari -->
            <template v-else>
                <ol class="install-prompt__steps">
                    <li class="install-prompt__step">
                        {{ t('pwa.install_ios_share') }}
                        <svg class="install-prompt__step-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4M5 12v8h14v-8" /></svg>
                    </li>
                    <li class="install-prompt__step">
                        {{ t('pwa.install_ios_add') }}
                        <svg class="install-prompt__step-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3" /><path d="M12 8v8M8 12h8" /></svg>
                    </li>
                </ol>
                <button type="button" class="install-prompt__later" @click="dismiss">{{ t('pwa.install_later') }}</button>
            </template>
        </div>

        <!-- Ilustrace: telefon s plochou, naše ikona se nadzvedává, nahoře upozornění (od středního displeje) -->
        <div class="install-prompt__art" aria-hidden="true">
            <div class="install-prompt__phone">
                <span class="install-prompt__notch"></span>
                <div class="install-prompt__screen">
                    <div class="install-prompt__notify">
                        <img src="/images/brand/icon-192.png" width="192" height="192" alt="" />
                        <span>
                            <strong>{{ t('pwa.install_demo_app') }}</strong>
                            {{ t('pwa.install_demo_text') }}
                        </span>
                    </div>
                    <span v-for="index in PHONE_APPS" :key="index" class="install-prompt__app" :class="{ 'install-prompt__app--ours': index - 1 === OUR_APP_INDEX }">
                        <img v-if="index - 1 === OUR_APP_INDEX" src="/images/brand/icon-192.png" width="192" height="192" alt="" />
                    </span>
                </div>
            </div>
        </div>
    </aside>
</template>
