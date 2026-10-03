// JSON GETs outside Inertia visits: a row form's values, a record page for the modal.
// Writes always go through Inertia's router, which carries the CSRF token.

/** An error from requestJson(), carrying the HTTP status. */
export interface HttpError extends Error {
    status: number;
}

/** Whether an error came from requestJson(). */
export const isHttpError = (error: unknown): error is HttpError => error instanceof Error && 'status' in error;

/** GET JSON from a Lodestone endpoint, throwing an HttpError on any non-2xx status. */
export async function requestJson<T>(url: string, init: { signal?: AbortSignal; headers?: Record<string, string> } = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        signal: init.signal,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...init.headers },
    });

    if (!response.ok) throw Object.assign(new Error(`Request failed (${response.status})`), { status: response.status });
    return response.json() as Promise<T>;
}
