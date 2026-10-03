import { cn } from 'cn';
import { Badge } from '../ui/badge';
import type { Tone } from '../protocol';

// Same --tone-* colours as sparklines and chart series.
const dot: Record<Tone, string> = {
    success: 'bg-(--tone-success)',
    danger: 'bg-(--tone-danger)',
    warning: 'bg-(--tone-warning)',
    info: 'bg-(--tone-info)',
    gray: 'bg-(--tone-gray)',
};

const text: Partial<Record<Tone, string>> = {
    danger: 'text-red-600 dark:text-red-400',
    warning: 'text-amber-700 dark:text-amber-400',
};

/** Values that read as in progress get a pulsing dot. */
const LIVE = new Set(['running', 'processing', 'queued', 'pending']);

/** The dot class for a tone. */
export function toneDot(tone: Tone) {
    return dot[tone];
}

/** The text class for a tone, where one stands out (danger and warning). */
export function toneText(tone?: Tone) {
    return tone ? text[tone] : undefined;
}

/** shadcn's outline badge with a status dot, as in the dashboard-01 data table. */
export function StatusBadge({ value, colors }: { value: unknown; colors?: Record<string, Tone> }) {
    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const key = String(value);
    const tone = colors?.[key] ?? 'gray';

    return (
        <Badge variant="outline" className={cn('gap-1.5 px-1.5', text[tone] ?? 'text-muted-foreground')}>
            <span className={cn('size-1.5 rounded-full', dot[tone], LIVE.has(key) && 'animate-pulse')} />
            {key.charAt(0).toUpperCase() + key.slice(1).replaceAll('_', ' ')}
        </Badge>
    );
}
