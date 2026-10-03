<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;

/**
 * A strip of stats.
 */
class Stats extends Component
{
    /**
     * Create a new stats strip instance.
     *
     * @param  list<Stat>  $stats
     */
    public function __construct(protected array $stats) {}

    /**
     * Create a strip of the given stats.
     *
     * @param  list<Stat>  $stats
     */
    public static function make(array $stats): static
    {
        return new static($stats);
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'stats', 'items' => array_map(fn (Stat $stat) => $stat->toArray(), $this->stats)];
    }
}
