import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, SquareTerminal } from 'lucide-react';
import type { PageProps, PanelLink } from '../protocol';
import { useRegistry } from '../registry';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '../ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '../ui/sidebar';

/** The sidebar header. When the user can open more than one panel it becomes a switcher; otherwise it's a plain header. */
export function PanelSwitcher() {
    const { lodestone } = usePage<PageProps>().props;
    const { icons } = useRegistry();
    const { isMobile } = useSidebar();
    const { panel, panels } = lodestone;
    const Icon = (panel.icon && icons[panel.icon]) || SquareTerminal;

    const identity = (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <Icon className="size-4" />
            </div>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">{panel.title}</span>
                <span className="text-muted-foreground truncate text-xs">{panel.description ?? 'Lodestone'}</span>
            </div>
        </>
    );

    if (panels.length < 2) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href={panel.url}>{identity}</Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            aria-label="Switch panel"
                        >
                            {identity}
                            <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-muted-foreground text-xs">Panels</DropdownMenuLabel>
                        {panels.map((item) => (
                            <PanelEntry key={item.url} item={item} />
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

/** One panel in the switcher. A full page load, not an Inertia visit: another panel can have its own Vite entry and theme. */
function PanelEntry({ item }: { item: PanelLink }) {
    const { icons } = useRegistry();
    const Icon = (item.icon && icons[item.icon]) || SquareTerminal;

    return (
        <DropdownMenuItem asChild className="gap-2 p-2">
            <a href={item.url} aria-current={item.current ? 'page' : undefined}>
                <div className="flex size-7 shrink-0 items-center justify-center rounded-md border">
                    <Icon className="size-4" />
                </div>
                <div className="grid min-w-0 flex-1 leading-tight">
                    <span className="truncate font-medium">{item.title}</span>
                    {item.description && <span className="text-muted-foreground truncate text-xs">{item.description}</span>}
                </div>
                {item.current && <Check className="ml-auto size-4" />}
            </a>
        </DropdownMenuItem>
    );
}
