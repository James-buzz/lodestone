<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;

/**
 * A friendly "nothing here" message, on its own or as a table's empty state:
 *
 *     EmptyState::make('No open issues')->description('New reports land here.')->icon('inbox')->link('View all', $url)
 */
class EmptyState extends Component
{
    protected ?string $description = null;

    protected string $icon = 'inbox';

    /**
     * The button under the message.
     *
     * @var array{label: string, url: string}|null
     */
    protected ?array $link = null;

    /**
     * Create a new empty state instance.
     */
    public function __construct(protected string $heading) {}

    /**
     * Create an empty state with the given heading.
     */
    public static function make(string $heading): static
    {
        return new static($heading);
    }

    /**
     * Set the description.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the Lucide icon, by name.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Add a button under the message.
     */
    public function link(string $label, string $url): static
    {
        $this->link = ['label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return [
            'type' => 'empty_state',
            'heading' => $this->heading,
            'description' => $this->description,
            'icon' => $this->icon,
            'link' => $this->link,
        ];
    }
}
