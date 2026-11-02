/**
 * Form field validators and normalizers
 */

export function required(value, label = 'This field') {
    if (value === null || value === undefined) {
        return `${label} is required.`;
    }
    if (typeof value === 'string' && value.trim() === '') {
        return `${label} is required.`;
    }
    if (Array.isArray(value) && value.length === 0) {
        return `${label} is required.`;
    }
    if (value instanceof FileList && value.length === 0) {
        return `${label} is required.`;
    }
    return null;
}

export function email(value) {
    if (!value || (typeof value === 'string' && value.trim() === '')) {
        return null;
    }
    const trimmed = String(value).trim();
    // RFC 5322 compatible regex
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
    if (!emailRegex.test(trimmed)) {
        return 'Please enter a valid email address.';
    }
    return null;
}

/**
 * Validates Philippine phone numbers:
 * Must be 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (13 chars) or 639XXXXXXXXX (12 digits)
 */
export function phPhone(value) {
    if (!value || (typeof value === 'string' && value.trim() === '')) {
        return null;
    }
    const stripped = String(value).replace(/[\s\-\(\)\.]/g, '');
    const phRegex = /^(09|\+639|639)\d{9}$/;
    if (!phRegex.test(stripped)) {
        return 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
    }
    return null;
}

/**
 * Normalizes Philippine phone number for Semaphore SMS / backend storage.
 * Defaults to 09XXXXXXXXX or optionally +639XXXXXXXXX.
 */
export function normalizePhPhone(value, prefix = '09') {
    if (!value) return '';
    const stripped = String(value).replace(/[\s\-\(\)\.]/g, '');
    const match = stripped.match(/^(?:09|\+639|639)(\d{9})$/);
    if (!match) return stripped;
    const digits = match[1];
    if (prefix === '+63' || prefix === '+639') {
        return `+639${digits}`;
    }
    return `09${digits}`;
}

export function minLength(min, label = 'This field') {
    return (value) => {
        if (!value) return null;
        const str = String(value);
        if (str.length < min) {
            return `${label} must be at least ${min} characters.`;
        }
        return null;
    };
}

export function maxLength(max, label = 'This field') {
    return (value) => {
        if (!value) return null;
        const str = String(value);
        if (str.length > max) {
            return `${label} cannot exceed ${max} characters.`;
        }
        return null;
    };
}

export function numberRange(min = 0, max, label = 'Value') {
    return (value) => {
        if (value === null || value === undefined || value === '') return null;
        const num = Number(value);
        if (Number.isNaN(num)) {
            return `${label} must be a valid number.`;
        }
        if (num < 0) {
            return `${label} cannot be negative.`;
        }
        if (min !== undefined && min !== null && num < min) {
            return `${label} must be at least ${min}.`;
        }
        if (max !== undefined && max !== null && num > max) {
            return `${label} cannot exceed ${max}.`;
        }
        return null;
    };
}

export function passwordStrength(value) {
    if (!value) return null;
    const str = String(value);
    if (str.length < 8) {
        return 'Password must be at least 8 characters long.';
    }
    const hasLetter = /[a-zA-Z]/.test(str);
    const hasNumber = /\d/.test(str);
    if (!hasLetter || !hasNumber) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

export function fileValidation(file, { allowedTypes = [], maxSizeMb = 5 } = {}) {
    if (!file) return null;
    if (maxSizeMb && file.size > maxSizeMb * 1024 * 1024) {
        return `File size (${(file.size / (1024 * 1024)).toFixed(1)} MB) exceeds maximum allowed size of ${maxSizeMb} MB.`;
    }
    if (allowedTypes && allowedTypes.length > 0) {
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        const mime = (file.type || '').toLowerCase();
        const matches = allowedTypes.some((t) => {
            const rule = t.trim().toLowerCase();
            if (rule.startsWith('.')) return ext === rule;
            if (rule.endsWith('/*')) {
                const group = rule.replace('/*', '');
                return mime.startsWith(group + '/');
            }
            return mime === rule;
        });
        if (!matches) {
            return `Invalid file type. Accepted types: ${allowedTypes.join(', ')}.`;
        }
    }
    return null;
}

/**
 * Evaluates an array of rules against a value.
 * Stops at first failure and returns error string, or null if all pass.
 */
export function validate(value, rules = [], label = 'This field') {
    for (const rule of rules) {
        if (typeof rule === 'function') {
            const err = rule(value, label);
            if (err) return err;
        }
    }
    return null;
}
