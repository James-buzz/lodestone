<?php

namespace Lodestone\Components;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Markdown rendered on the server. Raw HTML is stripped and unsafe links removed. Hidden when empty.
 */
class Markdown extends Component
{
    protected ?string $heading = null;

    /**
     * Create a new Markdown block instance.
     */
    public function __construct(protected string $text) {}

    /**
     * Create a Markdown block, hidden when the text is empty.
     */
    public static function make(?string $text): static
    {
        return (new static((string) $text))->visible(filled($text));
    }

    /**
     * Put the text in a card with the given heading.
     */
    public function heading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * Render Markdown to HTML, stripping raw HTML and unsafe links.
     */
    public static function toHtml(string $text): string
    {
        return Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        return ['type' => 'markdown', 'heading' => $this->heading, 'html' => self::toHtml($this->text)];
    }
}
