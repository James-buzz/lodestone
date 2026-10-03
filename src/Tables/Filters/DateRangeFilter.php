<?php

namespace Lodestone\Tables\Filters;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A from and to date on a date or datetime column. Either end can be left open.
 */
class DateRangeFilter extends Filter
{
    /**
     * Determine whether a submitted value should filter at all.
     */
    public function isActive(mixed $value): bool
    {
        return self::day($value, 'from') !== null || self::day($value, 'to') !== null;
    }

    /**
     * Apply the submitted range to the query.
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($from = self::day($value, 'from')) {
            $query->whereDate($query->qualifyColumn($this->name), '>=', $from);
        }

        if ($to = self::day($value, 'to')) {
            $query->whereDate($query->qualifyColumn($this->name), '<=', $to);
        }
    }

    /**
     * Get one end of the range as a real Y-m-d date, ignoring anything else the query string carries.
     */
    protected static function day(mixed $value, string $end): ?string
    {
        $day = is_array($value) ? $value[$end] ?? null : null;

        if (! is_string($day)) {
            return null;
        }

        // createFromFormat() rolls "2026-13-45" over to a later date, so round-tripping catches it.
        $date = DateTime::createFromFormat('Y-m-d', $day);

        return $date && $date->format('Y-m-d') === $day ? $day : null;
    }

    /**
     * Get the filter's JSON representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Builder $query): array
    {
        return [
            'type' => 'date_range',
            'name' => $this->name,
            'label' => $this->label ?? Str::ucfirst(Str::lower(Str::headline(preg_replace('/_(at|on)$/', '', $this->name)))),
        ];
    }
}
