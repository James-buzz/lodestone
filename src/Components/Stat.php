<?php

namespace Lodestone\Components;

use Closure;
use Lodestone\Enums\Tone;

/**
 * One number in a Stats strip. Pass a closure for a value that costs a query, so it only runs
 * when the page is drawn, not when a click rebuilds the page to find its button:
 *
 *     Stat::make('Open', fn () => Ticket::open()->count())
 */
class Stat
{
    protected ?Tone $tone = null;

    protected ?string $format = null;

    protected ?string $description = null;

    /**
     * The sparkline points, or a closure returning them.
     *
     * @var list<float|int|null|array<string, mixed>>|Closure|null
     */
    protected array|Closure|null $chart = null;

    /**
     * Create a new stat instance.
     */
    public function __construct(protected string $label, protected mixed $value) {}

    /**
     * Create a stat with the given label and value.
     */
    public static function make(string $label, mixed $value): static
    {
        return new static($label, $value);
    }

    /**
     * Use the danger tone.
     */
    public function danger(): static
    {
        return $this->tone('danger');
    }

    /**
     * Use the warning tone.
     */
    public function warning(): static
    {
        return $this->tone('warning');
    }

    /**
     * Use the success tone.
     */
    public function success(): static
    {
        return $this->tone('success');
    }

    /**
     * Use the info tone.
     */
    public function info(): static
    {
        return $this->tone('info');
    }

    /**
     * Set the tone.
     */
    public function tone(string|Tone $tone): static
    {
        $this->tone = $tone instanceof Tone ? $tone : Tone::from($tone);

        return $this;
    }

    /**
     * Set the description shown under the value.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Draw a sparkline under the value, from plain numbers or chart rows (their `value` key). Lazy, like the value.
     *
     * @param  list<float|int|null|array<string, mixed>>|Closure  $points
     */
    public function chart(array|Closure $points): static
    {
        $this->chart = $points;

        return $this;
    }

    /**
     * Format the value as a duration: seconds rendered as "1m 12s".
     */
    public function duration(): static
    {
        return $this->format('duration');
    }

    /**
     * Format the value as a percentage of a 0–1 ratio.
     */
    public function percent(): static
    {
        return $this->format('percent');
    }

    /**
     * Format the value as money in the given currency.
     */
    public function money(string $currency = 'GBP'): static
    {
        return $this->format("money:{$currency}");
    }

    /**
     * Set the value format.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Get the stat as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $value = $this->value instanceof Closure ? ($this->value)() : $this->value;
        $value = is_numeric($value) ? $value + 0 : $value;
        $chart = $this->chart instanceof Closure ? ($this->chart)() : $this->chart;

        return array_filter([
            'label' => $this->label,
            'value' => $value,
            'format' => $this->format ?? (is_numeric($value) ? 'number' : null),
            'tone' => $this->tone?->value,
            'description' => $this->description,
            'chart' => $chart === null ? null : array_map(fn ($point) => is_array($point) ? ($point['value'] ?? null) : $point, $chart),
        ], fn ($v) => $v !== null);
    }
}
