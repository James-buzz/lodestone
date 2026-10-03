<?php

namespace Lodestone\Components;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lodestone\Enums\Tone;

/**
 * A bar, line or area chart. Feed it rows with an `x` key plus one key per series; the app
 * prepares them. Pass a closure so the queries only run when the page is drawn:
 *
 *     Chart::bar('Runs per hour')->data(fn () => $runsPerHour)
 *         ->series(['completed' => 'success', 'failed' => 'danger'])->stacked()
 */
class Chart extends Component
{
    protected ?string $description = null;

    /**
     * The rows to plot, or a closure returning them.
     *
     * @var list<array<string, mixed>>|Closure
     */
    protected array|Closure $data = [];

    /**
     * The tone for each plotted key.
     *
     * @var array<string, string|null>
     */
    protected array $series = [];

    /**
     * The legend label for each plotted key.
     *
     * @var array<string, string>
     */
    protected array $labels = [];

    protected bool $stacked = false;

    protected int $height = 240;

    protected ?string $format = null;

    /**
     * Create a new chart instance.
     */
    public function __construct(protected string $kind, protected string $heading) {}

    /**
     * Create a bar chart.
     */
    public static function bar(string $heading): static
    {
        return new static('bar', $heading);
    }

    /**
     * Create a line chart.
     */
    public static function line(string $heading): static
    {
        return new static('line', $heading);
    }

    /**
     * Create an area chart.
     */
    public static function area(string $heading): static
    {
        return new static('area', $heading);
    }

    /**
     * Set the description shown under the heading.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the rows to plot. Each row has an `x` value and one value per series.
     *
     * @param  list<array<string, mixed>>|Closure  $rows
     */
    public function data(array|Closure $rows): static
    {
        $this->data = $rows;

        return $this;
    }

    /**
     * Set the keys to plot and their tones. A plain list of keys, or a null tone, uses the
     * theme's chart palette. Defaults to every key in the data except `x`.
     *
     * @param  array<int|string, string|Tone|null>  $series
     */
    public function series(array $series): static
    {
        if (array_is_list($series)) {
            $this->series = array_fill_keys($series, null);

            return $this;
        }

        $this->series = array_map(fn (string|Tone|null $tone) => match (true) {
            $tone === null => null,
            $tone instanceof Tone => $tone->value,
            default => Tone::from($tone)->value,
        }, $series);

        return $this;
    }

    /**
     * Set the legend and tooltip label for each key. Defaults to the key as a headline.
     *
     * @param  array<string, string>  $labels
     */
    public function labels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Stack the series.
     */
    public function stacked(bool $stacked = true): static
    {
        $this->stacked = $stacked;

        return $this;
    }

    /**
     * Set the height in pixels.
     */
    public function height(int $pixels): static
    {
        $this->height = $pixels;

        return $this;
    }

    /**
     * Set the value format for the axis and tooltip: number, duration, bytes, percent or money:GBP.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Build the node for the renderer.
     */
    public function toSchema(Request $request): array
    {
        $data = $this->data instanceof Closure ? ($this->data)() : $this->data;
        $series = $this->series ?: array_fill_keys(array_values(array_diff(array_keys($data[0] ?? []), ['x'])), null);

        return [
            'type' => 'chart',
            'kind' => $this->kind,
            'heading' => $this->heading,
            'description' => $this->description,
            'data' => $data,
            'series' => array_map(fn (string $key, ?string $tone) => [
                'key' => $key,
                'label' => $this->labels[$key] ?? ($key === 'value' ? $this->heading : Str::ucfirst(Str::lower(Str::headline($key)))),
                'tone' => $tone,
            ], array_keys($series), $series),
            'stacked' => $this->stacked,
            'height' => $this->height,
            'format' => $this->format,
            'x_format' => self::xFormat($data),
        ];
    }

    /**
     * Determine the x-axis format: 'hour' or 'day' when the x values are ISO timestamps, otherwise null.
     *
     * @param  list<array<string, mixed>>  $data
     */
    protected static function xFormat(array $data): ?string
    {
        $first = $data[0]['x'] ?? null;

        if (! is_string($first) || strtotime($first) === false || ! str_contains($first, 'T')) {
            return null;
        }

        foreach ($data as $row) {
            if (! str_contains((string) ($row['x'] ?? ''), 'T00:00:00')) {
                return 'hour';
            }
        }

        return 'day';
    }
}
