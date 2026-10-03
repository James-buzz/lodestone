<?php

namespace Lodestone;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Panel
{
    protected string $path;

    protected string $title;

    /** @var list<class-string<Page>> */
    protected array $pages = [];

    /** @var list<string> */
    protected array $middleware = [];

    protected string $viteEntry = 'resources/js/lodestone.tsx';

    protected ?string $icon = null;

    protected ?string $description = null;

    protected ?Closure $access = null;

    /**
     * Create a new panel instance.
     */
    public function __construct(public readonly string $id)
    {
        $this->path = $id;
        $this->title = Str::headline($id);
    }

    /**
     * Create a new panel with the given id.
     */
    public static function make(string $id): static
    {
        return new static($id);
    }

    /**
     * Set the path the panel is served at.
     */
    public function path(string $path): static
    {
        $this->path = trim($path, '/');

        return $this;
    }

    /**
     * Set the panel's title.
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the panel's pages.
     *
     * List pages appear in the sidebar in this order, and the first is the panel's home.
     * Record pages are routed but not listed.
     *
     * @param  list<class-string<Page>>  $pages
     */
    public function pages(array $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * Set the middleware added after the web group, such as ['auth', 'can:view-admin'].
     *
     * @param  list<string>  $middleware
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * Set the Lucide icon shown in the panel switcher.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the line shown under the title in the panel switcher.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set who can use the panel. Others don't see it in the panel switcher and get a 403.
     *
     * The check runs after the panel's middleware, so authentication has already happened.
     *
     * @param  Closure(?Authenticatable): bool  $check
     */
    public function canAccess(Closure $check): static
    {
        $this->access = $check;

        return $this;
    }

    /**
     * Determine whether the given user can use the panel.
     */
    public function isAccessibleBy(?Authenticatable $user): bool
    {
        return $this->access === null || (bool) ($this->access)($user);
    }

    /**
     * Get the panel's icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Get the panel's description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the host app's Vite entry that calls createLodestoneApp().
     */
    public function vite(string $entry): static
    {
        $this->viteEntry = $entry;

        return $this;
    }

    /**
     * Get the path the panel is served at.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the panel's title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the pages shown in the sidebar.
     *
     * @return list<class-string<Page>>
     */
    public function getNavPages(): array
    {
        return array_values(array_filter($this->pages, fn (string $page) => ! is_subclass_of($page, RecordPage::class)));
    }

    /**
     * Get the panel's middleware, starting with the web group.
     *
     * @return list<string>
     */
    public function getMiddleware(): array
    {
        return ['web', ...$this->middleware];
    }

    /**
     * Get the host app's Vite entry.
     */
    public function getViteEntry(): string
    {
        return $this->viteEntry;
    }

    /**
     * Find the list page with the given slug, or the home page when the slug is null.
     *
     * @return class-string<Page>|null
     */
    public function findPage(?string $slug): ?string
    {
        $pages = $this->getNavPages();

        if ($slug === null) {
            return $pages[0] ?? null;
        }

        foreach ($pages as $page) {
            if ($page::slug() === $slug) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Find the record page with the given slug.
     *
     * @return class-string<RecordPage>|null
     */
    public function findRecordPage(string $slug): ?string
    {
        foreach ($this->pages as $page) {
            if (is_subclass_of($page, RecordPage::class) && $page::slug() === $slug) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Get the URL of the given list page.
     *
     * @param  class-string<Page>  $page
     */
    public function url(string $page): string
    {
        return url($page === $this->findPage(null) ? $this->path : "{$this->path}/{$page::slug()}");
    }

    /**
     * Get the URL of the given record page for the given record.
     *
     * @param  class-string<RecordPage>  $page
     */
    public function recordUrl(string $page, Model|int|string $record): string
    {
        $key = $record instanceof Model ? $record->getKey() : $record;

        return url("{$this->path}/{$page::slug()}/{$key}");
    }
}
