<!--
    Textové pole formuláře s popiskem, nápovědou a chybou validace.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Atribut id i name pole — odpovídá klíči ve formuláři a v chybách validace. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    autocomplete: { type: String, default: undefined },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    /** Nápověda pod polem. */
    hint: { type: String, default: undefined },
    /** Chyba validace ze serveru (form.errors.…). */
    error: { type: String, default: undefined },
});

const model = defineModel({ type: String, default: '' });

const hintId = computed(() => `${props.id}-hint`);
const errorId = computed(() => `${props.id}-error`);

/** Popis pole pro čtečky — nápověda a chyba, pokud jsou. */
const describedBy = computed(() => [props.hint ? hintId.value : null, props.error ? errorId.value : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="form-field">
        <label :for="id" class="form-field__label">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :name="id"
            :type="type"
            :autocomplete="autocomplete"
            :required="required"
            :autofocus="autofocus"
            class="form-field__input"
            :class="{ 'form-field__input--invalid': error }"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
        />
        <p v-if="hint" :id="hintId" class="form-field__hint">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="form-field__error">{{ error }}</p>
    </div>
</template>
