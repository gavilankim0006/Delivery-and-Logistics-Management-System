<script setup>
import { ref, computed, useId } from 'vue';
import { fileValidation } from './validators.js';

const props = defineProps({
    id: { type: String, default: null },
    name: { type: String, default: null },
    label: { type: String, default: 'Upload document' },
    accept: { type: String, default: 'image/jpeg,image/png,image/webp,application/pdf' },
    allowedTypes: { type: Array, default: null },
    capture: { type: String, default: null },
    maxSizeMb: { type: Number, default: 5 },
    helperText: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'change', 'blur', 'clear']);

const autoId = useId ? useId() : `file-${Math.random().toString(36).slice(2, 9)}`;
const fileInputId = computed(() => props.id || autoId);
const helperId = computed(() => `${fileInputId.value}-help`);
const errorId = computed(() => `${fileInputId.value}-error`);

const fileInputRef = ref(null);
const internalError = ref('');
const selectedFile = ref(null);
const previewUrl = ref('');
const isPdf = ref(false);

const displayError = computed(() => props.error || internalError.value);
const hasError = computed(() => Boolean(displayError.value));

const formattedAcceptList = computed(() => {
    if (props.allowedTypes && props.allowedTypes.length > 0) {
        return props.allowedTypes;
    }
    if (!props.accept) return [];
    return props.accept.split(',').map((item) => item.trim());
});

const acceptNotice = computed(() => {
    const list = formattedAcceptList.value.flatMap((item) => {
        if (item === 'application/pdf' || item === '.pdf') return ['PDF'];
        if (item === 'image/*' || item === 'image/jpeg' || item === '.jpg' || item === '.jpeg') return ['JPG'];
        if (item === 'image/png' || item === '.png') return ['PNG'];
        if (item === 'image/webp' || item === '.webp') return ['WEBP'];
        if (item.includes('image/') || item.startsWith('.')) {
            return [item.replace('image/', '').replace('.', '').toUpperCase()];
        }
        return [item];
    });
    const unique = [...new Set(list)];
    return `Accepted formats: ${unique.join(', ')} (Max ${props.maxSizeMb} MB)`;
});

const formattedFileSize = computed(() => {
    if (!selectedFile.value) return '';
    const bytes = selectedFile.value.size;
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
});

function handleFileChange(event) {
    const files = event.target.files;
    if (!files || files.length === 0) {
        clearFile();
        return;
    }

    const file = files[0];
    const validationError = fileValidation(file, {
        allowedTypes: formattedAcceptList.value,
        maxSizeMb: props.maxSizeMb
    });

    if (validationError) {
        internalError.value = validationError;
        clearFile(false);
        return;
    }

    internalError.value = '';
    selectedFile.value = file;
    isPdf.value = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewUrl.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        previewUrl.value = '';
    }

    emit('change', file);
    emit('update:modelValue', file);
}

function clearFile(clearNativeInput = true) {
    selectedFile.value = null;
    previewUrl.value = '';
    isPdf.value = false;
    if (clearNativeInput && fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    emit('clear');
    emit('update:modelValue', null);
}

function handleBlur(event) {
    if (props.required && !selectedFile.value) {
        internalError.value = `${props.label || 'Document'} is required.`;
    }
    emit('blur', event);
}

function validate() {
    if (props.required && !selectedFile.value) {
        internalError.value = `${props.label || 'Document'} is required.`;
        return false;
    }
    if (selectedFile.value) {
        const err = fileValidation(selectedFile.value, {
            allowedTypes: formattedAcceptList.value,
            maxSizeMb: props.maxSizeMb
        });
        if (err) {
            internalError.value = err;
            return false;
        }
    }
    internalError.value = '';
    return true;
}

function focus() {
    if (fileInputRef.value) {
        fileInputRef.value.focus();
    }
}

defineExpose({
    validate,
    focus,
    clearFile,
    fileInputRef,
    selectedFile,
    isValid: computed(() => !displayError.value),
    error: displayError
});
</script>

<template>
    <div class="shared-field-wrap mb-3" :class="{ 'has-error': hasError }">
        <label :for="fileInputId" class="form-label shared-label">
            <span>{{ label }}</span>
            <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
        </label>

        <!-- Requirements hint badge -->
        <div class="small text-muted mb-2">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ acceptNotice }}
        </div>

        <!-- Hidden native file input for styling control -->
        <div class="file-drop-area p-3 surface border rounded text-center position-relative" :class="{ 'border-danger': hasError }">
            <input
                :id="fileInputId"
                ref="fileInputRef"
                :name="name"
                type="file"
                :accept="accept"
                :capture="capture"
                :required="required && !selectedFile"
                :disabled="disabled"
                :aria-invalid="hasError ? 'true' : 'false'"
                :aria-describedby="hasError ? errorId : (helperText ? helperId : null)"
                class="shared-native-file-input"
                @change="handleFileChange"
                @blur="handleBlur"
            >

            <!-- State when no valid file selected -->
            <div v-if="!selectedFile" class="file-picker-prompt py-2">
                <i class="bi bi-cloud-arrow-up-fill fs-2 text-primary d-block mb-1" aria-hidden="true"></i>
                <span class="btn btn-outline-primary btn-sm mb-1">Choose file</span>
                <p class="small text-muted mb-0">or click to browse from device</p>
            </div>

            <!-- State when valid file is selected -->
            <div v-else class="file-preview-card text-start d-flex align-items-center gap-2 p-2 bg-light rounded">
                <!-- Image thumbnail preview -->
                <div v-if="previewUrl" class="file-thumb-wrap">
                    <img :src="previewUrl" :alt="selectedFile.name" class="file-thumb-img rounded">
                </div>

                <!-- PDF document icon preview -->
                <div v-else-if="isPdf" class="file-thumb-icon text-danger fs-1">
                    <i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i>
                </div>

                <!-- Generic file icon -->
                <div v-else class="file-thumb-icon text-primary fs-1">
                    <i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i>
                </div>

                <!-- File metadata -->
                <div class="file-info flex-grow-1 overflow-hidden">
                    <div class="fw-semibold text-truncate" :title="selectedFile.name">{{ selectedFile.name }}</div>
                    <div class="small text-muted">{{ formattedFileSize }}</div>
                </div>

                <!-- Remove button -->
                <button
                    type="button"
                    class="btn btn-outline-danger btn-sm flex-shrink-0"
                    title="Remove selected file"
                    aria-label="Remove selected file"
                    @click="clearFile"
                >
                    <i class="bi bi-trash3-fill me-1" aria-hidden="true"></i>Remove
                </button>
            </div>
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

.file-drop-area {
    background-color: var(--surface, #FFFFFF);
    border: 2px dashed var(--line, #D3DDE5);
    border-radius: var(--r-md, 10px);
    transition: border-color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.file-drop-area:hover,
.file-drop-area:focus-within {
    border-color: var(--route, #087F98);
    background-color: color-mix(in srgb, var(--route, #087F98) 4%, var(--surface, #FFFFFF));
}

.shared-native-file-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 1;
}

.file-preview-card {
    position: relative;
    z-index: 2; /* Sits above the invisible file input so Remove button is clickable */
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
}

.file-info {
    min-width: 0;
}

.file-info > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-thumb-wrap {
    width: 56px;
    height: 56px;
    min-width: 56px;
    max-width: 56px;
    max-height: 56px;
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #000;
    border-radius: var(--r-sm, 6px);
}

.file-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-thumb-icon {
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
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
