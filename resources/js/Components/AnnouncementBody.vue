<!--
    Text zprávy od nás s odkazy (R175): řádky, jak je admin napsal, `[slova](/cesta)` jako odkaz —
    cesta v aplikaci přechodem Inertie, https adresa v novém okně. Bez v-html, text se nikdy
    nečte jako HTML. Stránka záznamu i náhled ve formuláři zprávy.

    @author Roman Hlaváček
    @created 2026-10-25
-->
<script setup>
import { announcementParts } from '@/lib/announcementText';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    body: { type: String, required: true },
    /** Vykreslit odkazy? Upozornění adminům (text z chyb a zdrojů) ne. */
    links: { type: Boolean, default: true },
});

const parts = computed(() => (props.links ? announcementParts(props.body) : [{ text: props.body }]));
</script>

<template>
    <p class="announcement-body">
        <template v-for="(part, index) in parts" :key="index">
            <a v-if="part.url && part.external" :href="part.url" target="_blank" rel="noopener noreferrer" class="link">{{ part.text }}</a>
            <Link v-else-if="part.url" :href="part.url" class="link">{{ part.text }}</Link>
            <template v-else>{{ part.text }}</template>
        </template>
    </p>
</template>
