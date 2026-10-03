import { CalendarDays, ChevronDown, Columns3 } from 'lucide-react';
import { useState } from 'react';
import { locale } from '../lib/format';
import type { Column, DateRangeFilter, FilterValue, SelectFilter, TableFilter } from '../protocol';
import { Button } from '../ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '../ui/dropdown-menu';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '../ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';

/** The "any" row's value in a single-select filter; Radix Select can't use an empty string. */
const ALL = '__all';

type DateRange = { from?: string; to?: string };

/** One filter control, by type. Every change is sent to the server straight away. */
export function FilterControl({
    filter,
    value,
    onChange,
}: {
    filter: TableFilter;
    value: FilterValue | undefined;
    onChange: (value: FilterValue) => void;
}) {
    if (filter.type === 'date_range') return <DateRangeControl filter={filter} value={value as DateRange | undefined} onChange={onChange} />;
    if (filter.multiple) return <MultiSelectControl filter={filter} value={(value as string[] | undefined) ?? []} onChange={onChange} />;

    return (
        <Select value={(value as string | undefined) ?? ALL} onValueChange={(next) => onChange(next === ALL ? '' : next)}>
            <SelectTrigger size="sm" aria-label={filter.label}>
                <SelectValue placeholder={filter.label} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{filter.label}</SelectItem>
                {filter.options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function MultiSelectControl({ filter, value, onChange }: { filter: SelectFilter; value: string[]; onChange: (value: string[]) => void }) {
    const selected = new Set(value);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="font-normal">
                    {filter.label}
                    {selected.size > 0 && (
                        <span className="bg-primary/10 text-primary rounded px-1.5 text-xs font-medium tabular-nums">{selected.size}</span>
                    )}
                    <ChevronDown className="opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-52">
                {filter.options.map((option) => (
                    <DropdownMenuCheckboxItem
                        key={option.value}
                        checked={selected.has(option.value)}
                        // Keep the menu open so several values can be picked in a row.
                        onSelect={(event) => event.preventDefault()}
                        onCheckedChange={(checked) => onChange(checked ? [...value, option.value] : value.filter((v) => v !== option.value))}
                    >
                        {option.label}
                    </DropdownMenuCheckboxItem>
                ))}
                {selected.size > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => onChange([])}>Clear</DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

const day = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const short = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString(locale, { day: 'numeric', month: 'short' });

/** Quick ranges, computed when rendered so "Today" is today. */
function presets(): [string, { from: string; to: string }][] {
    const today = new Date();
    const ago = (days: number) => day(new Date(today.getFullYear(), today.getMonth(), today.getDate() - days));
    return [
        ['Today', { from: day(today), to: day(today) }],
        ['Last 7 days', { from: ago(6), to: day(today) }],
        ['Last 30 days', { from: ago(29), to: day(today) }],
        ['This month', { from: day(new Date(today.getFullYear(), today.getMonth(), 1)), to: day(today) }],
    ];
}

/** A range as the filter button shows it, or null when nothing is set. */
function summarize(value: DateRange | undefined): string | null {
    if (value?.from && value?.to) return `${short(value.from)} – ${short(value.to)}`;
    if (value?.from) return `from ${short(value.from)}`;
    if (value?.to) return `until ${short(value.to)}`;
    return null;
}

function DateRangeControl({
    filter,
    value,
    onChange,
}: {
    filter: DateRangeFilter;
    value: DateRange | undefined;
    onChange: (value: DateRange) => void;
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState({ from: value?.from ?? '', to: value?.to ?? '' });
    const summary = summarize(value);

    const apply = (range: DateRange) => {
        onChange(range);
        setOpen(false);
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                if (next) setDraft({ from: value?.from ?? '', to: value?.to ?? '' });
                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="font-normal">
                    <CalendarDays className="opacity-60" />
                    {filter.label}
                    {summary && <span className="text-primary font-medium">{summary}</span>}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="grid w-80 gap-3">
                <div className="grid grid-cols-2 gap-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${filter.name}-from`}>From</Label>
                        <Input
                            id={`${filter.name}-from`}
                            type="date"
                            value={draft.from}
                            max={draft.to || undefined}
                            onChange={(e) => setDraft({ ...draft, from: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${filter.name}-to`}>To</Label>
                        <Input
                            id={`${filter.name}-to`}
                            type="date"
                            value={draft.to}
                            min={draft.from || undefined}
                            onChange={(e) => setDraft({ ...draft, to: e.target.value })}
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {presets().map(([label, range]) => (
                        <Button key={label} variant="secondary" size="xs" onClick={() => apply(range)}>
                            {label}
                        </Button>
                    ))}
                </div>
                <div className="flex justify-between border-t pt-3">
                    <Button variant="ghost" size="sm" onClick={() => apply({})} disabled={!summary}>
                        Clear
                    </Button>
                    <Button size="sm" onClick={() => apply(draft)}>
                        Apply
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

/** Show or hide toggleable columns. The table remembers the choice per page in localStorage. */
export function ColumnsMenu({ columns, hidden, onToggle }: { columns: Column[]; hidden: Set<string>; onToggle: (name: string) => void }) {
    const toggleable = columns.filter((column) => column.toggleable);
    if (toggleable.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="font-normal">
                    <Columns3 className="opacity-60" />
                    Columns
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
                <DropdownMenuLabel className="text-muted-foreground text-xs">Show columns</DropdownMenuLabel>
                {toggleable.map((column) => (
                    <DropdownMenuCheckboxItem
                        key={column.name}
                        checked={!hidden.has(column.name)}
                        onSelect={(event) => event.preventDefault()}
                        onCheckedChange={() => onToggle(column.name)}
                    >
                        {column.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Whether a filter value actually filters (empty strings, lists and ranges don't). */
export function isSet(value: FilterValue | undefined): boolean {
    if (Array.isArray(value)) return value.length > 0;
    if (value && typeof value === 'object') return Boolean(value.from || value.to);
    return Boolean(value);
}
