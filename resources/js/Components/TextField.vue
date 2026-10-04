<!--
    Textové pole formuláře s popiskem, nápovědou a chybou validace. Pole hesla může mít
    tlačítko „Ukázat heslo“ (revealable, R56) — registrace pak nepotřebuje heslo dvakrát.

    @author Roman Hlaváček
    @created 2026-10-02
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { computed, ref } from 'vue';

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
    /** Tlačítko, které ukáže zadané heslo (jen u type="password"). */
    revealable: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });

const t = useTranslations();

/** Heslo je právě vidět. */
const revealed = ref(false);

const hintId = computed(() => `${props.id}-hint`);
const errorId = computed(() => `${props.id}-error`);

/** Skutečný typ pole — ukázané heslo je obyčejný text. */
const inputType = computed(() => (props.revealable && revealed.value ? 'text' : props.type));

/** Popis pole pro čtečky — nápověda a chyba, pokud jsou. */
const describedBy = computed(() => [props.hint ? hintId.value : null, props.error ? errorId.value : null].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="form-field">
        <label :for="id" class="form-field__label">{{ label }}</label>
        <div class="form-field__control">
            <input
                :id="id"
                v-model="model"
                :name="id"
                :type="inputType"
                :autocomplete="autocomplete"
                :required="required"
                :autofocus="autofocus"
                class="form-field__input"
                :class="{ 'form-field__input--invalid': error, 'form-field__input--revealable': revealable }"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
            />
            <button
                v-if="revealable"
                type="button"
                class="form-field__reveal"
                :aria-pressed="revealed ? 'true' : 'false'"
                :aria-controls="id"
                @click="revealed = !revealed"
            >
                <!-- Oko, při ukázaném hesle přeškrtnuté -->
                <svg class="form-field__reveal-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                    <circle cx="12" cy="12" r="3" />
                    <path v-if="revealed" d="M4 4l16 16" />
                </svg>
                <span class="visually-hidden">{{ t('auth.password_show') }}</span>
            </button>
        </div>
        <p v-if="hint" :id="hintId" class="form-field__hint">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="form-field__error">{{ error }}</p>
    </div>
</template>
