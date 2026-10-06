<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\LandRecommendation;
use App\Models\LandRecommendationBatchDocument;
use App\Models\LandRecommendationDocument;
use App\Models\LandRecommendationBatchDraft;
use App\Models\LandUse;
use App\Models\Purpose;
use App\Rules\NigerianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Models\PrintLog;
use App\Support\LandRecommendationLog as RecLog;
use App\Support\OssRecommendationLocation;
use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Services\RofoRecommendationPurgeService;
use Barryvdh\DomPDF\Facade\Pdf;

class LandRecommendationController extends Controller
{
    use ExecutesMasterDelete;

    /**
     * Resolve the ground rent unit down to the words that will print.
     *
     * The form offers a fixed list plus "Other", and posts the free text in its own
     * field so the batch stepper can carry both per file. Only the resolved value
     * is ever stored: nothing downstream should have to know, or re-derive, which
     * of the fixed choices a unit came from.
     *
     * "Other" with nothing typed is no unit at all, not the literal word "Other".
     *
     * @param  array $data  A request payload, or one child of a batch.
     */
    private function resolveGroundRentUnit(array $data): array
    {
        return \App\Support\GroundRentUnit::resolve($data);
    }

    /**
     * An indexed plot size wins over whatever was posted for area_sqm.
     *
     * The form locks the field when the file's indexing carries a size, but a
     * disabled input is only a hint - the size is re-read from file_indexings on
     * save so a recommendation can never disagree with the file it was keyed from.
     * Files indexed without a size keep what the officer typed.
     *
     * @param  array $data  A request payload, or one child / row of a batch.
     */
    /**
     * The indexed plot size (m²) for a file number, for the form to prefill and
     * lock. plot_size is null when the file has no usable indexed size.
     */
    public function plotSize(Request $request)
    {
        return response()->json([
            'plot_size' => \App\Support\PlotSize::fromIndexing($request->input('file_number')),
        ]);
    }

    /**
     * Plot size (m²) is required on single capture and edit — the ground rent
     * amount is rate x plot size, so a record without one cannot be billed. A file
     * whose indexing carries a size is exempt: resolveIndexedPlotSize() fills it
     * from the indexing on save whatever was posted.
     */
    private function plotSizeRule(?string $fileNumber): string
    {
        return \App\Support\PlotSize::fromIndexing($fileNumber) !== null
            ? 'nullable|numeric|min:0'
            : 'required|numeric|gt:0';
    }

    private function resolveIndexedPlotSize(array $data, ?string $fileNumber): array
    {
        $indexed = \App\Support\PlotSize::fromIndexing($fileNumber);

        if ($indexed !== null) {
            $data['area_sqm'] = $indexed;
        }

        return $data;
    }

    /**
     * Grant-condition columns a regular batch may set per file rather than once
     * for the whole batch. Kept in step with GRANT_FIELDS in the form's Grant
     * Conditions stepper — a column added to one and not the other is a value the
     * officer keys per file and the batch then saves from the common set.
     */
    private const PER_CHILD_GRANT_FIELDS = [
        'term', 'cofo_year', 'selected_year', 'ground_rent', 'ground_rent_unit',
        // Plot size (m²): the quantity the ground rent rate is charged on.
        'area_sqm',
        'development_period',
        'development_value', 'development_charge', 'survey_fees',
        'preparation_fees', 'preparation_fees_words',
        // A hand-picked batch spans layouts, so TP No. is captured per file too.
        'layout_plan_no',
        // Page Number details, stepped per file on the same card. APN (page) and
        // PPPN (page_2) are excluded on purpose: those come off the batch table's
        // own columns and are written from $child directly, further down.
        'page_survey_report', 'survey_report', 'physical_planning_comment',
        'improvement', 'revision_period', 'time_of_erection',
        // Recommendation & Reasons. The reasons differ file by file in a hand-picked
        // batch — one set of reasons across ten unrelated plots is the exception,
        // not the rule — so the card steps like the others.
        'recommendation',
    ];

    /**
     * Under Use Subdivision Template the Old File Number field IS the Mother File
     * Number, and the default message would name the column the officer is no
     * longer looking at. Shared by store() and update() so the refusal reads the
     * same wherever the record is saved from.
     */
    private const SUBDIVISION_TEMPLATE_MESSAGES = [
        'old_file_number.required_if' =>
            'Enter the Mother File Number, or set Use Subdivision Template back to No.',
    ];

    public function index(Request $request)
    {
        $viewType = strtoupper(trim((string) $request->query('type', '')));
        if (!in_array($viewType, ['OSS', 'ROFO'], true)) {
            return redirect()->route('home');
        }

        // ── Tabs ────────────────────────────────────────────────────────────
        // OSS is a tab on the Land Recommendation page as well as a page of its
        // own (the menu still links straight to ?type=OSS). On the tab the list,
        // the row actions and the export are all the OSS ones — only the tab strip
        // and the header counters stay with the page you are on, which is why
        // $pageType is tracked separately from $isOssView below.
        $tab = $request->query('tab', 'not_printed');
        // 'extant' lists the files whose recommendation is a document we hold
        // rather than one KLAES printed -- see extantRecommendation(). It is a Land
        // notion: an OSS record never carries the subdivision template.
        $allowedTabs = $viewType === 'OSS'
            ? ['printed', 'not_printed', 'batches']
            : ['printed', 'not_printed', 'batches', 'oss', 'extant'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'not_printed';
        }

        $pageType = $viewType;
        $ossTab   = $tab === 'oss';

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
        // The OSS tab lists OSS records, so everything below that reads "is this
        // the OSS list" is true there too. The page identity is $pageType.
        $isOssView = $viewType === 'OSS' || $ossTab;

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
        // Built per scope rather than once: the header counters on the Land page
        // must keep counting Land prints even while the OSS tab is the one on
        // screen, and the two scopes log under different document types.
        $printedExistsFor = function (bool $oss) {
            $printedDocTypes = $oss
                ? ['Land Recommendation', 'OSS Recommendation For Grant']
                : ['Land Recommendation', 'Recommendation For Grant'];

            $securityCodeDocTypes = $oss
                ? ['OSS Recomm']
                : ['Lands ROFO', 'Land Conversion'];

            return function ($q) use ($printedDocTypes, $securityCodeDocTypes) {
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
        };

        $printedDocTypes = $isOssView
            ? ['Land Recommendation', 'OSS Recommendation For Grant']
            : ['Land Recommendation', 'Recommendation For Grant'];

        $securityCodeDocTypes = $isOssView
            ? ['OSS Recomm']
            : ['Lands ROFO', 'Land Conversion'];

        $printedExists = $printedExistsFor($isOssView);

        // Counters: cards and tab badges alike describe the PAGE, never the list.
        // The OSS tab swaps the table for the OSS register and stops there — when
        // the cards followed the table instead, ?type=ROFO&tab=oss showed the OSS
        // totals under the Land heading and the page read as the OSS page.
        $stats = $this->indexStats($request, $pageType === 'OSS', $applyOssChangeOfNameOriginFilter, $printedExistsFor($pageType === 'OSS'));
        $stats['oss'] = $this->ossRecommendationCount($applyOssChangeOfNameOriginFilter, $printedExistsFor(true));

        // The Batches tab pages over batches, not over recommendations. On the main
        // list a 100-child batch is one collapsed row whose children are spread
        // across five pages, so expanding it shows whichever 20 happen to be on the
        // page you are looking at — which reads as "this batch has 20 children".
        // Here one row is one whole batch, and expanding it loads every child.
        if ($tab === 'batches') {
            // A recommendation which was returned to Remaining Files retains its
            // historical batch id, but is no longer a member of that saved batch.
            // Every batch screen must use this same membership rule as the editor.
            $batchQuery = $this->activeBatchMembers(
                LandRecommendation::query()->whereNotNull('rofo_batch_id')
            );

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

            // The Batches register shows how much of a subdivision's full lineage
            // this recommendation batch contains (e.g. 21 of 994), not merely the
            // number of rows already captured. Regular batches have no mother and
            // therefore fall back to their own row count in the view.
            $batchChildTotals = collect($batches->items())->mapWithKeys(function ($batch) {
                $mother = trim((string) ($batch->mother_file_no ?? ''));

                return [$batch->rofo_batch_id => $mother !== ''
                    ? $this->subdivisionLineageChildCount($mother)
                    : (int) $batch->total];
            });

            // Which of the batches on this page already carry the mother's scanned
            // recommendation. Loaded for the whole page in one query rather than
            // asked per row: the menu on every subdivision batch offers either
            // Upload or View depending on the answer.
            $batchDocuments = LandRecommendationBatchDocument::whereIn(
                    'rofo_batch_id',
                    collect($batches->items())->pluck('rofo_batch_id')->filter()->values()
                )
                ->get()
                ->keyBy('rofo_batch_id');

            $PageTitle = 'Recommendation For Grant Of Statutory Right Of Occupancy';

            return view('land_recommendations.index', [
                'recommendations' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'batches'         => $batches,
                'batchCreators'   => $batchCreators,
                'batchDocuments'  => $batchDocuments,
                'batchChildTotals'=> $batchChildTotals,
                'PageTitle'       => $PageTitle,
                'stats'           => $stats,
                'isOssView'       => $isOssView,
                'pageType'        => $pageType,
                'ossTab'          => $ossTab,
                'tab'             => $tab,
                'printDates'      => [],
                'whiteCopyDone'   => [],
                'recSerials'      => [],
                'batchSizes'      => collect(),
                'batchActions'    => collect(),
                'approvedLetters' => collect(),
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

        // OSS recommendations are printed in OSS itself, so the OSS list only ever
        // shows printed records — there is no not-printed half of it to show.
        if ($tab === 'extant') {
            $this->extantRecommendation($query);
        } elseif ($tab === 'printed' || $ossTab) {
            $query->whereExists($printedExists);
            // The OSS register is its own list and has no extant tab to move to.
            if (!$ossTab) {
                $this->extantRecommendation($query, false);
            }
        } else { // not_printed
            $query->whereNotExists($printedExists);
            $this->extantRecommendation($query, false);
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
            ->tap(fn ($query) => $this->activeBatchMembers($query))
            ->groupBy('rofo_batch_id')
            ->selectRaw('rofo_batch_id, COUNT(*) AS total')
            ->pluck('total', 'rofo_batch_id');

        // Per batch: the ids still pending approval, and whether the whole batch is
        // approved. The batch row's actions act on every child, including any that
        // paginated onto another page.
        $batchActions = $batchIdsOnPage->isEmpty() ? collect() : LandRecommendation::query()
            ->whereIn('rofo_batch_id', $batchIdsOnPage)
            ->tap(fn ($query) => $this->activeBatchMembers($query))
            ->get(['id', 'rofo_batch_id', 'status'])
            ->groupBy('rofo_batch_id')
            ->map(fn ($rows) => [
                'pending_ids'  => $rows->where('status', LandRecommendation::STATUS_PENDING)->pluck('id')->values()->all(),
                'all_approved' => $rows->every(fn ($r) => $r->status === LandRecommendation::STATUS_APPROVED),
            ]);

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

        // Which rows have had their proof run off: the official print opens on the
        // strength of it, and the White Copy closes with it.
        $whiteCopyDone = array_flip(PrintLog::whiteCopyPrinted('Recommendation', $fileNumbers));

        // Records that stand for a recommendation already approved on paper, and
        // whether that letter has been uploaded yet. Loaded for the whole page in one
        // query: the row menu offers Upload or View on the answer, and Approve is
        // held shut until it is there.
        $approvedLetters = LandRecommendationDocument::whereIn(
                'land_recommendation_id',
                $recommendations->getCollection()->pluck('id')->all()
            )
            ->get()
            ->keyBy('land_recommendation_id');

        // The mother's scanned recommendation for records captured with Use
        // Subdivision Template. Such a record joins no batch, so its document hangs
        // off a key built from its own id rather than a rofo_batch_id -- see
        // LandRecommendationBatchDocument::subdivisionTemplateKey(). Loaded for the
        // whole page in one query, exactly like the batch documents above: the row
        // menu offers Upload or View on the answer.
        $subdivisionTemplateIds = $recommendations->getCollection()
            ->filter(fn ($rec) => (bool) $rec->use_subdivision_template)
            ->pluck('id');

        // A merged file inherits a recommendation from each of its parents, so one
        // record can hold several letters -- slot 1 under the bare key and the rest
        // suffixed beside it. Collected per record rather than one-to-one, and the
        // whole page is still a single query.
        $subdivisionTemplateDocs = $subdivisionTemplateIds->isEmpty()
            ? collect()
            : LandRecommendationBatchDocument::where(function ($q) use ($subdivisionTemplateIds) {
                    foreach ($subdivisionTemplateIds as $id) {
                        $key = LandRecommendationBatchDocument::subdivisionTemplateKey((int) $id);
                        $q->orWhere('rofo_batch_id', $key)
                          ->orWhere('rofo_batch_id', 'LIKE', $key . '-%');
                    }
                })
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($doc) => (int) LandRecommendationBatchDocument::subdivisionTemplateId($doc->rofo_batch_id));

        return view('land_recommendations.index', compact('recommendations', 'PageTitle', 'stats', 'isOssView', 'pageType', 'ossTab', 'tab', 'printDates', 'whiteCopyDone', 'recSerials', 'batchSizes', 'batchActions', 'approvedLetters', 'subdivisionTemplateDocs'));
    }

    /**
     * Files whose recommendation is a document we hold rather than one KLAES prints.
     *
     * Two routes arrive at the same place, and both belong on the same tab:
     *
     *   Use Subdivision Template — the file prints as a subdivision child and
     *   inherits its MOTHER's letter. A file merged from several parents inherits
     *   one from each, so any slot counts (see subdivisionTemplateKey()).
     *
     *   OP-holder Match — the file keeps the recommendation it was already granted
     *   on paper, and the scan uploaded against it IS that letter.
     *
     * Either way nothing is ever printed for the record, so without a tab of its
     * own it would sit in Not Printed for good. The letter has to actually be in
     * hand: a file still waiting for its scan has work outstanding and stays where
     * the officer will see it.
     */
    private function extantRecommendation($query, bool $has = true)
    {
        $prefix = \App\Models\LandRecommendationBatchDocument::SUBDIVISION_TEMPLATE_PREFIX;

        // The mother's letter. The key is rebuilt in SQL so the test can be pushed
        // into the query; the prefix is a class constant, never user input.
        $motherLetter = function ($sub) use ($prefix) {
            $sub->select(DB::raw('1'))
                ->from('land_recommendation_batch_documents as lrbd')
                ->whereRaw(
                    "(lrbd.rofo_batch_id = ? + CAST(land_recommendations.id AS nvarchar(20))"
                    . " OR lrbd.rofo_batch_id LIKE ? + CAST(land_recommendations.id AS nvarchar(20)) + '-%')",
                    [$prefix, $prefix]
                );
        };

        // The file's own already-approved letter.
        $ownLetter = function ($sub) {
            $sub->select(DB::raw('1'))
                ->from('land_recommendation_documents as lrd')
                ->whereRaw('lrd.land_recommendation_id = land_recommendations.id');
        };

        if ($has) {
            return $query->where(function ($q) use ($motherLetter, $ownLetter) {
                $q->where(function ($w) use ($motherLetter) {
                    $w->where('land_recommendations.use_subdivision_template', true)
                      ->whereExists($motherLetter);
                })->orWhere(function ($w) use ($ownLetter) {
                    $w->where('land_recommendations.is_existing_recommendation', true)
                      ->whereExists($ownLetter);
                });
            });
        }

        // NOT (A OR B) is NOT A AND NOT B. Each half is grouped so neither can leak
        // out of the other, or out of a surrounding OR.
        return $query->where(function ($q) use ($motherLetter, $ownLetter) {
            $q->where(function ($w) use ($motherLetter) {
                $w->where('land_recommendations.use_subdivision_template', '<>', true)
                  ->orWhereNull('land_recommendations.use_subdivision_template')
                  ->orWhereNotExists($motherLetter);
            })->where(function ($w) use ($ownLetter) {
                $w->where('land_recommendations.is_existing_recommendation', '<>', true)
                  ->orWhereNull('land_recommendations.is_existing_recommendation')
                  ->orWhereNotExists($ownLetter);
            });
        });
    }

    /**
     * How many OSS recommendations exist, for the OSS tab's badge on the Land page.
     * Scoped exactly like the OSS list itself — printed only — so the badge and
     * the tab agree.
     */
    private function ossRecommendationCount(callable $applyOssFilter, callable $printedExists): int
    {
        $query = LandRecommendation::query()->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
        $applyOssFilter($query);
        $query->whereExists($printedExists);

        return (int) $query->count();
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
            'printed' => $this->extantRecommendation($tabScope()->whereExists($printedExists), false)->count(),
            'not_printed' => $this->extantRecommendation($tabScope()->whereNotExists($printedExists), false)->count(),
            // Batches, not batched rows — the badge counts what the tab lists.
            'batches' => $this->activeBatchMembers((clone $statsQuery)->whereNotNull('rofo_batch_id'))
                ->distinct()->count('rofo_batch_id'),
            // Counted on the OSS page too, where it is always 0 and the tab is not
            // rendered — cheaper than making the caller ask which page it is on.
            'extant' => $this->extantRecommendation($tabScope())->count(),
        ];
    }

    /**
     * Restrict a batch query to records which are still part of its saved set.
     *
     * Returned records deliberately retain rofo_batch_id for audit/history, but
     * batch_seq is cleared when they go back to Remaining Files. Generated records
     * remain printable members even when a legacy import has no sequence number.
     */
    private function activeBatchMembers($query)
    {
        return $query->where(function ($query) {
            $query->whereNotNull('batch_seq')
                ->orWhere('rofo_status', LandRecommendation::ROFO_GENERATED);
        });
    }

    /**
     * Every child of one batch, for the Batches tab's expanded view.
     *
     * Deliberately unpaginated: the whole point of the tab is that a batch is shown
     * whole rather than sliced by the list's page size.
     */
    public function batchChildren(Request $request, string $batchId)
    {
        $children = $this->activeBatchMembers(
                LandRecommendation::where('rofo_batch_id', $batchId)
            )
            ->with(['landUse', 'purpose.landUse'])
            ->orderBy('batch_seq')
            ->orderBy('id')
            ->get([
                'id', 'batch_seq', 'file_number', 'applicant_name', 'applicant_phone', 'plot_number', 'location',
                'purpose_of_clause', 'purpose_id', 'land_use', 'land_use_id',
                'status', 'rofo_status', 'land_rofo_serial_no', 'batch_mother_file_no',
                'is_existing_recommendation',
            ]);

        if ($children->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Batch not found.'], 404);
        }

        // A subdivision's children inherit the mother's recommendation rather than
        // earning letters of their own, so their rows offer the mother's scan
        // instead of a Print action. A regular batch has no mother, so its children
        // print exactly as they always did — the flag below is what separates them.
        $isSubdivision = trim((string) $children->first()->batch_mother_file_no) !== '';
        $document = $isSubdivision
            ? LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first()
            : null;

        // Approving one child at a time is held to exactly the rules the whole-batch
        // approval is held to, so the row can say why it is shut instead of the
        // officer finding out from a 422. Two rules apply, and they are different
        // things: the mother's letter is the batch's, and covers every child at once
        // (motherRecommendationGate); an already-approved letter is one child's own,
        // and only that child waits on it (approvedLetterGate). Both are still
        // enforced server-side — this only saves the round trip.
        $motherSatisfied = !$isSubdivision || $document !== null;

        $ownLetters = LandRecommendationDocument::whereIn(
                'land_recommendation_id',
                $children->where('is_existing_recommendation', 1)->pluck('id')->all()
            )
            ->pluck('land_recommendation_id')
            ->flip();

        return response()->json([
            'success'  => true,
            'batch_id' => $batchId,
            'count'    => $children->count(),
            'is_subdivision' => $isSubdivision,
            'mother_file_no' => $children->first()->batch_mother_file_no,
            // One document for the whole batch: every child row links to the same
            // URL, so it is sent once rather than repeated on all 500 rows.
            'document' => $document ? [
                'view_url'      => route('land-recommendations.batch-document.show', $batchId),
                'original_name' => $document->original_name,
                'summary'       => $document->summary(),
                // Tells the preview card whether to draw an <img> or a PDF frame.
                'mime_type'     => $document->mime_type,
                'uploaded_at'   => optional($document->uploaded_at)->format('d/m/Y H:i'),
            ] : null,
            'upload_url' => $isSubdivision
                ? route('land-recommendations.batch-document.store', $batchId)
                : null,
            // The batch-wide half of the approval gate, and how much is left to do.
            'mother_satisfied' => $motherSatisfied,
            'pending_count'    => $children->where('status', '!=', LandRecommendation::STATUS_APPROVED)->count(),
            'batch_approve_url' => route('land-recommendations.batch-approve'),
            'children' => $children->map(fn ($c, $i) => [
                'id'             => $c->id,
                'seq'            => $c->batch_seq ?: ($i + 1),
                'file_number'    => $c->file_number,
                'applicant_name' => $c->applicant_name,
                'applicant_phone'=> $c->applicant_phone,
                'plot_number'    => $c->plot_number,
                'location'       => $c->location,
                'purpose'        => $c->landuse_purpose,
                'status'         => $c->status,
                'rofo_status'    => $c->rofo_status,
                'serial_no'      => $c->land_rofo_serial_no,
                'edit_url'       => route('land-recommendations.edit', $c->id),
                'print_url'      => route('land-recommendations.print', $c->id),
                'approve_url'    => route('land-recommendations.approve', $c->id),
                // This child's own letter, not the mother's: a record captured from
                // the OP-holder Match flow stands for a recommendation already
                // approved on paper, and waits for that scan on its own.
                'needs_letter'     => (bool) $c->is_existing_recommendation,
                'letter_uploaded'  => $ownLetters->has($c->id),
            ])->values(),
        ]);
    }

    /**
     * The whole batch as a read-only register page.
     *
     * The Batches tab's inline expander answers "which files are in here"; this
     * answers "what was actually captured against each of them" — every column the
     * letter prints from, side by side, without opening each child's edit form and
     * without any control that can change a record. Deliberately unpaginated for
     * the same reason batchChildren() is: a batch is shown whole.
     */
    public function batchRecords(Request $request, string $batchId)
    {
        $records = $this->activeBatchMembers(
                LandRecommendation::with(['creator', 'landUse', 'purpose.landUse'])
                    ->where('rofo_batch_id', $batchId)
            )
            ->orderBy('batch_seq')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            abort(404, 'No batch found under ' . $batchId . '.');
        }

        $first  = $records->first();
        $mother = trim((string) ($first->batch_mother_file_no ?: $first->old_file_number));

        $summary = [
            'batch_id'         => $batchId,
            'mother_file_no'   => $mother,
            'application_type' => (string) ($first->application_type ?? ''),
            'total'            => $records->count(),
            'approved'         => $records->where('status', LandRecommendation::STATUS_APPROVED)->count(),
            'generated'        => $records->where('rofo_status', LandRecommendation::ROFO_GENERATED)->count(),
            'created_at'       => $records->min('created_at'),
            'created_by'       => $first->creator->name ?? 'System',
            // A batch of OSS records goes back to the OSS tab, not the Land list.
            'is_oss'           => strtoupper((string) ($first->type ?? '')) === 'OSS',
        ];

        $PageTitle = 'Batch Records — ' . ($mother !== '' ? $mother : $batchId);

        return view('land_recommendations.batch_records', compact('records', 'summary', 'PageTitle'));
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
        // Match OP — the OP-holder card on a page of its own, at
        // /land-recommendations/create?match-op. Split out so the two jobs can run in
        // parallel: one officer clears unmatched Occupancy Permits while another
        // captures recommendations, instead of the second waiting on the first.
        if ($request->has('match-op')) {
            // The run's own record. Read here rather than fetched by the page so the
            // table is there on first paint — an officer arriving to continue
            // yesterday's work should see it, not a spinner.
            $matcher = app(\App\Services\OpHolderMatchService::class);

            return view('land_recommendations.match_op', [
                'PageTitle'    => 'Match OP',
                'matchedTots'  => $matcher->recentMatches(25),
                'matchedTotal' => $matcher->matchCount(),
            ]);
        }

        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses = LandUse::orderBy('landuse')->get();

        // Opens the timeline for this capture: everything the officer does from
        // here — draft autosaves, the submit, its outcome — is read back against
        // this entry, so it records how they arrived (fresh, re-issuance, or with
        // a file number already chosen elsewhere).
        RecLog::info('Capture form opened', [
            'reissuance'  => $request->query('reissuance'),
            'source_id'   => $request->query('source_id'),
            'file_number' => $request->query('file_number'),
        ]);

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

            // A legacy RofO has no prior KLAES recommendation to replicate, but the
            // selected file is still an authoritative source of its property details.
            // Re-use the same resolver as batch capture so File Indexing, MLS and the
            // file-number registry all participate in the fallback chain. Previously
            // this path carried only the file number (and occasionally its year),
            // forcing officers to re-key title, plot, location and land-use details.
            $fileDetails = $fileNo === ''
                ? null
                : $this->hydrateBatchRows([$fileNo], null, false)->first();

            $recommendation = new LandRecommendation([
                'file_number' => $fileNo,
                'cofo_year'   => $cofoYear,
            ]);

            if ($fileDetails) {
                $recommendation->fill(collect($fileDetails)->only([
                    'applicant_name',
                    'applicant_address',
                    'plot_number',
                    'location',
                    'district',
                    'lga',
                    'land_use',
                    'land_use_id',
                    'purpose_id',
                    'tracking_id',
                ])->all());
            }
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
     * Reject a save that would duplicate an existing file number. This is a hard
     * block: there is no "Save Anyway". The officer edits the existing record
     * instead, and a deliberate second letter for the same file goes through the
     * re-issuance flow, which carries `is_reissuance` and skips this guard from
     * its caller. The form blocks too; this is what stops a stale page, a failed
     * fetch or a direct POST.
     */
    private function guardAgainstDuplicate(Request $request, $excludeId = null): void
    {
        $existing = $this->findDuplicate((string) $request->input('file_number', ''), $excludeId);

        if (!$existing) {
            return;
        }

        throw ValidationException::withMessages([
            'file_number' => sprintf(
                'A recommendation already exists for %s (applicant: %s, status: %s, created %s). Open and edit the existing record instead of capturing it again.',
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
        $this->clearTpNoPlaceholder($request);

        // Store one canonical number for the Contact Applicant action and SMS.
        // This accepts common officer input (+234 801..., 0801 234...) but the
        // validation below still rejects anything that is not a Nigerian mobile
        // number after normalisation.
        $request->merge([
            'applicant_phone' => NigerianPhone::normalize($request->input('applicant_phone')),
        ]);

        // A re-issuance intentionally repeats the file number of the letter it
        // replaces, so the duplicate guard does not apply to it.
        $isReissuance = $request->boolean('is_reissuance');

        // Everything that can turn the officer back — the duplicate guard and the
        // rules below — is logged with the reason before it is re-thrown. A
        // rejection redirects back with the errors painted on the form, so without
        // this the log would show a capture that was simply never saved.
        try {
            if (!$isReissuance) {
                $this->guardAgainstDuplicate($request);
            }

            $this->guardAgainstUnmatchedOpHolder($request);

            $validated = $request->validate([
                'file_number' => 'required|string',
                // Under Use Subdivision Template this field IS the mother file number,
                // so it stops being optional. It is never checked against the register:
                // the option exists precisely for the mother that is not in it.
                'old_file_number' => 'nullable|string|max:100|required_if:use_subdivision_template,1',
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
                'ground_rent_unit' => 'nullable|string|max:100',
                'ground_rent_unit_other' => 'nullable|string|max:100',
                'area_sqm' => $this->plotSizeRule($request->input('file_number')),
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
                // The form has always posted this and no rule ever accepted it, so
                // every save dropped it: 387 of 388 records carry a null LGA. A blank
                // one is not harmless — the edit form fills it by asking which LGA is
                // commonest for the district, and the Full Location box is rebuilt from
                // whatever that guess returns, so the officer's answer appeared to
                // revert on every reopen.
                'lga' => 'nullable|string',
                'state' => 'nullable|string',
                'layout_plan_no' => 'nullable|string',
                'development_value' => 'nullable|numeric',
                'development_charge' => 'nullable|string',
                'tracking_id' => 'nullable|string',
                'application_date' => 'required|date',
                'applicant_address' => 'required|string|max:500',
                'applicant_phone' => ['required', 'string', 'max:30', new NigerianPhone],
                'type' => 'nullable|string',
                'application_type' => 'nullable|string',
                'use_standard_template' => 'nullable|boolean',
                // Single-file subdivision. The mother file number is carried by
                // old_file_number above — see the required_if on that rule.
                'use_subdivision_template' => 'nullable|boolean',
                'page' => 'nullable|string',
                'page_survey_report' => 'nullable|string',
                'survey_report' => 'nullable|string',
                'physical_planning_comment' => 'nullable|string',
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

                // Set by the OP-holder Match flow on the form. The file's
                // recommendation was already approved on paper, so this record
                // stands in for that letter instead of generating a new one.
                'is_existing_recommendation' => 'nullable|boolean',
                'op_match_tot_pra_id' => 'nullable|integer',
            ], self::SUBDIVISION_TEMPLATE_MESSAGES);
        } catch (ValidationException $e) {
            RecLog::warning('Single capture rejected', [
                'file_number'   => trim((string) $request->input('file_number', '')),
                'is_reissuance' => $isReissuance,
                'errors'        => $e->errors(),
            ]);
            throw $e;
        }

        // Map survey method radio to YES/NO flags
        // "Other" becomes the words the officer typed; only the resolved unit is stored.
        $validated = $this->resolveGroundRentUnit($validated);
        $validated = $this->resolveIndexedPlotSize($validated, $validated['file_number'] ?? null);

        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        // Unchecked checkboxes are absent from the request, so resolve the flag
        // explicitly instead of leaving a previously-saved value in place.
        $validated['use_standard_template'] = $request->boolean('use_standard_template');

        // Same reason. The mother file number itself is old_file_number, which is
        // NOT cleared when the answer goes back to No — that field means "the parent
        // / previous file" for every application type, and only borrows the Mother
        // File Number label while this flag is on.
        $validated['use_subdivision_template'] = $request->boolean('use_subdivision_template');

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

        $this->applyExistingRecommendationMode($request, $validated);

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

        try {
            $recommendation = LandRecommendation::create($validated);
        } catch (\Throwable $e) {
            RecLog::error('Single capture failed to save', [
                'file_number' => $validated['file_number'] ?? null,
                'exception'   => get_class($e),
                'error'       => $e->getMessage(),
                'at'          => $e->getFile() . ':' . $e->getLine(),
            ]);
            throw $e;
        }

        RecLog::info('Single recommendation saved', [
            'id'            => $recommendation->id,
            'file_number'   => $recommendation->file_number,
            'land_use_id'   => $recommendation->land_use_id,
            'is_reissuance' => $isReissuance,
            'reissuance_source' => $validated['reissuance_source'] ?? null,
            'reissued_from_id'  => $validated['reissued_from_id'] ?? null,
            'status'        => $recommendation->status,
        ]);

        if ($isReissuance) {
            return redirect()->route('land-rofos.index')
                ->with('success', 'Re-issuance created for ' . $recommendation->file_number . '. It is now on the RofO table, ready to print.');
        }

        // Nothing was generated for this file — its letter already exists on paper.
        // Land on the register with the upload prompt open on the new row, so the
        // officer is not left to find it, and approval is not left waiting on a
        // step nobody was asked to take.
        if ($recommendation->is_existing_recommendation) {
            return redirect()->route('land-recommendations.index', [
                'type' => strtoupper((string) $recommendation->type) === 'OSS' ? 'OSS' : 'ROFO',
                'upload_letter' => $recommendation->id,
            ])
                ->with('success', 'Record saved for ' . $recommendation->file_number
                    . '. No new recommendation was generated — upload the approved one to allow approval.');
        }

        return redirect()->route('land-recommendations.index', [
            'type' => strtoupper((string) $recommendation->type) === 'OSS' ? 'OSS' : 'ROFO',
        ])
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
    private function subdivisionLineageChildCount(string $mother): int
    {
        $variants = array_values(array_unique(array_filter([
            $mother,
            preg_replace('/\s*\(T\)\s*$/i', '', $mother),
            $mother . '(T)',
        ])));

        $linkedFileNumbers = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('related_fileno', 'LIKE', '%"' . $variant . '"%');
                }
            })
            ->pluck('file_number');

        $workflowChildren = $linkedFileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')->table('mls_file_no')
                ->whereIn('full_file_number', $linkedFileNumbers)
                ->where('source', 'Subdivision')
                ->where(function ($query) {
                    $query->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->pluck('full_file_number');

        $legacyChildren = DB::connection('sqlsrv')->table('manual_file_linkages')
            ->where('workflow_type', 'Subdivision')
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('old_file_numbers', 'LIKE', '%"' . $variant . '"%');
                }
            })
            ->pluck('new_file_number');

        return $workflowChildren
            ->merge($legacyChildren)
            ->map(fn ($fileNumber) => mb_strtoupper(trim((string) $fileNumber)))
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * The file numbers a Change of Purpose turned each of these files into, walked to
     * the live end: X -> Y -> Z gives [X, Y, Z]. Used to find the subdivision children
     * of a grandmother — the officer picks CON-COM-2003-118, but the subdivision was
     * commissioned on CON-RES-2026-2924, the number the Change of Purpose gave it,
     * and that is what the children's related_fileno names.
     *
     * Only a Change of Purpose is followed: a one-for-one rename whose successor's
     * mls_file_no row is source = 'Change of Purpose'. An Extension also retires a
     * file into one successor, but that is a different parcel and not the same file
     * under a new number.
     *
     * @return string[]  the input first, then each successor in order
     */
    private function changeOfPurposeSuccessors(string $fileNumber): array
    {
        $chain = [trim($fileNumber)];
        $seen  = [mb_strtoupper(trim($fileNumber)) => true];

        for ($hop = 0; $hop < 5; $hop++) {
            $row = DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', end($chain))
                ->where('is_decommissioned', 1)
                ->whereNotNull('successor_file_no')
                ->first(['successor_file_no']);

            $successors = $row ? array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $row->successor_file_no)
            ))) : [];

            if (count($successors) !== 1 || isset($seen[mb_strtoupper($successors[0])])) {
                break;
            }

            $isChangeOfPurpose = DB::connection('sqlsrv')->table('mls_file_no')
                ->where('full_file_number', $successors[0])
                ->where('source', 'Change of Purpose')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->exists();

            if (!$isChangeOfPurpose) {
                break;
            }

            $chain[] = $successors[0];
            $seen[mb_strtoupper($successors[0])] = true;
        }

        return $chain;
    }

    /**
     * The reverse of changeOfPurposeSuccessors(), for the whole picker at once: the
     * file each of these was before any Change of Purpose renamed it.
     *
     * A subdivision commissioned on a file that a Change of Purpose produced is, to
     * the officer, a subdivision of the ORIGINAL file — CON-RES-2026-2924 is only
     * CON-COM-2003-118 under its new land use — so the picker lists the grandmother.
     * Followed only where exactly one retired file names this one as its sole
     * successor, the same one-for-one test renumberedSubdivisionChildren() uses.
     *
     * @param  string[]  $fileNumbers
     * @return array<string,string>  upper-cased file => its original file (moved ones only)
     */
    private function changeOfPurposeOrigins(array $fileNumbers): array
    {
        $current = [];
        foreach ($fileNumbers as $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            if ($fileNumber !== '') {
                $current[mb_strtoupper($fileNumber)] = $fileNumber;
            }
        }

        $visited = [];
        foreach ($current as $origin => $fileNumber) {
            $visited[$origin] = [$origin => true];
        }

        for ($hop = 0; $hop < 5 && $current; $hop++) {
            $values = array_values(array_unique($current));

            // Of the files still being walked, the ones a Change of Purpose produced.
            $copBorn = [];
            foreach (array_chunk($values, 1000) as $slice) {
                foreach (DB::connection('sqlsrv')->table('mls_file_no')
                    ->whereIn('full_file_number', $slice)
                    ->where('source', 'Change of Purpose')
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->pluck('full_file_number') as $f) {
                    $copBorn[mb_strtoupper(trim((string) $f))] = true;
                }
            }

            if (!$copBorn) {
                break;
            }

            // The retired file each one replaced.
            $predecessors = [];
            foreach (array_chunk(array_keys($copBorn), 1000) as $slice) {
                foreach (DB::connection('sqlsrv')->table('file_indexings')
                    ->whereIn('successor_file_no', $slice)
                    ->where('is_decommissioned', 1)
                    ->get(['file_number', 'successor_file_no']) as $row) {
                    $predecessors[mb_strtoupper(trim((string) $row->successor_file_no))][] = trim((string) $row->file_number);
                }
            }

            $moved = false;
            foreach ($current as $origin => $fileNumber) {
                $key  = mb_strtoupper($fileNumber);
                $prev = array_values(array_unique($predecessors[$key] ?? []));

                if (!isset($copBorn[$key]) || count($prev) !== 1
                    || isset($visited[$origin][mb_strtoupper($prev[0])])) {
                    continue;
                }

                $current[$origin] = $prev[0];
                $visited[$origin][mb_strtoupper($prev[0])] = true;
                $moved = true;
            }

            if (!$moved) {
                break;
            }
        }

        return array_filter(
            $current,
            fn ($fileNumber, $origin) => mb_strtoupper($fileNumber) !== $origin,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Follow a subdivision child that was later renumbered to the file it became.
     *
     * A plot does not always keep the number the subdivision gave it. Run a Change
     * of Purpose over one of them and the plot is retired into a new number — same
     * parcel, same prop_id, new land use — and the successor back-links to the plot
     * it replaced rather than to the subdivision mother. The mother then reads short
     * (79 of 82, say), and the plots that moved have to be captured one at a time
     * outside the batch they belong to. The successor takes the plot's place in the
     * child list instead, so the mother keeps its full count.
     *
     * Only a one-for-one replacement is followed. A plot that was itself subdivided
     * retires into many successors, and those are children of THAT plot — they have
     * their own mother and their own batch, and rolling them up here would inflate
     * the grandmother. successor_file_no tells the two apart on its own: a rename
     * writes one file number, a split writes the whole CSV list.
     *
     * @param  iterable<int,string>  $fileNumbers
     * @return array<string,string>  upper-cased original => the file it became
     */
    private function renumberedSubdivisionChildren(iterable $fileNumbers): array
    {
        // Keyed on the plot the subdivision produced, holding whatever that plot is
        // called now. The walk below moves the value along; the key never changes,
        // because the child list is keyed on the original plot.
        $chain = [];
        foreach ($fileNumbers as $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            if ($fileNumber !== '') {
                $chain[mb_strtoupper($fileNumber)] = $fileNumber;
            }
        }

        if (!$chain) {
            return [];
        }

        // A plot can be renumbered more than once — a Change of Purpose, then a second
        // one over the same plot — so the chain is walked to its live end. What has
        // been visited is tracked per plot, not across the whole set: the successor of
        // one plot is very often another plot in the same set (both were passed in),
        // and a shared visited list would read that as a loop and refuse the hop.
        $visited = [];
        foreach ($chain as $origin => $current) {
            $visited[$origin] = [$origin => true];
        }

        // The hop limit is what stops a lineage that loops back on itself (bad data,
        // or a purpose changed back to what it was) from spinning here forever.
        for ($hop = 0; $hop < 5; $hop++) {
            // The picker asks about every child of every subdivided file at once, so
            // this is chunked to stay under SQL Server's parameter ceiling — the same
            // reason subdivisionMothers() chunks its own whereIn.
            $rows = collect();
            foreach (array_chunk(array_values($chain), 1000) as $slice) {
                $rows = $rows->merge(
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->whereIn('file_number', $slice)
                        ->where('is_decommissioned', 1)
                        ->whereNotNull('successor_file_no')
                        ->get(['file_number', 'successor_file_no'])
                );
            }
            $rows = $rows->keyBy(fn ($row) => mb_strtoupper(trim((string) $row->file_number)));

            $moved = false;

            foreach ($chain as $origin => $current) {
                $row = $rows[mb_strtoupper($current)] ?? null;
                if (!$row) {
                    continue;
                }

                $successors = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) $row->successor_file_no)
                )));

                // Not a rename: this plot was split, and its successors belong to it.
                if (count($successors) !== 1) {
                    continue;
                }

                $next = $successors[0];
                if (isset($visited[$origin][mb_strtoupper($next)])) {
                    continue;   // already on this plot's chain — a loop
                }

                $chain[$origin] = $next;
                $visited[$origin][mb_strtoupper($next)] = true;
                $moved = true;
            }

            if (!$moved) {
                break;
            }
        }

        // Only the plots that actually moved are worth reporting.
        return array_filter(
            $chain,
            fn ($current, $origin) => mb_strtoupper($current) !== $origin,
            ARRAY_FILTER_USE_BOTH
        );
    }

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

        // The picker lists a grandmother (CON-COM-2003-118) where the subdivision was
        // commissioned on the number its Change of Purpose produced (CON-RES-2026-2924),
        // so the children are looked up under that number too.
        $base = preg_replace('/\s*\(T\)\s*$/i', '', $mother);
        $variants = array_values(array_unique(array_merge(
            $variants,
            array_slice($this->changeOfPurposeSuccessors($base), 1)
        )));

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

        // Chunked: a subdivision can now hold 2000 plots, and a grandmother adds the
        // children of every file it was subdivided as -- past SQL Server's 2100
        // parameters in one whereIn.
        $workflowChildren = collect();
        foreach ($linkedFileNumbers->chunk(1000) as $slice) {
            $workflowChildren = $workflowChildren->merge(DB::connection('sqlsrv')->table('mls_file_no')
                ->whereIn('full_file_number', $slice->values()->all())
                ->where('source', 'Subdivision')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->pluck('full_file_number'));
        }

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

        // A plot retired into a new number — a Change of Purpose over one of them —
        // is captured under the number it became, not the one it no longer answers
        // to. Without this the batch offers a decommissioned file and the plots that
        // moved have to be done singly, outside the batch they belong to.
        $renumbered = $this->renumberedSubdivisionChildren($childFileNumbers);

        if ($renumbered) {
            $childFileNumbers = $childFileNumbers
                ->map(fn ($f) => $renumbered[mb_strtoupper(trim((string) $f))] ?? $f)
                ->unique()
                ->values();

            // The legacy map is keyed on the child file number, so a renumbered
            // legacy child has to move with it or its plot number and applicant are
            // lost on the way into hydrateBatchRows().
            foreach ($renumbered as $origin => $current) {
                $legacyRow = $legacyByChild->first(
                    fn ($row) => mb_strtoupper(trim((string) $row->new_file_number)) === $origin
                );
                if ($legacyRow) {
                    $legacyByChild = $legacyByChild->put($current, $legacyRow);
                }
            }
        }

        if ($childFileNumbers->isEmpty()) {
            // "I picked the mother and nothing loaded" is the most common report
            // about this screen, and the answer is almost always lineage rather
            // than the form — so the lookup that came back empty is recorded.
            RecLog::warning('Subdivision mother has no children', [
                'mother_file_no' => $mother,
                'variants'       => implode(', ', $variants),
            ]);

            return response()->json([
                'success'  => true,
                'mother'   => $mother,
                'children' => [],
                'message'  => 'No subdivision children are linked to this file number — neither commissioned through the workflow nor backfilled as a legacy linkage.',
            ]);
        }

        // See batchFileDetails(): while a saved batch is being edited its own
        // members must not read as files that already carry a recommendation.
        $excludeBatchId = trim((string) $request->query('exclude_batch_id', '')) ?: null;

        $payload = $this->hydrateBatchRows($childFileNumbers, $legacyByChild, true, $excludeBatchId);

        // ── The mother file's own recommendation ────────────────────────────
        // A subdivision is one grant split into plots, so the children are the
        // mother's conditions applied to smaller parcels — the same relationship a
        // Sectional Titling unit has with its primary application, and inherited
        // the same way. Every child that has no recommendation of its own comes
        // back carrying the mother's grant conditions as its starting values; a
        // child that already has one keeps what was captured against it.
        $motherRec = $this->motherRecommendation($variants);
        if ($motherRec) {
            $grant = [];
            foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
                $grant[$field] = (string) ($motherRec->{$field} ?? '');
            }

            $payload = $payload->map(function (array $row) use ($grant, $motherRec) {
                if (!empty($row['has_existing_record'])) {
                    return $row;
                }

                $row['grant']       = $grant;
                $row['inherited']   = true;
                // The registry is still the better source for these where it has
                // them; the mother only fills what the child's own file does not.
                $row['land_use_id'] = $row['land_use_id'] ?: $motherRec->land_use_id;
                $row['purpose_id']  = $row['purpose_id'] ?: $motherRec->purpose_id;

                return $row;
            });
        }

        // What the officer is about to key against: how many children the lineage
        // produced, how many already carry a recommendation (those come back
        // untickable), and whether the mother's grant conditions were inherited.
        RecLog::info('Subdivision children loaded', [
            'mother_file_no'  => $mother,
            'children'        => $payload->count(),
            'already_have_rec' => $payload->filter(fn ($r) => !empty($r['has_existing_record']))->count(),
            'mother_rec_id'   => $motherRec->id ?? null,
            'exclude_batch_id' => $excludeBatchId,
        ]);

        return response()->json([
            'success'  => true,
            'mother'   => $mother,
            'children' => $payload,
            'count'    => $payload->count(),
            // The batch-wide half of the inheritance: fields the form captures once
            // for the whole batch rather than per child.
            'mother_recommendation' => $motherRec ? [
                'file_number'        => (string) $motherRec->file_number,
                'recommendation'     => (string) ($motherRec->recommendation ?? ''),
                'premium'            => (string) ($motherRec->premium ?? ''),
                'premium_words'      => (string) ($motherRec->premium_words ?? ''),
                // Stored as the two YES/NO columns store() derives it into, so it
                // has to be read back out into the radio the form actually has.
                'rofo_survey_method' => strtoupper((string) ($motherRec->rofo_director_survey ?? '')) === 'YES'
                    ? 'DIRECTOR'
                    : (strtoupper((string) ($motherRec->rofo_licensed_surveyor ?? '')) === 'YES' ? 'LICENSED' : ''),
            ] : null,
        ]);
    }

    /**
     * The recommendation captured against the mother file, under any of the
     * spellings its file number is stored in. The most recent one wins: a file
     * re-recommended after a correction should hand its children the correction.
     */
    private function motherRecommendation(array $variants): ?LandRecommendation
    {
        if (!$variants) {
            return null;
        }

        return LandRecommendation::whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first();
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
     *
     * $excludeBatchId — the batch being edited. Its own members already carry a
     * recommendation by definition, and flagging them "has a recommendation" would
     * untick every row of the batch the officer is re-keying. Their saved values
     * are still read (that is the backfill), only the exclusion flag is dropped.
     */
    private function hydrateBatchRows($fileNumbers, $legacyByChild = null, bool $sortByPlot = true, ?string $excludeBatchId = null)
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

        // Chunked to stay under SQL Server's 2100-parameter ceiling: a batch can now
        // carry well over 2000 children (a 2000-plot subdivision plus a grandmother's
        // earlier ones).
        $mlsByFile = collect();
        $indexByFile = collect();
        foreach ($childFileNumbers->chunk(1000) as $slice) {
            $slice = $slice->values()->all();

            $mlsByFile = $mlsByFile->merge(DB::connection('sqlsrv')->table('mls_file_no')
                ->whereIn('full_file_number', $slice)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->get(['full_file_number', 'file_name', 'plot_no', 'location', 'lga', 'district', 'land_use', 'tracking_id', 'address']));

            $indexByFile = $indexByFile->merge(DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $slice)
                ->get(['file_number', 'file_title', 'plot_number', 'district', 'lga', 'land_use_type',
                    'residence_address', 'location', 'current_holder', 'original_holder']));
        }
        $mlsByFile = $mlsByFile->keyBy(fn ($r) => $key($r->full_file_number));
        $indexByFile = $indexByFile->keyBy(fn ($r) => $key($r->file_number));

        // Fourth name source, and the one the other three miss most often. A file
        // commissioned outside the MLS path (KANGIS, ST, a temporary number) has its
        // holder on the fileNumber registry and nowhere else, so a child hydrated
        // only from mls_file_no/file_indexings came back with a blank Applicant Name
        // — and the officer's only way to fill it was Apply-to-all from the source
        // row, which stamps one owner's name across children who each have their own.
        // Every number column is searched because a child is picked by whichever of
        // its numbers the officer holds.
        $registryNames = [];
        $registryAddresses = [];
        $registryRows = collect();

        // SQL Server accepts at most 2,100 bound parameters. This lookup checks
        // four registry columns, so a 900+ child subdivision would otherwise bind
        // more than 3,600 values and fail before the rows can be rendered. Keep
        // each four-column query below the limit while merging the results back
        // into the same hydration pipeline.
        foreach ($childFileNumbers->chunk(400) as $fileNumberChunk) {
            $numbers = $fileNumberChunk->all();

            $registryRows = $registryRows->concat(
                DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($q) use ($numbers) {
                        $q->whereIn('mlsfNo', $numbers)
                            ->orWhereIn('kangisFileNo', $numbers)
                            ->orWhereIn('NewKANGISFileNo', $numbers)
                            ->orWhereIn('st_file_no', $numbers);
                    })
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'FileName', 'address'])
            );
        }

        foreach ($registryRows as $row) {
            $name = trim((string) ($row->FileName ?? ''));
            $address = trim((string) ($row->address ?? ''));
            if ($name === '' && $address === '') {
                continue;
            }

            foreach ([$row->mlsfNo, $row->kangisFileNo, $row->NewKANGISFileNo, $row->st_file_no] as $number) {
                $lookup = $key($number);
                // A file number can carry several registry rows (a re-commissioning,
                // a KANGIS alias). The first row that actually names someone wins;
                // a later blank must not overwrite it.
                if ($lookup !== '' && $name !== '' && !isset($registryNames[$lookup])) {
                    $registryNames[$lookup] = $name;
                }
                // FileNumber is also the fallback for correspondence address. It
                // is especially important on older large subdivision batches,
                // whose MLS and indexing rows often carry names but no address.
                if ($lookup !== '' && $address !== '' && !isset($registryAddresses[$lookup])) {
                    $registryAddresses[$lookup] = $address;
                }
            }
        }

        // original_holder / current_holder are stored JSON-encoded — a quoted scalar
        // for a single holder, an array for a block-indexed one — so they cannot be
        // read as plain columns. Mirrors FileIndexing::formattedHolder(), which is on
        // the model while these rows come back from the query builder.
        $holder = function ($raw): string {
            $raw = (string) ($raw ?? '');
            if (trim($raw) === '') {
                return '';
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return trim($raw);
            }

            if (is_array($decoded)) {
                $names = array_filter(array_map('trim', $decoded), fn ($n) => $n !== '');

                return implode(', ', $names);
            }

            return trim((string) $decoded);
        };

        // Children that already carry a recommendation come back with it attached:
        // the row is unticked (the duplicate guard would reject it) and shows what
        // was already captured rather than the registry defaults, so the user can
        // see the existing letter's details instead of guessing why it is excluded.
        // Chunked by file number to stay under SQL Server's parameter ceiling. The
        // order is re-applied across chunks exactly as the single query had it
        // (id descending, then keyBy, which keeps the last row per file).
        $existingByFile = collect();
        foreach ($childFileNumbers->chunk(1000) as $slice) {
            $existingByFile = $existingByFile->merge(LandRecommendation::whereIn('file_number', $slice->values()->all())
                ->get([
                    'id', 'file_number', 'applicant_name', 'applicant_address', 'plot_number',
                    'location', 'land_use_id', 'purpose_id', 'page', 'page_2', 'page_3',
                    'status', 'rofo_status', 'rofo_batch_id',
                ]));
        }
        $existingByFile = $existingByFile->sortByDesc('id')->keyBy(fn ($r) => $key($r->file_number));

        // mls_file_no.land_use holds file-number prefixes (RES, CON-AG, IND-RC …)
        // while land_uses holds full names, so route the value through the shared
        // normaliser rather than string-matching the two vocabularies.
        $normalizer = new \App\Services\Prs\Support\LandUseNormalizer();
        $canonToLandUseId = LandUse::pluck('id', 'landuse')
            ->mapWithKeys(fn ($id, $name) => [$normalizer->normalize((string) $name) => $id])
            ->all();

        $payload = $childFileNumbers
            ->map(function ($fileNo) use ($mlsByFile, $indexByFile, $legacyByChild, $existingByFile, $registryNames, $registryAddresses, $holder, $canonToLandUseId, $normalizer, $key, $excludeBatchId) {
                $lookup = $key($fileNo);
                $mls    = $mlsByFile[$lookup] ?? null;
                $index  = $indexByFile[$lookup] ?? null;
                $legacy = $legacyByChild[$lookup] ?? null;
                $rec    = $existingByFile[$lookup] ?? null;

                // A member of the batch being edited is not a clash with itself.
                $ownBatch = $rec !== null && $excludeBatchId !== null
                    && (string) $rec->rofo_batch_id === $excludeBatchId;

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
                    // Whoever the file says it belongs to, from every register that
                    // could answer — the holder lines are consulted last because they
                    // are a title question, and a file titled in one name may still be
                    // recommended to another, but a name from them beats a blank box.
                    'applicant_name' => $pick($rec->applicant_name ?? null, $index->file_title ?? null,
                        $mls->file_name ?? null, $registryNames[$lookup] ?? null,
                        $holder($index->current_holder ?? null), $holder($index->original_holder ?? null),
                        $legacy->applicant_name ?? null),
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
                        $index->residence_address ?? null, $registryAddresses[$lookup] ?? null),
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
                    'is_unknown'     => $mls === null && $index === null && $legacy === null && $rec === null
                        && !isset($registryNames[$lookup]),
                    // Whether the file carries a recommendation at all, as opposed
                    // to whether that counts as a clash here. The mother's grant
                    // conditions are only inherited onto children that have none of
                    // their own, so the two questions cannot share one flag.
                    'has_existing_record' => $rec !== null,
                    'has_recommendation' => $rec !== null && !$ownBatch,
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
        // Sent while a saved batch is being edited, so its own members come back
        // ticked instead of reading as files that already carry a recommendation.
        $excludeBatchId = trim((string) $request->input('exclude_batch_id', '')) ?: null;

        $rows = $this->hydrateBatchRows($numbers, null, false, $excludeBatchId);

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

        // A mother that a Change of Purpose produced is listed as the file it was
        // before: CON-RES-2026-2924 holds the 82 children, but it is CON-COM-2003-118
        // under a new land use, and that original is the file the recommendation is
        // raised against. subdivisionChildren() follows the rename forward again.
        $origins      = $this->changeOfPurposeOrigins(array_keys($childrenByMother));
        $subdividedAs = [];

        if ($origins) {
            $regrouped = [];
            foreach ($childrenByMother as $motherFileNo => $kids) {
                $root = $origins[mb_strtoupper($motherFileNo)] ?? $motherFileNo;
                $regrouped[$root] = ($regrouped[$root] ?? []) + $kids;
                if ($root !== $motherFileNo) {
                    $subdividedAs[$root][] = $motherFileNo;
                }
            }
            $childrenByMother = $regrouped;
        }

        // A plot retired into a new number — a Change of Purpose over one of them —
        // still belongs to its mother's batch, so it is counted under the number it
        // became. Otherwise the mother reads short (79 of 82) and the plots that
        // moved drop out of the batch they belong to. See subdivisionChildren().
        $everyChild = [];
        foreach ($childrenByMother as $kids) {
            foreach (array_keys($kids) as $child) {
                $everyChild[$child] = true;
            }
        }

        $renumbered = $this->renumberedSubdivisionChildren(array_keys($everyChild));

        if ($renumbered) {
            foreach ($childrenByMother as $motherFileNo => $kids) {
                $remapped = [];
                foreach (array_keys($kids) as $child) {
                    $current = $renumbered[mb_strtoupper($child)] ?? $child;
                    $remapped[$current] = true;
                }
                $childrenByMother[$motherFileNo] = $remapped;
            }
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
                // The number the subdivision was actually commissioned on, when a
                // Change of Purpose renamed the file first — shown beside it.
                'subdivided_as'  => implode(', ', $subdividedAs[$fileNo] ?? []),
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
                    || str_contains(mb_strtoupper($m['subdivided_as']), $needle)
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
     * The rules a batch post is validated against, shared by storeBatch() and
     * updateBatch(). Editing a batch keys exactly the same form as capturing one,
     * so the two must accept exactly the same fields — a rule added to only one of
     * them is a value that saves on create and is silently dropped on edit.
     */
    private function batchRules(string $kind): array
    {
        return [
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
            'children.*.ground_rent_unit'   => 'nullable|string|max:100',
            'children.*.ground_rent_unit_other' => 'nullable|string|max:100',
            'children.*.area_sqm'           => 'nullable|numeric|min:0',
            'children.*.development_period' => 'nullable|string',
            'children.*.development_value'  => 'nullable|numeric',
            'children.*.development_charge' => 'nullable|string',
            'children.*.survey_fees'        => 'nullable|numeric',
            'children.*.preparation_fees'   => 'nullable|numeric',
            'children.*.preparation_fees_words' => 'nullable|string',
            'children.*.layout_plan_no'     => 'nullable|string',

            // Per-file Page Number details, from the same stepper.
            'children.*.page_survey_report'        => 'nullable|string',
            'children.*.survey_report'             => 'nullable|string',
            'children.*.physical_planning_comment' => 'nullable|string',
            'children.*.improvement'               => 'nullable|string',
            'children.*.revision_period'           => 'nullable|string',
            'children.*.time_of_erection'          => 'nullable|string',

            // Recommendation & Reasons, and RofO Generation Data. The survey method
            // is not a column: it is written to the rofo_director_survey /
            // rofo_licensed_surveyor pair per child, the same way the batch-wide one
            // is written to $common.
            'children.*.recommendation'      => 'nullable|string',
            'children.*.rofo_survey_method'  => 'nullable|string|in:DIRECTOR,LICENSED',

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
            'ground_rent_unit'   => 'nullable|string|max:100',
            'ground_rent_unit_other' => 'nullable|string|max:100',
            'area_sqm' => 'nullable|numeric|min:0',
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
            'district'           => 'nullable|string',
            'lga'                => 'nullable|string',
            'improvement'        => 'nullable|string',
            'revision_period'    => 'nullable|string',
            'time_of_erection'   => 'nullable|string',
            'survey_report'      => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'physical_planning_comment' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',

            // The autosaved draft this batch was keyed in, so it can be closed out
            // once the recommendations exist. Absent for a batch keyed in one sitting
            // with autosave unavailable — the save must not depend on it.
            'draft_key'          => 'nullable|string|max:40',

            // How many children the browser posted. See the truncation guard below.
            'children_expected'  => 'nullable|integer|min:0',
        ];
    }

    /**
     * Did PHP drop half of this batch post on the way in?
     *
     * PHP stops parsing a request at max_input_vars and says nothing: the request
     * simply arrives short. A batch row is ~9 fields, so a 500-child batch is
     * ~4,500 variables against a typical limit of 1,000-2,500 — the post is cut
     * mid-row, and everything the form renders *after* the children table (the
     * application date, the grant conditions, the survey method) never arrives
     * either.
     *
     * Two signals, because either can appear alone:
     *
     *   1. Fewer rows than the browser declared. #batch-children-expected is
     *      written from the ticked-row count at submit time, and sits above the
     *      table in the DOM, so it is one of the first fields parsed and survives
     *      a cut that the rows below it do not.
     *   2. A row missing keys the form always posts for a ticked row. This is the
     *      row the cut landed inside — the count can still match when the partial
     *      row is itself the last one received.
     *
     * @return array{message:string}|null Null when the post looks whole.
     */
    private function detectTruncatedBatchPost(Request $request): ?array
    {
        $children = $request->input('children');
        $children = is_array($children) ? $children : [];

        $expected = (int) $request->input('children_expected', 0);
        $received = count($children);

        // Every ticked row renders all four: file_number as a hidden input, the
        // other three as required inputs. A row that arrives without one of the keys
        // at all (as opposed to holding a blank value) was cut, not left empty.
        $partial = [];
        foreach ($children as $index => $child) {
            if (!is_array($child)) {
                $partial[] = $index;
                continue;
            }

            foreach (['file_number', 'applicant_name', 'applicant_address', 'land_use_id'] as $field) {
                if (!array_key_exists($field, $child)) {
                    $partial[] = $index;
                    break;
                }
            }
        }

        $short = $expected > 0 && $received < $expected;

        if (!$short && !$partial) {
            return null;
        }

        // What the officer needs is the number that fits, not the number that did
        // not. The cost of a row is counted from the widest row that actually
        // arrived whole — the table's own field count — rather than assumed, so it
        // stays right as columns are added or dropped. The margin covers the fields
        // outside the table (the common grant conditions, the CSRF token) that have
        // to fit in the same post.
        $limit  = (int) ini_get('max_input_vars');
        $perRow = 0;
        foreach ($children as $index => $child) {
            if (is_array($child) && !in_array($index, $partial, true)) {
                $perRow = max($perRow, count($child));
            }
        }
        $safe = $perRow > 0 && $limit > 0 ? (int) floor(($limit * 0.85) / $perRow) : 0;

        $message = ($short
                ? 'Only ' . $received . ' of ' . $expected . ' children reached the server'
                : 'The last child to reach the server arrived incomplete')
            . ' — the form is larger than this server accepts in one post '
            . '(PHP max_input_vars is ' . $limit . '). Nothing was saved and your draft is safe. '
            . ($safe > 0 ? 'Save the batch in groups of about ' . $safe . ' files, ' : 'Save the batch in smaller groups, ')
            . 'or ask an administrator to raise max_input_vars.';

        return [
            'message'           => $message,
            'children_expected' => $expected,
            'children_received' => $received,
            'partial_rows'      => implode(', ', array_slice($partial, 0, 10)),
            'partial_row_count' => count($partial),
        ];
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
        $this->clearTpNoPlaceholder($request);

        // Absent means subdivision: the only kind that existed before regular
        // batches, so an old form (or a resumed draft keyed by one) still posts
        // exactly what it always did.
        $kind = $request->input('batch_kind') === 'regular' ? 'regular' : 'subdivision';

        // A batch is the expensive capture on this screen — 40+ children keyed by
        // hand over an hour — so what arrived is recorded before anything can turn
        // it back. children_expected is the browser's own count: read against the
        // count the validator saw, it is what separates "the officer selected 12"
        // from "200 were sent and PHP kept 12".
        RecLog::info('Batch save attempted', [
            'batch_kind'        => $kind,
            'mother_file_no'    => $request->input('batch_mother_file_no'),
            'children_expected' => (int) $request->input('children_expected', 0),
            'children_received' => is_array($request->input('children')) ? count($request->input('children')) : 0,
            'draft_key'         => $request->input('draft_key'),
            'max_input_vars'    => ini_get('max_input_vars'),
        ]);

        // A 200-child batch is over 2,000 form fields. PHP discards everything past
        // max_input_vars silently, so a truncated post would save a short batch and
        // report success — the children that fell off would just never exist.
        //
        // This runs BEFORE validation, and must stay there. A cut post is missing
        // whole rows and half of the row it was cut inside, so the rules fire first
        // and bury the real problem under "the children.492.applicant_name field is
        // required" — an error about a field the officer did fill, on a row they
        // cannot even see, alongside required-field errors for the common fields
        // that were cut off after the table. The message below is the only one that
        // says what actually happened.
        $truncation = $this->detectTruncatedBatchPost($request);
        if ($truncation) {
            RecLog::warning('Batch POST truncated — nothing saved', array_merge([
                'batch_kind'     => $kind,
                'mother_file_no' => $request->input('batch_mother_file_no'),
                'draft_key'      => $request->input('draft_key'),
                'max_input_vars' => ini_get('max_input_vars'),
            ], $truncation));

            throw ValidationException::withMessages([
                'children' => $truncation['message'],
            ]);
        }

        try {
            $validated = $request->validate($this->batchRules($kind));
        } catch (ValidationException $e) {
            RecLog::warning('Batch rejected by validation', [
                'batch_kind'     => $kind,
                'mother_file_no' => $request->input('batch_mother_file_no'),
                'draft_key'      => $request->input('draft_key'),
                'errors'         => $e->errors(),
            ]);
            throw $e;
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
            RecLog::warning('Batch blocked by existing recommendations', [
                'batch_kind'     => $kind,
                'mother_file_no' => $mother,
                'draft_key'      => $validated['draft_key'] ?? null,
                'clashes'        => implode(', ', array_slice($clashes, 0, 40)),
                'clash_count'    => count($clashes),
            ]);

            throw ValidationException::withMessages([
                'children' => 'These children already have a recommendation: ' . implode(', ', $clashes)
                    . '. Untick them and save again.',
            ]);
        }

        $common = collect($validated)
            ->except(['children', 'batch_kind', 'batch_mother_file_no', 'rofo_survey_method', 'draft_key', 'children_expected'])
            ->all();
        // The batch-wide unit, resolved once. A child that sends its own overrides it below.
        $common = $this->resolveGroundRentUnit($common);

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
                // This child's own unit, with "Other" turned into the words typed
                // against THIS file - a batch may price one plot per square metre
                // and the next per hectare.
                $child = $this->resolveGroundRentUnit($child);
                // Its own indexed plot size, when the file has one (see resolveIndexedPlotSize).
                $child = $this->resolveIndexedPlotSize($child, $child['file_number'] ?? null);

                foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
                    if (array_key_exists($field, $child)) {
                        $row[$field] = ($child[$field] === '' ? null : $child[$field]);
                    }
                }

                // Survey method is one choice stored as two columns, so it cannot go
                // through the loop above. A child that sends one overrides the
                // batch-wide answer already in $common; a child that sends nothing
                // keeps it.
                if (filled($child['rofo_survey_method'] ?? null)) {
                    $row['rofo_director_survey']   = $child['rofo_survey_method'] === 'DIRECTOR' ? 'YES' : 'NO';
                    $row['rofo_licensed_surveyor'] = $child['rofo_survey_method'] === 'LICENSED' ? 'YES' : 'NO';
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

            // $created is how far the loop got before it threw — the child that
            // failed is the next one, which is the only thing that makes a rolled
            // back batch diagnosable after the fact.
            RecLog::error('Batch rolled back', [
                'batch_id'         => $batchId,
                'batch_kind'       => $kind,
                'mother_file_no'   => $mother,
                'draft_key'        => $validated['draft_key'] ?? null,
                'children_total'   => count($validated['children']),
                'children_written' => $created ?? 0,
                'exception'        => get_class($e),
                'error'            => $e->getMessage(),
                'at'               => $e->getFile() . ':' . $e->getLine(),
            ]);

            // The draft is deliberately left open: the user is coming straight back
            // to this form to fix whatever failed, and it is all they still have.
            return back()->withInput()
                ->with('error', 'Batch could not be saved: ' . $e->getMessage());
        }

        // Close the autosaved draft only now that the recommendations are committed.
        // Doing it any earlier would throw the capture away on a failed save.
        $draftsClosed = 0;
        if (!empty($validated['draft_key'])) {
            $draftsClosed = LandRecommendationBatchDraft::where('draft_key', $validated['draft_key'])
                ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
                ->update([
                    'status'        => LandRecommendationBatchDraft::STATUS_SUBMITTED,
                    'rofo_batch_id' => $batchId,
                    'updated_at'    => now(),
                ]);
        }

        // The batch id is what every later question is asked with (the RofO table
        // filters on it, printBatch prints it), so it is logged with the files it
        // covers. draft_closed = 0 against a draft_key means the autosaved draft
        // stayed open behind a committed batch — it would come back in the resume
        // list as work that is in fact already saved.
        RecLog::info('Batch saved', [
            'batch_id'       => $batchId,
            'batch_kind'     => $kind,
            'mother_file_no' => $mother,
            'created'        => $created,
            'draft_key'      => $validated['draft_key'] ?? null,
            'draft_closed'   => $draftsClosed,
            'file_numbers'   => implode(', ', array_slice(
                array_map(fn ($c) => trim((string) $c['file_number']), $validated['children']), 0, 40
            )),
        ]);

        $summary = $kind === 'subdivision'
            ? "Subdivision batch saved — {$created} recommendations created for children of {$mother}."
            : "Batch saved — {$created} recommendations created for the selected files.";

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO', 'batch' => $batchId])
            ->with('success', $summary);
    }

    /**
     * Re-open a saved batch in the capture form.
     *
     * The same screen that captured it, filled back in: the common fields render
     * from the first child (every child carries the common set, which is what
     * "common" means here), and the table plus the per-file steppers are seeded
     * from $batchEdit. Nothing is re-read from the registry — the saved records
     * are what the officer is editing, and a registry backfill would quietly
     * replace values that were keyed by hand.
     */
    public function editBatch(Request $request, string $batchId)
    {
        // A returned child remains stored as a recommendation, but a NULL
        // batch_seq explicitly means it is outside this saved batch.  Do not
        // seed it into the lower editor table as well as Remaining Files.
        // Issued RofO rows are retained even if an older record has no seq.
        $children = LandRecommendation::where('rofo_batch_id', $batchId)
            ->where(function ($query) {
                $query->whereNotNull('batch_seq')
                    ->orWhere('rofo_status', LandRecommendation::ROFO_GENERATED);
            })
            ->orderBy('batch_seq')
            ->orderBy('id')
            ->get();

        if ($children->isEmpty()) {
            abort(404, 'No batch found under ' . $batchId . '.');
        }

        // The first child is the form's model: it carries the whole common set, and
        // every field on the form outside the batch table reads from it.
        $recommendation = $children->first();

        $mother = trim((string) ($recommendation->batch_mother_file_no ?? ''));

        // Edit drafts use a deterministic, owner-specific key. They are distinct
        // from capture drafts, so restoring one can only ever reopen this batch.
        $editDraftKey = 'eb' . substr(sha1($batchId . '|' . Auth::id()), 0, 30);
        $editDraft = LandRecommendationBatchDraft::query()
            ->where('draft_key', $editDraftKey)
            ->where('user_id', Auth::id())
            ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
            ->value('payload');

        $batchEdit = [
            'batch_id'         => $batchId,
            'draft_key'        => $editDraftKey,
            'draft_payload'    => is_array($editDraft) && ($editDraft['edit_batch_id'] ?? null) === $batchId
                ? $editDraft
                : null,
            'kind'             => $mother !== '' ? 'subdivision' : 'regular',
            'mother_file_no'   => $mother,
            'application_type' => (string) ($recommendation->application_type ?? ''),
            'picked_files'     => $children->pluck('file_number')->map(fn ($f) => trim((string) $f))->values()->all(),
            'children'         => $children->map(function (LandRecommendation $child, $i) {
                $grant = [];
                foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
                    $grant[$field] = (string) ($child->{$field} ?? '');
                }

                return [
                    'file_number'       => (string) $child->file_number,
                    'applicant_name'    => (string) ($child->applicant_name ?? ''),
                    'applicant_address' => (string) ($child->applicant_address ?? ''),
                    'plot_number'       => (string) ($child->plot_number ?? ''),
                    'location'          => (string) ($child->location ?? ''),
                    'land_use_id'       => $child->land_use_id,
                    // "Other" is stored as a free-text purpose_of_clause with a
                    // null purpose_id. Rehydrate it as the select's Other option
                    // so the batch editor exposes the accompanying text field.
                    'purpose_id'        => $child->purpose_id ?: ($child->purpose_of_clause ? 'other' : null),
                    'purpose_id_other'  => (string) ($child->purpose_of_clause ?? ''),
                    'page'              => (string) ($child->page ?? ''),
                    'page_2'            => (string) ($child->page_2 ?? ''),
                    'page_3'            => (string) ($child->page_3 ?? ''),
                    'tracking_id'       => (string) ($child->tracking_id ?? ''),
                    // Every row is a saved member of this batch, so none of the
                    // "already has a recommendation" apparatus applies: they are
                    // ticked, and unticking one is how the officer leaves it as it
                    // stands rather than how it is excluded from a new batch.
                    'has_recommendation' => false,
                    'is_batch_hidden'    => false,
                    // A generated RoFO is an issued process, not a draft. Keep
                    // it in the lower table for the batch count, but do not let a
                    // later batch edit alter or remove it.
                    'rofo_locked'         => $child->rofo_status === LandRecommendation::ROFO_GENERATED,
                    'existing_status'    => trim(($child->status ?: 'pending') . ' · RofO ' . ($child->rofo_status ?: 'pending')),
                    'is_unknown'         => false,
                    'checked'            => true,
                    'is_source'          => $i === 0,
                    'grant'              => $grant,
                ];
            })->values()->all(),
        ];

        $batchEdit['hidden_children'] = LandRecommendation::where('rofo_batch_id', $batchId)
            ->whereNull('batch_seq')
            ->where('rofo_status', '!=', LandRecommendation::ROFO_GENERATED)
            ->orderBy('id')
            ->get()
            ->map(function (LandRecommendation $child) {
                return [
                    'file_number'        => (string) $child->file_number,
                    'applicant_name'     => (string) ($child->applicant_name ?? ''),
                    'applicant_address'  => (string) ($child->applicant_address ?? ''),
                    'plot_number'        => (string) ($child->plot_number ?? ''),
                    'location'           => (string) ($child->location ?? ''),
                    'land_use_id'        => $child->land_use_id,
                    'purpose_id'         => $child->purpose_id ?: ($child->purpose_of_clause ? 'other' : null),
                    'purpose_id_other'   => (string) ($child->purpose_of_clause ?? ''),
                    'page'               => (string) ($child->page ?? ''),
                    'page_2'             => (string) ($child->page_2 ?? ''),
                    'page_3'             => (string) ($child->page_3 ?? ''),
                    'tracking_id'        => (string) ($child->tracking_id ?? ''),
                    'has_recommendation' => true,
                    'is_batch_hidden'    => true,
                    'rofo_locked'        => false,
                    'existing_status'    => 'In recommendation · hidden from saved batch',
                    'checked'            => false,
                    'is_source'          => false,
                ];
            })->values()->all();

        $PageTitle = 'Edit Batch — Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses  = LandUse::orderBy('landuse')->get();
        $purposes  = [];
        if ($recommendation->land_use_id) {
            $purposes = Purpose::where('landuseid', $recommendation->land_use_id)->orderBy('name')->get();
        }

        $isEdit = true;

        return view('land_recommendations.form', compact(
            'recommendation', 'PageTitle', 'landUses', 'purposes', 'isEdit', 'batchEdit'
        ));
    }

    /**
     * Save a batch that was re-opened for editing.
     *
     * Deliberately the mirror of storeBatch(): the same rules, the same common /
     * per-child split, so a value that saves on capture saves on edit. What differs
     * is only what happens to a row —
     *   ticked and already in the batch  → updated in place
     *   ticked and new to the batch      → created and joined to the batch
     *   absent from the lower saved table → removed from this recommendation batch
     *                                      and returned to Remaining Records.
     */
    public function updateBatch(Request $request, string $batchId)
    {
        $this->clearTpNoPlaceholder($request);

        $existing = LandRecommendation::where('rofo_batch_id', $batchId)->get();

        if ($existing->isEmpty()) {
            abort(404, 'No batch found under ' . $batchId . '.');
        }

        $kind = $request->input('batch_kind') === 'regular' ? 'regular' : 'subdivision';

        $validated = $request->validate($this->batchRules($kind));

        // Same truncation guard as the capture path — see storeBatch().
        $expected = (int) ($validated['children_expected'] ?? 0);
        if ($expected > 0 && $expected !== count($validated['children'])) {
            throw ValidationException::withMessages([
                'children' => 'Only ' . count($validated['children']) . ' of ' . $expected
                    . ' children reached the server — the form is larger than this server accepts in one post '
                    . '(PHP max_input_vars is ' . ini_get('max_input_vars') . '). Nothing was saved. '
                    . 'Save the batch in smaller groups, or ask an administrator to raise max_input_vars.',
            ]);
        }

        $key = fn ($v) => mb_strtoupper(trim((string) $v));
        $byFile = $existing->keyBy(fn ($r) => $key($r->file_number));

        // A file already in this batch is not a clash with itself; one being added
        // to it is held to the same rule as a fresh capture.
        $clashes = [];
        foreach ($validated['children'] as $child) {
            if ($byFile->has($key($child['file_number']))) {
                continue;
            }
            if ($this->findDuplicate($child['file_number'])) {
                $clashes[] = $child['file_number'];
            }
        }
        if ($clashes) {
            throw ValidationException::withMessages([
                'children' => 'These files already have a recommendation outside this batch: ' . implode(', ', $clashes)
                    . '. Untick them and save again.',
            ]);
        }

        $mother = trim((string) ($validated['batch_mother_file_no'] ?? ''));

        $common = collect($validated)
            ->except(['children', 'batch_kind', 'batch_mother_file_no', 'rofo_survey_method', 'draft_key', 'children_expected'])
            ->all();
        $common['rofo_director_survey']   = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $common['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        $common['use_standard_template']  = $request->boolean('use_standard_template');
        $common['updated_by']             = Auth::id();

        // Files posted that are not yet in the batch — they join it.
        $incomingKeys = collect($validated['children'])->map(fn ($c) => $key($c['file_number']))->unique()->values();
        $joining      = $incomingKeys->reject(fn ($k) => $byFile->has($k))->count();
        // A child whose RoFO has been generated is permanent in this batch. The
        // browser greys it out, but keep the same rule on the server so an old
        // page or a crafted request cannot remove an issued record.
        $lockedExisting = $existing->filter(fn ($record) => $record->rofo_status === LandRecommendation::ROFO_GENERATED);
        $removing = $existing
            ->filter(fn ($record) => $record->batch_seq !== null)
            ->reject(fn ($record) => $incomingKeys->contains($key($record->file_number)))
            ->reject(fn ($record) => $lockedExisting->contains('id', $record->id))
            ->values();

        if ($kind === 'subdivision') {
            $common['old_file_number']      = $mother;
            $common['batch_mother_file_no'] = $mother;
            $common['application_type']     = 'Plot Subdivision';
            // The lower table is the whole saved batch on this pass: additions
            // join it and rows moved back to Remaining Records leave it.
            $common['num_plots']            = (string) $incomingKeys->count();
        }

        $purposeNames = Purpose::pluck('name', 'id')->all();
        $landUseNames = LandUse::pluck('landuse', 'id')->all();

        // New members carry on from the last sequence rather than restarting at 1.
        $nextSeq = (int) $existing->max('batch_seq');

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $updated = 0;
            $created = 0;

            // An unchecked record remains a recommendation. Mark it as outside
            // the Saved Batch Records editor instead of deleting its data.
            if ($removing->isNotEmpty()) {
                LandRecommendation::whereIn('id', $removing->pluck('id'))->update(['batch_seq' => null]);
            }

            foreach (array_values($validated['children']) as $child) {
                // As posted, before the resolvers below add keys of their own — an
                // issued record takes only what was actually sent for it.
                $posted = $child;
                $row = $common;
                $row['file_number']       = trim($child['file_number']);
                $row['applicant_name']    = $child['applicant_name'];
                $row['applicant_address'] = $child['applicant_address'];
                $row['plot_number']       = $child['plot_number'] ?? null;
                $row['location']          = $child['location'] ?? null;
                $row['tracking_id']       = $child['tracking_id'] ?? null;
                $row['land_use_id']       = $child['land_use_id'];
                $row['land_use']          = $landUseNames[$child['land_use_id']] ?? null;
                $row['page']              = $child['page'] ?? null;
                $row['page_2']            = $child['page_2'] ?? null;
                $row['page_3']            = $child['page_3'] ?? null;

                // Same rule as the capture path: a per-file value wins over the
                // common set, and a key present but blank is an answer.
                // This child's own unit, with "Other" turned into the words typed
                // against THIS file - a batch may price one plot per square metre
                // and the next per hectare.
                $child = $this->resolveGroundRentUnit($child);
                // Its own indexed plot size, when the file has one (see resolveIndexedPlotSize).
                $child = $this->resolveIndexedPlotSize($child, $child['file_number'] ?? null);

                foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
                    if (array_key_exists($field, $child)) {
                        $row[$field] = ($child[$field] === '' ? null : $child[$field]);
                    }
                }

                // Survey method is one choice stored as two columns, so it cannot go
                // through the loop above. A child that sends one overrides the
                // batch-wide answer already in $common; a child that sends nothing
                // keeps it.
                if (filled($child['rofo_survey_method'] ?? null)) {
                    $row['rofo_director_survey']   = $child['rofo_survey_method'] === 'DIRECTOR' ? 'YES' : 'NO';
                    $row['rofo_licensed_surveyor'] = $child['rofo_survey_method'] === 'LICENSED' ? 'YES' : 'NO';
                }

                // Purpose is per child and has no common fallback, so a blank one
                // leaves whatever the record already carries — it is not a
                // deliberate "no purpose", it is a row the table could not offer a
                // matching option for.
                $purposeId = $child['purpose_id'] ?? null;
                if ($purposeId === 'other') {
                    $row['purpose_id']        = null;
                    $row['purpose_of_clause'] = $child['purpose_id_other'] ?? null;
                } elseif ($purposeId) {
                    $row['purpose_id']        = $purposeId;
                    $row['purpose_of_clause'] = $purposeNames[$purposeId] ?? null;
                }

                $record = $byFile->get($key($child['file_number']));

                if ($record) {
                    // An issued RoFO may be corrected from a reopened batch, but
                    // only field by field: nothing from the batch-wide set, no
                    // blank over a value, and nothing equal to what is stored.
                    // The form posts a per-file condition for these rows only when
                    // the officer changed it, so a field left alone is never sent.
                    if ($record->rofo_status === LandRecommendation::ROFO_GENERATED) {
                        if ($this->applyIssuedBatchCorrections($record, $posted, $child, $row)) {
                            $record->updated_by = Auth::id();
                            $record->save();
                            $this->propagateToFileSources($record);
                            $updated++;
                        }
                        continue;
                    }
                    // Re-added from Remaining Files: make the existing
                    // recommendation visible in the saved batch again, appended
                    // after the current members.
                    if ($record->batch_seq === null) {
                        $record->batch_seq = ++$nextSeq;
                    }
                    $record->fill($row);
                    $record->save();
                    // Only what this save actually moved reaches the registers —
                    // the service takes its diff from the save itself, so the
                    // is-anything-dirty gate that used to stand here is now part
                    // of it. A later edit of this batch is always seeded from
                    // land_recommendations, and this keeps a fresh registry lookup
                    // elsewhere from putting an earlier plot/location/name back
                    // over the edit.
                    $this->propagateToFileSources($record);
                    $updated++;
                } else {
                    $row['rofo_batch_id'] = $batchId;
                    $row['batch_seq']     = ++$nextSeq;
                    $row['created_by']    = Auth::id();

                    $record = new LandRecommendation();
                    $record->fill($row);
                    $record->save();
                    $this->propagateToFileSources($record);
                    $created++;
                }
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();

            return back()->withInput()
                ->with('error', 'Batch could not be saved: ' . $e->getMessage());
        }

        $summary = "Batch {$batchId} saved — {$updated} recommendation(s) updated"
            . ($created ? ", {$created} added to the batch" : '')
            . ($removing->isNotEmpty() ? ", {$removing->count()} returned to Remaining Files" : '')
            . '.';

        // Close an edit snapshot only after the recommendation transaction has
        // committed. Until then it remains the safe way back from a failed save.
        if ($request->filled('draft_key')) {
            LandRecommendationBatchDraft::where('draft_key', $request->input('draft_key'))
                ->where('user_id', Auth::id())
                ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
                ->update([
                    'status'        => LandRecommendationBatchDraft::STATUS_SUBMITTED,
                    'rofo_batch_id' => $batchId,
                    'updated_at'    => now(),
                ]);
        }

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO', 'tab' => 'batches'])
            ->with('success', $summary);
    }

    /**
     * The TP No. select's "Other" option posts the placeholder `__other__` until
     * the officer types the real number into "Specify TP No…". A save that arrives
     * with the placeholder means no TP No. was given, so it is treated as blank
     * rather than stored (one record, 41507, was saved that way). An issued batch
     * record then keeps whatever it already had, since a blank never overwrites.
     */
    private function clearTpNoPlaceholder(Request $request): void
    {
        $isPlaceholder = fn ($v) => in_array(strtolower(trim((string) $v)), ['__other__', 'other', 'others'], true);

        if ($request->has('layout_plan_no') && $isPlaceholder($request->input('layout_plan_no'))) {
            $request->merge(['layout_plan_no' => null]);
        }

        $children = $request->input('children');
        if (is_array($children)) {
            $changed = false;
            foreach ($children as $i => $child) {
                if (is_array($child) && array_key_exists('layout_plan_no', $child) && $isPlaceholder($child['layout_plan_no'])) {
                    $children[$i]['layout_plan_no'] = null;
                    $changed = true;
                }
            }
            if ($changed) {
                $request->merge(['children' => $children]);
            }
        }
    }

    /**
     * Correct an issued (RoFO generated) record from a reopened batch without
     * disturbing anything that was saved correctly. Fills $record and returns
     * whether anything changed; the caller saves.
     *
     * - Table cells (name, address, plot, location, land use, purpose, pages)
     *   always post, seeded from the record, so only a value that differs from
     *   what is stored is taken.
     * - Per-file conditions (TP No., ground rent, plot size, ...) are taken only
     *   when posted for this file. The form sends one for an issued row only when
     *   the officer changed it; the batch-wide set is never applied.
     * - A blank never overwrites a stored value.
     *
     * @param array $posted The child exactly as posted.
     * @param array $child  The child after the unit / plot-size resolvers.
     * @param array $row    The row built for a non-issued child (cells resolved).
     */
    private function applyIssuedBatchCorrections(LandRecommendation $record, array $posted, array $child, array $row): bool
    {
        $same = function ($stored, $incoming): bool {
            $a = trim((string) $stored);
            $b = trim((string) $incoming);
            if (is_numeric($a) && is_numeric($b)) {
                return abs((float) $a - (float) $b) < 0.000001;
            }
            return $a === $b;
        };
        $take = function (string $field, $value) use ($record, $same) {
            if ($value === null || trim((string) $value) === '') return;
            if ($same($record->{$field}, $value)) return;
            $record->{$field} = is_string($value) ? trim($value) : $value;
        };

        foreach (['applicant_name', 'applicant_address', 'plot_number', 'location', 'page', 'page_2', 'page_3'] as $field) {
            $take($field, $row[$field] ?? null);
        }

        if (!$same($record->land_use_id, $row['land_use_id'] ?? null)) {
            $take('land_use_id', $row['land_use_id'] ?? null);
            $take('land_use', $row['land_use'] ?? null);
        }

        // Purpose: "Other" is a null purpose_id with the words in purpose_of_clause.
        $purposeId = $posted['purpose_id'] ?? null;
        if ($purposeId === 'other') {
            $text = trim((string) ($posted['purpose_id_other'] ?? ''));
            if ($text !== '' && ($record->purpose_id !== null || !$same($record->purpose_of_clause, $text))) {
                $record->purpose_id        = null;
                $record->purpose_of_clause = $text;
            }
        } elseif (filled($purposeId) && !$same($record->purpose_id, $purposeId)) {
            $record->purpose_id        = $row['purpose_id'] ?? $purposeId;
            $record->purpose_of_clause = $row['purpose_of_clause'] ?? $record->purpose_of_clause;
        }

        foreach (self::PER_CHILD_GRANT_FIELDS as $field) {
            $sent = array_key_exists($field, $posted)
                || ($field === 'ground_rent_unit' && array_key_exists('ground_rent_unit_other', $posted));
            if ($sent) {
                $take($field, $child[$field] ?? null);
            }
        }

        if (filled($posted['rofo_survey_method'] ?? null)) {
            $take('rofo_director_survey', $posted['rofo_survey_method'] === 'DIRECTOR' ? 'YES' : 'NO');
            $take('rofo_licensed_surveyor', $posted['rofo_survey_method'] === 'LICENSED' ? 'YES' : 'NO');
        }

        return $record->isDirty();
    }

    /**
     * Push what this save changed out to the registers that hold the same facts about
     * the file — file_indexings, the fileNumber register, the customer / entity name
     * mirrors and the manual subdivision linkages.
     *
     * Runs on BOTH the single-record edit and every child of a batch edit. It used to
     * run only on the latter, and wrote a snapshot of the record rather than a diff,
     * which is why it could never safely carry district or LGA: almost every
     * recommendation held a null LGA, so a snapshot push would have blanked a
     * populated register column. RecommendationPropagationService writes only fields
     * that actually moved, and never writes a blank over a value.
     *
     * Deliberately NOT caught here. Inside updateBatch() this runs within the batch
     * transaction, where a failure must roll the whole batch back rather than leave a
     * recommendation and its source rows disagreeing. update() commits first and so
     * catches it itself — see the call site there.
     */
    private function propagateToFileSources(LandRecommendation $record): void
    {
        $targets = app(\App\Services\RecommendationPropagationService::class)->propagate($record);

        if ($targets) {
            RecLog::info('Recommendation edit propagated to file sources', [
                'id'          => $record->id,
                'file_number' => $record->file_number,
                'targets'     => $targets,
            ]);
        }
    }

    public function show($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        return view('land_recommendations.show', compact('recommendation'));
    }

    public function edit($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // A single-record edit gets the same protection as a batch edit.  The
        // draft values are painted onto the model only for this form response;
        // they are not committed until the officer presses Update.
        $editDraftKey = 'er' . substr(sha1('record|' . $recommendation->id . '|' . Auth::id()), 0, 30);
        $editDraft = LandRecommendationBatchDraft::query()
            ->where('draft_key', $editDraftKey)
            ->where('user_id', Auth::id())
            ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
            ->value('payload');
        if (is_array($editDraft) && (int) ($editDraft['edit_record_id'] ?? 0) === (int) $recommendation->id) {
            $draftValues = (array) ($editDraft['values'] ?? []);
            $allowed = array_flip($recommendation->getFillable());
            $recommendation->fill(array_intersect_key($draftValues, $allowed));
        }

        // District / LGA from the file itself, before the form falls back to guessing.
        // With a blank LGA the blade asks InstrumentController::districtLookupLga(),
        // which answers with whichever LGA is most common for that district across
        // every indexed file — a popularity vote, not this file's answer. On
        // RES-2026-2600 it filled in "Kano Municipal" (9 BUK files say so, 2 say
        // Ungogo, and this file is one of the 2), and the Full Location builder then
        // rewrote the box to "BUK Kano Municipal Kano State" over the stored
        // "BUK, UNGOGO, KANO STATE". Filling the field here stops the guess firing.
        $this->fillBlankLocationFromFile($recommendation);

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
        return view('land_recommendations.form', compact('recommendation', 'PageTitle', 'landUses', 'purposes', 'editDraftKey'));
    }

    /**
     * Fill a blank district / LGA on the record being edited from the file's own
     * registry rows, in place and unsaved.
     *
     * Only blanks are touched — a value keyed on the letter is the officer's and stands.
     * The registries are consulted in the order they are trusted for this: the indexing
     * record is what the capture form reads when a file number is picked, mls_file_no
     * and fileNumber are the fallbacks it uses when the file was never indexed.
     *
     * A missing LGA is not a cosmetic gap: see edit() for what the form does with one.
     */
    private function fillBlankLocationFromFile(LandRecommendation $recommendation): void
    {
        $isBlank = fn ($value) => trim((string) ($value ?? '')) === '';

        $wanted = array_filter([
            'district' => $isBlank($recommendation->district),
            'lga'      => $isBlank($recommendation->lga),
        ]);

        if (empty($wanted)) {
            return;
        }

        // "X(T)" and "X" are the same file held in different columns, so both spellings
        // are asked for.
        $numbers = collect([$recommendation->file_number, $recommendation->old_file_number])
            ->map(fn ($number) => trim((string) $number))
            ->filter()
            ->flatMap(fn ($number) => [$number, trim((string) preg_replace('/\(\s*T\s*\)$/i', '', $number))])
            ->map(fn ($number) => trim($number))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($numbers)) {
            return;
        }

        $sources = [
            ['file_indexings', ['file_number', 'mls_file_no', 'new_kangis_file_no', 'temp_file_no'], ['district' => 'district', 'lga' => 'lga']],
            ['mls_file_no', ['full_file_number', 'old_fileno'], ['district' => 'district', 'lga' => 'lga']],
            ['fileNumber', ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'], ['district' => 'district', 'lga' => 'lga']],
        ];

        foreach ($sources as [$table, $keyColumns, $columns]) {
            if (empty($wanted) || !Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $keyColumns = array_values(array_filter(
                $keyColumns,
                fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
            ));

            $columns = array_filter(
                $columns,
                fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
            );

            if (empty($keyColumns) || empty($columns)) {
                continue;
            }

            $row = DB::connection('sqlsrv')->table($table)
                ->where(function ($query) use ($keyColumns, $numbers) {
                    foreach ($keyColumns as $index => $column) {
                        $index === 0
                            ? $query->whereIn($column, $numbers)
                            : $query->orWhereIn($column, $numbers);
                    }
                })
                ->first(array_values($columns));

            if (!$row) {
                continue;
            }

            foreach ($columns as $field => $column) {
                if (!isset($wanted[$field]) || $isBlank($row->$column ?? null)) {
                    continue;
                }

                $recommendation->$field = trim((string) $row->$column);
                unset($wanted[$field]);
            }
        }
    }

    public function update(Request $request, $id)
    {
        $this->clearTpNoPlaceholder($request);

        $recommendation = LandRecommendation::findOrFail($id);

        $request->merge([
            'applicant_phone' => NigerianPhone::normalize($request->input('applicant_phone')),
        ]);

        // Only a change of file number can create a duplicate. A re-issuance shares
        // its source's number by design, and older duplicate pairs predate the hard
        // block — neither may be locked out of ordinary edits.
        $fileNumberChanged = strtoupper(trim((string) $request->input('file_number', '')))
            !== strtoupper(trim((string) $recommendation->file_number));
        if ($fileNumberChanged) {
            $this->guardAgainstDuplicate($request, $recommendation->id);
        }

        $validated = $request->validate([
            'file_number' => 'required|string',
            // See store() — the mother file number under Use Subdivision Template.
            'old_file_number' => 'nullable|string|max:100|required_if:use_subdivision_template,1',
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
            'ground_rent_unit' => 'nullable|string|max:100',
            'ground_rent_unit_other' => 'nullable|string|max:100',
            'area_sqm' => $this->plotSizeRule($request->input('file_number')),
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
            // See store(): posted by the form, accepted by no rule until now.
            'lga' => 'nullable|string',
            'state' => 'nullable|string',
            'layout_plan_no' => 'nullable|string',
            'development_value' => 'nullable|numeric',
            'development_charge' => 'nullable|string',
            'tracking_id' => 'nullable|string',
            'application_date' => 'required|date',
            'applicant_address' => 'required|string|max:500',
            'applicant_phone' => ['required', 'string', 'max:30', new NigerianPhone],
            'edit_reason' => 'nullable|string',
            'type' => 'nullable|string',
            'page' => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'physical_planning_comment' => 'nullable|string',
            'improvement' => 'nullable|string',
            'revision_period' => 'nullable|string',
            'time_of_erection' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'type' => 'nullable|string',
            'application_type' => 'nullable|string',
            'use_standard_template' => 'nullable|boolean',
            // See store() — the mother file number is hand-keyed on purpose.
            'use_subdivision_template' => 'nullable|boolean',
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
        ], self::SUBDIVISION_TEMPLATE_MESSAGES);

        // "Other" becomes the words the officer typed; only the resolved unit is stored.
        $validated = $this->resolveGroundRentUnit($validated);
        $validated = $this->resolveIndexedPlotSize($validated, $validated['file_number'] ?? null);

        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        // Unchecked checkboxes are absent from the request, so resolve the flag
        // explicitly instead of leaving a previously-saved value in place.
        $validated['use_standard_template'] = $request->boolean('use_standard_template');

        // Same reason. The mother file number itself is old_file_number, which is
        // NOT cleared when the answer goes back to No — that field means "the parent
        // / previous file" for every application type, and only borrows the Mother
        // File Number label while this flag is on.
        $validated['use_subdivision_template'] = $request->boolean('use_subdivision_template');

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

        // Also applied on the edit, not only on the capture. A record saved before the
        // form learned to recognise a file matched on the Match OP page carries the
        // flag as 0, and re-opening it is the repair: the card sets the hidden inputs
        // again and this writes them. It never CLEARS the flag — a form posting 0 for
        // any other reason leaves a flagged record flagged.
        $this->applyExistingRecommendationMode($request, $validated);

        $recommendation->update($validated);

        // A correction keyed on the letter — plot number, district, LGA, the full
        // location — is a correction to the FILE, and the registers the capture form
        // hydrates from hold their own copy of it. Without this the next file to read
        // those rows hands the stale value straight back.
        //
        // Caught, unlike the batch call site: the recommendation is already committed
        // by the line above, so an unreachable register must not turn a save the
        // officer completed into an error page. It is reported loudly instead, and
        // File Indexing remains the authority that can push the value again.
        try {
            $this->propagateToFileSources($recommendation);
        } catch (\Throwable $e) {
            RecLog::error('Recommendation saved but propagation failed', [
                'id'          => $recommendation->id,
                'file_number' => $recommendation->file_number,
                'exception'   => get_class($e),
                'error'       => $e->getMessage(),
            ]);
        }

        // The update is now the source of truth; do not retain an older edit
        // snapshot that could overwrite it on the next visit.
        LandRecommendationBatchDraft::where('draft_key', 'er' . substr(sha1('record|' . $recommendation->id . '|' . Auth::id()), 0, 30))
            ->where('user_id', Auth::id())
            ->where('status', LandRecommendationBatchDraft::STATUS_OPEN)
            ->update(['status' => LandRecommendationBatchDraft::STATUS_SUBMITTED, 'updated_at' => now()]);

        // Opened from the RofO table (Actions > Edit Record), so the save belongs
        // back there — the officer is working the RofO list and never asked to be
        // moved to the recommendation register. The outstanding-letter note below
        // is still said, because the RofO table has no upload prompt to open.
        if ($request->input('return_to') === 'rofo') {
            $tab = $request->input('return_tab');
            $params = in_array($tab, ['not_printed', 'printed', 'batches', 'reissuance'], true)
                ? ['tab' => $tab]
                : [];

            $message = 'Recommendation updated successfully.';
            if ($recommendation->is_existing_recommendation && ! $recommendation->document()->exists()) {
                $message = 'Recommendation updated. No new recommendation is generated for '
                    . $recommendation->file_number . ' — upload the approved one to allow approval.';
            }

            return redirect()->route('land-rofos.index', $params)->with('success', $message);
        }

        // Same landing as the capture: a record standing for a letter that is not on
        // file yet goes back to the register with its upload open, rather than to a
        // list where the one outstanding step is a menu the officer has to go looking
        // for.
        if ($recommendation->is_existing_recommendation && ! $recommendation->document()->exists()) {
            return redirect()->route('land-recommendations.index', ['type' => 'ROFO', 'upload_letter' => $recommendation->id])
                ->with('success', 'Recommendation updated. No new recommendation is generated for '
                    . $recommendation->file_number . ' — upload the approved one to allow approval.');
        }

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO'])
            ->with('success', 'Recommendation updated successfully.');
    }

    /**
     * Subdivision batches among these recommendations whose mother recommendation
     * has not been uploaded yet.
     *
     * A subdivision's children inherit the mother's letter instead of earning one
     * each, so an approved child is a record that points at that scan. Approving
     * before it exists produces hundreds of approved recommendations that show
     * nothing when opened, and the officer has no way to tell from the list that
     * anything is missing — the approval is the point at which it has to be there.
     *
     * Regular batches and single recommendations are unaffected: they have no
     * mother, so there is nothing to wait for.
     *
     * @return array<string,string> rofo_batch_id => mother file number
     */
    private function batchesMissingMotherRecommendation(array $ids): array
    {
        $batches = LandRecommendation::whereIn('id', $ids)
            ->whereNotNull('rofo_batch_id')
            ->whereRaw("ISNULL(batch_mother_file_no, '') <> ''")
            ->pluck('batch_mother_file_no', 'rofo_batch_id');

        if ($batches->isEmpty()) {
            return [];
        }

        $uploaded = LandRecommendationBatchDocument::whereIn('rofo_batch_id', $batches->keys())
            ->pluck('rofo_batch_id')
            ->all();

        return $batches->except($uploaded)->all();
    }

    /**
     * Refuse a capture on a file whose Occupancy Permit names a holder that File
     * Indexing does not, while nothing on the file explains the change.
     *
     * The form offers Match for exactly this, and Match is one click. Letting the
     * capture through without it writes a recommendation on top of a chain that
     * still does not say how the title reached the person it is being written for —
     * which is the state this whole flow exists to stop being created.
     *
     * Only ever fires BEFORE Match: the transfer Match writes is what clears the
     * condition, so a matched file passes here on the same rule that failed a moment
     * earlier. Files whose transfer is merely spelt differently never qualify at all
     * (see OpHolderMatchService), so an ordinary capture is untouched.
     */
    private function guardAgainstUnmatchedOpHolder(Request $request): void
    {
        // Already answered: the officer matched, and the record is being saved as
        // standing for the letter that file was already granted.
        if ($request->boolean('is_existing_recommendation')) {
            return;
        }

        $fileNumber = trim((string) $request->input('file_number', ''));

        if ($fileNumber === '') {
            return;
        }

        $state = app(\App\Services\OpHolderMatchService::class)->check($fileNumber);

        if (! $state['applies']) {
            return;
        }

        RecLog::warning('Capture blocked — OP holder mismatch not matched', [
            'file_number'   => $fileNumber,
            'op_holder'     => $state['op']['holder'] ?? null,
            'indexing_name' => $state['indexing_name'],
        ]);

        throw ValidationException::withMessages([
            'file_number' => 'The Occupancy Permit on ' . $fileNumber . ' was granted to '
                . ($state['op']['holder'] ?? 'another holder') . ', but File Indexing holds '
                . $state['indexing_name'] . ' and no transfer on the file explains the change. '
                . 'Press Match on the file history card first — it records the missing Transfer of Title.',
        ]);
    }

    /**
     * "Existing recommendation" mode, set by the OP-holder Match flow on the form.
     *
     * The file's Occupancy Permit named one holder while File Indexing named
     * another; Match wrote the missing transfer, and the recommendation for such a
     * file already exists — approved, on paper. It is not written again and it does
     * not re-enter the approval queue on the strength of a fresh letter, so the
     * record is flagged here and cannot be approved until that letter is uploaded.
     *
     * The flag is NEVER inferred at approval time. Match writes the very row whose
     * absence made the file qualify, so re-asking the question later finds a file
     * that no longer qualifies and a gate with nothing to enforce.
     *
     * `type` is deliberately LEFT ALONE. It used to be forced to 'OSS' to record
     * that these files come through OSS, but that column is not a label — it is what
     * partitions the register, and every list that can act on the record excludes
     * it: the Land list filters `type <> 'OSS'`, and the OSS list additionally
     * demands a print record and an OSSOPCHANGEOFNAME application row, which a
     * matched file has neither of. A record saved as OSS therefore appeared on NO
     * page at all, so the letter could never be uploaded and the approval it gates
     * could never be reached — while the RofO table, which admits `type = 'OSS'`
     * regardless of status, showed it as PRINT READY before either had happened.
     * The OSS origin is carried by this flag instead, which the register badges.
     */
    private function applyExistingRecommendationMode(Request $request, array &$validated): void
    {
        if (! $request->boolean('is_existing_recommendation')) {
            return;
        }

        $validated['is_existing_recommendation'] = true;

        $totId = (int) $request->input('op_match_tot_pra_id');
        $validated['op_match_tot_pra_id'] = $totId > 0 ? $totId : null;

        RecLog::info('Captured as an existing (already approved) recommendation', [
            'file_number'         => $validated['file_number'] ?? null,
            'op_match_tot_pra_id' => $validated['op_match_tot_pra_id'],
        ]);
    }

    /**
     * Records flagged as standing for an already-approved letter that have no
     * letter uploaded yet.
     *
     * @return array<int,string> recommendation id => file number
     */
    private function recommendationsMissingApprovedLetter(array $ids): array
    {
        $flagged = LandRecommendation::whereIn('id', $ids)
            ->where('is_existing_recommendation', 1)
            ->pluck('file_number', 'id');

        if ($flagged->isEmpty()) {
            return [];
        }

        $uploaded = LandRecommendationDocument::whereIn('land_recommendation_id', $flagged->keys())
            ->pluck('land_recommendation_id')
            ->all();

        return $flagged->except($uploaded)->all();
    }

    /**
     * The refusal for the above, or null when every flagged record has its letter.
     * Shared by both approval endpoints, so a record approved one at a time from the
     * main list is held to the same rule as "Approve all".
     */
    private function approvedLetterGate(array $ids): ?string
    {
        $missing = $this->recommendationsMissingApprovedLetter($ids);

        if (! $missing) {
            return null;
        }

        $files = implode(', ', array_unique(array_values($missing)));

        RecLog::warning('Approval blocked — approved recommendation not uploaded', [
            'files'         => $files,
            'ids_attempted' => count($ids),
        ]);

        return count($missing) === 1
            ? 'The already-approved recommendation for ' . $files . ' has not been uploaded yet. '
                . 'This file keeps the letter it was granted on paper — upload it from the record first, '
                . 'because approving now would approve a recommendation that has nothing behind it.'
            : 'These records stand for recommendations already approved on paper, and none of their letters '
                . 'have been uploaded yet: ' . $files . '. Upload each one before approving.';
    }

    /**
     * The refusal for the above, or null when there is nothing to refuse. Shared by
     * both approval endpoints so a child approved one at a time from the main list
     * is held to the same rule as "Approve all".
     */
    private function motherRecommendationGate(array $ids): ?string
    {
        $missing = $this->batchesMissingMotherRecommendation($ids);

        if (!$missing) {
            return null;
        }

        $mothers = implode(', ', array_unique(array_values($missing)));

        RecLog::warning('Approval blocked — mother recommendation not uploaded', [
            'batches'      => implode(', ', array_keys($missing)),
            'mothers'      => $mothers,
            'ids_attempted' => count($ids),
        ]);

        return count($missing) === 1
            ? 'The mother recommendation for ' . $mothers . ' has not been uploaded yet. '
                . 'Upload it from the batch menu first — these children inherit that letter, '
                . 'so approving them now would leave records pointing at nothing.'
            : 'These subdivision batches have no mother recommendation uploaded yet: ' . $mothers . '. '
                . 'Upload each one from its batch menu before approving.';
    }

    public function approve($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Held to the same rule as "Approve all": a child of a subdivision batch
        // cannot be approved before the mother's letter is on file, however it is
        // approached.
        if ($blocked = $this->motherRecommendationGate([$recommendation->id])) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        // Same for a record that stands for a letter already approved on paper: the
        // scan is what it points at, so it has to be on file before approval.
        if ($blocked = $this->approvedLetterGate([$recommendation->id])) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        $recommendation->update([
            'status' => LandRecommendation::STATUS_APPROVED,
            'approved_at' => now()
        ]);

        $generated = $this->generateRofosForBatchMembers([$recommendation->id]);

        // LAAS Portal (spec i): tell the applicant their recommendation is
        // approved. Silent no-op for files that did not come from the portal.
        if (!empty($recommendation->file_number)) {
            app(\App\Services\Laas\LaasWorkflowService::class)->advanceByFileNumber(
                $recommendation->file_number,
                \App\Models\Laas\LaasApplication::STAGE_RECOMMENDATION_APPROVED,
                [
                    'title'   => 'Recommendation approved',
                    'body'    => 'The recommendation on your file has been approved.',
                    'columns' => ['land_recommendation_id' => $recommendation->id],
                ]
            );
        }

        return response()->json(['success' => true, 'rofos_generated' => $generated]);
    }

    public function batchApprove(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        if ($blocked = $this->motherRecommendationGate($ids)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        if ($blocked = $this->approvedLetterGate($ids)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

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

        $rofoSms = app(\App\Services\Sms\RofoSmsNotifier::class);

        foreach ($members as $member) {
            try {
                $generator->generate($member);
                $generated++;

                // Batch children never pass through LandRofoController::generate,
                // so without this the whole batch-approval path would be silent
                // while single-record generation texted the applicant.
                $rofoSms->send(
                    \App\Services\Sms\RofoSmsNotifier::SOURCE_LAND,
                    $member->file_number,
                    $member->id,
                    'Lands Department'
                );
            } catch (\Throwable $e) {
                // One child failing to sync must not strand the rest of the batch
                // as approved-but-ungenerated.
                Log::warning('Auto-generate RofO failed for recommendation ' . $member->id . ': ' . $e->getMessage());
            }
        }

        return $generated;
    }

    /**
     * The White Copy: a black & white proof of the recommendation, for vetting and
     * proofreading before an official copy is run off.
     *
     * The same record through the same templates, so what an officer reads here is
     * what the official document will say — but every mark of an issued document is
     * taken off it: the coat of arms, the QR, the security serial, the copy
     * designation and the signature blocks. In their place it is marked WHITE COPY,
     * and the acknowledgement sheet is left off — a proof is read, not collected
     * against.
     *
     * Nothing about official print state is touched on this path:
     *   - no security code is minted. This one matters more here than on the RofO,
     *     because these templates mint the serial themselves as they render, so a
     *     preview of a proof would otherwise burn a real serial;
     *   - no print_logs row is written, so the Printed tab and its counters see
     *     nothing (the templates omit the afterprint call entirely).
     *
     * Generating a White Copy therefore says nothing about whether it has been
     * proofread or approved — that is asked explicitly at the Print Manager.
     */
    public function printWhiteCopy(Request $request, $id)
    {
        // Shared rather than passed: the print for one recommendation resolves to
        // one of a dozen templates, several of which include others, and a proof
        // that silently rendered as an official copy because a variable did not
        // reach a partial is the one failure this flag cannot afford.
        view()->share('isWhiteCopy', true);

        $recommendation = LandRecommendation::findOrFail($id);
        $isOssRecommendation = strtoupper((string) $recommendation->type) === 'OSS';

        // DomPDF does not consistently honour the template's @media print rules.
        // Tell the view that this is the downloadable OSS proof so it can apply a
        // page-sized, padding-free PDF layout rather than the browser preview CSS.
        if ($isOssRecommendation) {
            view()->share('isPdfDownload', true);
        }

        $view = $this->print($request, $id);

        PrintLog::logWhiteCopy(
            'Recommendation',
            $recommendation->file_number,
            Auth::id()
        );

        // The OSS register's action menu reaches this route directly. Its White
        // Copy is a downloadable proof, not a browser print preview. Keep the
        // Land recommendation White Copy behaviour untouched: only OSS takes the
        // PDF download path.
        if ($isOssRecommendation) {
            $safeFileRef = preg_replace('/[^A-Z0-9._-]+/i', '-', (string) $recommendation->file_number)
                ?: 'OSS-RECOMMENDATION';

            return Pdf::loadHtml($view->render())
                ->setPaper('a4', 'portrait')
                ->download('OSS-Recommendation-White-Copy-' . $safeFileRef . '.pdf');
        }

        // The Land recommendation White Copy is generated as a PDF as well. These
        // letters are browser print templates (flexbox, letterheads) that DomPDF
        // cannot lay out, so Chrome prints the page exactly as the browser would.
        // Without Chrome on the server the page opens for printing as before.
        $renderer = app(\App\Services\ChromePdfRenderer::class);
        if ($renderer->available()) {
            $safeFileRef = preg_replace('/[^A-Z0-9._-]+/i', '-', (string) $recommendation->file_number)
                ?: 'RECOMMENDATION';

            try {
                return $renderer->download(
                    $renderer->render($view->render(), $request->root()),
                    'Recommendation-White-Copy-' . $safeFileRef . '.pdf'
                );
            } catch (\Throwable $e) {
                RecLog::warning('White copy PDF failed — serving the print page instead', [
                    'id'    => $recommendation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $view;
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // A file whose Occupancy Permit was matched keeps the recommendation it was
        // already granted on paper — the scan uploaded against the record IS its
        // letter, and the record exists to carry it, not to replace it. Printing
        // here would mint a fresh security serial and put a second, KLAES-issued
        // recommendation on a file that already has one.
        //
        // The white copy comes through this same method, so the proof is refused
        // with the official print rather than separately. The RofO is untouched:
        // it prints from LandRofoController on its own route, which is the document
        // these records are captured to produce.
        if ($recommendation->is_existing_recommendation) {
            RecLog::warning('Recommendation print refused — file keeps its extant letter', [
                'id'          => $recommendation->id,
                'file_number' => $recommendation->file_number,
                'white_copy'  => (bool) view()->shared('isWhiteCopy', false),
            ]);

            abort(403, 'No recommendation is printed for ' . $recommendation->file_number
                . '. Its Occupancy Permit was matched, so this record stands for the recommendation '
                . 'already approved on paper — open "View approved recommendation" on the record to read it.');
        }

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

            // The location the file records today, not the one it recorded when
            // the Recommendation card was last saved — and resolved by the very
            // ladder the White Copy's does, so the proof and the document can
            // never name two different places. See OssRecommendationLocation.
            $location = OssRecommendationLocation::resolve(
                (string) ($recommendation->file_number ?? ''),
                (string) ($recommendation->location ?? '')
            );

            $record = (object) [
                'applicant_name'    => strtoupper((string) ($recommendation->applicant_name ?? '')),
                'file_ref'          => (string) ($recommendation->file_number ?? ''),
                'purpose'           => strtoupper($recommendation->landuse_purpose),
                'location'          => $location,
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
                'ps_location'       => strtoupper($location),
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
        //
        // Use Subdivision Template stands the application type down the same way, so
        // the file is treated exactly as a child captured through a subdivision
        // batch: batch mode force-ticks use_standard_template for its children
        // (form.blade.php, applyKind), so a real child prints the ordinary letter
        // rather than the Plot Subdivision memo. This one does too.
        $primaryAppType = ($recommendation->use_standard_template || $recommendation->use_subdivision_template)
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
    /**
     * White copies of a whole batch of recommendations, as one document.
     *
     * The batch equivalent of printWhiteCopy(): the same records through the same
     * templates, with the arms, the QR, the serial, the signature blocks and the
     * acknowledgement sheets taken off and each marked WHITE COPY. No serial is
     * minted for any of them and the page carries no log URLs, so proofreading a
     * batch leaves every record exactly where it was.
     */
    public function printBatchWhiteCopy(Request $request, string $batchId)
    {
        // Shared rather than passed, for the same reason as the single print: a
        // batch resolves to whichever template its application type uses, and
        // several of those include others.
        view()->share('isWhiteCopy', true);

        $view = $this->printBatch($request, $batchId, true);

        foreach ($this->activeBatchMembers(LandRecommendation::where('rofo_batch_id', $batchId))->pluck('file_number') as $fileNumber) {
            PrintLog::logWhiteCopy('Recommendation', $fileNumber, Auth::id());
        }

        return $view;
    }

    public function printBatch(Request $request, string $batchId, bool $whiteCopy = false)
    {
        $records = $this->activeBatchMembers(LandRecommendation::where('rofo_batch_id', $batchId))
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
            'title'    => ($whiteCopy ? 'Batch Recommendation White Copy — ' : 'Batch Recommendation Print — ')
                . $mother . ' (' . $records->count() . ')',
            'subtitle' => 'Batch ' . $mother . ' — ' . $records->count() . ' '
                . \Illuminate\Support\Str::plural('recommendation', $records->count())
                . ($whiteCopy
                    ? ' — WHITE COPY · proofs for vetting, black & white on ordinary paper. '
                      . 'Nothing here counts as printed.'
                    : ''),
            // Empty for a proof: these fire on afterprint and would mark every
            // record in the batch as printed.
            'logUrls'  => $whiteCopy
                ? []
                : $records->map(fn ($r) => route('land-recommendations.log-print', $r->id))->all(),
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

    /**
     * MASTER DELETE — erase a land recommendation from every table it reached.
     *
     * Destructive and irreversible. The recommendation row goes, and with it the
     * RofO it became: the PRA transaction, the security paper (released back to
     * the pool, or retired if the sheet has already been through a printer), its
     * `security_codes` tracking row and the whole print history under both the
     * Recommendation and the RofO document types.
     *
     * What it does NOT touch: the file number itself, its indexing, and any deed
     * or instrument registered against the same file. Only rows this module wrote
     * are in range — see RofoRecommendationPurgeService.
     */
    public function masterDestroy(Request $request, $id)
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $recommendation = LandRecommendation::find($id);
        if (!$recommendation) {
            return response()->json(['success' => false, 'message' => 'Recommendation not found.'], 404);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $recommendation->file_number)) {
            return $deny;
        }

        $snapshot = $recommendation->toArray();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = app(RofoRecommendationPurgeService::class)->purgeLandRecommendation($recommendation);

            DB::connection('sqlsrv')->commit();

            $this->logMasterDelete(
                'land_recommendation',
                $recommendation->id,
                $snapshot,
                $counts,
                'Land Recommendation ' . $recommendation->file_number
            );

            return response()->json([
                'success' => true,
                'message' => 'Recommendation ' . $recommendation->file_number . ' deleted from all tables.',
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Land recommendation master delete failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting recommendation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
