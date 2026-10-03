import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { cn } from 'cn';
import { Check, Minus, Pencil } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { formatValue } from '../lib/format';
import type { FormComponent, FormCondition, FormField, FormLayout, FormSpec, FormValues } from '../protocol';
import { Button } from '../ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Checkbox } from '../ui/checkbox';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { Switch } from '../ui/switch';
import { Textarea } from '../ui/textarea';

/** Validation errors by field name. */
export type Errors = Record<string, string>;

const isLayout = (component: FormComponent): component is FormLayout => 'layout' in component;

/** Every field in a schema, depth first. */
export function fieldsOf(schema: FormComponent[]): FormField[] {
    return schema.flatMap((component) => (isLayout(component) ? fieldsOf(component.children) : [component]));
}

/** A value as a condition compares it: booleans as 'true' / 'false', null and undefined as ''. */
function asConditionValue(value: unknown): string {
    if (typeof value === 'boolean') return String(value);
    if (value === null || value === undefined) return '';
    return String(value);
}

/** Whether a condition holds for the current values, or undefined when there is no condition. */
function matches(condition: FormCondition | undefined, values: FormValues): boolean | undefined {
    if (!condition) return undefined;
    const value = values[condition.field];
    return Array.isArray(value)
        ? value.some((v) => condition.values.includes(asConditionValue(v)))
        : condition.values.includes(asConditionValue(value));
}

/** A field's state for the current values. Mirrors the exclude_unless / required_if rules the server builds. */
export function fieldState(field: FormField, values: FormValues) {
    const { when = {} } = field;
    const visible = field.type !== 'hidden' && matches(when.visible, values) !== false && matches(when.hidden, values) !== true;

    return {
        visible,
        required: field.required || matches(when.required, values) === true,
        disabled: Boolean(field.disabled) || matches(when.disabled, values) === true,
        editable: !field.read_only && !field.disabled && matches(when.disabled, values) !== true,
    };
}

/**
 * What to POST: values of fields that are visible and editable. The server drops the rest too
 * (exclude rules), this just keeps requests small. Files are only sent when a new one was chosen.
 */
export function formPayload(spec: FormSpec, values: FormValues): Record<string, FormDataConvertible> {
    const payload: Record<string, FormDataConvertible> = {};

    for (const field of fieldsOf(spec.schema)) {
        const state = fieldState(field, values);
        if (field.type !== 'hidden' && (!state.visible || !state.editable)) continue;
        const value = values[field.name];
        if (field.type === 'file' && !(value instanceof File)) continue;
        payload[field.name] = (value ?? null) as FormDataConvertible;
    }

    return payload;
}

const snapshot = (values: FormValues) => JSON.stringify(values, (_, v) => (v instanceof File ? `file:${v.name}:${v.size}` : v));

/** Has the form changed since it opened? */
export const isDirty = (values: FormValues, initial: FormValues) => snapshot(values) !== snapshot(initial);

/** Ask before leaving the page (or closing the tab) with unsaved changes. Skips posts, partial reloads and prefetches. */
export function useUnsavedChangesGuard(dirty: boolean) {
    useEffect(() => {
        if (!dirty) return;
        const unload = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', unload);
        const off = router.on('before', (event) => {
            const visit = event.detail.visit as { method: string; only: string[]; prefetch?: boolean };
            if (visit.method !== 'get' || visit.only.length > 0 || visit.prefetch) return;
            if (!window.confirm('You have unsaved changes. Leave without saving?')) event.preventDefault();
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            off();
        };
    }, [dirty]);
}

/** The "None" row's value in an optional single select; never reaches the form state. */
const NONE = '__none';

// Literal class names so Tailwind generates them.
const GRID: Record<number, string> = { 2: '@md:grid-cols-2', 3: '@xl:grid-cols-3', 4: '@2xl:grid-cols-4' };
const SPAN: Record<number, string> = { 2: '@md:col-span-2', 3: '@xl:col-span-3', 4: '@2xl:col-span-4' };

/** The <input type> for each text-like field type; anything else is plain text. */
const INPUT_TYPES: Record<string, string> = {
    number: 'number',
    date: 'date',
    datetime: 'datetime-local',
    time: 'time',
    email: 'email',
    url: 'url',
    tel: 'tel',
    password: 'password',
};

interface BodyProps {
    schema: FormComponent[];
    values: FormValues;
    errors?: Errors;
    /** Show values instead of inputs. */
    readOnly?: boolean;
    onChange?: (name: string, value: unknown) => void;
}

/** A form's fields and layout, as inputs or (readOnly) as label/value pairs. */
export function FormBody({ schema, values, errors = {}, readOnly = false, onChange }: BodyProps) {
    return (
        <div className="@container grid gap-4">
            {schema.map((component, i) => (
                <FormPart
                    key={isLayout(component) ? `layout-${i}` : component.name}
                    component={component}
                    values={values}
                    errors={errors}
                    readOnly={readOnly}
                    onChange={onChange}
                />
            ))}
        </div>
    );
}

function FormPart({ component, ...props }: Omit<BodyProps, 'schema'> & { component: FormComponent }) {
    const { values, errors = {}, readOnly, onChange } = props;

    if (isLayout(component)) {
        const visibleChildren = component.children.filter((child) => isLayout(child) || fieldState(child, values).visible);
        if (visibleChildren.length === 0) return null;

        const grid = (
            <div className={cn('grid gap-4', GRID[component.columns])}>
                {component.children.map((child, i) => (
                    <FormPart key={isLayout(child) ? `layout-${i}` : child.name} component={child} {...props} />
                ))}
            </div>
        );

        if (component.layout === 'grid') return <div className="col-span-full">{grid}</div>;

        return (
            <div role="group" aria-label={component.heading} className="col-span-full grid gap-3 border-t pt-4 first:border-t-0 first:pt-0">
                <div>
                    <h3 className="text-sm font-semibold">{component.heading}</h3>
                    {component.description && <p className="text-muted-foreground text-sm">{component.description}</p>}
                </div>
                {grid}
            </div>
        );
    }

    const state = fieldState(component, values);
    if (!state.visible) return null;
    const span = component.span ? SPAN[component.span] : undefined;

    if (readOnly || component.read_only) {
        return (
            <div className={cn('grid gap-1', span)}>
                <div className="text-muted-foreground text-sm">{component.label}</div>
                <div className="min-w-0 text-sm break-words">
                    <FieldValue field={component} value={values[component.name]} />
                </div>
            </div>
        );
    }

    return (
        <div className={span}>
            <FieldControl
                field={{ ...component, required: state.required, disabled: state.disabled }}
                value={values[component.name]}
                error={errors[component.name] ?? errors[`${component.name}.0`]}
                onChange={(value) => onChange?.(component.name, value)}
            />
        </div>
    );
}

/** A field's value as text, for read-only forms. */
export function FieldValue({ field, value }: { field: FormField; value: unknown }) {
    const empty = <span className="text-muted-foreground">—</span>;

    if (field.type === 'boolean' || field.type === 'toggle') {
        return value ? (
            <span className="inline-flex items-center gap-1">
                <Check className="size-4 text-emerald-600" /> Yes
            </span>
        ) : (
            <span className="text-muted-foreground inline-flex items-center gap-1">
                <Minus className="size-4" /> No
            </span>
        );
    }

    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) return empty;
    if (field.type === 'password') return <span>••••••••</span>;

    if (field.type === 'select') {
        const label = (v: unknown) => field.options?.find((o) => o.value === String(v))?.label ?? String(v);
        return <>{Array.isArray(value) ? value.map(label).join(', ') : label(value)}</>;
    }

    // A bare Y-m-d parses as UTC midnight, which is the day before west of Greenwich; read it as local.
    if (field.type === 'date') return <>{formatValue(/^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? `${value}T00:00` : value, 'date')}</>;
    if (field.type === 'datetime') return <>{formatValue(value, 'datetime')}</>;
    if (field.type === 'textarea' || field.type === 'markdown') return <span className="whitespace-pre-wrap">{String(value)}</span>;
    if (field.type === 'file') return <>{String(value).split('/').pop()}</>;
    const text = field.type === 'number' && value !== '' && !isNaN(Number(value)) ? formatValue(value, 'number') : String(value);

    return (
        <>
            {field.prefix}
            {text}
            {field.suffix && ` ${field.suffix}`}
        </>
    );
}

/** One form control, picked from the field type. */
export function FieldControl({
    field: f,
    value,
    error,
    onChange,
}: {
    field: FormField;
    value: unknown;
    error?: string;
    onChange: (value: unknown) => void;
}) {
    const id = `field-${f.name}`;
    const note = (
        <>
            {f.help && <p className="text-muted-foreground text-xs">{f.help}</p>}
            {error && <p className="text-destructive text-sm">{error}</p>}
        </>
    );

    if (f.type === 'hidden') return null;

    if (f.type === 'boolean' || f.type === 'toggle') {
        const Control = f.type === 'toggle' ? Switch : Checkbox;
        return (
            <div className="grid gap-1.5">
                <div className="flex items-center gap-2">
                    <Control
                        id={id}
                        checked={Boolean(value)}
                        disabled={f.disabled}
                        autoFocus={f.autofocus}
                        onCheckedChange={(checked: boolean | 'indeterminate') => onChange(checked === true)}
                    />
                    <Label htmlFor={id}>{f.label}</Label>
                </div>
                {note}
            </div>
        );
    }

    const common = { id, 'aria-invalid': Boolean(error), placeholder: f.placeholder, disabled: f.disabled, autoFocus: f.autofocus };
    let control: ReactNode;

    if (f.type === 'select' && f.multiple) {
        // A checkbox list: accessible, no extra dependency, fine for a handful of options.
        const selected = new Set((value as string[] | undefined) ?? []);
        control = (
            <div id={id} role="group" aria-invalid={Boolean(error)} className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3">
                {f.options?.map((option) => (
                    <label key={option.value} className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={selected.has(option.value)}
                            disabled={f.disabled}
                            onCheckedChange={(checked) => {
                                const next = new Set(selected);
                                checked === true ? next.add(option.value) : next.delete(option.value);
                                onChange([...next]);
                            }}
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        );
    } else if (f.type === 'select') {
        // Radix Select has no empty value, so an optional select gets a "None" row with a sentinel; the form state keeps null.
        const chosen = value === null || value === undefined || value === '' ? null : String(value);
        const clearable = !f.required;
        const placeholder = f.placeholder ?? 'Choose…';
        control = (
            <Select value={chosen ?? (clearable ? NONE : '')} onValueChange={(next) => onChange(next === NONE ? null : next)} disabled={f.disabled}>
                <SelectTrigger {...common} className="w-full">
                    <SelectValue placeholder={placeholder}>
                        {chosen === null ? <span className="text-muted-foreground">{placeholder}</span> : undefined}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {clearable && (
                        <SelectItem value={NONE} className="text-muted-foreground">
                            None
                        </SelectItem>
                    )}
                    {f.options?.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    } else if (f.type === 'textarea' || f.type === 'markdown') {
        control = (
            <Textarea
                {...common}
                maxLength={typeof f.max === 'number' ? f.max : undefined}
                rows={f.rows ?? (f.type === 'markdown' ? 6 : 3)}
                className={f.type === 'markdown' ? 'font-mono text-[13px]' : undefined}
                value={(value as string) ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        );
    } else if (f.type === 'file') {
        control = (
            <>
                {typeof value === 'string' && value && (
                    <p className="text-muted-foreground text-xs">Current: {value.split('/').pop()}. Choose a file to replace it.</p>
                )}
                <Input {...common} type="file" accept={f.accept} onChange={(e) => onChange(e.target.files?.[0] ?? null)} />
            </>
        );
    } else {
        const type = INPUT_TYPES[f.type] ?? 'text';
        const text = !['number', 'date', 'datetime-local', 'time'].includes(type);
        const input = (
            <Input
                {...common}
                type={type}
                maxLength={text && typeof f.max === 'number' ? f.max : undefined}
                minLength={text && typeof f.min === 'number' ? f.min : undefined}
                max={text ? undefined : f.max}
                min={text ? undefined : f.min}
                step={f.step}
                autoComplete={type === 'password' ? 'new-password' : undefined}
                className={cn(f.prefix && 'pl-(--prefix-width)', f.suffix && 'pr-10')}
                // Pads by the prefix's character count, which is close enough for short prefixes like £ or https://.
                style={f.prefix ? ({ '--prefix-width': `${f.prefix.length * 0.6 + 1.25}rem` } as React.CSSProperties) : undefined}
                value={(value as string | number) ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        );
        control =
            f.prefix || f.suffix ? (
                <div className="relative">
                    {f.prefix && (
                        <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm">{f.prefix}</span>
                    )}
                    {input}
                    {f.suffix && (
                        <span className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm">
                            {f.suffix}
                        </span>
                    )}
                </div>
            ) : (
                input
            );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {f.label}
                {!f.required && <span className="text-muted-foreground font-normal">(optional)</span>}
                {f.type === 'markdown' && <span className="text-muted-foreground ml-auto font-normal">Markdown</span>}
            </Label>
            {control}
            {note}
        </div>
    );
}

/** Errors that don't belong to a visible field (from a ValidationException in the callback), shown above the buttons. */
export function generalError(errors: Errors, schema: FormComponent[]): string | undefined {
    const names = new Set(fieldsOf(schema).map((f) => f.name));
    return Object.entries(errors).find(([key]) => !names.has(key.split('.')[0]))?.[1];
}

interface SubmitOptions {
    onSuccess?: () => void;
}

/** POST a form's values (and the rows it acts on) through Inertia, tracking processing and errors. */
export function useFormSubmit(url: string, ids: (string | number)[] = []) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const submit = (spec: FormSpec, values: FormValues, options: SubmitOptions = {}) => {
        router.post(url, { ids, ...formPayload(spec, values) } as Record<string, FormDataConvertible>, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setErrors({});
                options.onSuccess?.();
            },
            onError: (bag) => setErrors(bag as Errors),
        });
    };

    return { submit, processing, errors, setErrors };
}

/**
 * Section::form(): the values with an Edit button, turning into the form in place.
 * A form that creates (no fill()) is an inline form instead, which clears after saving.
 */
export function FormSection({ heading, description, form: spec }: { heading: string; description: string | null; form: FormSpec }) {
    const creating = spec.creates && !spec.read_only;
    const initial = spec.values ?? {};
    const [editing, setEditing] = useState(creating);
    const [values, setValues] = useState<FormValues>(initial);
    const { submit, processing, errors, setErrors } = useFormSubmit(spec.url);
    const dirty = editing && isDirty(values, initial);

    useUnsavedChangesGuard(dirty);

    // Fresh values from the server (after a save) replace ours unless we're mid-edit.
    const serverValues = JSON.stringify(spec.values ?? {});
    useEffect(() => {
        if (!editing || creating) setValues(spec.values ?? {});
    }, [serverValues]); // eslint-disable-line react-hooks/exhaustive-deps -- serverValues is spec.values as a string; the rest is read when it changes.

    // Locked since it was opened (by a save elsewhere): stop editing.
    useEffect(() => {
        if (spec.read_only && !creating) setEditing(false);
    }, [spec.read_only]); // eslint-disable-line react-hooks/exhaustive-deps -- only a change in read_only should end an edit.

    const startEditing = () => {
        setValues(spec.values ?? {});
        setErrors({});
        setEditing(true);
    };

    const cancel = () => {
        if (dirty && !window.confirm('Discard your changes?')) return;
        setValues(spec.values ?? {});
        setErrors({});
        if (!creating) setEditing(false);
    };

    const general = generalError(errors, spec.schema);

    return (
        <Card className="gap-4 py-4">
            <CardHeader className="px-4">
                <CardTitle>{heading}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
                {!editing && !spec.read_only && (
                    <CardAction>
                        <Button size="sm" variant="outline" onClick={startEditing}>
                            <Pencil />
                            Edit
                        </Button>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent className="px-4">
                {editing ? (
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            submit(spec, values, {
                                onSuccess: () => (creating ? setValues(spec.values ?? {}) : setEditing(false)),
                            });
                        }}
                    >
                        <FormBody
                            schema={spec.schema}
                            values={values}
                            errors={errors}
                            onChange={(name, value) => {
                                setValues((current) => ({ ...current, [name]: value }));
                                setErrors(({ [name]: _, ...rest }) => rest);
                            }}
                        />
                        {general && <p className="text-destructive text-sm">{general}</p>}
                        <div className="flex justify-end gap-2">
                            {(!creating || dirty) && (
                                <Button type="button" variant="outline" onClick={cancel}>
                                    {creating ? 'Clear' : 'Cancel'}
                                </Button>
                            )}
                            <Button type="submit" disabled={processing || (!creating && !dirty)}>
                                {spec.submit}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <FormBody schema={spec.schema} values={values} readOnly />
                )}
            </CardContent>
        </Card>
    );
}
