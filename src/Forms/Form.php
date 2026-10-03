<?php

namespace Lodestone\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Lodestone\Enums\Overlay;
use Lodestone\Forms\Components\Field;
use Lodestone\Forms\Components\FormComponent;
use Lodestone\Support\Callback;
use LogicException;

/**
 * Fields, starting values and a submit callback. Extend it to reuse a field list:
 *
 *     class ScheduleFeed extends Form          // key 'schedule-feed'
 *     {
 *         public function schema(): array { return [Select::make('frequency')->options(Frequency::class)->required()]; }
 *     }
 *
 *     ScheduleFeed::make()->fill($feed)->submit(fn (Feed $feed, array $data) => $feed->update($data))
 *
 * or build one inline: Form::make('note', [Textarea::make('body')])->submit(fn (array $data) => …).
 *
 * Before submit runs, the input is validated against the fields (required, types, options, conditions),
 * and values from hidden, disabled and read-only fields are dropped. The callback decides the rest.
 */
class Form
{
    protected string $key;

    /**
     * The fields and layouts of an inline form.
     *
     * @var array<int, FormComponent|null>
     */
    protected array $components = [];

    protected Model|array|Closure|null $fill = null;

    protected bool|Closure $readOnly = false;

    protected Overlay $placement = Overlay::Modal;

    protected ?string $description = null;

    protected ?string $submitLabel = null;

    protected ?Closure $submit = null;

    /**
     * Create a new form instance. A form class is keyed by its kebab-case name; an inline form needs a key.
     *
     * @param  array<int, FormComponent|null>  $components
     */
    public function __construct(?string $key = null, array $components = [])
    {
        $this->key = $key ?? (static::class === self::class
            ? throw new LogicException("Give inline forms a key: Form::make('note', [...]).")
            : Str::kebab(class_basename(static::class)));
        $this->components = $components;
    }

    /**
     * Create a new form instance.
     *
     * @param  array<int, FormComponent|null>  $components
     */
    public static function make(?string $key = null, array $components = []): static
    {
        return new static($key, $components);
    }

    /**
     * Get the fields, with the Sections and Grids around them. A form class overrides this.
     *
     * @return array<int, FormComponent|null>
     */
    public function schema(): array
    {
        return $this->components;
    }

    /**
     * Set the starting values: a model (read field by field, so 'owner.name' works), an array, or a
     * closure returning either, given the row or record: fill(fn (Feed $feed) => $feed). The model is
     * also what the submit callback receives. Without fill() the form creates.
     */
    public function fill(Model|array|Closure $values): static
    {
        $this->fill = $values;

        return $this;
    }

    /**
     * Show the values with no way to edit them. The server refuses a submit too.
     */
    public function readOnly(bool|Closure $readOnly = true): static
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    /**
     * Set how the form opens from a button: a modal (centred) or a slide-over (from the right).
     */
    public function placement(string|Overlay $placement): static
    {
        $this->placement = $placement instanceof Overlay ? $placement : Overlay::from($placement);

        return $this;
    }

    /**
     * Set the line under the title when the form opens from a button.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the submit button's label. Defaults to "Create", or "Save" when the form has fill().
     */
    public function submitLabel(string $label): static
    {
        $this->submitLabel = $label;

        return $this;
    }

    /**
     * Set the callback run on submit, with the validated input: fn (array $data, Feed $feed, Ui $ui) => ….
     * The model is the one given to fill(). A form without submit() is read-only.
     */
    public function submit(Closure $callback): static
    {
        $this->submit = $callback;

        return $this;
    }

    /**
     * Get the key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get every field in the schema.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return FormComponent::fieldsIn($this->schema());
    }

    /**
     * Get the model the form edits for the given record: the one fill() gives, or the record itself when fill() gives values.
     */
    public function model(?Model $record): ?Model
    {
        $fill = $this->resolveFill($record);

        return $fill instanceof Model ? $fill : $record;
    }

    /**
     * Get the starting values from fill(), or each field's default when creating or when $defaults is set.
     *
     * @return array<string, mixed>
     */
    public function values(?Model $record, bool $defaults = false): array
    {
        $fill = $defaults ? null : $this->resolveFill($record);
        $values = [];

        foreach ($this->fields() as $field) {
            $name = $field->getName();
            $values[$name] = match (true) {
                $fill instanceof Model => $field->normalize(data_get($fill, $name)),
                is_array($fill) && array_key_exists($name, $fill) => $field->normalize($fill[$name]),
                default => $field->getDefault(),
            };
        }

        return $values;
    }

    /**
     * Determine whether the form is read-only for the given record.
     */
    public function isReadOnlyFor(?Model $record): bool
    {
        return $this->submit === null || Callback::check($this->readOnly, $this->model($record));
    }

    /**
     * Determine whether the form creates rather than edits. Without fill() it starts from the defaults and clears after saving.
     */
    public function creates(): bool
    {
        return $this->fill === null;
    }

    /**
     * Validate the input against the fields, labelled the way people see them ("The owner field…").
     * Hidden, disabled and read-only fields are excluded, the same way the browser applies them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $rules = [];
        $labels = [];

        foreach ($this->fields() as $field) {
            $rules = [...$rules, ...$field->validationRules($input, $this->creates())];
            $labels[$field->getName()] = Str::lower($field->getLabel());
        }

        return Validator::make($input, $rules, [], $labels)->validate();
    }

    /**
     * Call the submit callback with the validated input and the fill() model.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Model>|null  $records  The selected rows, for a form on a bulk button.
     */
    public function handle(array $data, ?Model $record, ?Collection $records = null): mixed
    {
        $submit = $this->submit ?? throw new LogicException("Form [{$this->key}] has no submit().");

        return Callback::call($submit, $this->model($record), $records, ['data' => $data]);
    }

    /**
     * Build the form for the renderer. With 'defer', a row's values load when the form opens
     * (GET $url?ids[]=); with 'defaults', it starts from the fields' defaults.
     *
     * @param  'now'|'defer'|'defaults'  $values
     * @return array<string, mixed>
     */
    public function toSchema(string $url, ?Model $record = null, string $values = 'now'): array
    {
        return array_filter([
            'key' => $this->key,
            'schema' => FormComponent::renderAll($this->schema()),
            'placement' => $this->placement->value,
            'description' => $this->description,
            'submit' => $this->submitLabel ?? ($this->creates() ? 'Create' : 'Save'),
            'creates' => $this->creates(),
            'url' => $url,
            // An object, so an empty form encodes as {} rather than [].
            'values' => match ($values) {
                'now' => (object) $this->values($record),
                'defaults' => (object) $this->values(null, defaults: true),
                'defer' => null,
            },
            'read_only' => match ($values) {
                'now' => $this->isReadOnlyFor($record),
                'defaults' => $this->submit === null,
                'defer' => null,
            },
        ], fn ($value) => $value !== null);
    }

    /**
     * Resolve fill() for the given record.
     */
    protected function resolveFill(?Model $record): Model|array|null
    {
        if ($this->fill instanceof Closure) {
            // A per-row fill(fn (Feed $feed) => …) has nothing to read on a bulk submit, so the form creates there.
            return $record === null ? null : Callback::call($this->fill, $record);
        }

        return $this->fill;
    }
}
