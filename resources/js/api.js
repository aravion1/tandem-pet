export class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

export function apiMessage(payload, fallback = 'Не удалось выполнить запрос.') {
    return payload?.message ?? Object.values(payload?.errors ?? {})[0]?.[0] ?? fallback;
}

export async function request(path, options = {}, token = null) {
    const response = await fetch(`/api/${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...options.headers,
        },
    });
    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) throw new ApiError(response.status, apiMessage(payload));

    return payload;
}
