/**
 * Permintaan JSON kecil di luar navigasi Inertia (autosave draf, autocomplete,
 * unggah lampiran). Token CSRF diambil dari cookie XSRF-TOKEN milik Laravel.
 */
function xsrf(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

export class HttpError extends Error {
    constructor(
        message: string,
        public status: number,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

export async function json<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<T> {
    const isForm = body instanceof FormData;
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
        },
    };

    if (method !== 'GET' && body !== undefined) {
        init.body = isForm ? body : JSON.stringify(body);
    }

    const res = await fetch(url, init);

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        const errors = (data.errors ?? {}) as Record<string, string[]>;
        const first = Object.values(errors)[0]?.[0];

        throw new HttpError(
            first ?? data.message ?? `Permintaan gagal (${res.status}).`,
            res.status,
            errors,
        );
    }

    return res.status === 204 ? (undefined as T) : ((await res.json()) as T);
}
