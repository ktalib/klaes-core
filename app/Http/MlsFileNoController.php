<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\Prefix;

class MlsFileNoController extends Controller
{
    private const MLS_PRA_GRANTOR = 'Kano State Government';

    /**
     * Get dependent data for dropdowns
     */
    public function getDependentData(Request $request)
    {
        try {
            $landUseId = $request->input('land_use_id');

            if (!$landUseId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Land Use ID is required'
                ], 400);
            }

            // Return all purposes when 'all' is passed (used by SIT file type)
            if ($landUseId === 'all') {
                $purposes = Purpose::all(['id', 'name', 'code']);
                return response()->json([
                    'success' => true,
                    'purposes' => $purposes,
                    'prefixes' => []
                ]);
            }

            $purposes = Purpose::where('landuseid', $landUseId)->get(['id', 'name', 'code']);
            $prefixes = Prefix::where('land_use_id', $landUseId)->get(['id', 'prefix']);

            return response()->json([
                'success' => true,
                'purposes' => $purposes,
                'prefixes' => $prefixes
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching dependent data', [
                'error' => $e->getMessage(),
                'land_use_id' => $request->input('land_use_id')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data'
            ], 500);
        }
    }

    /**
     * Display the Manage MLS FileNo page
     */
    public function index()
    {
        try {
            // Test database connection first
            DB::connection('sqlsrv')->getPdo();

            // Count all rows in the fileNumber table (CACHED)
            $totalCount = \Illuminate\Support\Facades\Cache::remember('mls_fileno_total_count', 600, function () {
                return DB::connection('sqlsrv')->table('fileNumber')->count();
            });

            return view('mls_fileno.index', compact('totalCount'));

        } catch (\Exception $e) {
            Log::error('Error accessing MLS FileNo page', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('mls_fileno.index', [
                'totalCount' => 0,
                'mlsFileNumbers' => collect([]), // Empty collection
                'error' => 'Database Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get data for DataTables
     */
    public function getData(Request $request)
    {
        // Release session lock to allow concurrent AJAX requests (e.g. stats vs datatable)
        if (session_id())
            session_write_close();

        try {
            // Test database connection first
            DB::connection('sqlsrv')->getPdo();
            // Build a query builder for the main fileNumber table
            // LEFT JOIN with mls_file_no to get batch_no (which only exists in mls_file_no table)
            $query = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', function ($join) {
                    $join->on('fileNumber.tracking_id', '=', 'mls_file_no.tracking_id')
                        ->orOn('fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number');
                })
                ->leftJoin('instrument_capture as source_capture', 'mls_file_no.source_instrument_capture_id', '=', 'source_capture.id')
                ->leftJoin('pra as source_pra', 'mls_file_no.source_pra_id', '=', 'source_pra.id')
                ->select([
                    'fileNumber.id',
                    'fileNumber.mlsfNo',
                    'fileNumber.FileName',
                    'fileNumber.created_at',
                    'fileNumber.updated_at',
                    'fileNumber.location',
                    'fileNumber.lga',
                    'fileNumber.created_by',
                    'fileNumber.is_deleted',
                    'fileNumber.SOURCE',
                    'fileNumber.plot_no',
                    'fileNumber.tp_no',
                    'fileNumber.tracking_id',
                    'fileNumber.commissioning_date',
                    'fileNumber.kangisFileNo',
                    'fileNumber.NewKANGISFileNo',
                    'fileNumber.st_file_no',
                    'mls_file_no.batch_no', // Get batch_no from mls_file_no table
                    'mls_file_no.purpose_id',
                    'mls_file_no.customer_type',
                    'mls_file_no.source_instrument_capture_id',
                    'mls_file_no.source_pra_id',
                    DB::raw('COALESCE(
                        (
                            SELECT TOP 1 ic_hist.temp_fileno
                            FROM instrument_capture ic_hist
                            WHERE ic_hist.prop_id = source_capture.prop_id
                                AND ic_hist.temp_fileno IS NOT NULL
                            ORDER BY ic_hist.id DESC
                        ),
                        source_pra.temp_fileno
                    ) as source_temp_fileno'),
                    DB::raw('COALESCE(source_capture.prop_id, source_pra.prop_id) as source_prop_id'),
                    'purposes.name as purpose_name'
                ])
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                }); 

            // Apply filters from DataTables
            if ($request->filled('year')) {
                $year = intval($request->get('year'));
                if ($year > 1900) {
                    $query->whereYear('fileNumber.created_at', $year);
                }
            }

            if ($request->filled('status')) {
                $status = trim($request->get('status'));
                if ($status !== '') {
                    $query->where('fileNumber.SOURCE', $status);
                }
            }

            // OPTIMIZATION: Manually handle total count to avoid DataTables auto-counting overhead
            $recordsTotal = \Illuminate\Support\Facades\Cache::remember('mls_fileno_total_count', 600, function () {
                return DB::connection('sqlsrv')->table('fileNumber')->count();
            });

            // Apply searching manually for filtered count
            $searchValue = $request->input('search.value');
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('fileNumber.mlsfNo', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fileNumber.FileName', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fileNumber.tracking_id', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fileNumber.st_file_no', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fileNumber.kangisFileNo', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fileNumber.NewKANGISFileNo', 'LIKE', "%{$searchValue}%");
                });
            }

            // We still need filtered count for DataTables pagination to look correct
            // If No search, recordsFiltered == recordsTotal
            if (empty($searchValue) && !$request->filled('year') && !$request->filled('status')) {
                $recordsFiltered = $recordsTotal;
            } else {
                $recordsFiltered = $query->count();
            }

            // Handle sorting manually
            $orderColumnIndex = $request->input('order.0.column', 0);
            $orderDirection = $request->input('order.0.dir', 'desc');
            $columnData = $request->input("columns.{$orderColumnIndex}.data", 'mlsfNo');

            $sortMap = [
                'mlsfNo' => 'fileNumber.mlsfNo',
                'FileName' => 'fileNumber.FileName',
                'SOURCE' => 'fileNumber.SOURCE',
                'commissioning_date' => 'fileNumber.commissioning_date',
                'created_by' => 'fileNumber.created_by',
                'purpose_name' => 'purposes.name',
                'customer_type' => 'mls_file_no.customer_type',
                'land_use' => 'mls_file_no.land_use'
            ];

            $sortField = $sortMap[$columnData] ?? 'fileNumber.id';
            $query->orderBy($sortField, $orderDirection);

            // Handle manual paging
            $start = $request->input('start', 0);
            $length = $request->input('length', 20);

            // Limit the length to prevent massive fetches if someone tampers with the request
            if ($length > 100)
                $length = 100;

            $data = $query->offset($start)->limit($length)->get();

            // Pass the pre-fetched collection to DataTables
            return DataTables::of($data)
                ->setTotalRecords($recordsTotal)
                ->setFilteredRecords($recordsFiltered)
                ->skipPaging()
                ->editColumn('mlsfNo', function ($row) {
                    $numbers = $this->splitCompositeNumbers($row->mlsfNo ?? null);
                    $numbers = $this->filterUniqueNumbers($numbers);

                    if (!count($numbers)) {
                        return '<span class="text-sm text-gray-400">N/A</span>';
                    }

                    $badges = [];
                    foreach ($numbers as $number) {
                        $badgeClass = $this->resolveBadgeClass($number);
                        $badges[] = '<span class="file-number-badge ' . $badgeClass . '" title="MLS File Number">' .
                            htmlspecialchars($number) . '</span>';
                    }

                    return '<span class="other-number-group">' . implode('', $badges) . '</span>';
                })
                ->editColumn('FileName', function ($row) {
                    $fileName = $row->FileName ?? 'N/A';
                    return '<div class="max-w-xs truncate text-gray-900 font-medium" title="' . htmlspecialchars($fileName) . '">' .
                        $fileName . '</div>';
                })
                ->editColumn('SOURCE', function ($row) {
                    $source = $row->SOURCE;

                    // Handle NULL or empty sources
                    if (empty($source) || is_null($source)) {
                        $source = 'Unknown';
                    }

                    // Dynamic badge class assignment based on source value
                    $badgeClasses = [
                        'generated' => 'bg-green-100 text-green-800',
                        'mls_commissioned' => 'bg-green-100 text-green-800',
                        'mls_commissioned_batch' => 'bg-green-100 text-green-800',
                        'commissioned' => 'bg-green-100 text-green-800',
                        'captured' => 'bg-blue-100 text-blue-800',
                        'migrated' => 'bg-purple-100 text-purple-800',
                        'imported' => 'bg-orange-100 text-orange-800',
                        'indexing' => 'bg-purple-100 text-purple-800',
                        'system' => 'bg-indigo-100 text-indigo-800',
                        'manual' => 'bg-yellow-100 text-yellow-800',
                        'bulk' => 'bg-pink-100 text-pink-800',
                        'api' => 'bg-teal-100 text-teal-800',
                        'upload' => 'bg-cyan-100 text-cyan-800',
                        'legacy' => 'bg-amber-100 text-amber-800',
                        'unknown' => 'bg-gray-100 text-gray-800'
                    ];

                    $sourceKey = strtolower(trim($source));
                    $badgeClass = $badgeClasses[$sourceKey] ?? 'bg-gray-100 text-gray-800';

                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' .
                        $badgeClass . '" title="Source: ' . htmlspecialchars($source) . '">' .
                        ucfirst($source) . '</span>';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ?
                        '<span class="text-sm text-gray-900 font-medium">' .
                        \Carbon\Carbon::parse($row->created_at)->format('M d, Y') .
                        '</span><br><span class="text-xs text-gray-500">' .
                        \Carbon\Carbon::parse($row->created_at)->format('H:i') .
                        '</span>' : '<span class="text-sm text-gray-400">N/A</span>';
                })
                ->editColumn('updated_at', function ($row) {
                    return $row->updated_at ?
                        '<span class="text-sm text-gray-900 font-medium">' .
                        \Carbon\Carbon::parse($row->updated_at)->format('M d, Y') .
                        '</span><br><span class="text-xs text-gray-500">' .
                        \Carbon\Carbon::parse($row->updated_at)->format('H:i') .
                        '</span>' : '<span class="text-sm text-gray-400">N/A</span>';
                })
                ->editColumn('commissioning_date', function ($row) {
                    return $row->commissioning_date ?
                        '<span class="text-sm text-gray-900 font-medium">' .
                        \Carbon\Carbon::parse($row->commissioning_date)->format('M d, Y') .
                        '</span>' :
                        '<span class="text-sm text-gray-400">Not Set</span>';
                })
                ->editColumn('location', function ($row) {
                    $location = $row->location ?? 'N/A';
                    return '<span class="text-sm text-gray-900 font-medium" title="' . htmlspecialchars($location) . '">' .
                        $location . '</span>';
                })
                ->editColumn('created_by', function () {
                    return '<span class="text-xs font-semibold uppercase tracking-wide text-gray-400">N/A</span>';
                })
                ->addColumn('OtherNumbers', function ($row) {
                    $kangisNumbers = $this->filterUniqueNumbers(
                        $this->splitCompositeNumbers($row->kangisFileNo ?? null)
                    );

                    $newKangisNumbers = $this->filterUniqueNumbers(
                        $this->splitCompositeNumbers($row->NewKANGISFileNo ?? null)
                    );

                    // Do not de-duplicate ST numbers; show every entry exactly as stored
                    $stNumbers = $this->splitCompositeNumbers($row->st_file_no ?? null);

                    $parts = array_merge($kangisNumbers, $newKangisNumbers, $stNumbers);

                    if (!count($parts)) {
                        return '-';
                    }

                    $badges = [];
                    foreach ($parts as $number) {
                        $escaped = e($number);
                        $badgeClass = $this->resolveBadgeClass($number);
                        $badges[] = '<span class="file-number-badge other-number-badge ' . $badgeClass . '" title="Alternate file number">' . $escaped . '</span>';
                    }

                    return '<span class="other-number-group">' . implode('', $badges) . '</span>';
                })
                ->addColumn('actions', function ($row) {
                    return '
                        <div class="flex items-center space-x-1">
                            <button onclick="viewDetails(' . $row->id . ')" 
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 hover:text-blue-800 transition-all duration-200 shadow-sm">
                                <i data-lucide="eye" class="w-3 h-3 mr-1"></i>
                                View
                            </button>
                            <button onclick="editRecord(' . $row->id . ')" 
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-100 rounded-md hover:bg-emerald-200 hover:text-emerald-800 transition-all duration-200 shadow-sm">
                                <i data-lucide="edit-3" class="w-3 h-3 mr-1"></i>
                                Edit
                            </button>
                        </div>';
                })
                ->rawColumns(['mlsfNo', 'FileName', 'SOURCE', 'created_at', 'updated_at', 'commissioning_date', 'location', 'created_by', 'actions', 'OtherNumbers'])
                ->make(true);

        } catch (\Exception $e) {
            Log::error('Error fetching MLS FileNo data', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data: ' . $e->getMessage(),
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'draw' => $request->input('draw', 1)
            ], 500);
        }
    }

    /**
     * Get details of a specific file number
     */
    public function show($identifier)
    {
        try {
            // Try to find by ID first, then by file number
            $fileNumber = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', 'fileNumber.tracking_id', '=', 'mls_file_no.tracking_id')
                ->where(function ($query) use ($identifier) {
                    $query->where('fileNumber.id', $identifier)
                        ->orWhere('fileNumber.mlsfNo', $identifier);
                })
                ->select([
                    'fileNumber.*',
                    // Coalesce crucial fields to ensure data availability from both tables
                    DB::raw('COALESCE(fileNumber.lga, mls_file_no.lga) as lga'),
                    DB::raw('COALESCE(fileNumber.FileName, mls_file_no.file_name) as FileName'),
                    DB::raw('COALESCE(fileNumber.plot_no, mls_file_no.plot_no) as plot_no'),
                    DB::raw('COALESCE(fileNumber.tp_no, mls_file_no.tp_no) as tp_no'),
                    DB::raw('COALESCE(fileNumber.location, mls_file_no.location) as location'),
                    DB::raw('COALESCE(fileNumber.district, mls_file_no.district) as district'),
                    'mls_file_no.purpose_id',
                    'mls_file_no.customer_type',
                    'mls_file_no.batch_no'
                ])
                ->first();

            if (!$fileNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $fileNumber
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching file number details', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search file_indexings for related file numbers (used in recertification commissioning).
     */
    public function searchRelatedFile(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $results = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where(function ($query) use ($q) {
                    $query->where('file_number', 'LIKE', '%' . $q . '%')
                          ->orWhere('file_title', 'LIKE', '%' . $q . '%');
                })
                ->where(function ($query) {
                    $query->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->select('id', 'file_number', 'file_title', 'land_use_type', 'lga', 'location', 'plot_number')
                ->orderBy('file_number')
                ->limit(20)
                ->get();

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch the source TEMP file row details by prop_id from instrument_capture.
     */
    public function getTempFileDetailsByPropId(Request $request)
    {
        try {
            $validated = $request->validate([
                'prop_id' => 'nullable|required_without_all:source_capture_id,pra_id',
                'source_capture_id' => 'nullable|integer',
                'pra_id' => 'nullable|integer',
            ]);

            $propId = $validated['prop_id'] ?? null;
            $sourceCaptureId = $validated['source_capture_id'] ?? null;
            $praId = $validated['pra_id'] ?? null;

            $record = null;

            // First: prefer explicit instrument_capture source id.
            if (!empty($sourceCaptureId)) {
                $record = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('id', $sourceCaptureId)
                    ->first();
            }

            // Second: prop_id lookup in instrument_capture.
            if (!$record && !empty($propId)) {
                $query = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('prop_id', $propId)
                    ->orderByDesc('id');

                $record = (clone $query)
                    ->whereNotNull('temp_fileno')
                    ->first();

                if (!$record) {
                    $record = $query->first();
                }
            }

            if ($record) {
                // Fetch the original (earliest) PRA record for this prop_id
                // so the OP card shows the original Grantor/Allottee.
                $origParty1 = $record->party_1_name ?? null;
                $origParty2 = $record->party_2_name ?? null;
                if (!empty($record->prop_id)) {
                    $origPra = DB::connection('sqlsrv')
                        ->table('pra')
                        ->where('prop_id', $record->prop_id)
                        ->orderBy('id')
                        ->first();
                    if ($origPra) {
                        $origParty1 = $origPra->Grantor ?? ($origPra->party_1 ?? $origParty1);
                        $origParty2 = $origPra->Grantee ?? ($origPra->party_2 ?? $origParty2);
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'prop_id' => $record->prop_id ?? null,
                        'temp_fileno' => $record->temp_fileno ?? null,
                        'mlsFNo' => $record->mlsFNo ?? null,
                        'instrument_type' => $record->instrument_type ?? null,
                        'op_type' => $record->op_type ?? null,
                        'op_serial_number' => $record->op_serial_number ?? null,
                        'registration_number' => $record->registration_number ?? ($record->reg_no ?? null),
                        'party_2_name' => $record->party_2_name ?? null,
                        'party_1_name' => $record->party_1_name ?? null,
                        'orig_party_1_name' => $origParty1,
                        'orig_party_2_name' => $origParty2,
                        'plot_number' => $record->plot_number ?? ($record->plot_no ?? null),
                        'tp_no' => $record->tp_no ?? null,
                        'lga' => $record->lga ?? null,
                        'land_use' => $record->land_use ?? null,
                        'property_description' => $record->property_description ?? null,
                        'property_location' => $record->property_location ?? null,
                    ],
                    'source' => 'instrument_capture',
                ]);
            }

            // Third: explicit PRA id fallback.
            $praRecord = null;
            if (!empty($praId)) {
                $praRecord = DB::connection('sqlsrv')
                    ->table('pra')
                    ->where('id', $praId)
                    ->first();
            }

            // Fourth: prop_id fallback to PRA timeline.
            if (!$praRecord && !empty($propId)) {
                $praQuery = DB::connection('sqlsrv')
                    ->table('pra')
                    ->where('prop_id', $propId)
                    ->orderByDesc('id');

                $praRecord = (clone $praQuery)
                    ->whereNotNull('temp_fileno')
                    ->first();

                if (!$praRecord) {
                    $praRecord = $praQuery->first();
                }
            }

            if (!$praRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No source record found in instrument_capture or PRA for this reference.',
                ], 404);
            }

            // Fetch the original (earliest) PRA record for the OP card
            $origParty1 = $praRecord->Grantor ?? ($praRecord->party_1 ?? null);
            $origParty2 = $praRecord->Grantee ?? ($praRecord->party_2 ?? null);
            if (!empty($propId)) {
                $origPra = DB::connection('sqlsrv')
                    ->table('pra')
                    ->where('prop_id', $propId)
                    ->orderBy('id')
                    ->first();
                if ($origPra) {
                    $origParty1 = $origPra->Grantor ?? ($origPra->party_1 ?? $origParty1);
                    $origParty2 = $origPra->Grantee ?? ($origPra->party_2 ?? $origParty2);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'prop_id' => $praRecord->prop_id ?? null,
                    'temp_fileno' => $praRecord->temp_fileno ?? null,
                    'mlsFNo' => $praRecord->mlsFNo ?? null,
                    'instrument_type' => $praRecord->instrument_type ?? 'Occupancy Permit (OP)',
                    'op_type' => $praRecord->op_type ?? null,
                    'op_serial_number' => $praRecord->op_serial_number ?? null,
                    'registration_number' => $praRecord->regNo ?? ($praRecord->reg_no ?? null),
                    'party_2_name' => $praRecord->Grantee ?? ($praRecord->party_2 ?? null),
                    'party_1_name' => $praRecord->Grantor ?? ($praRecord->party_1 ?? null),
                    'orig_party_1_name' => $origParty1,
                    'orig_party_2_name' => $origParty2,
                    'plot_number' => $praRecord->plot_no ?? null,
                    'tp_no' => $praRecord->tp_no ?? null,
                    'lga' => $praRecord->lgsaOrCity ?? null,
                    'land_use' => $praRecord->land_use ?? null,
                    'property_description' => $praRecord->property_description ?? null,
                    'property_location' => $praRecord->location ?? null,
                ],
                'source' => 'pra',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error fetching temp file details by prop_id', [
                'prop_id' => $request->input('prop_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch temp file details.',
            ], 500);
        }
    }

    /**
     * Update a file number record
     */
    public function update(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'FileName' => 'required|string|max:500',
                'location' => 'nullable|string|max:255',
                'commissioning_date' => 'nullable|date',
                'customer_type' => 'nullable|string',
                'purpose_id' => 'nullable|integer'
            ]);

            $updated = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('id', $id)
                ->update([
                    'FileName' => $validatedData['FileName'],
                    'location' => $validatedData['location'] ?? null,
                    'commissioning_date' => $validatedData['commissioning_date'] ?? null,
                    'purpose_id' => $validatedData['purpose_id'] ?? null,
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name
                ]);

            // Also update the mls_file_no table if tracking_id exists
            $fileRecord = DB::connection('sqlsrv')->table('fileNumber')->where('id', $id)->first();
            if ($fileRecord && !empty($fileRecord->tracking_id)) {
                DB::connection('sqlsrv')->table('mls_file_no')
                    ->where('tracking_id', $fileRecord->tracking_id)
                    ->update([
                        'customer_type' => $validatedData['customer_type'] ?? null,
                        'purpose_id' => $validatedData['purpose_id'] ?? null,
                        'updated_at' => now()
                    ]);
            }

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'File number updated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No changes made or file number not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error updating file number', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        // Release session lock to allow concurrent AJAX requests
        if (session_id())
            session_write_close();

        try {
            $stats = \Illuminate\Support\Facades\Cache::remember('mls_fileno_stats', 600, function () {
                return [
                    'total' => DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->count(),

                    'by_source' => DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where(function ($q) {
                            // Include records with MLS file numbers OR ST file numbers
                            $q->whereNotNull('mlsfNo')
                                ->orWhereNotNull('st_file_no')
                                ->orWhereNotNull('kangisFileNo')
                                ->orWhereNotNull('NewKANGISFileNo');
                        })
                        ->where(function ($q) {
                            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        })
                        ->selectRaw('SOURCE, COUNT(*) as count')
                        ->groupBy('SOURCE')
                        ->orderBy('SOURCE')
                        ->get(),

                    'recent' => DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where(function ($q) {
                            // Include records with MLS file numbers OR ST file numbers
                            $q->whereNotNull('mlsfNo')
                                ->orWhereNotNull('st_file_no')
                                ->orWhereNotNull('kangisFileNo')
                                ->orWhereNotNull('NewKANGISFileNo');
                        })
                        ->where(function ($q) {
                            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        })
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching MLS FileNo statistics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available sources for filtering
     */
    public function getSources()
    {
        try {
            $sources = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    // Include records with MLS file numbers OR ST file numbers
                    $q->whereNotNull('mlsfNo')
                        ->orWhereNotNull('st_file_no')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileNo');
                })
                ->whereNotNull('SOURCE')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->select('SOURCE')
                ->distinct()
                ->orderBy('SOURCE')
                ->pluck('SOURCE')
                ->filter() // Remove empty values
                ->values();

            Log::info('MLS FileNo sources loaded', ['sources' => $sources->toArray()]);

            return response()->json([
                'success' => true,
                'data' => $sources
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching MLS FileNo sources', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching sources: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Test method specifically for ST file numbers debugging
     */
    public function testST()
    {
        try {
            // Query all records with st_file_no
            $stRecords = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('st_file_no')
                ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted', 'FileName')
                ->get();

            $response = [
                'total_st_records' => $stRecords->count(),
                'st_records' => $stRecords->toArray(),
            ];

            // Test the controller's query logic
            $controllerRecords = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select([
                    'fileNumber.id',
                    'fileNumber.mlsfNo',
                    'fileNumber.kangisFileNo',
                    'fileNumber.NewKANGISFileNo',
                    'fileNumber.st_file_no',
                    'fileNumber.SOURCE',
                    'fileNumber.is_deleted'
                ])
                ->where(function ($q) {
                    // Include records with MLS file numbers OR ST file numbers
                    $q->whereNotNull('fileNumber.mlsfNo')
                        ->orWhereNotNull('fileNumber.st_file_no')
                        ->orWhereNotNull('fileNumber.kangisFileNo')
                        ->orWhereNotNull('fileNumber.NewKANGISFileNo');
                })
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                })
                ->get();

            $response['controller_query_test'] = [
                'total_records' => $controllerRecords->count(),
                'st_file_no_records' => $controllerRecords->whereNotNull('st_file_no')->count(),
                'st_dept_records' => $controllerRecords->where('SOURCE', 'ST Dept')->count(),
                'sample_records' => $controllerRecords->take(10)->toArray()
            ];

            // Test specific ST file numbers
            $searchNumbers = [
                'ST-RES-2025-1',
                'ST-COM-2025-1-001',
                'ST-COM-2025-2-001',
                'ST-COM-2025-3-001',
                'ST-COM-2025-4',
                'ST-MIXED-2025-1'
            ];

            $response['search_results'] = [];
            foreach ($searchNumbers as $stNumber) {
                $found = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where(function ($query) use ($stNumber) {
                        $query->where('st_file_no', 'LIKE', "%{$stNumber}%")
                            ->orWhere('mlsfNo', 'LIKE', "%{$stNumber}%")
                            ->orWhere('kangisFileNo', 'LIKE', "%{$stNumber}%")
                            ->orWhere('NewKANGISFileNo', 'LIKE', "%{$stNumber}%");
                    })
                    ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted')
                    ->get();

                $response['search_results'][$stNumber] = [
                    'found_count' => $found->count(),
                    'records' => $found->toArray()
                ];
            }

            return response()->json($response, 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Debug method to check database connection and data
     */
    public function debug()
    {
        try {
            // Test connection
            $connection = DB::connection('sqlsrv')->getPdo();

            // Get sample data
            $sampleData = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    // Include records with MLS file numbers OR ST file numbers
                    $q->whereNotNull('mlsfNo')
                        ->orWhereNotNull('st_file_no')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileNo');
                })
                ->limit(5)
                ->get();

            // Get sources
            $sources = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    // Include records with MLS file numbers OR ST file numbers
                    $q->whereNotNull('mlsfNo')
                        ->orWhereNotNull('st_file_no')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileNo');
                })
                ->select('SOURCE')
                ->distinct()
                ->pluck('SOURCE');

            // Get total count
            $totalCount = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    // Include records with MLS file numbers OR ST file numbers
                    $q->whereNotNull('mlsfNo')
                        ->orWhereNotNull('st_file_no')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileNo');
                })
                ->count();

            return response()->json([
                'success' => true,
                'connection' => 'OK',
                'total_count' => $totalCount,
                'sources' => $sources,
                'sample_data' => $sampleData,
                'database_name' => DB::connection('sqlsrv')->getDatabaseName()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Split composite values (comma/newline separated or JSON encoded) into individual numbers.
     */
    private function splitCompositeNumbers($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $this->normalizeNumberCollection($value);
        }

        $string = trim((string) $value);
        if ($string === '' || strtoupper($string) === 'N/A') {
            return [];
        }

        $decoded = json_decode($string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->normalizeNumberCollection($decoded);
        }

        $normalized = str_replace(["\r\n", "\r", "\n"], ',', $string);
        $parts = preg_split('/\s*(?:,|;|\|)+\s*/', $normalized) ?: [];

        $results = [];
        foreach ($parts as $fragment) {
            $fragment = trim($fragment);
            if ($fragment === '' || strtoupper($fragment) === 'N/A') {
                continue;
            }
            $results[] = $fragment;
        }

        return $results ?: [$string];
    }

    /**
     * Flatten nested arrays into a flat list of cleaned string numbers.
     */
    private function normalizeNumberCollection($value): array
    {
        $results = [];

        $walker = function ($item) use (&$results, &$walker) {
            if (is_array($item)) {
                foreach ($item as $sub) {
                    $walker($sub);
                }
                return;
            }

            if (is_null($item)) {
                return;
            }

            $candidate = trim((string) $item);
            if ($candidate === '' || strtoupper($candidate) === 'N/A') {
                return;
            }

            $results[] = $candidate;
        };

        $walker($value);

        return $results;
    }

    /**
     * Remove duplicates and blank values while keeping original order.
     */
    private function filterUniqueNumbers(array $numbers): array
    {
        $filtered = [];
        foreach ($numbers as $number) {
            $candidate = trim((string) $number);
            if ($candidate === '' || strtoupper($candidate) === 'N/A') {
                continue;
            }

            if (!in_array($candidate, $filtered, true)) {
                $filtered[] = $candidate;
            }
        }

        return $filtered;
    }

    /**
     * Resolve badge class name based on file number prefix for consistent styling
     */
    private function resolveBadgeClass(?string $fileNumber): string
    {
        if (empty($fileNumber)) {
            return 'badge-default';
        }

        $normalized = strtoupper(trim($fileNumber));
        $prefix = substr($normalized, 0, 3);

        $map = [
            'COM' => 'badge-com',
            'RES' => 'badge-res',
            'CON' => 'badge-con',
            'IND' => 'badge-ind',
            'AGR' => 'badge-agr',
            'MIX' => 'badge-mix',
            'SPE' => 'badge-spe',
            'REC' => 'badge-rec',
            'EDU' => 'badge-edu',
            'REL' => 'badge-rel',
            'KNM' => 'badge-knm',
            'MLK' => 'badge-mlk',
            'MLS' => 'badge-mls',
        ];

        if (Str::startsWith($normalized, 'ST-')) {
            $parts = explode('-', $normalized);
            if (isset($parts[1])) {
                $stPrefix = substr(strtoupper($parts[1]), 0, 3);
                return $map[$stPrefix] ?? 'badge-st';
            }
            return 'badge-st';
        }

        return $map[$prefix] ?? 'badge-default';
    }

    /**
     * Resolve the source field value based on allocation configuration
     * 
     * Mapping:
     * - Conversion → "Conversion"
     * - Direct Allocation + Allocation List → "Direct Allocation"
     * - Direct Allocation + Default + Direct Allocation → "OP Direct Allocation"
     * - Direct Allocation + Default + Resettlement → "OP Resettlement"
     * - Direct Allocation + Default (no sub-option) → "Direct Allocation"
     */
    private function resolveSourceValue($applicationType, $allocatedByFilter, $defaultAllocationType)
    {
        // If application type is "conversion", always return "Conversion"
        if ($applicationType === 'conversion') {
            return 'Conversion';
        }

        if ($applicationType === 'change_of_purpose') {
            return 'Change of Purpose';
        }

        // applicationType === 'new' (Direct Allocation)
        // Check allocation source
        if ($allocatedByFilter === 'Allocation List') {
            return 'Direct Allocation';
        }

        // allocatedByFilter === '' (Default allocation)
        if (!empty($defaultAllocationType)) {
            if ($defaultAllocationType === 'resettlement') {
                return 'OP Resettlement';
            }
            if ($defaultAllocationType === 'direct') {
                return 'OP Direct Allocation';
            }
        }

        // Default case: no sub-option selected
        return 'Direct Allocation';
    }

    /**
     * Map source labels to OP-only PRA transaction metadata.
     * Returns null for non-OP sources to prevent invalid PRA inserts.
     */
    private function resolveOpPraMetadata(string $sourceValue): ?array
    {
        $normalized = strtolower(trim($sourceValue));

        if ($normalized === 'op resettlement') {
            return [
                'transaction_type' => 'Occupancy Permit (OP) - Resettlement',
                'op_type' => 'OP Resettlement',
            ];
        }

        if ($normalized === 'op direct allocation') {
            return [
                'transaction_type' => 'Occupancy Permit (OP) - Direct Allocation',
                'op_type' => 'OP Direct Allocation',
            ];
        }

        return null;
    }

     
    /**
     * Generate new MLS file number with land-use-based serial numbering
     */
    public function generateMlsFileNumber(Request $request)
    {
        try {
            $this->logOpLinkage('info', 'Generate request received', [
                'user_id' => Auth::id(),
               // if the application type is new , the application_type should be "Direct Allocation", 
                'application_type' => ($request->input('application_type') === 'new') ? 'Direct Allocation' : $request->input('application_type'),
                'allocated_by_filter' => $request->input('allocated_by_filter'),
                'require_op_source' => $request->input('require_op_source'),
                'source_instrument_capture_id' => $request->input('source_instrument_capture_id'),
                'source_op_serial_number' => $request->input('source_op_serial_number'),
                'source_registration_number' => $request->input('source_registration_number'),
            ]);

            Log::info('MLS generate request started', [
                'user_id' => Auth::id(),
                'application_type' => $request->input('application_type'),
                'default_allocation_type' => $request->input('default_allocation_type'),
                'file_option' => $request->input('file_option')
            ]);

            $validated = $request->validate([
                'land_use' => 'required_unless:file_option,sit|nullable|string|max:50',
                'file_name' => 'nullable|string|max:500',
                'plot_no' => 'nullable|string|max:100',
                'tp_no' => 'nullable|string|max:100',
                'location' => 'nullable|string',
                'lga' => 'nullable|string|max:100',
                'tracking_id' => 'nullable|string|max:50',
                'file_option' => 'nullable|string|max:50',
                'commissioned_by' => 'nullable|string|max:255',
                'commission_date' => 'nullable|date',
                'commission_time' => 'nullable|string',
                'customer_type' => 'required|string|in:Individual,Corporate,Multiple,Government',
                'existing_file_no' => 'nullable|string|max:50',
                'existing_file_no_manual' => 'nullable|string|max:50',
                'purpose_id' => 'nullable|integer',
                'allocation_id' => 'nullable|integer',
                'application_type' => 'nullable|string|max:50',
                'allocated_by_filter' => 'nullable|string|max:100',
                'default_allocation_type' => 'nullable|string|max:50',
                'require_op_source' => 'nullable|boolean',
                'source_instrument_capture_id' => 'nullable|integer',
                'source_pra_id' => 'nullable|integer',
                'source_prop_id' => 'nullable',
                'source_op_serial_number' => 'nullable|string|max:100',
                'source_registration_number' => 'nullable|string|max:100',
                'source_serial_no' => 'nullable|string|max:50',
                'source_page_no' => 'nullable|string|max:50',
                'source_volume_no' => 'nullable|string|max:50',
                'source_original_owner' => 'nullable|string|max:500',
                'source' => 'nullable|string|max:50',
                'sub_source' => 'nullable|string|max:100',
                'related_fileno' => 'nullable|string|max:255',
                'related_file_title' => 'nullable|string|max:500',
                'related_file_indexing_id' => 'nullable|integer',
            ]);

            $landUse = $validated['land_use'] ?? null;
            $year = date('Y');

            // Require related file info for Recertification (-RC) prefixes
            if ($landUse && str_contains(strtoupper($landUse), '-RC')) {
                if (empty($validated['related_fileno'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select the original file number that this recertification relates to.',
                    ], 422);
                }
                if (empty($validated['related_file_title'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please enter the file title for the related original file.',
                    ], 422);
                }
            }

            $requireOpSource = (bool)($validated['require_op_source'] ?? false);
            $this->logOpLinkage('info', 'Validated OP linkage payload', [
                'application_type' => $validated['application_type'] ?? null,
                'allocated_by_filter' => $validated['allocated_by_filter'] ?? null,
                'require_op_source' => $requireOpSource,
                'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                'source_pra_id' => $validated['source_pra_id'] ?? null,
                'source_prop_id' => $validated['source_prop_id'] ?? null,
                'source_original_owner' => $validated['source_original_owner'] ?? null,
            ]);

            if (
                $requireOpSource
                && empty($validated['source_instrument_capture_id'])
                && empty($validated['source_pra_id'])
            ) {
                $this->logOpLinkage('warning', 'Blocked generate due to missing required source OP');
                return response()->json([
                    'success' => false,
                    'message' => 'Source OP record is required before commissioning. Please select an existing OP and continue.',
                ], 422);
            }

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $fileOption = $validated['file_option'] ?? 'normal';
                
                // Determine file number and serial based on option
                if ($fileOption === 'temporary') {
                    // Logic for Temporary Files: Do NOT consume a serial number
                    $existingFileNo = $validated['existing_file_no'] ?? $validated['existing_file_no_manual'];
                    
                    if (empty($existingFileNo)) {
                        throw new \Exception("Existing file number is required for temporary version.");
                    }
                    
                    $serial = 0; // Use 0 to indicate no serial consumption
                    $fullFileNumber = $existingFileNo . '(T)';
                    
                    // Check if this temporary file already exists to avoid duplicates
                    if (\App\Models\MlsFileNo::where('full_file_number', $fullFileNumber)->exists()) {
                         throw new \Exception("This temporary file number already exists: {$fullFileNumber}");
                    }
                    
                } elseif ($fileOption === 'extension') {
                    // Logic for Extension Files: Do NOT consume a serial number
                    $existingFileNo = $validated['existing_file_no'] ?? $validated['existing_file_no_manual'];
                    
                    if (empty($existingFileNo)) {
                        throw new \Exception("Existing file number is required for extension.");
                    }
                    
                    $serial = 0; // Use 0 to indicate no serial consumption
                    $fullFileNumber = $existingFileNo . ' AND EXTENSION';
                    
                } elseif ($fileOption === 'sit') {
                    // SIT files: auto-serial via MlsSerialControl, no land use, customer type is always Government
                    $landUse = 'SIT';
                    $serial = \App\Models\MlsSerialControl::getNextSerial('SIT', $year);
                    $fullFileNumber = "SIT-{$year}-{$serial}";

                    // Force customer type to Government for SIT
                    $validated['customer_type'] = 'Government';

                } else {
                    // Normal Logic: Consume next available serial
                    // Get next serial for this land use/year combination
                    $serial = \App\Models\MlsSerialControl::getNextSerial($landUse, $year);
    
                    // Generate the full file number
                    $fullFileNumber = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $serial);
                }

                $commissionedBy = ($validated['commissioned_by'] ?? null) ?: ((Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System');

                // 1. Determine the file number to use for tracking ID lookup
                // For temporary and extension files, strip the suffix to get the base file number
                $lookupFileNumber = $fullFileNumber;
                if ($fileOption === 'temporary') {
                    // Strip (T) suffix for temporary files
                    $lookupFileNumber = preg_replace('/\(T\)\s*$/', '', $fullFileNumber);
                } elseif ($fileOption === 'extension') {
                    // Strip AND EXTENSION suffix for extension files
                    $lookupFileNumber = preg_replace('/\s+AND\s+EXTENSION\s*$/i', '', $fullFileNumber);
                }

                // Try to fetch tracking ID from grouping table if not provided
                $trackingId = $validated['tracking_id'] ?? null;
                if (empty($trackingId)) {
                    $trackingId = $this->tryFetchTrackingIdFromGrouping($lookupFileNumber);
                }

                // 2. Enforce grouping-table tracking ID policy (no auto-generation here).
                if (empty($trackingId)) {
                    throw new \RuntimeException('Tracking ID not found in grouping table for this file number. Please update grouping record first.');
                }

                // Resolve the source value based on allocation configuration
                $sourceValue = $this->resolveSourceValue(
                    $validated['application_type'] ?? 'new',
                    $validated['allocated_by_filter'] ?? '',
                    $validated['default_allocation_type'] ?? null
                );

                // Override source for temporary and extension file options
                if ($fileOption === 'temporary') {
                    $sourceValue = 'Temporary File';
                } elseif ($fileOption === 'extension') {
                    $sourceValue = 'Extension File';
                }

                // Create record in mls_file_no table
                $mlsRecord = \App\Models\MlsFileNo::create([
                    'land_use' => $landUse,
                    'year' => $year,
                    'serial_number' => $serial,
                    'full_file_number' => $fullFileNumber,
                    'file_name' => $validated['file_name'] ?? null,
                    'plot_no' => $validated['plot_no'] ?? null,
                    'tp_no' => $validated['tp_no'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'lga' => $validated['lga'] ?? null,
                    'tracking_id' => $trackingId,
                    'customer_type' => $validated['customer_type'],
                    'file_option' => $validated['file_option'] ?? 'normal',
                    'created_by' => $commissionedBy,
                    'purpose_id' => $validated['purpose_id'] ?? null,
                    'source' => $sourceValue,
                    'sub_source' => $validated['sub_source'] ?? null,
                    'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                    'source_pra_id' => $validated['source_pra_id'] ?? null,
                ]);

                // Also create record in fileNumber table for compatibility
                $fileNumberData = [
                    'tracking_id' => $trackingId,
                    'mlsfNo' => $fullFileNumber,
                    'FileName' => $validated['file_name'] ?? null,
                    'plot_no' => $validated['plot_no'] ?? null,
                    'tp_no' => $validated['tp_no'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'lga' => $validated['lga'] ?? null,
                    'source' => 'MLS_Commissioned',
                    'type' => 'MlsFileNO',
                    'created_by' => $commissionedBy,
                    'updated_at' => now()
                ];
                if (!empty($validated['related_fileno'])) {
                    $fileNumberData['related_fileno'] = json_encode([$validated['related_fileno']]);
                }
                DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberData); 

                // 3. Staging Logic for Entities and Customers
                try {
                    // Insert into entities_staging
                    $entityId = DB::connection('sqlsrv')->table('entities_staging')->insertGetId([
                        'entity_type' => $validated['customer_type'] ?? 'Individual',
                        'entity_name' => $validated['file_name'] ?? 'N/A',
                        'file_number' => $fullFileNumber,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Construct property address
                    $addressParts = [];
                    if (!empty($validated['plot_no'])) $addressParts[] = "Plot " . $validated['plot_no'];
                    if (!empty($validated['location'])) $addressParts[] = $validated['location'];
                    if (!empty($validated['lga'])) $addressParts[] = $validated['lga'];
                    $propertyAddress = implode(', ', $addressParts);

                    // Insert into customers_staging
                    DB::connection('sqlsrv')->table('customers_staging')->insert([
                        'customer_type' => $validated['customer_type'] ?? 'Individual',
                        'file_number' => $fullFileNumber,
                        'customer_name' => $validated['file_name'] ?? 'N/A',
                        'property_address' => $propertyAddress ?: 'N/A',
                        'entity_id' => $entityId,
                        'account_no' => $fullFileNumber, // account_no same as the file number
                        'status' => 'Active',
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } catch (\Exception $stagingError) {
                    Log::error('Staging table insertion failed', ['error' => $stagingError->getMessage(), 'file' => $fullFileNumber]);
                }

                // Auto-index the file in file_indexings table
                try {
                    $fileIndexingService = app(\App\Services\FileIndexingService::class);
                    $relatedFileNo = $validated['related_fileno'] ?? null;
                    $relatedFileTitle = $validated['related_file_title'] ?? null;
                    $fileIndexing = $fileIndexingService->createFromMlsFileNumber($mlsRecord, $relatedFileNo);

                    // Create file_indexing_links record to link recertification to original file
                    if ($relatedFileNo && $fileIndexing) {
                        try {
                            // Look up the file_indexings record for the related file number
                            $relatedIndexing = DB::connection('sqlsrv')
                                ->table('file_indexings')
                                ->where('file_number', $relatedFileNo)
                                ->first();

                            $relatedFileIndexingId = $relatedIndexing->id ?? null;

                            \App\Models\FileIndexingLink::create([
                                'file_indexing_id' => $relatedFileIndexingId,
                                'file_number' => $fullFileNumber,
                                'file_title' => $validated['file_name'] ?? null,
                                'land_use_type' => $fileIndexing->land_use_type ?? null,
                                'plot_number' => $validated['plot_no'] ?? null,
                                'tp_no' => $validated['tp_no'] ?? null,
                                'location' => $validated['location'] ?? null,
                                'lga' => $validated['lga'] ?? null,
                                'tracking_id' => $trackingId,
                                'indexing_type' => 'recertification_link',
                                'workflow_status' => 'indexed',
                                'created_by' => $commissionedBy,
                            ]);
                            Log::info('Recertification file indexing link created', [
                                'new_file' => $fullFileNumber,
                                'related_file' => $relatedFileNo,
                                'related_file_indexing_id' => $relatedFileIndexingId,
                            ]);
                        } catch (\Exception $linkError) {
                            Log::error('Failed to create recertification file indexing link', ['error' => $linkError->getMessage()]);
                        }
                    }
                } catch (\Exception $indexingError) {
                    Log::error('Auto-indexing failed (non-critical)', ['error' => $indexingError->getMessage()]);
                }

                try {
                    $groupingService = app(\App\Services\GroupingFileNumberService::class);
                    Log::info('MLS generate linking grouping record', ['file_number' => $fullFileNumber]);
                    $groupingService->linkAwaitingToMls($fullFileNumber);
                    Log::info('MLS generate grouping link done', ['file_number' => $fullFileNumber]);
                } catch (\Exception $e) {
                    Log::warning('Failed to link grouping record', ['error' => $e->getMessage()]);
                }

                // Update allocation list status if provided
                if (!empty($validated['allocation_id'])) {
                    \App\Models\AllocationListEntry::where('id', $validated['allocation_id'])
                        ->update(['is_allocated' => 1]);
                }

                $shouldMirror = $this->shouldMirrorResettlementToPropertyTables($validated);
                if (!empty($validated['source_pra_id'])) {
                    // Capture Existing OP should write to PRA only.
                    $shouldMirror = false;
                }
                $this->logOpLinkage('info', 'Mirror decision computed', [
                    'file_number' => $fullFileNumber,
                    'should_mirror' => $shouldMirror,
                    'application_type' => $validated['application_type'] ?? null,
                    'allocated_by_filter' => $validated['allocated_by_filter'] ?? null,
                    'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                    'source_pra_id' => $validated['source_pra_id'] ?? null,
                ]);
                Log::info('MLS generate OP mirror decision', [
                    'file_number' => $fullFileNumber,
                    'should_mirror' => $shouldMirror,
                    'default_allocation_type' => $validated['default_allocation_type'] ?? null,
                    'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                ]);

                if ($shouldMirror) {
                    $this->logOpLinkage('info', 'Mirror execution start', [
                        'file_number' => $fullFileNumber,
                        'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                    ]);
                    Log::info('MLS generate resettlement mirror start', ['file_number' => $fullFileNumber]);
                    $this->createResettlementLinkedRecords($validated, $fullFileNumber, $trackingId, $commissionedBy);
                    Log::info('MLS generate resettlement mirror done', ['file_number' => $fullFileNumber]);
                    $this->logOpLinkage('info', 'Mirror execution completed', ['file_number' => $fullFileNumber]);
                } else {
                    $skipAutoPra = !empty($validated['source_pra_id']);
                    if ($skipAutoPra) {
                        Log::info('MLS generate basic PRA creation skipped for Capture Existing OP fallback path', [
                            'file_number' => $fullFileNumber,
                            'source_pra_id' => $validated['source_pra_id'] ?? null,
                        ]);
                    }

                    $opPraMetadata = $this->resolveOpPraMetadata((string) $sourceValue);

                    // Create a basic PRA record for temp/new/extension file numbers
                    // that don't go through the OP resettlement mirror flow.
                    // Skip if the OP already exists in IC/DR.
                    if (!$skipAutoPra && $opPraMetadata !== null) {
                        try {
                        $propId = app(\App\Services\PropertyIdAllocationService::class)->allocateOrRetrievePropId(
                            $fullFileNumber,
                            $fullFileNumber,
                            null,
                            null,
                            []
                        );

                        $praService = app(\App\Services\Pra\PraRecordService::class);
                        $existingOp = $praService->findExistingOpInSource([
                            'op_serial_number' => $validated['source_op_serial_number'] ?? null,
                            'mlsFNo'           => $fullFileNumber,
                            'fileno'           => $fullFileNumber,
                        ]);

                        if ($existingOp) {
                            $praService->linkSourceOpRecord($existingOp, [
                                'prop_id' => $propId,
                                'mlsFNo'  => $fullFileNumber,
                            ]);
                            Log::info('MLS generate: OP exists in IC/DR — skipped PRA, linked source', [
                                'file_number'  => $fullFileNumber,
                                'source_table' => $existingOp['_source_table'],
                                'source_id'    => $existingOp['id'],
                                'prop_id'      => $propId,
                            ]);
                        } else {
                        $praService->createRecord([
                            'mlsFNo' => $fullFileNumber,
                            'fileno' => $fullFileNumber,
                            'temp_fileno' => null,
                            'transaction_type' => $opPraMetadata['transaction_type'],
                            'transaction_date' => now()->toDateString(),
                            'reg_date' => now()->toDateString(),
                            'system_source' => 'OSSOPCHANGEOFNAME',
                            'land_use' => $landUse,
                            'plot_no' => (string) ($validated['plot_no'] ?? ''),
                            'lgsaOrCity' => (string) ($validated['lga'] ?? ''),
                            'location' => (string) ($validated['location'] ?? ''),
                            'property_description' => (string) ($validated['location'] ?? ''),
                            'instrument_type' => 'Occupancy Permit (OP)',
                            'op_type' => $opPraMetadata['op_type'],
                            'Grantor' => self::MLS_PRA_GRANTOR,
                            'party_1' => self::MLS_PRA_GRANTOR,
                            'Grantee' => (string) ($validated['file_name'] ?? ''),
                            'prop_id' => $propId,
                            'tracking_id' => $trackingId,
                        ], Auth::id());

                        Log::info('MLS generate basic PRA record created', [
                            'file_number' => $fullFileNumber,
                            'prop_id' => $propId,
                            'transaction_type' => $opPraMetadata['transaction_type'],
                        ]);
                        } // end else (no existing IC/DR)
                        } catch (\Exception $praError) {
                            Log::error('MLS generate basic PRA creation failed (non-critical)', [
                                'error' => $praError->getMessage(),
                                'file_number' => $fullFileNumber,
                            ]);
                        }
                    } elseif (!$skipAutoPra) {
                        Log::info('MLS generate basic PRA creation skipped for non-OP source', [
                            'file_number' => $fullFileNumber,
                            'source' => $sourceValue,
                        ]);
                    }
                }

                DB::connection('sqlsrv')->commit();

                $this->ensureEdmsBlindScanFolder($fullFileNumber);

                Log::info('MLS File Number Generated', [
                    'file_number' => $fullFileNumber,
                    'land_use' => $landUse,
                    'serial' => $serial,
                    'user' => Auth::user()->name ?? 'Unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'File number generated successfully',
                    'mirror_created' => $shouldMirror,
                    'data' => [
                        'file_number' => $fullFileNumber,
                        'file_name' => $validated['file_name'] ?? ($mlsRecord->file_name ?? null),
                        'land_use' => $landUse,
                        'year' => $year,
                        'serial' => $serial,
                        'tracking_id' => $trackingId,
                        'id' => $mlsRecord->id,
                        'source_pra_id' => $validated['source_pra_id'] ?? null
                    ]
                ]);

            } catch (\Exception $e) {
                DB::connection('sqlsrv')->rollBack();
                $this->logOpLinkage('error', 'Generate transaction failed', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                Log::error('MLS generate transaction failed', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logOpLinkage('error', 'Generate endpoint failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::error('Error generating MLS file number', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate multiple MLS file numbers in batch mode
     */
    public function generateBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'batch_mode' => 'required|boolean',
                'batch_quantity' => 'required|integer|min:2|max:100',
                'application_type' => 'required|string',
                'file_option' => 'required|string',
                'file_name' => 'nullable|string|max:500',
                'land_use' => 'required|string|max:50',
                'year' => 'required|integer|min:2020|max:2050',
                'serial_start' => 'required|integer|min:1',
                'location_entries' => 'required|array|min:2|max:100',
                'location_entries.*.plotNo' => 'nullable|string|max:100',
                'location_entries.*.tpNo' => 'nullable|string|max:100',
                'location_entries.*.location' => 'nullable|string',
                'location_entries.*.lga' => 'nullable|string|max:100',
                'location_entries.*.tracking_id' => 'nullable|string|max:100',
                'commissioned_by' => 'nullable|string|max:255',
                'commission_date' => 'nullable|date',
                'commission_time' => 'nullable|string',
                'customer_type' => 'required|string|in:Individual,Corporate,Multiple',
                'purpose_id' => 'nullable|integer',
                'allocated_by_filter' => 'nullable|string|max:100',
                'default_allocation_type' => 'nullable|string|max:50',
                'source_instrument_capture_id' => 'nullable|integer',
                'sub_source' => 'nullable|string|max:100'
            ]);

            $landUse = $validated['land_use'];
            $year = $validated['year'];
            $startSerial = $validated['serial_start'];
            $batchQuantity = $validated['batch_quantity'];

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $generatedFiles = [];
                $mlsRecords = [];

                // Verify serial range availability
                $endSerial = $startSerial + $batchQuantity - 1;

                // Check if any serial in the range already exists
                $existingSerials = \App\Models\MlsFileNo::where('land_use', $landUse)
                    ->where('year', $year)
                    ->whereBetween('serial_number', [$startSerial, $endSerial])
                    ->pluck('serial_number')
                    ->toArray();

                if (!empty($existingSerials)) {
                    DB::connection('sqlsrv')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial number range conflict. Serials already in use: ' . implode(', ', $existingSerials)
                    ], 409);
                }

                // Generate a single batch number for all files in this batch (format: BATCH-YYYYMMDD-TIMESTAMP)
                $batchNo = 'BATCH-' . date('Ymd') . '-' . time();

                // Prepare arrays for bulk insertion
                $mlsData = [];
                $fileNumberData = [];
                $indexingData = [];
                $now = now();

                $commissionedBy = ($validated['commissioned_by'] ?? null) ?: ((Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System');

                // Resolve the source value based on allocation configuration
                $sourceValue = $this->resolveSourceValue(
                    $validated['application_type'] ?? 'new',
                    $validated['allocated_by_filter'] ?? '',
                    $validated['default_allocation_type'] ?? null
                );

                // 1. Generate all full file numbers first
                $allFileNumbers = [];
                for ($i = 0; $i < $batchQuantity; $i++) {
                    $allFileNumbers[] = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $startSerial + $i);
                }

                // Create only one record in entities_staging for the entire batch
                $entityId = DB::connection('sqlsrv')->table('entities_staging')->insertGetId([
                    'entity_type' => $validated['customer_type'] ?? 'Individual',
                    'entity_name' => $validated['file_name'] ?? 'N/A',
                    'file_number' => $allFileNumbers[0],
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // 2. Pre-fetch ALL grouping data in ONE query (Saves massive time on 7M row table)
                $groupingCache = []; // key: fileNumber -> {id, tracking_id, mls_fileno, mapping}
                try {
                    $groupingService = app(\App\Services\GroupingFileNumberService::class);
                    $tableName = $groupingService->getTableName('Lands Registry', $allFileNumbers[0]);
                    $fileNoColumn = $groupingService->getFileNoColumnName('Lands Registry');

                    $existingGroupings = DB::connection('sqlsrv')->table($tableName)
                        ->whereIn($fileNoColumn, $allFileNumbers)
                        ->select(['id', $fileNoColumn, 'tracking_id', 'mls_fileno', 'mapping'])
                        ->get();

                    foreach ($existingGroupings as $grouping) {
                        $groupingCache[$grouping->$fileNoColumn] = $grouping;
                    }
                } catch (\Exception $e) {
                    Log::warning('Bulk grouping lookup failed', ['error' => $e->getMessage()]);
                }

                // 3. Count how many new tracking IDs we need (only if not found in grouping or request)
                $neededNewIds = 0;
                foreach ($validated['location_entries'] as $index => $entry) {
                    $fullFileNumber = $allFileNumbers[$index];
                    $cached = $groupingCache[$fullFileNumber] ?? null;
                    if (empty($entry['tracking_id']) && (empty($cached) || empty($cached->tracking_id))) {
                        $neededNewIds++;
                    }
                }

                // 4. Pre-generate all needed unique tracking IDs in one go
                $newTrackingIdPool = $neededNewIds > 0 ? $this->getUniqueTrackingIds($neededNewIds) : [];
                $poolIndex = 0;

                $groupingUpdates = [];
                foreach ($validated['location_entries'] as $index => $entry) {
                    $serial = $startSerial + $index;
                    $fullFileNumber = $allFileNumbers[$index];
                    $cached = $groupingCache[$fullFileNumber] ?? null;

                    // Priority: 1. Manual, 2. Cached from grouping, 3. New Pool
                    $trackingId = $entry['tracking_id'] ?? ($cached->tracking_id ?? null);
                    if (empty($trackingId)) {
                        $trackingId = $newTrackingIdPool[$poolIndex++];
                    }

                    // Prepare for bulk updates to grouping table later
                    if ($cached) {
                        $currentMls = trim($cached->mls_fileno);
                        $currentMapping = (int) ($cached->mapping ?? 0);

                        // Queue update if mapping is missing or MLS number doesn't match
                        if ($currentMapping !== 1 || $currentMls !== $fullFileNumber) {
                            $groupingUpdates[] = [
                                'id' => $cached->id,
                                'mls_fileno' => $fullFileNumber
                            ];
                        }
                    }

                    // Prepare MLS  File numbers record
                    $mlsData[] = [
                        'land_use' => $landUse,
                        'year' => $year,
                        'serial_number' => $serial,
                        'full_file_number' => $fullFileNumber,
                        'file_name' => $validated['file_name'] ?? null,
                        'plot_no' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'tracking_id' => $trackingId,
                        'customer_type' => $validated['customer_type'],
                        'file_option' => $validated['file_option'],
                        'batch_no' => $batchNo,
                        'created_by' => $commissionedBy,
                        'purpose_id' => $validated['purpose_id'] ?? null,
                        'source' => $sourceValue,
                        'sub_source' => $validated['sub_source'] ?? null,
                        'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];

                    // Prepare compatibility record
                    $fileNumberData[] = [
                        'tracking_id' => $trackingId,
                        'mlsfNo' => $fullFileNumber,
                        'FileName' => $validated['file_name'] ?? null,
                        'plot_no' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'source' => 'MLS_Commissioned',
                        'type' => 'MlsFileNO',
                        'is_deleted' => 0,
                        'created_by' => $commissionedBy,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];

                    // Prepare indexing record
                    $indexingData[] = [
                        'tracking_id' => $trackingId,
                        'file_number' => $fullFileNumber,
                        'file_title' => $validated['file_name'] ?? null,
                        'land_use_type' => $this->simpleExtractLandUseType($landUse),
                        'plot_number' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'created_by' => $commissionedBy,
                        'current_holder' => $validated['file_name'] ?? null,
                        'original_holder' => $validated['file_name'] ?? null,
                        'workflow_status' => 'indexed',
                        'is_updated' => false,
                        'is_deleted' => false,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];

                    // Staging Logic for Customers (Run per entry in batch)
                    try {
                        $addressParts = [];
                        if (!empty($entry['plotNo'])) $addressParts[] = "Plot " . $entry['plotNo'];
                        if (!empty($entry['location'])) $addressParts[] = $entry['location'];
                        if (!empty($entry['lga'])) $addressParts[] = $entry['lga'];
                        $propertyAddress = implode(', ', $addressParts);

                        DB::connection('sqlsrv')->table('customers_staging')->insert([
                            'customer_type' => $validated['customer_type'] ?? 'Individual',
                            'file_number' => $fullFileNumber,
                            'customer_name' => $validated['file_name'] ?? 'N/A',
                            'property_address' => $propertyAddress ?: 'N/A',
                            'entity_id' => $entityId,
                            'account_no' => $fullFileNumber,
                            'status' => 'Active',
                            'created_by' => Auth::id(),
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                    } catch (\Exception $stagingError) {
                        Log::error('Batch staging table insertion failed', ['error' => $stagingError->getMessage(), 'file' => $fullFileNumber]);
                    }

                    $generatedFiles[] = $fullFileNumber;
                }

                // Bulk Inserts
                DB::connection('sqlsrv')->table('mls_file_no')->insert($mlsData);
                DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberData);
                DB::connection('sqlsrv')->table('file_indexings')->insert($indexingData);

                // 5. Consolidated Bulk Update for grouping table (High Speed)
                if (!empty($groupingUpdates)) {
                    $ids = array_column($groupingUpdates, 'id');
                    $caseMls = "CASE id ";
                    foreach ($groupingUpdates as $update) {
                        $quotedMls = DB::connection('sqlsrv')->getPdo()->quote($update['mls_fileno']);
                        $caseMls .= "WHEN {$update['id']} THEN {$quotedMls} ";
                    }
                    $caseMls .= "ELSE mls_fileno END";

                    DB::connection('sqlsrv')->table('grouping')
                        ->whereIn('id', $ids)
                        ->update([
                            'mls_fileno' => DB::raw($caseMls),
                            'mapping' => 1,
                            'updated_at' => $now
                        ]);
                }


                // Create basic PRA records for each batch entry
                try {
                    $praService = app(\App\Services\Pra\PraRecordService::class);
                    $propIdService = app(\App\Services\PropertyIdAllocationService::class);
                    $opPraMetadata = $this->resolveOpPraMetadata((string) $sourceValue);

                    if ($opPraMetadata !== null) {
                        foreach ($validated['location_entries'] as $index => $entry) {
                            $batchFileNumber = $allFileNumbers[$index];
                            $batchTrackingId = $mlsData[$index]['tracking_id'] ?? null;

                            $propId = $propIdService->allocateOrRetrievePropId(
                                $batchFileNumber,
                                $batchFileNumber,
                                null,
                                null,
                                []
                            );

                            // Check IC/DR for existing OP before creating PRA row
                            $existingOp = $praService->findExistingOpInSource([
                                'mlsFNo' => $batchFileNumber,
                                'fileno' => $batchFileNumber,
                            ]);

                            if ($existingOp) {
                                $praService->linkSourceOpRecord($existingOp, [
                                    'prop_id' => $propId,
                                    'mlsFNo'  => $batchFileNumber,
                                ]);
                                Log::info('Batch PRA: OP exists in IC/DR — skipped, linked source', [
                                    'file_number'  => $batchFileNumber,
                                    'source_table' => $existingOp['_source_table'],
                                    'source_id'    => $existingOp['id'],
                                ]);
                                continue;
                            }

                            $praService->createRecord([
                                'mlsFNo' => $batchFileNumber,
                                'fileno' => $batchFileNumber,
                                'temp_fileno' => null,
                                'transaction_type' => $opPraMetadata['transaction_type'],
                                'transaction_date' => now()->toDateString(),
                                'reg_date' => now()->toDateString(),
                                'system_source' => 'OSSOPCHANGEOFNAME',
                                'land_use' => $landUse,
                                'plot_no' => (string) ($entry['plotNo'] ?? ''),
                                'lgsaOrCity' => (string) ($entry['lga'] ?? ''),
                                'location' => (string) ($entry['location'] ?? ''),
                                'property_description' => (string) ($entry['location'] ?? ''),
                                'instrument_type' => 'Occupancy Permit (OP)',
                                'op_type' => $opPraMetadata['op_type'],
                                'Grantor' => self::MLS_PRA_GRANTOR,
                                'party_1' => self::MLS_PRA_GRANTOR,
                                'Grantee' => (string) ($validated['file_name'] ?? ''),
                                'prop_id' => $propId,
                                'tracking_id' => $batchTrackingId,
                            ], Auth::id());
                        }

                        Log::info('Batch PRA records created', [
                            'batch_size' => $batchQuantity,
                            'land_use' => $landUse,
                            'transaction_type' => $opPraMetadata['transaction_type'],
                        ]);
                    } else {
                        Log::info('Batch PRA creation skipped for non-OP source', [
                            'batch_size' => $batchQuantity,
                            'land_use' => $landUse,
                            'source' => $sourceValue,
                        ]);
                    }
                } catch (\Exception $praError) {
                    Log::error('Batch PRA creation failed (non-critical)', [
                        'error' => $praError->getMessage(),
                        'land_use' => $landUse,
                    ]);
                }


                // Update serial control to reserve the entire range
                \App\Models\MlsSerialControl::updateOrCreate(
                    [
                        'land_use' => $landUse,
                        'year' => $year
                    ],
                    [
                        'last_serial' => $endSerial
                    ]
                );

                DB::connection('sqlsrv')->commit();

                foreach ($generatedFiles as $generatedFileNumber) {
                    $this->ensureEdmsBlindScanFolder($generatedFileNumber);
                }

                Log::info('Batch MLS File Numbers Generated', [
                    'batch_size' => $batchQuantity,
                    'land_use' => $landUse,
                    'serial_range' => "{$startSerial} to {$endSerial}",
                    'files' => $generatedFiles,
                    'user' => Auth::user()->name ?? 'Unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Successfully generated {$batchQuantity} file numbers",
                    'files' => $generatedFiles,
                    'data' => [
                        'batch_size' => $batchQuantity,
                        'land_use' => $landUse,
                        'year' => $year,
                        'serial_range' => "{$startSerial} to {$endSerial}"
                    ]
                ]);

            } catch (\Exception $e) {
                DB::connection('sqlsrv')->rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::channel('mls_batch_errors')->error('Error generating batch MLS file numbers', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request' => $request->except(['_token', 'password', 'password_confirmation']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating batch file numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initialize serial number for a land use/year
     */
    public function initializeSerial(Request $request)
    {
        try {
            $validated = $request->validate([
                'land_use' => 'required|string|max:50',
                'year' => 'required|integer|min:2020|max:2050',
                'last_serial' => 'required|integer|min:0'
            ]);

            $landUse = $validated['land_use'];
            $year = $validated['year'];
            $lastSerial = $validated['last_serial'];

            // Check if already locked
            if (\App\Models\MlsSerialControl::isLocked($landUse, $year)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This land use/year combination is already locked and cannot be modified'
                ], 403);
            }

            // Initialize
            $control = \App\Models\MlsSerialControl::initialize(
                $landUse,
                $year,
                $lastSerial,
                Auth::user()->name ?? Auth::user()->email
            );

            return response()->json([
                'success' => true,
                'message' => 'Serial number initialized and locked successfully',
                'data' => $control
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error initializing serial', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error initializing serial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get serial control status for all land uses
     *  


     */
    public function getSerialStatus()
    {
        try {
            $currentYear = date('Y');

            $serialControls = \App\Models\MlsSerialControl::getAllForYear($currentYear);

            // Get all possible land uses from the Prefix model to avoid hardcoding
            $allLandUses = \App\Models\Prefix::pluck('prefix')->map(fn($p) => trim($p))->unique()->toArray();

            // Also include any land uses that already have serial controls but might not be in the prefix table
            $controlLandUses = $serialControls->pluck('land_use')->map(fn($p) => trim($p))->toArray();
            $allLandUses = array_unique(array_merge($allLandUses, $controlLandUses));
            sort($allLandUses);

            $status = [];
            foreach ($allLandUses as $landUse) {
                if (empty($landUse)) {
                    continue;
                }

                $control = $serialControls->firstWhere('land_use', $landUse);

                $status[] = [
                    'land_use' => $landUse,
                    'year' => $currentYear,
                    'last_serial' => $control ? (int) $control->last_serial : 0,
                    'is_initialized' => $control ? (bool) $control->is_initialized : false,
                    'is_locked' => $control ? (bool) $control->is_locked : false,
                    'initialized_at' => $control ? $control->initialized_at : null,
                    'initialized_by' => $control ? $control->initialized_by : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching serial status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching serial status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function previewTrackingId(Request $request)
    {
        try {
            $preferred = trim((string) $request->query('preferred', ''));
            $trackingId = $this->getUniqueTrackingId($preferred !== '' ? $preferred : null);

            return response()->json([
                'success' => true,
                'data' => [
                    'tracking_id' => $trackingId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to preview tracking ID', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate tracking ID preview',
            ], 500);
        }
    }

    private function shouldMirrorResettlementToPropertyTables(array $validated): bool
    {
        // OP linkage should mirror whenever we have a concrete source capture row.
        return !empty($validated['source_instrument_capture_id']);
    }

    private function createResettlementLinkedRecords(array $validated, string $fullFileNumber, string $trackingId, string $commissionedBy): void
    {
        $sourceId = (int) ($validated['source_instrument_capture_id'] ?? 0);
        $this->logOpLinkage('info', 'Loading source OP row for mirror', [
            'source_instrument_capture_id' => $sourceId,
            'file_number' => $fullFileNumber,
        ]);
        $source = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $sourceId)->first();

        if (!$source) {
            $this->logOpLinkage('error', 'Source OP row not found for mirror', [
                'source_instrument_capture_id' => $sourceId,
                'file_number' => $fullFileNumber,
            ]);
            throw new \RuntimeException('Source OP capture record not found for resettlement commissioning.');
        }

        $originalOwner = trim((string) (($validated['source_original_owner'] ?? null) ?: ($source->party_2_name ?? $source->party_1_name ?? '')));
        $newOwner = trim((string) ($validated['file_name'] ?? ''));

       if ($originalOwner === '' || $newOwner === '') {
            throw new \RuntimeException('Original owner and new owner are required
        ');
        }

        $serialNo = (string) (($validated['source_serial_no'] ?? null) ?: ($source->serial_no ?? ''));
        $pageNo = (string) (($validated['source_page_no'] ?? null) ?: ($source->page_no ?? $serialNo));
        $volumeNo = (string) (($validated['source_volume_no'] ?? null) ?: ($source->volume_no ?? ''));
        $registrationNumber = (string) (($validated['source_registration_number'] ?? null) ?: ($source->registration_number ?? $source->deeds_serial_no ?? ''));
        $opSerial = (string) (($validated['source_op_serial_number'] ?? null) ?: ($source->op_serial_number ?? ''));

        $propId = $validated['source_prop_id'] ?? ($source->prop_id ?? null);
        if (empty($propId)) {
            $propId = app(\App\Services\PropertyIdAllocationService::class)->allocateOrRetrievePropId(
                $fullFileNumber,
                $fullFileNumber,
                null,
                null,
                []
            );
        }

        // Skip PRA OP row — the IC + DR records created below are the source of
        // truth for the Occupancy Permit.  Only Transfer of Title rows belong in PRA.
        $praService = app(\App\Services\Pra\PraRecordService::class);
        $existingOp = $praService->findExistingOpInSource([
            'op_serial_number' => $opSerial,
            'mlsFNo'           => $fullFileNumber,
            'fileno'           => $fullFileNumber,
        ]);

        if ($existingOp) {
            $praService->linkSourceOpRecord($existingOp, [
                'prop_id' => $propId,
                'mlsFNo'  => $fullFileNumber,
            ]);
            Log::info('createResettlementLinkedRecords: OP exists in IC/DR — skipped PRA, linked source', [
                'source_table' => $existingOp['_source_table'],
                'source_id'    => $existingOp['id'],
                'prop_id'      => $propId,
            ]);
        } else {
            // No existing IC/DR yet — but IC+DR will be created below, so skip
            // PRA OP row to avoid triplication.  The IC+DR records ARE the source.
            Log::info('createResettlementLinkedRecords: skipping PRA OP row — IC+DR will be created as source', [
                'file_number' => $fullFileNumber,
                'prop_id'     => $propId,
            ]);
        }

        $captureResult = app(\App\Services\InstrumentCaptureService::class)->capture([
            'instrument_type' => 'Occupancy Permit (OP)',
            'mlsFNo' => $fullFileNumber,
            'fileno' => $fullFileNumber,
            'temp_fileno' => null,
            'prop_id' => $propId,
            'op_serial_number' => $opSerial,
            'op_type' => 'OP Resettlement',
            'firstPartyName' => $originalOwner,
            'secondPartyName' => $newOwner,
            'firstPartyStreet' => (string) ($source->party_2_address ?? ''),
            'firstPartyPhone' => (string) ($source->party_2_phone ?? ''),
            'firstPartyState' => (string) ($source->party_2_state ?? ''),
            'firstPartyLga' => (string) ($source->party_2_lga ?? ''),
            'firstPartyCity' => (string) ($source->party_2_city ?? ''),
            'firstPartyNationality' => (string) ($source->party_2_nationality ?? ''),
            'firstPartyIdType' => (string) ($source->party_2_id_type ?? ''),
            'firstPartyIdNumber' => (string) ($source->party_2_id_number ?? ''),
            'firstPartyDistrict' => (string) ($source->party_2_district ?? ''),
            'secondPartyStreet' => (string) ($validated['address'] ?? $source->property_location ?? ''),
            'secondPartyPhone' => (string) ($validated['phone_no'] ?? ''),
            'lga' => (string) (($validated['lga'] ?? null) ?: ($source->lga ?? '')),
            'district' => (string) ($source->district ?? ''),
            'land_use' => (string) (($source->land_use ?? null) ?: ($validated['land_use'] ?? '')),
            'purpose' => (string) ($source->purpose ?? ''),
            'land_use_id' => null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'plotNumber' => (string) (($validated['plot_no'] ?? null) ?: ($source->plot_number ?? '')),
            'tp_no' => (string) (($validated['tp_no'] ?? null) ?: ($source->tp_no ?? '')),
            'propertyDescription' => (string) (($source->property_description ?? null) ?: ($validated['location'] ?? '')),
            'entryDate' => now()->toDateString(),
            'registrationDate' => now()->toDateString(),
            'Grantor' => $originalOwner,
            'Grantee' => $newOwner,
            'propertyLocation' => (string) (($validated['location'] ?? null) ?: ($source->property_location ?? '')),
            'created_by' => $commissionedBy,
        ]);
        $this->logOpLinkage('info', 'Instrument capture mirror row created', [
            'source_instrument_capture_id' => $sourceId,
            'new_instrument_capture_id' => $captureResult['id'] ?? null,
            'file_number' => $fullFileNumber,
        ]);

        $captureId = (int) ($captureResult['id'] ?? 0);
        if ($captureId > 0) {
            $updates = [
                'mlsFNo' => $fullFileNumber,
                'temp_fileno' => null,
                'registration_number' => $registrationNumber !== '' ? $registrationNumber : null,
                'serial_no' => $serialNo !== '' ? $serialNo : null,
                'page_no' => $pageNo !== '' ? $pageNo : null,
                'volume_no' => $volumeNo !== '' ? $volumeNo : null,
                'deeds_serial_no' => $registrationNumber !== '' ? $registrationNumber : null,
                'party_1_name' => $originalOwner,
                'party_2_name' => $newOwner,
                'party_1_address' => $source->party_2_address ?? null,
                'party_1_phone' => $source->party_2_phone ?? null,
                'party_1_state' => $source->party_2_state ?? null,
                'party_1_lga' => $source->party_2_lga ?? null,
                'party_1_city' => $source->party_2_city ?? null,
                'party_1_nationality' => $source->party_2_nationality ?? null,
                'party_1_id_type' => $source->party_2_id_type ?? null,
                'party_1_id_number' => $source->party_2_id_number ?? null,
                'party_1_district' => $source->party_2_district ?? null,
                'prop_id' => $propId,
                'updated_at' => now(),
            ];

            $captureColumns = array_flip(Schema::connection('sqlsrv')->getColumnListing('instrument_capture'));
            $safeCaptureUpdates = array_filter($updates, function ($value, $key) use ($captureColumns) {
                return isset($captureColumns[$key]);
            }, ARRAY_FILTER_USE_BOTH);

            DB::connection('sqlsrv')->table('instrument_capture')->where('id', $captureId)->update($safeCaptureUpdates);

            $deedColumns = array_flip(Schema::connection('sqlsrv')->getColumnListing('deed_registrations'));
            $deedUpdates = [
                'registration_number' => $registrationNumber !== '' ? $registrationNumber : null,
                'serial_no' => $serialNo !== '' ? $serialNo : null,
                'page_no' => $pageNo !== '' ? $pageNo : null,
                'volume_no' => $volumeNo !== '' ? $volumeNo : null,
                'grantor' => $originalOwner,
                'grantee' => $newOwner,
                'fileno' => $fullFileNumber,
                'updated_at' => now(),
            ];
            if (isset($deedColumns['temp_fileno'])) {
                $deedUpdates['temp_fileno'] = null;
            }
            if (isset($deedColumns['prop_id'])) {
                $deedUpdates['prop_id'] = $propId;
            }

            $safeDeedUpdates = array_filter($deedUpdates, function ($value, $key) use ($deedColumns) {
                return isset($deedColumns[$key]);
            }, ARRAY_FILTER_USE_BOTH);

            DB::connection('sqlsrv')
                ->table('deed_registrations')
                ->where('instrument_capture_id', $captureId)
                ->update($safeDeedUpdates);

            $this->logOpLinkage('info', 'Mirror updates applied to transaction tables', [
                'source_instrument_capture_id' => $sourceId,
                'new_instrument_capture_id' => $captureId,
                'file_number' => $fullFileNumber,
                'prop_id' => $propId,
            ]);
        }
    }

    private function ensureEdmsBlindScanFolder(string $fileNumber): void
    {
        $rawFileNumber = trim($fileNumber);
        if ($rawFileNumber === '') {
            return;
        }

        // Keep folder names filesystem-safe while still human-readable.
        $safeFolderName = preg_replace('/[\\\\\/\:\*\?"<>\|]+/', '-', $rawFileNumber);
        $safeFolderName = trim((string) $safeFolderName);
        if ($safeFolderName === '') {
            return;
        }

        $folderPath = 'EDMS\SCAN_UPLOAD\Lands_Registry/' . $safeFolderName;

        try {
            if (!Storage::disk('public')->exists($folderPath)) {
                Storage::disk('public')->makeDirectory($folderPath);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create EDMS BLIND_SCAN folder for commissioned file', [
                'file_number' => $rawFileNumber,
                'folder_path' => $folderPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logOpLinkage(string $level, string $message, array $context = []): void
    {
        try {
            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/op-linkage.log'),
                'level' => 'debug',
            ]);
            $logger->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::warning('Failed to write OP linkage log', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Property to track generated tracking IDs within a request
     */
    private $generatedTrackingIds = [];
   //

    /**
     * Generate a unique tracking ID
     */
    private function getUniqueTrackingId(?string $preferred = null): string
    {
        $preferred = $preferred ? strtoupper(trim($preferred)) : null;
        $attempts = 0;

        do {
            $candidate = $preferred && $attempts === 0 ? $preferred : $this->generateTrackingId();

            if (isset($this->generatedTrackingIds[$candidate])) {
                $preferred = null;
                $attempts++;
                continue;
            }

            $exists = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('tracking_id', $candidate)
                ->exists();

            if (!$exists) {
                $this->generatedTrackingIds[$candidate] = true;
                return $candidate;
            }

            $preferred = null;
            $attempts++;
        } while ($attempts < 10);

        throw new \RuntimeException('Unable to generate a unique tracking ID after multiple attempts.');
    }

    /**
     * Generate multiple unique tracking IDs efficiently
     */
    private function getUniqueTrackingIds(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $ids = [];
        $timestamp = now()->format('ymdHis');
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // Strategy: Generate slightly more than needed to account for collisions
        // avoiding a second DB trip 99.9% of the time.
        $limit = $count * 2;
        $candidates = [];

        while (count($candidates) < $limit) {
            $random = '';
            for ($j = 0; $j < 6; $j++) { // Added 2 chars for better entropy
                $random .= $characters[rand(0, strlen($characters) - 1)];
            }
            $candidates[] = "TRK-{$timestamp}-{$random}";
        }
        $candidates = array_unique($candidates);
        $candidates = array_slice($candidates, 0, $limit); // Trim back to limit

        // Check against DB in one query
        $existing = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereIn('tracking_id', $candidates)
            ->pluck('tracking_id')
            ->toArray();

        $existingMap = array_flip($existing);

        foreach ($candidates as $candidate) {
            if (!isset($existingMap[$candidate])) {
                $ids[] = $candidate;
                if (count($ids) >= $count) {
                    break;
                }
            }
        }

        // Extremely rare fallback: if we still don't have enough, generate sequentially with microtime
        // This avoids any DB hits for the fallback.
        while (count($ids) < $count) {
            $micro = microtime(true);
            $ids[] = "TRK-" . date('ymdHis') . "-" . substr(md5($micro . rand()), 0, 6);
        }

        return $ids;
    }


    /**
     * Generate a random tracking ID
     */
    private function generateTrackingId()
    {
        // Generate a structured tracking ID matching FileTracker pattern
        // Structure: TRK-YYMMDDHHMMSS-RAND (e.g., TRK-250206123456-ABCD)
        $timestamp = now()->format('ymdHis');
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $random = '';
        for ($i = 0; $i < 4; $i++) {
            $random .= $characters[rand(0, strlen($characters) - 1)];
        }

        return "TRK-{$timestamp}-{$random}";
    }


    /**
     * Try to fetch an existing tracking ID from the grouping table
     */
    private function tryFetchTrackingIdFromGrouping(string $fileNumber): ?string
    {
        try {
            $groupingService = app(\App\Services\GroupingFileNumberService::class);
            // Registry type doesn't strictly matter for tracking_id if it's the same column across tables
            $result = $groupingService->findGroupingRecord(DB::connection('sqlsrv'), $fileNumber, $fileNumber);

            if ($result['record'] && !empty($result['record']->tracking_id)) {
                return trim($result['record']->tracking_id);
            }
        } catch (\Exception $e) {
            Log::warning('Error fetching tracking_id from grouping: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get all records for a specific batch number
     */
    public function getPrintableBatches(Request $request)
    {
        try {
            $batches = DB::connection('sqlsrv')
                ->table('mls_file_no as m')
                ->whereNotNull('m.batch_no')
                ->where('m.batch_no', '<>', '')
                ->where(function ($q) {
                    $q->whereNull('m.is_deleted')->orWhere('m.is_deleted', 0);
                })
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('print_logs as pl')
                        ->whereColumn('pl.reference_number', 'm.batch_no')
                        ->where('pl.document_type', 'Commissioning Sheet')
                        ->where('pl.print_type', 'Batch');
                })
                ->groupBy('m.batch_no')
                ->selectRaw('m.batch_no, COUNT(1) as total_records, MIN(m.created_at) as first_created_at, MAX(m.created_at) as last_created_at')
                ->orderByRaw('MIN(m.created_at) ASC')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $batches->count(),
                'data' => $batches,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching printable batches', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching printable batches: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getBatchRecords(Request $request)
    {
        try {
            $validated = $request->validate([
                'batch_no' => 'nullable|string|required_without:scope',
                'scope' => 'nullable|string|in:batch,daily_24h,date',
                'date' => 'nullable|date|required_if:scope,date'
            ]);

            $scope = $validated['scope'] ?? 'batch';
            $batchNo = $validated['batch_no'] ?? null;
            $date = $validated['date'] ?? null;

            // Fetch records joined with fileNumber for IDs.
            $query = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->leftJoin('fileNumber', 'mls_file_no.tracking_id', '=', 'fileNumber.tracking_id')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->where(function ($q) {
                    $q->whereNull('mls_file_no.is_deleted')->orWhere('mls_file_no.is_deleted', 0);
                })
                ->where(function ($q) {
                    $q->where('mls_file_no.sub_source', '!=', 'OP Change of Name')
                      ->orWhereNull('mls_file_no.sub_source');
                })
                ->select([
                    'mls_file_no.*',
                    'purposes.name as purpose_name',
                    'fileNumber.id as filenumber_id'
                ]);

            if ($scope === 'daily_24h') {
                $query->where('mls_file_no.created_at', '>=', now()->subHours(24))
                    ->orderBy('mls_file_no.created_at', 'asc')
                    ->orderBy('mls_file_no.serial_number', 'asc');
            } elseif ($scope === 'date' && $date) {
                $query->whereDate('mls_file_no.created_at', $date)
                    ->orderBy('mls_file_no.serial_number', 'asc')
                    ->orderBy('mls_file_no.created_at', 'asc');
            } else {
                $query->where('mls_file_no.batch_no', $batchNo)
                ->orderBy('mls_file_no.serial_number', 'asc')
                    ->orderBy('mls_file_no.created_at', 'asc');
            }

            $records = $query->get();

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => $scope === 'daily_24h'
                        ? 'No records found in the last 24 hours'
                        : ($scope === 'date' ? 'No records found for '. $date : 'No records found for this batch number')
                ], 404);
            }

            return response()->json([
                'success' => true,
                'count' => $records->count(),
                'scope' => $scope,
                'batch_no' => $batchNo,
                'date' => $date,
                'data' => $records
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching batch records', [
                'error' => $e->getMessage(),
                'scope' => $request->input('scope'),
                'batch_no' => $request->input('batch_no')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching batch records: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Map MLS land use codes to file_indexings land_use_type (simplified version for bulk)
     */
    private function simpleExtractLandUseType(string $landUse): string
    {
        // Remove CON- prefix if present (conversion files)
        $baseLandUse = str_replace('CON-', '', $landUse);

        // Remove -RC suffix if present (recertification files)
        $baseLandUse = str_replace('-RC', '', $baseLandUse);

        // Map to full land use type names
        $mapping = [
            'RES' => 'Residential',
            'COM' => 'Commercial',
            'IND' => 'Industrial',
            'AG' => 'Agricultural',
        ];

        return $mapping[$baseLandUse] ?? $landUse;
    }
}




