<?php

namespace App\Http\Controllers\ChangeOfPurpose;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Entity;
use App\Models\ChangeOfPurposeApplication;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Models\StreetName;

class ChangeOfPurposeController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    private const LAND_USE_OPTIONS = [
        'RES' => 'Residential',
        'COM' => 'Commercial',
        'IND' => 'Industrial',
        'AGR' => 'Agricultural',
        'MIX' => 'Mixed Use',
    ];

    /**
     * Display the Change of Purpose listing page (two-tab layout).
     */
    public function index(Request $request): View
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $base = ChangeOfPurposeApplication::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('land_use', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%")
                        ->orWhere('purpose', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        // Tab 1: pending / processing / rejected applications
        $pendingRecords = (clone $base)
            ->whereIn('status', [
                ChangeOfPurposeApplication::STATUS_PENDING,
                ChangeOfPurposeApplication::STATUS_PROCESSING,
                ChangeOfPurposeApplication::STATUS_REJECTED,
            ])
            ->limit($limit)
            ->get();

        // Tab 2 (Initiate Change): approved applications waiting for commissioning,
        // plus the already-commissioned ones so they stay on this tab.
        $approvedRecords = (clone $base)
            ->whereIn('status', [
                ChangeOfPurposeApplication::STATUS_APPROVED,
                ChangeOfPurposeApplication::STATUS_COMMISSIONED,
            ])
            ->limit($limit)
            ->get();

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // Dashboard stats
        $stats = [
            'total' => ChangeOfPurposeApplication::where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->count(),
            'daily' => ChangeOfPurposeApplication::where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->whereDate('created_at', today())->count(),
            'pending' => ChangeOfPurposeApplication::where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfPurposeApplication::STATUS_PENDING)->count(),
            'approved' => ChangeOfPurposeApplication::where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfPurposeApplication::STATUS_APPROVED)->count(),
            'rejected' => ChangeOfPurposeApplication::where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfPurposeApplication::STATUS_REJECTED)->count(),
        ];

        return view('change_of_purpose.index', compact(
            'pendingRecords',
            'approvedRecords',
            'limit',
            'search',
            'states',
            'lgas',
            'districts',
            'streetNames',
            'stats'
        ) + ['landUseOptions' => self::LAND_USE_OPTIONS]);
    }

    /**
     * Return a single record as JSON.
     */
    public function show(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()

            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $record]);
    }

    /**
     * Store a new application Ã¢â‚¬â€ enters the pending queue (no file-number change yet).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $comment = $data['comment'] ?? null;
        unset($data['comment']);

        $data['remarks'] = $comment;
        $data['captured_by'] = Auth::id();
        $data['status'] = ChangeOfPurposeApplication::STATUS_PENDING;

        $record = ChangeOfPurposeApplication::create($data);

        if ($request->hasFile('site_plan')) {
            $file = $request->file('site_plan');
            $filename = 'cop_' . $record->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('site_plans', $filename, 'public');
            $record->update(['site_plan' => $path]);
        }

        // Also save comment to pra table for the matching file number
        if ($comment && !empty($data['file_no'])) {
            DB::connection('sqlsrv')->table('pra')
                ->where('fileno', $data['file_no'])
                ->update(['comments' => $comment]);
        }

        $this->parcelNotifier->notifyCreated(
            'change_of_purpose',
            $record->id,
            $record->file_no,
            '',
            $record->applicant_name ?? ''
        );

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully. Awaiting approval.',
            'data' => $record,
        ]);
    }

    /**
     * Approve a pending application.
     */
    public function approve(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()

            ->findOrFail($id);

        $record->update([
            'status' => ChangeOfPurposeApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        $approver = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'change_of_purpose',
            $record->id,
            $record->file_no,
            '',
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    /**
     * Reject an application with an optional reason.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()

            ->findOrFail($id);

        $reason = trim((string) $request->input('reason', ''));

        $record->update([
            'status' => ChangeOfPurposeApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    /**
     * Return acknowledgement data for an approved application.
     */
    public function acknowledgement(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()

            ->findOrFail($id);

        $newFileNo = $this->generateNewFileNumber(
            (string) ($record->file_no ?? ''),
            strtoupper((string) ($record->purpose ?? ''))
        );

        return response()->json([
            'success' => true,
            'data' => $record,
            'new_file_no' => $newFileNo,
            'land_use_label' => self::LAND_USE_OPTIONS[$record->purpose ?? ''] ?? $record->purpose,
        ]);
    }

    /**
     * Render a printable acknowledgement sheet.
     */
    public function printAcknowledgement(int $id): View
    {
        $record = ChangeOfPurposeApplication::query()->findOrFail($id);

        $newFileNo = $this->generateNewFileNumber(
            (string) ($record->file_no ?? ''),
            strtoupper((string) ($record->purpose ?? ''))
        );

        $landUseLabel = $record->land_use ?: '-';
        $newPurposeLabel = self::LAND_USE_OPTIONS[$record->purpose ?? ''] ?? $record->purpose;

        return view('change_of_purpose.print.acknowledgement', compact(
            'record',
            'newFileNo',
            'landUseLabel',
            'newPurposeLabel'
        ));
    }

    /**
     * Mark an approved application as commissioned after the shared modal generates the file number.
     */
    public function commissionFileNumber(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()
            ->where('status', ChangeOfPurposeApplication::STATUS_APPROVED)
            ->findOrFail($id);

        $newFileNo = trim((string) $request->input('new_file_no', ''));
        $oldFileNo = trim((string) ($record->file_no ?? ''));

        $record->update([
            'status' => 'commissioned',
            'remarks' => trim(($record->remarks ?? '') . "\nCommissioned: {$oldFileNo} -> {$newFileNo} on " . now()->toDateTimeString()),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Change of Purpose commissioned successfully.',
            'old_file_no' => $oldFileNo,
            'new_file_no' => $newFileNo,
        ]);
    }

    /**
     * Update application metadata (no re-processing of file number).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()
            ->findOrFail($id);

        $rules = $this->rules();
        unset($rules['purpose']); // do not re-validate purpose on edit

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id();
        $record->update($data);

        return response()->json(['success' => true, 'message' => 'Record updated.', 'data' => $record->fresh()]);
    }


    /**
     * Search fileNumber records for the file-selector lookup.
     */
    public function searchFileNumbers(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('term'));

        if (strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $results = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where(function ($q) use ($term) {
                $q->where('mlsfNo', 'LIKE', "%{$term}%")
                    ->orWhere('kangisFileNo', 'LIKE', "%{$term}%")
                    ->orWhere('NewKANGISFileNo', 'LIKE', "%{$term}%")
                    ->orWhere('FileName', 'LIKE', "%{$term}%");
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName', 'location', 'lga', 'plot_no', 'tp_no', 'related_fileno', 'SOURCE')
            ->orderBy('mlsfNo')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * Search for approved Change of Purpose applications matching a search term.
     * Returns JSON with approved records.
     */
    public function searchApproved(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('term', ''));
        $limit = max(1, min((int) $request->input('limit', 50), 200));

        // An empty/short term is used to load the initial list when the selector
        // modal opens, so return the most recent approved applications instead of
        // an empty set — otherwise the modal appears blank.
        $results = ChangeOfPurposeApplication::query()
            ->where('status', ChangeOfPurposeApplication::STATUS_APPROVED)
            ->when(strlen($term) >= 2, function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('applicant_name', 'LIKE', "%{$term}%")
                        ->orWhere('file_no', 'LIKE', "%{$term}%")
                        ->orWhere('land_use', 'LIKE', "%{$term}%")
                        ->orWhere('location', 'LIKE', "%{$term}%")
                        ->orWhere('purpose', 'LIKE', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * Verify if a file number has an approved change of purpose application 
     * before allowing MLS file number generation.
     */
    public function verifyForCommission(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));

        if (empty($fileNo)) {
            return response()->json(['success' => false, 'message' => 'No file number provided.']);
        }

        $application = ChangeOfPurposeApplication::where('file_no', $fileNo)
            ->orderByDesc('created_at')
            ->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'This file number has not passed through the Change of Purpose application process.']);
        }

        if ($application->status !== ChangeOfPurposeApplication::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => "The Change of Purpose application for this file number is currently '{$application->status}'. It must be Approved before generating an MLS File Number."]);
        }

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * Preview the new file number without committing.
     */
    public function preview(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no'));
        $newPurpose = strtoupper(trim((string) $request->input('purpose')));

        if (!$fileNo || !$newPurpose) {
            return response()->json(['success' => false, 'message' => 'File number and purpose are required.'], 422);
        }

        $newFileNo = $this->generateNewFileNumber($fileNo, $newPurpose);

        if (!$newFileNo) {
            return response()->json(['success' => false, 'message' => 'Could not compute new file number from this format.'], 422);
        }

        return response()->json([
            'success' => true,
            'old_file_no' => $fileNo,
            'new_file_no' => $newFileNo,
            'new_purpose' => $newPurpose,
        ]);
    }

  

    /**
     * DEAD-CODE REMOVED: updateFileIndexing() and handlePra() previously duplicated the
     * Change-of-Purpose rename/PRA logic, but Change of Purpose is commissioned exclusively by
     * MlsFileNoController::generateMlsFileNumber() (application_type = 'change_of_purpose'), which
     * renames file_indexings/fileNumber in place, records the decommission audit, sets
     * related_fileno and inserts the PRA row. commissionFileNumber() below only marks the
     * application status afterwards. The old helpers were never called.
     */

    /**
     * Generate new file number by swapping the land-use prefix.
     *
     * Supported formats:
     *   ST-RES-2025-0001   Ã¢â€ â€™ ST-COM-2025-0001
     *   CON-RES-1984-248   Ã¢â€ â€™ CON-COM-1984-248
     *   RES-1994-762       Ã¢â€ â€™ COM-1994-762
     */
    private function generateNewFileNumber(string $oldFileNo, string $newPurpose): ?string
    {
        $oldFileNo = strtoupper(trim($oldFileNo));
        $newPurpose = strtoupper(trim($newPurpose));

        $knownPrefixes = [
            'RES',
            'COM',
            'IND',
            'AGR',
            'AGRIC',
            'AG',
            'MIX',
            'MIXED',
            'RESIDENTIAL',
            'COMMERCIAL',
            'INDUSTRIAL',
            'AGRICULTURAL'
        ];
        $pp = implode('|', $knownPrefixes);

        if (preg_match('/^(ST)-(' . $pp . ')-(.+)$/i', $oldFileNo, $m))
            return $m[1] . '-' . $newPurpose . '-' . $m[3];
        if (preg_match('/^(CON)-(' . $pp . ')-(.+)$/i', $oldFileNo, $m))
            return $m[1] . '-' . $newPurpose . '-' . $m[3];
        if (preg_match('/^(' . $pp . ')-(.+)$/i', $oldFileNo, $m))
            return $newPurpose . '-' . $m[2];

        return null;
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);
        $record->update([
            'created_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);
        return view('change_of_purpose.print.acknowledgement', compact('record')); // Use existing acknowledgement as application print
    }

    public function generateRecommendation(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::where('status', ChangeOfPurposeApplication::STATUS_APPROVED)->findOrFail($id);
        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);
        return view('change_of_purpose.print.recommendation', compact('record'));
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);
        $record->update([
            'land_size' => $request->input('land_size'),
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'KNUPDA status updated.']);
    }

    /**
     * Shared validation rules.
     */
    private function rules(): array
    {
        return [
            'applicant_name' => 'required|string|max:255',
            'file_no' => 'required|string|max:255',
            'purpose' => 'required|string|max:50',
            'new_purpose' => 'nullable|string|max:500',
            'land_use' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:2000',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:100',
            'plan_no' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'residential_address' => 'nullable|string|max:2000',
            'comment' => 'nullable|string|max:2000',
            'knupda_fee' => 'nullable|numeric',
            'land_size' => 'nullable|numeric',
            'site_plan' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ];
    }

    public function destroy(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);

        if ($record->status === ChangeOfPurposeApplication::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
    }
}


