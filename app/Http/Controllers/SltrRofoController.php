<?php

namespace App\Http\Controllers;

use App\Models\SltrRecommendation;
use App\Services\Pra\RofoPraSyncer;
use App\Services\SecurityPaperCodeService;
use App\Models\PrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Support\SltrApproval;
use App\Services\RofoRecommendationPurgeService;
use Illuminate\Support\Facades\Log;

class SltrRofoController extends Controller
{
    use ExecutesMasterDelete;

    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator')
            ->where('status', SltrRecommendation::STATUS_APPROVED);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%");
            });
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total_eligible'      => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'pending_generation'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)
                                        ->where('rofo_status', SltrRecommendation::ROFO_PENDING)->count(),
            'generated'           => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
            'total_ground_rent'   => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->sum('ground_rent'),
        ];

        $PageTitle = 'SLTR RofO Management';

        $canApprove = $this->userCanApprove();

        // Which rows have had their proof run off: the Print Manager opens on the
        // strength of it, and the White Copy closes with it. Keyed by sltr_number,
        // which is what the proof is logged against.
        $whiteCopyDone = array_flip(PrintLog::whiteCopyPrinted(
            'SLTR RofO',
            $recommendations->getCollection()->pluck('sltr_number')->filter()->all()
        ));

        return view('sltr_rofos.index', compact('recommendations', 'stats', 'PageTitle', 'canApprove', 'whiteCopyDone'));
    }

    /**
     * Delegated to SltrApproval so both SLTR screens answer this the same way:
     * a Supper Admin, the Director SLTR, the Deputy Director SLTR, or -- while
     * Open Approval is switched on -- anyone at all.
     */
    private function userCanApprove(): bool
    {
        return SltrApproval::allows(Auth::user());
    }

    public function generate(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status !== SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Recommendation must be approved before generating ROFO.'], 403);
        }

        $validated = $request->validate([
            'rofo_director_survey'  => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor'=> 'nullable|string|in:YES,NO',
            'rofo_date_generated'   => 'nullable|date',
            'rofo_time_generated'   => 'nullable|string',
        ]);

        $generatedAt = now();
        if ($request->filled('rofo_date_generated')) {
            $time = $request->filled('rofo_time_generated') ? $request->rofo_time_generated : '00:00';
            $generatedAt = \Carbon\Carbon::parse($request->rofo_date_generated . ' ' . $time);
        }

        $rec->update(array_merge($validated, [
            'rofo_status'       => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at' => $generatedAt,
            'rofo_date_generated'=> $request->rofo_date_generated ?? now()->toDateString(),
            'updated_by'        => Auth::id(),
        ]));

        app(RofoPraSyncer::class)->syncSltr($rec->fresh());

        // sltr_recommendations has no phone column, so the number comes from the
        // resolver's file_indexings fallback -- and its shared-number guard is
        // what stops a Letter of Grant going to whoever indexed the file.
        app(\App\Services\Sms\RofoSmsNotifier::class)->send(
            \App\Services\Sms\RofoSmsNotifier::SOURCE_SLTR,
            $rec->sltr_number,
            $rec->id,
            'SLTR Department'
        );

        return response()->json(['success' => true, 'message' => 'SLTR RofO generated successfully.']);
    }

    /**
     * The White Copy: a black & white proof of the SLTR letter, for vetting before
     * anything is put on security paper.
     *
     * The same record through the same template, with every mark of an issued
     * document taken off it — arms, QR, serial, copy designation, signature blocks —
     * and marked WHITE COPY instead. Nothing about official print state is touched:
     * the template omits the afterprint call to log-print, so no print_logs row is
     * written and the RofO does not move onto the Printed side.
     *
     * Recorded under its own document type so the proofing stage can be seen to be
     * done without any "is this printed?" query mistaking it for a real run.
     */
    /**
     * Store the date of issue on its own, for the White Copy card and the Print
     * Manager's Edit.
     *
     * Mirrors LandRofoController::saveIssueDate, including the apply rule: a date
     * already on a record is what an issued letter out in the world carries, so
     * 'missing' (the default) fills only the blanks and 'all' is sent only when an
     * operator has unlocked the field and confirmed the change.
     */
    public function saveIssueDate(Request $request)
    {
        $validated = $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer',
            'issue_date' => 'required|date',
        ]);

        $date      = \Carbon\Carbon::parse($validated['issue_date'])->startOfDay();
        $overwrite = $request->input('issue_date_apply') === 'all';

        $records = SltrRecommendation::whereIn('id', $validated['ids'])->get();

        foreach ($records as $rec) {
            if (!$overwrite && filled($rec->date_issued)) {
                continue;
            }

            $rec->date_issued = $date;
            $rec->updated_by  = Auth::id();
            $rec->save();
        }

        return response()->json(['success' => true, 'count' => $records->count()]);
    }

    public function printWhiteCopy(Request $request, $id)
    {
        $view = $this->print($request, $id, true);

        $sltrNumber = SltrRecommendation::find($id)?->sltr_number;

        // Logged on render rather than on afterprint: the proof carries no
        // afterprint call by design, and the Print Manager opens on the strength of
        // this row. print() above aborts on a RofO that was never generated, so
        // nothing is logged for a sheet that never rendered.
        PrintLog::logWhiteCopy('SLTR RofO', $sltrNumber, Auth::id());

        // Handed over as a PDF, the same way the Land RofO does it. The letter is a
        // browser print template (security paper background, border image, flexbox)
        // that DomPDF cannot lay out, so Chrome renders the page exactly as the
        // browser would. Without Chrome on the server it opens for printing as before.
        $renderer = app(\App\Services\ChromePdfRenderer::class);
        if ($renderer->available()) {
            $safeFileRef = preg_replace('/[^A-Z0-9._-]+/i', '-', (string) $sltrNumber) ?: 'SLTR-ROFO';

            try {
                return $renderer->download(
                    $renderer->render($view->render(), $request->root()),
                    'SLTR-RofO-White-Copy-' . $safeFileRef . '.pdf'
                );
            } catch (\Throwable $e) {
                Log::warning('SLTR RofO white copy PDF failed - serving the print page instead', [
                    'id'    => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $view;
    }

    public function print(Request $request, $id, bool $whiteCopy = false)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        if ($recommendation->rofo_status !== SltrRecommendation::ROFO_GENERATED) {
            abort(403, 'ROFO must be generated before printing.');
        }

        $isWhiteCopy = $whiteCopy;

        return view('sltr_rofos.templates.rofo_print', compact('recommendation', 'isWhiteCopy'));
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $rec = SltrRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $serial = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)->first();

            if (($serial->status ?? null) === 'voided') {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'That security paper was voided (' . SecurityPaperCodeService::label($serial->void_reason ?? null) . ') and cannot be reissued.'], 422);
            }

            if ($serial->is_used) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            if ($rec->sltr_rofo_serial_no) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $rec->sltr_rofo_serial_no)
                    ->update(['is_used' => false, 'assigned_to_type' => null, 'assigned_to_id' => null, 'assigned_by' => null, 'assigned_at' => null]);
            }

            $rec->update(['sltr_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update(['is_used' => true, 'assigned_to_type' => 'SltrRecommendation', 'assigned_to_id' => $rec->id, 'assigned_by' => Auth::id(), 'assigned_at' => now()]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function resetSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(SecurityPaperCodeService::REASONS))],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $rec = SltrRecommendation::findOrFail($id);

        if (!$rec->sltr_rofo_serial_no) {
            return response()->json(['success' => false, 'message' => 'No security paper code assigned to reset.'], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $oldCode = $rec->sltr_rofo_serial_no;

            SecurityPaperCodeService::release($oldCode, $request->reason, 'SLTR ROFO', $request->note);

            $rec->update(['sltr_rofo_serial_no' => null]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'          => true,
                'returned_to_pool' => SecurityPaperCodeService::returnsToPool($request->reason),
                'paper_code'       => $oldCode,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to reset security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        $recommendation->increment('rofo_print_count');

        return response()->json(['success' => true]);
    }

    /**
     * MASTER DELETE — un-issue an SLTR RofO.
     *
     * The recommendation SURVIVES, approved, and can be generated again. What goes
     * is the issuance: the generated status and its dates, the surveyor flags, the
     * date of issue, the print history and the PRA transaction, plus the security
     * paper — released back to the pool if unused, retired if it has already been
     * through a printer.
     *
     * To erase the recommendation as well, use the Master Delete on the SLTR
     * Recommendation screen — that one takes this with it.
     */
    public function masterDestroy(Request $request, $id)
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $rec = SltrRecommendation::find($id);
        if (!$rec) {
            return response()->json(['success' => false, 'message' => 'RofO record not found.'], 404);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $rec->sltr_number)) {
            return $deny;
        }

        $snapshot = $rec->toArray();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = app(RofoRecommendationPurgeService::class)->purgeSltrRofo($rec);

            DB::connection('sqlsrv')->commit();

            $this->logMasterDelete(
                'sltr_rofo',
                $rec->id,
                $snapshot,
                $counts,
                'SLTR RofO ' . $rec->sltr_number
            );

            return response()->json([
                'success' => true,
                'message' => 'RofO for ' . $rec->sltr_number . ' deleted. The recommendation remains and can be generated again.',
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('SLTR RofO master delete failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting RofO: ' . $e->getMessage(),
            ], 500);
        }
    }
}
