<script setup>
import { ref, computed, useId } from 'vue';
import { validate as runValidation } from './validators.js';

const props = defineProps({
    id: { type: String, default: null },
    name: { type: String, default: null },
    label: { type: String, default: '' },
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select an option' },
    placeholderDisabled: { type: Boolean, default: true },
    allowEmpty: { type: Boolean, default: false },
    helperText: { type: String, default: '' },
    error: { type: String, default: '' },
    rules: { type: Array, default: () => [] },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'change', 'blur', 'focus']);

const autoId = useId ? useId() : `select-${Math.random().toString(36).slice(2, 9)}`;
const selectId = computed(() => props.id || autoId);
const helperId = computed(() => `${selectId.value}-help`);
const errorId = computed(() => `${selectId.value}-error`);

const selectRef = ref(null);
const internalError = ref('');

const normalizedOptions = computed(() => {
    return (props.options || []).map((opt) => {
        if (opt !== null && typeof opt === 'object' && !Array.isArray(opt)) {
            return {
                value: opt.value !== undefined ? opt.value : opt.id,
                label: opt.label || opt.name || String(opt.value),
                disabled: Boolean(opt.disabled)
            };
        }
        if (Array.isArray(opt)) {
            return {
                value: opt[0],
                label: opt[1] !== undefined ? opt[1] : opt[0],
                disabled: Boolean(opt[2])
            };
        }
        return {
            value: opt,
            label: String(opt),
            disabled: false
        };
    });
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
    if (props.rules && props.rules.length > 0) {
        return runValidation(value, props.rules, props.label || 'This field');
    }
    if (props.required && (value === null || value === undefined || value === '')) {
        return `${props.label || 'This field'} is required.`;
    }
    return '';
}

function handleChange(event) {
    const val = event.target.value;
    emit('update:modelValue', val);
    emit('change', event);

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

function handleFocus(event) {
    emit('focus', event);
}

function validate() {
    const val = selectRef.value ? selectRef.value.value : props.modelValue;
    const err = runCheck(val);
    internalError.value = err || '';
    return !err;
}

function focus() {
    if (selectRef.value) {
        selectRef.value.focus();
    }
}

defineExpose({
    validate,
    focus,
    selectRef,
    isValid: computed(() => !displayError.value),
    error: displayError
});
</script>

<template>
    <div class="shared-field-wrap mb-3" :class="{ 'has-error': hasError }">
        <label v-if="label" :for="selectId" class="form-label shared-label">
            <span>{{ label }}</span>
            <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
        </label>

        <div class="select-container position-relative">
            <select
                :id="selectId"
                ref="selectRef"
                :name="name"
                :value="modelValue"
                :required="required"
                :disabled="disabled"
                :aria-invalid="hasError ? 'true' : 'false'"
                :aria-describedby="ariaDescribedby"
                class="form-select shared-select"
                :class="{ 'is-invalid': hasError }"
                @change="handleChange"
                @blur="handleBlur"
                @focus="handleFocus"
            >
                <!-- Disabled placeholder first option by default -->
                <option
                    v-if="placeholder"
                    value=""
                    :disabled="placeholderDisabled && !allowEmpty"
                    :selected="modelValue === '' || modelValue === null || modelValue === undefined"
                >
                    {{ placeholder }}
                </option>

                <option
                    v-for="(opt, idx) in normalizedOptions"
                    :key="`${opt.value}-${idx}`"
                    :value="opt.value"
                    :disabled="opt.disabled"
                    :selected="String(opt.value) === String(modelValue)"
                >
                    {{ opt.label }}
                </option>
            </select>
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

.shared-select {
    font-size: 16px !important; /* Prevents auto-zoom on mobile */
    min-height: 44px;
    border-radius: var(--r-sm, 6px);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.shared-select:focus {
    outline: none;
    border-color: var(--route, #087F98);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--route, #087F98) 35%, transparent);
}

.shared-select.is-invalid {
    border-color: var(--danger, #A12C2A) !important;
}

.shared-select.is-invalid:focus {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger, #A12C2A) 25%, transparent) !important;
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
