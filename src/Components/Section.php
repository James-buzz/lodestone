<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;
use Lodestone\Facades\Lodestone;
use Lodestone\Forms\Form;

/**
 * A heading over a group of components, optionally collapsible:
 *
 *     Section::make('Bank details')->description('From the tenancy agreement')->collapsed()->schema([...])
 *
 * Or a form, shown as values with an Edit button that edits in place:
 *
 *     Section::make('Schedule')->form(ScheduleFeed::make()->fill($this->record)->submit(fn (Feed $feed, array $data) => …))
 *
 * A form without fill() shows as an inline create form instead.
 */
class Section extends Component
{
    protected ?string $description = null;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    /**
     * The components in the section.
     *
     * @var array<int, Component|null>
     */
    protected array $schema = [];

    protected ?Form $form = null;

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
     * Let the heading toggle the contents.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * Start collapsed. Implies collapsible.
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsible = $this->collapsible || $collapsed;
        $this->collapsed = $collapsed;

        return $this;
    }

    /**
     * Set the components in the section.
     *
     * @param  array<int, Component|null>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * Show a form in the section. Its key on the page is 'form.{form key}'.
     */
    public function form(Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    /**
     * Get the nested components.
     */
    public function children(): array
    {
        return array_values(array_filter($this->schema));
    }

    /**
     * Get the section's form as an event target.
     */
    public function targets(): array
    {
        return $this->form ? [$this->formKey() => ['slot' => 'section', 'form' => $this->form]] : [];
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return [
            'type' => 'section',
            'heading' => $this->heading,
            'description' => $this->description,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'children' => Component::renderAll($this->schema, $request),
            // The record page's record is what the form's fill() closure receives.
            'form' => $this->form?->toSchema(Lodestone::eventUrl($this->formKey()), Lodestone::record()),
        ];
    }

    /**
     * Get the form's key on the page.
     */
    protected function formKey(): string
    {
        return "form.{$this->form->getKey()}";
    }
}
