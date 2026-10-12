<!--
    Pole hledání ve tvaru „pilulky“ (R147) — jedno pro Všechny akce, úvodní stránku, Hlídám
    i nákupní seznam: ikona vlevo (lupa, při načítání kolečko, nebo plus u přidávání), pole
    a tlačítko vpravo (Najít, Hlídat, Přidat), na počítači bez tlačítka nápověda „/“. Na červeném
    pruhu bílé se stínem (`on-hero`), v kartě s okrajem. Našeptávání a combobox (role, aria,
    klávesy) má komponenta, která pole používá — atributy a události jdou na <input>.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    /** Ikona vlevo: search (lupa) | add (plus). */
    icon: { type: String, default: 'search' },
    /** Načítá se — lupa se změní v točící se kolečko. */
    busy: { type: Boolean, default: false },
    /** Text tlačítka v poli; bez něj na počítači nápověda klávesy „/“ (shortcut). */
    submitLabel: { type: String, default: null },
    /** Nápověda klávesy „/“ bez tlačítka (Všechny akce na počítači). */
    shortcut: { type: Boolean, default: false },
    /** Na červeném pruhu: bílé pole bez okraje se stínem. */
    onHero: { type: Boolean, default: false },
});

const emit = defineEmits(['input', 'submit']);

/** Text pole (v-model). */
const model = defineModel({ type: String, default: '' });

const input = ref(null);

/**
 * Psaní: nejdřív model, pak událost — komponenta s našeptávačem čte už nový text.
 *
 * @param {InputEvent} event
 */
function onInput(event) {
    model.value = event.target.value;
    emit('input', event);
}

defineExpose({
    /** Zaměří pole. */
    focus: () => input.value?.focus(),
    /** Vezme poli fokus (zavře klávesnici na telefonu). */
    blur: () => input.value?.blur(),
});
</script>

<template>
    <div class="search-suggest__control search-field" :class="{ 'search-field--on-hero': onHero, 'search-field--with-submit': submitLabel }">
        <span class="search-suggest__icon" :class="{ 'search-suggest__icon--busy': busy }" aria-hidden="true">
            <svg v-if="icon === 'add'" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            <svg v-else viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5L21 21" /></svg>
        </span>
        <input ref="input" v-bind="$attrs" :value="model" type="search" class="form-field__input search-suggest__input" @input="onInput" />
        <!-- mousedown.prevent — tlačítko nesmí vzít poli fokus (blur by zavřel našeptávač dřív, než přijde click) -->
        <button v-if="submitLabel" type="button" class="button search-suggest__submit" @mousedown.prevent @click="emit('submit')">{{ submitLabel }}</button>
        <kbd v-else-if="shortcut" class="search-suggest__shortcut" aria-hidden="true">/</kbd>
    </div>
</template>
