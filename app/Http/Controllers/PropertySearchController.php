<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\LegalSearchService;
use App\Services\TimelineWeightingService;

class PropertySearchController extends Controller
{
    protected TimelineWeightingService $timelineService;
    protected LegalSearchService $legalSearchService;

    public function __construct(
        TimelineWeightingService $timelineService,
        LegalSearchService $legalSearchService
    ) {
        $this->timelineService = $timelineService;
        $this->legalSearchService = $legalSearchService;
    }

    /**
     * Display the Property Records search page.
     */
    public function index()
    {
        return view('property_search.index', [
            'PageTitle' => 'Property Records',
        ]);
    }

    /**
     * Server-side DataTable endpoint – returns unified records from all aggregate tables,
     * collapsed so each prop_id / file_number group appears only once.
     */
    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = min((int) $request->input('length', 25), 200);
        $searchValue = trim((string) $request->input('search.value', ''));

        $columns = [
            'file_number',        // 0
            'party_1',            // 1
            'party_2',            // 2
            'transaction_type',   // 3
            'land_use',           // 4
            'registration',       // 5
            'transaction_date',   // 6
            'location',           // 7
            'source_table',       // 8
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 6);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderColumn = $columns[$orderColumnIndex] ?? 'transaction_date';

        try {
            $connection = DB::connection('sqlsrv');

            $baseQuery = $this->buildCollapsedQuery($connection);

            $recordsTotal = (clone $baseQuery)->count();

            if ($searchValue !== '') {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $searchValue);
                $baseQuery->where(function ($q) use ($escaped) {
                    $q->where('file_number', 'LIKE', "%{$escaped}%")
                        ->orWhere('party_1', 'LIKE', "%{$escaped}%")
                        ->orWhere('party_2', 'LIKE', "%{$escaped}%")
                        ->orWhere('transaction_type', 'LIKE', "%{$escaped}%")
                        ->orWhere('land_use', 'LIKE', "%{$escaped}%")
                        ->orWhere('location', 'LIKE', "%{$escaped}%")
                        ->orWhere('registration', 'LIKE', "%{$escaped}%")
                        ->orWhere('source_table', 'LIKE', "%{$escaped}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();

            $records = (clone $baseQuery)
                ->orderByRaw("{$orderColumn} {$orderDir}")
                ->offset($start)
                ->limit($length)
                ->get();

            $data = $records->map(function ($row) {
                $propId = trim((string) ($row->prop_id_raw ?? ''));
                $fileNo = trim((string) ($row->file_number ?? ''));

                $rawRecords = $this->timelineService->getRawRecords($fileNo, $propId);
                $weightedCount = $this->timelineService->getWeightedCount($rawRecords);

                return [
                    'file_number' => $row->file_number ?: 'N/A',
                    'party_1' => $row->party_1 ?: 'N/A',
                    'party_2' => $row->party_2 ?: 'N/A',
                    'transaction_type' => $row->transaction_type ?: 'N/A',
                    'land_use' => $row->land_use ?: 'N/A',
                    'registration' => $row->registration ?: 'N/A',
                    'transaction_date' => $this->formatDate($row->transaction_date),
                    'location' => $row->location ?: 'N/A',
                    'source_table' => $row->source_table,
                    'timeline_count' => max(1, $weightedCount),
                    'prop_id' => $row->prop_id_raw,
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('PropertySearchController::data error', ['error' => $e->getMessage()]);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }
    }

    /**
     * Dashboard metrics for the Property Records page.
     */
    public function stats()
    {
        try {
            $connection = DB::connection('sqlsrv');
            $collapsed = $this->buildCollapsedQuery($connection);

            $totalRecords = (clone $collapsed)->count();
            $withTimeline = (clone $collapsed)->where('timeline_count', '>', 1)->count();

            $sourceCounts = [
                'file_history' => (clone $collapsed)->where('source_table', 'File History')->count(),
                'cofo' => (clone $collapsed)->where('source_table', 'CofO')->count(),
                'pra' => (clone $collapsed)->where('source_table', 'PRA')->count(),
                'deed_registration' => (clone $collapsed)->where('source_table', 'Deed Registration')->count(),
            ];

            $topTypes = (clone $collapsed)
                ->select('transaction_type', DB::raw('COUNT(*) as total'))
                ->whereNotNull('transaction_type')
                ->groupBy('transaction_type')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'multi_timeline_records' => $withTimeline,
                    'single_timeline_records' => max(0, $totalRecords - $withTimeline),
                    'source_counts' => $sourceCounts,
                    'top_transaction_types' => $topTypes,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('PropertySearchController::stats error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard statistics.',
                'data' => [
                    'total_records' => 0,
                    'multi_timeline_records' => 0,
                    'single_timeline_records' => 0,
                    'source_counts' => [
                        'file_history' => 0,
                        'cofo' => 0,
                        'pra' => 0,
                        'deed_registration' => 0,
                    ],
                    'top_transaction_types' => [],
                ],
            ], 500);
        }
    }

    /**
     * Build the Property Timeline payload from the core Legal Search report engine, so this
     * modal, the LS timeline, the PHS portal and the certified slip cannot disagree.
     *
     * Returns null when the engine produces no rows — the caller then falls back to the
     * legacy local pipeline rather than showing an empty modal.
     */
    private function buildTimelineFromLegalSearch(string $fileNumber, string $propId): ?array
    {
        try {
            $query = [];
            if ($fileNumber !== '') {
                $query['file_number'] = $fileNumber;
            }
            if ($propId !== '') {
                $query['prop_id'] = $propId;
            }

            $report = $this->legalSearchService->buildPrintReport($query);
            if (($report['status'] ?? null) !== 200) {
                return null;
            }

            $data = $report['payload']['data'] ?? [];
            $rows = $data['rows'] ?? [];
            if (empty($rows)) {
                return null;
            }
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        // Map the report's print-shaped rows onto the keys the timeline view renders.
        // "-" is the report's placeholder for an absent value; the view has its own em-dash
        // rendering for empty, so translate rather than printing a literal dash.
        $blank = fn ($v) => ($v === null || trim((string) $v) === '' || trim((string) $v) === '-')
            ? null
            : trim((string) $v);

        $transactions = [];
        foreach ($rows as $row) {
            // The report puts a real date in reg_date and falls back to a bare year in
            // transaction_date for legacy files with nothing on record — show whichever exists.
            $displayDate = $blank($row['transaction_date'] ?? null) ?? $blank($row['reg_date'] ?? null);

            $transactions[] = [
                'source_table'     => $row['source_table'] ?? null,
                'transaction_type' => $blank($row['instrument_type'] ?? null),
                'party_1'          => $blank($row['grantor'] ?? null),
                'party_2'          => $blank($row['grantee'] ?? null),
                'party_3'          => $blank($row['party_3'] ?? null),
                'primary_party'    => $blank($row['grantor'] ?? null),
                'secondary_party'  => $blank($row['grantee'] ?? null),
                'reg_no'           => $blank($row['reg_no'] ?? null),
                'transaction_date' => $displayDate,
                'display_date'     => $displayDate,
                'reg_date'         => $blank($row['reg_date'] ?? null),
                'location'         => $blank($row['location'] ?? null),
                'file_number'      => $blank($row['file_no'] ?? null),
                'root_of_title'    => $blank($row['root_of_title'] ?? null),
                // Every row the report kept is part of the primary timeline: the engine has
                // already dropped the duplicates that used to land in "Supporting Records".
                'timeline_weight'  => 1.0,
            ];
        }

        $first = $transactions[0] ?? [];
        $last = $transactions[count($transactions) - 1] ?? [];

        return [
            'fileNumber' => $blank($data['file_number'] ?? null)
                ?? ($fileNumber !== '' ? $fileNumber : ($first['file_number'] ?? $propId)),
            'propId' => $propId !== '' ? $propId : $this->resolvePropIdForDisplay($fileNumber),
            'landuse' => $blank($data['land_use'] ?? null),
            'location' => $blank($data['plot_description'] ?? null) ?? ($first['location'] ?? null),
            'totalTransactions' => count($transactions),
            'originalOwner' => $first['primary_party'] ?? null,
            'currentOwner' => $last['secondary_party'] ?? ($last['primary_party'] ?? null),
            'transactions' => $transactions,
            'weightedCount' => count($transactions),
            'omittedCount' => 0,
            'weighted' => $transactions,
            'omitted' => [],
        ];
    }

    /**
     * The prop_id shown in the summary strip when the modal was opened by file number.
     * Display only — PropID_Master is the canonical registry, so read it there rather than
     * from file_indexings, whose prop_id is wrong on a large share of legacy rows.
     */
    private function resolvePropIdForDisplay(string $fileNumber): ?string
    {
        if ($fileNumber === '') {
            return null;
        }

        try {
            $aliasColumns = ['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'];

            $propId = DB::connection('sqlsrv')->table('PropID_Master')
                ->where(function ($q) use ($aliasColumns, $fileNumber) {
                    foreach ($aliasColumns as $col) {
                        $q->orWhere($col, $fileNumber);
                    }
                })
                ->value('prop_id');

            return $propId !== null && trim((string) $propId) !== '' ? (string) $propId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Timeline view – shows all transactions for a given file number across all 4 tables, chronologically.
     */
    public function timeline(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        $propId = trim((string) $request->query('prop_id', ''));

        abort_if($fileNumber === '' && $propId === '', 404, 'A file number or property ID is required.');

        // The Property Timeline must show the SAME set the Legal Search timeline and the
        // certified slip show — same synthetic File Commissioning / Root of Title row, same
        // deduplication, same ordering. Rather than maintain a second engine that drifts
        // (it did: this modal was missing the commissioning row entirely and listed both
        // copies of a duplicated PRA deed as "Supporting Records"), read the authoritative
        // rows straight from LegalSearchService::buildPrintReport() — the same call the PHS
        // portal and the emailed online report already make.
        //
        // Falls back to the legacy local pipeline below when the report engine yields
        // nothing, so a file it cannot key on still renders something.
        $reportPayload = $this->buildTimelineFromLegalSearch($fileNumber, $propId);
        if ($reportPayload !== null) {
            $viewData = [
                'PageTitle' => 'Property Transaction Timeline',
                'historyPayload' => $reportPayload,
                'fileNumber' => $reportPayload['fileNumber'],
            ];

            return $request->query('mode') === 'partial'
                ? view('property_search.timeline_partial', $viewData)
                : view('property_search.timeline', $viewData);
        }

        $connection = DB::connection('sqlsrv');
        $transactions = collect();

        // --- 1. Fetch raw rows from all sources ---
        $fhQuery = $connection->table('file_history_staging')->where(fn($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
        $this->applyFileOrPropFilter($fhQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $fhRows = $fhQuery->get();

        $cofoQuery = $connection->table('CofO_staging')->whereNull('deleted_at');
        $this->applyFileOrPropFilter($cofoQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $cofoRows = $cofoQuery->get();

        $praQuery = $connection->table('pra')->where(fn($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
        $this->applyFileOrPropFilter($praQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $praRows = $praQuery->get();

        $deedQuery = $connection->table('deed_registrations')->where(fn($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
        $this->applyFileOrPropFilter($deedQuery, $fileNumber, $propId, ['fileno', 'parent_fileno']);
        $deedRows = $deedQuery->get();

        // --- 2. Normalize and Deduplicate using Service ---
        $allRawNormalized = [];
        foreach ($fhRows as $r)
            $allRawNormalized[] = $this->timelineService->normalizeRow($r, 'file_history_staging');
        foreach ($cofoRows as $r)
            $allRawNormalized[] = $this->timelineService->normalizeRow($r, 'CofO_staging');
        foreach ($praRows as $r)
            $allRawNormalized[] = $this->timelineService->normalizeRow($r, 'pra');
        foreach ($deedRows as $r)
            $allRawNormalized[] = $this->timelineService->normalizeRow($r, 'deed_registrations');

        $analysis = $this->timelineService->getWeightingAnalysis($allRawNormalized);
        $weightedRaw = $analysis['weighted'];
        $omittedRaw = $analysis['omitted'];

        // --- 3. Normalize for View ---
        $normalizeForView = function ($row) {
            $normalized = $this->normalizeTimelineRow((object) $row, $row['source_table']);
            // Add weighting info for JS
            $normalized['_ptl_weighting_status'] = $row['_ptl_weighting_status'] ?? null;
            $normalized['timeline_weight'] = $row['timeline_weight'] ?? 1.0;
            return $normalized;
        };

        $weightedTxns = collect($weightedRaw)->map($normalizeForView);
        $omittedTxns = collect($omittedRaw)->map($normalizeForView);

        // --- 4. Sort Chronologically (Weighted) ---
        $sortByDate = function ($transactions) {
            return $transactions->sort(function ($a, $b) {
                // 1. Sort by weight descending (Rule B: OP=10, TOT=9, ROFO=8, Others=1)
                $wa = (float) ($a['timeline_weight'] ?? 1.0);
                $wb = (float) ($b['timeline_weight'] ?? 1.0);
                if ($wa !== $wb) {
                    return $wb <=> $wa;
                }

                // 2. Chronological within weight tier
                $da = $a['sort_date'] ?? '9999-12-31';
                $db = $b['sort_date'] ?? '9999-12-31';
                
                if ($da !== $db) {
                    return $da <=> $db;
                }

                // 3. Fallback to ID
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            })->values();
        };

        $weightedTxns = $sortByDate($weightedTxns);
        $omittedTxns = $sortByDate($omittedTxns);

        $allTransactions = $weightedTxns->concat($omittedTxns);

        $displayFileNumber = $fileNumber ?: ($weightedTxns->first()['file_number'] ?? ($omittedTxns->first()['file_number'] ?? $propId));

        $firstTxn = $weightedTxns->first() ?? $omittedTxns->first();
        $lastTxn = $weightedTxns->last() ?? $omittedTxns->last();

        $historyPayload = [
            'fileNumber' => $displayFileNumber,
            'propId' => $propId ?: ($firstTxn['prop_id'] ?? null),
            'landuse' => $firstTxn['land_use'] ?? null,
            'location' => $firstTxn['location'] ?? null,
            'totalTransactions' => $weightedTxns->count(), // Badge count is weighted
            'originalOwner' => $firstTxn['primary_party'] ?? null,
            'currentOwner' => $lastTxn['primary_party'] ?? null,
            'transactions' => $allTransactions->toArray(),
            'weightedCount' => $weightedTxns->count(),
            'omittedCount' => $omittedTxns->count(),
            'weighted' => $weightedTxns->toArray(),
            'omitted' => $omittedTxns->toArray(),
        ];

        $viewData = [
            'PageTitle' => 'Property Transaction Timeline',
            'historyPayload' => $historyPayload,
            'fileNumber' => $displayFileNumber,
        ];

        // Support ?mode=partial for AJAX modal loading (no layout wrapper)
        if ($request->query('mode') === 'partial') {
            return view('property_search.timeline_partial', $viewData);
        }

        return view('property_search.timeline', $viewData);
    }

    /**
     * Build the UNION ALL query across 4 tables.
     */
    protected function buildUnionQuery($connection)
    {
        // Grouping key: use prop_id when available, otherwise fall back to file_number
        $groupKeyExpr = "CASE WHEN prop_id_raw IS NOT NULL AND LTRIM(RTRIM(prop_id_raw)) <> '' THEN prop_id_raw ELSE COALESCE(file_number, record_id) END";

        // file_history_staging
        $fh = $connection->table('file_history_staging')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(party_1, Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(party_2, Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'File History' AS source_table"),
            ]);

        // CofO_staging
        $cofo = $connection->table('CofO_staging')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'CofO' AS source_table"),
            ]);

        // pra
        $pra = $connection->table('pra')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                DB::raw("COALESCE(party_1, Assignor, Mortgagor, Grantor, Surrenderor, Lessor) AS party_1"),
                DB::raw("COALESCE(party_2, Assignee, Mortgagee, Grantee, Surrenderee, Lessee) AS party_2"),
                'transaction_type',
                'land_use',
                DB::raw("regNo AS registration"),
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'location',
                DB::raw("'PRA' AS source_table"),
            ]);

        // deed_registrations — uses deeds_date
        $deed = $connection->table('deed_registrations')
            ->select([
                DB::raw("CAST(id AS NVARCHAR(50)) AS record_id"),
                DB::raw("CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw"),
                DB::raw("fileno AS file_number"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("NULL AS land_use"),
                DB::raw("registration_number AS registration"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("COALESCE(district, lga) AS location"),
                DB::raw("'Deed Registration' AS source_table"),
            ]);

        // Build UNION ALL, then add grouping key
        $union = $fh->unionAll($cofo)->unionAll($pra)->unionAll($deed);

        // Wrap with group_key column for partitioning
        return $connection->query()->fromSub($union, 'base')
            ->select('*')
            ->selectRaw("{$groupKeyExpr} AS group_key");
    }

    /**
     * Build collapsed (rank 1 per group key) query reused by list + stats.
     *
     * timeline_count uses correlated sub-queries against the four real tables
     * (matching by prop_id) so the badge matches the timeline modal output.
     */
    protected function buildCollapsedQuery($connection)
    {
        $unionQuery = $this->buildUnionQuery($connection);

        $rankedQuery = $connection->query()->fromSub($unionQuery, 'unified')
            ->select('*')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY group_key ORDER BY transaction_date DESC) AS grp_rank");

        // Cross-table correlated count (mirrors OpsDashboardController pattern)
        $timelineCountExpr = "CASE WHEN ranked.prop_id_raw IS NOT NULL AND LTRIM(RTRIM(ranked.prop_id_raw)) <> '' THEN "
            . "(SELECT COUNT(*) FROM file_history_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) + "
            . "(SELECT COUNT(*) FROM pra WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw AND (is_deleted IS NULL OR is_deleted = 0)) + "
            . "(SELECT COUNT(*) FROM deed_registrations WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) + "
            . "(SELECT COUNT(*) FROM CofO_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ranked.prop_id_raw) "
            . "ELSE 1 END";

        $collapsedWithTimeline = $connection->query()->fromSub($rankedQuery, 'ranked')
            ->where('grp_rank', 1)
            ->select('ranked.*')
            ->selectRaw("({$timelineCountExpr}) AS timeline_count");

        // Wrap so timeline_count is a real column usable in WHERE (e.g. stats)
        return $connection->query()->fromSub($collapsedWithTimeline, 'collapsed');
    }

    /**
     * Apply file number or prop_id filter to a query.
     */
    protected function applyFileOrPropFilter($query, string $fileNumber, string $propId, array $fileColumns): void
    {
        $query->where(function ($q) use ($fileNumber, $propId, $fileColumns) {
            if ($fileNumber !== '') {
                $upper = strtoupper($fileNumber);
                foreach ($fileColumns as $col) {
                    $q->orWhereRaw("UPPER({$col}) = ?", [$upper]);
                }
            }
            if ($propId !== '') {
                $q->orWhere('prop_id', $propId);
            }
        });
    }

    /**
     * Normalize a timeline row into a consistent array shape.
     */
    protected function normalizeTimelineRow($row, string $source): array
    {
        $transactionDate = $row->transaction_date;
        $sortDate = null;
        $displayDate = null;

        if ($transactionDate) {
            try {
                $carbon = Carbon::parse($transactionDate);
                $sortDate = $carbon->toDateString();
                $displayDate = $carbon->format('F j, Y');
            } catch (\Exception $e) {
                $displayDate = $transactionDate;
            }
        }

        // Determine primary parties
        $party1 = ($row->party_1 ?? null) ?: (($row->assignor ?? null) ?: (($row->mortgagor ?? null) ?: (($row->grantor ?? null) ?: (($row->surrenderor ?? null) ?: ($row->lessor ?? null)))));
        $party2 = ($row->party_2 ?? null) ?: (($row->assignee ?? null) ?: (($row->mortgagee ?? null) ?: (($row->grantee ?? null) ?: (($row->surrenderee ?? null) ?: ($row->lessee ?? null)))));

        $parties = [];
        foreach (['assignor', 'assignee', 'mortgagor', 'mortgagee', 'grantor', 'grantee', 'surrenderor', 'surrenderee', 'lessor', 'lessee'] as $role) {
            $value = $row->{$role} ?? null;
            if ($value && trim((string) $value) !== '') {
                $parties[ucfirst($role)] = trim((string) $value);
            }
        }

        $regParts = explode('/', (string) ($row->regNo ?? ''));
        $serialNo = ($row->serial_no ?? null) ?: ($regParts[0] ?? null);
        $pageNo = ($row->page_no ?? null) ?: ($regParts[1] ?? null);
        $volumeNo = ($row->volume_no ?? null) ?: ($regParts[2] ?? null);

        return [
            'id' => $row->id,
            'file_number' => $row->file_number ?? 'N/A',
            'transaction_type' => ($row->transaction_type ?? null) ?: 'Transaction',
            'transaction_date' => $displayDate,
            'sort_date' => $sortDate,
            'primary_party' => $party1 ?: ($party2 ?: 'Unknown'),
            'secondary_party' => $party2,
            'party_1' => $party1,
            'party_2' => $party2,
            'party_3' => $row->party_3 ?? null,
            'parties' => $parties,
            'land_use' => $row->land_use ?? null,
            'location' => $row->location ?? null,
            'serial_no' => $serialNo,
            'page_no' => $pageNo,
            'volume_no' => $volumeNo,
            'regNo' => $row->regNo ?? null,
            'prop_id' => $row->prop_id ?? null,
            'comments' => $row->comments ?? null,
            'source_table' => $source,
        ];
    }

    /**
     * Format a date value for display.
     */
    protected function formatDate($value): string
    {
        if (!$value) {
            return 'N/A';
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
