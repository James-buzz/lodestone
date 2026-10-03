<?php

namespace Lodestone\Forms\Components;

/**
 * A yes/no switch. The value is a boolean.
 */
class Toggle extends Checkbox
{
    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'toggle';
    }
}
