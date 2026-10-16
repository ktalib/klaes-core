<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCharting;
use App\Services\CadastralDept\CasePurge;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\DeskQueues;
use App\Services\CadastralDept\ChartingCards;
use App\Services\CadastralDept\CorrespondenceCustody;
use App\Services\CadastralDept\CorrespondenceStep;
use App\Services\CadastralDept\DeedsDispatchChecks;
use App\Services\CadastralDept\FieldJackets;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\IntakeLookup;
use App\Services\CadastralDept\VerificationFileStatus;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use App\Services\CadastralDept\YesNoForms;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    use RunsWorkflowActions;
    use ExecutesMasterDelete;

    public function index(Request $request, WorkflowDefinition $def)
    {
        $installed = CaseWorkflow::installed();
        $cases = null;

        if ($installed) {
            $q = CadastralCase::query()->orderByDesc('id');
            if ($wf = $request->query('workflow')) {
                $q->where('workflow', $wf);
            }
            if ($status = $request->query('status')) {
                $q->where('status', $status);
            }
            if ($term = trim((string) $request->query('q'))) {
                $q->where(fn ($w) => $w->where('file_number', 'like', "%{$term}%")
                    ->orWhere('case_ref', 'like', "%{$term}%")
                    ->orWhere('file_title', 'like', "%{$term}%"));
            }
            $cases = $q->paginate(25)->withQueryString();

            // Summary cards, within the application type picked (if any); each filters the list.
            $byStatus = CadastralCase::query()->when($wf, fn ($w) => $w->where('workflow', $wf))
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $link = fn (?string $s) => route('cadastral-dept.cases.index', array_filter(['workflow' => $wf, 'status' => $s]));
            $cdCards = [
                ['label' => 'All files', 'value' => number_format($byStatus->sum()), 'hint' => 'ever registered', 'colour' => 'gray', 'url' => $link(null), 'active' => !$status],
                ['label' => 'In progress', 'value' => number_format($byStatus[CadastralCase::STATUS_IN_PROGRESS] ?? 0), 'hint' => 'open in the department', 'colour' => 'blue', 'url' => $link('in_progress'), 'active' => $status === 'in_progress'],
                ['label' => 'Completed', 'value' => number_format($byStatus['completed'] ?? 0), 'hint' => 'sent to Deeds / returned to requester', 'colour' => 'green', 'url' => $link('completed'), 'active' => $status === 'completed'],
                ['label' => 'Sent back', 'value' => number_format($byStatus['returned'] ?? 0), 'hint' => 'returned to source', 'colour' => 'red', 'url' => $link('returned'), 'active' => $status === 'returned'],
                ['label' => 'Registered this month', 'value' => number_format(CadastralCase::query()->when($wf, fn ($w) => $w->where('workflow', $wf))
                    ->where('created_at', '>=', now()->startOfMonth())->count()), 'hint' => now()->format('F Y'), 'colour' => 'indigo'],
            ];
        }

        return view('cadastral_dept.cases.index', ['def' => $def, 'installed' => $installed, 'cases' => $cases, 'cdCards' => $cdCards ?? []]);
    }

    /** Registry intake form (the Intake Queue registration, copied for this workflow). */
    public function create(WorkflowDefinition $def)
    {
        return view('cadastral_dept.cases.create', [
            'def' => $def,
            'installed' => CaseWorkflow::installed(),
        ]);
    }

    /**
     * The intake form's file check: called after a file is picked from the list.
     * GET with a verb-free name, so the permission layer infers view.
     */
    public function lookupFile(Request $request, IntakeLookup $lookup): JsonResponse
    {
        return response()->json($lookup->resolve([
            'file_number' => mb_substr((string) $request->query('file_number', ''), 0, 100),
            'tab' => mb_substr((string) $request->query('tab', ''), 0, 20),
            'file_indexing_id' => (int) $request->query('file_indexing_id', 0),
            'workflow' => mb_substr((string) $request->query('workflow', ''), 0, 20),
        ]));
    }

    /** The intake form's file dropdown: indexed files for the application type, filtered by what was typed. */
    public function lookupFiles(Request $request, IntakeLookup $lookup): JsonResponse
    {
        return response()->json($lookup->options(
            mb_substr((string) $request->query('workflow', ''), 0, 20),
            mb_substr((string) $request->query('q', ''), 0, 100)
        ));
    }

    /**
     * Register the file. It is re-read here, so only the number (or the chosen
     * index row) comes from the browser; title and location come from the records.
     */
    public function store(Request $request, CaseWorkflow $workflow, IntakeLookup $lookup)
    {
        $data = $request->validate([
            'workflow' => 'required|string|max:20',
            'file_type' => 'required|string|max:20',
            'sub_type' => 'nullable|string|max:30',
            'allocation_route' => 'nullable|string|max:30',
            'file_source' => 'required|string|max:20',
            'file_source_other' => 'nullable|required_if:file_source,other|string|max:100',
            'file_number' => 'required|string|max:100',
            'file_indexing_id' => 'nullable|integer|min:1',
            'file_system' => 'nullable|string|max:20',
            'tracking_id' => 'nullable|string|max:50',
            'received_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:2000',
        ], [
            'file_number.required' => 'Pick the file from the list.',
            'file_type.required' => 'Choose the file type.',
            'file_source.required' => 'Choose where the file came from.',
            'file_source_other.required_if' => 'Specify where the file came from.',
        ]);

        $found = $lookup->resolve([
            'file_number' => $data['file_number'],
            'tab' => $data['file_system'] ?? '',
            'file_indexing_id' => (int) ($data['file_indexing_id'] ?? 0),
            'workflow' => $data['workflow'],
        ]);
        if ($found['status'] !== 'ok') {
            return back()->withInput()->with('error', $found['message'] ?: 'Pick the file again.');
        }
        // Verification takes any indexed file (user, 2026-10-15); without a correspondence
        // file the Registry's detach step is simply not needed (CaseWorkflow::advance).

        $file = $found['file'];

        try {
            $case = $workflow->open([
                'workflow' => $data['workflow'],
                'file_type' => $data['file_type'],
                'sub_type' => $data['sub_type'] ?? null,
                'allocation_route' => $data['allocation_route'] ?? null,
                'file_source' => $data['file_source'],
                'file_source_other' => $data['file_source_other'] ?? null,
                'file_number' => $file['file_number'],
                'file_indexing_id' => $file['file_indexing_id'],
                'file_system' => $data['file_system'] ?? null,
                'tracking_id' => $data['tracking_id'] ?? null,
                'file_title' => $file['title'],
                'plot_no' => $file['plot'],
                'district' => $file['district'],
                'lga' => $file['lga'],
                'location' => $file['location'],
                'correspondence_fileno' => $found['correspondence']['fileno'],
                'shadow_fileno' => $found['correspondence']['shadow'] ?? null,
                'duplicate_flag' => $found['duplicate']['flag'],
                'duplicate_note' => $found['duplicate']['note'],
                'received_at' => $data['received_at'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ], auth()->id());
        } catch (WorkflowGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "{$case->case_ref}: {$case->file_number} registered and sent to " . $case->deskShort() . '.';
        if ($case->duplicate_flag) {
            $message .= ' Note, possible duplicate: ' . $case->duplicate_note;
        }

        return redirect()->route('cadastral-dept.desks.show', 'registry')->with('success', $message);
    }

    public function show(int $case, WorkflowEngine $engine, FileMovementCards $cards, FieldJackets $jackets, CorrespondenceCustody $custody, DeedsDispatchChecks $checks)
    {
        abort_unless(CaseWorkflow::installed(), 404);
        $case = CadastralCase::with(['movements.actor', 'tasks.doer', 'receiver'])->findOrFail($case);
        $def = $engine->definition();

        // The FMC: the card if it exists, else what the records would fill in.
        $fmc = FileMovementCards::installed() ? $case->fmc : null;
        $fmcBackfill = FileMovementCards::installed() && ! $fmc && $cards->dueNow($case) ? $cards->backfill($case) : null;

        $actions = [];
        if ($case->isOpen()) {
            try {
                $actions = $engine->actions($case->workflow, $case->current_step, $case->sub_type);
            } catch (WorkflowGuardException $e) {
                // A sub-type route with no sub-type yet: the page asks for it instead.
            }
        }
        // Where each choice sends the file, shown on the button and in its confirmation.
        foreach ($actions as &$a) {
            $a['to'] = $a['next']
                ? $def->deskShort($def->step($case->workflow, $a['next'])['desk'])
                : ($a['ends'] === 'returned' ? $case->fileSourceLabel() : 'Deeds / requester');
        }
        unset($a);
        // In a unit, staff send to the head; the step's real actions are greyed out for them.
        $headInfo = app(\App\Services\CadastralDept\HeadApproval::class)->shape($case, $actions, auth()->user());
        $actions = $headInfo['actions'];

        // P&D: plain field jacket and I to S; AD Cad Info: job, surveyor and the job's instruction.
        $fieldJacket = $jackets->forCase($case);
        $doneKeys = $case->doneTaskKeys();
        $jobDue = FieldJackets::installed() && $fieldJacket && !$fieldJacket->cadastral_survey_job_id && $jackets->due($case, FieldJackets::TASK_JOB);
        $pndItsDefault = $fieldJacket && $jackets->due($case, FieldJackets::TASK_ITS) && !in_array(FieldJackets::TASK_ITS, $doneKeys, true)
            ? $jackets->defaultPndInstruction($fieldJacket) : null;
        $itsDefault = $fieldJacket?->job && !$fieldJacket->job->its_number && $jackets->due($case, FieldJackets::TASK_JOB_ITS)
            ? $jackets->defaultInstructions($fieldJacket) : null;

        return view('cadastral_dept.cases.show', [
            'case' => $case,
            'def' => $def,
            'step' => $def->step($case->workflow, $case->current_step),
            'actingDesk' => $def->actingDesk($case->workflow, $case->current_step),
            'actions' => $actions,
            'headInfo' => $headInfo,
            'done' => $case->tasks->where('step', $case->current_step)->keyBy('task_key'),
            'progress' => $engine->phaseProgress($case->workflow, $case->current_step, !$case->isOpen()),
            'pipeline' => $engine->pipeline($case->workflow, $case->sub_type, $case->movements,
                $case->isOpen() ? $case->current_step : null, $case->isOpen() ? null : $case->status),
            'fmc' => $fmc,
            'fmcBackfill' => $fmcBackfill,
            'custodyInstalled' => CorrespondenceCustody::installed(),
            'custodyDue' => $custody->due($case),
            'custodyHistory' => $custody->history($case),
            'checks' => $case->isOpen() ? $checks->results($case) : [],
            'jacketsInstalled' => FieldJackets::installed(),
            'fieldJacket' => $fieldJacket,
            'jobDue' => $jobDue,
            'surveyors' => $jobDue ? $jackets->surveyors() : collect(),
            'waitingJobs' => $jobDue ? $jackets->waitingJobs() : collect(),
            'pndItsDefault' => $pndItsDefault,
            'itsDefault' => $itsDefault,
            'yesNoInstalled' => YesNoForms::installed(),
            'yesNoForm' => app(YesNoForms::class)->forCase($case),
            'chartingInstalled' => ChartingCards::installed(),
            'chartings' => ChartingCards::installed()
                ? CadastralCharting::query()->with('officer')->where('cadastral_case_id', $case->id)->where('is_current', true)->orderBy('id')->get()
                : collect(),
        ]);
    }

    /** The Verification file-status panel (read only), fetched by the case page. */
    public function fileStatus(int $case, VerificationFileStatus $status)
    {
        abort_unless(CaseWorkflow::installed(), 404);
        $case = CadastralCase::findOrFail($case);

        return view('cadastral_dept.cases._file_status', ['rows' => $status->results($case)]);
    }

    /** The same panel for a file picked on Register File, before there is a case. */
    public function lookupFileStatus(Request $request, VerificationFileStatus $status)
    {
        $data = $request->validate(['file_number' => 'required|string|max:100', 'file_indexing_id' => 'nullable|integer']);
        $case = new CadastralCase();
        $case->file_number = $data['file_number'];
        $case->file_indexing_id = $data['file_indexing_id'] ?? null;

        return view('cadastral_dept.cases._file_status', ['rows' => $status->results($case)]);
    }

    public function completeTask(Request $request, int $case, CaseWorkflow $workflow)
    {
        $data = $request->validate(['task' => 'required|string|max:50', 'note' => 'nullable|string|max:1000']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $workflow->completeTask($case, $data['task'], $data['note'] ?? null, auth()->id()), 'Task done.');
    }

    public function advance(Request $request, int $case, CaseWorkflow $workflow)
    {
        $data = $request->validate(['outcome' => 'required|string|max:30', 'remarks' => 'nullable|string|max:2000']);
        $case = CadastralCase::findOrFail($case);

        // Head-of-unit approval: staff send to the head; the head can return it (HeadApproval).
        return $this->attempt(fn () => match ($data['outcome']) {
            \App\Services\CadastralDept\HeadApproval::TO_HEAD => $workflow->sendToHead($case, $data['remarks'] ?? null, auth()->id()),
            \App\Services\CadastralDept\HeadApproval::RETURNED => $workflow->returnToStaff($case, $data['remarks'] ?? null, auth()->id()),
            default => $workflow->advance($case, $data['outcome'], $data['remarks'] ?? null, auth()->id()),
        }, 'Case moved.');
    }

    /** Create (or match) the file's correspondence file at AD Cad Report. */
    public function createCorrespondence(int $case, CorrespondenceStep $step)
    {
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $step->run($case, auth()->id()), 'Correspondence file done.');
    }

    /** Registry: detach or attach the correspondence file (ticks the step's task). */
    public function correspondenceCustody(Request $request, int $case, CorrespondenceCustody $custody)
    {
        $data = $request->validate(['action' => 'required|in:detach,attach', 'note' => 'nullable|string|max:1000']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $custody->record($case, $data['action'], $data['note'] ?? null, auth()->id()),
            $data['action'] === 'detach'
                ? "{$case->case_ref}: correspondence file detached from {$case->file_number}."
                : "{$case->case_ref}: correspondence file attached to {$case->file_number}.");
    }

    /**
     * Master Delete: the case and everything the workflow made for it. Supper Admin
     * only, with the case reference typed back (ExecutesMasterDelete, as the Land and
     * SLTR master deletes). Used to clear test records.
     */
    public function masterDestroy(Request $request, int $case, CasePurge $purge): JsonResponse
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }
        $case = CadastralCase::find($case);
        if (!$case) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }
        if ($deny = $this->denyUnlessConfirmationMatches($request, $case->case_ref)) {
            return $deny;
        }
        if ($blockers = $purge->blockers($case)) {
            return response()->json(['success' => false, 'message' => implode(' ', $blockers)], 422);
        }

        $snapshot = $case->toArray();
        $db = DB::connection('sqlsrv');
        $db->beginTransaction();
        try {
            $counts = $purge->purge($case);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Log::error('Cadastral case master delete failed', ['id' => $case->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not delete: ' . $e->getMessage()], 500);
        }

        Cache::forget(DeskQueues::CACHE_KEY);
        $this->logMasterDelete('cadastral_case', $case->id, $snapshot, $counts, "Cadastral case {$case->case_ref} ({$case->file_number})");

        return response()->json([
            'success' => true,
            'message' => "{$case->case_ref} ({$case->file_number}) deleted.",
            'details' => $counts,
        ]);
    }

    public function setSubType(Request $request, int $case, CaseWorkflow $workflow)
    {
        $data = $request->validate(['sub_type' => 'required|string|max:30']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $workflow->setSubType($case, $data['sub_type'], auth()->id()), 'Sub-type set.');
    }
}
