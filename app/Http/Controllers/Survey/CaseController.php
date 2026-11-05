<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use App\Models\Survey\SurveyTreeType;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compensation cases, including the six-step register.
 *
 * A case inherits scheme_type from its project and may never mix schemes.
 * The register posts every step in one request (steps are shown and hidden on
 * the client), so the whole form is re-validated here.
 *
 * Where the project has surveyors assigned, a case is the form for one portion
 * of one surveyor's allocation: the portion number must be free and within the
 * allocation, and the portion size is copied from the allocation (2026-10-25).
 */
class CaseController extends Controller
{
    /** Values the status column carries. 'Review' is shown as "Pending Review". */
    public const STATUSES = ['Pending', 'Active', 'Review', 'Completed', 'Rejected'];

    public const STATUS_DRAFT  = 'Pending';
    public const STATUS_REVIEW = 'Review';

    /** Statuses a beneficiary row on the register may carry. */
    private const BEN_STATUSES = ['Verified', 'Pending', 'Review'];

    public function index(Request $r)
    {
        // The Plot Showing Register view (?view=showing) stays blank (user, 2026-11-02; the sidebar now opens
        // Plot Confirmation). Farm Assessment's own list is back (user, 2026-11-03).
        if ($r->query('view') === 'showing') {
            return view('survey_module.compensation.cases', ['blank' => true, 'detail' => null]);
        }

        $q = SurveyCompCase::query()->with(['project', 'allocation', 'creator:id,first_name,last_name'])->withCount('beneficiaries');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('case_ref', 'like', "%$term%")
                  ->orWhere('survey_officer', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%")
                  ->orWhere('prop_lga', 'like', "%$term%")
                  ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%$term%")
                                                       ->orWhere('project_code', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status'))  $q->where('status', $st);
        if ($p  = $r->query('project')) $q->where('survey_project_id', $p);
        if ($s  = $r->query('scheme'))  $q->where('scheme_type', $s);
        // Two tabs (CR 30102026 item 23, user 2026-10-31): Imported (from the spreadsheets) and
        // Captured (registered or keyed in from a paper list). Each tab lists, and counts, its own.
        $tab = in_array($r->query('origin'), ['imported', 'entered'], true) ? $r->query('origin') : 'imported';
        $q->origin($tab);

        $cases = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $mine = fn () => SurveyCompCase::query()->origin($tab);
        $stats = [
            'total'     => $mine()->count(),
            'active'    => $mine()->where('status', 'Active')->count(),
            'review'    => $mine()->where('status', self::STATUS_REVIEW)->count(),
            'completed' => $mine()->where('status', 'Completed')->count(),
        ];

        return view('survey_module.compensation.cases', [
            'cases'    => $cases,
            'stats'    => $stats,
            'tab'      => $tab,
            'tabCounts' => ['imported' => SurveyCompCase::query()->origin('imported')->count(), 'entered' => SurveyCompCase::query()->origin('entered')->count()],
            'projects' => $this->projectOptions(),
            'detail'   => null,
        ]);
    }

    /**
     * Read-only drill-down. Rendered by the cases section partial rather than a
     * view of its own, so the list and the detail stay together.
     */
    public function show(SurveyCompCase $case)
    {
        $case->load(['project', 'allocation', 'beneficiaries', 'trees', 'opRecords', 'photos']);

        return view('survey_module.compensation.cases', [
            'detail' => $case,
            // The 50:50 numbers always come from the model.
            'split'  => $case->isMonetary() ? null : $case->plotSplit(),
        ]);
    }

    public function create(Request $r)
    {
        // Carried over from "Register a case under this project", and again on
        // the redirect back from a failed submission.
        $projectId = $r->old('survey_project_id', $r->query('project'));
        $project   = $projectId ? SurveyProject::find($projectId) : null;

        $case = new SurveyCompCase([
            'survey_project_id'   => $project?->id,
            'survey_project_surveyor_id' => $r->old('survey_project_surveyor_id', $r->query('allocation')),
            'portion_no'          => $r->old('portion_no', $r->query('portion')),
            'scheme_type'         => $project?->scheme_type,
            'purpose'             => $project?->purpose,
            'status'              => self::STATUS_DRAFT,
            'case_date'           => now()->toDateString(),
            'survey_officer'      => Auth::user()?->name,
            // A case usually sits inside its project's area; still editable.
            'prop_district'       => $project?->prop_district,
            'prop_district_other' => $project?->prop_district_other,
            'prop_lga'            => $project?->prop_lga,
            'prop_state'          => $project?->prop_state ?? 'Kano',
        ]);

        return view('survey_module.compensation.case_register', $this->formData($case));
    }

    public function store(Request $r)
    {
        [, $msg, $error] = $this->registerCase($r);

        return redirect()
            ->route('survey-module.compensation.cases')
            ->with($error ? 'error' : 'success', $msg);
    }

    /**
     * Validate and save a new case, submitting it too when submit_now is set.
     * Shared with the mobile register (MobileCaseController).
     *
     * @return array{0: SurveyCompCase, 1: string, 2: bool} the case, the message,
     *         and whether the message reports a refused submission
     */
    protected function registerCase(Request $r): array
    {
        $data    = $this->validated($r);
        $project = SurveyProject::findOrFail($data['survey_project_id']);

        $benRows  = $data['beneficiaries'] ?? [];
        $treeRows = $data['trees'] ?? [];
        unset($data['beneficiaries'], $data['trees']);

        // The scheme is the project's. It is never read from the request, so a
        // forged scheme_type field cannot put a case out of step with its project.
        $data['scheme_type'] = $project->scheme_type;
        $isMonetary = $project->scheme_type === SurveyProject::SCHEME_MONETARY;

        // Exclusive schemes: monetary allocates no plots, land pays no tree cash.
        if ($isMonetary) $data['num_plots'] = null;
        else             $treeRows = [];

        $data = $this->applyPortion($data, $project);

        $case = $this->portionSafe(fn () => DB::connection('sqlsrv')->transaction(function () use ($data, $benRows, $treeRows) {
            $data['case_ref'] = SurveyCompCase::nextRef('case_ref', 'C');
            $case = SurveyCompCase::create($data);

            $this->syncBeneficiaries($case, $benRows);
            $this->syncTrees($case, $treeRows);

            return $case;
        }));

        $msg = "Farm Assessment {$case->case_ref} registered under {$project->project_code} · scheme inherited: "
             . $this->schemeLabel($project->scheme_type) . '.';

        if ($r->boolean('submit_now')) {
            if ($problem = $this->submissionProblem($case)) {
                return [$case, "{$msg} It was saved as a draft and not submitted: {$problem}", true];
            }
            $case->update(['status' => self::STATUS_REVIEW]);
            $this->tellFarmers($case);
            $msg .= ' Submitted — now Pending Review.';
        }

        return [$case, $msg, false];
    }

    public function edit(SurveyCompCase $case)
    {
        $case->load(['beneficiaries', 'trees']);

        return view('survey_module.compensation.case_register', $this->formData($case));
    }

    public function update(Request $r, SurveyCompCase $case)
    {
        $data    = $this->validated($r, $case);
        $project = SurveyProject::findOrFail($data['survey_project_id']);

        // A case may be moved between projects, but only within its own scheme:
        // re-inheriting a different one would silently reinterpret its trees or plots.
        if ($project->scheme_type !== $case->scheme_type) {
            throw ValidationException::withMessages([
                'survey_project_id' => "{$project->project_code} is a "
                    . $this->schemeLabel($project->scheme_type) . " project, but {$case->case_ref} was registered as "
                    . $this->schemeLabel($case->scheme_type)
                    . '. A farm assessment cannot change scheme — capture a new one under that project instead.',
            ]);
        }

        $benRows  = $data['beneficiaries'] ?? [];
        $treeRows = $data['trees'] ?? [];
        unset($data['beneficiaries'], $data['trees']);

        $data['scheme_type'] = $project->scheme_type;   // still the project's, never the request's
        $isMonetary = $case->isMonetary();

        if ($isMonetary) $data['num_plots'] = null;
        else             $treeRows = [];

        $data = $this->applyPortion($data, $project, $case);

        $this->portionSafe(fn () => DB::connection('sqlsrv')->transaction(function () use ($case, $data, $benRows, $treeRows) {
            $case->update($data);
            $this->syncBeneficiaries($case, $benRows);
            $this->syncTrees($case, $treeRows);
        }));

        return redirect()
            ->route('survey-module.compensation.cases')
            ->with('success', "Farm Assessment {$case->case_ref} updated.");
    }

    public function destroy(SurveyCompCase $case)
    {
        $blockers = [];
        if ($n = $case->beneficiaries()->count()) $blockers[] = "{$n} beneficiary record(s)";
        if ($n = $case->trees()->count())         $blockers[] = "{$n} economic tree line(s)";
        if ($n = $case->plots()->count())         $blockers[] = "{$n} plot allocation(s)";
        if ($n = $case->opRecords()->count())     $blockers[] = "{$n} occupancy permit record(s)";

        if ($blockers) {
            return back()->with('error',
                "{$case->case_ref} still has " . implode(', ', $blockers) . ' and cannot be deleted. '
                . 'Detach or remove those first, or set the farm assessment status to Rejected instead.');
        }

        $ref = $case->case_ref;
        $case->delete();

        return back()->with('success', "Farm Assessment {$ref} deleted.");
    }

    /** Farm Capture Confirmation Sheet for one farmer (CR 03112026 item 8). */
    public function farmSheet(SurveyCompCase $case, int $farm)
    {
        $row = $case->beneficiaries()->findOrFail($farm);

        return view('survey_module.compensation.farm_capture_sheet', [
            'case' => $case->load(['project', 'trees']), 'farm' => $row,
            'farmer' => $row->survey_farmer_id ? \App\Models\Survey\SurveyFarmer::find($row->survey_farmer_id) : null,
        ]);
    }

    /** "Is this the existing farmer?" on Add Farmer (recording, 2026-11-05). */
    public function farmerLookup(Request $r)
    {
        return response()->json(app(\App\Services\Survey\FarmIds::class)->matches(
            (string) $r->query('name', ''), (string) $r->query('phone', ''), (string) $r->query('nin', '')));
    }

    /** The confirmation SMS, once the assessment is submitted (item 8). Placeholder phones are skipped by the dispatcher. */
    protected function tellFarmers(SurveyCompCase $case): void
    {
        $sms = app(\App\Services\Sms\KlaesSmsDispatcher::class);
        $farmers = \App\Models\Survey\SurveyFarmer::whereIn('id', $case->beneficiaries()->whereNotNull('survey_farmer_id')->pluck('survey_farmer_id'))->pluck('farmer_id_no', 'id');

        foreach ($case->beneficiaries()->whereNotNull('farm_id_no')->get() as $b) {
            $sms->queue(\App\Models\SmsSetting::KEY_SURVEY_FARM_CAPTURED, $b->phone, [
                'Name' => $b->full_name, 'FarmerId' => (string) ($farmers[$b->survey_farmer_id] ?? ''), 'FarmId' => $b->farm_id_no,
                'Section' => (string) $case->section_no, 'FarmNo' => (string) $b->farm_no,
            ], ['dedupe_key' => 'farm-capture:' . $b->id]);
        }
    }

    /** The Preview step's Submit button posts here; moves the case to Pending Review. */
    public function submit(Request $r, SurveyCompCase $case)
    {
        if ($case->status === self::STATUS_REVIEW) {
            return back()->with('error', "{$case->case_ref} is already Pending Review.");
        }
        if ($problem = $this->submissionProblem($case)) {
            return back()->with('error', "{$case->case_ref} cannot be submitted: {$problem}");
        }

        $case->update(['status' => self::STATUS_REVIEW]);
        $this->tellFarmers($case);

        return redirect()
            ->route('survey-module.compensation.cases')
            ->with('success', "Farm Assessment {$case->case_ref} submitted — now Pending Review. "
                . 'It moves through the OP workflow: Survey → GIS → Commissioner → Deeds → Land/OSS.');
    }

    /**
     * Land to Land cases only, with the 50:50 split. Monetary Compensation cases allocate no
     * plots, so they are excluded outright rather than listed with zeros.
     */
    public function landAllocation(Request $r)
    {
        $q = SurveyCompCase::query()
            ->where('scheme_type', SurveyProject::SCHEME_LAND)
            ->with(['project', 'creator:id,first_name,last_name'])
            ->withCount(['plots', 'beneficiaries']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('case_ref', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%")
                  ->orWhere('prop_lga', 'like', "%$term%")
                  ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status'))  $q->where('status', $st);
        if ($p  = $r->query('project')) $q->where('survey_project_id', $p);
        $q->origin($r->query('origin'));

        $cases = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // plotSplit() owns the formula (odd plot to Government); nothing here re-derives it.
        $splits = [];
        foreach ($cases as $c) $splits[$c->id] = $c->plotSplit();

        // Totals cover every land case, not just this page. One COUNT per case is
        // acceptable at this register's size and keeps the split in one place.
        $totals = ['cases' => 0, 'plots' => 0, 'farmer' => 0, 'govt' => 0];
        foreach (SurveyCompCase::where('scheme_type', SurveyProject::SCHEME_LAND)->get() as $c) {
            $s = $c->plotSplit();
            $totals['cases']++;
            $totals['plots']  += $s['total'];
            $totals['farmer'] += $s['farmer'];
            $totals['govt']   += $s['govt'];
        }

        return view('survey_module.compensation.land', [
            'cases'    => $cases,
            'splits'   => $splits,
            'totals'   => $totals,
            'projects' => $this->projectOptions(SurveyProject::SCHEME_LAND),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** Everything the six-step register needs, for both create and edit. */
    protected function formData(SurveyCompCase $case): array
    {
        // Active projects, plus whichever project this case already belongs to
        // even if that project has since been closed.
        $projects = SurveyProject::where('status', 'Active')
            ->when($case->survey_project_id, fn ($q) => $q->orWhere('id', $case->survey_project_id))
            ->orderBy('name')
            ->get();

        return [
            'case'      => $case,
            'projects'  => $projects,
            'portions'  => $this->portionOptions($projects, $case),
            'treeTypes' => SurveyTreeType::where('status', 'Active')->orderBy('name')->get(),
            // Only meaningful once the case exists and is land-to-land.
            'split'     => ($case->exists && ! $case->isMonetary()) ? $case->plotSplit() : null,
        ];
    }

    /**
     * Per project, its surveyor allocations and which portion numbers are still
     * free, for the portion picker. The case being edited keeps its own portion.
     */
    protected function portionOptions($projects, SurveyCompCase $case): array
    {
        $allocs = SurveyProjectSurveyor::whereIn('survey_project_id', $projects->pluck('id'))
            ->orderBy('surveyor_name')
            ->get();

        $out = [];
        foreach ($allocs as $a) {
            $used = $a->usedPortions($case->exists ? $case->id : null);
            $out[$a->survey_project_id][] = [
                'id'       => $a->id,
                'name'     => $a->surveyor_name,
                'portions' => $a->portions,
                'size'     => (float) $a->portion_size_ha,
                'free'     => array_values(array_diff(range(1, max(1, $a->portions)), $used)),
            ];
        }

        return $out;
    }

    /**
     * Check the chosen portion against the project's allocations and copy the
     * portion size from the allocation. Projects with no surveyors assigned
     * (older ones) take cases without a portion.
     */
    protected function applyPortion(array $data, SurveyProject $project, ?SurveyCompCase $case = null): array
    {
        $allocId = $data['survey_project_surveyor_id'] ?? null;

        if (! $allocId) {
            if ($project->surveyors()->exists()) {
                throw ValidationException::withMessages([
                    'survey_project_surveyor_id' => "{$project->project_code} has surveyors assigned. Select the surveyor and the portion this farm assessment surveys (Step 1).",
                ]);
            }
            $data['survey_project_surveyor_id'] = null;
            $data['portion_no'] = null;
            $data['portion_size_ha'] = null;

            return $data;
        }

        $alloc = SurveyProjectSurveyor::where('survey_project_id', $project->id)->find($allocId);
        if (! $alloc) {
            throw ValidationException::withMessages([
                'survey_project_surveyor_id' => "That surveyor is not assigned to {$project->project_code}.",
            ]);
        }

        $no = (int) ($data['portion_no'] ?? 0);
        if ($no < 1 || $no > $alloc->portions) {
            throw ValidationException::withMessages([
                'portion_no' => "{$alloc->surveyor_name} has portions 1–{$alloc->portions} on this project. Pick one of those.",
            ]);
        }
        if (in_array($no, $alloc->usedPortions($case?->id), true)) {
            throw ValidationException::withMessages([
                'portion_no' => "Portion {$no} of {$alloc->surveyor_name}'s allocation already has a farm assessment.",
            ]);
        }

        $data['portion_no'] = $no;
        // Never the client's: the portion's size is the allocation's.
        $data['portion_size_ha'] = $alloc->portion_size_ha;

        return $data;
    }

    /**
     * Run a case save; if another officer took the same portion a moment earlier, the
     * unique index refuses it, and that becomes a form error rather than a crash.
     */
    protected function portionSafe(callable $save)
    {
        try {
            return $save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_survey_cases_portion_live')) {
                throw ValidationException::withMessages([
                    'portion_no' => 'Someone registered this portion a moment ago. Pick another portion.',
                ]);
            }
            throw $e;
        }
    }

    /** Projects for the list filters. */
    private function projectOptions(?string $scheme = null)
    {
        return SurveyProject::when($scheme, fn ($q) => $q->where('scheme_type', $scheme))
            ->orderBy('name')
            ->get(['id', 'name', 'project_code', 'scheme_type']);
    }

    protected function schemeLabel(?string $type): string
    {
        // The ratio is the project's (CR 30102026 item 11), so the scheme alone does not name one.
        return $type === SurveyProject::SCHEME_LAND ? 'Land to Land' : 'Monetary Compensation';
    }

    /** Why this case may not be submitted yet, or null when it may. */
    protected function submissionProblem(SurveyCompCase $case): ?string
    {
        if (! $case->beneficiaries()->exists()) {
            return 'it has no beneficiaries (Step 2).';
        }
        if ($case->isMonetary() && ! $case->trees()->exists()) {
            return 'a Monetary Compensation assessment needs at least one economic tree line (Step 4).';
        }
        if (! $case->isMonetary() && (int) $case->num_plots < 1 && ! $case->plots()->exists()) {
            return 'a Land to Land assessment needs a plot count for the 50:50 split (Step 3).';
        }

        return null;
    }

    /**
     * Validate the whole register.
     *
     * The scheme decides which step is mandatory, and it is read from the project
     * (or from the case being edited) — never from the posted scheme_type, which
     * is ignored entirely.
     */
    protected function validated(Request $r, ?SurveyCompCase $existing = null): array
    {
        // Blank template rows the user never filled in must not fail validation.
        $r->merge([
            'beneficiaries' => $this->pruneRows($r->input('beneficiaries'), 'full_name'),
            'trees'         => $this->pruneRows($r->input('trees'), 'tree_type'),
        ]);

        $scheme = $existing?->scheme_type
            ?? SurveyProject::find($r->input('survey_project_id'))?->scheme_type;
        $isMonetary = $scheme !== SurveyProject::SCHEME_LAND;

        $rules = [
            'survey_project_id' => ['required', Rule::exists('sqlsrv.survey_projects', 'id')->whereNull('deleted_at')],
            'purpose'           => 'nullable|string|max:100',
            'survey_project_surveyor_id' => 'nullable|integer',
            'portion_no'        => 'nullable|integer|min:1',
            'survey_officer'    => 'required|string|max:255',
            'case_date'         => 'required|date',
            'area_ha'           => 'required|numeric|min:0.01|max:9999999999',
            'coordinates'       => 'nullable|string|max:255',
            'gps_reading'       => 'nullable|string|max:255',
            'num_plots'         => $isMonetary ? 'nullable|integer|min:0' : 'required|integer|min:1|max:1000000',
            'boundary_file'     => 'nullable|string|max:500',
            'description'       => 'nullable|string|max:4000',
            'status'            => ['required', Rule::in(self::STATUSES)],

            'beneficiaries'             => 'nullable|array',
            'beneficiaries.*.id'        => 'nullable|integer',
            'beneficiaries.*.full_name' => 'required|string|max:255',
            'beneficiaries.*.phone'     => 'nullable|string|max:50',
            'beneficiaries.*.nin'       => 'nullable|string|max:20',
            'beneficiaries.*.status'    => ['required', Rule::in(self::BEN_STATUSES)],
            // An existing farmer picked on the form, and the farm of theirs being recaptured (CR 03112026).
            'beneficiaries.*.survey_farmer_id' => 'nullable|integer',
            'beneficiaries.*.farm_id_no'       => 'nullable|string|max:20',

            // Step 4 is the valuation basis for a monetary case, so it is required
            // there. For land-to-land it is not applicable and is dropped instead.
            'trees'              => $isMonetary ? 'required|array|min:1' : 'nullable|array',
            'trees.*.id'         => 'nullable|integer',
            'trees.*.tree_type'  => 'required|string|max:255',
            'trees.*.maturity'   => ['nullable', Rule::in(SurveyTreeType::MATURITIES)],
            'trees.*.quantity'   => 'required|integer|min:1',
            'trees.*.unit_price' => 'required|numeric|min:0|max:99999999999',
        ] + AddressBuilder::rules('prop_');

        return $r->validate($rules, [
            'survey_project_id.required'         => 'Select the project this farm assessment belongs to (Step 1).',
            'survey_project_id.exists'           => 'That project does not exist.',
            'trees.required'                     => 'A Monetary Compensation assessment needs at least one economic tree line (Step 4).',
            'trees.min'                          => 'A Monetary Compensation assessment needs at least one economic tree line (Step 4).',
            'num_plots.required'                 => 'A Land to Land assessment needs the total number of plots for the 50:50 split (Step 3).',
            'beneficiaries.*.full_name.required' => 'Every beneficiary row needs a full name (Step 2).',
            'beneficiaries.*.status.required'    => 'Every beneficiary row needs a status (Step 2).',
            'trees.*.tree_type.required'         => 'Every tree line needs a tree type (Step 4).',
            'trees.*.quantity.required'          => 'Every tree line needs a quantity (Step 4).',
            'trees.*.unit_price.required'        => 'Every tree line needs a unit price (Step 4).',
            'prop_district.required'             => 'The district is required.',
            'prop_district_other.required_if'    => 'Please specify the district.',
            'prop_street_other.required_if'      => 'Please specify the street.',
            'prop_lga.required'                  => 'The LGA is required.',
            'prop_state.required'                => 'The state is required.',
        ]);
    }

    /** Drop repeated rows whose key field is blank. */
    protected function pruneRows($rows, string $key): array
    {
        $out = [];
        foreach ((array) $rows as $row) {
            if (! is_array($row)) continue;
            if (trim((string) ($row[$key] ?? '')) === '') continue;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Beneficiaries the register sent. A row dropped from the form is detached
     * from the case rather than deleted — the person is still a real record and
     * stays in the beneficiaries register.
     */
    protected function syncBeneficiaries(SurveyCompCase $case, array $rows): void
    {
        $keep = [];
        $ids = app(\App\Services\Survey\FarmIds::class);

        // Section No: the surveyor's next, once, when the assessment first has a farmer (CR 03112026 item 5).
        if ($rows && ! $case->section_no && trim((string) $case->survey_officer) !== '') {
            $case->update(['section_no' => $ids->nextSection((string) $case->survey_officer)]);
        }

        foreach ($rows as $row) {
            $attrs = [
                'full_name' => trim((string) $row['full_name']),
                'phone'     => ($row['phone']  ?? null) ?: null,
                'nin'       => ($row['nin']    ?? null) ?: null,
                'status'    => ($row['status'] ?? null) ?: 'Pending',
            ];

            $existing = ! empty($row['id']) ? $case->beneficiaries()->find($row['id']) : null;

            if ($existing) {
                $existing->update($attrs);
                $keep[] = $existing->id;
            } else {
                // Farmer ID, Farm ID and Farm No (items 6-7), on the server so a phone gets them when it syncs.
                $keep[] = $case->beneficiaries()->create($attrs + $ids->assign($case, $row))->id;
            }
        }

        $case->beneficiaries()
            ->when($keep, fn ($q) => $q->whereNotIn('id', $keep))
            ->update(['survey_comp_case_id' => null]);
    }

    /**
     * Tree lines. line_total is recalculated by SurveyCaseTree on save, so the
     * posted total is ignored. A line dropped from the form is deleted: it only
     * ever existed as part of this case's valuation.
     */
    protected function syncTrees(SurveyCompCase $case, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $name = trim((string) $row['tree_type']);

            $attrs = [
                'tree_type'           => $name,
                'maturity'            => $row['maturity'] ?? SurveyTreeType::MATURE,
                'quantity'            => (int) $row['quantity'],
                'unit_price'          => (float) $row['unit_price'],
                // Link to the catalogue row for this tree and Category, for reporting.
                'survey_tree_type_id' => SurveyTreeType::findFor($name, $row['maturity'] ?? null)?->id,
            ];

            $existing = ! empty($row['id']) ? $case->trees()->find($row['id']) : null;

            if ($existing) {
                $existing->update($attrs);
                $keep[] = $existing->id;
            } else {
                $keep[] = $case->trees()->create($attrs)->id;
            }
        }

        $case->trees()->when($keep, fn ($q) => $q->whereNotIn('id', $keep))->delete();
    }
}
