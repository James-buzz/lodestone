<?php

namespace Lodestone\Forms\Components;

/**
 * A date and time, sent as Y-m-d\TH:i in the app's timezone.
 */
class DateTimePicker extends DatePicker
{
    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'datetime';
    }

    /**
     * Get the format the input sends and expects.
     */
    protected function inputFormat(): string
    {
        return 'Y-m-d\TH:i';
    }
}
