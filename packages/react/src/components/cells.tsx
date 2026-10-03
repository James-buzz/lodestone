import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { MouseEvent, ReactNode } from 'react';
import { formatValue, useNow } from '../lib/format';
import type { CellComponent } from '../registry';
import { Badge } from '../ui/badge';
import { CopyButton, isInternal, UserChip } from './common';
import { StatusBadge } from './status-badge';

const stop = (event: MouseEvent) => event.stopPropagation();

const link = 'font-medium underline decoration-foreground/25 underline-offset-4 hover:decoration-foreground';

/** TextColumn: a formatted value with its prefix, suffix, limit, link, avatar, description and copy button. */
export const TextCell: CellComponent = ({ column, value, row }) => {
    // Subscribe to the shared clock so relative times re-render.
    useNow();

    const empty = value === null || value === undefined || value === '';
    let text = empty && column.placeholder ? String(column.placeholder) : formatValue(value, column.format);

    if (!empty && column.prefix) text = column.prefix + text;
    if (!empty && column.suffix) text += column.suffix;

    const full = text;
    if (column.limit && text.length > column.limit) text = text.slice(0, column.limit - 1) + '…';

    const url = empty ? undefined : (row[`${column.name}:url`] as string | undefined);
    const content = (
        <span
            title={full !== text ? full : undefined}
            className={cn(
                column.mono && 'font-mono text-xs',
                (column.muted || empty) && 'text-muted-foreground',
                column.format && 'tabular-nums',
                column.strong && 'font-medium',
            )}
        >
            {text}
        </span>
    );

    // Column::url(): same-origin links navigate with Inertia; others open in a new tab.
    let cell: ReactNode = content;
    if (url && isInternal(url)) {
        cell = (
            <Link href={url} prefetch onClick={stop} className={link}>
                {content}
            </Link>
        );
    } else if (url) {
        cell = (
            <a href={url} target="_blank" rel="noreferrer" onClick={stop} className={link}>
                {content}
            </a>
        );
    }

    const description = row[`${column.name}:description`] as string | undefined;

    if (column.avatar && !empty) {
        cell = (
            <UserChip name={full} image={row[`${column.name}:avatar`] as string | undefined} description={description}>
                {cell}
            </UserChip>
        );
    } else if (description) {
        cell = (
            <span className="flex min-w-0 flex-col">
                {cell}
                <span className="text-muted-foreground text-xs">{description}</span>
            </span>
        );
    }

    if (!column.copyable || empty) return cell;

    return (
        <span className="group/copy inline-flex max-w-full items-center gap-1">
            {cell}
            <CopyButton
                value={String(value)}
                label={`Copy ${column.label.toLowerCase()}`}
                className="text-muted-foreground -my-1 opacity-0 group-hover/copy:opacity-100 focus-visible:opacity-100 pointer-coarse:opacity-100"
            />
        </span>
    );
};

/** BadgeColumn: a status badge coloured by the column's colors map. */
export const BadgeCell: CellComponent = ({ column, value }) => <StatusBadge value={value} colors={column.colors} />;

/** TagsColumn: a badge per value. Values with a colour get a status dot; the rest are plain. */
export const TagsCell: CellComponent = ({ column, value }) => {
    const tags = Array.isArray(value) ? value.map(String) : [];
    if (tags.length === 0) return <span className="text-muted-foreground">{column.placeholder ?? '—'}</span>;

    const limit = typeof column.limit === 'number' ? column.limit : tags.length;
    const rest = tags.slice(limit);

    return (
        <span className="flex items-center gap-1 in-[dd]:flex-wrap">
            {tags.slice(0, limit).map((tag, i) =>
                column.colors?.[tag] ? (
                    <StatusBadge key={i} value={tag} colors={column.colors} />
                ) : (
                    <Badge key={i} variant="outline" className="text-muted-foreground px-1.5">
                        {tag}
                    </Badge>
                ),
            )}
            {rest.length > 0 && (
                <span title={rest.join(', ')} className="text-muted-foreground text-xs">
                    +{rest.length}
                </span>
            )}
        </span>
    );
};

/** The built-in cells, keyed by column type. */
export const defaultCells = { text: TextCell, badge: BadgeCell, tags: TagsCell };
