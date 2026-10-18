<!--
    Průvodce prvním nastavením (R153), krok 4 Jak vám dáme vědět: souhrn e-mailem (stejné
    uložení jako Můj účet, R42), upozornění v telefonu na tomto zařízení (lib/pushDevice.js, R66)
    a přidání na plochu — jen když ho prohlížeč nabízí nebo jde o iPhone (R139). Všechno nepovinné.

    @author Roman Hlaváček
    @created 2026-10-18
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { usePushDevice } from '@/lib/pushDevice';
import { installState, promptInstall } from '@/lib/pwaInstall';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Souhrn { frequency, options: [{ value, label }], errorBag }. */
    digest: { type: Object, required: true },
    /** Adresa uložení souhrnu (account.digest). */
    digestUrl: { type: String, required: true },
    /** Ověřený e-mail — souhrn chodí jen na něj (R51). */
    emailVerified: { type: Boolean, required: true },
    email: { type: String, required: true },
    /** Upozornění v telefonu (PushSubscriptionController::pageSettings); null = vypnutá na serveru. */
    push: { type: Object, default: null },
});

const t = useTranslations();

/** Vybraná četnost souhrnu — po klepnutí hned nová (R62). */
const frequency = ref(props.digest.frequency);

/**
 * Uloží četnost souhrnu (toast ze serveru jako v Mém účtu).
 *
 * @param {string} value
 */
function saveDigest(value) {
    frequency.value = value;
    router.put(props.digestUrl, { digest_frequency: value }, { preserveScroll: true, preserveState: true, errorBag: props.digest.errorBag, only: ['digest', 'errors', 'status'] });
}

/** Upozornění na tomto zařízení. */
const device = usePushDevice(() => props.push, t);

/** Přidání na plochu má smysl ukázat: už je na ploše, prohlížeč ho nabízí, nebo iPhone (postup). */
const installVisible = computed(() => installState.standalone || installState.promptEvent !== null || device.state.ios);
</script>

<template>
    <div class="onboarding-notify">
        <section class="onboarding-notify__option">
            <span class="onboarding-notify__icon" aria-hidden="true">
                <!-- Obálka -->
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
            </span>
            <h2 class="onboarding-notify__title">{{ t('onboarding.digest_title') }}</h2>
            <p class="onboarding-notify__hint">{{ t('onboarding.digest_hint', { email }) }}</p>
            <div class="onboarding-notify__control">
                <div class="view-toggle onboarding-notify__segments" role="group" :aria-label="t('onboarding.digest_label')">
                    <button
                        v-for="option in digest.options"
                        :key="option.value"
                        type="button"
                        class="view-toggle__button"
                        :aria-pressed="frequency === option.value ? 'true' : 'false'"
                        @click="saveDigest(option.value)"
                    >
                        {{ t(`onboarding.digest_short.${option.value}`) }}
                    </button>
                </div>
                <p class="onboarding-notify__note">{{ t(`account.digest_options.${frequency}`) }}</p>
                <p v-if="!emailVerified && frequency !== 'off'" class="onboarding-notify__warning">{{ t('account.digest_unverified') }}</p>
            </div>
        </section>

        <section v-if="push" class="onboarding-notify__option">
            <span class="onboarding-notify__icon" aria-hidden="true">
                <!-- Zvonek -->
                <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4" /></svg>
            </span>
            <h2 class="onboarding-notify__title">{{ t('onboarding.push_title') }}</h2>
            <p class="onboarding-notify__hint">{{ t('onboarding.push_hint') }}</p>
            <div class="onboarding-notify__control">
                <p v-if="device.enabled.value" class="onboarding-notify__done">
                    <svg class="onboarding-notify__done-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
                    {{ t('onboarding.push_enabled') }}
                </p>
                <p v-else-if="device.unavailableReason.value" class="onboarding-notify__warning">{{ t(device.unavailableReason.value) }}</p>
                <button v-else type="button" class="button button--soft" :disabled="device.state.busy" @click="device.toggle(true)">
                    {{ t('onboarding.push_enable') }}
                </button>
            </div>
        </section>

        <section v-if="installVisible" class="onboarding-notify__option">
            <span class="onboarding-notify__icon" aria-hidden="true">
                <!-- Telefon -->
                <svg viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2" /><path d="M11 18h2" /></svg>
            </span>
            <h2 class="onboarding-notify__title">
                {{ t('onboarding.install_title') }}
                <span class="tag onboarding-notify__badge">{{ t('onboarding.install_badge') }}</span>
            </h2>
            <p class="onboarding-notify__hint">{{ t('onboarding.install_hint') }}</p>
            <div class="onboarding-notify__control">
                <p v-if="installState.standalone" class="onboarding-notify__done">{{ t('pwa.installed') }}</p>
                <button v-else-if="installState.promptEvent" type="button" class="button button--soft" @click="promptInstall">{{ t('pwa.install') }}</button>
                <p v-else class="onboarding-notify__note">{{ t('pwa.install_ios_steps') }}</p>
            </div>
        </section>
    </div>
</template>
