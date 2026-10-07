<!--
    Logo poskytovatele přihlášení (R96) na tlačítku a v Mém účtu. Barevná loga (Google,
    Facebook) jsou obrázek; „esko“ Seznamu (R98) se barví barvou textu — manuál tlačítka
    ho dovoluje jen červené na světlém a bílé na tmavém podkladu — proto maska, ne obrázek.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
defineProps({
    /** Hodnota App\Enums\SocialProvider („seznam“). */
    provider: { type: String, required: true },
    /** Adresa loga (public/images/social). */
    logo: { type: String, required: true },
    /** Logo barvené barvou textu (SocialProvider::hasTintedLogo). */
    tinted: { type: Boolean, default: false },
});
</script>

<template>
    <span v-if="tinted" class="social-login__logo social-login__logo--tinted" :class="`social-login__logo--${provider}`" aria-hidden="true"></span>
    <img v-else :src="logo" alt="" class="social-login__logo" />
</template>
