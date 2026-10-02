<?php

namespace App\Http\Controllers;

use App\Models\ApplicationMother;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Renders the file-indexing UPDATE screen using the create form.
 *
 * Replaces resources/views/fileindexing/edit.blade.php, a 3,445-line parallel
 * implementation of the same screen that had drifted badly from the create form:
 * it is missing 48 fields the create form has — the entire Occupancy Permit block,
 * CofO no/date/status, current_holder / original_holder, indexing_type, dciv_reason,
 * prop_id, sub_prefix / suffix and shelf_location — so those could never be corrected
 * from the edit screen. (They were not wiped: update() builds its payload with
 * Arr::only($validated, ...), so fields absent from the request are preserved.)
 *
 * Nothing goes the other way: every field edit.blade.php had exists on the create
 * form, several as id-based inputs the shared JS reads (#tracking-id, #merged-plot,
 * #cofo-period), which is why a field-name diff appears to show otherwise.
 *
 * create_indexing.blade.php already carried a complete $record edit mode that no page
 * had ever rendered, so this reuses that rather than cloning a third copy. The data is
 * taken wholesale from FileIndexingController::edit() — including its tracking_id
 * backfill and the DCIV / indexed-record enrichment of related_details — so the two
 * paths cannot drift the way the two Blade files did.
 *
 * Fallback: /fileindexing/{id}/edit?legacy=1 renders the old page. Since .env changes
 * do not survive a production code upload, the query parameter — not the config flag —
 * is the escape hatch that always works.
 */
class FileIndexUpdatePageController extends Controller
{
    /**
     * View keys produced by FileIndexingController::edit() that the form needs.
     * Everything else it returns is shared view state (errors, app, __env).
     */
    protected const CARRIED_VIEW_KEYS = [
        'record',
        'lgas',
        'registries',
        'districts',
        'physicalRegistries',
        'landUseTypes',
        'cofoDetails',
        'hasPropertyRecords',
        'billBalances',
        'grantRents',
        'newKangisTransactions',
        'titleStatusSelection',
    ];

    public function __invoke(Request $request, $id)
    {
        // Instant fallback to the legacy screen, no deploy required.
        if ($this->shouldUseLegacyForm($request)) {
            return app(FileIndexingController::class)->edit($id);
        }

        $prepared = $this->prepareUpdateForm($request, $id);

        if (!is_array($prepared)) {
            return $prepared; // redirect from edit(), e.g. record not found
        }

        return view($this->formView(), $prepared);
    }

    protected function formView(): string
    {
        return 'fileindexing.addons.create_indexing';
    }

    /**
     * Build the form payload, or return the redirect edit() produced.
     *
     * @return array<string,mixed>|\Illuminate\Http\RedirectResponse
     */
    protected function prepareUpdateForm(Request $request, $id)
    {
        $rendered = app(FileIndexingController::class)->edit($id);

        // edit() redirects when the record is missing, or when the id turns out to be a
        // fileNumber id it can resolve to the right indexing record. Pass that through.
        if (!$rendered instanceof View) {
            return $rendered;
        }

        $viewData = $rendered->getData();
        $record = $viewData['record'] ?? null;

        if (!$record) {
            return redirect()->route('fileindex.index')
                ->with('error', 'File indexing record not found.');
        }

        $data = [];
        foreach (self::CARRIED_VIEW_KEYS as $key) {
            if (array_key_exists($key, $viewData)) {
                $data[$key] = $viewData[$key];
            }
        }

        // The shared form partials read properties that are NOT columns on
        // file_indexings ($record->has_related_file, $record->land_use) and read them
        // unguarded, inside branches that only run when $record is set. No page ever set
        // $record before this one, so those branches had never executed; on a plain
        // stdClass they raise "Undefined property", which Laravel promotes to an
        // ErrorException and 500s the page. Fluent returns null for anything absent, so
        // the partials stay untouched and future additions degrade to empty, not fatal.
        $data['record'] = new Fluent((array) $record);
        $data['fileIndexing'] = $data['record'];

        $data['PageTitle'] = 'Update File Index';
        $data['PageDescription'] = 'Update an existing file indexing record';

        // Supplied by the create page's controller, absent from edit()'s payload.
        $data['availableApplications'] = $this->getAvailableApplications();
        $data['streetNames'] = \App\Models\StreetName::orderBy('name')->get();

        $data['isNewKnMode'] = false;
        $data['prefillFileNumber'] = '';
        $data['prefillTrackingId'] = '';
        $data['returnTo'] = (string) $request->query('return_to', '');
        $data['backButton'] = $this->backButton($request);

        return $data;
    }

    /**
     * @return array{label:string,route:string}|null
     */
    protected function backButton(Request $request): ?array
    {
        $returnTo = trim((string) $request->query('return_to', ''));

        if ($returnTo !== '') {
            return ['label' => 'Back', 'route' => $returnTo];
        }

        return [
            'label' => 'Back to Indexed Files',
            'route' => route('fileindex.index'),
        ];
    }

    protected function shouldUseLegacyForm(Request $request): bool
    {
        if ($request->boolean('legacy')) {
            return true;
        }

        return (bool) config('fileindexing.legacy_edit_form', false);
    }

    protected function getAvailableApplications()
    {
        try {
            return ApplicationMother::on('sqlsrv')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('file_indexings')
                        ->whereRaw('file_indexings.main_application_id = mother_applications.id');
                })
                ->select('id', 'fileno', 'np_fileno', 'first_name', 'middle_name', 'surname', 'corporate_name', 'applicant_type')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        } catch (\Throwable $e) {
            // The applications picker is only used when creating from an application;
            // on the update screen an empty list is harmless.
            Log::warning('FileIndexUpdatePage - could not load available applications', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
