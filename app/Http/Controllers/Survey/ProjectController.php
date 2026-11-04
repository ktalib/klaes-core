<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyProject;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compensation projects. The scheme type is chosen here and then locked:
 * cases inherit it, so changing it later would silently reinterpret existing
 * cases. Editing is therefore allowed on everything except scheme_type once
 * the project has cases.
 *
 * A project sits in a CO file (one CO file, many projects) and carries its size
 * of acquisition and the surveyors working it, each with a number of portions
 * and a size per portion (user, 2026-10-25). Each portion is surveyed on its
 * own case form.
 */
class ProjectController extends Controller
{
    public function index(Request $r)
    {
        $q = SurveyProject::query()->with(['coFile', 'surveyors', 'creator:id,first_name,last_name', 'trees:id,survey_project_id,amount',
                // Each project's lands, listed under its row (user, 2026-11-02).
                'cases' => fn ($c) => $c->select(['id', 'case_ref', 'survey_project_id', 'survey_officer', 'portion_no', 'area_ha', 'num_plots',
                    'prop_plot', 'prop_district', 'prop_district_other', 'prop_lga', 'prop_state', 'status'])
                    ->withCount('beneficiaries')->orderBy('portion_no')->orderBy('id')])
            ->withCount('cases');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%$term%")
                  ->orWhere('project_code', 'like', "%$term%")
                  ->orWhere('plan_no', 'like', "%$term%");
            });
        }
        if ($s = $r->query('scheme')) $q->where('scheme_type', $s);
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($co = $r->query('co_file')) $q->where('survey_co_file_id', $co);
        $q->origin($r->query('origin'));

        $projects = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => SurveyProject::count(),
            'monetary' => SurveyProject::where('scheme_type', SurveyProject::SCHEME_MONETARY)->count(),
            'land'     => SurveyProject::where('scheme_type', SurveyProject::SCHEME_LAND)->count(),
            'active'   => SurveyProject::where('status', 'Active')->count(),
        ];

        $coFiles = SurveyCoFile::orderByDesc('id')->get(['id', 'co_file_no', 'title']);

        return view('survey_module.compensation.projects', compact('projects', 'stats', 'coFiles'));
    }

    public function create(Request $r)
    {
        $project = new SurveyProject([
            'scheme_type'       => SurveyProject::SCHEME_MONETARY,
            'state'             => 'Kano',
            'survey_co_file_id' => $r->query('co_file'),
        ]);

        return view('survey_module.compensation.project_register', $this->formData($project));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $rows = $data['surveyors'] ?? [];
        $trees = $data['trees'] ?? [];
        unset($data['surveyors'], $data['trees']);

        $project = DB::connection('sqlsrv')->transaction(function () use ($data, $rows, $trees) {
            $data['project_code'] = SurveyProject::nextRef('project_code', 'PRJ');
            $project = SurveyProject::create($data);
            $this->syncSurveyors($project, $rows);
            $this->syncTrees($project, $trees);

            return $project;
        });

        return redirect()
            ->route('survey-module.compensation.projects')
            ->with('success', "Project {$project->project_code} created. Scheme is locked to {$project->scheme_label}.");
    }

    public function edit(Request $r, SurveyProject $project)
    {
        $project->load('surveyors', 'trees');

        // Under the Update form: the project's records, Farmer > Farm > Land (plots) > OP (user, 2026-11-03).
        return view('survey_module.compensation.project_register', $this->formData($project) + [
            'records' => $this->records($project, $r),
        ]);
    }

    /**
     * The records captured under a project, grouped by farmer (user, 2026-11-03: "records captured under
     * that project grouped by the farmer; expand the farmer and his farms come"). A farm is one allocation
     * line (F/No + area) on one of the project's lands; its plot numbers are the land produced, and its
     * OP No. the OP. Farmers are matched by name, case and spacing ignored. 25 farmers a page, searchable.
     */
    private function records(SurveyProject $project, Request $r): array
    {
        $db = DB::connection('sqlsrv');
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('rq', '')));
        $key = "UPPER(LTRIM(RTRIM(ISNULL(a.farmer_name, ''))))";
        $has = fn (string $col) => "CASE WHEN LTRIM(RTRIM(ISNULL(a.{$col}, ''))) <> '' THEN 1 ELSE 0 END";

        $base = fn () => $db->table('survey_plot_allocations as a')
            ->join('survey_comp_cases as c', 'c.id', '=', 'a.survey_comp_case_id')
            ->where('c.survey_project_id', $project->id)
            ->whereNull('a.deleted_at')->whereNull('c.deleted_at');

        $totals = $base()->selectRaw("COUNT(DISTINCT {$key}) farmers, COUNT(*) farms, SUM(TRY_CAST(a.acreage AS decimal(18,4))) area, "
            . "SUM({$has('plot_no')}) plots, SUM({$has('op_no')}) ops")->first();
        // Lands = the plot numbers the farms produced (user, 2026-11-03: Farmer > Farm > Land > OP).
        $totals->lands = $base()->whereNotNull('a.plot_no')->pluck('a.plot_no')->sum(fn ($p) => count(self::plotList($p)));
        $ops = $this->projectOps($project);
        $totals->ops += $ops['count'];

        $filtered = fn () => $base()->when($term !== '', function ($q) use ($term) {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $q->where(fn ($w) => $w->where('a.farmer_name', 'like', $like)->orWhere('a.f_no', 'like', $like)
                ->orWhere('a.plot_no', 'like', $like)->orWhere('a.op_no', 'like', $like));
        });

        $perPage = 25;
        $page = max(1, (int) $r->query('rpage', 1));
        $count = (int) ($filtered()->selectRaw("COUNT(DISTINCT {$key}) n")->value('n') ?? 0);
        $groups = $filtered()
            ->selectRaw("{$key} as k, MIN(a.farmer_name) as name, COUNT(*) as farms, SUM(TRY_CAST(a.acreage AS decimal(18,4))) as area, "
                . "SUM({$has('plot_no')}) as plots, SUM({$has('op_no')}) as ops, MIN(ISNULL(TRY_CAST(a.f_no AS int), 2147483647)) as first_fno, MIN(a.id) as first_id")
            ->groupByRaw($key)
            ->orderBy('first_fno')->orderBy('first_id')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // Each listed farmer's farms (all of them, not only those matching the search).
        $farms = collect();
        if ($groups->isNotEmpty()) {
            $farms = $base()->whereIn(DB::raw($key), $groups->pluck('k')->all())
                ->select('a.id', 'a.f_no', 'a.farmer_name', 'a.acreage', 'a.creetable', 'a.farmer_50', 'a.govt_50', 'a.plot_no', 'a.op_no', 'a.remarks',
                    'c.id as case_id', 'c.case_ref', 'c.portion_no')
                ->selectRaw("{$key} as k")
                ->orderByRaw('ISNULL(TRY_CAST(a.f_no AS int), 2147483647)')->orderBy('a.id')
                ->get()
                ->each(function ($f) use ($ops) {
                    // Each farm's lands, and each land's OP(s): an OP recorded against that plot number, else the
                    // farm's own OP (the list's OP No., or one generated for the farmer on that land).
                    $farmOps = collect();
                    if (trim((string) $f->op_no) !== '') {
                        $farmOps->push((object) ['op_number' => trim($f->op_no), 'status' => null, 'verification' => null, 'source' => 'On the list']);
                    }
                    $farmOps = $farmOps->merge($ops['byFarmer'][$f->case_id . '|' . self::nameKey($f->farmer_name)] ?? []);
                    $f->lands = collect(self::plotList($f->plot_no))->map(fn ($plot) => (object) [
                        'plot_no' => $plot,
                        'ops'     => collect($ops['byPlot'][self::plotKey($plot)] ?? [])->whenEmpty(fn () => $farmOps),
                    ]);
                    $f->farm_ops = $farmOps;
                    $f->op_count = $f->lands->isEmpty() ? $farmOps->count() : $f->lands->sum(fn ($l) => $l->ops->count());
                })
                ->groupBy('k');
        }

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($groups, $count, $perPage, $page, [
            'path' => $r->url(), 'pageName' => 'rpage', 'query' => $r->query(), 'fragment' => 'records',
        ]);

        return ['totals' => $totals, 'farmers' => $paginator, 'farms' => $farms, 'term' => $term];
    }

    /**
     * The project's OP records (generated, or existing ones captured on the OP page), indexed for the
     * records tree: by plot number (existing OPs carry one), and by land + farmer (generated OPs).
     *
     * @return array{count:int, byPlot:array<string, array>, byFarmer:array<string, array>}
     */
    private function projectOps(SurveyProject $project): array
    {
        $caseIds = $project->cases()->pluck('id')->all();
        $rows = \App\Models\Survey\SurveyOpRecord::query()
            ->with('beneficiary:id,full_name')
            ->where(fn ($q) => $q->where('survey_project_id', $project->id)->when($caseIds, fn ($w) => $w->orWhereIn('survey_comp_case_id', $caseIds)))
            ->orderBy('id')
            ->get(['id', 'op_number', 'survey_comp_case_id', 'survey_beneficiary_id', 'survey_project_id', 'plot_no', 'allottee_name',
                'status', 'verification_status', 'is_existing']);

        $byPlot = [];
        $byFarmer = [];
        foreach ($rows as $op) {
            $item = (object) [
                'op_number'    => $op->op_number,
                'status'       => $op->status,
                'verification' => $op->verificationStatus(),
                'source'       => $op->is_existing ? 'Existing OP' : 'Generated',
            ];
            $plots = self::plotList($op->plot_no);
            foreach ($plots as $p) $byPlot[self::plotKey($p)][] = $item;
            $name = $op->beneficiary?->full_name ?: $op->allottee_name;
            if (! $plots && $op->survey_comp_case_id && trim((string) $name) !== '') {
                $byFarmer[$op->survey_comp_case_id . '|' . self::nameKey($name)][] = $item;
            }
        }

        return ['count' => $rows->count(), 'byPlot' => $byPlot, 'byFarmer' => $byFarmer];
    }

    /** "145,146 & 150-152" -> [145, 146, 150, 151, 152], as the lists count them (CompensationListImport::countPlots). */
    public static function plotList(?string $plots): array
    {
        $out = [];
        foreach (preg_split('/\s*[,;&]\s*/', trim((string) $plots)) as $p) {
            if ($p === '') continue;
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $p, $m) && $m[2] >= $m[1] && $m[2] - $m[1] < 500) {
                foreach (range((int) $m[1], (int) $m[2]) as $n) $out[] = (string) $n;
            } else {
                $out[] = $p;
            }
        }

        return $out;
    }

    private static function plotKey(string $plot): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $plot));
    }

    private static function nameKey(?string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    public function update(Request $r, SurveyProject $project)
    {
        $data = $this->validated($r, $project);

        // Scheme type is immutable once cases exist — they inherited it.
        if ($project->cases()->exists()) {
            // The sharing ratio too (CR 30102026 item 11): the cases' splits were worked out on it.
            unset($data['scheme_type'], $data['farmer_share_pct']);
        }

        $rows = $data['surveyors'] ?? [];
        $trees = $data['trees'] ?? [];
        unset($data['surveyors'], $data['trees']);

        DB::connection('sqlsrv')->transaction(function () use ($project, $data, $rows, $trees) {
            $project->update($data);
            $this->syncSurveyors($project, $rows);
            $this->syncTrees($project->refresh(), $trees);
        });

        return redirect()
            ->route('survey-module.compensation.projects')
            ->with('success', "Project {$project->project_code} updated.");
    }

    public function destroy(SurveyProject $project)
    {
        if ($project->cases()->exists()) {
            return back()->with('error',
                "{$project->project_code} has {$project->cases()->count()} case(s) and cannot be deleted. Set its status to Closed instead.");
        }

        $code = $project->project_code;
        DB::connection('sqlsrv')->transaction(function () use ($project) {
            $project->surveyors()->delete();
            $project->trees()->delete();
            $project->delete();
        });

        return back()->with('success', "Project {$code} deleted.");
    }

    /** Form data for create and edit. */
    private function formData(SurveyProject $project): array
    {
        // Open CO files, plus the project's own even if it has since closed.
        $coFiles = SurveyCoFile::where('status', '!=', 'Closed')
            ->when($project->survey_co_file_id, fn ($q) => $q->orWhere('id', $project->survey_co_file_id))
            ->orderByDesc('id')
            ->get(['id', 'co_file_no', 'title']);

        // Active staff, plus anyone already assigned who has since been deactivated.
        $assigned  = $project->exists ? $project->surveyors->pluck('surveyor_id')->filter()->all() : [];
        $surveyors = SurveyorListEntry::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($assigned, fn ($w) => $w->orWhereIn('id', $assigned)))
            ->orderBy('first_name')->orderBy('surname')
            ->get(['id', 'first_name', 'surname', 'other_name', 'rank', 'unit', 'is_active']);

        // The highest portion already surveyed on a case, per row: portions cannot drop below it.
        $usedMax = [];
        if ($project->exists) {
            foreach ($project->surveyors as $a) {
                $usedMax[$a->id] = (int) $a->cases()->max('portion_no');
            }
        }

        // Economic trees of the Kano schedule: name => [Category => rate per stand].
        $treeRates = [];
        foreach (\App\Models\Survey\SurveyTreeType::where('status', 'Active')->where('category', \App\Models\Survey\SurveyTreeType::ECONOMIC_TREE)
                     ->orderBy('name')->get(['name', 'maturity', 'default_unit_price']) as $t) {
            $treeRates[$t->name][$t->maturity ?: 'Mature'] = $t->default_unit_price === null ? null : (float) $t->default_unit_price;
        }

        return compact('project', 'coFiles', 'surveyors', 'usedMax', 'treeRates');
    }

    /**
     * Save the project's economic-tree lines (user, 2026-11-02): the form's list replaces the old
     * one. A Land to Land project has none.
     */
    private function syncTrees(SurveyProject $project, array $rows): void
    {
        $project->trees()->delete();
        if (! $project->isMonetary()) {
            return;
        }
        foreach (array_values($rows) as $i => $row) {
            \App\Models\Survey\SurveyProjectTree::create([
                'survey_project_id'   => $project->id,
                'survey_tree_type_id' => $row['tree_type_id'],
                'tree_name'           => $row['name'],
                'maturity'            => $row['maturity'],
                'stands'              => $row['stands'],
                'rate'                => $row['rate'],
                'amount'              => $row['amount'],
                'sort_order'          => $i,
            ]);
        }
    }

    /**
     * Save the surveyor rows. A row dropped from the form is removed, unless
     * cases were already registered against it.
     */
    private function syncSurveyors(SurveyProject $project, array $rows): void
    {
        $existing = $project->surveyors()->get()->keyBy('id');
        $keep = [];

        foreach ($rows as $i => $row) {
            $alloc    = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            // "Other (specify)": a surveyor not on the List of Surveyors, kept by name only.
            $surveyor = $row['surveyor_id'] === 'other' ? null : SurveyorListEntry::find($row['surveyor_id']);

            $attrs = [
                'surveyor_id'     => $surveyor?->id,
                'surveyor_name'   => $surveyor ? $surveyor->full_name : $row['surveyor_other'],
                'portions'        => (int) $row['portions'],
                'portion_size_ha' => round((float) $row['portion_size_ha'], 4),
            ];

            if ($alloc) {
                $used = (int) $alloc->cases()->max('portion_no');
                if ($attrs['portions'] < $used) {
                    throw ValidationException::withMessages([
                        "surveyors.$i.portions" => "{$alloc->surveyor_name} has already surveyed portion {$used}, so the portions cannot drop below {$used}.",
                    ]);
                }
                $swapped = $surveyor
                    ? (int) $alloc->surveyor_id !== $surveyor->id
                    : $alloc->surveyor_id || mb_strtoupper($alloc->surveyor_name) !== mb_strtoupper($attrs['surveyor_name']);
                if ($swapped && $alloc->cases()->exists()) {
                    throw ValidationException::withMessages([
                        "surveyors.$i.surveyor_id" => "{$alloc->surveyor_name} already has cases on this project and cannot be swapped for another surveyor.",
                    ]);
                }
                $alloc->update($attrs);
                $keep[] = $alloc->id;
            } else {
                $keep[] = $project->surveyors()->create($attrs)->id;
            }
        }

        foreach ($existing as $id => $alloc) {
            if (in_array($id, $keep, true)) continue;
            if ($n = $alloc->cases()->count()) {
                throw ValidationException::withMessages([
                    'surveyors' => "{$alloc->surveyor_name} has {$n} case(s) on this project and cannot be removed.",
                ]);
            }
            $alloc->delete();
        }
    }

    private function validated(Request $r, ?SurveyProject $existing = null): array
    {
        // Blank rows added and never filled in are ignored.
        $rows = array_values(array_filter((array) $r->input('surveyors'),
            fn ($row) => is_array($row) && trim((string) ($row['surveyor_id'] ?? '')) !== ''));

        // "Other (specify)": whitespace-normalised, case kept as typed. A name already on
        // the List of Surveyors (any case/spacing) is taken as that surveyor, not a new one.
        $staff = null;
        foreach ($rows as &$row) {
            if ((string) $row['surveyor_id'] !== 'other') {
                unset($row['surveyor_other']);
                continue;
            }
            $name = preg_replace('/\s+/u', ' ', trim((string) ($row['surveyor_other'] ?? '')));
            $row['surveyor_other'] = $name;
            $staff ??= SurveyorListEntry::get(['id', 'first_name', 'surname', 'other_name']);
            $match = $name === '' ? null : $staff->first(fn ($s) => mb_strtoupper($s->full_name) === mb_strtoupper($name));
            if ($match) {
                $row['surveyor_id'] = (string) $match->id;
                unset($row['surveyor_other']);
            }
        }
        unset($row);
        $r->merge(['surveyors' => $rows]);

        // One row per surveyor: listed ones by id, Other ones by name.
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = $row['surveyor_id'] === 'other' ? 'n:' . mb_strtoupper($row['surveyor_other']) : 'id:' . $row['surveyor_id'];
            if ($key !== 'n:' && isset($seen[$key])) {
                throw ValidationException::withMessages(["surveyors.$i.surveyor_id" => 'A surveyor appears twice. Give each surveyor one row.']);
            }
            $seen[$key] = true;
        }

        $rules = [
            'survey_co_file_id'   => ['required', Rule::exists('sqlsrv.survey_co_files', 'id')->whereNull('deleted_at')],
            'acquisition_size_ha' => 'required|numeric|min:0.0001|max:9999999999',

            'surveyors'                   => 'nullable|array',
            'surveyors.*.id'              => 'nullable|integer',
            'surveyors.*.surveyor_id'     => ['required', function ($attr, $value, $fail) {
                if ($value !== 'other' && ! SurveyorListEntry::whereKey($value)->exists()) $fail('Select a surveyor from the list, or Other (specify).');
            }],
            'surveyors.*.surveyor_other'  => 'required_if:surveyors.*.surveyor_id,other|nullable|string|max:255',
            'surveyors.*.portions'        => 'required|integer|min:1|max:10000',
            'surveyors.*.portion_size_ha' => 'required|numeric|min:0.0001|max:9999999999',

            'name'        => 'required|string|max:255',
            // The layout plan's TP number, e.g. TP/KNUPDA/UC/GZ/02 (an identifier: trimmed, never re-cased).
            'plan_no'     => 'nullable|string|max:100',
            'purpose'     => 'nullable|string|max:100',
            'scheme_type' => ['required', Rule::in([SurveyProject::SCHEME_MONETARY, SurveyProject::SCHEME_LAND])],
            // The sharing ratio (CR 30102026 item 11), one of Configurable Entities > Survey; Land to Land only.
            'farmer_share_pct' => ['nullable', 'integer', Rule::in(\App\Services\Survey\SurveySettings::sharingRatios()->pluck('farmer_pct')->map(fn ($p) => (int) $p)->all())],
            'status'      => ['required', Rule::in(['Active', 'Draft', 'Closed'])],
            'start_date'  => 'nullable|date',
            'description' => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        $data = $r->validate($rules, [
            'prop_district.required'       => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'            => 'The LGA is required.',
            'survey_co_file_id.required'   => 'Select the CO file this project belongs to.',
            'acquisition_size_ha.required' => 'Enter the size of acquisition.',
            'surveyors.*.surveyor_other.required_if' => 'Surveyor is Other: please specify the surveyor\'s name.',
            'surveyors.*.portions.required'        => 'Every surveyor needs a number of portions.',
            'surveyors.*.portion_size_ha.required' => 'Every surveyor needs a size per portion.',
            'farmer_share_pct.in'                  => 'Choose a sharing ratio from the list.',
        ]);

        $data['trees'] = $this->validatedTrees($r);

        // A Monetary Compensation project has no plots to share; a Land to Land one keeps the
        // ratio picked, else the general one.
        $data['farmer_share_pct'] = ($data['scheme_type'] ?? null) === SurveyProject::SCHEME_LAND
            ? (int) ($data['farmer_share_pct'] ?? \App\Services\Survey\SurveySettings::defaultFarmerPct())
            : null;

        return $data;
    }

    /**
     * Cash for economic trees (user, 2026-11-02): one line per tree and Category, with stands and
     * the rate per stand (the schedule's, unless the officer changed it); amount = stands x rate.
     * Blank rows are ignored; the same tree in the same Category twice is refused.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validatedTrees(Request $r): array
    {
        $rows = array_values(array_filter((array) $r->input('trees'),
            fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== ''));
        $r->merge(['trees' => $rows]);
        $r->validate([
            'trees'            => 'nullable|array|max:300',
            'trees.*.name'     => 'required|string|max:255',
            'trees.*.maturity' => ['required', Rule::in(\App\Models\Survey\SurveyTreeType::MATURITIES)],
            'trees.*.stands'   => 'required|integer|min:1|max:100000000',
            'trees.*.rate'     => 'nullable|numeric|min:0|max:9999999999',
        ], [
            'trees.*.maturity.required' => 'Every tree needs a Category (Mature, Immature or Seedling).',
            'trees.*.stands.required'   => 'Every tree needs its number of stands.',
            'trees.*.stands.min'        => 'Stands must be at least 1.',
        ]);

        $out = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $type = \App\Models\Survey\SurveyTreeType::findFor($row['name'], $row['maturity'], \App\Models\Survey\SurveyTreeType::ECONOMIC_TREE);
            if (! $type) {
                throw ValidationException::withMessages(["trees.$i.name" => "Pick the economic tree from the list (line " . ($i + 1) . ').']);
            }
            $key = $type->id;
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["trees.$i.name" => "{$type->label} is listed twice. Put all its stands on one line."]);
            }
            $seen[$key] = true;
            $rate = ($row['rate'] ?? '') !== '' && $row['rate'] !== null ? (float) $row['rate'] : $type->rate();
            if ($rate === null) {
                throw ValidationException::withMessages(["trees.$i.rate" => "{$type->label} has no rate in the schedule: type the rate per stand."]);
            }
            $out[] = [
                'tree_type_id' => $type->id,
                'name'         => $type->name,
                'maturity'     => $type->maturity ?: \App\Models\Survey\SurveyTreeType::MATURE,
                'stands'       => (int) $row['stands'],
                'rate'         => round($rate, 2),
                'amount'       => round((int) $row['stands'] * $rate, 2),
            ];
        }

        return $out;
    }
}
