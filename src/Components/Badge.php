<?php

namespace Lodestone\Components;

use BackedEnum;
use Lodestone\Enums\Tone;
use UnitEnum;

/**
 * A status badge in a record page's header.
 */
class Badge
{
    /**
     * The tone for each value.
     *
     * @var array<string, string>
     */
    protected array $colors = [];

    /**
     * Create a new badge instance.
     */
    public function __construct(protected mixed $value) {}

    /**
     * Create a badge for the given value.
     */
    public static function make(mixed $value): static
    {
        // A pure enum has no value to encode, so its name stands in.
        return new static(match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            default => $value,
        });
    }

    /**
     * Set the tone for each value, the same way as BadgeColumn::colors().
     *
     * @param  array<string, string|list<string>>  $colors
     */
    public function colors(array $colors): static
    {
        foreach ($colors as $tone => $values) {
            foreach ((array) $values as $value) {
                $this->colors[$value] = Tone::from($tone)->value;
            }
        }

        return $this;
    }

    /**
     * Get the badge as an array.
     *
     * @return array{value: mixed, colors: object}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'colors' => (object) $this->colors];
    }
}
