<!--
    „Hlídat“ u karty akce ve Všech akcích (R60). Akce přiřazená k produktu katalogu ho začne
    hlídat jedním klepnutím a stránka zůstane, kde byla; akce bez produktu otevře Hlídám
    s jejím názvem jako vlastními slovy. Nepřihlášeného vede na registraci — produkt se pak
    začne hlídat hned po ní.

    @author Roman Hlaváček
    @created 2026-10-04
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { showToast } from '@/lib/toast';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Co hlídat { productId, name, watched } (App\Domain\Catalog\WatchTargets). */
    target: { type: Object, required: true },
    /** Adresy a názvy parametrů { store, index, register, prefillParameter, watchParameter, stayField }. */
    urls: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const processing = ref(false);

const isGuest = computed(() => !page.props.auth.user);

/** Registrace s produktem k hlídání po ní. */
const registerHref = computed(() =>
    props.target.productId ? `${props.urls.register}?${new URLSearchParams({ [props.urls.watchParameter]: props.target.productId })}` : props.urls.register,
);

/** Hlídám s formulářem vlastních slov předvyplněným názvem akce. */
const prefillHref = computed(() => `${props.urls.index}?${new URLSearchParams({ [props.urls.prefillParameter]: props.target.name })}`);

/** Začne hlídat produkt katalogu; chybu (limit položek) ukáže toastem. */
function watchProduct() {
    router.post(
        props.urls.store,
        { product_id: props.target.productId, name: props.target.name, [props.urls.stayField]: true },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (errors) => showToast(Object.values(errors)[0]),
        },
    );
}
</script>

<template>
    <span v-if="target.watched" class="watch-offer watch-offer--done">{{ t('offers.watch.watched', { name: target.name }) }}</span>
    <Link v-else-if="isGuest" :href="registerHref" class="button button--soft watch-offer">
        {{ target.productId ? t('offers.watch.product', { name: target.name }) : t('offers.watch.own') }}
    </Link>
    <button v-else-if="target.productId" type="button" class="button button--soft watch-offer" :disabled="processing" @click="watchProduct">
        {{ t('offers.watch.product', { name: target.name }) }}
    </button>
    <Link v-else :href="prefillHref" class="button button--soft watch-offer">{{ t('offers.watch.own') }}</Link>
</template>
