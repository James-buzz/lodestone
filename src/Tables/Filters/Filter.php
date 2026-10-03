<?php

namespace Lodestone\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * A table filter. Its value arrives from the query string as ?{table}[filters][{name}]=….
 */
abstract class Filter
{
    protected ?string $label = null;

    /**
     * Create a new filter instance.
     */
    public function __construct(protected string $name) {}

    /**
     * Create a new filter for the given attribute.
     */
    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the filter's placeholder or button text, such as "All statuses".
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the attribute the filter applies to.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Determine whether a submitted value should filter at all.
     */
    public function isActive(mixed $value): bool
    {
        return is_array($value) ? self::present($value) !== [] : $value !== null && $value !== '';
    }

    /**
     * Get the scalar, non-empty entries of a submitted array.
     *
     * A multiple select with nothing chosen arrives as ?filters[status][]= with an empty entry.
     *
     * @param  array<array-key, mixed>  $values
     * @return list<mixed>
     */
    protected static function present(array $values): array
    {
        return array_values(array_filter($values, fn ($value) => is_scalar($value) && $value !== ''));
    }

    /**
     * Apply the submitted value to the query.
     */
    abstract public function apply(Builder $query, mixed $value): void;

    /**
     * Get the filter's JSON representation.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(Builder $query): array;
}
