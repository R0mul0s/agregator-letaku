<!--
    Akce hlídané položky v Mých slevách — upravit vlastní slova a přestat hlídat (s potvrzením).
    Sdílí je hlavička skupiny (WatchGroup) a řádek v sekci Zatím bez akce (WaitingSection, R100).

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
import { confirmDialog } from '@/lib/confirm';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    /** Položka z HomeController ({ name, fromCatalog, editUrl, deleteUrl }). */
    item: { type: Object, required: true },
});

const t = useTranslations();

/** Po potvrzení položku přestane hlídat; stránka zůstane na Mých slevách. */
async function remove() {
    const confirmed = await confirmDialog({
        title: t('watch.delete_confirm_title'),
        message: t('watch.delete_confirm', { name: props.item.name }),
        confirmLabel: t('watch.stop'),
    });
    if (confirmed) {
        router.delete(props.item.deleteUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="watch-group__actions">
        <Link v-if="!item.fromCatalog" :href="item.editUrl" class="icon-button" :title="t('watch.edit')">
            <!-- Tužka -->
            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4" /></svg>
            <span class="visually-hidden">{{ t('watch.edit') }} {{ item.name }}</span>
        </Link>
        <button type="button" class="icon-button icon-button--danger" :title="t('watch.stop')" @click="remove">
            <!-- Koš -->
            <svg class="icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" /></svg>
            <span class="visually-hidden">{{ t('watch.stop') }} {{ item.name }}</span>
        </button>
    </div>
</template>
