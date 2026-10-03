<?php

namespace Lodestone\Tables\Columns;

class BadgeColumn extends Column
{
    /** @var array<string, string> */
    protected array $colors = [];

    /**
     * Set the tone for each value: ['danger' => 'failed', 'gray' => ['queued', 'skipped']].
     *
     * @param  array<string, string|list<string>>  $colors
     */
    public function colors(array $colors): static
    {
        foreach ($colors as $tone => $values) {
            foreach ((array) $values as $value) {
                $this->colors[$value] = $tone;
            }
        }

        return $this;
    }

    /**
     * Get the cell type the renderer looks up in its registry.
     */
    protected function type(): string
    {
        return 'badge';
    }

    /**
     * Get the settings the cell needs.
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return ['colors' => (object) $this->colors];
    }
}
