<?php

namespace Lodestone\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lodestone\Components\Button;
use Lodestone\Http\Concerns\LoadsPage;
use Lodestone\RecordPage;
use Lodestone\Support\Callback;
use Lodestone\Support\EventMap;
use Lodestone\Tables\Table;
use Lodestone\Ui;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles clicks and submits. The URL names the page, the record and the builder's key; the
 * controller builds the page again, finds the builder, checks its conditions and calls its callback:
 *
 *   POST /{panel}/{page}[/{record}]/_event/{key}              a click or a form submit, with ids[] for rows
 *   GET  /{panel}/{page}[/{record}]/_event/{key}?ids[]=1      a download, or a row form's values
 *
 * A button that isn't on the rebuilt page for this user is a 404, so the rebuild is the security
 * check and there are no tokens to forge. A disabled button or a read-only form is on the page but
 * can't be used, so it answers with a danger toast instead.
 */
class EventController
{
    use LoadsPage;

    /**
     * Handle the event.
     */
    public function __invoke(Request $request, Ui $ui): Response
    {
        $page = $this->loadPage($request);
        $record = $page instanceof RecordPage ? $page->record : null;
        $target = (new EventMap($page->schema(), $page->buttons()))->find((string) $request->route('key')) ?? abort(404);
        ['slot' => $slot, 'button' => $button, 'form' => $form, 'table' => $table] = $target;

        $loadingValues = $request->isMethod('GET') && $form !== null;

        // A button that only navigates has nothing to run here.
        abort_unless($form !== null || $button?->getCallback() !== null, 404);
        abort_unless($loadingValues || $request->isMethod($button?->isDownload() ? 'GET' : 'POST'), 405);

        if (in_array($slot, ['row', 'bulk'], true)) {
            $rows = $this->rows($request, $table, $button, $slot);

            if ($rows->isEmpty()) {
                abort_if($loadingValues, 404);
                $ui->toast("This isn't available for the selected records.", 'danger');

                return back();
            }

            [$model, $records] = $slot === 'row' ? [$rows->first(), null] : [null, $rows];
        } else {
            [$model, $records] = [$record, null];

            if ($button !== null) {
                abort_unless($button->isVisibleFor($record), 404);

                if ($button->isDisabledFor($record)) {
                    abort_if($loadingValues, 404);
                    $ui->toast($button->getDisabledReason() ?? "This isn't available right now.", 'danger');

                    return back();
                }
            }
        }

        if ($loadingValues) {
            return response()->json([
                'values' => (object) $form->values($model),
                'read_only' => $form->isReadOnlyFor($model),
            ])->header('Cache-Control', 'no-store');
        }

        if ($form !== null) {
            if ($form->isReadOnlyFor($model)) {
                $ui->toast('This form is read-only.', 'danger');

                return back();
            }

            $result = $form->handle($form->validate($request->all()), $model, $records);
        } else {
            $result = Callback::call($button->getCallback(), $model, $records);
        }

        // A returned response wins, then the Ui's visit target, then back to the page. Toasts flash either way.
        if ($result instanceof Responsable) {
            $result = $result->toResponse($request);
        }

        if ($result instanceof Response) {
            return $result;
        }

        return $ui->getVisit() !== null ? redirect($ui->getVisit()) : back();
    }

    /**
     * Get the rows a row or bulk button acts on: loaded through the table's own query, then kept
     * only where the button is visible and enabled.
     *
     * @param  'row'|'bulk'  $slot
     * @return Collection<int, Model>
     */
    private function rows(Request $request, Table $table, Button $button, string $slot): Collection
    {
        $ids = array_values(array_unique(array_filter((array) $request->input('ids', []), fn ($id) => is_scalar($id) && $id !== '')));

        if ($ids === [] || ($slot === 'row' && count($ids) > 1)) {
            throw ValidationException::withMessages(['ids' => 'Choose a record first.']);
        }

        $rows = $table->recordsFor($ids);

        if ($rows->count() < count($ids)) {
            throw ValidationException::withMessages(['ids' => "Some of the selected records don't exist or aren't available."]);
        }

        return $rows->filter(fn (Model $row) => $button->isVisibleFor($row) && ! $button->isDisabledFor($row))->values();
    }
}
