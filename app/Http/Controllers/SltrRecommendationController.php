<?php

namespace App\Http\Controllers;

use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\PrintLog;
use App\Models\SltrRecommendation;
use App\Support\GroundRentUnit;
use App\Models\StreetName;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Http\Request;
use App\Rules\NigerianPhone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Support\SltrApproval;
use App\Services\RofoRecommendationPurgeService;
use Illuminate\Support\Facades\Log;

class SltrRecommendationController extends Controller
{
    use ExecutesMasterDelete;

    /**
     * How many recommendations one batch approval may carry.
     *
     * Each row costs a PRA round trip, so this is a ceiling on how long the
     * request can run, not a policy limit. Raise it only with the timeout in mind.
     */
    public const MAX_BATCH_APPROVE = 100;

    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%")
                  ->orWhere('lga', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total'    => SltrRecommendation::count(),
            'pending'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_PENDING)->count(),
            'approved' => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'rofo_generated' => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
        ];

        $PageTitle   = 'SLTR Recommendations';
        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('StatLGAs')
                         ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
                         ->where('States.StateName', 'Kano')
                         ->orderBy('LGAName')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $streetOptions = $streetNames->merge([(object)['name' => 'Other']]);
        $landUseOptions = LandUse::orderBy('landuse')->get();
        $purposeOptions = Purpose::orderBy('name')->get();

        $canApprove = $this->userCanApprove();

        // The Open Approval switch, and whether this user may work it. Both are
        // for the header control -- $canApprove above already reflects the switch.
        $approvalOpen      = SltrApproval::isOpen();
        $canManageApproval = SltrApproval::isSupperAdmin(Auth::user());

        // Which rows have had their proof run off: the official print opens on the
        // strength of it, and the White Copy closes with it.
        $whiteCopyDone = array_flip(PrintLog::whiteCopyPrinted(
            'SLTR Recommendation',
            $recommendations->getCollection()->pluck('sltr_number')->filter()->all()
        ));

        // The whole SLTR journey, Recommendation -> Original, shown as a plain rail (no
        // counts, no links) on the page and on the form — just the stage list, no queries.
        $workflowStages = \App\Services\Cofo\SltrCofoPipeline::RECOMMENDATION_STAGE
            + \App\Services\Cofo\SltrCofoPipeline::STAGES;

        return view('sltr_recommendations.index', compact(
            'recommendations', 'stats', 'PageTitle',
            'states', 'lgas', 'districts', 'streetOptions',
            'landUseOptions', 'purposeOptions', 'canApprove', 'whiteCopyDone',
            'approvalOpen', 'canManageApproval', 'workflowStages'
        ));
    }

    /**
     * Delegated to SltrApproval so both SLTR screens answer this the same way:
     * a Supper Admin, the Director SLTR, the Deputy Director SLTR, or -- while
     * Open Approval is switched on -- anyone at all.
     */
    private function userCanApprove(): bool
    {
        $user = Auth::user();

        /*
         | Two separate questions, and both have to be yes.
         |
         | SltrApproval answers WHO HOLDS THE OFFICE — the Director, his Deputy, or anyone
         | while Open Approval is switched on. That is a domain rule about posts.
         |
         | canDo() answers WHETHER THIS ACCOUNT WAS GRANTED APPROVAL on the module at all,
         | which is set per user on the permission grid. Holding the rank does not imply the
         | grant, and holding the grant does not make someone the Director.
         |
         | Non-breaking: can_approve was seeded from can_edit, so everyone who could approve
         | here before still can. It is narrowing it that is now possible.
        */
        return SltrApproval::allows($user)
            && $user !== null
            && $user->canDo('SLTR - Approvals', 'approve');
    }

    /**
     * Turn Open Approval on or off.
     *
     * While it is on, anyone who can reach the screen can approve, not just the
     * Director and his Deputy -- the department's release valve for when neither
     * officer is available. Only a Supper Admin may work the switch, and
     * the check is here rather than on the route because the button that calls it
     * is only rendered for them.
     */
    public function setApprovalMode(Request $request)
    {
        if (!SltrApproval::isSupperAdmin(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Only a Supper Admin may change who can approve.',
            ], 403);
        }

        $validated = $request->validate([
            'open' => 'required|boolean',
        ]);

        $open = (bool) $validated['open'];
        SltrApproval::setOpen($open);

        Log::info('SLTR Open Approval switched', [
            'open'    => $open,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'open'    => $open,
            'message' => $open
                ? 'Open Approval is ON -- anyone who can open this screen can now approve.'
                : 'Open Approval is OFF -- only the Director SLTR and his Deputy can approve.',
        ]);
    }

    public function checkFileNumber(Request $request)
    {
        $fileNumber = $request->input('file_number');
        $excludeId  = $request->input('exclude_id');

        if (!$fileNumber) {
            return response()->json(['exists' => false]);
        }

        $query = SltrRecommendation::where('sltr_number', $fileNumber);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->select('id', 'applicant_name', 'sltr_number', 'status')->first();

        return response()->json([
            'exists' => (bool) $existing,
            'record' => $existing,
        ]);
    }


    /**
     * Fold a typed purpose down onto the column that prints.
     *
     * The form offers a fixed list plus "Other" and posts the free text in its
     * own field. Only the resolved words are stored, so every screen, print and
     * export reads one column and none of them has to know the purpose came from
     * the Other box. "Other" with nothing typed stores nothing rather than the
     * literal word, which would print on the letter as a purpose.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolvePurposeClause(array $data): array
    {
        if (($data['purpose_of_clause'] ?? null) === 'Other') {
            $typed = trim((string) ($data['purpose_of_clause_other'] ?? ''));
            $data['purpose_of_clause'] = $typed !== '' ? $typed : null;
        }

        unset($data['purpose_of_clause_other']);

        return $data;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sqlsrv.sltr_recommendations', 'sltr_number')->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'applicant_phone'  => ['required', 'string', new NigerianPhone()],
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'revision_period'  => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'ground_rent_unit' => 'nullable|string|max:100',
            'ground_rent_unit_other' => 'nullable|string|max:100',
            'processing_fee'   => 'nullable|numeric|min:0',
            // The schedule line the fee came from, or the sub-type named for "Other".
            'land_use_subtype' => 'nullable|string|max:255',
            'purpose_of_clause'=> 'nullable|string|max:300',
            // The words typed when the schedule has no matching purpose. Never
            // stored on its own — resolvePurposeClause() folds it into the
            // column above, so nothing downstream reads two fields for one idea.
            'purpose_of_clause_other' => 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        // "Other" becomes the words the officer typed; only the resolved unit is stored.
        $validated = GroundRentUnit::resolve($validated);
        $validated = $this->resolvePurposeClause($validated);

        $rec = SltrRecommendation::create(array_merge($validated, [
            'status'     => SltrRecommendation::STATUS_PENDING,
            'rofo_status'=> SltrRecommendation::ROFO_PENDING,
            'created_by' => Auth::id(),
        ]));

        return response()->json(['success' => true, 'message' => 'Recommendation created.', 'data' => $rec]);
    }

    public function update(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sqlsrv.sltr_recommendations', 'sltr_number')->ignore($rec->id)->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'applicant_phone'  => ['required', 'string', new NigerianPhone()],
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'revision_period'  => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'ground_rent_unit' => 'nullable|string|max:100',
            'ground_rent_unit_other' => 'nullable|string|max:100',
            'processing_fee'   => 'nullable|numeric|min:0',
            // The schedule line the fee came from, or the sub-type named for "Other".
            'land_use_subtype' => 'nullable|string|max:255',
            'purpose_of_clause'=> 'nullable|string|max:300',
            // The words typed when the schedule has no matching purpose. Never
            // stored on its own — resolvePurposeClause() folds it into the
            // column above, so nothing downstream reads two fields for one idea.
            'purpose_of_clause_other' => 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        $validated = GroundRentUnit::resolve($validated);
        $validated = $this->resolvePurposeClause($validated);

        $rec->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return response()->json(['success' => true, 'message' => 'Recommendation updated.', 'data' => $rec]);
    }

    public function destroy($id)
    {
        $rec = SltrRecommendation::findOrFail($id);
        $rec->delete();

        return response()->json(['success' => true, 'message' => 'Recommendation deleted.']);
    }

    public function approve(Request $request, $id)
    {
        if (!$this->userCanApprove()) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to approve recommendations.'], 403);
        }

        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status === SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Already approved.'], 422);
        }

        $rec->update([
            'status'           => SltrRecommendation::STATUS_APPROVED,
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rofo_status'      => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at'=> now(),
            'updated_by'       => Auth::id(),
        ]);

        app(RofoPraSyncer::class)->syncSltr($rec->fresh());

        return response()->json(['success' => true, 'message' => 'Recommendation approved.']);
    }

    /**
     * Approve a set of recommendations in one pass.
     *
     * Does per record exactly what approve() does on its own -- including pushing
     * rofo_status straight to generated and syncing PRA -- so a file approved here
     * is indistinguishable from one approved singly. Capped at MAX_BATCH_APPROVE:
     * every row costs a PRA round trip, and an unbounded list would sit on the
     * request until it timed out halfway through, leaving the tail unapproved with
     * nothing to say which.
     *
     * Rows that are already approved are skipped rather than refused -- a stale tick
     * box on a page someone else has worked is not the operator's mistake, and the
     * rest of their selection should still go through. The response says how many
     * of each so the caller can report it honestly.
     */
    public function batchApprove(Request $request)
    {
        if (!$this->userCanApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to approve recommendations.',
            ], 403);
        }

        $validated = $request->validate([
            'ids'   => 'required|array|min:1|max:' . self::MAX_BATCH_APPROVE,
            'ids.*' => 'integer',
        ]);

        $records = SltrRecommendation::whereIn('id', $validated['ids'])->get();

        $approved = 0;
        $skipped  = 0;
        $failed   = [];

        $syncer = app(RofoPraSyncer::class);

        foreach ($records as $rec) {
            if ($rec->status === SltrRecommendation::STATUS_APPROVED) {
                $skipped++;
                continue;
            }

            try {
                $rec->update([
                    'status'           => SltrRecommendation::STATUS_APPROVED,
                    'approved_at'      => now(),
                    'approved_by'      => Auth::id(),
                    'rofo_status'      => SltrRecommendation::ROFO_GENERATED,
                    'rofo_generated_at'=> now(),
                    'updated_by'       => Auth::id(),
                ]);

                $approved++;

                // One record's PRA sync failing must not strand the rest of the
                // batch: the approval above is already committed, and the syncer
                // skips a file it has already written, so this is safe to re-run.
                $syncer->syncSltr($rec->fresh());
            } catch (\Throwable $e) {
                $failed[] = $rec->sltr_number ?: $rec->id;

                Log::warning('SLTR batch approve: one record failed', [
                    'id'    => $rec->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = $approved . ' recommendation' . ($approved === 1 ? '' : 's') . ' approved.';
        if ($skipped) {
            $message .= ' ' . $skipped . ' already approved.';
        }
        if ($failed) {
            $message .= ' ' . count($failed) . ' failed: ' . implode(', ', array_slice($failed, 0, 5)) . '.';
        }

        return response()->json([
            'success'  => true,
            'approved' => $approved,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'message'  => $message,
        ]);
    }

    public function show($id)
    {
        $rec = SltrRecommendation::with('creator', 'approver')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $rec]);
    }

    /**
     * The White Copy: a black & white proof of the SLTR recommendation, read against
     * the record before an official copy is run off.
     *
     * The same record through the same template with every mark of an issued
     * document taken off — the serial, the signature blocks, the acknowledgement
     * sheet — and marked WHITE COPY instead.
     *
     * Crucially it does NOT stamp printed_at. That column is what switches this
     * record's action from "Print" to "View Recommendation", so a proof that set it
     * would close the official print behind a document nobody has issued. No serial
     * is minted either — this template mints it as it renders, so a preview alone
     * would have spent one.
     */
    public function printWhiteCopy(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);
        $isWhiteCopy = true;

        PrintLog::logWhiteCopy('SLTR Recommendation', $recommendation->sltr_number, Auth::id());

        $view = view('sltr_recommendations.templates.recommendation_print', compact('recommendation', 'isWhiteCopy'));

        // Handed over as a PDF, the same way the Land recommendation does it. These
        // letters are browser print templates (flexbox, letterheads) that DomPDF
        // cannot lay out, so Chrome renders the page exactly as the browser would.
        // Without Chrome on the server it opens for printing as before.
        $renderer = app(\App\Services\ChromePdfRenderer::class);
        if ($renderer->available()) {
            $safeFileRef = preg_replace('/[^A-Z0-9._-]+/i', '-', (string) $recommendation->sltr_number)
                ?: 'SLTR-RECOMMENDATION';

            try {
                return $renderer->download(
                    $renderer->render($view->render(), $request->root()),
                    'SLTR-Recommendation-White-Copy-' . $safeFileRef . '.pdf'
                );
            } catch (\Throwable $e) {
                Log::warning('SLTR recommendation white copy PDF failed - serving the print page instead', [
                    'id'    => $recommendation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $view;
    }

    public function printRecommendation($id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        // Mark the recommendation as printed the first time its print view is opened
        // so the listing can switch the "Print" action to "View Recommendation".
        if (!$recommendation->printed_at) {
            $recommendation->update(['printed_at' => now()]);
        }

        return view('sltr_recommendations.templates.recommendation_print', compact('recommendation'));
    }

    /**
     * MASTER DELETE — erase an SLTR recommendation from every table it reached.
     *
     * Destructive and irreversible. On SLTR the recommendation record IS the RofO
     * record, so this takes both: the row, its PRA transaction, its security paper
     * (back to the pool, or retired if the sheet has been printed), its
     * `security_codes` tracking row and its print history.
     *
     * To undo only the issuance and keep the approved recommendation, use the
     * Master Delete on the SLTR RofO screen instead.
     */
    public function masterDestroy(Request $request, $id)
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $rec = SltrRecommendation::find($id);
        if (!$rec) {
            return response()->json(['success' => false, 'message' => 'Recommendation not found.'], 404);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $rec->sltr_number)) {
            return $deny;
        }

        $snapshot = $rec->toArray();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = app(RofoRecommendationPurgeService::class)->purgeSltrRecommendation($rec);

            DB::connection('sqlsrv')->commit();

            $this->logMasterDelete(
                'sltr_recommendation',
                $rec->id,
                $snapshot,
                $counts,
                'SLTR Recommendation ' . $rec->sltr_number
            );

            return response()->json([
                'success' => true,
                'message' => 'Recommendation ' . $rec->sltr_number . ' deleted from all tables.',
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('SLTR recommendation master delete failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting recommendation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
