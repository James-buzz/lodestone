import { Link, router, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ExternalLink,
    MoreHorizontal,
    PanelTop,
    Search,
} from 'lucide-react';
import { useContext, useEffect, useRef, useState, type MouseEvent } from 'react';
import type { Column, PageProps, Row, TableNode, TableState } from '../protocol';
import { storage } from '../lib/storage';
import { useRegistry } from '../registry';
import { Button } from '../ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Checkbox } from '../ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '../ui/dropdown-menu';
import { Input } from '../ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';
import { locale } from '../lib/format';
import { EmptyState } from './common';
import { ColumnsMenu, FilterControl, isSet } from './table-controls';
import { ButtonMenuItem, LodestoneButton, usePress } from './buttons';
import { InModalContext, useRecordModal } from './record-modal';

/** The parts of table state a visit changes. The page resets to 1 unless given. */
type Visit = Partial<TableState> & { page?: number };

/** The current URL without this table's own params, so several tables can share a page. */
function urlWithout(key: string): string {
    const url = new URL(window.location.href);
    for (const name of [...url.searchParams.keys()]) {
        if (name === key || name.startsWith(`${key}[`)) url.searchParams.delete(name);
    }
    return url.pathname + url.search;
}

/** Which way a column is sorted, from the table's sort state ("name" or "-name"). */
function sortDirection(sort: string | null, column: Column): 'asc' | 'desc' | null {
    if (sort === column.name) return 'asc';
    if (sort === `-${column.name}`) return 'desc';
    return null;
}

const SORT_ICON = { asc: ArrowUp, desc: ArrowDown } as const;
const ARIA_SORT = { asc: 'ascending', desc: 'descending' } as const;

/** The table node: server-side search, filters, sort and pagination, with header, row and bulk buttons. */
export function SchemaTable({ node }: { node: TableNode }) {
    const { cells } = useRegistry();
    const modal = useRecordModal();
    const inModal = useContext(InModalContext);
    const [search, setSearch] = useState(node.state.search);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<Set<Row['id']>>(new Set());
    // Per page, not per URL, so every record page shares one choice for its tables.
    const { lodestone, page: meta } = usePage<PageProps>().props;
    const storageKey = `lodestone:columns:${lodestone.panel.id}:${meta.slug}:${node.key}`;
    const [hidden, setHidden] = useState<Set<string>>(() => {
        try {
            const saved = storage.get(storageKey);
            if (saved) return new Set(JSON.parse(saved) as string[]);
        } catch {
            // A garbled value falls back to the defaults.
        }
        return new Set(node.columns.filter((column) => column.hidden).map((column) => column.name));
    });
    const columns = node.columns.filter((column) => !hidden.has(column.name));
    const toggleColumn = (name: string) =>
        setHidden((current) => {
            const next = new Set(current);
            next.has(name) ? next.delete(name) : next.add(name);
            storage.set(storageKey, JSON.stringify([...next]));
            return next;
        });
    const first = useRef(true);
    const { page, last_page, total, per_page } = node.pagination;

    const visit = (next: Visit) => {
        const state = { ...node.state, page: 1, ...next };
        const filters = Object.fromEntries(Object.entries(state.filters).filter(([, v]) => isSet(v)));
        const query = {
            ...(state.search && { search: state.search }),
            ...(Object.keys(filters).length && { filters }),
            ...(state.sort && { sort: state.sort }),
            ...(state.page > 1 && { page: state.page }),
        };

        setSelected(new Set());
        router.get(urlWithout(node.key), Object.keys(query).length ? { [node.key]: query } : {}, {
            only: ['components'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const searchInput = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (document.activeElement !== searchInput.current) setSearch(node.state.search);
    }, [node.state.search]);

    // Debounced server-side search.
    useEffect(() => {
        if (first.current) return void (first.current = false);
        const timer = setTimeout(() => search !== node.state.search && visit({ search }), 300);
        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps -- runs on typing only; visit and node.state are read when it fires.

    // Cycles descending, then ascending, then back to the default order.
    const sortBy = (column: Column) => {
        const sorted = sortDirection(node.state.sort, column);
        if (sorted === 'desc') return visit({ sort: column.name });
        if (sorted === 'asc') return visit({ sort: null });
        visit({ sort: `-${column.name}` });
    };

    const from = total ? (page - 1) * per_page + 1 : 0;
    const hasBulk = node.bulk_buttons.length > 0;
    const hasMenu = node.row_buttons.length > 0 || node.rows.some((row) => row._url || row._modal);
    const selectedRows = node.rows.filter((row) => selected.has(row.id));
    const allSelected = node.rows.length > 0 && selectedRows.length === node.rows.length;
    let headerChecked: boolean | 'indeterminate' = false;
    if (allSelected) headerChecked = true;
    else if (selectedRows.length > 0) headerChecked = 'indeterminate';
    const toggle = (id: Row['id']) =>
        setSelected((current) => {
            const next = new Set(current);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });

    // Rows behave like links: click opens the record page (or its modal), cmd/ctrl-click opens a new tab.
    const open = (row: Row, event: MouseEvent) => {
        const url = row._modal ?? row._url;
        if (!url) return;
        if (event.metaKey || event.ctrlKey) return void window.open(url, '_blank');
        row._modal ? modal.open(row._modal, node.overlay) : router.visit(url);
    };
    const hover = useRef<number | undefined>(undefined);
    const prefetch = (row: Row) => {
        // Moving straight from one row to the next would otherwise leave the old timer running.
        clearTimeout(hover.current);
        hover.current = window.setTimeout(() => {
            if (row._modal) modal.prefetch(row._modal);
            else if (row._url) router.prefetch(row._url, { method: 'get' }, { cacheFor: '30s' });
        }, 150);
    };

    const body = (
        <div className="flex flex-col gap-3">
            {selectedRows.length > 0 ? (
                <div className="flex min-h-8 flex-wrap items-center gap-2">
                    <span className="text-sm font-medium tabular-nums">{selectedRows.length} selected</span>
                    {node.bulk_buttons.map((button) => {
                        // Only rows this button is visible and enabled for; the server checks the same.
                        const ids = selectedRows.filter((row) => row._buttons?.includes(button.key)).map((row) => row.id);
                        const reason = selectedRows.map((row) => row._disabled?.[button.key]).find(Boolean);

                        return (
                            <LodestoneButton
                                key={button.key}
                                button={
                                    ids.length > 0 && ids.length !== selectedRows.length
                                        ? { ...button, label: `${button.label} (${ids.length})` }
                                        : button
                                }
                                ids={ids}
                                disabled={ids.length === 0}
                                disabledReason={reason || 'None of the selected rows allow this'}
                                onDone={() => setSelected(new Set())}
                            />
                        );
                    })}
                    <Button variant="ghost" size="sm" onClick={() => setSelected(new Set())}>
                        Clear
                    </Button>
                </div>
            ) : (
                (node.searchable || node.filters.length > 0 || node.columns.some((c) => c.toggleable) || node.header_buttons.length > 0) &&
                !inModal && (
                    <div className="flex flex-wrap items-center gap-2">
                        {node.searchable && (
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    type="search"
                                    placeholder="Search…"
                                    aria-label="Search"
                                    ref={searchInput}
                                    className="h-8 pl-8"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                        )}
                        {node.filters.map((filter) => (
                            <FilterControl
                                key={filter.name}
                                filter={filter}
                                value={node.state.filters[filter.name]}
                                onChange={(value) => visit({ filters: { ...node.state.filters, [filter.name]: value } })}
                            />
                        ))}
                        <span className="text-muted-foreground ml-auto text-xs tabular-nums">
                            {total.toLocaleString(locale)} {total === 1 ? 'row' : 'rows'}
                        </span>
                        <ColumnsMenu columns={node.columns} hidden={hidden} onToggle={toggleColumn} />
                        {node.header_buttons.map((button, i) => (
                            <LodestoneButton key={button.key} button={button} primary={i === 0} />
                        ))}
                    </div>
                )
            )}

            <div
                className={cn(
                    'overflow-hidden rounded-lg border transition-opacity',
                    node.sticky_header &&
                        '[&>[data-slot=table-container]]:max-h-(--lodestone-table-height) [&>[data-slot=table-container]]:overflow-y-auto',
                    loading && 'opacity-60',
                )}
                style={node.sticky_header ? ({ '--lodestone-table-height': node.sticky_header } as React.CSSProperties) : undefined}
            >
                <Table>
                    <TableHeader className={cn(node.sticky_header ? 'bg-muted sticky top-0 z-10' : 'bg-muted/50')}>
                        <TableRow>
                            {hasBulk && (
                                <TableHead className="w-10 pl-4">
                                    <Checkbox
                                        aria-label="Select all rows on this page"
                                        checked={headerChecked}
                                        onCheckedChange={() => setSelected(allSelected ? new Set() : new Set(node.rows.map((row) => row.id)))}
                                    />
                                </TableHead>
                            )}
                            {columns.map((column) => {
                                const sorted = sortDirection(node.state.sort, column);
                                const SortIcon = sorted ? SORT_ICON[sorted] : ChevronsUpDown;

                                return (
                                    <TableHead
                                        key={column.name}
                                        className={cn('text-muted-foreground first:pl-4 last:pr-4', column.align === 'right' && 'text-right')}
                                        aria-sort={sorted ? ARIA_SORT[sorted] : undefined}
                                    >
                                        {column.sortable && !inModal ? (
                                            <button
                                                type="button"
                                                onClick={() => sortBy(column)}
                                                className={cn(
                                                    'hover:text-foreground inline-flex cursor-pointer items-center gap-1',
                                                    column.align === 'right' && 'flex-row-reverse',
                                                )}
                                            >
                                                {column.label}
                                                <SortIcon className="size-3.5 opacity-60" />
                                            </button>
                                        ) : (
                                            column.label
                                        )}
                                    </TableHead>
                                );
                            })}
                            {hasMenu && (
                                <TableHead className="w-10 pr-4">
                                    <span className="sr-only">Menu</span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {node.rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={selected.has(row.id) ? 'selected' : undefined}
                                className={cn((row._url || row._modal) && 'cursor-pointer')}
                                onClick={(event) => open(row, event)}
                                onMouseEnter={() => prefetch(row)}
                                onMouseLeave={() => clearTimeout(hover.current)}
                            >
                                {hasBulk && (
                                    <TableCell className="pl-4" onClick={(event) => event.stopPropagation()}>
                                        <Checkbox
                                            aria-label={`Select row ${row.id}`}
                                            checked={selected.has(row.id)}
                                            onCheckedChange={() => toggle(row.id)}
                                        />
                                    </TableCell>
                                )}
                                {columns.map((column) => {
                                    const Cell = cells[column.type] ?? cells.text;

                                    return (
                                        <TableCell
                                            key={column.name}
                                            className={cn('max-w-80 truncate first:pl-4 last:pr-4', column.align === 'right' && 'text-right')}
                                        >
                                            <Cell column={column} value={row[column.name]} row={row} />
                                        </TableCell>
                                    );
                                })}
                                {hasMenu && (
                                    <TableCell className="pr-4 text-right" onClick={(event) => event.stopPropagation()}>
                                        <RowMenu row={row} node={node} />
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {node.rows.length === 0 &&
                    (node.state.search || Object.values(node.state.filters).some(isSet) ? (
                        <EmptyState heading="No rows match" description="Try a different search, or clear the filters." icon="search">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    visit({ search: '', filters: {} });
                                }}
                            >
                                Clear filters
                            </Button>
                        </EmptyState>
                    ) : (
                        <EmptyState heading="Nothing here yet" {...node.empty_state} />
                    ))}
            </div>

            {last_page > 1 && !inModal && (
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground text-sm tabular-nums">
                        {from}–{Math.min(total, page * per_page)} of {total.toLocaleString(locale)}
                    </span>
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium tabular-nums">
                            Page {page} of {last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Previous page"
                            disabled={page <= 1}
                            onClick={() => visit({ page: page - 1 })}
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Next page"
                            disabled={page >= last_page}
                            onClick={() => visit({ page: page + 1 })}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );

    if (!node.heading) return body;

    return (
        <Card className="gap-4 py-4">
            <CardHeader className="px-4">
                <CardTitle>{node.heading}</CardTitle>
                {node.description && <CardDescription>{node.description}</CardDescription>}
            </CardHeader>
            <CardContent className="px-4">{body}</CardContent>
        </Card>
    );
}

function RowMenu({ row, node }: { row: Row; node: TableNode }) {
    const press = usePress();
    const modal = useRecordModal();
    const full = row._url ?? row._modal;
    // Visible for this row: usable ones, then greyed-out ones with their reason.
    const buttons = node.row_buttons.filter((button) => row._buttons?.includes(button.key) || row._disabled?.[button.key] !== undefined);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" className="text-muted-foreground data-[state=open]:bg-muted size-7" aria-label="Row menu">
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {full && (
                    <>
                        <DropdownMenuItem onSelect={() => modal.open(full, node.overlay)}>
                            <PanelTop />
                            {row._modal ? 'Open' : 'Quick view'}
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={full} prefetch>
                                <ArrowRight />
                                {row._modal ? 'Open full page' : 'Open'}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a href={full} target="_blank" rel="noreferrer">
                                <ExternalLink />
                                Open in new tab
                            </a>
                        </DropdownMenuItem>
                    </>
                )}
                {full && node.row_buttons.length > 0 && <DropdownMenuSeparator />}
                {buttons.map((button) => {
                    const reason = row._disabled?.[button.key];

                    return (
                        <ButtonMenuItem
                            key={button.key}
                            button={button}
                            disabled={reason !== undefined}
                            disabledReason={reason}
                            onSelect={() => press(button, [row.id])}
                        />
                    );
                })}
                {node.row_buttons.length > 0 && buttons.length === 0 && <DropdownMenuItem disabled>Nothing to do for this row</DropdownMenuItem>}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
