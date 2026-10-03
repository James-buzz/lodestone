<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;

/**
 * Components side by side on wide screens, stacked on narrow ones.
 */
class Grid extends Component
{
    /**
     * The components in the grid.
     *
     * @var array<int, Component|null>
     */
    protected array $schema = [];

    /**
     * Create a new grid instance.
     */
    public function __construct(protected int $columns) {}

    /**
     * Create a grid with the given number of columns.
     */
    public static function make(int $columns = 2): static
    {
        return new static($columns);
    }

    /**
     * Set the components in the grid.
     *
     * @param  array<int, Component|null>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * Get the nested components.
     */
    public function children(): array
    {
        return array_values(array_filter($this->schema));
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'grid', 'columns' => $this->columns, 'children' => Component::renderAll($this->schema, $request)];
    }
}
