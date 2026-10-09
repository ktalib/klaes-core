<?php

namespace App\Http\Controllers\ChangeOfPurpose;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Entity;
use App\Models\ChangeOfPurposeApplication;
use App\Services\ChangeOfPurposeBatchCommitService;
use App\Services\Edms\ParcelDocumentIngestService;
use App\Services\ParcelUpdateNotificationService;
use App\Services\TitleStatusParcelRouter;
use App\Support\MasterJsiGate;
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
            // Hide rows routed in from Title Status / File Indexing until processed.
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS);
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
        $visible = fn ($q) => $q->where(fn ($x) => $x->whereNull('is_deleted')->orWhere('is_deleted', 0))
                                ->where(fn ($x) => $x->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS));
        $stats = [
            'total'    => ChangeOfPurposeApplication::where($visible)->count(),
            'daily'    => ChangeOfPurposeApplication::where($visible)->whereDate('created_at', today())->count(),
            'pending'  => ChangeOfPurposeApplication::where($visible)->where('status', ChangeOfPurposeApplication::STATUS_PENDING)->count(),
            'approved' => ChangeOfPurposeApplication::where($visible)->where('status', ChangeOfPurposeApplication::STATUS_APPROVED)->count(),
            'rejected' => ChangeOfPurposeApplication::where($visible)->where('status', ChangeOfPurposeApplication::STATUS_REJECTED)->count(),
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

        // One file, one application - whether it covers one plot or forty. The count
        // is what makes it a batch, and it is spent at commissioning, where the file
        // fans out into that many new numbers.
        $data['plot_count'] = max(1, (int) ($data['plot_count'] ?? 1));

        // Which sidebar raised this. Both open the same register, and until this
        // column existed only the page title knew the difference.
        $data['source_module'] = $request->query('mode') === 'land' ? 'land' : 'deeds';

        // A file already carrying a live application must not take a second one:
        // both would try to retire it.
        $live = ChangeOfPurposeApplication::query()
            ->where('file_no', trim((string) $data['file_no']))
            ->whereIn('status', [
                ChangeOfPurposeApplication::STATUS_PENDING,
                ChangeOfPurposeApplication::STATUS_PROCESSING,
                ChangeOfPurposeApplication::STATUS_APPROVED,
            ])
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->exists();

        if ($live) {
            return response()->json([
                'success' => false,
                'message' => 'A Change of Purpose is already in progress for ' . $data['file_no'] . '.',
            ], 422);
        }

        $record = ChangeOfPurposeApplication::create($data);

        if ($request->hasFile('site_plan')) {
            $file = $request->file('site_plan');
            $filename = 'cop_' . $record->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            // Goes into the EDMS tree and registers a scannings/pagetypings pair,
            // so the document shows in the EDMS workflow and the Virtual Folder
            // System instead of only on this screen. Falls back to site_plans/ when
            // the file number is not indexed — see ParcelDocumentIngestService.
            $path = app(ParcelDocumentIngestService::class)->ingest(
                $file,
                $data['file_no'] ?? null,
                'site_plan',
                'site_plans',
                $filename,
                Auth::id()
            );
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
            'message' => $record->isBatch()
                ? 'Application submitted for ' . $record->plotCount() . ' plots. Awaiting approval.'
                : 'Application submitted successfully. Awaiting approval.',
            'data' => $record,
            'plot_count' => $record->plotCount(),
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

        return response()->json([
            'success' => true,
            'message' => $record->isBatch()
                ? 'Approved - ' . $record->plotCount() . ' plots.'
                : 'Application approved.',
        ]);
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
            // How many plots the one file covers; the slip says so.
            'plot_count' => $record->plotCount(),
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

        // The slip states how many plots the approval covers: the same file, but a
        // holding of several plots, each of which becomes its own number.
        $plotCount = $record->plotCount();

        return view('change_of_purpose.print.acknowledgement', compact(
            'record',
            'newFileNo',
            'landUseLabel',
            'newPurposeLabel',
            'plotCount'
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
     * Commission every approved plot of a batch in one action.
     *
     * Each plot goes through the ordinary single Change of Purpose path, retiring its
     * own parent and taking its own serial, so the plots stay separate properties.
     * See ChangeOfPurposeBatchCommitService for why this is a loop and not the batch
     * engine, and why it is not atomic across plots.
     */
    public function commissionBatch(Request $request, int $id): JsonResponse
    {
        $meta = $request->validate([
            'commissioned_by' => 'nullable|string|max:255',
            'commission_date' => 'nullable|date',
            'commission_time' => 'nullable|string|max:20',
            'customer_type'   => 'nullable|string|in:Individual,Corporate,Multiple,Government',
            'gender'          => 'nullable|string|in:Male,Female,Corporate,Joint',
            'phone_no'        => 'nullable|string|max:100',
            'purpose_id'      => 'nullable|integer',
        ]);

        try {
            $result = app(ChangeOfPurposeBatchCommitService::class)
                ->commit(ChangeOfPurposeApplication::findOrFail($id), $meta);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $done = count($result['commissioned']);

        // A partial run is reported as a failure with what it managed, so the officer
        // is never told "done" over a batch that stopped halfway.
        if (!empty($result['failed'])) {
            return response()->json([
                'success' => false,
                'message' => "Commissioned {$done} plot(s), then stopped at {$result['failed']['file_no']}: "
                    . $result['failed']['message'],
                'result'  => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Batch commissioned - {$done} plot(s).",
            'result'  => $result,
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
    /**
     * The applicant's phone and address for a file the officer has just picked.
     *
     * These were typed by hand on every application, so they were blank on almost all
     * of them — 4 of 193 carried a phone — even where the registry already held the
     * holder's details. The form now asks for them only when nothing is on record.
     *
     * Three sources, in order of authority:
     *
     *   1. file_indexings — the registry's own answer for this file, and the one the
     *      rest of the system reads;
     *   2. the most recent parcel-update application against the same file, because an
     *      officer typed those with the file in front of them;
     *   3. mls_file_no, which carries what was captured at commissioning.
     *
     * Each field resolves on its own: a file with a phone in the registry and an
     * address only on an earlier application returns both, from different rows.
     */
    public function applicantDetails(Request $request): JsonResponse
    {
        $fileNo = strtoupper(trim((string) $request->query('file_no', '')));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'No file number given.'], 422);
        }

        $conn  = DB::connection('sqlsrv');
        $phone = null;
        $address = null;
        $from  = [];

        $take = function (?string $value): ?string {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        };

        // 1. The registry row for this file.
        $indexing = $conn->table('file_indexings')
            ->where('file_number', $fileNo)
            ->orderByDesc('id')
            ->first(['phone', 'residence_address']);

        if ($indexing) {
            $phone   = $take($indexing->phone);
            $address = $take($indexing->residence_address);

            if ($phone)   { $from['phone'] = 'file indexing'; }
            if ($address) { $from['address'] = 'file indexing'; }
        }

        // 2. The last parcel update captured against the same file.
        if ($phone === null || $address === null) {
            $application = $conn->table('change_of_purpose_applications')
                ->where('file_no', $fileNo)
                ->whereRaw("ISNULL(is_deleted, 0) = 0")
                ->orderByDesc('id')
                ->first(['phone', 'residential_address']);

            if ($application) {
                if ($phone === null && ($v = $take($application->phone))) {
                    $phone = $v;
                    $from['phone'] = 'a previous application';
                }

                if ($address === null && ($v = $take($application->residential_address))) {
                    $address = $v;
                    $from['address'] = 'a previous application';
                }
            }
        }

        // 3. What commissioning recorded.
        if ($phone === null || $address === null) {
            $mls = $conn->table('mls_file_no')
                ->where('full_file_number', $fileNo)
                ->orderByDesc('id')
                ->first(['phone_no', 'address']);

            if ($mls) {
                if ($phone === null && ($v = $take($mls->phone_no))) {
                    $phone = $v;
                    $from['phone'] = 'file commissioning';
                }

                if ($address === null && ($v = $take($mls->address))) {
                    $address = $v;
                    $from['address'] = 'file commissioning';
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'file_no' => $fileNo,
                'phone'   => $phone,
                'address' => $address,
                'source'  => $from,
            ],
        ]);
    }

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
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
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

        // The modal needs to know how many numbers choosing this will mint, so the
        // count travels with the row rather than being looked up again.
        $results->each(fn ($record) => $record->setAttribute('plot_count', $record->plotCount()));

        return response()->json(['success' => true, 'data' => $results->values()]);
    }

    /**
     * What commissioning will do with this application, for the modal's batch card.
     *
     * One source file, N plots, N new numbers. The numbers themselves are not named
     * here: they come off the live serial counter at the moment of commissioning,
     * and naming them early would state a number the registry has not issued.
     */
    public function plots(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);

        return response()->json([
            'success'        => true,
            'id'             => $record->id,
            'file_no'        => $record->file_no,
            'plot_count'     => $record->plotCount(),
            'applicant_name' => $record->applicant_name,
            'land_use'       => $record->land_use,
            'purpose'        => $record->purpose,
            'new_purpose'    => $record->new_purpose,
            'status'         => $record->status,
        ]);
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
            return response()->json(['success' => false, 'message' => "The Change of Purpose application for this file number is currently '{$application->status}'. It must be Approved before generating an MLPP File Number."]);
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
        // Stamp the generation flag the menu reads — NOT created_at, which is the
        // application's own capture date and must not move.
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        // Same sheet as the acknowledgement — build the identical view data so the
        // memo renders its land-use labels either way it is opened.
        return $this->printAcknowledgement($id);
    }

    public function generateRecommendation(Request $request, int $id): JsonResponse
    {
        // The recommendation memo is the document that goes UP to the Permanent
        // Secretary/Honourable Commissioner for approval, so it must be available
        // while the application is still pending. The only precondition — the one
        // the listing menu enforces — is the planning clearance.
        $record = ChangeOfPurposeApplication::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->find($id);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Application not found.'], 404);
        }

        // The clearance is an approved Master JSI. MasterJsiGate also lets through
        // a record cleared by the old KAMMA handshake, which is why this is asked
        // through the gate rather than by reading either status here.
        if (!MasterJsiGate::clearedFor($record, 'change_of_purpose')) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is required before the recommendation can be generated.',
            ], 422);
        }

        // Memo details entered on the Generate Recommendation card. All optional —
        // an officer may print the sheet with blanks and complete it by hand.
        $validator = Validator::make($request->all(), [
            'rec_page_application'  => 'nullable|string|max:50',
            'rec_page_planning'     => 'nullable|string|max:50',
            'rec_page_site_plan'    => 'nullable|string|max:50',
            'rec_title_alias'       => 'nullable|string|max:100',
            'rec_measurement_a'     => 'nullable|string|max:2000',
            'rec_measurement_b'     => 'nullable|string|max:2000',
            'rec_term_years'        => 'nullable|integer|min:0|max:999',
            'rec_commencement_date' => 'nullable|date',
            'rec_residual_years'    => 'nullable|integer|min:0|max:999',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $record->update($validator->validated() + [
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);

        // `purpose` holds the code (COM/RES/...), `land_use` the current use as
        // captured — resolve both to the labels the memo reads in prose.
        $currentUseLabel = self::LAND_USE_OPTIONS[strtoupper((string) $record->land_use)]
            ?? ($record->land_use ?: null);
        $newPurposeLabel = self::LAND_USE_OPTIONS[strtoupper((string) $record->purpose)]
            ?? ($record->new_purpose ?: $record->purpose);

        return view('change_of_purpose.print.recommendation', compact(
            'record',
            'currentUseLabel',
            'newPurposeLabel'
        ));
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::findOrFail($id);
        $record->update([
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'KAMMA status updated.']);
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
            'land_value' => 'nullable|numeric',
            'site_plan' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            // How many plots the one file covers. 1 (or absent) is an ordinary
            // single application; more is the batch size commissioning fans out to.
            'plot_count' => 'nullable|integer|min:1|max:200',
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


