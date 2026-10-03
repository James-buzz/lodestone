<?php

namespace Lodestone\Components;

use Closure;
use Illuminate\Http\Request;
use Lodestone\Enums\Tone;

/**
 * A callout. It hides itself when the text is empty, so Alert::danger('Failed', $run->error) just works.
 * The text is required: a title-only alert would never show.
 *
 * Pass a closure when the text costs a query, so it only runs when the page is drawn:
 *
 *     Alert::warning('Rush jobs', fn () => ($n = Shoot::rush()->count()) ? "{$n} still to deliver." : null)
 */
class Alert extends Component
{
    protected Tone $tone;

    /**
     * Create a new alert instance.
     */
    public function __construct(string|Tone $tone, protected string $title, protected string|Closure|null $text)
    {
        $this->tone = $tone instanceof Tone ? $tone : Tone::from($tone);
    }

    /**
     * Create a danger alert.
     */
    public static function danger(string $title, string|Closure|null $text): static
    {
        return new static(Tone::Danger, $title, $text);
    }

    /**
     * Create a warning alert.
     */
    public static function warning(string $title, string|Closure|null $text): static
    {
        return new static(Tone::Warning, $title, $text);
    }

    /**
     * Create an info alert.
     */
    public static function info(string $title, string|Closure|null $text): static
    {
        return new static(Tone::Info, $title, $text);
    }

    /**
     * Determine whether the alert is drawn.
     */
    public function isVisible(): bool
    {
        return $this->visible && filled($this->text());
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'alert', 'tone' => $this->tone->value, 'title' => $this->title, 'text' => $this->text()];
    }

    /**
     * Resolve the text, running the closure only once.
     */
    protected function text(): ?string
    {
        if ($this->text instanceof Closure) {
            $this->text = ($this->text)();
        }

        return $this->text;
    }
}
