<?php

namespace Lodestone\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lodestone\Components\Badge;
use Lodestone\Components\Button;
use Lodestone\Components\Component;
use Lodestone\Facades\Lodestone;
use Lodestone\Http\Concerns\LoadsPage;
use Lodestone\Page;
use Lodestone\Panel;
use Lodestone\RecordPage;
use Lodestone\Support\EventMap;

class PageController
{
    use LoadsPage;

    /**
     * Render the page as an Inertia response, or as JSON for the renderer's record modal.
     */
    public function __invoke(Request $request): Response|JsonResponse
    {
        $panel = Lodestone::current();
        $page = $this->loadPage($request);
        $schema = $page->schema();
        $buttons = array_values(array_filter($page->buttons()));

        // Building the map throws on duplicate keys, which an event couldn't tell apart.
        new EventMap($schema, $buttons);

        $props = [
            'page' => $this->meta($panel, $page, $buttons),
            'components' => Component::renderAll($schema, $request),
        ];

        if ($request->hasHeader('X-Lodestone-Modal')) {
            // The modal shares the full page's URL, so its JSON is kept out of the HTTP cache or Back could show it.
            return response()->json($props)->header('Cache-Control', 'no-store');
        }

        return Inertia::render('Lodestone/Page', $props)->withViewData('viteEntry', $panel->getViteEntry());
    }

    /**
     * Build the page's header: its title, breadcrumbs, badges and buttons.
     *
     * @param  list<Button>  $buttons
     * @return array<string, mixed>
     */
    private function meta(Panel $panel, Page $page, array $buttons): array
    {
        /** @var Model|null $record */
        $record = $page instanceof RecordPage ? $page->record : null;

        return [
            'slug' => $page::slug(),
            'title' => $record ? $page->heading() : $page::title(),
            'description' => $record ? $page->subheading() : $page::description(),
            'url' => $record ? $panel->recordUrl($page::class, $record) : $panel->url($page::class),
            'breadcrumbs' => $record ? $page->breadcrumbs() : [],
            'badges' => $record ? array_map(fn (Badge $badge) => $badge->toArray(), $page->badges()) : [],
            'buttons' => array_values(array_map(
                fn (Button $button) => $button->toSchema("buttons.{$button->getName()}", $record),
                array_filter($buttons, fn (Button $button) => $button->isVisibleFor($record)),
            )),
        ];
    }
}
