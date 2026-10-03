<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;

/**
 * A JSON block with a copy button, e.g. a job payload.
 */
class Code extends Component
{
    /**
     * Create a new code block instance.
     */
    public function __construct(protected string $heading, protected mixed $value) {}

    /**
     * Create a code block, hidden when the value is null.
     */
    public static function make(string $heading, mixed $value): static
    {
        return (new static($heading, $value))->visible($value !== null);
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'code', 'heading' => $this->heading, 'value' => $this->value];
    }
}
