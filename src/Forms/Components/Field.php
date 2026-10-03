<?php

namespace Lodestone\Forms\Components;

use BackedEnum;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * One input in a form. Each field carries its own validation rules, so a form's rules
 * are the sum of its fields'.
 *
 * Conditions are data, not closures: visibleWhen('provider', 'http') is sent to the browser,
 * which shows or hides the field as you type, and the server evaluates the same condition against
 * the submitted input, so a hidden field's value is never accepted.
 */
abstract class Field extends FormComponent
{
    protected ?string $label = null;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected bool $required = false;

    protected mixed $default = null;

    protected bool $readOnly = false;

    protected bool $disabled = false;

    protected ?int $span = null;

    protected bool $autofocus = false;

    /**
     * The conditions on other fields' values.
     *
     * @var array<'visible'|'hidden'|'required'|'disabled', array{field: string, values: list<string>}>
     */
    protected array $conditions = [];

    /**
     * The extra validation rules.
     *
     * @var list<mixed>
     */
    protected array $rules = [];

    /**
     * Create a new field instance.
     */
    public function __construct(protected string $name) {}

    /**
     * Create a field with the given name.
     */
    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Get the control type the renderer draws: 'text', 'select', 'toggle'…
     */
    abstract protected function type(): string;

    /**
     * Get the name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the label, defaulting to the name as a headline without an _id suffix.
     */
    public function getLabel(): string
    {
        return $this->label ?? Str::ucfirst(Str::lower(Str::headline(preg_replace('/_id$/', '', $this->name))));
    }

    /**
     * Set the label.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the small text under the field.
     */
    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Set the placeholder.
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Require a value.
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Set the starting value when creating. When editing, values come from Form::fill().
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Show the value, never an input. A submitted value is never accepted.
     */
    public function readOnly(bool $readOnly = true): static
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    /**
     * Show a greyed-out input. A submitted value is never accepted.
     */
    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Set how many of the surrounding Section or Grid's columns the field fills.
     */
    public function columnSpan(int $span): static
    {
        $this->span = $span;

        return $this;
    }

    /**
     * Focus the field when the form opens.
     */
    public function autofocus(bool $autofocus = true): static
    {
        $this->autofocus = $autofocus;

        return $this;
    }

    /**
     * Show the field only while another field has one of the given values. A hidden field's value is dropped on submit.
     */
    public function visibleWhen(string $field, mixed $values): static
    {
        return $this->when('visible', $field, $values);
    }

    /**
     * Hide the field while another field has one of the given values. A hidden field's value is dropped on submit.
     */
    public function hiddenWhen(string $field, mixed $values): static
    {
        return $this->when('hidden', $field, $values);
    }

    /**
     * Require a value only while another field has one of the given values.
     */
    public function requiredWhen(string $field, mixed $values): static
    {
        return $this->when('required', $field, $values);
    }

    /**
     * Grey the field out, and drop its value, while another field has one of the given values.
     */
    public function disabledWhen(string $field, mixed $values): static
    {
        return $this->when('disabled', $field, $values);
    }

    /**
     * Add Laravel validation rules to the ones the field implies.
     *
     * @param  string|list<mixed>  $rules
     */
    public function rules(string|array $rules): static
    {
        array_push($this->rules, ...(is_string($rules) ? explode('|', $rules) : $rules));

        return $this;
    }

    /**
     * Determine whether the field accepts input.
     */
    public function isEditable(): bool
    {
        return ! $this->readOnly && ! $this->disabled;
    }

    /**
     * Get the starting value, normalized for the control.
     */
    public function getDefault(): mixed
    {
        return $this->normalize($this->default);
    }

    /**
     * Normalize a model attribute for the control: enums to their value, dates to the input's format.
     * Override it for custom types.
     */
    public function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            default => $value,
        };
    }

    /**
     * Get the validation rules, keyed by name (and name.* for arrays). Conditions are evaluated here
     * against the submitted input, the same way the browser evaluates them, so a hidden or disabled
     * field is excluded and requiredWhen() only requires it while its condition holds.
     *
     * @param  array<string, mixed>  $input
     * @param  bool  $creating  False when the form edits a record (it has fill()); see FileUpload.
     * @return array<string, list<mixed>>
     */
    public function validationRules(array $input = [], bool $creating = true): array
    {
        $holds = fn (string $kind) => isset($this->conditions[$kind]) ? self::matches($this->conditions[$kind], $input) : null;

        $excluded = ! $this->isEditable()
            || $holds('visible') === false
            || $holds('hidden') === true
            || $holds('disabled') === true;

        if ($excluded) {
            return [$this->name => ['exclude']];
        }

        $required = $this->required || $holds('required') === true;

        return [$this->name => [$required ? 'required' : 'nullable', ...$this->typeRules(), ...$this->rules], ...$this->itemRules()];
    }

    /**
     * Determine whether another field's submitted value is one of the condition's values. A list
     * matches when any item does, so visibleWhen('channels', 'phone') works with a multi-select.
     *
     * @param  array{field: string, values: list<string>}  $condition
     * @param  array<string, mixed>  $input
     */
    public static function matches(array $condition, array $input): bool
    {
        $value = data_get($input, $condition['field']);
        $booleans = array_diff($condition['values'], ['true', 'false']) === [];

        // A boolean condition reads the input leniently: '1', 'on' and true all mean 'true'.
        $normalize = fn (mixed $v) => $booleans ? (filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') : self::stringify($v);
        $values = is_array($value) && ! $booleans ? $value : [$value];

        return array_intersect(array_map($normalize, $values), $condition['values']) !== [];
    }

    /**
     * Get the string form of a condition value or enum option: booleans as 'true'/'false', enums as their value or name.
     */
    protected static function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            default => (string) $value,
        };
    }

    /**
     * Get the field as an array.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type(),
            'label' => $this->getLabel(),
            'required' => $this->required,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'read_only' => $this->readOnly ?: null,
            'disabled' => $this->disabled ?: null,
            'span' => $this->span,
            'autofocus' => $this->autofocus ?: null,
            'when' => $this->conditions ?: null,
            ...$this->extra(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Get the validation rules the control implies, e.g. 'boolean' or 'date'.
     *
     * @return list<mixed>
     */
    protected function typeRules(): array
    {
        return [];
    }

    /**
     * Get the validation rules for array items, e.g. ['tags.*' => ['string']].
     *
     * @return array<string, list<mixed>>
     */
    protected function itemRules(): array
    {
        return [];
    }

    /**
     * Get the settings the control needs, merged into toArray().
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }

    /**
     * Add a condition on another field's value.
     */
    protected function when(string $kind, string $field, mixed $values): static
    {
        $this->conditions[$kind] = ['field' => $field, 'values' => array_map(self::stringify(...), is_array($values) ? array_values($values) : [$values])];

        return $this;
    }
}
