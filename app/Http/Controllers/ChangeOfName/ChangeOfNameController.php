<?php

namespace App\Http\Controllers\ChangeOfName;

use App\Http\Controllers\Controller;
use App\Models\ChangeOfNameApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\StreetName;

class ChangeOfNameController extends Controller
{
    private const TITLE_OPTIONS = [
        'Mr'     => 'Mr',
        'Mrs'    => 'Mrs',
        'Miss'   => 'Miss',
        'Ms'     => 'Ms',
        'Dr'     => 'Dr',
        'Prof'   => 'Prof',
        'Engr'   => 'Engr',
        'Alh'    => 'Alhaji',
        'Haj'    => 'Hajiya',
        'Chief'  => 'Chief',
        'Hon'    => 'Hon',
        'Barr'   => 'Barrister',
    ];

    /**
     * Display the Change of Name listing page (two-tab layout).
     */
    public function index(Request $request): View
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $base = ChangeOfNameApplication::query()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('current_name',   'LIKE', "%{$search}%")
                        ->orWhere('new_full_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no',       'LIKE', "%{$search}%")
                        ->orWhere('first_name',    'LIKE', "%{$search}%")
                        ->orWhere('last_name',     'LIKE', "%{$search}%")
                        ->orWhere('location',      'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        $pendingRecords = (clone $base)
            ->whereIn('status', [
                ChangeOfNameApplication::STATUS_PENDING,
                ChangeOfNameApplication::STATUS_PROCESSING,
                ChangeOfNameApplication::STATUS_REJECTED,
            ])
            ->limit($limit)
            ->get();

        $approvedRecords = (clone $base)
            ->where('status', ChangeOfNameApplication::STATUS_APPROVED)
            ->limit($limit)
            ->get();

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $stats = [
            'total'        => ChangeOfNameApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->count(),
            'daily'        => ChangeOfNameApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->whereDate('created_at', today())->count(),
            'pending'      => ChangeOfNameApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfNameApplication::STATUS_PENDING)->count(),
            'approved'     => ChangeOfNameApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfNameApplication::STATUS_APPROVED)->count(),
            'rejected'     => ChangeOfNameApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', ChangeOfNameApplication::STATUS_REJECTED)->count(),
        ];

        return view('change_of_name.index', compact(
            'pendingRecords', 'approvedRecords', 'limit', 'search',
            'states', 'lgas', 'districts', 'streetNames', 'stats'
        ) + ['titleOptions' => self::TITLE_OPTIONS]);
    }

    public function show(int $id): JsonResponse
    {
        $record = ChangeOfNameApplication::query()->findOrFail($id);
        return response()->json(['success' => true, 'data' => $record]);
    }

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

        $data = $validator->validated();
        $data['captured_by'] = Auth::id();
        $data['status']      = ChangeOfNameApplication::STATUS_PENDING;

        // Build full name from parts
        $data['new_full_name'] = $this->buildFullName($data);

        $record = ChangeOfNameApplication::create($data);

        // Save comment to pra table for the matching file number
        if (!empty($data['comment']) && !empty($data['file_no'])) {
            DB::connection('sqlsrv')->table('pra')
                ->where('fileno', $data['file_no'])
                ->update(['comments' => $data['comment']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully. Awaiting approval.',
            'data'    => $record,
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $record = ChangeOfNameApplication::query()->findOrFail($id);

        $record->update([
            'status'     => ChangeOfNameApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        // Update the FileName in fileNumber table
        $this->updateFileNumberTable($record);

        // Update file_indexings
        $this->updateFileIndexings($record);

        // Update customers_staging
        $this->updateCustomersStaging($record);

        // Update entities_staging
        $this->updateEntitiesStaging($record);

        return response()->json(['success' => true, 'message' => 'Application approved. File name updated across all related records.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfNameApplication::query()->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $record->update([
            'status'     => ChangeOfNameApplication::STATUS_REJECTED,
            'remarks'    => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function printAcknowledgement(int $id): View
    {
        $record = ChangeOfNameApplication::query()->findOrFail($id);

        return view('change_of_name.print.acknowledgement', compact('record'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = ChangeOfNameApplication::query()->findOrFail($id);

        $rules = $this->rules();
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
        $data['new_full_name'] = $this->buildFullName($data);
        $record->update($data);

        return response()->json(['success' => true, 'message' => 'Record updated.', 'data' => $record->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = ChangeOfNameApplication::findOrFail($id);
        
        if ($record->status === ChangeOfNameApplication::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update(['is_deleted' => 1]);
        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
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
                $q->where('mlsfNo',          'LIKE', "%{$term}%")
                  ->orWhere('kangisFileNo',   'LIKE', "%{$term}%")
                  ->orWhere('NewKANGISFileNo','LIKE', "%{$term}%")
                  ->orWhere('FileName',       'LIKE', "%{$term}%");
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

    // --- Private helpers ---

    private function buildFullName(array $data): string
    {
        $parts = array_filter([
            $data['title'] ?? '',
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
        ]);

        return strtoupper(implode(' ', $parts));
    }

    /**
     * Update FileName in the fileNumber table.
     */
    private function updateFileNumberTable(ChangeOfNameApplication $record): void
    {
        $fileNo = trim($record->file_no ?? '');
        if (!$fileNo) return;

        $db = DB::connection('sqlsrv');

        // Find the fileNumber row by any of the file number columns
        $row = $db->table('fileNumber')
            ->where(function ($q) use ($fileNo) {
                $q->where('mlsfNo', $fileNo)
                  ->orWhere('kangisFileNo', $fileNo)
                  ->orWhere('NewKANGISFileNo', $fileNo);
            })
            ->first();

        if ($row) {
            $db->table('fileNumber')
                ->where('id', $row->id)
                ->update([
                    'FileName' => $record->new_full_name,
                ]);
        }
    }

    /**
     * Update file_indexings rows that match the file number.
     */
    private function updateFileIndexings(ChangeOfNameApplication $record): void
    {
        $fileNo = trim($record->file_no ?? '');
        if (!$fileNo) return;

        DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNo)
            ->update([
                'current_holder' => $record->new_full_name,
                'updated_by'     => Auth::id(),
            ]);
    }

    /**
     * Update customers_staging rows that match the file number.
     */
    private function updateCustomersStaging(ChangeOfNameApplication $record): void
    {
        $fileNo = trim($record->file_no ?? '');
        if (!$fileNo) return;

        $db = DB::connection('sqlsrv');

        // Check if table and columns exist before updating
        try {
            $db->table('customers_staging')
                ->where('file_number', $fileNo)
                ->update([
                    'customer_name' => $record->new_full_name,
                ]);
        } catch (\Throwable $e) {
            // Table or column may not exist — skip gracefully
        }
    }

    /**
     * Update entities_staging rows that match the file number.
     */
    private function updateEntitiesStaging(ChangeOfNameApplication $record): void
    {
        $fileNo = trim($record->file_no ?? '');
        if (!$fileNo) return;

        $db = DB::connection('sqlsrv');

        try {
            $db->table('entities_staging')
                ->where('file_number', $fileNo)
                ->update([
                    'entity_name' => $record->new_full_name,
                ]);
        } catch (\Throwable $e) {
            // Table or column may not exist — skip gracefully
        }
    }

    private function rules(): array
    {
        return [
            'file_no'               => 'required|string|max:255',
            'current_name'          => 'nullable|string|max:500',
            'title'                 => 'nullable|string|max:50',
            'first_name'            => 'required|string|max:255',
            'middle_name'           => 'nullable|string|max:255',
            'last_name'             => 'required|string|max:255',
            'land_use'              => 'nullable|string|max:255',
            'location'              => 'nullable|string|max:2000',
            'plot_no'               => 'nullable|string|max:100',
            'plan_no'               => 'nullable|string|max:100',
            'phone'                 => 'nullable|string|max:50',
            'residential_address'   => 'nullable|string|max:2000',
            'comment'               => 'required|string|max:2000',
        ];
    }
}
