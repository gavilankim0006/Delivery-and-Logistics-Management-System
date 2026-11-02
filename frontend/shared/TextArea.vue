<script setup>
import { ref, computed, useId } from 'vue';
import { validate as runValidation } from './validators.js';

const props = defineProps({
    id: { type: String, default: null },
    name: { type: String, default: null },
    label: { type: String, default: '' },
    modelValue: { type: [String, Number], default: '' },
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
    rows: { type: [Number, String], default: 3 },
    showCounter: { type: Boolean, default: true }
});

const emit = defineEmits(['update:modelValue', 'input', 'change', 'blur', 'focus']);

const autoId = useId ? useId() : `textarea-${Math.random().toString(36).slice(2, 9)}`;
const textareaId = computed(() => props.id || autoId);
const helperId = computed(() => `${textareaId.value}-help`);
const errorId = computed(() => `${textareaId.value}-error`);
const counterId = computed(() => `${textareaId.value}-counter`);

const textareaRef = ref(null);
const internalError = ref('');

const characterCount = computed(() => {
    return props.modelValue !== null && props.modelValue !== undefined
        ? String(props.modelValue).length
        : 0;
});

const displayError = computed(() => props.error || internalError.value);
const hasError = computed(() => Boolean(displayError.value));

const ariaDescribedby = computed(() => {
    const ids = [];
    if (hasError.value) ids.push(errorId.value);
    if (props.helperText) ids.push(helperId.value);
    if (props.maxlength && props.showCounter) ids.push(counterId.value);
    return ids.length ? ids.join(' ') : null;
});

function runCheck(value) {
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
    emit('update:modelValue', val);
    emit('input', event);

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

function validate() {
    const val = textareaRef.value ? textareaRef.value.value : props.modelValue;
    const err = runCheck(val);
    internalError.value = err || '';
    return !err;
}

function focus() {
    if (textareaRef.value) {
        textareaRef.value.focus();
    }
}

defineExpose({
    validate,
    focus,
    textareaRef,
    isValid: computed(() => !displayError.value),
    error: displayError
});
</script>

<template>
    <div class="shared-field-wrap mb-3" :class="{ 'has-error': hasError }">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label v-if="label" :for="textareaId" class="form-label shared-label mb-0">
                <span>{{ label }}</span>
                <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
            </label>

            <!-- Live character counter in header -->
            <span
                v-if="maxlength && showCounter"
                :id="counterId"
                class="shared-char-counter small"
                :class="{ 'text-danger fw-semibold': Number(maxlength) - characterCount <= 10 }"
                aria-live="polite"
            >
                {{ characterCount }} / {{ maxlength }}
            </span>
        </div>

        <textarea
            :id="textareaId"
            ref="textareaRef"
            :name="name"
            :value="modelValue"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :readonly="readonly"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :maxlength="maxlength"
            :minlength="minlength"
            :rows="rows"
            :aria-invalid="hasError ? 'true' : 'false'"
            :aria-describedby="ariaDescribedby"
            class="form-control shared-textarea"
            :class="{ 'is-invalid': hasError }"
            @input="handleInput"
            @blur="handleBlur"
            @change="handleChange"
            @focus="handleFocus"
        ></textarea>

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
    display: inline-flex;
    align-items: center;
}

.shared-textarea {
    font-size: 16px !important; /* Prevents auto-zoom on mobile browsers */
    resize: vertical; /* Vertical-only resize per requirement */
    min-height: 80px;
    border-radius: var(--r-sm, 6px);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.shared-textarea:focus {
    outline: none;
    border-color: var(--route, #087F98);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--route, #087F98) 35%, transparent);
}

.shared-textarea.is-invalid {
    border-color: var(--danger, #A12C2A) !important;
}

.shared-textarea.is-invalid:focus {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger, #A12C2A) 25%, transparent) !important;
}

.shared-char-counter {
    color: var(--ink-3, #617687);
    font-variant-numeric: tabular-nums;
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
