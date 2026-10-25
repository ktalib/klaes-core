<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyRegisterFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Examination/Verification > Surveyors' Report (Changes) (docs/KLAES-CORE Survey
 * Module.md 5.7): "if discrepancies are found between the submitted plan and the
 * existing records, this unit generates and prints a Surveyors Report detailing the
 * required changes." Raised from a Register File line (its file details are taken
 * from it) or on its own. Draft until issued; issuing locks and prints it.
 */
class ChangeReportController extends Controller
{
    public function index(Request $r)
    {
        $q = CR::query()->with('registerFile');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('report_ref', 'like', "%$term%")->orWhere('file_number', 'like', "%$term%")
                ->orWhere('plan_no', 'like', "%$term%")->orWhere('surveyor_name', 'like', "%$term%"));
        }
        if (in_array($s = $r->query('status'), [CR::DRAFT, CR::ISSUED], true)) $q->where('status', $s);

        return view('survey_module.workflow.change_reports.index', [
            'reports' => $q->orderByDesc('id')->paginate(20)->withQueryString(),
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

        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $line]);
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
        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $report->registerFile]);
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
        return view('survey_module.workflow.change_reports.print', ['report' => $report]);
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
            'surveyor_name'    => 'nullable|string|max:255',
            'discrepancies'    => 'required|string|max:4000',
            'required_changes' => 'required|string|max:4000',
            'report_date'      => 'nullable|date',
            'examined_by'      => 'nullable|string|max:255',
        ], [
            'file_number.required'      => 'Select the file with Select File Number, or raise the report from its Register File line.',
            'discrepancies.required'    => 'Describe what does not match the records.',
            'required_changes.required' => 'List the changes required.',
        ]);

        // A report raised from a Register File line keeps that line's file.
        $lineId = $existing?->survey_register_file_id ?? ($data['survey_register_file_id'] ?? null);
        if ($lineId && ($line = SurveyRegisterFile::find($lineId))) {
            $data['survey_register_file_id'] = $line->id;
            $data['file_number'] = $line->file_number;
        }

        return $data;
    }
}
