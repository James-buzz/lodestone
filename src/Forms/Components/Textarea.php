<?php

namespace Lodestone\Forms\Components;

/**
 * Several lines of text. markdown() swaps in a monospace editor labelled "Markdown".
 */
class Textarea extends Field
{
    protected int $rows = 3;

    protected ?int $maxLength = null;

    protected bool $markdown = false;

    /**
     * Set the number of visible rows.
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Set the maximum length.
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Edit Markdown, shown read-only through the Markdown node.
     */
    public function markdown(bool $markdown = true): static
    {
        $this->markdown = $markdown;
        $this->rows = max($this->rows, 6);

        return $this;
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return $this->markdown ? 'markdown' : 'textarea';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return array_values(array_filter(['string', $this->maxLength !== null ? "max:{$this->maxLength}" : null]));
    }

    /**
     * Get the settings the control needs.
     */
    protected function extra(): array
    {
        return ['rows' => $this->rows, 'max' => $this->maxLength];
    }
}
