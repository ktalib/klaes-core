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
use App\Models\PlotMergerApplication;
use App\Models\PlotSubdivisionApplication;
use App\Models\PlotSeparationApplication;
use App\Models\ChangeOfPurposeApplication;
use App\Models\PlotExtensionApplication;
use App\Services\ParcelUpdateNotificationService;
use App\Services\PlotWorkflowService;
use App\Services\PropertyIdAllocationService;

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
            // Build a query builder for the main fileNumber table and union it with temporary files from mls_file_no
            $query1 = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', function ($join) {
                    $join->on('fileNumber.tracking_id', '=', 'mls_file_no.tracking_id')
                        ->orOn('fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number');
                })
                ->leftJoin('instrument_capture as source_capture', 'mls_file_no.source_instrument_capture_id', '=', 'source_capture.id')
                ->leftJoin('pra as source_pra', 'mls_file_no.source_pra_id', '=', 'source_pra.id')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->select([
                    'fileNumber.id as id',
                    'fileNumber.mlsfNo as mlsfNo',
                    'fileNumber.FileName as FileName',
                    DB::raw('COALESCE(mls_file_no.created_at, fileNumber.created_at) as created_at'),
                    DB::raw('COALESCE(mls_file_no.updated_at, fileNumber.updated_at) as updated_at'),
                    'fileNumber.location as location',
                    'fileNumber.lga as lga',
                    'fileNumber.created_by as created_by',
                    'fileNumber.is_deleted as is_deleted',
                    DB::raw('COALESCE(mls_file_no.source, fileNumber.SOURCE) as SOURCE'),
                    'fileNumber.plot_no as plot_no',
                    'fileNumber.tp_no as tp_no',
                    'fileNumber.tracking_id as tracking_id',
                    DB::raw('COALESCE(mls_file_no.commissioning_date, mls_file_no.created_at, fileNumber.commissioning_date) as commissioning_date'),
                    'fileNumber.kangisFileNo as kangisFileNo',
                    'fileNumber.NewKANGISFileNo as NewKANGISFileNo',
                    'fileNumber.st_file_no as st_file_no',
                    'mls_file_no.batch_no as batch_no',
                    'mls_file_no.purpose_id as purpose_id',
                    'mls_file_no.customer_type as customer_type',
                    'mls_file_no.land_use as land_use',
                    'mls_file_no.source_instrument_capture_id as source_instrument_capture_id',
                    'mls_file_no.source_pra_id as source_pra_id',
                    DB::raw('COALESCE(source_capture.temp_fileno, source_pra.temp_fileno) as source_temp_fileno'),
                    DB::raw('COALESCE(source_capture.prop_id, source_pra.prop_id) as source_prop_id'),
                    'purposes.name as purpose_name'
                ])
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                });

            $query2 = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->leftJoin('instrument_capture as source_capture', 'mls_file_no.source_instrument_capture_id', '=', 'source_capture.id')
                ->leftJoin('pra as source_pra', 'mls_file_no.source_pra_id', '=', 'source_pra.id')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->select([
                    'mls_file_no.id as id',
                    'mls_file_no.full_file_number as mlsfNo',
                    'mls_file_no.file_name as FileName',
                    'mls_file_no.created_at as created_at',
                    'mls_file_no.updated_at as updated_at',
                    'mls_file_no.location as location',
                    'mls_file_no.lga as lga',
                    'mls_file_no.created_by as created_by',
                    DB::raw('0 as is_deleted'),
                    'mls_file_no.source as SOURCE',
                    'mls_file_no.plot_no as plot_no',
                    'mls_file_no.tp_no as tp_no',
                    'mls_file_no.tracking_id as tracking_id',
                    'mls_file_no.created_at as commissioning_date',
                    DB::raw('NULL as kangisFileNo'),
                    DB::raw('NULL as NewKANGISFileNo'),
                    DB::raw('NULL as st_file_no'),
                    'mls_file_no.batch_no as batch_no',
                    'mls_file_no.purpose_id as purpose_id',
                    'mls_file_no.customer_type as customer_type',
                    'mls_file_no.land_use as land_use',
                    'mls_file_no.source_instrument_capture_id as source_instrument_capture_id',
                    'mls_file_no.source_pra_id as source_pra_id',
                    DB::raw('COALESCE(source_capture.temp_fileno, source_pra.temp_fileno) as source_temp_fileno'),
                    DB::raw('COALESCE(source_capture.prop_id, source_pra.prop_id) as source_prop_id'),
                    'purposes.name as purpose_name'
                ])
                ->where(function ($q) {
                    $q->where('mls_file_no.file_option', 'temporary')
                        ->orWhereNotExists(function ($exists) {
                            $exists->select(DB::raw(1))
                                ->from('fileNumber')
                                ->where(function ($match) {
                                    $match->whereColumn('fileNumber.mlsfNo', 'mls_file_no.full_file_number')
                                        ->orWhereColumn('fileNumber.tracking_id', 'mls_file_no.tracking_id');
                                });
                        });
                });

            // Plot Extension transactions: retain the original file number and are
            // stored in the isolated plot_extensions table. Surface them in the
            // main list with a distinct "Plot Extension" source badge.
            $query3 = DB::connection('sqlsrv')
                ->table('plot_extensions')
                ->leftJoin('purposes', 'plot_extensions.purpose_id', '=', 'purposes.id')
                ->select([
                    'plot_extensions.id as id',
                    'plot_extensions.original_file_no as mlsfNo',
                    'plot_extensions.file_name as FileName',
                    'plot_extensions.created_at as created_at',
                    'plot_extensions.updated_at as updated_at',
                    'plot_extensions.location as location',
                    'plot_extensions.lga as lga',
                    'plot_extensions.created_by as created_by',
                    DB::raw('0 as is_deleted'),
                    DB::raw("'Plot Extension' as SOURCE"),
                    'plot_extensions.plot_no as plot_no',
                    'plot_extensions.tp_no as tp_no',
                    'plot_extensions.tracking_id as tracking_id',
                    'plot_extensions.created_at as commissioning_date',
                    DB::raw('NULL as kangisFileNo'),
                    DB::raw('NULL as NewKANGISFileNo'),
                    DB::raw('NULL as st_file_no'),
                    DB::raw('NULL as batch_no'),
                    'plot_extensions.purpose_id as purpose_id',
                    'plot_extensions.customer_type as customer_type',
                    'plot_extensions.land_use as land_use',
                    DB::raw('NULL as source_instrument_capture_id'),
                    DB::raw('NULL as source_pra_id'),
                    DB::raw('NULL as source_temp_fileno'),
                    DB::raw('NULL as source_prop_id'),
                    'purposes.name as purpose_name'
                ])
                ->where(function ($q) {
                    $q->whereNull('plot_extensions.is_deleted')->orWhere('plot_extensions.is_deleted', 0);
                });

            $unionSql = $query1->toSql() . " UNION ALL " . $query2->toSql() . " UNION ALL " . $query3->toSql();
            $bindings = array_merge($query1->getBindings(), $query2->getBindings(), $query3->getBindings());

            $query = DB::connection('sqlsrv')
                ->table(DB::raw("({$unionSql}) as sub"))
                ->mergeBindings($query1)
                ->mergeBindings($query2)
                ->mergeBindings($query3);

            // Apply filters from DataTables
            if ($request->filled('year')) {
                $year = intval($request->get('year'));
                if ($year > 1900) {
                    $query->whereYear('sub.created_at', $year);
                }
            }

            if ($request->filled('status')) {
                $status = trim($request->get('status'));
                if ($status !== '') {
                    $query->where('sub.SOURCE', $status);
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
                    $q->where('sub.mlsfNo', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sub.FileName', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sub.tracking_id', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sub.st_file_no', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sub.kangisFileNo', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sub.NewKANGISFileNo', 'LIKE', "%{$searchValue}%");
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
            $columnData = $request->input("columns.{$orderColumnIndex}.data");

            $sortMap = [
                'mlsfNo' => 'sub.mlsfNo',
                'FileName' => 'sub.FileName',
                'SOURCE' => 'sub.SOURCE',
                'commissioning_date' => 'sub.commissioning_date',
                'created_at' => 'sub.created_at',
                'updated_at' => 'sub.updated_at',
                'created_by' => 'sub.created_by',
                'purpose_name' => 'sub.purpose_name',
                'customer_type' => 'sub.customer_type',
                'land_use' => 'sub.land_use'
            ];

            $sortField = $sortMap[$columnData] ?? 'sub.created_at';
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
                        'plot extension' => 'bg-rose-100 text-rose-800',
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
            // Try to find by ID (numeric) first, then by file number string.
            // SQL Server cannot compare a non-numeric string to an int column,
            // so only add the id condition when $identifier is actually numeric.
            $isNumeric = is_numeric($identifier);
            $fileNumber = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', 'fileNumber.tracking_id', '=', 'mls_file_no.tracking_id')
                ->where(function ($query) use ($identifier, $isNumeric) {
                    if ($isNumeric) {
                        $query->where('fileNumber.id', (int) $identifier)
                              ->orWhere('fileNumber.mlsfNo', $identifier);
                    } else {
                        $query->where('fileNumber.mlsfNo', $identifier);
                    }
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
                    'mls_file_no.batch_no',
                    'mls_file_no.sit_reason',
                    'mls_file_no.source as source'
                ])
                ->first();

            if (!$fileNumber) {
                // Try finding directly in mls_file_no (for temporary files)
                $fileNumber = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                    ->where('mls_file_no.full_file_number', $identifier)
                    ->select([
                        'mls_file_no.id as id',
                        'mls_file_no.full_file_number as mlsfNo',
                        'mls_file_no.file_name as FileName',
                        'mls_file_no.plot_no as plot_no',
                        'mls_file_no.tp_no as tp_no',
                        'mls_file_no.location as location',
                        'mls_file_no.lga as lga',
                        'mls_file_no.district as district',
                        'mls_file_no.tracking_id as tracking_id',
                        'mls_file_no.purpose_id as purpose_id',
                        'mls_file_no.customer_type as customer_type',
                        'mls_file_no.batch_no as batch_no',
                        'mls_file_no.sit_reason as sit_reason',
                        'mls_file_no.source as source',
                        'mls_file_no.created_at as created_at',
                        'mls_file_no.updated_at as updated_at',
                        DB::raw("'MLS_Commissioned' as SOURCE"),
                        'purposes.name as purpose_name'
                    ])
                    ->first();
            }

            // A file already indexed/commissioned in fileNumber or mls_file_no has no
            // "source" of its own — but if it was later run through the Plot Extension
            // flow, that's the real reason it was (re-)commissioned. Surface it instead
            // of leaving source empty (which makes callers fall back to guessing the
            // type from the file-number prefix, e.g. mislabeling CON- files "Conversion").
            if ($fileNumber && empty($fileNumber->source)) {
                $plotExtensionSource = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('original_file_no', $fileNumber->mlsfNo ?? $identifier)
                    ->where(function ($query) {
                        $query->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->orderByDesc('id')
                    ->exists();

                if ($plotExtensionSource) {
                    $fileNumber->source = 'Plot Extension';
                }
            }

            if (!$fileNumber) {
                // Plot Extension fallback: these retain the original file number but live
                // only in the isolated plot_extensions table. The original file row may not
                // exist in fileNumber/mls_file_no (e.g. non-indexed files, or a different
                // production dataset), so resolve the sheet from the plot extension itself.
                $fileNumber = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->leftJoin('purposes', 'plot_extensions.purpose_id', '=', 'purposes.id')
                    ->where(function ($query) use ($identifier, $isNumeric) {
                        $query->where('plot_extensions.original_file_no', $identifier);
                        if ($isNumeric) {
                            $query->orWhere('plot_extensions.id', (int) $identifier);
                        }
                    })
                    ->where(function ($query) {
                        $query->whereNull('plot_extensions.is_deleted')->orWhere('plot_extensions.is_deleted', 0);
                    })
                    ->orderByDesc('plot_extensions.id')
                    ->select([
                        'plot_extensions.id as id',
                        'plot_extensions.original_file_no as mlsfNo',
                        'plot_extensions.file_name as FileName',
                        'plot_extensions.plot_no as plot_no',
                        'plot_extensions.tp_no as tp_no',
                        'plot_extensions.location as location',
                        'plot_extensions.lga as lga',
                        'plot_extensions.district as district',
                        'plot_extensions.tracking_id as tracking_id',
                        'plot_extensions.purpose_id as purpose_id',
                        'plot_extensions.customer_type as customer_type',
                        DB::raw('NULL as batch_no'),
                        DB::raw('NULL as sit_reason'),
                        DB::raw("'Plot Extension' as source"),
                        'plot_extensions.created_at as created_at',
                        'plot_extensions.updated_at as updated_at',
                        DB::raw("'Plot Extension' as SOURCE"),
                        'purposes.name as purpose_name'
                    ])
                    ->first();
            }

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
                'source_op_serial_number' => 'nullable|string|max:100',
            ]);

            $propId = $validated['prop_id'] ?? null;
            $sourceCaptureId = $validated['source_capture_id'] ?? null;
            $praId = $validated['pra_id'] ?? null;
            $sourceOpSerial = trim((string) ($validated['source_op_serial_number'] ?? ''));

            $record = null;

            // First: prefer explicit instrument_capture source id.
            if (!empty($sourceCaptureId)) {
                $record = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('id', $sourceCaptureId)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->first();
            }

            // Second: prop_id lookup in instrument_capture.
            if (!$record && !empty($propId)) {
                $query = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('prop_id', $propId)
                    ->where('instrument_type', 'Occupancy Permit (OP)')
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });

                if ($sourceOpSerial !== '') {
                    $query->where('op_serial_number', $sourceOpSerial);
                }

                // Guard against duplicate/ambiguous prop_id lookups when no explicit
                // source row id (or OP serial) is supplied.
                if ($sourceOpSerial === '') {
                    $candidateCount = (clone $query)->count();
                    if ($candidateCount > 1) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Multiple Occupancy Permit source rows share this Prop ID. Please re-select from OP lookup so the exact source row is used.',
                            'error_code' => 'AMBIGUOUS_PROP_ID',
                            'candidate_count' => $candidateCount,
                        ], 409);
                    }
                }

                $record = (clone $query)
                    ->whereNotNull('temp_fileno')
                    ->orderByDesc('id')
                    ->first();

                if (!$record) {
                    $record = (clone $query)
                        ->orderByDesc('id')
                        ->first();
                }
            }

            if ($record) {
                // Fetch the original (earliest) PRA record for this prop_id
                // so the OP card shows the original Grantor/Allottee.
                $origParty1 = $record->party_1_name ?? null;
                $origParty2 = $record->party_2_name ?? null;
                if (!empty($record->prop_id)) {
                    $origPraQuery = DB::connection('sqlsrv')
                        ->table('pra')
                        ->where('prop_id', $record->prop_id);

                    if ($sourceOpSerial !== '') {
                        $origPraQuery->where('op_serial_number', $sourceOpSerial);
                    }

                    $origPra = (clone $origPraQuery)
                        ->where(function ($q) {
                            $q->where('instrument_type', 'like', '%Occupancy Permit%')
                                ->orWhere('source', 'like', '%Occupancy Permit%');
                        })
                        ->orderBy('id')
                        ->first();

                    if (!$origPra) {
                        $origPra = $origPraQuery->orderBy('id')->first();
                    }

                    if ($origPra) {
                        $origParty1 = $origPra->Grantor ?? ($origPra->party_1 ?? $origParty1);
                        $origParty2 = $origPra->Grantee ?? ($origPra->party_2 ?? $origParty2);
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'prop_id' => $record->prop_id ?? null,
                        'source_capture_id' => $record->id ?? null,
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
                    ->where(function ($q) {
                        $q->where('instrument_type', 'like', '%Occupancy Permit%')
                            ->orWhere('source', 'like', '%Occupancy Permit%');
                    })
                    ->where(function ($q) {
                        $q->whereNull('instrument_type')
                            ->orWhere('instrument_type', 'not like', '%Transfer of Title%');
                    });

                if ($sourceOpSerial !== '') {
                    $praQuery->where('op_serial_number', $sourceOpSerial);
                }

                // Guard against duplicate/ambiguous prop_id lookups when no explicit
                // PRA row id (or OP serial) is supplied.
                if ($sourceOpSerial === '') {
                    $candidateCount = (clone $praQuery)->count();
                    if ($candidateCount > 1) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Multiple PRA Occupancy Permit rows share this Prop ID. Please re-select from OP lookup so the exact source row is used.',
                            'error_code' => 'AMBIGUOUS_PROP_ID',
                            'candidate_count' => $candidateCount,
                        ], 409);
                    }
                }

                $praRecord = (clone $praQuery)
                    ->whereNotNull('temp_fileno')
                    ->orderByDesc('id')
                    ->first();

                if (!$praRecord) {
                    $praRecord = (clone $praQuery)
                        ->orderByDesc('id')
                        ->first();
                }
            }

            if (!$praRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No source record found in instrument_capture or PRA for this reference.',
                ], 404);
            }

            // Fetch the original (earliest) PRA record for the OP card. Prefer the
            // resolved record's own prop_id over the request's — when $praRecord was
            // found via explicit pra_id, that id can point at a later Transfer of
            // Title row sharing the same prop_id (no request prop_id is sent in that
            // case), and without this the OP card would show the TOT's parties
            // instead of the true Grantor/Grantee.
            $origParty1 = $praRecord->Grantor ?? ($praRecord->party_1 ?? null);
            $origParty2 = $praRecord->Grantee ?? ($praRecord->party_2 ?? null);
            $origPropId = $praRecord->prop_id ?? $propId;
            if (!empty($origPropId)) {
                $origPra = DB::connection('sqlsrv')
                    ->table('pra')
                    ->where('prop_id', $origPropId)
                    ->where(function ($q) {
                        $q->where('instrument_type', 'like', '%Occupancy Permit%')
                            ->orWhere('source', 'like', '%Occupancy Permit%');
                    })
                    ->orderBy('id')
                    ->first();

                if (!$origPra) {
                    $origPra = DB::connection('sqlsrv')
                        ->table('pra')
                        ->where('prop_id', $origPropId)
                        ->orderBy('id')
                        ->first();
                }

                if ($origPra) {
                    $origParty1 = $origPra->Grantor ?? ($origPra->party_1 ?? $origParty1);
                    $origParty2 = $origPra->Grantee ?? ($origPra->party_2 ?? $origParty2);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'prop_id' => $praRecord->prop_id ?? null,
                    'source_pra_id' => $praRecord->id ?? null,
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
                'source_capture_id' => $request->input('source_capture_id'),
                'pra_id' => $request->input('pra_id'),
                'source_op_serial_number' => $request->input('source_op_serial_number'),
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

            $db = DB::connection('sqlsrv');
            $confirmTransactionChange = $request->boolean('confirm_transaction_change');

            $fileRecord = $db->table('fileNumber')->where('id', $id)->first();
            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found'
                ], 404);
            }

            $newFileName = $validatedData['FileName'];
            $nameChanged = trim((string) $fileRecord->FileName) !== trim($newFileName);

            // File number identifiers used to match related tables (customers, entities, file indexing)
            $fileNoCandidates = array_values(array_unique(array_filter([
                $fileRecord->mlsfNo ?? null,
                $fileRecord->kangisFileNo ?? null,
                $fileRecord->NewKANGISFileNo ?? null,
            ])));

            // If the name is changing, warn the user when the file already has recorded transactions
            if ($nameChanged && !empty($fileNoCandidates) && !$confirmTransactionChange) {
                $hasTransaction = $db->table('file_indexings')
                    ->whereIn('file_number', $fileNoCandidates)
                    ->where('has_transaction', 1)
                    ->exists();

                if ($hasTransaction) {
                    return response()->json([
                        'success' => false,
                        'requires_confirmation' => true,
                        'message' => 'This file has recorded transactions. Changing the name will also update the name on the linked customer, entity and file indexing records. Do you want to continue?'
                    ], 409);
                }
            }

            $updated = $db->table('fileNumber')
                ->where('id', $id)
                ->update([
                    'FileName' => $newFileName,
                    'location' => $validatedData['location'] ?? null,
                    'commissioning_date' => $validatedData['commissioning_date'] ?? null,
                    'purpose_id' => $validatedData['purpose_id'] ?? null,
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name
                ]);

            // Also update the mls_file_no table if tracking_id exists
            if (!empty($fileRecord->tracking_id)) {
                $db->table('mls_file_no')
                    ->where('tracking_id', $fileRecord->tracking_id)
                    ->update([
                        'customer_type' => $validatedData['customer_type'] ?? null,
                        'purpose_id' => $validatedData['purpose_id'] ?? null,
                        'updated_at' => now()
                    ]);
            }

            // Keep the name in sync across customers, entities and file indexing records
            if ($nameChanged && !empty($fileNoCandidates)) {
                $this->propagateFileNameChange($fileNoCandidates, $newFileName);
            }

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => $nameChanged
                        ? 'File number updated successfully. Name changes applied across related customer, entity and file indexing records.'
                        : 'File number updated successfully'
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
     * Propagate a file name change to the related customer, entity and file
     * indexing records so the holder name stays consistent everywhere it appears.
     */
    private function propagateFileNameChange(array $fileNoCandidates, string $newName): void
    {
        $db = DB::connection('sqlsrv');
        $updatedBy = Auth::user() ? (Auth::user()->first_name . ' ' . Auth::user()->last_name) : null;

        try {
            $db->table('file_indexings')
                ->whereIn('file_number', $fileNoCandidates)
                ->update([
                    'current_holder' => $newName,
                    'updated_by' => $updatedBy,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to propagate file name change to file_indexings', ['error' => $e->getMessage()]);
        }

        try {
            $db->table('customers_staging')
                ->whereIn('file_number', $fileNoCandidates)
                ->update(['customer_name' => $newName]);
        } catch (\Throwable $e) {
            // Table or column may not exist — skip gracefully
        }

        try {
            $db->table('entities_staging')
                ->whereIn('file_number', $fileNoCandidates)
                ->update(['entity_name' => $newName]);
        } catch (\Throwable $e) {
            // Table or column may not exist — skip gracefully
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
     * - File Type "Re-grant" → "Re-grant"
     * - File Type "Resettlement" → "Resettlement"
     */
    private function resolveSourceValue($applicationType, $allocatedByFilter, $defaultAllocationType, $fileOption = 'normal')
    {
        // If application type is "conversion", always return "Conversion"
        if ($applicationType === 'conversion') {
            return 'Conversion';
        }

        if ($applicationType === 'change_of_purpose') {
            return 'Change of Purpose';
        }

        if ($applicationType === 'subdivision') {
            return 'Subdivision';
        }

        if ($applicationType === 'merger') {
            return 'Merger';
        }

        if ($applicationType === 'separation') {
            return 'Separation';
        }

        if ($applicationType === 'extension') {
            return 'Extension';
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

        if ($fileOption === 'subdivision') {
            return 'Subdivision';
        }

        if ($fileOption === 'merger') {
            return 'Merger';
        }

        if ($fileOption === 'separation') {
            return 'Separation';
        }

        if ($fileOption === 'extension') {
            return 'Extension';
        }

        if ($fileOption === 'regrant') {
            return 'Re-grant';
        }

        if ($fileOption === 'resettlement') {
            return 'Resettlement';
        }

        if ($fileOption === 'reissuance') {
            return 'Re-Issuance of FileNo';
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
     * Which system this file number was commissioned from, for mls_file_no.system_sub_type.
     *
     * The One Stop Shop has no commissioning writer of its own — it deep-links
     * into the generator page and posts here — so an OSS row and a generator row
     * are otherwise identical, and `source` cannot tell them apart (the generator
     * writes 'OP Resettlement' / 'OP Direct Allocation' for its own allocations
     * too). Record the origin the client reports; the MLS file list filters on
     * this column alone. See App\Support\OssOpCommissionFilter.
     */
    private function resolveSystemSubType(bool $ossCommission): string
    {
        return $ossCommission
            ? \App\Support\OssOpCommissionFilter::OSS
            : \App\Support\OssOpCommissionFilter::MLS;
    }


    /**
     * Generate new MLS file number with land-use-based serial numbering
     */
    public function generateMlsFileNumber(Request $request)
    {
        try {
            $this->logPlotsWorkflow('info', 'Generate request received', [
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
                'district' => 'nullable|string|max:100',
                'tracking_id' => 'nullable|string|max:50',
                'file_option' => 'nullable|string|max:50',
                'commissioned_by' => 'nullable|string|max:255',
                'commission_date' => 'nullable|date',
                'commission_time' => 'nullable|string',
                'customer_type' => 'required|string|in:Individual,Corporate,Multiple,Government',
                'gender' => 'required|string|in:Male,Female,Corporate,Joint',
                'existing_file_no' => 'nullable|string|max:50',
                'existing_file_no_manual' => 'nullable|string|max:50',
                'purpose_id' => 'nullable|integer',
                'allocation_id' => 'nullable|integer',
                'application_type' => 'nullable|string|max:50',
                'allocated_by_filter' => 'nullable|string|max:100',
                'default_allocation_type' => 'nullable|string|max:50',
                'require_op_source' => 'nullable|boolean',
                // Set by the client when commissioning was raised from an OSS entry
                // point; see resolveSystemSubType().
                'oss_commission' => 'nullable|boolean',
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
                // JSON array of {file_no, title, type, indexing_id}; see parseRelatedFiles().
                'related_files' => 'nullable|string',
                'merger_app_id' => 'nullable|integer',
                'subdivision_app_id' => 'nullable|integer',
                'separation_app_id' => 'nullable|integer',
                'change_of_purpose_app_id' => 'nullable|integer',
                'file_option' => 'nullable|string|max:50',
                'sit_reason' => 'nullable|string|max:1000',
                // Re-Issuance of FileNo: the old (duplicated) number being re-issued.
                'old_fileno' => 'nullable|string|max:100',
            ]);

            $landUse = $validated['land_use'] ?? null;
            $fileOption = $validated['file_option'] ?? 'normal';
            $year = date('Y');
            $skippedSerials = []; // serials skipped because their file number was already taken

            Log::info('MLS generate: Land Use check', [
                'received_land_use' => $landUse,
                'request_land_use' => $request->input('land_use'),
                'request_prefix' => $request->input('prefix'),
                'application_type' => $request->input('application_type'),
                'file_option' => $fileOption
            ]);

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

            // Re-Issuance replaces a duplicated file number, so the old number is what
            // makes the record meaningful — refuse to commission without it.
            if ($fileOption === 'reissuance' && empty($validated['old_fileno'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select the old (duplicate) file number being re-issued.',
                ], 422);
            }

            $requireOpSource = (bool) ($validated['require_op_source'] ?? false);
            $this->logPlotsWorkflow('info', 'Validated OP linkage payload', [
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
                $this->logPlotsWorkflow('warning', 'Blocked generate due to missing required source OP');
                return response()->json([
                    'success' => false,
                    'message' => 'Source OP record is required before commissioning. Please select an existing OP and continue.',
                ], 422);
            }

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $applicationType = $validated['application_type'] ?? 'new';
                $originalFileNo = $request->input('original_file_no');

                if ($applicationType === 'change_of_purpose') {
                    if (empty($originalFileNo)) {
                        throw new \Exception("Original file number missing for Change of Purpose. Please select the file to change.");
                    }

                    if (empty($landUse)) {
                        throw new \Exception("New land use purpose must be provided for Change of Purpose.");
                    }

                    // 1. Generate new file number, consuming new serial
                    $forceFileNumber = $request->input('force_file_number');
                    if ($forceFileNumber) {
                        $fullFileNumber = $forceFileNumber;
                        $serialParts = explode('-', $fullFileNumber);
                        $serial = (int) end($serialParts);
                        \App\Models\MlsSerialControl::initialize($landUse, $year, $serial);
                        \Log::channel('fileno_duplicates')->info('COP: force_file_number used', [
                            'forced_file_number' => $fullFileNumber,
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial' => $serial,
                            'user_id' => Auth::id(),
                            'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                        ]);
                    } else {
                        $serial = \App\Models\MlsSerialControl::getNextSerial($landUse, $year);
                        $fullFileNumber = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $serial);

                        // Check for duplicates in both modern and legacy tables
                        if (
                            \App\Models\MlsFileNo::where('full_file_number', $fullFileNumber)->exists() ||
                            DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fullFileNumber)->exists()
                        ) {
                            $suggested = $this->findNextAvailableFileNumber($landUse, $year, $serial);
                            DB::connection('sqlsrv')->rollBack();
                            \Log::channel('fileno_duplicates')->warning('COP: duplicate detected', [
                                'conflicting_file_number' => $fullFileNumber,
                                'suggested_file_number' => $suggested,
                                'land_use' => $landUse,
                                'year' => $year,
                                'serial' => $serial,
                                'original_file_no' => $originalFileNo,
                                'user_id' => Auth::id(),
                                'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                            ]);
                            return response()->json([
                                'success' => false,
                                'duplicate' => true,
                                'conflicting_file_number' => $fullFileNumber,
                                'suggested_file_number' => $suggested,
                                'message' => "File number {$fullFileNumber} already exists.",
                            ]);
                        }
                    }

                    $commissionedBy = ($validated['commissioned_by'] ?? null) ?: ((Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System');

                    // 2. Fetch Old File Record
                    $oldFileNoRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $originalFileNo)->first();
                    if (!$oldFileNoRecord) {
                        throw new \Exception("Original file ($originalFileNo) not found in fileNumber records.");
                    }

                    $oldIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $originalFileNo)->first();

                    // The OLD file's real commissioning date — preserved on the archive row so the
                    // Legal Search timeline can still show when the predecessor was commissioned.
                    // Stamping now() here would falsely date a years-old file to the CoP day.
                    $oldCommissioningDate = \App\Models\MlsFileNo::where('full_file_number', $originalFileNo)
                        ->orderByDesc('id')
                        ->value('commissioning_date')
                        ?? ($oldFileNoRecord->commissioning_date ?? null);

                    // 3. Decommission old file into decommissioned_files
                    $copDecommissionRow = [
                        'file_number_id' => $oldFileNoRecord->id ?? 0,
                        'file_no' => $originalFileNo,
                        'mls_file_no' => $originalFileNo,
                        'file_name' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? 'Change of Purpose generated',
                        'commissioning_date' => $oldCommissioningDate,
                        'decommissioning_date' => now(),
                        'decommissioning_reason' => 'Change of Purpose to ' . $landUse,
                        'decommissioned_by' => $commissionedBy,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    // old -> successor pointer (column added 2026_07_03). For Change of Purpose the
                    // successor is the newly minted number the old file was renamed to.
                    if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
                        $copDecommissionRow['successor_file_no'] = $fullFileNumber;
                    }
                    DB::connection('sqlsrv')->table('decommissioned_files')->insert($copDecommissionRow);

                    // Try to fetch new tracking ID from grouping table if not provided
                    $trackingId = $validated['tracking_id'] ?? null;
                    if (empty($trackingId)) {
                        $trackingId = $this->tryFetchTrackingIdFromGrouping($fullFileNumber);
                    }

                    // 4. Update core file numbers
                    DB::connection('sqlsrv')->table('fileNumber')
                        ->where('mlsfNo', $originalFileNo)
                        ->update([
                            'mlsfNo' => $fullFileNumber,
                            'tracking_id' => $trackingId ?? $oldFileNoRecord->tracking_id,
                            'updated_at' => now()
                        ]);

                    // Update mls_file_no model if present. The new number begins its own lifecycle,
                    // so its commissioning date is the CoP date — leaving the old file's
                    // commissioning_date on the renamed row would make the successor's
                    // "File Commissioning" timeline entry predate the Change of Purpose itself.
                    $mlsFileNoUpdated = \App\Models\MlsFileNo::where('full_file_number', $originalFileNo)->update([
                        'full_file_number' => $fullFileNumber,
                        'land_use' => $landUse,
                        'year' => $year,
                        'serial_number' => $serial,
                        'tracking_id' => $trackingId ?? $oldFileNoRecord->tracking_id,
                        'commissioning_date' => now(),
                    ]);

                    if (!$mlsFileNoUpdated) {
                        \App\Models\MlsFileNo::create([
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial_number' => $serial,
                            'full_file_number' => $fullFileNumber,
                            'file_name' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? null,
                            'plot_no' => $validated['plot_no'] ?? $oldFileNoRecord->plot_no ?? null,
                            'tp_no' => $validated['tp_no'] ?? $oldFileNoRecord->tp_no ?? null,
                            'location' => $validated['location'] ?? $oldFileNoRecord->location ?? null,
                            'lga' => $validated['lga'] ?? $oldFileNoRecord->lga ?? null,
                            'district' => $validated['district'] ?? $oldFileNoRecord->district ?? null,
                            'tracking_id' => $trackingId ?? $oldFileNoRecord->tracking_id,
                            'customer_type' => $validated['customer_type'] ?? 'Individual',
                            'file_option' => $validated['file_option'] ?? 'normal',
                            'created_by' => $commissionedBy,
                            'commissioning_date' => now(),
                            'source' => 'Change of Purpose',
                            'purpose_id' => $validated['purpose_id'] ?? null,
                        ]);
                    }

                    // 5. Resolve or Allocate Property ID across staging tables to ensure historical linkage
                    $propId = null;
                    try {
                        $allocationService = app(\App\Services\PropertyIdAllocationService::class);
                        // Pass new file number as primary/mls and the old file number as temp_fileno to scan all staging tables (like CofO_staging)
                        $propId = $allocationService->allocateOrRetrievePropId(
                            $fullFileNumber,
                            $fullFileNumber,
                            null,
                            null,
                            ['temp_fileno' => $originalFileNo]
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('PropertyIdAllocationService failed during Change of Purpose, falling back to basic lookup', [
                            'error' => $e->getMessage()
                        ]);
                        $propId = $validated['source_prop_id'] ?? ($oldIndexing->prop_id ?? null);
                    }

                    // Change of Purpose is a rename of the SAME property (same parcel,
                    // new land use), so the new file must inherit the ORIGINAL file's
                    // prop_id. The allocator can miss it when the old prop_id lives
                    // only on the source's PRA row (subdivision children carry prop_id
                    // in PRA, not file_indexings), so fall back to that lookup.
                    if (empty($propId)) {
                        $propId = ($oldIndexing->prop_id ?? null)
                            ?: DB::connection('sqlsrv')->table('pra')
                                ->where('mlsFNo', $originalFileNo)
                                ->whereNotNull('prop_id')
                                ->orderByDesc('id')
                                ->value('prop_id')
                            ?: ($validated['source_prop_id'] ?? null);
                    }

                    // 6. Update indexings to flip related_fileno and set prop_id
                    if ($oldIndexing) {
                        $correspondingMatch = DB::connection('sqlsrv')
                            ->table('corresponding_fileno')
                            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [trim((string) $fullFileNumber)])
                            ->value('fileno');

                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $originalFileNo)
                            ->update([
                                'file_number' => $fullFileNumber,
                                'land_use_type' => $landUse,
                                // Store as a JSON array for consistency with Merger/Subdivision/
                                // Separation/Extension branches (which write json_encode([$parent])).
                                'related_fileno' => json_encode([$originalFileNo]),
                                'is_corresponding_file' => ($correspondingMatch !== null) ? 1 : 0,
                                'corresponding_fileno' => $correspondingMatch,
                                'tracking_id' => $trackingId ?? $oldIndexing->tracking_id,
                                'prop_id' => $propId,
                                'updated_at' => now()
                            ]);
                    } else {
                        // Some legacy files only ever lived in the `fileNumber` table with no
                        // file_indexings counterpart (pre-dates indexing, or was migrated
                        // straight into fileNumber). Renaming mlsfNo alone then leaves the new
                        // file invisible everywhere that reads file_indexings — e.g. the main
                        // "MLS File Number Generator" grid — even though fileNumber shows it as
                        // commissioned. Backfill a fresh row from what's actually available.
                        $correspondingMatch = DB::connection('sqlsrv')
                            ->table('corresponding_fileno')
                            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [trim((string) $fullFileNumber)])
                            ->value('fileno');

                        DB::connection('sqlsrv')->table('file_indexings')->insert([
                            'file_number' => $fullFileNumber,
                            'file_title' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? null,
                            'land_use_type' => $landUse,
                            'plot_number' => $validated['plot_no'] ?? $oldFileNoRecord->plot_no ?? null,
                            'tp_no' => $oldFileNoRecord->tp_no ?? null,
                            'location' => $validated['location'] ?? $oldFileNoRecord->location ?? null,
                            'lga' => $validated['lga'] ?? $oldFileNoRecord->lga ?? null,
                            'district' => $oldFileNoRecord->district ?? null,
                            'related_fileno' => json_encode([$originalFileNo]),
                            'is_corresponding_file' => ($correspondingMatch !== null) ? 1 : 0,
                            'corresponding_fileno' => $correspondingMatch,
                            'tracking_id' => $trackingId ?? $oldFileNoRecord->tracking_id,
                            'prop_id' => $propId,
                            'current_holder' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? null,
                            'original_holder' => $oldFileNoRecord->FileName ?? null,
                            'created_by' => $commissionedBy,
                            'workflow_status' => 'indexed',
                            'is_updated' => 0,
                            'is_deleted' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 7. Update Entities and Customers staging — same legacy gap as above: if
                    // the old file never had staging rows either, updating by original file
                    // number silently affects zero rows, so insert fresh ones instead.
                    $entityUpdated = DB::connection('sqlsrv')->table('entities_staging')
                        ->where('file_number', $originalFileNo)
                        ->update([
                            'file_number' => $fullFileNumber,
                            'updated_at' => now()
                        ]);
                    if (!$entityUpdated) {
                        DB::connection('sqlsrv')->table('entities_staging')->insert([
                            'entity_type' => $validated['customer_type'] ?? 'Individual',
                            'entity_name' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? 'N/A',
                            'file_number' => $fullFileNumber,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $customerUpdated = DB::connection('sqlsrv')->table('customers_staging')
                        ->where('file_number', $originalFileNo)
                        ->update([
                            'file_number' => $fullFileNumber,
                            'account_no' => $fullFileNumber,
                            'updated_at' => now()
                        ]);
                    if (!$customerUpdated) {
                        DB::connection('sqlsrv')->table('customers_staging')->insert([
                            'customer_type' => $validated['customer_type'] ?? 'Individual',
                            'file_number' => $fullFileNumber,
                            'customer_name' => $validated['file_name'] ?? $oldFileNoRecord->FileName ?? 'N/A',
                            'account_no' => $fullFileNumber,
                            'status' => 'Active',
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 8. Write the Change of Purpose transaction into PRA via the shared
                    //    PraRecordService so the correct columns, prop_id allocation and
                    //    PropID-timeline sync are applied (mirrors the Subdivision/Merger/
                    //    Extension path). The previous raw insert referenced columns that
                    //    do not exist (Prop_id / transaction / instrument /
                    //    Property_Description_part1) and set temp_fileno instead of mlsFNo,
                    //    so it never produced a findable PRA row for the new file.
                    try {
                        $ownerName = (string) (($validated['file_name'] ?? null)
                            ?: ($oldIndexing->file_title ?? null)
                            ?: ($oldFileNoRecord->FileName ?? ''));

                        app(\App\Services\Pra\PraRecordService::class)->createRecord([
                            'mlsFNo' => $fullFileNumber,
                            'fileno' => $fullFileNumber,
                            'temp_fileno' => null,
                            'prop_id' => $propId,
                            'transaction_type' => 'Change of Purpose',
                            'instrument_type' => 'Change of Purpose',
                            'transaction_date' => now()->toDateString(),
                            'reg_date' => '0/0/0',
                            'regNo' => '0/0/0',
                            'serialNo' => '0',
                            'pageNo' => '0',
                            'volumeNo' => '0',
                            'system_source' => 'MLS_CHANGE_OF_PURPOSE',
                            'land_use' => $landUse,
                            'plot_no' => (string) (($validated['plot_no'] ?? null) ?: ($oldIndexing->plot_number ?? '')),
                            'lgsaOrCity' => (string) ($validated['lga'] ?? ''),
                            'location' => (string) (($validated['location'] ?? null) ?: ($oldIndexing->location ?? '')),
                            'property_description' => (string) (($validated['location'] ?? null) ?: ($oldIndexing->location ?? '')),
                            'Grantor' => $ownerName,
                            'Grantee' => $ownerName,
                            'party_1' => $ownerName,
                            'party_2' => $ownerName,
                            'related_file_number' => $originalFileNo,
                            'comments' => 'Change of Purpose: ' . $originalFileNo . ' -> ' . $fullFileNumber . ' (' . $landUse . ')',
                            'remarks' => 'Commissioned via Change of Purpose workflow',
                        ], Auth::id());
                    } catch (\Throwable $praError) {
                        \Illuminate\Support\Facades\Log::error('PRA creation failed for Change of Purpose (non-critical)', [
                            'error' => $praError->getMessage(),
                            'old_file' => $originalFileNo,
                            'new_file' => $fullFileNumber,
                        ]);
                    }

                    // 9. Sync the source Change of Purpose application to 'commissioned' so
                    //    it stops showing as pending on the applications list. Falls back to
                    //    looking the application up by its original file number when the
                    //    caller didn't pass change_of_purpose_app_id explicitly.
                    try {
                        $copApp = !empty($validated['change_of_purpose_app_id'])
                            ? ChangeOfPurposeApplication::find($validated['change_of_purpose_app_id'])
                            : ChangeOfPurposeApplication::where('file_no', $originalFileNo)
                                ->whereIn('status', ['approved', 'processing'])
                                ->orderByDesc('id')
                                ->first();

                        if ($copApp) {
                            $copApp->update([
                                'status' => ChangeOfPurposeApplication::STATUS_COMMISSIONED,
                                'remarks' => trim(($copApp->remarks ?? '') . "\nCommissioned: {$originalFileNo} -> {$fullFileNumber} on " . now()->toDateTimeString()),
                                'updated_by' => Auth::id(),
                            ]);

                            try {
                                app(ParcelUpdateNotificationService::class)->notifyCommissioned(
                                    'change_of_purpose',
                                    $copApp->id,
                                    $originalFileNo,
                                    $fullFileNumber,
                                    $commissionedBy
                                );
                            } catch (\Throwable $notifyError) {
                                \Illuminate\Support\Facades\Log::warning('Change of Purpose commissioned notification failed (non-critical)', [
                                    'error' => $notifyError->getMessage(),
                                    'app_id' => $copApp->id,
                                ]);
                            }
                        }
                    } catch (\Throwable $copSyncError) {
                        \Illuminate\Support\Facades\Log::error('Failed to sync change_of_purpose_applications status after commissioning (non-critical)', [
                            'error' => $copSyncError->getMessage(),
                            'old_file' => $originalFileNo,
                            'new_file' => $fullFileNumber,
                        ]);
                    }

                    $this->logPlotsWorkflow('info', 'Change of Purpose completed seamlessly', [
                        'old_file' => $originalFileNo,
                        'new_file' => $fullFileNumber,
                        'prop_id' => $propId
                    ]);

                    DB::connection('sqlsrv')->commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Change of Purpose generated successfully.',
                        'decommission_summary' => [
                            'archived' => [$originalFileNo],
                        ],
                        'data' => [
                            // 'file_number' is the key the success modal and other callers
                            // read (see MlsFileNoController's generic response below);
                            // 'file_no' is kept for any older caller still reading that name.
                            'file_number' => $fullFileNumber,
                            'file_no' => $fullFileNumber,
                            'old_file' => $originalFileNo,
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial' => str_pad($serial, 4, '0', STR_PAD_LEFT),
                            'tracking_id' => $trackingId ?? $oldFileNoRecord->tracking_id,
                            'application_type' => 'change_of_purpose',
                            'prop_id' => $propId,
                            'parent_prop_id' => null,
                        ],
                    ]);
                }

                $fileOption = $validated['file_option'] ?? 'normal';

                // Determine file number and serial based on option
                if ($fileOption === 'temporary') {
                    // Logic for Temporary Files: Now consumes a serial number as per user requirement
                    $existingFileNo = $validated['existing_file_no'] ?? $validated['existing_file_no_manual'];

                    if (empty($existingFileNo)) {
                        throw new \Exception("Existing file number is required for temporary version.");
                    }

                    // Extract year and serial from the existing file number (e.g. RES-1994-680)
                    $parsedYear = null;
                    $parsedSerial = null;
                    $cleanExisting = preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($existingFileNo));
                    $parts = explode('-', $cleanExisting);
                    $yearIndex = -1;
                    foreach ($parts as $idx => $part) {
                        if (preg_match('/^\d{4}$/', $part)) {
                            $yearIndex = $idx;
                            break;
                        }
                    }

                    if ($yearIndex !== -1) {
                        $parsedYear = (int)$parts[$yearIndex];
                        $serialPart = implode('-', array_slice($parts, $yearIndex + 1));
                        $parsedSerial = (int)preg_replace('/[^0-9]/', '', $serialPart);
                        
                        $extractedPrefix = implode('-', array_slice($parts, 0, $yearIndex));
                        if (!empty($extractedPrefix)) {
                            $landUse = $extractedPrefix;
                        }
                    }

                    if ($parsedYear !== null && $parsedSerial !== null) {
                        $year = $parsedYear;
                        $serial = $parsedSerial;
                        $fullFileNumber = $cleanExisting . '(T)';
                    } else {
                        // Fallback to original logic if parsing fails
                        $serial = \App\Models\MlsSerialControl::getNextSerial($landUse, $year);
                        $fullFileNumber = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $serial) . '(T)';
                    }

                    // Check if this temporary file already exists to avoid duplicates
                    $forceTempFileNumber = $request->input('force_file_number');
                    if ($forceTempFileNumber) {
                        $fullFileNumber = $forceTempFileNumber;
                        $baseForced = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fullFileNumber);
                        $serialParts = explode('-', $baseForced);
                        $serial = (int) end($serialParts);
                        \App\Models\MlsSerialControl::initialize($landUse, $year, $serial);
                        \Log::channel('fileno_duplicates')->info('Temporary: force_file_number used', [
                            'forced_file_number' => $fullFileNumber,
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial' => $serial,
                            'user_id' => Auth::id(),
                            'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                        ]);
                    } elseif (
                        \App\Models\MlsFileNo::where('full_file_number', $fullFileNumber)->exists() ||
                        DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fullFileNumber)->exists()
                    ) {
                        $suggested = $this->findNextAvailableFileNumber($landUse, $year, $serial, '(T)');
                        DB::connection('sqlsrv')->rollBack();
                        \Log::channel('fileno_duplicates')->warning('Temporary: duplicate detected', [
                            'conflicting_file_number' => $fullFileNumber,
                            'suggested_file_number' => $suggested,
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial' => $serial,
                            'user_id' => Auth::id(),
                            'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                        ]);
                        return response()->json([
                            'success' => false,
                            'duplicate' => true,
                            'conflicting_file_number' => $fullFileNumber,
                            'suggested_file_number' => $suggested,
                            'message' => "Temporary file number {$fullFileNumber} already exists.",
                        ]);
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
                    $forceFileNumber = $request->input('force_file_number');
                    if ($forceFileNumber) {
                        $fullFileNumber = $forceFileNumber;
                        $serialParts = explode('-', $fullFileNumber);
                        $serial = (int) end($serialParts);
                        \App\Models\MlsSerialControl::initialize($landUse, $year, $serial);
                        \Log::channel('fileno_duplicates')->info('Normal: force_file_number used', [
                            'forced_file_number' => $fullFileNumber,
                            'land_use' => $landUse,
                            'year' => $year,
                            'serial' => $serial,
                            'file_option' => $fileOption,
                            'user_id' => Auth::id(),
                            'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                        ]);
                    } else {
                        // Allocate the next free serial, automatically skipping any number already
                        // taken in mls_file_no, fileNumber, or file_indexings, instead of failing.
                        $allocation = $this->allocateNextFreeSerial($landUse, $year);
                        $serial = $allocation['serial'];
                        $fullFileNumber = $allocation['file_number'];
                        $skippedSerials = array_merge($skippedSerials, $allocation['skipped']);

                        if (!empty($allocation['skipped'])) {
                            \Log::channel('fileno_duplicates')->info('Normal: serials skipped, advanced to next free', [
                                'allocated_file_number' => $fullFileNumber,
                                'skipped' => $allocation['skipped'],
                                'land_use' => $landUse,
                                'year' => $year,
                                'file_option' => $fileOption,
                                'user_id' => Auth::id(),
                                'user' => Auth::user()->name ?? Auth::user()->email ?? 'Unknown',
                            ]);
                        }
                    }
                }

                $commissionedBy = ($validated['commissioned_by'] ?? null) ?: ((Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System');

                // 1. Determine the file number to use for tracking ID lookup
                // For temporary and extension files, strip the suffix to get the base file number
                $lookupFileNumber = $fullFileNumber;
                if ($fileOption === 'temporary') {
                    // Use the original source file number for tracking ID lookup
                    $lookupFileNumber = $existingFileNo;
                } elseif ($fileOption === 'extension') {
                    // Strip AND EXTENSION suffix for extension files
                    $lookupFileNumber = preg_replace('/\s+AND\s+EXTENSION\s*$/i', '', $fullFileNumber);
                } elseif ($fileOption === 'merger' || $fileOption === 'subdivision') {
                    // For Merger/Subdivision, use the commissioned file number directly for lookup
                    $lookupFileNumber = $fullFileNumber;
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
                $appType = $validated['application_type'] ?? 'new';
                if (!empty($validated['merger_app_id']))
                    $appType = 'merger';
                if (!empty($validated['subdivision_app_id']))
                    $appType = 'subdivision';
                if (!empty($validated['separation_app_id']))
                    $appType = 'separation';

                $sourceValue = $this->resolveSourceValue(
                    $appType,
                    $validated['allocated_by_filter'] ?? '',
                    $validated['default_allocation_type'] ?? null,
                    $fileOption
                );

                Log::info('MLS generate resolved source value', [
                    'file_number' => $fullFileNumber,
                    'app_type' => $appType,
                    'source_value' => $sourceValue
                ]);

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
                    'district' => $validated['district'] ?? null,
                    'tracking_id' => $trackingId,
                    'customer_type' => $validated['customer_type'],
                    'gender' => $validated['gender'] ?? null,
                    'file_option' => $validated['file_option'] ?? 'normal',
                    'created_by' => $commissionedBy,
                    'purpose_id' => $validated['purpose_id'] ?? null,
                    'source' => $sourceValue,
                    'sub_source' => $validated['sub_source'] ?? null,
                    'system_sub_type' => $this->resolveSystemSubType(
                        (bool) ($validated['oss_commission'] ?? false)
                    ),
                    'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                    'source_pra_id' => $validated['source_pra_id'] ?? null,
                    'sit_reason' => $fileOption === 'sit' ? ($validated['sit_reason'] ?? null) : null,
                    'old_fileno' => $fileOption === 'reissuance' ? ($validated['old_fileno'] ?? null) : null,
                ]);

                if ($fileOption !== 'temporary') {
                    // Also create record in fileNumber table for compatibility
                    $fileNumberData = [
                        'tracking_id' => $trackingId,
                        'mlsfNo' => $fullFileNumber,
                        'FileName' => $validated['file_name'] ?? null,
                        'plot_no' => $validated['plot_no'] ?? null,
                        'tp_no' => $validated['tp_no'] ?? null,
                        'location' => $validated['location'] ?? null,
                        'lga' => $validated['lga'] ?? null,
                        'district' => $validated['district'] ?? null,
                        'source' => 'MLS_Commissioned',
                        'type' => 'MlsFileNO',
                        'created_by' => $commissionedBy,
                        'updated_at' => now()
                    ];
                    if (!empty($validated['related_fileno'])) {
                        $fileNumberData['related_fileno'] = json_encode([$validated['related_fileno']]);
                    }
                    DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberData);
                }

                if ($fileOption !== 'temporary') {
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
                        if (!empty($validated['plot_no']))
                            $addressParts[] = "Plot " . $validated['plot_no'];
                        if (!empty($validated['location']))
                            $addressParts[] = $validated['location'];
                        if (!empty($validated['lga']))
                            $addressParts[] = $validated['lga'];
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
                    } catch (\Exception $e) {
                        \Log::error('Error in staging logic during file generation: ' . $e->getMessage());
                    }
                } else {
                    // For Temporary Files: Do not insert new rows into entities_staging or customers_staging.
                    // Instead, update the parent file_indexings record:
                    // set has_temp_file = 1 and temp_file_no = the temp file, where file_number = temp file without the (T)
                    try {
                        $parentFileNo = preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($existingFileNo ?? ''));
                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $parentFileNo)
                            ->update([
                                'has_temp_file' => 1,
                                'temp_file_no' => $fullFileNumber,
                                'updated_at' => now()
                            ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to update parent file_indexings record with temporary file: ' . $e->getMessage());
                    }
                }

                // Open tracking for the new file. The commissioning screen asks where
                // the file goes next, so it starts with two movement lines: the File
                // Commissioning line and the onward trip to the chosen unit/office.
                // Without a destination the file keeps the derived commissioning line
                // (DIIT) until someone logs it out.
                $this->startCommissioningTracking($request, $mlsRecord);

                $parentPropId = null;
                $relatedFileNumbers = null;
                $motherOwner = null;


                try {
                    $groupingService = app(\App\Services\GroupingFileNumberService::class);
                    $registryName = $validated['registry'] ?? 'Lands Registry';

                    $this->logPlotsWorkflow('info', 'MLS generate linking grouping record', [
                        'file_number' => $fullFileNumber,
                        'lookup' => $lookupFileNumber,
                        'option' => $fileOption
                    ]);

                    // Finalize the linkage in grouping table
                    $groupingService->linkAwaitingToMls($fullFileNumber, $lookupFileNumber, $registryName);

                    $this->logPlotsWorkflow('info', 'MLS generate grouping link done', ['file_number' => $fullFileNumber]);
                } catch (\Exception $e) {
                    $this->logPlotsWorkflow('warning', 'Failed to link grouping record', ['error' => $e->getMessage(), 'file' => $fullFileNumber]);
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
                $this->logPlotsWorkflow('info', 'Mirror decision computed', [
                    'file_number' => $fullFileNumber,
                    'should_mirror' => $shouldMirror,
                    'application_type' => $appType,
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
                    $this->logPlotsWorkflow('info', 'Mirror execution start', [
                        'file_number' => $fullFileNumber,
                        'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                    ]);
                    Log::info('MLS generate resettlement mirror start', ['file_number' => $fullFileNumber]);
                    $this->createResettlementLinkedRecords($validated, $fullFileNumber, $trackingId, $commissionedBy);
                    Log::info('MLS generate resettlement mirror done', ['file_number' => $fullFileNumber]);
                    $this->logPlotsWorkflow('info', 'Mirror execution completed', ['file_number' => $fullFileNumber]);
                } else {
                    $skipAutoPra = !empty($validated['source_pra_id']);
                    if ($skipAutoPra) {
                        Log::info('MLS generate basic PRA creation skipped for Capture Existing OP fallback path', [
                            'file_number' => $fullFileNumber,
                            'source_pra_id' => $validated['source_pra_id'] ?? null,
                        ]);
                    }

                    $propIdService = app(\App\Services\PropertyIdAllocationService::class);

                    if (!empty($validated['merger_app_id'])) {
                        $mergerApp = \App\Models\PlotMergerApplication::find($validated['merger_app_id']);
                        if ($mergerApp) {
                            $sourceFiles = $mergerApp->plotSizes()->whereIn('type', ['source', 'merger_source'])->pluck('source_file_no')->toArray();
                            if (!empty($sourceFiles)) {
                                $sourceRecords = DB::connection('sqlsrv')->table('file_indexings')
                                    ->whereIn('file_number', $sourceFiles)
                                    ->get();

                                $propIds = $sourceRecords->pluck('prop_id')->unique()->filter()->toArray();
                                foreach ($sourceFiles as $sf) {
                                    if (!$sourceRecords->where('file_number', $sf)->first()?->prop_id) {
                                        try {
                                            $sfPropId = $propIdService->allocateOrRetrievePropId($sf, null, null, null, ['skip_lookup' => false]);
                                            if ($sfPropId)
                                                $propIds[] = $sfPropId;
                                        } catch (\Exception $e) {
                                        }
                                    }
                                }

                                $parentPropId = implode(',', array_unique($propIds));
                                $relatedFileNumbers = json_encode($sourceFiles);
                                $relatedFileTitle = 'Consolidated Titles';

                                $indexedFiles = $sourceRecords->pluck('file_number')->toArray();
                                $missingFiles = array_diff($sourceFiles, $indexedFiles);
                                $titles = $sourceRecords->pluck('file_title')->unique()->filter()->toArray();

                                if (!empty($missingFiles)) {
                                    $registryTitles = DB::connection('sqlsrv')->table('fileNumber')
                                        ->whereIn('mlsfNo', $missingFiles)
                                        ->pluck('FileName')
                                        ->unique()
                                        ->filter()
                                        ->toArray();
                                    $titles = array_unique(array_merge($titles, $registryTitles));
                                }

                                $titles = array_values(array_filter($titles));
                                if (count($titles) > 1) {
                                    $last = array_pop($titles);
                                    $motherOwner = implode(', ', $titles) . ' and ' . $last;
                                } else {
                                    $motherOwner = $titles[0] ?? 'Multiple Owners';
                                }
                            }
                        }
                    } elseif (!empty($validated['subdivision_app_id'])) {
                        $subdivisionApp = \App\Models\PlotSubdivisionApplication::find($validated['subdivision_app_id']);
                        if ($subdivisionApp) {
                            $motherFileNo = (string) $subdivisionApp->file_no;
                            $motherFile = DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $motherFileNo)
                                ->first();

                            $resolvedPropId = $motherFile->prop_id ?? null;
                            if (!$resolvedPropId) {
                                try {
                                    $resolvedPropId = $propIdService->allocateOrRetrievePropId($motherFileNo, null, null, null, ['skip_lookup' => false]);
                                } catch (\Exception $e) {
                                }
                            }

                            $parentPropId = $resolvedPropId;
                            $relatedFileNumbers = json_encode([$motherFileNo]);
                            $motherOwner = $motherFile->file_title ?? 'Original Owner';
                        }
                    } elseif (!empty($validated['separation_app_id'])) {
                        $separationApp = \App\Models\PlotSeparationApplication::find($validated['separation_app_id']);
                        if ($separationApp) {
                            $motherFileNo = (string) $separationApp->file_no;
                            $motherFile = DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $motherFileNo)
                                ->first();

                            $resolvedPropId = $motherFile->prop_id ?? null;
                            if (!$resolvedPropId) {
                                try {
                                    $resolvedPropId = $propIdService->allocateOrRetrievePropId($motherFileNo, null, null, null, ['skip_lookup' => false]);
                                } catch (\Exception $e) {
                                }
                            }

                            $parentPropId = $resolvedPropId;
                            $relatedFileNumbers = json_encode([$motherFileNo]);
                            $motherOwner = $motherFile->file_title ?? 'Original Owner';
                        }
                    } elseif (($validated['file_option'] ?? '') === 'extension' && !empty($validated['existing_file_no'])) {
                        $extFileNo = (string) $validated['existing_file_no'];
                        $extFile = DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $extFileNo)
                            ->first();

                        $resolvedPropId = $extFile->prop_id ?? null;
                        if (!$resolvedPropId) {
                            try {
                                $resolvedPropId = $propIdService->allocateOrRetrievePropId($extFileNo, null, null, null, ['skip_lookup' => false]);
                            } catch (\Exception $e) {
                            }
                        }

                        $parentPropId = $resolvedPropId;
                        $relatedFileNumbers = json_encode([$extFileNo]);
                        $motherOwner = $extFile->file_title ?? 'Original Owner';
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
                                'mlsFNo' => $fullFileNumber,
                                'fileno' => $fullFileNumber,
                                'source_op_id' => $validated['source_instrument_capture_id'] ?? null,
                                'source_op_table' => !empty($validated['source_instrument_capture_id']) ? 'instrument_capture' : null,
                            ]);

                            if ($existingOp) {
                                $praService->linkSourceOpRecord($existingOp, [
                                    'prop_id' => $propId,
                                    'mlsFNo' => $fullFileNumber,
                                ]);
                                Log::info('MLS generate: OP exists in IC/DR — skipped PRA, linked source', [
                                    'file_number' => $fullFileNumber,
                                    'source_table' => $existingOp['_source_table'],
                                    'source_id' => $existingOp['id'],
                                    'prop_id' => $propId,
                                ]);
                            } else {
                                // Lineage: when a source instrument_capture row drove
                                // this commission, persist it on the PRA OP row so the
                                // OP\u2194ToT lookup never has to fall back to prop_id.
                                $sourceIcId = !empty($validated['source_instrument_capture_id'])
                                    ? (int) $validated['source_instrument_capture_id']
                                    : 0;
                                $praPayload = [
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
                                    'parent_prop_id' => $parentPropId,
                                    'tracking_id' => $trackingId,
                                ];
                                if ($sourceIcId > 0) {
                                    $praPayload['source_op_table'] = 'instrument_capture';
                                    $praPayload['source_op_id'] = $sourceIcId;
                                }
                                $praService->createRecord($praPayload, Auth::id());

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
                    } elseif (!$skipAutoPra && in_array($sourceValue, ['Subdivision', 'Merger', 'Extension', 'Separation'])) {
                        // Create PRA transaction records for Subdivision/Merger/Extension/Separation
                        try {
                            $plotTransactionType = $sourceValue === 'Extension' ? 'Plot Extension'
                                : ($sourceValue === 'Separation' ? 'Plot Separation' : $sourceValue);
                            $fileName = (string) ($validated['file_name'] ?? '');
                            $grantee = $fileName;
                            $grantor = ($sourceValue === 'Subdivision' || $sourceValue === 'Merger' || $sourceValue === 'Extension' || $sourceValue === 'Separation') ? ($motherOwner ?: $fileName) : $fileName;

                            $propId = app(\App\Services\PropertyIdAllocationService::class)->allocateOrRetrievePropId(
                                $fullFileNumber,
                                $fullFileNumber,
                                null,
                                null,
                                []
                            );

                            $praService = app(\App\Services\Pra\PraRecordService::class);

                            // Custom Comment building based on transaction type for PRA
                            $praComment = $plotTransactionType . " commissioning for " . $fullFileNumber;
                            if ($sourceValue === 'Merger') {
                                $mergerApp = \App\Models\PlotMergerApplication::find($validated['merger_app_id'] ?? 0);
                                if ($mergerApp) {
                                    $sourceFiles = $mergerApp->plotSizes()->whereIn('type', ['source', 'merger_source'])->pluck('source_file_no')->toArray();
                                    $praComment = 'Plot Merger: ' . implode(', ', $sourceFiles) . '; the new ' . $fullFileNumber;
                                }
                            } elseif ($sourceValue === 'Subdivision') {
                                $subdivisionApp = \App\Models\PlotSubdivisionApplication::find($validated['subdivision_app_id'] ?? 0);
                                if ($subdivisionApp) {
                                    $praComment = 'Plot Subdivision: ' . ($subdivisionApp->num_plots ?? '0') . ' Subdivided from ' . ($subdivisionApp->file_no ?? '');
                                }
                            } elseif ($sourceValue === 'Separation') {
                                $separationApp = \App\Models\PlotSeparationApplication::find($validated['separation_app_id'] ?? 0);
                                if ($separationApp) {
                                    $praComment = 'Plot Separation: ' . ($separationApp->num_plots ?? '0') . ' Separated from ' . ($separationApp->file_no ?? '');
                                }
                            } elseif ($sourceValue === 'Extension') {
                                $extFileNo = (string) ($validated['existing_file_no'] ?? '');
                                $praComment = 'Plot Extension: Plot ' . $extFileNo . ' extended by extra ' . $fullFileNumber;
                            }

                            $praService->createRecord([
                                'mlsFNo' => $fullFileNumber,
                                'fileno' => $fullFileNumber,
                                'temp_fileno' => null,
                                'transaction_type' => $plotTransactionType,
                                'instrument_type' => $plotTransactionType,
                                'transaction_date' => now()->toDateString(),
                                'reg_date' => '0/0/0',
                                'regNo' => '0/0/0',
                                'serialNo' => '0',
                                'pageNo' => '0',
                                'volumeNo' => '0',
                                'system_source' => 'MLS_PLOT_WORKFLOW',
                                'land_use' => $landUse,
                                'plot_no' => (string) ($validated['plot_no'] ?? ''),
                                'lgsaOrCity' => (string) ($validated['lga'] ?? ''),
                                'location' => (string) ($validated['location'] ?? ''),
                                'property_description' => (string) ($validated['location'] ?? ''),
                                'Grantor' => $grantor,
                                'Grantee' => $grantee,
                                'party_1' => $grantor,
                                'party_2' => $grantee,
                                'prop_id' => $propId,
                                'parent_prop_id' => $parentPropId,
                                'tracking_id' => $trackingId,
                                'related_file_number' => $relatedFileNumbers
                                    ? implode(', ', (array) (json_decode($relatedFileNumbers, true) ?: []))
                                    : null,
                                'comments' => $praComment,
                                'remarks' => "Commissioned via " . $sourceValue . " workflow",
                            ], Auth::id());

                            Log::info("PRA record created for {$sourceValue}", [
                                'file_number' => $fullFileNumber,
                                'prop_id' => $propId,
                                'transaction_type' => $plotTransactionType,
                            ]);
                        } catch (\Exception $praError) {
                            Log::error("PRA creation failed for {$sourceValue} (non-critical)", [
                                'error' => $praError->getMessage(),
                                'file_number' => $fullFileNumber,
                            ]);
                        }
                    } elseif (!$skipAutoPra && str_contains(strtoupper((string) $landUse), '-RC') && !empty($validated['related_fileno'])) {
                        // Recertification (-RC): mirror the original file into PRA so it
                        // surfaces in the Related File Numbers register. Classify the same
                        // way the register rebuild does — Old MLS "KN <digit>" files under an
                        // -RC parent are Land & Physical Planning recertifications; everything
                        // else (KNML/MLKN/KNGP legacy or modern files) is KANGIS.
                        try {
                            $relatedFileNo = trim((string) $validated['related_fileno']);
                            $isOldMlsKn = (bool) preg_match('/^KN[\s-]?\d/i', $relatedFileNo);
                            $recertType = $isOldMlsKn
                                ? 'Land & Physical Planning Recertification'
                                : 'KANGIS Recertification';

                            $propId = app(\App\Services\PropertyIdAllocationService::class)->allocateOrRetrievePropId(
                                $fullFileNumber,
                                $fullFileNumber,
                                null,
                                null,
                                []
                            );

                            $praService = app(\App\Services\Pra\PraRecordService::class);
                            $praService->createRecord([
                                'mlsFNo' => $fullFileNumber,
                                'fileno' => $fullFileNumber,
                                'temp_fileno' => null,
                                'related_file_number' => $relatedFileNo,
                                'transaction_type' => $recertType,
                                'instrument_type' => $recertType,
                                'transaction_date' => now()->toDateString(),
                                'reg_date' => now()->toDateString(),
                                'system_source' => 'MLS_RECERTIFICATION',
                                'land_use' => $landUse,
                                'plot_no' => (string) ($validated['plot_no'] ?? ''),
                                'lgsaOrCity' => (string) ($validated['lga'] ?? ''),
                                'location' => (string) ($validated['location'] ?? ''),
                                'property_description' => (string) ($validated['location'] ?? ''),
                                'Grantor' => (string) ($validated['related_file_title'] ?? ''),
                                'Grantee' => (string) ($validated['file_name'] ?? ''),
                                'party_1' => (string) ($validated['related_file_title'] ?? ''),
                                'party_2' => (string) ($validated['file_name'] ?? ''),
                                'prop_id' => $propId,
                                'parent_prop_id' => $parentPropId,
                                'tracking_id' => $trackingId,
                                'comments' => $recertType . ' of ' . $relatedFileNo . ' into ' . $fullFileNumber,
                                'remarks' => 'Commissioned via Recertification workflow',
                            ], Auth::id());

                            Log::info('MLS generate recertification PRA record created', [
                                'file_number' => $fullFileNumber,
                                'related_fileno' => $relatedFileNo,
                                'transaction_type' => $recertType,
                                'prop_id' => $propId,
                            ]);
                        } catch (\Exception $praError) {
                            Log::error('MLS generate recertification PRA creation failed (non-critical)', [
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

                // Auto-index the file in file_indexings table
                $newFileIndexing = null;
                if ($fileOption !== 'temporary') {
                    try {
                        $fileIndexingService = app(\App\Services\FileIndexingService::class);
                        $relatedFileNo = $validated['related_fileno'] ?? null;
                        $relatedFileTitle = $validated['related_file_title'] ?? null;

                        // Typed related files from the form. Their numbers join the JSON
                        // column (so lineage/PRA readers see them), while the type itself
                        // is written to related_file_number below.
                        $typedRelatedFiles = $this->parseRelatedFiles($validated['related_files'] ?? null);
                        if (!empty($typedRelatedFiles)) {
                            $existingRelated = $relatedFileNumbers
                                ? (json_decode($relatedFileNumbers, true) ?: [])
                                : ($relatedFileNo ? [$relatedFileNo] : []);
                            $mergedRelated = array_values(array_unique(array_merge(
                                $existingRelated,
                                array_column($typedRelatedFiles, 'file_no')
                            )));
                            $relatedFileNumbers = json_encode($mergedRelated);
                        }

                        $fileIndexing = $fileIndexingService->createFromFileNumberData([
                            'tracking_id' => $trackingId,
                            'file_number' => $fullFileNumber,
                            'file_title' => $validated['file_name'] ?? null,
                            'gender' => $validated['gender'] ?? null,
                            'land_use' => $landUse,
                            'plot_number' => $validated['plot_no'] ?? null,
                            'tp_no' => $validated['tp_no'] ?? null,
                            'location' => $validated['location'] ?? null,
                            'lga' => $validated['lga'] ?? null,
                            'created_by' => $commissionedBy,
                            'original_holder' => $motherOwner ?? ($validated['file_name'] ?? null),
                            'parent_prop_id' => $parentPropId,
                            'related_fileno' => $relatedFileNumbers ?? ($relatedFileNo ? json_encode([$relatedFileNo]) : null),
                        ]);
                        $newFileIndexing = $fileIndexing;

                        // One related_file_number row per typed link (source_id is NOT NULL,
                        // so this has to wait until the indexing row exists).
                        $this->storeRelatedFileLinks(
                            $typedRelatedFiles,
                            $fullFileNumber,
                            $validated['file_name'] ?? null,
                            $fileIndexing->prop_id ?? null,
                            $fileIndexing->id ?? null
                        );

                        // Create file_indexing_links record to link lineage (Subdivision/Merger/Extension/Recertification)
                        $now = now();
                        $lineageFileNumbers = [];
                        if (!empty($relatedFileNumbers)) {
                            $lineageFileNumbers = json_decode($relatedFileNumbers, true) ?: [];
                        } elseif (!empty($relatedFileNo)) {
                            $lineageFileNumbers = [$relatedFileNo];
                        }

                        if (!empty($lineageFileNumbers) && $fileIndexing) {
                            $linksToCreate = [];
                            foreach ($lineageFileNumbers as $oldFileNo) {
                                if (empty($oldFileNo))
                                    continue;

                                // Direction 1: NEW FILE -> OLD FILE (Lineage Backwards)
                                // This ensures that when viewing the NEW file, you see where it came from.
                                $linksToCreate[] = [
                                    'file_indexing_id' => $fileIndexing->id,
                                    'file_number' => $oldFileNo,
                                    'file_title' => $relatedFileTitle ?? 'Parent/Source File',
                                    'land_use_type' => $landUse,
                                    'plot_number' => $validated['plot_no'] ?? null,
                                    'tp_no' => $validated['tp_no'] ?? null,
                                    'location' => $validated['location'] ?? null,
                                    'lga' => $validated['lga'] ?? null,
                                    'tracking_id' => $trackingId,
                                    'indexing_type' => 'lineage_link',
                                    'workflow_status' => 'indexed',
                                    'created_by' => $commissionedBy,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];

                                // Direction 2: OLD FILE -> NEW FILE (Lineage Forwards)
                                // This ensures that when viewing the OLD file, you see its subdivisions/mergers.
                                try {
                                    $relatedIndexing = DB::connection('sqlsrv')
                                        ->table('file_indexings')
                                        ->where('file_number', $oldFileNo)
                                        ->first(['id', 'file_title']);

                                    if ($relatedIndexing) {
                                        $linksToCreate[] = [
                                            'file_indexing_id' => $relatedIndexing->id,
                                            'file_number' => $fullFileNumber,
                                            'file_title' => 'Subdivision/Merger/Link',
                                            'land_use_type' => $landUse,
                                            'plot_number' => $validated['plot_no'] ?? null,
                                            'tp_no' => $validated['tp_no'] ?? null,
                                            'location' => $validated['location'] ?? null,
                                            'lga' => $validated['lga'] ?? null,
                                            'tracking_id' => $trackingId,
                                            'indexing_type' => 'lineage_link',
                                            'workflow_status' => 'indexed',
                                            'created_by' => $commissionedBy,
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ];
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Failed to create forward lineage link', ['error' => $e->getMessage()]);
                                }
                            }

                            if (!empty($linksToCreate)) {
                                DB::connection('sqlsrv')->table('file_indexing_links')->insert($linksToCreate);
                                Log::info('Lineage file indexing links created', [
                                    'new_file' => $fullFileNumber,
                                    'link_count' => count($linksToCreate),
                                ]);
                            }
                        }
                    } catch (\Exception $indexingError) {
                        Log::error('Auto-indexing failed (non-critical)', ['error' => $indexingError->getMessage()]);
                    }
                }

                // Re-grant: the new file is a re-grant of the selected Related File. Flag both
                // files' title status and record the Re-grant application + linkage. The
                // Related File is optional — without one, only the new file is flagged.
                if ($fileOption === 'regrant') {
                    app(\App\Services\TitleStatusService::class)->recordRegrant(
                        $fullFileNumber,
                        (string) ($validated['related_fileno'] ?? ''),
                        [
                            'url'               => 'land',
                            'file_indexing_id'  => $newFileIndexing->id ?? null,
                            'prop_id'           => $newFileIndexing->prop_id ?? null,
                            'file_title'        => $validated['file_name'] ?? null,
                            'applicant_name'    => $validated['file_name'] ?? null,
                            'plot_no'           => $validated['plot_no'] ?? null,
                            'district'          => $validated['district'] ?? null,
                            'lga'               => $validated['lga'] ?? null,
                            'location'          => $validated['location'] ?? null,
                            'land_use'          => $landUse,
                        ]
                    );
                }

                // Resettlement: mirrors Re-grant. The new file is a resettlement of the selected
                // Related File. Flag both files' title status and record the Resettlement
                // application + linkage. The Related File is optional — without one, only the new
                // file is flagged.
                if ($fileOption === 'resettlement') {
                    app(\App\Services\TitleStatusService::class)->recordResettlement(
                        $fullFileNumber,
                        (string) ($validated['related_fileno'] ?? ''),
                        [
                            'url'               => 'land',
                            'file_indexing_id'  => $newFileIndexing->id ?? null,
                            'prop_id'           => $newFileIndexing->prop_id ?? null,
                            'file_title'        => $validated['file_name'] ?? null,
                            'applicant_name'    => $validated['file_name'] ?? null,
                            'plot_no'           => $validated['plot_no'] ?? null,
                            'district'          => $validated['district'] ?? null,
                            'lga'               => $validated['lga'] ?? null,
                            'location'          => $validated['location'] ?? null,
                            'land_use'          => $landUse,
                        ]
                    );
                }

                $workflowService = app(PlotWorkflowService::class);
                $propIdService = app(PropertyIdAllocationService::class);
                $parcelNotifier = app(ParcelUpdateNotificationService::class);
                $decommissionSummary = [
                    'archived' => [],
                    'history_updated' => 0
                ];

                // Handle Merger Application Linkage
                if (!empty($validated['merger_app_id'])) {
                    $mergerApp = PlotMergerApplication::find($validated['merger_app_id']);
                    if ($mergerApp) {
                        $sourceFiles = $mergerApp->plotSizes()
                            ->whereIn('type', ['source', 'merger_source'])
                            ->pluck('source_file_no')
                            ->toArray();
                        if (!empty($sourceFiles)) {
                            // 1. Get old PropIDs from indexings BEFORE they are deleted
                            $oldPropIds = DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $sourceFiles)
                                ->whereNotNull('prop_id')
                                ->pluck('prop_id')
                                ->toArray();

                            // 2. Decommission
                            Log::info('Plot workflow decommissioning source files', [
                                'new_file' => $fullFileNumber,
                                'source_files' => $sourceFiles
                            ]);
                            $res = $workflowService->decommissionFiles($sourceFiles, "Plot Merger to $fullFileNumber", $commissionedBy, $fullFileNumber);
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);

                            // PRA comment on the new merged file's row
                            $sourceFilesList = implode(', ', $sourceFiles);
                            $mergerPlotNo = $validated['plot_no'] ?? '';
                            $mergerComment = "{$sourceFilesList}; the new {$fullFileNumber} {$mergerPlotNo}";
                            DB::connection('sqlsrv')->table('pra')
                                ->where('mlsFNo', $fullFileNumber)
                                ->update(['comments' => $mergerComment]);
                            // Also stamp the source files' pra rows (fileno column)
                            DB::connection('sqlsrv')->table('pra')
                                ->whereIn('fileno', $sourceFiles)
                                ->update(['comments' => $mergerComment]);

                            Log::info('Plot workflow decommissioning completed', [
                                'archived' => $res['archived'],
                                'deleted' => $res['deleted'],
                                'errors' => $res['errors']
                            ]);

                            // 3. Historical PropID Update for Merger
                            $newPropId = $propIdService->allocateOrRetrievePropId($fullFileNumber);
                            if (!empty($oldPropIds)) {
                                $decommissionSummary['history_updated'] = $workflowService->updateHistoricalPropId($oldPropIds, $newPropId);

                                // Set parent_prop_id and related_fileno on new record
                                DB::connection('sqlsrv')->table('file_indexings')
                                    ->where('file_number', $fullFileNumber)
                                    ->update([
                                        'parent_prop_id' => implode(',', array_unique($oldPropIds)),
                                        'related_fileno' => json_encode(array_values($sourceFiles))
                                    ]);

                                DB::connection('sqlsrv')->table('fileNumber')
                                    ->where('mlsfNo', $fullFileNumber)
                                    ->update([
                                        'parent_prop_id' => implode(',', array_unique($oldPropIds)),
                                        'related_fileno' => json_encode(array_values($sourceFiles))
                                    ]);
                            }
                        }

                        $mergerApp->update([
                            'status' => PlotMergerApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned to File No: {$fullFileNumber} on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);

                        $parcelNotifier->notifyCommissioned(
                            'merger',
                            $mergerApp->id,
                            $mergerApp->file_no,
                            $fullFileNumber,
                            $commissionedBy
                        );
                    }
                    Log::info('Merger application marked as commissioned', ['app_id' => $validated['merger_app_id'], 'file_no' => $fullFileNumber]);
                }

                // Handle Subdivision Application Linkage
                if (!empty($validated['subdivision_app_id'])) {
                    $subdivisionApp = PlotSubdivisionApplication::find($validated['subdivision_app_id']);
                    if ($subdivisionApp) {
                        $motherFile = $subdivisionApp->file_no;
                        $motherIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->first();

                        if ($motherIndexing && $motherIndexing->prop_id) {
                            // Set parent_prop_id and related_fileno on new record
                            DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $fullFileNumber)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);

                            DB::connection('sqlsrv')->table('fileNumber')
                                ->where('mlsfNo', $fullFileNumber)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);
                        }

                        // Decommission mother if not already done
                        $motherRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $motherFile)->first();
                        if ($motherRecord && !$motherRecord->is_decommissioned) {
                            $res = $workflowService->decommissionFiles([$motherFile], "Plot Subdivision into fragments (e.g. $fullFileNumber)", $commissionedBy, $fullFileNumber);
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);
                        }

                        // PRA comment on the new fragment and the mother file
                        $subdivisionPlotNo = $validated['plot_no'] ?? '';
                        $subdivisionComment = "{$subdivisionApp->num_plots} Subdivided from {$motherFile},{$subdivisionPlotNo}";
                        DB::connection('sqlsrv')->table('pra')
                            ->where('mlsFNo', $fullFileNumber)
                            ->update(['comments' => $subdivisionComment]);
                        DB::connection('sqlsrv')->table('pra')
                            ->where('fileno', $motherFile)
                            ->update(['comments' => $subdivisionComment]);

                        $subdivisionApp->update([
                            'status' => PlotSubdivisionApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned fragment: {$fullFileNumber} on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);

                        $parcelNotifier->notifyCommissioned(
                            'subdivision',
                            $subdivisionApp->id,
                            $subdivisionApp->file_no,
                            $fullFileNumber,
                            $commissionedBy
                        );
                    }
                    Log::info('Subdivision application marked as commissioned', ['app_id' => $validated['subdivision_app_id'], 'file_no' => $fullFileNumber]);
                }

                // Handle Separation Application Linkage
                if (!empty($validated['separation_app_id'])) {
                    $separationApp = PlotSeparationApplication::find($validated['separation_app_id']);
                    if ($separationApp) {
                        $motherFile    = $separationApp->file_no;
                        $motherIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->first();

                        if ($motherIndexing && $motherIndexing->prop_id) {
                            DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $fullFileNumber)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);

                            DB::connection('sqlsrv')->table('fileNumber')
                                ->where('mlsfNo', $fullFileNumber)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);
                        }

                        // Decommission mother if not already done
                        $motherRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $motherFile)->first();
                        if ($motherRecord && !$motherRecord->is_decommissioned) {
                            $res = $workflowService->decommissionFiles([$motherFile], "Plot Separation into fragments (e.g. $fullFileNumber)", $commissionedBy, $fullFileNumber);
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);
                        }

                        $separationPlotNo  = $validated['plot_no'] ?? '';
                        $separationComment = "{$separationApp->num_plots} Separated from {$motherFile},{$separationPlotNo}";
                        DB::connection('sqlsrv')->table('pra')
                            ->where('mlsFNo', $fullFileNumber)
                            ->update(['comments' => $separationComment]);
                        DB::connection('sqlsrv')->table('pra')
                            ->where('fileno', $motherFile)
                            ->update(['comments' => $separationComment]);

                        $separationApp->update([
                            'status'     => PlotSeparationApplication::STATUS_COMMISSIONED,
                            'remarks'    => "Commissioned fragment: {$fullFileNumber} on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);

                        $parcelNotifier->notifyCommissioned(
                            'separation',
                            $separationApp->id,
                            $separationApp->file_no,
                            $fullFileNumber,
                            $commissionedBy
                        );
                    }
                    Log::info('Separation application marked as commissioned', ['app_id' => $validated['separation_app_id'], 'file_no' => $fullFileNumber]);
                }

                // Handle Normal Commissioning (Extension flow)
                if (($validated['file_option'] ?? '') === 'extension' && !empty($validated['existing_file_no'])) {
                    $oldFile = $validated['existing_file_no'];

                    $oldIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $oldFile)->first();

                    if ($oldIndexing && $oldIndexing->prop_id) {
                        $newPropId = $propIdService->allocateOrRetrievePropId($fullFileNumber);
                        $decommissionSummary['history_updated'] = $workflowService->updateHistoricalPropId([$oldIndexing->prop_id], $newPropId);

                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $fullFileNumber)
                            ->update([
                                'parent_prop_id' => $oldIndexing->prop_id,
                                'related_fileno' => json_encode([$oldFile])
                            ]);

                        DB::connection('sqlsrv')->table('fileNumber')
                            ->where('mlsfNo', $fullFileNumber)
                            ->update([
                                'parent_prop_id' => $oldIndexing->prop_id,
                                'related_fileno' => json_encode([$oldFile])
                            ]);
                    }

                    $res = $workflowService->decommissionFiles([$oldFile], "Plot Extension to $fullFileNumber", $commissionedBy, $fullFileNumber);
                    $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);

                    // PRA comment on the new extension file and the old file
                    $extensionComment = "Plot {$oldFile} extended by extra {$fullFileNumber}";
                    DB::connection('sqlsrv')->table('pra')
                        ->where('mlsFNo', $fullFileNumber)
                        ->update(['comments' => $extensionComment]);
                    DB::connection('sqlsrv')->table('pra')
                        ->where('fileno', $oldFile)
                        ->update(['comments' => $extensionComment]);

                    $parcelNotifier->notifyCommissioned(
                        'extension',
                        null,
                        $oldFile,
                        $fullFileNumber,
                        $commissionedBy
                    );
                }

                // Handle Change of Purpose Application Linkage
                if (!empty($validated['change_of_purpose_app_id'])) {
                    $copApp = ChangeOfPurposeApplication::find($validated['change_of_purpose_app_id']);
                    if ($copApp) {
                        // Supersede the original file and record the lineage — same rules as the
                        // merger/subdivision branches. Without this, commissioning a CoP through
                        // the Conversion flow left the original active and the new file unlinked.
                        $copOriginalFileNo = trim((string) $copApp->file_no);
                        if ($copOriginalFileNo !== '' && strcasecmp($copOriginalFileNo, $fullFileNumber) !== 0) {
                            $copOriginalIndexing = DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $copOriginalFileNo)
                                ->first();

                            // 1. Link the new file back to the original (new -> old).
                            $copLineageUpdate = [
                                'related_fileno' => json_encode([$copOriginalFileNo]),
                                'updated_at' => now(),
                            ];
                            // Carry property linkage forward: the original's prop_id, or — when the
                            // original is itself a subdivision fragment — its parent_prop_id.
                            $copParentProp = $copOriginalIndexing->prop_id
                                ?? ($copOriginalIndexing->parent_prop_id ?? null);
                            if (!empty($copParentProp)) {
                                $copLineageUpdate['parent_prop_id'] = (string) $copParentProp;
                            }

                            DB::connection('sqlsrv')->table('file_indexings')
                                ->where('file_number', $fullFileNumber)
                                ->update($copLineageUpdate);
                            DB::connection('sqlsrv')->table('fileNumber')
                                ->where('mlsfNo', $fullFileNumber)
                                ->update($copLineageUpdate);

                            // 2. Decommission the original (old -> new via successor_file_no).
                            $res = $workflowService->decommissionFiles(
                                [$copOriginalFileNo],
                                "Change of Purpose to $fullFileNumber",
                                $commissionedBy,
                                $fullFileNumber
                            );
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);
                        }

                        $copApp->update([
                            'status' => ChangeOfPurposeApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned to File No: {$fullFileNumber} on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);

                        $parcelNotifier->notifyCommissioned(
                            'change_of_purpose',
                            $copApp->id,
                            $copApp->file_no,
                            $fullFileNumber,
                            $commissionedBy
                        );
                    }
                    Log::info('Change of Purpose application marked as commissioned', ['app_id' => $validated['change_of_purpose_app_id'], 'file_no' => $fullFileNumber]);
                }

                DB::connection('sqlsrv')->commit();

                $edmsFolder = $this->ensureEdmsScanFolder($fullFileNumber);

                Log::info('MLS File Number Generated', [
                    'file_number' => $fullFileNumber,
                    'land_use' => $landUse,
                    'serial' => $serial,
                    'user' => Auth::user()->name ?? 'Unknown'
                ]);

                $skipNotice = null;
                if (!empty($skippedSerials)) {
                    $skippedFileNos = array_column($skippedSerials, 'file_number');
                    $skipNotice = count($skippedSerials) . ' file number(s) were already in use and skipped ('
                        . implode(', ', $skippedFileNos) . '); ' . $fullFileNumber . ' was assigned instead.';
                }

                return response()->json([
                    'success' => true,
                    'message' => 'File number generated successfully',
                    'mirror_created' => $shouldMirror,
                    'decommission_summary' => $decommissionSummary,
                    'skipped_serials' => $skippedSerials,
                    'notice' => $skipNotice,
                    // Where scans for this file go — shown on the commissioning summary.
                    'edms_folder' => $edmsFolder,
                    // Which tables the commissioning wrote to, for the same card.
                    'storage_summary' => $this->buildStorageSummary($fullFileNumber),
                    'data' => [
                        'file_number' => $fullFileNumber,
                        'file_name' => $validated['file_name'] ?? ($mlsRecord->file_name ?? null),
                        'land_use' => $landUse,
                        'year' => $year,
                        'serial' => $serial,
                        'tracking_id' => $trackingId,
                        'application_type' => !empty($validated['merger_app_id']) ? 'merger' :
                            (!empty($validated['subdivision_app_id']) ? 'subdivision' :
                                (!empty($validated['change_of_purpose_app_id']) ? 'change_of_purpose' :
                                    (in_array($validated['file_option'] ?? '', ['subdivision', 'merger', 'extension'])
                                        ? $validated['file_option']
                                        : ($validated['application_type'] ?? 'new')))),
                        'id' => $mlsRecord->id,
                        'source_pra_id' => $validated['source_pra_id'] ?? null,
                        'mother_file_no' => $motherFileNo ?? $validated['existing_file_no'] ?? null,
                        'source_files' => $sourceFiles ?? [],
                        'prop_id' => $propId ?? $parentPropId ?? null,
                        // Only set for subdivision/merger/extension/separation, where the new
                        // parcel really does descend from an existing one. Null for a plain
                        // new allocation, which gets a fresh prop_id of its own -- the UI uses
                        // this to avoid claiming lineage that doesn't exist.
                        'parent_prop_id' => $parentPropId ?? null,
                    ]
                ]);

            } catch (\Exception $e) {
                DB::connection('sqlsrv')->rollBack();
                $this->logPlotsWorkflow('error', 'Generate transaction failed', [
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
            $this->logPlotsWorkflow('error', 'Generate endpoint failed', [
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
     * Allocate the next FREE serial for single-file generation, skipping any serial whose
     * file number is already taken in mls_file_no, fileNumber, or file_indexings
     * (UX_file_indexings_file_number). Consumes serials via MlsSerialControl as it advances,
     * so taken serials are burned rather than retried later. Returns the free serial, its
     * file number, and the list of skipped serials so the UI can point out what was replaced.
     */
    /**
     * The relationship kinds an officer can tag a related file with. Anything outside
     * this list falls back to 'Other' rather than being written through verbatim.
     */
    private const RELATED_FILE_TYPES = [
        'Re-grant',
        'Resettlement',
        'Subdivision',
        'Merger',
        'Change of Purpose',
        'Mother File',
        'Temporary File',
        'Dummy File',
        'Other',
    ];

    /**
     * Decode the related_files JSON posted by the generate form into a clean list of
     * ['file_no' => ..., 'title' => ..., 'type' => ..., 'indexing_id' => ...].
     *
     * Rows without a file number are dropped, and duplicates on file number collapse to
     * the first occurrence so a double-click on "Add Another Related File" can't write
     * the same link twice.
     */
    private function parseRelatedFiles(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fileNo = trim((string) ($row['file_no'] ?? ''));
            if ($fileNo === '' || isset($rows[$fileNo])) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            if ($type !== '' && !in_array($type, self::RELATED_FILE_TYPES, true)) {
                $type = 'Other';
            }

            // 'Other' is a prompt, not an answer: when the officer specified what the
            // relationship actually is, that text becomes the stored type. transaction_type
            // is nvarchar(60), so the free text is capped to fit.
            if ($type === 'Other') {
                $specified = trim((string) ($row['type_other'] ?? ''));
                if ($specified !== '') {
                    $type = mb_substr($specified, 0, 60);
                }
            }

            $rows[$fileNo] = [
                'file_no' => $fileNo,
                'title' => trim((string) ($row['title'] ?? '')),
                'type' => $type,
                'indexing_id' => $row['indexing_id'] ?? null,
            ];
        }

        return array_values($rows);
    }

    /**
     * Persist typed related-file links for a newly commissioned file into
     * related_file_number (one row per link, carrying the type in transaction_type).
     *
     * The plain file numbers are stored separately as a JSON array on
     * file_indexings.related_fileno by the caller -- both stores are kept, since existing
     * lineage/PRA readers depend on the JSON column while the type only fits here.
     */
    /**
     * Start real tracking for a freshly commissioned file, using the "where does
     * this file go next" department / unit-office picked on the Generation Summary.
     *
     * A tracking failure must never fail the commissioning itself — the file
     * number is already issued, and a file without a tracker still shows its
     * default commissioning line (DIIT).
     *
     * @param object $mlsRecord The mls_file_no row just created.
     */
    private function startCommissioningTracking(Request $request, $mlsRecord): void
    {
        try {
            $service = app(\App\Services\FileCommissioningTrackingService::class);
            $destination = $service->destinationFromRequest($request);

            if (empty($destination)) {
                return;
            }

            $service->startTracking($service->hydrate((object) [
                'full_file_number'  => $mlsRecord->full_file_number,
                'file_name'         => $mlsRecord->file_name,
                'commissioning_date' => $mlsRecord->commissioning_date,
                'commissioning_time' => $mlsRecord->commissioning_time,
                'created_by'        => $mlsRecord->created_by,
                'created_at'        => $mlsRecord->created_at,
                'tracking_id'       => $mlsRecord->tracking_id,
                'source'            => $mlsRecord->source,
            ]), $destination);
        } catch (\Throwable $e) {
            \Log::warning('Failed to open commissioning tracking for file', [
                'file_number' => $mlsRecord->full_file_number ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Batch counterpart of startCommissioningTracking(). The bulk insert returns no
     * ids, so the register rows are re-read by file number — the same way the batch
     * related-file linking above recovers them.
     *
     * @param array<int,string> $fileNumbers
     */
    private function startBatchCommissioningTracking(Request $request, array $fileNumbers): void
    {
        if (empty($fileNumbers)) {
            return;
        }

        try {
            $service = app(\App\Services\FileCommissioningTrackingService::class);
            $destination = $service->destinationFromRequest($request);

            if (empty($destination)) {
                return;
            }

            // SQL Server caps a statement at 2,100 parameters.
            foreach (array_chunk($fileNumbers, 1000) as $chunk) {
                $rows = $service->baseQuery()
                    ->whereIn('full_file_number', $chunk)
                    ->get(['full_file_number', 'file_name', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source']);

                foreach ($rows as $row) {
                    $service->startTracking($service->hydrate($row), $destination);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to open commissioning tracking for batch', [
                'count' => count($fileNumbers),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function storeRelatedFileLinks(array $relatedFiles, string $fileNumber, ?string $fileTitle, ?string $propId, $sourceId = null): void
    {
        // source_id is NOT NULL, so without an indexing row there is nothing to anchor
        // the link to; skip rather than write an orphan.
        if (empty($relatedFiles) || empty($sourceId)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($relatedFiles as $related) {
            $rows[] = [
                'related_fileno' => $related['file_no'],
                'prop_id' => $propId,
                'source_table' => 'file_indexings',
                'source_id' => $sourceId,
                'file_number' => $fileNumber,
                'file_title' => $fileTitle,
                'location' => null,
                'comment' => trim(($related['type'] ?: 'Related file') . ' of ' . $related['file_no'] . ' for ' . $fileNumber),
                'transaction_type' => $related['type'] ?: 'Other',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            DB::connection('sqlsrv')->table('related_file_number')->insert($rows);
        } catch (\Exception $e) {
            // A failed link must not roll back a successfully commissioned file number.
            Log::warning('Failed to store related file links', [
                'file_number' => $fileNumber,
                'related' => array_column($relatedFiles, 'file_no'),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Which of $candidates are already present in file_indexings.
     *
     * Legacy imported rows carry a trailing CRLF in file_number (e.g. "IND-2026-213\r\n"),
     * which an exact `where` never matches -- the serial then looks free, gets handed out,
     * and no skip is reported even though the number is in use. Comparing on the
     * whitespace-stripped value catches both the clean and the dirty rows.
     *
     * Returns a set keyed by the *candidate* string (not the stored value) so callers can
     * look up by the number they asked about.
     */
    private function takenInFileIndexings(array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter($candidates, 'strlen')));
        if (empty($candidates)) {
            return [];
        }

        $normalized = "LTRIM(RTRIM(REPLACE(REPLACE(file_number, CHAR(13), ''), CHAR(10), '')))";
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));

        $found = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw("{$normalized} IN ({$placeholders})", $candidates)
            ->selectRaw("{$normalized} AS normalized_file_number")
            ->pluck('normalized_file_number')
            ->all();

        return array_flip($found);
    }

    private function allocateNextFreeSerial(string $landUse, int $year, string $suffix = '', int $maxTries = 200): array
    {
        $skipped = [];
        $tries = 0;
        $serial = null;
        $candidate = null;

        do {
            $serial = \App\Models\MlsSerialControl::getNextSerial($landUse, $year);
            $candidate = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $serial) . $suffix;

            $taken = \App\Models\MlsFileNo::where('full_file_number', $candidate)->exists()
                || DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $candidate)->exists()
                || count($this->takenInFileIndexings([$candidate])) > 0;

            if ($taken) {
                $skipped[] = ['serial' => $serial, 'file_number' => $candidate];
            }
            $tries++;
        } while ($taken && $tries < $maxTries);

        if ($taken) {
            throw new \RuntimeException("Could not find a free file number for {$landUse}-{$year} after {$maxTries} attempts. Too many consecutive serials are already in use.");
        }

        return ['serial' => $serial, 'file_number' => $candidate, 'skipped' => $skipped];
    }

    private function findNextAvailableFileNumber(string $landUse, int $year, int $currentSerial, string $suffix = ''): string
    {
        $serial = $currentSerial;
        $limit = 100;
        do {
            $serial++;
            $candidate = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $serial) . $suffix;
            $exists = \App\Models\MlsFileNo::where('full_file_number', $candidate)->exists() ||
                      DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $candidate)->exists();
        } while ($exists && $serial <= $currentSerial + $limit);

        return $candidate;
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
                'location_entries.*.district' => 'nullable|string|max:100',
                'location_entries.*.tracking_id' => 'nullable|string|max:100',
                'location_entries.*.file_name' => 'nullable|string|max:500',
                'location_entries.*.phone_no' => 'nullable|string|max:100',
                'location_entries.*.address' => 'nullable|string|max:500',
                'commissioned_by' => 'nullable|string|max:255',
                'commission_date' => 'nullable|date',
                'commission_time' => 'nullable|string',
                'customer_type' => 'required|string|in:Individual,Corporate,Multiple',
                'purpose_id' => 'nullable|integer',
                'allocated_by_filter' => 'nullable|string|max:100',
                'default_allocation_type' => 'nullable|string|max:50',
                'source_instrument_capture_id' => 'nullable|integer',
                'sub_source' => 'nullable|string|max:100',
                // See resolveSystemSubType().
                'oss_commission' => 'nullable|boolean',
                'subdivision_app_id' => 'nullable|integer',
                'separation_app_id' => 'nullable|integer',
                'merger_app_id' => 'nullable|integer',
                // JSON array of {file_no, title, type, indexing_id}; see parseRelatedFiles().
                'related_files' => 'nullable|string',
            ]);

            $landUse = $validated['land_use'];
            $year = $validated['year'];
            $startSerial = $validated['serial_start'];
            $batchQuantity = $validated['batch_quantity'];

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $generatedFiles = [];
                $mlsRecords = [];

                // Allocate free serials, skipping any already taken across mls_file_no,
                // file_indexings (UX_file_indexings_file_number) and fileNumber. Instead of
                // failing the whole batch on a conflict, advance past taken serials and still
                // produce the requested quantity, recording what was skipped.
                $allocatedSerials = [];
                $skippedSerials = [];
                $probeStart = $startSerial;
                $maxSerial = $startSerial + max($batchQuantity * 20, 1000); // safety cap

                while (count($allocatedSerials) < $batchQuantity && $probeStart <= $maxSerial) {
                    // Probe a window wide enough to likely cover the remaining need plus collisions
                    $remaining = $batchQuantity - count($allocatedSerials);
                    $probeEnd = min($probeStart + ($remaining * 3) + 10, $maxSerial);

                    // Build candidate serials + file numbers for this window
                    $windowSerials = range($probeStart, $probeEnd);
                    $candidateNumbers = [];
                    foreach ($windowSerials as $s) {
                        $candidateNumbers[$s] = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $s);
                    }

                    // Pre-fetch taken values in index-friendly bulk queries
                    $takenSerials = \App\Models\MlsFileNo::where('land_use', $landUse)
                        ->where('year', $year)
                        ->whereBetween('serial_number', [$probeStart, $probeEnd])
                        ->pluck('serial_number')
                        ->all();
                    $takenSerials = array_flip($takenSerials);

                    // Also probe mls_file_no by full_file_number directly. The UNIQUE
                    // constraint (UQ_mls_file_no) is enforced on full_file_number, so a
                    // serial-only check can miss rows whose land_use/year/serial_number
                    // columns don't decompose to the same string (legacy/manual inserts,
                    // casing differences, NULL serials). Matching the exact key the DB
                    // constraint enforces prevents the duplicate-key failure.
                    $takenMlsFull = \App\Models\MlsFileNo::whereIn('full_file_number', array_values($candidateNumbers))
                        ->pluck('full_file_number')
                        ->all();
                    $takenMlsFull = array_flip($takenMlsFull);

                    // Whitespace-insensitive: legacy rows store a trailing CRLF, which
                    // whereIn() misses. See takenInFileIndexings().
                    $takenIndexing = $this->takenInFileIndexings(array_values($candidateNumbers));

                    $takenFileNumber = DB::connection('sqlsrv')->table('fileNumber')
                        ->whereIn('mlsfNo', array_values($candidateNumbers))
                        ->pluck('mlsfNo')
                        ->all();
                    $takenFileNumber = array_flip($takenFileNumber);

                    foreach ($windowSerials as $s) {
                        if (count($allocatedSerials) >= $batchQuantity) {
                            break;
                        }
                        $fileNo = $candidateNumbers[$s];
                        $isTaken = isset($takenSerials[$s]) || isset($takenMlsFull[$fileNo]) || isset($takenIndexing[$fileNo]) || isset($takenFileNumber[$fileNo]);
                        if ($isTaken) {
                            $skippedSerials[] = ['serial' => $s, 'file_number' => $fileNo];
                        } else {
                            $allocatedSerials[] = $s;
                        }
                    }

                    $probeStart = $probeEnd + 1;
                }

                if (count($allocatedSerials) < $batchQuantity) {
                    DB::connection('sqlsrv')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not find ' . $batchQuantity . ' free serial numbers near ' . $startSerial . '. Too many consecutive serials are already in use — please choose a different starting serial.'
                    ], 409);
                }

                if (!empty($skippedSerials)) {
                    Log::channel('mls_batch')->info('Batch generation skipped taken serials', [
                        'land_use' => $landUse,
                        'year' => $year,
                        'requested_start' => $startSerial,
                        'skipped' => $skippedSerials,
                        'allocated' => $allocatedSerials,
                    ]);
                }

                // Generate a single batch number for all files in this batch (format: BATCH-YYYYMMDD-TIMESTAMP)
                $batchNo = 'BATCH-' . date('Ymd') . '-' . time();

                Log::channel('mls_batch')->info('Batch MLS generation started', [
                    'batch_no' => $batchNo,
                    'batch_size' => $batchQuantity,
                    'land_use' => $validated['land_use'] ?? null,
                    'file_option' => $validated['file_option'] ?? null,
                    'year' => $validated['year'] ?? null,
                    'serial_start' => $validated['serial_start'] ?? null,
                    'user' => Auth::user()->name ?? 'Unknown',
                ]);

                // Prepare arrays for bulk insertion
                $mlsData = [];
                $fileNumberData = [];
                $indexingData = [];
                $now = now();

                $commissionedBy = ($validated['commissioned_by'] ?? null) ?: ((Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System');

                // Resolve the source value based on allocation configuration
                $appType = $validated['application_type'] ?? 'new';
                if (!empty($validated['merger_app_id']))
                    $appType = 'merger';
                if (!empty($validated['subdivision_app_id']))
                    $appType = 'subdivision';
                if (!empty($validated['separation_app_id']))
                    $appType = 'separation';

                $sourceValue = $this->resolveSourceValue(
                    $appType,
                    $validated['allocated_by_filter'] ?? '',
                    $validated['default_allocation_type'] ?? null,
                    $validated['file_option'] ?? 'normal'
                );

                // Resolved once — every row in the batch shares the same origin.
                $batchSystemSubType = $this->resolveSystemSubType(
                    (bool) ($validated['oss_commission'] ?? false)
                );

                // 1. Generate all full file numbers first (from the allocated free serials)
                $allFileNumbers = [];
                for ($i = 0; $i < $batchQuantity; $i++) {
                    $allFileNumbers[] = \App\Models\MlsFileNo::generateFileNumber($landUse, $year, $allocatedSerials[$i]);
                }



                // 2. Pre-fetch grouping data efficiently:
                //    - First attempt fast exact matches using WHERE IN (index-friendly).
                //    - For any remaining items, fall back to the normalized REPLACE lookup
                //      but only for the small remainder to avoid scanning the whole table.
                $groupingCache = [];
                $groupingLookupStart = microtime(true);
                try {
                    $groupingService = app(\App\Services\GroupingFileNumberService::class);
                    $tableName = $groupingService->getTableName('Lands Registry', $allFileNumbers[0]);
                    $fileNoColumn = $groupingService->getFileNoColumnName('Lands Registry');

                    // Fast exact matches first (index-friendly)
                    $exactMatches = DB::connection('sqlsrv')->table($tableName)
                        ->whereIn($fileNoColumn, $allFileNumbers)
                        ->select(['id', $fileNoColumn, 'tracking_id', 'mls_fileno', 'mapping'])
                        ->get();

                    foreach ($exactMatches as $grouping) {
                        $key = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $grouping->$fileNoColumn));
                        $groupingCache[$key] = $grouping;
                    }

                    // Compute remaining file numbers that were not found by exact match
                    $foundRaw = $exactMatches->pluck($fileNoColumn)->map(function ($v) { return (string)$v; })->all();
                    $remaining = array_values(array_diff($allFileNumbers, $foundRaw));

                    if (!empty($remaining)) {
                        // Normalize search terms only for remaining items
                        $normalizedSearchTerms = array_map(function ($f) {
                            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $f));
                        }, $remaining);

                        // Run the expensive normalized lookup only for the small remainder
                        $existingGroupings = DB::connection('sqlsrv')->table($tableName)
                            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$fileNoColumn}), '-', ''), '/', ''), ' ', ''), '\\', ''), '.', '') IN ('" . implode("','", $normalizedSearchTerms) . "')")
                            ->select(['id', $fileNoColumn, 'tracking_id', 'mls_fileno', 'mapping'])
                            ->get();

                        foreach ($existingGroupings as $grouping) {
                            $key = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $grouping->$fileNoColumn));
                            $groupingCache[$key] = $grouping;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logPlotsWorkflow('warning', 'Bulk grouping lookup failed', ['error' => $e->getMessage()]);
                }
                $groupingLookupMs = round((microtime(true) - $groupingLookupStart) * 1000, 1);
                Log::info('Batch grouping lookup completed', ['ms' => $groupingLookupMs, 'found' => count($groupingCache), 'batch_size' => $batchQuantity]);

                // Pre-fetch corresponding file matches for the batch
                $correspondingCache = [];
                try {
                    $matches = DB::connection('sqlsrv')->table('corresponding_fileno')
                        ->whereIn('fileno', $allFileNumbers)
                        ->select(['fileno'])
                        ->get();
                    foreach ($matches as $m) {
                        $correspondingCache[strtoupper(trim($m->fileno))] = $m->fileno;
                    }
                } catch (\Exception $e) {
                    $this->logPlotsWorkflow('warning', 'Bulk corresponding_fileno lookup failed', ['error' => $e->getMessage()]);
                }

                // Resolve lineage and mother property metadata
                $parentPropId = null;
                $relatedFileNumbers = null;
                $motherOwner = null;
                $propIdService = app(\App\Services\PropertyIdAllocationService::class);

                // Typed related files apply to every file in the batch.
                $typedRelatedFiles = $this->parseRelatedFiles($validated['related_files'] ?? null);

                if (!empty($validated['subdivision_app_id'])) {
                    $subApp = \App\Models\PlotSubdivisionApplication::find($validated['subdivision_app_id']);
                    if ($subApp) {
                        $motherFileNo = (string) $subApp->file_no;
                        $motherFile = DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $motherFileNo)
                            ->first();

                        // Resolve prop_id: Try indexing table first, then master lookup
                        $resolvedPropId = $motherFile->prop_id ?? null;
                        if (!$resolvedPropId) {
                            try {
                                $resolvedPropId = $propIdService->allocateOrRetrievePropId($motherFileNo, null, null, null, ['skip_lookup' => false]);
                            } catch (\Exception $e) {
                                Log::warning('Failed to resolve mother prop_id via service', ['file' => $motherFileNo, 'error' => $e->getMessage()]);
                            }
                        }

                        $parentPropId = $resolvedPropId;
                        $relatedFileNumbers = json_encode([$motherFileNo]);
                        $motherOwner = $motherFile->file_title ?? 'Original Owner';

                        Log::info('Subdivision lineage resolved', [
                            'mother_file' => $motherFileNo,
                            'parent_prop_id' => $parentPropId,
                            'mother_owner' => $motherOwner
                        ]);
                    }
                } elseif (!empty($validated['merger_app_id'])) {
                    $mergerApp = \App\Models\PlotMergerApplication::find($validated['merger_app_id']);
                    if ($mergerApp) {
                        $sourceFiles = $mergerApp->plotSizes()->whereIn('type', ['source', 'merger_source'])->pluck('source_file_no')->toArray();
                        if (!empty($sourceFiles)) {
                            $sourceRecords = DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $sourceFiles)
                                ->get();

                            $propIds = $sourceRecords->pluck('prop_id')->unique()->filter()->toArray();

                            // Supplement missing prop_ids from service
                            foreach ($sourceFiles as $sf) {
                                if (!$sourceRecords->where('file_number', $sf)->first()?->prop_id) {
                                    try {
                                        $sfPropId = $propIdService->allocateOrRetrievePropId($sf, null, null, null, ['skip_lookup' => false]);
                                        if ($sfPropId)
                                            $propIds[] = $sfPropId;
                                    } catch (\Exception $e) {
                                    }
                                }
                            }

                            $parentPropId = implode(',', array_unique($propIds));
                            $relatedFileNumbers = json_encode($sourceFiles);

                            $indexedFiles = $sourceRecords->pluck('file_number')->toArray();
                            $missingFiles = array_diff($sourceFiles, $indexedFiles);
                            $titles = $sourceRecords->pluck('file_title')->unique()->filter()->toArray();

                            if (!empty($missingFiles)) {
                                $registryTitles = DB::connection('sqlsrv')->table('fileNumber')
                                    ->whereIn('mlsfNo', $missingFiles)
                                    ->pluck('FileName')
                                    ->unique()
                                    ->filter()
                                    ->toArray();
                                $titles = array_unique(array_merge($titles, $registryTitles));
                            }

                            $titles = array_values(array_filter($titles));
                            if (count($titles) > 1) {
                                $last = array_pop($titles);
                                $motherOwner = implode(', ', $titles) . ' and ' . $last;
                            } else {
                                $motherOwner = $titles[0] ?? 'Multiple Owners';
                            }

                            Log::info('Merger lineage resolved', [
                                'source_files' => $sourceFiles,
                                'parent_prop_id' => $parentPropId
                            ]);
                        }
                    }
                } elseif (!empty($validated['separation_app_id'])) {
                    $separationApp = \App\Models\PlotSeparationApplication::find($validated['separation_app_id']);
                    if ($separationApp) {
                        $motherFileNo = (string) $separationApp->file_no;
                        $motherFile = DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $motherFileNo)
                            ->first();

                        $resolvedPropId = $motherFile->prop_id ?? null;
                        if (!$resolvedPropId) {
                            try {
                                $resolvedPropId = $propIdService->allocateOrRetrievePropId($motherFileNo, null, null, null, ['skip_lookup' => false]);
                            } catch (\Exception $e) {
                                Log::warning('Failed to resolve mother prop_id via service (separation batch)', ['file' => $motherFileNo, 'error' => $e->getMessage()]);
                            }
                        }

                        $parentPropId = $resolvedPropId;
                        $relatedFileNumbers = json_encode([$motherFileNo]);
                        $motherOwner = $motherFile->file_title ?? 'Original Owner';

                        Log::info('Separation lineage resolved (batch)', [
                            'mother_file'    => $motherFileNo,
                            'parent_prop_id' => $parentPropId,
                            'mother_owner'   => $motherOwner
                        ]);
                    }
                } elseif (($validated['file_option'] ?? '') === 'extension' && !empty($validated['existing_file_no'])) {
                    $extFileNo = (string) $validated['existing_file_no'];
                    $extFile = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('file_number', $extFileNo)
                        ->first();

                    $resolvedPropId = $extFile->prop_id ?? null;
                    if (!$resolvedPropId) {
                        try {
                            $resolvedPropId = $propIdService->allocateOrRetrievePropId($extFileNo, null, null, null, ['skip_lookup' => false]);
                        } catch (\Exception $e) {
                        }
                    }

                    $parentPropId = $resolvedPropId;
                    $relatedFileNumbers = json_encode([$extFileNo]);
                    $motherOwner = $extFile->file_title ?? 'Original Owner';
                }

                // What the indexing rows record as related: the resolved lineage plus any
                // officer-entered related files. $relatedFileNumbers itself is left alone
                // because file_indexing_links treats it as the pure parent/source list.
                $batchRelatedFileNumbers = $relatedFileNumbers;
                if (!empty($typedRelatedFiles)) {
                    $batchRelatedFileNumbers = json_encode(array_values(array_unique(array_merge(
                        $relatedFileNumbers ? (json_decode($relatedFileNumbers, true) ?: []) : [],
                        array_column($typedRelatedFiles, 'file_no')
                    ))));
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
                    $serial = $allocatedSerials[$index];
                    $fullFileNumber = $allFileNumbers[$index];
                    $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $fullFileNumber));
                    $cached = $groupingCache[$normalizedKey] ?? null;

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
                        'file_name' => $entry['file_name'] ?? ($validated['file_name'] ?? null),
                        'plot_no' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'district' => $entry['district'] ?? null,
                        'tracking_id' => $trackingId,
                        'customer_type' => $validated['customer_type'],
                        'file_option' => $validated['file_option'],
                        'batch_no' => $batchNo,
                        'created_by' => $commissionedBy,
                        'purpose_id' => $validated['purpose_id'] ?? null,
                        'source' => $sourceValue,
                        'sub_source' => $validated['sub_source'] ?? null,
                        'system_sub_type' => $batchSystemSubType,
                        'source_instrument_capture_id' => $validated['source_instrument_capture_id'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];

                    // Prepare compatibility record
                    $fileNumberData[] = [
                        'tracking_id' => $trackingId,
                        'mlsfNo' => $fullFileNumber,
                        'FileName' => $entry['file_name'] ?? ($validated['file_name'] ?? null),
                        'plot_no' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'district' => $entry['district'] ?? null,
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
                        'file_title' => $entry['file_name'] ?? ($validated['file_name'] ?? null),
                        'land_use_type' => $this->simpleExtractLandUseType($landUse),
                        'plot_number' => $entry['plotNo'] ?? null,
                        'tp_no' => $entry['tpNo'] ?? null,
                        'location' => $entry['location'] ?? null,
                        'lga' => $entry['lga'] ?? null,
                        'created_by' => $commissionedBy,
                        'current_holder' => $entry['file_name'] ?? ($validated['file_name'] ?? null),
                        'original_holder' => $motherOwner ?? ($entry['file_name'] ?? ($validated['file_name'] ?? null)),
                        'parent_prop_id' => $parentPropId,
                        'related_fileno' => $batchRelatedFileNumbers,
                        'workflow_status' => 'indexed',
                        'is_updated' => false,
                        'is_deleted' => false,
                        'is_corresponding_file' => isset($correspondingCache[strtoupper(trim($fullFileNumber))]) ? 1 : 0,
                        'corresponding_fileno' => $correspondingCache[strtoupper(trim($fullFileNumber))] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];

                    // Staging Logic for Entities and Customers (Run per entry in batch)
                    try {
                        // Create separate entity for each file in the batch
                        $entityId = DB::connection('sqlsrv')->table('entities_staging')->insertGetId([
                            'entity_type' => $validated['customer_type'] ?? 'Individual',
                            'entity_name' => $entry['file_name'] ?? ($validated['file_name'] ?? 'N/A'),
                            'file_number' => $fullFileNumber,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);

                        $addressParts = [];
                        if (!empty($entry['plotNo']))
                            $addressParts[] = "Plot " . $entry['plotNo'];
                        if (!empty($entry['location']))
                            $addressParts[] = $entry['location'];
                        if (!empty($entry['lga']))
                            $addressParts[] = $entry['lga'];
                        $propertyAddress = implode(', ', $addressParts);

                        DB::connection('sqlsrv')->table('customers_staging')->insert([
                            'customer_type' => $validated['customer_type'] ?? 'Individual',
                            'file_number' => $fullFileNumber,
                            'customer_name' => $entry['file_name'] ?? ($validated['file_name'] ?? 'N/A'),
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
                // SQL Server caps a single statement at 2100 parameters, so chunk the rows
                // to keep (columns * rows) under that limit (21/14/20 columns respectively).
                foreach (array_chunk($mlsData, 90) as $chunk) {
                    DB::connection('sqlsrv')->table('mls_file_no')->insert($chunk);
                }
                foreach (array_chunk($fileNumberData, 140) as $chunk) {
                    DB::connection('sqlsrv')->table('fileNumber')->insert($chunk);
                }
                foreach (array_chunk($indexingData, 90) as $chunk) {
                    DB::connection('sqlsrv')->table('file_indexings')->insert($chunk);
                }

                // Open tracking for every file in the batch at the destination picked
                // on the Batch Generation Summary — same two lines as a single
                // commissioning (File Commissioning, then the onward movement).
                $this->startBatchCommissioningTracking($request, $generatedFiles);

                // Typed related-file links for every file in the batch. Re-queried rather
                // than captured on insert because the bulk insert above returns no ids.
                if (!empty($typedRelatedFiles) && !empty($generatedFiles)) {
                    $indexingRows = DB::connection('sqlsrv')->table('file_indexings')
                        ->whereIn('file_number', $generatedFiles)
                        ->get(['id', 'file_number', 'file_title', 'prop_id']);

                    foreach ($indexingRows as $row) {
                        $this->storeRelatedFileLinks(
                            $typedRelatedFiles,
                            $row->file_number,
                            $row->file_title,
                            $row->prop_id ?? null,
                            $row->id
                        );
                    }
                }

                // Create lineage links for batch records (Subdivision/Merger/Extension)
                if (!empty($relatedFileNumbers) && !empty($generatedFiles)) {
                    try {
                        $lineageFileNumbers = json_decode($relatedFileNumbers, true) ?: [];
                        if (!empty($lineageFileNumbers)) {
                            // Fetch the newly created indexing records to get their IDs
                            // Note: batch_no is INT in file_indexings; $indexingData does not set it,
                            // so rows have batch_no=NULL. Filter by file_number only.
                            $newIndexings = DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $generatedFiles)
                                ->get(['id', 'file_number', 'land_use_type', 'plot_number', 'tp_no', 'location', 'lga', 'tracking_id', 'file_title']);

                            $batchLinksToCreate = [];

                            // Pre-fetch related parent indexing records for forward links
                            $parentIndexings = DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $lineageFileNumbers)
                                ->get(['id', 'file_number'])
                                ->keyBy('file_number');

                            foreach ($newIndexings as $ni) {
                                foreach ($lineageFileNumbers as $oldFileNo) {
                                    if (empty($oldFileNo))
                                        continue;

                                    // Direction 1: NEW FILE -> OLD FILE
                                    $batchLinksToCreate[] = [
                                        'file_indexing_id' => $ni->id,
                                        'file_number' => $oldFileNo,
                                        'file_title' => 'Parent/Source File',
                                        'land_use_type' => $ni->land_use_type,
                                        'plot_number' => $ni->plot_number,
                                        'tp_no' => $ni->tp_no,
                                        'location' => $ni->location,
                                        'lga' => $ni->lga,
                                        'tracking_id' => $ni->tracking_id,
                                        'indexing_type' => 'lineage_link',
                                        'workflow_status' => 'indexed',
                                        'created_by' => $commissionedBy,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];

                                    // Direction 2: OLD FILE -> NEW FILE
                                    $pi = $parentIndexings->get($oldFileNo);
                                    if ($pi) {
                                        $batchLinksToCreate[] = [
                                            'file_indexing_id' => $pi->id,
                                            'file_number' => $ni->file_number,
                                            'file_title' => 'Subdivision/Merger/Link',
                                            'land_use_type' => $ni->land_use_type,
                                            'plot_number' => $ni->plot_number,
                                            'tp_no' => $ni->tp_no,
                                            'location' => $ni->location,
                                            'lga' => $ni->lga,
                                            'tracking_id' => $ni->tracking_id,
                                            'indexing_type' => 'lineage_link',
                                            'workflow_status' => 'indexed',
                                            'created_by' => $commissionedBy,
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ];
                                    }
                                }
                            }

                            if (!empty($batchLinksToCreate)) {
                                // Insert in chunks if the batch is large to avoid SQL limits
                                foreach (array_chunk($batchLinksToCreate, 100) as $chunk) {
                                    DB::connection('sqlsrv')->table('file_indexing_links')->insert($chunk);
                                }
                                Log::info('Batch lineage links created', ['count' => count($batchLinksToCreate)]);
                            }
                        }
                    } catch (\Exception $batchLinkError) {
                        Log::error('Failed to create batch lineage links', ['error' => $batchLinkError->getMessage()]);
                    }
                }

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

                    // $parentPropId / $relatedFileNumbers / $motherOwner were already resolved in the
                    // lineage block above, including the PropertyIdAllocationService fallback for
                    // mothers whose file_indexings row carries no prop_id (subdivision children keep
                    // prop_id on their PRA row, not on file_indexings). Re-resolving here with a bare
                    // ->value('prop_id') lookup silently dropped that fallback and left
                    // pra.parent_prop_id empty on every batch-commissioned child.

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
                                    'mlsFNo' => $batchFileNumber,
                                ]);
                                $this->logPlotsWorkflow('info', 'Batch PRA: OP exists in IC/DR — skipped, linked source', [
                                    'file_number' => $batchFileNumber,
                                    'source_table' => $existingOp['_source_table'],
                                    'source_id' => $existingOp['id'],
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
                                'parent_prop_id' => $parentPropId,
                                'tracking_id' => $batchTrackingId,
                            ], Auth::id());
                        }

                        $this->logPlotsWorkflow('info', 'Batch PRA records created', [
                            'batch_size' => $batchQuantity,
                            'land_use' => $landUse,
                            'transaction_type' => $opPraMetadata['transaction_type'],
                        ]);
                    } else if (in_array($sourceValue, ['Subdivision', 'Merger', 'Extension', 'Separation'])) {
                        // Create PRA transaction records for Subdivision/Merger/Extension/Separation
                        $plotTransactionType = $sourceValue === 'Extension' ? 'Plot Extension'
                            : ($sourceValue === 'Separation' ? 'Plot Separation' : $sourceValue);
                        $globalFileName = (string) ($validated['file_name'] ?? '');

                        // Note: $motherOwner, $parentPropId, and $relatedFileNumbers were resolved above

                        foreach ($validated['location_entries'] as $index => $entry) {
                            $entryFileName = $entry['file_name'] ?? $globalFileName;
                            $grantee = $entryFileName;
                            $grantor = ($sourceValue === 'Subdivision' || $sourceValue === 'Merger' || $sourceValue === 'Extension' || $sourceValue === 'Separation') ? ($motherOwner ?: $entryFileName) : $entryFileName;
                            $batchFileNumber = $allFileNumbers[$index];
                            $batchTrackingId = $mlsData[$index]['tracking_id'] ?? null;

                            $propId = $propIdService->allocateOrRetrievePropId(
                                $batchFileNumber,
                                $batchFileNumber,
                                null,
                                null,
                                []
                            );

                            // Custom Comment building based on transaction type for PRA in batch
                            $praComment = $plotTransactionType . " batch commissioning for " . $batchFileNumber;
                            if ($sourceValue === 'Merger') {
                                $mergerApp = \App\Models\PlotMergerApplication::find($validated['merger_app_id'] ?? 0);
                                if ($mergerApp) {
                                    $sourceFiles = $mergerApp->plotSizes()->whereIn('type', ['source', 'merger_source'])->pluck('source_file_no')->toArray();
                                    $praComment = 'Plot Merger: ' . implode(', ', $sourceFiles) . '; the new ' . $batchFileNumber;
                                }
                            } elseif ($sourceValue === 'Subdivision') {
                                $subdivisionApp = \App\Models\PlotSubdivisionApplication::find($validated['subdivision_app_id'] ?? 0);
                                if ($subdivisionApp) {
                                    $praComment = 'Plot Subdivision: ' . ($subdivisionApp->num_plots ?? '0') . ' Subdivided from ' . ($subdivisionApp->file_no ?? '');
                                }
                            } elseif ($sourceValue === 'Separation') {
                                $separationApp = \App\Models\PlotSeparationApplication::find($validated['separation_app_id'] ?? 0);
                                if ($separationApp) {
                                    $praComment = 'Plot Separation: ' . ($separationApp->num_plots ?? '0') . ' Separated from ' . ($separationApp->file_no ?? '');
                                }
                            } elseif ($sourceValue === 'Extension') {
                                $extFileNo = (string) ($validated['existing_file_no'] ?? '');
                                $praComment = 'Plot Extension: Plot ' . $extFileNo . ' extended by extra ' . $batchFileNumber;
                            }

                            $praService->createRecord([
                                'mlsFNo' => $batchFileNumber,
                                'fileno' => $batchFileNumber,
                                'temp_fileno' => null,
                                'transaction_type' => $plotTransactionType,
                                'instrument_type' => $plotTransactionType,
                                'transaction_date' => now()->toDateString(),
                                'reg_date' => '0/0/0',
                                'regNo' => '0/0/0',
                                'serialNo' => '0',
                                'pageNo' => '0',
                                'volumeNo' => '0',
                                'system_source' => 'MLS_PLOT_WORKFLOW',
                                'land_use' => $landUse,
                                'plot_no' => (string) ($entry['plotNo'] ?? ''),
                                'lgsaOrCity' => (string) ($entry['lga'] ?? ''),
                                'location' => (string) ($entry['location'] ?? ''),
                                'property_description' => (string) ($entry['location'] ?? ''),
                                'Grantor' => $grantor,
                                'Grantee' => $grantee,
                                'party_1' => $grantor,
                                'party_2' => $grantee,
                                'prop_id' => $propId,
                                'parent_prop_id' => $parentPropId,
                                'tracking_id' => $batchTrackingId,
                                // PraRecordService only persists 'related_file_number'; a bare
                                // 'related_fileno' key is dropped by prepareRecordPayload().
                                'related_file_number' => $relatedFileNumbers
                                    ? implode(', ', (array) (json_decode($relatedFileNumbers, true) ?: []))
                                    : null,
                                'comments' => $praComment,
                                'remarks' => "Batch commissioned via " . $sourceValue . " workflow",
                            ], Auth::id());
                        }

                        $this->logPlotsWorkflow('info', "Batch PRA records created for {$sourceValue}", [
                            'batch_size' => $batchQuantity,
                            'land_use' => $landUse,
                            'transaction_type' => $plotTransactionType,
                        ]);
                    } else {
                        $this->logPlotsWorkflow('info', 'Batch PRA creation skipped for non-OP source', [
                            'batch_size' => $batchQuantity,
                            'land_use' => $landUse,
                            'source' => $sourceValue,
                        ]);
                    }
                } catch (\Exception $praError) {
                    $this->logPlotsWorkflow('error', 'Batch PRA creation failed (non-critical)', [
                        'error' => $praError->getMessage(),
                        'land_use' => $landUse,
                    ]);
                }


                // Update serial control to reserve up to the highest allocated serial
                \App\Models\MlsSerialControl::updateOrCreate(
                    [
                        'land_use' => $landUse,
                        'year' => $year
                    ],
                    [
                        'last_serial' => max($allocatedSerials)
                    ]
                );

                $workflowService = app(PlotWorkflowService::class);
                $parcelNotifier = app(ParcelUpdateNotificationService::class);
                $decommissionSummary = [
                    'archived' => [],
                    'history_updated' => 0
                ];

                // Handle Application Linkage for Batch
                $this->logPlotsWorkflow('info', 'Batch linkage check', [
                    'merger_app_id'     => $validated['merger_app_id'] ?? null,
                    'subdivision_app_id'=> $validated['subdivision_app_id'] ?? null,
                    'separation_app_id' => $validated['separation_app_id'] ?? null,
                    'file_option'       => $validated['file_option'] ?? null,
                    'source'            => $sourceValue,
                ]);

                if (!empty($validated['merger_app_id'])) {
                    $mergerApp = PlotMergerApplication::find($validated['merger_app_id']);
                    $this->logPlotsWorkflow('info', 'Merger app lookup', [
                        'app_id' => $validated['merger_app_id'],
                        'found' => $mergerApp !== null,
                        'status' => $mergerApp->status ?? 'N/A',
                    ]);

                    if ($mergerApp) {
                        $sourceFiles = $mergerApp->plotSizes()
                            ->whereIn('type', ['source', 'merger_source'])
                            ->pluck('source_file_no')
                            ->toArray();
                        $this->logPlotsWorkflow('info', 'Merger source files resolved', [
                            'source_files' => $sourceFiles,
                            'count' => count($sourceFiles),
                        ]);

                        if (!empty($sourceFiles)) {
                            // 1. Get old PropIDs
                            $oldPropIds = DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $sourceFiles)
                                ->whereNotNull('prop_id')
                                ->pluck('prop_id')
                                ->toArray();

                            // 2. Decommission
                            // Pass every new file as the successor (CSV) so the Decommissioned Files
                            // list's Related File column shows all batch-generated files, not just the first.
                            $res = $workflowService->decommissionFiles($sourceFiles, "Plot Merger to batch of " . count($allFileNumbers) . " files", $commissionedBy, (!empty($allFileNumbers) ? implode(',', $allFileNumbers) : null));
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);

                            $this->logPlotsWorkflow('info', 'Merger decommission result', [
                                'archived' => $res['archived'],
                                'errors' => $res['errors'],
                            ]);

                            // 3. Update parent_prop_id for all new files
                            if (!empty($oldPropIds)) {
                                DB::connection('sqlsrv')->table('file_indexings')
                                    ->whereIn('file_number', $allFileNumbers)
                                    ->update([
                                        'parent_prop_id' => implode(',', array_unique($oldPropIds)),
                                        'related_fileno' => json_encode(array_values($sourceFiles))
                                    ]);

                                DB::connection('sqlsrv')->table('fileNumber')
                                    ->whereIn('mlsfNo', $allFileNumbers)
                                    ->update([
                                        'parent_prop_id' => implode(',', array_unique($oldPropIds)),
                                        'related_fileno' => json_encode(array_values($sourceFiles))
                                    ]);
                            }
                        }

                        $mergerApp->update([
                            'status' => PlotMergerApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned to Batch of {$batchQuantity} files (First: {$allFileNumbers[0]}) on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);
                    }
                    $this->logPlotsWorkflow('info', 'Batch Merger application marked as commissioned', ['app_id' => $validated['merger_app_id']]);
                }

                if (!empty($validated['subdivision_app_id'])) {
                    $subdivisionApp = PlotSubdivisionApplication::find($validated['subdivision_app_id']);
                    if ($subdivisionApp) {
                        $motherFile = $subdivisionApp->file_no;
                        $motherIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->first();

                        if ($motherIndexing && $motherIndexing->prop_id) {
                            DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $allFileNumbers)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);

                            DB::connection('sqlsrv')->table('fileNumber')
                                ->whereIn('mlsfNo', $allFileNumbers)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);
                        }

                        // Decommission mother if exists in registry
                        $motherExists = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $motherFile)->exists()
                            || DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->exists();

                        if ($motherExists) {
                            // Pass every new fragment as the successor (CSV) so the Decommissioned Files
                            // list's Related File column shows all fragments, not just the first.
                            $res = $workflowService->decommissionFiles([$motherFile], "Plot Subdivision into batch of " . count($allFileNumbers) . " fragments", $commissionedBy, (!empty($allFileNumbers) ? implode(',', $allFileNumbers) : null));
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);
                        }

                        $subdivisionApp->update([
                            'status' => PlotSubdivisionApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned to Batch of {$batchQuantity} files (First: {$allFileNumbers[0]}) on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);
                    }
                    $this->logPlotsWorkflow('info', 'Batch Subdivision application marked as commissioned', ['app_id' => $validated['subdivision_app_id']]);
                }

                if (!empty($validated['separation_app_id'])) {
                    $separationApp = \App\Models\PlotSeparationApplication::find($validated['separation_app_id']);
                    if ($separationApp) {
                        $motherFile = $separationApp->file_no;
                        $motherIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->first();

                        if ($motherIndexing && $motherIndexing->prop_id) {
                            DB::connection('sqlsrv')->table('file_indexings')
                                ->whereIn('file_number', $allFileNumbers)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);

                            DB::connection('sqlsrv')->table('fileNumber')
                                ->whereIn('mlsfNo', $allFileNumbers)
                                ->update([
                                    'parent_prop_id' => $motherIndexing->prop_id,
                                    'related_fileno' => json_encode([$motherFile])
                                ]);
                        }

                        // Decommission mother if exists in registry
                        $motherExists = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $motherFile)->exists()
                            || DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $motherFile)->exists();

                        if ($motherExists) {
                            // Pass every new fragment as the successor (CSV) so the Decommissioned Files
                            // list's Related File column shows all fragments, not just the first.
                            $res = $workflowService->decommissionFiles([$motherFile], "Plot Separation into batch of " . count($allFileNumbers) . " fragments", $commissionedBy, (!empty($allFileNumbers) ? implode(',', $allFileNumbers) : null));
                            $decommissionSummary['archived'] = array_merge($decommissionSummary['archived'], $res['archived']);
                        }

                        $separationApp->update([
                            'status' => \App\Models\PlotSeparationApplication::STATUS_COMMISSIONED,
                            'remarks' => "Commissioned to Batch of {$batchQuantity} files (First: {$allFileNumbers[0]}) on " . now()->toDateTimeString(),
                            'updated_by' => Auth::id()
                        ]);

                        // Notify Deeds users
                        $parcelNotifier->notifyCommissioned('separation', $separationApp->id, $motherFile, $allFileNumbers[0] ?? '', $commissionedBy);
                    }
                    $this->logPlotsWorkflow('info', 'Batch Separation application marked as commissioned', ['app_id' => $validated['separation_app_id']]);
                }

                DB::connection('sqlsrv')->commit();

                $edmsFolders = [];
                foreach ($generatedFiles as $generatedFileNumber) {
                    $edmsFolders[] = $this->ensureEdmsScanFolder($generatedFileNumber);
                }
                $edmsFolderSummary = [
                    'created'  => count(array_filter($edmsFolders, fn ($f) => $f['created'])),
                    'existed'  => count(array_filter($edmsFolders, fn ($f) => $f['existed'])),
                    'total'    => count($edmsFolders),
                    'registry' => $edmsFolders[0]['registry'] ?? null,
                ];

                $serialRange = $allocatedSerials[0] . ' to ' . end($allocatedSerials);

                Log::channel('mls_batch')->info('Batch MLS File Numbers Generated', [
                    'batch_no' => $batchNo,
                    'batch_size' => $batchQuantity,
                    'land_use' => $landUse,
                    'serial_range' => $serialRange,
                    'files' => $generatedFiles,
                    'user' => Auth::user()->name ?? 'Unknown'
                ]);

                // Build a human-readable notice when serials were skipped
                $skipNotice = null;
                if (!empty($skippedSerials)) {
                    $skippedFileNos = array_column($skippedSerials, 'file_number');
                    $skipNotice = count($skippedSerials) . ' file number(s) were already in use and skipped ('
                        . implode(', ', $skippedFileNos) . '); the next free numbers were assigned instead.';
                }

                return response()->json([
                    'success' => true,
                    'message' => "Successfully generated {$batchQuantity} file numbers",
                    'files' => $generatedFiles,
                    'decommission_summary' => $decommissionSummary,
                    'skipped_serials' => $skippedSerials,
                    'notice' => $skipNotice,
                    // One scan folder per file in the batch, rolled up for the summary.
                    'edms_folder' => $edmsFolderSummary,
                    'data' => [
                        'batch_size' => $batchQuantity,
                        'land_use' => $landUse,
                        'application_type' => in_array($validated['file_option'] ?? '', ['subdivision', 'merger', 'extension', 'separation'])
                            ? $validated['file_option']
                            : ($validated['application_type'] ?? 'new'),
                        'year' => $year,
                        'serial_range' => $serialRange,
                        'mother_file_no' => $motherFileNo ?? $validated['existing_file_no'] ?? null,
                        'source_files' => $sourceFiles ?? [],
                        // Null for plain new allocations: each file in the batch gets its own
                        // fresh prop_id rather than descending from a parent parcel.
                        'parent_prop_id' => $parentPropId ?? null,
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
            Log::channel('mls_batch')->error('Error generating batch MLS file numbers', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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
        $this->logPlotsWorkflow('info', 'Loading source OP row for mirror', [
            'source_instrument_capture_id' => $sourceId,
            'file_number' => $fullFileNumber,
        ]);
        $source = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $sourceId)->first();

        if (!$source) {
            $this->logPlotsWorkflow('error', 'Source OP row not found for mirror', [
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
            'mlsFNo' => $fullFileNumber,
            'fileno' => $fullFileNumber,
            'source_op_id' => $sourceId > 0 ? $sourceId : null,
            'source_op_table' => $sourceId > 0 ? 'instrument_capture' : null,
        ]);

        if ($existingOp) {
            $praService->linkSourceOpRecord($existingOp, [
                'prop_id' => $propId,
                'mlsFNo' => $fullFileNumber,
            ]);
            Log::info('createResettlementLinkedRecords: OP exists in IC/DR — skipped PRA, linked source', [
                'source_table' => $existingOp['_source_table'],
                'source_id' => $existingOp['id'],
                'prop_id' => $propId,
            ]);
        } else {
            // No existing IC/DR yet — but IC+DR will be created below, so skip
            // PRA OP row to avoid triplication.  The IC+DR records ARE the source.
            Log::info('createResettlementLinkedRecords: skipping PRA OP row — IC+DR will be created as source', [
                'file_number' => $fullFileNumber,
                'prop_id' => $propId,
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
        $this->logPlotsWorkflow('info', 'Instrument capture mirror row created', [
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

            $this->logPlotsWorkflow('info', 'Mirror updates applied to transaction tables', [
                'source_instrument_capture_id' => $sourceId,
                'new_instrument_capture_id' => $captureId,
                'file_number' => $fullFileNumber,
                'prop_id' => $propId,
            ]);
        }
    }

    /**
     * Create the commissioned file's EDMS scan folder.
     *
     * Delegates to EdmsScanUploadFolderService so commissioning, indexing and the
     * scanning/page-typing readers all agree on one path. This used to build the
     * path inline against a hardcoded "Lands_Registry" — fine in practice, since
     * MLS commissioning only ever issues Land file numbers, but it meant the
     * sanitising and slug rules lived in two places and could drift apart.
     *
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string}
     */
    private function ensureEdmsScanFolder(string $fileNumber): array
    {
        return app(\App\Services\EdmsScanUploadFolderService::class)
            ->ensure($fileNumber, 'Lands Registry', ['source' => 'mls_commissioning']);
    }

    /**
     * "Where did this commissioning land?" — counts for the Commission Summary card.
     *
     * Commissioning writes across mls_file_no, fileNumber, file_indexings and the
     * customer/entity staging tables, none of which the operator can see from the
     * form. Reuses IndexingStorageSummaryService so the commissioning card reads
     * like the one shown after file indexing and after ST commissioning.
     *
     * Best-effort and read-only: the commissioning is already committed by the time
     * this runs, so a failure here must never turn a successful save into an error.
     */
    private function buildStorageSummary(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        try {
            $indexing = \App\Models\FileIndexing::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->orderByDesc('id')
                ->first();

            if (!$indexing) {
                // Not every commissioning path creates an indexing row. An unsaved
                // stand-in carrying just the number still counts everything keyed BY
                // FILE NUMBER (mls_file_no, fileNumber, customer/entity staging);
                // the id-keyed rows correctly come back zero.
                $indexing = new \App\Models\FileIndexing();
                $indexing->setConnection('sqlsrv');
                $indexing->file_number = $fileNumber;
                $indexing->general_registry = 'Lands Registry';
            }

            return app(\App\Services\IndexingStorageSummaryService::class)
                ->summarize($indexing, ['is_update' => false]);
        } catch (\Throwable $e) {
            Log::warning('MlsFileNoController - could not build storage summary', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function logPlotsWorkflow(string $level, string $message, array $context = []): void
    {
        try {
            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/plots-workflow.log'),
                'level' => 'debug',
            ]);
            $logger->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::warning('Failed to write Plots workflow audit log', [
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
            // Normalize for the raw query in the service
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $fileNumber));

            $result = $groupingService->findGroupingRecord(DB::connection('sqlsrv'), $fileNumber, $normalized);

            if ($result['record'] && !empty($result['record']->tracking_id)) {
                return trim($result['record']->tracking_id);
            }
        } catch (\Exception $e) {
            Log::warning('Error fetching tracking_id from grouping for ' . $fileNumber . ': ' . $e->getMessage());
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
                'date' => 'nullable|date|required_if:scope,date',
                'include_printed' => 'nullable|boolean',
                'document_type' => 'nullable|string'
            ]);

            $scope = $validated['scope'] ?? 'batch';
            $batchNo = $validated['batch_no'] ?? null;
            $date = $validated['date'] ?? null;
            // Which document's print history decides "printed" here. Commissioning
            // sheets and Applications for Conversion are logged separately, so a date
            // whose sheets are all printed can still have unprinted conversion apps.
            $documentType = $validated['document_type'] ?? 'Commissioning Sheet';

            // Fetch records joined with fileNumber for IDs.
            // Use a subquery for fileNumber to avoid multiplication if tracking_id is duplicated.
            $query = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->leftJoin(DB::raw("(SELECT tracking_id, MIN(id) as id FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0) AND type = 'MlsFileNO' GROUP BY tracking_id) as fn"), 'mls_file_no.tracking_id', '=', 'fn.tracking_id')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->where(function ($q) {
                    $q->whereNull('mls_file_no.is_deleted')->orWhere('mls_file_no.is_deleted', 0);
                })
                ->tap(function ($q) {
                    // Exclude OSS / One-Stop Shop specific records
                    \App\Support\OssOpCommissionFilter::applyExclusion($q);
                })
                ->select([
                    'mls_file_no.*',
                    'purposes.name as purpose_name',
                    'fn.id as filenumber_id'
                ]);

            if ($scope === 'daily_24h') {
                $query->where('mls_file_no.created_at', '>=', now()->subHours(24))
                    ->orderBy('mls_file_no.created_at', 'asc')
                    ->orderBy('mls_file_no.serial_number', 'asc');
            } elseif ($scope === 'date' && $date) {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('mls_file_no.commissioning_date', $date)
                        ->orWhereDate('mls_file_no.created_at', $date);
                })
                    ->orderBy('mls_file_no.serial_number', 'asc')
                    ->orderBy('mls_file_no.created_at', 'asc');
            } else {
                $query->where('mls_file_no.batch_no', $batchNo)
                    ->orderBy('mls_file_no.serial_number', 'asc')
                    ->orderBy('mls_file_no.created_at', 'asc');
            }

            // For informative messaging, we check if records exist for this date at all
            $totalCountForScope = (clone $query)->count();

            // Count unprinted records separately for the count badge
            $unprintedCountQuery = (clone $query)->whereNotExists(function ($q) use ($documentType) {
                $q->select(DB::raw(1))
                    ->from('print_logs as pl')
                    ->whereColumn('pl.reference_number', 'mls_file_no.full_file_number')
                    ->where('pl.document_type', $documentType);
            });
            $unprintedCount = $unprintedCountQuery->count();
            $printedCount = $totalCountForScope - $unprintedCount;

            // Conversion (CON-) files carry a second document — the Application for
            // Conversion — with its own print log, so report its counts alongside.
            $conversionQuery = (clone $query)->where('mls_file_no.full_file_number', 'like', 'CON-%');
            $conversionTotal = (clone $conversionQuery)->count();
            $conversionUnprinted = (clone $conversionQuery)->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('print_logs as pl')
                    ->whereColumn('pl.reference_number', 'mls_file_no.full_file_number')
                    ->where('pl.document_type', 'Application for Conversion');
            })->count();
            $conversionPrinted = $conversionTotal - $conversionUnprinted;

            // Exclude files already printed (for date/daily scopes only).
            if (in_array($scope, ['date', 'daily_24h'], true) && !$request->boolean('include_printed')) {
                $query = $unprintedCountQuery;
            }

            $records = $query->get();

            if ($records->isEmpty() && !$request->boolean('include_printed')) {
                $message = 'No records found.';
                if ($scope === 'daily_24h') {
                    $message = 'No unprinted records found in the last 24 hours.';
                } elseif ($scope === 'date') {
                    if ($totalCountForScope > 0) {
                        $message = "All {$totalCountForScope} commissioning sheets for " . date('M j, Y', strtotime($date)) . " have already been printed.";
                    } else {
                        $message = "No commissioning sheets found for " . date('M j, Y', strtotime($date)) . ".";
                    }
                } elseif ($scope === 'batch') {
                    $message = "No unprinted records found for batch {$batchNo}.";
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'total_count' => $totalCountForScope,
                    'printed_count' => $printedCount,
                    'unprinted_count' => $unprintedCount,
                    'document_type' => $documentType,
                    'conversion_total_count' => $conversionTotal,
                    'conversion_printed_count' => $conversionPrinted,
                    'conversion_unprinted_count' => $conversionUnprinted
                ], 404);
            }

            return response()->json([
                'success' => true,
                'count' => $records->count(),
                'total_count' => $totalCountForScope,
                'printed_count' => $printedCount,
                'unprinted_count' => $unprintedCount,
                'document_type' => $documentType,
                'conversion_total_count' => $conversionTotal,
                'conversion_printed_count' => $conversionPrinted,
                'conversion_unprinted_count' => $conversionUnprinted,
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
     * Mark a list of file numbers as printed in print_logs.
     * Used by the date-scoped batch print so previously printed files
     * are excluded from subsequent batch prints on the same date.
     */
    public function markBatchPrinted(Request $request)
    {
        $validated = $request->validate([
            'file_numbers' => 'required|array|min:1',
            'file_numbers.*' => 'string',
            'document_type' => 'nullable|string',
            'reference_label' => 'nullable|string',
        ]);

        $docType = $validated['document_type'] ?? 'Commissioning Sheet';
        $referenceLabel = $validated['reference_label'] ?? null;

        try {
            $now = now();
            $userId = Auth::check() ? Auth::id() : null;

            $rows = [];
            foreach (array_unique($validated['file_numbers']) as $fileNo) {
                $fileNo = trim((string) $fileNo);
                if ($fileNo === '') {
                    continue;
                }

                $rows[] = [
                    'reference_number' => $fileNo,
                    'document_type' => $docType,
                    'print_type' => 'Batch',
                    'status' => 'Printed',
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid file numbers supplied.',
                ], 422);
            }

            // Also log a single aggregate entry tied to the reference label (date),
            // matching the existing pattern from FileNumberController::recordPrint().
            if ($referenceLabel) {
                $rows[] = [
                    'reference_number' => $referenceLabel,
                    'document_type' => $docType,
                    'print_type' => 'Date',
                    'status' => 'Printed',
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection('sqlsrv')->table('print_logs')->insert($rows);

            return response()->json([
                'success' => true,
                'logged' => count($rows),
            ]);
        } catch (\Exception $e) {
            Log::error('markBatchPrinted error', [
                'error' => $e->getMessage(),
                'count' => count($validated['file_numbers'] ?? []),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not record batch print: ' . $e->getMessage(),
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




