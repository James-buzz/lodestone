<?php

namespace Lodestone\Forms\Components;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Pick from a list. Options are value => label, a list of values, or an enum class:
 *
 *     Select::make('frequency')->options(Frequency::class)
 *     Select::make('owner_id')->options(User::support()->pluck('name', 'id'))
 *
 * A submitted value must be one of the options.
 */
class Select extends Field
{
    /**
     * The options, as value => label.
     *
     * @var array<array-key, string>
     */
    protected array $options = [];

    protected bool $multiple = false;

    /**
     * Set the options: value => label, a list of values, or an enum class.
     *
     * @param  array<array-key, string>|Arrayable<array-key, string>|class-string<UnitEnum>  $options
     */
    public function options(array|Arrayable|string $options): static
    {
        $this->options = match (true) {
            is_string($options) => self::enumOptions($options),
            $options instanceof Arrayable => $options->toArray(),
            array_is_list($options) => array_combine($options, $options),
            default => $options,
        };

        return $this;
    }

    /**
     * Allow several choices. The value is a list.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Normalize the value to a string, or to a list of strings when multiple.
     */
    public function normalize(mixed $value): mixed
    {
        if ($this->multiple) {
            $values = $value instanceof Arrayable ? $value->toArray() : (array) ($value ?? []);

            return array_values(array_map(fn ($item) => (string) parent::normalize($item), $values));
        }

        $value = parent::normalize($value);

        return $value === null ? null : (string) $value;
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return 'select';
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        return $this->multiple ? ['array'] : [Rule::in(array_map('strval', array_keys($this->options)))];
    }

    /**
     * Get the validation rules for array items.
     */
    protected function itemRules(): array
    {
        return $this->multiple ? ["{$this->name}.*" => [Rule::in(array_map('strval', array_keys($this->options)))]] : [];
    }

    /**
     * Get the settings the control needs.
     */
    protected function extra(): array
    {
        return [
            'options' => array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => (string) $label], array_keys($this->options), $this->options),
            'multiple' => $this->multiple ?: null,
        ];
    }

    /**
     * Get an enum's cases as options. Labels come from a label() method on the enum when it has one.
     *
     * @param  class-string<UnitEnum>  $enum
     * @return array<string, string>
     */
    protected static function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[self::stringify($case)] = method_exists($case, 'label') ? $case->label() : Str::headline($case->name);
        }

        return $options;
    }
}
