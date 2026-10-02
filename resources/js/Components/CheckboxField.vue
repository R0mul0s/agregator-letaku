<!--
    Zaškrtávací pole formuláře s popiskem.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** Atribut id i name pole — odpovídá klíči ve formuláři. */
    id: { type: String, required: true },
    label: { type: String, required: true },
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label :for="id" class="form-checkbox">
        <input :id="id" v-model="model" :name="id" type="checkbox" class="form-checkbox__input" />
        <span>{{ label }}</span>
    </label>
</template>
