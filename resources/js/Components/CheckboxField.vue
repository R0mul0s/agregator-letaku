<!--
    Zaškrtávací pole formuláře s popiskem. Popisek s odkazy (souhlas s podmínkami, R51) jde
    předat slotem místo textu; chyba validace se ukáže pod polem.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
defineProps({
    /** Atribut id i name pole — odpovídá klíči ve formuláři. */
    id: { type: String, required: true },
    /** Text popisku; bez něj popisek dodá výchozí slot. */
    label: { type: String, default: '' },
    /** Chyba validace pole. */
    error: { type: String, default: null },
    /** Pole nejde měnit (ukládání ještě běží). */
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <div class="form-checkbox-field">
        <label :for="id" class="form-checkbox">
            <input
                :id="id"
                v-model="model"
                :name="id"
                :disabled="disabled"
                type="checkbox"
                class="form-checkbox__input"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}_error` : undefined"
            />
            <span>
                <slot>{{ label }}</slot>
            </span>
        </label>
        <p v-if="error" :id="`${id}_error`" class="form-field__error" role="alert">{{ error }}</p>
    </div>
</template>
