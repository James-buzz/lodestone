<?php

namespace Lodestone\Tables\Filters;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SelectFilter extends Filter
{
    /** @var array<array-key, string>|null */
    protected ?array $options = null;

    /** @var array{0: string, 1: string}|null */
    protected ?array $relationship = null;

    protected bool $multiple = false;

    /**
     * Set the options as value => label pairs, or a list of values.
     *
     * @param  array<array-key, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = array_is_list($options) ? array_combine($options, array_map(Str::headline(...), $options)) : $options;

        return $this;
    }

    /**
     * Take the options from a relation, labelled by the given attribute.
     */
    public function relationship(string $relation, string $titleAttribute): static
    {
        $this->relationship = [$relation, $titleAttribute];

        return $this;
    }

    /**
     * Allow several values to be chosen; rows matching any of them are shown.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Determine whether a submitted value should filter at all.
     *
     * A single select takes one value, so an array sent against it is ignored rather than passed to where().
     */
    public function isActive(mixed $value): bool
    {
        return $this->multiple ? parent::isActive($value) : is_scalar($value) && $value !== '';
    }

    /**
     * Apply the submitted value to the query.
     */
    public function apply(Builder $query, mixed $value): void
    {
        $column = $query->qualifyColumn($this->name);

        if ($this->multiple) {
            $query->whereIn($column, self::present((array) $value));
        } else {
            $query->where($column, $value);
        }
    }

    /**
     * Get the filter's JSON representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Builder $query): array
    {
        $noun = Str::of($this->relationship[0] ?? $this->name)->replaceLast('_id', '')->headline()->lower()->plural();

        return array_filter([
            'type' => 'select',
            'name' => $this->name,
            'label' => $this->label ?? ($this->multiple ? Str::ucfirst($noun->singular()) : "All {$noun}"),
            'multiple' => $this->multiple ?: null,
            'options' => collect($this->resolveOptions($query))
                ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])
                ->values()
                ->all(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Get the options: as given, from the relation, or the distinct values of the table's own query.
     *
     * @return array<array-key, string>
     */
    protected function resolveOptions(Builder $query): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        if ($this->relationship !== null) {
            [$relation, $title] = $this->relationship;
            $related = $query->getModel()->{$relation}()->getRelated();

            return $related->newQuery()->orderBy($title)->pluck($title, $related->getKeyName())->all();
        }

        // Reading distinct values from the table's own query means the options never include rows the
        // table can't show. It is one DISTINCT scan per render, so pass options() for very large tables.
        $column = $query->qualifyColumn($this->name);

        return $query->clone()->reorder()->select($column)->distinct()->orderBy($column)->pluck($this->name)
            ->mapWithKeys(function ($value) {
                $value = $value instanceof BackedEnum ? $value->value : $value;

                return [$value => Str::headline((string) $value)];
            })
            ->all();
    }
}
