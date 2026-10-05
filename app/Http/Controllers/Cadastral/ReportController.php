<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralReport;
use App\Models\Cadastral\CadastralReportStep;
use App\Models\Cadastral\CadastralSiteInspection;
use App\Models\CadastralOfficer;
use App\Models\User;
use App\Http\Controllers\Cadastral\Concerns\LocksFileValues;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\CadastralDocuments;
use App\Services\Cadastral\DashboardMetrics;
use App\Services\Cadastral\ReportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Cadastral Report Unit (concept note 4.2).
 *
 * Three report types over one chain: verification runs eight steps, customary
 * and statutory the same seven without Field Inspection. The chain is seeded per
 * report from config, so editing the config never rewrites a report in flight.
 *
 * Routing is by JOB POST, held in cadastral_officers.post_code, not by
 * user_roles — module_permissions.php is explicit that modules are named menu
 * areas rather than job roles, so D/Cad and Chart Officer I do not belong there.
 *
 * On approval this deep-links to the existing Land 12 screen with the fields
 * pre-filled. It never writes survey_report_requests: that table holds real
 * records and has no migration.
 */
class ReportController extends Controller
{
    use LocksFileValues;

    /**
     * The form fields a report takes from its intake receipt (and the file
     * index behind it). Supplied ones are locked on the form and overwritten
     * on the server; blank ones are the officer's.
     */
    private const FILE_FIELDS = ['file_title', 'plot_no', 'prop_house', 'prop_street', 'prop_district', 'prop_lga', 'prop_state'];

    public function __construct(private ReportWorkflow $workflow, private CadastralRegistryLookup $lookup) {}

    /**
     * 4.2 — the desk officer's view across all three streams.
     *
     * The figures come from DashboardMetrics rather than being re-derived here,
     * so "in flight" means the same thing on this page, on the Registry
     * landing page and in any report built later.
     */
    public function dashboard(DashboardMetrics $metrics, DashboardController $dashboards)
    {
        $myPosts = CadastralOfficer::postsFor(auth()->id());

        return view('cadastral_module.reports.dashboard', [
            'm'         => $metrics->reports($myPosts),
            'myPosts'   => $myPosts,
            'navCounts' => $dashboards->navCountsFor(),
        ]);
    }

    /** All Reports: every type, with a type filter. */
    public function index(Request $r)
    {
        // An old ?report_type= link (the pre-Phase-4 sidebar) still filters.
        return $this->listing($r, null);
    }

    /* One page per stream (plan Phase 4). GET and verb-free names: they infer `view`. */

    public function verification(Request $r)
    {
        return $this->listing($r, CadastralReport::TYPE_VERIFICATION);
    }

    public function customary(Request $r)
    {
        return $this->listing($r, CadastralReport::TYPE_CUSTOMARY);
    }

    public function statutory(Request $r)
    {
        return $this->listing($r, CadastralReport::TYPE_STATUTORY);
    }

    /**
     * The report list, across all types or scoped to one. The type pages are
     * this list with the type fixed, a type-specific header, and Start Report
     * for that type.
     */
    private function listing(Request $r, ?string $type)
    {
        $q = CadastralReport::query()->with('steps');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('report_ref', 'like', "%$term%")
                  ->orWhere('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%");
            });
        }

        $type = $type ?? ($r->query('report_type') ?: null);
        if ($type && ! array_key_exists($type, CadastralReport::TYPES)) {
            $type = null;
        }

        if ($type)                           $q->where('report_type', $type);
        if ($s = $r->query('status'))        $q->where('status', $s);
        if ($p = $r->query('assigned_post')) $q->where('assigned_post', $p);

        // Stage filter: open reports sitting at one stage of this type's chain.
        $stage = $type ? (string) $r->query('stage', '') : '';
        if ($stage !== '') {
            $q->where('current_step_key', $stage)->whereNotIn('status', ['Dispatched', 'Rejected']);
        }

        $reports = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // Open reports at each stage, for the stage tracker on a type page.
        $stageCounts = $type
            ? CadastralReport::query()
                ->where('report_type', $type)
                ->whereNotIn('status', ['Dispatched', 'Rejected'])
                ->selectRaw('current_step_key, count(*) as n')
                ->groupBy('current_step_key')
                ->pluck('n', 'current_step_key')
                ->map(fn ($n) => (int) $n)
                ->all()
            : [];

        $stats = [
            'total'        => CadastralReport::count(),
            'verification' => CadastralReport::where('report_type', 'verification')->count(),
            'customary'    => CadastralReport::where('report_type', 'customary')->count(),
            'statutory'    => CadastralReport::where('report_type', 'statutory')->count(),
        ];

        return view('cadastral_module.reports.index', [
            'reports'   => $reports,
            'stats'     => $stats,
            'scopeType' => $r->route()?->getName() === 'cadastral-module.reports.index' ? null : $type,
            'typeFilter'=> $type,
            'stageFilter' => $stage,
            'stageCounts' => $stageCounts,
            'userNames' => $this->userNames($reports->pluck('assigned_user_id')->all()),
            'workflow'  => $this->workflow,
        ]);
    }

    public function show(CadastralReport $report)
    {
        $report->load(['steps', 'inspections', 'chart', 'receipt', 'surveyJob']);

        $current    = $this->workflow->currentStep($report);
        $assignable = $current ? $this->workflow->assignableUserIds($current) : [];

        $actorIds = $report->steps->pluck('actor_user_id')->all();

        return view('cadastral_module.reports.show', [
            'report'      => $report,
            'progress'    => $this->workflow->progress($report),
            'workflow'    => $this->workflow,
            'current'     => $current,
            'myPosts'     => CadastralOfficer::postsFor(auth()->id()),
            'assignable'  => $this->userNames($assignable),
            'userNames'   => $this->userNames(array_merge([$report->assigned_user_id], $actorIds)),
            'application' => [
                'installed' => CadastralReport::applicationInstalled(),
                'blocked'   => $this->workflow->applicationBlockReason($report),
                'area'      => $report->appliedArea(),
                'refs'      => $report->applicationRefs(),
            ],
            // Pre-filled Land 12, which the officer opens rather than this
            // controller writing into survey_report_requests.
            'land12'   => $this->land12Link($report),
        ]);
    }

    /** users.id => display name, for the ids given (nulls ignored). */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name . ' ' . $u->last_name) ?: ('User #' . $u->id)])
            ->all();
    }

    /**
     * Start Report. The file is picked with the shared file picker and must
     * be registered at intake; ?receipt= (the intake queue's link) or
     * ?file_number= preloads it.
     */
    public function create(Request $r)
    {
        $receiptId = (int) (old('cadastral_file_receipt_id') ?: $r->query('receipt'));
        $picked = match (true) {
            $receiptId > 0                  => $this->lookup->resolveFile(['receipt' => $receiptId, 'scope' => 'receipt']),
            (bool) $r->query('file_number') => $this->lookup->resolveFile(['file_number' => (string) $r->query('file_number'), 'scope' => 'receipt']),
            default                         => null,
        };

        $receipt = ($picked['status'] ?? null) === 'ok'
            ? CadastralFileReceipt::find($picked['hidden']['cadastral_file_receipt_id'] ?? 0)
            : null;

        $report = new CadastralReport([
            'report_type' => $r->query('type', CadastralReport::TYPE_VERIFICATION),
            'file_number' => $receipt?->file_number,
            'file_title'  => $receipt?->file_title,
            'cadastral_file_receipt_id' => $receipt?->id,
            // A receipt keeps its plot in the builder's prop_plot; here it is plot_no.
            'plot_no'       => $receipt?->prop_plot,
            'status'        => 'Draft',
        ] + CadastralAddress::inherit($receipt, 'prop_'));

        return view('cadastral_module.reports.register', compact('report', 'picked'));
    }

    /**
     * Open a report on a registered intake file. The file number, owner, plot
     * and location come from the receipt (and the index behind it), whatever
     * was posted; only what they leave blank is taken from the form.
     */
    public function store(Request $r)
    {
        $receipt = $this->requireRegisteredReceipt($r->input('cadastral_file_receipt_id'), 'have a report opened on it');

        $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, [
            'file_number'               => $receipt->file_number,
            'cadastral_file_receipt_id' => $receipt->id,
        ]);

        $data = $this->validated($r);

        $report = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $data['report_ref'] = CadastralReport::nextRef('report_ref', 'CRP', 4);

            $report = CadastralReport::create($data);

            // The chain is seeded now, from config, and is this report's own
            // from here on.
            $this->workflow->seed($report);

            return $report;
        });

        $steps = $report->steps()->count();

        return redirect()
            ->route('cadastral-module.reports.show', $report)
            ->with('success', "{$report->type_label} report {$report->report_ref} opened with {$steps} steps.");
    }

    public function edit(CadastralReport $report)
    {
        // The file is fixed; the picker shows it locked, as update() re-reads it.
        $picked = $report->cadastral_file_receipt_id
            ? $this->lookup->resolveFile(['receipt' => $report->cadastral_file_receipt_id, 'scope' => 'receipt'])
            : null;

        return view('cadastral_module.reports.register', compact('report', 'picked'));
    }

    public function update(Request $r, CadastralReport $report)
    {
        if ($report->isFinished()) {
            return back()->with('error', "{$report->report_ref} is {$report->status} and can no longer be edited.");
        }

        // The file is fixed at opening: the number and receipt are the
        // report's own, and what the receipt supplies is re-read from it.
        $receipt = $report->cadastral_file_receipt_id ? CadastralFileReceipt::find($report->cadastral_file_receipt_id) : null;
        // report_type too: the edit form shows it disabled (so it never posts),
        // and the rules still require it.
        $fixed   = [
            'file_number'               => $report->file_number,
            'cadastral_file_receipt_id' => $report->cadastral_file_receipt_id,
            'report_type'               => $report->report_type,
        ];

        if ($receipt) {
            $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, $fixed);
        } else {
            $r->merge($fixed);
        }

        $data = $this->validated($r, $report);

        // The type decides the chain, and the chain is already seeded. Changing
        // it now would leave a verification report running a customary chain.
        unset($data['report_type']);

        $report->update($data);

        return back()->with('success', "{$report->report_ref} updated.");
    }

    /**
     * Assign the current step to a named officer. The officer must hold the
     * step's post; ReportWorkflow::assign() checks and audits.
     */
    public function assign(Request $r, CadastralReport $report)
    {
        $data = $r->validate(['assigned_user_id' => 'required|integer']);

        $result = $this->inTransaction(fn () => $this->workflow->assign($report, (int) $data['assigned_user_id']));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /* ------------------------------ the chain ------------------------------ */

    public function markStepDone(Request $r, CadastralReport $report, CadastralReportStep $step)
    {
        $note = $r->validate(['note' => 'nullable|string|max:4000'])['note'] ?? null;

        $result = $this->inTransaction(fn () => $this->workflow->markDone($report, $step, $note));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Return the report from this (its current) step to the previous one. A note is required. */
    public function markStepReturned(Request $r, CadastralReport $report, CadastralReportStep $step)
    {
        $data = $r->validate(['note' => 'required|string|max:4000'], [
            'note.required' => 'Say what has to be corrected — a return needs a note.',
        ]);

        $result = $this->inTransaction(fn () => $this->workflow->markReturned($report, $step, $data['note']));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function approve(Request $r, CadastralReport $report)
    {
        $note = $r->validate(['note' => 'nullable|string|max:4000'])['note'] ?? null;

        $result = $this->inTransaction(fn () => $this->workflow->decide($report, 'approve', $note));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $r, CadastralReport $report)
    {
        $data = $r->validate(['note' => 'required|string|max:4000'], [
            'note.required' => 'A reason is required when rejecting a report.',
        ]);

        $result = $this->inTransaction(fn () => $this->workflow->decide($report, 'reject', $data['note']));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Dispatch is the completion of the chain's last step, so it is refused
     * until approval is done — the old status check let a Checked report skip
     * approval.
     */
    public function markDispatched(Request $r, CadastralReport $report)
    {
        $data = $r->validate([
            'dispatched_to' => 'required|string|max:255',
            'note'          => 'nullable|string|max:3000',
        ], [
            'dispatched_to.required' => 'Say where the report is being dispatched to.',
        ]);

        $result = $this->inTransaction(fn () => $this->workflow->dispatch($report, $data['dispatched_to'], $data['note'] ?? null));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * A chain move touches the step, the next step and the report; all or none.
     * A refusal returns ok=false without writing, so it commits nothing.
     */
    private function inTransaction(callable $move): array
    {
        return DB::connection('sqlsrv')->transaction(fn () => $move());
    }

    /* --------------------- Report on Application (§3a) --------------------- */

    /**
     * Save the questionnaire. Filled on the Report step, by whoever may act on
     * it; the if-yes / if-no sub-fields are required on their branch and
     * cleared on the other, so the print never shows a tracing number under a
     * "NO".
     */
    public function saveApplication(Request $r, CadastralReport $report)
    {
        if ($reason = $this->workflow->applicationBlockReason($report)) {
            return back()->with('error', $reason);
        }

        $yn = ['nullable', Rule::in(['Yes', 'No'])];

        $data = $r->validate([
            'govt_item_no'               => 'nullable|string|max:100',
            'sltr_no'                    => 'nullable|string|max:100',
            'sit_no'                     => 'nullable|string|max:100',
            'q1_plan_sufficient'         => $yn,
            'q2_ground_open'             => $yn,
            'q2_overlapping_title'       => 'nullable|required_if:q2_ground_open,No|string|max:500',
            'q3_beaconed'                => $yn,
            'q3_tracing_no'              => 'nullable|required_if:q3_beaconed,Yes|string|max:100',
            'q3_deposition_plan_no'      => 'nullable|string|max:100',
            'q3_unapproved_town_plan_no' => 'nullable|string|max:100',
            'q3_layout_no'               => 'nullable|string|max:100',
            'q3_separate_survey'         => array_merge($yn, ['required_if:q3_beaconed,No']),
            'q4_town_plan'               => $yn,
            'q4_town_plan_no'            => 'nullable|required_if:q4_town_plan,Yes|string|max:100',
            'q4_shape_agrees'            => array_merge($yn, ['required_if:q4_town_plan,Yes']),
            'q4_purpose'                 => 'nullable|required_if:q4_town_plan,Yes|string|max:100',
            'q4_purpose_other'           => 'nullable|string|max:100',
            'q4_area_for_purpose'        => array_merge($yn, ['required_if:q4_town_plan,Yes']),
            'q5_previous_title'          => $yn,
            'q5_details'                 => 'nullable|required_if:q5_previous_title,Yes|string|max:1000',
            'q6_railway'                 => $yn,
            'q7_trunk_road'              => $yn,
            'area_applied_ha'            => 'nullable|numeric|min:0|max:99999999',
        ], [
            'q2_overlapping_title.required_if' => 'Q2 is No: say what title or application lies over the land.',
            'q3_tracing_no.required_if'        => 'Q3 is Yes: give the Tracing No.',
            'q3_separate_survey.required_if'   => 'Q3 is No: say whether a separate survey is required.',
            'q4_town_plan_no.required_if'      => 'Q4 is Yes: give the Town Plan No.',
            'q4_shape_agrees.required_if'      => 'Q4 is Yes: say whether the shape agrees with the Town Plan.',
            'q4_purpose.required_if'           => 'Q4 is Yes: give the purpose applied for.',
            'q4_area_for_purpose.required_if'  => 'Q4 is Yes: say whether the Town Plan shows the area for that purpose.',
            'q5_details.required_if'           => 'Q5 is Yes: give the details.',
        ]);

        // "Other" on the purpose select means the officer's own words.
        if (($data['q4_purpose'] ?? null) === 'Other') {
            $data['q4_purpose'] = trim((string) ($data['q4_purpose_other'] ?? '')) ?: null;

            if ($data['q4_purpose'] === null && ($data['q4_town_plan'] ?? null) === 'Yes') {
                return back()->withInput()->withErrors(['q4_purpose_other' => 'Q4(c): name the other purpose.']);
            }
        }
        unset($data['q4_purpose_other']);

        // The branch not taken is cleared, not kept: a stale answer under the
        // other branch would print as if it applied.
        if (($data['q2_ground_open'] ?? null) !== 'No') {
            $data['q2_overlapping_title'] = null;
        }
        if (($data['q3_beaconed'] ?? null) !== 'Yes') {
            $data['q3_tracing_no'] = $data['q3_deposition_plan_no'] = null;
        }
        if (($data['q3_beaconed'] ?? null) !== 'No') {
            $data['q3_unapproved_town_plan_no'] = $data['q3_layout_no'] = $data['q3_separate_survey'] = null;
        }
        if (($data['q4_town_plan'] ?? null) !== 'Yes') {
            $data['q4_town_plan_no'] = $data['q4_shape_agrees'] = $data['q4_purpose'] = $data['q4_area_for_purpose'] = null;
        }
        if (($data['q5_previous_title'] ?? null) !== 'Yes') {
            $data['q5_details'] = null;
        }

        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = trim($v) === '' ? null : trim($v);
            }
        }

        // Every column is written, so a cleared field really clears.
        $values = array_merge(array_fill_keys(CadastralReport::APPLICATION_COLUMNS, null), $data);

        $result = $this->inTransaction(fn () => $this->workflow->saveApplication($report, $values));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * The official "Report on Application – Cadastral Department" print: a
     * Blade copy of docs/templates/cadastral/Report-on-Application-Cadastral-
     * Department.html with its blanks filled. Read-only, so it works before the
     * questionnaire migration too — the derived blanks fill and the rest stay
     * as the paper form's empty lines.
     */
    public function applicationPrint(CadastralReport $report)
    {
        $report->load(['steps', 'receipt', 'chart']);

        $approval = $report->steps->firstWhere('step_key', 'approval');
        $area     = $report->appliedArea();

        return view('cadastral_module.reports.application_print', [
            'report'    => $report,
            'installed' => CadastralReport::applicationInstalled(),
            'refs'      => $report->applicationRefs(),
            'areaHa'    => $area['ha'],
            'areaAcres' => $area['ha'] === null ? null : round($area['ha'] * CadastralReport::ACRES_PER_HECTARE_AS_PRINTED, 4),
            // Signed only once approval is actually done.
            'approval'  => $approval && $approval->isDone() ? $approval : null,
            // The QR opens the report in KLAES; rendered locally (qr_data_uri),
            // nothing is sent to a third-party QR service.
            'qr'        => function_exists('qr_data_uri')
                ? qr_data_uri(route('cadastral-module.reports.show', $report), 140)
                : '',
        ]);
    }

    /* ---------------------------- the artefacts ---------------------------- */

    /**
     * Attach the scanned trimsheet/topsheet the chain's third step asks for.
     *
     * Two ways, both kept: upload the scan itself — filed into the file's EDMS
     * folder as a page (CadastralDocuments), superseding any earlier manual
     * chart for this report — or type the path of a scan already on disk, the
     * Phase 4 fallback, stored on the chart. At least one is required.
     */
    public function storeManualChart(Request $r, CadastralReport $report, CadastralDocuments $documents)
    {
        $data = $r->validate([
            'manual_chart_path'  => 'nullable|required_without:document|string|max:500',
            'cadastral_chart_id' => 'nullable|integer',
            'document'           => array_merge(['nullable'], CadastralDocuments::fileRule()),
        ], [
            'manual_chart_path.required_without' => 'Upload the scanned chart, or type the path of a scan already on disk.',
        ] + CadastralDocuments::messages('document'));

        $upload = $r->file('document');

        if ($upload && ! CadastralDocuments::installed()) {
            return back()->with('error', 'Document upload is pending installation (the Phase 5 migration). Type the scan path instead.');
        }

        $chart = null;

        if (! empty($data['cadastral_chart_id'])) {
            $chart = CadastralChart::find($data['cadastral_chart_id']);

            if (! $chart) {
                return back()->with('error', 'That chart does not exist.');
            }

            if ($chart->file_number !== $report->file_number) {
                // The one mistake that silently attaches a parcel to the wrong file.
                return back()->with('error',
                    "That chart is for {$chart->file_number}, not {$report->file_number}.");
            }
        }

        // Refused before anything is written if the file has no EDMS folder.
        $indexing = $upload
            ? $documents->requireIndexing($report->receipt?->file_indexing_id, $report->file_number, 'document')
            : null;

        try {
            DB::connection('sqlsrv')->transaction(function () use ($report, $chart, $data, $upload, $indexing, $documents) {
                if ($chart) {
                    $report->update(['cadastral_chart_id' => $chart->id]);

                    if (! empty($data['manual_chart_path'])) {
                        $chart->update(['manual_chart_path' => $data['manual_chart_path']]);
                    }
                }

                if ($upload) {
                    $documents->file(
                        $upload,
                        $indexing,
                        CadastralDocuments::OWNER_REPORT,
                        $report->id,
                        CadastralDocuments::KIND_MANUAL_CHART,
                        "Cadastral manual chart for report {$report->report_ref}."
                    );
                }
            });
        } catch (\Throwable $e) {
            // Disk writes do not roll back with the transaction.
            $documents->discardWritten();
            throw $e;
        }

        return back()->with('success', $upload
            ? 'Manual chart filed to the file\'s EDMS folder; it will appear in Page Typing.'
            : 'Manual chart recorded.');
    }

    public function storeInspection(Request $r, CadastralReport $report)
    {
        if ($report->report_type !== CadastralReport::TYPE_VERIFICATION) {
            return back()->with('error',
                "{$report->type_label} reports have no field inspection step — only verification does.");
        }

        $data = $this->inspectionRules($r);

        $data['cadastral_report_id'] = $report->id;
        $data['inspection_ref']      = 'INS-' . $report->report_ref;

        CadastralSiteInspection::create($data);

        return back()->with('success', 'Field inspection recorded.');
    }

    public function updateInspection(Request $r, CadastralReport $report, CadastralSiteInspection $inspection)
    {
        if ($inspection->cadastral_report_id !== $report->id) {
            return back()->with('error', 'That inspection belongs to another report.');
        }

        $inspection->update($this->inspectionRules($r));

        return back()->with('success', 'Field inspection updated.');
    }

    public function print(CadastralReport $report)
    {
        return view('cadastral_module.reports.print', [
            'report'   => $report->load(['steps', 'inspections', 'chart']),
            'progress' => $this->workflow->progress($report),
        ]);
    }

    /** CSV of the current filter, for the monthly returns. */
    public function export(Request $r)
    {
        $q = CadastralReport::query();

        if ($t = $r->query('report_type')) $q->where('report_type', $t);
        if ($s = $r->query('status'))      $q->where('status', $s);

        $rows = $q->orderByDesc('id')->limit(5000)->get();

        $columns = ['report_ref', 'report_type', 'file_number', 'file_title', 'status',
            'current_step_key', 'assigned_post', 'due_date', 'dispatched_at', 'created_at'];

        $callback = function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => (string) $row->{$c}, $columns));
            }

            fclose($out);
        };

        return response()->streamDownload($callback, 'cadastral-reports-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * A pre-filled Land 12 URL rather than a row written into
     * survey_report_requests. That table holds real records, has no migration,
     * and is owned by SurveyReportController.
     */
    private function land12Link(CadastralReport $report): ?string
    {
        if (! \Illuminate\Support\Facades\Route::has('survey-report.create')) {
            return null;
        }

        return route('survey-report.create', array_filter([
            'file_number'      => $report->file_number,
            'plot_description' => $report->plot_description,
            'survey_necessary' => $report->survey_necessary,
            'beacon_numbers'   => $report->beacon_numbers,
        ]));
    }

    private function inspectionRules(Request $r): array
    {
        return $r->validate([
            'inspected_on'          => 'nullable|date',
            'field_officer_name'    => 'nullable|string|max:255',
            'gps_latitude'          => 'nullable|numeric|between:-90,90',
            'gps_longitude'         => 'nullable|numeric|between:-180,180',
            'gps_accuracy_m'        => 'nullable|numeric|min:0',
            'development_status'    => 'nullable|string|max:100',
            'occupancy_status'      => 'nullable|string|max:100',
            'access_road'           => 'nullable|string|max:100',
            'encroachment'          => 'nullable|boolean',
            'encroachment_note'     => 'nullable|string|max:1000',
            'ground_findings'       => 'nullable|string|max:8000',
            'chart_findings'        => 'nullable|string|max:8000',
            'photo_path'            => 'nullable|string|max:500',
            'sketch_path'           => 'nullable|string|max:500',
        ]);
    }

    private function validated(Request $r, ?CadastralReport $existing = null): array
    {
        $rules = [
            'report_type'      => ['required', Rule::in(array_keys(CadastralReport::TYPES))],
            'file_number'      => 'required|string|max:100',
            'file_title'       => 'nullable|string|max:500',
            'plot_no'          => 'nullable|string|max:50',
            'block_no'         => 'nullable|string|max:50',
            'layout_name'      => 'nullable|string|max:255',
            'cadastral_file_receipt_id' => 'nullable|integer',
            'cadastral_chart_id'        => 'nullable|integer',
            'ground_status'    => 'nullable|string|max:8000',
            'chart_status'     => 'nullable|string|max:8000',
            'survey_necessary' => ['nullable', Rule::in(['Yes', 'No'])],
            'beacon_numbers'   => 'nullable|string|max:500',
            'plot_description' => 'nullable|string|max:8000',
            'due_date'         => 'nullable|date',
            'dispatched_to'    => 'nullable|string|max:255',
        ] + $this->addressRules($r, 'prop_');

        // plot_no is the report's plot field; prop_plot follows it.
        $data = CadastralAddress::normalise(
            $r->validate($rules, $this->addressMessages('prop_')),
            'prop_',
            'plot_no'
        );

        // A chart named by id must be a chart of this file.
        if (! empty($data['cadastral_chart_id'])) {
            $chart = CadastralChart::find($data['cadastral_chart_id']);

            if (! $chart || $chart->file_number !== $data['file_number']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cadastral_chart_id' => $chart
                        ? "Chart {$chart->chart_ref} is for {$chart->file_number}, not {$data['file_number']}."
                        : 'That chart does not exist.',
                ]);
            }
        }

        return $data;
    }
}
