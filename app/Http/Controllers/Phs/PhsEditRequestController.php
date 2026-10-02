<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsEditRequest;
use App\Models\Phs\PhsSearchLog;
use App\Services\Phs\PhsEditRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Member-facing half of the correction workflow: raise "this result is wrong",
 * then collect the free re-run once PHS-P Admin has fixed it.
 *
 * The admin half lives in PhsAdminController; the rules that decide whether a
 * re-run is free live in PhsEditRequestService and nowhere else.
 */
class PhsEditRequestController extends Controller
{
    public function __construct(private PhsEditRequestService $editRequests)
    {
    }

    /**
     * "Send Edit Request" from a search result the member says is wrong.
     */
    public function store(Request $request)
    {
        $member = Auth::guard('phs')->user();

        $validated = $request->validate([
            'file_number'     => 'required|string|max:100',
            'reason_category' => 'required|string|in:' . implode(',', array_keys(PhsEditRequest::REASONS)),
            'reason'          => 'required|string|max:4000',
            'reference_no'    => 'nullable|string|max:60',
            // The report the member is looking at, so the admin corrects against
            // what was actually complained about.
            'original_result' => 'nullable|array',
        ]);

        // Tie the complaint to the search that produced it where we can, so the
        // admin can see what was charged and when.
        $searchLog = null;
        if (!empty($validated['reference_no'])) {
            $searchLog = PhsSearchLog::where('reference_no', $validated['reference_no'])
                ->where('phs_member_id', $member->id)
                ->latest('id')
                ->first();
        }

        $editRequest = $this->editRequests->open(
            $member,
            $validated['file_number'],
            $validated['reason_category'],
            $validated['reason'],
            $validated['original_result'] ?? null,
            $searchLog,
            $request->ip()
        );

        // open() returns the existing row when one is already outstanding, so a
        // member who clicks twice is told where it stands rather than being told
        // it worked twice.
        $alreadyOpen = !$editRequest->wasRecentlyCreated;

        return response()->json([
            'success'      => true,
            'already_open' => $alreadyOpen,
            'status'       => $editRequest->status,
            'status_label' => $editRequest->statusLabel(),
            'message'      => $alreadyOpen
                ? 'You already have an open edit request for ' . $editRequest->file_number
                    . ' (' . $editRequest->statusLabel() . '). We will notify you when it has been corrected.'
                : 'Your edit request has been sent to the PHS-P Admin. You will be notified once the result has been corrected.',
            'edit_request' => $this->present($editRequest),
        ]);
    }

    /**
     * The member's own edit requests, newest first.
     *
     * Drives the portal's notification badge: anything READY_FOR_RERUN is a
     * corrected result waiting to be collected.
     */
    public function index()
    {
        $member = Auth::guard('phs')->user();

        $requests = PhsEditRequest::where('phs_member_id', $member->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success'  => true,
            'requests' => $requests->map(fn ($r) => $this->present($r))->values(),
            // What the member is owed right now.
            'ready_for_rerun' => $requests
                ->filter(fn ($r) => $r->authorisesFreeRerun())
                ->map(fn ($r) => $this->present($r))
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PhsEditRequest $r): array
    {
        return [
            'id'                => $r->id,
            'file_number'       => $r->file_number,
            'reference_no'      => $r->reference_no,
            'reason_category'   => $r->reason_category,
            'reason_label'      => $r->reasonLabel(),
            'reason'            => $r->reason,
            'status'            => $r->status,
            'status_label'      => $r->statusLabel(),
            'requested_at'      => optional($r->requested_at)->toDateTimeString(),
            'corrected_at'      => optional($r->corrected_at)->toDateTimeString(),
            'reviewer_name'     => $r->reviewer_name,
            'admin_response'    => $r->admin_response,
            // Drives the Re-run button. Deliberately the service's own answer,
            // never re-derived from the status in the front end.
            'can_rerun'         => $r->authorisesFreeRerun(),
            'rerun_at'          => optional($r->rerun_at)->toDateTimeString(),
            'notification'      => $r->authorisesFreeRerun()
                ? 'Your search result has been corrected and is ready. Click Re-run Search to generate the updated result. No token will be deducted for this re-run.'
                : null,
        ];
    }
}
