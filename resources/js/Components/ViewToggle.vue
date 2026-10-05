<!--
    Přepínač zobrazení akcí: karty s obrázkem, nebo kompaktní řádky (R82). Dvě tlačítka vedle
    sebe se stavem v aria-pressed; popisky jsou na úzkém displeji jen pro čtečky.

    @author Roman Hlaváček
    @created 2026-10-05
-->
<script setup>
import { useTranslations } from '@/lib/i18n';

/** true = řádky, false = karty. */
const compact = defineModel({ type: Boolean, required: true });

const t = useTranslations();
</script>

<template>
    <div class="view-toggle" role="group" :aria-label="t('view.label')">
        <button type="button" class="view-toggle__button" :aria-pressed="compact ? 'false' : 'true'" :title="t('view.cards')" @click="compact = false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5" /><rect x="13" y="4" width="7" height="7" rx="1.5" /><rect x="4" y="13" width="7" height="7" rx="1.5" /><rect x="13" y="13" width="7" height="7" rx="1.5" /></svg>
            <span class="view-toggle__label">{{ t('view.cards') }}</span>
        </button>
        <button type="button" class="view-toggle__button" :aria-pressed="compact ? 'true' : 'false'" :title="t('view.rows')" @click="compact = true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
            <span class="view-toggle__label">{{ t('view.rows') }}</span>
        </button>
    </div>
</template>
