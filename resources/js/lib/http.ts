/**
 * Minimal JSON client for admin endpoints that are not Inertia pages
 * (media uploads, picker listings). Sends Laravel's XSRF token from its cookie.
 */
export class HttpError extends Error {
    constructor(
        message: string,
        public status: number,
        public data: any,
    ) {
        super(message);
    }

    /** First validation message for a field, or the general message. */
    fieldError(field: string): string {
        return this.data?.errors?.[field]?.[0] ?? this.message;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

type Options = {
    method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
    body?: Record<string, unknown> | FormData;
    onProgress?: (percent: number) => void;
};

/** XHR rather than fetch so uploads can report progress. */
export function request<T = any>(url: string, { method = 'GET', body, onProgress }: Options = {}): Promise<T> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open(method, url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());

        if (body && !(body instanceof FormData)) {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }

        if (onProgress) {
            xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(Math.round((e.loaded / e.total) * 100));
        }

        xhr.onload = () => {
            let data: any = null;

            try {
                data = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                data = null;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(data as T);
            } else {
                const message =
                    data?.message ??
                    (xhr.status === 413 ? 'The file is too large for the server.' : `Request failed (${xhr.status}).`);
                reject(new HttpError(message, xhr.status, data));
            }
        };
        xhr.onerror = () => reject(new HttpError('Network error. Check your connection and try again.', 0, null));

        xhr.send(body instanceof FormData ? body : body ? JSON.stringify(body) : null);
    });
}

/** Build FormData from a flat object, nesting plain objects as key[sub]. */
export function formData(values: Record<string, unknown>): FormData {
    const data = new FormData();

    const append = (key: string, value: unknown) => {
        if (value === null || value === undefined) {
            return;
        }

        if (value instanceof Blob) {
            data.append(key, value);
        } else if (typeof value === 'object') {
            Object.entries(value as Record<string, unknown>).forEach(([k, v]) => append(`${key}[${k}]`, v));
        } else {
            data.append(key, String(value));
        }
    };

    Object.entries(values).forEach(([k, v]) => append(k, v));

    return data;
}
