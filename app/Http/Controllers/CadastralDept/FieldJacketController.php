<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralFieldJacket;
use App\Services\Cadastral\CadastralSettings;
use App\Services\CadastralDept\FieldJackets;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\DocumentQr\QrRenderer;
use Illuminate\Http\Request;

/** Field Jacket, job, surveyor and I to S (step 10). */
class FieldJacketController extends Controller
{
    use RunsWorkflowActions;

    public function __construct(private FieldJackets $jackets)
    {
    }

    public function index(Request $request, WorkflowDefinition $def)
    {
        $installed = FieldJackets::installed();
        $term = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), CadastralSurveyJob::STATUSES, true) ? $request->query('status') : null;
        $rows = null;

        if ($installed) {
            $rows = CadastralFieldJacket::query()->with(['case', 'job'])
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('field_jacket_no', 'like', "%{$term}%")
                    ->orWhere('file_number', 'like', "%{$term}%")
                    ->orWhereHas('job', fn ($j) => $j->where('job_number', 'like', "%{$term}%")
                        ->orWhere('its_number', 'like', "%{$term}%")
                        ->orWhere('surveyor_name', 'like', "%{$term}%"))))
                ->when($status, fn ($q) => $q->whereHas('job', fn ($j) => $j->where('status', $status)))
                ->orderByDesc('id')
                ->paginate(25)->withQueryString();
        }

        return view('cadastral_dept.field_jackets.index', compact('def', 'installed', 'term', 'status', 'rows'));
    }

    public function store(Request $request, int $case)
    {
        $data = $request->validate([
            'cadastral_surveyor_id' => 'required|integer',
            'job_scope' => 'nullable|string|max:4000',
            'remarks' => 'nullable|string|max:2000',
        ], ['cadastral_surveyor_id.required' => 'Choose the surveyor.']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case, $data) {
            $jacket = $this->jackets->commission($case, $data, auth()->id());
            $msg = "Field jacket {$jacket->field_jacket_no} commissioned with job {$jacket->job->job_number}, surveyor {$jacket->job->surveyor_name}.";
            if (app(CadastralSettings::class)->jobNumberFormat()['is_placeholder']) {
                $msg .= ' The job-number format is still the unconfirmed placeholder.';
            }

            return $msg;
        }, 'Field jacket commissioned.');
    }

    public function issueIts(Request $request, int $case)
    {
        $data = $request->validate([
            'its_instructions' => 'required|string|max:8000',
            'its_officer_post' => 'nullable|string|max:30',
        ], ['its_instructions.required' => 'Write the instruction text.']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case, $data) {
            $job = $this->jackets->issueIts($case, $data, auth()->id());

            return "I to S {$job->its_number} issued to {$job->its_recipient}.";
        }, 'I to S issued.');
    }

    public function reassign(Request $request, int $jacket)
    {
        $data = $request->validate([
            'cadastral_surveyor_id' => 'required|integer',
            'reason' => 'required|string|max:1000',
        ], [
            'cadastral_surveyor_id.required' => 'Choose the new surveyor.',
            'reason.required' => 'Give the reason for the reassignment.',
        ]);
        abort_unless(FieldJackets::installed(), 404);
        $jacket = CadastralFieldJacket::findOrFail($jacket);

        return $this->attempt(fn () => $this->jackets->reassign($jacket, $data['cadastral_surveyor_id'], $data['reason'], auth()->id()), 'Reassigned.');
    }

    public function show(int $jacket, WorkflowDefinition $def)
    {
        abort_unless(FieldJackets::installed(), 404);
        $jacket = CadastralFieldJacket::with(['case', 'job', 'surveyor', 'assignments.assigner', 'commissioner'])->findOrFail($jacket);

        return view('cadastral_dept.field_jackets.show', [
            'def' => $def,
            'jacket' => $jacket,
            'surveyors' => $this->jackets->surveyors(),
            'canReassign' => $jacket->case->isOpen() && !in_array($jacket->job->status, ['Accepted', 'Cancelled'], true),
        ]);
    }

    /** The jacket cover: its numbers, the file, the surveyor and a QR code to the file's page. */
    public function print(int $jacket, QrRenderer $qr)
    {
        abort_unless(FieldJackets::installed(), 404);
        $jacket = CadastralFieldJacket::with(['case', 'job'])->findOrFail($jacket);
        $this->jackets->countPrint($jacket);

        return view('cadastral_dept.field_jackets.print', [
            'jacket' => $jacket,
            'qr' => $qr->dataUri(route('cadastral-dept.cases.show', $jacket->cadastral_case_id), 150),
        ]);
    }

    public function printIts(int $jacket)
    {
        abort_unless(FieldJackets::installed(), 404);
        $jacket = CadastralFieldJacket::with(['job.surveyor'])->findOrFail($jacket);
        if (!$jacket->job->its_number) {
            return redirect()->route('cadastral-dept.field-jackets.show', $jacket->id)->with('error', 'The I to S has not been issued yet.');
        }

        return view('cadastral_dept.field_jackets.its_print', [
            'jacket' => $jacket,
            'job' => $jacket->job,
            'postName' => config('cadastral_module.posts')[$jacket->job->its_officer_post] ?? null,
        ]);
    }
}
