import { useSyncExternalStore } from 'react';

/** The app's locale, from <html lang> (app()->getLocale()), so numbers and dates read the way the app does. */
export const locale = document.documentElement.lang || undefined;

const number = new Intl.NumberFormat(locale);

/** Format a raw schema value using a format hint from the server ("number", "duration", "money:GBP", ...). */
export function formatValue(value: unknown, format?: string): string {
    if (value === null || value === undefined || value === '') return '—';

    const [kind, arg] = (format ?? '').split(':');
    const n = Number(value);

    switch (kind) {
        case 'number':
            return number.format(n);
        case 'duration':
            return duration(Math.round(n));
        case 'bytes':
            return bytes(n);
        case 'percent':
            return `${Math.round(n * 100)}%`;
        case 'money':
            return new Intl.NumberFormat(locale, { style: 'currency', currency: arg || 'GBP' }).format(n);
        case 'relative':
            return relative(new Date(String(value)).getTime());
        case 'date':
            return new Date(String(value)).toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
        case 'datetime':
            return new Date(String(value)).toLocaleString(locale, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
        default:
            return String(value);
    }
}

const pad = (n: number) => String(n).padStart(2, '0');

function bytes(n: number): string {
    if (n < 1e3) return `${n} B`;
    if (n < 1e6) return `${Math.round(n / 1e3)} KB`;
    if (n < 1e9) return `${(n / 1e6).toFixed(1)} MB`;
    return `${(n / 1e9).toFixed(1)} GB`;
}

function duration(s: number): string {
    if (s < 60) return `${s}s`;
    if (s < 3600) return `${Math.floor(s / 60)}m ${pad(s % 60)}s`;
    return `${Math.floor(s / 3600)}h ${pad(Math.floor((s % 3600) / 60))}m`;
}

function relative(time: number): string {
    const seconds = (Date.now() - time) / 1000;
    const s = Math.abs(seconds);
    if (s < 45) return 'just now';

    let span: string;
    if (s < 3600) span = `${Math.round(s / 60)}m`;
    else if (s < 86400) span = `${Math.round(s / 3600)}h`;
    else span = `${Math.round(s / 86400)}d`;

    return seconds > 0 ? `${span} ago` : `in ${span}`;
}

// One shared 30s clock so relative times stay fresh without a timer per cell.
const listeners = new Set<() => void>();
let now = Date.now();
setInterval(() => {
    now = Date.now();
    listeners.forEach((listener) => listener());
}, 30_000);

/** The current time, refreshed every 30 seconds. Subscribe from a cell so its relative time re-renders. */
export function useNow(): number {
    return useSyncExternalStore(
        (listener) => (listeners.add(listener), () => listeners.delete(listener)),
        () => now,
    );
}
