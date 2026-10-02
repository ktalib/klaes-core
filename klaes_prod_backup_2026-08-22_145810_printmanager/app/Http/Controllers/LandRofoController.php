<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Services\Pra\RofoPraSyncer;
use App\Services\SecurityPaperCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\PrintLog;

class LandRofoController extends Controller
{
    /**
     * SQL predicate for "this RofO counts as printed".
     *
     * rofo_print_count on its own is not enough for OSS. A batch of OSS rows was
     * marked printed by a backfill without ever being issued a Security Serial No.
     * (no security_codes row of document_type 'Land ROFO'), so they sat under the
     * Printed tab while the paper in the applicant's hand carries no serial.
     * Requiring the serial for OSS drops those back into Not Printed, where they
     * can be reprinted through the system and pick up a real serial.
     *
     * Land rows are exempt: print() always mints a serial, so the extra EXISTS
     * would only penalise legacy rows this rule is not aimed at.
     */
    private function printedPredicateSql(): string
    {
        return "(ISNULL(rofo_print_count, 0) > 0
                 AND (UPPER(ISNULL(type, '')) <> 'OSS'
                      OR EXISTS (SELECT 1 FROM security_codes sc
                                 WHERE sc.document_id   = land_recommendations.id
                                   AND sc.document_type = 'Land ROFO')))";
    }

    /**
     * Every RofO in one batch, for the Batches tab's expanded view.
     *
     * Unpaginated by design — a batch shown in slices is exactly the confusion this
     * tab exists to remove.
     */
    public function batchChildren(Request $request, string $batchId)
    {
        $children = LandRecommendation::where('rofo_batch_id', $batchId)
            ->orderBy('batch_seq')
            ->orderBy('id')
            ->get([
                'id', 'batch_seq', 'file_number', 'applicant_name', 'plot_number', 'location',
                'purpose_of_clause', 'land_use', 'status', 'rofo_status', 'land_rofo_serial_no',
                'rofo_print_count', 'rofo_originals_printed_at', 'rofo_office_copies_printed_at',
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
                'purpose'        => $c->purpose_of_clause ?: $c->land_use,
                'status'         => $c->status,
                'rofo_status'    => $c->rofo_status,
                'serial_no'      => $c->land_rofo_serial_no,
                'print_count'    => (int) ($c->rofo_print_count ?? 0),
                // 'none' | 'originals' | 'complete' -- the batch table shows which
                // half of a split print this child is still owed.
                'print_stage'    => $c->rofo_print_stage,
                'print_url'      => route('land-rofos.print', $c->id),
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $ossViewOnly = $request->query('view') === 'only';

        // Show approved recommendations AND OSS-type records (CoN applications ready to print)
        // Select only the columns the view needs — avoids loading large text fields (recommendation, survey_report, etc.)
        $query = LandRecommendation::with('creator')
            ->select([
                'id', 'file_number', 'applicant_name', 'purpose_of_clause', 'location',
                'plot_number', 'layout_plan_no', 'term', 'ground_rent', 'development_period',
                'survey_fees', 'development_value', 'development_charge', 'type',
                'rofo_status', 'status', 'approved_at', 'land_rofo_serial_no',
                'created_at', 'created_by', 'land_use', 'land_use_id', 'purpose_id',
                'is_reissuance', 'reissuance_source',
                'rofo_batch_id', 'batch_mother_file_no', 'batch_seq', 'application_type',
            ]);

        if ($ossViewOnly) {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        } else {
            $query->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            });
        }

        // Tab filter: "printed" vs "not_printed", both driven by printedPredicateSql()
        // — rofo_print_count (incremented only by a real individual/batch print, so it
        // excludes preview-only serials and the bulk-backfilled serials assigned_by
        // 101563 on 2026-05-21) AND, for OSS, an actual Security Serial No. Tabs apply
        // to both the main view and the OSS-only view (each scoped to its own record
        // set, see counts below).
        $tab = $request->query('tab', 'not_printed');
        if (!in_array($tab, ['printed', 'not_printed', 'batches', 'reissuance'], true)) {
            $tab = 'not_printed';
        }
        // The Batches tab pages over batches rather than over RofOs. On the main
        // list a batch is one collapsed row and its children are spread across the
        // pages behind it, so expanding it only ever reveals the handful that share
        // the current page — which reads as the batch being that small.
        if ($tab === 'reissuance') {
            // A re-issued RofO keeps the file number of the letter it replaces, so
            // in the main list it is indistinguishable from a first issue apart from
            // the Source badge. This tab is the only place they can be seen as a set.
            //
            // Deliberately not split by print state and not stripped of batched
            // rows: it is a short, self-selecting list, and a re-issuance is listed
            // here whether or not it has been run off yet. It still appears under
            // Printed / Not Printed as well — it is a letter that has to be printed
            // like any other, and dropping it from those would hide outstanding work.
            $query->where('is_reissuance', 1);
        } elseif ($tab !== 'batches') {
            if ($tab === 'printed') {
                $query->whereRaw($this->printedPredicateSql());
            } else { // not_printed
                $query->whereRaw('NOT ' . $this->printedPredicateSql());
            }

            // Batched RofOs belong to the Batches tab and are kept out of this
            // list — one subdivision could otherwise fill several pages of it.
            // A search is the exception: a specific file number must be findable
            // whether or not it was captured in a batch.
            if (!$request->filled('search')) {
                $query->whereNull('rofo_batch_id');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // One row per batch, whole — see the note on the tab filter above.
        $rofoBatches = null;
        if ($tab === 'batches') {
            $batchQuery = LandRecommendation::query()->whereNotNull('rofo_batch_id');

            if ($ossViewOnly) {
                $batchQuery->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            } else {
                $batchQuery->where(function ($q) {
                    $q->where('status', LandRecommendation::STATUS_APPROVED)
                      ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                // A hit on any child surfaces its whole batch.
                $batchQuery->where(function ($q) use ($search) {
                    $q->where('batch_mother_file_no', 'LIKE', "%{$search}%")
                      ->orWhere('rofo_batch_id', 'LIKE', "%{$search}%")
                      ->orWhere('file_number', 'LIKE', "%{$search}%")
                      ->orWhere('applicant_name', 'LIKE', "%{$search}%");
                });
            }

            $rofoBatches = $batchQuery
                ->groupBy('rofo_batch_id')
                ->selectRaw(
                    'rofo_batch_id'
                    . ', MAX(batch_mother_file_no) AS mother_file_no'
                    . ', MAX(old_file_number) AS old_file_number'
                    . ', MAX(application_type) AS application_type'
                    . ', COUNT(*) AS total'
                    . ", SUM(CASE WHEN rofo_status = 'generated' THEN 1 ELSE 0 END) AS generated_count"
                    . ', SUM(CASE WHEN ISNULL(rofo_print_count, 0) > 0 THEN 1 ELSE 0 END) AS printed_count'
                    . ', MIN(created_at) AS created_at'
                    . ', MAX(created_by) AS created_by'
                )
                ->orderByRaw('MIN(created_at) DESC')
                ->paginate(20)
                ->withQueryString();
        }

        // Subdivision batches share one created_at, so batch_seq is what keeps their
        // children in capture order and adjacent under the grouped batch row.
        $recommendations = $query->latest()
            ->orderBy('rofo_batch_id')
            ->orderBy('batch_seq')
            ->paginate(20)
            ->withQueryString();

        // Full child count per batch, so a batch split across two pages still
        // reports its real size rather than "however many landed on this page".
        $batchIdsOnPage = $recommendations->pluck('rofo_batch_id')->filter()->unique()->values();
        $batchSizes = $batchIdsOnPage->isEmpty() ? collect() : LandRecommendation::query()
            ->whereIn('rofo_batch_id', $batchIdsOnPage)
            ->groupBy('rofo_batch_id')
            ->selectRaw('rofo_batch_id, COUNT(*) AS total')
            ->pluck('total', 'rofo_batch_id');

        // Ids per batch for the grouped row's Print Batch action — the whole batch,
        // not just the rows visible on this page.
        //
        // Only children whose RofO has actually been generated can be printed:
        // batchPrint() filters on that status, so passing a pending child produces a
        // sheet with nothing on it. The row carries the printable ids and the count
        // still waiting on generation so it can say which it is.
        $batchMemberIds = $batchIdsOnPage->isEmpty() ? collect() : LandRecommendation::query()
            ->whereIn('rofo_batch_id', $batchIdsOnPage)
            ->orderBy('batch_seq')
            ->get(['id', 'rofo_batch_id', 'rofo_status'])
            ->groupBy('rofo_batch_id')
            ->map(function ($rows) {
                $generated = $rows->where('rofo_status', LandRecommendation::ROFO_GENERATED);

                return [
                    'printable_ids' => $generated->pluck('id')->values()->all(),
                    'not_generated' => $rows->count() - $generated->count(),
                ];
            });

        $PageTitle = $ossViewOnly ? 'OSS RofO' : 'Land RofO';
        $landUses = \App\Models\LandUse::orderBy('landuse')->get();

        // Single aggregated query for all stats to avoid multiple full-table scans.
        // (printed / not_printed are computed separately below because SQL Server
        // cannot nest an EXISTS subquery inside an aggregate function.)
        $statsRow = DB::connection('sqlsrv')->table('land_recommendations')->selectRaw("
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved'                                          THEN 1 END)   AS total_eligible,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved' AND ISNULL(rofo_status,'') = 'pending'   THEN 1 END)   AS pending_generation,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated'                        THEN 1 END)   AS generated,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS'                                                                THEN 1 END)   AS total_land,
            ISNULL(SUM(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated' THEN ISNULL(rofo_dev_charge,0) ELSE 0 END), 0) AS total_dev_charge
        ")->first();

        // Printed / Not-Printed counts — same printedPredicateSql() the tabs use, so the
        // card totals cannot disagree with the rows listed. Scoped to the same record set
        // the tabs filter, so the OSS-only view counts OSS rows alone rather than the
        // whole RofO register.
        $rofoScopeQuery = DB::connection('sqlsrv')->table('land_recommendations');
        if ($ossViewOnly) {
            $rofoScopeQuery->whereRaw("UPPER(ISNULL(type,'')) = 'OSS'");
        } else {
            $rofoScopeQuery->where(function ($q) {
                $q->whereRaw("status = 'approved' AND UPPER(ISNULL(type,'')) <> 'OSS'")
                  ->orWhereRaw("UPPER(ISNULL(type,'')) = 'OSS'");
            });
        }
        // Same batched-record exclusion the list applies, so a tab badge can never
        // promise rows the tab does not show.
        $tabScope = fn () => $request->filled('search')
            ? (clone $rofoScopeQuery)
            : (clone $rofoScopeQuery)->whereNull('rofo_batch_id');

        $printedCount    = $tabScope()->whereRaw($this->printedPredicateSql())->count();
        $notPrintedCount = $tabScope()->whereRaw('NOT ' . $this->printedPredicateSql())->count();
        // Off $rofoScopeQuery rather than $tabScope(): the Re-issuance tab keeps
        // batched rows, so excluding them here would under-report its badge.
        $reissuanceCount = (clone $rofoScopeQuery)->where('is_reissuance', 1)->count();

        // Count OSS Applications from the authoritative source (oss_applications) so
        // the stat matches the Change of Name page instead of counting type='OSS' rows
        // in land_recommendations which may have duplicates or test records.
        $ossColumns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('oss_applications');
        $ossHasIsDeleted = in_array('is_deleted', array_map('strtolower', $ossColumns));
        $ossBaseQuery = DB::connection('sqlsrv')->table('oss_applications')
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->where(function ($q) use ($ossHasIsDeleted) {
                if ($ossHasIsDeleted) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                }
            });
        $ossTotal = (clone $ossBaseQuery)->count();
        $ossDailyTotal = (clone $ossBaseQuery)->whereDate('created_at', now()->toDateString())->count();

        $stats = [
            'total_eligible'    => (int) ($statsRow->total_eligible    ?? 0),
            'pending_generation'=> (int) ($statsRow->pending_generation ?? 0),
            'generated'         => (int) ($statsRow->generated          ?? 0),
            'printed'           => (int) $printedCount,
            'not_printed'       => (int) $notPrintedCount,
            'reissuance'        => (int) $reissuanceCount,
            'total_land'        => (int) ($statsRow->total_land         ?? 0),
            'total_dev_charge'  => (float) ($statsRow->total_dev_charge ?? 0),
            'oss_total'         => $ossTotal,
            'oss_daily'         => $ossDailyTotal,
        ];

        // Only fetch the paper_code column — the view only ever reads s.paper_code
        $availableSerials = DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->select('paper_code')
            ->where('is_used', false)
            ->orderBy('paper_code', 'asc')
            ->get();

        // Batch-load the auto-generated security codes for the rows on this page so
        // the table can show the same serial-no fraction as the printed certificate,
        // without generating a new code for every listed record (side-effect free).
        $rofoSerials = [];
        $recIds = $recommendations->getCollection()->pluck('id')->all();
        if (!empty($recIds)) {
            $codeService = app(\App\Services\SecurityCodeService::class);
            $codes = DB::connection('sqlsrv')->table('security_codes')
                ->whereIn('document_id', $recIds)
                ->where('document_type', 'Land ROFO')
                ->where('is_used', 0)
                ->orderBy('id')
                ->get(['document_id', 'code']);
            foreach ($codes as $c) {
                // getOrGenerateForDocument returns the first unused code; mirror that
                // by keeping the earliest per document.
                if (!isset($rofoSerials[$c->document_id])) {
                    $rofoSerials[$c->document_id] = $codeService->formatForDisplay($c->code);
                }
            }
        }

        // Batch-load the "Print Date" per record (keyed by record id) — ONLY for records
        // that count as printed under printedPredicateSql(), so a backfilled serial never
        // shows a date on a not-printed row, and a serial-less OSS row reads "Not printed"
        // to match the tab it now sits in. The date is the serial's date, with an actual
        // print_log taking precedence. No N+1.
        $printDates = [];
        $printedIds = empty($recIds) ? [] : DB::connection('sqlsrv')->table('land_recommendations')
            ->whereIn('id', $recIds)
            ->whereRaw($this->printedPredicateSql())
            ->pluck('id')->all();
        if (!empty($printedIds)) {
            // Serial date per printed record id.
            $serialRows = DB::connection('sqlsrv')->table('security_codes')
                ->where('document_type', 'Land ROFO')
                ->whereIn('document_id', $printedIds)
                ->selectRaw('document_id, MIN(created_at) AS serial_date')
                ->groupBy('document_id')
                ->get();
            foreach ($serialRows as $r) {
                $printDates[$r->document_id] = $r->serial_date;
            }

            // print_logs override, matched by file number, mapped back to record id.
            $idByFile = [];
            foreach ($recommendations->getCollection() as $rec) {
                if (in_array($rec->id, $printedIds, true)) {
                    $idByFile[strtoupper(trim((string) $rec->file_number))] = $rec->id;
                }
            }
            $fileNumbers = array_keys($idByFile);
            if (!empty($fileNumbers)) {
                $logRows = DB::connection('sqlsrv')->table('print_logs')
                    ->where('document_type', 'Land ROFO')
                    ->whereRaw('UPPER(LTRIM(RTRIM(reference_number))) IN (' . implode(',', array_fill(0, count($fileNumbers), '?')) . ')', $fileNumbers)
                    ->selectRaw('UPPER(LTRIM(RTRIM(reference_number))) AS fn, MAX(created_at) AS last_printed')
                    ->groupByRaw('UPPER(LTRIM(RTRIM(reference_number)))')
                    ->get();
                foreach ($logRows as $r) {
                    if (isset($idByFile[$r->fn])) {
                        $printDates[$idByFile[$r->fn]] = $r->last_printed;
                    }
                }
            }
        }

        // Batch count for the tab badge, on the same record set the tabs filter.
        $rofoBatchCount = (clone $rofoScopeQuery)->whereNotNull('rofo_batch_id')
            ->distinct()->count('rofo_batch_id');

        $rofoBatchCreators = $rofoBatches
            ? \App\Models\User::whereIn('id', collect($rofoBatches->items())->pluck('created_by')->filter()->unique())
                ->get(['id', 'first_name', 'last_name'])->keyBy('id')
            : collect();

        return view('land_rofos.index', compact('recommendations', 'PageTitle', 'landUses', 'stats', 'availableSerials', 'ossViewOnly', 'rofoSerials', 'tab', 'printDates', 'batchSizes', 'batchMemberIds', 'rofoBatches', 'rofoBatchCount', 'rofoBatchCreators'));
    }

    /**
     * Column metadata shared by the JSON preview, the client-side CSV/PDF and the
     * streamed server CSV, so all three stay in step.
     * `pdfWidth` is in mm — A4 landscape gives ~277mm of usable width.
     */
    private function exportColumns(): array
    {
        return [
            ['key' => 'sn',             'label' => 'S/N',               'pdfWidth' => 9,  'wrap' => false],
            ['key' => 'file_number',    'label' => 'File Number',       'pdfWidth' => 26, 'wrap' => false],
            ['key' => 'source',         'label' => 'Source',            'pdfWidth' => 11],
            ['key' => 'applicant_name', 'label' => 'Applicant Name',    'pdfWidth' => 34],
            ['key' => 'purpose',        'label' => 'Land Use / Purpose','pdfWidth' => 22],
            // No pdfWidth: Location is the flexible column and absorbs the
            // remaining page width (see buildColumnStyles in records_export.js).
            ['key' => 'location',       'label' => 'Location'],
            ['key' => 'plot_number',    'label' => 'Plot No',           'pdfWidth' => 12],
            ['key' => 'layout_plan_no', 'label' => 'Layout Plan',       'pdfWidth' => 14],
            ['key' => 'term',           'label' => 'Term',              'pdfWidth' => 10],
            ['key' => 'ground_rent',    'label' => 'Ground Rent',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_period',     'label' => 'Dev. Period',       'pdfWidth' => 12],
            ['key' => 'survey_fees',    'label' => 'Survey Fees',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_value',      'label' => 'Dev. Value',        'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_charge',     'label' => 'Dev. Charge',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'status',         'label' => 'Status',            'pdfWidth' => 14],
            ['key' => 'approved_on',    'label' => 'Approved On',       'pdfWidth' => 18],
            ['key' => 'created_by',     'label' => 'Created By',        'pdfWidth' => 18],
            ['key' => 'paper_code',     'label' => 'Security Paper Code','pdfWidth' => 18],
            ['key' => 'date_generated', 'label' => 'Date Generated',    'pdfWidth' => 18],
        ];
    }

    /**
     * Build the filtered export query. Mirrors index() plus the export-only
     * status and created_at date-range filters.
     */
    private function buildExportQuery(Request $request, bool $ossViewOnly)
    {
        $query = LandRecommendation::with('creator')
            ->select([
                'id', 'file_number', 'applicant_name', 'purpose_of_clause', 'location',
                'plot_number', 'house_no', 'layout_plan_no', 'term', 'ground_rent', 'development_period',
                'survey_fees', 'development_value', 'development_charge', 'type',
                'rofo_status', 'status', 'approved_at', 'land_rofo_serial_no',
                'created_at', 'created_by', 'land_use', 'land_use_id', 'purpose_id',
            ]);

        if ($ossViewOnly) {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        } else {
            $query->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
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

        // Status here means RofO generation state, matching the on-screen badge.
        $status = strtolower(trim((string) $request->query('status', '')));
        if ($status === 'generated') {
            $query->whereRaw("ISNULL(rofo_status, '') = ?", [LandRecommendation::ROFO_GENERATED])
                  ->whereRaw("UPPER(ISNULL(type, '')) <> 'OSS'");
        } elseif ($status === 'pending') {
            $query->whereRaw("ISNULL(rofo_status, '') <> ?", [LandRecommendation::ROFO_GENERATED])
                  ->whereRaw("UPPER(ISNULL(type, '')) <> 'OSS'");
        } elseif ($status === 'oss') {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * Flatten one record into the export column keys.
     */
    private function exportRow(LandRecommendation $rec, int $sn): array
    {
        $isOss = strtoupper($rec->type ?? '') === 'OSS';

        return [
            'sn'             => $sn,
            'file_number'    => $rec->file_number,
            'source'         => $isOss ? 'OSS' : 'Land',
            'applicant_name' => $rec->applicant_name,
            'purpose'        => $rec->purpose_of_clause,
            'location'       => $rec->display_location,
            'plot_number'    => $rec->plot_number,
            'layout_plan_no' => $rec->layout_plan_no,
            'term'           => $rec->term,
            'ground_rent'    => number_format((float) $rec->ground_rent, 2),
            'dev_period'     => $rec->development_period,
            'survey_fees'    => number_format((float) $rec->survey_fees, 2),
            'dev_value'      => number_format((float) $rec->development_value, 2),
            'dev_charge'     => number_format((float) $rec->development_charge, 2),
            'status'         => $isOss
                ? 'PRINT READY'
                : ($rec->rofo_status === LandRecommendation::ROFO_GENERATED ? 'APPROVED' : 'PENDING'),
            'approved_on'    => $isOss ? '' : ($rec->approved_at ? $rec->approved_at->format('Y-m-d h:i A') : 'N/A'),
            'created_by'     => $rec->creator->name ?? 'System',
            'paper_code'     => $rec->land_rofo_serial_no ?: 'Unassigned',
            'date_generated' => $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A',
        ];
    }

    public function export(Request $request)
    {
        $ossViewOnly = $request->query('view') === 'only';
        $columns = $this->exportColumns();
        $query = $this->buildExportQuery($request, $ossViewOnly);

        // JSON feed for the export preview modal (client-side CSV + PDF).
        if ($request->query('format') === 'json') {
            $rows = [];
            $sn = 0;
            $query->chunk(500, function ($chunk) use (&$rows, &$sn) {
                foreach ($chunk as $rec) {
                    $rows[] = $this->exportRow($rec, ++$sn);
                }
            });

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'data'    => $rows,
                'count'   => count($rows),
            ]);
        }

        $filename = ($ossViewOnly ? 'oss-rofo' : 'land-rofo') . '-export-' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        $callback = function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders the ₦ sign and other characters correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));

            $sn = 0;
            $query->chunk(500, function ($rows) use ($out, $columns, &$sn) {
                foreach ($rows as $rec) {
                    $row = $this->exportRow($rec, ++$sn);
                    fputcsv($out, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $recommendation = LandRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Check if paper code is already used
            $serial = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->first();

            if (($serial->status ?? null) === 'voided') {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'That security paper was voided (' . SecurityPaperCodeService::label($serial->void_reason ?? null) . ') and cannot be reissued.'], 422);
            }

            if ($serial->is_used) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            // If recommendation already has a paper code, mark the old one as unused
            if ($recommendation->land_rofo_serial_no) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $recommendation->land_rofo_serial_no)
                    ->update([
                        'is_used' => false,
                        'assigned_to_type' => null,
                        'assigned_to_id' => null,
                        'assigned_by' => null,
                        'assigned_at' => null,
                    ]);
            }

            // Assign new paper code
            $recommendation->update(['land_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update([
                    'is_used' => true,
                    'assigned_to_type' => 'LandRecommendation',
                    'assigned_to_id' => $recommendation->id,
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);

            // Also update security_codes table for tracking/linking
            DB::connection('sqlsrv')->table('security_codes')->insert([
                'code' => 'L-' . $request->paper_code, // Use L- prefix for Land
                'security_paper_code' => $request->paper_code,
                'used_security_paper_code' => $request->paper_code,
                'is_used' => true,
                'assigned_to' => 'Land ROFO',
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'file_number' => $recommendation->file_number,
                'document_id' => $recommendation->id,
                'document_type' => 'Land ROFO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to assign security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function resetSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(SecurityPaperCodeService::REASONS))],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $recommendation = LandRecommendation::findOrFail($id);

        if (!$recommendation->land_rofo_serial_no) {
            return response()->json(['success' => false, 'message' => 'No security paper code assigned to reset.'], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $oldCode = $recommendation->land_rofo_serial_no;

            SecurityPaperCodeService::release($oldCode, $request->reason, 'Land ROFO', $request->note);

            // Clear the paper code on the recommendation
            $recommendation->update(['land_rofo_serial_no' => null]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'         => true,
                'returned_to_pool' => SecurityPaperCodeService::returnsToPool($request->reason),
                'paper_code'      => $oldCode,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to reset security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function generate(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        
        if ($recommendation->status !== LandRecommendation::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation must be approved before generating ROFO.'
            ], 403);
        }

        $validated = $request->validate([
            'rofo_survey_fees' => 'nullable|numeric',
            'rofo_dev_charge' => 'nullable|numeric',
            'rofo_director_survey' => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor' => 'nullable|string|in:YES,NO',
            'rofo_land_use_category' => 'nullable|string',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'land_use_id' => 'nullable|exists:sqlsrv.land_uses,id',
            'purpose_id' => 'nullable|exists:sqlsrv.purposes,id',
        ]);

        if ($request->filled('land_use_id')) {
            $lu = \App\Models\LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            $p = \App\Models\Purpose::find($request->purpose_id);
            if ($p) $validated['purpose_of_clause'] = $p->name;
        }

        // Anything the form left empty falls back to the stored record — that is what
        // a quick-generate from the index does. Shared with the automatic generation
        // on batch approval so both produce identically-shaped records.
        app(\App\Services\LandRofoGenerator::class)->generate($recommendation, $validated);

        // LAAS Portal (spec j): shown on the applicant's timeline, but not
        // texted — the message they are waiting for is the signed RofO, which
        // follows from logPrint() below.
        if (!empty($recommendation->file_number)) {
            app(\App\Services\Laas\LaasWorkflowService::class)->advanceByFileNumber(
                $recommendation->file_number,
                \App\Models\Laas\LaasApplication::STAGE_ROFO_GENERATED,
                [
                    'title'   => 'RofO generated',
                    'body'    => 'Your Right of Occupancy has been generated and is awaiting signature.',
                    'columns' => ['rofo_id' => $recommendation->id],
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * DATE OF ISSUE on the letter is the application date, not the date the RofO
     * record happened to be generated — a letter is issued as of the day the
     * application is dated, and back-dated runs must print that day, not today.
     *
     * Older rows were printed before the field was collected and hold nothing, so
     * the print dialog asks for it and sends it here with the print itself. It is
     * written to the record rather than used for the one printout: a reprint has to
     * come out carrying the same date as the copy already in the file.
     *
     * A date already on a record is what an issued letter out in the world carries,
     * so it is not replaced casually:
     *
     *   issue_date_apply=missing (default) — fills only rows that have none. What a
     *                                        bulk run sends, so one answer for many
     *                                        files can never overwrite a dated one.
     *   issue_date_apply=all              — the operator unlocked the field on one
     *                                        record and confirmed the edit.
     */
    private function applyIssueDate(Request $request, $records): void
    {
        $raw = trim((string) $request->input('issue_date', ''));
        if ($raw === '') {
            return;
        }

        try {
            $date = \Carbon\Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            // An unparseable date is not worth failing a print over — the letter
            // prints with whatever the record already holds.
            return;
        }

        $overwrite = $request->input('issue_date_apply') === 'all';

        foreach ($records as $rec) {
            if (!$overwrite && filled($rec->application_date)) {
                continue;
            }

            $rec->application_date = $date;
            $rec->updated_by = Auth::id();
            $rec->save();
        }
    }

    /**
     * What the print dialog needs to decide whether to ask: the application date
     * each selected RofO already holds, if any.
     */
    public function issueDates(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        $records = LandRecommendation::whereIn('id', $ids)
            ->get(['id', 'file_number', 'application_date'])
            ->map(function ($r) {
                return [
                    'id'               => (int) $r->id,
                    'file_number'      => (string) $r->file_number,
                    'application_date' => optional($r->application_date)->format('Y-m-d'),
                ];
            });

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * Store the application date on its own, for the print routes that navigate
     * away instead of posting the print form (the Print Manager).
     */
    public function saveIssueDate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
            'issue_date' => 'required|date',
        ]);

        $records = LandRecommendation::whereIn('id', $request->input('ids'))->get();
        $this->applyIssueDate($request, $records);

        return response()->json(['success' => true, 'count' => $records->count()]);
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Keyed in on the print dialog: see applyIssueDate().
        $this->applyIssueDate($request, [$recommendation]);

        // Resolve land_use text from land_use_id if the text column is empty
        if (empty($recommendation->land_use) && $recommendation->land_use_id) {
            $lu = \App\Models\LandUse::find($recommendation->land_use_id);
            if ($lu) $recommendation->land_use = $lu->landuse;
        }

        // Resolve purpose_of_clause text from purpose_id if the text column is empty
        if (empty($recommendation->purpose_of_clause) && $recommendation->purpose_id) {
            $p = \App\Models\Purpose::find($recommendation->purpose_id);
            if ($p) $recommendation->purpose_of_clause = $p->name;
        }

        // Bypass limit check for Certified True Copy
        $isCTC = $request->query('status') === 'CTC' || $request->query('isCTC') == 1;
        // Generate security code for this print
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCode = $securityCodeService->getOrGenerateForDocument(
            $recommendation->file_number,
            $recommendation->id,
            'Land ROFO'
        );

        // ?supersede=1 switches the single RofO template into re-issuance mode: the
        // same letter plus the "supersedes the previous one issued on ..." notice,
        // a RE-ISSUANCE watermark, and the Original copy only.
        // ?superseded_date=... fills that notice; omitted, the template falls back
        // to the record's own issue date.
        $supersedeView   = $request->boolean('supersede');
        $supersededDate  = trim((string) $request->query('superseded_date', ''));

        // The re-issuance dialog sends an ISO date (Y-m-d); the letter reads
        // "issued on 31st July, 2026". Anything unparseable prints verbatim.
        if ($supersededDate !== '') {
            try {
                $supersededDate = \Carbon\Carbon::parse($supersededDate)->format('jS F, Y');
            } catch (\Throwable $e) {
                // keep the raw value
            }
        }

        // The two-print cap that used to 403 here has been removed on request:
        // rofo_print_count is still incremented and still drives the Printed tab
        // and the print history, but it no longer blocks a print.

        // One template for both: it reads ?supersede=1 itself to switch modes.
        return view(
            'land_rofos.templates.rofo_print',
            compact('recommendation', 'securityCode', 'supersededDate')
        );
    }

    /**
     * Select2 feed for the Re-issuance dialog (KLAES-generated option): the file
     * numbers that appear on the RofO table — approved land records plus OSS.
     */
    public function reissuanceSearch(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $query = LandRecommendation::select([
                'id', 'file_number', 'applicant_name', 'location', 'rofo_generated_at', 'created_at',
            ])
            ->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            });

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('file_number', 'LIKE', "%{$term}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$term}%");
            });
        }

        $results = $query->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($rec) => [
                'id'        => $rec->id,
                'text'      => $rec->file_number,
                'applicant' => $rec->applicant_name,
                'location'  => $rec->location,
                // Only rofo_generated_at is a real issue date. created_at is when the
                // recommendation was captured, so falling back to it made a RofO that
                // was never generated look as though it were issued that day.
                'issued_on' => optional($rec->rofo_generated_at)->format('Y-m-d'),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Re-issue a RofO that was generated in KLAES. The recommendation and the RofO
     * are already captured, so this only stamps the re-issuance fields on the
     * existing record — nothing is re-entered and no new record is created.
     */
    public function reissue(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $recommendation->is_reissuance     = true;
        $recommendation->reissuance_source = 'klaes';
        $recommendation->updated_by        = Auth::id();
        $recommendation->save();

        return response()->json([
            'success' => true,
            'message' => $recommendation->file_number . ' is now marked as a re-issued RofO.',
        ]);
    }

    public function unprintedJson()
    {
        // File numbers already batch-printed
        $printed = DB::connection('sqlsrv')->table('print_logs')
            ->where('document_type', 'Land ROFO')
            ->where('print_type', 'LandRofoBatch')
            ->pluck('reference_number')
            ->map(fn($r) => strtoupper(trim((string) $r)))
            ->unique()
            ->all();

        $records = LandRecommendation::select([
                'id', 'file_number', 'applicant_name', 'location', 'plot_number',
                'land_rofo_serial_no', 'rofo_status', 'rofo_print_count',
                'rofo_originals_printed_at', 'rofo_office_copies_printed_at',
            ])
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get()
            // A file whose Originals went out in run 1 of a split print has a
            // LandRofoBatch log row, so the printed test drops it — while its
            // Duplicate and Triplicate are still owed. Those belong in this queue
            // more than anything else does: the paper for them has not been used
            // yet, and the modal flags them so they are not mistaken for untouched
            // files.
            ->filter(fn($r) => !in_array(strtoupper(trim((string) $r->file_number)), $printed)
                || $r->rofo_print_stage === LandRecommendation::PRINT_STAGE_ORIGINALS)
            ->values()
            ->map(function ($r) {
                $r->setAttribute('print_stage', $r->rofo_print_stage);
                return $r;
            });

        return response()->json(['success' => true, 'data' => $records, 'count' => $records->count()]);
    }

    public function batchPrint(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        // Only generated RofOs are printable, so a selection of pending ones would
        // otherwise render the template shell with no letters in it — a blank page.
        if ($records->isEmpty()) {
            $requested = LandRecommendation::whereIn('id', $ids)->pluck('file_number');

            abort(422, $requested->isEmpty()
                ? 'No records were selected for printing.'
                : 'None of these have a generated RofO yet, so there is nothing to print: '
                    . $requested->implode(', ') . '. Generate the RofO first.');
        }

        // One date keyed in on the print dialog, filling whichever of these rows
        // have no application date yet: see applyIssueDate().
        $this->applyIssueDate($request, $records);

        // Use the same service the individual print uses so codes are consistent
        $securityCodeService = app(\App\Services\SecurityCodeService::class);

        // The letter itself comes from rofo_print — the same template a single print
        // uses. The old batch-specific copy of the letter had drifted from it (wrong
        // frame, missing the ministry address line), which is exactly the failure mode
        // a duplicated template invites.
        //
        // status=Batch makes each record emit its Original/Duplicate/Triplicate set,
        // matching what batchPrintLog() records.
        $request->merge(['status' => 'Batch']);

        // Which copies this run puts on paper:
        //   'all'      (default) — every file's Original, Duplicate and Triplicate in
        //                          one go. What this has always done.
        //   'original'           — the Originals alone.
        //   'office'             — the Duplicate and Triplicate alone, paired per file.
        //
        // The split exists because the two bundles are not printed on the same thing:
        // the Original goes on the colour security stock, the office copies go on
        // plain paper black & white. That is two passes through the printer with the
        // paper changed in between, so it cannot be one continuous job — the caller
        // runs 'original', the operator reloads the tray, then it runs 'office'.
        $copies = in_array($request->input('copies'), ['original', 'office'], true)
            ? $request->input('copies')
            : 'all';

        $versionsFor = [
            'all'      => null,                             // null = the template's own full set
            'original' => ['Original'],
            'office'   => ['Duplicate', 'Triplicate'],
        ][$copies];

        $records->each(function ($rec) {
            // Mirror the single print: fall back to the ids when the text columns are
            // empty, or the letter prints a blank purpose / land use.
            if (empty($rec->land_use) && $rec->land_use_id) {
                $lu = \App\Models\LandUse::find($rec->land_use_id);
                if ($lu) $rec->land_use = $lu->landuse;
            }
            if (empty($rec->purpose_of_clause) && $rec->purpose_id) {
                $p = \App\Models\Purpose::find($rec->purpose_id);
                if ($p) $rec->purpose_of_clause = $p->name;
            }
        });

        $letterFor = function ($rec, ?array $onlyVersions = null) use ($securityCodeService) {
            return view('land_rofos.templates.rofo_print', [
                'recommendation' => $rec,
                'securityCode'   => $securityCodeService->getOrGenerateForDocument(
                    $rec->file_number,
                    $rec->id,
                    'Land ROFO'
                ),
                'supersededDate' => '',
                // Null lets the template emit the whole Original/Duplicate/Triplicate
                // set for this record — the 'all' run.
                'printVersionsOnly' => $onlyVersions,
            ]);
        };

        $views = $records->map(fn ($rec) => $letterFor($rec, $versionsFor));

        $stitched = app(\App\Services\StitchedBatchPrint::class)->stitch($views);

        // Says which run this is, on the bar above the letters — with two tabs open
        // at once, the operator has to be able to tell them apart at a glance, and
        // which paper is in the tray depends on getting that right.
        $runLabel = [
            'all'      => ' — Original, Duplicate and Triplicate of each',
            'original' => ' — ORIGINALS ONLY (run 1 of 2) · colour / security paper',
            'office'   => ' — DUPLICATE & TRIPLICATE ONLY (run 2 of 2) · plain paper, black & white',
        ][$copies];

        return view('print.stitched_batch', [
            'head'     => $stitched['head'],
            'bodies'   => $stitched['bodies'],
            'title'    => 'Batch RofO Print (' . $records->count() . ' records)'
                . ($copies === 'all' ? '' : ' — ' . ucfirst($copies)),
            'subtitle' => $records->count() . ' ' . \Illuminate\Support\Str::plural('RofO', $records->count())
                . $runLabel,
            // Empty on purpose: the RofO list records the batch through
            // batch-print-log before this page is opened, so logging here again
            // would double-count every print.
            'logUrls'  => [],
            // rofo_print breaks between its own pages with
            // `.page-container ~ .page-container { page-break-before: always }`.
            // That is a general-sibling rule, so once the records are stitched it
            // already starts each one on a fresh sheet — adding a break marker on
            // top of it emitted a blank page at every join.
            'breakBetween' => false,
        ]);
    }

    /**
     * Where a batch stands between the two runs of a split print.
     *
     * A split print is Originals on security paper, then Duplicate + Triplicate on
     * plain paper, with the tray reloaded in between. The gap is where a run gets
     * abandoned, so the dialog asks this first: if the Originals are already on
     * paper it offers to resume from the office copies instead of starting the
     * whole batch again.
     */
    public function batchPrintStatus(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records specified.'], 422);
        }

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get(['id', 'file_number', 'rofo_print_count',
                   'rofo_originals_printed_at', 'rofo_office_copies_printed_at', 'rofo_print_run_mode']);

        $byStage = $records->groupBy('rofo_print_stage');

        $awaitingOffice = $byStage->get(LandRecommendation::PRINT_STAGE_ORIGINALS, collect());

        return response()->json([
            'success'         => true,
            'total'           => $records->count(),
            'not_started'     => $byStage->get(LandRecommendation::PRINT_STAGE_NONE, collect())->count(),
            'awaiting_office' => $awaitingOffice->count(),
            'complete'        => $byStage->get(LandRecommendation::PRINT_STAGE_COMPLETE, collect())->count(),
            // The ids run 2 still owes, so resuming prints those and nothing else --
            // a file whose office copies are already out must not come round again.
            'resume_ids'      => $awaitingOffice->pluck('id')->values()->all(),
            'originals_at'    => optional($awaitingOffice->max('rofo_originals_printed_at'))->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Records a batch print run.
     *
     * copies says which half of the paper this call is for, and mirrors the same
     * parameter on batchPrint():
     *   'all'      -- the single pass: Original, Duplicate and Triplicate together.
     *   'original' -- run 1 of a split print.
     *   'office'   -- run 2 of a split print.
     * Absent reads as 'all', so an older caller records exactly what it always did.
     *
     * rofo_print_count counts prints of the batch, not passes through the printer:
     * the two runs of a split print are one print of the same letters, so only the
     * run that first puts paper in the tray increments it.
     */
    public function batchPrintLog(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records specified.'], 422);
        }

        $copies = in_array($request->input('copies'), ['original', 'office'], true)
            ? $request->input('copies')
            : 'all';

        $copyVersions = [
            'all'      => ['Original', 'Duplicate', 'Triplicate'],
            'original' => ['Original'],
            'office'   => ['Duplicate', 'Triplicate'],
        ][$copies];

        // A re-issuance reprints a letter that was already issued. Like a CTC it
        // sits outside the print allowance: it is recorded, but it does not count
        // against rofo_print_count and does not touch the run stamps — those track
        // where the file's own issue stands, which a re-issuance does not change.
        // Its own print_type also keeps it out of the "already batch-printed" test
        // in unprintedJson(), so re-issuing does not empty a file out of the queue.
        $isReissuance = filled($request->input('reissuance'));

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        $now = now();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            foreach ($records as $rec) {
                foreach ($copyVersions as $copy) {
                    PrintLog::create([
                        'reference_number' => $rec->file_number,
                        'document_type'    => 'Land ROFO',
                        'print_type'       => $isReissuance ? 'LandRofoReissuance' : 'LandRofoBatch',
                        'status'           => $copy,
                        'user_id'          => Auth::id(),
                    ]);
                }

                if ($isReissuance) {
                    continue;
                }

                // Whether this run is the one that starts the batch off, which is
                // what rofo_print_count measures. An 'office' run resuming an
                // abandoned split was already counted by its 'original' run.
                $startsTheBatch = $copies !== 'office' || !$rec->rofo_originals_printed_at;

                $stamps = ['rofo_print_run_mode' => $copies === 'all' ? 'all' : 'split'];

                if ($copies === 'all') {
                    $stamps['rofo_originals_printed_at'] = $now;
                    $stamps['rofo_office_copies_printed_at'] = $now;
                } elseif ($copies === 'original') {
                    $stamps['rofo_originals_printed_at'] = $now;
                    // Reprinting the Originals reopens the run: the office copies of
                    // this new set are outstanding again.
                    $stamps['rofo_office_copies_printed_at'] = null;
                } else {
                    $stamps['rofo_office_copies_printed_at'] = $now;
                    // Office copies with no Originals run behind them (an operator
                    // resuming a batch printed before these columns existed) still
                    // close the run.
                    $stamps['rofo_originals_printed_at'] = $rec->rofo_originals_printed_at ?: $now;
                }

                $rec->forceFill($stamps)->save();

                if ($startsTheBatch) {
                    $rec->increment('rofo_print_count');
                }
            }
            DB::connection('sqlsrv')->commit();
            return response()->json([
                'success' => true,
                'count'   => $records->count(),
                'copies'  => $copies,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $status = $request->query('status', 'Original');
        $isCTC = $status === 'CTC' || $request->query('isCTC') == 1;

        // A re-issuance replaces a letter that was already issued, so like a CTC it
        // sits outside the two-print allowance and does not consume it.
        $isReissuance = $status === 'Re-issuance';

        // The two-print cap was removed here as well, to match print(): logging a
        // third print must not fail after the letter has already been rendered.
        // $isCTC / $isReissuance still matter below — they decide whether the print
        // counts against rofo_print_count, which the Printed tab reads.

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $recommendation->file_number,
                'document_type' => 'Land ROFO',
                'print_type' => 'Individual',
                'status' => $status,
                'user_id' => Auth::id()
            ]);

            // Only increment count for non-CTC, non-re-issuance prints
            if (!$isCTC && !$isReissuance) {
                $recommendation->increment('rofo_print_count');
            }

            DB::commit();

            // LAAS Portal (spec k): the applicant is told their RofO is signed
            // and ready to collect. There is no separate "Director signed" action
            // in the system — issuing the printed letter IS the signing step, so
            // a counted Original print is what stands for it. A CTC or a
            // re-issuance reprints an already-issued letter and must not fire it.
            if (!$isCTC && !$isReissuance && !empty($recommendation->file_number)) {
                app(\App\Services\Laas\LaasWorkflowService::class)->advanceByFileNumber(
                    $recommendation->file_number,
                    \App\Models\Laas\LaasApplication::STAGE_ROFO_SIGNED,
                    [
                        'title' => 'RofO signed',
                        'body'  => 'Your Right of Occupancy has been signed by the Director of Lands and is ready for collection.',
                    ]
                );
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging ROFO print: ' . $e->getMessage()
            ], 500);
        }
    }
}
