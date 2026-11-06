<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyItsCoordinate;
use App\Models\Survey\SurveyItsObservation;
use App\Models\Survey\SurveyRegisterFile;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\SurveyDocuments;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;
use App\Models\Survey\SurveyorListEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examination/Verification > Surveyors' Report (docs/KLAES-CORE Survey
 * Module.md 5.7): "if discrepancies are found between the submitted plan and the
 * existing records, this unit generates and prints a Surveyors Report detailing the
 * required changes." Raised from a Register File line (its file details are taken
 * from it) or on its own. Draft until issued; issuing locks and prints it.
 *
 * Surveyor's Report (Private) (change list p.2, 2026-10-29): once saved it carries the
 * same core records as the GKN report: List of Coordinates, Beacon Nos, Computation
 * Field Observations and the Sketch Plan, edited with the GKN editors anchored
 * "private-<id>" (SurveyItsAnchors) and locked with the report on issue.
 */
class ChangeReportController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    public function index(Request $r)
    {
        $reports = $this->reportQuery($r)->orderByDesc('id')->paginate(20)->withQueryString();
        // The three sections of the table (recording, 2026-11-06), as on the GKN report.
        $anchors = app(SurveyItsAnchors::class);
        $sections = app(\App\Services\Survey\ReportRecords::class);
        $rowRecords = $reports->getCollection()->mapWithKeys(function ($x) use ($anchors, $sections) {
            $a = $anchors->resolve('private-' . $x->id);

            return [$x->id => ['anchor' => $a, 'rec' => $a ? $sections->summary($a) : null]];
        });

        return view('survey_module.workflow.change_reports.index', [
            'reports' => $reports,
            'rowRecords' => $rowRecords,
            'stats'   => ['total' => CR::count(), 'draft' => CR::where('status', CR::DRAFT)->count(), 'issued' => CR::where('status', CR::ISSUED)->count()],
            // Examination lines cleared with "Changes required" and no report yet.
            'waiting' => SurveyRegisterFile::where('finding', 'changes_required')
                ->whereNotIn('id', CR::whereNotNull('survey_register_file_id')->pluck('survey_register_file_id')->all() ?: [0])
                ->orderByDesc('id')->get(['id', 'register_ref', 'file_number', 'file_title']),
        ]);
    }

    public function create(Request $r)
    {
        $line = $r->query('line') ? SurveyRegisterFile::find($r->query('line')) : null;
        $report = new CR([
            'survey_register_file_id' => $line?->id,
            'file_number' => $line?->file_number, 'file_title' => $line?->file_title,
            'plot_no' => $line?->plot_no, 'district' => $line?->district, 'lga' => $line?->lga,
            'report_date' => now()->toDateString(), 'examined_by' => Auth::user()?->name,
        ]);

        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $line, 'surveyors' => $this->surveyorOptions()]);
    }

    /**
     * "Plan prepared by" dropdown (user, 2026-11-03): Survey Department staff and the SURCON register,
     * as on the Beacon Register, keyed "staff:<id>" / "surcon:<id>". "Other" is added by the view.
     */
    private function surveyorOptions(): array
    {
        $out = ['staff' => [], 'surcon' => []];
        foreach (SurveyorListEntry::active()->orderBy('first_name')->orderBy('surname')->get() as $s) {
            $out['staff']['staff:' . $s->id] = ['name' => $s->full_name, 'hint' => $s->rank];
        }
        $surcon = DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')
            ->when(\App\Services\Cadastral\SurveyStaffSync::installed(), fn ($q) => $q->whereNull('surveyors_list_id'))
            ->orderBy('full_name')->get(['id', 'full_name', 'surcon_number']);
        foreach ($surcon as $s) {
            $out['surcon']['surcon:' . $s->id] = ['name' => trim($s->full_name), 'hint' => $s->surcon_number ? 'Reg. ' . $s->surcon_number : null];
        }

        return $out;
    }

    /** The stored name for the dropdown choice: read from the source table, or typed under Other. */
    private function surveyorName(Request $r): ?string
    {
        $choice = (string) $r->input('surveyor_choice');
        if ($choice === '') return null;
        if ($choice === 'other') {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $r->input('surveyor_name_other')));
            if ($name === '') throw ValidationException::withMessages(['surveyor_name_other' => "Type the name of the surveyor who prepared the plan."]);
            return mb_substr($name, 0, 255);
        }
        [$src, $id] = array_pad(explode(':', $choice, 2), 2, null);
        $name = match ($src) {
            'staff'  => SurveyorListEntry::find((int) $id)?->full_name,
            'surcon' => DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')->where('id', (int) $id)->value('full_name'),
            default  => null,
        };
        if (! $name) throw ValidationException::withMessages(['surveyor_choice' => 'Choose the surveyor who prepared the plan.']);

        return trim($name);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        if (! empty($data['survey_register_file_id'])
            && ($existing = CR::where('survey_register_file_id', $data['survey_register_file_id'])->first())) {
            return redirect()->route('survey-module.workflow.change-reports.edit', $existing)
                ->with('error', "That Register File line already has {$existing->report_ref}; it is opened here.");
        }
        $report = CR::create($data + ['report_ref' => CR::nextRef('report_ref', 'SRC'), 'status' => CR::DRAFT]);

        return redirect()->route('survey-module.workflow.change-reports.edit', $report)
            ->with('success', "{$report->report_ref} saved as a draft. Issue it to print and send.");
    }

    public function edit(CR $report)
    {
        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $report->registerFile, 'records' => $this->records($report), 'surveyors' => $this->surveyorOptions()]);
    }

    public function update(Request $r, CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} has been issued and can no longer be edited.");
        $report->update($this->validated($r, $report));

        return back()->with('success', "{$report->report_ref} saved.");
    }

    public function issue(CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} is already issued.");
        $report->update(['status' => CR::ISSUED, 'issued_at' => now(), 'issued_by' => Auth::id()]);

        return redirect()->route('survey-module.workflow.change-reports.print', $report);
    }

    public function destroy(CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} has been issued and cannot be deleted.");
        $ref = $report->report_ref;
        $report->delete();

        return redirect()->route('survey-module.workflow.change-reports.index')->with('success', "Draft {$ref} deleted.");
    }

    public function print(CR $report)
    {
        return view('survey_module.workflow.change_reports.print', ['report' => $report, 'records' => $this->records($report)]);
    }

    /** The report's List of Coordinates, Beacon Nos, Field Observations and Sketch Plan. */
    private function records(CR $report): array
    {
        $anchor = app(SurveyItsAnchors::class)->resolve('private-' . $report->id);
        $coords = SurveyItsCoordinate::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();
        $beacons = app(BeaconNumberCheck::class)->forAnchor($anchor);
        $sketch = app(SurveyDocuments::class)->for('private', $report->id, SurveyDocuments::KIND_SKETCH_PLAN)->firstWhere('is_superseded', 0);

        return [
            'key'      => $anchor['key'],
            'coords'   => $coords,
            'beacons'  => $beacons,
            'summary'  => BeaconNumberCheck::summary($beacons),
            'readings' => SurveyItsObservation::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->count(),
            'sketch'   => $sketch,
        ];
    }

    private function validated(Request $r, ?CR $existing = null): array
    {
        $data = $r->validate([
            'survey_register_file_id' => ['nullable', Rule::exists('sqlsrv.survey_register_files', 'id')->whereNull('deleted_at')],
            // From a Register File line the file is the line's; otherwise it must be picked.
            'file_number'      => ($existing?->survey_register_file_id || $r->filled('survey_register_file_id')) ? 'nullable|string|max:100' : 'required|string|max:100',
            'file_title'       => 'nullable|string|max:500',
            'plot_no'          => 'nullable|string|max:100',
            'district'         => 'nullable|string|max:255',
            'lga'              => 'nullable|string|max:255',
            'plan_no'          => 'nullable|string|max:100',
            'surveyor_choice'  => 'nullable|string|max:50',
            'surveyor_name_other' => 'nullable|string|max:255',
            'discrepancies'    => 'required|string|max:4000',
            'required_changes' => 'required|string|max:4000',
            'report_date'      => 'nullable|date',
            'examined_by'      => 'nullable|string|max:255',
        ], [
            'file_number.required'      => 'Select the file with Select File Number, or raise the report from its Register File line.',
            'discrepancies.required'    => 'Describe what does not match the records.',
            'required_changes.required' => 'List the changes required.',
        ]);

        unset($data['surveyor_choice'], $data['surveyor_name_other']);
        $data['surveyor_name'] = $this->surveyorName($r);

        // A report raised from a Register File line keeps that line's file.
        $lineId = $existing?->survey_register_file_id ?? ($data['survey_register_file_id'] ?? null);
        if ($lineId && ($line = SurveyRegisterFile::find($lineId))) {
            $data['survey_register_file_id'] = $line->id;
            $data['file_number'] = $line->file_number;
        }

        return $data;
    }

    /** The list's filters, shared with Export Records. */
    private function reportQuery(Request $r)
    {
        $q = CR::query()->with('registerFile');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('report_ref', 'like', "%$term%")->orWhere('file_number', 'like', "%$term%")
                ->orWhere('plan_no', 'like', "%$term%")->orWhere('surveyor_name', 'like', "%$term%"));
        }
        if (in_array($s = $r->query('status'), [CR::DRAFT, CR::ISSUED], true)) $q->where('status', $s);

        return $q;
    }

    public function export(Request $r)
    {
        return $this->exportRecords($r, fn () => $this->reportQuery($r)->orderByDesc('id'), [
            'ref' => 'Report No', 'register_ref' => 'Register File', 'file_number' => 'File No.', 'plan_no' => 'Plan No.', 'surveyor' => 'Surveyor', 'plot_no' => 'Plot No.',
            'district' => 'District', 'lga' => 'LGA', 'date' => 'Date', 'status' => 'Status',
        ], fn ($x) => [
            'ref' => $x->report_ref, 'register_ref' => $x->registerFile?->register_ref, 'file_number' => $x->file_number, 'plan_no' => $x->plan_no, 'surveyor' => $x->surveyor_name,
            'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga, 'date' => $this->exportDate($x->report_date), 'status' => $x->status,
        ], 'report_date');
    }
}
