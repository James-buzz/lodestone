<?php

namespace Lodestone\Forms\Components;

use DateTimeInterface;

/**
 * A date, sent as Y-m-d.
 */
class DatePicker extends Field
{
    protected ?string $minDate = null;

    protected ?string $maxDate = null;

    /**
     * Set the earliest date allowed.
     */
    public function minDate(DateTimeInterface|string $date): static
    {
        $this->minDate = $this->format($date);

        return $this;
    }

    /**
     * Set the latest date allowed.
     */
    public function maxDate(DateTimeInterface|string $date): static
    {
        $this->maxDate = $this->format($date);

        return $this;
    }

    /**
     * Normalize a date, or an uncast DATETIME string, to the input's format.
     */
    public function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $this->format($value),
            is_string($value) && ($time = strtotime($value)) !== false => date($this->inputFormat(), $time),
            default => parent::normalize($value),
        };
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'date';
    }

    /**
     * Get the format the input sends and expects.
     */
    protected function inputFormat(): string
    {
        return 'Y-m-d';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return array_values(array_filter([
            'date',
            $this->minDate ? "after_or_equal:{$this->minDate}" : null,
            $this->maxDate ? "before_or_equal:{$this->maxDate}" : null,
        ]));
    }

    /**
     * Get the settings the control needs.
     */
    protected function extra(): array
    {
        return ['min' => $this->minDate, 'max' => $this->maxDate];
    }

    /**
     * Format a date for the input, leaving a string as is.
     */
    protected function format(DateTimeInterface|string $date): string
    {
        return is_string($date) ? $date : $date->format($this->inputFormat());
    }
}
