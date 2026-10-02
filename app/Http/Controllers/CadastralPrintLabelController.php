<?php

namespace App\Http\Controllers;
   
use App\Models\FileIndexing;
use App\Models\CadastralPrintLabelBatch;
use App\Models\CadastralPrintLabelBatchItem;
use App\Models\CadastralRackShelfLabel;
use App\Jobs\SyncCadastralShelfLocationToFileIndexings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CadastralPrintLabelController extends Controller
{ 
    private const RACK_SHELF_CAPACITY = 100;
    private const MAX_BATCH_SELECTION = 500;
    private const MAX_MANUAL_OVERRIDE = 100;

    public function index(Request $request) {
        // Check if we should filter for ST records
        $showOnlyST = $request->get('url') === 'st';
        
        // Set page details based on filter type
        if ($showOnlyST) {
            $PageTitle = 'Cadastral ST File Labels';
            $PageDescription = 'Generate and print cadastral labels for Sectional Titling files';
        } else {
            $PageTitle = 'Cadastral File Labels';
            $PageDescription = 'Generate and print labels for cadastral files';
        }
        
        // Get statistics with error handling
        try {
            if ($showOnlyST) {
                // For ST files, don't require batch_no
                $availableFilesQuery = FileIndexing::on('sqlsrv')
                    ->where(function($query) {
                        $query->whereNotNull('main_application_id')
                              ->orWhereNotNull('subapplication_id');
                    })
                    ->whereDoesntHave('cadastralPrintLabelBatchItems');
            } else {
                // For regular files, require batch_no
                $availableFilesQuery = FileIndexing::on('sqlsrv')
                    ->whereNotNull('batch_no')
                    ->whereDoesntHave('cadastralPrintLabelBatchItems');
            }
            
            $availableFilesCount = $availableFilesQuery->count();
        } catch (\Exception $e) {
            // Fallback if tables don't exist yet or relationship issues
            Log::warning('Error counting available files for print labels', ['error' => $e->getMessage()]);
            $availableFilesCount = 0;
        }
        
        try {
            $recentBatches = CadastralPrintLabelBatch::with(['batchItems', 'creator'])
                ->recent(30)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            // Fallback if tables don't exist yet
            Log::warning('Error fetching recent batches', ['error' => $e->getMessage()]);
            $recentBatches = collect();
        }
        
        Log::info('Print Label page accessed', ['user_id' => auth()->id(), 'st_filter' => $showOnlyST]);
        return view('cadastral_printlabel.index', compact(
            'PageTitle', 
            'PageDescription', 
            'availableFilesCount',
            'recentBatches',
            'showOnlyST'
        ));
    }

    /**
     * Return paginated list of files available for label printing
     */
    public function getAvailableFiles(Request $request)
    {
        try {
            // Log the start of the request for debugging
            Log::info('getAvailableFiles API called', [
                'user_id' => auth()->id(),
                'request_params' => $request->all()
            ]);

            $perPage = (int) $request->get('per_page', 30);
            $perPage = max(1, min($perPage, 100));

            $stFilter = $request->get('st_filter') === 'true';
            $search = trim((string) $request->get('search', ''));

            // Optimize: Check shelf_rack column existence only once with caching
            static $shelfRackCache = null;
            if ($shelfRackCache === null) {
                try {
                    $shelfRackCache = Schema::connection('sqlsrv')->hasColumn('grouping', 'shelf_rack');
                } catch (\Exception $e) {
                    Log::warning('Could not check shelf_rack column existence', ['error' => $e->getMessage()]);
                    $shelfRackCache = false;
                }
            }
            $groupingHasShelfRack = $shelfRackCache;

            // Build optimized select columns
            $selectColumns = [
                'file_indexings.id',
                'file_indexings.file_number', 
                'file_indexings.file_title',
                'file_indexings.plot_number',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.land_use_type',
                'file_indexings.shelf_location',
                'file_indexings.shelf_label_id',
                'file_indexings.batch_no',
                'file_indexings.tracking_id as indexing_tracking_id',
                'file_indexings.registry_batch_no',
                'file_indexings.main_application_id',
                'file_indexings.subapplication_id',
                'file_indexings.st_fillno',
                'rack_labels.full_label as shelf_full_label',
            ];

            // Only join expensive tables when search is provided or ST filter is active
            $needsGrouping = $search !== '' || $stFilter;
            $needsApplications = $stFilter;

            if ($needsGrouping) {
                $selectColumns = array_merge($selectColumns, [
                    'grouping.tracking_id as grouping_tracking_id',
                    'grouping.awaiting_fileno',
                    'grouping.mls_fileno',
                    'grouping.batch_no as grouping_batch_no',
                    'grouping.registry_batch_no as grouping_registry_batch_no',
                    'grouping.landuse',
                ]);

                if ($groupingHasShelfRack) {
                    $selectColumns[] = 'grouping.shelf_rack as grouping_shelf_rack';
                }
            }

            if ($needsApplications) {
                $selectColumns = array_merge($selectColumns, [
                    'mother_applications.fileno as mother_fileno',
                    'mother_applications.np_fileno as mother_np_fileno',
                    'mother_applications.application_type as mother_application_type',
                    'subapplications.fileno as sub_fileno',
                    'subapplications.np_fileno as sub_np_fileno',
                    'subapplications.application_type as sub_application_type',
                ]);
            }

            $query = FileIndexing::on('sqlsrv')
                ->select($selectColumns)
                ->leftJoin('Rack_Shelf_Labels as rack_labels', 'rack_labels.id', '=', 'file_indexings.shelf_label_id')
                ->where(function ($query) {
                    $query->whereNull('file_indexings.is_deleted')
                        ->orWhere('file_indexings.is_deleted', false);
                })
                ->whereDoesntHave('cadastralPrintLabelBatchItems');

            // Add joins only when needed
            if ($needsGrouping) {
                $query->leftJoin('grouping', 'grouping.awaiting_fileno', '=', 'file_indexings.file_number');
            }

            if ($needsApplications) {
                $query->leftJoin('mother_applications', 'file_indexings.main_application_id', '=', 'mother_applications.id')
                      ->leftJoin('subapplications', 'file_indexings.subapplication_id', '=', 'subapplications.id');
            }

            // Apply filters
            if (!$stFilter) {
                $query->whereNotNull('file_indexings.batch_no');
            } else {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('file_indexings.subapplication_id')
                        ->orWhereNotNull('file_indexings.main_application_id');
                });
            }

            // Optimize search: only search in joined columns if they're available
            if ($search !== '') {
                $query->where(function ($searchQuery) use ($search, $needsGrouping, $needsApplications) {
                    $like = '%' . $search . '%';
                    $searchQuery->where('file_indexings.file_number', 'like', $like)
                        ->orWhere('file_indexings.file_title', 'like', $like)
                        ->orWhere('file_indexings.plot_number', 'like', $like)
                        ->orWhere('file_indexings.district', 'like', $like)
                        ->orWhere('file_indexings.lga', 'like', $like);

                    if ($needsGrouping) {
                        $searchQuery->orWhere('grouping.awaiting_fileno', 'like', $like)
                            ->orWhere('grouping.mls_fileno', 'like', $like)
                            ->orWhere('grouping.tracking_id', 'like', $like);
                    }

                    if ($needsApplications) {
                        $searchQuery->orWhere('mother_applications.fileno', 'like', $like)
                            ->orWhere('mother_applications.np_fileno', 'like', $like)
                            ->orWhere('subapplications.fileno', 'like', $like)
                            ->orWhere('subapplications.np_fileno', 'like', $like);
                    }
                });
            }

            // Use limit instead of pagination for better performance on large datasets
            $files = $query
                ->orderByDesc('file_indexings.id')
                ->limit($perPage)
                ->get();

            $records = $files->map(function ($item) use ($groupingHasShelfRack, $needsGrouping, $needsApplications) {
                $shelfFullLabel = $item->shelf_full_label ?? null;
                $rawShelfLocation = $item->shelf_location ?? null;
                $effectiveShelfLocation = $shelfFullLabel ?? $rawShelfLocation;

                $record = [
                    'id' => $item->id,
                    'file_number' => $item->file_number,
                    'file_title' => $item->file_title,
                    'plot_number' => $item->plot_number,
                    'district' => $item->district,
                    'lga' => $item->lga,
                    'land_use_type' => $item->land_use_type,
                    'shelf_location' => $effectiveShelfLocation,
                    'shelf_label' => $shelfFullLabel ?? $rawShelfLocation,
                    'shelf_full_label' => $shelfFullLabel,
                    'shelf_value' => $shelfFullLabel ?? $rawShelfLocation,
                    'shelf_label_id' => $item->shelf_label_id,
                    'batch_no' => $item->batch_no,
                    'tracking_id' => $item->indexing_tracking_id,
                    'main_application_id' => $item->main_application_id,
                    'subapplication_id' => $item->subapplication_id,
                    'registry_batch_no' => $item->registry_batch_no,
                    'st_fillno' => $item->st_fillno,
                    // Default values for optional fields
                    'landuse' => null,
                    'awaiting_fileno' => null,
                    'indexing_mls_fileno' => null,
                    'mother_fileno' => null,
                    'mother_np_fileno' => null,
                    'sub_fileno' => null,
                    'sub_np_fileno' => null,
                    'grouping_registry_batch_no' => null,
                    'grouping_shelf_rack' => null,
                ];

                // Add grouping data if available
                if ($needsGrouping) {
                    $record['landuse'] = $item->landuse ?? null;
                    $record['tracking_id'] = $item->grouping_tracking_id ?? $item->indexing_tracking_id;
                    $record['awaiting_fileno'] = $item->awaiting_fileno ?? null;
                    $record['indexing_mls_fileno'] = $item->mls_fileno ?? null;
                    $record['batch_no'] = $item->batch_no ?? $item->grouping_batch_no;
                    $record['grouping_registry_batch_no'] = $item->grouping_registry_batch_no ?? null;
                    $record['land_use_type'] = $item->land_use_type ?? $item->landuse;

                    if ($groupingHasShelfRack) {
                        $record['grouping_shelf_rack'] = $item->grouping_shelf_rack ?? null;
                    }
                }

                // Add application data if available  
                if ($needsApplications) {
                    $record['mother_fileno'] = $item->mother_fileno ?? null;
                    $record['mother_np_fileno'] = $item->mother_np_fileno ?? null;
                    $record['sub_fileno'] = $item->sub_fileno ?? null;
                    $record['sub_np_fileno'] = $item->sub_np_fileno ?? null;
                }

                return $record;
            })->toArray();

            if (count($records) === 0) {
                $fallback = $this->fetchFallbackGroupingRows($perPage);

                if (!empty($fallback['records'])) {
                    $fallbackRegistryBatchRaw = $fallback['meta']['registry_batch'] ?? null;
                    $fallbackRegistryBatch = $fallbackRegistryBatchRaw !== null && trim((string) $fallbackRegistryBatchRaw) !== ''
                        ? trim((string) $fallbackRegistryBatchRaw)
                        : 'fallback-preview';

                    return response()->json([
                        'success' => true,
                        'data' => $fallback['records'],
                        'fallback' => [
                            'source' => 'grouping',
                            'registry_batch' => $fallbackRegistryBatchRaw !== null ? trim((string) $fallbackRegistryBatchRaw) : null,
                            'effective_registry_batch' => $fallbackRegistryBatch,
                            'count' => count($fallback['records']),
                        ],
                        'label_statistics' => $this->buildShelfStatistics($fallbackRegistryBatch),
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => $perPage,
                            'has_more_pages' => false,
                            'next_page_url' => null,
                            'prev_page_url' => null,
                            'last_page' => 1,
                            'total' => null,
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'has_more_pages' => $files->count() >= $perPage,
                    'next_page_url' => $files->count() >= $perPage ? url()->current() . '?page=2' : null,
                    'prev_page_url' => null,
                    'last_page' => 1,
                    'total' => null,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Error fetching available files for printing', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch available files.',
            ], 500);
        }
    }

    public function lookupFileNumbers(Request $request)
    {
        $rawInput = $request->input('file_numbers');
        if ($rawInput === null && $request->filled('file_numbers_raw')) {
            $rawInput = $request->input('file_numbers_raw');
        }

        $fileNumbers = $this->parseFileNumberList($rawInput);

        if ($fileNumbers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one file number.',
            ], 422);
        }

        if ($fileNumbers->count() > self::MAX_MANUAL_OVERRIDE) {
            return response()->json([
                'success' => false,
                'message' => sprintf('You can look up a maximum of %d file numbers at once.', self::MAX_MANUAL_OVERRIDE),
            ], 422);
        }

        try {
            $selectColumns = [
                'file_indexings.id',
                'file_indexings.file_number',
                'file_indexings.file_title',
                'file_indexings.plot_number',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.land_use_type',
                'file_indexings.shelf_location',
                'file_indexings.shelf_label_id',
                'file_indexings.batch_no',
                'file_indexings.tracking_id as indexing_tracking_id',
                'file_indexings.registry_batch_no',
                'file_indexings.main_application_id',
                'file_indexings.subapplication_id',
                'file_indexings.st_fillno',
                'rack_labels.full_label as shelf_full_label',
            ];

            $files = FileIndexing::on('sqlsrv')
                ->select($selectColumns)
                ->leftJoin('Rack_Shelf_Labels as rack_labels', 'rack_labels.id', '=', 'file_indexings.shelf_label_id')
                ->where(function ($query) {
                    $query->whereNull('file_indexings.is_deleted')
                        ->orWhere('file_indexings.is_deleted', false)
                        ->orWhere('file_indexings.is_deleted', 0);
                })
                ->whereIn('file_indexings.file_number', $fileNumbers->all())
                ->get();
        } catch (\Throwable $e) {
            Log::error('Failed to lookup file numbers for manual override', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to lookup file numbers at this time.',
            ], 500);
        }

        $groupingHasShelfRack = false;
        $needsGrouping = false;
        $needsApplications = false;

        $records = $files->map(function ($item) use ($groupingHasShelfRack, $needsGrouping, $needsApplications) {
            $shelfFullLabel = $item->shelf_full_label ?? null;
            $rawShelfLocation = $item->shelf_location ?? null;
            $effectiveShelfLocation = $shelfFullLabel ?? $rawShelfLocation;

            $record = [
                'id' => $item->id,
                'file_number' => $item->file_number,
                'file_title' => $item->file_title,
                'plot_number' => $item->plot_number,
                'district' => $item->district,
                'lga' => $item->lga,
                'land_use_type' => $item->land_use_type,
                'shelf_location' => $effectiveShelfLocation,
                'shelf_label' => $shelfFullLabel ?? $rawShelfLocation,
                'shelf_full_label' => $shelfFullLabel,
                'shelf_value' => $shelfFullLabel ?? $rawShelfLocation,
                'shelf_label_id' => $item->shelf_label_id,
                'batch_no' => $item->batch_no,
                'tracking_id' => $item->indexing_tracking_id,
                'main_application_id' => $item->main_application_id,
                'subapplication_id' => $item->subapplication_id,
                'registry_batch_no' => $item->registry_batch_no,
                'st_fillno' => $item->st_fillno,
                'landuse' => null,
                'awaiting_fileno' => null,
                'indexing_mls_fileno' => null,
                'mother_fileno' => null,
                'mother_np_fileno' => null,
                'sub_fileno' => null,
                'sub_np_fileno' => null,
                'grouping_registry_batch_no' => null,
                'grouping_shelf_rack' => null,
            ];

            if ($needsGrouping) {
                $record['landuse'] = $item->landuse ?? null;
                $record['tracking_id'] = $item->grouping_tracking_id ?? $item->indexing_tracking_id;
                $record['awaiting_fileno'] = $item->awaiting_fileno ?? null;
                $record['indexing_mls_fileno'] = $item->mls_fileno ?? null;
                $record['batch_no'] = $item->batch_no ?? $item->grouping_batch_no;
                $record['grouping_registry_batch_no'] = $item->grouping_registry_batch_no ?? null;
                $record['land_use_type'] = $item->land_use_type ?? $item->landuse;

                if ($groupingHasShelfRack) {
                    $record['grouping_shelf_rack'] = $item->grouping_shelf_rack ?? null;
                }
            }

            if ($needsApplications) {
                $record['mother_fileno'] = $item->mother_fileno ?? null;
                $record['mother_np_fileno'] = $item->mother_np_fileno ?? null;
                $record['sub_fileno'] = $item->sub_fileno ?? null;
                $record['sub_np_fileno'] = $item->sub_np_fileno ?? null;
            }

            return $record;
        })->toArray();

        $recordsCollection = collect($records);

        $recordsByNormalized = $recordsCollection->mapWithKeys(function (array $record) {
            $normalized = strtoupper(trim((string) ($record['file_number'] ?? '')));

            return $normalized === ''
                ? []
                : [$normalized => $record];
        });

        $indexedRequested = $fileNumbers
            ->map(function (string $value) {
                $normalized = strtoupper(trim($value));

                return [
                    'original' => $value,
                    'normalized' => $normalized,
                ];
            })
            ->filter(function (array $entry) {
                return $entry['normalized'] !== '';
            })
            ->values();

        $groupingRecords = $this->fetchGroupingRecordsForManualOverride($indexedRequested, $recordsByNormalized);

        if ($groupingRecords->isNotEmpty()) {
            foreach ($groupingRecords as $groupingRecord) {
                $normalizedKey = strtoupper(trim((string) ($groupingRecord['file_number'] ?? '')));
                if ($normalizedKey === '') {
                    continue;
                }

                if (!$recordsByNormalized->has($normalizedKey)) {
                    $recordsCollection->push($groupingRecord);
                    $recordsByNormalized->put($normalizedKey, $groupingRecord);
                }
            }
        }

        $orderedRecords = $indexedRequested
            ->map(function (array $entry) use ($recordsByNormalized) {
                return $recordsByNormalized->get($entry['normalized']);
            })
            ->filter()
            ->values()
            ->all();

        $missing = $indexedRequested
            ->filter(function (array $entry) use ($recordsByNormalized) {
                return !$recordsByNormalized->has($entry['normalized']);
            })
            ->map(function (array $entry) {
                return $entry['original'];
            })
            ->unique()
            ->values()
            ->all();

        Log::info('cadastral_printlabel.override.lookup', [
            'requested' => $fileNumbers->count(),
            'found' => count($orderedRecords),
            'missing' => count($missing),
            'grouping_found' => $groupingRecords->count(),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $orderedRecords,
            'meta' => [
                'requested_count' => $fileNumbers->count(),
                'found_count' => count($orderedRecords),
                'not_found' => $missing,
                'grouping_matches' => $groupingRecords->count(),
            ],
        ]);
    }

    /**
     * Preview grouping table records for batch mode
     */
    public function previewGroupingBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'registry_batch_no' => 'required|string|max:600',
            'start' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_BATCH_SELECTION,
            'per_batch_limit' => 'nullable|integer|min:1|max:' . self::MAX_BATCH_SELECTION,
            'exclude_assigned' => 'nullable|boolean',
            'registry' => 'nullable|in:1,2,3',
            'year' => 'nullable|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $registryBatchInput = trim((string) $validated['registry_batch_no']);
        $start = isset($validated['start']) ? (int) $validated['start'] : 1;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 30;
        $limit = max(1, min($limit, self::MAX_BATCH_SELECTION));
        $perBatchLimitInput = isset($validated['per_batch_limit']) ? (int) $validated['per_batch_limit'] : null;
        $perBatchLimit = $perBatchLimitInput !== null
            ? max(1, min($perBatchLimitInput, self::MAX_BATCH_SELECTION))
            : min($limit, self::RACK_SHELF_CAPACITY);
        $registry = isset($validated['registry']) ? (string) $validated['registry'] : null;
        $yearFilter = array_key_exists('year', $validated) ? trim((string) $validated['year']) : null;
        if ($yearFilter === '') {
            $yearFilter = null;
        }

        $registryBatchValues = collect(preg_split('/[\s,]+/', $registryBatchInput, -1, PREG_SPLIT_NO_EMPTY))
            ->map(static function (string $value) {
                return trim($value);
            })
            ->filter(static function (?string $value) {
                return $value !== null && $value !== '';
            })
            ->unique()
            ->values();

        if ($registryBatchValues->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one registry batch number.',
            ], 422);
        }
        if ($registryBatchValues->count() > 25) {
            return response()->json([
                'success' => false,
                'message' => 'Please limit the request to 25 registry batch numbers at a time.',
            ], 422);
        }

        $primaryRegistryBatch = $registryBatchValues->first();
        $multiBatchRequested = $registryBatchValues->count() > 1;

        $registryProgress = null;
        $autoStart = $start;

        if (!$multiBatchRequested) {
            $registryProgress = $this->getRegistryBatchProgress($primaryRegistryBatch, $registry);
            $printedTotal = (int) ($registryProgress['printed'] ?? 0);
            $autoStart = max(1, $printedTotal + 1);

            if ($start < $autoStart) {
                $start = $autoStart;
            }

        }

        $rangeEnd = $start + $perBatchLimit - 1;
        $totalRequested = $perBatchLimit * max(1, $registryBatchValues->count());

        static $cachedShelfRackColumn = null;
        if ($cachedShelfRackColumn === null) {
            try {
                $cachedShelfRackColumn = Schema::connection('sqlsrv')->hasColumn('grouping', 'shelf_rack');
            } catch (\Throwable $schemaException) {
                $cachedShelfRackColumn = false;
                Log::warning('Unable to determine grouping.shelf_rack column presence', [
                    'error' => $schemaException->getMessage(),
                ]);
            }
        }

        $groupingHasShelfRack = (bool) $cachedShelfRackColumn;
        $excludeAssigned = filter_var($request->get('exclude_assigned', false), FILTER_VALIDATE_BOOLEAN);
        $assignmentFilter = ($groupingHasShelfRack && $excludeAssigned)
            ? "        AND (shelf_rack IS NULL OR LTRIM(RTRIM(shelf_rack)) = '')\n"
            : '';
        $tableHint = $this->getGroupingTableHintClause();
        $registryBatchSignature = $registryBatchValues
            ->map(static function (string $value) {
                return Str::lower($value);
            })
            ->implode('|');

        $cacheKey = sprintf(
            'cadastral_printlabel:grouping-preview:%s:%d:%d:%d:%d:%s:%s',
            $registryBatchSignature,
            $start,
            $perBatchLimit,
            $totalRequested,
            $excludeAssigned ? 1 : 0,
            $registry ?? 'any',
            $yearFilter ?? 'any'
        );

        $previewTimer = microtime(true);
        $cacheHit = true;

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes(2), function () use (
                &$cacheHit,
                $registryBatchValues,
                $primaryRegistryBatch,
                $multiBatchRequested,
                $start,
                $rangeEnd,
                $perBatchLimit,
                $assignmentFilter,
                $groupingHasShelfRack,
                $tableHint,
                $registry,
                $yearFilter
            ) {
                $cacheHit = false;
                $localGroupingHasShelfRack = $groupingHasShelfRack;
                $localAssignmentFilter = $assignmentFilter;

                $columnList = $this->buildGroupingColumnList($localGroupingHasShelfRack);
                $selectColumns = implode(', ', $columnList);
                $partitionExpression = 'COALESCE(registry_batch_no, sys_batch_no)';

                $placeholders = implode(', ', array_fill(0, $registryBatchValues->count(), '?'));
                $bindings = $registryBatchValues->all();
                $whereClause = "WHERE registry_batch_no IN ({$placeholders})";

                if ($yearFilter !== null) {
                    $whereClause .= ' AND [year] = ?';
                    $bindings[] = $yearFilter;
                }

                if ($registry !== null) {
                    $whereClause .= ' AND registry = ?';
                    $bindings[] = $registry;
                }

                $sql = <<<SQL
WITH filtered AS (
    SELECT {$selectColumns},
           ROW_NUMBER() OVER (PARTITION BY {$partitionExpression} ORDER BY id ASC) AS registry_row
    FROM grouping {$tableHint}
    {$whereClause}
    {$localAssignmentFilter}
)
SELECT *
FROM filtered
WHERE registry_row BETWEEN ? AND ?
ORDER BY {$partitionExpression} ASC, registry_row ASC
SQL;

                $bindings[] = $start;
                $bindings[] = $rangeEnd;

                try {
                    $records = collect(DB::connection('sqlsrv')->select($sql, $bindings));
                } catch (QueryException $queryException) {
                    if ($localGroupingHasShelfRack && Str::contains($queryException->getMessage(), 'shelf_rack')) {
                        Log::warning('Retrying grouping preview without shelf_rack column', [
                            'registry_batch' => $registryBatchValues->all(),
                        ]);

                        $localGroupingHasShelfRack = false;
                        $localAssignmentFilter = '';
                        $columnList = $this->buildGroupingColumnList(false);
                        $selectColumns = implode(', ', $columnList);

                        $placeholders = implode(', ', array_fill(0, $registryBatchValues->count(), '?'));
                        $fallbackBindings = $registryBatchValues->all();
                        $fallbackWhere = "WHERE registry_batch_no IN ({$placeholders})";

                        if ($yearFilter !== null) {
                            $fallbackWhere .= ' AND [year] = ?';
                            $fallbackBindings[] = $yearFilter;
                        }

                        if ($registry !== null) {
                            $fallbackWhere .= ' AND registry = ?';
                            $fallbackBindings[] = $registry;
                        }

                        $sql = <<<SQL
WITH filtered AS (
    SELECT {$selectColumns},
           ROW_NUMBER() OVER (PARTITION BY {$partitionExpression} ORDER BY id ASC) AS registry_row
    FROM grouping {$tableHint}
    {$fallbackWhere}
)
SELECT *
FROM filtered
WHERE registry_row BETWEEN ? AND ?
ORDER BY {$partitionExpression} ASC, registry_row ASC
SQL;

                        $fallbackBindings[] = $start;
                        $fallbackBindings[] = $rangeEnd;

                        $records = collect(DB::connection('sqlsrv')->select($sql, $fallbackBindings));
                    } else {
                        throw $queryException;
                    }
                }

                $formatted = $records->map(function ($row) use ($localGroupingHasShelfRack) {
                    $primary = $row->mls_fileno
                        ?? $row->awaiting_fileno
                        ?? $row->tracking_id;

                    $displayTitle = $row->tracking_id
                        ?? $row->awaiting_fileno
                        ?? 'Pending tracking ID';

                    return [
                        'id' => (int) $row->id,
                        'file_number' => $primary ?? 'N/A',
                        'file_title' => $displayTitle,
                        'plot_number' => null,
                        'district' => null,
                        'lga' => null,
                        'batch_no' => $row->registry_batch_no ?? $row->sys_batch_no,
                        'registry_batch_no' => $row->registry_batch_no,
                        'registry' => $row->registry ?? null,
                        'sys_batch_no' => $row->sys_batch_no,
                        'land_use_type' => $row->landuse,
                        'tracking_id' => $row->tracking_id,
                        'cadastral_tracking_id' => $row->cadastral_tracking_id ?? $row->tracking_id,
                        'qr_value' => $row->cadastral_tracking_id ?? $row->tracking_id,
                        'awaiting_fileno' => $row->awaiting_fileno,
                        'indexing_mls_fileno' => $row->mls_fileno,
                        'shelf_label' => $localGroupingHasShelfRack ? ($row->shelf_rack ?? null) : null,
                        'shelf_location' => $localGroupingHasShelfRack ? ($row->shelf_rack ?? null) : null,
                        'shelf_full_label' => $localGroupingHasShelfRack ? ($row->shelf_rack ?? null) : null,
                    ];
                });

                $labelStatistics = !$multiBatchRequested
                    ? $this->buildShelfStatistics((string) $primaryRegistryBatch, $registry)
                    : null;
                $availableYearHints = [];
                $message = null;

                if ($formatted->isEmpty()) {
                    if ($yearFilter !== null) {
                        $availableYearHints = $this->buildGroupingYearHints($registryBatchValues, $registry);
                        $batchDescriptor = $multiBatchRequested
                            ? $registryBatchValues->implode(', ')
                            : (string) $primaryRegistryBatch;
                        $message = sprintf(
                            'No records found for year %s in registry batch%s %s.',
                            $yearFilter,
                            $multiBatchRequested ? 'es' : '',
                            $batchDescriptor
                        );

                        if (!empty($availableYearHints)) {
                            $message .= ' Suggested year(s) are listed for each batch.';
                        }
                    } else {
                        $message = 'No unassigned grouping rows were found for the selected batch.';
                    }
                }

                return [
                    'records' => $formatted,
                    'requested' => [
                        'registry_batch_no' => $multiBatchRequested
                            ? $registryBatchValues->implode(', ')
                            : (string) $primaryRegistryBatch,
                        'registry_batch_list' => $registryBatchValues->all(),
                        'registry' => $registry,
                        'year' => $yearFilter,
                        'start' => $start,
                        'per_batch_limit' => $perBatchLimit,
                        'total_requested' => $perBatchLimit * max(1, $registryBatchValues->count()),
                    ],
                    'label_statistics' => $labelStatistics,
                    'available_years' => $availableYearHints,
                    'message' => $message,
                ];
            });

            $durationMs = (int) round((microtime(true) - $previewTimer) * 1000);

            Log::info('cadastral_printlabel.grouping.preview', [
                'registry_batch_list' => $registryBatchValues->all(),
                'start' => $start,
                'per_batch_limit' => $perBatchLimit,
                'total_requested' => $totalRequested,
                'multi_batch' => $multiBatchRequested,
                'cache_hit' => $cacheHit,
                'printed_within_capacity' => $registryProgress['printed_within_capacity'] ?? null,
                'auto_start' => $autoStart,
                'remaining_within_capacity' => $registryProgress['remaining'] ?? null,
                'registry_year' => $yearFilter,
                'duration_ms' => $durationMs,
            ]);

            $payload['registry_progress'] = $registryProgress;

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to preview grouping batch records', [
                'registry_batch' => $registryBatchValues->all(),
                'start' => $start,
                'per_batch_limit' => $perBatchLimit,
                'total_requested' => $totalRequested,
                'multi_batch' => $multiBatchRequested,
                'registry_year' => $yearFilter,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to preview grouping batch records.',
                'data' => [
                    'registry_progress' => $registryProgress ?? null,
                ],
            ], 500);
        }
    }
    /**
     * Search registry batch numbers for Select2 dropdowns.
     */
    public function searchRegistryBatches(Request $request)
    {
        $perPage = (int) $request->get('per_page', 100);
        $perPage = max(10, min($perPage, 100));
        $page = max(1, (int) $request->get('page', 1));
        $term = trim((string) $request->get('q', ''));
        $registryFilter = $request->get('registry');
        $order = strtolower((string) $request->get('order', 'desc'));

        try {
            $primaryQuery = $this->buildRegistryBatchQuery('file_indexings', $registryFilter, $term, $order);
            $primaryRows = $primaryQuery
                ->take($perPage)
                ->get();

            $hasMore = false;

            $results = $this->mapRegistryBatchRows($primaryRows);

            if ($term !== '') {
                $groupingResults = $this->searchGroupingRegistryBatches($registryFilter, $term, $order, $perPage);

                $results = $results
                    ->concat($groupingResults)
                    ->unique('id')
                    ->values()
                    ->take($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to search registry batches', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to search registry batches at this time.',
            ], 500);
        }
    }

    protected function buildRegistryBatchQuery(string $table, $registryFilter, string $term, string $order)
    {
        $query = DB::connection('sqlsrv')
            ->table($table)
            ->select('registry_batch_no')
            ->whereNotNull('registry_batch_no');

        if ($registryFilter !== null && in_array((string) $registryFilter, ['1', '2', '3'], true)) {
            $query->where('registry', $registryFilter);
        }

        if ($term !== '') {
            $query->where('registry_batch_no', 'like', $term . '%');
        }

        $direction = $order === 'asc' ? 'ASC' : 'DESC';
        $query->distinct()->orderBy('registry_batch_no', $direction);

        return $query;
    }

    protected function searchGroupingRegistryBatches($registryFilter, string $term, string $order, int $limit)
    {
        $query = DB::connection('sqlsrv')
            ->table('grouping')
            ->select('registry_batch_no')
            ->whereNotNull('registry_batch_no')
            ->where('registry_batch_no', 'like', $term . '%');

        if ($registryFilter !== null && in_array((string) $registryFilter, ['1', '2', '3'], true)) {
            $query->where('registry', $registryFilter);
        }

        $direction = $order === 'asc' ? 'ASC' : 'DESC';

        $rows = $query
            ->distinct()
            ->orderBy('registry_batch_no', $direction)
            ->take($limit)
            ->get();

        return $this->mapRegistryBatchRows($rows);
    }

    protected function mapRegistryBatchRows($rows)
    {
        return $rows->map(function ($row) {
            $batchNo = trim((string) $row->registry_batch_no);

            return [
                'id' => $batchNo,
                'text' => $batchNo,
                'registry_batch_no' => $batchNo,
            ];
        });
    }

    /**
     * Fetch rack/shelf label status by full label.
     */
    public function getRackLabelStatus(Request $request)
    {
        $fullLabel = strtoupper(trim((string) $request->get('full_label', '')));
        $requestedRegistry = $this->normalizeRegistryId($request->get('registry'));

        if ($fullLabel === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a rack/shelf label.',
            ], 422);
        }

        $label = $this->resolveRackLabelRecord($fullLabel);

        if (!$label) {
            $label = $this->resolveRackLabelRecord($fullLabel, null, null, null, false, true);
        }

        if (!$label) {
            return response()->json([
                'success' => false,
                'message' => 'The selected rack/shelf label was not found.',
            ]);
        }

        $assignment = $this->parseRegistryAssignmentValue($label->assigned);
        $progress = null;

        if ($assignment['batch'] !== null) {
            $progress = $this->getRegistryBatchProgress(
                $assignment['batch'],
                $requestedRegistry ?? $assignment['registry']
            );
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRackLabelStatus($label, $progress, $requestedRegistry),
        ]);
    }

    /**
     * Create a new label batch
     */
    public function createBatch(Request $request)
    {
        $source = $request->input('source', 'file_indexings');
        $source = $source === 'grouping' ? 'grouping' : 'file_indexings';

        $baseRules = [
            'label_format' => 'required|in:standard,compact,qr_code,30-in-1',
            'orientation' => 'required|in:portrait,landscape',
            'batch_size' => 'nullable|integer|min:1|max:' . self::MAX_BATCH_SELECTION,
        ];

        if ($source === 'grouping') {
            $rules = array_merge($baseRules, [
                'registry_batch_no' => 'required|string|max:600',
                'registry_year' => 'nullable|string|max:4',
                'registry' => 'required|in:1,2,3',
                'rack_primary' => 'required|alpha|size:1',
                'rack_secondary' => 'nullable|alpha|size:1',
                'shelf' => 'required|integer|min:1|max:100',
                'full_label' => 'required|string|max:10',
                'range_key' => ['required', 'regex:/^\d+\-\d+$/'],
                'records' => 'required|array|min:1',
                'records.*.id' => 'required|integer',
                'records.*.file_number' => 'nullable|string|max:255',
                'records.*.file_title' => 'nullable|string|max:255',
                'records.*.tracking_id' => 'nullable|string|max:255',
                'records.*.cadastral_tracking_id' => 'nullable|string|max:255',
                'records.*.qr_value' => 'nullable|string|max:255',
                'records.*.awaiting_fileno' => 'nullable|string|max:255',
                'records.*.indexing_mls_fileno' => 'nullable|string|max:255',
                'records.*.land_use_type' => 'nullable|string|max:255',
                'records.*.plot_number' => 'nullable|string|max:255',
                'records.*.district' => 'nullable|string|max:255',
                'records.*.lga' => 'nullable|string|max:255',
                'records.*.shelf_label' => 'nullable|string|max:255',
                'manual_override' => 'nullable|boolean',
            ]);
        } else {
            $rules = array_merge($baseRules, [
                'file_ids' => 'required|array|min:1',
                'file_ids.*' => 'integer',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $manualOverride = !empty($validated['manual_override']);
        $batchSize = $validated['batch_size'] ?? ($source === 'grouping'
            ? count($validated['records'])
            : count($validated['file_ids']));
        $batchSize = max(1, min((int) $batchSize, self::MAX_BATCH_SELECTION));

        $controllerStart = microtime(true);
        Log::info('cadastral_printlabel.createBatch.requested', [
            'source' => $source,
            'batch_size' => $batchSize,
            'label_format' => $validated['label_format'],
            'orientation' => $validated['orientation'],
            'registry' => $validated['registry'] ?? null,
            'manual_override' => $manualOverride,
        ]);

        $groupingPreparation = null;
        if ($source === 'grouping') {
            $records = collect($validated['records'])->values();
            $cacheBuildStart = microtime(true);
            $indexingCaches = $this->buildFileIndexingCaches($records);

            Log::info('cadastral_printlabel.grouping.cache_ready', [
                'record_count' => $records->count(),
                'manual_override' => $manualOverride,
                'lookup_ids' => count($indexingCaches['by_id']),
                'lookup_files' => count($indexingCaches['by_file_number']),
                'lookup_tracking' => count($indexingCaches['by_tracking_id']),
                'duration_ms' => (int) round((microtime(true) - $cacheBuildStart) * 1000),
            ]);

            $groupingPreparation = [
                'records' => $records,
                'indexingCaches' => $indexingCaches,
            ];
        }

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use ($source, $validated, $batchSize, $groupingPreparation) {
                $batch = CadastralPrintLabelBatch::create([
                    'batch_number' => CadastralPrintLabelBatch::generateBatchNumber(),
                    'batch_size' => $batchSize,
                    'label_format' => $validated['label_format'],
                    'orientation' => $validated['orientation'],
                    'status' => CadastralPrintLabelBatch::STATUS_PENDING,
                    'created_by' => auth()->id(),
                ]);

                $itemsCreated = 0;
                $context = [];

                if ($source === 'grouping') {
                    if ($groupingPreparation === null) {
                        throw new \RuntimeException('Grouping data was not prepared.');
                    }

                    $groupingWorkStart = microtime(true);
                    $records = $groupingPreparation['records'];

                    // Resolve grouping.cadastral_tracking_id for every selected row so the QR
                    // encodes the cadastral tracking id (tracking_id + -0001, -0002, ...).
                    $cadastralTrackingMap = [];
                    try {
                        $groupingIdsForTracking = $records->pluck('id')
                            ->filter()
                            ->map(function ($v) { return (int) $v; })
                            ->unique()
                            ->values()
                            ->all();

                        if (!empty($groupingIdsForTracking)) {
                            $cadastralTrackingMap = DB::connection('sqlsrv')
                                ->table('grouping')
                                ->whereIn('id', $groupingIdsForTracking)
                                ->pluck('cadastral_tracking_id', 'id')
                                ->toArray();
                        }
                    } catch (\Throwable $cadastralTrackingException) {
                        Log::warning('cadastral_printlabel.cadastral_tracking_lookup_failed', [
                            'error' => $cadastralTrackingException->getMessage(),
                        ]);
                    }

                    $resolveCadastralTracking = function (array $record) use ($cadastralTrackingMap) {
                        $gid = isset($record['id']) ? (int) $record['id'] : null;
                        $fromDb = ($gid !== null && !empty($cadastralTrackingMap[$gid]))
                            ? $cadastralTrackingMap[$gid]
                            : null;

                        return $fromDb
                            ?? ($record['cadastral_tracking_id'] ?? null)
                            ?? ($record['qr_value'] ?? null)
                            ?? ($record['tracking_id'] ?? null);
                    };

                    $manualOverride = !empty($validated['manual_override']);
                    $now = now();
                    $resolvedItems = [];
                    $indexedIds = [];
                    $missingReferences = [];
                    $duplicateReferences = [];
                    $indexingCaches = $groupingPreparation['indexingCaches'];
                    $registryBatchNo = trim((string) $validated['registry_batch_no']);
                    $registryProgressBefore = $this->getRegistryBatchProgress($registryBatchNo, $validated['registry'] ?? null);

                    static $fileIndexingColumnCache = null;
                    if ($fileIndexingColumnCache === null) {
                        try {
                            $schema = Schema::connection('sqlsrv');
                            $fileIndexingColumnCache = [
                                'shelf_label_id' => $schema->hasColumn('file_indexings', 'shelf_label_id'),
                                'assigned_by' => $schema->hasColumn('file_indexings', 'assigned_by'),
                                'assigned_at' => $schema->hasColumn('file_indexings', 'assigned_at'),
                                'is_updated' => $schema->hasColumn('file_indexings', 'is_updated'),
                            ];
                        } catch (\Throwable $schemaException) {
                            Log::warning('Unable to inspect file_indexings columns for rack/shelf updates', [
                                'error' => $schemaException->getMessage(),
                            ]);
                            $fileIndexingColumnCache = [
                                'shelf_label_id' => false,
                                'assigned_by' => false,
                                'assigned_at' => false,
                                'is_updated' => false,
                            ];
                        }
                    }

                    [$rangeStart, $rangeEnd, $rangeCount] = $this->parseRangeSelection($validated['range_key']);
                    $printedTotalBefore = (int) ($registryProgressBefore['printed'] ?? 0);

                    if ($rangeEnd <= $printedTotalBefore) {
                        throw ValidationException::withMessages([
                            'range_key' => 'The selected range has already been processed for this registry batch.',
                        ]);
                    }

                    $rackPrimary = strtoupper(trim($validated['rack_primary']));
                    $rackSecondary = isset($validated['rack_secondary']) && $validated['rack_secondary'] !== ''
                        ? strtoupper(trim($validated['rack_secondary']))
                        : null;
                    $shelfNumber = (int) $validated['shelf'];
                    $computedFullLabel = $this->buildFullLabelFromParts($rackPrimary, $rackSecondary, $shelfNumber);
                    $requestedFullLabel = strtoupper(trim((string) ($validated['full_label'] ?? '')));
                    $fullLabel = $requestedFullLabel !== '' ? $requestedFullLabel : $computedFullLabel;

                    if (strcasecmp($fullLabel, $computedFullLabel) !== 0) {
                        $fullLabel = $computedFullLabel;
                    }

                    $recordsToAssign = $records
                        ->take(min($records->count(), $batchSize, self::MAX_BATCH_SELECTION))
                        ->values();

                    if ($recordsToAssign->isEmpty()) {
                        throw new \RuntimeException('Unable to build label entries for the selected grouping records.');
                    }

                    $pendingRecords = $recordsToAssign->values();
                    $placeholderIndex = -1;
                    $fileIndexingIdsToUpdate = [];
                    $lastRackLabel = null;
                    $shelfOffset = 0;
                    $capacityOverrideApplied = false;

                    while ($pendingRecords->isNotEmpty()) {
                        $activeShelfNumber = $shelfNumber + $shelfOffset;
                        $activeFullLabel = $this->buildFullLabelFromParts($rackPrimary, $rackSecondary, $activeShelfNumber);

                        $claim = $this->claimRackLabelForRange(
                            $activeFullLabel,
                            $registryBatchNo,
                            $validated['registry'] ?? null,
                            $pendingRecords->count(),
                            $rackPrimary,
                            $rackSecondary,
                            $activeShelfNumber,
                            true,
                            $manualOverride
                        );

                        $assignable = (int) ($claim['assignable'] ?? 0);
                        $label = $claim['label'];
                        if (!empty($claim['capacity_overridden'])) {
                            $capacityOverrideApplied = true;
                        }

                        if ($assignable <= 0) {
                            // Shelf is full for this registry; advance to the next shelf automatically.
                            $shelfOffset++;
                            continue;
                        }

                        $lastRackLabel = $label;
                        $labelDisplay = $label->full_label ?? $activeFullLabel;
                        if ($manualOverride) {
                            $labelDisplay = $activeFullLabel;
                        }
                        $chunkRecords = $pendingRecords->splice(0, $assignable);

                        foreach ($chunkRecords as $record) {
                            $indexing = $this->resolveFileIndexingForGroupingRecord($record, $indexingCaches, false);

                            if ($indexing && in_array($indexing->id, $indexedIds, true)) {
                                $duplicateReferences[] = $indexing->file_number
                                    ?? $indexing->tracking_id
                                    ?? (string) $indexing->id;
                                continue;
                            }

                            if ($indexing) {
                                $indexedIds[] = $indexing->id;
                                $fileIndexingIdsToUpdate[] = $indexing->id;

                                $fileNumber = $record['file_number']
                                    ?? $record['indexing_mls_fileno']
                                    ?? $record['awaiting_fileno']
                                    ?? $indexing->file_number
                                    ?? $indexing->tracking_id;

                                $title = $record['file_title']
                                    ?? $record['tracking_id']
                                    ?? $record['awaiting_fileno']
                                    ?? $fileNumber
                                    ?? 'Grouping record';

                                $resolvedItems[] = [
                                    'batch_id' => $batch->id,
                                    'file_indexing_id' => $indexing->id,
                                    'file_number' => $fileNumber,
                                    'file_title' => $title,
                                    'plot_number' => $record['plot_number'] ?? $indexing->plot_number,
                                    'district' => $record['district'] ?? $indexing->district,
                                    'lga' => $record['lga'] ?? $indexing->lga,
                                    'land_use_type' => $record['land_use_type'] ?? $indexing->land_use_type,
                                    'shelf_location' => $labelDisplay,
                                    'label_position' => count($resolvedItems) + 1,
                                    'qr_code_data' => json_encode([
                                        'file_number' => $fileNumber,
                                        'tracking_id' => $record['tracking_id'] ?? $indexing->tracking_id,
                                        'cadastral_tracking_id' => $resolveCadastralTracking($record) ?? $record['tracking_id'] ?? $indexing->tracking_id,
                                        'awaiting_fileno' => $record['awaiting_fileno'] ?? null,
                                        'indexing_mls_fileno' => $record['indexing_mls_fileno'] ?? null,
                                        'land_use_type' => $record['land_use_type'] ?? $indexing->land_use_type,
                                        'shelf_location' => $labelDisplay,
                                        'generated_at' => $now->toIso8601String(),
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ];
                            } else {
                                $missingReferences[] = $this->describeGroupingRecord($record);

                                $fileNumber = $record['file_number']
                                    ?? $record['awaiting_fileno']
                                    ?? $record['indexing_mls_fileno']
                                    ?? $record['tracking_id']
                                    ?? 'UNASSIGNED';

                                $title = $record['file_title']
                                    ?? $record['tracking_id']
                                    ?? $fileNumber;

                                $resolvedItems[] = [
                                    'batch_id' => $batch->id,
                                    'file_indexing_id' => $placeholderIndex,
                                    'file_number' => $fileNumber,
                                    'file_title' => $title,
                                    'plot_number' => $record['plot_number'] ?? null,
                                    'district' => $record['district'] ?? null,
                                    'lga' => $record['lga'] ?? null,
                                    'land_use_type' => $record['land_use_type'] ?? null,
                                    'shelf_location' => $labelDisplay,
                                    'label_position' => count($resolvedItems) + 1,
                                    'qr_code_data' => json_encode([
                                        'file_number' => $fileNumber,
                                        'tracking_id' => $record['tracking_id'] ?? null,
                                        'cadastral_tracking_id' => $resolveCadastralTracking($record),
                                        'awaiting_fileno' => $record['awaiting_fileno'] ?? null,
                                        'indexing_mls_fileno' => $record['indexing_mls_fileno'] ?? null,
                                        'shelf_location' => $labelDisplay,
                                        'generated_at' => $now->toIso8601String(),
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ];

                                $placeholderIndex -= 1;
                            }
                        }

                        if (!$manualOverride) {
                            $this->finalizeRackLabelUsage(
                                $label,
                                $registryBatchNo,
                                $validated['registry'] ?? null,
                                $assignable,
                                $now
                            );
                        }

                        $shelfOffset++;
                    }

                    if ($pendingRecords->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'range_key' => 'Unable to allocate shelf space for all requested labels. Please review the rack selection.',
                        ]);
                    }

                    $fileIndexingIdsToUpdate = array_values(array_unique($fileIndexingIdsToUpdate));
                    
                    Log::info('cadastral_printlabel.grouping.before_fileindexing_update', [
                        'batch_id' => $batch->id,
                        'ids_to_update' => count($fileIndexingIdsToUpdate),
                    ]);
                    
                    // SKIP file_indexings update to avoid hanging - only create batch items
                    // The shelf_location will be stored in the batch_items table
                    if (!empty($fileIndexingIdsToUpdate)) {
                        Log::info('cadastral_printlabel.grouping.skipping_fileindexing_update', [
                            'reason' => 'Preventing table lock and timeout issues',
                            'ids_count' => count($fileIndexingIdsToUpdate),
                        ]);
                        
                        // Optional: Update in background job later if needed
                        // For now, shelf_location is in cadastral_print_label_batch_items table
                    }

                    if (empty($resolvedItems)) {
                        throw new \RuntimeException('Unable to build label entries for the selected grouping records.');
                    }

                    Log::info('cadastral_printlabel.grouping.before_batch_insert', [
                        'batch_id' => $batch->id,
                        'items_count' => count($resolvedItems),
                    ]);

                    if (!empty($missingReferences)) {
                        Log::warning('Grouping records without matching indexed files encountered during label batch creation', [
                            'batch_id' => $batch->id,
                            'references' => $missingReferences,
                        ]);
                    }

                    if (!empty($duplicateReferences)) {
                        Log::warning('Duplicate grouping references detected while creating label batch', [
                            'batch_id' => $batch->id,
                            'duplicates' => $duplicateReferences,
                        ]);
                    }

                    $preparedItems = $this->prepareBatchItemsForInsert($resolvedItems, $now);
                    $this->insertBatchItemsInChunks($preparedItems);

                    // Dispatch job to sync shelf_location to file_indexings table
                    SyncCadastralShelfLocationToFileIndexings::dispatch($batch->id);

                    // Flag the processed grouping rows as having a cadastral file generated,
                    // recording a short text context describing how/when it was produced.
                    $groupingIdsToFlag = $recordsToAssign
                        ->pluck('id')
                        ->filter()
                        ->map(function ($id) {
                            return (int) $id;
                        })
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($groupingIdsToFlag)) {
                        try {
                            $cadastralContext = sprintf(
                                'Cadastral file generated via batch %s (registry batch %s, shelf %s) on %s',
                                $batch->batch_number,
                                $registryBatchNo,
                                $fullLabel,
                                $now->toDateTimeString()
                            );

                            DB::connection('sqlsrv')
                                ->table('grouping')
                                ->whereIn('id', $groupingIdsToFlag)
                                ->update([
                                    'cadastral_file_generated' => 1,
                                    'cadastral_context' => $cadastralContext,
                                ]);
                        } catch (\Throwable $flagException) {
                            Log::warning('cadastral_printlabel.grouping.flag_failed', [
                                'batch_id' => $batch->id,
                                'ids' => count($groupingIdsToFlag),
                                'error' => $flagException->getMessage(),
                            ]);
                        }
                    }

                    $itemsCreated = count($resolvedItems);
                    $registryProgressAfter = $this->getRegistryBatchProgress($registryBatchNo, $validated['registry'] ?? null);

                    $batch->update([
                        'status' => CadastralPrintLabelBatch::STATUS_GENERATED,
                        'generated_count' => $itemsCreated,
                        'updated_by' => auth()->id(),
                    ]);

                    $context = [
                        'source' => 'grouping',
                        'registry_batch_no' => $registryBatchNo,
                        'registry' => $validated['registry'],
                        'full_label' => $fullLabel,
                        'rack_primary' => $rackPrimary,
                        'rack_secondary' => $rackSecondary,
                        'shelf' => $shelfNumber,
                        'range_key' => $validated['range_key'],
                        'range_start' => $rangeStart,
                        'range_end' => $rangeEnd,
                        'registry_progress' => $registryProgressAfter,
                        'manual_override' => $manualOverride,
                        'rack_capacity_override' => $capacityOverrideApplied,
                    ];

                    $warnings = [];

                    if (!empty($missingReferences)) {
                        $warnings['missing_references'] = $missingReferences;
                    }

                    if (!empty($duplicateReferences)) {
                        $warnings['duplicate_references'] = $duplicateReferences;
                    }

                    if (!empty($warnings)) {
                        $context['warnings'] = $warnings;
                    }

                    $context['rack_label_status'] = $lastRackLabel
                        ? $this->formatRackLabelStatus(
                            $lastRackLabel,
                            $registryProgressAfter,
                            $validated['registry'] ?? null
                        )
                        : null;

                    Log::info('cadastral_printlabel.grouping.batch_items_inserted', [
                        'batch_id' => $batch->id,
                        'items_inserted' => $itemsCreated,
                        'manual_override' => $manualOverride,
                        'rack_capacity_override' => $capacityOverrideApplied,
                        'duration_ms' => (int) round((microtime(true) - $groupingWorkStart) * 1000),
                    ]);

                    Log::info('cadastral_printlabel.grouping.processed', [
                        'batch_id' => $batch->id,
                        'registry_batch_no' => $registryBatchNo,
                        'records_processed' => $recordsToAssign->count(),
                        'items_created' => $itemsCreated,
                        'manual_override' => $manualOverride,
                        'rack_capacity_override' => $capacityOverrideApplied,
                        'duration_ms' => (int) round((microtime(true) - $groupingWorkStart) * 1000),
                    ]);
                } else {
                    $files = FileIndexing::on('sqlsrv')->whereIn('id', $validated['file_ids'])->get();

                    if ($files->count() !== count($validated['file_ids'])) {
                        throw new \RuntimeException('Some selected files were not found.');
                    }

                    $now = now();
                    $resolvedItems = $files->values()->map(function ($file, $index) use ($batch, $now) {
                        $qrPayload = [
                            'file_number' => $file->file_number,
                            'tracking_id' => $file->tracking_id ?? null,
                            'plot_number' => $file->plot_number ?? null,
                            'district' => $file->district ?? null,
                            'lga' => $file->lga ?? null,
                            'land_use_type' => $file->land_use_type ?? null,
                            'shelf_location' => $file->shelf_location ?? null,
                            'generated_at' => $now->toIso8601String(),
                        ];

                        return [
                            'batch_id' => $batch->id,
                            'file_indexing_id' => $file->id,
                            'file_number' => $file->file_number,
                            'file_title' => $file->file_title,
                            'plot_number' => $file->plot_number,
                            'district' => $file->district,
                            'lga' => $file->lga,
                            'land_use_type' => $file->land_use_type,
                            'shelf_location' => $file->shelf_location,
                            'label_position' => $index + 1,
                            'qr_code_data' => $qrPayload,
                            'barcode_data' => $file->file_number,
                        ];
                    })->all();

                    $preparedItems = $this->prepareBatchItemsForInsert($resolvedItems, $now);
                    $this->insertBatchItemsInChunks($preparedItems);

                    // Dispatch job to sync shelf_location to file_indexings table
                    SyncCadastralShelfLocationToFileIndexings::dispatch($batch->id);

                    $itemsCreated = $files->count();

                    Log::info('cadastral_printlabel.fileindexings.batch_items_inserted', [
                        'batch_id' => $batch->id,
                        'items_inserted' => $itemsCreated,
                    ]);
                    $batch->update([
                        'status' => CadastralPrintLabelBatch::STATUS_GENERATED,
                        'generated_count' => $itemsCreated,
                        'updated_by' => auth()->id(),
                    ]);

                    $context = [
                        'source' => 'file_indexings',
                    ];
                }

                return [
                    'batch' => $batch->fresh(['batchItems.fileIndexing']),
                    'items_created' => $itemsCreated,
                    'context' => $context,
                ];
            });

            Log::info('Label batch created successfully', [
                'batch_id' => $result['batch']->id,
                'batch_number' => $result['batch']->batch_number,
                'items_created' => $result['items_created'],
                'user_id' => auth()->id(),
            ]);

            Log::info('cadastral_printlabel.createBatch.completed', [
                'batch_id' => $result['batch']->id,
                'batch_number' => $result['batch']->batch_number,
                'source' => $source,
                'items_created' => $result['items_created'],
                'duration_ms' => (int) round((microtime(true) - $controllerStart) * 1000),
            ]);

            $labelStatistics = null;
            if (($result['context']['source'] ?? null) === 'grouping') {
                $labelStatistics = $this->buildShelfStatistics(
                    $result['context']['registry_batch_no'] ?? null,
                    $result['context']['registry'] ?? null
                );
            }

            $batch = $result['batch'];
            $batchItems = $batch->batchItems ?? collect();

            $labelItems = $batchItems->map(function (CadastralPrintLabelBatchItem $item) {
                $qrData = [];

                if (!empty($item->qr_code_data)) {
                    $decoded = json_decode($item->qr_code_data, true);
                    if (is_array($decoded)) {
                        $qrData = $decoded;
                    }
                }

                $fileIndexing = $item->fileIndexing;
                $trackingId = $qrData['tracking_id']
                    ?? optional($fileIndexing)->tracking_id
                    ?? null;

                return [
                    'batch_item_id' => $item->id,
                    'file_indexing_id' => $item->file_indexing_id,
                    'file_number' => $item->file_number,
                    'file_title' => $item->file_title,
                    'plot_number' => $item->plot_number,
                    'district' => $item->district,
                    'lga' => $item->lga,
                    'land_use_type' => $item->land_use_type,
                    'shelf_location' => $item->shelf_location,
                    'shelf_value' => $item->shelf_location,
                    'shelf_label' => $item->shelf_location,
                    'tracking_id' => $trackingId,
                    'awaiting_fileno' => $qrData['awaiting_fileno'] ?? null,
                    'indexing_mls_fileno' => $qrData['indexing_mls_fileno'] ?? null,
                    'qr_code_data' => $qrData,
                    'qr_value' => $qrData['cadastral_tracking_id'] ?? $qrData['tracking_id'] ?? $item->file_number,
                    'label_position' => $item->label_position,
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Label batch created successfully',
                'data' => [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'file_count' => $result['items_created'],
                    'source' => $result['context']['source'] ?? null,
                    'registry_batch_no' => $result['context']['registry_batch_no'] ?? null,
                    'registry_progress' => $result['context']['registry_progress'] ?? null,
                    'rack_label_status' => $result['context']['rack_label_status'] ?? null,
                    'warnings' => $result['context']['warnings'] ?? [],
                    'label_items' => $labelItems,
                    'label_statistics' => $labelStatistics,
                ],
            ]);

        } catch (ValidationException $validationException) {
            return response()->json([
                'success' => false,
                'message' => $validationException->getMessage(),
                'errors' => $validationException->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error creating label batch', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating batch: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normalize rack/shelf parts into a full label string.
     */
    private function buildFullLabelFromParts(string $rackPrimary, ?string $rackSecondary, int $shelf): string
    {
        $rackPrimary = strtoupper(trim($rackPrimary));
        $secondary = $rackSecondary !== null ? strtoupper(trim($rackSecondary)) : '';
        $shelfPart = strtoupper(trim((string) $shelf));

        return trim($rackPrimary . $secondary . $shelfPart);
    }

    /**
     * Parse a configured range key (e.g. "1-30") into numeric bounds.
     *
     * @return array{0:int,1:int,2:int}
     *
     * @throws ValidationException
     */
    private function parseRangeSelection(string $rangeKey): array
    {
        $parts = array_map('trim', explode('-', $rangeKey));

        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'range_key' => 'Invalid range selection provided.',
            ]);
        }

        $start = (int) $parts[0];
        $end = (int) $parts[1];

        $rangeCount = ($end - $start) + 1;

        if ($start < 1 || $start > $end || $rangeCount > self::MAX_BATCH_SELECTION) {
            throw ValidationException::withMessages([
                'range_key' => sprintf(
                    'Range selection must be sequential, start at 1 or higher, and include no more than %d positions.',
                    self::MAX_BATCH_SELECTION
                ),
            ]);
        }

        return [$start, $end, $rangeCount];
    }

    private function normalizeRegistryId(?string $registryId): ?string
    {
        if ($registryId === null) {
            return null;
        }

        $trimmed = trim((string) $registryId);

        return $trimmed === '' ? null : $trimmed;
    }

    private function formatRegistryAssignmentValue(string $registryBatchNo, ?string $registryId): string
    {
        $normalizedBatch = trim($registryBatchNo);
        $normalizedRegistry = $this->normalizeRegistryId($registryId);

        if ($normalizedRegistry === null) {
            return $normalizedBatch;
        }

        return $normalizedRegistry . '|' . $normalizedBatch;
    }

    private function parseRegistryAssignmentValue(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return ['registry' => null, 'batch' => null];
        }

        if (strpos($trimmed, '|') !== false) {
            [$registryPart, $batchPart] = array_pad(explode('|', $trimmed, 2), 2, null);
            $registryPart = $this->normalizeRegistryId($registryPart);
            $batchPart = $batchPart !== null ? trim($batchPart) : null;

            if ($batchPart === '') {
                $batchPart = null;
            }

            return ['registry' => $registryPart, 'batch' => $batchPart];
        }

        return ['registry' => null, 'batch' => $trimmed];
    }

    private function buildGroupingYearHints(Collection $registryBatchValues, ?string $registry = null, int $perBatchLimit = 10): array
    {
        if ($registryBatchValues->isEmpty()) {
            return [];
        }

        try {
            $query = DB::connection('sqlsrv')
                ->table('grouping')
                ->select(
                    'registry_batch_no',
                    DB::raw('RTRIM(LTRIM([year])) as normalized_year')
                )
                ->whereIn('registry_batch_no', $registryBatchValues->all())
                ->whereNotNull('year');

            if ($registry !== null) {
                $query->where('registry', $registry);
            }

            $rows = $query
                ->orderBy('registry_batch_no')
                ->orderBy('year')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Unable to lookup grouping year hints', [
                'registry_batch' => $registryBatchValues->all(),
                'registry' => $registry,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $hints = [];

        foreach ($rows as $row) {
            $batch = trim((string) ($row->registry_batch_no ?? ''));
            $year = trim((string) ($row->normalized_year ?? $row->year ?? ''));

            if ($batch === '' || $year === '') {
                continue;
            }

            if (!array_key_exists($batch, $hints)) {
                $hints[$batch] = [];
            }

            if (!in_array($year, $hints[$batch], true)) {
                if (count($hints[$batch]) >= $perBatchLimit) {
                    continue;
                }
                $hints[$batch][] = $year;
            }
        }

        return $hints;
    }

    private function retrieveAssignedRackLabels(string $registryBatchNo, ?string $registryId = null): Collection
    {
        $normalizedBatch = trim($registryBatchNo);
        if ($normalizedBatch === '') {
            return collect();
        }

        $normalizedRegistry = $this->normalizeRegistryId($registryId);

        if ($normalizedRegistry !== null) {
            $targetAssignment = $this->formatRegistryAssignmentValue($normalizedBatch, $normalizedRegistry);

            $labels = CadastralRackShelfLabel::where('assigned', $targetAssignment)
                ->orderBy('id')
                ->get();

            if ($labels->isNotEmpty()) {
                return $labels;
            }

            $legacyLabels = CadastralRackShelfLabel::where('assigned', $normalizedBatch)
                ->orderBy('id')
                ->get();

            if ($legacyLabels->isEmpty()) {
                return $legacyLabels;
            }

            foreach ($legacyLabels as $legacyLabel) {
                $legacyLabel->assigned = $targetAssignment;
                $legacyLabel->save();
                $legacyLabel->refresh();
            }

            return $legacyLabels;
        }

        return CadastralRackShelfLabel::where(function ($builder) use ($normalizedBatch) {
                $builder->where('assigned', $normalizedBatch)
                    ->orWhere(function ($inner) use ($normalizedBatch) {
                        $inner->whereNotNull('assigned')
                            ->whereRaw("CHARINDEX('|', assigned) > 0")
                            ->whereRaw("RIGHT(assigned, LEN(assigned) - CHARINDEX('|', assigned)) = ?", [$normalizedBatch]);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Lock and validate a rack label for the requested assignment count.
     *
     * @throws ValidationException
     */
    private function claimRackLabelForRange(
        string $fullLabel,
        string $registryBatchNo,
        ?string $registryId,
        int $requestedCount,
        string $rackPrimary = '',
        ?string $rackSecondary = null,
        int $shelfNumber = 1,
        bool $allowPartial = false,
        bool $allowCapacityOverride = false
    ): array
    {
        $lockForUpdate = !$allowCapacityOverride;
        $label = $this->resolveRackLabelRecord(
            $fullLabel,
            $rackPrimary,
            $rackSecondary,
            $shelfNumber,
            $lockForUpdate,
            true
        );

        if (!$label) {
            $normalized = strtoupper(trim($fullLabel));
            throw ValidationException::withMessages([
                'full_label' => "Rack/Shelf label {$normalized} does not exist. Please select a valid label.",
            ]);
        }

        $normalizedRegistry = $this->normalizeRegistryId($registryId);
        $assignment = $this->parseRegistryAssignmentValue($label->assigned);

        if (!$allowCapacityOverride && $assignment['registry'] === null && $normalizedRegistry !== null && $assignment['batch'] !== null && (int) ($label->counter ?? 0) > 0) {
            $label->assigned = $this->formatRegistryAssignmentValue($assignment['batch'], $normalizedRegistry);
            $label->save();
            $label->refresh();
            $assignment = $this->parseRegistryAssignmentValue($label->assigned);
        }

        $currentCounter = (int) ($label->counter ?? 0);
        $hasRegistryAssignment = $assignment['registry'] !== null && $normalizedRegistry !== null;
        $registriesMatch = $hasRegistryAssignment && strcasecmp($assignment['registry'], $normalizedRegistry) === 0;
        $registriesConflict = $hasRegistryAssignment && !$registriesMatch;

        // Freestyle: no capacity limit — always assign the full requested count
        return [
            'label'              => $label,
            'assignable'         => $requestedCount,
            'remaining'          => PHP_INT_MAX,
            'capacity_overridden' => false,
        ];
    }

    /**
     * Persist rack label usage after assignment.
     */
    private function finalizeRackLabelUsage(
        CadastralRackShelfLabel $label,
        string $registryBatchNo,
        ?string $registryId,
        int $increment,
        \DateTimeInterface $timestamp
    ): CadastralRackShelfLabel
    {
        if ($increment <= 0) {
            return $label;
        }

        $normalizedBatch = trim($registryBatchNo);
        $normalizedRegistry = $this->normalizeRegistryId($registryId);
        $existingAssignment = $this->parseRegistryAssignmentValue($label->assigned);
        $existingRegistry = $existingAssignment['registry'];

        if (
            $existingRegistry !== null
            && $normalizedRegistry !== null
            && strcasecmp($existingRegistry, $normalizedRegistry) !== 0
        ) {
            $label->counter = 0;
        }

        $label->counter = (int) ($label->counter ?? 0) + $increment;
        $label->assigned = $this->formatRegistryAssignmentValue($normalizedBatch, $normalizedRegistry);
        $label->is_used = true;
        $label->reserved_by = auth()->id();
        $label->reserved_at = $timestamp instanceof \DateTimeInterface ? Carbon::make($timestamp) : $timestamp;
        $label->save();

        return $label->refresh();
    }

    protected function prepareBatchItemsForInsert(array $items, Carbon $timestamp): array
    {
        return array_map(function ($item) use ($timestamp) {
            if (isset($item['qr_code_data']) && is_array($item['qr_code_data'])) {
                $item['qr_code_data'] = json_encode($item['qr_code_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (!array_key_exists('barcode_data', $item) || $item['barcode_data'] === null) {
                $item['barcode_data'] = $item['file_number'] ?? null;
            }

            $item['created_at'] = $item['created_at'] ?? $timestamp;
            $item['updated_at'] = $item['updated_at'] ?? $timestamp;
            $item['is_printed'] = $item['is_printed'] ?? false;

            return $item;
        }, array_values($items));
    }

    /**
     * Insert batch items while respecting SQL Server's 2100-parameter limit.
     */
    private function insertBatchItemsInChunks(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $sample = $items[0];
        $columnCount = is_array($sample) ? max(1, count($sample)) : 1;

        // Keep some buffer under 2100 parameters so we don't bump into the limit.
        $maxPerInsert = max(1, (int) floor(2000 / $columnCount));

        foreach (array_chunk($items, $maxPerInsert) as $chunk) {
            CadastralPrintLabelBatchItem::insert($chunk);
        }
    }

    private function resolveRackLabelRecord(
        string $fullLabel,
        ?string $rackPrimary = null,
        ?string $rackSecondary = null,
        ?int $shelfNumber = null,
        bool $lockForUpdate = false,
        bool $createIfMissing = false
    ): ?CadastralRackShelfLabel {
        $normalized = strtoupper(trim($fullLabel));

        $byFullLabel = CadastralRackShelfLabel::query();
        if ($lockForUpdate) {
            $byFullLabel->lockForUpdate();
        }

        $label = $byFullLabel->whereRaw('UPPER(LTRIM(RTRIM(full_label))) = ?', [$normalized])->first();
        if ($label) {
            return $label;
        }

        if (($rackPrimary === null || $shelfNumber === null) && preg_match('/^([A-Z]{1,2})(\d{1,3})$/', $normalized, $matches)) {
            $rackGroup = $matches[1];
            $rackPrimary = substr($rackGroup, 0, 1);
            $rackSecondary = strlen($rackGroup) > 1 ? substr($rackGroup, 1) : null;
            $shelfNumber = (int) $matches[2];
        }

        if ($rackPrimary === null || $shelfNumber === null) {
            return null;
        }

        $rackCandidates = array_values(array_filter(array_unique([
            strtoupper(trim($rackPrimary . ($rackSecondary ?? ''))),
            strtoupper(trim($rackPrimary)),
        ])));

        if (empty($rackCandidates)) {
            return null;
        }

        $fallbackQuery = CadastralRackShelfLabel::query();
        if ($lockForUpdate) {
            $fallbackQuery->lockForUpdate();
        }

        $fallbackQuery->where(function ($builder) use ($rackCandidates) {
            foreach ($rackCandidates as $index => $candidate) {
                if ($candidate === '') {
                    continue;
                }

                if ($index === 0) {
                    $builder->whereRaw('UPPER(LTRIM(RTRIM(rack))) = ?', [$candidate]);
                } else {
                    $builder->orWhereRaw('UPPER(LTRIM(RTRIM(rack))) = ?', [$candidate]);
                }
            }
        });

        $fallbackQuery->where(function ($builder) use ($shelfNumber) {
            $builder->where('shelf', $shelfNumber)
                ->orWhereRaw('CAST(shelf AS NVARCHAR(10)) = ?', [$shelfNumber]);
        });

        $label = $fallbackQuery->first();

        if ($label) {
            if (empty($label->full_label)) {
                $label->full_label = $normalized;
                $label->save();
            }

            return $label;
        }

        if ($createIfMissing && $rackPrimary !== null && $shelfNumber !== null) {
            $payload = [
                'rack' => strtoupper(trim($rackPrimary . ($rackSecondary ?? ''))),
                'shelf' => (string) $shelfNumber,
                'full_label' => $normalized,
                'is_used' => false,
                'counter' => 0,
            ];

            if ($payload['rack'] === '') {
                $payload['rack'] = strtoupper(trim($rackPrimary));
            }

            return CadastralRackShelfLabel::create($payload);
        }

        return null;
    }

    private function formatRackLabelStatus(
        CadastralRackShelfLabel $label,
        ?array $registryProgress = null,
        ?string $expectedRegistry = null
    ): array
    {
        $actualUsage = max(0, (int) ($label->counter ?? 0));
        $assignment = $this->parseRegistryAssignmentValue($label->assigned);
        $assignedBatch = $assignment['batch'];
        $assignedRegistry = $assignment['registry'];
        $normalizedExpectedRegistry = $this->normalizeRegistryId($expectedRegistry);
        $registryMismatch =
            $normalizedExpectedRegistry !== null
            && $assignedRegistry !== null
            && strcasecmp($assignedRegistry, $normalizedExpectedRegistry) !== 0;
        $displayUsage = $registryMismatch ? 0 : $actualUsage;
        $nextLabelStart = $displayUsage + 1;
        $nextLabelRangeKey = $this->determineRangeKeyForPosition($nextLabelStart);

        if ($registryProgress === null && $assignedBatch !== null && !$registryMismatch) {
            $progressRegistry = $assignedRegistry ?? $normalizedExpectedRegistry;
            if ($progressRegistry !== null) {
                $registryProgress = $this->getRegistryBatchProgress($assignedBatch, $progressRegistry);
            }
        }

        return [
            'label_id' => $label->id,
            'full_label' => $label->full_label ?? trim((string) ($label->rack ?? '') . (string) ($label->shelf ?? '')),
            'rack' => $label->rack,
            'shelf' => $label->shelf,
            'counter' => $displayUsage,
            'raw_counter' => $actualUsage,
            'capacity' => null,
            'remaining' => null,
            'next_start' => $nextLabelStart,
            'next_range_key' => $nextLabelRangeKey,
            'assigned' => $label->assigned,
            'assigned_registry' => $assignedRegistry,
            'assigned_batch' => $assignedBatch,
            'is_full' => false,
            'registry_conflict' => $registryMismatch,
            'reserved_by' => $label->reserved_by,
            'reserved_at' => optional($label->reserved_at)->toIso8601String(),
            'registry_progress' => $registryProgress,
        ];
    }

    private function determineRangeKeyForPosition(int $position): ?string
    {
        if ($position >= 1 && $position <= self::RACK_SHELF_CAPACITY) {
            return '1-100';
        }

        return null;
    }

    private function getRegistryBatchProgress(?string $registryBatchNo, ?string $registryId = null): array
    {
        $normalized = $registryBatchNo !== null ? trim((string) $registryBatchNo) : '';
        $normalizedRegistry = $this->normalizeRegistryId($registryId);

        if ($normalized === '') {
            return [
                'registry_batch_no' => null,
                'printed' => 0,
                'printed_within_capacity' => 0,
                'remaining' => null,
                'next_start' => 1,
                'next_range_key' => '1-100',
                'next_range_start' => 1,
                'next_range_end' => 100,
                'is_complete' => false,
                'auto_advance' => false,
                'capacity' => null,
                'registry' => $normalizedRegistry,
            ];
        }

        $statistics = $this->buildShelfStatistics($normalized, $normalizedRegistry) ?? [];
        $totalUsed = max(0, (int) ($statistics['total_used'] ?? 0));
        $withinCapacityUsed = $totalUsed;
        $remaining = null;

        $nextStart = $totalUsed + 1;
        $nextRangeKey = $nextStart !== null ? $this->determineRangeKeyForPosition($nextStart) : null;
        $rangeStart = null;
        $rangeEnd = null;

        if ($nextRangeKey !== null) {
            try {
                [$rangeStart, $rangeEnd] = $this->parseRangeSelection($nextRangeKey);
            } catch (\Throwable $ignored) {
                $nextRangeKey = null;
                $rangeStart = null;
                $rangeEnd = null;
            }
        }

        return [
            'registry_batch_no' => $normalized,
            'printed' => $totalUsed,
            'printed_within_capacity' => $withinCapacityUsed,
            'remaining' => null,
            'next_start' => $nextStart,
            'next_range_key' => $nextRangeKey,
            'next_range_start' => $rangeStart,
            'next_range_end' => $rangeEnd,
            'is_complete' => false,
            'auto_advance' => false,
            'capacity' => null,
            'registry' => $normalizedRegistry,
        ];
    }

    /**
     * Produce a descriptive label for logging and warnings
     */
    private function describeGroupingRecord(array $record): string
    {
        return $record['tracking_id']
            ?? $record['awaiting_fileno']
            ?? $record['file_number']
            ?? $record['indexing_mls_fileno']
            ?? ('ID-' . ($record['id'] ?? 'unknown'));
    }

    /**
     * Get generated batches (alias for compatibility)
     */
    public function getBatches(Request $request)
    {
        return $this->getGeneratedBatches($request);
    }

    /**
     * Get generated batches
     */
    public function getGeneratedBatches(Request $request)
    {
        try {
            $query = CadastralPrintLabelBatch::query()
                ->with(['creator:id,first_name,last_name'])
                ->withCount('batchItems')
                ->where('status', '!=', CadastralPrintLabelBatch::STATUS_PENDING);

            // Apply filters
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // Search by batch number
            if ($request->has('search') && !empty($request->search)) {
                $query->where('batch_number', 'like', '%' . $request->search . '%');
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Apply pagination
            $perPage = $request->get('per_page', 20);
            $batches = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $batchIds = collect($batches->items())
                ->pluck('id')
                ->filter()
                ->values();

            $yearsByBatchId = collect();
            if ($batchIds->isNotEmpty()) {
                $yearsByBatchId = DB::connection('sqlsrv')
                    ->table('cadastral_print_label_batch_items as pbi')
                    ->join('grouping as g', 'g.awaiting_fileno', '=', 'pbi.file_number')
                    ->whereIn('pbi.batch_id', $batchIds->all())
                    ->whereNotNull('g.year')
                    ->select('pbi.batch_id', 'g.year')
                    ->distinct()
                    ->get()
                    ->groupBy('batch_id')
                    ->map(function ($rows) {
                        return collect($rows)
                            ->pluck('year')
                            ->filter(function ($value) {
                                return !is_null($value) && trim((string) $value) !== '';
                            })
                            ->map(function ($value) {
                                return trim((string) $value);
                            })
                            ->unique()
                            ->values();
                    });
            }

            $batches->setCollection(
                collect($batches->items())->map(function ($batch) use ($yearsByBatchId) {
                    $years = $yearsByBatchId->get($batch->id, collect());

                    $fileYear = $years->count() === 1
                        ? $years->first()
                        : ($years->isNotEmpty() ? $years->implode(', ') : null);

                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'created_at' => optional($batch->created_at)->toISOString(),
                        'batch_size' => (int) ($batch->batch_size ?? 0),
                        'batch_items_count' => (int) ($batch->batch_items_count ?? 0),
                        'label_format' => $batch->label_format,
                        'status' => $batch->status,
                        'file_year' => $fileYear,
                        'creator' => $batch->creator ? [
                            'id' => $batch->creator->id,
                            'name' => trim(implode(' ', array_filter([
                                $batch->creator->first_name ?? null,
                                $batch->creator->last_name ?? null,
                            ]))) ?: 'Unknown',
                        ] : null,
                    ];
                })
            );

            return response()->json([
                'success' => true,
                'data' => $batches->items(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page' => $batches->lastPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching generated batches', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching batches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch details with items
     */
    public function getBatchDetails($batchId)
    {
        try {
            $batch = CadastralPrintLabelBatch::with(['batchItems.fileIndexing', 'creator'])
                ->findOrFail($batchId);

            return response()->json([
                'success' => true,
                'data' => $batch
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching batch details', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching batch details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch for printing (with file details)
     */
    public function getBatchForPrinting($batchId)
    {
        try {
            $batch = CadastralPrintLabelBatch::with(['batchItems'])
                ->findOrFail($batchId);

            $fileIndexingIds = $batch->batchItems
                ->pluck('file_indexing_id')
                ->filter()
                ->unique()
                ->values();

            $indexingDetails = collect();

            if ($fileIndexingIds->isNotEmpty()) {
                $indexingDetails = FileIndexing::on('sqlsrv')
                    ->select([
                        'file_indexings.*',
                        'mother_applications.fileno as mother_fileno',
                        'mother_applications.np_fileno as mother_np_fileno',
                        'subapplications.fileno as sub_fileno',
                        'subapplications.np_fileno as sub_np_fileno'
                    ])
                    ->leftJoin('mother_applications', 'file_indexings.main_application_id', '=', 'mother_applications.id')
                    ->leftJoin('subapplications', 'file_indexings.subapplication_id', '=', 'subapplications.id')
                    ->whereIn('file_indexings.id', $fileIndexingIds)
                    ->get()
                    ->keyBy('id');
            }

            // Transform batch items to match the expected format for printing
            $files = $batch->batchItems->map(function ($item) use ($indexingDetails) {
                $details = $indexingDetails->get($item->file_indexing_id);

                $qrData = [];
                if (!empty($item->qr_code_data)) {
                    $decoded = json_decode($item->qr_code_data, true);
                    if (is_array($decoded)) {
                        $qrData = $decoded;
                    }
                }

                $trackingId = $qrData['tracking_id']
                    ?? optional($details)->tracking_id
                    ?? null;

                // Determine the best shelf/rack value to display
                $shelfValue = $item->shelf_location
                    ?? optional($details)->shelf_location
                    ?? null;

                return [
                    'id' => $item->file_indexing_id,
                    'batch_item_id' => $item->id,
                    'file_number' => $item->file_number,
                    'file_title' => $item->file_title,
                    'plot_number' => $item->plot_number,
                    'district' => $item->district,
                    'lga' => $item->lga,
                    'land_use_type' => $item->land_use_type,
                    'shelf_location' => $item->shelf_location ?? optional($details)->shelf_location,
                    'shelf_value' => $shelfValue,
                    'shelf_label' => $shelfValue,
                    'tracking_id' => $trackingId,
                    'qr_code_data' => $qrData,
                    'qr_value' => $qrData['cadastral_tracking_id'] ?? $qrData['tracking_id'] ?? $item->file_number,
                    'batch_no' => $item->file_number,
                    'main_application_id' => optional($details)->main_application_id,
                    'subapplication_id' => optional($details)->subapplication_id,
                    'mother_fileno' => optional($details)->mother_fileno,
                    'mother_np_fileno' => optional($details)->mother_np_fileno,
                    'sub_fileno' => optional($details)->sub_fileno,
                    'sub_np_fileno' => optional($details)->sub_np_fileno,
                    'st_fillno' => optional($details)->st_fillno,
                ];
            });

            $labelStatistics = null;
            if ($files->isNotEmpty()) {
                $labelCandidate = $files->first(function ($item) {
                    $value = isset($item['shelf_value']) ? trim((string) $item['shelf_value']) : null;
                    return $value !== null && $value !== '' && strcasecmp($value, 'N/A') !== 0;
                });

                if (!$labelCandidate) {
                    $labelCandidate = $files->first(function ($item) {
                        $value = isset($item['shelf_label']) ? trim((string) $item['shelf_label']) : null;
                        return $value !== null && $value !== '' && strcasecmp($value, 'N/A') !== 0;
                    });
                }

                if ($labelCandidate) {
                    $candidateValue = strtoupper(trim((string) ($labelCandidate['shelf_value'] ?? $labelCandidate['shelf_label'])));

                    $labelRecord = CadastralRackShelfLabel::whereRaw('UPPER(LTRIM(RTRIM(full_label))) = ?', [$candidateValue])->first();

                    if (!$labelRecord) {
                        $labelRecord = CadastralRackShelfLabel::whereRaw(
                            "UPPER(LTRIM(RTRIM(CONCAT(ISNULL(rack, ''), ISNULL(shelf, ''))))) = ?",
                            [$candidateValue]
                        )->first();
                    }

                    if ($labelRecord && $labelRecord->assigned) {
                        $assignment = $this->parseRegistryAssignmentValue($labelRecord->assigned);
                        $targetBatch = $assignment['batch'] ?? trim((string) $labelRecord->assigned);
                        $labelStatistics = $this->buildShelfStatistics($targetBatch, $assignment['registry']);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch,
                    'files' => $files,
                    'label_statistics' => $labelStatistics,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching batch for printing', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching batch for printing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark batch as printed
     */
    public function markBatchAsPrinted($batchId)
    {
        try {
            $batch = CadastralPrintLabelBatch::findOrFail($batchId);
            $batch->markAsPrinted();

            // Mark all items as printed
            $batch->batchItems()->update([
                'is_printed' => true,
                'printed_at' => now()
            ]);

            Log::info('Batch marked as printed', [
                'batch_id' => $batchId,
                'batch_number' => $batch->batch_number,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch marked as printed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking batch as printed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error marking batch as printed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a batch
     */
    public function deleteBatch($batchId)
    {
        try {
            $batch = CadastralPrintLabelBatch::findOrFail($batchId);
            
            // Only allow deletion of pending or generated batches
            if (in_array($batch->status, [CadastralPrintLabelBatch::STATUS_PRINTED, CadastralPrintLabelBatch::STATUS_COMPLETED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete printed or completed batches'
                ], 400);
            }

            $batchNumber = $batch->batch_number;
            $batch->delete();

            Log::info('Batch deleted', [
                'batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get print statistics
     */
    public function getStatistics()
    {
        try {
            // Check if ST filter is requested
            $stFilter = request('st_filter') === 'true';
            
            // Base query for available files
            $availableFilesQuery = FileIndexing::on('sqlsrv')->whereNotNull('batch_no')
                ->whereDoesntHave('cadastralPrintLabelBatchItems');
            
            // Apply ST filter if requested
            if ($stFilter) {
                $availableFilesQuery->where(function($query) {
                    $query->whereNotNull('subapplication_id')
                          ->orWhereHas('motherApplication', function($subQuery) {
                              $subQuery->where('application_type', 'like', '%sectional%')
                                       ->orWhere('application_type', 'like', '%ST%');
                          });
                });
            }
            
            $stats = [
                'available_files' => $availableFilesQuery->count(),
                'total_batches' => CadastralPrintLabelBatch::count(),
                'pending_batches' => CadastralPrintLabelBatch::where('status', CadastralPrintLabelBatch::STATUS_PENDING)->count(),
                'generated_batches' => CadastralPrintLabelBatch::where('status', CadastralPrintLabelBatch::STATUS_GENERATED)->count(),
                'printed_batches' => CadastralPrintLabelBatch::where('status', CadastralPrintLabelBatch::STATUS_PRINTED)->count(),
                'completed_batches' => CadastralPrintLabelBatch::where('status', CadastralPrintLabelBatch::STATUS_COMPLETED)->count(),
                'total_labels_printed' => CadastralPrintLabelBatchItem::where('is_printed', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching statistics', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function getGroupingTableHintClause(): string
    {
        static $cachedHint = null;

        if ($cachedHint !== null) {
            return $cachedHint;
        }

        $hintParts = ['NOLOCK'];

        try {
            $indexExists = DB::connection('sqlsrv')->selectOne("
                SELECT 1 AS has_index
                FROM sys.indexes
                WHERE name = 'idx_grouping_registry_batch_no_id'
                  AND object_id = OBJECT_ID('grouping')
            ");

            if ($indexExists) {
                $hintParts[] = 'INDEX(idx_grouping_registry_batch_no_id)';
            }
        } catch (\Throwable $exception) {
            Log::debug('Failed to inspect grouping index hint', [
                'error' => $exception->getMessage(),
            ]);
        }

        $cachedHint = 'WITH (' . implode(', ', $hintParts) . ')';

        return $cachedHint;
    }

    /**
     * Attempt to match a grouping record to an indexed file
     */
    protected function resolveFileIndexingForGroupingRecord(array $record, ?array $cachedIndexings = null, bool $allowDatabaseLookup = true): ?FileIndexing
    {
        if (is_array($cachedIndexings) && !empty($cachedIndexings)) {
            if (!empty($record['file_indexing_id'])) {
                $idKey = (int) $record['file_indexing_id'];
                if (isset($cachedIndexings['by_id'][$idKey])) {
                    return $cachedIndexings['by_id'][$idKey];
                }
            }

            $fileCandidates = [
                $record['file_number'] ?? null,
                $record['indexing_mls_fileno'] ?? null,
                $record['awaiting_fileno'] ?? null,
            ];

            foreach ($fileCandidates as $candidate) {
                $key = $this->normalizeLookupKey($candidate ?? null);
                if ($key && isset($cachedIndexings['by_file_number'][$key])) {
                    return $cachedIndexings['by_file_number'][$key];
                }
            }

            $trackingKey = $this->normalizeLookupKey($record['tracking_id'] ?? null);
            if ($trackingKey && isset($cachedIndexings['by_tracking_id'][$trackingKey])) {
                return $cachedIndexings['by_tracking_id'][$trackingKey];
            }
        }

        if (!$allowDatabaseLookup) {
            return null;
        }

        $query = FileIndexing::on('sqlsrv')
            ->select('file_indexings.*')
            ->leftJoin('grouping', 'grouping.awaiting_fileno', '=', 'file_indexings.file_number')
            ->where(function ($builder) {
                $builder->whereNull('file_indexings.is_deleted')
                    ->orWhere('file_indexings.is_deleted', false)
                    ->orWhere('file_indexings.is_deleted', 0);
            });

        $hasCriteria = false;

        $query->where(function ($builder) use ($record, &$hasCriteria) {
            if (!empty($record['file_indexing_id'])) {
                $builder->orWhere('file_indexings.id', (int) $record['file_indexing_id']);
                $hasCriteria = true;
            }

            if (!empty($record['indexing_id'])) {
                $builder->orWhere('file_indexings.id', (int) $record['indexing_id']);
                $hasCriteria = true;
            }

            if (!empty($record['id'])) {
                $builder->orWhere('grouping.id', (int) $record['id']);
                $hasCriteria = true;
            }

            if (!empty($record['file_number'])) {
                $builder->orWhere('file_indexings.file_number', $record['file_number']);
                $hasCriteria = true;
            }

            if (!empty($record['indexing_mls_fileno'])) {
                $builder->orWhere('file_indexings.file_number', $record['indexing_mls_fileno']);
                $hasCriteria = true;
            }

            if (!empty($record['awaiting_fileno'])) {
                $builder->orWhere('file_indexings.file_number', $record['awaiting_fileno']);
                $hasCriteria = true;
            }

            if (!empty($record['tracking_id'])) {
                $builder->orWhere('file_indexings.tracking_id', $record['tracking_id']);
                $hasCriteria = true;
            }

            if (!empty($record['registry_batch_no'])) {
                $builder->orWhere('file_indexings.registry_batch_no', $record['registry_batch_no']);
                $hasCriteria = true;
            } elseif (!empty($record['sys_batch_no'])) {
                $builder->orWhere('file_indexings.sys_batch_no', $record['sys_batch_no']);
                $hasCriteria = true;
            }

            if (!empty($record['st_fillno'])) {
                $builder->orWhere('file_indexings.st_fillno', $record['st_fillno']);
                $hasCriteria = true;
            }
        });

        if (!$hasCriteria) {
            return null;
        }

        return $query
            ->orderByDesc('file_indexings.updated_at')
            ->orderByDesc('file_indexings.id')
            ->first();
    }

    protected function buildGroupingColumnList(bool $includeShelfRack): array
    {
        $columns = [
            'id',
            'registry_batch_no',
            'sys_batch_no',
            'tracking_id',
            'cadastral_tracking_id',
            'registry',
            'awaiting_fileno',
            'mls_fileno',
            'landuse',
        ];

        if ($includeShelfRack) {
            $columns[] = 'shelf_rack';
        }

        return $columns;
    }

    protected function buildShelfStatistics(?string $registryBatchNo, ?string $registryId = null): ?array
    {
        $registryBatchNo = $registryBatchNo !== null ? trim((string) $registryBatchNo) : '';
        $normalizedRegistry = $this->normalizeRegistryId($registryId);

        if ($registryBatchNo === '') {
            return null;
        }

        $capacity = self::RACK_SHELF_CAPACITY;

        $assignedLabels = $this->retrieveAssignedRackLabels($registryBatchNo, $normalizedRegistry);

        $labels = $assignedLabels->map(function (CadastralRackShelfLabel $label) use ($capacity) {
                $used = (int) ($label->counter ?? 0);
                $clampedUsed = max(0, min($used, $capacity));
                $remaining = max(0, $capacity - $clampedUsed);
                $assignment = $this->parseRegistryAssignmentValue($label->assigned);

                return [
                    'label_id' => $label->id,
                    'label' => $label->full_label ?? trim((string) ($label->rack ?? '') . (string) ($label->shelf ?? '')),
                    'used' => $clampedUsed,
                    'remaining' => $remaining,
                    'capacity' => $capacity,
                    'status' => 'active',
                    'reserved_at' => optional($label->reserved_at)->toIso8601String(),
                    'reserved_by' => $label->reserved_by,
                    'assigned_registry' => $assignment['registry'],
                    'assigned_batch' => $assignment['batch'],
                ];
            })
            ->values();

        $totalUsed = $labels->sum('used');
        $activeLabelCount = max($labels->count(), 1);
        $totalCapacity = $activeLabelCount * $capacity;
        $totalRemaining = max(0, $totalCapacity - $totalUsed);

        $nextLabel = CadastralRackShelfLabel::whereNull('assigned')
            ->orderBy('id')
            ->first();

        return [
            'labels' => $labels->toArray(),
            'label_capacity' => $capacity,
            'total_used' => $totalUsed,
            'total_remaining' => $totalRemaining,
            'total_capacity' => $totalCapacity,
            'registry' => $normalizedRegistry,
            'next_label' => $nextLabel ? [
                'label_id' => $nextLabel->id,
                'label' => $nextLabel->full_label ?? trim((string) ($nextLabel->rack ?? '') . (string) ($nextLabel->shelf ?? '')),
                'capacity' => $capacity,
                'status' => 'available',
            ] : null,
        ];
    }

    protected function fetchFallbackGroupingRows(int $limit = 30): array
    {
        $limit = max(1, min($limit, self::MAX_BATCH_SELECTION));

        static $manualOverrideShelfCache = null;
        if ($manualOverrideShelfCache === null) {
            $manualOverrideShelfCache = Schema::connection('sqlsrv')->hasColumn('grouping', 'shelf_rack');
        }

        $hasShelfRackColumn = $manualOverrideShelfCache;
        $columnList = $this->buildGroupingColumnList($hasShelfRackColumn);
        $selectColumns = implode(', ', $columnList);
        $assignmentFilter = $hasShelfRackColumn ? "AND (shelf_rack IS NULL OR LTRIM(RTRIM(shelf_rack)) = '')" : '';

        $records = collect(DB::connection('sqlsrv')->select(
            <<<SQL
SELECT TOP {$limit} {$selectColumns}
FROM grouping WITH (NOLOCK)
WHERE (awaiting_fileno IS NOT NULL OR tracking_id IS NOT NULL)
{$assignmentFilter}
ORDER BY id ASC
SQL
        ));

        if ($records->isEmpty()) {
            return [
                'records' => [],
                'meta' => [
                    'registry_batch' => null,
                ],
            ];
        }

        $formatted = $records->map(function ($row) use ($hasShelfRackColumn) {
            $primaryNumber = $row->mls_fileno
                ?? $row->awaiting_fileno
                ?? $row->tracking_id
                ?? null;

            $shelfValue = $hasShelfRackColumn ? ($row->shelf_rack ?? null) : null;

            return [
                'id' => (int) $row->id,
                'file_number' => $primaryNumber,
                'file_title' => $row->tracking_id ?? $row->awaiting_fileno ?? 'Pending tracking ID',
                'plot_number' => null,
                'district' => null,
                'lga' => null,
                'land_use_type' => $row->landuse,
                'shelf_location' => $shelfValue,
                'batch_no' => $row->registry_batch_no ?? $row->sys_batch_no,
                'registry_batch_no' => $row->registry_batch_no,
                'sys_batch_no' => $row->sys_batch_no,
                'tracking_id' => $row->tracking_id,
                'awaiting_fileno' => $row->awaiting_fileno,
                'indexing_mls_fileno' => $row->mls_fileno,
                'main_application_id' => null,
                'subapplication_id' => null,
                'grouping_registry_batch_no' => $row->registry_batch_no,
                'grouping_sys_batch_no' => $row->sys_batch_no,
                'grouping_shelf_rack' => $shelfValue,
                'st_fillno' => null,
                'mother_fileno' => null,
                'mother_np_fileno' => null,
                'sub_fileno' => null,
                'sub_np_fileno' => null,
                'landuse' => $row->landuse,
            ];
        })->toArray();

        $firstRecord = $records->first();
        $meta = [
            'registry_batch' => $firstRecord->registry_batch_no ?? $firstRecord->sys_batch_no ?? null,
        ];

        return [
            'records' => $formatted,
            'meta' => $meta,
        ];
    }

    protected function buildFileIndexingCaches(Collection $records): array
    {
        $idKeys = [];
        $fileNumbers = [];
        $trackingIds = [];

        foreach ($records as $record) {
            if (!empty($record['file_indexing_id'])) {
                $idKeys[] = (int) $record['file_indexing_id'];
            }

            foreach (['file_number', 'indexing_mls_fileno', 'awaiting_fileno'] as $field) {
                if (!empty($record[$field])) {
                    $value = trim((string) $record[$field]);
                    if ($value !== '') {
                        $fileNumbers[] = $value;
                    }
                }
            }

            if (!empty($record['tracking_id'])) {
                $value = trim((string) $record['tracking_id']);
                if ($value !== '') {
                    $trackingIds[] = $value;
                }
            }
        }

        $idKeys = array_values(array_unique($idKeys));
        $fileNumbers = array_values(array_unique($fileNumbers));
        $trackingIds = array_values(array_unique($trackingIds));

        $caches = [
            'by_id' => [],
            'by_file_number' => [],
            'by_tracking_id' => [],
        ];

        if (empty($idKeys) && empty($fileNumbers) && empty($trackingIds)) {
            return $caches;
        }

        $columns = [
            'id',
            'file_number',
            'file_title',
            'plot_number',
            'district',
            'lga',
            'land_use_type',
            'shelf_location',
            'tracking_id',
        ];

        if (!empty($idKeys)) {
            $entries = FileIndexing::on('sqlsrv')
                ->select($columns)
                ->whereIn('id', $idKeys)
                ->get();
            $this->appendIndexingsToCache($caches, $entries);
        }

        $remainingFiles = array_values(array_filter($fileNumbers, function ($value) use (&$caches) {
            $key = $this->normalizeLookupKey($value);
            return $key && !isset($caches['by_file_number'][$key]);
        }));

        if (!empty($remainingFiles)) {
            $entries = FileIndexing::on('sqlsrv')
                ->select($columns)
                ->whereIn('file_number', $remainingFiles)
                ->get();
            $this->appendIndexingsToCache($caches, $entries);
        }

        $remainingTracking = array_values(array_filter($trackingIds, function ($value) use (&$caches) {
            $key = $this->normalizeLookupKey($value);
            return $key && !isset($caches['by_tracking_id'][$key]);
        }));

        if (!empty($remainingTracking)) {
            $entries = FileIndexing::on('sqlsrv')
                ->select($columns)
                ->whereIn('tracking_id', $remainingTracking)
                ->get();
            $this->appendIndexingsToCache($caches, $entries);
        }

        return $caches;
    }

    private function parseFileNumberList($input): Collection
    {
        if ($input instanceof Collection) {
            $values = $input->all();
        } elseif (is_array($input)) {
            $values = $input;
        } elseif (is_string($input)) {
            $values = preg_split('/[\s,;|]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $values = [];
        }

        $seen = [];

        return collect($values)
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter(function (?string $value) use (&$seen) {
                if ($value === null || $value === '') {
                    return false;
                }

                $normalized = strtoupper($value);
                if (isset($seen[$normalized])) {
                    return false;
                }

                $seen[$normalized] = true;

                return true;
            })
            ->values();
    }

    protected function fetchGroupingRecordsForManualOverride(Collection $indexedRequested, Collection $existingRecords): Collection
    {
        if ($indexedRequested->isEmpty()) {
            return collect();
        }

        $missingNormalized = $indexedRequested
            ->filter(function (array $entry) use ($existingRecords) {
                return !$existingRecords->has($entry['normalized']);
            })
            ->pluck('normalized')
            ->unique()
            ->values();

        if ($missingNormalized->isEmpty()) {
            return collect();
        }

        $originalValues = $indexedRequested
            ->filter(function (array $entry) use ($missingNormalized) {
                return $missingNormalized->contains($entry['normalized']);
            })
            ->pluck('original')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter(function ($value) {
                return $value !== '';
            })
            ->unique()
            ->values();

        if ($originalValues->isEmpty()) {
            return collect();
        }

        static $manualOverrideShelfRackCache = null;
        if ($manualOverrideShelfRackCache === null) {
            $manualOverrideShelfRackCache = Schema::connection('sqlsrv')->hasColumn('grouping', 'shelf_rack');
        }

        $hasShelfRackColumn = $manualOverrideShelfRackCache;

        $columnList = array_merge(
            $this->buildGroupingColumnList($hasShelfRackColumn),
            ['year', 'number', 'date', 'created_by', 'indexed_by', 'date_index']
        );

        $rows = DB::connection('sqlsrv')
            ->table('grouping')
            ->select($columnList)
            ->where(function ($outer) use ($originalValues) {
                $first = true;
                foreach ($originalValues as $value) {
                    $outer->{$first ? 'where' : 'orWhere'}(function ($inner) use ($value) {
                        $inner->where('awaiting_fileno', $value)
                            ->orWhere('mls_fileno', $value)
                            ->orWhere('tracking_id', $value);
                    });
                    $first = false;
                }
            })
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $normalizedKeys = $missingNormalized;

        return $rows
            ->map(function ($row) use ($hasShelfRackColumn, $normalizedKeys) {
                $primary = $row->mls_fileno
                    ?? $row->awaiting_fileno
                    ?? $row->tracking_id;

                if ($primary === null) {
                    return null;
                }

                $fileNumber = trim((string) $primary);
                if ($fileNumber === '') {
                    return null;
                }

                $shelfValue = $hasShelfRackColumn ? ($row->shelf_rack ?? null) : null;

                $record = [
                    'id' => (int) $row->id,
                    'grouping_id' => (int) $row->id,
                    'file_number' => $fileNumber,
                    'file_title' => $row->tracking_id ?? $row->awaiting_fileno ?? $fileNumber,
                    'plot_number' => null,
                    'district' => null,
                    'lga' => null,
                    'land_use_type' => $row->landuse,
                    'landuse' => $row->landuse,
                    'shelf_location' => $shelfValue,
                    'shelf_label' => $shelfValue,
                    'shelf_full_label' => $shelfValue,
                    'shelf_value' => $shelfValue,
                    'batch_no' => $row->registry_batch_no ?? $row->sys_batch_no,
                    'registry_batch_no' => $row->registry_batch_no ?? $row->sys_batch_no,
                    'sys_batch_no' => $row->sys_batch_no,
                    'registry' => $row->registry ?? null,
                    'tracking_id' => $row->tracking_id,
                    'awaiting_fileno' => $row->awaiting_fileno,
                    'indexing_mls_fileno' => $row->mls_fileno,
                    'grouping_registry_batch_no' => $row->registry_batch_no,
                    'grouping_sys_batch_no' => $row->sys_batch_no,
                    'grouping_shelf_rack' => $shelfValue,
                    'year' => $row->year ?? null,
                    'number' => $row->number ?? null,
                    'manual_override' => true,
                    'manual_override_source' => 'grouping',
                ];

                $matchField = $this->detectGroupingOverrideMatch($row, $normalizedKeys);
                if ($matchField !== null) {
                    $record['override_match'] = $matchField;
                }

                $indexing = $this->resolveFileIndexingForGroupingRecord($record);
                if ($indexing) {
                    $record['file_indexing_id'] = (int) $indexing->id;
                    $record['indexing_tracking_id'] = $indexing->tracking_id;
                    $record['file_title'] = $indexing->file_title ?? $record['file_title'];
                    $record['plot_number'] = $indexing->plot_number ?? $record['plot_number'];
                    $record['district'] = $indexing->district ?? $record['district'];
                    $record['lga'] = $indexing->lga ?? $record['lga'];
                    $record['land_use_type'] = $record['land_use_type'] ?? $indexing->land_use_type;
                    $record['shelf_location'] = $record['shelf_location'] ?? $indexing->shelf_location;
                    $record['shelf_label'] = $record['shelf_label'] ?? $indexing->shelf_location;
                    $record['shelf_full_label'] = $record['shelf_full_label'] ?? $indexing->shelf_location;
                    $record['batch_no'] = $record['batch_no'] ?? $indexing->batch_no;
                    $record['registry_batch_no'] = $record['registry_batch_no'] ?? $indexing->registry_batch_no;
                    $record['tracking_id'] = $record['tracking_id'] ?? $indexing->tracking_id;
                }

                return $record;
            })
            ->filter()
            ->values();
    }

    protected function detectGroupingOverrideMatch($row, Collection $normalizedKeys): ?string
    {
        $candidates = [
            'mls_fileno' => $row->mls_fileno ?? null,
            'awaiting_fileno' => $row->awaiting_fileno ?? null,
            'tracking_id' => $row->tracking_id ?? null,
        ];

        foreach ($candidates as $field => $value) {
            $normalized = $this->normalizeLookupKey($value);
            if ($normalized !== null && $normalizedKeys->contains($normalized)) {
                return 'grouping.' . $field;
            }
        }

        $primary = $this->normalizeLookupKey($row->mls_fileno ?? $row->awaiting_fileno ?? $row->tracking_id ?? null);
        if ($primary !== null && $normalizedKeys->contains($primary)) {
            return 'grouping.primary';
        }

        return null;
    }

    protected function normalizeLookupKey(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function appendIndexingsToCache(array &$caches, Collection $entries): void
    {
        foreach ($entries as $indexing) {
            $id = (int) $indexing->id;
            if (!isset($caches['by_id'][$id])) {
                $caches['by_id'][$id] = $indexing;
            }

            $fileKey = $this->normalizeLookupKey($indexing->file_number ?? null);
            if ($fileKey) {
                $caches['by_file_number'][$fileKey] = $indexing;
            }

            $trackingKey = $this->normalizeLookupKey($indexing->tracking_id ?? null);
            if ($trackingKey) {
                $caches['by_tracking_id'][$trackingKey] = $indexing;
            }
        }
    }
}
