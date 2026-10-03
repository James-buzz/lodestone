import { router, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import { ChevronDown, Circle } from 'lucide-react';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { isHttpError, requestJson } from '../lib/http';
import type { ButtonSpec, FormSpec, FormValues, FormValuesResponse, SharedProps, Toast } from '../protocol';
import { useRegistry } from '../registry';
import { Button } from '../ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '../ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../ui/dropdown-menu';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '../ui/sheet';
import { Skeleton } from '../ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '../ui/tooltip';
import { isInternal } from './common';
import { FormBody, generalError, isDirty, useFormSubmit } from './form';
import { RecordModalContext, type RecordModal } from './record-modal';
import { toneText } from './status-badge';

/** A row id. */
export type Id = string | number;

/** Press a button: confirm first if it asks, then do what its kind says. `ids` are the rows it acts on. */
export type Press = (button: ButtonSpec, ids?: Id[], onDone?: () => void) => void;

interface Pending {
    button: ButtonSpec;
    ids: Id[];
    onDone?: () => void;
    modal: RecordModal | null;
}

const PressContext = createContext<((pending: Pending) => void) | null>(null);

// Bumped after every successful click or submit, so views that fetch their own data (the record modal) can refresh.
const VersionContext = createContext(0);

/** How many clicks and submits have succeeded, so a view that fetches its own data knows to refresh. */
export function useButtonVersion(): number {
    return useContext(VersionContext);
}

/** Get the press function from the nearest ButtonProvider. */
export function usePress(): Press {
    const press = useContext(PressContext);
    const modal = useContext(RecordModalContext);
    if (!press) throw new Error('usePress() must be used inside <ButtonProvider>.');
    return (button, ids = [], onDone) => press({ button, ids, onDone, modal });
}

/** Append ?ids[]=… to a URL, for requests the browser makes itself (downloads, a row form's values). */
export function withIds(url: string, ids: Id[]): string {
    const next = new URL(url, window.location.href);
    for (const id of ids) next.searchParams.append('ids[]', String(id));
    return next.toString();
}

const SHOW_TOAST: Record<Toast['tone'], (message: string) => void> = {
    success: toast.success,
    danger: toast.error,
    warning: toast.warning,
    info: toast.info,
};

const applies = (ids: Id[]) => (ids.length > 1 ? `Applies to ${ids.length} selected rows.` : '');

/** Hold the confirm dialog and form overlays, and show toasts flashed by callbacks. */
export function ButtonProvider({ children }: { children: ReactNode }) {
    const [confirming, setConfirming] = useState<Pending | null>(null);
    const [filling, setFilling] = useState<Pending | null>(null);
    const [processing, setProcessing] = useState(false);
    const [version, setVersion] = useState(0);
    const flash = usePage<SharedProps>().props.toast;

    useEffect(() => {
        if (flash) (SHOW_TOAST[flash.tone] ?? toast)(flash.message);
    }, [flash]);

    const done = (pending: Pending) => {
        setVersion((v) => v + 1);
        pending.onDone?.();
    };

    const run = (pending: Pending) => {
        const { button, ids } = pending;

        switch (button.kind) {
            case 'click':
                return router.post(
                    button.url,
                    { ids },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: () => {
                            setConfirming(null);
                            done(pending);
                        },
                        onError: (bag) => {
                            setConfirming(null);
                            toast.error(Object.values(bag)[0] ?? "That didn't work.");
                        },
                    },
                );
            case 'download':
                // A plain browser request, so the browser handles the file.
                setConfirming(null);
                window.location.assign(withIds(button.url, ids));
                return pending.onDone?.();
            case 'form':
                setConfirming(null);
                return setFilling(pending);
            case 'visit':
                setConfirming(null);
                return isInternal(button.url) ? router.visit(button.url) : window.location.assign(button.url);
            case 'open':
                setConfirming(null);
                return void window.open(button.url, '_blank', 'noopener,noreferrer');
            case 'modal':
                setConfirming(null);
                return pending.modal ? pending.modal.open(button.url, button.overlay) : router.visit(button.url);
            case 'copy':
                setConfirming(null);
                return void navigator.clipboard?.writeText(button.text).then(
                    () => toast.success('Copied.'),
                    () => toast.error("Couldn't copy."),
                );
        }
    };

    const press = (pending: Pending) => {
        if (pending.button.disabled) return;
        if (pending.button.confirm) return setConfirming(pending);
        run(pending);
    };

    const confirm = confirming?.button.confirm;
    const description = [confirm?.description, confirming && applies(confirming.ids)].filter(Boolean).join(' ');

    return (
        <PressContext.Provider value={press}>
            {/* One tooltip provider for every button on the page, including those in overlays. */}
            <TooltipProvider delayDuration={200}>
                <VersionContext.Provider value={version}>{children}</VersionContext.Provider>
            </TooltipProvider>
            <Dialog open={confirming !== null} onOpenChange={(open) => !open && setConfirming(null)}>
                {confirming && confirm && (
                    <DialogContent>
                        <form
                            className="grid gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                run(confirming);
                            }}
                        >
                            <DialogHeader>
                                <DialogTitle>{confirm.title}</DialogTitle>
                                <DialogDescription className={description ? undefined : 'sr-only'}>{description || confirm.title}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setConfirming(null)}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant={confirming.button.tone === 'danger' ? 'destructive' : 'default'}
                                    disabled={processing}
                                    autoFocus
                                >
                                    {confirming.button.label}
                                    {confirming.ids.length > 1 ? ` ${confirming.ids.length}` : ''}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                )}
            </Dialog>
            {filling && filling.button.kind === 'form' && (
                <FormOverlay
                    key={`${filling.button.key}:${filling.ids.join(',')}`}
                    title={filling.button.label}
                    spec={filling.button.form}
                    ids={filling.ids}
                    onClose={() => setFilling(null)}
                    onSaved={() => {
                        setFilling(null);
                        done(filling);
                    }}
                />
            )}
        </PressContext.Provider>
    );
}

interface Loaded {
    values: FormValues;
    initial: FormValues;
    readOnly: boolean;
}

/** What to tell someone whose row form couldn't load, by status. */
function loadErrorMessage(error: unknown): string {
    const status = isHttpError(error) ? error.status : 0;

    // A 404 means the server rebuilt the page and this button no longer applies to the row.
    if (status === 404) return "This isn't available for this record any more.";
    if (status === 401 || status === 403) return "You don't have access to this record.";
    return "This record couldn't be loaded.";
}

/** A form opened from a button, as a modal or slide-over. A row's form loads that row's values first. */
function FormOverlay({ title, spec, ids, onClose, onSaved }: { title: string; spec: FormSpec; ids: Id[]; onClose: () => void; onSaved: () => void }) {
    const fromValues = (values: FormValues, readOnly: boolean): Loaded => ({ values, initial: values, readOnly });
    const [loaded, setLoaded] = useState<Loaded | null>(spec.values ? fromValues(spec.values, Boolean(spec.read_only)) : null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const { submit, processing, errors, setErrors } = useFormSubmit(spec.url, ids);

    useEffect(() => {
        if (loaded) return;
        const controller = new AbortController();
        requestJson<FormValuesResponse>(withIds(spec.url, ids), { signal: controller.signal })
            .then((response) => setLoaded(fromValues(response.values, response.read_only)))
            .catch((error: unknown) => {
                if (controller.signal.aborted) return;
                setLoadError(loadErrorMessage(error));
            });
        return () => controller.abort();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps -- runs once per overlay; the key remounts it per row.

    const dirty = loaded ? isDirty(loaded.values, loaded.initial) : false;
    const close = () => {
        if (dirty && !window.confirm('Discard your changes?')) return;
        onClose();
    };
    const general = generalError(errors, spec.schema);
    const readOnly = loaded?.readOnly ?? false;
    const description = [spec.description, applies(ids)].filter(Boolean).join(' ');
    const formId = `lodestone-form-${spec.key}`;

    const body = loadError ? (
        <p className="text-destructive text-sm">{loadError}</p>
    ) : !loaded ? (
        <div className="grid gap-4">
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-2/3" />
        </div>
    ) : (
        <form
            id={formId}
            className="grid gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                submit(spec, loaded.values, { onSuccess: onSaved });
            }}
        >
            <FormBody
                schema={spec.schema}
                values={loaded.values}
                errors={errors}
                readOnly={readOnly}
                onChange={(name, value) => {
                    setLoaded((current) => current && { ...current, values: { ...current.values, [name]: value } });
                    setErrors(({ [name]: _, ...rest }) => rest);
                }}
            />
            {general && <p className="text-destructive text-sm">{general}</p>}
        </form>
    );

    const buttons = (
        <>
            <Button type="button" variant="outline" onClick={close}>
                {readOnly ? 'Close' : 'Cancel'}
            </Button>
            {!readOnly && (
                <Button type="submit" form={formId} disabled={processing || !loaded || (!spec.creates && !dirty)}>
                    {spec.submit}
                    {ids.length > 1 ? ` ${ids.length}` : ''}
                </Button>
            )}
        </>
    );

    if (spec.placement === 'slide-over') {
        return (
            <Sheet open onOpenChange={(open) => !open && close()}>
                <SheetContent className="w-full gap-0 sm:max-w-xl">
                    <SheetHeader className="border-b">
                        <SheetTitle>{title}</SheetTitle>
                        <SheetDescription className={description ? undefined : 'sr-only'}>{description || title}</SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 overflow-y-auto p-4">{body}</div>
                    <SheetFooter className="flex-row justify-end border-t">{buttons}</SheetFooter>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className={description ? undefined : 'sr-only'}>{description || title}</DialogDescription>
                </DialogHeader>
                {body}
                <DialogFooter>{buttons}</DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const ICON_SIZE = { xs: 'icon-xs', sm: 'icon-sm', default: 'icon' } as const;

/** Wrap a disabled or icon-only button in a tooltip with its reason or label. */
function WithTooltip({ text, children }: { text?: string; children: ReactNode }) {
    if (!text) return <>{children}</>;

    return (
        <Tooltip>
            {/* A span, because disabled buttons don't fire the pointer events tooltips need. */}
            <TooltipTrigger asChild>
                <span tabIndex={0} className="inline-flex">
                    {children}
                </span>
            </TooltipTrigger>
            <TooltipContent>{text}</TooltipContent>
        </Tooltip>
    );
}

/**
 * One ButtonSpec as a shadcn button. `primary` makes it solid (red for danger); the rest are outlined.
 * `disabled` overrides the spec's own, for row buttons checked per row.
 */
export function LodestoneButton({
    button,
    ids,
    primary = false,
    disabled,
    disabledReason,
    onDone,
    className,
}: {
    button: ButtonSpec;
    ids?: Id[];
    primary?: boolean;
    disabled?: boolean;
    disabledReason?: string;
    onDone?: () => void;
    className?: string;
}) {
    const press = usePress();
    const { icons } = useRegistry();
    const Icon = button.icon ? (icons[button.icon] ?? Circle) : null;
    const isDisabled = disabled ?? Boolean(button.disabled);
    const reason = isDisabled ? (disabledReason ?? button.disabled_reason) : undefined;
    const size = button.icon_only ? ICON_SIZE[button.size ?? 'sm'] : (button.size ?? 'sm');

    return (
        <WithTooltip text={reason || (button.icon_only ? button.label : undefined)}>
            <Button
                type="button"
                size={size}
                variant={primary ? (button.tone === 'danger' ? 'destructive' : 'default') : 'outline'}
                disabled={isDisabled}
                aria-label={button.icon_only ? button.label : undefined}
                className={cn(!primary && button.tone && toneText(button.tone), className)}
                onClick={() => press(button, ids, onDone)}
            >
                {Icon && <Icon />}
                {!button.icon_only && button.label}
            </Button>
        </WithTooltip>
    );
}

const SHOWN = 3;

/** Page::buttons() for a page or record modal: the first three as buttons, the rest under "More". */
export function PageButtons({ buttons }: { buttons: ButtonSpec[] }) {
    const press = usePress();

    if (buttons.length === 0) return null;

    const shown = buttons.slice(0, SHOWN);
    const more = buttons.slice(SHOWN);

    return (
        <div className="flex flex-wrap gap-2">
            {shown.map((button, i) => (
                <LodestoneButton key={button.key} button={button} primary={i === 0} />
            ))}
            {more.length > 0 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="sm" variant="outline">
                            More
                            <ChevronDown />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-48">
                        {more.map((button) => (
                            <ButtonMenuItem
                                key={button.key}
                                button={button}
                                onSelect={() => press(button)}
                                disabled={button.disabled}
                                disabledReason={button.disabled ? button.disabled_reason : undefined}
                            />
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}

/** A button as a dropdown item, for "More" and row menus. Disabled items show their reason underneath. */
export function ButtonMenuItem({
    button,
    onSelect,
    disabled,
    disabledReason,
}: {
    button: ButtonSpec;
    onSelect: () => void;
    disabled?: boolean;
    disabledReason?: string;
}) {
    const { icons } = useRegistry();
    const Icon = button.icon ? (icons[button.icon] ?? Circle) : Circle;

    return (
        <DropdownMenuItem onSelect={onSelect} disabled={disabled} variant={button.tone === 'danger' ? 'destructive' : 'default'}>
            <Icon />
            <span className="flex flex-col">
                {button.label}
                {disabled && disabledReason && <span className="text-muted-foreground text-xs">{disabledReason}</span>}
            </span>
        </DropdownMenuItem>
    );
}
