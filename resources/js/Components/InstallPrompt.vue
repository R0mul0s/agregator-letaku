<!--
    Výzva k přidání Slevohlídky na plochu telefonu (R66) — v Mých slevách a nákupním seznamu,
    tedy až když má aplikace pro uživatele smysl, ne hned při příchodu. Android a Chrome mají
    dialog prohlížeče (beforeinstallprompt), iPhone jen ruční postup přes Sdílet. Po „Teď ne“
    se na čas schová (letaky.pwa.install_prompt_snooze_days).

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { installState, isInstallPromptSnoozed, isIos, promptInstall, snoozeInstallPrompt } from '@/lib/pwa';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

/** Stránky, na kterých se výzva ukazuje. */
const PAGES = ['Home', 'ShoppingList'];

const t = useTranslations();
const page = usePage();

/** Zjišťuje se až v prohlížeči (localStorage, systém). */
const snoozed = ref(true);
const ios = ref(false);

onMounted(() => {
    snoozed.value = isInstallPromptSnoozed();
    ios.value = isIos();
});

const visible = computed(
    () => page.props.auth.user && PAGES.includes(page.component) && !snoozed.value && !installState.standalone && (installState.promptEvent || ios.value),
);

/** Dialog prohlížeče; po přidání se výzva už neukáže (aplikace poběží z plochy). */
async function install() {
    if (await promptInstall()) {
        snoozed.value = true;
    }
}

/** „Teď ne“ — výzva se na čas schová. */
function dismiss() {
    snoozeInstallPrompt();
    snoozed.value = true;
}
</script>

<template>
    <aside v-if="visible" class="install-prompt" :aria-label="t('pwa.install_title')">
        <img src="/images/brand/icon-192.png" alt="" class="install-prompt__icon" />
        <div class="install-prompt__body">
            <p class="install-prompt__title">{{ t('pwa.install_title') }}</p>
            <p class="install-prompt__text">{{ t('pwa.install_text') }}</p>
            <p v-if="!installState.promptEvent" class="install-prompt__steps">{{ t('pwa.install_ios_steps') }}</p>
            <div class="install-prompt__actions">
                <button v-if="installState.promptEvent" type="button" class="button button--primary" @click="install">{{ t('pwa.install') }}</button>
                <button type="button" class="button button--ghost" @click="dismiss">{{ t('pwa.install_later') }}</button>
            </div>
        </div>
    </aside>
</template>
