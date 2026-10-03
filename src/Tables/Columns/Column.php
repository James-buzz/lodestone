<?php

namespace Lodestone\Tables\Columns;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class Column
{
    protected string $label;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected ?Closure $url = null;

    protected bool $toggleable = false;

    protected bool $hiddenByDefault = false;

    /**
     * Create a new column instance.
     *
     * The label is derived from the name: 'owner.name' becomes "Owner" and 'client_id' becomes "Client".
     */
    public function __construct(protected string $name)
    {
        $this->label = Str::ucfirst(Str::lower(Str::headline(preg_replace('/_id$/', '', Str::afterLast(str_replace('.name', '', $name), '.')))));
    }

    /**
     * Create a new column for the given attribute.
     */
    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the column's label.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Allow the table to be sorted by this column.
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Include this column in the table's search.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Make the value a link to the URL the closure returns for the record.
     */
    public function url(Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Let people hide or show this column from the table's "Columns" menu.
     */
    public function toggleable(bool $hiddenByDefault = false): static
    {
        $this->toggleable = true;
        $this->hiddenByDefault = $hiddenByDefault;

        return $this;
    }

    /**
     * Get the column's label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the URL the value links to for the given record, if any.
     */
    public function urlFor(Model $record): ?string
    {
        return $this->url ? ($this->url)($record) : null;
    }

    /**
     * Get the extra per-record values the cell needs, sent as row['{name}:{key}'].
     *
     * @return array<string, mixed>
     */
    public function cellData(Model $record): array
    {
        return array_filter(['url' => $this->urlFor($record)], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Read an attribute path or a closure from the given record.
     */
    protected function resolve(string|Closure|null $source, Model $record): mixed
    {
        return $source instanceof Closure ? $source($record) : ($source === null ? null : data_get($record, $source));
    }

    /**
     * Get the attribute the column reads.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Determine whether the table can be sorted by this column.
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Determine whether this column is included in the table's search.
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Get the raw, JSON-safe value for the given record.
     *
     * Formatting happens in the renderer so relative times stay live.
     */
    public function value(Model $record): mixed
    {
        $value = data_get($record, $this->name);

        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }

    /**
     * Get the cell type the renderer looks up in its registry.
     */
    abstract protected function type(): string;

    /**
     * Get the settings the cell needs, merged into toArray().
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }

    /**
     * Get the column's JSON representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'name' => $this->name,
            'label' => $this->label,
            ...array_filter([
                'sortable' => $this->sortable,
                'searchable' => $this->searchable,
                'toggleable' => $this->toggleable,
                'hidden' => $this->hiddenByDefault,
            ]),
            ...$this->extra(),
        ];
    }
}
