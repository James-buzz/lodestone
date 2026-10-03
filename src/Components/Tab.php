<?php

namespace Lodestone\Components;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One tab in a Tabs node.
 */
class Tab
{
    /**
     * The components in the tab.
     *
     * @var array<int, Component|null>
     */
    protected array $schema = [];

    protected int|Closure|null $badge = null;

    /**
     * Create a new tab instance.
     */
    public function __construct(protected string $label, protected string $key) {}

    /**
     * Create a tab with the given label. The key, kept in ?tab=, defaults to the label as a slug.
     */
    public static function make(string $label, ?string $key = null): static
    {
        return new static($label, $key ?? Str::slug($label));
    }

    /**
     * Set the components in the tab.
     *
     * @param  array<int, Component|null>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * Show a count beside the label. Pass a closure when it costs a query, so it only runs when drawn.
     */
    public function badge(int|Closure|null $count): static
    {
        $this->badge = $count;

        return $this;
    }

    /**
     * Get the tab's components.
     *
     * @return list<Component>
     */
    public function components(): array
    {
        return array_values(array_filter($this->schema));
    }

    /**
     * Get the tab as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'badge' => $this->badge instanceof Closure ? ($this->badge)() : $this->badge,
            'children' => Component::renderAll($this->schema, $request),
        ];
    }
}
