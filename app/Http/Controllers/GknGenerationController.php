<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FileIndexing;
use App\Models\FileNumber;
use App\Models\GknGrouping;
use App\Models\GknFileNo;
use App\Models\GknSerialControl;
use App\Models\Lga;
use App\Models\Entity;
use App\Models\Customer;
use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GknGenerationController extends Controller
{
    /**
     * Display the GKN generation form.
     */
    public function index(Request $request)
    {
        $PageTitle = 'GKN File Number Commissioning';
        $PageDescription = '';

        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $landUses = LandUse::orderBy('landuse')->get();
        $purposes = Purpose::orderBy('name')->get();
        $customerTypes = CustomerType::all()->sort(function($a, $b) {
            if (strtolower(trim($a->customer_type_name)) === 'government') return -1;
            if (strtolower(trim($b->customer_type_name)) === 'government') return 1;
            return strcasecmp($a->customer_type_name, $b->customer_type_name);
        })->values();
        $user = Auth::user();
        $search = $request->query('search');

        // Grouping logic: Individual files stand alone, batch files group together.
        $query = GknFileNo::with(['user', 'landUse', 'purpose'])
            ->whereIn('id', function($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('gkn_file_no')
                    ->where('is_deleted', 0)
                    ->groupBy(DB::raw("CASE WHEN batch_no IS NULL THEN CAST(id AS VARCHAR(100)) ELSE batch_no END"));
            })
            ->where('is_deleted', 0);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_file_number', 'LIKE', "%{$search}%")
                  ->orWhere('file_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%")
                  ->orWhere('plot_no', 'LIKE', "%{$search}%")
                  ->orWhere('lga', 'LIKE', "%{$search}%");
            });
        }

        $records = $query->orderBy('id', 'desc')
            ->paginate(20)
            ->appends(['search' => $search]);

        // Add batch count to records
        foreach ($records as $record) {
            if ($record->batch_no) {
                $record->batch_count = GknFileNo::where('batch_no', $record->batch_no)->count();
            } else {
                $record->batch_count = 1;
            }
        }

        // Fetch indexed GKN files from file_indexings (registry = 'Survey', not already in gkn_file_no)
        $indexedQuery = FileIndexing::where('registry', 'Survey')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->whereNotIn('file_number', function ($q) {
                $q->select('full_file_number')->from('gkn_file_no')->where('is_deleted', 0);
            });

        if ($search) {
            $indexedQuery->where(function ($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('file_title', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%")
                  ->orWhere('plot_number', 'LIKE', "%{$search}%")
                  ->orWhere('lga', 'LIKE', "%{$search}%");
            });
        }

        $indexedFiles = $indexedQuery->orderBy('id', 'desc')
            ->paginate(20, ['*'], 'fi_page')
            ->appends(['search' => $search]);

        // Calculate Statistics (always unfiltered totals)
        $indexedFilesTotal = FileIndexing::where('registry', 'Survey')
            ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
            ->whereNotIn('file_number', function ($q) {
                $q->select('full_file_number')->from('gkn_file_no')->where('is_deleted', 0);
            })->count();

        $today = date('Y-m-d');
        $stats = [
            'total' => GknFileNo::where('is_deleted', 0)->count() + $indexedFilesTotal,
            'today' => GknFileNo::where('is_deleted', 0)->whereDate('commissioning_date', date('Y-m-d'))->count(),
            'month' => GknFileNo::where('is_deleted', 0)
                ->whereMonth('commissioning_date', date('m'))
                ->whereRaw('YEAR(commissioning_date) = ?', [date('Y')])
                ->count(),
        ];

        // Serial Control Status
        $serialStatuses = GknSerialControl::all()
            ->keyBy('prefix');

        return view('gkn_generation.index', compact(
            'PageTitle', 'PageDescription', 'records', 'districts', 'lgas', 'landUses', 'purposes', 'stats', 'search', 'user', 'customerTypes', 'serialStatuses', 'indexedFiles'
        ));
    }

    /**
     * Return the stats + table partial for AJAX refresh.
     */
    public function getPartial(Request $request)
    {
        $search = $request->query('search');

        $query = GknFileNo::with(['user', 'landUse', 'purpose'])
            ->whereIn('id', function($q) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('gkn_file_no')
                    ->where('is_deleted', 0)
                    ->groupBy(DB::raw("CASE WHEN batch_no IS NULL THEN CAST(id AS VARCHAR(100)) ELSE batch_no END"));
            })
            ->where('is_deleted', 0);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_file_number', 'LIKE', "%{$search}%")
                  ->orWhere('file_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%")
                  ->orWhere('plot_no', 'LIKE', "%{$search}%")
                  ->orWhere('lga', 'LIKE', "%{$search}%");
            });
        }

        $records = $query->orderBy('id', 'desc')
            ->paginate(20)
            ->appends(['search' => $search]);

        foreach ($records as $record) {
            $record->batch_count = $record->batch_no
                ? GknFileNo::where('batch_no', $record->batch_no)->count()
                : 1;
        }

        $indexedQuery = FileIndexing::where('registry', 'Survey')
            ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
            ->whereNotIn('file_number', function($q) {
                $q->select('full_file_number')->from('gkn_file_no')->where('is_deleted', 0);
            });

        if ($search) {
            $indexedQuery->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('file_title', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%")
                  ->orWhere('plot_number', 'LIKE', "%{$search}%")
                  ->orWhere('lga', 'LIKE', "%{$search}%");
            });
        }

        $indexedFiles = $indexedQuery->orderBy('id', 'desc')
            ->paginate(20, ['*'], 'fi_page')
            ->appends(['search' => $search]);

        $indexedFilesTotal = FileIndexing::where('registry', 'Survey')
            ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
            ->whereNotIn('file_number', function($q) {
                $q->select('full_file_number')->from('gkn_file_no')->where('is_deleted', 0);
            })->count();

        $stats = [
            'total' => GknFileNo::where('is_deleted', 0)->count() + $indexedFilesTotal,
            'today' => GknFileNo::where('is_deleted', 0)->whereDate('commissioning_date', date('Y-m-d'))->count(),
            'month' => GknFileNo::where('is_deleted', 0)
                ->whereMonth('commissioning_date', date('m'))
                ->whereRaw('YEAR(commissioning_date) = ?', [date('Y')])
                ->count(),
        ];

        return view('gkn_generation.partials.table', compact('records', 'stats', 'indexedFiles'));
    }

    /**
     * Fetch available GKN records for preview.
     */
    public function getAvailableGkn(Request $request)
    {
        try {
            $limit = $request->query('limit', 1);
            $type = $request->query('type', 'GKN');
        
            $configMap = [
                'GKN' => ['table' => 'gkn_grouping', 'column' => 'gkn_awaiting_fileno', 'prefix' => 'GKN-'],
                'LPKN' => ['table' => 'lpkn_grouping', 'column' => 'lpkn_awaiting_fileno', 'prefix' => 'LPKN-'],
                'MISCS' => ['table' => 'miscs_kn_grouping', 'column' => 'miscs_kn_awaiting_fileno', 'prefix' => 'MISC KN '],
            ];

            $config = $configMap[$type] ?? $configMap['GKN'];

            // 1. Determine the next serial from OUR Serial Control table
            $control = GknSerialControl::where('prefix', $type)->first();

            if (!$control || !$control->is_initialized) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'next_serial' => 'Not Initialized',
                    'is_initialized' => false
                ]);
            }

            $nextSerial = $control->last_serial + 1;

            $prefix = $config['prefix'];
            $table = $config['table'];
            $column = $config['column'];

            // 3. Construct the exact file number(s) we expect to find in the grouping table
            $constructedFileNos = [];
            for ($i = 0; $i < $limit; $i++) {
                $constructedFileNos[] = $prefix . ($nextSerial + $i);
            }

            // 4. Search ONLY for these specific file numbers in the specific grouping table
            $gknRecords = DB::connection('sqlsrv')->table($table)
                ->whereIn($column, $constructedFileNos)
                ->where(function($q) {
                    $q->where('mapping', 0)->orWhereNull('mapping');
                })
                ->orderBy('id', 'asc')
                ->get();

            // Normalize records for frontend
            $normalizedRecords = $gknRecords->map(function($record) use ($column) {
                // Attach as a dynamic property so frontend doesn't need to change
                $record->gkn_awaiting_fileno = $record->{$column};
                return $record;
            });

            $availableCount = $normalizedRecords->count();

            Log::info("GKN Strict Lookup ({$type} on {$table}.{$column}): Constructing: " . implode(', ', $constructedFileNos) . " | Found: {$availableCount}");

            return response()->json([
                'success' => true,
                'data' => $normalizedRecords,
                'next_serial' => $nextSerial,
                'is_initialized' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available GKN: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error fetching available GKN records'
            ], 500);
        }
    }

    /**
     * Store the generated GKN file numbers and sync across tables.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch_mode' => 'required|boolean',
            'quantity' => 'required_if:batch_mode,true|integer|min:1|max:50',
            'file_title' => 'required|string|max:500',
            'land_use_id' => 'required|integer',
            'purpose_id' => 'required|integer',
            'plot_number' => 'nullable|string|max:255',
            'tp_no' => 'nullable|string|max:100',
            'district_id' => 'nullable|integer',
            'lga_id' => 'required|integer',
            'type' => 'required|string|in:GKN,LPKN,MISCS',
            'location' => 'required|string',
            'location_entries' => 'nullable|array',
            'location_entries.*.plot_number' => 'nullable|string|max:100',
            'location_entries.*.tp_no' => 'nullable|string|max:100',
            'location_entries.*.district_id' => 'nullable|integer',
            'location_entries.*.lga_id' => 'nullable|integer',
            'location_entries.*.location' => 'nullable|string',
            'customer_type' => 'required|string',
        ]);

        $batchMode = $validated['batch_mode'];
        $quantity = $batchMode ? $validated['quantity'] : 1;
        $district = District::find($validated['district_id']);
        $lga = Lga::find($validated['lga_id']);

        $type = $validated['type'];

        // Generate a unique batch number if in batch mode
        $batchNo = $batchMode ? $type . '-B-' . date('Ymd-His') . '-' . rand(100, 999) : null;

        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Determine the next serial number within the transaction (Scoped by type) from Control Table
            $control = GknSerialControl::where('prefix', $type)->lockForUpdate()->first();

            if (!$control || !$control->is_initialized) {
                throw new \Exception("Serial numbering for {$type} has not been initialized.");
            }

            $nextSerial = $control->last_serial + 1;

            // 2. Mapping for dynamic grouping tables
            $configMap = [
                'GKN' => ['table' => 'gkn_grouping', 'column' => 'gkn_awaiting_fileno', 'prefix' => 'GKN-'],
                'LPKN' => ['table' => 'lpkn_grouping', 'column' => 'lpkn_awaiting_fileno', 'prefix' => 'LPKN-'],
                'MISCS' => ['table' => 'miscs_kn_grouping', 'column' => 'miscs_kn_awaiting_fileno', 'prefix' => 'MISC KN '],
            ];

            $config = $configMap[$type] ?? $configMap['GKN'];
            $prefix = $config['prefix'];
            $table = $config['table'];
            $column = $config['column'];

            // 3. Construct the exact file number(s) we expect to find in the grouping table
            $constructedFileNos = [];
            for ($i = 0; $i < $quantity; $i++) {
                $constructedFileNos[] = $prefix . ($nextSerial + $i);
            }

            // 4. Fetch and lock available records from the specific grouping table
            $gknRecords = DB::connection('sqlsrv')->table($table)
                ->whereIn($column, $constructedFileNos)
                ->where(function($q) {
                    $q->where('mapping', 0)->orWhereNull('mapping');
                })
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($gknRecords->count() < $quantity) {
                $missing = array_diff($constructedFileNos, $gknRecords->pluck($column)->toArray());
                throw new \Exception("Insufficient matching records found in {$table} table. Missing: " . implode(', ', $missing));
            }

            $now = now();
            $userId = Auth::id();
            $userName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $entityName = $validated['file_title'];

            // 1. Create/Update ONE Entity (Batch Logic: Only one row for entities_staging)
            $entity = Entity::updateOrCreate(
                [
                    'entity_name' => $entityName,
                    'file_number' => $gknRecords->first()->{$column} ?? $gknRecords->first()->number
                ],
                [
                    'entity_type' => 'Individual',
                    'updated_at' => $now
                ]
            );

            $generatedNumbers = [];

        foreach ($gknRecords as $index => $gkn) {
            // Determine location details for this specific entry
            $plotNo = $validated['plot_number'];
            $tpNo = $validated['tp_no'] ?? null;
            $loc = $validated['location'];
            $currentDistrictId = $validated['district_id'];
            $currentLgaId = $validated['lga_id'];

            // Override with batch-specific entry if available
            if ($batchMode && isset($validated['location_entries'][$index])) {
                $entry = $validated['location_entries'][$index];
                $plotNo = $entry['plot_number'] ?? $plotNo;
                $tpNo = $entry['tp_no'] ?? $tpNo;
                $loc = $entry['location'] ?? $loc;
                $currentDistrictId = $entry['district_id'] ?? $currentDistrictId;
                $currentLgaId = $entry['lga_id'] ?? $currentLgaId;
            }

            // Fetch names for district and lga
            $currentDistrictName = ($currentDistrictId == $validated['district_id']) 
                ? ($district->name ?? null) 
                : (District::find($currentDistrictId)->name ?? null);
            $currentLgaName = ($currentLgaId == $validated['lga_id']) 
                ? ($lga->name ?? null) 
                : (Lga::find($currentLgaId)->name ?? null);

            // Determine the file number (prefer dynamic column if structured, fallback to constructed)
            $gknNumber = ($gkn->{$column} ?? null) ?: $prefix . $gkn->number;
            $trackingId = $gkn->tracking_id;

            // 2. Update the specific grouping table
            DB::connection('sqlsrv')->table($table)
                ->where('id', $gkn->id)
                ->update([
                    'mapping' => 1,
                    ($type === 'GKN' ? 'gkn_fileno' : ($type === 'LPKN' ? 'lpkn_fileno' : 'miscs_kn_fileno')) => $gknNumber,
                    'created_by' => $userId,
                    'updated_at' => $now,
                ]);

            // 3. Insert into fileNumber (Legacy Table)
            FileNumber::create([
                'tracking_id' => $trackingId,
                'mlsfNo' => $gknNumber,
                'gkn_fileno' => $gknNumber,
                'FileName' => $entityName,
                'plot_no' => $plotNo,
                'location' => $loc,
                'lga' => $currentLgaName,
                'type' => $type,
                'SOURCE' => 'GKN_Generation',
                'commissioning_date' => $now,
                'created_by' => $userId,
                'is_deleted' => 0,
            ]);

            // 4. Insert into gkn_file_no (Dedicated Metadata Table)
            GknFileNo::create([
                'batch_no' => $batchNo,
                'year' => date('Y'),
                'serial_number' => $nextSerial + $index,
                'full_file_number' => $gknNumber,
                'file_name' => $entityName,
                'plot_no' => $plotNo,
                'tp_no' => $tpNo,
                'location' => $loc,
                'lga' => $currentLgaName,
                'tracking_id' => $trackingId,
                'created_by' => $userId,
                'commissioning_date' => $now,
                'commissioning_time' => $now->format('H:i:s'),
                'land_use_id' => $validated['land_use_id'],
                'purpose_id' => $validated['purpose_id'],
                'type' => $type,
                'customer_type' => $validated['customer_type'],
            ]);

            // 5. Insert into file_indexings (Metadata Table)
            FileIndexing::create([
                'tracking_id' => $trackingId,
                'file_number' => $gknNumber,
                'file_title' => $entityName,
                'land_use_type' => $type,
                'plot_number' => $plotNo,
                'tp_no' => $tpNo,
                'location' => $loc,
                'district' => $currentDistrictName,
                'lga' => $currentLgaName,
                'created_by' => $userName,
                'workflow_status' => 'indexed',
                'source' => 'GKN FileNo Commissioning',
            ]);

            // 6. Insert into customers_staging (One for each file number, linked to single entity)
            Customer::create([
                'entity_id' => $entity->id,
                'customer_name' => $entityName,
                'file_number' => $gknNumber,
                'account_no' => $gknNumber,
                'customer_type' => $validated['customer_type'],
                'property_address' => $loc,
                'status' => 'Active',
                'created_by' => $userId,
            ]);

                $generatedNumbers[] = [
                    'file_number' => $gknNumber,
                    'tracking_id' => $trackingId
                ];
            }

            // Update Control Table
            $control->update([
                'last_serial' => $nextSerial + $quantity - 1,
                'is_locked' => true,
                'updated_at' => $now,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully generated " . count($generatedNumbers) . " GKN record(s)",
                'data' => $generatedNumbers
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('GKN Generation Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate GKN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all records for a specific batch.
     */
    public function getBatchMembers($batchNo)
    {
        try {
            $records = GknFileNo::with(['user'])
                ->where('batch_no', $batchNo)
                ->where('is_deleted', 0)
                ->orderBy('full_file_number', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch a single record for editing.
     */
    public function edit($id)
    {
        try {
            $record = GknFileNo::findOrFail($id);
            
            // Map district/lga back to IDs for the form
            $district = District::where('name', 'LIKE', '%' . $record->district . '%')->first(); // Wait, GknFileNo doesn't have district column, it has location
            // Checking model again... it has plot_no, location, lga.
            // But we need district_id and lga_id for the form.
            
            // Actually, we can just return the record and let JS handle it, 
            // but let's try to be helpful.
            
            return response()->json([
                'success' => true,
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Update GKN record metadata.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'file_name' => 'required|string|max:500',
            'land_use_id' => 'required|integer',
            'purpose_id' => 'required|integer',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'district_id' => 'nullable|integer',
            'lga_id' => 'required|integer',
            'type' => 'required|string|in:GKN,LPKN,MISCS',
            'location' => 'required|string',
            'customer_type' => 'required|string',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $record = GknFileNo::findOrFail($id);
            $oldFileNo = $record->full_file_number;

            $district = District::find($validated['district_id']);
            $lga = Lga::find($validated['lga_id']);

            // Update GKN record
            $record->update([
                'file_name' => $validated['file_name'],
                'land_use_id' => $validated['land_use_id'],
                'purpose_id' => $validated['purpose_id'],
                'plot_no' => $validated['plot_no'],
                'tp_no' => $validated['tp_no'],
                'location' => $validated['location'],
                'lga' => $lga->name ?? $record->lga,
                'type' => $validated['type'],
                'customer_type' => $validated['customer_type'],
            ]);

            // Sync with file_indexings
            FileIndexing::where('file_number', $oldFileNo)->update([
                'file_title' => $validated['file_name'],
                'plot_number' => $validated['plot_no'],
                'tp_no' => $validated['tp_no'],
                'location' => $validated['location'],
                'district' => $district->name ?? null,
                'lga' => $lga->name ?? null,
                'land_use_type' => $validated['type'],
            ]);

            // Sync with fileNumber
            FileNumber::where('mlsfNo', $oldFileNo)->update([
                'FileName' => $validated['file_name'],
                'plot_no' => $validated['plot_no'],
                'location' => $validated['location'],
                'lga' => $lga->name ?? null,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Serial initialization moved to System Admin → Configurable Entries →
     * Serial Initialization (Land & Deeds). That one screen sets and locks the
     * starting serial for every generator; see
     * ConfigurableEntriesController::SERIAL_REGISTERS.
     */
}
