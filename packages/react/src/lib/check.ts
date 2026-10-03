// Development-only check that the server's JSON matches protocol.ts. The shapes are written twice
// (PHP toSchema() and these types), so this catches the two drifting apart. Vite strips it from builds.
import type { SchemaNode } from '../protocol';

/** Keys every built-in node must have. Optional keys aren't listed. */
const REQUIRED: Record<string, string[]> = {
    stats: ['items'],
    table: ['key', 'searchable', 'columns', 'filters', 'header_buttons', 'row_buttons', 'bulk_buttons', 'state', 'rows', 'pagination'],
    tabs: ['tabs'],
    grid: ['columns', 'children'],
    section: ['heading', 'collapsible', 'collapsed', 'children'],
    empty_state: ['heading', 'icon'],
    fields: ['items'],
    code: ['heading', 'value'],
    alert: ['tone', 'title', 'text'],
    chart: ['kind', 'heading', 'data', 'series', 'stacked', 'height'],
    markdown: ['html'],
};

const warned = new Set<string>();

const warnOnce = (id: string, message: string) => {
    if (warned.has(id)) return;
    warned.add(id);
    console.warn(`Lodestone: ${message}`);
};

/** Whether this is a Vite development build. */
export const isDev = Boolean((import.meta as ImportMeta & { env?: { DEV?: boolean } }).env?.DEV);

/** Warn about an unregistered node type, or a built-in node missing keys the renderer needs. */
export function checkNode(node: SchemaNode, registered: boolean): void {
    if (!registered) {
        warnOnce(
            `type:${node.type}`,
            `no component is registered for node type "${node.type}". Add it with createLodestoneApp({ nodes: { '${node.type}': MyComponent } }).`,
        );
        return;
    }

    const missing = (REQUIRED[node.type] ?? []).filter((key) => !(key in node));
    if (missing.length > 0) {
        warnOnce(
            `keys:${node.type}:${missing.join()}`,
            `a "${node.type}" node is missing ${missing.map((k) => `"${k}"`).join(', ')}. Its PHP toSchema() and protocol.ts have drifted apart.`,
        );
    }
}
