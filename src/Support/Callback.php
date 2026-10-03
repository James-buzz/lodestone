<?php

namespace Lodestone\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Calls a builder's closure with its arguments injected by type, the way controller methods get
 * theirs: a model type gets the row or record, a Collection gets the selected rows, `array $data`
 * gets the validated input, and anything else comes from the container. A union such as
 * `Feed|Collection $feeds` takes whichever this event has, so one callback can serve a row and a bulk button.
 *
 * @internal
 */
class Callback
{
    /**
     * Call the closure with its arguments resolved for this event.
     *
     * @param  Collection<int, Model>|null  $records
     * @param  array<string, mixed>  $named  Values matched by parameter name, such as data.
     */
    public static function call(Closure $callback, ?Model $model = null, ?Collection $records = null, array $named = []): mixed
    {
        $arguments = [];

        foreach ((new ReflectionFunction($callback))->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $classes = self::classes($type);
            $models = array_filter($classes, fn (string $class) => is_a($class, Model::class, true));
            $wantsModel = $model !== null && array_filter($models, fn (string $class) => $model instanceof $class) !== [];
            $wantsRecords = array_filter($classes, fn (string $class) => is_a($class, Collection::class, true)) !== [];

            if (array_key_exists($name, $named)) {
                $arguments[$name] = $named[$name];
            } elseif ($wantsModel) {
                $arguments[$name] = $model;
            } elseif ($wantsRecords && $records !== null) {
                $arguments[$name] = $records;
            } elseif ($models !== []) {
                $arguments[$name] = self::fallback($parameter, 'This callback asks for '.implode('|', $models)." \${$name}, but ".($model ? 'it runs on a '.$model::class : 'there is no record here').'.');
            } elseif ($wantsRecords) {
                // Left to the container, an empty Collection would be built and the callback would run on nothing.
                $arguments[$name] = self::fallback($parameter, "This callback asks for Collection \${$name}, but this is a row or header event, not a bulk one.");
            } elseif ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
                $arguments[$name] = self::fallback($parameter, "This callback asks for array \${$name}; the validated input is only available on a form submit, as array \$data.");
            } elseif ($type === null && ! $parameter->isOptional()) {
                // An untyped parameter takes the row, record or selection, whichever this event has.
                $arguments[$name] = $model ?? $records;
            }
        }

        return app()->call($callback, $arguments);
    }

    /**
     * Evaluate a condition: a bool, or a closure called with the record.
     */
    public static function check(bool|Closure $condition, ?Model $model = null): bool
    {
        return $condition instanceof Closure ? (bool) self::call($condition, $model) : $condition;
    }

    /**
     * Get the class names in a parameter's type, leaving builtins out.
     *
     * @return list<class-string>
     */
    private static function classes(?ReflectionType $type): array
    {
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $named = array_filter($types, fn ($t) => $t instanceof ReflectionNamedType && ! $t->isBuiltin());

        return array_values(array_map(fn (ReflectionNamedType $t) => $t->getName(), $named));
    }

    /**
     * Get what to pass when this event can't supply the parameter: its default, null if allowed, else fail.
     */
    private static function fallback(ReflectionParameter $parameter, string $otherwise): mixed
    {
        return match (true) {
            $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
            $parameter->allowsNull() => null,
            default => throw new LogicException($otherwise),
        };
    }
}
