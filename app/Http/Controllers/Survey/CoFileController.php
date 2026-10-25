<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CO (Compensation) files. "Commission CO File" numbers a new one Prj-YYYY-0001;
 * "data capture" keys in an existing paper CO file under its own number. Projects
 * are opened under a CO file, one CO file to many projects.
 */
class CoFileController extends Controller
{
    public function index(Request $r)
    {
        $q = SurveyCoFile::query()
            ->withCount('projects')
            ->withSum('projects', 'acquisition_size_ha')
            ->with(['projects.surveyors']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('co_file_no', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%")
                  ->orWhereHas('projects', fn ($p) => $p->where('name', 'like', "%$term%")
                                                        ->orWhere('project_code', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($src = $r->query('source')) $q->where('source', $src);
        if ($from = $r->query('from')) $q->whereDate('commission_date', '>=', $from);
        if ($to = $r->query('to'))     $q->whereDate('commission_date', '<=', $to);

        $coFiles = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => SurveyCoFile::count(),
            'active'   => SurveyCoFile::whereIn('status', ['Commissioned', 'Active'])->count(),
            'captured' => SurveyCoFile::where('source', SurveyCoFile::SOURCE_CAPTURED)->count(),
            'closed'   => SurveyCoFile::where('status', 'Closed')->count(),
        ];

        return view('survey_module.compensation.co_files', compact('coFiles', 'stats'));
    }

    public function create(Request $r)
    {
        $capture = $r->query('mode') === SurveyCoFile::SOURCE_CAPTURED
            || old('source') === SurveyCoFile::SOURCE_CAPTURED;

        $coFile = new SurveyCoFile([
            'source'          => $capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED,
            'status'          => 'Commissioned',
            'commission_date' => $capture ? null : now()->toDateString(),
            'prop_state'      => 'Kano',
        ]);

        return view('survey_module.compensation.co_file_register', [
            'coFile'   => $coFile,
            'nextNo'   => SurveyCoFile::nextNumber(),
        ]);
    }

    public function store(Request $r)
    {
        $capture = $r->input('source') === SurveyCoFile::SOURCE_CAPTURED;
        $data = $this->validated($r, null, $capture);

        $data['source'] = $capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED;
        if (! $capture) {
            // Numbered on save, never from the form.
            $data['co_file_no'] = SurveyCoFile::nextNumber();
        }

        $coFile = SurveyCoFile::create($data);

        return redirect()
            ->route('survey-module.compensation.co-files')
            ->with('success', $capture
                ? "Existing CO file {$coFile->co_file_no} captured."
                : "CO file {$coFile->co_file_no} commissioned. Open its projects from All Projects.");
    }

    public function edit(SurveyCoFile $coFile)
    {
        return view('survey_module.compensation.co_file_register', ['coFile' => $coFile, 'nextNo' => null]);
    }

    public function update(Request $r, SurveyCoFile $coFile)
    {
        $capture = $coFile->source === SurveyCoFile::SOURCE_CAPTURED;
        $data = $this->validated($r, $coFile, $capture);

        // A commissioned number is the system's; only a captured one may be corrected.
        if (! $capture) unset($data['co_file_no']);

        $coFile->update($data);

        return redirect()
            ->route('survey-module.compensation.co-files')
            ->with('success', "CO file {$coFile->co_file_no} updated.");
    }

    public function destroy(SurveyCoFile $coFile)
    {
        if ($n = $coFile->projects()->count()) {
            return back()->with('error',
                "{$coFile->co_file_no} holds {$n} project(s) and cannot be deleted. Set its status to Closed instead.");
        }

        $no = $coFile->co_file_no;
        $coFile->delete();

        return back()->with('success', "CO file {$no} deleted.");
    }

    private function validated(Request $r, ?SurveyCoFile $existing, bool $capture): array
    {
        $rules = [
            'title'           => 'required|string|max:255',
            'commission_date' => $capture ? 'nullable|date' : 'required|date',
            'status'          => ['required', Rule::in(SurveyCoFile::STATUSES)],
            'remarks'         => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        if ($capture) {
            // The paper file's own number, kept exactly as written.
            $rules['co_file_no'] = [
                'required', 'string', 'max:50',
                Rule::unique('sqlsrv.survey_co_files', 'co_file_no')->ignore($existing?->id),
            ];
        }

        $data = $r->validate($rules, [
            'co_file_no.required'             => 'Enter the existing CO file number.',
            'co_file_no.unique'               => 'That CO file number is already registered.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
        ]);

        if (isset($data['co_file_no'])) $data['co_file_no'] = trim($data['co_file_no']);
        $data['title'] = trim(preg_replace('/\s+/', ' ', $data['title']));

        return $data;
    }
}
