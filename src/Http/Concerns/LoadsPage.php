<?php

namespace Lodestone\Http\Concerns;

use Illuminate\Http\Request;
use Lodestone\Facades\Lodestone;
use Lodestone\Page;
use Lodestone\RecordPage;

trait LoadsPage
{
    /**
     * Load the page for the current request, the same way for drawing it and for its events.
     */
    private function loadPage(Request $request): Page
    {
        // Route parameters are read by name because Laravel passes them to controllers positionally.
        $panel = Lodestone::current();
        $recordKey = $request->route('record');
        $class = $recordKey === null ? $panel->findPage($request->route('page')) : $panel->findRecordPage($request->route('page'));

        abort_if($class === null, 404);

        // Access is checked before the record is loaded, so a 404 can't reveal which records exist.
        abort_unless($class::canAccess($request->user()), 403);

        // Builders read these back through Lodestone::page() and Lodestone::record().
        $request->attributes->set('lodestone.page', $class);
        $page = app($class);

        if ($recordKey !== null) {
            /** @var RecordPage $page */
            $page->record = $class::query($request->user())->findOrFail($recordKey);
            $request->attributes->set('lodestone.record', $page->record);
        }

        return $page;
    }
}
