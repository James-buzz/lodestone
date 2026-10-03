import { Head, Link, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import { Circle, Monitor, Moon, Sun } from 'lucide-react';
import { Fragment, useEffect } from 'react';
import { Toaster } from 'sonner';
import { ButtonProvider, PageButtons } from './components/buttons';
import { PanelSwitcher } from './components/panel-switcher';
import { RecordModalProvider } from './components/record-modal';
import { StatusBadge } from './components/status-badge';
import { useTheme, type Theme } from './lib/theme';
import { Node, nodeKey } from './node';
import { PROTOCOL, type NavItem, type PageMeta, type PageProps } from './protocol';
import { useRegistry } from './registry';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from './ui/breadcrumb';
import { Separator } from './ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from './ui/sidebar';

/** The only Inertia page component. Every panel page renders through it. */
export default function LodestonePage() {
    const { page, components, lodestone } = usePage<PageProps>().props;
    const [theme] = useTheme();

    useEffect(() => {
        if (lodestone.protocol !== PROTOCOL) {
            console.warn(
                `Lodestone: server speaks protocol v${lodestone.protocol}, this renderer speaks v${PROTOCOL}. Update the npm and Composer packages together.`,
            );
        }
    }, [lodestone.protocol]);

    return (
        <ButtonProvider>
            <RecordModalProvider>
                <SidebarProvider>
                    <Head title={page.title} />
                    <PanelSidebar active={page.slug} />
                    <SidebarInset className="min-w-0">
                        <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
                            <SidebarTrigger className="-ml-1" />
                            <Separator orientation="vertical" className="mr-1 data-[orientation=vertical]:h-4" />
                            <Breadcrumb className="min-w-0">
                                <BreadcrumbList className="flex-nowrap">
                                    {/* Keyed by position: the panel home and the first list page share a URL. */}
                                    {[[lodestone.panel.title, lodestone.panel.url], ...page.breadcrumbs].map(([label, url], i) => (
                                        <Fragment key={i}>
                                            <BreadcrumbItem className="hidden truncate sm:inline-flex">
                                                <BreadcrumbLink asChild>
                                                    <Link href={url} prefetch>
                                                        {label}
                                                    </Link>
                                                </BreadcrumbLink>
                                            </BreadcrumbItem>
                                            <BreadcrumbSeparator className="hidden sm:block" />
                                        </Fragment>
                                    ))}
                                    <BreadcrumbItem className="min-w-0">
                                        <BreadcrumbPage className="truncate">{page.title}</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </header>
                        <main className="@container mx-auto flex w-full max-w-7xl flex-col gap-4 p-4 md:gap-6 md:p-6">
                            <PageHeader page={page} />
                            {components.map((node, i) => (
                                <Node key={`${page.url}:${nodeKey(node, i, components)}`} node={node} />
                            ))}
                        </main>
                    </SidebarInset>
                </SidebarProvider>
            </RecordModalProvider>
            <Toaster theme={theme} position="bottom-right" richColors closeButton />
        </ButtonProvider>
    );
}

function PageHeader({ page }: { page: PageMeta }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold tracking-tight text-balance">{page.title}</h1>
                    {page.badges.map((badge, i) => (
                        <StatusBadge key={i} value={badge.value} colors={badge.colors} />
                    ))}
                </div>
                {page.description && <p className="text-muted-foreground mt-1 text-sm">{page.description}</p>}
            </div>
            <PageButtons buttons={page.buttons} />
        </div>
    );
}

function PanelSidebar({ active }: { active: string }) {
    const { lodestone } = usePage<PageProps>().props;
    const { icons } = useRegistry();

    // Keep the server's order; each group appears where its first page does.
    const groups: [string | null, NavItem[]][] = [];
    for (const item of lodestone.nav) {
        const group = groups.find(([name]) => name === item.group);
        group ? group[1].push(item) : groups.push([item.group, [item]]);
    }

    return (
        <Sidebar variant="inset">
            <SidebarHeader>
                <PanelSwitcher />
            </SidebarHeader>
            <SidebarContent>
                {groups.map(([group, items]) => (
                    <SidebarGroup key={group ?? ''}>
                        {group && <SidebarGroupLabel>{group}</SidebarGroupLabel>}
                        <SidebarMenu>
                            {items.map((item) => {
                                const Icon = icons[item.icon] ?? Circle;

                                return (
                                    <SidebarMenuItem key={item.slug}>
                                        <SidebarMenuButton asChild isActive={item.slug === active} tooltip={item.title}>
                                            <Link href={item.url} prefetch cacheFor="30s">
                                                <Icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        {item.badge && item.badge.value > 0 && (
                                            <SidebarMenuBadge
                                                className={cn(item.badge.tone === 'danger' && 'bg-red-500/15 text-red-600 dark:text-red-400')}
                                            >
                                                {item.badge.value}
                                            </SidebarMenuBadge>
                                        )}
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            <SidebarFooter className="gap-3 p-4 text-sm group-data-[collapsible=icon]:hidden">
                <ThemeToggle />
            </SidebarFooter>
        </Sidebar>
    );
}

const THEMES: [Theme, typeof Sun][] = [
    ['light', Sun],
    ['system', Monitor],
    ['dark', Moon],
];

function ThemeToggle() {
    const [theme, setTheme] = useTheme();

    return (
        <div className="text-muted-foreground flex items-center justify-between gap-2">
            <span>Theme</span>
            <div role="group" aria-label="Theme" className="flex rounded-md border shadow-xs">
                {THEMES.map(([value, Icon], i) => (
                    <button
                        key={value}
                        type="button"
                        aria-pressed={theme === value}
                        aria-label={value}
                        title={value}
                        onClick={() => setTheme(value)}
                        className={cn(
                            'hover:bg-accent aria-pressed:bg-accent aria-pressed:text-foreground grid h-7 w-8 cursor-pointer place-items-center',
                            i > 0 && 'border-l',
                            i === 0 && 'rounded-l-md',
                            i === THEMES.length - 1 && 'rounded-r-md',
                        )}
                    >
                        <Icon className="size-3.5" />
                    </button>
                ))}
            </div>
        </div>
    );
}
