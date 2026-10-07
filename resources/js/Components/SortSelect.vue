<!--
    Výběr řazení akcí (R100) — malý select s popiskem ve výšce přepínače karty / řádky. Všechny
    akce ho posílají v adrese, Moje slevy ukládají jako předvolbu účtu; o to se stará stránka.

    @author Roman Hlaváček
    @created 2026-10-07
-->
<script setup>
defineProps({
    /** Id selectu (popisek na něj odkazuje). */
    id: { type: String, required: true },
    /** Viditelný popisek („Řadit“). */
    label: { type: String, required: true },
    /** Možnosti [{ value, label }]. */
    options: { type: Array, required: true },
});

/** Hodnota vybraného řazení. */
const sort = defineModel({ type: String, required: true });

const emit = defineEmits(['change']);
</script>

<template>
    <div class="sort-select">
        <label :for="id" class="sort-select__label">{{ label }}</label>
        <select :id="id" v-model="sort" class="sort-select__input" @change="emit('change', sort)">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </div>
</template>
