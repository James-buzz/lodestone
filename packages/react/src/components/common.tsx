import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { Check, Copy } from 'lucide-react';
import { useState, type MouseEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import type { EmptyStateNode } from '../protocol';
import { useRegistry } from '../registry';
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { Button } from '../ui/button';

/** Whether a URL is same-origin, so it can navigate with Inertia instead of a full load. */
export const isInternal = (url: string) => new URL(url, window.location.href).origin === window.location.origin;

/** A small icon button that copies text and shows a tick for a moment. Doesn't trigger row clicks. */
export function CopyButton({ value, label = 'Copy', className }: { value: string; label?: string; className?: string }) {
    const [copied, setCopied] = useState(false);

    const copy = (event: MouseEvent) => {
        event.stopPropagation();
        navigator.clipboard?.writeText(value).then(
            () => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
            },
            () => toast.error("Couldn't copy."),
        );
    };

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={copied ? 'Copied' : label}
            title={label}
            onClick={copy}
            className={className}
        >
            {copied ? <Check /> : <Copy />}
        </Button>
    );
}

/** Up to two initials from a name, for avatar fallbacks. */
export const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

/** An avatar (image, or initials when there's none or it fails to load) beside a name and optional second line. */
export function UserChip({ name, image, description, children }: { name: string; image?: string; description?: ReactNode; children?: ReactNode }) {
    return (
        <span className="flex min-w-0 items-center gap-2.5">
            <Avatar size={description ? 'default' : 'sm'}>
                {image && <AvatarImage src={image} alt="" />}
                <AvatarFallback className="text-foreground/70 text-[11px] font-medium">{initials(name)}</AvatarFallback>
            </Avatar>
            <span className="flex min-w-0 flex-col">
                <span className="truncate">{children ?? name}</span>
                {description && <span className="text-muted-foreground truncate text-xs">{description}</span>}
            </span>
        </span>
    );
}

/** "Nothing here" with an icon, a line of help and an optional button. Tables use it for no rows. */
export function EmptyState({
    heading,
    description,
    icon = 'inbox',
    link,
    children,
    className,
}: {
    heading: string;
    description?: string | null;
    icon?: string;
    link?: { label: string; url: string } | null;
    children?: ReactNode;
    className?: string;
}) {
    const { icons } = useRegistry();
    const Icon = icons[icon] ?? icons.inbox;

    return (
        <div className={cn('flex flex-col items-center gap-1.5 px-6 py-10 text-center', className)}>
            <div className="bg-muted text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full">
                <Icon className="size-5" />
            </div>
            <p className="text-sm font-medium">{heading}</p>
            {description && <p className="text-muted-foreground max-w-sm text-sm text-balance">{description}</p>}
            {(link || children) && (
                <div className="mt-3 flex gap-2">
                    {link && (
                        <Button asChild variant="outline" size="sm">
                            {isInternal(link.url) ? (
                                <Link href={link.url} prefetch>
                                    {link.label}
                                </Link>
                            ) : (
                                <a href={link.url} target="_blank" rel="noreferrer">
                                    {link.label}
                                </a>
                            )}
                        </Button>
                    )}
                    {children}
                </div>
            )}
        </div>
    );
}

/** The empty-state node, as a dashed card. */
export function SchemaEmptyState({ node }: { node: EmptyStateNode }) {
    return <EmptyState {...node} className="rounded-xl border border-dashed" />;
}
