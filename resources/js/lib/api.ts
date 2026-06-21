import { config } from '@/config';
import type { Envelope } from '@/types/api';

/** Error thrown for any non-2xx API response, carrying the platform envelope. */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** First validation message for a field, if any. */
    fieldError(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

/** Emitted on 401 so the app can bounce to /login without circular imports. */
export const AUTH_EXPIRED_EVENT = 'sentrix:auth-expired';

function readCookie(name: string): string | undefined {
    const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : undefined;
}

let csrfPrimed = false;

/** Laravel issues the XSRF-TOKEN cookie from this endpoint; needed for writes. */
async function ensureCsrfCookie(): Promise<void> {
    if (csrfPrimed && readCookie('XSRF-TOKEN')) return;
    await fetch(config.csrfCookieUrl, { credentials: 'include' });
    csrfPrimed = true;
}

interface RequestOptions {
    params?: Record<string, string | number | boolean | undefined | null>;
    body?: unknown;
    signal?: AbortSignal;
}

function buildUrl(path: string, params?: RequestOptions['params']): string {
    const url = new URL(config.apiBase + path, window.location.origin);
    if (params) {
        for (const [key, value] of Object.entries(params)) {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, String(value));
            }
        }
    }
    return url.toString();
}

async function request<T>(method: string, path: string, options: RequestOptions = {}): Promise<Envelope<T>> {
    const isWrite = method !== 'GET' && method !== 'HEAD';
    if (isWrite) await ensureCsrfCookie();

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    const xsrf = readCookie('XSRF-TOKEN');
    if (isWrite && xsrf) headers['X-XSRF-TOKEN'] = xsrf;

    const response = await fetch(buildUrl(path, options.params), {
        method,
        headers,
        credentials: 'include',
        signal: options.signal,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });

    if (response.status === 204) {
        return { success: true, message: 'No Content', data: undefined as T };
    }

    const payload = (await response.json().catch(() => null)) as Envelope<T> | null;

    if (!response.ok) {
        if (response.status === 401) {
            window.dispatchEvent(new CustomEvent(AUTH_EXPIRED_EVENT));
        }
        throw new ApiError(
            response.status,
            payload?.message ?? response.statusText,
            (payload as unknown as { errors?: Record<string, string[]> })?.errors ?? {},
        );
    }

    if (!payload) {
        throw new ApiError(response.status, 'Malformed response');
    }
    return payload;
}

/** Thin verbs. Each returns the full envelope so callers can read meta/links. */
export const api = {
    get: <T>(path: string, params?: RequestOptions['params'], signal?: AbortSignal) =>
        request<T>('GET', path, { params, signal }),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, { body }),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, { body }),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, { body }),
    delete: <T>(path: string, body?: unknown) => request<T>('DELETE', path, { body }),
};
