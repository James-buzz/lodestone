<?php

namespace Lodestone;

use Illuminate\Database\Eloquent\Model;
use Lodestone\Enums\Tone;
use Lodestone\Facades\Lodestone;

/**
 * Lodestone's reply to the screen from inside a callback. Ask for it by type, or fetch it with
 * app(Ui::class) from deeper in your own code. It is bound once per request.
 */
class Ui
{
    protected ?string $visit = null;

    /**
     * Show a toast on the next page load.
     */
    public function toast(string $message, string|Tone $tone = Tone::Success): static
    {
        $tone = $tone instanceof Tone ? $tone : Tone::from($tone);

        session()->flash('lodestone.toast', ['message' => $message, 'tone' => $tone->value]);

        return $this;
    }

    /**
     * Go to a URL, or to a page in the current panel, after the callback instead of back.
     *
     * @param  string|class-string<Page>  $target
     */
    public function visit(string $target, Model|int|string|null $record = null): static
    {
        $this->visit = is_subclass_of($target, Page::class) ? Lodestone::url($target, $record) : $target;

        return $this;
    }

    /**
     * Get the URL to go to after the callback, if one was set.
     *
     * @internal
     */
    public function getVisit(): ?string
    {
        return $this->visit;
    }
}
