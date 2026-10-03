import { cn } from 'cn';
import { AlertCircle, AlertTriangle, ChevronDown, CircleCheck, Info } from 'lucide-react';
import { useContext, useState } from 'react';
import { Node, nodeKey } from '../node';
import type { AlertNode, CodeNode, FieldsNode, GridNode, MarkdownNode, SectionNode, TabsNode, Tone } from '../protocol';
import { useRegistry } from '../registry';
import { Alert, AlertDescription, AlertTitle } from '../ui/alert';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '../ui/card';
import { CopyButton } from './common';
import { FormSection } from './form';
import { InModalContext } from './record-modal';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';

/** Tabs whose panels hold more nodes. The open tab is kept in ?tab= so it can be linked (not in the modal, whose URL is the page behind). */
export function SchemaTabs({ node }: { node: TabsNode }) {
    const inModal = useContext(InModalContext);
    const initial = inModal ? null : new URLSearchParams(window.location.search).get('tab');
    const [tab, setTab] = useState(node.tabs.find((t) => t.key === initial)?.key ?? node.tabs[0]?.key);

    const select = (key: string) => {
        setTab(key);
        if (inModal) return;
        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <Tabs value={tab} onValueChange={select} className="gap-4">
            <div className="-mx-1 overflow-x-auto px-1 pb-0.5">
                <TabsList>
                    {node.tabs.map((t) => (
                        <TabsTrigger key={t.key} value={t.key} className="px-3">
                            {t.label}
                            {t.badge !== null && (
                                <span className="bg-foreground/10 text-muted-foreground rounded-full px-1.5 text-[11px] leading-4 tabular-nums">
                                    {t.badge}
                                </span>
                            )}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </div>
            {node.tabs.map((t) => (
                <TabsContent key={t.key} value={t.key} className="flex flex-col gap-4">
                    {t.children.map((child, i) => (
                        <Node key={nodeKey(child, i, t.children)} node={child} />
                    ))}
                </TabsContent>
            ))}
        </Tabs>
    );
}

// Literal class names so Tailwind generates them.
const GRID_COLUMNS: Record<number, string> = { 2: '@3xl:grid-cols-2', 3: '@4xl:grid-cols-3', 4: '@4xl:grid-cols-4' };

/** The grid node: nodes side by side, collapsing to one column when narrow. */
export function SchemaGrid({ node }: { node: GridNode }) {
    return (
        <div className={cn('grid items-start gap-4', GRID_COLUMNS[node.columns])}>
            {node.children.map((child, i) => (
                <Node key={nodeKey(child, i, node.children)} node={child} />
            ))}
        </div>
    );
}

/** A heading over nodes. Collapsible ones use <details>, so they work before JS and with find-in-page. */
export function SchemaSection({ node }: { node: SectionNode }) {
    if (node.form) {
        return (
            <div className="flex flex-col gap-4">
                <FormSection heading={node.heading} description={node.description} form={node.form} />
                {node.children.map((child, i) => (
                    <Node key={nodeKey(child, i, node.children)} node={child} />
                ))}
            </div>
        );
    }

    const header = (
        <div className="flex min-w-0 flex-col gap-0.5">
            <h2 className="text-base font-semibold tracking-tight">{node.heading}</h2>
            {node.description && <p className="text-muted-foreground text-sm">{node.description}</p>}
        </div>
    );
    const children = (
        <div className="flex flex-col gap-4">
            {node.children.map((child, i) => (
                <Node key={nodeKey(child, i, node.children)} node={child} />
            ))}
        </div>
    );

    if (!node.collapsible) {
        return (
            <section className="flex flex-col gap-3">
                {header}
                {children}
            </section>
        );
    }

    return (
        <details open={!node.collapsed} className="group/section open:[&>summary]:mb-3">
            <summary className="hover:bg-muted/50 -mx-2 flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg px-2 py-1.5 select-none [&::-webkit-details-marker]:hidden">
                {header}
                <ChevronDown className="text-muted-foreground size-4 shrink-0 transition-transform group-open/section:rotate-180" />
            </summary>
            {children}
        </details>
    );
}

/** The fields node: a label/value list whose values render with the same cell components as table columns. */
export function SchemaFields({ node }: { node: FieldsNode }) {
    const { cells } = useRegistry();

    return (
        <Card className="gap-3 py-4">
            {node.heading && (
                <CardHeader className="px-4">
                    <CardTitle>{node.heading}</CardTitle>
                </CardHeader>
            )}
            <CardContent className="px-4">
                <dl className="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-4 text-sm">
                    {node.items.map(({ column, value, ...data }, i) => {
                        const Cell = cells[column.type] ?? cells.text;
                        // Cells read extras as row['<name>:<key>'], so shape the item like a table row (the id is only there to satisfy Row).
                        const row = Object.fromEntries(Object.entries(data).map(([key, v]) => [`${column.name}:${key}`, v]));

                        return (
                            <div key={column.name} className={cn('col-span-2 grid grid-cols-subgrid py-2', i > 0 && 'border-t')}>
                                <dt className="text-muted-foreground">{column.label}</dt>
                                <dd className="min-w-0 break-words">
                                    <Cell column={column} value={value} row={{ id: column.name, ...row }} />
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </CardContent>
        </Card>
    );
}

/** The code node: a value as pretty-printed JSON (or text) with a copy button. */
export function SchemaCode({ node }: { node: CodeNode }) {
    const text = typeof node.value === 'string' ? node.value : JSON.stringify(node.value, null, 2);

    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle>{node.heading}</CardTitle>
                <CardAction>
                    <CopyButton value={text} />
                </CardAction>
            </CardHeader>
            <CardContent className="px-4">
                <pre className="bg-muted/40 max-h-80 overflow-auto rounded-lg border p-3 font-mono text-xs leading-relaxed">{text}</pre>
            </CardContent>
        </Card>
    );
}

/** Server-rendered Markdown. The PHP package strips raw HTML; other backends must send safe HTML too. */
export function SchemaMarkdown({ node }: { node: MarkdownNode }) {
    const body = <div className="lodestone-prose" dangerouslySetInnerHTML={{ __html: node.html }} />;

    if (!node.heading) return body;

    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle>{node.heading}</CardTitle>
            </CardHeader>
            <CardContent className="px-4">{body}</CardContent>
        </Card>
    );
}

const ALERT_ICON: Record<Tone, typeof Info> = { danger: AlertCircle, warning: AlertTriangle, info: Info, success: CircleCheck, gray: Info };

const ALERT_CLASS: Partial<Record<Tone, string>> = {
    warning:
        'text-amber-700 *:data-[slot=alert-description]:text-amber-700/90 dark:text-amber-400 dark:*:data-[slot=alert-description]:text-amber-400/90',
    success:
        'text-emerald-700 *:data-[slot=alert-description]:text-emerald-700/90 dark:text-emerald-400 dark:*:data-[slot=alert-description]:text-emerald-400/90',
    gray: 'text-muted-foreground',
};

/** The alert node: a callout with an icon for its tone. */
export function SchemaAlert({ node }: { node: AlertNode }) {
    const Icon = ALERT_ICON[node.tone] ?? Info;

    return (
        <Alert variant={node.tone === 'danger' ? 'destructive' : 'default'} className={cn(ALERT_CLASS[node.tone])}>
            <Icon />
            <AlertTitle>{node.title}</AlertTitle>
            <AlertDescription className="break-words">{node.text}</AlertDescription>
        </Alert>
    );
}
