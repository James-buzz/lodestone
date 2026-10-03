<?php

namespace Lodestone\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Lodestone\Components\Button;
use Lodestone\Components\Component;
use Lodestone\Components\EmptyState;
use Lodestone\Facades\Lodestone;
use Lodestone\RecordPage;
use Lodestone\Support\Search;
use Lodestone\Tables\Columns\Column;
use Lodestone\Tables\Filters\Filter;
use LogicException;

/**
 * A server-side table. Its search, filters, sort and page live in the query string, namespaced
 * by the table's key so several tables can share a page:
 *
 *   ?runs[search]=zoopla&runs[filters][status]=failed&runs[sort]=-created_at&runs[page]=2
 */
class Table extends Component
{
    protected Builder|Relation|null $query = null;

    /** @var list<Column> */
    protected array $columns = [];

    /** @var list<Filter> */
    protected array $filters = [];

    protected int $perPage = 15;

    protected ?string $heading = null;

    protected ?string $description = null;

    /** @var array{header: list<Button>, row: list<Button>, bulk: list<Button>} */
    protected array $buttons = ['header' => [], 'row' => [], 'bulk' => []];

    /** @var class-string<RecordPage>|null */
    protected ?string $recordPage = null;

    protected ?Closure $recordResolver = null;

    protected bool $recordInModal = false;

    protected ?string $stickyHeader = null;

    protected ?EmptyState $emptyState = null;

    /** @var list<string> */
    protected array $searchIn = [];

    /**
     * Create a new table instance.
     */
    public function __construct(protected string $key) {}

    /**
     * Create a new table with the given key.
     */
    public static function make(string $key = 'table'): static
    {
        return new static($key);
    }

    /**
     * Set the key that namespaces the table's state in the query string.
     */
    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the query the table reads its rows from.
     */
    public function query(Builder|Relation $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set the table's columns.
     *
     * @param  list<Column>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Set the table's filters.
     *
     * @param  list<Filter>  $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Set extra attribute paths the search box matches that aren't shown as columns.
     *
     * @param  list<string>  $paths
     */
    public function searchIn(array $paths): static
    {
        $this->searchIn = $paths;

        return $this;
    }

    /**
     * Keep the header visible while the rows scroll inside the given height.
     */
    public function stickyHeader(string $maxHeight = '70vh'): static
    {
        $this->stickyHeader = $maxHeight;

        return $this;
    }

    /**
     * Get the table's key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Set the number of rows per page.
     */
    public function paginate(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Set the heading shown above the table, with an optional description.
     */
    public function heading(string $heading, ?string $description = null): static
    {
        $this->heading = $heading;
        $this->description = $description;

        return $this;
    }

    /**
     * Set what to show when the table has no rows at all.
     *
     * With a search or filter active, the table reports that nothing matched and offers to clear them instead.
     */
    public function emptyState(string|EmptyState $state): static
    {
        $this->emptyState = is_string($state) ? EmptyState::make($state) : $state;

        return $this;
    }

    /**
     * Set the buttons shown above the table, which need no selection.
     *
     * On a record page, their callbacks receive the page's record.
     *
     * @param  list<Button|null>  $buttons
     */
    public function headerButtons(array $buttons): static
    {
        $this->buttons['header'] = array_values(array_filter($buttons));

        return $this;
    }

    /**
     * Set the buttons shown in each row's menu.
     *
     * Conditions run per row, and callbacks receive the row.
     *
     * @param  list<Button|null>  $buttons
     */
    public function rowButtons(array $buttons): static
    {
        $this->buttons['row'] = array_values(array_filter($buttons));

        return $this;
    }

    /**
     * Set the buttons shown when rows are selected.
     *
     * Conditions run per row, and callbacks receive the rows that passed them.
     *
     * @param  list<Button|null>  $buttons
     */
    public function bulkButtons(array $buttons): static
    {
        $this->buttons['bulk'] = array_values(array_filter($buttons));

        return $this;
    }

    /**
     * Get every button keyed by its place on the page: "{table}.{slot}.{name}".
     *
     * @return array<string, array{0: 'header'|'row'|'bulk', 1: Button}>
     */
    public function buttons(): array
    {
        $keyed = [];

        foreach ($this->buttons as $slot => $buttons) {
            foreach ($buttons as $button) {
                $key = "{$this->key}.{$slot}.{$button->getName()}";

                if (isset($keyed[$key])) {
                    throw new LogicException("Table [{$this->key}] has two {$slot} buttons named [{$button->getName()}].");
                }

                $keyed[$key] = [$slot, $button];
            }
        }

        return $keyed;
    }

    /**
     * Get the event targets the table contributes to the page.
     *
     * @return array<string, array{slot: string, button: Button, table: Table}>
     */
    public function targets(): array
    {
        $targets = [];

        foreach ($this->buttons() as $key => [$slot, $button]) {
            $targets[$key] = ['slot' => $slot, 'button' => $button, 'table' => $this];
        }

        return $targets;
    }

    /**
     * Open the given record page when a row is clicked.
     *
     * Pass a resolver when the row isn't the page's record itself.
     *
     * @param  class-string<RecordPage>  $page
     */
    public function recordPage(string $page, ?Closure $resolve = null): static
    {
        $this->recordPage = $page;
        $this->recordResolver = $resolve;
        $this->recordInModal = false;

        return $this;
    }

    /**
     * Open the given record page in a modal over this page when a row is clicked.
     *
     * @param  class-string<RecordPage>  $page
     */
    public function recordModal(string $page, ?Closure $resolve = null): static
    {
        $this->recordPage($page, $resolve);
        $this->recordInModal = true;

        return $this;
    }

    /**
     * Get the records for a row or bulk button through the table's own query, so a click can
     * only reach rows the table would show.
     *
     * @param  list<int|string>  $ids
     * @return EloquentCollection<int, Model>
     */
    public function recordsFor(array $ids): EloquentCollection
    {
        return $this->baseQuery()->whereKey($ids)->get();
    }

    /**
     * Get the table's JSON node.
     *
     * @return array<string, mixed>
     */
    public function toSchema(Request $request): array
    {
        $base = $this->baseQuery();
        [$query, $state] = $this->filteredQuery($request);
        ['search' => $search, 'filters' => $filters, 'sort' => $sort] = $state;

        $page = $query->paginate(perPage: $this->perPage, page: $state['page']);

        // Header buttons and their forms on a record page are checked against its record.
        $record = Lodestone::record();
        $buttons = ['header' => [], 'row' => [], 'bulk' => []];
        $perRow = [];

        foreach ($this->buttons() as $key => [$slot, $button]) {
            if ($slot === 'header') {
                if ($button->isVisibleFor($record)) {
                    $buttons['header'][] = $button->toSchema($key, $record, 'header');
                }

                continue;
            }

            $buttons[$slot][] = $button->toSchema($key, null, $slot);
            $perRow[$key] = $button;
        }

        return array_filter([
            'type' => 'table',
            'key' => $this->key,
            'heading' => $this->heading,
            'description' => $this->description,
            'searchable' => $this->searchIn !== [] || array_filter($this->columns, fn (Column $column) => $column->isSearchable()) !== [],
            'columns' => array_map(fn (Column $column) => $column->toArray(), $this->columns),
            'filters' => array_map(fn (Filter $filter) => $filter->toArray($base), $this->filters),
            'sticky_header' => $this->stickyHeader,
            'empty_state' => $this->emptyState?->toSchema($request),
            'header_buttons' => $buttons['header'],
            'row_buttons' => $buttons['row'],
            'bulk_buttons' => $buttons['bulk'],
            'state' => [
                'search' => $search,
                'filters' => (object) $filters,
                'sort' => $sort ? ($sort[1] === 'desc' ? '-' : '').$sort[0] : null,
            ],
            'overlay' => $this->recordPage ? $this->recordPage::overlay()->value : null,
            // Relationship autoloading turns relation columns into one query per relation rather than one per row.
            'rows' => $page->getCollection()->withRelationshipAutoloading()->map(fn (Model $row) => $this->row($row, $perRow))->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * Get the query with the request's search, filters and sort applied, and the state to echo back.
     *
     * @return array{0: Builder, 1: array{search: string, filters: array<string, mixed>, sort: array{0: string, 1: string}|null, page: int}}
     */
    protected function filteredQuery(Request $request): array
    {
        $state = (array) $request->query($this->key, []);
        $search = is_string($state['search'] ?? null) ? trim($state['search']) : '';
        $filterMap = $this->filterMap();
        $filters = array_filter(
            array_intersect_key((array) ($state['filters'] ?? []), $filterMap),
            fn ($value, $name) => $filterMap[$name]->isActive($value),
            ARRAY_FILTER_USE_BOTH,
        );
        $sort = $this->parseSort($state['sort'] ?? null);
        $query = $this->baseQuery();

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        foreach ($filters as $name => $value) {
            $filterMap[$name]->apply($query, $value);
        }

        if ($sort) {
            // The column is qualified only when the query has joins, so aliases such as withCount()'s
            // runs_count still sort. The key breaks ties so rows don't shuffle between pages.
            $query->reorder($query->getQuery()->joins ? $query->qualifyColumn($sort[0]) : $sort[0], $sort[1])
                ->orderBy($query->getModel()->getQualifiedKeyName(), $sort[1]);
        } elseif (! $query->getQuery()->orders && ! $query->getQuery()->unionOrders) {
            // Without an order the database picks, and rows could shuffle between pages.
            $query->orderByDesc($query->getModel()->getQualifiedKeyName());
        }

        return [$query, ['search' => $search, 'filters' => $filters, 'sort' => $sort, 'page' => max(1, (int) ($state['page'] ?? 1))]];
    }

    /**
     * Get a fresh copy of the table's query.
     */
    protected function baseQuery(): Builder
    {
        if ($this->query === null) {
            throw new LogicException("Table [{$this->key}] has no query.");
        }

        if (! $this->query instanceof Relation) {
            return $this->query->clone();
        }

        // A relation only adds its select at get() time, so a joined one (belongsToMany, hasManyThrough)
        // would mix pivot or through columns into the rows. Select the related table only.
        $query = $this->query->getQuery()->clone();
        $base = $query->getQuery();

        return $base->joins && $base->columns === null ? $query->select($this->query->getRelated()->qualifyColumn('*')) : $query;
    }

    /**
     * Build one row of the table.
     *
     * @param  array<string, Button>  $buttons  The row and bulk buttons, by key.
     * @return array<string, mixed>
     */
    protected function row(Model $record, array $buttons): array
    {
        $row = ['id' => $record->getKey()];

        foreach ($this->columns as $column) {
            $row[$column->getName()] = $column->value($record);
        }

        // Each row names the buttons it can use, so menus and bulk bars only offer those.
        foreach ($buttons as $key => $button) {
            if (! $button->isVisibleFor($record)) {
                continue;
            }

            if ($button->isDisabledFor($record)) {
                $row['_disabled'][$key] = $button->getDisabledReason() ?? '';
            } else {
                $row['_buttons'][] = $key;
            }
        }

        if ($this->recordPage !== null && ($target = $this->recordResolver ? ($this->recordResolver)($record) : $record)) {
            $row[$this->recordInModal ? '_modal' : '_url'] = Lodestone::url($this->recordPage, $target);
        }

        foreach ($this->columns as $column) {
            foreach ($column->cellData($record) as $key => $value) {
                $row["{$column->getName()}:{$key}"] = $value;
            }
        }

        return $row;
    }

    /**
     * Apply the search term to the query across every searchable attribute.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        Search::apply($query, [...$this->searchIn, ...array_map(
            fn (Column $column) => $column->getName(),
            array_values(array_filter($this->columns, fn (Column $column) => $column->isSearchable())),
        )], $search);
    }

    /**
     * Parse a sort such as "-created_at" into a column and direction.
     *
     * Only sortable columns that aren't relation paths are accepted; a relation column would need a join to sort.
     *
     * @return array{0: string, 1: 'asc'|'desc'}|null
     */
    protected function parseSort(mixed $sort): ?array
    {
        if (! is_string($sort) || $sort === '') {
            return null;
        }

        $name = ltrim($sort, '-');

        foreach ($this->columns as $column) {
            if ($column->isSortable() && $column->getName() === $name && ! str_contains($name, '.')) {
                return [$name, str_starts_with($sort, '-') ? 'desc' : 'asc'];
            }
        }

        return null;
    }

    /**
     * Get the filters keyed by name.
     *
     * @return array<string, Filter>
     */
    protected function filterMap(): array
    {
        $map = [];

        foreach ($this->filters as $filter) {
            $map[$filter->getName()] = $filter;
        }

        return $map;
    }
}
