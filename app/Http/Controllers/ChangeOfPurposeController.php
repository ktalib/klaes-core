<?php

namespace App\Http\Controllers\ChangeOfPurpose;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Entity;
use App\Models\ChangeOfPurposeApplication;
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
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $base = ChangeOfPurposeApplication::query()
            
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no',       'LIKE', "%{$search}%")
                        ->orWhere('land_use',      'LIKE', "%{$search}%")
                        ->orWhere('location',      'LIKE', "%{$search}%")
                        ->orWhere('purpose',       'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        // Tab 1: pending / processing applications
        $pendingRecords = (clone $base)
            ->whereIn('status', [
                ChangeOfPurposeApplication::STATUS_PENDING,
                ChangeOfPurposeApplication::STATUS_PROCESSING,
                ChangeOfPurposeApplication::STATUS_REJECTED,
            ])
            ->limit($limit)
            ->get();

        // Tab 2: approved applications waiting for commissioning
        $approvedRecords = (clone $base)
            ->where('status', ChangeOfPurposeApplication::STATUS_APPROVED)
            ->limit($limit)
            ->get();

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // Dashboard stats
        $stats = [
            'total'        => ChangeOfPurposeApplication::count(),
            'pending'      => ChangeOfPurposeApplication::where('status', ChangeOfPurposeApplication::STATUS_PENDING)->count(),
            'approved'     => ChangeOfPurposeApplication::where('status', ChangeOfPurposeApplication::STATUS_APPROVED)->count(),
            'commissioned' => ChangeOfPurposeApplication::where('status', 'commissioned')->count(),
            'rejected'     => ChangeOfPurposeApplication::where('status', ChangeOfPurposeApplication::STATUS_REJECTED)->count(),
        ];

        return view('change_of_purpose.index', compact(
            'pendingRecords', 'approvedRecords', 'limit', 'search',
            'states', 'lgas', 'districts', 'streetNames', 'stats'
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
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data                     = $validator->validated();
        $comment                  = $data['comment'] ?? null;
        unset($data['comment']);

        $data['remarks']          = $comment;
        $data['captured_by']      = Auth::id();
        $data['status']           = ChangeOfPurposeApplication::STATUS_PENDING;

        $record = ChangeOfPurposeApplication::create($data);

        // Also save comment to pra table for the matching file number
        if ($comment && !empty($data['file_no'])) {
            DB::connection('sqlsrv')->table('pra')
                ->where('fileno', $data['file_no'])
                ->update(['comments' => $comment]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully. Awaiting approval.',
            'data'    => $record,
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
            'status'     => ChangeOfPurposeApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

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
            'status'     => ChangeOfPurposeApplication::STATUS_REJECTED,
            'remarks'    => $reason ? "Rejected: {$reason}" : 'Rejected',
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
            'data'    => $record,
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

        $landUseLabel    = $record->land_use ?: '-';
        $newPurposeLabel = self::LAND_USE_OPTIONS[$record->purpose ?? ''] ?? $record->purpose;

        return view('change_of_purpose.print.acknowledgement', compact(
            'record', 'newFileNo', 'landUseLabel', 'newPurposeLabel'
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
            'status'     => 'commissioned',
            'remarks'    => trim(($record->remarks ?? '') . "\nCommissioned: {$oldFileNo} -> {$newFileNo} on " . now()->toDateTimeString()),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Change of Purpose commissioned successfully.',
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
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data               = $validator->validated();
        $data['updated_by'] = Auth::id();
        $record->update($data);

        return response()->json(['success' => true, 'message' => 'Record updated.', 'data' => $record->fresh()]);
    }

    /**
     * Delete an application record.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = ChangeOfPurposeApplication::query()
            
            ->findOrFail($id);

        $record->delete();

        return response()->json(['success' => true, 'message' => 'Record deleted.']);
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
                $q->where('mlsfNo',         'LIKE', "%{$term}%")
                  ->orWhere('kangisFileNo',  'LIKE', "%{$term}%")
                  ->orWhere('NewKANGISFileNo','LIKE', "%{$term}%")
                  ->orWhere('FileName',      'LIKE', "%{$term}%");
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
     * Preview the new file number without committing.
     */
    public function preview(Request $request): JsonResponse
    {
        $fileNo     = trim((string) $request->input('file_no'));
        $newPurpose = strtoupper(trim((string) $request->input('purpose')));

        if (! $fileNo || ! $newPurpose) {
            return response()->json(['success' => false, 'message' => 'File number and purpose are required.'], 422);
        }

        $newFileNo = $this->generateNewFileNumber($fileNo, $newPurpose);

        if (! $newFileNo) {
            return response()->json(['success' => false, 'message' => 'Could not compute new file number from this format.'], 422);
        }

        return response()->json([
            'success'     => true,
            'old_file_no' => $fileNo,
            'new_file_no' => $newFileNo,
            'new_purpose' => $newPurpose,
        ]);
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Private helpers Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Update file_indexings rows: rename file_number and append old to related_fileno JSON.
     */
    private function updateFileIndexing(string $oldFileNo, string $newFileNo): void
    {
        $db   = DB::connection('sqlsrv');
        $rows = $db->table('file_indexings')->where('file_number', $oldFileNo)->get();

        foreach ($rows as $row) {
            $existing = [];
            if (! empty($row->related_fileno)) {
                $decoded  = json_decode($row->related_fileno, true);
                $existing = is_array($decoded) ? $decoded : [$row->related_fileno];
            }

            if (! in_array($oldFileNo, $existing, true)) {
                $existing[] = $oldFileNo;
            }

            $db->table('file_indexings')
                ->where('id', $row->id)
                ->update([
                    'file_number'    => $newFileNo,
                    'related_fileno' => json_encode(array_values($existing)),
                    'updated_by'     => Auth::id(),
                ]);
        }
    }

    /**
     * PRA: copy last row (if any), update file number columns, set transaction type, insert.
     */
    private function handlePra(string $oldFileNo, string $newFileNo): void
    {
        $db = DB::connection('sqlsrv');

        // Determine which file-number columns actually exist in pra
        $candidates  = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno'];
        $praColumns  = [];

        foreach ($candidates as $col) {
            try {
                if (Schema::connection('sqlsrv')->hasColumn('pra', $col)) {
                    $praColumns[] = $col;
                }
            } catch (\Throwable $e) {
                // Column check failed Ã¢â‚¬â€ skip safely
            }
        }

        if (empty($praColumns)) {
            return;
        }

        // Find rows for the old file number
        $query = $db->table('pra')->where(function ($q) use ($praColumns, $oldFileNo) {
            foreach ($praColumns as $col) {
                $q->orWhere($col, $oldFileNo);
            }
        });

        $rows = $query->orderByDesc('id')->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Clone the most recent row
        $newRow = (array) $rows->first();
        unset($newRow['id']);

        // Update file number columns
        foreach ($praColumns as $col) {
            if (array_key_exists($col, $newRow)) {
                $newRow[$col] = $newFileNo;
            }
        }

        // Set transaction type (handle both naming conventions)
        foreach (['transaction_type', 'transactionType'] as $typeCol) {
            if (array_key_exists($typeCol, $newRow)) {
                $newRow[$typeCol] = 'Change of Purpose';
                break;
            }
        }

        // Timestamps
        $now = now();
        if (array_key_exists('created_at', $newRow)) {
            $newRow['created_at'] = $now;
        }
        if (array_key_exists('updated_at', $newRow)) {
            $newRow['updated_at'] = $now;
        }

        $db->table('pra')->insert($newRow);
    }

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
        $oldFileNo  = strtoupper(trim($oldFileNo));
        $newPurpose = strtoupper(trim($newPurpose));

        $knownPrefixes = ['RES', 'COM', 'IND', 'AGR', 'AGRIC', 'AG', 'MIX', 'MIXED',
                          'RESIDENTIAL', 'COMMERCIAL', 'INDUSTRIAL', 'AGRICULTURAL'];
        $pp = implode('|', $knownPrefixes);

        if (preg_match('/^(ST)-(' . $pp . ')-(.+)$/i',  $oldFileNo, $m)) return $m[1] . '-' . $newPurpose . '-' . $m[3];
        if (preg_match('/^(CON)-(' . $pp . ')-(.+)$/i', $oldFileNo, $m)) return $m[1] . '-' . $newPurpose . '-' . $m[3];
        if (preg_match('/^(' . $pp . ')-(.+)$/i',        $oldFileNo, $m)) return $newPurpose . '-' . $m[2];

        return null;
    }

    /**
     * Shared validation rules.
     */
    private function rules(): array
    {
        return [
            'applicant_name'         => 'required|string|max:255',
            'file_no'                => 'required|string|max:255',
            'purpose'                => 'required|string|max:50',
            'land_use'               => 'nullable|string|max:255',
            'location'               => 'nullable|string|max:2000',
            'plot_no'                => 'nullable|string|max:100',
            'plan_no'                => 'nullable|string|max:100',
            'phone'                  => 'nullable|string|max:50',
            'residential_address'    => 'nullable|string|max:2000',
            'comment'                => 'required|string|max:2000',
        ];
    }
}


