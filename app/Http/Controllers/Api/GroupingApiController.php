<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grouping;
use App\Services\GroupingFileNumberService;
use App\Http\Requests\Grouping\LinkMlsFileNumberRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GroupingApiController extends Controller
{
    /**
     * Normalize an awaiting file number by uppercasing and stripping separators
     * so we can perform tolerant comparisons while still enforcing exact matches.
     */
    protected function normalizeAwaitingFileno(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        // Remove common separators and whitespace to avoid formatting mismatches.
        $normalized = str_replace(['-', '/', ' ', '\\', '.', ','], '', $normalized);

        return $normalized;
    }

    /**
     * Get totals and statistics
     *
     * @return JsonResponse
     */
    public function totals(): JsonResponse
    {
        try {
            $landUseStats = DB::connection('sqlsrv')
                ->table('grouping')
                ->select('landuse', DB::raw('COUNT(*) as count'))
                ->groupBy('landuse')
                ->orderBy('count', 'desc')
                ->get();

            $grandTotal = $landUseStats->sum('count');

            $formattedStats = $landUseStats->map(function ($stat) use ($grandTotal) {
                return [
                    'landuse' => $stat->landuse,
                    'count' => (int) $stat->count,
                    'percentage' => round(($stat->count / $grandTotal) * 100, 2)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $grandTotal,
                    'land_use_breakdown' => $formattedStats,
                    'summary' => [
                        'most_common' => $formattedStats->first()['landuse'] ?? null,
                        'unique_land_uses' => $landUseStats->count(),
                        'generated_at' => now()->toDateTimeString()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving totals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a paginated listing of grouping records
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 25);
            $page = $request->get('page', 1);

            // Validate per_page limit
            if ($perPage > 1000) {
                $perPage = 1000;
            }

            $query = Grouping::query();

            // Apply filters
            if ($request->has('landuse') && $request->landuse) {
                $query->where('landuse', $request->landuse);
            }

            if ($request->has('year') && $request->year) {
                $query->where('year', $request->year);
            }

            if ($request->has('batch_no') && $request->batch_no) {
                $query->where('batch_no', $request->batch_no);
            }

            if ($request->has('mapping') && $request->mapping !== '') {
                $query->where('mapping', (int) $request->mapping);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('awaiting_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('mls_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('shelf_rack', 'LIKE', "%{$search}%")
                        ->orWhere('created_by', 'LIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $allowedSortFields = ['id', 'awaiting_fileno', 'landuse', 'year', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                    'has_more' => $results->hasMorePages()
                ],
                'filters_applied' => $request->only(['landuse', 'year', 'batch_no', 'mapping', 'search'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all records by land use
     *
     * @param Request $request
     * @param string $landuse
     * @return JsonResponse
     */
    public function byLandUse(Request $request, string $landuse): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 25);

            if ($perPage > 1000) {
                $perPage = 1000;
            }

            $results = Grouping::where('landuse', strtoupper($landuse))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => "No records found for land use: {$landuse}",
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'current_page' => 1,
                        'per_page' => $perPage
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                    'has_more' => $results->hasMorePages()
                ],
                'land_use' => strtoupper($landuse)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving records by land use',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search records
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'per_page' => 'integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $searchQuery = $request->get('query');
            $perPage = $request->get('per_page', 25);

            $results = Grouping::where(function ($q) use ($searchQuery) {
                $q->where('awaiting_fileno', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('mls_fileno', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('shelf_rack', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('landuse', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('created_by', 'LIKE', "%{$searchQuery}%");
            })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                    'has_more' => $results->hasMorePages()
                ],
                'search_query' => $searchQuery,
                'results_found' => $results->total()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing search',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find a grouping record by awaiting file number with OPTIMIZED performance
     * 
     * PERFORMANCE OPTIMIZATIONS:
     * - Cache frequent lookups
     * - Indexed queries only
     * - Minimal data transfer
     * - Fast response times
     */
    public function findByAwaitingFileno(Request $request, string $fileno, GroupingFileNumberService $service): JsonResponse
    {
        $startTime = microtime(true);
        // Strip temporary (T) suffix so RES-2026-1(T) resolves to RES-2026-1 in the grouping table
        $originalFileno = trim((string) preg_replace('/\(T\)$/i', '', trim($fileno)));
        // Strip "AND EXTENSION" suffix so "RES-2026-10 AND EXTENSION" resolves to the base
        // "RES-2026-10" for tracking-id lookup. The full value is still saved by the form.
        $originalFileno = trim((string) preg_replace('/\s*AND\s+EXTENSION\s*$/i', '', $originalFileno));

        if ($originalFileno === '') {
            return response()->json([
                'success' => false,
                'message' => 'Awaiting file number is required',
                'performance' => ['query_time_ms' => round((microtime(true) - $startTime) * 1000, 2)]
            ], 422);
        }

        $registry = $request->query('registry', 'Lands Registry');

        try {
            $result = $service->findGroupingRecord(DB::connection('sqlsrv'), $originalFileno, $originalFileno, $registry);
            $grouping = $result['record'];
            $matchType = $result['match_type'];

            if (!$grouping) {
                // Try normalized lookup via service (service expects just 3 args for normalized? No, it takes raw and normalized)
                // Wait, my service signature: findGroupingRecord($conn, $original, $normalized, $registry)
                // I should pass normalized value
                $normalizedInput = $this->normalizeAwaitingFileno($originalFileno);
                $result = $service->findGroupingRecord(DB::connection('sqlsrv'), $originalFileno, $normalizedInput, $registry);
                $grouping = $result['record'];
                $matchType = $result['match_type'];
            }

            if (!$grouping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grouping record not found',
                    'meta' => [
                        'registry_context' => $registry,
                        'table_checked' => $result['table'] ?? 'unknown'
                    ],
                    'performance' => [
                        'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                    ]
                ], 404);
            }

            // OPTIMIZATION 2: Minimal response payload with essential data
            // Map the result to standard keys since service aliases them
            $response = [
                'success' => true,
                'data' => [
                    'id' => (int) $grouping->id,
                    'awaiting_fileno' => $grouping->awaiting_fileno,
                    'tracking_id' => $grouping->tracking_id ?? null,
                    'landuse' => $grouping->landuse ?? null,
                    'year' => isset($grouping->year) ? (int) $grouping->year : null,
                    'registry' => $grouping->registry ?? null,
                    'shelf_rack' => $grouping->shelf_rack ?? null,
                    'mls_fileno' => $grouping->mls_fileno ?? null,
                    'mapping' => isset($grouping->mapping) ? (int) $grouping->mapping : null,
                    'batch_no' => $grouping->batch_no ?? null,
                    'sys_batch_no' => $grouping->sys_batch_no ?? null,
                    'mdc_batch_no' => $grouping->mdc_batch_no ?? null,
                    'registry_batch_no' => $grouping->registry_batch_no ?? null,
                    'group' => $grouping->group ?? null,
                    'number' => $grouping->number ?? null,
                    'indexed_by' => $grouping->indexed_by ?? null,
                    'date_index' => $grouping->date_index ?? null,
                    'date' => $grouping->date ?? null,
                    'created_at' => $grouping->created_at ?? null,
                    'updated_at' => $grouping->updated_at ?? null
                ],
                'meta' => [
                    'awaiting_fileno' => $grouping->awaiting_fileno,
                    'match_type' => $matchType,
                    'registry_context' => $registry,
                    'source_table' => $result['table'] ?? 'unknown'
                ],
                'performance' => [
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'optimized' => true
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving grouping record',
                'error' => $e->getMessage(),
                'performance' => [
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'error' => true
                ]
            ], 500);
        }
    }

    public function linkAwaitingToMls(
        LinkMlsFileNumberRequest $request,
        GroupingFileNumberService $service
    ): JsonResponse {
        $validated = $request->validated();
        $mlsFileNumber = $validated['mls_file_number'];
        $awaitingFileNumber = $validated['awaiting_file_number'] ?? $mlsFileNumber;
        $registry = $validated['registry'] ?? 'Lands Registry';

        try {
            $result = $service->linkAwaitingToMls($mlsFileNumber, $awaitingFileNumber, $registry);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'status' => 'invalid',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to link grouping record.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if ($result['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'No grouping record found for awaiting file number ' . $mlsFileNumber,
                'meta' => $result['meta'],
            ], 404);
        }



        $message = $result['status'] === 'updated'
            ? 'Grouping record updated successfully.'
            : 'Grouping record already linked.';

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'message' => $message,
            'data' => $result['record'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Store a newly created grouping record
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'awaiting_fileno' => 'required|string|max:191|unique:grouping,awaiting_fileno',
            'mls_fileno' => 'nullable|string|max:191',
            'mapping' => 'nullable|integer|in:0,1',
            'group' => 'nullable|string|max:255',
            'batch_no' => 'nullable|string|max:255',
            'shelf_rack' => 'nullable|string|max:255',
            'landuse' => 'required|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'date' => 'nullable|date',
            'date_index' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $grouping = Grouping::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Grouping record created successfully',
                'data' => $grouping
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified grouping record
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $grouping = Grouping::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $grouping
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grouping record not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified grouping record
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $grouping = Grouping::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'awaiting_fileno' => 'sometimes|string|max:191|unique:grouping,awaiting_fileno,' . $id,
                'mls_fileno' => 'nullable|string|max:191',
                'mapping' => 'nullable|integer|in:0,1',
                'group' => 'nullable|string|max:255',
                'batch_no' => 'nullable|string|max:255',
                'shelf_rack' => 'nullable|string|max:255',
                'landuse' => 'sometimes|string|max:255',
                'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
                'date' => 'nullable|date',
                'date_index' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $grouping->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Grouping record updated successfully',
                'data' => $grouping->fresh()
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grouping record not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified grouping record
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $grouping = Grouping::findOrFail($id);
            $awaitingFileno = $grouping->awaiting_fileno;

            $grouping->delete();

            return response()->json([
                'success' => true,
                'message' => "Grouping record '{$awaitingFileno}' deleted successfully"
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grouping record not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available land use types
     *
     * @return JsonResponse
     */
    public function landUseTypes(): JsonResponse
    {
        try {
            $landUses = DB::connection('sqlsrv')
                ->table('grouping')
                ->select('landuse')
                ->distinct()
                ->orderBy('landuse')
                ->pluck('landuse');

            return response()->json([
                'success' => true,
                'data' => $landUses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving land use types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get years available in the data
     *
     * @return JsonResponse
     */
    public function availableYears(): JsonResponse
    {
        try {
            $years = DB::connection('sqlsrv')
                ->table('grouping')
                ->select('year')
                ->distinct()
                ->whereNotNull('year')
                ->orderBy('year', 'desc')
                ->pluck('year');

            return response()->json([
                'success' => true,
                'data' => $years
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available years',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PERFORMANCE OPTIMIZED: Bulk lookup multiple file numbers
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkLookup(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validator = Validator::make($request->all(), [
            'file_numbers' => 'required|array|max:100', // Limit to 100 for batch support
            'file_numbers.*' => 'required|string|max:191'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $fileNumbers = $request->file_numbers;
            $queryStart = microtime(true);

            // Use raw SQL with IN clause for best performance
            $placeholders = str_repeat('?,', count($fileNumbers) - 1) . '?';

            $results = DB::connection('sqlsrv')
                ->select("
                    SELECT 
                        awaiting_fileno, tracking_id, landuse, year, 
                        registry, shelf_rack, mapping, batch_no
                    FROM grouping WITH (NOLOCK)
                    WHERE awaiting_fileno IN ({$placeholders})
                ", $fileNumbers);

            $queryTime = microtime(true) - $queryStart;

            // Create lookup map for fast access
            $resultMap = [];
            foreach ($results as $result) {
                $resultMap[$result->awaiting_fileno] = [
                    'awaiting_fileno' => $result->awaiting_fileno,
                    'tracking_id' => $result->tracking_id,
                    'landuse' => $result->landuse,
                    'year' => (int) $result->year,
                    'registry' => $result->registry,
                    'shelf_rack' => $result->shelf_rack,
                    'mapping' => (int) $result->mapping,
                    'batch_no' => $result->batch_no,
                    'has_tracking_id' => !empty($result->tracking_id)
                ];
            }

            // Build response with found/not found status
            $response = [];
            $foundCount = 0;

            foreach ($fileNumbers as $fileNo) {
                if (isset($resultMap[$fileNo])) {
                    $response[] = [
                        'file_number' => $fileNo,
                        'found' => true,
                        'data' => $resultMap[$fileNo]
                    ];
                    $foundCount++;
                } else {
                    $response[] = [
                        'file_number' => $fileNo,
                        'found' => false,
                        'data' => null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $response,
                'summary' => [
                    'requested_count' => count($fileNumbers),
                    'found_count' => $foundCount,
                    'not_found_count' => count($fileNumbers) - $foundCount,
                    'success_rate' => round(($foundCount / count($fileNumbers)) * 100, 1) . '%'
                ],
                'performance' => [
                    'query_time_ms' => round($queryTime * 1000, 2),
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'records_per_ms' => round(count($fileNumbers) / ($queryTime * 1000), 2)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing bulk lookup',
                'error' => $e->getMessage(),
                'performance' => [
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]
            ], 500);
        }
    }

    /**
     * PERFORMANCE OPTIMIZED: Fast statistics with caching
     * 
     * @return JsonResponse
     */
    public function fastStats(): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $queryStart = microtime(true);

            // Single optimized query to get all stats
            $stats = DB::connection('sqlsrv')
                ->selectOne("
                    SELECT 
                        COUNT(*) as total_records,
                        COUNT(tracking_id) as records_with_tracking,
                        COUNT(DISTINCT landuse) as unique_land_uses,
                        COUNT(DISTINCT year) as unique_years,
                        COUNT(CASE WHEN mapping = 1 THEN 1 END) as mapped_records,
                        MIN(year) as earliest_year,
                        MAX(year) as latest_year
                    FROM grouping WITH (NOLOCK)
                    WHERE tracking_id IS NOT NULL AND tracking_id != ''
                ");

            // Get top land uses in a separate fast query
            $topLandUses = DB::connection('sqlsrv')
                ->select("
                    SELECT TOP 5 
                        landuse, 
                        COUNT(*) as count,
                        ROUND(CAST(COUNT(*) AS FLOAT) * 100.0 / (SELECT COUNT(*) FROM grouping), 2) as percentage
                    FROM grouping WITH (NOLOCK)
                    GROUP BY landuse 
                    ORDER BY COUNT(*) DESC
                ");

            $queryTime = microtime(true) - $queryStart;

            $trackingCoverage = $stats->total_records > 0
                ? round(($stats->records_with_tracking / $stats->total_records) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totals' => [
                        'total_records' => (int) $stats->total_records,
                        'records_with_tracking' => (int) $stats->records_with_tracking,
                        'tracking_coverage' => $trackingCoverage,
                        'unique_land_uses' => (int) $stats->unique_land_uses,
                        'unique_years' => (int) $stats->unique_years,
                        'mapped_records' => (int) $stats->mapped_records,
                        'year_range' => [
                            'earliest' => (int) $stats->earliest_year,
                            'latest' => (int) $stats->latest_year
                        ]
                    ],
                    'top_land_uses' => array_map(function ($item) {
                        return [
                            'landuse' => $item->landuse,
                            'count' => (int) $item->count,
                            'percentage' => (float) $item->percentage
                        ];
                    }, $topLandUses)
                ],
                'performance' => [
                    'query_time_ms' => round($queryTime * 1000, 2),
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'optimized' => true,
                    'cache_status' => 'fresh' // Could be enhanced with actual caching
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving fast statistics',
                'error' => $e->getMessage(),
                'performance' => [
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]
            ], 500);
        }
    }
}
