<!--
    Můj účet (R12, R40, R41) — profilový obrázek, jméno a e-mail, heslo (Fortify),
    předvolby Mých slev, e-mailový souhrn (R42), aplikace a upozornění v telefonu (R66),
    novinky a nabídky (souhlas, R51),
    přihlášená zařízení a zrušení účtu.

    Sekce pod sebou s navigací (R63): nadpis s vysvětlením vlevo, pole vpravo. Volby (souhrn,
    novinky, předvolby Mých slev) se ukládají hned po změně; tlačítko mají jen formuláře, kde
    se píše. Heslo pro odhlášení zařízení a zrušení účtu se ukáže až po klepnutí.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import CheckboxField from '@/Components/CheckboxField.vue';
import PhoneAppSettings from '@/Components/PhoneAppSettings.vue';
import TextField from '@/Components/TextField.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { confirmDialog } from '@/lib/confirm';
import { useTranslations } from '@/lib/i18n';
import { squareImage } from '@/lib/image';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    urls: { type: Object, required: true },
    /** Názvy sad chyb validace — každý formulář má vlastní, aby se chyby nepletly. */
    errorBags: { type: Object, required: true },
    /** Rozměry profilového obrázku { sizePx, maxKilobytes }. */
    avatar: { type: Object, required: true },
    /** Přihlášení na zařízeních [{ device, ipAddress, lastActiveAt, current }]. */
    sessions: { type: Array, required: true },
    /** Předvolby Mých slev { sort, minDiscountPercent, sortOptions, minDiscountOptions } (R41). */
    offersPreferences: { type: Object, required: true },
    /** E-mailový souhrn { frequency, options } (R42). */
    digest: { type: Object, required: true },
    /** Souhlas s obchodními sděleními (R51). */
    marketingConsent: { type: Boolean, required: true },
    /** Upozornění v telefonu (R66, PhoneAppSettings.vue); null = vypnutá na serveru. */
    push: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();

/**
 * Sekce stránky v pořadí — kotva (odkazy z e-mailu a z Mých slev: #souhrn, #moje-slevy)
 * a klíč textů (lang: account.sections).
 */
const SECTIONS = [
    { id: 'profil', key: 'profile' },
    { id: 'souhrn', key: 'notifications' },
    { id: 'moje-slevy', key: 'offers' },
    { id: 'zabezpeceni', key: 'security' },
    { id: 'zruseni-uctu', key: 'delete' },
];

const user = computed(() => page.props.auth.user);

/** Sekce, ve které uživatel právě je — zvýrazní se v navigaci (R63). */
const activeSection = ref(SECTIONS[0].id);

/** Tolerance (px) pro „posunuto až na konec stránky“ — zaokrouhlení výšek v prohlížeči. */
const BOTTOM_TOLERANCE_PX = 2;

/** Čeká na snímek, aby se při posouvání nepočítalo víckrát než jednou za vykreslení. */
let spyFrame = null;

/**
 * Sekce vybraná klepnutím v navigaci — platí, dokud uživatel sám neposune stránku. Sekce
 * u konce stránky k hlavičce nedojede a pravidlo „konec stránky“ by zvýraznilo poslední.
 */
let clickedSection = null;

/**
 * Klepnutí na sekci v navigaci.
 *
 * @param {string} id
 */
function selectSection(id) {
    activeSection.value = id;
    clickedSection = id;
}

/** Posunutí kolečkem, prstem nebo klávesou — dál rozhoduje poloha stránky. */
function releaseClickedSection() {
    clickedSection = null;
}

/** Události, kterými uživatel posouvá sám (ne skok na kotvu po klepnutí). */
const USER_SCROLL_EVENTS = ['wheel', 'touchstart', 'keydown'];

/**
 * Najde aktuální sekci: poslední, jejíž začátek už dojel pod hlavičku (odsazení kotev
 * scroll-padding-top). Na konci stránky poslední sekce — krátká by k hlavičce nedojela.
 */
function updateActiveSection() {
    spyFrame = null;
    if (clickedSection !== null) {
        return;
    }

    const root = document.documentElement;
    if (window.innerHeight + window.scrollY >= root.scrollHeight - BOTTOM_TOLERANCE_PX) {
        activeSection.value = SECTIONS[SECTIONS.length - 1].id;

        return;
    }

    const offset = parseFloat(getComputedStyle(root).scrollPaddingTop) || 0;
    let current = SECTIONS[0].id;
    for (const section of SECTIONS) {
        const element = document.getElementById(section.id);
        if (element && element.getBoundingClientRect().top <= offset + BOTTOM_TOLERANCE_PX) {
            current = section.id;
        }
    }
    activeSection.value = current;
}

/** Posluchač posouvání — výpočet jednou za snímek. */
function onScroll() {
    spyFrame ??= requestAnimationFrame(updateActiveSection);
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    USER_SCROLL_EVENTS.forEach((event) => window.addEventListener(event, releaseClickedSection, { passive: true }));
    updateActiveSection();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    USER_SCROLL_EVENTS.forEach((event) => window.removeEventListener(event, releaseClickedSection));
    if (spyFrame !== null) {
        cancelAnimationFrame(spyFrame);
    }
});

/** Přihlášení i jinde než tady — jen pak má smysl odhlásit ostatní zařízení. */
const hasOtherSessions = computed(() => props.sessions.some((session) => !session.current));

/** Pole s heslem pro odhlášení zařízení a zrušení účtu se ukážou až po klepnutí. */
const devicesFormOpen = ref(false);
const deleteFormOpen = ref(false);

const profileForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
    current_password: '',
});

/** Změna e-mailu chce současné heslo (R54) — pole se ukáže, až když se adresa liší. */
const emailChanged = computed(() => profileForm.email.trim().toLowerCase() !== user.value.email.toLowerCase());

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const offersForm = useForm({
    offers_sort: props.offersPreferences.sort,
    min_discount_percent: props.offersPreferences.minDiscountPercent,
});

const digestForm = useForm({ digest_frequency: props.digest.frequency });
const marketingForm = useForm({ marketing: props.marketingConsent });

const devicesForm = useForm({ password: '' });
const deleteForm = useForm({ password: '' });

const avatarInput = ref(null);
const avatarError = ref(null);
const avatarUploading = ref(false);

/** Uloží jméno a e-mail; heslo se po odeslání vždy vymaže. */
function updateProfile() {
    profileForm.put(props.urls.profile, {
        errorBag: props.errorBags.profile,
        preserveScroll: true,
        onFinish: () => profileForm.reset('current_password'),
    });
}

/** Změní heslo; pole s hesly se po odeslání vždy vymažou. */
function updatePassword() {
    passwordForm.put(props.urls.password, {
        errorBag: props.errorBags.password,
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}

/**
 * Vybraný obrázek ořízne na čtverec, zmenší a nahraje.
 *
 * @param {Event} event
 */
async function uploadAvatar(event) {
    const [file] = event.target.files;
    event.target.value = '';
    if (!file) {
        return;
    }

    avatarError.value = null;
    let squared;
    try {
        squared = await squareImage(file, props.avatar.sizePx);
    } catch {
        avatarError.value = t('account.avatar_unreadable');

        return;
    }

    router.post(
        props.urls.avatar,
        { avatar: squared },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (avatarUploading.value = true),
            onFinish: () => (avatarUploading.value = false),
            onError: (errors) => (avatarError.value = errors.avatar ?? null),
        },
    );
}

/** Odebere obrázek — ukážou se iniciály. */
function removeAvatar() {
    router.delete(props.urls.avatarDelete, { preserveScroll: true });
}

/** Uloží řazení a minimální slevu Mých slev — hned po změně výběru. */
function updateOffersPreferences() {
    offersForm.put(props.urls.offersPreferences, {
        errorBag: props.errorBags.offersPreferences,
        preserveScroll: true,
    });
}

/** Uloží četnost e-mailového souhrnu — hned po výběru. */
function updateDigest() {
    digestForm.put(props.urls.digest, {
        errorBag: props.errorBags.digest,
        preserveScroll: true,
    });
}

/** Uloží souhlas s novinkami a nabídkami — hned po zaškrtnutí. */
function updateMarketing() {
    marketingForm.put(props.urls.marketing, {
        errorBag: props.errorBags.marketing,
        preserveScroll: true,
    });
}

/** Odhlásí ostatní zařízení po zadání hesla; po úspěchu pole s heslem zase schová. */
function logoutOtherDevices() {
    devicesForm.delete(props.urls.logoutOtherDevices, {
        errorBag: props.errorBags.devices,
        preserveScroll: true,
        onSuccess: () => (devicesFormOpen.value = false),
        onFinish: () => devicesForm.reset(),
    });
}

/** Po potvrzení zruší účet. */
async function deleteAccount() {
    const confirmed = await confirmDialog({
        title: t('account.delete_confirm_title'),
        message: t('account.delete_confirm'),
        confirmLabel: t('account.delete_submit'),
    });
    if (!confirmed) {
        return;
    }

    deleteForm.delete(props.urls.delete, {
        errorBag: props.errorBags.delete,
        preserveScroll: true,
        onFinish: () => deleteForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('account.title')" />

        <header class="page__header">
            <h1 class="page__title">{{ t('account.title') }}</h1>
        </header>

        <div class="account">
            <!-- Sekce stránky: na počítači vlevo a jedou s ní, na telefonu řada odkazů nahoře -->
            <nav class="account-nav" :aria-label="t('account.nav_label')">
                <a
                    v-for="section in SECTIONS"
                    :key="section.id"
                    :href="`#${section.id}`"
                    class="account-nav__link"
                    :class="{ 'account-nav__link--active': activeSection === section.id }"
                    :aria-current="activeSection === section.id ? 'location' : undefined"
                    @click="selectSection(section.id)"
                >
                    {{ t(`account.sections.${section.key}.title`) }}
                </a>
            </nav>

            <div class="account__sections">
                <section id="profil" class="card account-section" aria-labelledby="profil-title">
                    <header class="account-section__header">
                        <h2 id="profil-title" class="account-section__title">{{ t('account.sections.profile.title') }}</h2>
                        <p class="account-section__hint">{{ t('account.sections.profile.hint') }}</p>
                    </header>
                    <div class="account-section__body">
                        <div class="account-avatar">
                            <UserAvatar :name="user.name" :url="user.avatarUrl" large />
                            <div class="account-avatar__body">
                                <p class="account-avatar__title">{{ t('account.avatar') }}</p>
                                <p class="form-field__hint">{{ t('account.avatar_hint') }}</p>
                                <div class="account-avatar__actions">
                                    <input ref="avatarInput" type="file" accept="image/*" class="visually-hidden" tabindex="-1" @change="uploadAvatar" />
                                    <button type="button" class="button button--ghost" :disabled="avatarUploading" @click="avatarInput.click()">
                                        {{ user.avatarUrl ? t('account.avatar_change') : t('account.avatar_upload') }}
                                    </button>
                                    <button v-if="user.avatarUrl" type="button" class="button button--ghost" @click="removeAvatar">{{ t('account.avatar_remove') }}</button>
                                </div>
                                <p v-if="avatarError" class="form-field__error" role="alert">{{ avatarError }}</p>
                            </div>
                        </div>

                        <form class="form" novalidate @submit.prevent="updateProfile">
                            <TextField id="name" v-model="profileForm.name" :label="t('auth.name')" autocomplete="name" required :error="profileForm.errors.name" />
                            <TextField id="email" v-model="profileForm.email" :label="t('auth.email')" type="email" autocomplete="email" required :error="profileForm.errors.email" />
                            <TextField
                                v-if="emailChanged"
                                id="profile_current_password"
                                v-model="profileForm.current_password"
                                :label="t('account.current_password')"
                                :hint="t('account.email_change_password_hint')"
                                type="password"
                                autocomplete="current-password"
                                required
                                revealable
                                :error="profileForm.errors.current_password"
                            />
                            <div class="form__actions">
                                <button type="submit" class="button button--primary" :disabled="profileForm.processing">{{ t('account.save') }}</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section id="souhrn" class="card account-section" aria-labelledby="souhrn-title">
                    <header class="account-section__header">
                        <h2 id="souhrn-title" class="account-section__title">{{ t('account.sections.notifications.title') }}</h2>
                        <p class="account-section__hint">{{ t('account.sections.notifications.hint') }}</p>
                        <p class="account-section__autosave">{{ t('account.autosave') }}</p>
                    </header>
                    <div class="account-section__body">
                        <fieldset class="account-choice">
                            <legend class="account-section__subtitle">{{ t('account.digest_title') }}</legend>
                            <p class="form-field__hint">{{ t('account.digest_hint', { email: user.email }) }}</p>
                            <p v-if="!user.emailVerified" class="account-section__warning">{{ t('account.digest_unverified') }}</p>
                            <label
                                v-for="option in digest.options"
                                :key="option.value"
                                class="account-choice__option"
                                :class="{ 'account-choice__option--selected': digestForm.digest_frequency === option.value }"
                            >
                                <input
                                    v-model="digestForm.digest_frequency"
                                    type="radio"
                                    name="digest_frequency"
                                    :value="option.value"
                                    class="form-checkbox__input"
                                    @change="updateDigest"
                                />
                                <span>
                                    <span class="account-choice__label">{{ option.label }}</span>
                                    <span class="account-choice__hint">{{ t(`account.digest_options.${option.value}`) }}</span>
                                </span>
                            </label>
                            <p v-if="digestForm.errors.digest_frequency" class="form-field__error" role="alert">{{ digestForm.errors.digest_frequency }}</p>
                        </fieldset>

                        <PhoneAppSettings :push="push" />

                        <div id="novinky" class="account-section__part">
                            <h3 class="account-section__subtitle">{{ t('account.marketing_title') }}</h3>
                            <p class="form-field__hint">
                                {{ t('account.marketing_hint') }}
                                <Link :href="urls.privacy" class="link">{{ t('account.privacy_link') }}</Link>
                            </p>
                            <CheckboxField
                                id="marketing"
                                v-model="marketingForm.marketing"
                                :label="t('account.marketing_label')"
                                :error="marketingForm.errors.marketing"
                                @update:model-value="updateMarketing"
                            />
                        </div>
                    </div>
                </section>

                <section id="moje-slevy" class="card account-section" aria-labelledby="moje-slevy-title">
                    <header class="account-section__header">
                        <h2 id="moje-slevy-title" class="account-section__title">{{ t('account.sections.offers.title') }}</h2>
                        <p class="account-section__hint">{{ t('account.sections.offers.hint') }}</p>
                        <p class="account-section__autosave">{{ t('account.autosave') }}</p>
                    </header>
                    <div class="account-section__body form">
                        <div class="form-field">
                            <label for="offers_sort" class="form-field__label">{{ t('account.offers_sort') }}</label>
                            <select id="offers_sort" v-model="offersForm.offers_sort" class="form-field__input" @change="updateOffersPreferences">
                                <option v-for="option in offersPreferences.sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="offersForm.errors.offers_sort" class="form-field__error" role="alert">{{ offersForm.errors.offers_sort }}</p>
                        </div>
                        <div class="form-field">
                            <label for="min_discount_percent" class="form-field__label">{{ t('account.min_discount') }}</label>
                            <select
                                id="min_discount_percent"
                                v-model="offersForm.min_discount_percent"
                                class="form-field__input"
                                aria-describedby="min_discount_hint"
                                @change="updateOffersPreferences"
                            >
                                <option :value="null">{{ t('account.min_discount_all') }}</option>
                                <option v-for="percent in offersPreferences.minDiscountOptions" :key="percent" :value="percent">
                                    {{ t('account.min_discount_option', { percent }) }}
                                </option>
                            </select>
                            <p id="min_discount_hint" class="form-field__hint">{{ t('account.min_discount_hint') }}</p>
                            <p v-if="offersForm.errors.min_discount_percent" class="form-field__error" role="alert">{{ offersForm.errors.min_discount_percent }}</p>
                        </div>
                    </div>
                </section>

                <section id="zabezpeceni" class="card account-section" aria-labelledby="zabezpeceni-title">
                    <header class="account-section__header">
                        <h2 id="zabezpeceni-title" class="account-section__title">{{ t('account.sections.security.title') }}</h2>
                        <p class="account-section__hint">{{ t('account.sections.security.hint') }}</p>
                    </header>
                    <div class="account-section__body">
                        <form class="form" novalidate @submit.prevent="updatePassword">
                            <h3 class="account-section__subtitle">{{ t('account.password') }}</h3>
                            <TextField
                                id="current_password"
                                v-model="passwordForm.current_password"
                                :label="t('account.current_password')"
                                type="password"
                                autocomplete="current-password"
                                required
                                revealable
                                :error="passwordForm.errors.current_password"
                            />
                            <TextField
                                id="password"
                                v-model="passwordForm.password"
                                :label="t('account.new_password')"
                                type="password"
                                autocomplete="new-password"
                                required
                                revealable
                                :error="passwordForm.errors.password"
                            />
                            <TextField
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                :label="t('auth.password_confirmation')"
                                type="password"
                                autocomplete="new-password"
                                required
                                revealable
                                :error="passwordForm.errors.password_confirmation"
                            />
                            <div class="form__actions">
                                <button type="submit" class="button button--primary" :disabled="passwordForm.processing">{{ t('account.password_submit') }}</button>
                            </div>
                        </form>

                        <div class="account-section__part">
                            <h3 class="account-section__subtitle">{{ t('account.devices') }}</h3>
                            <p class="form-field__hint">{{ t('account.devices_hint') }}</p>
                            <ul v-if="sessions.length" class="device-list">
                                <li v-for="(session, index) in sessions" :key="index" class="device-list__item">
                                    <span class="device-list__name">
                                        {{ session.device }}
                                        <span v-if="session.current" class="tag tag--accent">{{ t('account.this_device') }}</span>
                                    </span>
                                    <span class="device-list__meta">
                                        {{ session.ipAddress }} · {{ t('account.last_active', { at: formatDateTime(session.lastActiveAt, page.props.locale, page.props.timezone) }) }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="page__empty">{{ t('account.devices_empty') }}</p>

                            <template v-if="hasOtherSessions">
                                <button v-if="!devicesFormOpen" type="button" class="button button--ghost account-section__action" @click="devicesFormOpen = true">
                                    {{ t('account.logout_others_start') }}
                                </button>
                                <form v-else class="form account-section__confirm" novalidate @submit.prevent="logoutOtherDevices">
                                    <TextField
                                        id="devices_password"
                                        v-model="devicesForm.password"
                                        :label="t('account.confirm_password')"
                                        type="password"
                                        autocomplete="current-password"
                                        required
                                        autofocus
                                        revealable
                                        :error="devicesForm.errors.password"
                                    />
                                    <div class="form__actions">
                                        <button type="submit" class="button button--primary" :disabled="devicesForm.processing">{{ t('account.logout_others') }}</button>
                                        <button type="button" class="button button--ghost" @click="devicesFormOpen = false">{{ t('account.cancel') }}</button>
                                    </div>
                                </form>
                            </template>
                            <p v-else-if="sessions.length" class="form-field__hint">{{ t('account.devices_only_this') }}</p>
                        </div>
                    </div>
                </section>

                <section id="zruseni-uctu" class="card card--danger account-section" aria-labelledby="zruseni-uctu-title">
                    <header class="account-section__header">
                        <h2 id="zruseni-uctu-title" class="account-section__title">{{ t('account.sections.delete.title') }}</h2>
                        <p class="account-section__hint">{{ t('account.sections.delete.hint') }}</p>
                    </header>
                    <div class="account-section__body">
                        <button v-if="!deleteFormOpen" type="button" class="button button--danger account-section__action" @click="deleteFormOpen = true">{{ t('account.delete_start') }}</button>
                        <form v-else class="form account-section__confirm" novalidate @submit.prevent="deleteAccount">
                            <TextField
                                id="delete_password"
                                v-model="deleteForm.password"
                                :label="t('account.confirm_password')"
                                type="password"
                                autocomplete="current-password"
                                required
                                autofocus
                                revealable
                                :error="deleteForm.errors.password"
                            />
                            <div class="form__actions">
                                <button type="submit" class="button button--danger" :disabled="deleteForm.processing">{{ t('account.delete_submit') }}</button>
                                <button type="button" class="button button--ghost" @click="deleteFormOpen = false">{{ t('account.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
