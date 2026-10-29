<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyComplaint;
use App\Models\Survey\SurveyProject;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compensation complaints, as the printed Compensation Complaint Form
 * (amended 2026-10-29; docs/KLAES Survey Module – Workflow_V02.md 2.4).
 *
 * One page with three tabs, one per form category. The panel is both the
 * create and the edit form. Picking a farmer (beneficiary), case or project
 * fills the complainant, location, compensation type and original amount or
 * plots from those records. Status only moves along SurveyComplaint::NEXT,
 * and a Resolved complaint can no longer be edited or deleted.
 */
class ComplaintController extends Controller
{
    /** The project-type dropdown's single "Other (specify)" value; never stored. */
    public const PROJECT_TYPE_OTHER = '__other__';

    public function index(Request $r)
    {
        return view('survey_module.compensation.complaints', $this->pageData($r));
    }

    public function edit(Request $r, SurveyComplaint $complaint)
    {
        if ($complaint->isFrozen()) {
            return redirect()->route('survey-module.compensation.complaints', ['tab' => $complaint->category_key])
                ->with('error', "{$complaint->complaint_ref} is resolved and can no longer be edited.");
        }

        return view('survey_module.compensation.complaints', $this->pageData($r, $complaint));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['complaint_ref'] = SurveyComplaint::nextRef('complaint_ref', 'CMP');
        $data['status'] = SurveyComplaint::PENDING;

        $c = SurveyComplaint::create($data);

        return redirect()->route('survey-module.compensation.complaints', ['tab' => $c->category_key])
            ->with('success', "Complaint {$c->complaint_ref} logged for {$c->complainant_name}.");
    }

    public function update(Request $r, SurveyComplaint $complaint)
    {
        if ($complaint->isFrozen()) {
            return back()->with('error', "{$complaint->complaint_ref} is resolved and can no longer be edited.");
        }

        $complaint->update($this->validated($r));

        return redirect()->route('survey-module.compensation.complaints', ['tab' => $complaint->category_key])
            ->with('success', "Complaint {$complaint->complaint_ref} updated.");
    }

    /** Move along the status chain. Every step except Under Review needs a remark. */
    public function status(Request $r, SurveyComplaint $complaint)
    {
        $data = $r->validate([
            'status'     => ['required', Rule::in(SurveyComplaint::STATUSES)],
            'resolution' => 'nullable|string|max:4000',
        ]);

        if (! $complaint->canMoveTo($data['status'])) {
            return back()->with('error', "{$complaint->complaint_ref} is {$complaint->status}; it cannot move to {$data['status']}.");
        }

        $note = trim((string) ($data['resolution'] ?? ''));
        if ($data['status'] !== SurveyComplaint::UNDER_REVIEW && $note === '') {
            throw ValidationException::withMessages([
                'resolution' => $data['status'] === SurveyComplaint::RESOLVED
                    ? 'Say how the complaint was resolved.'
                    : "Give the remarks behind the finding \"{$data['status']}\".",
            ]);
        }

        $attrs = ['status' => $data['status']];
        if ($note !== '') {
            // Keep the trail: each step's remark is appended, never overwritten.
            $stamp = now()->format('d M Y H:i') . ' · ' . (Auth::user()?->name ?? 'system') . ' · ' . $data['status'];
            $attrs['resolution'] = trim(($complaint->resolution ? $complaint->resolution . "\n" : '') . "[$stamp] $note");
        }
        if ($data['status'] === SurveyComplaint::RESOLVED) {
            $attrs['resolved_at'] = now();
            $attrs['resolved_by'] = Auth::id();
        }

        $complaint->update($attrs);

        return back()->with('success', "Complaint {$complaint->complaint_ref} is now {$data['status']}.");
    }

    public function destroy(SurveyComplaint $complaint)
    {
        if ($complaint->status !== SurveyComplaint::PENDING) {
            return back()->with('error', "{$complaint->complaint_ref} is {$complaint->status}; only a Pending complaint logged in error can be deleted.");
        }

        $ref = $complaint->complaint_ref;
        $complaint->delete();

        return back()->with('success', "Complaint {$ref} deleted.");
    }

    /** The form filled in, for signing and the file. */
    public function print(SurveyComplaint $complaint)
    {
        return view('survey_module.compensation.complaint_print', [
            'complaint' => $complaint->load(['project', 'case']),
            'blank'     => false,
        ]);
    }

    /** The empty form, for walk-in complainants to fill by hand. */
    public function printBlank()
    {
        return view('survey_module.compensation.complaint_print', [
            'complaint' => new SurveyComplaint(),
            'blank'     => true,
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function pageData(Request $r, ?SurveyComplaint $editing = null): array
    {
        $tab = (string) $r->query('tab');
        if (! array_key_exists($tab, SurveyComplaint::CATEGORIES)) {
            $tab = $editing?->category_key ?? SurveyComplaint::CAT_INADEQUATE;
        }
        $label = SurveyComplaint::CATEGORIES[$tab];

        $q = SurveyComplaint::query()->with(['project', 'case', 'beneficiary'])->where('category', $label);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                foreach (['complaint_ref', 'complainant_name', 'complainant_phone', 'farm_no', 'file_reference', 'map_sheet_no', 'district', 'subject'] as $col) {
                    $w->orWhere($col, 'like', "%$term%");
                }
            });
        }
        if ($st = $r->query('status'))  $q->where('status', $st);
        if ($p = $r->query('project'))  $q->where('survey_project_id', $p);

        $stats = array_fill_keys(SurveyComplaint::STATUSES, 0);
        $byStatus = SurveyComplaint::where('category', $label)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        foreach ($byStatus as $s => $n) $stats[$s] = (int) $n;

        $tabCounts = [];
        $byCat = SurveyComplaint::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');
        foreach (SurveyComplaint::CATEGORIES as $k => $l) $tabCounts[$k] = (int) ($byCat[$l] ?? 0);

        $projects = SurveyProject::with('coFile:id,co_file_no')->orderByDesc('id')
            ->get(['id', 'project_code', 'name', 'scheme_type', 'survey_co_file_id', 'prop_district', 'prop_district_other', 'prop_lga']);
        $cases = SurveyCompCase::withSum('trees', 'line_total')->withCount('plots')->orderByDesc('id')
            ->get(['id', 'case_ref', 'survey_project_id', 'scheme_type', 'num_plots', 'prop_district', 'prop_district_other', 'prop_lga']);
        $beneficiaries = SurveyBeneficiary::with('case:id,case_ref')->orderBy('full_name')
            ->get(['id', 'full_name', 'phone', 'survey_comp_case_id']);

        return [
            'tab'           => $tab,
            'tabCounts'     => $tabCounts,
            'complaints'    => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats'         => $stats,
            'projects'      => $projects,
            'cases'         => $cases,
            'beneficiaries' => $beneficiaries,
            'projectTypes'  => $this->projectTypes(),
            'meta'          => $this->backfillMeta($projects, $cases, $beneficiaries),
            'complaint'     => $editing ?? new SurveyComplaint([
                'received_date' => now()->toDateString(),
                'category'      => $label,
            ]),
        ];
    }

    /** What picking a farmer, case or project fills in, keyed by id for the page script. */
    private function backfillMeta($projects, $cases, $beneficiaries): array
    {
        return [
            'projects' => $projects->mapWithKeys(fn ($p) => [$p->id => [
                'scheme'   => $p->scheme_type,
                'district' => AddressBuilder::resolve($p->prop_district, $p->prop_district_other),
                'lga'      => AddressBuilder::resolve($p->prop_lga),
                'fileRef'  => $p->coFile?->co_file_no,
            ]]),
            'cases' => $cases->mapWithKeys(function ($c) {
                $plots = (int) $c->plots_count ?: (int) $c->num_plots;

                return [$c->id => [
                    'project'  => $c->survey_project_id,
                    'scheme'   => $c->scheme_type,
                    'district' => AddressBuilder::resolve($c->prop_district, $c->prop_district_other),
                    'lga'      => AddressBuilder::resolve($c->prop_lga),
                    'amount'   => $c->trees_sum_line_total !== null ? round((float) $c->trees_sum_line_total, 2) : null,
                    // The farmer's half of the 50:50 split (SurveyCompCase::plotSplit).
                    'plots'    => $plots ? intdiv($plots, 2) : null,
                ]];
            }),
            'beneficiaries' => $beneficiaries->mapWithKeys(fn ($b) => [$b->id => [
                'name'  => $b->full_name,
                'phone' => $b->phone,
                'case'  => $b->survey_comp_case_id,
            ]]),
        ];
    }

    /** The listed project types plus any already typed, so an edited record keeps its own. */
    private function projectTypes(): array
    {
        $used = SurveyComplaint::whereNotNull('project_type')->distinct()->pluck('project_type')->all();

        return array_values(array_unique(array_merge(SurveyComplaint::PROJECT_TYPES, $used)));
    }

    private function validated(Request $r): array
    {
        // The linked record wins: a farmer brings their case, a case its project,
        // and the case or project fixes the compensation type and CO file reference.
        $caseId = $r->input('survey_comp_case_id');
        if ($r->filled('survey_beneficiary_id')) {
            $caseId = SurveyBeneficiary::whereKey($r->input('survey_beneficiary_id'))->value('survey_comp_case_id') ?: $caseId;
        }
        $case = $caseId ? SurveyCompCase::with('project.coFile')->find($caseId) : null;
        $project = $case?->project
            ?? ($r->filled('survey_project_id') ? SurveyProject::with('coFile')->find($r->input('survey_project_id')) : null);

        $merge = [];
        if ($case) {
            $merge['survey_comp_case_id'] = $case->id;
            $merge['survey_project_id'] = $case->survey_project_id;
        }
        if ($scheme = $case?->scheme_type ?: $project?->scheme_type) $merge['compensation_type'] = $scheme;
        if ($ref = $project?->coFile?->co_file_no)                    $merge['file_reference'] = $ref;
        $r->merge($merge);

        $cat   = $r->input('category');
        $land  = $r->input('compensation_type') === SurveyProject::SCHEME_LAND;
        $money = $r->input('compensation_type') === SurveyProject::SCHEME_MONETARY;
        $docs  = (array) $r->input('supporting_documents', []);

        $data = $r->validate([
            'survey_beneficiary_id'       => ['nullable', Rule::exists('sqlsrv.survey_beneficiaries', 'id')->whereNull('deleted_at')],
            'survey_comp_case_id'         => ['nullable', Rule::exists('sqlsrv.survey_comp_cases', 'id')->whereNull('deleted_at')],
            'survey_project_id'           => ['nullable', Rule::exists('sqlsrv.survey_projects', 'id')->whereNull('deleted_at')],
            // Section 1
            'complainant_name'            => 'required|string|max:255',
            'farm_no'                     => 'nullable|string|max:50',
            'section_no'                  => 'nullable|string|max:50',
            'project_type'                => 'nullable|string|max:100',
            'project_type_other'          => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('project_type') === self::PROJECT_TYPE_OTHER)],
            'complainant_phone'           => 'required|string|max:50',
            'complainant_email'           => 'nullable|email|max:255',
            'representative_name'         => 'nullable|string|max:255',
            'representative_relationship' => 'nullable|required_with:representative_name|string|max:100',
            // Section 2
            'file_reference'              => 'required|string|max:100',
            'map_sheet_no'                => 'required|string|max:50',
            'district'                    => 'required|string|max:255',
            'lga'                         => 'nullable|string|max:255',
            'compensation_type'           => ['required', Rule::in(array_keys(SurveyComplaint::COMP_TYPES))],
            'original_amount'             => [Rule::requiredIf($money), 'nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'original_plots'              => [Rule::requiredIf($land), 'nullable', 'integer', 'min:0', 'max:100000'],
            // Section 3
            'category'                    => ['required', Rule::in(array_keys(SurveyComplaint::CATEGORIES))],
            'description'                 => 'required|string|max:4000',
            'expected_amount'             => [Rule::requiredIf($cat === SurveyComplaint::CAT_INADEQUATE && $money), 'nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'expected_plots'              => [Rule::requiredIf($cat === SurveyComplaint::CAT_INADEQUATE && $land), 'nullable', 'integer', 'min:0', 'max:100000'],
            'field_officer_name'          => 'nullable|string|max:255',
            'correct_plot_coordinates'    => 'nullable|string|max:1000',
            'boundary_type'               => [Rule::requiredIf($cat === SurveyComplaint::CAT_BOUNDARY), 'nullable', Rule::in(SurveyComplaint::BOUNDARY_TYPES)],
            'affected_party'              => 'nullable|string|max:255',
            // Section 4 and official use
            'supporting_documents'        => 'nullable|array',
            'supporting_documents.*'      => [Rule::in(array_keys(SurveyComplaint::DOCUMENTS))],
            'other_documents'             => [Rule::requiredIf(in_array('other', $docs, true)), 'nullable', 'string', 'max:500'],
            'assigned_to'                 => 'nullable|string|max:255',
            'received_date'               => 'required|date|before_or_equal:today',
        ], [
            'project_type_other.required'                => 'Please specify the project type.',
            'representative_relationship.required_with' => "Give the representative's relationship to the owner.",
            'original_amount.required'                   => 'Enter the original compensation amount (A — Monetary).',
            'original_plots.required'                    => 'Enter the number of plots given (B — Land to Land).',
            'expected_amount.required'                   => 'Enter the expected / correct valuation.',
            'expected_plots.required'                    => 'Enter the expected / correct number of plots.',
            'boundary_type.required'                     => 'Select the type of boundary dispute.',
            'other_documents.required'                   => 'Name the other relevant documents.',
            'description.required'                       => 'Give the details of the complaint.',
            'received_date.before_or_equal'              => 'The date received cannot be in the future.',
        ]);

        $one = function ($v) {
            $v = trim(preg_replace('/\s+/', ' ', (string) $v));

            return $v === '' ? null : $v;
        };

        // Names, places and free text: collapse whitespace, keep the typed case.
        foreach (['complainant_name', 'representative_name', 'representative_relationship', 'district', 'lga',
                  'field_officer_name', 'affected_party', 'assigned_to', 'other_documents'] as $k) {
            $data[$k] = $one($data[$k] ?? null);
        }
        // Identifiers keep their exact form, only trimmed.
        foreach (['farm_no', 'section_no', 'complainant_phone', 'complainant_email', 'file_reference', 'map_sheet_no'] as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            $data[$k] = $v === '' ? null : $v;
        }

        // "Other": the typed project type is stored, but a listed one typed in another case wins.
        if (($data['project_type'] ?? null) === self::PROJECT_TYPE_OTHER) {
            // A category label like the listed ones: Title Case ("hospital" -> "Hospital").
            $typed = mb_convert_case((string) $one($data['project_type_other']), MB_CASE_TITLE);
            $data['project_type'] = collect($this->projectTypes())->first(fn ($t) => mb_strtolower($t) === mb_strtolower($typed)) ?? $typed;
        }
        unset($data['project_type_other']);

        // Keep only the chosen compensation type's figures and the chosen category's block.
        $isLand = $data['compensation_type'] === SurveyProject::SCHEME_LAND;
        if ($isLand) {
            $data['original_amount'] = null;
        } else {
            $data['original_plots'] = null;
        }

        $catKey = $data['category'];
        if ($catKey !== SurveyComplaint::CAT_INADEQUATE) {
            $data['expected_amount'] = $data['expected_plots'] = null;
        } elseif ($isLand) {
            $data['expected_amount'] = null;
        } else {
            $data['expected_plots'] = null;
        }
        if ($catKey !== SurveyComplaint::CAT_MISIDENTIFICATION) {
            $data['field_officer_name'] = $data['correct_plot_coordinates'] = null;
        }
        if ($catKey !== SurveyComplaint::CAT_BOUNDARY) {
            $data['boundary_type'] = $data['affected_party'] = null;
        }

        $docs = array_values(array_unique($data['supporting_documents'] ?? []));
        $data['supporting_documents'] = $docs ?: null;
        if (! in_array('other', $docs, true)) {
            $data['other_documents'] = null;
        }

        $data['category'] = SurveyComplaint::CATEGORIES[$catKey];
        $data['subject'] = match ($catKey) {
            SurveyComplaint::CAT_INADEQUATE => $data['category'] . ($isLand ? ' (Land to Land)' : ' (Monetary)'),
            SurveyComplaint::CAT_BOUNDARY   => $data['category'] . ': ' . $data['boundary_type'],
            default                         => $data['category'],
        };

        return $data;
    }
}
