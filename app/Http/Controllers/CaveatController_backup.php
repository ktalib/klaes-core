<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Caveat;
use App\Models\InstrumentType;

class CaveatController extends Controller
{
    public function index()
    {
        $PageTitle = "Caveat Records";
        $PageDescriptionS = "";

        // Get active instrument types for the dropdown
        $instrumentTypes = InstrumentType::active()
            ->select('InstrumentTypeID', 'InstrumentName', 'Description')
            ->orderBy('InstrumentName')
            ->get();

        return view('caveat.index', compact('PageTitle', 'PageDescriptionS', 'instrumentTypes'));
    }

    // Create/place a new caveat
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encumbrance_type' => 'required|string|max:100',
            'instrument_type_id' => 'nullable|integer|exists:sqlsrv.InstrumentTypes,InstrumentTypeID',
            'file_number' => 'required|string|max:100', // Made required for business rule validation
            'file_number_id' => 'nullable|integer', // FK to fileNumber or file_numbers
            'file_number_type' => 'nullable|string|max:50',
            'location' => 'required|string|max:255',
            'petitioner' => 'required|string|max:255',
            'petitioner_address' => 'nullable|string|max:500',
            'grantee' => 'nullable|string|max:255', // Frontend field name
            'grantee_address' => 'nullable|string|max:500',
            'serial_no' => 'nullable|integer|min:1',
            'page_no' => 'nullable|integer|min:1',
            'volume_no' => 'nullable|integer|min:1',
            'registration_number' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'release_date' => 'nullable|date',
            'instructions' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        // Business Rule: Check if record exists in any of the 3 tables before allowing caveat
        $fileNumber = $validated['file_number'];
        $recordExists = $this->checkRecordExists($fileNumber);
        
        if (!$recordExists) {
            return response()->json([
                'success' => false,
                'error' => 'Record not found. Please create the record first before placing a caveat.',
                'error_type' => 'record_not_found'
            ], 422);
        }

        // Business Rule: Check for duplicate caveat placement
        $duplicateCheck = $this->checkDuplicateCaveat($fileNumber, $validated);
        
        if ($duplicateCheck['isDuplicate']) {
            return response()->json([
                'success' => false,
                'error' => $duplicateCheck['message'],
                'error_type' => 'duplicate_caveat',
                'existing_caveat' => $duplicateCheck['existingCaveat']
            ], 422);
        }

        // Resolve file_number -> id/type if not provided
        if (empty($validated['file_number_id']) && !empty($validated['file_number'])) {
            [$resolvedId, $resolvedType] = $this->resolveFileNumber($validated['file_number']);
            if ($resolvedId) {
                $validated['file_number_id'] = $resolvedId;
            }
            if ($resolvedType && empty($validated['file_number_type'])) {
                $validated['file_number_type'] = $resolvedType;
            }
        }

        // Store file number in appropriate field based on type
        $this->setFileNumberFields($validated, $fileNumber);

        // Generate caveat number if not provided
        $caveatNumber = $request->input('caveat_number');
        if (!$caveatNumber) {
            $caveatNumber = 'CAV-' . date('Y') . '-' . str_pad((string) (Caveat::count() + 1), 4, '0', STR_PAD_LEFT);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $caveat = new Caveat();
            
            // Map frontend field names to database column names
            $caveatData = $validated;
            if (isset($caveatData['grantee'])) {
                $caveatData['grantee_name'] = $caveatData['grantee'];
                unset($caveatData['grantee']); // Remove the frontend field name
            }
            
            $caveat->fill($caveatData);
            $caveat->caveat_number = $caveatNumber;
            $caveat->status = $request->input('status', 'active');
            $caveat->created_by = Auth::user()->name ?? 'System';
            $caveat->updated_by = Auth::user()->name ?? 'System';

            // Default page_no to serial_no if not provided
            if (empty($caveat->page_no) && !empty($caveat->serial_no)) {
                $caveat->page_no = $caveat->serial_no;
            }

            // Compose registration number if parts are present and not provided
            if (empty($caveat->registration_number) && $caveat->serial_no && $caveat->page_no && $caveat->volume_no) {
                $caveat->registration_number = $caveat->serial_no . '/' . $caveat->page_no . '/' . $caveat->volume_no;
            }

            $caveat->save();

            // Apply caveated flags to related tables and set caveat_id
            if (!empty($validated['file_number'])) {
                $comment = 'Caveated via ' . ($validated['encumbrance_type'] ?? 'encumbrance') . ' (Caveat No: ' . $caveat->caveat_number . ') on ' . date('Y-m-d');
                $this->setCaveatedFlags($validated['file_number'], true, $comment, $caveat->id);
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $caveat,
        ]);
    }

    // Search/list caveats with filters
    public function indexApi(Request $request)
    {
        $query = Caveat::query();

        // Filters
        if ($request->filled('q')) {
            $search = '%' . $request->input('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('caveat_number', 'like', $search)
                  ->orWhere('registration_number', 'like', $search)
                  ->orWhere('petitioner', 'like', $search)
                  ->orWhere('grantee_name', 'like', $search)
                  ->orWhere('location', 'like', $search)
                  ->orWhere('encumbrance_type', 'like', $search);
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('start_date', '<=', $request->input('to_date'));
        }

        $caveats = $query->orderByDesc('start_date')->limit(200)->get();

        return response()->json([
            'success' => true,
            'data' => $caveats,
            'count' => $caveats->count(),
        ]);
    }

    // Get a single caveat
    public function show($id)
    {
        $caveat = Caveat::findOrFail($id);
        return response()->json(['success' => true, 'data' => $caveat]);
    }

    // Get caveat statistics
    public function stats()
    {
        try {
            $total = Caveat::count();
            $active = Caveat::where('status', 'active')->count();
            $released = Caveat::where('status', 'released')->count();
            $lifted = Caveat::where('status', 'lifted')->count();
            $draft = Caveat::where('status', 'draft')->count();
            $expired = Caveat::where('status', 'expired')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'released' => $released,
                    'lifted' => $lifted,
                    'draft' => $draft,
                    'expired' => $expired
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load statistics',
                'data' => [
                    'total' => 0,
                    'active' => 0,
                    'released' => 0,
                    'lifted' => 0,
                    'draft' => 0,
                    'expired' => 0
                ]
            ]);
        }
    }

    // Lift (release) a caveat: set status and release fields
    public function lift(Request $request, $id)
    {
        $caveat = Caveat::findOrFail($id);

        $validated = $request->validate([
            'release_date' => 'required|date',
            'lift_instructions' => 'nullable|string',
            'lift_remarks' => 'nullable|string',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $caveat->release_date = $validated['release_date'];
            // Append lifting notes into main narrative fields (simple model; can be normalized later)
            if (!empty($validated['lift_instructions'])) {
                $caveat->instructions = trim(($caveat->instructions ? $caveat->instructions . "\n\n" : '') . 'LIFT INSTRUCTIONS: ' . $validated['lift_instructions']);
            }
            if (!empty($validated['lift_remarks'])) {
                $caveat->remarks = trim(($caveat->remarks ? $caveat->remarks . "\n\n" : '') . 'LIFT REMARKS: ' . $validated['lift_remarks']);
            }
            $caveat->status = 'released';
            $caveat->updated_by = Auth::user()->name ?? 'System';
            $caveat->save();

            // Try to load file number string from fileNumber tables using stored id
            $fileNo = null;
            if (!empty($caveat->file_number_id)) {
                // Try dbo.fileNumber first
                try {
                    $row = DB::connection('sqlsrv')->table('fileNumber')->where('id', $caveat->file_number_id)->first();
                    if ($row) {
                        $fileNo = $row->kangisFileNo ?: ($row->mlsfNo ?: ($row->NewKANGISFileNo ?: null));
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                // Fallback to file_numbers
                if (!$fileNo) {
                    try {
                        $row = DB::connection('sqlsrv')->table('file_numbers')->where('id', $caveat->file_number_id)->first();
                        if ($row) {
                            $fileNo = $row->file_number;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            if ($fileNo) {
                $comment = 'Caveat lifted (Caveat No: ' . $caveat->caveat_number . ') on ' . date('Y-m-d');
                $this->setCaveatedFlags($fileNo, false, $comment, null);
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'data' => $caveat]);
    }

    private function resolveFileNumber(string $fileNo): array
    {
        $conn = DB::connection('sqlsrv');
        // Try dbo.fileNumber table (preferred)
        try {
            $row = $conn->table('fileNumber')
                ->select('id', 'kangisFileNo', 'mlsfNo', 'NewKANGISFileNo')
                ->where('kangisFileNo', $fileNo)
                ->orWhere('mlsfNo', $fileNo)
                ->orWhere('NewKANGISFileNo', $fileNo)
                ->first();
            if ($row) {
                $type = $row->kangisFileNo ? 'KANGIS' : ($row->NewKANGISFileNo ? 'New KANGIS' : 'MLSF');
                return [$row->id, $type];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        // Fallback to file_numbers table
        try {
            $row = $conn->table('file_numbers')->select('id', 'file_type')->where('file_number', $fileNo)->first();
            if ($row) {
                return [$row->id, $row->file_type];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return [null, null];
    }

    private function setCaveatedFlags(string $fileNo, bool $isCaveated, string $comment, ?int $caveatId = null): void
    {
        $conn = DB::connection('sqlsrv');

        // property_records: match on common file number columns
        try {
            $conn->table('property_records')
                ->where(function ($q) use ($fileNo) {
                    $q->where('mlsFNo', $fileNo)
                      ->orWhere('kangisFileNo', $fileNo)
                      ->orWhere('NewKANGISFileno', $fileNo);
                })
                ->update([
                    'is_caveated' => $isCaveated ? 1 : 0,
                    'caveat_id' => $caveatId,
                    'caveated_comment' => DB::raw($isCaveated
                        ? "CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . str_replace("'", "''", $comment) . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                        : "CASE WHEN caveated_comment IS NULL THEN NULL ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                    ),
                ]);
        } catch (\Throwable $e) {
            // ignore
        }

        // registered_instruments: try common columns
        try {
            $query = $conn->table('registered_instruments');
            // We don't know exact column, attempt common ones via OR
            $query->where(function ($q) use ($fileNo) {
                $q->where('file_number', $fileNo)
                  ->orWhere('file_no', $fileNo)
                  ->orWhere('fileno', $fileNo)
                  ->orWhere('mlsfNo', $fileNo)
                  ->orWhere('kangisFileNo', $fileNo)
                  ->orWhere('NewKANGISFileNo', $fileNo);
            })->update([
                'is_caveated' => $isCaveated ? 1 : 0,
                'caveat_id' => $caveatId,
                'caveated_comment' => DB::raw($isCaveated
                    ? "CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . str_replace("'", "''", $comment) . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                    : "CASE WHEN caveated_comment IS NULL THEN NULL ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                ),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        // CofO: try multiple possible table names and columns
        foreach (['CofO', 'cofo', 'CoFO', 'COFO'] as $tbl) {
            try {
                $exists = $conn->selectOne("SELECT 1 AS e FROM sys.tables WHERE name = ?", [$tbl]);
                if ($exists) {
                    $conn->table($tbl)
                        ->where(function ($q) use ($fileNo) {
                            $q->where('file_no', $fileNo)
                              ->orWhere('FileNo', $fileNo)
                              ->orWhere('NewFileNo', $fileNo)
                              ->orWhere('mlsfNo', $fileNo)
                              ->orWhere('kangisFileNo', $fileNo)
                              ->orWhere('NewKANGISFileNo', $fileNo);
                        })
                        ->update([
                            'is_caveated' => $isCaveated ? 1 : 0,
                            'caveat_id' => $caveatId,
                            'caveated_comment' => DB::raw($isCaveated
                                ? "CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . str_replace("'", "''", $comment) . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                                : "CASE WHEN caveated_comment IS NULL THEN NULL ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"
                            ),
                        ]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Check if a record exists in any of the 3 tables (property_records, CofO, registered_instruments)
     */
    private function checkRecordExists(string $fileNo): bool
    {
        $conn = DB::connection('sqlsrv');

        // Check property_records
        try {
            $exists = $conn->table('property_records')
                ->where(function ($q) use ($fileNo) {
                    $q->where('mlsFNo', $fileNo)
                      ->orWhere('kangisFileNo', $fileNo)
                      ->orWhere('NewKANGISFileno', $fileNo);
                })
                ->exists();
            if ($exists) return true;
        } catch (\Throwable $e) {
            // ignore
        }

        // Check registered_instruments
        try {
            $exists = $conn->table('registered_instruments')
                ->where(function ($q) use ($fileNo) {
                    $q->where('file_number', $fileNo)
                      ->orWhere('file_no', $fileNo)
                      ->orWhere('fileno', $fileNo)
                      ->orWhere('mlsfNo', $fileNo)
                      ->orWhere('kangisFileNo', $fileNo)
                      ->orWhere('NewKANGISFileNo', $fileNo);
                })
                ->exists();
            if ($exists) return true;
        } catch (\Throwable $e) {
            // ignore
        }

        // Check CofO (try multiple table name variations)
        foreach (['CofO', 'cofo', 'CoFO', 'COFO'] as $tbl) {
            try {
                $tableExists = $conn->selectOne("SELECT 1 AS e FROM sys.tables WHERE name = ?", [$tbl]);
                if ($tableExists) {
                    $exists = $conn->table($tbl)
                        ->where(function ($q) use ($fileNo) {
                            $q->where('file_no', $fileNo)
                              ->orWhere('FileNo', $fileNo)
                              ->orWhere('NewFileNo', $fileNo)
                              ->orWhere('mlsfNo', $fileNo)
                              ->orWhere('kangisFileNo', $fileNo)
                              ->orWhere('NewKANGISFileNo', $fileNo);
                        })
                        ->exists();
                    if ($exists) return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return false;
    }

    /**
     * Search for file number across property_records, registered_instruments, and CofO tables
     */
    public function searchFileNumber(Request $request)
    {
        $fileNumber = $request->input('file_number');
        
        if (!$fileNumber) {
            return response()->json([
                'success' => false,
                'error' => 'File number is required'
            ]);
        }

        try {
            $conn = DB::connection('sqlsrv');
            $found = false;
            $data = null;
            $table = null;

            // Check property_records first
            try {
                $record = $conn->table('property_records')
                    ->where(function ($q) use ($fileNumber) {
                        $q->where('mlsFNo', $fileNumber)
                          ->orWhere('kangisFileNo', $fileNumber)
                          ->orWhere('NewKANGISFileno', $fileNumber);
                    })
                    ->first();
                
                if ($record) {
                    $found = true;
                    $data = $record;
                    $table = 'property_records';
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // Check registered_instruments if not found
            if (!$found) {
                try {
                    $record = $conn->table('registered_instruments')
                        ->where(function ($q) use ($fileNumber) {
                            $q->where('file_number', $fileNumber)
                              ->orWhere('file_no', $fileNumber)
                              ->orWhere('fileno', $fileNumber)
                              ->orWhere('mlsfNo', $fileNumber)
                              ->orWhere('kangisFileNo', $fileNumber)
                              ->orWhere('NewKANGISFileNo', $fileNumber);
                        })
                        ->first();
                    
                    if ($record) {
                        $found = true;
                        $data = $record;
                        $table = 'registered_instruments';
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Check CofO tables if not found
            if (!$found) {
                foreach (['CofO', 'cofo', 'CoFO', 'COFO'] as $tbl) {
                    try {
                        $tableExists = $conn->selectOne("SELECT 1 AS e FROM sys.tables WHERE name = ?", [$tbl]);
                        if ($tableExists) {
                            $record = $conn->table($tbl)
                                ->where(function ($q) use ($fileNumber) {
                                    $q->where('file_no', $fileNumber)
                                      ->orWhere('FileNo', $fileNumber)
                                      ->orWhere('NewFileNo', $fileNumber)
                                      ->orWhere('mlsfNo', $fileNumber)
                                      ->orWhere('kangisFileNo', $fileNumber)
                                      ->orWhere('NewKANGISFileNo', $fileNumber);
                                })
                                ->first();
                            
                            if ($record) {
                                $found = true;
                                $data = $record;
                                $table = $tbl;
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            return response()->json([
                'success' => true,
                'found' => $found,
                'data' => $data,
                'table' => $table
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new property record
     */
    public function createPropertyRecord(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'grantor' => 'nullable|string|max:255',
            'grantee' => 'nullable|string|max:255',
            'instrument_type' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date',
            'property_description' => 'nullable|string|max:500'
        ]);

        try {
            $conn = DB::connection('sqlsrv');
            
            // Determine file number type and set appropriate field
            $fileNumberFields = $this->getFileNumberFields($validated['file_number']);
            
            // Insert into property_records
            $recordId = $conn->table('property_records')->insertGetId([
                'mlsFNo' => $fileNumberFields['mlsf'] ?: null,
                'kangisFileNo' => $fileNumberFields['kangis'] ?: null,
                'NewKANGISFileno' => $fileNumberFields['new_kangis'] ?: null,
                'property_description' => $validated['location'] ?: $validated['property_description'],
                'Grantor' => $validated['grantor'],
                'Grantee' => $validated['grantee'],
                'transaction_type' => $validated['instrument_type'],
                'transaction_date' => $validated['transaction_date'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Get the created record
            $record = $conn->table('property_records')->find($recordId);

            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => 'Property record created successfully'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create property record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file number fields based on pattern
     */
    private function getFileNumberFields(string $fileNumber): array
    {
        $fields = [
            'mlsf' => null,
            'kangis' => null,
            'new_kangis' => null
        ];

        if (preg_match('/^RES-\d{4}-\d+/', $fileNumber)) {
            // MLSF file number (format: RES-YYYY-NNNN)
            $fields['mlsf'] = $fileNumber;
        } elseif (preg_match('/^NK\d+/', $fileNumber)) {
            // New KANGIS file number (starts with NK)
            $fields['new_kangis'] = $fileNumber;
        } elseif (preg_match('/^KN\d+/', $fileNumber)) {
            // KANGIS file number (starts with KN)
            $fields['kangis'] = $fileNumber;
        } else {
            // Default to KANGIS if pattern doesn't match
            $fields['kangis'] = $fileNumber;
        }

        return $fields;
    }

    /**
     * Check for duplicate caveat placement
     * A caveat is considered duplicate if:
     * 1. Same file number has an active caveat
     * 2. Same file number + encumbrance type has active caveat
     * 3. Same petitioner + file number has active caveat
     */
    private function checkDuplicateCaveat(string $fileNumber, array $validated): array
    {
        // Check for existing active caveats on the same file number
        $existingActiveCaveats = $this->findExistingCaveats($fileNumber, ['active']);
        
        if ($existingActiveCaveats->isNotEmpty()) {
            $existingCaveat = $existingActiveCaveats->first();
            
            // Check for exact duplicate (same file number + same encumbrance type + same petitioner)
            $exactDuplicate = $existingActiveCaveats->where('encumbrance_type', $validated['encumbrance_type'])
                                                   ->where('petitioner', $validated['petitioner'])
                                                   ->first();
            
            if ($exactDuplicate) {
                return [
                    'isDuplicate' => true,
                    'message' => 'Duplicate caveat detected. An identical caveat (same file number, encumbrance type, and petitioner) already exists and is active.',
                    'existingCaveat' => $exactDuplicate
                ];
            }
            
            // Check for same file number + same encumbrance type (different petitioner)
            $sameEncumbrance = $existingActiveCaveats->where('encumbrance_type', $validated['encumbrance_type'])->first();
            
            if ($sameEncumbrance) {
                return [
                    'isDuplicate' => true,
                    'message' => 'A caveat with the same encumbrance type already exists on this file number. Caveat No: ' . $sameEncumbrance->caveat_number,
                    'existingCaveat' => $sameEncumbrance
                ];
            }
            
            // Check for same file number + same petitioner (different encumbrance)
            $samePetitioner = $existingActiveCaveats->where('petitioner', $validated['petitioner'])->first();
            
            if ($samePetitioner) {
                return [
                    'isDuplicate' => true,
                    'message' => 'You already have an active caveat on this file number. Caveat No: ' . $samePetitioner->caveat_number,
                    'existingCaveat' => $samePetitioner
                ];
            }
            
            // General check - multiple active caveats on same file (warning, but allow)
            // This could be a business decision - you might want to warn but still allow
            // For now, we'll be strict and not allow multiple active caveats on same file
            return [
                'isDuplicate' => true,
                'message' => 'This file number already has an active caveat. Please lift the existing caveat first. Caveat No: ' . $existingCaveat->caveat_number,
                'existingCaveat' => $existingCaveat
            ];
        }
        
        return [
            'isDuplicate' => false,
            'message' => null,
            'existingCaveat' => null
        ];
    }

    /**
     * Find existing caveats for a file number
     */
    private function findExistingCaveats(string $fileNumber, array $statuses = ['active', 'draft'])
    {
        $query = Caveat::whereIn('status', $statuses);
        
        // Search across all file number fields
        $query->where(function ($q) use ($fileNumber) {
            $q->where('file_number_kangis', $fileNumber)
              ->orWhere('file_number_mlsf', $fileNumber)
              ->orWhere('file_number_new_kangis', $fileNumber);
        });
        
        return $query->get();
    }
}
