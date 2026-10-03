<?php

namespace Lodestone\Forms\Components;

/**
 * A titled group of fields inside a form:
 *
 *     Section::make('Owner')->description('Who gets failure emails.')->columns(2)->schema([...])
 */
class Section extends Layout
{
    protected ?string $description = null;

    /**
     * Create a new section instance.
     */
    public function __construct(protected string $heading) {}

    /**
     * Create a section with the given heading.
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
     * Get the layout as an array.
     */
    public function toArray(): array
    {
        return [
            'layout' => 'section',
            'heading' => $this->heading,
            'description' => $this->description,
            'columns' => $this->columns,
            'children' => FormComponent::renderAll($this->schema),
        ];
    }
}
