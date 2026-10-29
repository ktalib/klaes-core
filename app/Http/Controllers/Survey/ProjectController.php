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
        $q = SurveyProject::query()->with(['coFile', 'surveyors'])->withCount('cases');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%$term%")
                  ->orWhere('project_code', 'like', "%$term%");
            });
        }
        if ($s = $r->query('scheme')) $q->where('scheme_type', $s);
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($co = $r->query('co_file')) $q->where('survey_co_file_id', $co);

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
        unset($data['surveyors']);

        $project = DB::connection('sqlsrv')->transaction(function () use ($data, $rows) {
            $data['project_code'] = SurveyProject::nextRef('project_code', 'PRJ');
            $project = SurveyProject::create($data);
            $this->syncSurveyors($project, $rows);

            return $project;
        });

        return redirect()
            ->route('survey-module.compensation.projects')
            ->with('success', "Project {$project->project_code} created. Scheme is locked to {$project->scheme_label}.");
    }

    public function edit(SurveyProject $project)
    {
        $project->load('surveyors');

        return view('survey_module.compensation.project_register', $this->formData($project));
    }

    public function update(Request $r, SurveyProject $project)
    {
        $data = $this->validated($r, $project);

        // Scheme type is immutable once cases exist — they inherited it.
        if ($project->cases()->exists()) {
            unset($data['scheme_type']);
        }

        $rows = $data['surveyors'] ?? [];
        unset($data['surveyors']);

        DB::connection('sqlsrv')->transaction(function () use ($project, $data, $rows) {
            $project->update($data);
            $this->syncSurveyors($project, $rows);
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

        return compact('project', 'coFiles', 'surveyors', 'usedMax');
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
            'survey_co_file_id.required'   => 'Select the CO file this project belongs to.',
            'acquisition_size_ha.required' => 'Enter the size of acquisition.',
            'surveyors.*.surveyor_other.required_if' => 'Surveyor is Other: please specify the surveyor\'s name.',
            'surveyors.*.portions.required'        => 'Every surveyor needs a number of portions.',
            'surveyors.*.portion_size_ha.required' => 'Every surveyor needs a size per portion.',
        ]);
    }
}
