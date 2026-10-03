<?php

namespace Lodestone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Lodestone\Facades\Lodestone;
use Lodestone\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs inside the panel's route group, after the host app's own Inertia middleware if it has
 * one, so the panel always gets its own root view.
 */
class HandleLodestoneRequests extends Middleware
{
    protected $rootView = 'lodestone::app';

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Lodestone::get($request->route('panel'))->isAccessibleBy($request->user()), 403);

        return parent::handle($request, $next);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $panel = Lodestone::get($request->route('panel'));

        return [
            ...parent::share($request),
            'lodestone' => fn () => [
                'protocol' => Lodestone::PROTOCOL,
                'panel' => ['id' => $panel->id, 'title' => $panel->getTitle(), 'description' => $panel->getDescription(), 'icon' => $panel->getIcon(), 'url' => url($panel->getPath())],
                'panels' => Lodestone::switcher($request->user(), $panel),
                'nav' => array_values(array_map(fn (string $page) => [
                    'slug' => $page::slug(),
                    'title' => $page::title(),
                    'icon' => $page::icon(),
                    'group' => $page::group(),
                    'url' => $panel->url($page),
                    'badge' => $this->badge($page),
                ], array_filter($panel->getNavPages(), fn (string $page) => $page::canAccess($request->user())))),
            ],
            'toast' => fn () => $request->session()->get('lodestone.toast'),
        ];
    }

    /**
     * Get the sidebar badge for the given page, if it has one.
     *
     * @param  class-string<Page>  $page
     * @return array{value: int, tone: ?string}|null
     */
    private function badge(string $page): ?array
    {
        $value = app($page)->badge();

        return $value === null ? null : ['value' => $value, 'tone' => $page::badgeTone()?->value];
    }
}
