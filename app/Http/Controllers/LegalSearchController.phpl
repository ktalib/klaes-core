<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LegalSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\FileIndexing;
use App\Models\FileTracker;

class LegalSearchController extends Controller
{
    protected LegalSearchService $searchService;

    protected string $pageTitle = 'Legal Search - Official (for filing purpose)';
    protected string $viewPrefix = 'legal_search';
    protected string $searchRouteName = 'legalsearch.search';
    protected string $watermarkText = 'FOR OFFICE USE ONLY';
    protected string $printTemplateRouteName = 'legal_search.print.official';
    protected string $printManagerDocType = '';

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    protected function moduleConfig(): array
    {
        return [
            'pageTitle' => $this->pageTitle,
            'viewPrefix' => $this->viewPrefix,
            'searchRouteName' => $this->searchRouteName,
            'watermarkText' => $this->watermarkText,
            'printTemplateRouteName' => $this->printTemplateRouteName,
            'printManagerDocType' => $this->printManagerDocType,
        ];
    }

    public function index()
    {
        $PageTitle = $this->pageTitle;
        $PageDescription = '';
        $moduleConfig = $this->moduleConfig();
        $landUseOptions = DB::connection('sqlsrv')->table('land_uses')->pluck('landuse')->toArray();
        $districtOptions = DB::connection('sqlsrv')->table('districts')->pluck('name')->toArray();
        $instrumentTypeOptions = DB::connection('sqlsrv')->table('InstrumentTypes')
            ->where('IsActive', 1)
            ->orderBy('InstrumentName')
            ->pluck('InstrumentName')
            ->toArray();
        return view($this->viewPrefix . '.index', compact('PageTitle', 'PageDescription', 'moduleConfig', 'landUseOptions', 'districtOptions', 'instrumentTypeOptions'));
    }

    /**
     * Aggregate real search-log data to power the dashboard cards, trend chart and recent activity feed.
     */
    public function dashboardStats()
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();

        $weeklyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            $count = \App\Models\LegalSearchLog::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $weeklyTrend[] = [
                'week' => $weekStart->format('M j'),
                'searches' => $count,
            ];
        }

        $thisMonthLogs = \App\Models\LegalSearchLog::where('created_at', '>=', $startOfMonth);
        $totalThisMonth = (clone $thisMonthLogs)->count();
        $foundThisMonth = (clone $thisMonthLogs)->where('result_status', 'Found')->count();
        $successRate = $totalThisMonth > 0 ? round(($foundThisMonth / $totalThisMonth) * 100) : 0;
        $printedThisMonth = (clone $thisMonthLogs)->where('printed', true)->count();

        $mostCommonType = (clone $thisMonthLogs)
            ->select('search_parameter', DB::raw('COUNT(*) as total'))
            ->whereNotNull('search_parameter')
            ->groupBy('search_parameter')
            ->orderByDesc('total')
            ->first();

        $recentActivity = \App\Models\LegalSearchLog::with('user')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($log) {
                return [
                    'title' => trim(($log->search_parameter ?: 'Search') . ($log->search_value ? ' - ' . $log->search_value : '')),
                    'time' => $log->created_at ? $log->created_at->diffForHumans() : '-',
                    'status' => $log->result_status ?: 'Not Found',
                ];
            });

        return response()->json([
            'weekly_trend' => $weeklyTrend,
            'stats' => [
                'total_this_month' => $totalThisMonth,
                'success_rate' => $successRate,
                'most_common_type' => $mostCommonType->search_parameter ?? '-',
                'printed_this_month' => $printedThisMonth,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }

    public function search(Request $request)
    {
        $results = $this->searchService->search($request->all());

        $searchParam = null;
        $searchValue = null;

        $mappings = [
            'query' => 'File Number',
            'guarantorName' => 'Party 1',
            'guaranteeName' => 'Party 2',
            'lga' => 'LGA',
            'district' => 'District',
            'location' => 'Location',
            'plotNumber' => 'Plot Number',
            'planNumber' => 'Plan Number',
            'size' => 'Size',
            'caveat' => 'Caveat',
        ];

        foreach ($mappings as $key => $label) {
            if ($request->filled($key)) {
                $searchParam = $label;
                $searchValue = $request->input($key);
                break;
            }
        }

        if ($searchParam) {
            $totalCount = $results['total_count'] ?? 0;
            $resultStatus = $totalCount > 0 ? 'Found' : 'Not Found';

            // Collect query params for direct link
            $queryParams = $request->only(array_keys($mappings));
            // Filter out empty params
            $queryParams = array_filter($queryParams, function ($val) {
                return !empty($val);
            });
            $directLink = route('legal_search.index', $queryParams);

            \App\Models\LegalSearchLog::create([
                'user_id' => auth()->id(),
                'search_parameter' => $searchParam,
                'search_value' => $searchValue,
                'result_status' => $resultStatus,
                'results_count' => $totalCount,
                'lga' => $request->input('lga'),
                'receipt_no' => $request->input('token'),
                'printed' => false,
                'direct_link' => $directLink,
                'search_source' => $this->printTemplateRouteName,
            ]);
        }

        return response()->json($results);
    }

    public function report()
    {
        return view($this->viewPrefix . '.report');
    }

    public function legal_search_report()
    {
        $PageTitle = 'Legal Search Report';
        $moduleConfig = $this->moduleConfig();
        return view($this->viewPrefix . '.report', compact('PageTitle', 'moduleConfig'));
    }

    public function online()
    {
        return response()->file(base_path('docs/templates/online.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function onlineSearch(Request $request)
    {
        $results = $this->searchService->search($request->all());

        $mappings = [
            'query' => 'File Number',
            'guarantorName' => 'Party 1',
            'guaranteeName' => 'Party 2',
            'lga' => 'LGA',
            'district' => 'District',
            'location' => 'Location',
            'plotNumber' => 'Plot Number',
            'planNumber' => 'Plan Number',
            'size' => 'Size',
            'caveat' => 'Caveat',
        ];

        $searchParam = null;
        $searchValue = null;
        foreach ($mappings as $key => $label) {
            if ($request->filled($key)) {
                $searchParam = $label;
                $searchValue = $request->input($key);
                break;
            }
        }

        if ($searchParam) {
            $totalCount = $results['total_count'] ?? 0;
            \App\Models\LegalSearchLog::create([
                'user_id' => auth()->id(),
                'search_parameter' => $searchParam,
                'search_value' => $searchValue,
                'result_status' => $totalCount > 0 ? 'Found' : 'Not Found',
                'results_count' => $totalCount,
                'lga' => $request->input('lga'),
                'printed' => false,
                'search_source' => 'legal_search.print.online',
            ]);
        }

        return response()->json($results);
    }

    public function printTemplateOfficial()
    {
        return response()->file(resource_path('views/legal_search/templates/OFFICIAL SEARCH REPORT.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function printTemplateOnpremise()
    {
        return response()->file(resource_path('views/legal_search/templates/PAY-PER-SEARCH.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function printTemplateOnline()
    {
        return response()->file(resource_path('views/legal_search/templates/ONLINE.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }


    /**
     * Return archived file folders linked to a searched file number.
     */
    public function archiveSummary(Request $request)
    {

        $request->validate([
            'file_number' => 'required|string|max:255',
        ]);

        $fileNumber = $this->normalizeArchiveLookupValue($request->input('file_number'));
        if ($fileNumber === '') {
            return response()->json([
                'success' => true,
                'file_number' => $request->input('file_number'),
                'folders' => [],
                'message' => 'No files found in the digital archive for this file.',
            ]);
        }

        $allowedFileNumbers = [$fileNumber];
        if (method_exists($this->searchService, 'getSmeAllowedFileNos')) {
            $relatedFileNumbers = $this->searchService->getSmeAllowedFileNos($fileNumber, DB::connection('sqlsrv'));
            if (!empty($relatedFileNumbers)) {
                $allowedFileNumbers = array_values(array_unique(array_merge($allowedFileNumbers, $relatedFileNumbers)));
            }
        }

        if (Schema::connection('sqlsrv')->hasTable('related_file_number')) {
            $relatedRows = DB::connection('sqlsrv')->table('related_file_number AS rfn')
                ->where(function ($q) use ($allowedFileNumbers) {
                    foreach ($allowedFileNumbers as $candidate) {
                        $candidate = trim((string) $candidate);
                        if ($candidate === '') {
                            continue;
                        }

                        $q->orWhere('rfn.file_number', $candidate)
                          ->orWhere('rfn.related_fileno', $candidate)
                          ->orWhere('rfn.related_fileno', 'like', '%'.$candidate.'%');
                    }
                })
                ->select(['rfn.file_number', 'rfn.related_fileno'])
                ->get();

            foreach ($relatedRows as $row) {
                if (!empty($row->file_number)) {
                    $allowedFileNumbers[] = trim((string) $row->file_number);
                }
                if (!empty($row->related_fileno)) {
                    $allowedFileNumbers[] = trim((string) $row->related_fileno);
                }
            }
            $allowedFileNumbers = array_values(array_unique($allowedFileNumbers));
        }

        // Normalize every candidate to the same form used by the indexed columns below
        // (uppercase, slashes/underscores/equals collapsed to dashes, whitespace removed).
        // The searched file number is already normalized, but related file numbers from the
        // SME group and the related_file_number table arrive raw — without this they would
        // never match the normalized column comparison and their buttons would be missing.
        $allowedFileNumbers = array_values(array_unique(array_filter(array_map(
            fn ($candidate) => $this->normalizeArchiveLookupValue((string) $candidate),
            $allowedFileNumbers
        ))));

        $query = FileIndexing::query()
            ->select(['id', 'file_number', 'file_title', 'registry', 'land_use_type', 'plot_number', 'location', 'district', 'lga', 'related_fileno', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'])
            ->withCount(['scannings'])
            ->where(function ($builder) use ($fileNumber, $allowedFileNumbers) {
                $normalizedColumn = "UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(%s, ''))), '/', '-'), '=', '-'), '_', '-'))";
                foreach ($allowedFileNumbers as $candidate) {
                    $builder->orWhereRaw(sprintf($normalizedColumn, 'file_number') . ' = ?', [$candidate]);
                }

                foreach ($allowedFileNumbers as $candidate) {
                    $builder->orWhereRaw(sprintf($normalizedColumn, 'related_fileno') . ' = ?', [$candidate])
                        ->orWhereRaw(sprintf($normalizedColumn, 'related_fileno') . ' LIKE ?', ['%"' . $candidate . '"%'])
                        ->orWhereRaw(sprintf($normalizedColumn, 'related_fileno') . ' LIKE ?', ['%' . $candidate . '%']);
                }

                foreach ($allowedFileNumbers as $candidate) {
                    $builder->orWhereRaw(sprintf($normalizedColumn, 'mls_file_no') . ' = ?', [$candidate])
                        ->orWhereRaw(sprintf($normalizedColumn, 'kangis_file_no') . ' = ?', [$candidate])
                        ->orWhereRaw(sprintf($normalizedColumn, 'new_kangis_file_no') . ' = ?', [$candidate]);
                }
            })
            ->whereHas('scannings');

        $folders = $query->orderByDesc('updated_at')->get()->map(function (FileIndexing $file) {
            return [
                'id' => $file->id,
                'folder_name' => $file->file_title ?: $file->file_number,
                'file_number' => $file->file_number,
                'document_count' => (int) ($file->scannings_count ?? 0),
                'is_logged_out' => $this->isFileLoggedOut($file->file_number),
                'file_title' => $file->file_title,
                'registry' => $file->registry,
                'meta' => array_values(array_filter([
                    $file->land_use_type,
                    $file->district,
                    $file->lga,
                    $file->plot_number,
                ])),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'file_number' => $request->input('file_number'),
            'folders' => $folders,
            'message' => $folders->isEmpty()
                ? 'No files found in the digital archive for this file.'
                : null,
        ]);
    }

    /**
     * Determine whether a file is currently logged out via the file tracker.
     * Mirrors FileTrackerApiController::checkLogoutStatus(): a file is "logged out"
     * when an active (non COMPLETED/CANCELLED) tracker has no movement entry that is
     * still 'active' with an empty log_out_date.
     */
    private function isFileLoggedOut(?string $fileNumber): bool
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return false;
        }

        $existingTrackers = FileTracker::where('file_number', $fileNumber)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('COMPLETED', 'CANCELLED')")
            ->get();

        foreach ($existingTrackers as $existing) {
            $log = $existing->movement_log ?? [];
            if (empty($log)) {
                continue;
            }

            $currentlyCheckedIn = collect($log)->contains(
                fn ($e) => strtolower($e['status'] ?? '') === 'active' && empty($e['log_out_date'])
            );

            if (!$currentlyCheckedIn) {
                return true;
            }
        }

        return false;
    }

    private function normalizeArchiveLookupValue(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[\/=_]+/', '-', $value);
        $value = preg_replace('/\s+/', '', $value);

        return $value ?: '';
    }
    public function reportTemplateData(Request $request)
    {
        // The report-building engine now lives in LegalSearchService::buildPrintReport
        // so the PHS Portal slip can reuse the exact same dedup/weighting/caveat logic.
        $result = $this->searchService->buildPrintReport($request->query());
        return response()->json($result['payload'], $result['status'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }


    // ================================================================
    // Cleanup Mode endpoints
    // ================================================================

    /**
     * Match: Assign orphan records to a prop_id group.
     */
    public function match(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'prop_id' => 'required|string|max:20',
        ]);

        try {
            $count = $this->searchService->matchRecords(
                $request->input('table'),
                $request->input('ids'),
                $request->input('prop_id')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) matched to prop_id {$request->input('prop_id')}.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Drop: Unlink records from a prop_id group (set prop_id = NULL).
     */
    public function drop(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        try {
            $count = $this->searchService->dropRecords(
                $request->input('table'),
                $request->input('ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) dropped from prop_id group.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove: Soft-delete records (set is_deleted = 1).
     */
    public function remove(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        try {
            $count = $this->searchService->removeRecords(
                $request->input('table'),
                $request->input('ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} record(s) removed.",
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update: Edit fields on a single record.
     */
    public function update(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'id' => 'required|integer',
            'fields' => 'required|array|min:1',
        ]);

        try {
            $updated = $this->searchService->updateRecord(
                $request->input('table'),
                $request->input('id'),
                $request->input('fields')
            );

            return response()->json([
                'success' => $updated,
                'message' => $updated ? 'Record updated successfully.' : 'No changes made.',
                'data' => [],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update File Information fields on the file_indexings record for the searched file.
     * Matched by file_number first, then by KANGIS / New KANGIS file number.
     */
    public function updateFileIndexing(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
            'kangis_file_no' => 'nullable|string|max:100',
            'new_kangis_file_no' => 'nullable|string|max:100',
            'file_title' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'land_use' => 'nullable|string|max:100',
        ]);

        $candidates = array_values(array_filter(array_unique([
            trim($validated['file_number']),
            trim((string) ($validated['kangis_file_no'] ?? '')),
            trim((string) ($validated['new_kangis_file_no'] ?? '')),
        ]), fn ($v) => $v !== '' && $v !== '-'));

        $record = null;
        foreach ($candidates as $fileNo) {
            $record = FileIndexing::where('file_number', $fileNo)
                ->orWhere('kangis_file_no', $fileNo)
                ->orWhere('new_kangis_file_no', $fileNo)
                ->first();
            if ($record) {
                break;
            }
        }

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No file indexing record found for this file number.',
            ], 404);
        }

        $record->fill([
            'file_title' => $validated['file_title'] ?? null,
            'plot_number' => $validated['plot_no'] ?? null,
            'plot_size' => $validated['size'] ?? null,
            'tp_no' => $validated['tp_no'] ?? null,
            'district' => $validated['district'] ?? null,
            'lga' => $validated['lga'] ?? null,
            'land_use_type' => $validated['land_use'] ?? null,
        ]);
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'File information updated successfully.',
            'data' => [
                'file_title' => $record->file_title,
                'plot_no' => $record->plot_number,
                'size' => $record->plot_size,
                'tp_no' => $record->tp_no,
                'district' => $record->district,
                'lga' => $record->lga,
                'land_use' => $record->land_use_type,
            ],
        ]);
    }

    /**
     * Transfer caveat from a source record to a target record (PRA/CofO only).
     */
    public function transferCaveat(Request $request)
    {
        $request->validate([
            'source_table' => 'required|string|in:pra,CofO_staging',
            'source_id' => 'required|integer|min:1',
            'target_table' => 'required|string|in:pra,CofO_staging',
            'target_id' => 'required|integer|min:1',
        ]);

        try {
            $ok = $this->searchService->transferCaveat(
                $request->input('source_table'),
                (int) $request->input('source_id'),
                $request->input('target_table'),
                (int) $request->input('target_id')
            );

            return response()->json([
                'success' => $ok,
                'message' => $ok ? 'Caveat transferred successfully.' : 'No changes made.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to transfer caveat.'], 500);
        }
    }

    /**
     * Get a single record for editing.
     */
    public function getRecord(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'id' => 'required|integer',
        ]);

        try {
            $record = $this->searchService->getRecord(
                $request->input('table'),
                $request->input('id')
            );

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Record loaded.',
                'data' => $record,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Detect prop_id conflicts across selected records.
     */
    public function detectConflicts(Request $request)
    {
        $request->validate([
            'selections' => 'required|array|min:1',
            'selections.*.table' => 'required|string',
            'selections.*.ids' => 'required|array|min:1',
            'selections.*.ids.*' => 'integer',
        ]);

        $propIds = $this->searchService->detectPropIdConflicts(
            $request->input('selections')
        );

        return response()->json([
            'success' => true,
            'has_conflict' => count($propIds) > 1,
            'prop_ids' => $propIds,
        ]);
    }

    /**
     * Save timeline arrangement order for a prop_id.
     */
    public function saveArrangement(Request $request)
    {
        $request->validate([
            'prop_id' => 'required|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.table' => 'required|string',
            'items.*.id' => 'required|integer',
            'items.*.order' => 'required|integer|min:1',
        ]);

        try {
            $count = $this->searchService->saveTimelineArrangement(
                $request->input('prop_id'),
                $request->input('items'),
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Arrangement saved successfully.',
                'data' => ['affected' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get timeline arrangement order for a prop_id.
     */
    public function getArrangement(Request $request)
    {
        $request->validate([
            'prop_id' => 'required|string|max:20',
        ]);

        $arrangement = $this->searchService->getTimelineArrangement(
            $request->input('prop_id')
        );

        return response()->json([
            'success' => true,
            'data' => ['arrangement' => $arrangement],
        ]);
    }

    // ================================================================
    // Comments / Remarks Staging
    // ================================================================

    public function getComments(Request $request)
    {
        $fileNumber = trim($request->input('file_number', ''));
        $propId = trim($request->input('prop_id', ''));

        if (!$fileNumber && !$propId) {
            return response()->json(['success' => false, 'message' => 'File number or prop_id required.']);
        }

        $query = DB::connection('sqlsrv')->table('ls_comment_staging');
        if ($fileNumber) {
            $query->where('file_number', $fileNumber);
        } else {
            $query->where('prop_id', $propId);
        }

        $records = $query->orderByDesc('updated_at')->get();

        $data = [];
        foreach ($records as $r) {
            $data[$r->comment_type ?? 'ground_rent'] = [
                'id' => $r->id,
                'amount' => $r->amount,
                'comment' => $r->comment,
                'comment_type' => $r->comment_type ?? 'ground_rent',
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function saveComment(Request $request)
    {
        $request->validate([
            'file_number' => 'required|string|max:100',
            'comment_type' => 'required|string|in:ground_rent,ground_rent_paid,no_cofo,encumbrance,litigation,wrc,cofo,commencement_date,general',
            'amount' => 'nullable|numeric|min:0',
            'comment' => 'nullable|string|max:2000',
        ]);

        $fileNumber = trim($request->input('file_number'));
        $propId = trim($request->input('prop_id', ''));
        $commentType = $request->input('comment_type');
        $amount = $request->input('amount');
        $comment = trim($request->input('comment', ''));

        $existing = DB::connection('sqlsrv')->table('ls_comment_staging')
            ->where('file_number', $fileNumber)
            ->where('comment_type', $commentType)
            ->first();

        if ($existing) {
            DB::connection('sqlsrv')->table('ls_comment_staging')
                ->where('id', $existing->id)
                ->update([
                    'amount' => $amount,
                    'comment' => $comment,
                    'prop_id' => $propId ?: $existing->prop_id,
                    'updated_at' => now(),
                ]);
        } else {
            DB::connection('sqlsrv')->table('ls_comment_staging')->insert([
                'file_number' => $fileNumber,
                'prop_id' => $propId,
                'comment_type' => $commentType,
                'amount' => $amount,
                'comment' => $comment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Comment saved.']);
    }

    /**
     * Save comment specifically for a CofO record.
     */
    public function saveCofoComment(Request $request)
    {
        $request->validate([
            'cofo_id' => 'required|integer|min:1',
            'cofo_comment' => 'nullable|string|max:2000',
        ]);

        $cofoId = (int) $request->input('cofo_id');
        $cofoComment = trim((string) $request->input('cofo_comment', ''));

        $conn = DB::connection('sqlsrv');
        $schema = $conn->getSchemaBuilder();

        if (!$schema->hasColumn('CofO_staging', 'cofo_comment')) {
            return response()->json([
                'success' => false,
                'message' => 'Column cofo_comment does not exist on CofO table.',
            ], 500);
        }

        $updateData = [
            'cofo_comment' => $cofoComment,
        ];

        if ($schema->hasColumn('CofO_staging', 'updated_at')) {
            $updateData['updated_at'] = now();
        }

        $affected = $conn->table('CofO_staging')
            ->where('id', $cofoId)
            ->update($updateData);

        if ($affected < 1) {
            return response()->json([
                'success' => false,
                'message' => 'CofO record not found or no changes made.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'CofO comment updated successfully.',
        ]);
    }

    /**
     * Create a new PRA or CofO record from the legal search interface.
     * PRA uses PropertyRecordController::store, while CofO writes directly to CofO_staging.
     */
    public function createRecord(Request $request)
    {
        $targetTable = trim((string) $request->input('target_table', 'pra'));

        if ($targetTable === 'pra') {
            $request->merge(['record_mode' => 'property']);
            $propertyRecordController = app(PropertyRecordController::class);
            return $propertyRecordController->store($request);
        }

        if ($targetTable === 'CofO_staging') {
            $request->validate([
                'mlsFNo' => 'nullable|string|max:255',
                'kangisFileNo' => 'nullable|string|max:255',
                'NewKANGISFileno' => 'nullable|string|max:255',
                'fileno' => 'nullable|string|max:255',
                'transactionType' => 'nullable|string|max:255',
                'transactionDate' => 'nullable|date',
                'serialNo' => 'nullable|string|max:50',
                'pageNo' => 'nullable|string|max:50',
                'volumeNo' => 'nullable|string|max:50',
                'regNo' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'plot_no' => 'nullable|string|max:100',
                'lgsaOrCity' => 'nullable|string|max:255',
                'land_use' => 'nullable|string|max:255',
                'comments' => 'nullable|string|max:2000',
            ]);

            $insertData = [
                'mlsFNo' => $request->input('mlsFNo'),
                'kangisFileNo' => $request->input('kangisFileNo'),
                'NewKANGISFileno' => $request->input('NewKANGISFileno'),
                'fileno' => $request->input('fileno') ?: $request->input('mlsFNo') ?: $request->input('kangisFileNo'),
                'transaction_type' => $request->input('transactionType') ?: $request->input('instrumentType'),
                'transaction_date' => $request->input('transactionDate'),
                'serialNo' => $request->input('serialNo'),
                'pageNo' => $request->input('pageNo'),
                'volumeNo' => $request->input('volumeNo'),
                'regNo' => $request->input('regNo'),
                'party_1' => $request->input('firstParty') ?: $request->input('Assignor') ?: $request->input('Grantor'),
                'party_2' => $request->input('secondParty') ?: $request->input('Assignee') ?: $request->input('Grantee'),
                'party_3' => $request->input('thirdParty'),
                'party_4' => $request->input('fourthParty') ?: $request->input('party_4'),
                'Assignor' => $request->input('Assignor'),
                'Assignee' => $request->input('Assignee'),
                'Mortgagor' => $request->input('Mortgagor'),
                'Mortgagee' => $request->input('Mortgagee'),
                'Grantor' => $request->input('Grantor'),
                'Grantee' => $request->input('Grantee'),
                'Surrenderor' => $request->input('Surrenderor'),
                'Surrenderee' => $request->input('Surrenderee'),
                'Lessor' => $request->input('Lessor'),
                'Lessee' => $request->input('Lessee'),
                'land_use' => $request->input('land_use'),
                'location' => $request->input('location'),
                'plot_no' => $request->input('plot_no'),
                'lgsaOrCity' => $request->input('lgsaOrCity'),
                'comments' => $request->input('comments'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $hasSignal = !empty(array_filter([
                $insertData['mlsFNo'],
                $insertData['kangisFileNo'],
                $insertData['NewKANGISFileno'],
                $insertData['fileno'],
                $insertData['transaction_type'],
                $insertData['party_1'],
                $insertData['party_2'],
                $insertData['regNo'],
            ], fn($v) => trim((string) $v) !== ''));

            if (!$hasSignal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide at least a file number, transaction type, parties, or registration particulars.',
                ], 422);
            }

            DB::connection('sqlsrv')->table('CofO_staging')->insert($insertData);

            return response()->json([
                'success' => true,
                'message' => 'CofO record created successfully.',
                'data' => [],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid target table.'], 422);
    }
}
