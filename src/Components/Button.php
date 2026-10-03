<?php

namespace Lodestone\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lodestone\Enums\Overlay;
use Lodestone\Enums\Size;
use Lodestone\Enums\Tone;
use Lodestone\Facades\Lodestone;
use Lodestone\Forms\Form;
use Lodestone\Page;
use Lodestone\RecordPage;
use Lodestone\Support\Callback;
use LogicException;

/**
 * Something to click. Configure it, add conditions, then say what a click does:
 *
 *     Button::make('retry')
 *         ->icon('rotate-ccw')
 *         ->confirm('Retry this run?')
 *         ->visible(fn (FeedRun $run) => $run->failed())
 *         ->disabled(fn (FeedRun $run) => $run->attempt >= 5, 'Too many attempts')
 *         ->click(fn (FeedRun $run) => $run->retry());
 *
 * Buttons go in a page's buttons() and in a table's header, row and bulk slots. Conditions run when
 * the page is drawn and again when the click arrives, so a hidden or disabled button can't be used.
 */
class Button
{
    protected ?string $label = null;

    protected ?string $icon = null;

    protected ?Tone $tone = null;

    protected Size $size = Size::Sm;

    protected bool $iconOnly = false;

    /**
     * The confirmation dialog's title and description.
     *
     * @var array{title: ?string, description: ?string}|null
     */
    protected ?array $confirm = null;

    protected bool|Closure $visible = true;

    protected bool|Closure $disabled = false;

    protected ?string $disabledReason = null;

    protected ?string $ability = null;

    /**
     * The model class the ability is checked against when there is no record.
     *
     * @var class-string<Model>|null
     */
    protected ?string $abilityModel = null;

    protected ?string $kind = null;

    protected ?Closure $callback = null;

    protected ?Form $form = null;

    protected ?string $url = null;

    protected ?string $text = null;

    protected Overlay $overlay = Overlay::Modal;

    /**
     * Create a new button instance.
     */
    public function __construct(protected string $name) {}

    /**
     * Create a button with the given name. The name is its key on the page and, as a headline,
     * its default label: Button::make('run-now') shows as "Run now".
     */
    public static function make(string $name): static
    {
        return new static($name);
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
     * Set the Lucide icon, by name.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the tone.
     */
    public function tone(string|Tone $tone): static
    {
        $this->tone = $tone instanceof Tone ? $tone : Tone::from($tone);

        return $this;
    }

    /**
     * Set the size.
     */
    public function size(string|Size $size): static
    {
        $this->size = $size instanceof Size ? $size : Size::from($size);

        return $this;
    }

    /**
     * Show only the icon, with the label as a tooltip.
     */
    public function iconOnly(bool $iconOnly = true): static
    {
        $this->iconOnly = $iconOnly;

        return $this;
    }

    /**
     * Ask for confirmation before acting. The title defaults to the label with a question mark.
     */
    public function confirm(?string $title = null, ?string $description = null): static
    {
        $this->confirm = ['title' => $title, 'description' => $description];

        return $this;
    }

    /**
     * Show the button only while the condition holds: a bool, or a closure given the row or record.
     */
    public function visible(bool|Closure $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Grey the button out, with the reason as a tooltip. The server refuses it too.
     */
    public function disabled(bool|Closure $disabled = true, ?string $reason = null): static
    {
        $this->disabled = $disabled;
        $this->disabledReason = $reason;

        return $this;
    }

    /**
     * Show the button only when the user's policy allows the ability on the record: ->can('delete').
     * A button with no record names the model instead: ->can('create', Feed::class).
     *
     * @param  class-string<Model>|null  $model
     */
    public function can(string $ability, ?string $model = null): static
    {
        $this->ability = $ability;
        $this->abilityModel = $model;

        return $this;
    }

    /**
     * Run the callback on the server when clicked. Ask for what you need by type: the row or record
     * (Feed $feed), the selected rows on a bulk button (Collection $feeds), Ui $ui, any service.
     * Return a response (a redirect, a download) to send it; anything else goes back to the page.
     */
    public function click(Closure $callback): static
    {
        return $this->does('click', callback: $callback);
    }

    /**
     * Download the file response the click callback returns. Call it after click().
     *
     * The browser fetches it with a plain GET, which has no CSRF protection, so the callback
     * must only read. Anything that writes stays a click().
     */
    public function download(): static
    {
        if ($this->callback === null) {
            throw new LogicException("Button [{$this->name}]: call click() before download().");
        }

        $this->kind = 'download';

        return $this;
    }

    /**
     * Open the form in a modal or slide-over. Its submit callback runs on submit.
     */
    public function form(Form $form): static
    {
        return $this->does('form', form: $form);
    }

    /**
     * Navigate to a URL, or to a page in this panel (a record page with its record). No callback runs.
     *
     * @param  string|class-string<Page>  $target
     */
    public function visit(string $target, Model|int|string|null $record = null): static
    {
        return $this->does('visit', url: $this->resolveUrl($target, $record));
    }

    /**
     * Open the URL in a new tab.
     */
    public function open(string $url): static
    {
        return $this->does('open', url: $url);
    }

    /**
     * Open a record page over this one, as its $overlay says: a modal or a slide-over.
     *
     * @param  class-string<RecordPage>  $page
     */
    public function modal(string $page, Model|int|string $record): static
    {
        $this->overlay = $page::overlay();

        return $this->does('modal', url: $this->resolveUrl($page, $record));
    }

    /**
     * Copy the text to the clipboard.
     */
    public function copy(string $text): static
    {
        $this->text = $text;

        return $this->does('copy');
    }

    /**
     * Get the name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the label, defaulting to the name as a headline.
     */
    public function getLabel(): string
    {
        return $this->label ?? Str::ucfirst(Str::lower(Str::headline($this->name)));
    }

    /**
     * Get the form the button opens, if any.
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * Get the click callback, if any.
     */
    public function getCallback(): ?Closure
    {
        return $this->callback;
    }

    /**
     * Determine whether the click callback's response is downloaded.
     */
    public function isDownload(): bool
    {
        return $this->kind === 'download';
    }

    /**
     * Determine whether the button is visible for the given record, by visible() and can().
     */
    public function isVisibleFor(?Model $record = null): bool
    {
        if ($this->ability !== null && $record === null && $this->abilityModel === null) {
            throw new LogicException("Button [{$this->name}]: can('{$this->ability}') has no record here; name the model: can('{$this->ability}', Model::class).");
        }

        if ($this->ability !== null && ! Gate::allows($this->ability, $record ?? $this->abilityModel)) {
            return false;
        }

        return Callback::check($this->visible, $record);
    }

    /**
     * Determine whether the button is disabled for the given record.
     */
    public function isDisabledFor(?Model $record = null): bool
    {
        return Callback::check($this->disabled, $record);
    }

    /**
     * Get the reason shown when the button is disabled.
     */
    public function getDisabledReason(): ?string
    {
        return $this->disabledReason;
    }

    /**
     * Build the button for the renderer. Page and header buttons are checked against the record
     * here; row and bulk buttons are checked per row by the table.
     *
     * @param  string  $key  The button's key on the page, e.g. 'buttons.retry' or 'feeds.row.pause'.
     * @param  'page'|'header'|'row'|'bulk'  $slot
     * @return array<string, mixed>
     */
    public function toSchema(string $key, ?Model $record = null, string $slot = 'page'): array
    {
        $kind = $this->kind ?? throw new LogicException("Button [{$this->name}] does nothing: give it click(), form(), visit(), open(), modal() or copy().");
        $disabled = in_array($slot, ['page', 'header'], true) && $this->isDisabledFor($record);
        $url = in_array($kind, ['click', 'download', 'form'], true) ? Lodestone::eventUrl($key) : $this->url;

        // A row's form loads its values when it opens, and a bulk form spans several rows, so it starts from the defaults.
        $values = match ($slot) {
            'row' => 'defer',
            'bulk' => 'defaults',
            default => 'now',
        };

        return array_filter([
            'key' => $key,
            'label' => $this->getLabel(),
            'icon' => $this->icon,
            'tone' => $this->tone?->value,
            'size' => $this->size === Size::Sm ? null : $this->size->value,
            'icon_only' => $this->iconOnly ?: null,
            'confirm' => $this->confirm === null ? null : ['title' => $this->confirm['title'] ?? $this->getLabel().'?', 'description' => $this->confirm['description']],
            'disabled' => $disabled ?: null,
            'disabled_reason' => $disabled ? $this->disabledReason : null,
            'kind' => $kind,
            'url' => $url,
            'text' => $kind === 'copy' ? $this->text : null,
            'overlay' => $kind === 'modal' ? $this->overlay->value : null,
            'form' => $kind === 'form' ? $this->form->toSchema((string) $url, $record, $values) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Set what a click does: click, download, form, visit, open, modal or copy.
     */
    protected function does(string $kind, ?Closure $callback = null, ?Form $form = null, ?string $url = null): static
    {
        [$this->kind, $this->callback, $this->form, $this->url] = [$kind, $callback, $form, $url];

        return $this;
    }

    /**
     * Resolve a page class to its URL, leaving a plain URL as is.
     */
    protected function resolveUrl(string $target, Model|int|string|null $record): string
    {
        return is_subclass_of($target, Page::class) ? Lodestone::url($target, $record) : $target;
    }
}
