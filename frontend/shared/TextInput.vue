<script setup>
import { ref, computed, useId } from 'vue';
import { validate as runValidation } from './validators.js';

const props = defineProps({
    id: { type: String, default: null },
    name: { type: String, default: null },
    label: { type: String, default: '' },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    helperText: { type: String, default: '' },
    error: { type: String, default: '' },
    rules: { type: Array, default: () => [] },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    inputmode: { type: String, default: null },
    maxlength: { type: [Number, String], default: null },
    minlength: { type: [Number, String], default: null },
    min: { type: [Number, String], default: null },
    max: { type: [Number, String], default: null },
    step: { type: [Number, String], default: null },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    debounce: { type: [Number, String], default: null },
    clearable: { type: Boolean, default: false },
    showPasswordToggle: { type: Boolean, default: true }
});

const emit = defineEmits(['update:modelValue', 'input', 'change', 'blur', 'focus', 'clear', 'search']);

// Unique ID for label and ARIA binding
const autoId = useId ? useId() : `input-${Math.random().toString(36).slice(2, 9)}`;
const inputId = computed(() => props.id || autoId);
const helperId = computed(() => `${inputId.value}-help`);
const errorId = computed(() => `${inputId.value}-error`);

const inputRef = ref(null);
const internalError = ref('');
const isPasswordVisible = ref(false);
let debounceTimer = null;

const computedMin = computed(() => {
    if (props.min !== null && props.min !== undefined) return props.min;
    if (props.type === 'number') return 0; // Number fields reject negative values
    return null;
});

const computedAutocomplete = computed(() => {
    if (props.autocomplete !== null) return props.autocomplete;
    // ID number and ID type fields must default to autocomplete="off"
    if (props.name === 'id_number' || props.name === 'id_type' || props.id === 'id_number' || props.id === 'id_type') {
        return 'off';
    }
    return null;
});

const computedType = computed(() => {
    if (props.type === 'password') {
        return isPasswordVisible.value ? 'text' : 'password';
    }
    return props.type;
});

const computedInputmode = computed(() => {
    if (props.inputmode) return props.inputmode;
    if (props.type === 'email') return 'email';
    if (props.type === 'tel') return 'tel';
    if (props.type === 'number') return 'decimal';
    if (props.type === 'search') return 'search';
    return null;
});

const debounceDelay = computed(() => {
    if (props.debounce !== null && props.debounce !== undefined) return Number(props.debounce);
    if (props.type === 'search') return 300;
    return 0;
});

const displayError = computed(() => props.error || internalError.value);
const hasError = computed(() => Boolean(displayError.value));

const ariaDescribedby = computed(() => {
    const ids = [];
    if (hasError.value) ids.push(errorId.value);
    if (props.helperText) ids.push(helperId.value);
    return ids.length ? ids.join(' ') : null;
});

function runCheck(value) {
    if (props.type === 'number' && value !== null && value !== undefined && value !== '') {
        const num = Number(value);
        if (!Number.isNaN(num) && num < 0) {
            return `${props.label || 'Value'} cannot be negative.`;
        }
    }
    if (props.rules && props.rules.length > 0) {
        return runValidation(value, props.rules, props.label || 'This field');
    }
    if (props.required && (value === null || value === undefined || String(value).trim() === '')) {
        return `${props.label || 'This field'} is required.`;
    }
    return '';
}

function handleInput(event) {
    const val = event.target.value;
    if (debounceDelay.value > 0) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            emit('update:modelValue', val);
            emit('search', val);
        }, debounceDelay.value);
    } else {
        emit('update:modelValue', val);
    }
    emit('input', event);

    // If there's an active error, clear it as soon as the value becomes valid
    if (internalError.value) {
        const check = runCheck(val);
        if (!check) {
            internalError.value = '';
        }
    }
}

function handleBlur(event) {
    const val = event.target.value;
    internalError.value = runCheck(val) || '';
    emit('blur', event);
}

function handleChange(event) {
    emit('change', event);
}

function handleFocus(event) {
    emit('focus', event);
}

function clearValue() {
    emit('update:modelValue', '');
    emit('clear');
    emit('search', '');
    internalError.value = '';
    if (inputRef.value) {
        inputRef.value.value = '';
        inputRef.value.focus();
    }
}

function togglePassword() {
    isPasswordVisible.value = !isPasswordVisible.value;
    if (inputRef.value) {
        inputRef.value.focus();
    }
}

function validate() {
    const val = inputRef.value ? inputRef.value.value : props.modelValue;
    const err = runCheck(val);
    internalError.value = err || '';
    return !err;
}

function focus() {
    if (inputRef.value) {
        inputRef.value.focus();
    }
}

defineExpose({
    validate,
    focus,
    inputRef,
    isValid: computed(() => !displayError.value),
    error: displayError
});
</script>

<template>
    <div class="shared-field-wrap mb-3" :class="{ 'has-error': hasError }">
        <label v-if="label" :for="inputId" class="form-label shared-label">
            <span>{{ label }}</span>
            <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
        </label>

        <div class="input-container position-relative" :class="{ 'input-group': prefix || suffix }">
            <!-- Prefix Unit/Add-on (e.g. PHP ₱) -->
            <span v-if="prefix" class="input-group-text shared-prefix" aria-hidden="true">
                {{ prefix }}
            </span>

            <input
                :id="inputId"
                ref="inputRef"
                :name="name"
                :type="computedType"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :readonly="readonly"
                :autocomplete="computedAutocomplete"
                :inputmode="computedInputmode"
                :maxlength="maxlength"
                :minlength="minlength"
                :min="computedMin"
                :max="max"
                :step="step"
                :aria-invalid="hasError ? 'true' : 'false'"
                :aria-describedby="ariaDescribedby"
                class="form-control shared-input"
                :class="{
                    'is-invalid': hasError,
                    'pe-5': (type === 'password' && showPasswordToggle) || (clearable && modelValue)
                }"
                @input="handleInput"
                @blur="handleBlur"
                @change="handleChange"
                @focus="handleFocus"
            >

            <!-- Suffix Unit/Add-on (e.g. km, kg, min, PHP) -->
            <span v-if="suffix" class="input-group-text shared-suffix" aria-hidden="true">
                {{ suffix }}
            </span>

            <!-- Password show/hide toggle button -->
            <button
                v-if="type === 'password' && showPasswordToggle"
                type="button"
                class="btn-field-action"
                :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
                :title="isPasswordVisible ? 'Hide password' : 'Show password'"
                tabindex="0"
                @click="togglePassword"
            >
                <i :class="isPasswordVisible ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'" aria-hidden="true"></i>
            </button>

            <!-- Clear (X) button for search/filterable inputs -->
            <button
                v-else-if="clearable && modelValue"
                type="button"
                class="btn-field-action"
                aria-label="Clear input"
                title="Clear input"
                tabindex="0"
                @click="clearValue"
            >
                <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Helper text -->
        <div v-if="helperText && !hasError" :id="helperId" class="form-text shared-helper-text mt-1">
            {{ helperText }}
        </div>

        <!-- Accessible error message -->
        <div
            v-if="hasError"
            :id="errorId"
            class="invalid-feedback shared-error-text d-block mt-1"
            role="alert"
            aria-live="polite"
        >
            <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ displayError }}
        </div>
    </div>
</template>

<style scoped>
.shared-field-wrap {
    width: 100%;
}

.shared-label {
    font-weight: 500;
    margin-bottom: var(--s-1, 4px);
    display: inline-flex;
    align-items: center;
}

.shared-input {
    font-size: 16px !important; /* Prevents auto-zoom on iOS Safari */
    min-height: 44px;
    border-radius: var(--r-sm, 6px);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.shared-input:focus {
    outline: none;
    border-color: var(--route, #087F98);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--route, #087F98) 35%, transparent);
}

.shared-input.is-invalid {
    border-color: var(--danger, #A12C2A) !important;
}

.shared-input.is-invalid:focus {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger, #A12C2A) 25%, transparent) !important;
}

.shared-prefix,
.shared-suffix {
    min-height: 44px;
    background-color: var(--paper, #EEF2F5);
    border-color: var(--line, #D3DDE5);
    color: var(--ink-2, #4C6070);
    font-weight: 600;
    font-size: 14px;
    padding-inline: 14px;
}

.btn-field-action {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 6px;
    color: var(--ink-3, #617687);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    line-height: 1;
    z-index: 4;
}

.btn-field-action:hover,
.btn-field-action:focus-visible {
    color: var(--ink, #172B3A);
    outline: 2px solid var(--route, #087F98);
    outline-offset: 1px;
}

.shared-helper-text {
    color: var(--ink-2, #4C6070);
    font-size: var(--font-size-14, 14px);
}

.shared-error-text {
    color: var(--danger, #A12C2A);
    font-size: var(--font-size-14, 14px);
    font-weight: 500;
}
</style>
