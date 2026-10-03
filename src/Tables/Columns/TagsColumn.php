<?php

namespace Lodestone\Tables\Columns;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * A list of badges from an array attribute, a collection, or a to-many relation path such as
 * 'tenants.*.student.name'. Colours work as on BadgeColumn; tags without one are grey.
 */
class TagsColumn extends BadgeColumn
{
    protected ?int $limit = null;

    /**
     * Show this many tags, then a "+n" count for the rest.
     */
    public function limit(int $count): static
    {
        $this->limit = $count;

        return $this;
    }

    /**
     * Get the tags for the given record.
     *
     * @return list<mixed>
     */
    public function value(Model $record): array
    {
        $value = data_get($record, $this->name);
        $tags = Arr::wrap($value instanceof Arrayable ? $value->toArray() : $value);

        return array_values(array_map(
            fn ($tag) => $tag instanceof BackedEnum ? $tag->value : $tag,
            array_filter($tags, fn ($tag) => filled($tag)),
        ));
    }

    /**
     * Get the cell type the renderer looks up in its registry.
     */
    protected function type(): string
    {
        return 'tags';
    }

    /**
     * Get the settings the cell needs.
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return array_filter([...parent::extra(), 'limit' => $this->limit]);
    }
}
