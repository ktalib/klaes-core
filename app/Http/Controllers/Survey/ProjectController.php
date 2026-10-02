<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyProject;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Compensation projects. The scheme type is chosen here and then locked:
 * cases inherit it, so changing it later would silently reinterpret existing
 * cases. Editing is therefore allowed on everything except scheme_type once
 * the project has cases.
 */
class ProjectController extends Controller
{
    public function index(Request $r)
    {
        $q = SurveyProject::query()->withCount('cases');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%$term%")
                  ->orWhere('project_code', 'like', "%$term%");
            });
        }
        if ($s = $r->query('scheme')) $q->where('scheme_type', $s);
        if ($st = $r->query('status')) $q->where('status', $st);

        $projects = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => SurveyProject::count(),
            'monetary' => SurveyProject::where('scheme_type', SurveyProject::SCHEME_MONETARY)->count(),
            'land'     => SurveyProject::where('scheme_type', SurveyProject::SCHEME_LAND)->count(),
            'active'   => SurveyProject::where('status', 'Active')->count(),
        ];

        return view('survey_module.compensation.projects', compact('projects', 'stats'));
    }

    public function create()
    {
        $project = new SurveyProject(['scheme_type' => SurveyProject::SCHEME_MONETARY, 'state' => 'Kano']);
        return view('survey_module.compensation.project_register', compact('project'));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $data['project_code'] = SurveyProject::nextRef('project_code', 'PRJ');
        $project = SurveyProject::create($data);

        return redirect()
            ->route('survey-module.compensation.projects')
            ->with('success', "Project {$project->project_code} created. Scheme is locked to {$project->scheme_label}.");
    }

    public function edit(SurveyProject $project)
    {
        return view('survey_module.compensation.project_register', compact('project'));
    }

    public function update(Request $r, SurveyProject $project)
    {
        $data = $this->validated($r, $project);

        // Scheme type is immutable once cases exist — they inherited it.
        if ($project->cases()->exists()) {
            unset($data['scheme_type']);
        }

        $project->update($data);

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
        $project->delete();

        return back()->with('success', "Project {$code} deleted.");
    }

    private function validated(Request $r, ?SurveyProject $existing = null): array
    {
        $rules = [
            'name'        => 'required|string|max:255',
            'purpose'     => 'nullable|string|max:100',
            'scheme_type' => ['required', Rule::in([SurveyProject::SCHEME_MONETARY, SurveyProject::SCHEME_LAND])],
            'status'      => ['required', Rule::in(['Active', 'Draft', 'Closed'])],
            'start_date'  => 'nullable|date',
            'description' => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        return $r->validate($rules, [
            'prop_district.required'       => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'            => 'The LGA is required.',
        ]);
    }
}
