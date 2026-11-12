/**
 * Unified Frontend API Client
 * Automatically manages CSRF tokens, JSON serialization, and standardized error handling.
 */

export class ApiError extends Error {
    constructor(message, status = 500, data = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

export function getCsrfToken() {
    if (typeof window !== 'undefined') {
        if (window.__APP_SHELL__?.csrfToken) {
            return window.__APP_SHELL__.csrfToken;
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta?.content) return meta.content;

        const input = document.querySelector('input[name="csrf_token"]');
        if (input?.value) return input.value;
    }
    return '';
}

export function getBaseUrl() {
    if (typeof window !== 'undefined') {
        const base = window.__APP_SHELL__?.baseUrl;
        if (base) {
            return base.endsWith('/') ? base : base + '/';
        }
    }
    return '/';
}

export function resolveUrl(path = '') {
    if (/^https?:\/\//i.test(path)) return path;
    const base = getBaseUrl();
    const cleanPath = String(path).replace(/^\/+/, '');
    return base + cleanPath;
}

async function request(path, options = {}) {
    const url = resolveUrl(path);
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});

    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }
    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    const csrf = getCsrfToken();
    if (csrf && !headers.has('X-CSRF-Token')) {
        headers.set('X-CSRF-Token', csrf);
    }

    let body = options.body;
    if (body !== undefined && body !== null) {
        if (body instanceof FormData) {
            if (csrf && !body.has('csrf_token')) {
                body.append('csrf_token', csrf);
            }
        } else if (typeof body === 'object' && !(body instanceof Blob) && !(body instanceof ArrayBuffer)) {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify({ ...body, ...(csrf ? { csrf_token: csrf } : {}) });
        }
    }

    const fetchConfig = {
        ...options,
        method,
        headers,
        body,
        credentials: options.credentials || 'same-origin'
    };

    let response;
    try {
        response = await fetch(url, fetchConfig);
    } catch (networkErr) {
        throw new ApiError('Network connection failed. Please check your internet connection.', 0, networkErr);
    }

    let data = null;
    const contentType = response.headers.get('Content-Type') || '';
    if (contentType.includes('application/json')) {
        try {
            data = await response.json();
        } catch {
            data = null;
        }
    } else {
        try {
            data = await response.text();
        } catch {
            data = null;
        }
    }

    if (!response.ok) {
        const message = (data && typeof data === 'object' && (data.error || data.message))
            ? (data.error || data.message)
            : `Request failed with status ${response.status}`;

        if (response.status === 401 && options.redirectOnUnauthorized !== false && typeof window !== 'undefined') {
            window.location.href = resolveUrl('login');
        }

        throw new ApiError(message, response.status, data);
    }

    return data;
}

export const api = {
    get(path, params = null, options = {}) {
        let finalPath = path;
        if (params && typeof params === 'object') {
            const search = new URLSearchParams();
            for (const [k, v] of Object.entries(params)) {
                if (v !== undefined && v !== null && v !== '') {
                    search.append(k, String(v));
                }
            }
            const query = search.toString();
            if (query) {
                finalPath += (finalPath.includes('?') ? '&' : '?') + query;
            }
        }
        return request(finalPath, { ...options, method: 'GET' });
    },

    post(path, body = null, options = {}) {
        return request(path, { ...options, method: 'POST', body });
    },

    put(path, body = null, options = {}) {
        return request(path, { ...options, method: 'PUT', body });
    },

    delete(path, body = null, options = {}) {
        return request(path, { ...options, method: 'DELETE', body });
    }
};

export default api;
