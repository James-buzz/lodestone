<?php

namespace Lodestone\Forms\Components;

/**
 * Anything in a form's schema: a field, or a layout that groups fields.
 */
abstract class FormComponent
{
    /**
     * Get the component as an array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Get the nested components.
     *
     * @return list<FormComponent>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * Get every field in a schema, depth first.
     *
     * @param  array<int, FormComponent|null>  $components
     * @return list<Field>
     */
    public static function fieldsIn(array $components): array
    {
        $fields = [];

        foreach (array_filter($components) as $component) {
            if ($component instanceof Field) {
                $fields[] = $component;
            } else {
                array_push($fields, ...self::fieldsIn($component->children()));
            }
        }

        return $fields;
    }

    /**
     * Render the components, skipping nulls.
     *
     * @param  array<int, FormComponent|null>  $components
     * @return list<array<string, mixed>>
     */
    public static function renderAll(array $components): array
    {
        return array_values(array_map(fn (FormComponent $component) => $component->toArray(), array_filter($components)));
    }
}
