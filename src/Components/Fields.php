<?php

namespace Lodestone\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lodestone\Tables\Columns\Column;
use LogicException;

/**
 * A label/value list for one record. It uses table columns, so formats and custom cells carry over.
 */
class Fields extends Component
{
    protected ?Model $record = null;

    /**
     * The columns shown, one per row.
     *
     * @var list<Column>
     */
    protected array $fields = [];

    /**
     * Create a new field list instance.
     */
    public function __construct(protected ?string $heading) {}

    /**
     * Create a field list with an optional heading.
     */
    public static function make(?string $heading = null): static
    {
        return new static($heading);
    }

    /**
     * Set the record whose values are shown.
     */
    public function record(Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    /**
     * Set the columns to show.
     *
     * @param  list<Column>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        $record = $this->record ?? throw new LogicException('Fields needs a record: Fields::make()->record($model).');

        return [
            'type' => 'fields',
            'heading' => $this->heading,
            'items' => array_map(fn (Column $column) => [
                'column' => $column->toArray(),
                'value' => $column->value($record),
                ...$column->cellData($record),
            ], $this->fields),
        ];
    }
}
