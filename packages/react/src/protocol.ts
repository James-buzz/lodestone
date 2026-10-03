// The JSON contract between a Lodestone server (the PHP package) and this renderer.
// Any backend that emits these shapes gets the UI. Bump PROTOCOL on breaking changes.

/** Mirrors Lodestone::PROTOCOL; the page warns in development when the two differ. */
export const PROTOCOL = 2;

/** Lodestone\Enums\Tone. */
export type Tone = 'success' | 'danger' | 'warning' | 'info' | 'gray';

/** Lodestone\Enums\Overlay: how a form or record page opens over the page. */
export type Overlay = 'modal' | 'slide-over';

/** One sidebar page, emitted by HandleLodestoneRequests::share(). */
export interface NavItem {
    slug: string;
    title: string;
    icon: string;
    /** Sidebar section; null for the ungrouped pages listed first. */
    group: string | null;
    url: string;
    badge: { value: number; tone: Tone | null } | null;
}

/** A panel the user can open, emitted by LodestoneManager::switcher(). */
export interface PanelLink {
    id: string;
    title: string;
    description: string | null;
    icon: string | null;
    url: string;
    current: boolean;
}

/** Shared on every Inertia response by HandleLodestoneRequests::share(). */
export interface SharedProps {
    lodestone: {
        protocol: number;
        panel: { id: string; title: string; description: string | null; icon: string | null; url: string };
        panels: PanelLink[];
        nav: NavItem[];
    };
    /** Flashed by Ui::toast() in a callback, shown once. */
    toast: Toast | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** The tones Ui::toast() and Button::tone() accept; `Tone` adds 'gray' for badges and series. */
export type ButtonTone = 'danger' | 'warning' | 'success' | 'info';

/** Flashed by Lodestone\Ui::toast(). */
export interface Toast {
    message: string;
    tone: ButtonTone;
}

/** Emitted by Lodestone\Components\Badge::toArray(). */
export interface BadgeSpec {
    value: string | number | boolean | null;
    colors: Record<string, Tone>;
}

/** A field in a form's schema, emitted by Lodestone\Forms\Components\Field::toArray(). */
export interface FormField {
    name: string;
    type:
        | 'text'
        | 'email'
        | 'url'
        | 'tel'
        | 'password'
        | 'number'
        | 'textarea'
        | 'markdown'
        | 'date'
        | 'datetime'
        | 'time'
        | 'select'
        | 'boolean'
        | 'toggle'
        | 'file'
        | 'hidden';
    label: string;
    required: boolean;
    options?: { value: string; label: string }[];
    /** Selects: choose several. */
    multiple?: boolean;
    /** Files: the browser's accept filter. */
    accept?: string;
    max?: number | string;
    placeholder?: string;
    help?: string;
    /** Shown as a value, never an input. */
    read_only?: boolean;
    disabled?: boolean;
    autofocus?: boolean;
    /** Columns to fill in the surrounding section or grid. */
    span?: number;
    min?: number | string;
    step?: number;
    rows?: number;
    prefix?: string;
    suffix?: string;
    /** Conditions on other fields, evaluated as you type. The server applies the same ones. */
    when?: Partial<Record<'visible' | 'hidden' | 'required' | 'disabled', FormCondition>>;
}

/** Emitted by Field::visibleWhen() and friends: true when `field`'s value is one of `values` (booleans as 'true' / 'false'). */
export interface FormCondition {
    field: string;
    values: string[];
}

/** A Section or Grid inside a form, emitted by Lodestone\Forms\Components\Layout::toArray(). */
export interface FormLayout {
    layout: 'section' | 'grid';
    heading?: string;
    description?: string | null;
    columns: number;
    children: FormComponent[];
}

/** Anything in a form's schema. */
export type FormComponent = FormField | FormLayout;

/** A form's values by field name. */
export type FormValues = Record<string, unknown>;

/** Emitted by Lodestone\Forms\Form::toSchema(). */
export interface FormSpec {
    key: string;
    schema: FormComponent[];
    placement: Overlay;
    description?: string;
    /** Submit button label. */
    submit: string;
    /** No fill(): starts from defaults, and an inline one clears after saving. */
    creates: boolean;
    /** POST the values here. */
    url: string;
    /** Starting values. Missing for a row's form: GET url?ids[]={id} for { values, read_only }. */
    values?: FormValues;
    /** No editing (readOnly(), or no submit()). Missing until a row's form has loaded. */
    read_only?: boolean;
}

/** Returned by EventController for a row form's values request. */
export interface FormValuesResponse {
    values: FormValues;
    read_only: boolean;
}

interface ButtonBase {
    /** Where it sits on the page: 'buttons.retry', 'feeds.row.pause', 'feeds.bulk.archive'. */
    key: string;
    label: string;
    icon?: string;
    tone?: ButtonTone;
    size?: 'xs' | 'default';
    icon_only?: boolean;
    confirm?: { title: string; description: string | null };
    /** Header buttons only; row and bulk buttons are checked per row (Row._disabled). */
    disabled?: boolean;
    disabled_reason?: string;
}

/** Emitted by Lodestone\Components\Button::toSchema(). `kind` says what a click does. */
export type ButtonSpec = ButtonBase &
    (
        | { kind: 'click'; /** POST { ids } here. */ url: string }
        | { kind: 'download'; /** GET ?ids[]= here as a plain browser request. */ url: string }
        | { kind: 'form'; url: string; form: FormSpec }
        | { kind: 'visit' | 'open'; url: string }
        | { kind: 'modal'; url: string; overlay: Overlay }
        | { kind: 'copy'; text: string }
    );

/** The page header, emitted by PageController::meta(). */
export interface PageMeta {
    slug: string;
    title: string;
    description: string | null;
    url: string;
    /** Record pages only: [label, url] pairs before the title. */
    breadcrumbs: [string, string][];
    badges: BadgeSpec[];
    /** Page::buttons(), checked against the record on record pages. */
    buttons: ButtonSpec[];
}

/** Emitted by Lodestone\Components\Stat::toArray(). */
export interface StatItem {
    label: string;
    value: number | string | null;
    format?: string;
    tone?: Tone;
    description?: string;
    /** Sparkline points. */
    chart?: (number | null)[];
}

/** Emitted by Lodestone\Components\Stats::toSchema(). */
export interface StatsNode {
    type: 'stats';
    items: StatItem[];
}

/** Emitted by Lodestone\Tables\Columns\Column::toArray(). */
export interface Column {
    type: string;
    name: string;
    label: string;
    sortable?: boolean;
    searchable?: boolean;
    format?: string;
    align?: 'right';
    mono?: boolean;
    muted?: boolean;
    strong?: boolean;
    limit?: number;
    prefix?: string;
    suffix?: string;
    /** Shown instead of "—" for empty values. */
    placeholder?: string;
    /** Can be hidden from the "Columns" menu. */
    toggleable?: boolean;
    /** Hidden until someone turns it on. */
    hidden?: boolean;
    /** Text columns: a copy button beside the value. */
    copyable?: boolean;
    /** Text columns: show the value as a person, with an avatar (row['<name>:avatar'] or initials). */
    avatar?: boolean;
    colors?: Record<string, Tone>;
    /** Custom column types can send any extra settings. */
    [setting: string]: unknown;
}

/** Emitted by Lodestone\Tables\Filters\SelectFilter::toArray(). */
export interface SelectFilter {
    type: 'select';
    name: string;
    label: string;
    /** Pick several values. */
    multiple?: boolean;
    options: { value: string; label: string }[];
}

/** Emitted by Lodestone\Tables\Filters\DateRangeFilter::toArray(). */
export interface DateRangeFilter {
    type: 'date_range';
    name: string;
    label: string;
}

/** Any filter a table can carry. */
export type TableFilter = SelectFilter | DateRangeFilter;

/** A select's value, several values, or a { from, to } date range (Y-m-d). */
export type FilterValue = string | string[] | { from?: string; to?: string };

/**
 * One table row, emitted by Lodestone\Tables\Table::row(). Cells use flat keys matching Column.name
 * (row['feed.name']); per-record cell data rides along as row['<column name>:<key>'] (url, avatar,
 * description, or a custom column's own).
 */
export type Row = {
    id: string | number;
    /** Keys of the row and bulk buttons this row can use. */
    _buttons?: string[];
    /** Keys of row and bulk buttons shown greyed out for this row, with the reason ('' for none). */
    _disabled?: Record<string, string>;
    /** Record page to open when the row is clicked. */
    _url?: string;
    /** Record page to open in a modal when the row is clicked (Table::recordModal). */
    _modal?: string;
} & Record<string, unknown>;

/** The search, filters and sort the server applied, from Table::toSchema(). */
export interface TableState {
    search: string;
    filters: Record<string, FilterValue>;
    sort: string | null;
}

/** Emitted by Lodestone\Tables\Table::toSchema(). */
export interface TableNode {
    type: 'table';
    key: string;
    heading?: string;
    description?: string;
    /** Whether to show the search box. */
    searchable: boolean;
    columns: Column[];
    filters: TableFilter[];
    /** Max height; the header sticks while rows scroll. */
    sticky_header?: string;
    /** Shown when there are no rows and no search or filter is active. */
    empty_state?: EmptyStateNode;
    /** Above the table; already checked. */
    header_buttons: ButtonSpec[];
    /** In each row's menu, where the row's _buttons / _disabled list them. */
    row_buttons: ButtonSpec[];
    /** In the bar shown when rows are selected. */
    bulk_buttons: ButtonSpec[];
    state: TableState;
    /** How record pages from this table open over the page. */
    overlay?: Overlay;
    rows: Row[];
    pagination: { page: number; per_page: number; total: number; last_page: number };
}

/** Emitted by Lodestone\Components\Tabs::toSchema(). */
export interface TabsNode {
    type: 'tabs';
    tabs: { key: string; label: string; badge: number | null; children: SchemaNode[] }[];
}

/** Emitted by Lodestone\Components\Grid::toSchema(). */
export interface GridNode {
    type: 'grid';
    columns: number;
    children: SchemaNode[];
}

/** Emitted by Lodestone\Components\Fields::toSchema(). */
export interface FieldsNode {
    type: 'fields';
    heading: string | null;
    /** Any keys besides column and value are cell data (url, avatar, description…). */
    items: ({ column: Column; value: unknown } & Record<string, unknown>)[];
}

/** Emitted by Lodestone\Components\Section::toSchema(). */
export interface SectionNode {
    type: 'section';
    heading: string;
    description: string | null;
    collapsible: boolean;
    collapsed: boolean;
    children: SchemaNode[];
    /** Section::form(): the values with an Edit button, or an inline create form when it creates. */
    form?: FormSpec | null;
}

/** Emitted by Lodestone\Components\EmptyState::toSchema(). */
export interface EmptyStateNode {
    type: 'empty_state';
    heading: string;
    description: string | null;
    icon: string;
    link: { label: string; url: string } | null;
}

/** Emitted by Lodestone\Components\Code::toSchema(). */
export interface CodeNode {
    type: 'code';
    heading: string;
    value: unknown;
}

/** Emitted by Lodestone\Components\Alert::toSchema(). */
export interface AlertNode {
    type: 'alert';
    tone: Tone;
    title: string;
    text: string;
}

/** Emitted by Lodestone\Components\Chart::toSchema(). */
export interface ChartNode {
    type: 'chart';
    kind: 'bar' | 'line' | 'area';
    heading: string;
    description: string | null;
    /** Rows with an `x` value and one value per series key. */
    data: ({ x: string | number } & Record<string, unknown>)[];
    series: { key: string; label: string; tone: Tone | null }[];
    stacked: boolean;
    height: number;
    format: string | null;
    /** Set when x values are ISO timestamps. */
    x_format: 'hour' | 'day' | null;
}

/** Emitted by Lodestone\Components\Markdown::toSchema(). */
export interface MarkdownNode {
    type: 'markdown';
    heading: string | null;
    /** Rendered on the server with raw HTML stripped. Must already be safe. */
    html: string;
}

/** Every node this renderer draws out of the box. */
export type BuiltInNode =
    StatsNode | TableNode | TabsNode | GridNode | SectionNode | EmptyStateNode | FieldsNode | CodeNode | AlertNode | ChartNode | MarkdownNode;

/** Any node. Custom types are allowed; register a component for them in createLodestoneApp({ nodes }). */
export type SchemaNode = BuiltInNode | { type: string; [key: string]: unknown };

/** The props of the Lodestone/Page Inertia page, from PageController. */
export interface PageProps extends SharedProps {
    page: PageMeta;
    components: SchemaNode[];
}
