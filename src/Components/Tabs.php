<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;

/**
 * Tabs over groups of components. The open tab is kept in ?tab=.
 */
class Tabs extends Component
{
    /**
     * Create a new tabs instance.
     *
     * @param  list<Tab>  $tabs
     */
    public function __construct(protected array $tabs) {}

    /**
     * Create tabs from the given list, skipping nulls.
     *
     * @param  list<Tab|null>  $tabs
     */
    public static function make(array $tabs): static
    {
        return new static(array_values(array_filter($tabs)));
    }

    /**
     * Get the nested components across every tab.
     */
    public function children(): array
    {
        return array_merge(...array_map(fn (Tab $tab) => $tab->components(), $this->tabs));
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return [
            'type' => 'tabs',
            'tabs' => array_map(fn (Tab $tab) => $tab->toArray($request), $this->tabs),
        ];
    }
}
