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
    /**
     * Accept by scan (2026-10-27): the unit scans the file's QR (or types its tracking ID / file
     * number) and the open file sent to this desk is accepted. The route has no {case}, so the
     * desk check the cadastral.desk middleware makes for case routes is made here.
     */
    public function acceptScan(Request $request, string $desk, \App\Services\CadastralDept\IntakeLookup $lookup,
                               \App\Services\CadastralDept\CadastralFileTracking $tracking, \App\Services\CadastralDept\DeskAccess $access)
    {
        $code = trim(mb_substr((string) $request->input('code', ''), 0, 2000));
        $back = redirect()->route('cadastral-dept.desks.show', $desk);
        if ($code === '') {
            return $back->with('error', 'Scan the file\'s QR code, or type its tracking ID or file number.');
        }
        $numbers = collect($lookup->scan($code)['matches'])->pluck('file_number')->map(fn ($n) => strtoupper(trim($n)))->all();
        if (!$numbers) {
            return $back->with('error', 'No file matches that code.');
        }
        $case = CadastralCase::query()->open()->where('current_desk', $desk)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $numbers)->orderByDesc('id')->first();
        if (!$case) {
            $elsewhere = CadastralCase::query()->open()->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $numbers)->first();

            return $back->with('error', $elsewhere
                ? "{$elsewhere->file_number} ({$elsewhere->case_ref}) is with {$elsewhere->deskShort()}, not this desk."
                : implode(', ', $numbers) . ' is not an open file in the department.');
        }
        if (!$access->canActOn($request->user(), $case)) {
            return $back->with('error', "Accepting {$case->case_ref} needs this desk's role.");
        }
        if (!\App\Services\CadastralDept\CadastralFileTracking::awaiting($case)) {
            return $back->with('success', "{$case->case_ref} ({$case->file_number}) is already accepted here.");
        }
        try {
            return $back->with('success', $tracking->accept($case, $request->user()?->id, 'scan'));
        } catch (\App\Services\InstrumentWorkflow\WorkflowGuardException $e) {
            return $back->with('error', $e->getMessage());
        }
    }

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
                ->orderByDesc('created_at')->orderByDesc('id')->get();

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
                    ->orderByDesc('created_at')->orderByDesc('id')->get();
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
            if (\App\Services\CadastralDept\CadastralFileTracking::installed() && !\App\Services\CadastralDept\CadastralFileTracking::autoAccept()) {   // sent here, not yet accepted (2026-10-27)
                array_splice($cards, 1, 0, [['label' => 'Awaiting acceptance', 'value' => $here->filter(fn ($c) => \App\Services\CadastralDept\CadastralFileTracking::awaiting($c))->count(),
                    'hint' => 'accept before working on it', 'colour' => 'purple']]);
            }
            if (($def->desk($desk)['head'] ?? null) && \App\Services\CadastralDept\HeadApproval::enabled()) {   // a unit: files its staff sent to the head
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
            $sent = $sentQuery->orderByDesc('created_at')->orderByDesc('id')->limit(self::SENT_LIMIT)->get();
            $cards[] = ['label' => 'Sent on', 'value' => $sentTotal, 'hint' => 'still open at another desk', 'colour' => 'gray'];

            // Finished files this desk handled (Registry: every file), read only and view only
            // (user, 2026-10-12). Newest first, capped like the sent list.
            $closedQuery = $filter(CadastralCase::query()->where('status', '!=', CadastralCase::STATUS_IN_PROGRESS)->with(['receiver']))
                ->where(fn ($q) => $q->where('current_desk', $desk)
                    ->orWhereHas('movements', fn ($m) => $m->where('from_desk', $desk)));
            $closedTotal = (clone $closedQuery)->count();
            $closed = $closedQuery->orderByDesc('created_at')->orderByDesc('id')->limit(self::SENT_LIMIT)->get();
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
                    $done = $doneQuery->orderByDesc('created_at')->orderByDesc('id')->limit(self::SENT_LIMIT)->get();
                    $closed = $done->filter(fn ($c) => !$c->isOpen())->values();
                    $sent = $done->filter(fn ($c) => $c->isOpen())->values();
                } elseif (!empty($queue['awaiting_head'])) {
                    // Approval keeps the files this unit sent to its head once they have moved on,
                    // read only, wherever they are now and even once completed (user, 2026-10-16).
                    $doneQuery = $filter(CadastralCase::query()->with(['receiver']))
                        ->whereNotIn('id', $here->pluck('id'))
                        ->where(fn ($q) => $q->where('current_desk', '!=', $desk)->orWhere('status', '!=', CadastralCase::STATUS_IN_PROGRESS))
                        ->whereHas('movements', fn ($m) => $m->where('outcome', \App\Services\CadastralDept\HeadApproval::TO_HEAD)->where('to_desk', $desk));
                    $sentTotal = (clone $doneQuery)->count();
                    $done = $doneQuery->orderByDesc('created_at')->orderByDesc('id')->limit(self::SENT_LIMIT)->get();
                    $closed = $done->filter(fn ($c) => !$c->isOpen())->values();
                    $sent = $done->filter(fn ($c) => $c->isOpen())->values();
                } elseif (array_intersect_key($queue, array_flip(['steps', 'to', 'at']))) {
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
            'acceptInstalled' => $installed && \App\Services\CadastralDept\CadastralFileTracking::installed()
                && !\App\Services\CadastralDept\CadastralFileTracking::autoAccept(),
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
            'fmcVisible' => FileMovementCards::canView(auth()->user()),   // config fmc_roles (2026-10-26)
            'waitingJobs' => $attachDue ? app(FieldJackets::class)->waitingJobs() : null,
            'surveyors' => $attachDue ? app(FieldJackets::class)->surveyors() : collect(),
            'filterWorkflow' => $wfFilter,
            'term' => $term,
            'queueKey' => $queueKey,
            'queueLabel' => $queue['label'] ?? null,
            'queueKeepsDone' => !empty($queue['tasks']) || !empty($queue['awaiting_head']),   // task and Approval lists: done files stay, read only
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
            if (!array_intersect(['steps', 'tasks', 'to', 'at', 'ready', 'awaiting_head', 'dms'], array_keys($entry))) {
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
    protected function menu(WorkflowEngine $engine, CadastralCase $case): array
    {
        $def = $engine->definition();
        $step = $def->step($case->workflow, $case->current_step);
        $missing = $engine->missingTasks($case->workflow, $case->current_step, $case->doneTaskKeys());
        // No correspondence file: nothing to detach or attach (as CaseWorkflow).
        if (!trim((string) $case->correspondence_fileno)) {
            $missing = array_values(array_diff($missing, CorrespondenceCustody::TASKS));
        }
        // The main file's page count to and from Land (PageCounts): no new pages, no DMS update;
        // a count still to enter is one more thing to do on the file page.
        $pageCounts = app(\App\Services\CadastralDept\PageCounts::class);
        $countDue = false;
        if ($pageCounts->direction($case)) {
            $pc = $pageCounts->state($case);
            $countDue = !$pc['recorded'];
            if ($pc['triggered'] === false) {
                $missing = array_values(array_diff($missing, [\App\Services\CadastralDept\EdmsUpdate::TASK]));
            }
        }

        // Registry's correspondence task is done from the menu: Attach Land File / Detach Correspondence.
        $custody = null;
        if (CorrespondenceCustody::installed()) {
            $custody = in_array('correspondence_detached', $missing, true) ? 'detach'
                : (in_array('correspondence_attached', $missing, true) ? 'attach' : null);
        }
        // Count first, then detach; DMS update first, then attach (client, 2026-10-18): until then
        // the menu says to finish the tasks on the file page.
        if ($custody && $pageCounts->custodyBlocker($case, $custody)) {
            $custody = null;
        }
        $open = count($missing) - ($custody ? 1 : 0) + ($countDue ? 1 : 0);

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
            // Final Charting: only the route the file's surveyor calls for (SG for a Government survey).
            $actions = app(\App\Services\CadastralDept\SurveyRoute::class)->filter($case, $actions);
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

        return ['tasks_open' => $open, 'custody' => $custody, 'jacket' => $jacket, 'edms' => in_array(\App\Services\CadastralDept\EdmsUpdate::TASK, $missing, true), 'actions' => ($failing || $custody) ? [] : $actions, 'failing' => $failing,
            'question' => $question, 'decision' => $decision, 'head' => $head];
    }
}
