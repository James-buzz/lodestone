import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { isHttpError, requestJson } from '../lib/http';
import { Node } from '../node';
import type { Overlay, PageMeta, SchemaNode } from '../protocol';
import { Button } from '../ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '../ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '../ui/sheet';
import { Skeleton } from '../ui/skeleton';
import { PageButtons, useButtonVersion } from './buttons';
import { StatusBadge } from './status-badge';

interface RecordData {
    page: PageMeta;
    components: SchemaNode[];
}

/** Record pages fetched as JSON. Hovering a row prefetches; entries stay fresh for 30s. */
const cache = new Map<string, { at: number; data: Promise<RecordData> }>();

/** Fetch a record page as JSON, reusing a fresh cache entry unless told otherwise. */
function load(url: string, fresh = false): Promise<RecordData> {
    const hit = cache.get(url);
    if (!fresh && hit && Date.now() - hit.at < 30_000) return hit.data;

    const data = requestJson<RecordData>(url, { headers: { 'X-Lodestone-Modal': '1' } }).catch((error: unknown) => {
        const status = isHttpError(error) ? error.status : 0;
        throw new Error(status === 403 ? "You don't have access to this record." : `Couldn't load this record (${status || 'network error'}).`);
    });

    cache.set(url, { at: Date.now(), data });
    data.catch(() => cache.delete(url));
    return data;
}

/** The record modal, as rows and buttons use it. */
export interface RecordModal {
    /** Open any record page's URL over the page, centred ('modal') or from the right ('slide-over'). */
    open: (url: string, overlay?: Overlay) => void;
    /** Warm the cache for a URL that's likely to be opened next. */
    prefetch: (url: string) => void;
}

/** The record modal, provided by RecordModalProvider. */
export const RecordModalContext = createContext<RecordModal | null>(null);

/** True inside the modal, so tables there skip controls that would change the page behind. */
export const InModalContext = createContext(false);

/** Get the record modal, or throw outside RecordModalProvider. */
export function useRecordModal(): RecordModal {
    const modal = useContext(RecordModalContext);
    if (!modal) throw new Error('useRecordModal() must be used inside <RecordModalProvider>.');
    return modal;
}

/** Render any record page over the current one, as a large modal or a slide-over. */
export function RecordModalProvider({ children }: { children: ReactNode }) {
    const [url, setUrl] = useState<string | null>(null);
    const [overlay, setOverlay] = useState<Overlay>('modal');
    const [data, setData] = useState<RecordData | null>(null);
    const [error, setError] = useState<string | null>(null);
    const version = useButtonVersion();

    const show = useCallback((next: string, fresh = false) => {
        setUrl(next);
        setError(null);
        if (!fresh) setData(null);
        load(next, fresh).then(setData, (e: Error) => setError(e.message));
    }, []);

    // A click or submit (from the modal or the page behind it) can change any record, so forget the
    // cache and, if the modal is open, refetch what it shows.
    useEffect(() => {
        if (version === 0) return;
        cache.clear();
        if (url) show(url, true);
    }, [version]); // eslint-disable-line react-hooks/exhaustive-deps -- reacts to successful events only; url and show are read at that moment.

    const close = () => {
        setUrl(null);
        setData(null);
    };

    return (
        <RecordModalContext.Provider
            value={{
                open: (next, style = 'modal') => {
                    setOverlay(style);
                    show(next);
                },
                prefetch: (next) => void load(next).catch(() => {}),
            }}
        >
            {children}
            <OverlayFrame overlay={overlay} open={url !== null} onClose={close}>
                {(Title, Description) =>
                    data && url ? (
                        <RecordBody data={data} url={url} Title={Title} Description={Description} />
                    ) : error ? (
                        <div className="p-6">
                            <Title>Record unavailable</Title>
                            <p className="text-muted-foreground mt-2 text-sm">{error}</p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4 p-6">
                            <Title className="sr-only">Loading</Title>
                            <Skeleton className="h-7 w-64" />
                            <Skeleton className="h-4 w-80 max-w-full" />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Skeleton className="h-48" />
                                <Skeleton className="h-48" />
                            </div>
                        </div>
                    )
                }
            </OverlayFrame>
        </RecordModalContext.Provider>
    );
}

type TitleComponent = typeof DialogTitle | typeof SheetTitle;
type DescriptionComponent = typeof DialogDescription | typeof SheetDescription;

/** The container: a centred dialog, or a sheet sliding in from the right. Same body either way. */
function OverlayFrame({
    overlay,
    open,
    onClose,
    children,
}: {
    overlay: Overlay;
    open: boolean;
    onClose: () => void;
    children: (Title: TitleComponent, Description: DescriptionComponent) => ReactNode;
}) {
    // Following a link inside the overlay navigates the page behind it, so close first. New-tab links don't.
    const closeOnLink = (event: React.MouseEvent) => {
        const link = (event.target as HTMLElement).closest('a[href]');
        if (link && link.getAttribute('target') !== '_blank') onClose();
    };

    if (overlay === 'slide-over') {
        return (
            <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
                <SheetContent
                    side="right"
                    aria-describedby={undefined}
                    className="w-full gap-0 overflow-y-auto p-0 sm:max-w-2xl"
                    onClickCapture={closeOnLink}
                >
                    {children(SheetTitle, SheetDescription)}
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent aria-describedby={undefined} className="max-h-[88vh] gap-0 overflow-y-auto p-0 sm:max-w-4xl" onClickCapture={closeOnLink}>
                {children(DialogTitle, DialogDescription)}
            </DialogContent>
        </Dialog>
    );
}

function RecordBody({
    data: { page, components },
    url,
    Title,
    Description,
}: {
    data: RecordData;
    url: string;
    Title: TitleComponent;
    Description: DescriptionComponent;
}) {
    return (
        <div className="flex flex-col gap-5 p-6">
            <div className="flex flex-col gap-1.5 pr-8 text-left">
                <div className="flex flex-wrap items-center gap-2">
                    <Title className="text-xl font-semibold tracking-tight">{page.title}</Title>
                    {page.badges.map((badge, i) => (
                        <StatusBadge key={i} value={badge.value} colors={badge.colors} />
                    ))}
                </div>
                {page.description && <Description>{page.description}</Description>}
            </div>
            <PageButtons buttons={page.buttons} />
            <InModalContext.Provider value={true}>
                <div className="@container flex flex-col gap-4">
                    {components.map((node, i) => (
                        <Node key={`${url}:${i}`} node={node} />
                    ))}
                </div>
            </InModalContext.Provider>
            <div className="flex justify-end border-t pt-4">
                <Button asChild variant="outline" size="sm">
                    <Link href={url}>
                        Open full page
                        <ArrowUpRight />
                    </Link>
                </Button>
            </div>
        </div>
    );
}
