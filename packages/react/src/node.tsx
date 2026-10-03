import { Suspense } from 'react';
import { checkNode, isDev } from './lib/check';
import type { SchemaNode } from './protocol';
import { useRegistry } from './registry';
import { Skeleton } from './ui/skeleton';

/** A node's own identity, when it has one: a table's key, or a section's heading. */
function identityOf(node: SchemaNode): string | null {
    const { key, heading } = node as { key?: unknown; heading?: unknown };
    if (typeof key === 'string') return `${node.type}:${key}`;
    if (typeof heading === 'string') return `${node.type}:${heading}`;
    return null;
}

/**
 * A React key for a node among its siblings. Named nodes keep their subtree across a reorder; the
 * index is only added for anonymous nodes and for a name repeated among siblings.
 */
export function nodeKey(node: SchemaNode, index: number, siblings: SchemaNode[] = []): string {
    const identity = identityOf(node);
    if (identity === null) return `${node.type}:${index}`;

    const repeated = siblings.filter((sibling) => identityOf(sibling) === identity).length > 1;
    return repeated ? `${identity}:${index}` : identity;
}

/** Render one schema node by looking its type up in the registry. */
export function Node({ node }: { node: SchemaNode }) {
    const { nodes } = useRegistry();
    const Component = nodes[node.type];

    if (isDev) checkNode(node, Boolean(Component));

    if (!Component) {
        return (
            <div className="border-destructive/40 text-destructive rounded-lg border border-dashed p-4 font-mono text-xs">
                No component registered for node type "{node.type}". Add it with createLodestoneApp({'{'} nodes: {'{'} '{node.type}': MyComponent{' '}
                {'}'} {'}'}).
            </div>
        );
    }

    // Some built-ins (charts) are lazy-loaded; show a skeleton while their code downloads.
    return (
        <Suspense fallback={<Skeleton className="h-64 w-full rounded-xl" />}>
            <Component node={node} />
        </Suspense>
    );
}
