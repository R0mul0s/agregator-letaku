<!--
    Výběr formuláře s popiskem, nápovědou a chybou validace — jako TextField: chybné pole má
    aria-invalid, ať na něj lib/a11y.js po chybě přesune fokus (R99, R113). Volby jsou ve slotu.

    @author Roman Hlaváček
    @created 2026-10-09
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Atribut id pole — odpovídá klíči ve formuláři a v chybách validace. */
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Nápověda pod polem. */
    hint: { type: String, default: undefined },
    /** Chyba validace ze serveru (form.errors.…). */
    error: { type: String, default: undefined },
});

const model = defineModel({ type: [String, Number, null], default: null });

const emit = defineEmits(['change']);

const hintId = computed(() => `${props.id}-hint`);
const errorId = computed(() => `${props.id}-error`);

/** Popis pole pro čtečky — nápověda a chyba, pokud jsou. */
const describedBy = computed(() => [props.hint ? hintId.value : null, props.error ? errorId.value : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="form-field">
        <label :for="id" class="form-field__label">{{ label }}</label>
        <select
            :id="id"
            v-model="model"
            :name="id"
            class="form-field__input"
            :class="{ 'form-field__input--invalid': error }"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            @change="emit('change')"
        >
            <slot />
        </select>
        <p v-if="hint" :id="hintId" class="form-field__hint">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="form-field__error" role="alert">{{ error }}</p>
    </div>
</template>
