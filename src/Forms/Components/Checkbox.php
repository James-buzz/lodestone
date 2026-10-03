<?php

namespace Lodestone\Forms\Components;

/**
 * A yes/no tick box. The value is a boolean.
 */
class Checkbox extends Field
{
    protected mixed $default = false;

    /**
     * Normalize the value to a boolean.
     */
    public function normalize(mixed $value): mixed
    {
        return (bool) $value;
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'boolean';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return ['boolean'];
    }
}
