<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use App\Models\AllocationSourceLookup;
use App\Models\FileNumber;
use App\Services\AllocationSourceResolver;
use App\Services\KangisLandPairService;
use App\Support\OssOpCommissionFilter;
use App\Support\RecertificationFile;
use App\Models\LandUse;
use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\BulkSmsNgService;
use App\Services\Sms\KlaesSmsDispatcher;
use App\Models\StreetName;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileNumberController extends Controller
{
    /**
     * Cache of tracking IDs generated during the current request to avoid duplicates
     * before database persistence.
     *
     * @var array<string, bool>
     */
    private array $generatedTrackingIds = [];

    /**
     * Display the MLS File number generation page
     */
    public function index()
    {
        // Single query replaces 3 separate COUNT round-trips.
        // Results are cached for 10 minutes; cache is busted when a new file number
        // is commissioned (see store/generate methods which call Cache::forget).
        $stats = Cache::remember('mls_fileno_page_stats_v2', 600, function () {
            $today = now()->toDateString();   // YYYY-MM-DD
            $month = now()->month;
            $year = now()->year;

            $row = DB::connection('sqlsrv')->selectOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN CAST(created_at AS DATE) = ? THEN 1 ELSE 0 END) AS today_count,
                    SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) AS month_count
                 FROM fileNumber
                 WHERE mlsfNo IS NOT NULL
                   AND LTRIM(RTRIM(mlsfNo)) != ''
                   AND SOURCE IN ('MLS_Commissioned','MLS_Commissioned_Batch')
                   AND type = 'MlsFileNO'
                   AND (is_deleted IS NULL OR is_deleted = 0)
                   AND " . OssOpCommissionFilter::notExistsSql('fileNumber.mlsfNo'),
                [$today, $month, $year]
            );

            return [
                'total' => (int) ($row->total ?? 0),
                'today' => (int) ($row->today_count ?? 0),
                'month' => (int) ($row->month_count ?? 0),
            ];
        });

        $totalCount = $stats['total'];
        $todayCount = $stats['today'];
        $monthCount = $stats['month'];

        // Fetch LGAs for dropdown
        $lgas = DB::connection('sqlsrv')->table('lgas')->select('name')->orderBy('name')->get();

        // Fetch states and street names for instrument capture modal reuse
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // Fetch Districts for dropdown
        $districts = DB::connection('sqlsrv')->table('districts')->select('name')->where('is_active', 1)->orderBy('name')->get();
        if ($districts->isEmpty()) {
            $districts = DB::connection('sqlsrv')->table('districts')->select('name')->orderBy('name')->get();
        }

        // Fetch Land Uses
        $landUses = LandUse::all();

        // Fetch all Prefixes with land_use_id for frontend logic
        $allPrefixes = \App\Models\Prefix::select('id', 'prefix', 'land_use_id')->get();

        // Fetch unallocated entries from the allocation list.
        //
        // The dropdown exists to pick an allottee, so a row carrying no name has
        // nothing to offer it: Select2 renders it as a blank, unsearchable line.
        // Bare plot rows — captured from an allocation sheet that lists a plot
        // with no allottee against it yet — are exactly that case, so they are
        // held back until someone puts a name on them.
        $unallocatedEntries = \App\Models\AllocationListEntry::where('is_allocated', 0)
            ->where(function ($q) {
                $q->where(fn ($n) => $n->whereNotNull('allottee_name')->where('allottee_name', '<>', ''))
                  ->orWhere(fn ($n) => $n->whereNotNull('first_name')->where('first_name', '<>', ''));
            })
            // This screen mints a new file number, so an entry that already
            // carries one has nothing to generate: only entries still awaiting
            // a file number belong in the dropdown.
            ->where(fn ($q) => $q->whereNull('file_no')->orWhere('file_no', ''))
            ->orderBy('first_name')
            ->get();

        return view('generate_fileno.mlsfno', compact('totalCount', 'todayCount', 'monthCount', 'lgas', 'states', 'districts', 'streetNames', 'landUses', 'allPrefixes', 'unallocatedEntries'));
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        $capturedTypes = ['Captured', 'Migrated', 'indexing', 'Indexing', 'INDEXING', 'KANGIS GIS'];
        $capturedSources = ['Captured', 'Migrated', 'indexing', 'Indexing', 'INDEXING', 'KANGIS GIS'];

        // Helper query builder
        $queryBuilder = function () use ($capturedTypes, $capturedSources) {
            return DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->whereIn('SOURCE', ['MLS_Commissioned', 'MLS_Commissioned_Batch'])
                ->where('type', 'MlsFileNO')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
        };

        $totalCount = (clone $queryBuilder())->count();
        $todayCount = (clone $queryBuilder())->whereDate('created_at', now()->today())->count();
        $monthCount = (clone $queryBuilder())->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalCount,
                'today' => $todayCount,
                'month' => $monthCount
            ]
        ]);
    }

    /**
     * Display the Capture Existing File page (Global View – all file numbers)
     */
    public function captureIndex()
    {
        $notDeleted = "(is_deleted IS NULL OR is_deleted = 0)";

        $stats = DB::connection('sqlsrv')->selectOne("
            SELECT
                COUNT(*)                                                                             AS total,
                SUM(CASE WHEN mlsfNo          IS NOT NULL AND LTRIM(RTRIM(mlsfNo))          != '' THEN 1 ELSE 0 END) AS mls_count,
                SUM(CASE WHEN kangisFileNo    IS NOT NULL AND LTRIM(RTRIM(kangisFileNo))    != '' THEN 1 ELSE 0 END) AS kangis_count,
                SUM(CASE WHEN NewKANGISFileNo IS NOT NULL AND LTRIM(RTRIM(NewKANGISFileNo)) != '' THEN 1 ELSE 0 END) AS new_kangis_count,
                SUM(CASE WHEN st_file_no      IS NOT NULL AND LTRIM(RTRIM(st_file_no))      != '' THEN 1 ELSE 0 END) AS st_count
            FROM fileNumber
            WHERE {$notDeleted}
        ");

        $totalCount = (int) ($stats->total ?? 0);
        $mlsfCount = (int) ($stats->mls_count ?? 0);
        $kangisCount = (int) ($stats->kangis_count ?? 0);
        $newKangisCount = (int) ($stats->new_kangis_count ?? 0);
        $stCount = (int) ($stats->st_count ?? 0);

        // Generate a fresh tracking ID for this session
        $trackingId = $this->generateTrackingId();

        return view('generate_fileno.capture_existing', compact(
            'totalCount',
            'mlsfCount',
            'kangisCount',
            'newKangisCount',
            'stCount',
            'trackingId'
        ));
    }



    public function getData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 20);
            $search = $request->input('search.value', '');
            $source = $request->input('source', 'New');

            // ── Source WHERE fragment (no bindings needed — string literals only) ──
            if ($source === 'New') {
                // Hide OP/TOT files commissioned from the OSS. The test lives in
                // OssOpCommissionFilter so this list, the page's stat cards (index())
                // and the batch commissioning sheet
                // (MlsFileNoController::getBatchRecords) hide the same rows — when
                // they disagree the table shows rows the counters do not count.
                //
                // It deliberately does NOT test source IN ('OP Resettlement',
                // 'OP Direct Allocation'): the generator writes those same values for
                // its own OP allocations, so that test also hid files commissioned in
                // MLS File Commissioning. Origin is stamped into sub_source instead.
                $sourceWhere = "(
                                    (fn.SOURCE IN ('MLS_Commissioned','MLS_Commissioned_Batch') AND fn.type = 'MlsFileNO')
                                    OR EXISTS (
                                        SELECT 1 FROM mls_file_no ms_new
                                        WHERE ms_new.full_file_number = fn.mlsfNo
                                          AND (ms_new.is_deleted IS NULL OR ms_new.is_deleted = 0)
                                          AND (
                                              ms_new.file_option IS NULL
                                              OR LTRIM(RTRIM(ms_new.file_option)) = ''
                                              OR LOWER(LTRIM(RTRIM(ms_new.file_option))) <> 'temporary'
                                          )
                                    )
                                )
                                AND " . OssOpCommissionFilter::notExistsSql('fn.mlsfNo');
            } elseif ($source === 'All') {
                // Global view – no type/source filter, show everything
                $sourceWhere = "1=1";
            } else {
                $sourceWhere = "(   fn.type   IN ('Captured','Migrated','indexing','Indexing','INDEXING','KANGIS GIS')
                                 OR fn.SOURCE IN ('Captured','Migrated','indexing','Indexing','INDEXING','KANGIS GIS')
                                 OR (fn.st_file_no IS NOT NULL AND LTRIM(RTRIM(fn.st_file_no)) != ''))";
            }

            // ── Search WHERE fragment + bindings ──
            $searchSql = '';
            $searchBindings = [];
            if (!empty($search)) {
                $pct = "%{$search}%";
                $searchSql = "AND (   fn.kangisFileNo    LIKE ?
                                       OR fn.NewKANGISFileNo LIKE ?
                                       OR fn.FileName        LIKE ?
                                       OR fn.mlsfNo          LIKE ?
                                       OR fn.st_file_no      LIKE ?
                                       OR fn.tracking_id     LIKE ?
                                       OR fn.lga             LIKE ?
                                       OR fn.location        LIKE ?
                                       OR fn.plot_no         LIKE ?
                                       OR fn.tp_no           LIKE ?)";
                $searchBindings = [$pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct, $pct];
            }

            // ── Fast total count (cached 5 min) ──
            // Uses a single OUTER APPLY for batch_no only — no other lookups needed.
            // v4: bumped when the OSS exclusion above dropped its source-based marker,
            // so the corrected total is not served from a stale v3 entry after deploy.
            $recordsTotal = Cache::remember("file_numbers_total_v4_{$source}", 300, function () use ($sourceWhere) {
                $row = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(DISTINCT COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))) AS cnt
                     FROM fileNumber fn
                     OUTER APPLY (
                         SELECT TOP 1 batch_no FROM mls_file_no
                         WHERE full_file_number = fn.mlsfNo ORDER BY id DESC
                     ) AS mls
                     WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                       AND {$sourceWhere}"
                );
                return (int) ($row->cnt ?? 0);
            });

            // ── Add temporary-file count to totals (source=New only) ──
            // Temporary files only exist in mls_file_no, so we count them separately.
            $tempCount = 0;
            if ($source === 'New') {
                $tempRow = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(*) AS cnt FROM mls_file_no
                     WHERE file_option = 'temporary'
                       AND (is_deleted IS NULL OR is_deleted = 0)
                       AND NOT EXISTS (
                           SELECT 1 FROM fileNumber fn2
                           WHERE fn2.mlsfNo = mls_file_no.full_file_number
                             AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                       )"
                );
                $tempCount = (int) ($tempRow->cnt ?? 0);
                $recordsTotal += $tempCount;
            }

            // ── Build Plot Extension rows once (source=New only) ──
            // These now interleave by date through the unified phase-1 query; the full
            // formatted set is kept here so phase-2 can pick the rows on the current page.
            $plotExtRows = $this->formatPlotExtensionRows($source, $search);
            $recordsTotal += $plotExtRows->count();

            // ── Fast filtered count ──
            // Short-circuit when no search: filtered = total (saves a whole SQL round-trip).
            if (empty($search)) {
                $filteredRecords = $recordsTotal;
            } else {
                $row = DB::connection('sqlsrv')->selectOne(
                    "SELECT COUNT(DISTINCT COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))) AS cnt
                     FROM fileNumber fn
                     OUTER APPLY (
                         SELECT TOP 1 batch_no FROM mls_file_no
                         WHERE full_file_number = fn.mlsfNo ORDER BY id DESC
                     ) AS mls
                     WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                       AND {$sourceWhere}
                       {$searchSql}",
                    $searchBindings
                );
                $filteredRecords = (int) ($row->cnt ?? 0);

                // Also count matching temporary files in filtered count
                if ($source === 'New') {
                    $pct = "%{$search}%";
                    $tempFiltered = DB::connection('sqlsrv')->selectOne(
                        "SELECT COUNT(*) AS cnt FROM mls_file_no
                         WHERE file_option = 'temporary'
                           AND (is_deleted IS NULL OR is_deleted = 0)
                           AND NOT EXISTS (
                               SELECT 1 FROM fileNumber fn2
                               WHERE fn2.mlsfNo = mls_file_no.full_file_number
                                 AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                           )
                           AND (
                               full_file_number LIKE ?
                               OR file_name     LIKE ?
                               OR tracking_id   LIKE ?
                               OR location      LIKE ?
                               OR lga           LIKE ?
                               OR plot_no       LIKE ?
                               OR tp_no         LIKE ?
                           )",
                        [$pct, $pct, $pct, $pct, $pct, $pct, $pct]
                    );
                    $filteredRecords += (int) ($tempFiltered->cnt ?? 0);
                }

                // Plot Extension rows are pre-filtered by search in formatPlotExtensionRows().
                $filteredRecords += $plotExtRows->count();
            }

            // ── Main data query (two-phase) ──
            //
            // PHASE 1 – Paginate cheaply. Only the mls_file_no OUTER APPLY is needed
            // here (for batch_no grouping); expensive per-row lookups are deferred to
            // phase 2 so they fire at most page-size times.
            //
            // Three row-source branches are paginated TOGETHER, ordered by date, so plot
            // extensions / temp files interleave chronologically instead of being pinned
            // to the top. F = fileNumber (batch-grouped), T = temporary files,
            // P = plot extensions. T/P only exist in the "New" view.
            $includeExtras = ($source === 'New');

            $fileBranch = "
                SELECT CAST('F' AS CHAR(1)) AS source_type, w.id,
                       w.derived_batch_no, w.batch_count, w.batch_first_file, w.sort_date
                FROM (
                    SELECT
                        fn.id,
                        mls.batch_no AS derived_batch_no,
                        ROW_NUMBER() OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                            ORDER BY fn.id DESC
                        ) AS group_rn,
                        COUNT(*) OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                        ) AS batch_count,
                        FIRST_VALUE(fn.mlsfNo) OVER (
                            PARTITION BY COALESCE(NULLIF(mls.batch_no,''), CAST(fn.id AS VARCHAR(20)))
                            ORDER BY fn.id ASC
                            ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
                        ) AS batch_first_file,
                        COALESCE(mls.created_at, mls.commissioning_date, fn.created_at) AS sort_date
                    FROM fileNumber fn
                    OUTER APPLY (
                        SELECT TOP 1 m.batch_no, m.created_at, m.commissioning_date
                        FROM mls_file_no m
                        WHERE m.full_file_number = fn.mlsfNo
                        ORDER BY m.id DESC
                    ) AS mls
                    WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
                      AND {$sourceWhere}
                      {$searchSql}
                ) AS w
                WHERE w.group_rn = 1
            ";

            $unionBranches = [$fileBranch];
            $unionBindings = $searchBindings;

            if ($includeExtras) {
                $tempSrch = !empty($search)
                    ? "AND (m.full_file_number LIKE ? OR m.file_name LIKE ? OR m.tracking_id LIKE ?
                            OR m.location LIKE ? OR m.lga LIKE ? OR m.plot_no LIKE ? OR m.tp_no LIKE ?)"
                    : '';
                $unionBranches[] = "
                    SELECT CAST('T' AS CHAR(1)) AS source_type, m.id,
                           CAST(NULL AS VARCHAR(50)) AS derived_batch_no, 1 AS batch_count,
                           m.full_file_number AS batch_first_file, m.created_at AS sort_date
                    FROM mls_file_no m
                    WHERE m.file_option = 'temporary'
                      AND (m.is_deleted IS NULL OR m.is_deleted = 0)
                      AND NOT EXISTS (
                          SELECT 1 FROM fileNumber fn2
                          WHERE fn2.mlsfNo = m.full_file_number
                            AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
                      )
                      {$tempSrch}
                ";

                $plotSrch = !empty($search)
                    ? "AND (pe.original_file_no LIKE ? OR pe.file_name LIKE ? OR pe.tracking_id LIKE ?
                            OR pe.location LIKE ? OR pe.lga LIKE ? OR pe.plot_no LIKE ? OR pe.tp_no LIKE ?)"
                    : '';
                $unionBranches[] = "
                    SELECT CAST('P' AS CHAR(1)) AS source_type, pe.id,
                           CAST(NULL AS VARCHAR(50)) AS derived_batch_no, 1 AS batch_count,
                           pe.original_file_no AS batch_first_file, pe.created_at AS sort_date
                    FROM plot_extensions pe
                    WHERE (pe.is_deleted IS NULL OR pe.is_deleted = 0)
                      {$plotSrch}
                ";

                if (!empty($search)) {
                    $pct = "%{$search}%";
                    // temp branch (7) then plot branch (7)
                    $unionBindings = array_merge(
                        $unionBindings,
                        [$pct, $pct, $pct, $pct, $pct, $pct, $pct],
                        [$pct, $pct, $pct, $pct, $pct, $pct, $pct]
                    );
                }
            }

            $phaseSql = "
                SELECT source_type, id, derived_batch_no, batch_count, batch_first_file
                FROM (
                    " . implode("\n                    UNION ALL\n", $unionBranches) . "
                ) AS unified
                ORDER BY sort_date DESC, id DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ";

            // This query has to group the full file register before it can return a
            // single DataTable page.  Keep the result of that expensive first phase
            // briefly: the browser commonly asks for the same page again after a
            // redraw, and several users can open the default page at once.  Phase 2
            // still reads the current records, so only pagination metadata is cached.
            $pageCacheVersion = (string) Cache::get('file_numbers_datatable_version', '1');
            $pageCacheKey = 'file_numbers_phase1_v1_' . sha1(json_encode([
                $pageCacheVersion,
                $source,
                $search,
                $start,
                $length,
            ]));

            $pagedRows = Cache::remember($pageCacheKey, now()->addMinutes(5), function () use ($phaseSql, $unionBindings, $start, $length) {
                return DB::connection('sqlsrv')->select(
                    $phaseSql,
                    array_merge($unionBindings, [$start, $length])
                );
            });

            // Phase-1 order + per-source id buckets + batch meta (F rows only).
            $pageOrder = [];   // ordered list of [source_type, id]
            $fileIds   = [];
            $tempIds   = [];
            $plotIds   = [];
            $batchMeta = [];   // F id => [batch_no, batch_count, batch_first_file]
            foreach ($pagedRows as $r) {
                $sid = (int) $r->id;
                $pageOrder[] = [$r->source_type, $sid];
                if ($r->source_type === 'F') {
                    $fileIds[] = $sid;
                    $batchMeta[$sid] = [
                        'batch_no' => $r->derived_batch_no,
                        'batch_count' => (int) $r->batch_count,
                        'batch_first_file' => $r->batch_first_file,
                    ];
                } elseif ($r->source_type === 'T') {
                    $tempIds[] = $sid;
                } else {
                    $plotIds[] = $sid;
                }
            }

            // Nothing on this page at all
            if (empty($pageOrder)) {
                return response()->json([
                    'draw' => intval($draw),
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $filteredRecords,
                    'data' => [],
                ]);
            }

            // PHASE 2 – Enrich only the page-size rows (20 max).
            // All expensive OUTER APPLYs (pra, instrument_capture,
            // file_commissioning_sheets, dciv_link, file_indexing_links)
            // now fire at most 20 times per request.
            $inList = implode(',', $fileIds ?: [0]);   // F rows only — safe: all cast to int
            $enrichSql = "
                SELECT
                    fn.id, fn.kangisFileNo, fn.mlsfNo, fn.NewKANGISFileNo, fn.FileName,
                    fn.st_file_no, fn.plot_no, fn.tp_no, fn.location, fn.tracking_id,
                    fn.type, fn.created_at, fn.SOURCE,
                    -- The Edit modal's file-number field lands in one of two places
                    -- depending on its \"Old File Number\" checkbox, so the column has to
                    -- read both: related_fileno is a JSON array on fileNumber, old_fileno
                    -- a plain string on mls_file_no.
                    fn.related_fileno                         AS derived_related_fileno,
                    mls.old_fileno                            AS derived_old_fileno,
                    rot.root_of_title                         AS derived_root_of_title,
                    mls.batch_no                              AS derived_batch_no,
                    mls.land_use                              AS derived_land_use,
                    mls.customer_type                         AS derived_customer_type,
                    mls.commissioning_date                    AS derived_commissioning_date,
                    mls.created_at                            AS derived_created_at,
                    mls.source                                AS derived_source,
                    mls.source_instrument_capture_id          AS derived_source_instrument_capture_id,
                    COALESCE(fn.lga,         mls.lga)         AS derived_lga,
                    COALESCE(fn.district,    mls.district)    AS derived_district,
                    COALESCE(mls.created_by, fn.created_by)   AS derived_created_by,
                    pur.name                                  AS purpose_name,
                    -- An OP commissioned from an existing PRA record keeps that
                    -- record's id on mls_file_no.source_pra_id. Prefer this explicit
                    -- source over the new file's mirrored PRA row, otherwise the list
                    -- cannot show the source TEMP number or open its OP card.
                    COALESCE(mls.source_pra_id, pra.id) AS derived_source_pra_id,
                    COALESCE(ic.prop_id, source_pra.prop_id, pra.prop_id) AS derived_source_prop_id,
                    COALESCE(ic.temp_fileno, source_pra.temp_fileno, pra.temp_fileno) AS derived_source_temp_fileno,
                    CASE WHEN cs.file_number IS NOT NULL THEN 1 ELSE 0 END AS has_commissioning_sheet,
                    geo.latitude                              AS derived_latitude,
                    geo.longitude                             AS derived_longitude,
                    -- Applicant's phone. Held in three places with very different
                    -- coverage: fileNumber (122 rows), mls_file_no (5) and
                    -- file_indexings (17,498), so the indexed number is the fallback
                    -- that actually answers for most files. rep_phone_no is the
                    -- representative's, kept separate so the card can label it.
                    COALESCE(NULLIF(LTRIM(RTRIM(fn.phone_no)), ''),
                             NULLIF(LTRIM(RTRIM(mls.phone_no)), ''),
                             NULLIF(LTRIM(RTRIM(ph.phone)), ''))   AS derived_phone_no,
                    COALESCE(NULLIF(LTRIM(RTRIM(fn.rep_phone_no)), ''),
                             NULLIF(LTRIM(RTRIM(mls.rep_phone_no)), '')) AS derived_rep_phone_no,
                    dciv_rel.dciv_related,
                    fil_rel.fil_related
                FROM fileNumber fn
                OUTER APPLY (
                    SELECT TOP 1
                        m.batch_no, m.land_use, m.customer_type, m.commissioning_date, m.created_at,
                        m.source, m.source_instrument_capture_id, m.source_pra_id, m.lga, m.district, m.created_by, m.purpose_id,
                        m.old_fileno, m.phone_no, m.rep_phone_no
                    FROM mls_file_no m
                    WHERE m.full_file_number = fn.mlsfNo
                    ORDER BY m.id DESC
                ) AS mls
                OUTER APPLY (
                    SELECT TOP 1 p.name FROM purposes p WHERE p.id = mls.purpose_id
                ) AS pur
                OUTER APPLY (
                    SELECT TOP 1 p.id, p.prop_id, p.temp_fileno
                    FROM pra p WHERE p.mlsFNo = fn.mlsfNo
                    ORDER BY p.id DESC
                ) AS pra
                OUTER APPLY (
                    SELECT TOP 1 ic.prop_id, ic.temp_fileno
                    FROM instrument_capture ic
                    WHERE ic.id = mls.source_instrument_capture_id
                ) AS ic
                OUTER APPLY (
                    SELECT TOP 1 p.id, p.prop_id, p.temp_fileno
                    FROM pra p
                    WHERE p.id = mls.source_pra_id
                ) AS source_pra
                -- Map pin. The generator writes the pinned coordinates straight to
                -- file_indexings (see MlsFileNoController's createFromFileNumberData
                -- call); neither fileNumber nor mls_file_no has lat/long columns.
                OUTER APPLY (
                    SELECT TOP 1 fi.latitude, fi.longitude
                    FROM file_indexings fi
                    WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                      AND fi.latitude IS NOT NULL AND fi.longitude IS NOT NULL
                    ORDER BY fi.id DESC
                ) AS geo
                -- Root of Title. Hand-keyed on the File Indexing form and held only on
                -- file_indexings, so it has to be read across every number the file may be
                -- indexed under. Rows that carry no value are skipped rather than winning
                -- the TOP 1 with a NULL.
                OUTER APPLY (
                    SELECT TOP 1 fi.phone
                    FROM file_indexings fi
                    WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                      AND fi.phone IS NOT NULL AND LTRIM(RTRIM(fi.phone)) != ''
                    ORDER BY fi.id DESC
                ) AS ph
                OUTER APPLY (
                    SELECT TOP 1 fi.root_of_title
                    FROM file_indexings fi
                    WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                      AND fi.root_of_title IS NOT NULL
                      AND LTRIM(RTRIM(fi.root_of_title)) != ''
                    ORDER BY fi.id DESC
                ) AS rot
                OUTER APPLY (
                    SELECT STUFF((
                        SELECT ', ' + dl.related_file_number
                        FROM dciv_link dl
                        WHERE dl.main_file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                          AND dl.related_file_number IS NOT NULL
                          AND LTRIM(RTRIM(dl.related_file_number)) != ''
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS dciv_related
                ) AS dciv_rel
                OUTER APPLY (
                    SELECT STUFF((
                        SELECT ', ' + fil.file_number
                        FROM file_indexing_links fil
                        WHERE fil.file_indexing_id IN (
                            SELECT fi.id FROM file_indexings fi
                            WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
                        )
                          AND fil.file_number IS NOT NULL
                          AND LTRIM(RTRIM(fil.file_number)) != ''
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS fil_related
                ) AS fil_rel
                LEFT JOIN (SELECT DISTINCT file_number FROM file_commissioning_sheets) cs
                    ON cs.file_number = fn.mlsfNo
                WHERE fn.id IN ({$inList})
            ";

            // Enrich F (regular fileNumber) rows only.
            $fileRows = [];
            if (!empty($fileIds)) {
                $enrichedMap = [];
                foreach (DB::connection('sqlsrv')->select($enrichSql) as $r) {
                    $enrichedMap[$r->id] = $r;
                }
                foreach ($fileIds as $id) {
                    if (!isset($enrichedMap[$id]))
                        continue;
                    $row = $enrichedMap[$id];
                    // Overlay correct batch aggregates from phase 1
                    $row->derived_batch_no = $batchMeta[$id]['batch_no'] ?? '';
                    $row->batch_count = $batchMeta[$id]['batch_count'] ?? 1;
                    $row->batch_first_file = $batchMeta[$id]['batch_first_file'] ?? $row->mlsfNo;
                    $fileRows[$id] = $row;
                }
            }

            // Passports are stored as `scannings` rows joined through file_indexings, so
            // resolving one per row would be a query per row. prime() reads the whole page
            // in one go and fills the service's cache; resolve() below is then free.
            try {
                app(\App\Services\FilePassportService::class)->prime(
                    collect($fileRows)
                        ->flatMap(fn ($r) => [$r->mlsfNo ?? null, $r->kangisFileNo ?? null, $r->NewKANGISFileNo ?? null])
                        ->filter(fn ($v) => trim((string) $v) !== '')
                        ->unique()
                        ->values()
                        ->all()
                );
            } catch (\Throwable $e) {
                // A missing photo must never take the listing down.
                Log::warning('Could not prime passports for the file-number list', ['error' => $e->getMessage()]);
            }

            // Every related file number entered at commissioning, for the whole page in
            // one query. Read from the register rather than fileNumber.related_fileno,
            // which only ever held the primary number.
            $relatedFilesMap = $this->relatedFileNumbersForRows($fileRows);

            // Which numbers on this page the registry has already retired, and what each
            // became. A decommissioned file goes on being listed — the row is the record
            // of its issue — but printed like any live one it reads as a number that still
            // answers, and the officer has no way to reach its successor. One query for
            // the page, the same idiom as the passports and the related files above.
            $supersededMap = $this->supersededFileNumbersForRows($fileRows);

            // ── Format F rows into a map keyed by fileNumber.id ──
            $fileMap = collect($fileRows)->map(function ($row) use ($relatedFilesMap, $supersededMap) {
                // Build primaryFileNo: first non-empty of mlsfNo, kangisFileNo, NewKANGISFileNo, st_file_no
                $primaryFileNo = collect([
                    $row->mlsfNo ?? '',
                    $row->kangisFileNo ?? '',
                    $row->NewKANGISFileNo ?? '',
                    $row->st_file_no ?? '',
                ])->map(fn($v) => trim($v))->first(fn($v) => $v !== '') ?: 'N/A';

                // Build relatedFileNo: merge from dciv_link and file_indexing_links
                $relatedParts = array_filter([
                    $row->dciv_related ?? '',
                    $row->fil_related ?? '',
                ]);
                $allRelated = [];
                foreach ($relatedParts as $part) {
                    foreach (explode(',', $part) as $r) {
                        $r = trim($r);
                        if ($r !== '' && !in_array($r, $allRelated)) {
                            $allRelated[] = $r;
                        }
                    }
                }
                $relatedFileNo = implode(', ', $allRelated) ?: 'N/A';

                // The number the Root of Title and the Recertification rule are decided on.
                $rowFileNo = trim((string) ($row->mlsfNo ?? '')) ?: $primaryFileNo;
                $relatedOrOld = $this->formatRelatedOrOldFileNo(
                    $row->derived_related_fileno ?? null,
                    $row->derived_old_fileno ?? null
                );

                return [
                    'id' => $row->id,
                    // Passport, Root of Title and the Related/Old file number, as shown on
                    // the Edit modal. Resolved from the page-wide cache primed above.
                    'passport_url' => app(\App\Services\FilePassportService::class)
                        ->resolve($row->mlsfNo ?? $row->kangisFileNo ?? $row->NewKANGISFileNo ?? null)['url'] ?? null,
                    'root_of_title' => trim((string) ($row->derived_root_of_title ?? '')) ?: 'N/A',
                    'is_root_of_title' => $this->isRootOfTitleFile(
                        $row->derived_source ?? null,
                        $row->derived_root_of_title ?? null,
                        $rowFileNo
                    ),
                    'root_of_title_ref' => $this->rootOfTitleReference($rowFileNo, $relatedOrOld),
                    'related_old_fileno' => $relatedOrOld,
                    // Null when the file is live; otherwise what retired it and what it
                    // became. The listing strikes the number through and prints the
                    // successor beneath it.
                    'superseded' => $supersededMap[strtoupper(trim((string) $rowFileNo))] ?? null,
                    // Contact for the quick-action card. No email is stored for a
                    // commissioned file anywhere (customers_staging holds 0), so the
                    // card shows that action as unavailable rather than opening a
                    // mailto: with nothing in it.
                    'phone_no' => trim((string) ($row->derived_phone_no ?? '')),
                    'rep_phone_no' => trim((string) ($row->derived_rep_phone_no ?? '')),
                    'email' => '',
                    // Every related number, not just the one the inline badge shows.
                    'related_files' => $relatedFilesMap[$row->id] ?? [],
                    'primaryFileNo' => $primaryFileNo,
                    'relatedFileNo' => $relatedFileNo,
                    'mlsfNo' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: 'N/A',
                    'stFileNo' => trim($row->st_file_no ?? '') ?: 'N/A',
                    'FileName' => trim($row->FileName ?? '') ?: 'N/A',
                    'land_use' => trim($row->derived_land_use ?? '') ?: 'N/A',
                    'customer_type' => trim($row->derived_customer_type ?? '') ?: 'N/A',
                    'purpose_name' => trim($row->purpose_name ?? '') ?: 'N/A',
                    'plot_no' => trim($row->plot_no ?? '') ?: 'N/A',
                    'tp_no' => trim($row->tp_no ?? '') ?: 'N/A',
                    'location' => trim($row->location ?? '') ?: 'N/A',
                    'lga' => trim($row->derived_lga ?? '') ?: 'N/A',
                    'district' => trim($row->derived_district ?? '') ?: 'N/A',
                    'tracking_id' => trim($row->tracking_id ?? '') ?: 'N/A',
                    'type' => trim($row->type ?? '') ?: 'N/A',
                    'created_by' => trim($row->derived_created_by ?? '') ?: 'System',
                    // An RC number is a recertification whatever the allocation route
                    // recorded, and most RC files are legacy rows with no source at all —
                    // so the label is derived here rather than read from the column.
                    'source' => RecertificationFile::applySourceLabel(
                        $rowFileNo,
                        trim($row->derived_source ?? '') ?: (trim($row->SOURCE ?? '') ?: 'N/A')
                    ),
                    'source_instrument_capture_id' => $row->derived_source_instrument_capture_id ? (int) $row->derived_source_instrument_capture_id : null,
                    'source_pra_id' => $row->derived_source_pra_id ? (int) $row->derived_source_pra_id : null,
                    'source_prop_id' => $row->derived_source_prop_id ? (int) $row->derived_source_prop_id : null,
                    'source_temp_fileno' => trim($row->derived_source_temp_fileno ?? '') ?: 'N/A',
                    'batch_no' => trim($row->derived_batch_no ?? ''),
                    'batch_count' => (int) $row->batch_count,
                    'batch_first_file' => trim($row->batch_first_file ?? ''),
                    'commissioning_date' => $row->derived_commissioning_date ? date('Y-m-d', strtotime($row->derived_commissioning_date)) : 'N/A',
                    'has_commissioning_sheet' => (bool) $row->has_commissioning_sheet,
                    'latitude' => $this->formatCoordinate($row->derived_latitude ?? null),
                    'longitude' => $this->formatCoordinate($row->derived_longitude ?? null),
                    'created_at' => ($row->derived_created_at ?? null)
                        ? date('Y-m-d H:i:s', strtotime($row->derived_created_at))
                        : ($row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A'),
                    'action' => $this->buildCaptureActionColumn($row, $row->SOURCE),
                ];
            })->all();

            // ── Build temp-file map for this page's T rows (source=New only) ──
            $tempMap = [];
            if ($includeExtras && !empty($tempIds)) {
                $tempInList = implode(',', $tempIds);
                $tempRows = DB::connection('sqlsrv')->select(
                    "SELECT m.id, m.full_file_number, m.file_name, m.land_use, m.customer_type,
                            m.plot_no, m.tp_no, m.location, m.lga, m.district, m.tracking_id,
                            m.created_by, m.commissioning_date, m.created_at, m.source,
                            m.batch_no, m.old_fileno, m.phone_no, m.rep_phone_no, p.name AS purpose_name,
                            rot.root_of_title,
                            geo.latitude, geo.longitude
                     FROM mls_file_no m
                     LEFT JOIN purposes p ON p.id = m.purpose_id
                     OUTER APPLY (
                         SELECT TOP 1 fi.root_of_title
                         FROM file_indexings fi
                         WHERE fi.file_number = m.full_file_number
                           AND fi.root_of_title IS NOT NULL
                           AND LTRIM(RTRIM(fi.root_of_title)) != ''
                         ORDER BY fi.id DESC
                     ) AS rot
                     OUTER APPLY (
                         SELECT TOP 1 fi.latitude, fi.longitude
                         FROM file_indexings fi
                         WHERE fi.file_number = m.full_file_number
                           AND fi.latitude IS NOT NULL AND fi.longitude IS NOT NULL
                         ORDER BY fi.id DESC
                     ) AS geo
                     WHERE m.id IN ({$tempInList})"
                );

                $passportService = app(\App\Services\FilePassportService::class);
                $passportService->prime(collect($tempRows)->pluck('full_file_number')->filter()->all());

                foreach ($tempRows as $row) {
                    $fileNo = trim($row->full_file_number ?? '');
                    // Build a fake action column for temp files (view only, no batch)
                    $id = (int) $row->id;
                    $actionHtml = '
                    <div class="relative action-dropdown">
                        <button type="button" class="p-1 rounded-full hover:bg-slate-100 transition-colors">
                            <i data-lucide="more-horizontal" class="w-5 h-5 text-slate-500"></i>
                        </button>
                        <div class="action-dropdown-menu"><div class="py-1">
                            <button onclick="editRecord(' . $id . ', \'Temporary\')" class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-slate-700 hover:bg-slate-50">
                                <i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i>
                                <span class="font-medium">Edit Record</span>
                            </button>
                        </div></div>
                    </div>';

                    $tempMap[$id] = [
                        'id'                          => $id,
                        'primaryFileNo'               => $fileNo ?: 'N/A',
                        'relatedFileNo'               => 'N/A',
                        'mlsfNo'                      => $fileNo ?: 'N/A',
                        'kangisFileNo'                => 'N/A',
                        'NewKANGISFileNo'             => 'N/A',
                        'stFileNo'                    => 'N/A',
                        'FileName'                    => trim($row->file_name ?? '') ?: 'N/A',
                        'land_use'                    => trim($row->land_use ?? '') ?: 'N/A',
                        'customer_type'               => trim($row->customer_type ?? '') ?: 'N/A',
                        'purpose_name'                => trim($row->purpose_name ?? '') ?: 'N/A',
                        'plot_no'                     => trim($row->plot_no ?? '') ?: 'N/A',
                        'tp_no'                       => trim($row->tp_no ?? '') ?: 'N/A',
                        'location'                    => trim($row->location ?? '') ?: 'N/A',
                        'lga'                         => trim($row->lga ?? '') ?: 'N/A',
                        'district'                    => trim($row->district ?? '') ?: 'N/A',
                        'tracking_id'                 => trim($row->tracking_id ?? '') ?: 'N/A',
                        'type'                        => 'Temporary',
                        'passport_url'                => $passportService->resolve($fileNo)['url'] ?? null,
                        'root_of_title'               => trim((string) ($row->root_of_title ?? '')) ?: 'N/A',
                        'is_root_of_title'            => $this->isRootOfTitleFile(
                            $row->source ?? null,
                            $row->root_of_title ?? null,
                            $fileNo
                        ),
                        'root_of_title_ref'           => '',
                        'related_old_fileno'          => $this->formatRelatedOrOldFileNo(null, $row->old_fileno ?? null),
                        'phone_no'                    => trim((string) ($row->phone_no ?? '')),
                        'rep_phone_no'                => trim((string) ($row->rep_phone_no ?? '')),
                        'email'                       => '',
                        // A temporary number carries no commissioning related-file list.
                        'related_files'               => array_values(array_filter([
                            trim((string) ($row->old_fileno ?? '')) !== ''
                                ? ['file_no' => trim((string) $row->old_fileno), 'type' => 'Old File Number']
                                : null,
                        ])),
                        'created_by'                  => trim($row->created_by ?? '') ?: 'System',
                        'source'                      => trim($row->source ?? '') ?: 'Temporary File',
                        'source_instrument_capture_id'=> null,
                        'source_pra_id'               => null,
                        'source_prop_id'              => null,
                        'source_temp_fileno'          => 'N/A',
                        'batch_no'                    => trim($row->batch_no ?? ''),
                        'batch_count'                 => 1,
                        'batch_first_file'            => $fileNo,
                        'commissioning_date'          => $row->commissioning_date ? date('Y-m-d', strtotime($row->commissioning_date)) : 'N/A',
                        'has_commissioning_sheet'     => false,
                        'latitude'                    => $this->formatCoordinate($row->latitude ?? null),
                        'longitude'                   => $this->formatCoordinate($row->longitude ?? null),
                        'created_at'                  => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A',
                        'action'                      => $actionHtml,
                    ];
                }
            }

            // Plot-extension rows for this page (already formatted in $plotExtRows), keyed by id.
            $plotMap = $plotExtRows->keyBy('id');

            // Assemble the page in phase-1 (date) order, interleaving all three sources.
            $mergedData = [];
            foreach ($pageOrder as [$stype, $sid]) {
                if ($stype === 'F' && isset($fileMap[$sid])) {
                    $mergedData[] = $fileMap[$sid];
                } elseif ($stype === 'T' && isset($tempMap[$sid])) {
                    $mergedData[] = $tempMap[$sid];
                } elseif ($stype === 'P' && isset($plotMap[$sid])) {
                    $mergedData[] = $plotMap[$sid];
                }
            }
            $mergedData = array_values($mergedData);

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $filteredRecords,
                'data' => $mergedData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileNumberController getData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Format one pinned coordinate for the table's Latitude / Longitude columns.
     *
     * Only file_indexings stores the pin, and most files were never pinned, so a null
     * here is the normal case. 0 is treated as unpinned: 0,0 is the Gulf of Guinea,
     * never a Kano parcel.
     */
    private function formatCoordinate($value): string
    {
        if (!is_numeric($value) || (float) $value == 0.0) {
            return 'N/A';
        }

        return number_format((float) $value, 6, '.', '');
    }

    /**
     * A TP number as it should be stored, or null when what arrived was the picker's
     * "Other" placeholder rather than a number. Twin of the same guard on the
     * commissioning paths in MlsFileNoController.
     */
    private function sanitizeTpNumber($value): ?string
    {
        $tpNo = trim((string) ($value ?? ''));

        if ($tpNo === '' || in_array(strtolower($tpNo), ['__other__', 'other', 'others'], true)) {
            return null;
        }

        return $tpNo;
    }

    /**
     * Match the Root of Title decision used by Legal Search for commissioned files.
     *
     * A recertification is the one case the source alone gets wrong. An RC file is
     * commissioned through the ordinary Direct Allocation route, so the rule below used to
     * mark it as its own root — but a recertification re-issues a title that some earlier
     * file already granted. The root belongs to the file it was raised from, which is why
     * `$fileNo` is taken into account and the marker moves to the related/old number
     * (see rootOfTitleReference()).
     */
    private function isRootOfTitleFile(?string $commissioningSource, ?string $indexedRoot, ?string $fileNo = null): bool
    {
        if (RecertificationFile::isRecertFileNumber($fileNo)) {
            return false;
        }

        if (trim((string) $indexedRoot) !== '') {
            return true;
        }

        $source = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $commissioningSource)));

        return $source === 'direct allocation'
            || $source === 'allocation list'
            || $source === 'op'
            || str_starts_with($source, 'op ')
            || str_contains($source, 'occupancy permit');
    }

    /**
     * The file number that carries the Root of Title on behalf of this row, or '' when the
     * row carries it itself.
     *
     * Only a recertification has one: its root is the original file it was raised from,
     * which is the related/old number already shown beneath it. Returning the number rather
     * than a flag lets the table print the "-RoT" marker against that number instead of
     * against the recertification's own title.
     */
    private function rootOfTitleReference(?string $fileNo, array $relatedOrOld): string
    {
        if (!RecertificationFile::isRecertFileNumber($fileNo)) {
            return '';
        }

        $value = trim((string) ($relatedOrOld['value'] ?? ''));

        return ($value === '' || $value === 'N/A') ? '' : $value;
    }

    /**
     * Build formatted Plot Extension rows for the file-numbers DataTable.
     *
     * Plot Extensions live in their own isolated `plot_extensions` table and retain
     * the ORIGINAL file number (no " AND EXTENSION" suffix, no mls_file_no row). They
     * are prepended to the "New" view just like temporary files.
     */
    private function formatPlotExtensionRows(string $source, ?string $search): \Illuminate\Support\Collection
    {
        if ($source !== 'New') {
            return collect();
        }

        $search = $search ?? '';
        $searchSql = '';
        $bindings = [];
        if (!empty($search)) {
            $pct = "%{$search}%";
            $searchSql = "AND (pe.original_file_no LIKE ? OR pe.file_name LIKE ? OR pe.tracking_id LIKE ?
                OR pe.location LIKE ? OR pe.lga LIKE ? OR pe.plot_no LIKE ? OR pe.tp_no LIKE ?)";
            $bindings = [$pct, $pct, $pct, $pct, $pct, $pct, $pct];
        }

        $rows = DB::connection('sqlsrv')->select(
            "SELECT pe.id, pe.original_file_no, pe.file_name, pe.land_use, pe.customer_type,
                    pe.plot_no, pe.tp_no, pe.location, pe.lga, pe.district, pe.tracking_id,
                    pe.created_by, pe.created_at, p.name AS purpose_name,
                    rot.root_of_title,
                    geo.latitude, geo.longitude
             FROM plot_extensions pe
             LEFT JOIN purposes p ON p.id = pe.purpose_id
             OUTER APPLY (
                 SELECT TOP 1 fi.root_of_title
                 FROM file_indexings fi
                 WHERE fi.file_number = pe.original_file_no
                   AND fi.root_of_title IS NOT NULL
                   AND LTRIM(RTRIM(fi.root_of_title)) != ''
                 ORDER BY fi.id DESC
             ) AS rot
             OUTER APPLY (
                 SELECT TOP 1 fi.latitude, fi.longitude
                 FROM file_indexings fi
                 WHERE fi.file_number = pe.original_file_no
                   AND fi.latitude IS NOT NULL AND fi.longitude IS NOT NULL
                 ORDER BY fi.id DESC
             ) AS geo
             WHERE (pe.is_deleted IS NULL OR pe.is_deleted = 0)
               {$searchSql}
             ORDER BY pe.id DESC",
            $bindings
        );

        $passportService = app(\App\Services\FilePassportService::class);
        $passportService->prime(collect($rows)->pluck('original_file_no')->filter()->all());

        return collect($rows)->map(function ($row) use ($passportService) {
            $fileNo = trim($row->original_file_no ?? '');
            $actionHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800" title="Plot Extension transaction">Plot Extension</span>';

            return [
                'id'                          => (int) $row->id,
                'primaryFileNo'               => $fileNo ?: 'N/A',
                'relatedFileNo'               => 'N/A',
                'mlsfNo'                      => $fileNo ?: 'N/A',
                'kangisFileNo'                => 'N/A',
                'NewKANGISFileNo'             => 'N/A',
                'stFileNo'                    => 'N/A',
                'FileName'                    => trim($row->file_name ?? '') ?: 'N/A',
                'land_use'                    => trim($row->land_use ?? '') ?: 'N/A',
                'customer_type'               => trim($row->customer_type ?? '') ?: 'N/A',
                'purpose_name'                => trim($row->purpose_name ?? '') ?: 'N/A',
                'plot_no'                     => trim($row->plot_no ?? '') ?: 'N/A',
                'tp_no'                       => trim($row->tp_no ?? '') ?: 'N/A',
                'location'                    => trim($row->location ?? '') ?: 'N/A',
                'lga'                         => trim($row->lga ?? '') ?: 'N/A',
                'district'                    => trim($row->district ?? '') ?: 'N/A',
                'tracking_id'                 => trim($row->tracking_id ?? '') ?: 'N/A',
                'type'                        => 'Plot Extension',
                'passport_url'                => $passportService->resolve($fileNo)['url'] ?? null,
                'root_of_title'               => trim((string) ($row->root_of_title ?? '')) ?: 'N/A',
                'is_root_of_title'            => $this->isRootOfTitleFile(
                    'Plot Extension',
                    $row->root_of_title ?? null,
                    $fileNo
                ),
                'root_of_title_ref'           => '',
                'related_old_fileno'          => ['value' => 'N/A', 'kind' => 'none'],
                'related_files'               => [],
                'phone_no'                    => '',
                'rep_phone_no'                => '',
                'email'                       => '',
                'latitude'                    => $this->formatCoordinate($row->latitude ?? null),
                'longitude'                   => $this->formatCoordinate($row->longitude ?? null),
                'created_by'                  => trim($row->created_by ?? '') ?: 'System',
                'source'                      => 'Plot Extension',
                'source_instrument_capture_id'=> null,
                'source_pra_id'               => null,
                'source_prop_id'              => null,
                'source_temp_fileno'          => 'N/A',
                'batch_no'                    => '',
                'batch_count'                 => 1,
                'batch_first_file'            => $fileNo,
                'commissioning_date'          => $row->created_at ? date('Y-m-d', strtotime($row->created_at)) : 'N/A',
                'has_commissioning_sheet'     => false,
                'created_at'                  => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : 'N/A',
                'action'                      => $actionHtml,
            ];
        });
    }

    /**
     * Get data for Capture Existing Files DataTables (Global View)
     */
    public function getCaptureData(Request $request)
    {
        // Global view — show ALL file numbers
        $request->merge(['source' => 'All']);
        return $this->getData($request);
    }

    protected function buildCaptureActionColumn($row, $source = null): string
    {
        $id = (int) ($row->id ?? 0);
        $batchNo = trim($row->derived_batch_no ?? $row->batch_no ?? '');
        $hasBatchNo = !empty($batchNo);

        if ($id <= 0) {
            return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-xs font-semibold text-slate-500">No actions</span>';
        }

        $isSupperAdmin = Auth::user() && Auth::user()->assign_role === 'Supper Admin';

        // Use the same dropdown structure as the main table for consistency and responsiveness
        $dropdown = '
        <div class="relative action-dropdown">
            <button type="button" class="p-1 rounded-full hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-200">
                <i data-lucide="more-horizontal" class="w-5 h-5 text-slate-500"></i>
            </button>
            
            <div class="action-dropdown-menu">
                <div class="py-1">
                    <!-- Edit -->
                    <button onclick="editRecord(' . $id . ')" 
                            class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-slate-700 hover:bg-slate-50 transition-colors">
                        <i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i>
                        <span class="font-medium">Edit Record</span>
                    </button>';

        if ($isSupperAdmin) {
            $dropdown .= '
                    <!-- Delete -->
                    <button onclick="deleteRecord(' . $id . ')" 
                            class="w-full text-left px-4 py-2.5 text-sm flex items-center space-x-3 text-red-600 hover:bg-red-50 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        <span class="font-medium">Delete Record</span>
                    </button>';
        }

        if ($hasBatchNo && $source !== 'Captured') {
            $dropdown .= '
                    <div class="border-t border-slate-100 my-1"></div>
                    
                    <!-- Printer Manager -->
                    <button onclick="openPrinterManager(\'' . $id . '\', \'' . $batchNo . '\')" 
                            class="w-full text-left px-4 py-2.5 text-sm text-blue-600 hover:bg-blue-50 flex items-center space-x-3"
                            title="Manage printing protocols for this batch">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span class="font-medium">Printer Manager</span>
                    </button>';
        }

        $dropdown .= '
                </div>
            </div>
        </div>';

        return $dropdown;
    }

    /**
     * Get the next serial number for the current year
     */
    public function getNextSerial(Request $request)
    {
        $currentYear = $request->get('year', date('Y'));

        try {
            // Get all records for the current year and extract serial numbers
            // Filter by type = 'Generated' to only consider generated file numbers
            $records = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('mlsfNo', 'like', '%-' . $currentYear . '-%')
                ->where('SOURCE', 'MLS_Commissioned')
                ->where('type', 'MlsFileNO')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->get();

            $maxSerial = 0;

            foreach ($records as $record) {
                if ($record->mlsfNo) {
                    // Extract serial number from patterns like: RES-2024-0001, CON-IND-42154, etc.
                    // Look for the last number in the string that could be a serial
                    if (preg_match('/-(\d+)(?:\(T\))?(?:\s+AND\s+EXTENSION)?$/', $record->mlsfNo, $matches)) {
                        $serial = (int) $matches[1];
                        if ($serial > $maxSerial) {
                            $maxSerial = $serial;
                        }
                    }
                }
            }

            $nextSerial = $maxSerial + 1;

            return response()->json(['nextSerial' => $nextSerial]);

        } catch (\Exception $e) {
            \Log::error('Error getting next serial number: ' . $e->getMessage());
            return response()->json(['nextSerial' => 1]);
        }
    }

    /**
     * Get existing file numbers for extension dropdown
     */
    public function getExistingFileNumbers()
    {
        try {
            $fileNumbers = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select('mlsfNo')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('mlsfNo', 'desc')
                ->limit(100)
                ->get();

            return response()->json($fileNumbers);

        } catch (\Exception $e) {
            \Log::error('Error getting existing file numbers: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Store a new MLS File number
     */
    public function store(Request $request)
    {
        // Validate required fields
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'lga' => 'required|string|max:100',
            'tracking_id' => 'nullable|string|max:100',
            'location' => 'required|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'rep_phone_no' => 'nullable|string|max:50',
            'rep_address' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $fileOption = $request->file_option;
            $mlsfNo = '';

            // Debug: Log the request data
            \Log::info('FileNumber store request data:', [
                'file_option' => $request->file_option,
                'serial_no' => $request->serial_no,
                'land_use' => $request->land_use,
                'year' => $request->year,
                'file_name' => $request->file_name,
                'tracking_id' => $request->tracking_id
            ]);

            if ($fileOption === 'extension') {
                // For extensions, use the existing file number with "AND EXTENSION" unless the
                // officer opted out — see suppress_extension_suffix in the commission modal.
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = filter_var($request->input('suppress_extension_suffix', false), FILTER_VALIDATE_BOOLEAN)
                    ? $existingFileNo
                    : $existingFileNo . ' AND EXTENSION';
            } elseif ($fileOption === 'temporary') {
                // For temporary files, use the existing file number with "(T)"
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . '(T)';
            } elseif ($fileOption === 'miscellaneous') {
                // Format: MISC-KN-0203
                $mlsfNo = 'MISC-' . $request->middle_prefix . '-' . $request->serial_no;
            } elseif ($fileOption === 'sltr') {
                // Format: SLTR-0203567
                $mlsfNo = 'SLTR-' . $request->serial_no;
            } elseif ($fileOption === 'sit') {
                // Format: SIT-2025-0203567
                $mlsfNo = 'SIT-' . $request->year . '-' . $request->serial_no;
            } else {
                // Generate new file number for normal files - no padding for serial number
                $mlsfNo = $request->land_use . '-' . $request->year . '-' . $request->serial_no;
            }

            $trackingId = $this->getUniqueTrackingId($request->input('tracking_id'));

            // Only validate for duplicates (skip validation for extension and temporary files).
            // An extension whose suffix was suppressed reuses the original number verbatim, so it
            // is matched on $fileOption rather than on the suffix.
            if ($fileOption !== 'extension' && !str_ends_with($mlsfNo, ' AND EXTENSION') && !str_ends_with($mlsfNo, '(T)')) {
                $exists = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $mlsfNo)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File number already exists: ' . $mlsfNo
                    ], 409);
                }
            }

            // Insert new record - only populate mlsfNo field, leave others null
            $id = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->insertGetId([
                    'tracking_id' => $trackingId,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,  // Leave empty
                    'NewKANGISFileNo' => null,  // Leave empty
                    'FileName' => $validatedData['file_name'],
                    'plot_no' => $request->plot_no,
                    'tp_no' => $request->tp_no,
                    'location' => $validatedData['location'],
                    'lga' => $validatedData['lga'],
                    'customer_type' => $validatedData['customer_type'],
                    'type' => 'MlsFileNO',
                    'SOURCE' => 'MLS_Commissioned',
                    'is_deleted' => 0,
                    'created_by' => (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'phone_no' => $request->phone_no,
                    'address' => $request->address,
                    'rep_phone_no' => $request->rep_phone_no,
                    'rep_address' => $request->rep_address
                ]);

            // Save to mls_file_no as well for customer_type and other details
            DB::connection('sqlsrv')->table('mls_file_no')->insert([
                'full_file_number' => $mlsfNo,
                'file_name' => $validatedData['file_name'],
                'customer_type' => $validatedData['customer_type'] ?? 'Individual',
                'purpose_id' => $request->purpose_id,
                'tracking_id' => $trackingId,
                'land_use' => $request->land_use,
                'location' => $validatedData['location'],
                'lga' => $validatedData['lga'],
                'file_option' => $fileOption,
                'source' => $fileOption === 'temporary' ? 'Temporary File' : ($fileOption === 'extension' ? 'Extension File' : 'Direct Allocation'),
                'created_at' => now(),
                'updated_at' => now(),
                'phone_no' => $request->phone_no,
                'address' => $request->address,
                'rep_phone_no' => $request->rep_phone_no,
                'rep_address' => $request->rep_address
            ]);

            return response()->json([
                'success' => true,
                'message' => 'MLPP File number generated successfully: ' . $mlsfNo,
                'data' => [
                    'id' => $id,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,
                    'NewKANGISFileNo' => null,
                    'FileName' => $request->file_name,
                    'tracking_id' => $trackingId
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generating MLS File number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating MLPP File number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a captured existing file number
     */
    public function captureStore(Request $request)
    {
        // Only validate file name and that we have a generated file number
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'lga' => 'required|string|max:100',
            'tracking_id' => 'nullable|string|max:100',
            'location' => 'required|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'land_use' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $fileOption = $request->file_option;
            $mlsfNo = '';

            // Generate the complete file number based on file option
            if ($fileOption === 'extension') {
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                // The suffix is opt-out — see suppress_extension_suffix in the commission modal.
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = filter_var($request->input('suppress_extension_suffix', false), FILTER_VALIDATE_BOOLEAN)
                    ? $existingFileNo
                    : $existingFileNo . ' AND EXTENSION';
            } elseif ($fileOption === 'temporary') {
                // For temporary files, use the existing file number with "(T)"
                // Handle both dropdown (existing_file_no) and manual input (existing_file_no_manual)
                $existingFileNo = $request->existing_file_no ?: $request->existing_file_no_manual;
                $mlsfNo = $existingFileNo . '(T)';
            } elseif ($fileOption === 'miscellaneous') {
                $mlsfNo = 'MISC-' . $request->middle_prefix . '-' . $request->serial_no;
            } elseif ($fileOption === 'old_mls') {
                $mlsfNo = 'KN ' . $request->serial_no;
            } elseif ($fileOption === 'sltr') {
                $mlsfNo = 'SLTR-' . $request->serial_no;
            } elseif ($fileOption === 'sit') {
                $mlsfNo = 'SIT-' . $request->year . '-' . $request->serial_no;
            } else {
                // Normal format - no padding for serial number  
                $mlsfNo = $request->prefix . '-' . $request->year . '-' . $request->serial_no;
            }

            $trackingId = $this->getUniqueTrackingId($request->input('tracking_id'));

            // Only validate for duplicates (skip validation for extension and temporary files).
            // An extension whose suffix was suppressed reuses the original number verbatim, so it
            // is matched on $fileOption rather than on the suffix.
            if ($fileOption !== 'extension' && !str_ends_with($mlsfNo, ' AND EXTENSION') && !str_ends_with($mlsfNo, '(T)')) {
                $exists = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $mlsfNo)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File number already exists: ' . $mlsfNo
                    ], 409);
                }
            }

            // Insert new record with only mlsfNo and file name
            $id = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->insertGetId([
                    'tracking_id' => $trackingId,
                    'mlsfNo' => $mlsfNo,
                    'kangisFileNo' => null,
                    'NewKANGISFileNo' => null,
                    'FileName' => $validatedData['file_name'],
                    'plot_no' => $request->plot_no,
                    'tp_no' => $request->tp_no,
                    'location' => $validatedData['location'],
                    'lga' => $validatedData['lga'],
                    'customer_type' => $validatedData['customer_type'],
                    'phone_no' => $request->phone_no,
                    'address' => $request->address,
                    'type' => 'Captured',
                    'is_deleted' => 0,
                    'created_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

            // Save to mls_file_no as well for customer_type and other details
            DB::connection('sqlsrv')->table('mls_file_no')->insert([
                'full_file_number' => $mlsfNo,
                'file_name' => $validatedData['file_name'],
                'customer_type' => $validatedData['customer_type'] ?? 'Individual',
                'purpose_id' => $request->purpose_id,
                'tracking_id' => $trackingId,
                'land_use' => $request->land_use ?: $request->prefix,
                'location' => $validatedData['location'],
                'lga' => $validatedData['lga'],
                'phone_no' => $request->phone_no,
                'address' => $request->address,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Existing file number captured successfully: ' . $mlsfNo,
                'data' => [
                    'id' => $id,
                    'mlsfNo' => $mlsfNo,
                    'FileName' => $validatedData['file_name'],
                    'tracking_id' => $trackingId
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error capturing existing file number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error capturing existing file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate data from CSV file (simple and efficient)
     */
    public function migrate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:csv,txt|max:20480' // Only CSV files, 20MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload a valid CSV file. Max size: 20MB',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Increase memory limit and execution time for large files
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300); // 5 minutes

            $file = $request->file('excel_file');
            $filePath = $file->getPathname();

            \Log::info("CSV Migration started for file: " . $file->getClientOriginalName());

            $imported = 0;
            $duplicates = 0;
            $errors = 0;
            $batchSize = 100;
            $batch = [];
            $rowNumber = 0;

            // Get existing records to check for duplicates
            $existingMlsfNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('mlsfNo')
                ->where('mlsfNo', '!=', '')
                ->pluck('mlsfNo')
                ->toArray();

            $existingKangisNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('kangisFileNo')
                ->where('kangisFileNo', '!=', '')
                ->pluck('kangisFileNo')
                ->toArray();

            $existingNewKangisNos = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->whereNotNull('NewKANGISFileNo')
                ->where('NewKANGISFileNo', '!=', '')
                ->pluck('NewKANGISFileNo')
                ->toArray();

            // Open and read CSV file
            if (($handle = fopen($filePath, 'r')) !== FALSE) {

                // Read header row to understand column structure
                $header = fgetcsv($handle, 1000, ',');

                if (!$header) {
                    throw new \Exception('Could not read CSV header row');
                }

                \Log::info("CSV Header: " . implode(', ', $header));

                // Find column indexes (case insensitive)
                $mlsfNoIndex = -1;
                $kangisFileIndex = -1;
                $newKangisFileNoIndex = -1;
                $fileNameIndex = -1;

                foreach ($header as $index => $column) {
                    $column = strtolower(trim($column));
                    if (in_array($column, ['mlsfno', 'mls_file_no', 'mlsfileno'])) {
                        $mlsfNoIndex = $index;
                    } elseif (in_array($column, ['kangisfile', 'kangis_file', 'kangisfileno'])) {
                        $kangisFileIndex = $index;
                    } elseif (in_array($column, ['newkangisfileno', 'new_kangis_file_no', 'newkangisfile'])) {
                        $newKangisFileNoIndex = $index;
                    } elseif (in_array($column, ['filename', 'file_name', 'name'])) {
                        $fileNameIndex = $index;
                    }
                }

                \Log::info("Column mapping - mlsfNo: {$mlsfNoIndex}, kangisFile: {$kangisFileIndex}, newKangisFileNo: {$newKangisFileNoIndex}, fileName: {$fileNameIndex}");

                // Process each data row
                while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $rowNumber++;

                    try {
                        // Skip empty rows
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        // Extract data based on column indexes
                        $mlsfNo = trim($row[$mlsfNoIndex] ?? '');
                        $kangisFileNo = trim($row[$kangisFileIndex] ?? '');
                        $newKangisFileNo = trim($row[$newKangisFileNoIndex] ?? '');
                        $fileName = trim($row[$fileNameIndex] ?? '');

                        // Skip if all essential data is missing
                        if (empty($mlsfNo) && empty($kangisFileNo) && empty($newKangisFileNo)) {
                            continue;
                        }

                        // Check for duplicates
                        $isDuplicate = false;
                        if (!empty($mlsfNo) && in_array($mlsfNo, $existingMlsfNos)) {
                            $isDuplicate = true;
                        } elseif (!empty($kangisFileNo) && in_array($kangisFileNo, $existingKangisNos)) {
                            $isDuplicate = true;
                        } elseif (!empty($newKangisFileNo) && in_array($newKangisFileNo, $existingNewKangisNos)) {
                            $isDuplicate = true;
                        }

                        if ($isDuplicate) {
                            $duplicates++;
                            continue;
                        }

                        // Add to batch
                        $batch[] = [
                            'tracking_id' => $this->getUniqueTrackingId(),
                            'mlsfNo' => !empty($mlsfNo) ? $mlsfNo : null,
                            'kangisFileNo' => !empty($kangisFileNo) ? $kangisFileNo : null,
                            'NewKANGISFileNo' => !empty($newKangisFileNo) ? $newKangisFileNo : null,
                            'FileName' => !empty($fileName) ? $fileName : null,
                            'type' => 'Migrated',
                            'is_deleted' => 0,
                            'created_by' => 'Migrated',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];

                        // Update existing arrays to prevent duplicates within the same import
                        if (!empty($mlsfNo))
                            $existingMlsfNos[] = $mlsfNo;
                        if (!empty($kangisFileNo))
                            $existingKangisNos[] = $kangisFileNo;
                        if (!empty($newKangisFileNo))
                            $existingNewKangisNos[] = $newKangisFileNo;

                        // Insert batch when it reaches the batch size
                        if (count($batch) >= $batchSize) {
                            DB::connection('sqlsrv')->table('fileNumber')->insert($batch);
                            $imported += count($batch);
                            $batch = [];

                            // Log progress every 1000 records
                            if ($imported % 1000 == 0) {
                                \Log::info("Migration progress: {$imported} records imported");
                            }
                        }

                    } catch (\Exception $e) {
                        \Log::error("Error importing CSV row {$rowNumber}: " . $e->getMessage());
                        $errors++;
                    }
                }

                // Insert remaining batch
                if (!empty($batch)) {
                    DB::connection('sqlsrv')->table('fileNumber')->insert($batch);
                    $imported += count($batch);
                }

                fclose($handle);

            } else {
                throw new \Exception('Could not open CSV file for reading');
            }

            // Clean up memory
            unset($existingMlsfNos, $existingKangisNos, $existingNewKangisNos, $batch);

            \Log::info("CSV Migration completed: {$imported} imported, {$duplicates} duplicates, {$errors} errors");

            return response()->json([
                'success' => true,
                'message' => "CSV migration completed successfully! Imported: {$imported}, Duplicates skipped: {$duplicates}, Errors: {$errors}",
                'data' => [
                    'imported' => $imported,
                    'duplicates' => $duplicates,
                    'errors' => $errors,
                    'total_processed' => $imported + $duplicates + $errors,
                    'rows_processed' => $rowNumber
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error during CSV migration: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error during CSV migration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific record
     */
    public function show($id, Request $request)
    {
        try {
            // Plot Extensions live in their own isolated `plot_extensions` table and
            // reuse numeric ids that collide with `fileNumber.id`. When the DataTable
            // row is a Plot Extension it flags entity=plot_extension so we resolve the
            // record from the right table and backfill the edit form correctly.
            if ($request->query('entity') === 'plot_extension') {
                $pe = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('id', $id)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->first();

                if (!$pe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Record not found'
                    ], 404);
                }

                return response()->json([
                    'id'            => $pe->id,
                    'entity'        => 'plot_extension',
                    'mlsfNo'        => $pe->original_file_no,
                    'kangisFileNo'  => $pe->original_file_no,
                    'FileName'      => $pe->file_name,
                    'plot_no'       => $pe->plot_no,
                    'tp_no'         => $pe->tp_no,
                    'location'      => $pe->location,
                    'lga'           => $pe->lga,
                    'district'      => $pe->district,
                    'customer_type' => $pe->customer_type,
                    'purpose_id'    => $pe->purpose_id,
                    'phone_no'      => $pe->phone_no,
                    'address'       => $pe->address,
                    // Plot extensions keep no related/old file number of their own.
                    'related_fileno' => null,
                    'old_fileno'     => null,
                    // The photograph belongs to the original file, which is what a plot
                    // extension is an extension of.
                    'passport_url'   => app(\App\Services\FilePassportService::class)
                        ->resolve($pe->original_file_no ?? null)['url'] ?? null,
                ]);
            }

            // Temporary files exist ONLY in mls_file_no — the list's "T" branch takes its
            // id straight from that table, and those ids collide with fileNumber's. Left
            // unhandled, a temporary row loaded a different file into the edit form and
            // then saved over it: temporary RES-1993-2644(T) is mls_file_no id 1166, and
            // fileNumber 1166 is the unrelated live file CON-AG-1987-57.
            if (\App\Support\MlsRowTarget::entity($request->query('entity')) === \App\Support\MlsRowTarget::TEMPORARY) {
                $temp = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('id', $id)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->first();

                if (!$temp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Record not found'
                    ], 404);
                }

                return response()->json([
                    'id'             => $temp->id,
                    'entity'         => \App\Support\MlsRowTarget::TEMPORARY,
                    'mlsfNo'         => $temp->full_file_number,
                    'kangisFileNo'   => null,
                    'FileName'       => $temp->file_name,
                    'plot_no'        => $temp->plot_no,
                    'tp_no'          => $temp->tp_no,
                    'location'       => $temp->location,
                    'lga'            => $temp->lga,
                    'district'       => $temp->district,
                    'customer_type'  => $temp->customer_type,
                    'purpose_id'     => $temp->purpose_id,
                    'phone_no'       => $temp->phone_no,
                    'address'        => $temp->address,
                    'rep_phone_no'   => $temp->rep_phone_no,
                    'rep_address'    => $temp->rep_address,
                    'related_fileno' => null,
                    'old_fileno'     => $temp->old_fileno,
                    // A temporary file is never batched — it has no fileNumber row to group.
                    'batch_no'       => null,
                    'batch_count'    => 1,
                    'passport_url'   => app(\App\Services\FilePassportService::class)
                        ->resolve($temp->full_file_number ?? null)['url'] ?? null,
                ]);
            }

            $record = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                ->leftJoin('mother_applications as ma', 'ma.id', '=', 'fileNumber.application_id')
                // Latest file indexing record for this file number (for district/lga/plot/tp).
                ->leftJoinSub(
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->select(['file_number', DB::raw('MAX(id) as max_id')])
                        ->groupBy('file_number'),
                    'fi_max',
                    'fi_max.file_number',
                    '=',
                    'fileNumber.mlsfNo'
                )
                ->leftJoin('file_indexings as fi', 'fi.id', '=', 'fi_max.max_id')
                ->select([
                    'fileNumber.*',
                    // Coalesce crucial fields across every source so the edit form
                    // backfills whatever data exists (fileNumber -> mls_file_no ->
                    // file indexing -> mother application).
                    DB::raw('COALESCE(fileNumber.lga, mls_file_no.lga, fi.lga, ma.property_lga) as lga'),
                    DB::raw('COALESCE(fileNumber.FileName, mls_file_no.file_name, fi.file_title) as FileName'),
                    DB::raw('COALESCE(fileNumber.plot_no, mls_file_no.plot_no, fi.plot_number, ma.property_plot_no) as plot_no'),
                    DB::raw('COALESCE(fileNumber.tp_no, mls_file_no.tp_no, fi.tp_no) as tp_no'),
                    DB::raw('COALESCE(fileNumber.location, mls_file_no.location) as location'),
                    DB::raw('COALESCE(fileNumber.district, mls_file_no.district, fi.district, ma.property_district) as district'),
                    'ma.property_house_no as ma_house_no',
                    'ma.property_street_name as ma_street_name',
                    'mls_file_no.customer_type',
                    'mls_file_no.purpose_id',
                    // The edit modal shows one field for both, picked by a checkbox.
                    'mls_file_no.old_fileno'
                ])
                ->where('fileNumber.id', $id)
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                })
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            // The applicant's passport, if one was ever filed (at commissioning or from a
            // previous edit). Null simply means this file has no photograph on record —
            // the edit form then shows an empty slot rather than an error.
            $record->passport_url = app(\App\Services\FilePassportService::class)
                ->resolve($record->mlsfNo ?? $record->kangisFileNo ?? null)['url'] ?? null;

            // If no explicit location was stored, assemble one from the mother
            // application's property parts so the edit form still shows something.
            if (empty(trim((string) ($record->location ?? '')))) {
                $parts = array_values(array_filter([
                    $record->ma_house_no ?? null,
                    $record->ma_street_name ?? null,
                    $record->district ?? null,
                ], fn ($v) => trim((string) $v) !== ''));
                if (!empty($parts)) {
                    $record->location = implode(', ', $parts);
                }
            }

            // The list draws a whole batch as ONE row labelled with a range, so the edit
            // form has to say which single file it is about to write and offer to cover
            // the rest. Without this the user edits COM-2026-84 believing they edited
            // COM-2026-78 … COM-2026-84.
            $batchService = app(\App\Services\FileNumber\BatchExpansionService::class);
            $batchNo = $batchService->batchNoFor($record);
            $batchMembers = $batchNo !== '' ? $batchService->members($batchNo) : collect();

            $record->entity = \App\Support\MlsRowTarget::FILE_NUMBER;
            $record->batch_no = $batchNo ?: null;
            $record->batch_count = max(1, $batchMembers->count());
            $record->batch_range = $batchMembers->count() > 1
                ? $batchService->describeRange($batchMembers)
                : null;

            return response()->json($record);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a record (file name, KANGIS file number, and New KANGIS file number can be updated)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string|max:255',
            'kangis_file_no' => 'nullable|string|max:255',
            'new_kangis_file_no' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'purpose_id' => 'nullable|integer',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'rep_phone_no' => 'nullable|string|max:50',
            'rep_address' => 'nullable|string|max:255',
            'related_fileno' => 'nullable|string|max:255',
            'is_old_fileno' => 'nullable|boolean',
            // Batch scope. A batch row stands for N files; `apply_to_batch` opts into
            // writing all of them, and `batch_no` is verified against the record itself
            // before it is expanded (see BatchExpansionService::resolveFor).
            'batch_no' => 'nullable|string|max:100',
            'apply_to_batch' => 'nullable|boolean',
            'confirm_batch_divergence' => 'nullable|boolean',
            'confirm_transaction_change' => 'nullable|boolean',
            // Replacement passport photograph. Optional on edit — an edit that does not
            // touch the photo leaves whatever is already filed untouched. Same limits as
            // the commissioning form so a photo accepted there is accepted here.
            'passport' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            // Empty upload keeps the current image; this flag is posted only by the
            // explicit Remove Passport Photograph control in the FC edit modal.
            'remove_passport' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // The TP picker's "Other (specify)" row is a UI sentinel, not a TP number. The
        // form replaces it with what the officer types in the specify box, but a stale
        // post can still carry the placeholder through — and land_recommendations already
        // holds rows where exactly that was saved as the TP number.
        // Only when it was actually sent: every consumer below decides whether to touch
        // the column with $request->has('tp_no'), so merging unconditionally would turn
        // "field not submitted" into "clear the TP number".
        if ($request->has('tp_no')) {
            $request->merge(['tp_no' => $this->sanitizeTpNumber($request->input('tp_no'))]);
        }

        try {
            $entity = \App\Support\MlsRowTarget::entity($request->input('entity'));

            // Temporary files live only in mls_file_no and their ids collide with
            // fileNumber's, so they get their own save path for the same reason plot
            // extensions do — see updateTemporaryFile().
            if ($entity === \App\Support\MlsRowTarget::TEMPORARY) {
                return $this->updateTemporaryFile($request, $id);
            }

            // Plot Extension rows are stored in their own table with colliding ids.
            // Route the update to plot_extensions so the edit modal saves back to the
            // correct record instead of a same-id fileNumber row.
            if ($entity === \App\Support\MlsRowTarget::PLOT_EXTENSION) {
                $pe = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('id', $id)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->first();

                if (!$pe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Record not found'
                    ], 404);
                }

                $peUpdate = [
                    'file_name'  => $request->file_name,
                    'updated_at' => now(),
                ];
                if ($request->has('lga'))           $peUpdate['lga'] = $request->lga;
                if ($request->has('district'))      $peUpdate['district'] = $request->district;
                if ($request->has('plot_no'))       $peUpdate['plot_no'] = $request->plot_no;
                if ($request->has('tp_no'))         $peUpdate['tp_no'] = $request->tp_no;
                if ($request->has('location'))      $peUpdate['location'] = $request->location;
                if ($request->has('purpose_id'))    $peUpdate['purpose_id'] = $request->purpose_id;
                if ($request->has('customer_type')) $peUpdate['customer_type'] = $request->customer_type;
                if ($request->has('phone_no'))      $peUpdate['phone_no'] = $request->phone_no;
                if ($request->has('address'))       $peUpdate['address'] = $request->address;

                DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('id', $id)
                    ->update($peUpdate);

                // Keep the matching file_indexings row in sync — the original file
                // number is retained as-is on the plot extension, so the indexing
                // screen must reflect the edited file name and location details.
                if (!empty($pe->original_file_no)) {
                    try {
                        $fiUpdate = [
                            'file_title' => $request->file_name,
                            'current_holder' => $request->file_name,
                            'updated_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
                            'updated_at' => now(),
                        ];
                        if ($request->has('location')) $fiUpdate['location'] = $request->location;
                        if ($request->has('lga'))      $fiUpdate['lga'] = $request->lga;
                        if ($request->has('district')) $fiUpdate['district'] = $request->district;
                        if ($request->has('plot_no'))  $fiUpdate['plot_number'] = $request->plot_no;
                        if ($request->has('tp_no'))    $fiUpdate['tp_no'] = $request->tp_no;

                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('file_number', $pe->original_file_no)
                            ->update($fiUpdate);
                    } catch (\Exception $e) {
                        Log::warning('Failed to propagate plot extension FileName to file_indexings', [
                            'plot_extension_id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $passportUpload = $this->storePassportIfSent($request, $pe->original_file_no ?? null);
                $passportRemoved = !$passportUpload && $this->removePassportIfRequested($request, $pe->original_file_no ?? null);

                return response()->json([
                    'success' => true,
                    'message' => 'Plot extension updated successfully',
                    'passport_url' => $passportUpload['url'] ?? null,
                    'passport_removed' => $passportRemoved,
                ]);
            }

            $record = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('id', $id)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            // ── Which files does this save cover? ──
            //
            // The list collapses a batch into ONE row carrying the id of its newest
            // member, so "edit the batch" and "edit this file" look identical on screen.
            // Default to the single clicked file — an unwanted single edit is easy to
            // undo, an unwanted batch-wide one is not — and let the user opt in.
            $applyToBatch = $request->boolean('apply_to_batch');
            $targets = collect([$record]);
            $batchNo = '';

            if ($applyToBatch) {
                $batchService = app(\App\Services\FileNumber\BatchExpansionService::class);
                $resolved = $batchService->resolveFor($record, $request->input('batch_no'));

                if (!$resolved['ok']) {
                    return response()->json([
                        'success' => false,
                        'message' => $resolved['message'],
                    ], 422);
                }

                $targets = $resolved['members'];
                $batchNo = $batchService->batchNoFor($record);

                // Most batches are one allocation and agree on every field, but ~1 in 5
                // genuinely differ per file. Flattening those is silent and irreversible,
                // so show the competing values and make the user say yes.
                if (!$request->boolean('confirm_batch_divergence')) {
                    $divergent = \App\Support\BatchDivergence::detect(
                        $targets,
                        $this->submittedWatchedFields($request),
                        self::BATCH_COLUMN_MAP
                    );

                    if (!empty($divergent)) {
                        return response()->json([
                            'success' => false,
                            'requires_batch_confirmation' => true,
                            'divergent' => $divergent,
                            'batch_count' => $targets->count(),
                            'message' => \App\Support\BatchDivergence::summarise($divergent, $targets->count()),
                        ], 409);
                    }
                }
            }

            // ── Does the name change need confirming? ──
            //
            // A name is not just a label on this screen: it is mirrored onto the customer,
            // entity and indexing records below. On a file that already has transactions
            // that is a consequential rewrite, so it is gated — the front end already
            // knows how to answer this (submitEditForm re-posts with the flag).
            $newName = trim((string) $request->file_name);
            $nameChangedAnywhere = $targets->contains(
                fn ($t) => trim((string) ($t->FileName ?? '')) !== $newName
            );

            if ($nameChangedAnywhere && !$request->boolean('confirm_transaction_change')) {
                if ($this->targetsHaveTransactions($targets)) {
                    return response()->json([
                        'success' => false,
                        'requires_confirmation' => true,
                        'message' => $targets->count() > 1
                            ? "This batch has recorded transactions. Changing the name will also update the name on the linked customer, entity and file indexing records for all {$targets->count()} files. Do you want to continue?"
                            : 'This file has recorded transactions. Changing the name will also update the name on the linked customer, entity and file indexing records. Do you want to continue?',
                    ], 409);
                }
            }

            // Every file in the batch moves together or not at all.
            $connection = DB::connection('sqlsrv');
            $connection->beginTransaction();

            try {
                foreach ($targets as $target) {
                    $this->applyFileNumberEdit($request, $target, (int) $target->id === (int) $record->id);
                }

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollBack();
                throw $e;
            }

            // The photograph belongs to the file that was opened, not to its batch
            // siblings — a batch shares an allocation, not an applicant's passport.
            $passportUpload = $this->storePassportIfSent(
                $request,
                $record->mlsfNo ?? $record->kangisFileNo ?? $record->NewKANGISFileNo ?? null
            );
            $passportRemoved = !$passportUpload && $this->removePassportIfRequested(
                $request,
                $record->mlsfNo ?? $record->kangisFileNo ?? $record->NewKANGISFileNo ?? null
            );

            $this->forgetFileNumberCaches();

            return response()->json([
                'success' => true,
                'message' => $targets->count() > 1
                    ? "Batch updated successfully — {$targets->count()} files in {$batchNo} were changed."
                    : 'Record updated successfully',
                'updated_count' => $targets->count(),
                'batch_no' => $batchNo ?: null,
                'passport_url' => $passportUpload['url'] ?? null,
                'passport_removed' => $passportRemoved,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Every related file number recorded against this page's rows, keyed by fileNumber.id.
     *
     * File Commissioning writes one `related_file_number` row per link -- the typed
     * entries from the related-files widget plus every plain one
     * (see RelatedFileNumberRegistrar / MlsFileNoController::storeRelatedFileLinks) --
     * so the register is the only store that holds ALL of them.
     * `fileNumber.related_fileno` historically received the primary number only, and
     * `mls_file_no.old_fileno` holds a single previous number, so both are merged in
     * rather than read on their own.
     *
     * @param  array<int,object> $fileRows  enriched F rows, keyed by fileNumber.id
     * @return array<int,array<int,array{file_no:string,type:string}>>
     */

    /**
     * The archive rows for a page of file numbers: file number => what replaced it.
     *
     * decommissioned_files is where every workflow that retires a number puts it — a
     * Change of Purpose renaming a parcel, a merger swallowing its sources, a
     * subdivision consuming its mother — so this is the register's own answer rather
     * than lineage re-derived per screen, and it stays correct for workflows added later.
     *
     * The FIRST archive row per file is kept: a later one describes what happened to its
     * successor, not to it. A successor may be a CSV (a merger names every source), so
     * the count travels with it and the listing shows the first plus "+N more".
     *
     * @param  iterable $fileRows  rows carrying mlsfNo / kangisFileNo / NewKANGISFileNo
     * @return array<string, array{file_no:string, successor:?string, count:int, reason:?string, date:?string}>
     */
    private function supersededFileNumbersForRows($fileRows): array
    {
        $numbers = collect($fileRows)
            ->flatMap(fn ($r) => [$r->mlsfNo ?? null, $r->kangisFileNo ?? null, $r->NewKANGISFileNo ?? null])
            ->map(fn ($v) => strtoupper(trim((string) $v)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($numbers)) {
            return [];
        }

        $out = [];

        try {
            foreach (array_chunk($numbers, 500) as $chunk) {
                $rows = DB::connection('sqlsrv')->table('decommissioned_files')
                    ->whereIn('file_no', $chunk)
                    ->orderBy('id')
                    ->get(['file_no', 'successor_file_no', 'decommissioning_reason', 'decommissioning_date']);

                foreach ($rows as $row) {
                    $key = strtoupper(trim((string) $row->file_no));

                    if (isset($out[$key])) {
                        continue;
                    }

                    $successors = array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) ($row->successor_file_no ?? ''))
                    )));

                    $out[$key] = [
                        'file_no'   => $key,
                        'successor' => $successors[0] ?? null,
                        'count'     => count($successors),
                        'reason'    => $row->decommissioning_reason,
                        'date'      => $row->decommissioning_date,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // A listing must not fall over because the archive could not be read.
            Log::warning('Could not read decommissioned files for the listing', ['error' => $e->getMessage()]);

            return [];
        }

        return $out;
    }

    private function relatedFileNumbersForRows(array $fileRows): array
    {
        if (empty($fileRows)) {
            return [];
        }

        $norm = fn ($v) => strtoupper(trim((string) $v));

        // Every number each row may be registered under, so a file commissioned as MLS
        // but registered under its KANGIS number is still found.
        $numbersByRow = [];
        $lookup = [];
        foreach ($fileRows as $id => $row) {
            $numbers = [];
            foreach ([$row->mlsfNo ?? null, $row->kangisFileNo ?? null, $row->NewKANGISFileNo ?? null, $row->st_file_no ?? null] as $candidate) {
                $clean = trim((string) $candidate);
                if ($clean !== '' && $clean !== 'N/A') {
                    $numbers[$norm($clean)] = $clean;
                }
            }
            $numbersByRow[$id] = $numbers;
            foreach ($numbers as $key => $clean) {
                $lookup[$key] = $clean;
            }
        }

        // Register rows for the whole page in one query. A page is capped at the
        // DataTables length (20), so the IN list stays far inside SQL Server's
        // 2,100-parameter ceiling.
        $registered = [];
        if (!empty($lookup)) {
            try {
                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('related_file_number')) {
                    $rows = DB::connection('sqlsrv')
                        ->table('related_file_number')
                        ->whereIn('file_number', array_values($lookup))
                        ->orderBy('id')
                        ->get(['file_number', 'related_fileno', 'transaction_type']);

                    foreach ($rows as $r) {
                        $related = trim((string) ($r->related_fileno ?? ''));
                        if ($related === '') {
                            continue;
                        }
                        $registered[$norm($r->file_number)][] = [
                            'file_no' => $related,
                            'type' => trim((string) ($r->transaction_type ?? '')) ?: 'Related File',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // The register is an enrichment: a failure here must not take the
                // listing down, it just falls back to the columns below.
                Log::warning('Could not read the related file number register for the file list', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result = [];
        foreach ($fileRows as $id => $row) {
            $entries = [];
            $seen = [];
            $ownNumbers = $numbersByRow[$id] ?? [];

            $add = function (?string $fileNo, string $type) use (&$entries, &$seen, $ownNumbers, $norm) {
                $clean = trim((string) $fileNo);
                if ($clean === '' || $clean === 'N/A' || $clean === '[]') {
                    return;
                }
                $key = $norm($clean);
                // A file is not related to itself.
                if (isset($ownNumbers[$key]) || isset($seen[$key])) {
                    return;
                }
                $seen[$key] = true;
                $entries[] = ['file_no' => $clean, 'type' => $type];
            };

            // Old number first: formatRelatedOrOldFileNo() has always let it win over
            // the related list, and the cell badge takes its headline from position 1,
            // so leading with it keeps a re-issued file reading as OLD rather than REL.
            $add($row->derived_old_fileno ?? null, 'Old File Number');

            foreach ($ownNumbers as $key => $clean) {
                foreach ($registered[$key] ?? [] as $entry) {
                    $add($entry['file_no'], $entry['type']);
                }
            }

            // fileNumber.related_fileno: a JSON array on newer rows, plain
            // comma-separated text on the ones that predate it.
            $raw = trim((string) ($row->derived_related_fileno ?? ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $parts = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : explode(',', $raw);
                foreach ($parts as $part) {
                    $add(is_scalar($part) ? (string) $part : null, 'Related File');
                }
            }

            $result[$id] = $entries;
        }

        return $this->attachRelatedFileAliases($this->attachRelatedFileTitles($result));
    }

    /**
     * Attach each related number's KANGIS alias, so the cell can name a file by both
     * of its numbers — the same pair the commissioning sheet prints as
     * "COM-2016-327 (MLKN 1516)".
     *
     * A KANGIS number is a second name for a land file, not a second file, so it
     * rides on the entry rather than becoming one of its own: the related count, and
     * the "View all N related" card it drives, keep reporting files.
     *
     * @param  array<int,array<int,array{file_no:string,type:string,title?:string}>> $result
     * @return array<int,array<int,array{file_no:string,type:string,title?:string,alias:?string}>>
     */
    private function attachRelatedFileAliases(array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        // One instance for the whole page: it memoises each pairing, and a page asks
        // for the same numbers over and over (every file of a batch quotes one mother).
        $pairs = app(KangisLandPairService::class);

        // Resolve the page in one pass. Asked one at a time this costs a LIKE scan of
        // file_indexings per number (~0.15s), which a 20-row listing cannot afford.
        $numbers = [];
        foreach ($result as $entries) {
            foreach ($entries as $entry) {
                $numbers[] = $entry['file_no'] ?? null;
            }
        }
        $pairs->warmPairs($numbers);

        foreach ($result as $id => $entries) {
            foreach ($entries as $i => $entry) {
                $result[$id][$i]['alias'] = $pairs->aliasFor($entry['file_no'] ?? null);
            }
        }

        return $result;
    }

    /**
     * Fill in each related file's OWN title, in one pass for the whole page.
     *
     * `related_file_number.file_title` is the title of the file that OWNS the link,
     * not of the file it points at (see RelatedFileNumberRegistrar::sync()), and the
     * per-entry title typed on the commissioning form never reaches that table. So the
     * name shown beside a related number has to be resolved from the file itself.
     *
     * @param  array<int,array<int,array{file_no:string,type:string}>> $result
     * @return array<int,array<int,array{file_no:string,type:string,title:string}>>
     */
    private function attachRelatedFileTitles(array $result): array
    {
        $norm = fn ($v) => strtoupper(trim((string) $v));

        $numbers = [];
        foreach ($result as $entries) {
            foreach ($entries as $entry) {
                $numbers[$norm($entry['file_no'])] = trim((string) $entry['file_no']);
            }
        }

        if (empty($numbers)) {
            return $result;
        }

        $titles = [];

        // Indexed files first — file_indexings is where a title is actually curated.
        // Chunked: a page can carry hundreds of related numbers (one merger quotes 30),
        // and SQL Server caps a statement at 2,100 parameters.
        try {
            foreach (array_chunk(array_values($numbers), 500) as $chunk) {
                $rows = DB::connection('sqlsrv')
                    ->table('file_indexings')
                    ->whereIn('file_number', $chunk)
                    ->orderBy('id')
                    ->get(['file_number', 'file_title']);

                foreach ($rows as $row) {
                    $title = trim((string) ($row->file_title ?? ''));
                    if ($title !== '') {
                        $titles[$norm($row->file_number)] = $title;
                    }
                }
            }

            // Then the commissioning register, for a file issued but not yet indexed.
            $missing = array_values(array_filter(
                $numbers,
                fn ($value, $key) => !isset($titles[$key]),
                ARRAY_FILTER_USE_BOTH
            ));

            foreach (array_chunk($missing, 500) as $chunk) {
                $rows = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->whereIn('full_file_number', $chunk)
                    ->orderBy('id')
                    ->get(['full_file_number', 'file_name']);

                foreach ($rows as $row) {
                    $title = trim((string) ($row->file_name ?? ''));
                    if ($title !== '') {
                        $titles[$norm($row->full_file_number)] = $title;
                    }
                }
            }
        } catch (\Throwable $e) {
            // A title is a courtesy on the card; the numbers are the fact. Never let a
            // lookup failure take the listing down.
            Log::warning('Could not resolve related file titles for the file list', [
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($result as $id => $entries) {
            foreach ($entries as $i => $entry) {
                $result[$id][$i]['title'] = $titles[$norm($entry['file_no'])] ?? '';
            }
        }

        return $result;
    }

    /**
     * Render the file's related OR old file number for the list.
     *
     * These are the two halves of a single field on the Edit modal: an "Old File Number"
     * tick sends the value to mls_file_no.old_fileno, an untick to fileNumber.related_fileno
     * as a JSON array. Only one is ever populated, so the column shows whichever it is and
     * labels it, rather than pretending they are the same thing.
     *
     * @return array{value: string, kind: string}
     */
    private function formatRelatedOrOldFileNo($relatedJson, $oldFileNo): array
    {
        $old = trim((string) ($oldFileNo ?? ''));
        if ($old !== '') {
            return ['value' => $old, 'kind' => 'old'];
        }

        $raw = trim((string) ($relatedJson ?? ''));
        if ($raw === '') {
            return ['value' => 'N/A', 'kind' => 'none'];
        }

        // Written as a JSON array, but rows predating that are plain comma-separated text.
        $decoded = json_decode($raw, true);
        $parts = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? $decoded
            : explode(',', $raw);

        $parts = array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $parts),
            fn ($v) => $v !== ''
        ));

        return empty($parts)
            ? ['value' => 'N/A', 'kind' => 'none']
            : ['value' => implode(', ', $parts), 'kind' => 'related'];
    }

    /**
     * Form field -> the column holding it on a `fileNumber` row, for divergence checks.
     */
    private const BATCH_COLUMN_MAP = [
        'file_name' => 'FileName',
        'plot_no'   => 'plot_no',
        'tp_no'     => 'tp_no',
        'district'  => 'district',
        'lga'       => 'lga',
        'location'  => 'location',
    ];

    /**
     * The watched fields this request is actually writing, for the batch divergence check.
     *
     * @return array<string, mixed>
     */
    private function submittedWatchedFields(Request $request): array
    {
        $submitted = [];

        foreach (array_keys(\App\Support\BatchDivergence::WATCHED) as $field) {
            if ($request->has($field)) {
                $submitted[$field] = $request->input($field);
            }
        }

        return $submitted;
    }

    /**
     * Does any of these files already carry a recorded transaction?
     *
     * @param  \Illuminate\Support\Collection<int, object>  $targets
     */
    private function targetsHaveTransactions($targets): bool
    {
        $fileNumbers = $targets
            ->flatMap(fn ($t) => [$t->mlsfNo ?? null, $t->kangisFileNo ?? null, $t->NewKANGISFileNo ?? null])
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->unique()
            ->values();

        if ($fileNumbers->isEmpty()) {
            return false;
        }

        try {
            return DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $fileNumbers->all())
                ->where('has_transaction', 1)
                ->exists();
        } catch (\Exception $e) {
            Log::warning('Could not check has_transaction before a file-number edit', [
                'error' => $e->getMessage(),
            ]);

            // Unable to tell — do not block the edit on an infrastructure failure.
            return false;
        }
    }

    /**
     * Write one file's edit across fileNumber, mls_file_no, file_indexings and the
     * customer / entity mirrors.
     *
     * Extracted so a batch save is a loop over the same code a single save runs, rather
     * than a second implementation that can drift away from it.
     *
     * @param  bool  $isPrimary  true for the file whose row was actually clicked. The
     *                           old/related file number and the passport belong to that
     *                           one file, never to its batch siblings.
     */
    private function applyFileNumberEdit(Request $request, $record, bool $isPrimary): void
    {
        $id = $record->id;

        // Prepare update data
        $updateData = [
            'FileName' => $request->file_name,
            'updated_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
            'updated_at' => now()
        ];

            // Add fields if provided
            if ($request->has('kangis_file_no'))
                $updateData['kangisFileNo'] = $request->kangis_file_no;
            if ($request->has('new_kangis_file_no'))
                $updateData['NewKANGISFileNo'] = $request->new_kangis_file_no;
            if ($request->has('lga'))
                $updateData['lga'] = $request->lga;
            if ($request->has('district'))
                $updateData['district'] = $request->district;
            if ($request->has('plot_no'))
                $updateData['plot_no'] = $request->plot_no;
            if ($request->has('tp_no'))
                $updateData['tp_no'] = $request->tp_no;
            if ($request->has('location'))
                $updateData['location'] = $request->location;

            // purpose_id and customer_type are usually in mls_file_no, but check if they're in fileNumber too
            if ($request->has('purpose_id') && \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'purpose_id')) {
                $updateData['purpose_id'] = $request->purpose_id;
            }
            if ($request->has('customer_type') && \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'customer_type')) {
                $updateData['customer_type'] = $request->customer_type;
            }

            // Handle phone_no dynamically
            if ($request->has('phone_no')) {
                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'phone_no')) {
                    $updateData['phone_no'] = $request->phone_no;
                } elseif (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'PhoneNo')) {
                    $updateData['PhoneNo'] = $request->phone_no;
                }
            }

            // Other new fields
            if ($request->has('address')) {
                $updateData['address'] = $request->address;
            }
            if ($request->has('rep_phone_no')) {
                $updateData['rep_phone_no'] = $request->rep_phone_no;
            }
            if ($request->has('rep_address')) {
                $updateData['rep_address'] = $request->rep_address;
            }

            // The edit modal ships a single file-number field plus an "Old File Number"
            // checkbox that decides which column it lands in: related_fileno (a JSON
            // array on fileNumber) or old_fileno (a plain string on mls_file_no).
            // Only one is kept, so ticking/unticking the box clears the other.
            //
            // This is per-file identity, not a property of the allocation: stamping one
            // file's predecessor onto all seven of its batch siblings would invent
            // history. It is therefore written only for the file that was opened.
            $isOldFileNo = false;
            $relatedFileNoInput = '';
            if ($isPrimary && $request->has('related_fileno')) {
                $isOldFileNo = filter_var($request->input('is_old_fileno'), FILTER_VALIDATE_BOOLEAN);
                $relatedFileNoInput = trim((string) $request->input('related_fileno'));
                $relatedFileNumbers = array_values(array_filter(
                    array_map('trim', preg_split('/\s*,\s*/', $relatedFileNoInput)),
                    fn ($v) => $v !== ''
                ));

                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                    $updateData['related_fileno'] = (!$isOldFileNo && !empty($relatedFileNumbers))
                        ? json_encode($relatedFileNumbers)
                        : null;
                }
            }

            // Update the record in fileNumber
            DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where('id', $id)
                ->update($updateData);

            // Also sync to mls_file_no if mlsfNo is present
            if ($record->mlsfNo) {
                $mlsUpdateData = [];
                if (isset($updateData['FileName']))
                    $mlsUpdateData['file_name'] = $updateData['FileName'];
                if (isset($updateData['lga']))
                    $mlsUpdateData['lga'] = $updateData['lga'];
                if (isset($updateData['district']))
                    $mlsUpdateData['district'] = $updateData['district'];
                if (isset($updateData['plot_no']))
                    $mlsUpdateData['plot_no'] = $updateData['plot_no'];
                if (isset($updateData['tp_no']))
                    $mlsUpdateData['tp_no'] = $updateData['tp_no'];
                if (isset($updateData['location']))
                    $mlsUpdateData['location'] = $updateData['location'];

                if ($request->has('purpose_id'))
                    $mlsUpdateData['purpose_id'] = $request->purpose_id;

                if ($request->has('customer_type'))
                    $mlsUpdateData['customer_type'] = $request->customer_type;

                if ($request->has('phone_no')) {
                    if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'phone_no')) {
                        $mlsUpdateData['phone_no'] = $request->phone_no;
                    } elseif (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'PhoneNo')) {
                        $mlsUpdateData['PhoneNo'] = $request->phone_no;
                    }
                }

                if ($request->has('address')) {
                    $mlsUpdateData['address'] = $request->address;
                }
                if ($request->has('rep_phone_no')) {
                    $mlsUpdateData['rep_phone_no'] = $request->rep_phone_no;
                }
                if ($request->has('rep_address')) {
                    $mlsUpdateData['rep_address'] = $request->rep_address;
                }

                if ($isPrimary && $request->has('related_fileno')
                    && \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
                    $mlsUpdateData['old_fileno'] = ($isOldFileNo && $relatedFileNoInput !== '')
                        ? $relatedFileNoInput
                        : null;
                }

                if (!empty($mlsUpdateData)) {
                    // Matched the way the delete cascade matches: on the file number OR
                    // the tracking id. A row reachable only by tracking_id was previously
                    // updated by neither path.
                    $trackingId = trim((string) ($record->tracking_id ?? ''));

                    DB::connection('sqlsrv')
                        ->table('mls_file_no')
                        ->where(function ($q) use ($record, $trackingId) {
                            $q->where('full_file_number', $record->mlsfNo);
                            if ($trackingId !== '') {
                                $q->orWhere('tracking_id', $trackingId);
                            }
                        })
                        ->update($mlsUpdateData);
                }

                // The old number is not just a column on mls_file_no: route it through
                // the ledger so the history survives an edit, and so the mirror lands on
                // file_indexings.old_fileno as well. Unticking the box clears the mirrors
                // but keeps the ledger -- see OldFileNumberService::clear().
                if ($isPrimary && $request->has('related_fileno')) {
                    $oldFileNumberService = app(\App\Services\OldFileNumberService::class);

                    if ($isOldFileNo && $relatedFileNoInput !== '') {
                        $oldFileNumberService->record(
                            $record->mlsfNo,
                            $relatedFileNoInput,
                            \App\Models\OldFileNumber::SOURCE_EDIT,
                            $updateData['FileName'] ?? null,
                            Auth::id()
                        );
                    } else {
                        $oldFileNumberService->clear($record->mlsfNo);
                    }
                }
            }

        // Keep the matching file_indexings row in sync — it's edited from a different
        // screen but must show the same title and property details as fileNumber.
        $fileNoCandidates = array_values(array_unique(array_filter([
            $record->mlsfNo ?? null,
            $record->kangisFileNo ?? null,
            $record->NewKANGISFileNo ?? null,
            $updateData['kangisFileNo'] ?? null,
            $updateData['NewKANGISFileNo'] ?? null,
        ])));

        // Whether THIS file's name is changing. Computed per file, not once for the
        // batch: a batch can hold files under different names.
        $nameChanged = trim((string) ($record->FileName ?? '')) !== trim((string) $request->file_name);

        if (!empty($fileNoCandidates)) {
            try {
                $fiUpdate = [
                    'file_title' => $updateData['FileName'],
                    'updated_by' => $updateData['updated_by'],
                    'updated_at' => now(),
                ];

                // current_holder is NOT a copy of the file title — a Deed of Assignment or
                // Transfer of Title moves it to the new owner while FileName keeps naming
                // the original allottee. Rewriting it on every save meant an edit to the
                // plot number quietly reinstated the previous owner, so it moves only when
                // the name itself is being changed.
                if ($nameChanged) {
                    $fiUpdate['current_holder'] = $updateData['FileName'];
                }

                if ($request->has('location')) $fiUpdate['location'] = $request->location;
                if ($request->has('lga'))      $fiUpdate['lga'] = $request->lga;
                if ($request->has('district')) $fiUpdate['district'] = $request->district;
                if ($request->has('plot_no'))  $fiUpdate['plot_number'] = $request->plot_no;
                if ($request->has('tp_no'))    $fiUpdate['tp_no'] = $request->tp_no;

                // These columns exist on file_indexings but were never written from here,
                // so a corrected phone or address stayed stale on the indexing screen.
                if ($request->has('phone_no')) $fiUpdate['phone'] = $request->phone_no;
                if ($request->has('address'))  $fiUpdate['residence_address'] = $request->address;

                DB::connection('sqlsrv')->table('file_indexings')
                    ->whereIn('file_number', $fileNoCandidates)
                    ->update($fiUpdate);
            } catch (\Exception $e) {
                Log::warning('Failed to propagate FileName to file_indexings', [
                    'fileNumber_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            // The customer and entity mirrors. This screen has always claimed five tables
            // — the delete dialog lists them — but the edit only ever wrote three, so a
            // renamed file kept its old name on the Customer and Entity records forever.
            $this->propagateToStaging($request, $fileNoCandidates, $nameChanged);

            // Commissioning also publishes the file to the OSS applications list
            // (MlsCommissioningOssApplicationService), which is what
            // /lands-one-stop-shop/applications?type=no-change-of-name reads. Without this
            // an edit stopped at the file-number screens and that page kept showing the
            // values captured at commissioning time.
            $this->propagateToOssApplications($request, $fileNoCandidates, $nameChanged);
        }
    }

    /**
     * Mirror an edit onto the OSS applications row this file was published as.
     *
     * The column mapping deliberately matches what commissioning wrote in the first place
     * (MlsCommissioningOssApplicationService::payload) — note `plan_no` is fed from the TP
     * number, so a TP correction has to travel that way or it never reaches the page.
     *
     * Applicant identity follows the same rule as everywhere else on this form: the name
     * moves only when it actually changed, never as a side effect of a plot-number fix.
     *
     * @param  array<int, string>  $fileNoCandidates
     */
    private function propagateToOssApplications(Request $request, array $fileNoCandidates, bool $nameChanged): void
    {
        try {
            $update = [];

            if ($nameChanged) {
                $newName = trim((string) $request->file_name);
                if ($newName !== '') {
                    $update['applicant_name'] = $newName;
                }
            }

            foreach ([
                'plot_no' => 'plot_no',
                'tp_no' => 'plan_no',
                'location' => 'location',
                'district' => 'district',
                'lga' => 'lga',
                'address' => 'address',
                'phone_no' => 'phone',
            ] as $field => $column) {
                if ($request->has($field)) {
                    $update[$column] = $request->input($field);
                }
            }

            if (empty($update)) {
                return;
            }

            $update['updated_at'] = now();

            DB::connection('sqlsrv')->table('oss_applications')
                ->whereIn('file_no', $fileNoCandidates)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->update($update);
        } catch (\Throwable $e) {
            // The file-number registers are already saved and are authoritative; a missing
            // column on the OSS row must not fail the edit.
            Log::warning('Failed to propagate file-number edit to oss_applications', [
                'file_numbers' => $fileNoCandidates,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mirror an edit onto the customer and entity staging records.
     *
     * Deliberately narrow. The NAME is written only when it actually changed: these rows
     * are not copies of `fileNumber`, and rewriting an identity on every unrelated save
     * (a plot number correction, say) is how the file_indexings holder bug happened.
     * Contact details follow the same rule as everywhere else on this form — written when
     * the field was submitted.
     *
     * Each table is wrapped separately: a missing column on one mirror must never abort a
     * save that has already succeeded on the file-number registers.
     *
     * @param  array<int, string>  $fileNoCandidates
     */
    private function propagateToStaging(Request $request, array $fileNoCandidates, bool $nameChanged): void
    {
        $newName = trim((string) $request->file_name);

        try {
            $customerUpdate = [];

            if ($nameChanged && $newName !== '') {
                $customerUpdate['customer_name'] = $newName;
            }
            if ($request->has('customer_type')) $customerUpdate['customer_type'] = $request->customer_type;
            if ($request->has('phone_no'))      $customerUpdate['phone'] = $request->phone_no;
            if ($request->has('address'))       $customerUpdate['property_address'] = $request->address;

            if (!empty($customerUpdate)) {
                $customerUpdate['updated_at'] = now();

                DB::connection('sqlsrv')->table('customers_staging')
                    ->whereIn('file_number', $fileNoCandidates)
                    ->update($customerUpdate);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to propagate file-number edit to customers_staging', [
                'file_numbers' => $fileNoCandidates,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $entityUpdate = [];

            if ($nameChanged && $newName !== '') {
                $entityUpdate['entity_name'] = $newName;
            }
            if ($request->has('customer_type')) $entityUpdate['entity_type'] = $request->customer_type;

            if (!empty($entityUpdate)) {
                $entityUpdate['updated_at'] = now();

                DB::connection('sqlsrv')->table('entities_staging')
                    ->whereIn('file_number', $fileNoCandidates)
                    ->update($entityUpdate);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to propagate file-number edit to entities_staging', [
                'file_numbers' => $fileNoCandidates,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save an edit to a temporary file.
     *
     * Temporary files are commissioned into `mls_file_no` and never get a `fileNumber`
     * row, so the list surfaces them from that table directly — which means the row's id
     * is an `mls_file_no` id, freely colliding with `fileNumber` ids. Before this existed,
     * editing one loaded and then overwrote a completely unrelated file.
     */
    private function updateTemporaryFile(Request $request, $id)
    {
        $db = DB::connection('sqlsrv');

        $temp = $db->table('mls_file_no')
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$temp) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found'
            ], 404);
        }

        $update = [
            'file_name'  => $request->file_name,
            'updated_at' => now(),
        ];

        foreach ([
            'lga' => 'lga', 'district' => 'district', 'plot_no' => 'plot_no',
            'tp_no' => 'tp_no', 'location' => 'location', 'purpose_id' => 'purpose_id',
            'customer_type' => 'customer_type', 'phone_no' => 'phone_no',
            'address' => 'address', 'rep_phone_no' => 'rep_phone_no',
            'rep_address' => 'rep_address',
        ] as $field => $column) {
            if ($request->has($field)) {
                $update[$column] = $request->input($field);
            }
        }

        $db->table('mls_file_no')->where('id', $id)->update($update);

        $nameChanged = trim((string) ($temp->file_name ?? '')) !== trim((string) $request->file_name);
        $fileNoCandidates = array_values(array_filter([$temp->full_file_number ?? null]));

        if (!empty($fileNoCandidates)) {
            try {
                $fiUpdate = [
                    'file_title' => $request->file_name,
                    'updated_by' => Auth::user()->name ?? Auth::user()->email ?? 'System',
                    'updated_at' => now(),
                ];
                if ($nameChanged)              $fiUpdate['current_holder'] = $request->file_name;
                if ($request->has('location')) $fiUpdate['location'] = $request->location;
                if ($request->has('lga'))      $fiUpdate['lga'] = $request->lga;
                if ($request->has('district')) $fiUpdate['district'] = $request->district;
                if ($request->has('plot_no'))  $fiUpdate['plot_number'] = $request->plot_no;
                if ($request->has('tp_no'))    $fiUpdate['tp_no'] = $request->tp_no;

                $db->table('file_indexings')
                    ->whereIn('file_number', $fileNoCandidates)
                    ->update($fiUpdate);
            } catch (\Exception $e) {
                Log::warning('Failed to propagate temporary file edit to file_indexings', [
                    'mls_file_no_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->propagateToStaging($request, $fileNoCandidates, $nameChanged);
            $this->propagateToOssApplications($request, $fileNoCandidates, $nameChanged);
        }

        $passportUpload = $this->storePassportIfSent($request, $temp->full_file_number ?? null);
        $passportRemoved = !$passportUpload && $this->removePassportIfRequested($request, $temp->full_file_number ?? null);

        $this->forgetFileNumberCaches();

        return response()->json([
            'success' => true,
            'message' => 'Temporary file updated successfully',
            'updated_count' => 1,
            'passport_url' => $passportUpload['url'] ?? null,
            'passport_removed' => $passportRemoved,
        ]);
    }

    /**
     * File a passport photograph submitted with an edit, if one was submitted.
     *
     * Returns null when the form carried no image — the overwhelmingly common case, since
     * most edits change a name or a plot number and must leave the existing photo alone.
     *
     * @return array{stored:bool, path:?string, url:?string, scanning_id:?int, reason:string}|null
     */
    private function storePassportIfSent(Request $request, ?string $fileNumber): ?array
    {
        if (!$request->hasFile('passport') || trim((string) $fileNumber) === '') {
            return null;
        }

        return app(\App\Services\FilePassportService::class)
            ->store($request->file('passport'), (string) $fileNumber);
    }

    /** Remove a passport only when the Edit modal explicitly asks for it. */
    private function removePassportIfRequested(Request $request, ?string $fileNumber): bool
    {
        if (!$request->boolean('remove_passport') || trim((string) $fileNumber) === '') {
            return false;
        }

        return app(\App\Services\FilePassportService::class)->remove((string) $fileNumber);
    }

    /**
     * Remove the specified file number record and cascade delete from related tables.
     */
    public function destroy($id, Request $request)
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.'
            ], 403);
        }

        // The list is a UNION of three tables with colliding ids. A row that is not a
        // fileNumber row must never be resolved against fileNumber — that is how a Master
        // Delete on temporary RES-1993-2644(T) (mls_file_no id 1166) purged the unrelated
        // live file CON-AG-1987-57 (fileNumber id 1166) from five tables.
        $refusal = $this->refuseNonFileNumberDelete($request->input('entity'));
        if ($refusal) {
            return $refusal;
        }

        $fileRecord = DB::connection('sqlsrv')->table('fileNumber')->where('id', $id)->first();
        if (!$fileRecord) {
            return response()->json([
                'success' => false,
                'message' => 'File number record not found.'
            ], 404);
        }

        // A batch row stands for N files but carries one id. Deleting only that id left
        // the row on screen (redrawn from the next member), reading as a failed delete.
        $records = collect([$fileRecord]);
        $batchNo = '';

        if ($request->boolean('apply_to_batch')) {
            $batchService = app(\App\Services\FileNumber\BatchExpansionService::class);
            $resolved = $batchService->resolveFor($fileRecord, $request->input('batch_no'));

            if (!$resolved['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $resolved['message'],
                ], 422);
            }

            $records = $resolved['members'];
            $batchNo = $batchService->batchNoFor($fileRecord);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $totals = array_fill_keys(
                ['fileNumber', 'mls_file_no', 'entities_staging', 'customers_staging', 'file_indexings', 'old_file_numbers', 'oss_applications', 'file_tracking'],
                0
            );

            foreach ($records as $record) {
                $result = $this->cascadeDeleteFileRecord($record->id, $record);
                foreach ($totals as $k => $_) {
                    $totals[$k] += $result['counts'][$k] ?? 0;
                }
                $this->writeMasterDeleteAudit($record->id, $result, $records->count() > 1);
            }

            $this->forgetFileNumberCaches();

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'deleted_count' => $records->count(),
                'batch_no' => $batchNo ?: null,
                'message' => $records->count() > 1
                    ? "Batch {$batchNo} deleted — {$records->count()} files purged from all 8 systems."
                    : 'MLS record and all associated staging/indexing records deleted successfully from all 8 systems.',
                'details' => [
                    'fileNumber_deleted' => $totals['fileNumber'],
                    'mls_file_no_deleted' => $totals['mls_file_no'],
                    'entities_staging_deleted' => $totals['entities_staging'],
                    'customers_staging_deleted' => $totals['customers_staging'],
                    'file_indexings_deleted' => $totals['file_indexings'],
                    'old_file_numbers_deleted' => $totals['old_file_numbers'],
                    'oss_applications_deleted' => $totals['oss_applications'],
                    'file_tracking_deleted' => $totals['file_tracking'],
                ]
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            \Log::error('Error executing master delete for MLS file ID ' . $id . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refuse a Master Delete aimed at a row that is not backed by `fileNumber`.
     *
     * Temporary files and Plot Extensions appear on this list but live in their own
     * tables, with their own ids and their own lifecycles. A five-table cascade keyed on
     * `fileNumber.id` is not merely unsupported for them — it resolves to a DIFFERENT
     * file. Refusing is the whole point: the client also hides these buttons, but a stale
     * page must not be able to talk the server into it.
     */
    private function refuseNonFileNumberDelete($entity)
    {
        $entity = \App\Support\MlsRowTarget::entity($entity);

        if ($entity === \App\Support\MlsRowTarget::FILE_NUMBER) {
            return null;
        }

        $label = \App\Support\MlsRowTarget::label($entity);

        return response()->json([
            'success' => false,
            'message' => "{$label} records cannot be master-deleted from this screen — they are not held in the file number register.",
        ], 422);
    }

    /**
     * Bulk master delete for multiple MLS file records.
     * All deletes execute inside ONE transaction — any failure rolls back every record.
     */
    public function bulkDestroy(Request $request)
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            // Selection keys are "F:123" / "T:1166" / "P:4"; a bare "123" from a page
            // cached before this shipped still resolves as a fileNumber row.
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'required',
            'apply_to_batch' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request: ' . $validator->errors()->first(),
            ], 422);
        }

        $parsed = \App\Support\MlsRowTarget::parseKeys($request->input('ids'));

        // Temporary files and Plot Extensions are not held in `fileNumber`; their ids
        // collide with it. Skip them loudly rather than resolving them against the wrong
        // table — three of the four live temporary rows sit on top of real files.
        $skipped = [];
        $ids = [];

        foreach ($parsed['targets'] as $target) {
            if ($target['entity'] !== \App\Support\MlsRowTarget::FILE_NUMBER) {
                $skipped[] = [
                    'id' => $target['id'],
                    'reason' => \App\Support\MlsRowTarget::label($target['entity'])
                        . ' records cannot be master-deleted from this screen.',
                ];
                continue;
            }

            $ids[] = $target['id'];
        }

        foreach ($parsed['rejected'] as $bad) {
            $skipped[] = ['id' => $bad, 'reason' => 'Unrecognised selection.'];
        }

        $ids = array_values(array_unique($ids));

        $records = empty($ids)
            ? collect()
            : DB::connection('sqlsrv')->table('fileNumber')->whereIn('id', $ids)->get()->keyBy('id');
        $missingIds = array_values(array_diff($ids, $records->keys()->all()));

        // Expand batch rows. Each selected row may stand for many files, so the real
        // blast radius — and the 200 cap — has to be measured in FILES, not in rows.
        if ($request->boolean('apply_to_batch') && $records->isNotEmpty()) {
            $batchService = app(\App\Services\FileNumber\BatchExpansionService::class);
            $expanded = collect();

            foreach ($records as $record) {
                $resolved = $batchService->resolveFor($record, null);
                $members = $resolved['ok'] ? $resolved['members'] : collect([$record]);

                foreach ($members as $member) {
                    $expanded->put($member->id, $member);
                }
            }

            $records = $expanded;
        }

        if ($records->count() > 200) {
            return response()->json([
                'success' => false,
                'message' => 'That selection expands to ' . $records->count()
                    . ' files. Please delete 200 or fewer files per batch.',
            ], 422);
        }

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching file records found for the provided IDs.',
                'missing_ids' => $missingIds,
                'skipped' => $skipped,
            ], 404);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $totals = [
                'fileNumber' => 0, 'mls_file_no' => 0, 'entities_staging' => 0,
                'customers_staging' => 0, 'file_indexings' => 0, 'old_file_numbers' => 0,
                'oss_applications' => 0, 'file_tracking' => 0,
            ];
            $perRecord = [];

            foreach ($records as $id => $fileRecord) {
                $result = $this->cascadeDeleteFileRecord($id, $fileRecord);
                foreach ($totals as $k => $_) {
                    $totals[$k] += $result['counts'][$k] ?? 0;
                }
                $this->writeMasterDeleteAudit($id, $result, true);
                $perRecord[] = [
                    'id' => $id,
                    'mlsfNo' => $result['snapshot']['mlsfNo'],
                ];
            }

            $this->forgetFileNumberCaches();
            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => count($records) . ' file(s) deleted successfully across all 8 systems.',
                'deleted_count' => count($records),
                'missing_ids' => $missingIds,
                'skipped' => $skipped,
                'records' => $perRecord,
                'totals' => $totals,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            \Log::error('Error executing bulk master delete for MLS file IDs: ' . $e->getMessage(), [
                'ids' => $ids,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute the 5-table cascade delete for a single fileNumber row.
     * Caller is responsible for transaction management.
     */
    private function cascadeDeleteFileRecord($id, $fileRecord): array
    {
        $mlsfNo          = trim($fileRecord->mlsfNo ?? '');
        $trackingId      = trim($fileRecord->tracking_id ?? '');
        $kangisFileNo    = trim($fileRecord->kangisFileNo ?? '');
        $newKangisFileNo = trim($fileRecord->NewKANGISFileNo ?? '');
        $stFileNo        = trim($fileRecord->st_file_no ?? '');

        // 1. Delete from mls_file_no
        $deletedMlsFileNo = 0;
        if ($mlsfNo !== '') {
            $deletedMlsFileNo += DB::connection('sqlsrv')->table('mls_file_no')
                ->where('full_file_number', $mlsfNo)->delete();
        }
        if ($trackingId !== '') {
            $deletedMlsFileNo += DB::connection('sqlsrv')->table('mls_file_no')
                ->where('tracking_id', $trackingId)->delete();
        }

        $fileNumbersForStaging = array_unique(array_filter([$mlsfNo, $kangisFileNo, $newKangisFileNo, $stFileNo]));

        // 2. entities_staging
        $deletedEntities = 0;
        if (!empty($fileNumbersForStaging)) {
            $deletedEntities = DB::connection('sqlsrv')->table('entities_staging')
                ->whereIn('file_number', $fileNumbersForStaging)->delete();
        }

        // 3. customers_staging
        $deletedCustomers = 0;
        if (!empty($fileNumbersForStaging)) {
            $deletedCustomers = DB::connection('sqlsrv')->table('customers_staging')
                ->whereIn('file_number', $fileNumbersForStaging)->delete();
        }

        // 4. file_indexings (+ children)
        $deletedIndexings = 0;
        $fileIndexingIds = [];
        if (!empty($fileNumbersForStaging)) {
            $fileIndexingIds = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $fileNumbersForStaging)->pluck('id')->toArray();
        }
        if ($trackingId !== '') {
            $moreIds = DB::connection('sqlsrv')->table('file_indexings')
                ->where('tracking_id', $trackingId)->pluck('id')->toArray();
            $fileIndexingIds = array_unique(array_merge($fileIndexingIds, $moreIds));
        }
        if (!empty($fileIndexingIds)) {
            DB::connection('sqlsrv')->table('scannings')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            DB::connection('sqlsrv')->table('pagetypings')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            DB::connection('sqlsrv')->table('print_label_batch_items')
                ->whereIn('file_indexing_id', $fileIndexingIds)->delete();
            $deletedIndexings = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('id', $fileIndexingIds)->delete();
        }

        // 5. old_file_numbers — the ledger this screen's own Edit modal writes through
        // OldFileNumberService. Left behind, it kept naming a file number that no longer
        // exists and resurfaced in searches keyed on it.
        $deletedOldFileNumbers = 0;
        if (!empty($fileNumbersForStaging)) {
            try {
                $deletedOldFileNumbers = DB::connection('sqlsrv')->table('old_file_numbers')
                    ->whereIn('file_number', $fileNumbersForStaging)->delete();
            } catch (\Exception $e) {
                Log::warning('Could not purge old_file_numbers during master delete', [
                    'fileNumber_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 6. oss_applications — commissioning PUBLISHES every MLS file here
        // (MlsCommissioningOssApplicationService), and this table is what
        // /lands-one-stop-shop/applications?type=no-change-of-name lists. Left behind, a
        // master-deleted file kept appearing on that page with no record anywhere else:
        // IND-2026-272 was purged from all six original tables and still showed there.
        $deletedOssApplications = 0;
        if (!empty($fileNumbersForStaging)) {
            try {
                $deletedOssApplications = DB::connection('sqlsrv')->table('oss_applications')
                    ->whereIn('file_no', $fileNumbersForStaging)->delete();
            } catch (\Exception $e) {
                Log::warning('Could not purge oss_applications during master delete', [
                    'fileNumber_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 7. File tracking. `file_tracker` is keyed on the file number, and its rows survive
        // as ACTIVE requests for a file that no longer exists — the five IND-2026-25x
        // deletions each left one sitting in the SLTR department. Children are keyed on the
        // tracker id, so they are resolved first; there are no FK constraints, so the order
        // here is ours to choose rather than the database's.
        $deletedTracking = 0;
        if (!empty($fileNumbersForStaging)) {
            try {
                $trackerIds = DB::connection('sqlsrv')->table('file_tracker')
                    ->whereIn('file_number', $fileNumbersForStaging)->pluck('id')->all();

                if (!empty($trackerIds)) {
                    foreach (['kangis_checkout_approvals', 'file_tracker_department_backfill'] as $childTable) {
                        try {
                            DB::connection('sqlsrv')->table($childTable)
                                ->whereIn('file_tracker_id', $trackerIds)->delete();
                        } catch (\Exception $e) {
                            Log::warning("Could not purge {$childTable} during master delete", [
                                'fileNumber_id' => $id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // An indexing_duplicates row is a record about INDEXING, not about the
                    // tracking request, so the pointer is cleared rather than the row deleted.
                    try {
                        DB::connection('sqlsrv')->table('indexing_duplicates')
                            ->whereIn('file_tracker_id', $trackerIds)
                            ->update(['file_tracker_id' => null]);
                    } catch (\Exception $e) {
                        Log::warning('Could not clear indexing_duplicates.file_tracker_id during master delete', [
                            'fileNumber_id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $deletedTracking += DB::connection('sqlsrv')->table('file_tracker')
                        ->whereIn('id', $trackerIds)->delete();
                }

                // Both are keyed on the file number directly and carry no children.
                $deletedTracking += DB::connection('sqlsrv')->table('rds_tracking')
                    ->whereIn('file_number', $fileNumbersForStaging)->delete();
                $deletedTracking += DB::connection('sqlsrv')->table('digital_file_tracking_requests')
                    ->whereIn('file_no', $fileNumbersForStaging)->delete();
            } catch (\Exception $e) {
                Log::warning('Could not purge file tracking during master delete', [
                    'fileNumber_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 8. fileNumber
        $deletedFileNumbers = DB::connection('sqlsrv')->table('fileNumber')
            ->where('id', $id)->delete();
        if ($mlsfNo !== '') {
            $deletedFileNumbers += DB::connection('sqlsrv')->table('fileNumber')
                ->where('mlsfNo', $mlsfNo)->delete();
        }

        return [
            'snapshot' => [
                'id' => $id,
                'mlsfNo' => $mlsfNo,
                'tracking_id' => $trackingId,
                'kangisFileNo' => $kangisFileNo,
                'NewKANGISFileNo' => $newKangisFileNo,
                'st_file_no' => $stFileNo,
                'FileName' => $fileRecord->FileName ?? null,
            ],
            'counts' => [
                'fileNumber' => $deletedFileNumbers,
                'mls_file_no' => $deletedMlsFileNo,
                'entities_staging' => $deletedEntities,
                'customers_staging' => $deletedCustomers,
                'file_indexings' => $deletedIndexings,
                'old_file_numbers' => $deletedOldFileNumbers,
                'oss_applications' => $deletedOssApplications,
                'file_tracking' => $deletedTracking,
            ],
        ];
    }

    private function forgetFileNumberCaches(): void
    {
        Cache::forget('mls_fileno_page_stats_v2');
        Cache::forget('file_numbers_total_v4_New');
        Cache::forget('file_numbers_total_v4_All');
        Cache::forget('file_numbers_total_v4_Captured');
        // Page keys include this version, which lets us invalidate every cached
        // DataTable page without relying on cache-store-specific wildcard deletes.
        Cache::forever('file_numbers_datatable_version', (string) microtime(true));
    }

    private function writeMasterDeleteAudit($id, array $result, bool $isBulk = false): void
    {
        try {
            $c = $result['counts'];
            $prefix = $isBulk ? 'Bulk Master Delete' : 'Master Delete';
            app(\App\Services\AuditService::class)->logAction(
                'DELETED',
                'mls_file_record',
                $id,
                $result['snapshot'],
                null,
                "{$prefix} executed for MLS File Record ID {$id}. Affected tables: fileNumber ({$c['fileNumber']} rows), mls_file_no ({$c['mls_file_no']} rows), entities_staging ({$c['entities_staging']} rows), customers_staging ({$c['customers_staging']} rows), file_indexings ({$c['file_indexings']} rows), old_file_numbers ({$c['old_file_numbers']} rows), oss_applications ({$c['oss_applications']} rows), file tracking ({$c['file_tracking']} rows)."
            );
        } catch (\Exception $auditEx) {
            \Log::warning("AuditLog failed during MLS master delete: " . $auditEx->getMessage());
        }
    }

    /**
     * Get total count of file numbers
     */
    public function getCount()
    {
        try {
            $count = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            return response()->json(['count' => $count]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting count: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear file numbers cache
     */
    public function clearCache()
    {
        try {
            $this->forgetFileNumberCaches();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test database connection and table structure
     */
    public function testDatabase()
    {
        try {
            // Test connection
            $connectionTest = DB::connection('sqlsrv')->getPdo();

            // Test table existence
            $tableExists = DB::connection('sqlsrv')
                ->select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'fileNumber'");

            // Get table structure
            $columns = DB::connection('sqlsrv')
                ->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber'");

            // Get record count
            $recordCount = 0;
            $sampleRecords = [];

            if ($tableExists[0]->count > 0) {
                $recordCount = DB::connection('sqlsrv')->table('fileNumber')->count();
                $sampleRecords = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'connection' => 'Connected successfully',
                'table_exists' => $tableExists[0]->count > 0,
                'columns' => $columns,
                'record_count' => $recordCount,
                'sample_records' => $sampleRecords,
                'database_name' => DB::connection('sqlsrv')->getDatabaseName(),
                'server_info' => DB::connection('sqlsrv')->select('SELECT @@VERSION as version')[0]->version ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Global API: Search file numbers (Top 10 + Search functionality)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

    public function searchFileNumbers(Request $request)
    {
        try {
            \Log::info('FileNumber search API called', [
                'query' => $request->input('query', ''),
                'limit' => $request->input('limit', 10),
                'page' => $request->input('page', 1)
            ]);

            $query = $request->input('query', '');
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);

            // Test database connection first
            try {
                \DB::connection('sqlsrv')->getPdo();
                \Log::info('SQL Server connection successful');
            } catch (\Exception $connException) {
                \Log::error('SQL Server connection failed', ['error' => $connException->getMessage()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Database connection failed',
                    'message' => 'Unable to connect to SQL Server: ' . $connException->getMessage(),
                    'timestamp' => now()->toISOString()
                ], 500);
            }

            // Build the query
            $fileQuery = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'tracking_id',
                    'decommissioning_reason',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc');

            // If query provided, search across relevant fields
            if (!empty($query)) {
                $fileQuery->where(function ($q) use ($query) {
                    $q->where('kangisFileNo', 'LIKE', "%{$query}%")
                        ->orWhere('mlsfNo', 'LIKE', "%{$query}%")
                        ->orWhere('NewKANGISFileNo', 'LIKE', "%{$query}%")
                        ->orWhere('FileName', 'LIKE', "%{$query}%");
                });
            }

            // Get total count for pagination
            $totalCount = $fileQuery->count();
            \Log::info('Total file numbers found', ['count' => $totalCount]);

            // Apply pagination
            $offset = ($page - 1) * $limit;
            $results = $fileQuery->skip($offset)->take($limit)->get();
            \Log::info('Retrieved paginated results', ['count' => $results->count()]);

            // Format results for consistent API response
            $formattedResults = $results->map(function ($file) {
                return [
                    'id' => $file->id,
                    'kangis_file_no' => $file->kangisFileNo,
                    'mlsf_no' => $file->mlsfNo,
                    'new_kangis_file_no' => $file->NewKANGISFileNo,
                    'file_name' => $file->FileName,
                    'tracking_id' => $file->tracking_id,
                    'display_name' => $this->formatDisplayName($file),
                    'search_text' => $this->formatSearchText($file),
                    'status' => 'Active',
                    'decommissioning_reason' => $file->decommissioning_reason,
                    'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                    'has_more' => ($page * $limit) < $totalCount
                ],
                'query' => $query,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('FileNumber search API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to search file numbers',
                'message' => $e->getMessage(),
                'debug_info' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ],
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Global API: Get top 10 recent active file numbers
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopFileNumbers()
    {
        try {
            \Log::info('FileNumber getTopFileNumbers API called');

            $topFiles = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            \Log::info('Retrieved top file numbers', ['count' => $topFiles->count()]);

            $formattedResults = $topFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'kangis_file_no' => $file->kangisFileNo,
                    'mlsf_no' => $file->mlsfNo,
                    'new_kangis_file_no' => $file->NewKANGISFileNo,
                    'file_name' => $file->FileName,
                    'display_name' => $this->formatDisplayName($file),
                    'search_text' => $this->formatSearchText($file),
                    'status' => 'Active',
                    'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
                ];
            });

        } catch (\Exception $connException) {
            \Log::error('SQL Server connection failed in getTopFileNumbers, using mock data', ['error' => $connException->getMessage()]);

            // Return mock data when database is unavailable
            $formattedResults = collect([
                [
                    'id' => 1,
                    'kangis_file_no' => 'KLA/2024/001',
                    'mlsf_no' => 'MLSF-2024-001',
                    'new_kangis_file_no' => 'NKLA/2024/001',
                    'file_name' => 'Victoria Island Property',
                    'display_name' => 'KLA/2024/001 - Victoria Island Property',
                    'search_text' => 'KLA/2024/001 MLSF-2024-001 NKLA/2024/001 Victoria Island Property',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'kangis_file_no' => 'COM/2024/001',
                    'mlsf_no' => 'MLSF-COM-2024-001',
                    'new_kangis_file_no' => 'NCOM/2024/001',
                    'file_name' => 'Commercial Plaza Lagos',
                    'display_name' => 'COM/2024/001 - Commercial Plaza Lagos',
                    'search_text' => 'COM/2024/001 MLSF-COM-2024-001 NCOM/2024/001 Commercial Plaza Lagos',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'id' => 3,
                    'kangis_file_no' => 'RES/2024/001',
                    'mlsf_no' => 'MLSF-RES-2024-001',
                    'new_kangis_file_no' => 'NRES/2024/001',
                    'file_name' => 'Residential Estate Abuja',
                    'tracking_id' => 'TRK-20240101-ABCD',
                    'display_name' => 'RES/2024/001 - Residential Estate Abuja',
                    'search_text' => 'TRK-20240101-ABCD RES/2024/001 MLSF-RES-2024-001 NRES/2024/001 Residential Estate Abuja',
                    'status' => 'Active',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $formattedResults,
            'count' => $formattedResults->count(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Global API: Get file number details by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFileNumberDetails($id)
    {
        try {
            $file = FileNumber::active()
                ->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'tracking_id',
                    'decommissioning_reason',
                    'created_by',
                    'updated_by',
                    'location',
                    'SOURCE',
                    'commissioning_date',
                    'created_at',
                    'updated_at'
                ])
                ->find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => 'File number not found',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            $formattedFile = [
                'id' => $file->id,
                'kangis_file_no' => $file->kangisFileNo,
                'mlsf_no' => $file->mlsfNo,
                'new_kangis_file_no' => $file->NewKANGISFileNo,
                'file_name' => $file->FileName,
                'tracking_id' => $file->tracking_id,
                'display_name' => $this->formatDisplayName($file),
                'search_text' => $this->formatSearchText($file),
                'status' => 'Active',
                'decommissioning_reason' => $file->decommissioning_reason,
                'created_by' => $file->created_by,
                'updated_by' => $file->updated_by,
                'location' => $file->location,
                'source' => $file->SOURCE,
                'commissioning_date' => $file->commissioning_date?->format('Y-m-d H:i:s'),
                'created_at' => $file->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $file->updated_at?->format('Y-m-d H:i:s')
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedFile,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch file number details',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Helper method to format display name for file number
     * 
     * @param FileNumber $file
     * @return string
     */
    private function formatDisplayName($file)
    {
        // Helper function to check if a value is valid (not null, empty, or N/A)
        $isValidValue = function ($value) {
            return $value &&
                trim($value) !== '' &&
                strtoupper(trim($value)) !== 'N/A' &&
                strtoupper(trim($value)) !== 'NULL';
        };

        $validFileNumbers = array_filter([
            $file->kangisFileNo,
            $file->mlsfNo,
            $file->NewKANGISFileNo
        ], $isValidValue);

        // Use array_values to reindex and check if array has elements
        $validFileNumbers = array_values($validFileNumbers);
        $primaryNumber = !empty($validFileNumbers) ? $validFileNumbers[0] : 'N/A';

        // Only add file name if it's valid
        $fileName = $isValidValue($file->FileName) ? ' - ' . $file->FileName : '';

        // Only add tracking ID if it's valid
        $trackingSuffix = $isValidValue($file->tracking_id) ? ' [' . $file->tracking_id . ']' : '';

        return $primaryNumber . $fileName . $trackingSuffix;
    }

    /**
     * Helper method to format search text for file number
     * 
     * @param FileNumber $file
     * @return string
     */
    private function formatSearchText($file)
    {
        // Helper function to check if a value is valid (not null, empty, or N/A)
        $isValidValue = function ($value) {
            return $value &&
                trim($value) !== '' &&
                strtoupper(trim($value)) !== 'N/A' &&
                strtoupper(trim($value)) !== 'NULL';
        };

        return implode(' ', array_filter([
            $file->tracking_id,
            $file->kangisFileNo,
            $file->mlsfNo,
            $file->NewKANGISFileNo,
            $file->FileName
        ], $isValidValue));
    }

    /**
     * The option letters printed on the LGA Confirmation Sheet's "Property
     * Acquisition Method" list. 'e' is the free-text one.
     */
    private const ACQUISITION_METHODS = ['a', 'b', 'c', 'd', 'e'];

    /**
     * An option letter, or null for anything that isn't one — so a hand-edited URL
     * cannot put junk on the sheet or in the stored answer.
     */
    private function normalizeAcquisitionMethod($method): ?string
    {
        $method = strtolower(trim((string) $method));

        return in_array($method, self::ACQUISITION_METHODS, true) ? $method : null;
    }

    /**
     * Resolve the file an "Application for Conversion" (LGA Confirmation Sheet) is
     * printed from. Shared by the print route and the saved-acquisition-method
     * lookup so the two can never disagree about which record they mean.
     *
     * @param  string|int $id  numeric fileNumber.id OR a file number string
     * @return array{record: object|null, is_plot_extension: bool, keyed_by_file_number: bool}
     */
    private function resolveConversionApplicationRecord($id): array
    {
        // $id may be a numeric fileNumber.id OR a file number string (plot extensions
        // are passed by their original file number to avoid id collisions).
        $isNumeric = is_numeric($id);

        $record = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->select([
                'fileNumber.*',
                DB::raw("(SELECT TOP 1 land_use FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as land_use_derived"),
                DB::raw("(SELECT TOP 1 lga FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as lga_derived"),
                DB::raw("(SELECT TOP 1 created_by FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as created_by_derived"),
                DB::raw("(SELECT TOP 1 source FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as source_derived"),
                'mls_file_no.batch_no'
            ])
            ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
            ->where(function ($q) use ($id, $isNumeric) {
                if ($isNumeric) {
                    $q->where('fileNumber.id', (int) $id)->orWhere('fileNumber.mlsfNo', $id);
                } else {
                    // An ST file (SuA especially) is filed under st_file_no with no
                    // mlsfNo of its own, so match either column.
                    $q->where('fileNumber.mlsfNo', $id)->orWhere('fileNumber.st_file_no', $id);
                }
            })
            ->first();

        // A file already present in fileNumber (e.g. legacy indexed files) has no
        // mls_file_no.source of its own — but if it was later run through the Plot
        // Extension flow, that's the real reason this document is being generated.
        // Without this, source_derived stays empty and the "(Conversion)" label is
        // wrongly displayed just because the file number happens to start with CON-.
        if ($record && empty($record->source_derived)) {
            $hasPlotExtension = DB::connection('sqlsrv')
                ->table('plot_extensions')
                ->where('original_file_no', $record->mlsfNo)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->exists();

            if ($hasPlotExtension) {
                $record->source_derived = 'Plot Extension';
            }
        }

        // A file commissioned through ST File Number Commissioning keeps its captured
        // location on st_file_numbers only — it has no mls_file_no row, and (for a
        // directly commissioned SuA) no subapplications row either. This is also the
        // row the commissioning edit screen writes back to, so it is checked first.
        if ($record && !empty($record->st_file_no)
            && (empty($record->lga_derived) || empty($record->plot_no))) {
            $stFile = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('fileno', trim((string) $record->st_file_no))
                ->orderByDesc('id')
                ->first(['property_lga', 'property_plot_no']);

            if ($stFile) {
                if (empty($record->lga_derived) && !empty($stFile->property_lga)) {
                    $record->lga_derived = $stFile->property_lga;
                }
                if (empty($record->plot_no) && !empty($stFile->property_plot_no)) {
                    $record->plot_no = $stFile->property_plot_no;
                }
            }
        }

        // A PRIMARY ST conversion is filed under its CON number, not under st_file_no,
        // so the lookup above misses it. Match the CON number itself to pick up the
        // location captured on the commissioning form.
        if ($record && (empty($record->lga_derived) || empty($record->plot_no))) {
            $stByCon = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where(function ($q) use ($record) {
                    $q->where('mls_fileno', $record->mlsfNo)
                      ->orWhere('fileno', $record->mlsfNo);
                })
                ->orderByDesc('id')
                ->first(['property_lga', 'property_plot_no']);

            if ($stByCon) {
                if (empty($record->lga_derived) && !empty($stByCon->property_lga)) {
                    $record->lga_derived = $stByCon->property_lga;
                }
                if (empty($record->plot_no) && !empty($stByCon->property_plot_no)) {
                    $record->plot_no = $stByCon->property_plot_no;
                }
            }
        }

        // The SuA-only facts the Confirmation Sheet prints — which kind of ST file
        // this is, its parcel number, allocation slip no and the institution it was
        // allocated by. They live on st_file_numbers and nowhere else, and none of
        // them are derivable from the location back-fills above.
        if ($record && !empty($record->st_file_no) && !isset($record->file_no_type)) {
            $stMeta = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('fileno', trim((string) $record->st_file_no))
                ->orderByDesc('id')
                ->first([
                    'file_no_type', 'parcel_no', 'allocation_ref_no',
                    'allocation_source', 'allocation_entity',
                    'institution_category', 'institution_name',
                    // The primary this unit sits under — the sheet quotes both.
                    'mls_fileno',
                ]);

            if ($stMeta) {
                foreach ((array) $stMeta as $column => $value) {
                    $record->{$column} = $value;
                }
            }
        }

        // A SuA has no mls_file_no row to derive the LGA from — the unit keeps its own
        // in subapplications, and that is the Local Government the sheet is addressed to.
        if ($record && empty($record->lga_derived) && !empty($record->st_file_no)) {
            $unit = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('fileno', trim((string) $record->st_file_no))
                ->orderByDesc('id')
                ->first(['unit_lga', 'unit_district']);

            if ($unit && !empty($unit->unit_lga)) {
                $record->lga_derived = $unit->unit_lga;
            }
        }

        // ST fallback: an ST file commissioned through ST File Number Commissioning
        // is not always mirrored into fileNumber (a PRIMARY conversion never is), so
        // st_file_numbers is the only row it has. Its location — the LGA the sheet is
        // addressed to and the plot it names — lives on that row too.
        $isStOnly = false;
        if (!$record && !$isNumeric) {
            $stRow = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where(function ($q) use ($id) {
                    $q->where('fileno', $id)
                      ->orWhere('mls_fileno', $id)
                      ->orWhere('np_fileno', $id);
                })
                ->orderByDesc('id')
                ->first();

            if ($stRow) {
                $isStOnly = true;
                $record = (object) [
                    'id'                 => $stRow->id,
                    'mlsfNo'             => $stRow->mls_fileno ?: $stRow->fileno,
                    'st_file_no'         => $stRow->fileno,
                    'tracking_id'        => $stRow->tra ?? null,
                    // Corporate name wins, then the personal name, then the joint list.
                    'FileName'           => trim((string) ($stRow->corporate_name ?? ''))
                        ?: (trim(implode(' ', array_filter([
                            $stRow->applicant_title ?? null,
                            $stRow->first_name ?? null,
                            $stRow->middle_name ?? null,
                            $stRow->surname ?? null,
                        ]))) ?: trim((string) ($stRow->multiple_owners_names ?? ''))),
                    'plot_no'            => $stRow->property_plot_no ?? null,
                    'tp_no'              => null,
                    'location'           => $stRow->property_district ?? null,
                    'lga'                => $stRow->property_lga ?? null,
                    // SuA-only facts the Confirmation Sheet prints.
                    'file_no_type'         => $stRow->file_no_type ?? null,
                    'mls_fileno'           => $stRow->mls_fileno ?? null,
                    'parcel_no'            => $stRow->parcel_no ?? null,
                    'allocation_ref_no'    => $stRow->allocation_ref_no ?? null,
                    'allocation_source'    => $stRow->allocation_source ?? null,
                    'allocation_entity'    => $stRow->allocation_entity ?? null,
                    'institution_category' => $stRow->institution_category ?? null,
                    'institution_name'     => $stRow->institution_name ?? null,
                    'land_use_derived'   => $stRow->land_use ?? null,
                    'lga_derived'        => $stRow->property_lga ?? null,
                    'created_by_derived' => $stRow->created_by ?? null,
                    'source_derived'     => $stRow->application_type ?? null,
                    'batch_no'           => null,
                ];
            }
        }

        // Plot Extension fallback: retains the original file number but lives only in
        // the isolated plot_extensions table. Resolve the conversion document from it
        // when the original file row is absent from fileNumber (e.g. on production).
        $isPlotExtension = false;
        if (!$record) {
            $pe = DB::connection('sqlsrv')
                ->table('plot_extensions')
                ->where(function ($q) use ($id, $isNumeric) {
                    $q->where('original_file_no', $id);
                    if ($isNumeric) {
                        $q->orWhere('id', (int) $id);
                    }
                })
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->first();

            if ($pe) {
                $isPlotExtension = true;
                $record = (object) [
                    'id'                 => $pe->id,
                    'mlsfNo'             => $pe->original_file_no,
                    'tracking_id'        => $pe->tracking_id,
                    'FileName'           => $pe->file_name,
                    'plot_no'            => $pe->plot_no,
                    'tp_no'              => $pe->tp_no,
                    'location'           => $pe->location,
                    'lga'                => $pe->lga,
                    'land_use_derived'   => $pe->land_use,
                    'lga_derived'        => $pe->lga,
                    'created_by_derived' => $pe->created_by,
                    'source_derived'     => 'Plot Extension',
                    'batch_no'           => null,
                ];
            }
        }

        return [
            'record' => $record,
            'is_plot_extension' => $isPlotExtension,
            // Neither fallback has a fileNumber.id, so their sheet is keyed by the
            // file number instead of mls_file_no_id.
            'keyed_by_file_number' => $isPlotExtension || $isStOnly,
        ];
    }

    /**
     * The conversion_applications row for a resolved record — one per LGA
     * Confirmation Sheet. Plot extensions key off the file number (they have no
     * mls_file_no_id); everything else keys off mls_file_no_id.
     */
    private function conversionApplicationQuery(object $record, bool $keyedByFileNumber)
    {
        return DB::connection('sqlsrv')
            ->table('conversion_applications')
            ->when($keyedByFileNumber,
                fn ($q) => $q->where('full_file_number', $record->mlsfNo),
                fn ($q) => $q->where('mls_file_no_id', $record->id)
            );
    }

    /** The two Allocation Sources a Confirmation Sheet can be raised under. */
    private const ALLOCATION_SOURCES = ['State Government', 'Local Government'];

    /**
     * One of the two sources, or null for anything else — so a hand-edited URL
     * cannot decide who the sheet is addressed to.
     */
    private function normalizeAllocationSource($source): ?string
    {
        $source = trim((string) $source);

        foreach (self::ALLOCATION_SOURCES as $known) {
            if (strcasecmp($source, $known) === 0) {
                return $known;
            }
        }

        return null;
    }

    /**
     * What the print card should show for Allocation Source before it is confirmed.
     *
     * Preference order: the answer stored for this sheet, then the file's own
     * allocation info (a SuA carries it from commissioning), then its LGA — which
     * is what the sheet has always been addressed to.
     *
     * A State Government entity has no "<name> Local Government, Kano State" form
     * to fall back on, so its posting address is carried too — remembered per sheet
     * and, failing that, borrowed from the last sheet addressed to the same entity.
     *
     * `locked` marks an answer somebody actually gave — on this sheet, or on the
     * file at commissioning. The print card shows those read-only: the source is
     * not the printer's to change. A source merely inferred from the file's LGA is
     * a guess and stays editable.
     *
     * A SuA also carries the institution it was allocated by — captured on the
     * commissioning form under the newer Institution Category / Institution
     * lists — and the officer this particular letter is addressed to, which is
     * chosen per sheet and so is never locked.
     *
     * @return array{source: string|null, entity: string|null, address: string|null, locked: bool, category: string|null, institution: string|null, addressed_to: string|null}
     */
    private function suggestedAllocationForSheet(object $record, ?object $existingApp): array
    {
        $source = $this->normalizeAllocationSource($existingApp->allocation_source ?? null);
        $entity = trim((string) ($existingApp->allocation_entity ?? '')) ?: null;
        $address = trim((string) ($existingApp->allocation_address ?? '')) ?: null;

        // The addressee is a per-letter choice: whatever this sheet was last
        // printed with, offered back as the default.
        $addressedTo = trim((string) ($existingApp->addressed_to ?? '')) ?: null;

        // The institution: this sheet's own answer first, then the file's.
        $institution = AllocationSourceResolver::resolve($existingApp);
        if ($institution['institution'] === null) {
            $institution = AllocationSourceResolver::resolve($record);
        }

        if ($source !== null) {
            return [
                'source' => $source,
                'entity' => $entity,
                'address' => $address ?: $this->lastKnownAllocationAddress($entity),
                'locked' => true,
                'category' => $institution['category'],
                'institution' => $institution['institution'],
                'addressed_to' => $addressedTo,
            ];
        }

        // A SuA is filed under its own fileno; a PRIMARY conversion under its CON
        // number, which is what mlsfNo holds. Match either.
        $stKeys = array_values(array_filter([
            trim((string) ($record->st_file_no ?? '')),
            trim((string) ($record->mlsfNo ?? '')),
        ]));

        if ($stKeys !== []) {
            $stFile = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where(function ($q) use ($stKeys) {
                    $q->whereIn('fileno', $stKeys)->orWhereIn('mls_fileno', $stKeys);
                })
                ->orderByDesc('id')
                ->first([
                    'allocation_source', 'allocation_entity',
                    'institution_category', 'institution_name',
                ]);

            $stSource = $this->normalizeAllocationSource($stFile->allocation_source ?? null);
            if ($stSource !== null) {
                $stEntity = trim((string) ($stFile->allocation_entity ?? '')) ?: null;
                $stInstitution = AllocationSourceResolver::resolve($stFile);

                return [
                    'source' => $stSource,
                    'entity' => $stEntity,
                    'address' => $this->lastKnownAllocationAddress($stEntity),
                    'locked' => true,
                    'category' => $stInstitution['category'] ?? $institution['category'],
                    'institution' => $stInstitution['institution'] ?? $institution['institution'],
                    'addressed_to' => $addressedTo,
                ];
            }
        }

        $lga = trim((string) ($record->lga_derived ?? $record->lga ?? ''));

        return [
            'source' => $lga !== '' ? 'Local Government' : null,
            'entity' => $lga !== '' ? $lga : null,
            'address' => null,
            'locked' => false,
            'category' => $institution['category'],
            'institution' => $institution['institution'],
            'addressed_to' => $addressedTo,
        ];
    }

    /**
     * The address last printed for a State Government entity, so the second sheet
     * addressed to it doesn't have to be typed out again.
     */
    private function lastKnownAllocationAddress(?string $entity): ?string
    {
        if (!$entity) {
            return null;
        }

        $address = DB::connection('sqlsrv')
            ->table('conversion_applications')
            ->where('allocation_entity', $entity)
            ->whereNotNull('allocation_address')
            ->orderByDesc('id')
            ->value('allocation_address');

        return trim((string) $address) ?: null;
    }

    /**
     * The Property Acquisition Method saved for a file's LGA Confirmation Sheet.
     * The Print LCS action calls this first: an answered sheet reprints straight
     * away instead of re-opening the "Property Acquisition Method" card.
     */
    public function getConversionAcquisitionMethod(Request $request, $id)
    {
        try {
            ['record' => $record, 'keyed_by_file_number' => $keyedByFileNumber] =
                $this->resolveConversionApplicationRecord($id);

            if (!$record) {
                return response()->json([
                    'success' => false, 'method' => null, 'other' => null,
                    'allocation_source' => null, 'allocation_entity' => null,
                    'allocation_address' => null, 'allocation_locked' => false,
                    'institution_category' => null, 'institution_name' => null,
                    'addressed_to' => null,
                ]);
            }

            $existingApp = $this->conversionApplicationQuery($record, $keyedByFileNumber)->first();
            // null until the question has been answered once for this sheet.
            $method = $this->normalizeAcquisitionMethod($existingApp->acquisition_method ?? null);
            $allocation = $this->suggestedAllocationForSheet($record, $existingApp);

            return response()->json([
                'success' => true,
                'method'  => $method,
                'other'   => $method === 'e'
                    ? (trim((string) ($existingApp->acquisition_other ?? '')) ?: null)
                    : null,
                // Pre-fills the Allocation Source half of the print card.
                'allocation_source' => $allocation['source'],
                'allocation_entity' => $allocation['entity'],
                'allocation_address' => $allocation['address'],
                // true when the source was entered at commissioning (or already
                // issued on this sheet) — the card then shows it read-only.
                'allocation_locked' => $allocation['locked'],
                // Pre-fills the SuA card: category + institution come from
                // commissioning, the addressee is picked per letter.
                'institution_category' => $allocation['category'],
                'institution_name' => $allocation['institution'],
                'addressed_to' => $allocation['addressed_to'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error reading saved acquisition method: ' . $e->getMessage());

            // Never block printing on this — the caller just asks the question.
            return response()->json([
                'success' => false, 'method' => null, 'other' => null,
                'allocation_source' => null, 'allocation_entity' => null,
                'allocation_address' => null, 'allocation_locked' => false,
                'institution_category' => null, 'institution_name' => null,
                'addressed_to' => null,
            ]);
        }
    }

    /**
     * Generate "Application for Conversion" document
     */
    public function generateConversionApplication(Request $request, $id)
    {
        try {
            ['record' => $record, 'keyed_by_file_number' => $keyedByFileNumber] =
                $this->resolveConversionApplicationRecord($id);

            if (!$record) {
                return abort(404, 'Record not found');
            }

            // Wrap in collection for template consistency
            $records = collect([$record]);

            // Capture acquisition method from request
            $acquisitionMethod = $this->normalizeAcquisitionMethod($request->query('method'));
            $specifyOther = $acquisitionMethod === 'e'
                ? (trim((string) $request->query('other')) ?: null)
                : null;

            // Capture the Allocation Source confirmed alongside it — it decides who
            // the sheet is addressed to.
            $allocationSource = $this->normalizeAllocationSource($request->query('allocation_source'));
            $allocationEntity = trim((string) $request->query('allocation_entity')) ?: null;
            // Only a state entity needs one (an LGA is addressed by name), but which
            // source wins is settled below — keep the request's answer until then.
            $allocationAddress = trim((string) $request->query('allocation_address')) ?: null;

            // The SuA card's answers: who the letter is addressed to, at which
            // institution. Both may be typed under "Others (Specify)", in which
            // case they join the shared lookup lists for next time.
            $isSuaSheet = strcasecmp(trim((string) ($record->file_no_type ?? '')), 'SUA') === 0;
            $institutionCategory = AllocationSourceResolver::normalizeCategory(
                $request->query('institution_category')
            );
            $institutionName = AllocationSourceLookup::remember(
                AllocationSourceResolver::institutionType($institutionCategory),
                $request->query('institution_name')
            );
            $addressedTo = AllocationSourceLookup::remember(
                AllocationSourceResolver::addresseeType($institutionCategory),
                $request->query('addressed_to')
            );

            // Check for existing Serial Number.
            $existingApp = $this->conversionApplicationQuery($record, $keyedByFileNumber)->first();

            // The answer is remembered per sheet, so a reprint neither has to ask
            // again nor can quietly contradict the copy already issued.
            $savedMethod = $this->normalizeAcquisitionMethod($existingApp->acquisition_method ?? null);
            $savedOther = $savedMethod === 'e'
                ? (trim((string) ($existingApp->acquisition_other ?? '')) ?: null)
                : null;

            if ($acquisitionMethod === null && $savedMethod !== null) {
                $acquisitionMethod = $savedMethod;
                $specifyOther = $savedOther;
            }

            $savedSource = $this->normalizeAllocationSource($existingApp->allocation_source ?? null);
            $savedEntity = trim((string) ($existingApp->allocation_entity ?? '')) ?: null;
            $savedAddress = trim((string) ($existingApp->allocation_address ?? '')) ?: null;

            $fallback = $this->suggestedAllocationForSheet($record, $existingApp);

            if ($fallback['locked']) {
                // Entered at commissioning (or already issued on this sheet): the
                // printer confirms it, never rewrites it.
                $allocationSource = $fallback['source'];
                $allocationEntity = $fallback['entity'];
            } elseif ($allocationSource === null) {
                $allocationSource = $fallback['source'];
                $allocationEntity = $allocationEntity ?: $fallback['entity'];
            }

            // A SuA is always addressed to a named institution, so it always
            // prints an address; the LGA/Conversion sheet only does so for a
            // state entity, which is addressed by name and not by council.
            $allocationAddress = ($isSuaSheet || $allocationSource === 'State Government')
                ? ($allocationAddress ?: $fallback['address'])
                : null;

            // Institution and addressee fall back the same way the source does:
            // this sheet's stored answer, then the file's own.
            $savedInstitutionCategory = trim((string) ($existingApp->institution_category ?? '')) ?: null;
            $savedInstitutionName = trim((string) ($existingApp->institution_name ?? '')) ?: null;
            $savedAddressedTo = trim((string) ($existingApp->addressed_to ?? '')) ?: null;

            if ($institutionName === null) {
                $institutionName = $fallback['institution'];
                $institutionCategory = $fallback['category'] ?: $institutionCategory;
            }
            if ($addressedTo === null) {
                $addressedTo = $fallback['addressed_to'];
            }

            if ($existingApp && $existingApp->serial_no) {
                $serialNo = $existingApp->serial_no;

                // A freshly answered question (or a corrected one) is written back.
                $changes = [];

                if ($acquisitionMethod !== null
                    && ($acquisitionMethod !== $savedMethod || $specifyOther !== $savedOther)) {
                    $changes['acquisition_method'] = $acquisitionMethod;
                    $changes['acquisition_other'] = $specifyOther;
                }

                if ($allocationSource !== null
                    && ($allocationSource !== $savedSource
                        || $allocationEntity !== $savedEntity
                        || $allocationAddress !== $savedAddress)) {
                    $changes['allocation_source'] = $allocationSource;
                    $changes['allocation_entity'] = $allocationEntity;
                    $changes['allocation_address'] = $allocationAddress;
                }

                if ($institutionName !== null
                    && ($institutionName !== $savedInstitutionName
                        || $institutionCategory !== $savedInstitutionCategory)) {
                    $changes['institution_category'] = $institutionCategory;
                    $changes['institution_name'] = $institutionName;
                }

                if ($addressedTo !== null && $addressedTo !== $savedAddressedTo) {
                    $changes['addressed_to'] = $addressedTo;
                }

                if ($changes !== []) {
                    $changes['updated_at'] = now();
                    $this->conversionApplicationQuery($record, $keyedByFileNumber)->update($changes);
                }
            } else {
                // Generate new Serial Number
                $maxSerial = DB::connection('sqlsrv')->table('conversion_applications')->max('serial_no');
                $serialNo = $maxSerial ? $maxSerial + 1 : 1;

                // Record the generation
                DB::connection('sqlsrv')
                    ->table('conversion_applications')
                    ->insert([
                        'mls_file_no_id' => $keyedByFileNumber ? null : $record->id,
                        'tracking_id' => $record->tracking_id,
                        // An ST file (SuA) has no mlsfNo of its own; it is filed under
                        // st_file_no, and the column is NOT NULL.
                        'full_file_number' => $record->mlsfNo ?: ($record->st_file_no ?? null),
                        'serial_no' => $serialNo,
                        'acquisition_method' => $acquisitionMethod,
                        'acquisition_other' => $specifyOther,
                        'allocation_source' => $allocationSource,
                        'allocation_entity' => $allocationEntity,
                        'allocation_address' => $allocationAddress,
                        // Recipient of a SuA sheet; null on an LGA/Conversion one.
                        'institution_category' => $institutionName !== null ? $institutionCategory : null,
                        'institution_name' => $institutionName,
                        'addressed_to' => $addressedTo,
                        'generated_by' => (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
            }

            $record->serial_no = $serialNo;

            // Determine watermark text based on print count. A directly commissioned
            // SuA has no mlsfNo of its own — without the fallback every reprint would
            // count zero and stamp ORIGINAL.
            $printCount = DB::connection('sqlsrv')->table('print_logs')
                ->where('reference_number', $record->mlsfNo ?: ($record->st_file_no ?? null))
                ->where('document_type', 'Application for Conversion')
                ->count();

            $watermarkText = ($printCount > 1) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            // The allocation slip the SuA sheet quotes. The number it prints for
            // the property comes from the record's own plot_no, under whichever name
            // the allocation calls it.
            $allocationRefNo = trim((string) ($record->allocation_ref_no ?? '')) ?: null;

            return view('generate_fileno.application_for_conversion', compact(
                'records', 'acquisitionMethod', 'specifyOther', 'watermarkText',
                'allocationSource', 'allocationEntity', 'allocationAddress',
                'isSuaSheet', 'institutionName', 'addressedTo', 'allocationRefNo'
            ));

        } catch (\Exception $e) {
            \Log::error('Error generating Conversion Application: ' . $e->getMessage());
            return abort(500, 'Error generating document: ' . $e->getMessage());
        }
    }

    /**
     * Generate batch "Application for Conversion" documents
     */
    public function generateBatchConversionApplication(Request $request, $batchNo)
    {
        try {
            $records = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select([
                    'fileNumber.*',
                    DB::raw("(SELECT TOP 1 land_use FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as land_use_derived"),
                    DB::raw("(SELECT TOP 1 lga FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as lga_derived"),
                    DB::raw("(SELECT TOP 1 created_by FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as created_by_derived"),
                    DB::raw("(SELECT TOP 1 source FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as source_derived"),
                    'mls_file_no.batch_no'
                ])
                ->leftJoin('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                ->where('mls_file_no.batch_no', $batchNo)
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                })
                ->get();

            if ($records->isEmpty()) {
                return abort(404, 'No records found for this batch');
            }

            // Capture acquisition method from request
            $acquisitionMethod = $this->normalizeAcquisitionMethod($request->query('method'));
            $specifyOther = $acquisitionMethod === 'e'
                ? (trim((string) $request->query('other')) ?: null)
                : null;

            $generateBy = (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System';

            // Get current max serial to increment efficiently
            $currentMax = DB::connection('sqlsrv')->table('conversion_applications')->max('serial_no') ?? 0;
            $nextSerial = $currentMax + 1;

            // 1. Pre-fetch existing conversion applications in bulk
            $fileNumberIds = $records->pluck('id')->toArray();
            $existingApps = DB::connection('sqlsrv')
                ->table('conversion_applications')
                ->whereIn('mls_file_no_id', $fileNumberIds)
                ->get()
                ->keyBy('mls_file_no_id');

            // 2. Pre-fetch print counts in bulk to avoid per-record queries if possible
            // For now, individual count is okay if limited, but bulk is better
            $mlsfNumbers = $records->pluck('mlsfNo')->toArray();
            $printCounts = DB::connection('sqlsrv')->table('print_logs')
                ->whereIn('reference_number', $mlsfNumbers)
                ->where('document_type', 'Application for Conversion')
                ->select('reference_number', DB::raw('count(*) as total'))
                ->groupBy('reference_number')
                ->get()
                ->keyBy('reference_number');

            $conversionData = [];
            foreach ($records as $record) {
                $existingApp = $existingApps->get($record->id);

                if ($existingApp && $existingApp->serial_no) {
                    $record->serial_no = $existingApp->serial_no;
                } else {
                    // Assign new serial
                    $record->serial_no = $nextSerial;

                    $conversionData[] = [
                        'mls_file_no_id' => $record->id,
                        'tracking_id' => $record->tracking_id,
                        'full_file_number' => $record->mlsfNo,
                        'serial_no' => $record->serial_no,
                        // The batch answers the question once for every file in it.
                        // Only rows created here are stamped — a file that already
                        // has a sheet keeps the answer given for it individually.
                        'acquisition_method' => $acquisitionMethod,
                        'acquisition_other' => $acquisitionMethod === 'e' ? $specifyOther : null,
                        'generated_by' => $generateBy,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $nextSerial++;
                }

                // Determine watermark text based on pre-fetched print count
                $count = isset($printCounts[$record->mlsfNo]) ? $printCounts[$record->mlsfNo]->total : 0;
                $record->watermarkText = ($count > 900) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
            }

            // 3. Bulk insert new conversion applications
            if (!empty($conversionData)) {
                DB::connection('sqlsrv')->table('conversion_applications')->insert($conversionData);
            }

            // Determine watermark text based on print count for the batch
            $printCount = DB::connection('sqlsrv')->table('print_logs')
                ->where('reference_number', $batchNo)
                ->where('document_type', 'Application for Conversion')
                ->count();

            $watermarkText = ($printCount > 900) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            return view('generate_fileno.application_for_conversion', compact('records', 'acquisitionMethod', 'specifyOther', 'batchNo', 'watermarkText'));

        } catch (\Exception $e) {
            \Log::error('Error generating Batch Conversion Application: ' . $e->getMessage());
            return abort(500, 'Error generating documents: ' . $e->getMessage());
        }
    }

    /**
     * Generate "Application for Conversion" documents for every Conversion (CON-)
     * file commissioned on a given date. Mirrors the batch generator but is scoped
     * by date, so it pairs with the "Application for Conversion" option on the
     * Batch Print — Commissioning Sheets card (which works by date, not batch_no).
     */
    public function generateDateConversionApplication(Request $request)
    {
        try {
            $date = $request->query('date');
            if (!$date) {
                return abort(400, 'A date is required');
            }

            $records = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select([
                    'fileNumber.*',
                    DB::raw("(SELECT TOP 1 land_use FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as land_use_derived"),
                    DB::raw("(SELECT TOP 1 lga FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as lga_derived"),
                    DB::raw("(SELECT TOP 1 created_by FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as created_by_derived"),
                    DB::raw("(SELECT TOP 1 source FROM mls_file_no WHERE full_file_number = fileNumber.mlsfNo ORDER BY id DESC) as source_derived"),
                    'mls_file_no.batch_no'
                ])
                ->join('mls_file_no', 'fileNumber.mlsfNo', '=', 'mls_file_no.full_file_number')
                // Conversion files only.
                ->where('fileNumber.mlsfNo', 'like', 'CON-%')
                // Same date window the batch-print card uses.
                ->where(function ($q) use ($date) {
                    $q->whereDate('mls_file_no.commissioning_date', $date)
                        ->orWhereDate('mls_file_no.created_at', $date);
                })
                ->where(function ($q) {
                    $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
                });

            // Optional narrowing to the exact files the operator reviewed (normally the
            // ones whose Application for Conversion has not been printed yet).
            $onlyFiles = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('files')))));
            if (!empty($onlyFiles)) {
                $records->whereIn('fileNumber.mlsfNo', $onlyFiles);
            }

            $records = $records->orderBy('fileNumber.mlsfNo')->get();

            if ($records->isEmpty()) {
                return abort(404, 'No conversion files found for ' . $date);
            }

            $acquisitionMethod = $this->normalizeAcquisitionMethod($request->query('method'));
            $specifyOther = $acquisitionMethod === 'e'
                ? (trim((string) $request->query('other')) ?: null)
                : null;

            $generateBy = (Auth::user()->first_name . ' ' . Auth::user()->last_name) ?: Auth::user()->name ?: Auth::user()->email ?: 'System';

            // Assign / reuse serial numbers (bulk), same approach as the batch generator.
            $currentMax = DB::connection('sqlsrv')->table('conversion_applications')->max('serial_no') ?? 0;
            $nextSerial = $currentMax + 1;

            $fileNumberIds = $records->pluck('id')->toArray();
            $existingApps = DB::connection('sqlsrv')
                ->table('conversion_applications')
                ->whereIn('mls_file_no_id', $fileNumberIds)
                ->get()
                ->keyBy('mls_file_no_id');

            $mlsfNumbers = $records->pluck('mlsfNo')->toArray();
            $printCounts = DB::connection('sqlsrv')->table('print_logs')
                ->whereIn('reference_number', $mlsfNumbers)
                ->where('document_type', 'Application for Conversion')
                ->select('reference_number', DB::raw('count(*) as total'))
                ->groupBy('reference_number')
                ->get()
                ->keyBy('reference_number');

            $conversionData = [];
            foreach ($records as $record) {
                $existingApp = $existingApps->get($record->id);

                if ($existingApp && $existingApp->serial_no) {
                    $record->serial_no = $existingApp->serial_no;
                } else {
                    $record->serial_no = $nextSerial;
                    $conversionData[] = [
                        'mls_file_no_id' => $record->id,
                        'tracking_id' => $record->tracking_id,
                        'full_file_number' => $record->mlsfNo,
                        'serial_no' => $record->serial_no,
                        // Answered once for the whole date run. Only rows created
                        // here are stamped — a file that already has a sheet keeps
                        // the answer given for it individually.
                        'acquisition_method' => $acquisitionMethod,
                        'acquisition_other' => $acquisitionMethod === 'e' ? $specifyOther : null,
                        'generated_by' => $generateBy,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $nextSerial++;
                }

                $count = isset($printCounts[$record->mlsfNo]) ? $printCounts[$record->mlsfNo]->total : 0;
                $record->watermarkText = ($count > 900) ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
            }

            if (!empty($conversionData)) {
                DB::connection('sqlsrv')->table('conversion_applications')->insert($conversionData);
            }

            $watermarkText = 'ORIGINAL';

            return view('generate_fileno.application_for_conversion', compact('records', 'acquisitionMethod', 'specifyOther', 'watermarkText'));

        } catch (\Exception $e) {
            \Log::error('Error generating Date Conversion Application: ' . $e->getMessage());
            return abort(500, 'Error generating documents: ' . $e->getMessage());
        }
    }

    /**
     * Get the current print status for a document
     */
    public function getPrintStatus(Request $request)
    {
        $reference = $request->query('reference');
        $type = $request->query('type');
        $docType = $request->query('doc_type');
        $batchNo = $request->query('batch_no');

        if (!$reference || !$type || !$docType) {
            return response()->json(['count' => 0, 'status' => 'Original', 'allowed' => true]);
        }

        // Count existing prints
        $query = DB::connection('sqlsrv')->table('print_logs')
            ->where('document_type', $docType);

        if ($type === 'Individual') {
            $query->where(function ($q) use ($reference, $type, $batchNo) {
                // Check for individual prints
                $q->where(function ($sub) use ($reference, $type) {
                    $sub->where('reference_number', $reference)
                        ->where('print_type', $type);
                });

                // Also check for batch prints if batch logic is applicable
                if ($batchNo && $batchNo !== 'null' && $batchNo !== 'undefined') {
                    $q->orWhere(function ($sub) use ($batchNo) {
                        $sub->where('reference_number', $batchNo)
                            ->where('print_type', 'Batch');
                    });
                }
            });
        } else {
            // Batch mode
            $query->where('reference_number', $reference)
                ->where('print_type', $type);
        }

        $count = $query->count();

        // Determine status and eligibility
        // Always allowed as per user request
        $allowed = true;
        // Status is Original for the first 50 prints, then Certified True Copy
        $status = $count < 50 ? 'Original' : 'Certified True Copy';

        return response()->json([
            'count' => $count,
            'status' => $status,
            'allowed' => $allowed
        ]);
    }

    /**
     * Record a print action
     */
    public function recordPrint(Request $request)
    {
        $reference = $request->input('reference');
        $type = $request->input('type');
        $docType = $request->input('doc_type');
        // Optional narrowing: log only these file numbers within the batch/date.
        // Used by documents that apply to a subset of the batch (e.g. the
        // Application for Conversion, which only covers CON- files).
        $onlyFileNumbers = array_values(array_filter((array) $request->input('file_numbers', [])));

        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'Missing reference']);
        }

        try {
            if ($type === 'Batch' || $type === 'Date') {
                $query = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('mls_file_no')
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });

                if ($type === 'Date') {
                    $query->where(function ($q) use ($reference) {
                        $q->whereDate('commissioning_date', $reference)
                            ->orWhereDate('created_at', $reference);
                    });
                } else {
                    $query->where('batch_no', $reference);
                }

                // Exclude OSS / One-Stop Shop specific records (match getBatchRecords filters)
                OssOpCommissionFilter::applyExclusion($query);

                if (!empty($onlyFileNumbers)) {
                    $query->whereIn('full_file_number', $onlyFileNumbers);
                }

                // Only log records that haven't been printed yet
                $query->whereNotExists(function ($q) use ($docType) {
                    $q->select(DB::raw(1))
                        ->from('print_logs as pl')
                        ->whereColumn('pl.reference_number', 'mls_file_no.full_file_number')
                        ->where('pl.document_type', $docType);
                });

                $files = $query->pluck('full_file_number');

                $logs = [];
                $now = now();
                $userId = \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null;

                foreach ($files as $fileNo) {
                    if (!empty($fileNo)) {
                        $logs[] = [
                            'reference_number' => $fileNo,
                            'document_type' => $docType,
                            'print_type' => 'Individual',
                            'status' => 'Printed',
                            'user_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }
                }

                if (!empty($logs)) {
                    \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('print_logs')->insert($logs);
                }
            } else {
                \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('print_logs')->insert([
                    'reference_number' => $reference,
                    'document_type' => $docType,
                    'print_type' => $type,
                    'status' => 'Printed', // You might want to track checking vs printing
                    'user_id' => \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Print log error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not log print']);
        }
    }


    /**
     * Consolidation Report – fetch records from mls_file_no for export (PDF / Excel).
     *
     * Filters:
     *   - date_from / date_to   (commissioning date range)
     *   - file_year             (the year embedded in full_file_number, e.g. 2026)
     *   - prefix                (land_use prefix, e.g. RES, COM, IND, AG …)
     */
    public function getConsolidationReport(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->leftJoin('purposes', 'mls_file_no.purpose_id', '=', 'purposes.id')
                ->leftJoin('prefix', 'mls_file_no.land_use', '=', 'prefix.prefix')
                ->leftJoin('land_uses', 'prefix.land_use_id', '=', 'land_uses.id')
                // Registry + land use come from the grouping pool, which is where a file's
                // physical registry is recorded. A plain LEFT JOIN, not OUTER APPLY: the
                // correlated form runs per row against 7.4M grouping rows and does not
                // finish. grouping holds the odd duplicate mls_fileno, so the result is
                // de-duplicated by mls_file_no.id below.
                ->leftJoin('grouping', 'grouping.mls_fileno', '=', 'mls_file_no.full_file_number')
                ->where(function ($q) {
                    $q->whereNull('mls_file_no.is_deleted')->orWhere('mls_file_no.is_deleted', 0);
                })
                ->select([
                    'mls_file_no.id',
                    'mls_file_no.full_file_number',
                    'mls_file_no.file_name',
                    'mls_file_no.land_use',
                    'mls_file_no.customer_type',
                    'mls_file_no.plot_no',
                    'mls_file_no.tp_no',
                    'mls_file_no.location',
                    'mls_file_no.lga',
                    'mls_file_no.source',
                    'mls_file_no.old_fileno',
                    'mls_file_no.created_by',
                    'mls_file_no.created_at',
                    'purposes.name as purpose_name',
                    'land_uses.landuse as land_use_full',
                    'grouping.registry as registry_id',
                    'grouping.landuse as registry_landuse',
                ]);

            // Filter by commissioning date range
            if ($request->filled('date_from')) {
                $query->whereDate('mls_file_no.created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('mls_file_no.created_at', '<=', $request->input('date_to'));
            }

            // Filter by the year in the file number (e.g. RES-2026-xxx → year = 2026)
            if ($request->filled('file_year')) {
                $year = (int) $request->input('file_year');
                $query->where('mls_file_no.full_file_number', 'LIKE', "%-{$year}-%");
            }

            // Filter by land-use prefix (Exact match)
            if ($request->filled('prefix')) {
                $prefix = trim($request->input('prefix'));
                $query->where('mls_file_no.land_use', $prefix);
            }

            // Ordered so each Registry / Land Use group is contiguous and the report can
            // print one heading per group; id keeps the order inside a group stable.
            $query->orderBy('grouping.registry', 'asc')
                  ->orderBy('grouping.landuse', 'asc')
                  ->orderBy('mls_file_no.id', 'asc');

            $records = $this->dedupeConsolidationRows($query->get());
            $records = $this->attachConsolidationRegistryGroup($records);

            // Every related / previous number recorded against these files, so the report
            // can print them beneath the file number the way the listing does.
            $this->attachConsolidationRelatedFiles($records);

            return response()->json([
                'success' => true,
                'count' => $records->count(),
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            \Log::error('Consolidation report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching consolidation report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * One row per file, whatever the grouping join returned.
     *
     * grouping is a pool table and carries the occasional duplicate mls_fileno, which a
     * LEFT JOIN turns into duplicate report rows -- and this report's whole point is a
     * count, so a file listed twice is a wrong count. First match wins; the ordering is
     * already applied by SQL, so "first" is deterministic.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    private function dedupeConsolidationRows($records)
    {
        $seen = [];

        return $records->filter(function ($row) use (&$seen) {
            $id = $row->id ?? null;
            if ($id === null) {
                return true;
            }
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;

            return true;
        })->values();
    }

    /**
     * Label each row with the Registry / Land Use group the report is broken down by.
     *
     * The registry comes from the file number's prefix + year via config/file_ranges.php;
     * grouping.registry (a physical_registries id) is only the fallback for a number the
     * ranges do not cover. grouping.landuse gives the registry's own land-use wording
     * (Residential, Commercial, ...). physical_registries is a handful of rows, so it is
     * read once per report.
     *
     * A file that matches no range and has no grouping row is grouped under
     * "Unassigned Registry" against its own land use rather than being dropped: the
     * report is a count of what was commissioned, not of what was pooled.
     *
     * Sorting is finished here rather than left to SQL. The fallback land use for an
     * unpooled file comes from the file's own prefix, which grouping.landuse -- the
     * column SQL ordered on -- is null for, so those rows would interleave.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    private function attachConsolidationRegistryGroup($records)
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $registryNames = [];
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('physical_registries')) {
                foreach (DB::connection('sqlsrv')->table('physical_registries')->get(['id', 'name']) as $r) {
                    $registryNames[(string) $r->id] = trim((string) $r->name);
                }
            }
        } catch (\Throwable $e) {
            // Falls back to "Registry <id>" below.
            Log::warning('Could not read physical_registries for the consolidation report', [
                'error' => $e->getMessage(),
            ]);
        }

        // grouping.landuse says "Residential"; land_uses.landuse, the fallback, says
        // "RESIDENTIAL". Left alone the same land use heads two groups, so the four the
        // two vocabularies share are canonicalised. Anything else (SIT, CMD-COM) is left
        // exactly as stored rather than mangled by a blanket title-case.
        $canonical = [
            'RESIDENTIAL' => 'Residential',
            'COMMERCIAL'  => 'Commercial',
            'INDUSTRIAL'  => 'Industrial',
            'AGRICULTURE' => 'Agriculture',
        ];

        // The registry a file belongs to is set by its prefix and year in
        // config/file_ranges.php (COM/IND/AG and the -RC prefixes are Registry 1,
        // RES 1992+ and SIT Registry 2, every CON- prefix Registry 3). The pool's
        // grouping.registry is only the fallback for a number the ranges do not cover:
        // it had COM/IND/AG filed under Registry 2 and left SIT unassigned.
        $resolver = app(\App\Services\FileLocationResolver::class);
        $landRegistryNames = [];
        foreach ($registryNames as $name) {
            if (preg_match('/^(Registry \d+) - Land$/i', $name, $m)) {
                $landRegistryNames[strtoupper($m[1])] = $name;
            }
        }

        foreach ($records as $row) {
            $id = trim((string) ($row->registry_id ?? ''));
            $landUse = trim((string) ($row->registry_landuse ?? ''))
                ?: trim((string) ($row->land_use_full ?? ''))
                ?: trim((string) ($row->land_use ?? ''));
            $landUse = $canonical[strtoupper($landUse)] ?? $landUse;

            $range = $resolver->matchRange((string) ($row->full_file_number ?? ''));
            if ($range !== null) {
                // Registry 1 - Land is closed: files commissioned now go to Registry 2
                // (or Registry 3 for conversions, which the ranges already give). The
                // ranges still say Registry 1 because Quick Search uses them to locate
                // the archived physical files, so the report remaps it here only.
                $registry = strcasecmp($range['registry'], 'Registry 1') === 0 ? 'Registry 2' : $range['registry'];
                $row->registry_name = $landRegistryNames[strtoupper($registry)]
                    ?? ($registry . ' - Land');
            } else {
                $row->registry_name = $id === ''
                    ? 'Unassigned Registry'
                    : ($registryNames[$id] ?? ('Registry ' . $id));
            }
            $row->registry_landuse_label = $landUse ?: 'Unspecified Land Use';
            $row->registry_group = $row->registry_name . ' - ' . $row->registry_landuse_label;
        }

        // Group-contiguous, with the unpooled files last, and file order inside a group
        // kept by id. The padded id makes the sort total, so it cannot depend on the
        // sort being stable.
        return $records->sortBy(function ($row) {
            $unassigned = $row->registry_name === 'Unassigned Registry' ? '1' : '0';

            return $unassigned
                . '|' . $row->registry_name
                . '|' . $row->registry_landuse_label
                . '|' . str_pad((string) ($row->id ?? 0), 12, '0', STR_PAD_LEFT);
        })->values();
    }

    /**
     * Attach a `related_files` list to each consolidation-report row, so the report can
     * print the related / previous numbers under the file number the way the listing does.
     *
     * Deliberately leaner than relatedFileNumbersForRows(): no related-file titles and no
     * KANGIS aliases. Both cost a per-number LIKE scan over file_indexings, which a 20-row
     * listing page can afford and a report that returns a whole year cannot.
     *
     * Sources, in the order the listing badges them:
     *   1. mls_file_no.old_fileno  — the file's previous number (OLD)
     *   2. related_file_number     — the register written at commissioning (REL)
     *
     * @param  \Illuminate\Support\Collection  $records
     */
    private function attachConsolidationRelatedFiles($records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $norm = fn ($v) => strtoupper(trim((string) $v));

        // Both lookups are read WHOLESALE and matched in PHP, rather than filtered by
        // this report's file numbers.
        //
        // The report is not paginated -- a single year is ~8,000 rows -- and a chunked
        // whereIn over that many numbers measured 17.9s against this database. The two
        // source tables together are ~12,700 rows and load in ~120ms, so reading all of
        // them and matching in memory is ~150x faster for a couple of MB. Revisit only
        // if related_file_number grows into the hundreds of thousands.
        //
        // Sources, in the order the listing badges them:
        //   1. mls_file_no.old_fileno   -- the file's previous number (OLD)
        //   2. related_file_number      -- the register written at commissioning (REL)
        //   3. fileNumber.related_fileno -- the register's predecessor (REL)
        //
        // Source 3 is read AS WELL AS the register, not instead of it: ~1,150
        // commissioned files carry their related number only there and have no register
        // row (Change of Purpose and Conversion files especially), so the register alone
        // would show fewer related numbers here than on the listing this mirrors.

        $registered = [];
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('related_file_number')) {
                $rows = DB::connection('sqlsrv')
                    ->table('related_file_number')
                    ->whereNotNull('related_fileno')
                    ->orderBy('id')
                    ->get(['file_number', 'related_fileno', 'transaction_type']);

                foreach ($rows as $r) {
                    $related = trim((string) ($r->related_fileno ?? ''));
                    $key = $norm($r->file_number ?? '');
                    if ($related === '' || $key === '') {
                        continue;
                    }
                    $registered[$key][] = [
                        'file_no' => $related,
                        'type' => trim((string) ($r->transaction_type ?? '')) ?: 'Related File',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // An enrichment: a failure here leaves the report standing, just without the
            // related numbers filled in.
            Log::warning('Could not read the related file number register for the consolidation report', [
                'error' => $e->getMessage(),
            ]);
        }

        // A JSON array on newer rows, plain comma-separated text on the ones that
        // predate it. Filtered in SQL so the fetch stays at ~1,300 rows.
        $legacyRelated = [];
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                $rows = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->whereNotNull('related_fileno')
                    ->whereRaw("LTRIM(RTRIM(related_fileno)) NOT IN ('', '[]', 'N/A')")
                    ->get(['mlsfNo', 'related_fileno']);

                foreach ($rows as $r) {
                    $key = $norm($r->mlsfNo ?? '');
                    if ($key === '') {
                        continue;
                    }
                    $legacyRelated[$key][] = trim((string) $r->related_fileno);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not read fileNumber.related_fileno for the consolidation report', [
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($records as $row) {
            $own = $norm($row->full_file_number ?? '');
            $entries = [];
            $seen = [];

            $add = function (?string $fileNo, string $type) use (&$entries, &$seen, $own, $norm) {
                $clean = trim((string) $fileNo);
                if ($clean === '' || $clean === 'N/A' || $clean === '[]') {
                    return;
                }
                $key = $norm($clean);
                // A file is not related to itself, and is listed once however many
                // register rows quote it.
                if ($key === $own || isset($seen[$key])) {
                    return;
                }
                $seen[$key] = true;
                $entries[] = ['file_no' => $clean, 'type' => $type];
            };

            // Old number first, so a re-issued file reads as OLD rather than REL -- the
            // same precedence formatRelatedOrOldFileNo() applies on the listing.
            $add($row->old_fileno ?? null, 'Old File Number');

            foreach ($registered[$own] ?? [] as $entry) {
                $add($entry['file_no'], $entry['type']);
            }

            foreach ($legacyRelated[$own] ?? [] as $raw) {
                $decoded = json_decode($raw, true);
                $parts = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : explode(',', $raw);
                foreach ($parts as $part) {
                    $add(is_scalar($part) ? (string) $part : null, 'Related File');
                }
            }

            $row->related_files = $entries;
        }
    }

    /**
     * Send a one-off SMS to the applicant on a commissioned file.
     *
     * This is the SMS action on the phone card in the file list: the officer is
     * looking at the row, has read the number, and types the message themselves.
     *
     * IT DOES NOT GO THROUGH KlaesSmsDispatcher, and that is deliberate. The
     * dispatcher exists to police the NINE catalogue messages -- is this key
     * switched on, what is its configured wording, has this event already been
     * reported -- and none of those questions apply to a message a person just
     * typed. Sending it through there would mean inventing a tenth catalogue key
     * that has no template and must never be switched off, which is worse. It
     * still lands in sms_dispatch_logs under its own key so the SMS Control
     * Centre's log shows it beside everything else; klaes_sms.php's catalogue has
     * no entry for that key, so it is labelled by the key itself and does not
     * appear on the per-sender pages.
     *
     * The shared-number guard WARNS here rather than blocking. It exists to stop
     * an automatic hook texting an indexing clerk whose number sits on 1,211
     * files; an officer who has deliberately clicked that number has made the
     * judgement the guard was standing in for.
     */
    public function sendSms(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            // One page is 160 characters and each extra page bills again. The cap
            // is three pages: long enough for anything an officer needs to say at
            // a counter, short enough that a pasted document cannot quietly cost
            // twenty pages per recipient.
            'message' => 'required|string|max:480',
            'file_number' => 'nullable|string|max:100',
        ]);

        $message = trim($validated['message']);

        if ($message === '') {
            return response()->json([
                'success' => false,
                'message' => 'Type a message before sending.',
            ], 422);
        }

        $normalised = BulkSmsNgService::normalizeNumber($validated['phone']);

        if ($normalised === null) {
            return response()->json([
                'success' => false,
                'message' => '"' . $validated['phone'] . '" is not a usable Nigerian mobile number.',
            ], 422);
        }

        /*
         | A fresh gateway per send. BulkSmsNgService keeps the last status code
         | and failure reason as instance state, so a container-shared instance
         | would report the previous message's outcome against this one and the
         | log would quietly lie. Same reasoning as KlaesSmsDispatcher.
         */
        $gateway = new BulkSmsNgService();
        $sent = $gateway->send($normalised, $message);

        $fileNumber = trim((string) ($validated['file_number'] ?? '')) ?: null;

        try {
            SmsDispatchLog::create([
                'message_key' => 'manual_file_list',
                'phone' => $normalised,
                'message' => $message,
                'file_number' => $fileNumber,
                'status' => $sent
                    ? SmsDispatchLog::STATUS_SENT
                    : SmsDispatchLog::STATUS_FAILED,
                'gateway_code' => $gateway->lastStatusCode(),
                'failure_reason' => $sent ? null : $gateway->lastFailureReason(),
                'attempts' => 1,
                'subject_type' => 'manual',
                // No dedupe key: an officer may quite reasonably send the same
                // text twice, and the unique index is filtered so NULLs do not
                // collide.
                'dedupe_key' => null,
                'event_at' => now(config('klaes_sms.timezone', 'Africa/Lagos'))->toDateTimeString(),
                'created_by' => Auth::user()->name ?? null,
            ]);
        } catch (\Throwable $e) {
            // The message has already gone. A log table that is missing on this
            // server must not turn a delivered SMS into an error on screen.
            Log::warning('Manual file-list SMS could not be logged', [
                'phone' => $normalised,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'Accepted by the gateway. Accepted is not delivered: this account is on the promotional route, so a DND-blocked handset receives nothing, and code 609 means it is held until 08:30.'
                : ($gateway->lastFailureReason() ?: 'The gateway refused the message.'),
            'code' => $gateway->lastStatusCode(),
            'phone' => $normalised,
            'pages' => (int) ceil(mb_strlen($message) / 160),
        ], $sent ? 200 : 502);
    }

    /** Render the live KANOMLPP commissioning wording for a manual contact card. */
    public function contactSmsTemplate(Request $request)
    {
        $data = $request->validate([
            'file_number' => 'nullable|string|max:100',
            'application_type' => 'nullable|string|max:120',
        ]);

        $fileNo = strtoupper(trim((string) ($data['file_number'] ?? '')));
        $applicationType = trim((string) ($data['application_type'] ?? '')) ?: 'File Commissioning';
        $message = app(KlaesSmsDispatcher::class)->render(SmsSetting::KEY_LAND_FC, [
            'FileNo' => $fileNo,
            'Application Type' => $applicationType,
            'Department' => 'Land Department',
        ]);

        return response()->json(['success' => true, 'message' => $message ?? '']);
    }

}
