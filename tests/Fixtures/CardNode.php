<?php

namespace Lodestone\Tests\Fixtures;

use Illuminate\Http\Request;
use Lodestone\Components\Button;
use Lodestone\Components\Component;

/**
 * A custom node that owns a button. Its targets() are what let the click find the callback.
 */
final class CardNode extends Component
{
    /**
     * Create a new card instance.
     */
    public function __construct(private Button $button) {}

    /**
     * Get the event targets the card contributes to the page.
     */
    public function targets(): array
    {
        return ["card.{$this->button->getName()}" => ['slot' => 'page', 'button' => $this->button]];
    }

    /**
     * Get the card's JSON node.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'card', 'button' => $this->button->toSchema("card.{$this->button->getName()}")];
    }
}
