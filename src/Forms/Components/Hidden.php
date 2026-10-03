<?php

namespace Lodestone\Forms\Components;

/**
 * A value carried through the form without showing it. Validate it like any other input.
 */
class Hidden extends Field
{
    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'hidden';
    }
}
