import { createContext, useContext, type ComponentType } from 'react';
import type { LucideIcon } from 'lucide-react';
import type { Column, Row, SchemaNode } from './protocol';

/** A component that draws one node type. */
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- the registry holds a component per node type, so the default must accept any node.
export type NodeComponent<N extends SchemaNode = any> = ComponentType<{ node: N }>;

/** A component that draws one cell of a column type. */
export type CellComponent = ComponentType<{ column: Column; value: unknown; row: Row }>;

/** Everything the renderer looks up by name. Override or extend any of it in createLodestoneApp(). */
export interface Registry {
    nodes: Record<string, NodeComponent>;
    cells: Record<string, CellComponent>;
    icons: Record<string, LucideIcon>;
}

/** The registry for the app, provided once by createLodestoneApp(). */
export const RegistryContext = createContext<Registry | null>(null);

/** Get the registry, or throw outside createLodestoneApp(). */
export function useRegistry(): Registry {
    const registry = useContext(RegistryContext);

    if (!registry) {
        throw new Error('Lodestone components must render inside createLodestoneApp().');
    }

    return registry;
}
