<?php

namespace Lodestone;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lodestone\Components\Badge;
use Lodestone\Enums\Overlay;
use Lodestone\Facades\Lodestone;

/**
 * A page for one record, such as /admin/feed-runs/123. Reached by clicking a row in a table that
 * declares recordPage(). Build it by overriding schema() and reading $this->record.
 */
abstract class RecordPage extends Page
{
    /** @var class-string<Model> */
    protected static string $model;

    /**
     * How the page looks when opened over another page: a modal, or a slide-over from the right.
     */
    protected static string $overlay = 'modal';

    /**
     * The record, loaded from query() by the router before any method below is called.
     */
    public Model $record;

    /**
     * Get the page's URL segment: the model's plural, so it matches the list page.
     */
    public static function slug(): string
    {
        return static::$slug ?? Str::plural(Str::kebab(class_basename(static::$model)));
    }

    /**
     * Get how the page looks when opened over another page.
     */
    public static function overlay(): Overlay
    {
        return Overlay::from(static::$overlay);
    }

    /**
     * Get the records this page can show.
     *
     * The router loads the record from it, so a record outside it is a 404, and so are its
     * buttons' events. Scope it to what the user may see, and eager-load what heading() reads.
     */
    public static function query(?Authenticatable $user): Builder
    {
        return static::$model::query();
    }

    /**
     * Get the model class the page shows.
     *
     * @return class-string<Model>
     */
    public static function model(): string
    {
        return static::$model;
    }

    /**
     * Get the page's heading.
     */
    public function heading(): string
    {
        return Str::ucfirst(Str::lower(Str::headline(class_basename(static::$model))))." #{$this->record->getKey()}";
    }

    /**
     * Get the line shown under the heading.
     */
    public function subheading(): ?string
    {
        return null;
    }

    /**
     * Get the trail shown before the heading, as [label, url] pairs.
     *
     * Defaults to the list page with the same slug, if the panel has one.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function breadcrumbs(): array
    {
        $list = Lodestone::current()?->findPage(static::slug());

        return $list ? [[$list::title(), Lodestone::url($list)]] : [];
    }

    /**
     * Get the badges shown beside the heading.
     *
     * @return list<Badge>
     */
    public function badges(): array
    {
        return [];
    }
}
