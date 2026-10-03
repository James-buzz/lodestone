<?php

namespace Lodestone\Forms\Components;

/**
 * Fields in columns, without a heading: Grid::make(2)->schema([...])
 */
class Grid extends Layout
{
    /**
     * Create a grid with the given number of columns.
     */
    public static function make(int $columns = 2): static
    {
        return (new static)->columns($columns);
    }

    /**
     * Get the layout as an array.
     */
    public function toArray(): array
    {
        return [
            'layout' => 'grid',
            'columns' => $this->columns,
            'children' => FormComponent::renderAll($this->schema),
        ];
    }
}
