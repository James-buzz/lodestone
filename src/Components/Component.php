<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;
use Lodestone\Forms\Form;
use Lodestone\Tables\Table;

/**
 * A node in a page's schema.
 */
abstract class Component
{
    protected bool $visible = true;

    /**
     * Build the node for the renderer. It must include a `type`.
     *
     * @return array<string, mixed>
     */
    abstract public function toSchema(Request $request): array;

    /**
     * Set whether the component is drawn.
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Determine whether the component is drawn.
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Get the nested components, for layouts like Tabs and Grid.
     *
     * @return list<Component>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * Get the buttons and forms in this node that take events, by their key on the page.
     * A custom node overrides this so its buttons and forms get event URLs; see Table and Section.
     *
     * @return array<string, array{slot: string, button?: Button, form?: Form, table?: Table}>
     */
    public function targets(): array
    {
        return [];
    }

    /**
     * Render the components, skipping nulls and hidden ones, so a page can write
     * `$run->failed() ? Alert::danger(...) : null` or `->visible($run->failed())`.
     *
     * @param  array<int, Component|null>  $components
     * @return list<array<string, mixed>>
     */
    public static function renderAll(array $components, Request $request): array
    {
        $visible = array_filter($components, fn ($component) => $component instanceof Component && $component->isVisible());

        return array_values(array_map(fn (Component $component) => $component->toSchema($request), $visible));
    }
}
