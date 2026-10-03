<?php

namespace Lodestone\Tables\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TextColumn extends Column
{
    protected ?string $format = null;

    protected bool $mono = false;

    protected bool $muted = false;

    protected bool $strong = false;

    protected ?int $limit = null;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected ?string $placeholder = null;

    protected bool $copyable = false;

    protected bool $avatar = false;

    protected string|Closure|null $avatarImage = null;

    protected string|Closure|null $description = null;

    /**
     * Format the value as a number.
     */
    public function numeric(): static
    {
        return $this->format('number');
    }

    /**
     * Format the value as a duration in seconds, such as "1m 12s".
     */
    public function duration(): static
    {
        return $this->format('duration');
    }

    /**
     * Format the value as a relative time, such as "3m ago", kept live in the browser.
     */
    public function relative(): static
    {
        return $this->format('relative');
    }

    /**
     * Format the value as a date and time.
     */
    public function dateTime(): static
    {
        return $this->format('datetime');
    }

    /**
     * Format the value as a date, such as "29 Sept 2026".
     */
    public function date(): static
    {
        return $this->format('date');
    }

    /**
     * Format the value as a byte count.
     */
    public function bytes(): static
    {
        return $this->format('bytes');
    }

    /**
     * Format the value as money in the given currency.
     */
    public function money(string $currency = 'GBP'): static
    {
        return $this->format("money:{$currency}");
    }

    /**
     * Set the format the renderer applies to the value.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Show the value in a monospace font.
     */
    public function mono(): static
    {
        $this->mono = true;

        return $this;
    }

    /**
     * Show the value muted.
     */
    public function muted(): static
    {
        $this->muted = true;

        return $this;
    }

    /**
     * Show the value in bold.
     */
    public function strong(): static
    {
        $this->strong = true;

        return $this;
    }

    /**
     * Truncate the value to the given number of characters.
     */
    public function limit(int $characters): static
    {
        $this->limit = $characters;

        return $this;
    }

    /**
     * Set text shown before the value.
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set text shown after the value.
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Set the muted text shown instead of a dash when the value is empty.
     */
    public function placeholder(string $text): static
    {
        $this->placeholder = $text;

        return $this;
    }

    /**
     * Show a copy button beside the value.
     */
    public function copyable(bool $copyable = true): static
    {
        $this->copyable = $copyable;

        return $this;
    }

    /**
     * Show the value as a person with an avatar before it: initials, or an image from an
     * attribute or a closure returning a URL.
     */
    public function avatar(string|Closure|null $image = null): static
    {
        $this->avatar = true;
        $this->avatarImage = $image;

        return $this;
    }

    /**
     * Show a second, muted line under the value from an attribute path or a closure.
     */
    public function description(string|Closure $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the extra per-record values the cell needs.
     *
     * @return array<string, mixed>
     */
    public function cellData(Model $record): array
    {
        return array_filter([
            ...parent::cellData($record),
            'avatar' => $this->avatar ? $this->resolve($this->avatarImage, $record) : null,
            'description' => $this->resolve($this->description, $record),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Get the cell type the renderer looks up in its registry.
     */
    protected function type(): string
    {
        return 'text';
    }

    /**
     * Get the settings the cell needs.
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        $numeric = in_array(Str::before($this->format ?? '', ':'), ['number', 'duration', 'bytes', 'money'], true);

        return array_filter([
            'format' => $this->format,
            'align' => $numeric ? 'right' : null,
            'mono' => $this->mono,
            'muted' => $this->muted,
            'strong' => $this->strong,
            'limit' => $this->limit,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'placeholder' => $this->placeholder,
            'copyable' => $this->copyable,
            'avatar' => $this->avatar,
        ]);
    }
}
