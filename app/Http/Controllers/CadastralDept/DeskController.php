<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\CorrespondenceCustody;
use App\Services\CadastralDept\FieldJackets;
use App\Services\CadastralDept\DeedsDispatchChecks;
use App\Services\CadastralDept\DeskQueues;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;

class DeskController extends Controller
{
    /** Rows of "sent on" files shown on a desk page. */
    private const SENT_LIMIT = 200;

    /**
     * A desk's listing. Each row carries an action menu with only what the file
     * can do at its current step (client brief: actions switch on at the right
     * step, never all at once).
     *
     * External desks have no listing of their own: files out with another
     * department are listed under the Cadastral desk that receives them back.
     * One table per page: files at the desk, those out with another department,
     * then (view only) the open files this desk has already sent on.
     */
    public function show(Request $request, string $desk, WorkflowEngine $engine)
    {
        $def = $engine->definition();
        abort_unless(isset($def->internalDesks()[$desk]), 404);

        $wfFilter = $def->has((string) $request->query('workflow')) ? (string) $request->query('workflow') : null;
        $term = trim((string) $request->query('q', ''));
        $queueKey = (string) $request->query('queue', '');
        $queue = $queueKey !== '' ? $def->queue($desk, $queueKey) : null;
        if (!$queue) {
            $queueKey = '';
        }
        $filter = function ($q) use ($wfFilter, $term) {
            return $q->when($wfFilter, fn ($w) => $w->where('workflow', $wfFilter))
                ->when($term !== '', fn ($w) => $w->where(fn ($x) => $x->where('file_number', 'like', "%{$term}%")
                    ->orWhere('case_ref', 'like', "%{$term}%")
                    ->orWhere('file_title', 'like', "%{$term}%")));
        };

        $installed = CaseWorkflow::installed();
        $here = $away = $sent = $closed = collect();
        $cards = [];

        if ($installed) {
            $here = $filter(CadastralCase::query()->open()->with(['tasks', 'latestMovement', 'receiver'])->where('current_desk', $desk))
                ->orderBy('updated_at')->get();

            $recorded = [];
            foreach ($def->stepsForDesk($desk) as $wf => $steps) {
                foreach ($steps as $step) {
                    if (!empty($def->step($wf, $step)['external'])) {
                        $recorded[$wf][] = $step;
                    }
                }
            }
            if ($recorded) {
                $away = $filter(CadastralCase::query()->open()->with(['tasks', 'latestMovement', 'receiver']))
                    ->where(function ($q) use ($recorded) {
                        foreach ($recorded as $wf => $steps) {
                            $q->orWhere(fn ($w) => $w->where('workflow', $wf)->whereIn('current_step', $steps));
                        }
                    })
                    ->orderBy('updated_at')->get();
            }

            // Summary cards: the whole desk, before any sidebar list narrows it.
            $open = $here->filter(fn ($c) => $engine->missingTasks($c->workflow, $c->current_step, $c->doneTaskKeys()) !== []);
            $base = route('cadastral-dept.desks.show', $desk);
            $cards = [
                ['label' => 'At this desk', 'value' => $here->count(), 'hint' => 'open files waiting here', 'colour' => 'blue', 'url' => $base, 'active' => !$queue],
                ['label' => 'Tasks still open', 'value' => $open->count(), 'hint' => 'finish the tasks before sending', 'colour' => 'yellow'],
                ['label' => 'Ready to send on', 'value' => $here->count() - $open->count(), 'hint' => 'every task done', 'colour' => 'green'],
                ['label' => 'Waiting over 7 days', 'value' => $here->filter(fn ($c) => $c->updated_at && $c->updated_at->lt(now()->subDays(7)))->count(), 'hint' => 'no movement in a week', 'colour' => 'red'],
            ];
            if ($def->desk($desk)['head'] ?? null) {   // a unit: files its staff sent to the head
                $heads = app(\App\Services\CadastralDept\HeadApproval::class);
                $cards[] = ['label' => 'With the ' . $def->desk($desk)['head'], 'value' => $here->filter(fn ($c) => $heads->awaiting($c))->count(),
                    'hint' => 'sent for approval', 'colour' => 'purple',
                    'url' => route('cadastral-dept.desks.show', ['desk' => $desk, 'queue' => 'approval']), 'active' => $queueKey === 'approval'];
            }
            if ($recorded) {   // only desks that record another department's return
                $cards[] = ['label' => 'Out with other departments', 'value' => $away->count(), 'hint' => 'record when they come back', 'colour' => 'purple'];
            }

            // Open files this desk sent on (Registry: every file it registered), shown with
            // where they are now. Newest first, capped; search finds older ones.
            $sentQuery = $filter(CadastralCase::query()->open()->with(['receiver']))
                ->where('current_desk', '!=', $desk)
                ->whereNotIn('id', $away->pluck('id'))
                ->whereHas('movements', fn ($m) => $m->where('from_desk', $desk));
            $sentTotal = (clone $sentQuery)->count();
            $sent = $sentQuery->orderByDesc('updated_at')->limit(self::SENT_LIMIT)->get();
            $cards[] = ['label' => 'Sent on', 'value' => $sentTotal, 'hint' => 'still open at another desk', 'colour' => 'gray'];

            // Finished files this desk handled (Registry: every file), read only and view only
            // (user, 2026-10-12). Newest first, capped like the sent list.
            $closedQuery = $filter(CadastralCase::query()->where('status', '!=', CadastralCase::STATUS_IN_PROGRESS)->with(['receiver']))
                ->where(fn ($q) => $q->where('current_desk', $desk)
                    ->orWhereHas('movements', fn ($m) => $m->where('from_desk', $desk)));
            $closedTotal = (clone $closedQuery)->count();
            $closed = $closedQuery->orderByDesc('updated_at')->limit(self::SENT_LIMIT)->get();
            $cards[] = ['label' => 'Finished', 'value' => $closedTotal, 'hint' => 'completed or returned, view only', 'colour' => 'gray'];

            // A sidebar list narrows the inbox to the files where its action is due.
            if ($queue) {
                $queues = app(DeskQueues::class);
                $here = $here->filter(fn ($c) => $queues->matches($c, $queue))->values();
                $away = $away->filter(fn ($c) => $queues->matches($c, $queue))->values();
                // A list with no rule (Registry's Intake Queue) is the whole desk, so keep them.
                if (!empty($queue['tasks'])) {
                    // A task list keeps the files whose task is done, read only, wherever they are
                    // now and even once completed (user, 2026-10-14). Newest first, capped.
                    $doneQuery = $filter(CadastralCase::query()->with(['receiver']))
                        ->whereNotIn('id', $here->concat($away)->pluck('id'))
                        ->whereHas('tasks', fn ($t) => $t->whereIn('task_key', (array) $queue['tasks']));
                    $sentTotal = (clone $doneQuery)->count();
                    $done = $doneQuery->orderByDesc('updated_at')->limit(self::SENT_LIMIT)->get();
                    $closed = $done->filter(fn ($c) => !$c->isOpen())->values();
                    $sent = $done->filter(fn ($c) => $c->isOpen())->values();
                } elseif (array_intersect_key($queue, array_flip(['steps', 'to', 'at', 'awaiting_head']))) {
                    $sent = $closed = collect();
                }
                // A Send ('ready') list keeps the desk's sent-on and finished files.
            }
        }

        $menus = [];
        foreach ($here->concat($away) as $case) {
            $menus[$case->id] = $this->menu($engine, $case);
        }
        // Assign to Surveyor (I to S): the jobs waiting for a file, for the Attach Survey Job pop-up.
        $attachDue = collect($menus)->contains(fn ($m) => $m['jacket'] === 'attach_job');

        return view('cadastral_dept.desk', [
            'def' => $def,
            'desk' => $desk,
            'deskInfo' => $def->desk($desk),
            'installed' => $installed,
            'here' => $here,
            'away' => $away,
            'sent' => $sent,
            'closed' => $closed,
            'sentTotal' => $sentTotal ?? 0,
            'sentLimit' => self::SENT_LIMIT,
            'menus' => $menus,
            'fmcInstalled' => FileMovementCards::installed(),
            'waitingJobs' => $attachDue ? app(FieldJackets::class)->waitingJobs() : null,
            'surveyors' => $attachDue ? app(FieldJackets::class)->surveyors() : collect(),
            'filterWorkflow' => $wfFilter,
            'term' => $term,
            'queueKey' => $queueKey,
            'queueLabel' => $queue['label'] ?? null,
            'queueKeepsDone' => !empty($queue['tasks']),   // task list: done files stay, read only
            // Header as the sidebar names it: the list opened, or the desk's whole-inbox
            // entry (Intake Queue, All files at ...), or the desk itself (D Cad).
            'pageTitle' => $queue['label'] ?? $this->sidebarTitle($def, $desk),
            'pageGroup' => $def->queues()[$desk]['group'] ?? $def->desk($desk)['short'],
            'cdCards' => $cards,
        ]);
    }

    /** The sidebar's name for a desk's whole inbox: its rule-less entry, else the desk group. */
    private function sidebarTitle(WorkflowDefinition $def, string $desk): string
    {
        $q = $def->queues()[$desk] ?? null;
        foreach ((array) ($q['entries'] ?? []) as $entry) {
            if (!array_intersect(['steps', 'tasks', 'to', 'at', 'ready', 'awaiting_head'], array_keys($entry))) {
                return $entry['label'];
            }
        }

        return $q['group'] ?? $def->desk($desk)['short'];
    }

    /**
     * The row's action menu: the step's actions, or "finish the tasks" while any
     * task is open. An action that needs a reason opens the file page instead.
     *
     * @return array{tasks_open:int, actions:array, question:?string}
     */
    private function menu(WorkflowEngine $engine, CadastralCase $case): array
    {
        $def = $engine->definition();
        $step = $def->step($case->workflow, $case->current_step);
        $missing = $engine->missingTasks($case->workflow, $case->current_step, $case->doneTaskKeys());

        // Registry's correspondence task is done from the menu: Attach Land File / Detach Correspondence.
        $custody = null;
        if (CorrespondenceCustody::installed()) {
            $custody = in_array('correspondence_detached', $missing, true) ? 'detach'
                : (in_array('correspondence_attached', $missing, true) ? 'attach' : null);
        }
        $open = count($missing) - ($custody ? 1 : 0);

        // Field jacket work, in order (transcript, 2026-10-16): P&D commissions the jacket and
        // issues the I to S; AD Cad Info attaches the job and surveyor, then issues the instruction.
        $jacket = null;
        if (FieldJackets::installed()) {
            foreach ([FieldJackets::TASK_JACKET => 'commission', FieldJackets::TASK_ITS => 'pnd_its',
                FieldJackets::TASK_JOB => 'attach_job', FieldJackets::TASK_JOB_ITS => 'job_its'] as $task => $kind) {
                if (in_array($task, $missing, true)) {
                    $jacket = $kind;
                    break;
                }
            }
        }

        try {
            $actions = $engine->actions($case->workflow, $case->current_step, $case->sub_type);
        } catch (WorkflowGuardException $e) {
            $actions = [];   // an OP file with no sub-type yet: the file page asks for it
        }

        // Where each choice sends the file, for the confirmation pop-up.
        foreach ($actions as &$a) {
            $a['to'] = $a['next']
                ? $def->deskShort($def->step($case->workflow, $a['next'])['desk'])
                : ($a['ends'] === 'returned' ? $case->fileSourceLabel() : 'Deeds / requester');
        }
        unset($a);

        // In a unit, staff send to the head; only the head sends across (HeadApproval).
        $head = app(\App\Services\CadastralDept\HeadApproval::class)->shape($case, $actions, auth()->user());
        $actions = $head['actions'];

        // Checks (Send to Deeds): failing ones are listed instead of the action; staff
        // sending to the head are not held up by them (the head's Send to Deeds is).
        $failing = ($open || $custody || ($head['required'] && !$head['is_head'])) ? [] : app(DeedsDispatchChecks::class)->failing($case);

        // A decision (a question with several answers) is one menu item, e.g. "Recommendation",
        // whose pop-up offers the answers.
        $decision = !empty($step['question']) && count($actions) > 1 ? ($step['menu_label'] ?? $step['label']) : null;
        // The staff's single "Send to <head>" is not the step's question.
        $question = $head['required'] && !$head['is_head'] ? null : ($step['question'] ?? null);

        return ['tasks_open' => $open, 'custody' => $custody, 'jacket' => $jacket, 'actions' => ($failing || $custody) ? [] : $actions, 'failing' => $failing,
            'question' => $question, 'decision' => $decision, 'head' => $head];
    }
}
