<?php

namespace Lodestone\Forms\Components;

use DateTimeInterface;

/**
 * A time of day, sent as H:i.
 */
class TimePicker extends Field
{
    /**
     * Normalize a time, or a TIME column's '09:30:00', to H:i.
     */
    public function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('H:i'),
            is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1 => substr($value, 0, 5),
            default => parent::normalize($value),
        };
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'time';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return ['date_format:H:i'];
    }
}
