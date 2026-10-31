<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use App\Services\Survey\CompensationListCapture;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Capture Compensation List (user, 2026-10-30): the paper compensation and
 * resettlement allocation lists that were not in the ALL COMPENSATION
 * spreadsheets, keyed in by hand into the same records the import made.
 */
class CompensationCaptureController extends Controller
{
    public function create(Request $r)
    {
        return view('survey_module.compensation.capture_list', [
            'projects'  => SurveyProject::orderByDesc('id')->get(['id', 'project_code', 'name', 'plan_no', 'scheme_type']),
            'surveyors' => SurveyorListEntry::all()->map->full_name
                ->merge(SurveyProjectSurveyor::distinct()->pluck('surveyor_name'))
                ->map(fn ($n) => CompensationListCapture::tidy($n))->filter()
                ->unique(fn ($n) => mb_strtolower($n))->sort()->values(),
            'project'   => $r->query('project'),
        ]);
    }

    public function store(Request $r, CompensationListCapture $capture)
    {
        $d = $r->validate($this->rules(), [
            'survey_project_id.required_if' => 'Choose the project this list belongs to.',
            'project_name.required_if'      => 'Enter the new project name.',
            'scheme_type.required_if'       => 'Choose the new project\'s scheme.',
            'rows.*.area.numeric'           => 'Area must be a number (row :position).',
            'rows.*.govt50.numeric'         => 'Govt. 50% must be a number.',
            'rows.*.farmer50.numeric'       => 'Farmer 50% must be a number.',
        ]);

        // Keep only rows with something in them; tidy every text cell.
        $rows = [];
        foreach ($d['rows'] ?? [] as $row) {
            $clean = [];
            foreach (CompensationListCapture::ROW_FIELDS as $f) {
                $v = CompensationListCapture::tidy(isset($row[$f]) ? (string) $row[$f] : null);
                if ($v !== null) $clean[$f] = $v;
            }
            if ($clean) $rows[] = $clean;
        }
        if (! $rows) {
            throw ValidationException::withMessages(['rows' => 'Enter at least one row of the list.']);
        }
        foreach ($rows as $i => $row) {
            if (! isset($row['name']) && ! isset($row['plot'])) {
                throw ValidationException::withMessages(['rows' => 'Row ' . ($i + 1) . ' needs a farmer name or a plot number.']);
            }
        }

        // The CO number picked in the selector: a registered file is linked; one not yet in
        // KLAES is checked here and captured by the service under that number.
        $d['co_file_no'] = null;
        if ($d['project_mode'] === 'new' && empty($d['survey_co_file_id']) && $r->filled('co_file_no')) {
            $no = SurveyCoFile::normaliseNumber($r->input('co_file_no'));
            $year = $no ? (int) substr($no, strrpos($no, '-') + 1) : 0;
            if (! $no || $year < SurveyCoFile::FIRST_YEAR || $year > (int) date('Y')) {
                throw ValidationException::withMessages(['co_file_no' => 'Pick the CO file as CO-serial-year, e.g. CO-9-2018.']);
            }
            if ($existing = SurveyCoFile::withTrashed()->where('co_file_no', $no)->first()) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['co_file_no' => "CO file {$no} was deleted; its number cannot be reused."]);
                }
                $d['survey_co_file_id'] = $existing->id;
            } else {
                $d['co_file_no'] = $no;
            }
        }

        $case = $capture->save($d, $rows, auth()->id());

        return redirect()
            ->route('survey-module.tools.plot-allocation', ['case' => $case->id])
            ->with('success', count($rows) . " row(s) captured as land {$case->case_ref}"
                . ($case->project ? " under {$case->project->project_code}" : '') . '. Check them below; plot and OP numbers can still be edited here.');
    }

    private function rules(): array
    {
        $num = 'nullable|numeric|min:0|max:9999999';

        return [
            'project_mode'      => ['required', Rule::in(['existing', 'new'])],
            'survey_project_id' => ['nullable', 'required_if:project_mode,existing', Rule::exists('sqlsrv.survey_projects', 'id')->whereNull('deleted_at')],
            'project_name'      => 'nullable|required_if:project_mode,new|string|max:255',
            'plan_no'           => 'nullable|string|max:100',
            'purpose'           => 'nullable|string|max:100',
            'scheme_type'       => ['nullable', 'required_if:project_mode,new', Rule::in([SurveyProject::SCHEME_LAND, SurveyProject::SCHEME_MONETARY])],
            'survey_co_file_id' => ['nullable', Rule::exists('sqlsrv.survey_co_files', 'id')->whereNull('deleted_at')],
            'co_file_no'        => 'nullable|string|max:50',
            'list_type'         => ['required', Rule::in(array_keys(CompensationListCapture::LIST_TYPES))],
            'surveyor_name'     => 'nullable|string|max:255',
            'section'           => 'nullable|string|max:50',
            'list_title'        => 'nullable|string|max:255',
            'list_date'         => 'nullable|date',
            'source_ref'        => 'nullable|string|max:255',
            'rows'              => 'required|array|max:3000',
            'rows.*.fn'         => 'nullable|string|max:50',
            'rows.*.name'       => 'nullable|string|max:255',
            'rows.*.area'       => $num,
            'rows.*.creatable'  => 'nullable|string|max:20',
            'rows.*.govt50'     => $num,
            'rows.*.farmer50'   => $num,
            'rows.*.plot'       => 'nullable|string|max:400',
            'rows.*.op'         => 'nullable|string|max:50',
            'rows.*.sign'       => 'nullable|string|max:100',
            'rows.*.remarks'    => 'nullable|string|max:500',
        ] + AddressBuilder::rules('prop_', false);
    }
}
