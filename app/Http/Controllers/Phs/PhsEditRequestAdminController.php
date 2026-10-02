<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsEditRequest;
use App\Services\LegalSearchService;
use App\Services\Phs\PhsEditRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PHS-P Admin side of the correction workflow.
 *
 * The queue lists what members have reported; the preview screen is where the
 * records are actually corrected, using the SAME editing endpoints as the main
 * Legal Search timeline (legalsearch.match / drop / remove / update /
 * createRecord / saveArrangement) so corrections made here behave identically to
 * corrections made there — and, more to the point, so there is only one
 * implementation of "edit a timeline record" to keep correct.
 *
 * Returning a request is the act that authorises the member's free re-run, so it
 * goes through PhsEditRequestService rather than touching the row here.
 */
class PhsEditRequestAdminController extends Controller
{
    public function __construct(
        private PhsEditRequestService $editRequests,
        private LegalSearchService $searchService
    ) {
    }

    /**
     * The correction queue. Defaults to what is actually waiting on the admin.
     */
    public function index(Request $request)
    {
        $PageTitle = 'PHS — Edit Requests';
        $statusFilter = $request->query('status', PhsEditRequest::STATUS_EDIT_REQUESTED);

        $query = PhsEditRequest::with(['institution', 'member'])->orderByDesc('id');
        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $items = $query->limit(200)->get();

        $statsByStatus = collect(array_keys(PhsEditRequest::STATUS_LABELS))
            ->mapWithKeys(fn ($s) => [$s => PhsEditRequest::where('status', $s)->count()])
            ->all();

        return view('system-admin.phs.edit_requests', compact(
            'PageTitle', 'items', 'statusFilter', 'statsByStatus'
        ));
    }

    /**
     * The correction workspace for one request.
     *
     * Shows the report the member complained about alongside the CURRENT report,
     * so the admin can see what changed as they correct it. The current report is
     * rebuilt from the canonical print engine on every load — that is the whole
     * point: the admin needs to see the live state, not a cached copy.
     */
    public function preview(int $id)
    {
        $editRequest = PhsEditRequest::with(['institution', 'member'])->findOrFail($id);
        $PageTitle = 'PHS — Correct Search Result';

        $fileNumber = trim((string) $editRequest->file_number);
        $report = null;
        $reportError = null;
        $records = [];
        $propId = null;

        // ONE engine pass. buildPrintReport() calls search() internally, so asking
        // for both ran the whole search twice — measured at 14.1s + 8.3s on a heavy
        // file, which put the page within reach of the 60s request limit.
        //
        // search() returns the actual records, with ids and prop_id-expanded, which
        // is precisely what correcting needs: the contaminating rows an admin has to
        // remove are usually the ones pulled in from a linked file, and the cheaper
        // findRecordsForFileNumberAllSources() misses exactly those.
        try {
            $found = $this->searchService->search(['query' => $fileNumber]);

            if (empty($found['transactions'])) {
                $reportError = 'No records were found for ' . ($fileNumber ?: '—') . '.';
            }

            foreach ($found['transactions'] ?? [] as $t) {
                if (empty($t['id'])) {
                    continue;
                }

                // search() labels the source ("CofO"); the edit/remove endpoints
                // want the table ("CofO_staging").
                $table = [
                    'File History'      => 'file_history_staging',
                    'CofO'              => 'CofO_staging',
                    'PRA'               => 'pra',
                    'Deed Registration' => 'deed_registrations',
                ][$t['source_table'] ?? ''] ?? null;

                if (!$table) {
                    continue;
                }

                // The arrangement table is keyed on prop_id, so capture it off the
                // rows rather than looking it up again.
                if ($propId === null && !empty($t['prop_id'])) {
                    $propId = (string) $t['prop_id'];
                }

                $records[] = [
                    'id'          => $t['id'],
                    'table'       => $table,
                    'instrument'  => $t['transaction_type'] ?? 'Instrument',
                    'party_1'     => $t['party_1'] ?? null,
                    'party_2'     => $t['party_2'] ?? null,
                    'reg_no'      => $t['regNo'] ?? ($t['registration'] ?? null),
                    'date'        => $t['transaction_date'] ?? ($t['reg_date'] ?? null),
                    'file_number' => $t['lifecycle_file_no'] ?? ($t['file_number'] ?? $fileNumber),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return view('system-admin.phs.edit_request_preview', [
            'PageTitle'   => $PageTitle,
            'editRequest' => $editRequest,
            'report'      => $report,
            'reportError' => $reportError,
            'records'     => $records,
            'propId'      => $propId,
            'original'    => $editRequest->originalResult(),
            'fileNumber'  => $fileNumber,
        ]);
    }

    /**
     * Corrections are done — return the request and authorise the free re-run.
     */
    public function returnForRerun(Request $request, int $id)
    {
        $data = $request->validate([
            'admin_response' => ['nullable', 'string', 'max:2000'],
        ]);

        $editRequest = PhsEditRequest::findOrFail($id);

        $ok = $this->editRequests->returnForRerun(
            $editRequest,
            Auth::user(),
            $data['admin_response'] ?? null
        );

        if (!$ok) {
            return back()->with('error',
                'This request is already ' . $editRequest->statusLabel() . ' and cannot be returned again.');
        }

        return redirect()
            ->route('system-admin.phs.edit-requests')
            ->with('success', 'Request returned to '
                . ($editRequest->requester_name ?: 'the member')
                . '. They can now re-run the search free of charge.');
    }

    /**
     * Nothing to correct — close the request without granting a free re-run.
     */
    public function decline(Request $request, int $id)
    {
        $data = $request->validate([
            'admin_response' => ['required', 'string', 'max:2000'],
        ]);

        $editRequest = PhsEditRequest::findOrFail($id);

        $ok = $this->editRequests->decline($editRequest, Auth::user(), $data['admin_response']);

        if (!$ok) {
            return back()->with('error',
                'This request is already ' . $editRequest->statusLabel() . ' and cannot be declined.');
        }

        return redirect()
            ->route('system-admin.phs.edit-requests')
            ->with('success', 'Request declined. No free re-run was granted.');
    }
}
