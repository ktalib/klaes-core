<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class FileNumberApiController extends Controller
{
    /**
     * Cache locally generated tracking IDs to avoid duplicates within a single request lifecycle.
     *
     * @var array<string, bool>
     */
    private array $generatedTrackingIds = [];

    /**
     * Global index endpoint for file numbers (MLS, ST, KANGIS, etc.) backed by dbo.fileNumber.
     * Supports pagination, filtering, and lightweight searching.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) max(1, min($request->get('per_page', 50), 200));
            $orderBy = $request->get('order_by', 'fn.id');
            $orderDirection = strtolower($request->get('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = $this->baseFileNumberQuery();

            if ($search = trim((string) $request->get('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('fn.mlsfNo', 'LIKE', "%{$search}%")
                        ->orWhere('fn.kangisFileNo', 'LIKE', "%{$search}%")
                        ->orWhere('fn.NewKANGISFileNo', 'LIKE', "%{$search}%")
                        ->orWhere('fn.st_file_no', 'LIKE', "%{$search}%")
                        ->orWhere('fn.FileName', 'LIKE', "%{$search}%")
                        ->orWhere('fn.tracking_id', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('tracking_id')) {
                $query->where('fn.tracking_id', trim($request->get('tracking_id')));
            }

            if ($request->filled('mlsf_no')) {
                $query->where('fn.mlsfNo', trim($request->get('mlsf_no')));
            }

            if ($request->filled('st_file_no')) {
                $query->where('fn.st_file_no', trim($request->get('st_file_no')));
            }

            if ($request->filled('kangis_file_no')) {
                $query->where('fn.kangisFileNo', trim($request->get('kangis_file_no')));
            }

            if ($request->filled('new_kangis_file_no')) {
                $query->where('fn.NewKANGISFileNo', trim($request->get('new_kangis_file_no')));
            }

            if ($request->filled('type')) {
                $query->where('fn.type', $request->get('type'));
            }

            if ($request->filled('source')) {
                $query->where('fn.SOURCE', $request->get('source'));
            }

            if ($request->boolean('has_st_file')) {
                $query->whereNotNull('fn.st_file_no')->whereRaw("LTRIM(RTRIM(fn.st_file_no)) != ''");
            }

            if ($request->boolean('only_active', false)) {
                $query->where(function ($q) {
                    $q->whereNull('fn.is_decommissioned')
                        ->orWhere('fn.is_decommissioned', 0);
                });
            }

            $allowedOrderColumns = [
                'fn.id',
                'fn.created_at',
                'fn.updated_at',
                'fn.mlsfNo',
                'fn.kangisFileNo',
                'fn.NewKANGISFileNo',
                'fn.st_file_no',
                'fn.FileName',
                'fn.tracking_id'
            ];
            if (!in_array($orderBy, $allowedOrderColumns, true)) {
                $orderBy = 'fn.id';
            }

            $paginator = $query
                ->orderBy($orderBy, $orderDirection)
                ->paginate($perPage)
                ->appends($request->query());

            $data = collect($paginator->items())
                ->map(fn($row) => $this->transformFileNumberRecord((array) $row));

            return response()->json([
                'success' => true,
                'message' => 'File numbers fetched successfully.',
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
                'filters' => $request->only([
                    'search',
                    'tracking_id',
                    'mlsf_no',
                    'st_file_no',
                    'kangis_file_no',
                    'new_kangis_file_no',
                    'type',
                    'source',
                    'has_st_file',
                    'only_active'
                ]),
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('fetch file numbers', $e);
        }
    }

    /**
     * Create a new file number record through the global API.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_name' => ['nullable', 'string', 'max:255'],
            'mlsf_no' => ['nullable', 'string', 'max:255'],
            'st_file_no' => ['nullable', 'string', 'max:255'],
            'kangis_file_no' => ['nullable', 'string', 'max:255'],
            'new_kangis_file_no' => ['nullable', 'string', 'max:255'],
            'tracking_id' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'plot_no' => ['nullable', 'string', 'max:255'],
            'tp_no' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'created_by' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (
                !$request->filled('mlsf_no')
                && !$request->filled('st_file_no')
                && !$request->filled('kangis_file_no')
                && !$request->filled('new_kangis_file_no')
            ) {
                $validator->errors()->add('file_numbers', 'Provide at least one file number value (MLS, ST, KANGIS, or NEW KANGIS).');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payload = $validator->validated();
            $connection = DB::connection('sqlsrv');

            $duplicates = [
                'mlsfNo' => $payload['mlsf_no'] ?? null,
                'st_file_no' => $payload['st_file_no'] ?? null,
                'kangisFileNo' => $payload['kangis_file_no'] ?? null,
                'NewKANGISFileNo' => $payload['new_kangis_file_no'] ?? null,
                'tracking_id' => $payload['tracking_id'] ?? null,
            ];

            foreach ($duplicates as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $exists = $connection->table('fileNumber')
                    ->where($column, $value)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => ucfirst(str_replace('_', ' ', $column)) . ' already exists.',
                    ], 409);
                }
            }

            $trackingId = $this->getUniqueTrackingId($payload['tracking_id'] ?? null);

            $createdBy = $payload['created_by'] ?? null;
            if (auth()->check()) {
                $createdBy = (string) auth()->id();
            }

            $insertData = [
                'mlsfNo' => $payload['mlsf_no'] ?? null,
                'st_file_no' => $payload['st_file_no'] ?? null,
                'kangisFileNo' => $payload['kangis_file_no'] ?? null,
                'NewKANGISFileNo' => $payload['new_kangis_file_no'] ?? null,
                'FileName' => $payload['file_name'] ?? null,
                'location' => $payload['location'] ?? null,
                'plot_no' => $payload['plot_no'] ?? null,
                'tp_no' => $payload['tp_no'] ?? null,
                'tracking_id' => $trackingId,
                'type' => $payload['type'] ?? 'API',
                'SOURCE' => $payload['source'] ?? 'API',
                'is_deleted' => 0,
                'created_by' => $createdBy ?? 'API',
                'created_at' => now(),
                'updated_by' => $createdBy ?? 'API',
                'updated_at' => now(),
            ];

            $id = $connection->table('fileNumber')->insertGetId($insertData);

            $record = $this->baseFileNumberQuery()
                ->where('fn.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'File number created successfully.',
                'data' => $this->transformFileNumberRecord((array) $record),
            ], 201);
        } catch (\Throwable $e) {
            return $this->apiError('create file number', $e);
        }
    }

    /**
     * Lookup endpoint that returns a single record by tracking ID or any of the file number columns.
     */
    public function lookup(Request $request): JsonResponse
    {
        $selectedFileNumber = trim((string) $request->get('file_number', ''));
        $module = strtolower(trim((string) $request->get('module', '')));

        $criteria = array_filter([
            'tracking_id' => $request->get('tracking_id'),
            'mlsf_no' => $request->get('mlsf_no'),
            'st_file_no' => $request->get('st_file_no'),
            'kangis_file_no' => $request->get('kangis_file_no'),
            'new_kangis_file_no' => $request->get('new_kangis_file_no'),
            'file_number' => $request->get('file_number'),
        ], fn($value) => !empty($value));

        if ($selectedFileNumber === '') {
            $selectedFileNumber = trim((string) (
                $criteria['file_number']
                ?? $criteria['mlsf_no']
                ?? $criteria['kangis_file_no']
                ?? $criteria['new_kangis_file_no']
                ?? $criteria['st_file_no']
                ?? ''
            ));
        }

        if (empty($criteria)) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one identifier (tracking_id, file_number, mlsf_no, st_file_no, kangis_file_no, new_kangis_file_no).',
            ], 422);
        }

        try {
            $record = $this->findFileNumber($criteria, true);
            $isKangisModule = in_array($module, ['kangis', 'new_kangis', 'kgis'], true);
            $isKnFormat = $selectedFileNumber !== '' && preg_match('/^KN\d+$/i', $selectedFileNumber);

            if (!$record) {
                if ($isKangisModule && $selectedFileNumber !== '') {
                    // For KN-format numbers (new KANGIS awaiting files), check file_tracker directly.
                    if ($isKnFormat) {
                        $knData = $this->findKnFileTrackerData($selectedFileNumber);
                        if ($knData) {
                            return response()->json([
                                'success' => true,
                                'message' => 'File number retrieved from New KANGIS tracker.',
                                'data'    => $knData,
                            ]);
                        }

                        // For KN-format in KANGIS module, the source of truth for "indexed"
                        // is file_indexings / file_tracker. Do NOT fall back to grouping
                        // tables — otherwise a stale kn_grouping / kangis_grouping row will
                        // backfill a tracking_id and the UI will skip the "File Not Yet
                        // Indexed" prompt even though the file was never indexed.
                        $indexingData = $this->getFileIndexingData($selectedFileNumber);
                        if (!empty($indexingData) && !empty($indexingData['tracking_id'])) {
                            return response()->json([
                                'success' => true,
                                'message' => 'File number retrieved from file indexing tracking source.',
                                'data' => [
                                    'file_number' => $selectedFileNumber,
                                    'tracking_id' => $indexingData['tracking_id'],
                                    'file_name'   => $indexingData['file_name'] ?? null,
                                ],
                            ]);
                        }

                        return response()->json([
                            'success' => false,
                            'message' => 'File number not found.',
                        ], 404);
                    }

                    $groupingTrackingId = $this->findKangisGroupingTrackingId($selectedFileNumber);
                    if ($groupingTrackingId) {
                        return response()->json([
                            'success' => true,
                            'message' => 'File number retrieved from KANGIS grouping.',
                            'data' => [
                                'file_number' => $selectedFileNumber,
                                'tracking_id' => $groupingTrackingId,
                            ],
                        ]);
                    }
                }

                if ($selectedFileNumber !== '') {
                    $indexingData = $this->getFileIndexingData($selectedFileNumber);
                    if ($indexingData) {
                        return response()->json([
                            'success' => true,
                            'message' => 'File number retrieved from file indexing source.',
                            'data' => $indexingData,
                        ]);
                    }

                    $fallbackTrackingId = $this->findTrackingIdFromGroupingTables($selectedFileNumber);

                    if (!empty($fallbackTrackingId)) {
                        return response()->json([
                            'success' => true,
                            'message' => 'File number retrieved from grouping tracking source.',
                            'data' => [
                                'file_number' => $selectedFileNumber,
                                'tracking_id' => $fallbackTrackingId,
                            ],
                        ]);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'File number not found.',
                ], 404);
            }

            $payload = $this->transformFileNumberRecord((array) $record);

            if ($isKangisModule && $selectedFileNumber !== '') {
                // For KN-format numbers, tracking_id comes from file_tracker, not fileNumber table.
                if ($isKnFormat) {
                    $knData = $this->findKnFileTrackerData($selectedFileNumber);
                    if ($knData && !empty($knData['tracking_id'])) {
                        $payload['tracking_id'] = $knData['tracking_id'];
                        if (!empty($knData['file_name'])) {
                            $payload['file_name'] = $knData['file_name'];
                        }
                    } else {
                        // Only trust file_indexings for KN-format — never backfill from
                        // kn_grouping / kangis_grouping, so the UI can detect "not indexed".
                        $indexingTrackingId = $this->findTrackingIdFromFileIndexings($selectedFileNumber);
                        if (!empty($indexingTrackingId)) {
                            $payload['tracking_id'] = $indexingTrackingId;
                        } else {
                            $payload['tracking_id'] = null;
                        }
                    }
                } else {
                    $groupingTrackingId = $this->findKangisGroupingTrackingId($selectedFileNumber);
                    if ($groupingTrackingId) {
                        $payload['tracking_id'] = $groupingTrackingId;
                    }
                }
            }

            // Skip generic grouping-table fallback for KN-format in KANGIS module —
            // its indexed state is authoritative from file_indexings / file_tracker only.
            if (
                empty(trim((string) ($payload['tracking_id'] ?? '')))
                && $selectedFileNumber !== ''
                && !($isKangisModule && $isKnFormat)
            ) {
                $indexingData = $this->getFileIndexingData($selectedFileNumber);
                if ($indexingData && !empty($indexingData['tracking_id'])) {
                    $payload['tracking_id'] = $indexingData['tracking_id'];
                    // Backfill other missing details if possible
                    $payload['plot_no'] = $payload['plot_no'] ?? $indexingData['plot_no'] ?? null;
                    $payload['tp_no'] = $payload['tp_no'] ?? $indexingData['tp_no'] ?? null;
                    $payload['temp_file_no'] = $payload['temp_file_no'] ?? $indexingData['temp_file_no'] ?? null;
                    $payload['is_indexed'] = true;
                    $payload['file_indexing_id'] = $indexingData['file_indexing_id'] ?? null;
                } else {
                    $fallbackTrackingId = $this->findTrackingIdFromGroupingTables($selectedFileNumber);
                    if (!empty($fallbackTrackingId)) {
                        $payload['tracking_id'] = $fallbackTrackingId;
                    }
                }
            }

            // For KN-format in KANGIS module without a tracking_id, treat as "not indexed"
            // so the UI shows the File Not Yet Indexed prompt instead of rendering a stale
            // fileNumber-table record with no tracker.
            if (
                $isKangisModule
                && $isKnFormat
                && empty(trim((string) ($payload['tracking_id'] ?? '')))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File number retrieved successfully.',
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('lookup file number', $e);
        }
    }

    /**
     * Resolve tracking ID from KANGIS grouping using the selected file number.
     */
    private function findKangisGroupingTrackingId(string $selectedFileNumber): ?string
    {
        $fileNumber = trim($selectedFileNumber);
        if ($fileNumber === '') {
            return null;
        }

        try {
            $row = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->select('tracking_id')
                ->where('kangis_awaiting_fileno', $fileNumber)
                ->whereNotNull('tracking_id')
                ->whereRaw("LTRIM(RTRIM(tracking_id)) <> ''")
                ->orderByDesc('id')
                ->first();

            return $row?->tracking_id ? trim((string) $row->tracking_id) : null;
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve KANGIS grouping tracking ID', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve tracking ID directly from file_indexings by file number.
     */
    private function findTrackingIdFromFileIndexings(string $selectedFileNumber): ?string
    {
        $fileNumber = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($selectedFileNumber)));
        if ($fileNumber === '') {
            return null;
        }

        try {
            $row = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->select('tracking_id')
                ->where(function ($q) use ($fileNumber, $selectedFileNumber) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$fileNumber])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$selectedFileNumber])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$selectedFileNumber]);
                })
                ->whereNotNull('tracking_id')
                ->whereRaw("LTRIM(RTRIM(tracking_id)) <> ''")
                ->orderByDesc('id')
                ->first();

            return $row?->tracking_id ? trim((string) $row->tracking_id) : null;
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve tracking ID from file_indexings', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch comprehensive file indexing data for a given file number.
     */
    private function getFileIndexingData(string $selectedFileNumber): ?array
    {
        $fileNumber = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($selectedFileNumber)));
        if ($fileNumber === '') {
            return null;
        }

        try {
            // VARCHAR columns: bind the parameter as VARCHAR so indexes are used and
            // we avoid the NVARCHAR implicit-conversion full scan. Default CI collation
            // makes "=" case- and trailing-space-insensitive.
            $values = array_values(array_unique(array_filter(
                [$fileNumber, $selectedFileNumber],
                fn($v) => $v !== ''
            )));

            $row = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where(function ($q) use ($values) {
                    foreach ($values as $value) {
                        $q->orWhereRaw('file_number = CAST(? AS VARCHAR(255))', [$value])
                          ->orWhereRaw('temp_file_no = CAST(? AS VARCHAR(255))', [$value]);
                    }
                })
                ->orderByDesc('id')
                ->first();

            if (!$row) {
                return null;
            }

            return [
                'file_number' => $row->file_number,
                'temp_file_no' => $row->temp_file_no,
                'file_name' => $row->file_title,
                'plot_no' => $row->plot_number,
                'tp_no' => $row->tp_no,
                'location' => $row->district,
                'district' => $row->district,
                'street_name' => $row->street_name ?? null,
                'lga' => $row->lga,
                'tracking_id' => $row->tracking_id,
                'num_pages' => $row->pagetypings_count ?? null,
                'in_digital_archive' => true,
                'is_indexed' => true,
                'file_indexing_id' => $row->id,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Failed to fetch file indexing data', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve tracking ID from registry grouping tables for any file number type.
     *
     * Note: LPKN and MISC-KN are under Survey and therefore resolved via gkn_grouping.
     */
    private function findTrackingIdFromGroupingTables(string $selectedFileNumber): ?string
    {
        $fileNumber = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($selectedFileNumber)));
        if ($fileNumber === '') {
            return null;
        }

        $targets = [
            ['table' => 'grouping', 'columns' => ['awaiting_fileno', 'mls_fileno']],
            ['table' => 'gkn_grouping', 'columns' => ['gkn_awaiting_fileno', 'gkn_fileno']],
            ['table' => 'sltr_grouping', 'columns' => ['sltr_awaiting_fileno', 'sltr_fileno']],
            ['table' => 'sit_grouping', 'columns' => ['sit_awaiting_fileno', 'sit_fileno']],
            ['table' => 'dciv_grouping', 'columns' => ['dciv_awaiting_fileno', 'dciv_fileno']],
            ['table' => 'kangis_grouping', 'columns' => ['kangis_awaiting_fileno', 'kangis_fileno', 'indexing_kangis_fileno']],
            ['table' => 'kn_grouping', 'columns' => ['kn_awaiting_fileno', 'kn_fileno']],
        ];

        try {
            foreach ($targets as $target) {
                $table = $target['table'];
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'tracking_id')) {
                    continue;
                }

                $columns = array_values(array_filter($target['columns'], function ($column) use ($table) {
                    return Schema::connection('sqlsrv')->hasColumn($table, $column);
                }));

                if (empty($columns)) {
                    continue;
                }

                // Columns are VARCHAR; bind the parameter as VARCHAR so the existing
                // indexes are used. A plain NVARCHAR bind (PDO default) forces an
                // implicit column conversion and a full table scan (seconds on the
                // multi-million-row grouping table). The default CI collation already
                // makes "=" case- and trailing-space-insensitive, so the previous
                // UPPER(LTRIM(RTRIM(...))) wrappers were both slow and unnecessary.
                $values = array_values(array_unique(array_filter(
                    [$fileNumber, $selectedFileNumber],
                    fn($v) => $v !== ''
                )));

                // NOTE: do not add ORDER BY id DESC to this query. With the OR seek on
                // the file-number columns, the optimizer abandons the index seek in
                // favour of a descending PK scan of the whole table (seconds vs ~10ms).
                // We fetch the small matching set and pick the latest row in PHP instead.
                $row = DB::connection('sqlsrv')
                    ->table($table)
                    ->select('id', 'tracking_id')
                    ->whereNotNull('tracking_id')
                    ->where('tracking_id', '<>', '')
                    ->where(function ($q) use ($columns, $values) {
                        foreach ($columns as $column) {
                            foreach ($values as $value) {
                                $q->orWhereRaw("{$column} = CAST(? AS VARCHAR(255))", [$value]);
                            }
                        }
                    })
                    ->get()
                    ->sortByDesc('id')
                    ->first();

                if (!empty($row?->tracking_id)) {
                    return trim((string) $row->tracking_id);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve tracking ID from grouping tables', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Resolve file tracker data for a New KANGIS awaiting file number (KN-format).
     * These numbers live in file_tracker.file_number, created at indexing time.
     *
     * @return array{file_number:string,tracking_id:string,file_name:string}|null
     */
    private function findKnFileTrackerData(string $fileNumber): ?array
    {
        $fileNumber = strtoupper(trim($fileNumber));
        if ($fileNumber === '') {
            return null;
        }

        try {
            $row = DB::connection('sqlsrv')
                ->table('file_tracker')
                ->select(['tracking_id', 'file_number', 'file_title'])
                ->where('file_number', $fileNumber)
                ->whereNotNull('tracking_id')
                ->whereRaw("LTRIM(RTRIM(tracking_id)) <> ''")
                ->orderByDesc('id')
                ->first();

            if (!$row || empty($row->tracking_id)) {
                return null;
            }

            return [
                'file_number' => $row->file_number ?? $fileNumber,
                'tracking_id' => trim((string) $row->tracking_id),
                'file_name'   => $row->file_title ?? '',
            ];
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve KN file tracker data', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convenience endpoint to fetch a record by tracking ID directly.
     */
    public function showByTracking(string $trackingId): JsonResponse
    {
        try {
            $record = $this->findFileNumber(['tracking_id' => $trackingId]);

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File number retrieved successfully.',
                'data' => $this->transformFileNumberRecord((array) $record),
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('fetch file number by tracking ID', $e);
        }
    }

    /**
     * Fetch latest MLS file numbers.
     */
    public function mls(Request $request): JsonResponse
    {
        try {
            $limit = (int) max(1, min($request->get('limit', 100), 500));
            $search = $request->get('search');
            $excludeMatched = $request->get('exclude_matched');
            // When set, the MLS selector surfaces EVERY file number in the indexing
            // table (KANGIS, New KANGIS, SLTR, SIT, DCIV, GKN, …) instead of the
            // default MLS/Lands-only scope. Driven by the global file-number modal.
            $includeAllRegistries = filter_var($request->get('all_registries', false), FILTER_VALIDATE_BOOLEAN);
            // Temporary-file toggle (Create File Tracker "Is Temporary File"): restrict
            // the selector to temporary "(T)" file numbers only.
            $tempOnly = filter_var($request->get('temp_only', false), FILTER_VALIDATE_BOOLEAN);

            $query = DB::connection('sqlsrv')
                ->table('dbo.fileNumber')
                ->select([
                    'id',
                    DB::raw('mlsfNo as mlsFNo'),
                    DB::raw('mlsfNo as file_number'),
                    'FileName',
                    'location',
                    'plot_no',
                    'tp_no',
                    'temp_file_no'
                ])
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });

            // Restrict to temporary "(T)" file numbers when requested. In SQL Server
            // LIKE, parentheses are literal so '%(T)%' matches the "(T)" suffix.
            if ($tempOnly) {
                $query->where('mlsfNo', 'LIKE', '%(T)%');
            }

            // Exclude matching if requested
            if (!empty($excludeMatched)) {
                switch (strtolower($excludeMatched)) {
                    case 'lands':
                        $query->where(function($q) {
                            $q->whereNull('pp_lands_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_lands_matching', '!=', 1)
                                       ->where('pp_lands_matching', '!=', '1');
                              });
                        });
                        break;
                    case 'st':
                        $query->where(function($q) {
                            $q->whereNull('pp_st_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_st_matching', '!=', 1)
                                       ->where('pp_st_matching', '!=', '1');
                              });
                        });
                        break;
                    case 'sltr':
                        $query->where(function($q) {
                            $q->whereNull('pp_sltr_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_sltr_matching', '!=', 1)
                                       ->where('pp_sltr_matching', '!=', '1');
                              });
                        });
                        break;
                }
            }

            if (!empty($search)) {
                $this->applyFileNumberSearch($query, 'mlsfNo', $search);

                // Also search in temp_file_no column of dbo.fileNumber if it matches.
                // Skipped in temp-only mode: this loose top-level OR would otherwise
                // escape the "(T)" AND-constraint and pull in non-temp rows, filling
                // the limit before the temp-only guard/file_indexings pass runs.
                if (!$tempOnly) {
                    $query->orWhere(function ($q) use ($search) {
                        $q->where('temp_file_no', 'LIKE', "%{$search}%")
                          ->orWhere('temp_fileno', 'LIKE', "%{$search}%");
                    });
                }
            }

            $rows = $query
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $files = $rows->map(fn($r) => [
                'mlsFNo' => $r->mlsFNo,
                'file_number' => $r->file_number,
                'id' => $r->id,
                'FileName' => $r->FileName,
                'file_name' => $r->FileName,
                'location' => $r->location,
                'plot_no' => $r->plot_no,
                'tp_no' => $r->tp_no,
                'temp_file_no' => $r->temp_file_no ?? null,
            ]);

            // Pull additional rows from file_indexings. In the default MLS/Lands-only
            // scope this only runs for searches (to catch temp numbers). When
            // all_registries is requested it always runs — including the initial
            // load — and drops the registry filters so KANGIS, New KANGIS, SLTR,
            // SIT, DCIV, GKN, etc. all appear in the MLS selector.
            if (($includeAllRegistries || !empty($search)) && $files->count() < $limit) {
                $indexingQuery = DB::connection('sqlsrv')
                    ->table('file_indexings')
                    ->select([
                        'id',
                        'file_number',
                        'mls_file_no',
                        'kangis_file_no',
                        'new_kangis_file_no',
                        'temp_file_no',
                        'file_title as FileName',
                        'district as location',
                        'plot_number as plot_no',
                        'tp_no'
                    ]);

                if (!empty($search)) {
                    $indexingQuery->where(function ($q) use ($search) {
                        $q->where('temp_file_no', 'LIKE', "%{$search}%")
                          ->orWhere('file_number', 'LIKE', "%{$search}%")
                          ->orWhere('mls_file_no', 'LIKE', "%{$search}%")
                          ->orWhere('kangis_file_no', 'LIKE', "%{$search}%")
                          ->orWhere('new_kangis_file_no', 'LIKE', "%{$search}%");
                    });
                }

                // Temp-only: keep just the rows whose file numbers carry the "(T)" marker.
                if ($tempOnly) {
                    $indexingQuery->where(function ($q) {
                        $q->where('file_number', 'LIKE', '%(T)%')
                          ->orWhere('temp_file_no', 'LIKE', '%(T)%')
                          ->orWhere('mls_file_no', 'LIKE', '%(T)%')
                          ->orWhere('kangis_file_no', 'LIKE', '%(T)%')
                          ->orWhere('new_kangis_file_no', 'LIKE', '%(T)%');
                    });
                }

                if (!$includeAllRegistries) {
                    // Keep this MLS endpoint MLS/Lands-only: exclude file_indexings rows
                    // that belong to other registries (KANGIS / New KANGIS / SLTR / ST /
                    // SIT / DCIV / Survey) so the MLS selector never surfaces their files.
                    $indexingQuery
                        ->whereRaw("ISNULL(kangis_file_no, '') = ''")
                        ->whereRaw("ISNULL(new_kangis_file_no, '') = ''")
                        ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(registry, '')))) NOT IN ('SLTR','DCIV','KANGIS','SURVEY','SIT','ST REGISTRY','CADESTRAL','CADASTRAL')")
                        ->where(function ($q) {
                            foreach (['KN%','MLKN%','KNGP%','KNML%','SLTR%','SIT%','ST-%','ST/%','LPCC%','DCIV%','GKN%','LPKN%'] as $prefix) {
                                $q->whereRaw("UPPER(LTRIM(ISNULL(file_number, ''))) NOT LIKE ?", [$prefix]);
                            }
                        });
                }

                $indexingResults = $indexingQuery
                    ->orderByDesc('id')
                    ->limit($limit - $files->count())
                    ->get();

                foreach ($indexingResults as $r) {
                    // Resolve the file number to display. When surfacing every registry
                    // we fall back across the registry-specific columns so KANGIS-only
                    // rows (which may leave file_number blank) still show a number.
                    $displayNo = $includeAllRegistries
                        ? ($r->file_number ?: $r->mls_file_no ?: $r->kangis_file_no ?: $r->new_kangis_file_no ?: $r->temp_file_no)
                        : ($r->temp_file_no ?: $r->file_number);

                    if (empty($displayNo)) {
                        continue;
                    }

                    // Avoid duplicate entries if already found in fileNumber
                    if ($files->contains('file_number', $displayNo)) {
                        continue;
                    }

                    $files->push([
                        'mlsFNo' => $displayNo,
                        'file_number' => $displayNo,
                        'id' => 'fi_' . $r->id, // Prefix ID to avoid collision
                        'FileName' => $r->FileName,
                        'file_name' => $r->FileName,
                        'location' => $r->location,
                        'plot_no' => $r->plot_no,
                        'tp_no' => $r->tp_no,
                        'temp_file_no' => $r->temp_file_no,
                    ]);
                }
            }

            // Final safety net: guarantee the temp-only selector never surfaces a
            // display number without the "(T)" marker, regardless of source/column.
            if ($tempOnly) {
                $files = $files->filter(function ($f) {
                    return strpos((string) ($f['file_number'] ?? ''), '(T)') !== false;
                })->values();
            }

            return response()->json([
                'success' => true,
                'files' => $files
            ]);
        } catch (\Throwable $e) {
            return $this->error('MLS', $e);
        }
    }

    public function st(Request $request): JsonResponse
    {
        try {
            $limit = (int) max(1, min($request->get('limit', 100), 500));
            $search = $request->get('search');

            $query = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->select([
                    'id',
                    DB::raw('fileno as file_number'),
                    DB::raw('fileno as mlsFNo'),
                    DB::raw("COALESCE(corporate_name, NULLIF(LTRIM(RTRIM(ISNULL(first_name, '') + ' ' + ISNULL(surname, ''))), ''), applicant_type) as FileName"),
                    DB::raw("'ST Registry' as location")
                ])
                ->whereNotNull('fileno')
                ->where('fileno', '!=', '')
                ->whereIn('status', ['ACTIVE', 'USED', 'RESERVED']);

            if (!empty($search)) {
                $query->where('fileno', 'LIKE', '%' . $search . '%');
            }

            return response()->json([
                'success' => true,
                'files' => $query->orderBy('id', 'desc')->limit($limit)->get()
            ]);
        } catch (\Throwable $e) {
            return $this->error('ST', $e);
        }
    }


    /**
     * Fetch legacy KANGIS file numbers.
     */
    public function kangis(Request $request): JsonResponse
    {
        try {
            $limit = (int) max(1, min($request->get('limit', 100), 500));
            $search = $request->get('search');
            $excludeMatched = $request->get('exclude_matched');

            $query = DB::connection('sqlsrv')
                ->table('dbo.fileNumber')
                ->select([
                    'id',
                    DB::raw('kangisFileNo as file_number'),
                    'FileName',
                    'location',
                    'plot_no',
                    'tp_no'
                ])
                ->whereNotNull('kangisFileNo')
                ->where('kangisFileNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                // Only surface genuine KANGIS-family file numbers. The kangisFileNo
                // column also holds leaked MLS-format values (RES-/COM-/AG-/CON-/IND-/
                // SLTR-…) which must never appear in the KANGIS selector.
                ->where(function ($q) {
                    $q->whereRaw("UPPER(LTRIM(kangisFileNo)) LIKE 'KN%'")
                      ->orWhereRaw("UPPER(LTRIM(kangisFileNo)) LIKE 'MLKN%'");
                });

            // Exclude matching if requested
            if (!empty($excludeMatched)) {
                switch (strtolower($excludeMatched)) {
                    case 'lands':
                        $query->where(function($q) {
                            $q->whereNull('pp_lands_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_lands_matching', '!=', 1)
                                       ->where('pp_lands_matching', '!=', '1');
                              });
                        });
                        break;
                    case 'st':
                        $query->where(function($q) {
                            $q->whereNull('pp_st_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_st_matching', '!=', 1)
                                       ->where('pp_st_matching', '!=', '1');
                              });
                        });
                        break;
                    case 'sltr':
                        $query->where(function($q) {
                            $q->whereNull('pp_sltr_matching')
                              ->orWhere(function ($q2) {
                                    $q2->where('pp_sltr_matching', '!=', 1)
                                       ->where('pp_sltr_matching', '!=', '1');
                              });
                        });
                        break;
                }
            }

            if (!empty($search)) {
                $this->applyFileNumberSearch($query, 'kangisFileNo', $search);
            }

            $rows = $query
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'files' => $rows->map(fn($r) => [
                    'kangisFileNo' => $r->file_number, // Changed from $r->kangisFileNo
                    'kangis_file_no' => $r->file_number, // Changed from $r->kangis_file_no
                    'id' => $r->id,
                    'FileName' => $r->FileName,
                    'file_name' => $r->FileName,
                    'location' => $r->location,
                    'plot_no' => $r->plot_no,
                    'tp_no' => $r->tp_no,
                ])
            ]);
        } catch (\Throwable $e) {
            return $this->error('KANGIS', $e);
        }
    }

    /**
     * Fetch new KANGIS file numbers.
     */
    public function newKangis(Request $request): JsonResponse
    {
        try {
            $limit = (int) max(1, min($request->get('limit', 100), 500));
            $search = $request->get('search');

            // KN-format numbers (KN1, KN2, …) live in kn_grouping.kn_awaiting_fileno.
            // Join file_tracker so the modal can surface the tracking_id immediately.
            $query = DB::connection('sqlsrv')
                ->table('kn_grouping as kg')
                ->leftJoin('file_tracker as ft', 'ft.file_number', '=', 'kg.kn_awaiting_fileno')
                ->select([
                    'kg.id',
                    DB::raw('kg.kn_awaiting_fileno as file_number'),
                    'ft.tracking_id',
                    'ft.file_title',
                ])
                ->whereNotNull('kg.kn_awaiting_fileno')
                ->where('kg.kn_awaiting_fileno', '!=', '');

            if (!empty($search)) {
                $query->where('kg.kn_awaiting_fileno', 'LIKE', '%' . $search . '%');
            }

            $rows = $query
                ->orderByDesc('kg.id')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'files' => $rows->map(fn($r) => [
                    'id'                 => $r->id,
                    'file_number'        => $r->file_number,
                    // Field aliases the modal JS checks for the 'newkangis' tab:
                    'NewKANGISFileNo'    => $r->file_number,
                    'new_kangis_file_no' => $r->file_number,
                    'tracking_id'        => $r->tracking_id,
                    'file_title'         => $r->file_title,
                ])
            ]);
        } catch (\Throwable $e) {
            return $this->error('NEWKANGIS', $e);
        }
    }

    /**
     * Get all ST file numbers from st_file_numbers table
     * Global API endpoint for retrieving file numbers in JSON format
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSTFileNumbers(Request $request): JsonResponse
    {
        try {
            \Log::info('ST File Numbers API called', ['params' => $request->all()]);

            // Base query - simple approach without user join for now
            $query = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->leftJoin('subapplications', 'st_file_numbers.fileno', '=', 'subapplications.fileno')
                ->select([
                    'st_file_numbers.id',
                    'st_file_numbers.np_fileno',
                    'st_file_numbers.fileno',
                    'st_file_numbers.mls_fileno',
                    'st_file_numbers.land_use',
                    'st_file_numbers.land_use_code',
                    'st_file_numbers.serial_no',
                    'st_file_numbers.unit_sequence',
                    'st_file_numbers.year',
                    'st_file_numbers.file_no_type',
                    'st_file_numbers.parent_id',
                    'st_file_numbers.mother_application_id',
                    'st_file_numbers.subapplication_id',
                    'st_file_numbers.buyer_list_id',
                    'st_file_numbers.status',
                    'st_file_numbers.used_at',
                    'st_file_numbers.tra',
                    'st_file_numbers.applicant_type',
                    'st_file_numbers.applicant_title',
                    'st_file_numbers.first_name',
                    'st_file_numbers.middle_name',
                    'st_file_numbers.surname',
                    'st_file_numbers.corporate_name',
                    'st_file_numbers.rc_number',
                    'st_file_numbers.multiple_owners_names',
                    'st_file_numbers.created_by',
                    'st_file_numbers.created_at',
                    'st_file_numbers.updated_at'
                ]);

            // Optionally exclude file numbers already present in subapplications
            if ($request->boolean('only_available', false)) {
                $query->whereNull('subapplications.fileno');

                // Also check mother_applications to be thorough
                $query->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('mother_applications')
                        ->whereColumn('mother_applications.fileno', 'st_file_numbers.fileno');
                });
            }

            // Apply filters if provided
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('st_file_numbers.np_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('st_file_numbers.fileno', 'LIKE', "%{$search}%")
                        ->orWhere('st_file_numbers.mls_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('st_file_numbers.first_name', 'LIKE', "%{$search}%")
                        ->orWhere('st_file_numbers.surname', 'LIKE', "%{$search}%")
                        ->orWhere('st_file_numbers.corporate_name', 'LIKE', "%{$search}%");
                });
            }

            // Filter by land use
            if ($request->has('land_use') && !empty($request->land_use)) {
                $query->where('st_file_numbers.land_use', $request->land_use);
            }

            // Filter by mother_application_id
            if ($request->has('mother_application_id') && !empty($request->mother_application_id)) {
                $query->where('st_file_numbers.mother_application_id', $request->mother_application_id);
            }

            // Filter by np_fileno (can match either mls_fileno or np_fileno in the table)
            if ($request->has('np_fileno') && !empty($request->np_fileno)) {
                $identifiers = explode(',', $request->np_fileno);
                $query->where(function ($q) use ($identifiers) {
                    foreach ($identifiers as $id) {
                        $id = trim($id);
                        if (empty($id)) continue;
                        $q->orWhere('st_file_numbers.np_fileno', $id)
                          ->orWhere('st_file_numbers.mls_fileno', $id);
                    }
                });
            }

            // Filter by year
            if ($request->has('year') && !empty($request->year)) {
                $query->where('st_file_numbers.year', $request->year);
            }

            // Filter by file type
            if ($request->has('file_no_type') && !empty($request->file_no_type)) {
                $query->where('st_file_numbers.file_no_type', $request->file_no_type);
            }

            // Filter by status - supports single value or comma-separated multiple values
            if ($request->has('status') && !empty($request->status)) {
                $status = $request->status;
                if (is_string($status) && strpos($status, ',') !== false) {
                    // Handle comma-separated values like "ACTIVE,RESERVED"
                    $statusArray = array_map('trim', explode(',', $status));
                    $query->whereIn('st_file_numbers.status', $statusArray);
                } else {
                    // Handle single value
                    $query->where('st_file_numbers.status', $status);
                }
            }

            // Filter by applicant type
            if ($request->has('applicant_type') && !empty($request->applicant_type)) {
                $query->where('st_file_numbers.applicant_type', $request->applicant_type);
            }

            // Ordering
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Pagination support
            $limit = $request->get('limit', null);
            if ($limit && is_numeric($limit)) {
                $query->limit($limit);
            }

            // Execute query
            $fileNumbers = $query->get();

            // Get user names for records that have created_by
            $userIds = $fileNumbers->pluck('created_by')->filter()->unique()->toArray();
            $users = [];
            if (!empty($userIds)) {
                $users = DB::connection('sqlsrv')
                    ->table('users')
                    ->whereIn('id', $userIds)
                    ->get()
                    ->keyBy('id');
            }

            // Format response
            $response = [
                'status' => 'success',
                'message' => 'ST File numbers fetched successfully.',
                'count' => $fileNumbers->count(),
                'data' => $fileNumbers->map(function ($item) use ($users) {
                    // Get username if user exists
                    $createdByName = 'System';
                    if ($item->created_by && isset($users[$item->created_by])) {
                        $user = $users[$item->created_by];
                        $createdByName = $user->name ?? ($user->first_name . ' ' . $user->last_name) ?? 'Unknown';
                    }

                    return [
                        'id' => $item->id,
                        'np_fileno' => $item->np_fileno,
                        'fileno' => $item->fileno,
                        'mls_fileno' => $item->mls_fileno,
                        'land_use' => $item->land_use,
                        'land_use_code' => $item->land_use_code,
                        'serial_no' => $item->serial_no,
                        'unit_sequence' => $item->unit_sequence,
                        'year' => $item->year,
                        'file_no_type' => $item->file_no_type,
                        'parent_id' => $item->parent_id,
                        'mother_application_id' => $item->mother_application_id,
                        'subapplication_id' => $item->subapplication_id,
                        'buyer_list_id' => $item->buyer_list_id,
                        'status' => $item->status,
                        'used_at' => $item->used_at,
                        'tra' => $item->tra,
                        'applicant_type' => $item->applicant_type,
                        'applicant_title' => $item->applicant_title,
                        'first_name' => $item->first_name,
                        'middle_name' => $item->middle_name,
                        'surname' => $item->surname,
                        'corporate_name' => $item->corporate_name,
                        'rc_number' => $item->rc_number,
                        'multiple_owners_names' => $item->multiple_owners_names,
                        'created_by' => $item->created_by,
                        'created_by_name' => $createdByName,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        // Computed fields for easier frontend usage
                        'display_name' => $this->getDisplayName($item),
                        'full_file_number' => $this->getFullFileNumber($item)
                    ];
                })
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('ST File Numbers API Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to load ST File numbers: ' . $e->getMessage(),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Get summary statistics for ST file numbers
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSTFileNumberStats(): JsonResponse
    {
        try {
            $stats = DB::connection('sqlsrv')->table('st_file_numbers')
                ->selectRaw("
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN file_no_type = 'PRIMARY' THEN 1 END) as primary_count,
                    COUNT(CASE WHEN file_no_type = 'SUA' THEN 1 END) as sua_count,
                    COUNT(CASE WHEN file_no_type = 'PUA' THEN 1 END) as pua_count,
                    COUNT(CASE WHEN land_use = 'RESIDENTIAL' THEN 1 END) as residential_count,
                    COUNT(CASE WHEN land_use = 'COMMERCIAL' THEN 1 END) as commercial_count,
                    COUNT(CASE WHEN land_use = 'INDUSTRY' THEN 1 END) as industry_count,
                    COUNT(CASE WHEN land_use = 'MIXED-USE' THEN 1 END) as mixed_use_count,
                    COUNT(CASE WHEN status = 'generated' THEN 1 END) as generated_count,
                    COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved_count,
                    MAX(year) as latest_year,
                    MIN(year) as earliest_year
                ")
                ->first();

            return response()->json([
                'status' => 'success',
                'message' => 'ST File number statistics fetched successfully.',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->error('ST File Number Stats', $e);
        }
    }

    /**
     * Get unique values for dropdown populations
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSTDropdownData(): JsonResponse
    {
        try {
            $data = [
                'land_uses' => DB::connection('sqlsrv')->table('st_file_numbers')
                    ->select('land_use')
                    ->distinct()
                    ->whereNotNull('land_use')
                    ->pluck('land_use'),

                'years' => DB::connection('sqlsrv')->table('st_file_numbers')
                    ->select('year')
                    ->distinct()
                    ->whereNotNull('year')
                    ->orderBy('year', 'desc')
                    ->pluck('year'),

                'file_types' => DB::connection('sqlsrv')->table('st_file_numbers')
                    ->select('file_no_type')
                    ->distinct()
                    ->whereNotNull('file_no_type')
                    ->pluck('file_no_type'),

                'statuses' => DB::connection('sqlsrv')->table('st_file_numbers')
                    ->select('status')
                    ->distinct()
                    ->whereNotNull('status')
                    ->pluck('status'),

                'applicant_types' => DB::connection('sqlsrv')->table('st_file_numbers')
                    ->select('applicant_type')
                    ->distinct()
                    ->whereNotNull('applicant_type')
                    ->pluck('applicant_type')
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'ST Dropdown data fetched successfully.',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return $this->error('ST Dropdown Data', $e);
        }
    }

    /**
     * Get unit file numbers for PHS unit selection dropdown
     * This should return units with their actual commissioning status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitsForDropdown(Request $request): JsonResponse
    {
        try {
            $parentFileNo = $request->get('parent_file_no', 'RES-1982-2081');
            
            // Get unit file numbers with their actual commissioning status
            $units = DB::connection('sqlsrv')->table('st_file_numbers')
                ->select([
                    'id',
                    'fileno',
                    'np_fileno', 
                    'first_name',
                    'surname',
                    'corporate_name',
                    'applicant_type',
                    'status',
                    'date_commissioned',
                    'used_at'
                ])
                ->where('np_fileno', $parentFileNo)
                ->whereIn('file_no_type', ['PUA', 'SUA'])
                ->orderBy('unit_sequence')
                ->get();

            $formattedUnits = $units->map(function ($unit) {
                // Determine commissioning status based on database fields
                $isCommissioned = !empty($unit->date_commissioned) || 
                                (!empty($unit->used_at) && $unit->status === 'USED');
                
                // Get applicant name
                $applicantName = '';
                if ($unit->applicant_type === 'Corporate') {
                    $applicantName = $unit->corporate_name ?: 'Corporate Applicant';
                } else {
                    $applicantName = trim(($unit->first_name ?: '') . ' ' . ($unit->surname ?: ''));
                    if (empty($applicantName)) {
                        $applicantName = 'Individual Applicant';
                    }
                }
                
                return [
                    'id' => $unit->id,
                    'fileno' => $unit->fileno,
                    'np_fileno' => $unit->np_fileno,
                    'applicant_name' => $applicantName,
                    'applicant_type' => $unit->applicant_type,
                    'status' => $unit->status,
                    'is_commissioned' => $isCommissioned,
                    'date_commissioned' => $unit->date_commissioned,
                    'display_text' => $unit->fileno . ' - ' . $applicantName . ' - ' . 
                                    ($isCommissioned ? 'Commissioned' : 'Not Commissioned')
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Unit file numbers fetched successfully.',
                'data' => $formattedUnits,
                'parent_file_no' => $parentFileNo
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching units for dropdown: ' . $e->getMessage(), [
                'parent_file_no' => $request->get('parent_file_no'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching unit file numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get display name for applicant
     * 
     * @param object $item
     * @return string
     */
    private function getDisplayName($item): string
    {
        if ($item->applicant_type === 'Corporate' && $item->corporate_name) {
            return $item->corporate_name;
        } elseif ($item->applicant_type === 'Multiple' && $item->multiple_owners_names) {
            return $item->multiple_owners_names;
        } else {
            $name = trim(($item->first_name ?? '') . ' ' . ($item->middle_name ?? '') . ' ' . ($item->surname ?? ''));
            return $name ?: 'N/A';
        }
    }

    /**
     * Helper method to get the most relevant file number for display
     * 
     * @param object $item
     * @return string
     */
    private function getFullFileNumber($item): string
    {
        if ($item->mls_fileno) {
            return $item->mls_fileno;
        } elseif ($item->fileno) {
            return $item->fileno;
        } else {
            return $item->np_fileno;
        }
    }

    /**
     * Build the base query for dbo.fileNumber with consistent joins and selects.
     */
    private function baseFileNumberQuery(bool $withJoin = true)
    {
        $query = DB::connection('sqlsrv')
            ->table('fileNumber as fn');

        if ($withJoin) {
            $query->leftJoin('mother_applications as ma', 'ma.id', '=', 'fn.application_id');
            $query->leftJoin('users as creator_users', function ($join) {
                $join->on('creator_users.id', '=', DB::raw('TRY_CONVERT(INT, fn.created_by)'));
            });
            // Join file_indexings to get plot_number, tp_no, lga, district (latest record per file)
            $query->leftJoinSub(
                DB::connection('sqlsrv')->table('file_indexings')
                    ->select([
                        'file_number',
                        DB::raw('MAX(id) as max_id'),
                    ])
                    ->groupBy('file_number'),
                'fi_max',
                'fi_max.file_number',
                '=',
                'fn.mlsfNo'
            )->leftJoin('file_indexings as fi', 'fi.id', '=', 'fi_max.max_id');
        }

        $selects = [
            'fn.id',
            'fn.application_id',
            'fn.mlsfNo',
            'fn.kangisFileNo',
            'fn.NewKANGISFileNo',
            'fn.FileName',
            'fn.created_at',
            'fn.updated_at',
            'fn.location',
            'fn.lga',
            'fn.created_by',
            'fn.updated_by',
            'fn.type',
            'fn.is_deleted',
            'fn.SOURCE',
            'fn.commissioning_date',
            'fn.decommissioning_date',
            'fn.decommissioning_reason',
            'fn.is_decommissioned',
            'fn.temp_fileno',
            'fn.temp_file_no',
            'fn.plot_no',
            'fn.tp_no',
            'fn.sub_application_id',
            'fn.st_file_no',
            'fn.tracking_id',
            'fn.phone_no',
            'fn.address',
            'fn.rep_phone_no',
            'fn.rep_address',
        ];

        if ($withJoin) {
            $selects[] = 'ma.land_use as ma_land_use';
            $selects[] = 'ma.property_lga as ma_lga';
            $selects[] = 'ma.property_district as ma_district';
            $selects[] = 'ma.property_street_name as ma_street_name';
            $selects[] = 'ma.property_house_no as ma_house_no';
            $selects[] = 'ma.property_plot_no as ma_plot_no';
            $selects[] = 'fi.plot_number as fi_plot_no';
            $selects[] = 'fi.tp_no as fi_tp_no';
            $selects[] = 'fi.lga as fi_lga';
            $selects[] = 'fi.district as fi_district';
            $selects[] = 'fi.street_name as fi_street_name';
            $selects[] = 'fi.file_title as fi_file_title';
            
            $selects[] = DB::raw("LTRIM(RTRIM(CONCAT(ISNULL(creator_users.first_name, ''), CASE WHEN ISNULL(creator_users.last_name, '') = '' THEN '' ELSE ' ' END, ISNULL(creator_users.last_name, '')))) as created_by_name");
        }

        return $query->select($selects)
            ->where(function ($q) {
                $q->whereNull('fn.is_deleted')->orWhere('fn.is_deleted', 0);
            });
    }

    /**
     * Map raw database values into API shape.
     */
    /**
     * Resolve a display location for a file number record.
     *
     * Prefers an explicit location stored on the fileNumber row, then a location
     * from the latest file indexing, and finally assembles one from the mother
     * application's property parts (house no, street, district) so files that are
     * not yet indexed still backfill a usable location.
     */
    /**
     * First value that is neither null nor blank, else null.
     */
    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function resolveLocation(array $record): ?string
    {
        $explicit = trim((string) ($record['location'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $parts = array_values(array_filter([
            $record['ma_house_no'] ?? null,
            $record['ma_street_name'] ?? null,
            $record['ma_district'] ?? null,
        ], fn ($v) => trim((string) $v) !== ''));

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    private function transformFileNumberRecord(array $record): array
    {
        $indexingStatus = $this->resolveIndexingStatus($record);

        return [
            'id' => isset($record['id']) ? (int) $record['id'] : null,
            'application_id' => $record['application_id'] ?? null,
            'mlsf_no' => $record['mlsfNo'] ?? null,
            'st_file_no' => $record['st_file_no'] ?? null,
            'kangis_file_no' => $record['kangisFileNo'] ?? null,
            'new_kangis_file_no' => $record['NewKANGISFileNo'] ?? null,
            'file_name' => $record['FileName'] ?? $record['fi_file_title'] ?? null,
            'location' => $this->resolveLocation($record),
            // Indexing rows often hold '' rather than NULL, so fall through blanks too —
            // otherwise an empty fi_district masks a usable mother-application value.
            'district'    => $this->firstFilled($record['district'] ?? null, $record['fi_district'] ?? null, $record['ma_district'] ?? null),
            'lga'         => $this->firstFilled($record['lga'] ?? null, $record['fi_lga'] ?? null, $record['ma_lga'] ?? null),
            'street_name' => $this->firstFilled($record['fi_street_name'] ?? null, $record['ma_street_name'] ?? null),
            'plot_no'     => $this->firstFilled($record['plot_no'] ?? null, $record['fi_plot_no'] ?? null, $record['ma_plot_no'] ?? null),
            'tp_no'    => $record['tp_no'] ?? $record['fi_tp_no'] ?? null,
            'type' => $record['type'] ?? null,
            'source' => $record['SOURCE'] ?? null,
            'tracking_id' => $record['tracking_id'] ?? null,
            'sub_application_id' => $record['sub_application_id'] ?? null,
            'commissioning_date' => $this->formatDate($record['commissioning_date'] ?? null),
            'decommissioning_date' => $this->formatDate($record['decommissioning_date'] ?? null),
            'decommissioning_reason' => $record['decommissioning_reason'] ?? null,
            'is_decommissioned' => isset($record['is_decommissioned']) ? (bool) $record['is_decommissioned'] : false,
            'is_deleted' => isset($record['is_deleted']) ? (bool) $record['is_deleted'] : false,
            'temp_fileno' => $record['temp_fileno'] ?? null,
            'temp_file_no' => $record['temp_file_no'] ?? null,
            'phone_no' => $record['phone_no'] ?? null,
            'address' => $record['address'] ?? null,
            'rep_phone_no' => $record['rep_phone_no'] ?? null,
            'rep_address' => $record['rep_address'] ?? null,
            'land_use' => $record['ma_land_use'] ?? null,
            'created_by' => $record['created_by'] ?? null,
            'created_by_name' => $record['created_by_name'] ?? null,
            'updated_by' => $record['updated_by'] ?? null,
            'created_at' => $this->formatDate($record['created_at'] ?? null),
            'updated_at' => $this->formatDate($record['updated_at'] ?? null),
            'is_indexed' => $indexingStatus['is_indexed'],
            'file_indexing_id' => $indexingStatus['file_indexing_id'],
            'links' => $this->buildLinks($record),
        ];
    }

    private function resolveIndexingStatus(array $record): array
    {
        $candidates = array_values(array_unique(array_filter([
            $record['mlsfNo'] ?? null,
            $record['st_file_no'] ?? null,
            $record['kangisFileNo'] ?? null,
            $record['NewKANGISFileNo'] ?? null,
        ], fn ($value) => !empty($value))));

        if (empty($candidates)) {
            return ['is_indexed' => false, 'file_indexing_id' => null];
        }

        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->select('id')
            ->where(function ($query) use ($candidates) {
                $query->whereIn('file_number', $candidates)
                    ->orWhereIn('mls_file_no', $candidates)
                    ->orWhereIn('kangis_file_no', $candidates)
                    ->orWhereIn('new_kangis_file_no', $candidates);
            })
            ->orderByDesc('id')
            ->first();

        return [
            'is_indexed' => (bool) $indexing,
            'file_indexing_id' => $indexing->id ?? null,
        ];
    }

    /**
     * Locate a single file number record by a set of criteria.
     */ 



    private function findFileNumber(array $criteria, bool $withJoin = true)
    {
        $query = $this->baseFileNumberQuery($withJoin);

        if (!empty($criteria['tracking_id'])) {
            $query->where('fn.tracking_id', $criteria['tracking_id']);
        }

        if (!empty($criteria['mlsf_no'])) {
            $val = $criteria['mlsf_no'];
            $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $val);
            if ($stripped !== $val) {
                $query->where(function ($q) use ($val, $stripped) {
                    $q->where('fn.mlsfNo', $val)
                      ->orWhere('fn.mlsfNo', $stripped);
                });
            } else {
                $query->where('fn.mlsfNo', $val);
            }
        }

        if (!empty($criteria['st_file_no'])) {
            $val = $criteria['st_file_no'];
            $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $val);
            if ($stripped !== $val) {
                $query->where(function ($q) use ($val, $stripped) {
                    $q->where('fn.st_file_no', $val)
                      ->orWhere('fn.st_file_no', $stripped);
                });
            } else {
                $query->where('fn.st_file_no', $val);
            }
        }

        if (!empty($criteria['kangis_file_no'])) {
            $val = $criteria['kangis_file_no'];
            $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $val);
            if ($stripped !== $val) {
                $query->where(function ($q) use ($val, $stripped) {
                    $q->where('fn.kangisFileNo', $val)
                      ->orWhere('fn.kangisFileNo', $stripped);
                });
            } else {
                $query->where('fn.kangisFileNo', $val);
            }
        }

        if (!empty($criteria['new_kangis_file_no'])) {
            $val = $criteria['new_kangis_file_no'];
            $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $val);
            if ($stripped !== $val) {
                $query->where(function ($q) use ($val, $stripped) {
                    $q->where('fn.NewKANGISFileNo', $val)
                      ->orWhere('fn.NewKANGISFileNo', $stripped);
                });
            } else {
                $query->where('fn.NewKANGISFileNo', $val);
            }
        }

        if (!empty($criteria['file_number'])) {
            $raw = $criteria['file_number'];
            $values = is_array($raw) ? $raw : [$raw];

            // Temporary file number support: "RES-2026-1(T)" should also match "RES-2026-1".
            $expanded = [];
            foreach ($values as $v) {
                $v = (string) $v;
                if ($v === '') {
                    continue;
                }
                $expanded[] = $v;
                $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $v);
                if ($stripped !== null && $stripped !== '' && $stripped !== $v) {
                    $expanded[] = $stripped;
                }
            }
            $expanded = array_values(array_unique(array_filter($expanded)));

            $query->where(function ($q) use ($expanded) {
                $q->whereIn('fn.mlsfNo', $expanded)
                    ->orWhereIn('fn.kangisFileNo', $expanded)
                    ->orWhereIn('fn.NewKANGISFileNo', $expanded)
                    ->orWhereIn('fn.st_file_no', $expanded)
                    ->orWhereIn('fn.tracking_id', $expanded)
                    ->orWhereIn('fn.temp_file_no', $expanded)
                    ->orWhereIn('fn.temp_fileno', $expanded);
            });
        }

        return $query->orderBy('fn.id', 'desc')->first();
    }

    private function buildLinks(array $record): array
    {
        $trackingId = $record['tracking_id'] ?? null;

        return [
            'self' => $trackingId ? route('api.file-numbers.show-tracking', ['trackingId' => $trackingId]) : null,
            'lookup' => $trackingId ? route('api.file-numbers.lookup', ['tracking_id' => $trackingId]) : null,
        ];
    }

    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    private function apiError(string $context, \Throwable $e): JsonResponse
    {
        \Log::error("FileNumber API failed to {$context}", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => ucfirst($context) . ' failed.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }

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

    private function generateTrackingId(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segmentOne = '';
        $segmentTwo = '';
        $length = strlen($characters) - 1;

        for ($i = 0; $i < 8; $i++) {
            $segmentOne .= $characters[random_int(0, $length)];
        }

        for ($i = 0; $i < 5; $i++) {
            $segmentTwo .= $characters[random_int(0, $length)];
        }

        return "TRK-{$segmentOne}-{$segmentTwo}";
    }

    private function error(string $system, \Throwable $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Failed to load $system files: " . $e->getMessage(),
        ], 500);
    }

    /**
     * Apply case-insensitive and normalized search filters to a file number column.
     */
    protected function applyFileNumberSearch($query, string $column, string $search): void
    {
        $normalized = $this->normalizeSearch($search);

        if ($normalized === '') {
            return;
        }

        $upper = strtoupper(trim($search));
        $upperWildcard = "%{$upper}%";
        $normalizedWildcard = "%{$normalized}%";

        $query->where(function ($q) use ($column, $upperWildcard, $normalizedWildcard) {
            $q->whereRaw("UPPER({$column}) LIKE ?", [$upperWildcard])
                ->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$column}), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?",
                    [$normalizedWildcard]
                );
        });

        $orderExpression = sprintf(
            "CASE WHEN UPPER(%1\$s) LIKE ? THEN 0 " .
            "WHEN REPLACE(REPLACE(REPLACE(REPLACE(UPPER(%1\$s), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ? THEN 1 " .
            "ELSE 2 END",
            $column
        );

        $query->orderByRaw($orderExpression, [$upper . '%', $normalizedWildcard]);
    }

    /**
     * Normalize a search string by removing separators and uppercasing.
     */
    protected function normalizeSearch(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return '';
        }

        return str_replace(['-', '/', ' ', '.', '\\'], '', $value);
    }
}

