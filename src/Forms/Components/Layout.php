<?php

namespace Lodestone\Forms\Components;

/**
 * Child components laid out in columns, shared by Section and Grid.
 */
abstract class Layout extends FormComponent
{
    /**
     * The components in the layout.
     *
     * @var array<int, FormComponent|null>
     */
    protected array $schema = [];

    protected int $columns = 1;

    /**
     * Set the components in the layout.
     *
     * @param  array<int, FormComponent|null>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * Set the number of columns on wide screens, from 1 to 4. Phones get one.
     */
    public function columns(int $columns): static
    {
        $this->columns = max(1, min(4, $columns));

        return $this;
    }

    /**
     * Get the nested components.
     */
    public function children(): array
    {
        return array_values(array_filter($this->schema));
    }
}
