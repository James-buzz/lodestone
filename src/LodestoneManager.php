<?php

namespace Lodestone;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Lodestone\Http\Controllers\EventController;
use Lodestone\Http\Controllers\PageController;
use Lodestone\Http\Middleware\HandleLodestoneRequests;
use LogicException;

/**
 * Registers panels at boot and builds URLs inside the current one.
 */
class LodestoneManager
{
    /**
     * The version of the JSON protocol shared with the React renderer.
     *
     * PHP and React ship together, so this only catches an app that didn't rebuild its assets after updating.
     */
    public const PROTOCOL = 2;

    /** @var array<string, Panel> */
    protected array $panels = [];

    /**
     * Register a panel.
     */
    public function panel(Panel $panel): Panel
    {
        $this->panels[$panel->id] = $panel;

        // Panels normally register during boot, and the service provider adds their routes once booted.
        if (app()->isBooted() && ! app()->routesAreCached()) {
            $this->routes($panel);
        }

        return $panel;
    }

    /**
     * Register the panel's routes.
     */
    public function routes(Panel $panel): void
    {
        Route::middleware([...$panel->getMiddleware(), HandleLodestoneRequests::class])
            ->prefix($panel->getPath())
            ->name("lodestone.{$panel->id}.")
            ->group(function () use ($panel) {
                // The event routes come first, or the page routes would read "_event" as a record id.
                Route::match(['get', 'post'], '/{page}/_event/{key}', EventController::class)->defaults('panel', $panel->id)->name('event');
                Route::match(['get', 'post'], '/{page}/{record}/_event/{key}', EventController::class)->defaults('panel', $panel->id)->name('record.event');
                Route::get('/{page}/{record}', PageController::class)->defaults('panel', $panel->id)->name('record');
                Route::get('/{page?}', PageController::class)->defaults('panel', $panel->id)->name('page');
            });
    }

    /**
     * Get the registered panels, keyed by id.
     *
     * @return array<string, Panel>
     */
    public function panels(): array
    {
        return $this->panels;
    }

    /**
     * Get the panel with the given id.
     */
    public function get(string $id): Panel
    {
        return $this->panels[$id] ?? throw new InvalidArgumentException("Lodestone panel [{$id}] is not registered.");
    }

    /**
     * Get the protocol version.
     */
    public function protocol(): int
    {
        return self::PROTOCOL;
    }

    /**
     * Get the panels the given user can open, for the panel switcher.
     *
     * @return list<array{id: string, title: string, description: ?string, icon: ?string, url: string, current: bool}>
     */
    public function switcher(?Authenticatable $user, ?Panel $current): array
    {
        return array_values(array_map(fn (Panel $panel) => [
            'id' => $panel->id,
            'title' => $panel->getTitle(),
            'description' => $panel->getDescription(),
            'icon' => $panel->getIcon(),
            'url' => url($panel->getPath()),
            'current' => $panel === $current,
        ], array_filter($this->panels, fn (Panel $panel) => $panel->isAccessibleBy($user))));
    }

    /**
     * Get the panel serving the current request, if any.
     */
    public function current(): ?Panel
    {
        $id = request()->route('panel');

        return is_string($id) ? $this->panels[$id] ?? null : null;
    }

    /**
     * Get a page's URL in the current panel, or a record page's URL for the given record.
     *
     * @param  class-string<Page>  $page
     */
    public function url(string $page, Model|int|string|null $record = null): string
    {
        $panel = $this->current() ?? throw new LogicException('Lodestone::url() needs a panel request.');

        return $record === null ? $panel->url($page) : $panel->recordUrl($page, $record);
    }

    /**
     * Get the page class being drawn or handling an event.
     *
     * @return class-string<Page>
     */
    public function page(): string
    {
        return request()->attributes->get('lodestone.page') ?? throw new LogicException('Lodestone::page() needs a page request.');
    }

    /**
     * Get the current record page's record, or null on a list page.
     */
    public function record(): ?Model
    {
        return request()->attributes->get('lodestone.record');
    }

    /**
     * Get the URL a button or form on the current page sends its events to.
     *
     * It is just a path: the event controller rebuilds the page and finds the builder by key.
     *
     * @internal
     */
    public function eventUrl(string $key): string
    {
        $panel = $this->current() ?? throw new LogicException('Event URLs need a panel request.');
        $page = $this->page();
        $record = $this->record();
        $path = $record ? "{$page::slug()}/{$record->getKey()}" : $page::slug();

        return url("{$panel->getPath()}/{$path}/_event/".rawurlencode($key));
    }
}
