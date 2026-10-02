<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\LandRecommendation;
use App\Models\LandRecommendationBatchDraft;
use App\Models\LandUse;
use App\Models\Purpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Models\PrintLog;

class LandRecommendationController extends Controller
{
    /**
     * Grant-condition columns a regular batch may set per file rather than once
     * for the whole batch. Kept in step with GRANT_FIELDS in the form's Grant
     * Conditions stepper — a column added to one and not the other is a value the
     * officer keys per file and the batch then saves from the common set.
     */
    private const PER_CHILD_GRANT_FIELDS = [
        'term', 'cofo_year', 'selected_year', 'ground_rent', 'development_period',
        'development_value', 'development_charge', 'survey_fees',
        'preparation_fees', 'preparation_fees_words',
        // A hand-picked batch spans layouts, so TP No. is captured per file too.
        'layout_plan_no',
    ];

    public function index(Request $request)
    {
        $viewType = strtoupper(trim((string) $request->query('type', '')));
        if (!in_array($viewType, ['OSS', 'ROFO'], true)) {
            return redirect()->route('home');
        }

        $ossHasIsDeleted = false;
        try {
            $ossHasIsDeleted = Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted');
        } catch (\Throwable $e) {
            $ossHasIsDeleted = false;
        }

        $applyOssChangeOfNameOriginFilter = function ($builder) use ($ossHasIsDeleted) {
            $builder->whereExists(function ($subQuery) use ($ossHasIsDeleted) {
                $subQuery->select(DB::raw('1'))
                    ->from('oss_applications as oa')
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))")
                    ->where('oa.system_source', 'OSSOPCHANGEOFNAME');

                if ($ossHasIsDeleted) {
                    $subQuery->where(function ($q) {
                        $q->whereNull('oa.is_deleted')
                          ->orWhere('oa.is_deleted', 0);
                    });
                }
            });
        };

        // landUse/purpose back the "Landuse/Purpose Clause" column when the row itself
        // only stored the ids (see LandRecommendation::getLandusePurposeAttribute).
        $query = LandRecommendation::with(['creator', 'landUse', 'purpose.landUse']);
        $isOssView = $viewType === 'OSS';

        if ($isOssView) {
            $ossAddressSubSql = "(
                SELECT
                    file_no,
                    address,
                    residential_address,
                    correspondence_address,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(ISNULL(file_no, ''))))
                        ORDER BY id DESC
                    ) as rn
                FROM oss_applications
                WHERE system_source = 'OSSOPCHANGEOFNAME'";

            if ($ossHasIsDeleted) {
                $ossAddressSubSql .= " AND (is_deleted IS NULL OR is_deleted = 0)";
            }

            $ossAddressSubSql .= "
            ) as oa_addr";

            $query->leftJoin(DB::raw($ossAddressSubSql), function ($join) {
                $join->whereRaw("oa_addr.rn = 1")
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa_addr.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))");
            });

            $query->select('land_recommendations.*')
                ->selectRaw("COALESCE(
                    NULLIF(LTRIM(RTRIM(ISNULL(land_recommendations.applicant_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.residential_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.correspondence_address, ''))), '')
                ) as resolved_applicant_address");

            $query->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
            $applyOssChangeOfNameOriginFilter($query);
        } else {
            $query->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
            });
        }

        // The register is shown whole. It used to default to the signed-in user's
        // own records with an "All Users" escape hatch, which meant a recommendation
        // captured by a colleague simply was not there — and nothing on screen said
        // why. Nothing filters on created_by any more.
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // Printed / Not-Printed tab filter. This uses the SAME source as the
        // "Date Printed" column — the existence of a print_logs row matched on
        // file number — so a record can never appear in "Not Printed" while
        // still showing a print date (print_logs has no recommendation id, only
        // the file number, and print_count diverges for CTC/duplicate-file cases).
        //
        // Three document_type values land in print_logs for this document, and all
        // three must count as printed:
        //   'Land Recommendation'           — legacy logPrint() endpoint below
        //   'Recommendation For Grant'      — SmartPrintManager, main view
        //   'OSS Recommendation For Grant'  — SmartPrintManager, OSS view
        // The last two are what index.blade.php passes to SmartPrintManager.open();
        // matching only the first hid 105 already-printed OSS records (and 13 main-
        // view ones) behind a permanently empty "Printed" tab.
        $printedDocTypes = $isOssView
            ? ['Land Recommendation', 'OSS Recommendation For Grant']
            : ['Land Recommendation', 'Recommendation For Grant'];

        $securityCodeDocTypes = $isOssView
            ? ['OSS Recomm']
            : ['Lands ROFO', 'Land Conversion'];

        $printedExists = function ($q) use ($printedDocTypes, $securityCodeDocTypes) {
            $unionQuery = DB::connection('sqlsrv')->table('print_logs')
                ->selectRaw('reference_number as fn')
                ->whereIn('document_type', $printedDocTypes)
                ->unionAll(
                    DB::connection('sqlsrv')->table('security_codes')
                        ->selectRaw('file_number as fn')
                        ->whereIn('document_type', $securityCodeDocTypes)
                );

            $q->select(DB::raw('1'))
              ->fromSub($unionQuery, 'printed_records')
              ->whereRaw('UPPER(LTRIM(RTRIM(printed_records.fn))) = UPPER(LTRIM(RTRIM(land_recommendations.file_number)))');
        };

        $tab = $request->query('tab', 'not_printed');
        if (!in_array($tab, ['printed', 'not_printed', 'batches'], true)) {
            $tab = 'not_printed';
        }

        // The Batches tab pages over batches, not over recommendations. On the main
        // list a 100-child batch is one collapsed row whose children are spread
        // across five pages, so expanding it shows whichever 20 happen to be on the
        // page you are looking at — which reads as "this batch has 20 children".
        // Here one row is one whole batch, and expanding it loads every child.
        if ($tab === 'batches') {
            $batchQuery = LandRecommendation::query()->whereNotNull('rofo_batch_id');

            if ($isOssView) {
                $batchQuery->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
                $applyOssChangeOfNameOriginFilter($batchQuery);
            } else {
                $batchQuery->where(function ($q) {
                    $q->whereNull('type')->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                // Searching batches means searching for the mother file or for a
                // child inside it, so a hit on any member surfaces the whole batch.
                $batchQuery->where(function ($q) use ($search) {
                    $q->where('batch_mother_file_no', 'LIKE', "%{$search}%")
                      ->orWhere('rofo_batch_id', 'LIKE', "%{$search}%")
                      ->orWhere('file_number', 'LIKE', "%{$search}%")
                      ->orWhere('applicant_name', 'LIKE', "%{$search}%");
                });
            }

            $batches = $batchQuery
                ->groupBy('rofo_batch_id')
                ->selectRaw(
                    'rofo_batch_id'
                    . ', MAX(batch_mother_file_no) AS mother_file_no'
                    . ', MAX(old_file_number) AS old_file_number'
                    . ', MAX(application_type) AS application_type'
                    . ', COUNT(*) AS total'
                    . ", SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count"
                    . ", SUM(CASE WHEN rofo_status = 'generated' THEN 1 ELSE 0 END) AS generated_count"
                    . ', MIN(created_at) AS created_at'
                    . ', MAX(created_by) AS created_by'
                )
                ->orderByRaw('MIN(created_at) DESC')
                ->paginate(20)
                ->withQueryString();

            $batchCreators = \App\Models\User::whereIn('id', collect($batches->items())->pluck('created_by')->filter()->unique())
                ->get(['id', 'first_name', 'last_name'])
                ->keyBy('id');

            $PageTitle = 'Recommendation For Grant Of Statutory Right Of Occupancy';

            return view('land_recommendations.index', [
                'recommendations' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'batches'         => $batches,
                'batchCreators'   => $batchCreators,
                'PageTitle'       => $PageTitle,
                'stats'           => $this->indexStats($request, $isOssView, $applyOssChangeOfNameOriginFilter, $printedExists),
                'isOssView'       => $isOssView,
                'tab'             => $tab,
                'printDates'      => [],
                'recSerials'      => [],
                'batchSizes'      => collect(),
                'batchActions'    => collect(),
            ]);
        }

        // Batched records belong to the Batches tab and are kept out of this list —
        // a single subdivision could otherwise fill several pages of it, which is
        // what made batches so hard to see in the first place.
        //
        // A search is the exception: someone looking up a specific file number must
        // find it whether or not it was captured in a batch.
        if (!$request->filled('search')) {
            $query->whereNull('land_recommendations.rofo_batch_id');
        }

        if ($tab === 'printed') {
            $query->whereExists($printedExists);
        } else { // not_printed
            $query->whereNotExists($printedExists);
        }

        // Newest first by date created, for both the Printed and Not Printed tabs
        // (and both the OSS and ROFO views). The column is qualified because the
        // OSS view joins a derived table, and `id` breaks ties: bulk-created rows
        // share a created_at, and SQL Server's OFFSET/FETCH paging needs a
        // deterministic order or rows repeat / vanish between pages.
        $recommendations = $query
            ->orderByDesc('land_recommendations.created_at')
            // Subdivision batches share one created_at; these two keep a batch's
            // children adjacent and in capture order under their grouped row.
            ->orderBy('land_recommendations.rofo_batch_id')
            ->orderBy('land_recommendations.batch_seq')
            ->orderByDesc('land_recommendations.id')
            ->paginate(20)
            ->withQueryString();
        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';

        // Full size of each batch on this page, and every member id, so a batch
        // split across two pages still reports its real child count.
        $batchIdsOnPage = $recommendations->pluck('rofo_batch_id')->filter()->unique()->values();
        $batchSizes = $batchIdsOnPage->isEmpty() ? collect() : LandRecommendation::query()
            ->whereIn('rofo_batch_id', $batchIdsOnPage)
            ->groupBy('rofo_batch_id')
            ->selectRaw('rofo_batch_id, COUNT(*) AS total')
            ->pluck('total', 'rofo_batch_id');

        // Per batch: the ids still pending approval, and whether the whole batch is
        // approved. The batch row's actions act on every child, including any that
        // paginated onto another page.
        $batchActions = $batchIdsOnPage->isEmpty() ? collect() : LandRecommendation::query()
            ->whereIn('rofo_batch_id', $batchIdsOnPage)
            ->get(['id', 'rofo_batch_id', 'status'])
            ->groupBy('rofo_batch_id')
            ->map(fn ($rows) => [
                'pending_ids'  => $rows->where('status', LandRecommendation::STATUS_PENDING)->pluck('id')->values()->all(),
                'all_approved' => $rows->every(fn ($r) => $r->status === LandRecommendation::STATUS_APPROVED),
            ]);

        $stats = $this->indexStats($request, $isOssView, $applyOssChangeOfNameOriginFilter, $printedExists);

        // Batch-load the most recent print date per file number (from print_logs)
        // so the table can show a "Print Date" column without an N+1 per row.
        $printDates = [];
        $fileNumbers = $recommendations->getCollection()
            ->pluck('file_number')
            ->filter()
            ->map(fn ($fn) => strtoupper(trim((string) $fn)))
            ->unique()
            ->all();
        if (!empty($fileNumbers)) {
            $rows = DB::connection('sqlsrv')->table('print_logs')
                ->whereIn('document_type', $printedDocTypes)
                ->whereRaw('UPPER(LTRIM(RTRIM(reference_number))) IN (' . implode(',', array_fill(0, count($fileNumbers), '?')) . ')', $fileNumbers)
                ->selectRaw('UPPER(LTRIM(RTRIM(reference_number))) AS fn, MAX(created_at) AS last_printed')
                ->groupByRaw('UPPER(LTRIM(RTRIM(reference_number)))')
                ->get();
            foreach ($rows as $r) {
                $printDates[$r->fn] = $r->last_printed;
            }
        }

        // The recommendation's Serial No. `land_rofo_serial_no` is NOT it — that
        // column carries the RofO's security paper code, which a recommendation
        // never has. The real serial is the 'OSS Recomm' security_codes row the
        // print template mints, keyed by file number, so it exists only once the
        // recommendation has actually been printed. Batch-loaded and side-effect
        // free: listing a record must never mint a serial.
        $recSerials = [];
        if (!empty($fileNumbers)) {
            $codeService = app(\App\Services\SecurityCodeService::class);
            $codes = DB::connection('sqlsrv')->table('security_codes')
                ->whereIn('document_type', $securityCodeDocTypes)
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) IN (' . implode(',', array_fill(0, count($fileNumbers), '?')) . ')', $fileNumbers)
                ->orderBy('id')
                ->get(['file_number', 'code', 'created_at']);
            foreach ($codes as $c) {
                $fn = strtoupper(trim((string) $c->file_number));
                // getOrGenerateForDocument returns the first unused row; mirror
                // that by keeping the earliest code per file number.
                if (!isset($recSerials[$fn])) {
                    $recSerials[$fn] = $codeService->formatForDisplay($c->code);
                }
                if (!isset($printDates[$fn]) && !empty($c->created_at)) {
                    $printDates[$fn] = $c->created_at;
                }
            }
        }

        return view('land_recommendations.index', compact('recommendations', 'PageTitle', 'stats', 'isOssView', 'tab', 'printDates', 'recSerials', 'batchSizes', 'batchActions'));
    }

    /**
     * The header counters and the tab badges. Extracted so the Batches tab, which
     * returns early with a different paginator, still reports the same numbers as
     * the Printed / Not Printed tabs.
     */
    private function indexStats(Request $request, bool $isOssView, callable $applyOssFilter, callable $printedExists): array
    {
        $statsQuery = LandRecommendation::query();
        if ($isOssView) {
            $statsQuery->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
            $applyOssFilter($statsQuery);
        } else {
            $statsQuery->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
            });
        }

        // No created_by filter: the cards and tab badges count the whole register,
        // matching the list they sit above.

        // The tab badges have to count exactly what their tab lists, so they apply
        // the same batched-record exclusion the list does. The header cards above
        // them are register-wide totals and deliberately do not.
        $tabScope = fn () => $request->filled('search')
            ? (clone $statsQuery)
            : (clone $statsQuery)->whereNull('rofo_batch_id');

        return [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', LandRecommendation::STATUS_PENDING)->count(),
            'approved' => (clone $statsQuery)->where('status', LandRecommendation::STATUS_APPROVED)->count(),
            'total_ground_rent' => (clone $statsQuery)->sum('ground_rent'),
            'printed' => $tabScope()->whereExists($printedExists)->count(),
            'not_printed' => $tabScope()->whereNotExists($printedExists)->count(),
            // Batches, not batched rows — the badge counts what the tab lists.
            'batches' => (clone $statsQuery)->whereNotNull('rofo_batch_id')
                ->distinct()->count('rofo_batch_id'),
        ];
    }

    /**
     * Every child of one batch, for the Batches tab's expanded view.
     *
     * Deliberately unpaginated: the whole point of the tab is that a batch is shown
     * whole rather than sliced by the list's page size.
     */
    public function batchChildren(Request $request, string $batchId)
    {
        $children = LandRecommendation::where('rofo_batch_id', $batchId)
            ->with(['landUse', 'purpose.landUse'])
            ->orderBy('batch_seq')
            ->orderBy('id')
            ->get([
                'id', 'batch_seq', 'file_number', 'applicant_name', 'plot_number', 'location',
                'purpose_of_clause', 'purpose_id', 'land_use', 'land_use_id',
                'status', 'rofo_status', 'land_rofo_serial_no',
            ]);

        if ($children->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Batch not found.'], 404);
        }

        return response()->json([
            'success'  => true,
            'batch_id' => $batchId,
            'count'    => $children->count(),
            'children' => $children->map(fn ($c, $i) => [
                'id'             => $c->id,
                'seq'            => $c->batch_seq ?: ($i + 1),
                'file_number'    => $c->file_number,
                'applicant_name' => $c->applicant_name,
                'plot_number'    => $c->plot_number,
                'location'       => $c->location,
                'purpose'        => $c->landuse_purpose,
                'status'         => $c->status,
                'rofo_status'    => $c->rofo_status,
                'serial_no'      => $c->land_rofo_serial_no,
                'edit_url'       => route('land-recommendations.edit', $c->id),
                'print_url'      => route('land-recommendations.print', $c->id),
            ])->values(),
        ]);
    }

    /**
     * Column metadata shared by the JSON preview, the client-side CSV/PDF and the
     * streamed server CSV, so all three stay in step.
     * `pdfWidth` is in mm — A4 landscape gives ~277mm of usable width.
     */
    private function exportColumns(): array
    {
        return [
            ['key' => 'sn',             'label' => 'S/N',              'pdfWidth' => 9,  'wrap' => false],
            ['key' => 'file_number',    'label' => 'File Number',      'pdfWidth' => 26, 'wrap' => false],
            ['key' => 'applicant_name', 'label' => 'Applicant Name',   'pdfWidth' => 34],
            ['key' => 'purpose',        'label' => 'Landuse/Purpose Clause', 'pdfWidth' => 30],
            // No pdfWidth: Location is the flexible column and absorbs the
            // remaining page width (see buildColumnStyles in records_export.js).
            ['key' => 'location',       'label' => 'Location'],
            ['key' => 'address',        'label' => 'Applicant Address','pdfWidth' => 32],
            ['key' => 'plot_number',    'label' => 'Plot No',          'pdfWidth' => 12],
            ['key' => 'layout_plan_no', 'label' => 'Layout Plan',      'pdfWidth' => 14],
            ['key' => 'term',           'label' => 'Term',             'pdfWidth' => 10],
            ['key' => 'ground_rent',    'label' => 'Ground Rent',      'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_period',     'label' => 'Dev. Period',      'pdfWidth' => 12],
            ['key' => 'prep_fees',      'label' => 'Prep. Fees',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_value',      'label' => 'Dev. Value',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'status',         'label' => 'Status',           'pdfWidth' => 14],
            ['key' => 'created_by',     'label' => 'Created By',       'pdfWidth' => 18],
            ['key' => 'date_generated', 'label' => 'Date Generated',   'pdfWidth' => 18],
            ['key' => 'application_date','label' => 'Application Date','pdfWidth' => 18],
        ];
    }

    /**
     * Flatten one record into the export column keys.
     */
    private function exportRow(LandRecommendation $rec, int $sn, bool $isOssView): array
    {
        return [
            'sn'               => $sn,
            'file_number'      => $rec->file_number,
            'applicant_name'   => $rec->applicant_name,
            'purpose'          => $rec->landuse_purpose,
            'location'         => $rec->display_location,
            'address'          => $rec->resolved_applicant_address ?? $rec->applicant_address ?? 'N/A',
            'plot_number'      => $rec->plot_number,
            'layout_plan_no'   => $rec->layout_plan_no,
            'term'             => $rec->term,
            'ground_rent'      => number_format((float) $rec->ground_rent, 2),
            'dev_period'       => $rec->development_period,
            'prep_fees'        => number_format((float) $rec->preparation_fees, 2),
            'dev_value'        => number_format((float) $rec->development_value, 2),
            'status'           => $isOssView
                ? 'GENERATED'
                : ($rec->status === LandRecommendation::STATUS_APPROVED ? 'APPROVED' : 'PENDING'),
            'created_by'       => $rec->creator->name ?? 'System',
            'date_generated'   => $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A',
            'application_date' => $rec->application_date
                ? $rec->application_date->format('Y-m-d')
                : ($rec->created_at ? $rec->created_at->format('Y-m-d') : 'N/A'),
        ];
    }

    /**
     * Export the Land / OSS recommendation register.
     * `format=json` feeds the preview modal (client-side CSV + PDF);
     * otherwise a UTF-8 CSV is streamed straight to the browser.
     */
    public function export(Request $request)
    {
        $viewType = strtoupper(trim((string) $request->query('type', '')));
        if (!in_array($viewType, ['OSS', 'ROFO'], true)) {
            return redirect()->route('home');
        }

        $isOssView = $viewType === 'OSS';

        $ossHasIsDeleted = false;
        try {
            $ossHasIsDeleted = Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted');
        } catch (\Throwable $e) {
            $ossHasIsDeleted = false;
        }

        // landUse/purpose back the "Landuse/Purpose Clause" column when the row itself
        // only stored the ids (see LandRecommendation::getLandusePurposeAttribute).
        $query = LandRecommendation::with(['creator', 'landUse', 'purpose.landUse']);

        if ($isOssView) {
            // Same resolved-address join the index page uses, so the exported
            // address column matches what is on screen.
            $ossAddressSubSql = "(
                SELECT
                    file_no,
                    address,
                    residential_address,
                    correspondence_address,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(ISNULL(file_no, ''))))
                        ORDER BY id DESC
                    ) as rn
                FROM oss_applications
                WHERE system_source = 'OSSOPCHANGEOFNAME'";

            if ($ossHasIsDeleted) {
                $ossAddressSubSql .= " AND (is_deleted IS NULL OR is_deleted = 0)";
            }

            $ossAddressSubSql .= "
            ) as oa_addr";

            $query->leftJoin(DB::raw($ossAddressSubSql), function ($join) {
                $join->whereRaw("oa_addr.rn = 1")
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa_addr.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))");
            });

            $query->select('land_recommendations.*')
                ->selectRaw("COALESCE(
                    NULLIF(LTRIM(RTRIM(ISNULL(land_recommendations.applicant_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.residential_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.correspondence_address, ''))), '')
                ) as resolved_applicant_address");

            $query->whereRaw("UPPER(ISNULL(land_recommendations.type, '')) = ?", ['OSS']);
            $query->whereExists(function ($subQuery) use ($ossHasIsDeleted) {
                $subQuery->select(DB::raw('1'))
                    ->from('oss_applications as oa')
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))")
                    ->where('oa.system_source', 'OSSOPCHANGEOFNAME');

                if ($ossHasIsDeleted) {
                    $subQuery->where(function ($q) {
                        $q->whereNull('oa.is_deleted')
                          ->orWhere('oa.is_deleted', 0);
                    });
                }
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        $status = strtolower(trim((string) $request->query('status', '')));
        if (in_array($status, [LandRecommendation::STATUS_PENDING, LandRecommendation::STATUS_APPROVED], true)) {
            $query->where('land_recommendations.status', $status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('land_recommendations.created_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('land_recommendations.created_at', '<=', $request->query('end_date'));
        }

        // Same order as the on-screen table. The `id` tiebreak matters more here:
        // the export chunks with OFFSET, so a non-deterministic order would drop
        // or duplicate rows between chunks.
        $query->orderByDesc('land_recommendations.created_at')
              ->orderByDesc('land_recommendations.id');

        $columns = $this->exportColumns();

        if ($request->query('format') === 'json') {
            $rows = [];
            $sn = 0;
            $query->chunk(500, function ($chunk) use (&$rows, &$sn, $isOssView) {
                foreach ($chunk as $rec) {
                    $rows[] = $this->exportRow($rec, ++$sn, $isOssView);
                }
            });

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'data'    => $rows,
                'count'   => count($rows),
            ]);
        }

        $filename = ($isOssView ? 'oss-recommendation' : 'land-recommendation')
            . '-export-' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        $callback = function () use ($query, $columns, $isOssView) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders the ₦ sign and other characters correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));

            $sn = 0;
            $query->chunk(500, function ($chunk) use ($out, $columns, $isOssView, &$sn) {
                foreach ($chunk as $rec) {
                    $row = $this->exportRow($rec, ++$sn, $isOssView);
                    fputcsv($out, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function create(Request $request)
    {
        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses = LandUse::orderBy('landuse')->get();

        // RofO Re-issuance entry point (from the Land RofO page). The re-issued
        // letter is captured as a NEW recommendation for the same file number:
        //   klaes  — the original exists in KLAES, so its details are copied in
        //   legacy — pre-KLAES original, so only the file number is carried over
        $reissuance = strtolower(trim((string) $request->query('reissuance', '')));
        if (!in_array($reissuance, ['klaes', 'legacy'], true)) {
            return view('land_recommendations.form', compact('PageTitle', 'landUses'));
        }

        $source = null;
        if ($reissuance === 'klaes' && $request->filled('source_id')) {
            $source = LandRecommendation::find($request->query('source_id'));
        }

        if ($source) {
            // replicate() gives an unsaved copy — the form treats it as prefill,
            // not as an edit, because $isEdit is passed as false.
            $recommendation = $source->replicate();
            $recommendation->status            = LandRecommendation::STATUS_PENDING;
            $recommendation->approved_at       = null;
            $recommendation->rofo_status       = LandRecommendation::ROFO_PENDING;
            $recommendation->rofo_generated_at = null;
            $recommendation->rofo_print_count  = 0;
            $recommendation->print_count       = 0;
            $recommendation->land_rofo_serial_no = null;
        } else {
            $fileNo = (string) $request->query('file_number', '');
            $cofoYear = null;
            if ($fileNo !== '') {
                if (preg_match('/(?:^|[^0-9])(19\d{2}|20\d{2})(?:[^0-9]|$)/', $fileNo, $matches)) {
                    $cofoYear = (int)$matches[1];
                } else {
                    $commissioningDate = DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('mlsfNo', $fileNo)
                        ->orWhere('kangisFileNo', $fileNo)
                        ->orWhere('NewKANGISFileNo', $fileNo)
                        ->orWhere('st_file_no', $fileNo)
                        ->value('commissioning_date');
                    if ($commissioningDate) {
                        $cofoYear = \Carbon\Carbon::parse($commissioningDate)->year;
                    }
                }
            }
            $recommendation = new LandRecommendation([
                'file_number' => $fileNo,
                'cofo_year'   => $cofoYear,
            ]);
        }

        $purposes = [];
        if ($recommendation->land_use_id) {
            $purposes = Purpose::where('landuseid', $recommendation->land_use_id)->orderBy('name')->get();
        }

        $isEdit           = false;
        $reissuanceSource = $reissuance;
        $reissuedFromId   = $source->id ?? null;

        return view('land_recommendations.form', compact(
            'PageTitle', 'landUses', 'purposes', 'recommendation',
            'isEdit', 'reissuanceSource', 'reissuedFromId'
        ));
    }

    /**
     * Find an existing recommendation for the given file number, if any.
     * Shared by the AJAX warning endpoint and the server-side guard in
     * store()/update() so both apply exactly the same matching rule.
     */
    private function findDuplicate(string $fileNumber, $excludeId = null)
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return null;
        }

        $query = LandRecommendation::query()
            // Case/space-insensitive match so "RES-2026-1" and "res-2026-1 " collide.
            ->whereRaw("UPPER(LTRIM(RTRIM(file_number))) = ?", [strtoupper($fileNumber)]);

        if (!empty($excludeId)) {
            $query->where('id', '!=', (int) $excludeId);
        }

        return $query->orderByDesc('created_at')
            ->first(['id', 'file_number', 'applicant_name', 'status', 'type', 'created_at']);
    }

    /**
     * Reject a save that would duplicate an existing file number, unless the
     * user explicitly confirmed it (the "Save Anyway" path sets
     * `duplicate_confirmed`). The client-side check is only a warning — this is
     * what actually stops a duplicate from a stale page, a failed fetch or a
     * direct POST.
     */
    private function guardAgainstDuplicate(Request $request, $excludeId = null): void
    {
        if ($request->boolean('duplicate_confirmed')) {
            return;
        }

        $existing = $this->findDuplicate((string) $request->input('file_number', ''), $excludeId);

        if (!$existing) {
            return;
        }

        throw ValidationException::withMessages([
            'file_number' => sprintf(
                'A recommendation already exists for %s (applicant: %s, status: %s, created %s). Re-select the file number and choose "Save Anyway" if this is intentional.',
                $existing->file_number,
                $existing->applicant_name ?: '—',
                $existing->status ?: '—',
                optional($existing->created_at)->format('Y-m-d') ?: '—'
            ),
        ]);
    }

    /**
     * Check whether a recommendation already exists for the given file number.
     * Used by the form to warn the user before they re-enter a duplicate.
     * `exclude_id` lets the edit page skip the record being edited.
     */
    public function checkDuplicate(Request $request)
    {
        $existing = $this->findDuplicate(
            (string) $request->query('file_number', ''),
            $request->query('exclude_id')
        );

        if (!$existing) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists'         => true,
            'id'             => $existing->id,
            'file_number'    => $existing->file_number,
            'applicant_name' => $existing->applicant_name,
            'status'         => $existing->status,
            'type'           => $existing->type,
            'created_at'     => optional($existing->created_at)->format('Y-m-d h:i A'),
            'edit_url'       => route('land-recommendations.edit', $existing->id),
        ]);
    }

    public function store(Request $request)
    {
        // A re-issuance intentionally repeats the file number of the letter it
        // replaces, so the duplicate guard does not apply to it.
        $isReissuance = $request->boolean('is_reissuance');
        if (!$isReissuance) {
            $this->guardAgainstDuplicate($request);
        }

        $validated = $request->validate([
            'file_number' => 'required|string',
            'old_file_number' => 'nullable|string|max:100',
            'is_reissuance' => 'nullable|boolean',
            'reissuance_source' => 'nullable|string|in:klaes,legacy',
            'reissued_from_id' => 'nullable|integer',
            // The legacy path is the only one that has to key in the original
            // letter's date; the KLAES path re-issues a record that already has it.
            'reissuance_original_date' => 'nullable|date|before_or_equal:today',
            'applicant_name' => 'required|string',
            'purpose_of_clause' => 'nullable|string',
            'purpose_id' => 'nullable|string',
            'purpose_id_other' => 'nullable|string',
            'location' => 'nullable|string',
            'term' => 'nullable|string',
            'cofo_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'selected_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'ground_rent' => 'nullable|numeric',
            'effective_date' => 'nullable|date',
            'premium' => 'nullable|numeric',
            'development_period' => 'nullable|string',
            'survey_fees' => 'nullable|numeric',
            'preparation_fees' => 'nullable|numeric',
            'land_use' => 'nullable|string',
            'land_use_id' => 'required|exists:sqlsrv.land_uses,id',
            'meeting_date' => 'nullable|date',
            'recommendation' => 'nullable|string',
            'plot_number' => 'nullable|string',
            'house_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'district' => 'nullable|string',
            'state' => 'nullable|string',
            'layout_plan_no' => 'nullable|string',
            'development_value' => 'nullable|numeric',
            'development_charge' => 'nullable|string',
            'tracking_id' => 'nullable|string',
            'application_date' => 'required|date',
            'applicant_address' => 'required|string',
            'type' => 'nullable|string',
            'application_type' => 'nullable|string',
            'use_standard_template' => 'nullable|boolean',
            'page' => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'improvement' => 'nullable|string',
            'revision_period' => 'nullable|string',
            'time_of_erection' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'premium' => 'nullable|numeric',
            'num_plots' => 'nullable|string',
            'file_title' => 'nullable|string',
            'premium_words' => 'nullable|string',
            'preparation_fees_words' => 'nullable|string',
            'plot_sizes' => 'nullable|string',
            'page_2' => 'nullable|string',
            'page_3' => 'nullable|string',
            'page_4' => 'nullable|string',
            'page_5' => 'nullable|string',
            'purpose_description' => 'nullable|string',
            'dimensions_text' => 'nullable|string',
        ]);

        // Map survey method radio to YES/NO flags
        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        // Unchecked checkboxes are absent from the request, so resolve the flag
        // explicitly instead of leaving a previously-saved value in place.
        $validated['use_standard_template'] = $request->boolean('use_standard_template');

        if ($request->filled('land_use_id')) {
            $lu = LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            if ($request->purpose_id === 'other') {
                $validated['purpose_of_clause'] = $request->purpose_id_other;
                $validated['purpose_id'] = null;
            } else {
                $p = Purpose::find($request->purpose_id);
                if ($p) $validated['purpose_of_clause'] = $p->name;
            }
        }

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        // A re-issuance replaces a letter that was already approved and issued, so
        // it lands on the RofO table ready to print rather than re-entering the
        // approval queue.
        if ($isReissuance) {
            $validated['is_reissuance'] = true;
            $validated['status']        = LandRecommendation::STATUS_APPROVED;
            $validated['approved_at']   = now();
            $validated['rofo_status']   = LandRecommendation::ROFO_GENERATED;
            $validated['rofo_generated_at'] = now();
        }

        $recommendation = LandRecommendation::create($validated);

        if ($isReissuance) {
            return redirect()->route('land-rofos.index')
                ->with('success', 'Re-issuance created for ' . $recommendation->file_number . '. It is now on the RofO table, ready to print.');
        }

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO'])
            ->with('success', 'Recommendation created successfully.');
    }

    /**
     * Children of a subdivided mother file, for the Plot Subdivision batch capture.
     *
     * A subdivision child is an mls_file_no row with source = 'Subdivision' whose
     * file_indexings row back-links to the mother through the related_fileno JSON
     * array (written by MlsFileNoController when the subdivision is commissioned).
     * related_fileno is matched on the quoted JSON value rather than a bare LIKE,
     * so "RES-RC-1982-73" cannot pick up the children of "RES-RC-1982-731".
     */
    public function subdivisionChildren(Request $request)
    {
        $mother = trim((string) $request->query('mother_file_no', ''));

        if ($mother === '') {
            return response()->json(['success' => false, 'message' => 'No mother file number supplied.'], 422);
        }

        // A file captured under a temporary number lives as "X(T)" in one place and
        // "X" in another, so look for the child link under both spellings.
        $variants = array_values(array_unique(array_filter([
            $mother,
            preg_replace('/\s*\(T\)\s*$/i', '', $mother),
            $mother . '(T)',
        ])));

        // Source 1 — commissioned through the Subdivision workflow: the child's
        // file_indexings row back-links to the mother via related_fileno, and its
        // mls_file_no row is marked source = 'Subdivision'. The status filter matters
        // because related_fileno also carries Merger / Extension / Recertification
        // lineage.
        $linkedFileNumbers = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('related_fileno', 'LIKE', '%"' . $v . '"%');
                }
            })
            ->pluck('file_number');

        $workflowChildren = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', $linkedFileNumbers)
            ->where('source', 'Subdivision')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->pluck('full_file_number');

        // Source 2 — subdivided manually before the workflow existed and backfilled
        // through Legacy Parcel Update. Those children pre-date the workflow, so they
        // carry no 'Subdivision' source and no related_fileno link; the manual linkage
        // row is the only record of the split.
        $legacyRows = DB::connection('sqlsrv')->table('manual_file_linkages')
            ->where('workflow_type', 'Subdivision')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('old_file_numbers', 'LIKE', '%"' . $v . '"%');
                }
            })
            ->get(['new_file_number', 'child_plot_number', 'applicant_name', 'holding_file_no']);

        // old_file_numbers is a JSON array, so matching on the quoted value is exact —
        // "RES-1999-46" cannot hit a row whose mother is "RES-1999-469".
        $legacyByChild = $legacyRows->keyBy(fn ($r) => trim((string) $r->new_file_number));

        $childFileNumbers = $workflowChildren
            ->merge($legacyByChild->keys())
            ->map(fn ($f) => trim((string) $f))
            ->filter()
            ->unique()
            ->values();

        if ($childFileNumbers->isEmpty()) {
            return response()->json([
                'success'  => true,
                'mother'   => $mother,
                'children' => [],
                'message'  => 'No subdivision children are linked to this file number — neither commissioned through the workflow nor backfilled as a legacy linkage.',
            ]);
        }

        $payload = $this->hydrateBatchRows($childFileNumbers, $legacyByChild);

        return response()->json([
            'success'  => true,
            'mother'   => $mother,
            'children' => $payload,
            'count'    => $payload->count(),
        ]);
    }

    /**
     * Turn a list of file numbers into the row shape the batch table renders.
     *
     * Shared by both batch kinds: the Plot Subdivision capture (children of one
     * mother file) and the regular-files capture (an arbitrary set the user picked).
     * Everything the table shows comes from whatever the file actually has —
     * mls_file_no, file_indexings, a legacy manual linkage, or an existing
     * recommendation — so the two kinds cannot drift apart in what they backfill.
     *
     * $legacyByChild is only ever populated by the subdivision path; a regular
     * batch has no linkage rows to draw on.
     */
    private function hydrateBatchRows($fileNumbers, $legacyByChild = null, bool $sortByPlot = true)
    {
        $fileNumbers  = collect($fileNumbers)->map(fn ($f) => trim((string) $f))->filter()->unique()->values();

        // Every lookup below is keyed on the UPPERCASED file number. SQL Server's
        // collation is case-insensitive, so whereIn() happily matches a row stored
        // as "Res-2026-1000" against a picked "RES-2026-1000" — but keyBy() then
        // files it under the database's spelling and the PHP lookup misses. The row
        // came back blank and flagged "Not on file" even though the file exists.
        // The picker uppercases anything typed into it, so this was hit routinely.
        $key = fn ($v) => mb_strtoupper(trim((string) $v));

        $legacyByChild = collect($legacyByChild ?? [])
            ->keyBy(fn ($row, $k) => $key($k));

        if ($fileNumbers->isEmpty()) {
            return collect();
        }

        // Hydrate from whatever each child actually has. A legacy child may exist in
        // mls_file_no under a different source, only in file_indexings, or in neither.
        $childFileNumbers = $fileNumbers;

        $mlsByFile = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', $childFileNumbers)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->get(['full_file_number', 'file_name', 'plot_no', 'location', 'lga', 'district', 'land_use', 'tracking_id', 'address'])
            ->keyBy(fn ($r) => $key($r->full_file_number));

        $indexByFile = DB::connection('sqlsrv')->table('file_indexings')
            ->whereIn('file_number', $childFileNumbers)
            ->get(['file_number', 'file_title', 'plot_number', 'district', 'lga', 'land_use_type', 'residence_address', 'location'])
            ->keyBy(fn ($r) => $key($r->file_number));

        // Children that already carry a recommendation come back with it attached:
        // the row is unticked (the duplicate guard would reject it) and shows what
        // was already captured rather than the registry defaults, so the user can
        // see the existing letter's details instead of guessing why it is excluded.
        $existingByFile = LandRecommendation::whereIn('file_number', $childFileNumbers)
            ->orderByDesc('id')
            ->get([
                'id', 'file_number', 'applicant_name', 'applicant_address', 'plot_number',
                'location', 'land_use_id', 'purpose_id', 'page', 'page_2', 'page_3',
                'status', 'rofo_status',
            ])
            ->keyBy(fn ($r) => $key($r->file_number));

        // mls_file_no.land_use holds file-number prefixes (RES, CON-AG, IND-RC …)
        // while land_uses holds full names, so route the value through the shared
        // normaliser rather than string-matching the two vocabularies.
        $normalizer = new \App\Services\Prs\Support\LandUseNormalizer();
        $canonToLandUseId = LandUse::pluck('id', 'landuse')
            ->mapWithKeys(fn ($id, $name) => [$normalizer->normalize((string) $name) => $id])
            ->all();

        $payload = $childFileNumbers
            ->map(function ($fileNo) use ($mlsByFile, $indexByFile, $legacyByChild, $existingByFile, $canonToLandUseId, $normalizer, $key) {
                $lookup = $key($fileNo);
                $mls    = $mlsByFile[$lookup] ?? null;
                $index  = $indexByFile[$lookup] ?? null;
                $legacy = $legacyByChild[$lookup] ?? null;
                $rec    = $existingByFile[$lookup] ?? null;

                // Land use is not recorded on a manual linkage, so a legacy child
                // falls back to the indexing row and finally to its file-number prefix.
                $landUseName = trim((string) ($mls->land_use ?? $index->land_use_type ?? ''));
                if ($landUseName === '') {
                    $landUseName = (string) $normalizer->deriveFromFileNumber($fileNo);
                }
                $landUseId = $canonToLandUseId[$normalizer->normalize($landUseName)] ?? null;

                // A child that already has a recommendation shows THAT record's values
                // rather than the registry defaults — the row is unticked, so what is
                // displayed should be what was actually captured, not a fresh guess at
                // it. Each field still falls back to the registry where the existing
                // record left it empty.
                // First non-blank wins, in the order listed. `??` was used here and it
                // only falls through on NULL — a column holding an empty string ended
                // the chain and the later, populated source was never consulted. The
                // precedence below is unchanged; only the blank test is.
                // "PLOT 61, 14, 31" -> "14, 31". file_indexings leads its location
                // with the plot for ~43k rows, and the plot has its own column on the
                // batch row — left in, every letter would carry it twice. Only a
                // leading "PLOT <token>," is taken, and only when something is left
                // after it, so a location that is nothing but a plot survives intact.
                $stripPlot = function (string $loc): string {
                    $out = trim((string) preg_replace('/^\s*PLOT\s+[^,]+,\s*/i', '', $loc));

                    return $out !== '' ? $out : $loc;
                };

                $pick = function (...$values) {
                    foreach ($values as $v) {
                        $text = trim((string) ($v ?? ''));
                        if ($text !== '') {
                            return $text;
                        }
                    }

                    return '';
                };

                return [
                    'file_number'    => $fileNo,
                    'applicant_name' => $pick($rec->applicant_name ?? null, $index->file_title ?? null,
                        $mls->file_name ?? null, $legacy->applicant_name ?? null),
                    'plot_number'    => $pick($rec->plot_number ?? null, $mls->plot_no ?? null,
                        $legacy->child_plot_number ?? null, $index->plot_number ?? null),
                    // file_indexings carries the property description too, and for
                    // older files it is the only place it exists. Its convention is
                    // to lead with the plot ("PLOT 61, 14, 31") — that has its own
                    // column on the row, so it is stripped rather than printed twice.
                    'location'       => $stripPlot($pick($rec->location ?? null, $mls->location ?? null,
                        $index->location ?? null)),
                    // Correspondence address for the letter. Rarely captured on a
                    // subdivision child, so it is usually keyed in the batch table.
                    'applicant_address' => $pick($rec->applicant_address ?? null, $mls->address ?? null,
                        $index->residence_address ?? null),
                    'district'       => $pick($mls->district ?? null, $index->district ?? null),
                    'lga'            => $pick($mls->lga ?? null, $index->lga ?? null),
                    'land_use'       => $landUseName,
                    'land_use_id'    => ($rec && $rec->land_use_id) ? $rec->land_use_id : $landUseId,
                    'purpose_id'     => $rec->purpose_id ?? null,
                    'page'           => (string) ($rec->page ?? ''),
                    'page_2'         => (string) ($rec->page_2 ?? ''),
                    'page_3'         => (string) ($rec->page_3 ?? ''),
                    'tracking_id'    => trim((string) ($mls->tracking_id ?? '')),
                    'is_legacy'      => $legacy !== null && $mls === null,
                    // Nothing on file anywhere. The row is still rendered — the number
                    // was picked deliberately — but the picker says so, because an
                    // empty row is otherwise indistinguishable from a load that failed.
                    'is_unknown'     => $mls === null && $index === null && $legacy === null && $rec === null,
                    'has_recommendation' => $rec !== null,
                    // Shown on the row so the reason it is excluded is visible.
                    'existing_status' => $rec
                        ? trim(($rec->status ?: 'pending') . ' · RofO ' . ($rec->rofo_status ?: 'pending'))
                        : null,
                ];
            })
            ->values();

        // A subdivision reads as a run of plots, so its rows go in plot order. A
        // hand-picked set has no such order — the only meaningful one is the order
        // the officer picked them in, which is the order they were passed in.
        if ($sortByPlot) {
            $payload = $payload->sortBy([['plot_number', 'asc'], ['file_number', 'asc']])->values();
        }

        return $payload->map(function ($row, $i) {
            $row['seq'] = $i + 1;
            return $row;
        });
    }

    /**
     * File numbers for the regular-files batch picker.
     *
     * Deliberately a different search from the global file-number modal: this one
     * answers "what can a recommendation be captured against", so it is scoped to
     * the same two registries the batch table hydrates from, and every hit says
     * whether it already carries a recommendation. A file that does cannot go into
     * a batch — storeBatch() rejects the whole post over it — so it is shown, and
     * disabled, rather than silently omitted and hunted for.
     */
    public function batchFiles(Request $request)
    {
        $term  = trim((string) $request->query('q', ''));
        $limit = max(1, min(100, (int) $request->query('limit', 30)));

        // Without a search term the picker would be a meaningless slice of the whole
        // register, so the first thing it shows is an instruction to type.
        if ($term === '') {
            return response()->json(['success' => true, 'files' => [], 'count' => 0, 'message' => 'Type to search file numbers.']);
        }

        // A file number is searched the way it is read: from the front. "IND-2026"
        // means the IND-2026 run, not every number with those characters buried in
        // it — which is what pushed the real hits off the end of a capped list.
        // Prefix matches are taken first; only if there is room left does a
        // contains match fill the rest, so nothing that used to be findable stops
        // being findable.
        // SQL Server has no default LIKE escape character, so a backslash would be
        // matched literally rather than escaping anything — wildcards typed into
        // the box are neutralised with bracket classes instead. '[' is replaced
        // first; the brackets that introduces contain no % or _ for the later
        // passes to touch.
        $escaped  = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term);
        $prefix   = $escaped . '%';
        $anywhere = '%' . $escaped . '%';

        $files = [];

        $addMls = function (string $like, int $take) use (&$files) {
            if ($take <= 0) {
                return;
            }
            $rows = DB::connection('sqlsrv')->table('mls_file_no')
                ->where('full_file_number', 'LIKE', $like)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('full_file_number')
                ->limit($take)
                ->get(['full_file_number', 'file_name', 'plot_no', 'location']);

            foreach ($rows as $row) {
                $no = trim((string) $row->full_file_number);
                if ($no === '' || isset($files[mb_strtoupper($no)])) {
                    continue;
                }
                $files[mb_strtoupper($no)] = [
                    'file_number'    => $no,
                    'applicant_name' => trim((string) ($row->file_name ?? '')),
                    'plot_number'    => trim((string) ($row->plot_no ?? '')),
                    'location'       => trim((string) ($row->location ?? '')),
                ];
            }
        };

        // Files that were indexed but never made it into mls_file_no — a real and
        // common case for older records, and one the subdivision path already
        // hydrates from, so the picker has to be able to find them too.
        $addIndexed = function (string $like, int $take) use (&$files) {
            if ($take <= 0) {
                return;
            }
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', 'LIKE', $like)
                ->orderBy('file_number')
                ->limit($take)
                ->get(['file_number', 'file_title', 'plot_number', 'district']);

            foreach ($rows as $row) {
                $no = trim((string) $row->file_number);
                if ($no === '' || isset($files[mb_strtoupper($no)])) {
                    continue;
                }
                $files[mb_strtoupper($no)] = [
                    'file_number'    => $no,
                    'applicant_name' => trim((string) ($row->file_title ?? '')),
                    'plot_number'    => trim((string) ($row->plot_number ?? '')),
                    'location'       => trim((string) ($row->district ?? '')),
                ];
            }
        };

        $addMls($prefix, $limit);
        $addIndexed($prefix, $limit - count($files));
        $addMls($anywhere, $limit - count($files));
        $addIndexed($anywhere, $limit - count($files));

        $files = array_slice($files, 0, $limit, true);

        // Case-insensitive collation, so a plain whereIn matches the same rows
        // findDuplicate() would while still using the index.
        $used = [];
        if ($files) {
            foreach (LandRecommendation::whereIn('file_number', array_column($files, 'file_number'))->pluck('file_number') as $u) {
                $used[mb_strtoupper(trim((string) $u))] = true;
            }
        }

        $out = [];
        foreach ($files as $key => $file) {
            $file['has_recommendation'] = isset($used[$key]);
            $out[] = $file;
        }

        // A broad term matches far more than the picker returns, and silently
        // showing the first 30 reads as "my file is not in the register". The
        // picker says so instead and asks for a narrower term.
        return response()->json([
            'success' => true,
            'files'   => $out,
            'count'   => count($out),
            'capped'  => count($out) >= $limit,
            'limit'   => $limit,
        ]);
    }

    /**
     * Table rows for a set of hand-picked file numbers — the regular-files batch.
     *
     * POST rather than GET: a batch of 100 file numbers does not fit comfortably in
     * a query string, and this reads nothing the user has not already selected.
     */
    public function batchFileDetails(Request $request)
    {
        $numbers = $request->input('file_numbers', []);
        if (is_string($numbers)) {
            // Never split on whitespace: KANGIS numbers legitimately contain spaces
            // ("KNML 1"), and doing so would tear one file number into two.
            $numbers = preg_split('/[,;\r\n]+/', $numbers);
        }

        $numbers = collect($numbers)->map(fn ($f) => trim((string) $f))->filter()->unique()->values();

        if ($numbers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No file numbers supplied.'], 422);
        }

        if ($numbers->count() > 300) {
            return response()->json([
                'success' => false,
                'message' => 'A batch is capped at 300 files. Save these in smaller groups.',
            ], 422);
        }

        // Every number picked gets a row, whether or not any registry has anything
        // on it — the officer chose it deliberately and can key the details by hand.
        // Rows nothing is known about are flagged so a blank row cannot be mistaken
        // for a load that failed.
        $rows = $this->hydrateBatchRows($numbers, null, false);

        return response()->json([
            'success'  => true,
            'children' => $rows,
            'count'    => $rows->count(),
            'unknown'  => $rows->where('is_unknown', true)->pluck('file_number')->values(),
        ]);
    }

    /**
     * Mother files that actually have commissioned subdivision children, for the
     * batch-mode picker.
     *
     * Derived from the children rather than from plot_subdivision_applications, so
     * the list can only ever contain files the batch capture can populate — an
     * application that was never commissioned has no children to recommend.
     */
    public function subdivisionMothers(Request $request)
    {
        // Source 1 — commissioned through the Subdivision workflow.
        $children = DB::connection('sqlsrv')->table('mls_file_no as m')
            ->join('file_indexings as fi', 'fi.file_number', '=', 'm.full_file_number')
            ->where('m.source', 'Subdivision')
            ->where(function ($q) {
                $q->whereNull('m.is_deleted')->orWhere('m.is_deleted', 0);
            })
            ->whereNotNull('fi.related_fileno')
            ->get(['m.full_file_number', 'fi.related_fileno']);

        // related_fileno is a JSON array of the files this one derives from.
        // Child sets rather than plain counters, so a file recorded in both sources
        // is not counted twice.
        $childrenByMother = [];
        foreach ($children as $child) {
            $related = json_decode((string) $child->related_fileno, true);
            if (!is_array($related)) {
                continue;
            }
            foreach ($related as $mother) {
                $mother = trim((string) $mother);
                if ($mother === '') {
                    continue;
                }
                $childrenByMother[$mother][trim((string) $child->full_file_number)] = true;
            }
        }

        // Source 2 — subdivided manually before the workflow existed, backfilled
        // through Legacy Parcel Update. old_file_numbers is a JSON array of the
        // decommissioned mother file(s).
        $legacy = DB::connection('sqlsrv')->table('manual_file_linkages')
            ->where('workflow_type', 'Subdivision')
            ->whereNotNull('old_file_numbers')
            ->get(['new_file_number', 'old_file_numbers']);

        foreach ($legacy as $row) {
            $olds = json_decode((string) $row->old_file_numbers, true);
            if (!is_array($olds)) {
                continue;
            }
            foreach ($olds as $mother) {
                $mother = trim((string) $mother);
                if ($mother === '') {
                    continue;
                }
                $childrenByMother[$mother][trim((string) $row->new_file_number)] = true;
            }
        }

        if (!$childrenByMother) {
            return response()->json(['success' => true, 'mothers' => [], 'count' => 0, 'total' => 0]);
        }

        // A child that already carries a recommendation cannot be captured again —
        // storeBatch() rejects the whole batch over it. So the picker counts only
        // the children still available, and a mother whose children are all done
        // drops out of the list entirely rather than offering an empty table.
        $allChildren = [];
        foreach ($childrenByMother as $kids) {
            foreach (array_keys($kids) as $child) {
                $allChildren[$child] = true;
            }
        }

        // Plain whereIn, no UPPER(): the database collation is case-insensitive and
        // ignores trailing spaces, so this matches the same rows findDuplicate()
        // would while still using the index. Chunked to stay under SQL Server's
        // parameter ceiling.
        $usedChildren = [];
        foreach (array_chunk(array_keys($allChildren), 1000) as $chunk) {
            foreach (LandRecommendation::whereIn('file_number', $chunk)->pluck('file_number') as $used) {
                $usedChildren[mb_strtoupper(trim((string) $used))] = true;
            }
        }

        $mothers = [];
        foreach ($childrenByMother as $fileNo => $kids) {
            $total = count($kids);
            $free  = 0;
            foreach (array_keys($kids) as $child) {
                if (!isset($usedChildren[mb_strtoupper($child)])) {
                    $free++;
                }
            }

            if ($free === 0) {
                continue;
            }

            $mothers[] = [
                'file_number'    => $fileNo,
                // What the picker counts and labels: children still to be done.
                'children'       => $free,
                'children_total' => $total,
                'children_used'  => $total - $free,
            ];
        }

        if (!$mothers) {
            return response()->json(['success' => true, 'mothers' => [], 'count' => 0, 'total' => 0]);
        }

        usort($mothers, fn ($a, $b) => strcmp($a['file_number'], $b['file_number']));

        $total = count($mothers);

        // The picker searches server-side: this list only grows as more files are
        // subdivided, and shipping every one of them to the browser on each page
        // load stops scaling long before the register does.
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $needle  = mb_strtoupper($term);
            $mothers = array_values(array_filter(
                $mothers,
                fn ($m) => str_contains(mb_strtoupper($m['file_number']), $needle)
            ));
        }

        // A cap keeps the first, unsearched dropdown small; typing narrows it.
        $limit   = max(1, min(200, (int) $request->query('limit', 50)));
        $matched = count($mothers);
        $mothers = array_slice($mothers, 0, $limit);

        return response()->json([
            'success'   => true,
            'mothers'   => $mothers,
            'count'     => count($mothers),
            'matched'   => $matched,
            'total'     => $total,
            'truncated' => $matched > $limit,
        ]);
    }

    /**
     * Save a batch: one recommendation per selected file, all sharing a
     * rofo_batch_id so the RofO table can group them back together.
     *
     * Two kinds, one path. A `subdivision` batch is keyed to a mother file and
     * covers its commissioned children; a `regular` batch is an arbitrary set of
     * files the officer picked, with no lineage between them. Everything after the
     * mother-file rules is identical — the same common fields are copied onto the
     * same per-file rows — so the two share this method rather than drifting.
     */
    public function storeBatch(Request $request)
    {
        // Absent means subdivision: the only kind that existed before regular
        // batches, so an old form (or a resumed draft keyed by one) still posts
        // exactly what it always did.
        $kind = $request->input('batch_kind') === 'regular' ? 'regular' : 'subdivision';

        $validated = $request->validate([
            'batch_kind' => 'nullable|string|in:subdivision,regular',
            // Only a subdivision batch has a mother; a regular batch is a set of
            // unrelated files and has nothing to group under.
            'batch_mother_file_no' => ($kind === 'subdivision' ? 'required' : 'nullable') . '|string|max:100',
            // A regular batch keeps whatever application type was picked on the
            // form (or none). A subdivision batch is Plot Subdivision by definition
            // and overwrites this below.
            'application_type'     => 'nullable|string|max:60',
            'old_file_number'      => 'nullable|string|max:100',
            'children'                    => 'required|array|min:1',
            'children.*.file_number'      => 'required|string|max:100',
            'children.*.applicant_name'   => 'required|string',
            'children.*.applicant_address' => 'required|string',
            'children.*.plot_number'      => 'nullable|string',
            'children.*.location'         => 'nullable|string',
            'children.*.land_use_id'      => 'required|exists:sqlsrv.land_uses,id',
            'children.*.purpose_id'       => 'nullable|string',
            'children.*.purpose_id_other' => 'nullable|string',
            'children.*.page'             => 'nullable|string',
            'children.*.page_2'           => 'nullable|string',
            'children.*.page_3'           => 'nullable|string',
            'children.*.tracking_id'      => 'nullable|string',

            // Per-file grant conditions. Only a regular batch sends these — its
            // files are unrelated grants, each with its own term and fees, captured
            // on the Grant Conditions stepper. A subdivision omits them and every
            // child keeps the single common set below, exactly as before.
            'children.*.term'               => 'nullable|string',
            'children.*.cofo_year'          => 'nullable|integer|min:1900|max:' . date('Y'),
            'children.*.selected_year'      => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'children.*.ground_rent'        => 'nullable|numeric',
            'children.*.development_period' => 'nullable|string',
            'children.*.development_value'  => 'nullable|numeric',
            'children.*.development_charge' => 'nullable|string',
            'children.*.survey_fees'        => 'nullable|numeric',
            'children.*.preparation_fees'   => 'nullable|numeric',
            'children.*.preparation_fees_words' => 'nullable|string',
            'children.*.layout_plan_no'     => 'nullable|string',

            // Common fields — captured once and copied onto every child.
            // applicant_address is NOT here: it is captured per child in the table,
            // because subdivided plots routinely go to different owners.
            //
            // A batch always prints the standard document (use_standard_template is
            // ticked with batch mode), so Direct / Conversion is live and has to be
            // saved — print() routes on it once the application type is stood down.
            'type'               => 'nullable|string',
            'application_date'   => 'required|date',
            'meeting_date'       => 'nullable|date',
            'effective_date'     => 'nullable|date',
            'term'               => 'nullable|string',
            'cofo_year'          => 'nullable|integer|min:1900|max:' . date('Y'),
            'selected_year'      => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'ground_rent'        => 'nullable|numeric',
            'premium'            => 'nullable|numeric',
            'premium_words'      => 'nullable|string',
            'development_period' => 'nullable|string',
            'development_value'  => 'nullable|numeric',
            'development_charge' => 'nullable|string',
            'survey_fees'        => 'nullable|numeric',
            'preparation_fees'   => 'nullable|numeric',
            'preparation_fees_words' => 'nullable|string',
            'recommendation'     => 'nullable|string',
            'layout_plan_no'     => 'nullable|string',
            'state'              => 'nullable|string',
            'street_name'        => 'nullable|string',
            'improvement'        => 'nullable|string',
            'revision_period'    => 'nullable|string',
            'time_of_erection'   => 'nullable|string',
            'survey_report'      => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',

            // The autosaved draft this batch was keyed in, so it can be closed out
            // once the recommendations exist. Absent for a batch keyed in one sitting
            // with autosave unavailable — the save must not depend on it.
            'draft_key'          => 'nullable|string|max:40',

            // How many children the browser posted. See the truncation guard below.
            'children_expected'  => 'nullable|integer|min:0',
        ]);

        // A 200-child batch is over 2,000 form fields. PHP discards everything past
        // max_input_vars silently, so a truncated post would save a short batch and
        // report success — the children that fell off would just never exist. The
        // browser declares its own count so the mismatch is caught here instead.
        $expected = (int) ($validated['children_expected'] ?? 0);
        if ($expected > 0 && $expected !== count($validated['children'])) {
            throw ValidationException::withMessages([
                'children' => 'Only ' . count($validated['children']) . ' of ' . $expected
                    . ' children reached the server — the form is larger than this server accepts in one post '
                    . '(PHP max_input_vars is ' . ini_get('max_input_vars') . '). Nothing was saved and your draft is safe. '
                    . 'Save the batch in smaller groups, or ask an administrator to raise max_input_vars.',
            ]);
        }

        $mother = trim((string) ($validated['batch_mother_file_no'] ?? ''));

        // Same rule as the single-record path: a file number may not carry two
        // recommendations. Reported for the whole batch at once so the user fixes
        // the selection in one pass rather than one child per attempt.
        $clashes = [];
        foreach ($validated['children'] as $child) {
            if ($this->findDuplicate($child['file_number'])) {
                $clashes[] = $child['file_number'];
            }
        }
        if ($clashes) {
            throw ValidationException::withMessages([
                'children' => 'These children already have a recommendation: ' . implode(', ', $clashes)
                    . '. Untick them and save again.',
            ]);
        }

        $common = collect($validated)
            ->except(['children', 'batch_kind', 'batch_mother_file_no', 'rofo_survey_method', 'draft_key', 'children_expected'])
            ->all();
        $common['rofo_director_survey']    = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $common['rofo_licensed_surveyor']  = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        $common['use_standard_template']   = $request->boolean('use_standard_template');
        $common['created_by']              = Auth::id();
        $common['updated_by']              = Auth::id();

        if ($kind === 'subdivision') {
            // The mother is both the lineage link and what groups the batch, and
            // num_plots is the size of the split — none of which a regular batch has.
            $common['old_file_number']      = $mother;
            $common['batch_mother_file_no'] = $mother;
            $common['application_type']     = 'Plot Subdivision';
            $common['num_plots']            = (string) count($validated['children']);
        }

        $batchId = 'RB-' . now()->format('YmdHis') . '-' . strtoupper(substr(md5(($mother ?: 'REGULAR') . microtime(true)), 0, 4));

        // One timestamp for the whole batch. The RofO table is ordered newest-first
        // on created_at, which carries milliseconds — left to itself each row gets
        // its own and the batch interleaves with other records instead of grouping.
        // Assigned on the model rather than through create(), because the timestamp
        // columns are not fillable and mass assignment would drop them.
        $batchNow = now();

        $purposeNames = Purpose::pluck('name', 'id')->all();
        $landUseNames = LandUse::pluck('landuse', 'id')->all();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $created = 0;
            foreach (array_values($validated['children']) as $i => $child) {
                $row = $common;
                $row['rofo_batch_id']  = $batchId;
                $row['batch_seq']      = $i + 1;
                $row['file_number']       = trim($child['file_number']);
                $row['applicant_name']    = $child['applicant_name'];
                $row['applicant_address'] = $child['applicant_address'];
                $row['plot_number']       = $child['plot_number'] ?? null;
                $row['location']       = $child['location'] ?? null;
                $row['tracking_id']    = $child['tracking_id'] ?? null;
                $row['land_use_id']    = $child['land_use_id'];
                $row['land_use']       = $landUseNames[$child['land_use_id']] ?? null;
                $row['page']           = $child['page'] ?? null;
                $row['page_2']         = $child['page_2'] ?? null;
                $row['page_3']         = $child['page_3'] ?? null;

                // A regular batch captures grant conditions per file, so anything
                // the child carries wins over the common set. Only keys actually
                // present are considered — a subdivision sends none of them and
                // falls through to $common untouched. A key present but blank is
                // still an answer ("no ground rent"), so it is written as null
                // rather than quietly reverting to the common value.
                foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
                    if (array_key_exists($field, $child)) {
                        $row[$field] = ($child[$field] === '' ? null : $child[$field]);
                    }
                }

                $purposeId = $child['purpose_id'] ?? null;
                if ($purposeId === 'other') {
                    $row['purpose_id']        = null;
                    $row['purpose_of_clause'] = $child['purpose_id_other'] ?? null;
                } elseif ($purposeId) {
                    $row['purpose_id']        = $purposeId;
                    $row['purpose_of_clause'] = $purposeNames[$purposeId] ?? null;
                }

                $record = new LandRecommendation();
                $record->fill($row);
                $record->created_at = $batchNow;
                $record->updated_at = $batchNow;
                $record->save();
                $created++;
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();

            // The draft is deliberately left open: the user is coming straight back
            // to this form to fix whatever failed, and it is all they still have.
            return back()->withInput()
                ->with('error', 'Batch could not be saved: ' . $e->getMessage());
        }

        // Close the autosaved draft only now that the recommendations are committed.
        // Doing it any earlier would throw the capture away on a failed save.
        if (!empty($validated['draft_key'])) {
            LandRecommendationBatchDraft::where('draft_key', $validated['draft_key'])
                ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
                ->update([
                    'status'        => LandRecommendationBatchDraft::STATUS_SUBMITTED,
                    'rofo_batch_id' => $batchId,
                    'updated_at'    => now(),
                ]);
        }

        $summary = $kind === 'subdivision'
            ? "Subdivision batch saved — {$created} recommendations created for children of {$mother}."
            : "Batch saved — {$created} recommendations created for the selected files.";

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO', 'batch' => $batchId])
            ->with('success', $summary);
    }

    public function show($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        return view('land_recommendations.show', compact('recommendation'));
    }

    public function edit($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Legacy records stored the district name inside the freeform `location`
        // field (e.g. "PLOT 47, FARI, DAWAKIN KUDU") before the structured
        // District/LGA builder existed, so `location` is almost never equal to
        // just the district name — it needs a substring match, not equality.
        // District catalog names are also often suffixed with "District" (e.g.
        // "Dawakin Kudu District") which never appears verbatim in the legacy
        // text, so that suffix is stripped before comparing.
        if (empty($recommendation->district) && !empty($recommendation->location)) {
            $locationUpper = strtoupper(trim($recommendation->location));

            $matchedDistrict = District::where('is_active', true)
                ->get()
                ->map(function ($district) {
                    $district->display_name = trim(preg_replace('/\s+District$/i', '', $district->name));
                    return $district;
                })
                ->filter(fn ($district) => $district->display_name !== '' && str_contains($locationUpper, strtoupper($district->display_name)))
                ->sortByDesc(fn ($district) => strlen($district->display_name))
                ->first();

            if ($matchedDistrict) {
                $recommendation->district = $matchedDistrict->display_name;
            }
        }

        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses = LandUse::orderBy('landuse')->get();
        $purposes = [];
        if ($recommendation->land_use_id) {
            $purposes = Purpose::where('landuseid', $recommendation->land_use_id)->orderBy('name')->get();
        }
        return view('land_recommendations.form', compact('recommendation', 'PageTitle', 'landUses', 'purposes'));
    }

    public function update(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $this->guardAgainstDuplicate($request, $recommendation->id);

        $validated = $request->validate([
            'file_number' => 'required|string',
            'old_file_number' => 'nullable|string|max:100',
            // Editable so a legacy re-issuance saved before this date was captured
            // (or keyed in wrongly) can be corrected — the letter prints from it.
            'reissuance_original_date' => 'nullable|date|before_or_equal:today',
            'applicant_name' => 'required|string',
            'purpose_of_clause' => 'nullable|string',
            'purpose_id' => 'nullable|string',
            'purpose_id_other' => 'nullable|string',
            'location' => 'nullable|string',
            'term' => 'nullable|string',
            'cofo_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'selected_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'ground_rent' => 'nullable|numeric',
            'effective_date' => 'nullable|date',
            'premium' => 'nullable|numeric',
            'development_period' => 'nullable|string',
            'survey_fees' => 'nullable|numeric',
            'preparation_fees' => 'nullable|numeric',
            'land_use' => 'nullable|string',
            'land_use_id' => 'required|exists:sqlsrv.land_uses,id',
            'meeting_date' => 'nullable|date',
            'recommendation' => 'nullable|string',
            'plot_number' => 'nullable|string',
            'house_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'district' => 'nullable|string',
            'state' => 'nullable|string',
            'layout_plan_no' => 'nullable|string',
            'development_value' => 'nullable|numeric',
            'development_charge' => 'nullable|string',
            'tracking_id' => 'nullable|string',
            'application_date' => 'required|date',
            'applicant_address' => 'required|string',
            'edit_reason' => 'nullable|string',
            'type' => 'nullable|string',
            'page' => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'improvement' => 'nullable|string',
            'revision_period' => 'nullable|string',
            'time_of_erection' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'type' => 'nullable|string',
            'application_type' => 'nullable|string',
            'use_standard_template' => 'nullable|boolean',
            'premium' => 'nullable|numeric',
            'num_plots' => 'nullable|string',
            'file_title' => 'nullable|string',
            'premium_words' => 'nullable|string',
            'preparation_fees_words' => 'nullable|string',
            'plot_sizes' => 'nullable|string',
            'page_2' => 'nullable|string',
            'page_3' => 'nullable|string',
            'page_4' => 'nullable|string',
            'page_5' => 'nullable|string',
            'purpose_description' => 'nullable|string',
            'dimensions_text' => 'nullable|string',
        ]);

        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        // Unchecked checkboxes are absent from the request, so resolve the flag
        // explicitly instead of leaving a previously-saved value in place.
        $validated['use_standard_template'] = $request->boolean('use_standard_template');

        if ($request->filled('land_use_id')) {
            $lu = LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            if ($request->purpose_id === 'other') {
                $validated['purpose_of_clause'] = $request->purpose_id_other;
                $validated['purpose_id'] = null;
            } else {
                $p = Purpose::find($request->purpose_id);
                if ($p) $validated['purpose_of_clause'] = $p->name;
            }
        }

        // `type` marks where the recommendation came from, and the edit form only
        // offers Direct / Conversion — OSS is not one of its choices. Saving an OSS
        // record through this form would therefore post type=Direct and silently
        // move it out of the OSS scope every list and print path filters on, so the
        // OSS origin is never overwritten here.
        if (strtoupper((string) $recommendation->type) === 'OSS') {
            $validated['type'] = 'OSS';
        }

        $validated['updated_by'] = Auth::id();

        $recommendation->update($validated);

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO'])
            ->with('success', 'Recommendation updated successfully.');
    }

    public function approve($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        $recommendation->update([
            'status' => LandRecommendation::STATUS_APPROVED,
            'approved_at' => now()
        ]);

        $generated = $this->generateRofosForBatchMembers([$recommendation->id]);

        return response()->json(['success' => true, 'rofos_generated' => $generated]);
    }

    public function batchApprove(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $count = LandRecommendation::whereIn('id', $ids)
            ->where('status', LandRecommendation::STATUS_PENDING)
            ->update(['status' => LandRecommendation::STATUS_APPROVED, 'approved_at' => now()]);

        $generated = $this->generateRofosForBatchMembers($ids);

        return response()->json(['success' => true, 'approved' => $count, 'rofos_generated' => $generated]);
    }

    /**
     * Approving a subdivision batch also generates its RofOs.
     *
     * A batch is captured in one pass with every RofO value already keyed, so a
     * separate per-child "Generate RofO" step would be pure clicking — approval is
     * the decision, and the letter is ready the moment it is made. Ordinary
     * single-record recommendations keep the two-step approve-then-generate flow,
     * which is why this is scoped to rows carrying a rofo_batch_id.
     */
    private function generateRofosForBatchMembers(array $ids): int
    {
        if (!$ids) {
            return 0;
        }

        $members = LandRecommendation::whereIn('id', $ids)
            ->whereNotNull('rofo_batch_id')
            ->where('status', LandRecommendation::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('rofo_status')
                  ->orWhere('rofo_status', '!=', LandRecommendation::ROFO_GENERATED);
            })
            ->get();

        if ($members->isEmpty()) {
            return 0;
        }

        $generator = app(\App\Services\LandRofoGenerator::class);
        $generated = 0;

        foreach ($members as $member) {
            try {
                $generator->generate($member);
                $generated++;
            } catch (\Throwable $e) {
                // One child failing to sync must not strand the rest of the batch
                // as approved-but-ungenerated.
                Log::warning('Auto-generate RofO failed for recommendation ' . $member->id . ': ' . $e->getMessage());
            }
        }

        return $generated;
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        $isOssRecommendation = strtoupper((string) ($recommendation->type ?? '')) === 'OSS';

        if ($isOssRecommendation) {
            $formatNaira = static function ($value): string {
                if ($value === null || $value === '') {
                    return '';
                }

                $number = is_numeric($value)
                    ? (float) $value
                    : (float) preg_replace('/[^0-9.\-]/', '', (string) $value);

                return 'N' . number_format($number, 2);
            };

            $record = (object) [
                'applicant_name'    => strtoupper((string) ($recommendation->applicant_name ?? '')),
                'file_ref'          => (string) ($recommendation->file_number ?? ''),
                'purpose'           => strtoupper($recommendation->landuse_purpose),
                'location'          => (string) ($recommendation->location ?? ''),
                'plot_no'           => strtoupper((string) ($recommendation->plot_number ?? '')),
                'plan_no'           => strtoupper((string) ($recommendation->layout_plan_no ?? '')),
                'term'              => (string) ($recommendation->term ?? ''),
                'dev_value'         => $formatNaira($recommendation->development_value ?? null),
                // The unit lives on the accessor now that the form captures a bare
                // number of years — see LandRecommendation::development_period_label.
                'completion_time'   => $recommendation->development_period_label,
                'ground_rent'       => $formatNaira($recommendation->ground_rent ?? null),
                'dev_charge'        =>  (string)($recommendation->development_charge ?? null),
                'survey_charges'    => $formatNaira($recommendation->survey_fees ?? null),
                'director_reasons'  => (string) ($recommendation->recommendation ?? ''),
                'director_sign'     => '',
                'director_date'     => '',
                'ps_plot'           => strtoupper((string) ($recommendation->plot_number ?? '')),
                'ps_location'       => strtoupper((string) ($recommendation->location ?? '')),
                'ps_sign'           => '',
                'ps_date'           => '',
                'commissioner_name' => '',
                'commissioner_date' => '',
                'approval_status'   => '',
                'tracking_id'       => (string) ($recommendation->tracking_id ?? ''),
                'rofo_serial_no'    => (string) ($recommendation->land_rofo_serial_no ?? ''),
            ];

            return view('lands_one_stop_shop.partials.print_recommendation', compact('record'));
        }

        if (!$isOssRecommendation && $recommendation->status !== LandRecommendation::STATUS_APPROVED) {
            abort(403, 'Document must be approved before printing.');
        }

        // Print limit enforcement disabled for now.

        return $this->printViewFor($recommendation);
    }

    /**
     * The print view for one (non-OSS) recommendation. Shared by the single print
     * and the batch print so a batch can never drift onto a different template
     * than the individual documents it is made of.
     */
    private function printViewFor(LandRecommendation $recommendation)
    {
        // Route by Application Type first; fall back to Recommendation Type.
        // The "standard template" override keeps the application type on the record
        // (extra fields / old file number) but prints Direct / Conversion instead.
        $primaryAppType = $recommendation->use_standard_template
            ? null
            : ($recommendation->application_type ?? null);

        $appTypeTemplates = [
            'Private Layout'                                     => 'land_recommendations.templates.application_for_plot_merger',
            'Plot Merger'                                        => 'land_recommendations.templates.application_for_plot_merger',
            'Plot Subdivision'                                   => 'land_recommendations.templates.application_for_plot_subdivision',
            'Plot Extension'                                     => 'land_recommendations.templates.application_for_plot_extension',
            'Temporary File No'             => 'land_recommendations.templates.application_for_temporary_file_no',
            'Ministry of Works'             => 'land_recommendations.templates.application_for_ministry_of_works',
            'Change of Purpose'             => 'land_recommendations.templates.application_for_change_of_purpose',
            // Legacy keys kept for old records
            'Statutory Right of Occupancy'                => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
            'Statutory Right of Occupancy (Residential)'  => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
            'Statutory Right of Occupancy (Commercial)'   => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
        ];

        if ($primaryAppType && isset($appTypeTemplates[$primaryAppType])) {
            $record = $recommendation;
            // Attach plotSizes as a collection for templates that use dimension tables
            $plotSizesRaw = $recommendation->plot_sizes ?? null;
            $record->plotSizes = $plotSizesRaw ? collect(json_decode($plotSizesRaw)) : collect([]);
            // Detect residential vs commercial from file number prefix
            $filePrefix = strtoupper(substr($recommendation->file_number ?? '', 0, 3));
            $isResidential = $filePrefix === 'RES';
            $isCommercial  = in_array($filePrefix, ['COM', 'IND', 'CON']);
            return view($appTypeTemplates[$primaryAppType], compact('recommendation', 'record', 'isResidential', 'isCommercial'));
        }

        if ($recommendation->type === 'Conversion') {
            return view('land_recommendations.templates.conversion_print', compact('recommendation'));
        }

        return view('land_recommendations.templates.standalone_print', compact('recommendation'));
    }

    /**
     * Print every recommendation in a subdivision batch as one document.
     *
     * There is no batch template: each child is rendered through the very same
     * view its individual print uses, then the bodies are stitched together with
     * page breaks. Every child of a batch shares an application type, so they all
     * resolve to the same template and its <head> can be reused for the whole
     * document.
     */
    public function printBatch(Request $request, string $batchId)
    {
        $records = LandRecommendation::where('rofo_batch_id', $batchId)
            ->orderBy('batch_seq')
            ->get();

        if ($records->isEmpty()) {
            abort(404, 'Batch not found.');
        }

        $unapproved = $records->where('status', '!=', LandRecommendation::STATUS_APPROVED);
        if ($unapproved->isNotEmpty()) {
            abort(403, 'Every recommendation in the batch must be approved before printing. Pending: '
                . $unapproved->pluck('file_number')->implode(', '));
        }

        $stitched = app(\App\Services\StitchedBatchPrint::class)
            ->stitch($records->map(fn ($record) => $this->printViewFor($record)));

        // A regular batch has no mother file to name it by, so it is titled by its
        // batch id instead of by an empty string.
        $mother = trim((string) ($records->first()->batch_mother_file_no ?: $records->first()->old_file_number))
            ?: $batchId;

        return view('print.stitched_batch', [
            'head'     => $stitched['head'],
            'bodies'   => $stitched['bodies'],
            'title'    => 'Batch Recommendation Print — ' . $mother . ' (' . $records->count() . ')',
            'subtitle' => 'Batch ' . $mother . ' — ' . $records->count() . ' '
                . \Illuminate\Support\Str::plural('recommendation', $records->count()),
            'logUrls'  => $records->map(fn ($r) => route('land-recommendations.log-print', $r->id))->all(),
        ]);
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $status = $request->query('status', 'Original');

        // Print limit enforcement disabled for now.

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $recommendation->file_number,
                'document_type' => 'Land Recommendation',
                'print_type' => 'Individual',
                'status' => $status,
                'user_id' => Auth::id()
            ]);

            // Only increment count for non-CTC prints
            if ($status !== 'CTC') {
                $recommendation->increment('print_count');
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging print: ' . $e->getMessage()
            ], 500);
        }
    }
}
