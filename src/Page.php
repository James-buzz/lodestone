<?php

namespace Lodestone;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Lodestone\Components\Button;
use Lodestone\Components\Component;
use Lodestone\Components\Stat;
use Lodestone\Components\Stats;
use Lodestone\Enums\Tone;
use Lodestone\Tables\Table;

abstract class Page
{
    protected static ?string $title = null;

    protected static ?string $slug = null;

    protected static string $icon = 'layout-dashboard';

    protected static ?string $description = null;

    /**
     * The tone of the sidebar badge, or null for neutral.
     */
    protected static ?string $badgeTone = null;

    /**
     * The sidebar section the page is listed under. Pages without a group are listed first.
     */
    protected static ?string $group = null;

    /**
     * Get the page's URL segment.
     */
    public static function slug(): string
    {
        return static::$slug ?? Str::kebab(Str::replaceLast('Page', '', class_basename(static::class)));
    }

    /**
     * Get the page's title.
     */
    public static function title(): string
    {
        return static::$title ?? Str::ucfirst(Str::lower(Str::headline(static::slug())));
    }

    /**
     * Get the page's sidebar icon.
     */
    public static function icon(): string
    {
        return static::$icon;
    }

    /**
     * Get the line shown under the page's title.
     */
    public static function description(): ?string
    {
        return static::$description;
    }

    /**
     * Get the tone of the sidebar badge.
     */
    public static function badgeTone(): ?Tone
    {
        return static::$badgeTone === null ? null : Tone::from(static::$badgeTone);
    }

    /**
     * Get the sidebar section the page is listed under.
     */
    public static function group(): ?string
    {
        return static::$group;
    }

    /**
     * Determine whether the given user can open the page.
     *
     * Users who fail the check don't see it in the sidebar and get a 403.
     */
    public static function canAccess(?Authenticatable $user): bool
    {
        return true;
    }

    /**
     * Get the count shown beside the page in the sidebar.
     */
    public function badge(): ?int
    {
        return null;
    }

    /**
     * Get the buttons in the page header.
     *
     * The first three show as buttons and the rest go in a "More" menu. Their keys are
     * "buttons.{name}", and on a record page their callbacks receive the record.
     *
     * @return list<Button|null>
     */
    public function buttons(): array
    {
        return [];
    }

    /**
     * Get the stats shown above the page's table.
     *
     * @return list<Stat>
     */
    public function stats(): array
    {
        return [];
    }

    /**
     * Get the page's table.
     *
     * It is static so other pages can embed it: FeedRunsPage::table()->query($feed->runs()).
     */
    public static function table(): ?Table
    {
        return null;
    }

    /**
     * Get the page as a list of components.
     *
     * Nulls and hidden components are skipped, so conditionals can stay inline. A click rebuilds
     * the page to find its button, so keep it cheap to build: pass closures for values that cost
     * a query, and build the same buttons for the same user and record.
     *
     * @return array<int, Component|null>
     */
    public function schema(): array
    {
        $stats = $this->stats();

        return [
            $stats ? Stats::make($stats) : null,
            static::table(),
        ];
    }
}
