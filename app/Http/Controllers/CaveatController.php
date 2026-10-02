<?php

namespace App\Http\Controllers;

use App\Rules\NigerianPhone;

use App\Exceptions\PropIdAllocationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Models\Caveat;  
use App\Models\InstrumentType;
use App\Services\PropertyIdAllocationService;

class CaveatController extends Controller
{
    /**
     * Cached column listings per table to avoid repeated INFORMATION_SCHEMA lookups.
     * @var array<string, array<string>>
     */
    protected array $tableColumnCache = [];

    private PropertyIdAllocationService $propertyIdAllocationService;

    public function __construct(PropertyIdAllocationService $propertyIdAllocationService)
    {
        $this->propertyIdAllocationService = $propertyIdAllocationService;
    }

    public function index()
    {
        $PageTitle = "Caveat Records";
        $PageDescriptionS = "";

        // Get active instrument types for the dropdown
        // Business rule: exclude "Deed of Surrender and Release" from caveat dropdowns
        $instrumentTypes = InstrumentType::active()
            ->select('InstrumentTypeID', 'InstrumentName', 'Description')
            ->whereRaw("LOWER(REPLACE(REPLACE(InstrumentName, '&', 'and'), '  ', ' ')) NOT IN (?, ?, ?)", [
                'deed of surrender and release',
                'deed of surrender & release',
                'deed of surrender, and release',
            ])
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
            'file_number' => 'required|string|max:100',
            'caveat_number' => 'nullable|string|max:50',
            'caveat_number_scheme' => 'nullable|string|in:CAV,ADD-CAV',
            'source_table' => 'nullable|string|in:pra,file_history_staging,deed_registrations,CofO_staging',
            'source_id' => 'nullable|integer|min:1',
            'file_number_id' => 'nullable|integer',
            'file_number_type' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'petitioner' => 'required|string|max:255',
            // The caveator's own number, for the placed/lifted messages. caveats
            // recorded a petitioner and their address and no way to reach them
            // until 2026_09_06_000300 added this column.
            'petitioner_phone' => ['required', 'string', 'max:100', new NigerianPhone()],
            'phone_confirmed' => 'nullable|boolean',
            'petitioner_address' => 'nullable|string|max:500',
            'grantee' => 'nullable|string|max:255',
            'grantee_address' => 'nullable|string',
            'serial_no' => 'nullable|string',
            'page_no' => 'nullable|string',
            'volume_no' => 'nullable|string',
            'registration_number' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'release_date' => 'nullable|date',
            'instructions' => 'nullable|string',
            'remarks' => 'nullable|string',
            'is_expired' => 'nullable|boolean',
            'bypass_record_check' => 'nullable|boolean',
        ]);

        $isExpired = (bool) $request->input('is_expired', false);
        $bypassRecordCheck = (bool) $request->input('bypass_record_check', false);
        $targetSourceTable = $validated['source_table'] ?? null;
        $targetSourceId = isset($validated['source_id']) ? (int) $validated['source_id'] : null;

        // Business Rule: Check if record exists (skip when caller already verified, e.g. Legal Search)
        $fileNumber = $validated['file_number'];
        if (!$bypassRecordCheck) {
            $recordExists = $this->checkRecordExists($fileNumber);
            if (!$recordExists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Record not found. Please create the record first before placing a caveat.',
                    'error_type' => 'record_not_found'
                ], 422);
            }
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

        $primaryFileIdentifier = $this->firstNonEmpty([
            $validated['file_number_kangis'] ?? null,
            $validated['file_number_new_kangis'] ?? null,
            $validated['file_number_mlsf'] ?? null,
            $fileNumber,
        ]);

        if ($primaryFileIdentifier === null) {
            return response()->json([
                'success' => false,
                'error' => 'A valid file number is required to allocate a property identifier.',
            ], 422);
        }

        try {
            $propId = $this->propertyIdAllocationService->allocateOrRetrievePropId(
                $primaryFileIdentifier,
                $validated['file_number_mlsf'] ?? null,
                $validated['file_number_kangis'] ?? null,
                $validated['file_number_new_kangis'] ?? null
            );
        } catch (PropIdAllocationException $allocationException) {
            return response()->json([
                'success' => false,
                'error' => $allocationException->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            \Log::error('Caveat: prop_id allocation failed', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Unable to allocate property identifier for this file number. Please verify the associated record exists.',
            ], 500);
        }

        $validated['prop_id'] = $propId;

        // Generate caveat number if not provided.
        // Default format is CAV-YYYY-NNNN, but Legal Search can request
        // ADD-CAV-YYYY-NNN by sending caveat_number_scheme=ADD-CAV.
        $caveatNumber = $request->input('caveat_number');
        $caveatNumberScheme = strtoupper((string) $request->input('caveat_number_scheme', 'CAV'));
        if (!in_array($caveatNumberScheme, ['CAV', 'ADD-CAV'], true)) {
            $caveatNumberScheme = 'CAV';
        }

        if (!$caveatNumber) {
            $year = date('Y');

            if ($caveatNumberScheme === 'ADD-CAV') {
                $lastSeq = (int) (Caveat::where('caveat_number', 'like', 'ADD-CAV-' . $year . '-%')
                    ->get()
                    ->map(function ($c) use ($year) {
                        if (preg_match('/ADD\-CAV\-' . $year . '\-(\d+)/i', (string) $c->caveat_number, $m)) {
                            return (int) $m[1];
                        }
                        return 0;
                    })
                    ->max() ?? 0);
                $caveatNumber = 'ADD-CAV-' . $year . '-' . str_pad((string) ($lastSeq + 1), 3, '0', STR_PAD_LEFT);
            } else {
                $lastSeq = (int) (Caveat::where('caveat_number', 'like', 'CAV%' . $year . '%')
                    ->get()
                    ->map(function ($c) use ($year) {
                        if (preg_match('/CAV[\/\-]' . $year . '[\/\-](\d+)/', (string) $c->caveat_number, $m)) {
                            return (int) $m[1];
                        }
                        return 0;
                    })
                    ->max() ?? 0);
                $caveatNumber = 'CAV-' . $year . '-' . str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
            }
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
            // phone_confirmed is a decision the officer made on the form, not a
            // column -- it rides in the request so the SMS dispatcher knows the
            // shared-number warning was cleared.
            unset($caveatData['source_table'], $caveatData['source_id'], $caveatData['phone_confirmed']);
            
            $caveat->fill($caveatData);
            $caveat->caveat_number = $caveatNumber;
            $caveat->status = $isExpired ? 'expired' : $request->input('status', 'active');
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
            $caveat->load(['instrumentType', 'user']);

            // Apply caveated flags to related tables and set caveat_id
            if (!empty($validated['file_number'])) {
                if ($isExpired) {
                    // Expired caveat: write a historical comment but do NOT mark is_caveated=1
                    $comment = "Historical Caveat (Expired)\nCaveat No: " . $caveat->caveat_number
                        . "\nEncumbrance: " . ($validated['encumbrance_type'] ?? 'N/A')
                        . "\nDate Placed: " . ($validated['start_date'] ?? now()->toDateString())
                        . "\nExpiry Date: " . ($validated['release_date'] ?? 'N/A');
                    if ($targetSourceTable && $targetSourceId) {
                        $this->setCaveatedFlagForRecord($targetSourceTable, $targetSourceId, false, $comment, $caveat->id);
                    } else {
                        $this->setCaveatedFlags($validated['file_number'], false, $comment, $caveat->id);
                    }
                } else {
                    $comment = "Property Under Caveat\nCaveat No: " . $caveat->caveat_number
                        . "\nEncumbrance: " . ($validated['encumbrance_type'] ?? 'N/A')
                        . "\nDate: " . now()->format('M d, Y, h:i A');
                    if ($targetSourceTable && $targetSourceId) {
                        $this->setCaveatedFlagForRecord($targetSourceTable, $targetSourceId, true, $comment, $caveat->id);
                    } else {
                        $this->setCaveatedFlags($validated['file_number'], true, $comment, $caveat->id);
                    }
                }
                $this->propertyIdAllocationService->syncPropIdToFileHistory($primaryFileIdentifier, $propId);
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        /*
         | Tell the caveator their caveat is on the property.
         |
         | After the commit: a message saying a caveat exists must not go out on
         | a transaction that then rolled back. An expired (back-captured
         | historical) caveat sends nothing -- it was never placed today, and
         | nobody is waiting to hear about a caveat from 2019.
         */
        if (!$isExpired) {
            app(\App\Services\Sms\CaveatSmsNotifier::class)->placed(
                $caveat,
                $validated['petitioner_phone'] ?? null,
                (bool) ($validated['phone_confirmed'] ?? false)
            );
        }

        return response()->json([
            'success' => true,
            'data' => $caveat,
        ]);
    }

    /** Update the captured caveat without creating a second caveat for the file. */
    public function update(Request $request, $id)
    {
        $caveat = Caveat::findOrFail($id);
        $validated = $request->validate([
            'encumbrance_type' => 'required|string|max:100',
            'instrument_type_id' => 'nullable|integer|exists:sqlsrv.InstrumentTypes,InstrumentTypeID',
            'file_number' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'petitioner' => 'required|string|max:255',
            'petitioner_phone' => ['required', 'string', 'max:100', new NigerianPhone()],
            'grantee' => 'nullable|string|max:255',
            'serial_no' => 'nullable|string',
            'page_no' => 'nullable|string',
            'volume_no' => 'nullable|string',
            'registration_number' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'release_date' => 'nullable|date',
            'instructions' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        // The file reference is deliberately locked in edit mode. Preserve the
        // original file-link columns and update only the caveat's own details.
        unset($validated['file_number']);
        $validated['grantee_name'] = $validated['grantee'] ?? null;
        unset($validated['grantee']);
        $validated['updated_by'] = Auth::user()->name ?? 'System';

        $caveat->fill($validated);
        if (empty($caveat->page_no) && !empty($caveat->serial_no)) {
            $caveat->page_no = $caveat->serial_no;
        }
        if (empty($caveat->registration_number) && $caveat->serial_no && $caveat->page_no && $caveat->volume_no) {
            $caveat->registration_number = $caveat->serial_no . '/' . $caveat->page_no . '/' . $caveat->volume_no;
        }
        $caveat->save();
        $caveat->load(['instrumentType', 'user']);

        return response()->json(['success' => true, 'data' => $caveat]);
    }

    // Return instrument types for dropdowns (used by Legal Search caveat modal)
    // Business rule: exclude "Deed of Surrender and Release" so caveats cannot be placed on fully released titles.
    public function instrumentTypes()
    {
        $types = InstrumentType::active()
            ->select('InstrumentTypeID', 'InstrumentName')
            ->whereRaw("LOWER(REPLACE(REPLACE(InstrumentName, '&', 'and'), '  ', ' ')) NOT IN (?, ?, ?)", [
                'deed of surrender and release',
                'deed of surrender & release',
                'deed of surrender, and release',
            ])
            ->orderBy('InstrumentName')
            ->get();
        return response()->json(['success' => true, 'data' => $types]);
    }

    /**
     * Return the next ADD-CAV reference for the Legal Search Add Caveat flow.
     * Format: ADD-CAV-YYYY-NNN (3-digit padded).
     */
    public function nextDraftId()
    {
        $year = date('Y');
        $lastSeq = (int) (Caveat::where('caveat_number', 'like', 'ADD-CAV-' . $year . '-%')
            ->get()
            ->map(function ($c) use ($year) {
                if (preg_match('/ADD\-CAV\-' . $year . '\-(\d+)/i', (string) $c->caveat_number, $m)) {
                    return (int) $m[1];
                }
                return 0;
            })
            ->max() ?? 0);

        $draftId = 'ADD-CAV-' . $year . '-' . str_pad((string) ($lastSeq + 1), 3, '0', STR_PAD_LEFT);

        return response()->json(['success' => true, 'draft_id' => $draftId]);
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

        $caveats = $query->with(['instrumentType', 'user'])->orderByDesc('start_date')->limit(200)->get();

        // Transform to include instrument_type name string and caveated_comment
        $transformed = $caveats->map(function ($caveat) {
            $arr = $caveat->toArray();
            $arr['instrument_type'] = $caveat->instrumentType?->InstrumentName ?? null;
            unset($arr['instrument_type_relation']);

            // Resolve the primary file number to look up caveated_comment
            $fileNo = $caveat->file_number_kangis ?? $caveat->file_number_new_kangis ?? $caveat->file_number_mlsf ?? null;
            $arr['caveated_comment'] = $fileNo ? $this->fetchCaveatedComment($fileNo) : null;

            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
            'count' => $caveats->count(),
        ]);
    }

    // API list (v2)
    public function apiList(Request $request)
    {
        $status = $request->get('status', 'all');
        $caveats = Caveat::with(['instrumentType', 'user'])
            ->withStatus($status)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $caveats,
            'count' => $caveats->count()
        ]);
    }

    public function show($id)
    {
        $caveat = Caveat::with(['instrumentType', 'user'])->findOrFail($id);
        $arr = $caveat->toArray();
        $arr['instrument_type'] = $caveat->instrumentType?->InstrumentName ?? null;

        $fileNo = $caveat->file_number_kangis ?? $caveat->file_number_new_kangis ?? $caveat->file_number_mlsf ?? null;
        $arr['caveated_comment'] = $fileNo ? $this->fetchCaveatedComment($fileNo) : null;

        return response()->json(['success' => true, 'data' => $arr]);
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
            $liftedBy = Auth::user()->name ?? 'System';

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

            /*
             | One shared lift path -- see App\Services\CaveatLiftService for why
             | this and removeByFile() go through the same method while still
             | writing their own historical status strings ('released' here,
             | 'lifted' there). It also queues the caveator's SMS.
             */
            app(\App\Services\CaveatLiftService::class)->lift(
                $caveat,
                \App\Services\CaveatLiftService::MODE_MANUAL,
                function ($caveat) use ($fileNo) {
                    if ($fileNo) {
                        $comment = "Caveat Lifted\nCaveat No: " . $caveat->caveat_number . "\nDate: " . now()->format('M d, Y, h:i A');
                        $this->setCaveatedFlags($fileNo, false, $comment, null);
                    }
                },
                $liftedBy,
                $validated['release_date']
            );

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'data' => $caveat]);
    }

    /**
     * Remove (lift) the active caveat for a given file number.
     * Reuses the lift logic: sets caveat status to 'lifted' and clears the
     * is_caveated flag on the source row(s). Invoked by the Legal Search
     * "Remove Caveat" action-menu button.
     */
    public function removeByFile(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string',
            'remarks' => 'nullable|string',
            'source_table' => 'nullable|string|in:pra,file_history_staging,deed_registrations,CofO_staging',
            'source_id' => 'nullable|integer|min:1',
        ]);

        $fileNo = trim($validated['file_number']);
        $targetSourceTable = $validated['source_table'] ?? null;
        $targetSourceId = isset($validated['source_id']) ? (int) $validated['source_id'] : null;
        $fileNumberColumns = ['file_number', 'file_number_mlsf', 'file_number_kangis', 'file_number_new_kangis'];

        // Try exact match first across all caveat file number columns.
        $exactQuery = Caveat::query()->whereIn('status', ['active', 'draft']);
        $this->applyFileNumberFilter($exactQuery, $fileNumberColumns, [$fileNo]);
        $caveat = $exactQuery->orderByDesc('id')->first();

        if (!$caveat) {
            $variants = $this->buildFileNumberVariants($fileNo);
            if (!empty($variants)) {
                $query = Caveat::query()->whereIn('status', ['active', 'draft']);
                $this->applyFileNumberFilter($query, $fileNumberColumns, $variants);
                $caveat = $query->orderByDesc('id')->first();
            }

            // Final caveat-table fallback: compare normalized file-number forms
            // (ignores spaces, dashes, slashes, and underscores).
            if (!$caveat) {
                $normalizedKey = $this->buildNormalizedFileNumberKey($fileNo);
                if ($normalizedKey !== '') {
                    $normalizedQuery = Caveat::query()->whereIn('status', ['active', 'draft']);
                    $this->applyNormalizedFileNumberFilter($normalizedQuery, $fileNumberColumns, $normalizedKey);
                    $caveat = $normalizedQuery->orderByDesc('id')->first();
                }
            }
        }

        // As a final fallback, try to resolve via prop_id linked to this file.
        if (!$caveat) {
            try {
                $propId = null;

                [$fileId, $fileType] = $this->resolveFileNumber($fileNo);
                if ($fileId) {
                    $fileNumberCols = $this->filterAvailableColumns('fileNumber', ['id', 'prop_id']);
                    if (in_array('id', $fileNumberCols, true) && in_array('prop_id', $fileNumberCols, true)) {
                        $propId = DB::connection('sqlsrv')->table('fileNumber')
                            ->where('id', $fileId)
                            ->value('prop_id');
                    }
                }

                if (!$propId) {
                    $sourceResults = $this->collectFileNumberSources($fileNo);
                    foreach (($sourceResults['sources'] ?? []) as $sourceEntry) {
                        $candidatePropId = data_get($sourceEntry, 'raw.prop_id');
                        if (!empty($candidatePropId)) {
                            $propId = (int) $candidatePropId;
                            break;
                        }
                    }
                }

                if ($propId) {
                    $caveat = Caveat::where('prop_id', $propId)
                        ->whereIn('status', ['active', 'draft'])
                        ->orderByDesc('id')
                        ->first();
                }
            } catch (\Throwable $e) {
                \Log::warning('Caveat removeByFile: prop_id fallback failed', [
                    'file_number' => $fileNo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$caveat) {
            // If the caveat record was already lifted but the selected source row
            // still shows a stale caveat flag, clear that row-specific flag.
            if ($targetSourceTable && $targetSourceId) {
                DB::connection('sqlsrv')->beginTransaction();
                try {
                    $comment = "Caveat Flag Cleared\nDate: " . now()->format('M d, Y, h:i A');
                    $this->setCaveatedFlagForRecord($targetSourceTable, $targetSourceId, false, $comment, null);
                    DB::connection('sqlsrv')->commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'No active caveat record found, but the selected row flag was cleared.',
                    ]);
                } catch (\Throwable $e) {
                    DB::connection('sqlsrv')->rollBack();
                    return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'No active caveat found for this file number.',
            ], 404);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            if (!empty($validated['remarks'])) {
                $caveat->remarks = trim(($caveat->remarks ? $caveat->remarks . "\n\n" : '') . 'REMOVE REMARKS: ' . $validated['remarks']);
            }
            $liftedBy = Auth::user()->name ?? 'System';

            $flagFileNo = $this->firstNonEmpty([
                // NOTE: caveats has no `file_number` column, so this first entry
                // is always null. Left in place because removing it would change
                // which variant wins, and the order below is load-bearing.
                $caveat->file_number ?? null,
                $caveat->file_number_mlsf ?? null,
                $caveat->file_number_kangis ?? null,
                $caveat->file_number_new_kangis ?? null,
                $fileNo,
            ]) ?? $fileNo;

            // Shared with lift() -- see App\Services\CaveatLiftService. This path
            // keeps writing status 'lifted' rather than lift()'s 'released';
            // both are historical and neither was normalised.
            app(\App\Services\CaveatLiftService::class)->lift(
                $caveat,
                \App\Services\CaveatLiftService::MODE_REMOVE_BY_FILE,
                function ($caveat) use ($targetSourceTable, $targetSourceId, $flagFileNo) {
                    $comment = "Caveat Removed\nCaveat No: " . $caveat->caveat_number . "\nDate: " . now()->format('M d, Y, h:i A');

                    if ($targetSourceTable && $targetSourceId) {
                        $this->setCaveatedFlagForRecord($targetSourceTable, $targetSourceId, false, $comment, null);
                    } else {
                        $this->setCaveatedFlags($flagFileNo, false, $comment, null);
                    }
                },
                $liftedBy
            );

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
        $variants = $this->buildFileNumberVariants($fileNo);
        // Try dbo.fileNumber table (preferred)
        try {
            $query = $conn->table('fileNumber')
                ->select('id', 'kangisFileNo', 'mlsfNo', 'NewKANGISFileNo', 'st_file_no');
            $this->applyFileNumberFilter($query, ['kangisFileNo', 'mlsfNo', 'NewKANGISFileNo', 'st_file_no'], $variants);
            $row = $query->first();
            if ($row) {
                $type = null;
                if (!empty($row->st_file_no)) {
                    $type = 'ST';
                } elseif (!empty($row->kangisFileNo)) {
                    $type = 'KANGIS';
                } elseif (!empty($row->NewKANGISFileNo)) {
                    $type = 'New KANGIS';
                } else {
                    $type = 'MLSF';
                }
                return [$row->id, $type];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        // Fallback to file_numbers table
        try {
            $query = $conn->table('file_numbers')->select('id', 'file_type', 'file_number');
            $this->applyFileNumberFilter($query, ['file_number'], $variants);
            $row = $query->first();
            if ($row) {
                return [$row->id, $row->file_type];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return [null, null];
    }

    private function setCaveatedFlagForRecord(string $sourceTable, int $sourceId, bool $isCaveated, string $comment, ?int $caveatId = null): void
    {
        if ($sourceId <= 0) {
            return;
        }

        $allowedTables = ['pra', 'file_history_staging', 'CofO_staging', 'deed_registrations'];
        if (!in_array($sourceTable, $allowedTables, true)) {
            return;
        }

        $conn = DB::connection('sqlsrv');
        $query = $conn->table($sourceTable)->where('id', $sourceId);

        if ($sourceTable === 'deed_registrations') {
            $statusCols = $this->filterAvailableColumns('deed_registrations', ['status']);
            if (!empty($statusCols)) {
                $query->update([
                    'status' => $isCaveated ? 'caveated' : 'active',
                ]);
            }
            return;
        }

        $columns = $this->filterAvailableColumns($sourceTable, ['is_caveated', 'caveat_id', 'caveated_comment']);
        if (empty($columns)) {
            return;
        }

        $payload = [];
        if (in_array('is_caveated', $columns, true)) {
            $payload['is_caveated'] = $isCaveated ? 1 : null;
        }
        if (in_array('caveat_id', $columns, true)) {
            $payload['caveat_id'] = $isCaveated ? $caveatId : null;
        }
        if (in_array('caveated_comment', $columns, true)) {
            if ($isCaveated) {
                $escapedComment = str_replace("'", "''", $comment);
                $payload['caveated_comment'] = DB::raw("CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . $escapedComment . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . $escapedComment . "' END");
            } else {
                $payload['caveated_comment'] = null;
            }
        }

        if (!empty($payload)) {
            $query->update($payload);
        }
    }

    private function setCaveatedFlags(string $fileNo, bool $isCaveated, string $comment, ?int $caveatId = null): void
    {
        $conn = DB::connection('sqlsrv');
        $variants = $this->buildFileNumberVariants($fileNo);

        // pra: match on common file number columns
        try {
            $query = $conn->table('pra');
            if ($this->applyFileNumberFilterForTable($query, 'pra', ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'], $variants, 'setFlags.pra')) {
                if ($isCaveated) {
                    $query->update([
                        'is_caveated' => 1,
                        'caveat_id' => $caveatId,
                        'caveated_comment' => DB::raw("CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . str_replace("'", "''", $comment) . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"),
                    ]);
                } else {
                    // On removal: reset both the caveat flag and comment to NULL.
                    $query->update([
                        'is_caveated' => null,
                        'caveat_id' => null,
                        'caveated_comment' => null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // deed_registrations: try common columns
        try {
            $query = $conn->table('deed_registrations');
            if ($this->applyFileNumberFilterForTable($query, 'deed_registrations', ['fileno', 'parent_fileno'], $variants, 'setFlags.deed_registrations')) {
                // deed_registrations does not have is_caveated/caveated_comment columns;
                // update status if the column exists, otherwise skip silently.
                $deedColumns = $this->filterAvailableColumns('deed_registrations', ['status']);
                if (!empty($deedColumns)) {
                    $query->update([
                        'status' => $isCaveated ? 'caveated' : 'active',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // CofO_staging: single canonical table
        try {
            $query = $conn->table('CofO_staging');
            if ($this->applyFileNumberFilterForTable($query, 'CofO_staging', ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno'], $variants, 'setFlags.CofO_staging')) {
                if ($isCaveated) {
                    $query->update([
                        'is_caveated' => 1,
                        'caveated_comment' => DB::raw("CASE WHEN caveated_comment IS NULL OR LTRIM(RTRIM(caveated_comment)) = '' THEN '" . str_replace("'", "''", $comment) . "' ELSE caveated_comment + CHAR(13)+CHAR(10) + '" . str_replace("'", "''", $comment) . "' END"),
                    ]);
                } else {
                    // On removal: reset both the caveat flag and comment to NULL.
                    $query->update([
                        'is_caveated' => null,
                        'caveated_comment' => null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Check if a record exists in any of the 3 tables (pra, deed_registrations, CofO_staging)
     */
    private function checkRecordExists(string $fileNo): bool
    {
        $conn = DB::connection('sqlsrv');
        $variants = $this->buildFileNumberVariants($fileNo);

        try {
            $query = $conn->table('pra')->limit(1);
            if ($this->applyFileNumberFilterForTable($query, 'pra', ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'], $variants, 'exists.pra') && $query->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $query = $conn->table('deed_registrations')->limit(1);
            if ($this->applyFileNumberFilterForTable($query, 'deed_registrations', ['fileno', 'parent_fileno'], $variants, 'exists.deed_registrations') && $query->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $query = $conn->table('CofO_staging')->limit(1);
            if ($this->applyFileNumberFilterForTable($query, 'CofO_staging', ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno'], $variants, 'exists.CofO_staging') && $query->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return false;
    }

    /**
     * Search for file number across pra, deed_registrations, and CofO_staging tables
     */
    public function searchFileNumber(Request $request)
    {
        $fileNumber = trim((string) $request->input('file_number'));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'error' => 'File number is required'
            ]);
        }

        try {
            $conn = DB::connection('sqlsrv');
            \Log::info('Caveat: SQLSRV connection info', [
                'database' => $conn->getDatabaseName(),
                'host' => $conn->getConfig('host'),
                'driver' => $conn->getConfig('driver'),
            ]);

            // Log the search attempt
            \Log::info('Caveat: Searching for file number', ['file_number' => $fileNumber]);
            
            $results = $this->collectFileNumberSources($fileNumber);
            
            // Log results
            \Log::info('Caveat: Search results', [
                'file_number' => $fileNumber,
                'found' => $results['found'],
                'primary_source' => $results['primary_source'],
                'sources_count' => count($results['sources'])
            ]);

            if (empty($results['sources'])) {
                return response()->json([
                    'success' => true,
                    'found' => false,
                    'data' => null,
                    'table' => null,
                    'primary_source' => null,
                    'sources' => [],
                    'normalized' => ['fields' => [], 'source_order' => []]
                ]);
            }

            $primarySource = $results['primary_source'];
            $primaryPayload = $primarySource && isset($results['sources'][$primarySource])
                ? $results['sources'][$primarySource]
                : null;

            return response()->json([
                'success' => true,
                'found' => $results['found'],
                'data' => $primaryPayload['raw'] ?? null,
                'table' => $primaryPayload['table'] ?? $primarySource,
                'primary_source' => $primarySource,
                'sources' => $results['sources'],
                'normalized' => $results['normalized']
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gather file number information across supported tables and return normalized view.
     */
    protected function collectFileNumberSources(string $fileNumber): array
    {
        $conn = DB::connection('sqlsrv');
        $sources = [];
        $found = false;
        $variants = $this->buildFileNumberVariants($fileNumber);
        $normalizedKey = $this->buildNormalizedFileNumberKey($fileNumber);
        
        \Log::info('Caveat: Built file number variants', [
            'input' => $fileNumber,
            'variants_count' => count($variants),
            'variants_sample' => array_slice($variants, 0, 5)
        ]);

        // pra has highest priority
        try {
            $recordQuery = $conn->table('pra');
            if (! $this->applyFileNumberFilterForTable($recordQuery, 'pra', ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'], $variants, 'collect.pra')) {
                \Log::debug('Caveat: Skipping pra search due to missing columns', ['table' => 'pra']);
            }
            $records = $recordQuery
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            if ($records->isEmpty() && $normalizedKey !== '') {
                $fallbackQuery = $conn->table('pra');
                $columns = $this->filterAvailableColumns('pra', ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']);
                if (! empty($columns)) {
                    $this->applyNormalizedFileNumberFilter($fallbackQuery, $columns, $normalizedKey);
                    $records = $fallbackQuery
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get();
                }
            }

            if ($records->isNotEmpty()) {
                $found = true;
                foreach ($records as $index => $record) {
                    $raw = $this->convertRecordToArray($record);
                    $sourceKey = $index === 0 ? 'pra' : 'pra_' . ($index + 1);
                    $sources[$sourceKey] = [
                        'table' => 'pra',
                        'raw' => $raw,
                        'fields' => $this->normalizePropertyRecord($raw),
                        'priority' => 90 - ($index * 0.01),
                    ];
                }
                \Log::info('Caveat: Found in pra', ['records_count' => $records->count()]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Caveat: Error searching pra', ['error' => $e->getMessage()]);
        }

        // deed_registrations next
        try {
            $recordQuery = $conn->table('deed_registrations');
            if (! $this->applyFileNumberFilterForTable($recordQuery, 'deed_registrations', ['fileno', 'parent_fileno'], $variants, 'collect.deed_registrations')) {
                \Log::debug('Caveat: Skipping deed_registrations search due to missing columns', ['table' => 'deed_registrations']);
            }
            $records = $recordQuery
                ->orderByDesc('instrument_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            if ($records->isEmpty() && $normalizedKey !== '') {
                $fallbackQuery = $conn->table('deed_registrations');
                $columns = $this->filterAvailableColumns('deed_registrations', ['fileno', 'parent_fileno']);
                if (! empty($columns)) {
                    $this->applyNormalizedFileNumberFilter($fallbackQuery, $columns, $normalizedKey);
                    $records = $fallbackQuery
                        ->orderByDesc('instrument_date')
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get();
                }
            }

            if ($records->isNotEmpty()) {
                $found = true;
                foreach ($records as $index => $record) {
                    $raw = $this->convertRecordToArray($record);
                    $sourceKey = $index === 0 ? 'deed_registrations' : 'deed_registrations_' . ($index + 1);
                    $sources[$sourceKey] = [
                        'table' => 'deed_registrations',
                        'raw' => $raw,
                        'fields' => $this->normalizeRegisteredInstrument($raw),
                        'priority' => 80 - ($index * 0.01),
                    ];
                }
                \Log::info('Caveat: Found in deed_registrations', ['records_count' => $records->count()]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Caveat: Error searching deed_registrations', ['error' => $e->getMessage()]);
        }

        // CofO_staging
        try {
            $recordQuery = $conn->table('CofO_staging');
            $applied = $this->applyFileNumberFilterForTable($recordQuery, 'CofO_staging', ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno'], $variants, 'collect.CofO_staging');
            \Log::info('Caveat: Searching in CofO_staging', [
                'columns_requested' => ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno'],
                'columns_applied' => $applied ? $this->filterAvailableColumns('CofO_staging', ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno']) : [],
            ]);

                if ($applied) {
                    $records = $recordQuery
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get();

                    if ($records->isEmpty() && $normalizedKey !== '') {
                        $fallbackQuery = $conn->table('CofO_staging');
                        $columns = $this->filterAvailableColumns('CofO_staging', ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno']);
                        if (! empty($columns)) {
                            $this->applyNormalizedFileNumberFilter($fallbackQuery, $columns, $normalizedKey);
                            $records = $fallbackQuery
                                ->orderByDesc('updated_at')
                                ->orderByDesc('id')
                                ->limit(5)
                                ->get();
                        }
                    }

                    if ($records->isNotEmpty()) {
                        $found = true;
                        foreach ($records as $index => $record) {
                            $raw = $this->convertRecordToArray($record);
                            $sourceKey = $index === 0 ? 'cofo' : 'cofo_' . ($index + 1);
                            $sources[$sourceKey] = [
                                'table' => 'CofO_staging',
                                'raw' => $raw,
                                'fields' => $this->normalizeCofORecord($raw),
                                'priority' => 70 - ($index * 0.01),
                            ];
                        }
                        \Log::info('Caveat: Found in CofO_staging', ['records_count' => $records->count()]);
                    } else {
                        \Log::info('Caveat: No records found in CofO_staging with given variants');
                    }
                }
        } catch (\Throwable $e) {
            \Log::warning('Caveat: Error searching CofO_staging', ['error' => $e->getMessage()]);
        }

        // file_indexings: source of applicant/solicitor only
        try {
            $recordQuery = $conn->table('file_indexings');
            $applied = $this->applyFileNumberFilterForTable($recordQuery, 'file_indexings', ['file_number'], $variants, 'collect.file_indexings');

            if ($applied) {
                $record = $recordQuery
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();

                if ($record) {
                    $raw = $this->convertRecordToArray($record);
                    $sources['file_indexings'] = [
                        'table' => 'file_indexings',
                        'raw' => $raw,
                        'fields' => $this->normalizeFileIndexingRecord($raw),
                        'priority' => 65,
                    ];
                    \Log::info('Caveat: Found in file_indexings', ['record_id' => $record->id ?? 'unknown']);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Caveat: Error searching file_indexings', ['error' => $e->getMessage()]);
        }

        // Optional fallback: dbo.fileNumber for metadata (does not satisfy business rule)
        try {
            $recordQuery = $conn->table('fileNumber');
            $this->applyFileNumberFilter(
                $recordQuery,
                ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'],
                $variants
            );
            $record = $recordQuery
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($record) {
                $raw = $this->convertRecordToArray($record);
                $sources['file_number'] = [
                    'table' => 'fileNumber',
                    'raw' => $raw,
                    'fields' => $this->normalizeFileNumberRecord($raw),
                    'priority' => 40,
                ];
            }
        } catch (\Throwable $e) {
            // ignore fallback failure
        }

        // Determine primary source by highest priority
        $primarySource = null;
        if (! empty($sources)) {
            $sorted = collect($sources)
                ->sortByDesc(fn ($payload) => $payload['priority'] ?? 0)
                ->keys()
                ->all();
            $primarySource = $sorted[0] ?? null;
            // Reorder sources by priority for deterministic JSON
            $sources = collect($sources)
                ->sortByDesc(fn ($payload) => $payload['priority'] ?? 0)
                ->map(function ($payload, $key) {
                    $payload['source_key'] = $key;
                    return $payload;
                })
                ->all();
        }

        // Keep found strictly tied to required legal tables (pra, deed_registrations, CofO_staging).
        // Auxiliary sources like file_indexings/fileNumber can still provide autofill metadata,
        // but must not satisfy the business rule for placing caveat.

        return [
            'found' => $found,
            'primary_source' => $primarySource,
            'sources' => $sources,
            'normalized' => $this->mergeNormalizedFields($sources),
        ];
    }

    protected function convertRecordToArray($record): array
    {
        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record), true) ?? [];
    }

    protected function normalizePropertyRecord(array $record): array
    {
        $fields = [];

        $transactionType = $this->cleanString(Arr::get($record, 'transaction_type'));
        $instrumentType = $this->cleanString(Arr::get($record, 'instrument_type'));

        if ($encumbrance = $this->mapEncumbranceType($transactionType, $instrumentType)) {
            $fields['encumbrance_type'] = $this->makeFieldEntry($encumbrance, 'transaction_type', 0.92);
        }

        if ($instrumentType) {
            $fields['instrument_type'] = $this->makeFieldEntry($instrumentType, 'instrument_type', 0.9);
        } elseif ($transactionType) {
            $fields['instrument_type'] = $this->makeFieldEntry($transactionType, 'transaction_type', 0.75);
        }

        // Per CAVEAT.md, property location is sourced from registry table location columns only.
        $fields['location'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'location')), 'location', 0.9);

        $partyFields = [
            'party_1' => 'grantor',
            'party_2' => 'grantee',
            'Assignor' => 'assignor',
            'Assignee' => 'assignee',
            'Mortgagor' => 'mortgagor',
            'Mortgagee' => 'mortgagee',
            'Surrenderor' => 'surrenderor',
            'Surrenderee' => 'surrenderee',
            'Lessor' => 'lessor',
            'Lessee' => 'lessee',
            'Grantor' => 'grantor',
            'Grantee' => 'grantee',
        ];

        $parties = [];
        foreach ($partyFields as $column => $alias) {
            $value = $this->cleanString(Arr::get($record, $column));
            if ($value) {
                $parties[$alias] = $this->makeFieldEntry($value, $column, 0.88);
            }
        }

        if (! empty($parties['grantor'])) {
            $fields['grantor'] = $parties['grantor'];
        }
        if (! empty($parties['grantee'])) {
            $fields['grantee'] = $parties['grantee'];
        }

        $serial = $this->cleanString(Arr::get($record, 'serialNo'));
        $page = $this->cleanString(Arr::get($record, 'pageNo'));
        $volume = $this->cleanString(Arr::get($record, 'volumeNo'));

        $fields['serial_no'] = $this->makeFieldEntry($serial, 'serialNo', 0.9);
        $fields['page_no'] = $this->makeFieldEntry($page ?: $serial, $page ? 'pageNo' : 'serialNo', $page ? 0.85 : 0.7);
        $fields['volume_no'] = $this->makeFieldEntry($volume, 'volumeNo', 0.9);
        $fields['registration_number'] = $this->makeFieldEntry(
            $this->firstNonEmpty([
                Arr::get($record, 'regNo'),
                Arr::get($record, 'registration_number'),
                $this->composeRegistrationNumber($serial, $page ?: $serial, $volume),
            ]),
            'regNo',
            0.93
        );

        $fields['start_date'] = $this->makeFieldEntry(
            $this->formatDateTimeForInput(Arr::get($record, 'transaction_date') ?: Arr::get($record, 'regDate')),
            Arr::get($record, 'transaction_date') ? 'transaction_date' : 'regDate',
            0.75
        );

        $fields['instructions'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'schedule')), 'schedule', 0.55);
        $fields['remarks'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'caveated_comment')), 'caveated_comment', 0.5);

        $fields['land_use'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'land_use')), 'land_use', 0.4);

        return array_filter($fields);
    }

    protected function normalizeRegisteredInstrument(array $record): array
    {
        $fields = [];

        $instrumentType = $this->cleanString(Arr::get($record, 'instrument_type'));
        if ($instrumentType) {
            $fields['instrument_type'] = $this->makeFieldEntry($instrumentType, 'instrument_type', 0.82);
        }

        if ($encumbrance = $this->mapEncumbranceType($instrumentType, null)) {
            $fields['encumbrance_type'] = $this->makeFieldEntry($encumbrance, 'instrument_type', 0.8);
        }

        // Per CAVEAT.md, deeds does not contribute property location.
        $fields['location'] = null;

        foreach (['grantor' => 'grantor', 'grantee' => 'grantee'] as $column => $key) {
            $value = $this->cleanString(Arr::get($record, $column));
            if ($value) {
                $fields[$key] = $this->makeFieldEntry($value, $column, 0.78);
            }
        }

        $serial = $this->cleanString(Arr::get($record, 'serial_no'));
        $page = $this->cleanString(Arr::get($record, 'page_no'));
        $volume = $this->cleanString(Arr::get($record, 'volume_no'));

        $fields['serial_no'] = $this->makeFieldEntry($serial, 'serial_no', 0.7);
        $fields['page_no'] = $this->makeFieldEntry($page ?: $serial, $page ? 'page_no' : 'serial_no', $page ? 0.65 : 0.55);
        $fields['volume_no'] = $this->makeFieldEntry($volume, 'volume_no', 0.7);
        $fields['registration_number'] = $this->makeFieldEntry(
            Arr::get($record, 'registration_number') ?: $this->composeRegistrationNumber($serial, $page ?: $serial, $volume),
            'registration_number',
            0.72
        );

        $fields['start_date'] = $this->makeFieldEntry(
            $this->formatDateTimeForInput(Arr::get($record, 'instrument_date') ?: Arr::get($record, 'deeds_date')),
            Arr::get($record, 'instrument_date') ? 'instrument_date' : 'deeds_date',
            0.65
        );

        return array_filter($fields);
    }

    protected function normalizeCofORecord(array $record): array
    {
        $fields = [];

        $fields['instrument_type'] = $this->makeFieldEntry(
            $this->cleanString(Arr::get($record, 'instrument_type') ?: Arr::get($record, 'cofo_type') ?: Arr::get($record, 'transaction_type')),
            Arr::exists($record, 'instrument_type') ? 'instrument_type' : (Arr::exists($record, 'cofo_type') ? 'cofo_type' : 'transaction_type'),
            0.6
        );

        $fields['encumbrance_type'] = $this->makeFieldEntry('Government Acquisition/Reservation', 'cofo', 0.4);

        // Per CAVEAT.md, property location is sourced from registry location columns only.
        $fields['location'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'location')), 'location', 0.7);

        foreach (['Grantor' => 'grantor', 'Grantee' => 'grantee'] as $column => $key) {
            $value = $this->cleanString(Arr::get($record, $column));
            if ($value) {
                $fields[$key] = $this->makeFieldEntry($value, $column, 0.62);
            }
        }

        $serial = $this->cleanString(Arr::get($record, 'serialNo'));
        $page = $this->cleanString(Arr::get($record, 'pageNo'));
        $volume = $this->cleanString(Arr::get($record, 'volumeNo'));

        $fields['serial_no'] = $this->makeFieldEntry($serial, 'serialNo', 0.6);
        $fields['page_no'] = $this->makeFieldEntry($page ?: $serial, $page ? 'pageNo' : 'serialNo', $page ? 0.55 : 0.5);
        $fields['volume_no'] = $this->makeFieldEntry($volume, 'volumeNo', 0.6);
        $fields['registration_number'] = $this->makeFieldEntry(
            $this->firstNonEmpty([
                Arr::get($record, 'regNo'),
                Arr::get($record, 'registration_number'),
                $this->composeRegistrationNumber($serial, $page ?: $serial, $volume),
            ]),
            'regNo',
            0.65
        );

        $fields['start_date'] = $this->makeFieldEntry(
            $this->formatDateTimeForInput(Arr::get($record, 'transaction_date') ?: Arr::get($record, 'transactionDate')),
            Arr::get($record, 'transaction_date') ? 'transaction_date' : 'transactionDate',
            0.55
        );

        $fields['remarks'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'remarks') ?: Arr::get($record, 'caveated_comment')), 'remarks', 0.5);

        $fields['land_use'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'land_use')), 'land_use', 0.5);

        return array_filter($fields);
    }

    protected function normalizeFileNumberRecord(array $record): array
    {
        $fields = [];

        $fields['location'] = $this->makeFieldEntry(
            $this->firstNonEmpty([
                Arr::get($record, 'location'),
                Arr::get($record, 'plot_no'),
            ]),
            'location',
            0.35
        );

        $fields['grantor'] = $this->makeFieldEntry($this->cleanString(Arr::get($record, 'FileName')), 'FileName', 0.3);

        return array_filter($fields);
    }

    protected function normalizeFileIndexingRecord(array $record): array
    {
        $fields = [];

        $fields['petitioner'] = $this->makeFieldEntry(
            $this->cleanString(Arr::get($record, 'file_title')),
            'file_title',
            0.98
        );

        $fields['land_use'] = $this->makeFieldEntry(
            $this->cleanString(Arr::get($record, 'land_use_type') ?: Arr::get($record, 'land_use')),
            Arr::get($record, 'land_use_type') ? 'land_use_type' : 'land_use',
            0.85
        );

        $fields['file_number'] = $this->makeFieldEntry(
            $this->cleanString(Arr::get($record, 'file_number')),
            'file_number',
            0.98
        );

        return array_filter($fields);
    }

    protected function mergeNormalizedFields(array $sources): array
    {
        $merged = [];

        foreach ($sources as $sourceKey => $payload) {
            $priority = $payload['priority'] ?? 0;
            $tableName = $payload['table'] ?? $sourceKey;

            foreach ($payload['fields'] as $field => $entry) {
                if (! is_array($entry) || ! isset($entry['value']) || $entry['value'] === null || $entry['value'] === '') {
                    continue;
                }

                $candidate = array_merge($entry, [
                    'source' => $tableName,
                    'source_key' => $sourceKey,
                    'priority' => $priority,
                ]);

                if (! isset($merged[$field])) {
                    $merged[$field] = $candidate;
                    continue;
                }

                if ($this->shouldReplaceField($merged[$field], $candidate)) {
                    $candidate['alternatives'] = isset($merged[$field]) ? [$merged[$field]] : [];
                    $merged[$field] = $candidate;
                } else {
                    $merged[$field]['alternatives'] = $merged[$field]['alternatives'] ?? [];
                    $merged[$field]['alternatives'][] = $candidate;
                }
            }
        }

        return [
            'fields' => $merged,
            'source_order' => array_values(array_map(fn ($payload) => $payload['table'] ?? $payload['source_key'], $sources)),
        ];
    }

    /**
     * Retrieve a table's columns (cached) using INFORMATION_SCHEMA for SQL Server.
     */
    protected function getTableColumns(string $table): array
    {
        $cacheKey = strtolower($table);
        if (isset($this->tableColumnCache[$cacheKey])) {
            return $this->tableColumnCache[$cacheKey];
        }

        try {
            $rows = DB::connection('sqlsrv')->select(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE LOWER(TABLE_NAME) = LOWER(?)",
                [$table]
            );

            $columns = array_map(static function ($row) {
                return isset($row->COLUMN_NAME) ? $row->COLUMN_NAME : (isset($row['COLUMN_NAME']) ? $row['COLUMN_NAME'] : null);
            }, $rows);

            $columns = array_values(array_filter($columns, static fn ($value) => $value !== null && $value !== ''));
            // Only cache on successful resolution; avoid caching empty to allow retry after transient DB issues
            if (! empty($columns)) {
                $this->tableColumnCache[$cacheKey] = $columns;
            }
            return $columns;
        } catch (\Throwable $e) {
            \Log::warning('Caveat: Failed to fetch table columns', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            // Do not cache failures; allow subsequent calls to retry when connectivity is restored
            return [];
        }
    }

    /**
     * Filter candidate columns against actual table columns (case-insensitive).
     */
    protected function filterAvailableColumns(string $table, array $candidates): array
    {
        $tableColumns = $this->getTableColumns($table);
        if (empty($tableColumns)) {
            return [];
        }

        $lookup = [];
        foreach ($tableColumns as $column) {
            $lookup[strtolower($column)] = $column;
        }

        $matched = [];
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($lookup[$key])) {
                $matched[] = $lookup[$key];
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * Apply file-number variants to a query only when the table exposes matching columns.
     * Returns true when the filter was applied, false otherwise.
     */
    protected function applyFileNumberFilterForTable($query, string $table, array $candidates, array $variants, ?string $context = null): bool
    {
        $columns = $this->filterAvailableColumns($table, $candidates);
        if (empty($columns)) {
            if ($context) {
                \Log::debug('Caveat: No matching file-number columns for table', [
                    'table' => $table,
                    'context' => $context,
                    'requested_columns' => $candidates,
                ]);
            }
            return false;
        }

        $this->applyFileNumberFilter($query, $columns, $variants);
        return true;
    }

    protected function shouldReplaceField(array $current, array $candidate): bool
    {
        $currentConfidence = $current['confidence'] ?? 0;
        $candidateConfidence = $candidate['confidence'] ?? 0;

        if ($candidateConfidence > $currentConfidence + 0.05) {
            return true;
        }

        if (abs($candidateConfidence - $currentConfidence) <= 0.05) {
            return ($candidate['priority'] ?? 0) > ($current['priority'] ?? 0);
        }

        return false;
    }

    protected function makeFieldEntry($value, string $sourceField, float $confidence): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === 'N/A' || $value === '-') {
            return null;
        }

        return [
            'value' => $value,
            'source_field' => $sourceField,
            'confidence' => round($confidence, 2),
        ];
    }

    protected function firstNonEmpty(array $values)
    {
        foreach ($values as $value) {
            $clean = $this->cleanString($value);
            if ($clean !== null && $clean !== '') {
                return $clean;
            }
        }

        return null;
    }

    protected function combineParts(array $parts): ?string
    {
        $filtered = array_filter(array_map([$this, 'cleanString'], $parts));
        if (empty($filtered)) {
            return null;
        }

        return implode(', ', $filtered);
    }

    /**
     * Generate common variants for a given file number, accounting for case, spacing,
     * delimiter swaps, and leading zero padding on the serial segment. This improves
     * lookup robustness across legacy tables that store values with differing formats.
     */
    protected function buildFileNumberVariants(string $fileNumber): array
    {
        $trimmed = trim($fileNumber);
        if ($trimmed === '') {
            return [];
        }

        $variants = [$trimmed];

        $upper = strtoupper($trimmed);
        $lower = strtolower($trimmed);

        $variants[] = $upper;
        $variants[] = $lower;

        $collapsed = preg_replace('/\s+/', '', $trimmed);
        if ($collapsed && $collapsed !== $trimmed) {
            $variants[] = $collapsed;
        }

        $collapsedUpper = preg_replace('/\s+/', '', $upper);
        if ($collapsedUpper && $collapsedUpper !== $upper) {
            $variants[] = $collapsedUpper;
        }

        $collapsedLower = preg_replace('/\s+/', '', $lower);
        if ($collapsedLower && $collapsedLower !== $lower) {
            $variants[] = $collapsedLower;
        }

        $dashToSlash = str_replace('-', '/', $upper);
        if ($dashToSlash !== $upper) {
            $variants[] = $dashToSlash;
        }

        $slashToDash = str_replace('/', '-', $upper);
        if ($slashToDash !== $upper) {
            $variants[] = $slashToDash;
        }

        $segments = preg_split('/[-\/]/', $upper);
        if ($segments !== false && count($segments) >= 2) {
            $serialSegment = array_pop($segments);
            $serialDigits = preg_replace('/\D+/', '', $serialSegment ?? '');

            if ($serialDigits !== '') {
                $serialBase = ltrim($serialDigits, '0');
                if ($serialBase === '') {
                    $serialBase = '0';
                }

                $baseLengths = [
                    strlen($serialSegment ?? ''),
                    strlen($serialDigits),
                    2,
                    3,
                    4,
                    5,
                ];

                $dynamicMaxLength = max(6, ($baseLengths[0] ?? 0) + 4, ($baseLengths[1] ?? 0) + 4);
                $dynamicRange = range(2, $dynamicMaxLength);

                $lengthCandidates = array_filter(array_unique(array_merge($baseLengths, $dynamicRange)));

                $prefixHyphen = implode('-', $segments);
                $prefixSlash = implode('/', $segments);

                $delimiters = [];
                if ($prefixHyphen !== '') {
                    $delimiters['-'] = $prefixHyphen;
                }
                if ($prefixSlash !== '' && $prefixSlash !== $prefixHyphen) {
                    $delimiters['/'] = $prefixSlash;
                }

                foreach ($delimiters as $delimiter => $prefix) {
                    $variants[] = $prefix . $delimiter . $serialBase;
                    foreach ($lengthCandidates as $length) {
                        $variants[] = $prefix . $delimiter . str_pad($serialBase, $length, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        // Normalize variants by adding uppercase counterparts, then filter duplicates/empties.
        $variants = array_merge($variants, array_map('strtoupper', $variants));

        return array_values(array_unique(array_filter($variants, fn ($value) => $value !== null && $value !== '')));
    }

    /**
     * Apply the generated file number variants to a query builder across the supplied columns.
     * Supports both the base query builder and Eloquent builder instances.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array $columns Columns to compare against the variants
     * @param array $variants File number variants generated by buildFileNumberVariants
     * @return mixed
     */
    protected function applyFileNumberFilter($query, array $columns, array $variants)
    {
        $variants = array_values(array_filter(array_unique($variants)));
        if (empty($columns) || empty($variants)) {
            return $query;
        }

        $query->where(function ($q) use ($columns, $variants) {
            $isFirstCondition = true;
            foreach ($columns as $column) {
                foreach ($variants as $variant) {
                    if ($isFirstCondition) {
                        $q->where($column, $variant);
                        $isFirstCondition = false;
                    } else {
                        $q->orWhere($column, $variant);
                    }
                }
            }
        });

        return $query;
    }

    protected function buildNormalizedFileNumberKey(string $fileNumber): string
    {
        $normalized = strtoupper(trim($fileNumber));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);
        return $normalized ?? '';
    }

    protected function applyNormalizedFileNumberFilter($query, array $columns, string $normalized)
    {
        if (empty($columns) || $normalized === '') {
            return $query;
        }

        $query->where(function ($q) use ($columns, $normalized) {
            foreach ($columns as $column) {
                $expr = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM([$column]))), ' ', ''), '-', ''), '/', ''), '_', '')";
                $q->orWhereRaw("$expr = ?", [$normalized]);
            }
        });

        return $query;
    }

    protected function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;

        if ($value === '' || $value === 'N/A' || $value === '-') {
            return null;
        }

        return (string) $value;
    }

    protected function composeRegistrationNumber(?string $serial, ?string $page, ?string $volume): ?string
    {
        if (! $serial || ! $volume) {
            return null;
        }

        $page = $page ?: $serial;

        return sprintf('%s/%s/%s', $serial, $page, $volume);
    }

    protected function formatDateTimeForInput($value): ?string
    {
        $value = $this->cleanString($value);
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function mapEncumbranceType(?string $primary, ?string $secondary): ?string
    {
        $candidates = collect([$primary, $secondary])
            ->filter()
            ->map(fn ($value) => strtolower($value))
            ->all();

        $map = [
            'mortgage' => 'Mortgage',
            'charge' => 'Charge',
            'lien' => 'Lien',
            'assignment' => 'Deed of Assignment/Transfer Not Completed',
            'transfer' => 'Deed of Assignment/Transfer Not Completed',
            'leasehold' => 'Leasehold Interest',
            'lease' => 'Leasehold Interest',
            'sub-lease' => 'Sub-Lease/Sub-Under Lease',
            'sublease' => 'Sub-Lease/Sub-Under Lease',
            'power of attorney' => 'Power of Attorney',
            'probate' => 'Probate/Letters of Administration',
            'court' => 'Court Order/Restraining Order',
            'litigation' => 'Pending Litigation (Lis Pendens)',
            'caution' => 'Caution (General or Specific)',
            'government' => 'Government Acquisition/Reservation',
            'reservation' => 'Government Acquisition/Reservation',
        ];

        foreach ($candidates as $candidate) {
            foreach ($map as $keyword => $target) {
                if (str_contains($candidate, $keyword)) {
                    return $target;
                }
            }
        }

        return null;
    }

    protected function determinePetitioner(?string $transactionType, array $parties): ?array
    {
        $transactionType = strtolower($transactionType ?? '');

        $order = [
            'mortgage' => ['mortgagor', 'mortgagee', 'grantor'],
            'assignment' => ['assignor', 'grantor'],
            'transfer' => ['assignor', 'grantor'],
            'lease' => ['lessor', 'grantor'],
            'surrender' => ['surrenderor', 'grantor'],
            'default' => ['grantor', 'assignor', 'lessor'],
        ];

        $keys = collect($order)
            ->filter(function ($_, $key) use ($transactionType) {
                return $key !== 'default' && str_contains($transactionType, $key);
            })
            ->first() ?? $order['default'];

        foreach ($keys as $key) {
            if (! empty($parties[$key])) {
                return $parties[$key];
            }
        }

        return null;
    }

    /**
     * Check for potential duplicate caveats before form submission
     * This endpoint allows the frontend to warn users about potential duplicates
     */
    public function checkDuplicates(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
            'encumbrance_type' => 'nullable|string|max:100',
            'petitioner' => 'nullable|string|max:255',
        ]);

        $fileNumber = $validated['file_number'];
        
        // Find all existing caveats for this file number (including all statuses for informational purposes)
        $allExistingCaveats = $this->findExistingCaveats($fileNumber, ['active', 'draft', 'released', 'lifted']);
        $activeExistingCaveats = $this->findExistingCaveats($fileNumber, ['active', 'draft']);
        
        $warnings = [];
        $blockingIssues = [];
        
        if ($activeExistingCaveats->isNotEmpty()) {
            // Check for exact duplicates if we have encumbrance_type and petitioner
            if (!empty($validated['encumbrance_type']) && !empty($validated['petitioner'])) {
                $exactDuplicate = $activeExistingCaveats->where('encumbrance_type', $validated['encumbrance_type'])
                                                       ->where('petitioner', $validated['petitioner'])
                                                       ->first();
                
                if ($exactDuplicate) {
                    $blockingIssues[] = [
                        'type' => 'exact_duplicate',
                        'message' => 'Exact duplicate caveat exists',
                        'caveat' => $exactDuplicate,
                        'severity' => 'error'
                    ];
                }
            }
            
            // Check for same encumbrance type
            if (!empty($validated['encumbrance_type'])) {
                $sameEncumbrance = $activeExistingCaveats->where('encumbrance_type', $validated['encumbrance_type'])->first();
                if ($sameEncumbrance && empty($blockingIssues)) {
                    $blockingIssues[] = [
                        'type' => 'same_encumbrance',
                        'message' => 'Caveat with same encumbrance type exists',
                        'caveat' => $sameEncumbrance,
                        'severity' => 'error'
                    ];
                }
            }
            
            // Check for same petitioner
            if (!empty($validated['petitioner'])) {
                $samePetitioner = $activeExistingCaveats->where('petitioner', $validated['petitioner'])->first();
                if ($samePetitioner && empty($blockingIssues)) {
                    $blockingIssues[] = [
                        'type' => 'same_petitioner',
                        'message' => 'You already have an active caveat on this file',
                        'caveat' => $samePetitioner,
                        'severity' => 'error'
                    ];
                }
            }
            
            // General warning about existing active caveats
            if (empty($blockingIssues)) {
                $warnings[] = [
                    'type' => 'general',
                    'message' => 'This file number has existing active caveats',
                    'caveats' => $activeExistingCaveats,
                    'severity' => 'warning'
                ];
            }
        }
        
        // Show historical caveats for reference
        $historicalCaveats = $allExistingCaveats->whereIn('status', ['released', 'lifted']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'file_number' => $fileNumber,
                'has_active_caveats' => $activeExistingCaveats->isNotEmpty(),
                'active_caveats_count' => $activeExistingCaveats->count(),
                'total_caveats_count' => $allExistingCaveats->count(),
                'blocking_issues' => $blockingIssues,
                'warnings' => $warnings,
                'active_caveats' => $activeExistingCaveats,
                'historical_caveats' => $historicalCaveats,
                'can_proceed' => empty($blockingIssues)
            ]
        ]);
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
            'instrument_type' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|string|max:50',
            'property_description' => 'nullable|string|max:500'
        ]);

        // Parse transaction_date if provided (handles datetime-local format)
        $transactionDate = null;
        if (!empty($validated['transaction_date'])) {
            try {
                $transactionDate = \Carbon\Carbon::parse($validated['transaction_date']);
            } catch (\Exception $e) {
                $transactionDate = null;
            }
        }

        try {
            $conn = DB::connection('sqlsrv');
            
            // Determine file number type and set appropriate field
            $fileNumberFields = $this->getFileNumberFields($validated['file_number']);
            $isCertificateOfOccupancy = $this->isCertificateOfOccupancyInstrument($validated['instrument_type'] ?? null);
            $targetTable = $isCertificateOfOccupancy ? 'CofO_staging' : 'pra';
            $targetFileColumns = $isCertificateOfOccupancy
                ? ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno']
                : ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'];

            $variants = $this->buildFileNumberVariants($validated['file_number']);
            $existingQuery = $conn->table($targetTable);
            $hasPraFilter = $this->applyFileNumberFilterForTable(
                $existingQuery,
                $targetTable,
                $targetFileColumns,
                $variants,
                'createPropertyRecord.existing'
            );

            $existingRecord = $hasPraFilter
                ? $existingQuery->orderByDesc('updated_at')->orderByDesc('id')->first()
                : null;

            $payload = [
                'fileno' => $validated['file_number'],
                'mlsFNo' => $fileNumberFields['mlsf'] ?: null,
                'kangisFileNo' => $fileNumberFields['kangis'] ?: null,
                'NewKANGISFileno' => $fileNumberFields['new_kangis'] ?: null,
                'property_description' => $validated['location'] ?: $validated['property_description'],
                'Grantor' => $validated['grantor'],
                'Grantee' => $validated['grantee'],
                'transaction_type' => $validated['instrument_type'],
                'transaction_date' => $transactionDate,
                'updated_at' => now(),
            ];

            if ($existingRecord) {
                $conn->table($targetTable)->where('id', $existingRecord->id)->update($payload);
                $recordId = $existingRecord->id;
                $action = 'updated';
            } else {
                $payload['created_at'] = now();
                $recordId = $conn->table($targetTable)->insertGetId($payload);
                $action = 'created';
            }

            // Get the upserted record
            $record = $conn->table($targetTable)->find($recordId);
            $lookup = $this->collectFileNumberSources($validated['file_number']);

            return response()->json([
                'success' => true,
                'data' => $record,
                'found' => $lookup['found'] ?? true,
                'table' => $targetTable,
                'primary_source' => $lookup['primary_source'] ?? ($isCertificateOfOccupancy ? 'cofo' : 'pra'),
                'sources' => $lookup['sources'] ?? [],
                'normalized' => $lookup['normalized'] ?? ['fields' => [], 'source_order' => []],
                'message' => $action === 'updated' ? 'Property record updated successfully' : 'Property record created successfully',
                'action' => $action,
                'target_table' => $targetTable,
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

    private function isCertificateOfOccupancyInstrument(?string $instrumentType): bool
    {
        if (!is_string($instrumentType) || trim($instrumentType) === '') {
            return false;
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $instrumentType)));

        if (str_contains($normalized, 'certificate of occupancy')) {
            return true;
        }

        return str_contains($normalized, 'certificate')
            && (
                str_contains($normalized, 'occupancy')
                || str_contains($normalized, 'c of o')
                || str_contains($normalized, 'c/o')
            );
    }

    /**
     * Check for duplicate caveat placement
     * Check if the file number already exists in any of the caveat table fields:
     * - file_number_kangis
     * - file_number_mlsf  
     * - file_number_new_kangis
     * If any exist (regardless of status), then the caveat is duplicate - don't save it
     */
    private function checkDuplicateCaveat(string $fileNumber, array $validated): array
    {
        // Check for existing caveats with the same file number (ALL statuses)
        $existingCaveats = $this->findExistingCaveats($fileNumber, ['active', 'draft', 'released', 'lifted', 'expired']);
        
        if ($existingCaveats->isNotEmpty()) {
            $existingCaveat = $existingCaveats->first();
            
            return [
                'isDuplicate' => true,
                'message' => 'Duplicate caveat detected. This file number already exists in the caveat table. Existing caveat by: ' . $existingCaveat->petitioner . ' (' . $existingCaveat->encumbrance_type . ') with status: ' . $existingCaveat->status . '. Caveat No: ' . $existingCaveat->caveat_number,
                'existingCaveat' => $existingCaveat,
                'duplicateType' => 'file_number_exists'
            ];
        }
        
        return [
            'isDuplicate' => false,
            'message' => null,
            'existingCaveat' => null,
            'duplicateType' => null
        ];
    }

    /**
     * Find existing caveats for a file number
     * Check if file number exists in any of the caveat table fields:
     * - file_number_kangis
     * - file_number_mlsf  
     * - file_number_new_kangis
     */
    private function findExistingCaveats(string $fileNumber, array $statuses = ['active', 'draft'])
    {
        $query = Caveat::whereIn('status', $statuses);
        $variants = $this->buildFileNumberVariants($fileNumber);
        
        // Search across all file number fields in the caveat table
        $this->applyFileNumberFilter($query, ['file_number_kangis', 'file_number_mlsf', 'file_number_new_kangis'], $variants);
        
        return $query->get();
    }

    /**
     * Store file number in appropriate field based on type/pattern
     */
    private function setFileNumberFields(array &$validated, string $fileNumber): void
    {
        // Determine file number type based on pattern and store in appropriate field
        $fileNumberFields = $this->getFileNumberFields($fileNumber);
        
        // Set the specific file number fields
        $validated['file_number_kangis'] = $fileNumberFields['kangis'];
        $validated['file_number_mlsf'] = $fileNumberFields['mlsf'];
        $validated['file_number_new_kangis'] = $fileNumberFields['new_kangis'];
    }

    /**
     * Fetch caveated_comment from pra, CofO_staging, or file_history_staging for a given file number.
     */
    private function fetchCaveatedComment(string $fileNo): ?string
    {
        $conn = DB::connection('sqlsrv');
        $variants = $this->buildFileNumberVariants($fileNo);

        $tables = [
            'pra'                  => ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'CofO_staging'         => ['fileno', 'np_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'cofo_no', 'temp_fileno'],
        ];

        foreach ($tables as $table => $columns) {
            try {
                $query = $conn->table($table)->select('caveated_comment');
                if ($this->applyFileNumberFilterForTable($query, $table, $columns, $variants, 'fetchComment.' . $table)) {
                    $row = $query->whereNotNull('caveated_comment')
                                 ->where('caveated_comment', '!=', '')
                                 ->first();
                    if ($row && !empty($row->caveated_comment)) {
                        return $row->caveated_comment;
                    }
                }
            } catch (\Throwable $e) {
                // skip table if column missing or other error
            }
        }

        return null;
    }
}
