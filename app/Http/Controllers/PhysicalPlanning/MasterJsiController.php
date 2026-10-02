<?php

namespace App\Http\Controllers\PhysicalPlanning;

use App\Http\Controllers\Controller;
use App\Models\LandUse;
use App\Models\MasterJsiEvidence;
use App\Models\MasterJsiExtensionPortion;
use App\Models\MasterJsiMergerProperty;
use App\Models\MasterJsiParticipant;
use App\Models\MasterJsiPortion;
use App\Models\MasterJsiPurpose;
use App\Models\MasterJsiPurposeChange;
use App\Models\MasterJsiReport;
use App\Models\MasterJsiSubdivisionPlot;
use App\Services\FileIndexingCoordinateBackfillService;
use App\Services\ParcelUpdateNotificationService;
use App\Support\MasterJsiPortionTemplates;
use App\Support\MasterJsiSubjectResolver;
use App\Support\ParcelSizeSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The Master JSI register — Physical Planning site observations for parcel updates.
 *
 * One page and one form for both categories (SPU, the five single workflows; APU,
 * the duplex) and all five sheets. The layout is chosen by parcel_update_type from
 * MasterJsiPortionTemplates, so nothing here branches per sheet.
 *
 * The report walks draft -> generated -> submitted -> approved, and an approved
 * report is the planning clearance the parcel update needs — the question is asked
 * through MasterJsiGate, never by reading status from a listing.
 *
 * Sending to Deeds is a stamp and a notification, nothing else. It follows
 * DuplexParcelUpdateController::sendToLand() deliberately: no registry writes
 * happen on a handover.
 */
class MasterJsiController extends Controller
{
    public function __construct(private ParcelUpdateNotificationService $parcelNotifier)
    {
    }

    /** The register. */
    public function index(Request $request): View
    {
        $query = MasterJsiReport::visible();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($type = $request->query('type')) {
            $query->where('parcel_update_type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('jsi_ref', 'LIKE', "%{$search}%")
                    ->orWhere('file_number', 'LIKE', "%{$search}%")
                    ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                    ->orWhere('file_title', 'LIKE', "%{$search}%");
            });
        }

        $records = $query->orderByDesc('id')->paginate(25)->withQueryString();

        // Counted off the whole register, not the filtered page: the tiles say what
        // the office is holding, and a tile that moved because someone typed in the
        // search box would be read as work appearing and disappearing.
        $counts = MasterJsiReport::visible()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('physical_planning.master_jsi.index', [
            'records' => $records,
            'stats'   => [
                'total'     => (int) $counts->sum(),
                'draft'     => (int) $counts->get(MasterJsiReport::STATUS_DRAFT, 0),
                'generated' => (int) $counts->get(MasterJsiReport::STATUS_GENERATED, 0),
                'submitted' => (int) $counts->get(MasterJsiReport::STATUS_SUBMITTED, 0),
                'approved'  => (int) $counts->get(MasterJsiReport::STATUS_APPROVED, 0),
            ],
            'filters'         => $request->only(['category', 'type', 'status', 'search']),
            'PageTitle'       => 'Master JSI',
            'PageDescription' => 'Physical Planning site observations for SPU and APU parcel updates.',
        ]);
    }

    /**
     * The capture form.
     *
     * Opens blank from the register, or pre-filled when a parcel-update listing sent
     * the officer here through its action menu (?subject_type=&subject_id=).
     */
    public function create(Request $request): View
    {
        $subjectType = $request->query('subject_type');
        $subjectId   = $request->query('subject_id');

        $record = MasterJsiSubjectResolver::find($subjectType, $subjectId);

        // A parcel update already knows which kind of update it is; only a report
        // started from the register asks.
        $type = $request->query('type')
            ?: ($subjectType && $subjectType !== 'duplex' ? $subjectType : null);

        return view('physical_planning.master_jsi.form', [
            'report'       => null,
            'subjectType'  => $subjectType,
            'subjectId'    => $subjectId ? (int) $subjectId : null,
            'category'     => $request->query('category')
                ?: ($subjectType ? MasterJsiSubjectResolver::categoryFor($subjectType) : null),
            'parcelUpdateType' => $type,
            'prefill'      => MasterJsiSubjectResolver::prefill($subjectType, $record),
            'districts'    => $this->districts(),
            'lgas'         => $this->lgas(),
            'landUses'     => $this->landUses(),
            'returnUrl'    => $request->query('return') ?: route('master-jsi.index'),
            'PageTitle'    => 'New Master JSI',
        ]);
    }

    /**
     * The configured land-use register, for the Change of Purpose section.
     *
     * land_uses is the registry list every selector in the system reads; a change
     * of purpose picks BOTH values from it, so the form never invents a list.
     */
    private function landUses()
    {
        return LandUse::orderBy('landuse')->get(['id', 'landuse']);
    }

    /**
     * The district list behind the dropdown.
     *
     * One select on this page, so the full list is fine — the OOM this codebase hit
     * before came from ~23 duplicated district selects on one screen, not from the
     * 1,800 options themselves.
     */
    private function districts()
    {
        return DB::connection('sqlsrv')->table('districts')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['name']);
    }

    /**
     * The LGA list behind the dropdown — the canonical Kano lgas reference table.
     */
    private function lgas()
    {
        return DB::connection('sqlsrv')->table('lgas')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['name']);
    }

    public function edit(int $id, Request $request): View
    {
        $report = MasterJsiReport::visible()
            ->with([
                'portions',
                'purposes',
                'participants',
                'mergerProperties',
                'extensionPortions',
                'subdivisionPlots',
                'purposeChanges',
            ])
            ->findOrFail($id);

        return view('physical_planning.master_jsi.form', [
            'report'           => $report,
            'subjectType'      => $report->subject_type,
            'subjectId'        => $report->subject_id ? (int) $report->subject_id : null,
            'category'         => $report->category,
            'parcelUpdateType' => $report->parcel_update_type,
            'prefill'          => [],
            'districts'        => $this->districts(),
            'lgas'             => $this->lgas(),
            'landUses'         => $this->landUses(),
            'returnUrl'        => $request->query('return') ?: route('master-jsi.index'),
            'PageTitle'        => 'Master JSI ' . $report->jsi_ref,
        ]);
    }

    public function show(int $id): View
    {
        $report = MasterJsiReport::visible()
            ->with([
                'portions',
                'purposes',
                'participants',
                'mergerProperties',
                'extensionPortions',
                'subdivisionPlots',
                'purposeChanges',
                'evidence',
            ])
            ->findOrFail($id);

        return view('physical_planning.master_jsi.show', [
            'report'    => $report,
            'subject'   => MasterJsiSubjectResolver::find($report->subject_type, $report->subject_id),
            'landUses'  => $this->landUses(),
            'PageTitle' => 'Master JSI ' . $report->jsi_ref,
        ]);
    }

    /** Create or update — the form posts the whole sheet either way. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $report = DB::connection('sqlsrv')->transaction(function () use ($data, $request) {
            $report = MasterJsiReport::create(array_merge($this->reportAttributes($data), [
                'jsi_ref'    => MasterJsiReport::allocateRef(),
                'status'     => MasterJsiReport::STATUS_DRAFT,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]));

            $this->syncChildren($report, $data);

            return $report;
        });

        Log::info('Master JSI captured', [
            'jsi_ref'      => $report->jsi_ref,
            'category'     => $report->category,
            'type'         => $report->parcel_update_type,
            'subject_type' => $report->subject_type,
            'subject_id'   => $report->subject_id,
            'user_id'      => Auth::id(),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Master JSI ' . $report->jsi_ref . ' saved.',
            'id'       => $report->id,
            'jsi_ref'  => $report->jsi_ref,
            'redirect' => route('master-jsi.show', $report->id),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        // An approved report is the clearance the recommendation was drawn on, so
        // it is not edited underneath that — it is rejected and captured again.
        if ($report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI cannot be edited. Reject it first if the observation was wrong.',
            ], 422);
        }

        $data = $this->validated($request);

        DB::connection('sqlsrv')->transaction(function () use ($report, $data, $request) {
            $report->update(array_merge($this->reportAttributes($data), [
                'updated_by' => Auth::id(),
            ]));

            $this->syncChildren($report, $data);
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Master JSI ' . $report->jsi_ref . ' updated.',
            'id'       => $report->id,
            'redirect' => route('master-jsi.show', $report->id),
        ]);
    }

    /** The sheet is drawn — from here it can be printed and sent up. */
    public function generate(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->with(['portions', 'purposes'])->findOrFail($id);

        if (($gate = $this->generateBlocker($report)) !== null) {
            return response()->json(['success' => false, 'message' => $gate], 422);
        }

        $report->update([
            'status'       => MasterJsiReport::STATUS_GENERATED,
            'generated_at' => now(),
            'generated_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI generated.']);
    }

    /**
     * Why a sheet cannot be generated yet, or null when it can.
     *
     * A legacy record has measurement rows on the sheet; a new-template record has
     * purpose children. Both must carry at least one recorded measurement of some
     * kind before the printed sheet is worth signing.
     */
    private function generateBlocker(MasterJsiReport $report): ?string
    {
        // New-template record: there is a purpose, and its section must be fleshed
        // out enough to print.
        if ($report->purposes->isNotEmpty()) {
            $slugs = $report->purposeSlugs();

            if ($report->wantsSubdivisionSection()
                && $report->subdivisionPlots->isEmpty()
                && !$report->site_recommended_area_sqm) {
                return 'Record the subdivision plots or the recommended site measurement before generating the sheet.';
            }

            if (in_array('merger', $slugs, true) && $report->mergerProperties->count() < 2) {
                return 'Record at least two properties to merge before generating the sheet.';
            }

            if (in_array('extension', $slugs, true) && $report->extensionPortions->isEmpty()) {
                return 'Record at least one extension portion before generating the sheet.';
            }

            if (in_array('change_of_purpose', $slugs, true) && $report->purposeChanges->isEmpty()) {
                return 'Record at least one change of purpose before generating the sheet.';
            }

            return null;
        }

        if ($report->portions->isEmpty()) {
            return 'Record the measurements before generating the sheet.';
        }

        return null;
    }

    public function submit(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()
            ->with(['portions', 'purposes', 'mergerProperties', 'extensionPortions', 'subdivisionPlots', 'purposeChanges'])
            ->findOrFail($id);

        if (!$report->generated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Generate the sheet before submitting it for approval.',
            ], 422);
        }

        if (($gate = $this->submitBlocker($report)) !== null) {
            return response()->json(['success' => false, 'message' => $gate], 422);
        }

        $report->update([
            'status'       => MasterJsiReport::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI submitted for approval.']);
    }

    /**
     * Why a generated sheet cannot yet be submitted, or null when it can.
     *
     * Generation only requires enough to print; submission requires the inspection
     * itself to be complete — each selected purpose must have its observations.
     */
    private function submitBlocker(MasterJsiReport $report): ?string
    {
        if ($report->purposes->isEmpty()) {
            return null;
        }

        $slugs = $report->purposeSlugs();

        if ($report->wantsSubdivisionSection() && $report->subdivisionPlots->isEmpty()) {
            return 'Record the subdivision plots you inspected before submitting.';
        }

        if (in_array('merger', $slugs, true) && $report->mergerProperties->count() < 2) {
            return 'Record at least two distinct properties being merged before submitting.';
        }

        if (in_array('extension', $slugs, true) && $report->extensionPortions->isEmpty()) {
            return 'Record at least one extension portion before submitting.';
        }

        if (in_array('change_of_purpose', $slugs, true) && $report->purposeChanges->isEmpty()) {
            return 'Record at least one change of purpose before submitting.';
        }

        $unnamed = $report->participants
            ->where('participant', MasterJsiParticipant::PARTICIPANT_APPLICANT_REP)
            ->filter(fn ($participant) => blank($participant->name));

        if ($unnamed->isNotEmpty()) {
            return 'Every applicant representative listed needs a name.';
        }

        return null;
    }

    /**
     * The approval that opens the parcel update's recommendation.
     *
     * This is the answer MasterJsiGate reads, so it is the moment Generate
     * Recommendation and Approve unlock on the parcel-update listing.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Submit the Master JSI before approving it.',
            ], 422);
        }

        $report->update([
            'status'             => MasterJsiReport::STATUS_APPROVED,
            'approved_at'        => now(),
            'approved_by'        => Auth::id(),
            'rejected_reason'    => null,
            'supervisor_decision' => 'approved',
            'supervisor_remarks' => $request->input('supervisor_remarks')
                ? trim((string) $request->input('supervisor_remarks')) : null,
            'updated_by'         => Auth::id(),
        ]);

        Log::info('Master JSI approved', [
            'jsi_ref'      => $report->jsi_ref,
            'subject_type' => $report->subject_type,
            'subject_id'   => $report->subject_id,
            'user_id'      => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master JSI approved. The parcel update can now be recommended.',
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $report->update([
            'status'             => MasterJsiReport::STATUS_REJECTED,
            'rejected_reason'    => $reason ?: 'Rejected',
            'supervisor_decision' => 'rejected',
            'supervisor_remarks' => $request->input('supervisor_remarks')
                ? trim((string) $request->input('supervisor_remarks')) : null,
            'updated_by'         => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI rejected.']);
    }

    /**
     * Return the sheet to the officer for rework.
     *
     * A returned sheet is not a rejection — it goes back to the generated/draft pool
     * so the officer can edit, resubmit and get a fresh decision. The stamp survives
     * as supervisor_decision='returned' beside returned_at/by, and the clearance
     * stays with the officer's latest submission.
     */
    public function returnReport(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Only a submitted Master JSI can be returned.',
            ], 422);
        }

        $report->update([
            'status'              => MasterJsiReport::STATUS_GENERATED,
            'submitted_at'        => null,
            'submitted_by'        => null,
            'supervisor_decision' => 'returned',
            'supervisor_remarks'  => trim((string) $request->input('supervisor_remarks')) ?: null,
            'returned_at'         => now(),
            'returned_by'         => Auth::id(),
            'updated_by'          => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master JSI returned to the officer for correction.',
        ]);
    }

    // ---------------------------------------------------------------------
    // Evidence on the inspection record.
    // ---------------------------------------------------------------------

    /** Attach a photograph, plan, sketch or coordinate to the inspection. */
    public function storeEvidence(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if ($report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is closed — it cannot take more attachments.',
            ], 422);
        }

        $validated = $request->validate([
            'kind'        => 'required|in:' . implode(',', array_keys(MasterJsiEvidence::KINDS)),
            'description' => 'nullable|string|max:500',
            'value'       => 'nullable|string|max:1000',
            'file'        => 'nullable|file|max:10240|mimes:png,jpg,jpeg,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt',
        ]);

        if (MasterJsiEvidence::KIND_COORDINATES === $validated['kind'] && blank($validated['value'])) {
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record the location coordinates, or attach a file.',
                ], 422);
            }
        }

        $path = null;
        $fileName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $path = $file->storeAs('parcel_documents/master_jsi', $report->id . '_' . time() . '_' . $fileName, 'public');
        }

        $evidence = MasterJsiEvidence::create([
            'master_jsi_report_id' => $report->id,
            'kind'                 => $validated['kind'],
            'file_path'            => $path,
            'file_name'            => $fileName,
            'description'          => $validated['description'] ?: null,
            'value'                => $validated['value'] ?: null,
            'sequence'             => $report->evidence->count() + 1,
            'created_by'           => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => MasterJsiEvidence::KINDS[$evidence->kind] . ' attached.',
            'id'      => $evidence->id,
        ]);
    }

    /** Remove an attachment and its file — only while the inspection is open. */
    public function destroyEvidence(int $evidenceId): JsonResponse
    {
        $evidence = MasterJsiEvidence::findOrFail($evidenceId);
        $report   = $evidence->report()->first();

        if ($report === null || $report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'The inspection it belongs to is closed.',
            ], 422);
        }

        if ($evidence->file_path) {
            Storage::disk('public')->delete($evidence->file_path);
        }

        $evidence->delete();

        return response()->json(['success' => true, 'message' => 'Attachment removed.']);
    }

    /**
     * Hand the approved inspection to Deeds. A stamp and a notification — no
     * registry writes, exactly as the duplex hands itself to Land.
     */
    public function sendToDeeds(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Only an approved Master JSI can be sent to Deeds.',
            ], 422);
        }

        if ($report->sentToDeeds()) {
            return response()->json([
                'success' => false,
                'message' => 'This Master JSI was already sent to Deeds on '
                    . $report->sent_to_deeds_at->format('d M Y') . '.',
            ], 422);
        }

        $report->update([
            'sent_to_deeds_at' => now(),
            'sent_to_deeds_by' => Auth::id(),
            'updated_by'       => Auth::id(),
        ]);

        $sender = Auth::user();

        $this->parcelNotifier->notifyJsiSentToDeeds(
            (string) $report->parcel_update_type,
            (int) $report->id,
            (string) $report->jsi_ref,
            (string) $report->file_number,
            (string) $report->file_title,
            $sender ? ($sender->name ?? $sender->username ?? '') : ''
        );

        Log::info('Master JSI sent to Deeds', [
            'jsi_ref' => $report->jsi_ref,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master JSI ' . $report->jsi_ref . ' sent to Deeds.',
        ]);
    }

    /** The printable sheet. */
    public function print(int $id): View
    {
        $report = MasterJsiReport::visible()
            ->with([
                'portions',
                'purposes',
                'participants',
                'mergerProperties',
                'extensionPortions',
                'subdivisionPlots',
                'purposeChanges',
                'evidence',
            ])
            ->findOrFail($id);

        return view('physical_planning.master_jsi.print.report', [
            'report'   => $report,
            'template' => $report->isLegacyLayout()
                ? MasterJsiPortionTemplates::for($report->parcel_update_type)
                : null,
            'landUses' => $this->landUses(),
        ]);
    }

    /** Soft delete, matching the flag every parcel-update table carries. */
    public function destroy(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if ($report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI cannot be deleted.',
            ], 422);
        }

        $report->update([
            'is_deleted' => true,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI deleted.']);
    }

    /**
     * Which parcel-update application a chosen file number belongs to.
     *
     * The form asks for a FILE NUMBER through the global selector — that is what the
     * officer is holding — and this resolves it back to the application so the
     * clearance has something to attach to. Not finding one is a normal answer, not
     * an error: an inspection may be carried out before the application is captured,
     * and the form says so rather than refusing the file number.
     */
    public function resolveSubject(Request $request): JsonResponse
    {
        $fileNumber  = (string) $request->query('file_number');
        $subjectType = (string) $request->query('subject_type');

        if (MasterJsiSubjectResolver::modelClass($subjectType) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Choose the parcel update type first.',
            ], 422);
        }

        $record = MasterJsiSubjectResolver::findByFileNumber($subjectType, $fileNumber);

        if ($record === null) {
            return response()->json([
                'success' => true,
                'found'   => false,
                'message' => 'No ' . (MasterJsiReport::SUBJECTS[$subjectType][1] ?? $subjectType)
                    . ' application found for ' . $fileNumber
                    . '. The inspection will be captured unlinked.',
            ]);
        }

        // The applicant's phone, when the file-indexing record holds one. The
        // parcel-update registers do not all carry a phone column, so the sheet
        // falls back to the indexing row the coordinates endpoint already reads.
        try {
            $applicantPhone = (string) DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $fileNumber)
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->value('phone');
        } catch (\Throwable $e) {
            $applicantPhone = '';
        }

        return response()->json([
            'success'      => true,
            'found'        => true,
            'subject_type' => $subjectType,
            'subject_id'   => $record->id,
            'label'        => MasterJsiSubjectResolver::label($subjectType, $record),
            'prefill'      => MasterJsiSubjectResolver::prefill($subjectType, $record),
            'applicant_phone' => $applicantPhone,
            'message'      => 'Linked to ' . (MasterJsiReport::SUBJECTS[$subjectType][1] ?? $subjectType)
                . ' — ' . MasterJsiSubjectResolver::label($subjectType, $record),
        ]);
    }

    /** The register search, kept for callers that pick a record rather than a file. */
    public function searchSubjects(Request $request): JsonResponse
    {
        $subjectType = (string) $request->query('subject_type');

        if (MasterJsiSubjectResolver::modelClass($subjectType) === null) {
            return response()->json(['success' => false, 'results' => []], 422);
        }

        return response()->json([
            'success' => true,
            'results' => MasterJsiSubjectResolver::search($subjectType, $request->query('q')),
        ]);
    }

    /**
     * Coordinates for the picked file number, so the Location Coordinates field can
     * be backfilled and a pin dropped on the map. Prefers the latitude/longitude
     * stored on the file-indexing row; falls back to geocoding the location parts
     * through Nominatim when the indexing row has none stored.
     */
    public function coordinates(Request $request): JsonResponse
    {
        $result = app(FileIndexingCoordinateBackfillService::class)->coordinates(
            (string) $request->query('file_number'),
            [
                'location' => (string) $request->query('location'),
                'district' => (string) $request->query('district'),
                'lga'      => (string) $request->query('lga'),
            ],
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'No coordinates available for this file number or its location.',
            ]);
        }

        return response()->json([
            'success'   => true,
            'latitude'  => $result['lat'],
            'longitude' => $result['lng'],
            'source'    => $result['source'],
            // App-wide convention: the Location Coordinates label reads longitude first.
            'value'     => number_format($result['lng'], 7) . ', ' . number_format($result['lat'], 7),
        ]);
    }

    // -------------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'           => 'required|in:SPU,APU',
            'parcel_update_type' => 'required|in:' . implode(',', MasterJsiPortionTemplates::types()),
            'subject_type'       => 'nullable|in:' . implode(',', array_keys(MasterJsiSubjectResolver::MODELS)),
            'subject_id'         => 'nullable|integer',
            'duplex_stage_id'    => 'nullable|integer',

            // Required: it is what the sheet is filed against and what resolves the
            // parcel update. subject_id may still be empty — a site can be inspected
            // before the application is captured — but the file number cannot.
            'file_number'    => 'required|string|max:100',
            'file_title'     => 'nullable|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'inspection_date' => 'nullable|date',
            'location'       => 'nullable|string|max:500',
            'plot_number'    => 'nullable|string|max:100',
            'district'       => 'nullable|string|max:255',
            'lga'            => 'nullable|string|max:255',
            'inspection_officer'    => 'nullable|string|max:255',
            'inspection_officer_id' => 'nullable|integer',

            'available_on_ground'  => 'nullable|string|max:50',
            'boundary_segments'    => 'nullable|array',
            'boundary_description' => 'nullable|string',
            'road_reservation'     => 'nullable|string|max:700',
            'conformity'           => 'nullable|boolean',
            'prevailing_land_use'  => 'nullable|string|max:255',
            'existing_land_use'    => 'nullable|string|max:255',
            'recommended_land_use' => 'nullable|string|max:255',
            'existing_purpose'     => 'nullable|string|max:255',
            'recommended_purpose'  => 'nullable|string|max:255',
            'number_of_units'      => 'nullable|integer|min:0',
            'average_size'         => 'nullable|string|max:100',
            'narrative_summary'    => 'nullable|string',
            'additional_observations' => 'nullable|string',

            'portions'                => 'nullable|array|max:60',
            'portions.*.role'         => 'nullable|string|max:40',
            'portions.*.label'        => 'nullable|string|max:255',
            'portions.*.land_use'     => 'nullable|string|max:255',
            'portions.*.unit_count'   => 'nullable|integer|min:0',
            'portions.*.area_sqm'     => 'nullable|numeric|min:0',
            'portions.*.dimensions'   => 'nullable|array|max:24',
            'portions.*.dimensions.*' => 'nullable|numeric|min:0',

            // Site Inspection Template Update — inspection purposes.
            'purposes'    => 'nullable|array|max:5',
            'purposes.*'  => 'nullable|in:' . implode(',', array_keys(MasterJsiPurpose::PURPOSES)),

            // The parties who were physically present at the joint inspection.
            'participants'                  => 'nullable|array|max:25',
            'participants.*.participant'    => 'nullable|in:'
                . MasterJsiParticipant::PARTICIPANT_APPLICANT . ','
                . MasterJsiParticipant::PARTICIPANT_APPLICANT_REP,
            'participants.*.name'           => 'nullable|string|max:255',
            'participants.*.phone'          => 'nullable|string|max:40',
            'participants.*.relationship'   => 'nullable|string|max:255',

            // Merger: every property being merged keeps its own file number.
            'merger_properties'                   => 'nullable|array|max:50',
            'merger_properties.*.property_file_number' => 'nullable|string|max:100',
            'merger_properties.*.plot_number'     => 'nullable|string|max:100',
            'merger_properties.*.area_sqm'        => 'nullable|numeric|min:0',
            'merger_properties.*.unit'            => 'nullable|in:sqm,ha',

            // Extension: number of extensions = number of separate portions.
            'extension_portions'                  => 'nullable|array|max:50',
            'extension_portions.*.extension_number' => 'nullable|string|max:100',
            'extension_portions.*.description'    => 'nullable|string|max:500',
            'extension_portions.*.area_sqm'       => 'nullable|numeric|min:0',
            'extension_portions.*.unit'           => 'nullable|in:sqm,ha',

            // Subdivision: one row per proposed plot.
            'subdivision_plots'                   => 'nullable|array|max:500',
            'subdivision_plots.*.plot_number'     => 'nullable|string|max:100',
            'subdivision_plots.*.area_sqm'        => 'nullable|numeric|min:0',
            'subdivision_plots.*.unit'            => 'nullable|in:sqm,ha',
            'subdivision_plots.*.remarks'         => 'nullable|string|max:500',

            // Change of purpose: both values come from the land-use register.
            'purpose_changes'                        => 'nullable|array|max:50',
            'purpose_changes.*.current_land_use'     => 'nullable|string|max:255',
            'purpose_changes.*.proposed_land_use'    => 'nullable|string|max:255',
            'purpose_changes.*.area_sqm'             => 'nullable|numeric|min:0',
            'purpose_changes.*.unit'                 => 'nullable|in:sqm,ha',
            'purpose_changes.*.remarks'              => 'nullable|string|max:500',

            // Site / land measurement.
            'site_unit'                 => 'nullable|in:sqm,ha',
            'site_existing_area_sqm'    => 'nullable|numeric|min:0',
            'site_existing_dimensions'  => 'nullable|string|max:255',
            'site_recommended_area_sqm' => 'nullable|numeric|min:0',
            'recommended_total_override_sqm' => 'nullable|numeric|min:0',
            'override_reason'           => 'required_with:recommended_total_override_sqm|nullable|string|max:500',
            'proposed_merged_plot_number' => 'nullable|string|max:100',
            'recommended_merged_area_sqm' => 'nullable|numeric|min:0',
            'merger_remarks'            => 'nullable|string',
            'extension_remarks'         => 'nullable|string',
            'location_coordinates'      => 'nullable|string|max:500',

            // Findings of the joint inspection.
            'finding_site_suitable'     => 'nullable|boolean',
            'finding_site_accessible'   => 'nullable|boolean',
            'development_status'        => 'nullable|in:developed,partially_developed,undeveloped',
            'officer_recommendation'    => 'nullable|string',
            'officer_designation'       => 'nullable|string|max:255',
            'further_directions'        => 'nullable|string',
        ], $this->purposeCrossChecks($request));
    }

    /**
     * Cross-field checks that only apply once a purpose is actually selected.
     *
     * The form may capture bits of a section before it is complete, but what is
     * posted must always read back as the observation recorded:
     *   merger        → two or more distinct properties being merged,
     *   reps          → a representative always has a name,
     *   change of purpose → the proposed use is never the current one.
     */
    private function purposeCrossChecks(Request $request): array
    {
        return ['after' => function ($validator) use ($request) {
            $purposes = $request->input('purposes', []);

            if (in_array('merger', $purposes, true)) {
                $properties = collect($request->input('merger_properties', []))
                    ->filter(fn ($row) => !blank($row['property_file_number'] ?? null))
                    ->pluck('property_file_number')
                    ->unique()
                    ->values();

                if ($properties->count() >= 1 && $properties->count() < 2) {
                    $validator->errors()->add(
                        'merger_properties',
                        'A merger takes two or more distinct properties.'
                    );
                }
            }

            foreach ((array) $request->input('participants', []) as $index => $participant) {
                if (($participant['participant'] ?? null) === MasterJsiParticipant::PARTICIPANT_APPLICANT_REP
                    && blank($participant['name'] ?? null)) {
                    $validator->errors()->add(
                        "participants.$index.name",
                        'An applicant representative always needs a name.'
                    );
                }
            }

            foreach ((array) $request->input('purpose_changes', []) as $index => $change) {
                if (!blank($change['current_land_use'] ?? null)
                    && (string) ($change['current_land_use'] ?? '') === (string) ($change['proposed_land_use'] ?? '')) {
                    $validator->errors()->add(
                        "purpose_changes.$index.proposed_land_use",
                        'The proposed land use must differ from the current one.'
                    );
                }
            }
        }];
    }

    /** The report columns, with the two derived ones resolved. */
    private function reportAttributes(array $data): array
    {
        $attributes = collect($data)->except([
            'portions',
            'boundary_segments',

            // Child tables persisted by syncChildren, not report columns.
            'purposes',
            'participants',
            'merger_properties',
            'extension_portions',
            'subdivision_plots',
            'purpose_changes',
        ])->all();

        // The four direction boxes are the source; boundary_description is compiled
        // from them by the shared trait so it reads the same as the OSS JSI sheet.
        if (array_key_exists('boundary_segments', $data)) {
            $attributes['boundary_description'] = MasterJsiReport::compileBoundaryDescription(
                $data['boundary_segments'],
                $data['boundary_description'] ?? null
            );
        }

        // Site figures arrive in the unit picked on the sheet (m² or ha) and are
        // stored in m², exactly as the on-screen totals interpret them — so a
        // figure entered in hectares never lands in the column as the raw number.
        if (($data['site_unit'] ?? 'sqm') === 'ha') {
            foreach (['site_existing_area_sqm', 'site_recommended_area_sqm', 'recommended_total_override_sqm'] as $field) {
                if (array_key_exists($field, $attributes) && filled($attributes[$field])) {
                    $attributes[$field] = round((float) $attributes[$field] * 10000, 4);
                }
            }
        }

        return $attributes;
    }

    /**
     * Replace the sheet's rows with what was submitted.
     *
     * A whole-table replace rather than a diff: the rows are a single observation
     * taken on one visit, they are few, and matching them up by id would let a
     * renamed row keep an area that was measured against a different portion.
     */
    private function syncPortions(MasterJsiReport $report, $portions): void
    {
        MasterJsiPortion::where('master_jsi_report_id', $report->id)->delete();

        $sequence = 0;

        foreach ((array) $portions as $portion) {
            $dimensions = ParcelSizeSummary::dimensionsText($portion['dimensions'] ?? null);
            $area       = $portion['area_sqm'] ?? null;
            $landUse    = $portion['land_use'] ?? null;
            $count      = $portion['unit_count'] ?? null;

            // The form derives the area on screen and posts it, but the sides are
            // what was measured — so if an area did not arrive, derive it here by
            // the same rule rather than storing a row with a blank measurement.
            if (($area === null || $area === '') && !empty($portion['dimensions'])) {
                $area = ParcelSizeSummary::areaFromSides($portion['dimensions']);
            }

            // A row with nothing measured on it is not an observation — the sheet
            // prints the rows that were filled in, not the empty template.
            if ($dimensions === null && ($area === null || $area === '')
                && ($landUse === null || $landUse === '')
                && ($count === null || $count === '')) {
                continue;
            }

            $sequence++;

            MasterJsiPortion::create([
                'master_jsi_report_id' => $report->id,
                'sn'                   => $sequence,
                'role'                 => $portion['role'] ?? null,
                'label'                => $portion['label'] ?? null,
                'dimensions'           => $dimensions,
                'area_sqm'             => ($area === null || $area === '') ? null : $area,
                'land_use'             => $landUse ?: null,
                'unit_count'           => ($count === null || $count === '') ? null : $count,
                'sequence'             => $sequence,
            ]);
        }
    }

    /**
     * Persist every table the inspection template now carries.
     *
     * All child sets are whole-table replaces, like syncPortions: they are one
     * observation taken on one visit, and a renamed row must not keep a measured
     * area that belongs to a different portion.
     */
    private function syncChildren(MasterJsiReport $report, array $data): void
    {
        $this->syncPortions($report, $data['portions'] ?? []);

        // Purposes are a whole-table replace like every other child set. They were
        // originally appended blindly, so re-saving a sheet kept the old purposes
        // and added the newly ticked ones again — each edit doubled the count.
        MasterJsiPurpose::where('master_jsi_report_id', $report->id)->delete();

        $purposeSequence = 0;
        foreach ((array) ($data['purposes'] ?? []) as $purpose) {
            if (!is_string($purpose) || $purpose === '') {
                continue;
            }
            $purposeSequence++;
            MasterJsiPurpose::create([
                'master_jsi_report_id' => $report->id,
                'purpose'              => $purpose,
                'sequence'             => $purposeSequence,
            ]);
        }

        MasterJsiParticipant::where('master_jsi_report_id', $report->id)->delete();
        $participantSequence = 0;
        foreach ((array) ($data['participants'] ?? []) as $participant) {
            if (blank($participant['participant'] ?? null) && blank($participant['name'] ?? null)) {
                continue;
            }
            $participantSequence++;
            MasterJsiParticipant::create([
                'master_jsi_report_id' => $report->id,
                'participant'          => $participant['participant'] ?? null,
                'name'                 => $participant['name'] ?? null,
                'phone'                => $participant['phone'] ?? null,
                'relationship'         => $participant['relationship'] ?? null,
                'sequence'             => $participantSequence,
            ]);
        }

        MasterJsiMergerProperty::where('master_jsi_report_id', $report->id)->delete();
        $mergerSequence = 0;
        foreach ((array) ($data['merger_properties'] ?? []) as $property) {
            if (blank($property['property_file_number'] ?? null)) {
                continue;
            }
            $mergerSequence++;
            MasterJsiMergerProperty::create([
                'master_jsi_report_id'  => $report->id,
                'property_file_number'  => $property['property_file_number'],
                'plot_number'           => $property['plot_number'] ?? null,
                'area_sqm'              => $this->normaliseArea($property['area_sqm'] ?? null, $property['unit'] ?? null),
                'unit'                  => $property['unit'] ?? null,
                'sequence'              => $mergerSequence,
            ]);
        }

        MasterJsiExtensionPortion::where('master_jsi_report_id', $report->id)->delete();
        $extensionSequence = 0;
        foreach ((array) ($data['extension_portions'] ?? []) as $extension) {
            if (blank($extension['description'] ?? null) && blank($extension['area_sqm'] ?? null)) {
                continue;
            }
            $extensionSequence++;
            MasterJsiExtensionPortion::create([
                'master_jsi_report_id' => $report->id,
                'extension_number'     => $extension['extension_number'] ?? $extensionSequence,
                'description'          => $extension['description'] ?? null,
                'area_sqm'             => $this->normaliseArea($extension['area_sqm'] ?? null, $extension['unit'] ?? null),
                'unit'                 => $extension['unit'] ?? null,
                'sequence'             => $extensionSequence,
            ]);
        }

        MasterJsiSubdivisionPlot::where('master_jsi_report_id', $report->id)->delete();
        $plotSequence = 0;
        foreach ((array) ($data['subdivision_plots'] ?? []) as $plot) {
            if (blank($plot['plot_number'] ?? null) && blank($plot['area_sqm'] ?? null)) {
                continue;
            }
            $plotSequence++;
            MasterJsiSubdivisionPlot::create([
                'master_jsi_report_id' => $report->id,
                'plot_number'          => $plot['plot_number'] ?? null,
                'area_sqm'             => $this->normaliseArea($plot['area_sqm'] ?? null, $plot['unit'] ?? null),
                'unit'                 => $plot['unit'] ?? null,
                'remarks'              => $plot['remarks'] ?? null,
                'sequence'             => $plotSequence,
            ]);
        }

        MasterJsiPurposeChange::where('master_jsi_report_id', $report->id)->delete();
        $changeSequence = 0;
        foreach ((array) ($data['purpose_changes'] ?? []) as $change) {
            if (blank($change['current_land_use'] ?? null) && blank($change['proposed_land_use'] ?? null)) {
                continue;
            }
            $changeSequence++;
            MasterJsiPurposeChange::create([
                'master_jsi_report_id'  => $report->id,
                'current_land_use'      => $change['current_land_use'] ?? null,
                'proposed_land_use'     => $change['proposed_land_use'] ?? null,
                'area_sqm'              => $this->normaliseArea($change['area_sqm'] ?? null, $change['unit'] ?? null),
                'unit'                  => $change['unit'] ?? null,
                'remarks'               => $change['remarks'] ?? null,
                'sequence'              => $changeSequence,
            ]);
        }
    }

    /**
     * Every measurement is stored in square metres.
     *
     * The officer may enter hectares on the form; it is converted here so the
     * stored number and the printed sheet always agree, regardless of unit picked.
     */
    private function normaliseArea($area, $unit): ?float
    {
        if ($area === null || $area === '') {
            return null;
        }

        $value = (float) $area;
        $value = max(0, $value);

        if ($value > 0 && ($unit === 'ha' || strtolower((string) $unit) === 'hectares')) {
            $value *= 10000;
        }

        return $value;
    }
}
