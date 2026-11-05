/**
 * Dispatch Global Form Enhancer
 * Provides consistent validation, accessible error handling, character counters,
 * password show/hide, search clear (X) buttons with 300ms debounce, and file upload previews
 * for all standard HTML forms across the application.
 */
(function () {
    'use strict';

    // Normalize Philippine phone numbers (strip spaces/dashes, standard 09XXXXXXXXX or +639XXXXXXXXX)
    function cleanPhPhone(val) {
        return (val || '').replace(/[\s\-\(\)\.]/g, '');
    }

    function isPhPhoneValid(val) {
        const cleaned = cleanPhPhone(val);
        return /^(09|\+639|639)\d{9}$/.test(cleaned);
    }

    function normalizePhPhone(val) {
        const cleaned = cleanPhPhone(val);
        const match = cleaned.match(/^(?:09|\+639|639)(\d{9})$/);
        return match ? `09${match[1]}` : cleaned;
    }

    // Set error on field
    function setFieldError(field, message) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        let feedback = field.parentElement ? field.parentElement.querySelector('.invalid-feedback.auto-feedback') : null;
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback auto-feedback d-block mt-1';
            feedback.setAttribute('role', 'alert');
            feedback.setAttribute('aria-live', 'polite');

            const errorId = (field.id || 'field-' + Math.random().toString(36).slice(2, 7)) + '-error';
            feedback.id = errorId;
            field.setAttribute('aria-describedby', errorId);

            // If input is inside an input-group, append feedback after the group
            if (field.parentElement && field.parentElement.classList.contains('input-group')) {
                field.parentElement.parentElement.appendChild(feedback);
            } else if (field.parentElement) {
                field.parentElement.appendChild(feedback);
            }
        }
        feedback.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>${message}`;
    }

    // Clear error from field
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const parent = field.parentElement ? (field.parentElement.classList.contains('input-group') ? field.parentElement.parentElement : field.parentElement) : null;
        if (parent) {
            const feedback = parent.querySelector('.invalid-feedback.auto-feedback');
            if (feedback) feedback.remove();
        }
    }

    // Validate a single field
    function getFieldValidationError(field) {
        // Skip disabled, readonly, hidden, or chat/custom-exempt fields
        if (field.disabled || field.readOnly || field.type === 'hidden') return null;
        if (field.hasAttribute('data-no-validate') || (field.closest && field.closest('[data-no-validate="true"]'))) return null;

        const val = field.value !== undefined ? String(field.value).trim() : '';
        const labelEl = field.id ? document.querySelector(`label[for="${field.id}"]`) : null;
        const fieldName = labelEl ? labelEl.textContent.replace('*', '').trim() : (field.getAttribute('placeholder') || field.name || 'This field');

        // Required check
        if (field.hasAttribute('required') && !val) {
            return `${fieldName} is required.`;
        }

        if (!val) {
            return null;
        }

        // Email check
        if (field.type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(val)) {
                return 'Please enter a valid email address.';
            }
        }

        // Phone check
        const isSupportPhone = field.name === 'support_phone' || field.id === 'support-phone' || field.dataset.allowLandline === 'true';
        if (field.type === 'tel' || field.name === 'phone' || (field.id && field.id.includes('phone'))) {
            if (isSupportPhone) {
                // Customer support phone: allow Philippine mobile, landline, or hotline (digits, spaces, dashes, parentheses, +)
                const cleaned = val.replace(/[\s\-\(\)\.]/g, '');
                const validHotline = /^\+?[0-9]{7,15}$/.test(cleaned) && /^[0-9+\s\-\(\)\.]+$/.test(val);
                if (!validHotline) {
                    return 'Please enter a valid phone number, landline, or hotline.';
                }
            } else {
                if (!isPhPhoneValid(val)) {
                    return 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
                }
            }
        }

        // Number check: reject negative values and check min/max
        if (field.type === 'number') {
            const num = parseFloat(val);
            if (!isNaN(num)) {
                const minAttr = field.getAttribute('min');
                const min = minAttr !== null ? parseFloat(minAttr) : 0;
                if (num < min || num < 0) {
                    return min > 0 ? `${fieldName} must be at least ${min}.` : `${fieldName} cannot be negative.`;
                }
                const max = field.getAttribute('max');
                if (max !== null && num > parseFloat(max)) {
                    return `${fieldName} cannot exceed ${max}.`;
                }
            }
        }

        // Password strength: apply ONLY to create-account, change-password, and admin temporary-password fields. Never on Sign in!
        if (field.type === 'password' && val) {
            const isSignIn = field.autocomplete === 'current-password'
                || field.id === 'login-password'
                || field.id === 'password' && document.querySelector('#vue-auth-root[data-page="login"]')
                || (field.form && field.form.action && field.form.action.includes('login'));

            if (!isSignIn) {
                const isNewPassword = field.autocomplete === 'new-password'
                    || field.name === 'password'
                    || field.name === 'password_confirm'
                    || (field.id && (field.id.includes('create') || field.id.includes('new') || field.id.includes('register')));

                if (isNewPassword) {
                    if (val.length < 8) {
                        return 'Password must be at least 8 characters long.';
                    }
                    if (!/[a-zA-Z]/.test(val) || !/\d/.test(val)) {
                        return 'Password must contain at least one letter and one number.';
                    }
                }
            }
        }

        // Min length
        const minlen = field.getAttribute('minlength');
        if (minlen && val.length < parseInt(minlen, 10)) {
            return `${fieldName} must be at least ${minlen} characters.`;
        }

        return null;
    }

    function validateField(field) {
        const error = getFieldValidationError(field);
        if (error) {
            setFieldError(field, error);
            return false;
        }
        clearFieldError(field);
        return true;
    }

    // Enhance all forms on the page
    function initFormEnhancements() {
        // Enforce autocomplete="off" on ID fields
        document.querySelectorAll('input[name="id_type"], input[name="id_number"], input#id_type, input#id_number').forEach((input) => {
            input.setAttribute('autocomplete', 'off');
        });

        // Enforce min="0" on number fields unless negative is specifically allowed
        document.querySelectorAll('input[type="number"]').forEach((input) => {
            if (!input.hasAttribute('min')) {
                input.setAttribute('min', '0');
            }
        });

        // 1. Password show/hide toggle
        document.querySelectorAll('input[type="password"]').forEach((input) => {
            if (input.dataset.enhancedPassword) return;
            input.dataset.enhancedPassword = 'true';

            const wrapper = document.createElement('div');
            wrapper.className = 'position-relative d-flex align-items-center w-100';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn-toggle-password';
            toggleBtn.setAttribute('aria-label', 'Show password');
            toggleBtn.title = 'Show password';
            toggleBtn.innerHTML = '<i class="bi bi-eye-fill" aria-hidden="true"></i>';

            toggleBtn.addEventListener('click', () => {
                const isShowing = input.type === 'text';
                input.type = isShowing ? 'password' : 'text';
                toggleBtn.setAttribute('aria-label', isShowing ? 'Show password' : 'Hide password');
                toggleBtn.title = isShowing ? 'Show password' : 'Hide password';
                toggleBtn.innerHTML = isShowing
                    ? '<i class="bi bi-eye-fill" aria-hidden="true"></i>'
                    : '<i class="bi bi-eye-slash-fill" aria-hidden="true"></i>';
                input.focus();
            });

            wrapper.appendChild(toggleBtn);
        });

        // 2. Search clear (X) buttons and 300ms debounce
        document.querySelectorAll('input[type="search"]').forEach((input) => {
            if (input.dataset.enhancedSearch) return;
            input.dataset.enhancedSearch = 'true';

            if (!input.placeholder) {
                input.placeholder = 'Search by keyword, status, or reference...';
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'position-relative d-flex align-items-center w-100';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'btn-clear-search';
            clearBtn.setAttribute('aria-label', 'Clear search');
            clearBtn.title = 'Clear search';
            clearBtn.innerHTML = '<i class="bi bi-x-circle-fill" aria-hidden="true"></i>';
            clearBtn.style.display = input.value ? 'inline-flex' : 'none';

            let debounceTimer = null;
            input.addEventListener('input', () => {
                clearBtn.style.display = input.value ? 'inline-flex' : 'none';
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    input.dispatchEvent(new CustomEvent('debounced-search', { bubbles: true, detail: { value: input.value } }));
                }, 300);
            });

            clearBtn.addEventListener('click', () => {
                input.value = '';
                clearBtn.style.display = 'none';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.dispatchEvent(new CustomEvent('debounced-search', { bubbles: true, detail: { value: '' } }));
                input.focus();
            });

            wrapper.appendChild(clearBtn);
        });

        // 3. Textarea vertical-only resize & live character counter (skip chat boxes with data-no-validate)
        document.querySelectorAll('textarea').forEach((textarea) => {
            if (textarea.dataset.enhancedTextarea) return;
            textarea.dataset.enhancedTextarea = 'true';
            if (textarea.hasAttribute('data-no-validate') || (textarea.closest && textarea.closest('[data-no-validate="true"]'))) {
                return;
            }
            textarea.style.resize = 'vertical';

            const max = textarea.getAttribute('maxlength');
            if (max) {
                const isSms = textarea.name === 'message' || textarea.id.includes('sms') || parseInt(max, 10) === 160;
                const counter = document.createElement('div');
                counter.className = 'textarea-char-counter small text-muted text-end mt-1';
                counter.textContent = isSms
                    ? `${textarea.value.length} / ${max} (${Math.max(1, Math.ceil(textarea.value.length / 160))} SMS)`
                    : `${textarea.value.length} / ${max}`;
                textarea.parentNode.insertBefore(counter, textarea.nextSibling);

                textarea.addEventListener('input', () => {
                    counter.textContent = isSms
                        ? `${textarea.value.length} / ${max} (${Math.max(1, Math.ceil(textarea.value.length / 160))} SMS)`
                        : `${textarea.value.length} / ${max}`;
                    if (parseInt(max, 10) - textarea.value.length <= 10) {
                        counter.classList.add('text-danger', 'fw-semibold');
                    } else {
                        counter.classList.remove('text-danger', 'fw-semibold');
                    }
                });
            }
        });

        // 4. Dropdowns: ensure disabled placeholder first option
        document.querySelectorAll('select').forEach((select) => {
            if (select.dataset.enhancedSelect) return;
            select.dataset.enhancedSelect = 'true';

            if (select.hasAttribute('required') && select.options.length > 0) {
                const first = select.options[0];
                if (first.value === '') {
                    first.disabled = true;
                }
            }
        });

        // 5. File upload enhancement: accepted types, size limit, preview, remove button
        document.querySelectorAll('input[type="file"]').forEach((fileInput) => {
            if (fileInput.dataset.enhancedFile) return;
            fileInput.dataset.enhancedFile = 'true';

            const accept = fileInput.getAttribute('accept') || 'image/jpeg,image/png,image/webp,application/pdf';
            const maxSizeMb = parseInt(fileInput.dataset.maxSizeMb || '5', 10);

            // Container for preview
            const previewCard = document.createElement('div');
            previewCard.className = 'file-enhancer-preview d-none mt-2 p-2 bg-light border rounded align-items-center gap-3';
            fileInput.parentNode.insertBefore(previewCard, fileInput.nextSibling);

            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0];
                if (!file) {
                    previewCard.classList.add('d-none');
                    previewCard.innerHTML = '';
                    return;
                }

                // Check size
                if (file.size > maxSizeMb * 1024 * 1024) {
                    const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                    setFieldError(fileInput, `File size (${sizeMb} MB) exceeds maximum allowed limit of ${maxSizeMb} MB.`);
                    fileInput.value = '';
                    previewCard.classList.add('d-none');
                    previewCard.innerHTML = '';
                    return;
                }

                // Check type
                if (accept) {
                    const ext = '.' + file.name.split('.').pop().toLowerCase();
                    const mime = (file.type || '').toLowerCase();
                    const validList = accept.split(',').map((s) => s.trim().toLowerCase());
                    const isValid = validList.some((t) => {
                        if (t.startsWith('.')) return ext === t;
                        if (t.endsWith('/*')) return mime.startsWith(t.replace('/*', '') + '/');
                        return mime === t;
                    });
                    if (!isValid) {
                        setFieldError(fileInput, `Invalid file format. Accepted formats: ${accept}`);
                        fileInput.value = '';
                        previewCard.classList.add('d-none');
                        previewCard.innerHTML = '';
                        return;
                    }
                }

                clearFieldError(fileInput);

                // Build preview
                const isImage = file.type.startsWith('image/');
                const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                const sizeStr = file.size < 1024 * 1024 ? `${(file.size / 1024).toFixed(1)} KB` : `${(file.size / (1024 * 1024)).toFixed(2)} MB`;

                previewCard.classList.remove('d-none');
                previewCard.classList.add('d-flex');
                previewCard.innerHTML = `
                    <div class="file-enhancer-thumb rounded overflow-hidden d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #eef2f5;">
                        ${isImage ? `<div class="file-enhancer-img-slot d-flex align-items-center justify-content-center w-100 h-100"><i class="bi bi-image fs-3 text-secondary"></i></div>` : (isPdf ? `<i class="bi bi-file-earmark-pdf-fill fs-2 text-danger"></i>` : `<i class="bi bi-file-earmark-text-fill fs-2 text-primary"></i>`)}
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-semibold text-truncate small">${file.name}</div>
                        <div class="text-muted small">${sizeStr}</div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm file-enhancer-remove">
                        <i class="bi bi-trash3-fill me-1"></i>Remove
                    </button>
                `;

                if (isImage) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const slot = previewCard.querySelector('.file-enhancer-img-slot');
                        if (slot) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = file.name;
                            img.style.width = '100%';
                            img.style.height = '100%';
                            img.style.objectFit = 'cover';
                            slot.innerHTML = '';
                            slot.appendChild(img);
                        }
                    };
                    reader.readAsDataURL(file);
                }

                const removeBtn = previewCard.querySelector('.file-enhancer-remove');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        fileInput.value = '';
                        previewCard.classList.add('d-none');
                        previewCard.classList.remove('d-flex');
                        previewCard.innerHTML = '';
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
            });
        });

        // 6. Validation on blur and immediate clearing when valid
        document.querySelectorAll('input, select, textarea').forEach((field) => {
            if (field.dataset.enhancedValidation) return;
            field.dataset.enhancedValidation = 'true';
            if (field.closest && field.closest('#vue-auth-root, [data-vue-form]')) return;
            if (field.hasAttribute('data-no-validate') || (field.closest && field.closest('[data-no-validate="true"]'))) {
                return;
            }

            field.addEventListener('blur', () => {
                validateField(field);
            });

            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    if (!getFieldValidationError(field)) {
                        clearFieldError(field);
                    }
                }
            });

            field.addEventListener('change', () => {
                if (field.classList.contains('is-invalid')) {
                    if (!getFieldValidationError(field)) {
                        clearFieldError(field);
                    }
                }
            });
        });

        // 7. Form submission: validation, first invalid field scroll/focus, submit loading state
        document.querySelectorAll('form').forEach((form) => {
            if (form.dataset.enhancedSubmit) return;
            form.dataset.enhancedSubmit = 'true';

            form.addEventListener('submit', (e) => {
                if (form.getAttribute('data-no-validate') === 'true') return;
                // Vue forms own their validation and error rendering. The global enhancer
                // must not attach a second validation pass or duplicate feedback.
                if (form.closest('#vue-auth-root, [data-vue-form]')) return;

                let firstInvalid = null;
                const fields = form.querySelectorAll('input, select, textarea');
                fields.forEach((field) => {
                    const valid = validateField(field);
                    if (!valid && !firstInvalid) {
                        firstInvalid = field;
                    }
                });

                if (firstInvalid) {
                    e.preventDefault();
                    e.stopPropagation();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                    return;
                }

                // Normalize phone numbers before submit (skip customer support landline/hotline)
                form.querySelectorAll('input[type="tel"], input[name="phone"]').forEach((phoneInput) => {
                    const isSupport = phoneInput.name === 'support_phone' || phoneInput.id === 'support-phone' || phoneInput.dataset.allowLandline === 'true';
                    if (phoneInput.value && !isSupport) {
                        phoneInput.value = normalizePhPhone(phoneInput.value);
                    }
                });

                // Show submitting state on submit button
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !form.hasAttribute('data-no-disable')) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('aria-busy', 'true');
                    const origText = submitBtn.textContent.trim();
                    submitBtn.dataset.originalText = origText;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFormEnhancements);
    } else {
        initFormEnhancements();
    }
})();
