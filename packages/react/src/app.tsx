import { createInertiaApp } from '@inertiajs/react';
import { lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { defaultCells } from './components/cells';
import { SchemaEmptyState } from './components/common';
import { SchemaTable } from './components/data-table';
import { SchemaAlert, SchemaCode, SchemaFields, SchemaGrid, SchemaMarkdown, SchemaSection, SchemaTabs } from './components/layout';
import { SchemaStats } from './components/stat-cards';
import { defaultIcons } from './lib/icons';
import LodestonePage from './page';
import { RegistryContext, type Registry } from './registry';

/** Options for createLodestoneApp(). Each registry map is merged over the built-ins. */
export interface LodestoneOptions {
    /** Add node types or replace built-ins: { table: MyTable, 'support.thread': TicketThread }. */
    nodes?: Registry['nodes'];
    /** Column cell renderers keyed by column type: { money: MoneyCell }. */
    cells?: Registry['cells'];
    /** Extra lucide icons, referenced by name from PHP: { 'life-buoy': LifeBuoy }. */
    icons?: Registry['icons'];
    /** Turn a page title into the document title. */
    title?: (title: string) => string;
}

const SchemaChart = lazy(() => import('./components/chart'));

/** The built-in node components, keyed by node type. */
export const defaultNodes: Registry['nodes'] = {
    stats: SchemaStats,
    table: SchemaTable,
    tabs: SchemaTabs,
    grid: SchemaGrid,
    section: SchemaSection,
    empty_state: SchemaEmptyState,
    fields: SchemaFields,
    code: SchemaCode,
    alert: SchemaAlert,
    chart: SchemaChart,
    markdown: SchemaMarkdown,
};

/** Boot the Inertia app with the Lodestone page and registry. */
export function createLodestoneApp(options: LodestoneOptions = {}) {
    const registry: Registry = {
        nodes: { ...defaultNodes, ...options.nodes },
        cells: { ...defaultCells, ...options.cells },
        icons: { ...defaultIcons, ...options.icons },
    };

    return createInertiaApp({
        title: options.title ?? ((title) => title),
        // A library supplies its own single page component, so resolve manually instead of via the Vite plugin.
        resolve: () => LodestonePage,
        setup({ el, App, props }) {
            createRoot(el!).render(
                <RegistryContext.Provider value={registry}>
                    <App {...props} />
                </RegistryContext.Provider>,
            );
        },
        progress: { delay: 150 },
    });
}
