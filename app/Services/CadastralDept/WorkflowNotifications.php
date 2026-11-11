<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralOfficer;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * In-app notifications for the Cadastral Dept workflow (user, 2026-10-14): the header bell.
 *
 * The one-working-day timer adds its own (timerAlert / timerSummary, from CadastralCaseTimer): Due soon,
 * Warning, Overdue, Penalty; and a file reaching a timed office is told when it must move on (dueLine).
 *
 * Four events, all read off one movement (CaseWorkflow::move):
 *   arrived    a file reaches a desk (registered, or sent on)  -> that desk's officers
 *   sent_back  a desk returns a file, with the reason          -> the receiving desk's officers
 *   out / back the file goes to another department / returns   -> the desk that records the return
 *   closed     completed, or returned to its source            -> the closing desk, Registry, and
 *                                                                 the officer who registered it
 *
 * A desk's officers are the users holding its desk role (the role that opens the desk), plus
 * anyone set on its `post` in the officers directory. A desk with nobody notifies no one. The officer who made the move is never notified of it.
 *
 * Sent after the save commits, so a refused or rolled-back move notifies nobody, and a
 * notification failure is logged, never shown to the officer: the move has already happened.
 */
class WorkflowNotifications
{
    public const TYPE = 'cadastral_workflow';
    public const MODULE = 'cadastral_dept';

    public function __construct(private UserNotificationService $notifier)
    {
    }

    /** Queue the notification for a movement just written inside CaseWorkflow's transaction. */
    public function moved(CadastralCase $case, ?string $fromStep, ?string $toStep, string $outcome, ?string $remarks, ?int $actorId): void
    {
        $snapshot = clone $case;
        $send = function () use ($snapshot, $fromStep, $toStep, $outcome, $remarks, $actorId) {
            try {
                $this->send($snapshot, $fromStep, $toStep, $outcome, $remarks, $actorId);
            } catch (\Throwable $e) {
                Log::warning('Cadastral workflow notification failed', ['case' => $snapshot->case_ref, 'error' => $e->getMessage()]);
            }
        };

        // Runs at once when no transaction is open.
        DB::connection('sqlsrv')->afterCommit($send);
    }

    /**
     * Who is told what about one movement: [[user ids], event, title, body].
     * Public so it can be checked without writing anything.
     */
    public function plan(CadastralCase $case, ?string $fromStep, ?string $toStep, string $outcome, ?string $remarks, ?int $actorId): array
    {
        $def = new WorkflowDefinition();
        $wf = $case->workflow;
        $file = trim($case->file_number . ($case->file_title ? " ({$case->file_title})" : ''));
        $reason = trim((string) $remarks) !== '' ? ' Reason: “' . trim((string) $remarks) . '”.' : '';

        if ($toStep === null) {   // the workflow closed
            $fromDesk = $fromStep ? $def->actingDesk($wf, $fromStep) : null;
            $returned = $case->status === CadastralCase::STATUS_RETURNED;
            $users = array_merge($this->officers($def, $fromDesk), $this->officers($def, 'registry'), [$case->received_by]);

            return [$this->without($users, $actorId), 'closed',
                $returned ? "{$case->file_number} returned to " . $case->fileSourceLabel() : "{$case->file_number} completed",
                "{$case->case_ref}: file {$file} " . ($returned ? 'was sent back to ' . $case->fileSourceLabel() : 'has completed the ' . $case->workflowLabel() . ' workflow')
                    . ($fromDesk ? ' (closed by ' . $def->deskShort($fromDesk) . ')' : '') . '.' . $reason];
        }

        // Head-of-unit approval: the file stays on its step (HeadApproval).
        if ($outcome === HeadApproval::TO_HEAD || $outcome === HeadApproval::RETURNED) {
            $desk = $def->step($wf, $toStep)['desk'];
            $head = $def->desks()[$desk]['head'] ?? $def->deskShort($desk);
            if ($outcome === HeadApproval::TO_HEAD) {
                return [$this->without(app(HeadApproval::class)->headUserIds($desk), $actorId), 'approval',
                    "{$case->file_number} sent to the {$head} for approval",
                    "{$case->case_ref}: file {$file} ({$def->step($wf, $toStep)['label']}) is waiting for the {$head} to send it on." . $reason];
            }
            $sender = \App\Models\CadastralDept\CadastralCaseMovement::query()->where('cadastral_case_id', $case->id)
                ->where('outcome', HeadApproval::TO_HEAD)->orderByDesc('id')->value('acted_by');

            return [$this->without(array_filter([$sender]), $actorId), 'sent_back', "{$case->file_number} returned by the {$head}",
                "{$case->case_ref}: the {$head} returned file {$file} to the staff." . $reason];
        }

        $to = $def->step($wf, $toStep);
        $toDesk = $to['desk'];
        $from = $fromStep ? $def->step($wf, $fromStep) : null;

        if (!empty($to['external'])) {   // out with another department; a Cadastral desk records its return
            $recorder = $def->actingDesk($wf, $toStep);

            // The Survey unit's own staff are told too (Survey > Other Departments, 2026-10-28).
            return [$this->without(array_merge($this->officers($def, $recorder), $this->officers($def, $toDesk)), $actorId), 'out',
                "{$case->file_number} sent to " . $def->deskShort($toDesk),
                "{$case->case_ref}: file {$file} is out with " . $def->deskLabel($toDesk) . ' (' . $to['label'] . '). Record it on ' . $def->deskShort($recorder) . ' when it comes back.' . $reason];
        }

        $users = $this->without($this->officers($def, $toDesk), $actorId);

        if ($fromStep && $def->outcomeNeedsReason($wf, $fromStep, $outcome)) {
            $by = $def->deskShort(!empty($from['external']) ? $from['desk'] : $def->actingDesk($wf, $fromStep));

            return [$users, 'sent_back', "{$case->file_number} sent back by {$by}",
                "{$case->case_ref}: file {$file} was sent back to " . $def->deskShort($toDesk) . " by {$by}. Now: {$to['label']}." . $reason . $this->dueLine($toDesk)];
        }

        if ($from && !empty($from['external'])) {
            return [$users, 'back', "{$case->file_number} back from " . $def->deskShort($from['desk']),
                "{$case->case_ref}: file {$file} is back from " . $def->deskLabel($from['desk']) . ' and is now at ' . $def->deskShort($toDesk) . ": {$to['label']}." . $reason . $this->dueLine($toDesk)];
        }

        // The next step on the same desk: the file has not moved, so nobody new is told.
        if ($fromStep && $def->actingDesk($wf, $fromStep) === $toDesk) {
            return [[], 'same_desk', '', ''];
        }

        $origin = $fromStep ? $def->deskShort($def->actingDesk($wf, $fromStep)) : 'Registry (registered)';

        return [$users, 'arrived', "{$case->file_number} received at " . $def->deskShort($toDesk),
            "{$case->case_ref}: file {$file} has reached " . $def->deskShort($toDesk) . " from {$origin}. Next: {$to['label']}." . $reason . $this->dueLine($toDesk)];
    }

    /**
     * A file has gone past its working day at an office (CadastralCaseTimer, user 2026-11-09: "bell").
     * Kept for the timer before migration 2026_11_09_120000; afterwards timerAlert() rings every stage.
     */
    public function overdue(CadastralCase $case, array $state): void
    {
        $this->timerAlert($case, $state, 'default');
    }

    /**
     * The timer stages (user, 2026-11-09; Daniel's list of 08/11): Due soon, Warning, Overdue, Penalty, once
     * per office each. To the desk's officers and the admins; the penalty also to the unit head picked in
     * Central Admin (the line manager). Overdue and Penalty carry tone "red" so the bell shows them in red.
     */
    public function timerAlert(CadastralCase $case, array $state, string $stage): void
    {
        try {
            $def = new WorkflowDefinition();
            $desk = (string) $case->current_desk;
            $users = $this->timerRecipients($def, $desk, $stage);
            if (!$users) {
                return;
            }
            $short = $def->deskShort($desk);
            $file = trim($case->file_number . ($case->file_title ? " ({$case->file_title})" : ''));
            $due = $state['deadline']->format('d/m/Y H:i');
            [$title, $tail] = match ($stage) {
                'reminder' => ["{$case->file_number} due soon at {$short}", " It must move on by {$due}."],
                'warning'  => ["{$case->file_number}: time nearly up at {$short}", " It must move on by {$due}, or it goes overdue."],
                'penalty'  => ["{$case->file_number}: penalty at {$short}", " It was due by {$due}; a penalty is recorded against {$short}."],
                default    => ["{$case->file_number} over 1 working day at {$short}", " It was due to move on by {$due}."],
            };
            $body = "{$case->case_ref}: file {$file} reached {$short} on " . $state['since']->format('d/m/Y H:i') . '.' . $tail;
            $data = $this->timerData($stage, $desk) + [
                'case_id' => $case->id,
                'case_ref' => $case->case_ref,
                'file_number' => $case->file_number,
                'file_name' => $case->file_title,
                'deadline' => $state['deadline']->toDateTimeString(),
                'open_path' => route('cadastral-dept.cases.show', $case->id, false),
            ];
            foreach ($users as $userId) {
                $this->notifier->create($userId, self::TYPE, $title, $body, $data, ['module' => self::MODULE]);
            }
        } catch (\Throwable $e) {
            Log::warning('Cadastral timer notification failed', ['case' => $case->case_ref, 'stage' => $stage, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Several files at one office reaching one stage together (the backlog when the bells were switched on,
     * user 2026-11-09: "one summary per office"): one bell listing them, opening that desk.
     *
     * @param  array  $alerts  [case, state, stage] rows, all at $desk and $stage
     */
    public function timerSummary(string $desk, string $stage, array $alerts): void
    {
        try {
            $def = new WorkflowDefinition();
            $users = $this->timerRecipients($def, $desk, $stage);
            if (!$users) {
                return;
            }
            $short = $def->deskShort($desk);
            $n = count($alerts);
            $title = match ($stage) {
                'reminder' => "{$n} files due soon at {$short}",
                'warning'  => "{$n} files: time nearly up at {$short}",
                'penalty'  => "{$n} files: penalty at {$short}",
                default    => "{$n} files over 1 working day at {$short}",
            };
            $list = array_map(fn ($a) => $a[0]->file_number, array_slice($alerts, 0, 15));
            $body = ($stage === 'penalty' ? "Past their working day; a penalty is recorded against {$short} for each. " : '')
                . 'Files: ' . implode(', ', $list) . ($n > 15 ? ' and ' . ($n - 15) . ' more' : '') . '.';
            $data = $this->timerData($stage, $desk) + [
                'count' => $n,
                'case_ids' => array_map(fn ($a) => $a[0]->id, $alerts),
                'open_path' => route('cadastral-dept.central-admin.index', ['desk' => $desk], false),
            ];
            foreach ($users as $userId) {
                $this->notifier->create($userId, self::TYPE, $title, $body, $data, ['module' => self::MODULE]);
            }
        } catch (\Throwable $e) {
            Log::warning('Cadastral timer summary failed', ['desk' => $desk, 'stage' => $stage, 'error' => $e->getMessage()]);
        }
    }

    /** The desk's officers and the admins; for a penalty, the unit head too (the line manager). */
    private function timerRecipients(WorkflowDefinition $def, string $desk, string $stage): array
    {
        $users = array_merge($this->without($this->officers($def, $desk), null), $this->admins());
        if ($stage === 'penalty' && ($head = HeadApproval::headUserId($desk))) {
            $users[] = $head;
        }

        return array_values(array_unique(array_map('intval', $users)));
    }

    private function timerData(string $stage, string $desk): array
    {
        return [
            'module' => self::MODULE,
            'event' => ['reminder' => 'due_soon', 'warning' => 'warning', 'default' => 'overdue', 'penalty' => 'penalty'][$stage] ?? $stage,
            'tone' => CadastralCaseTimer::STAGES[$stage]['tone'] ?? null,
            'desk' => $desk,
        ];
    }

    /** " Due to move on by …" for a file reaching an office the clock runs at (Daniel's item 1, processing deadline). */
    private function dueLine(string $desk): string
    {
        try {
            $timer = app(CadastralCaseTimer::class);
            if (!CadastralCaseTimer::installed() || !$timer->isTimed($desk)) {
                return '';
            }

            return ' Due to move on by ' . $timer->deadlineFrom(now())->format('d/m/Y H:i') . '.';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function send(CadastralCase $case, ?string $fromStep, ?string $toStep, string $outcome, ?string $remarks, ?int $actorId): void
    {
        [$users, $event, $title, $body] = $this->plan($case, $fromStep, $toStep, $outcome, $remarks, $actorId);
        if ($title === '') {
            return;   // nothing to tell (the next step on the same desk)
        }
        // Super Admin and Central Admin see every Cadastral notification, their own moves included
        // (user, 2026-10-27: "the admin should be able to see all the notifications").
        $users = array_values(array_unique(array_merge($users, $this->admins())));
        if (!$users) {
            return;
        }

        $data = [
            'module' => self::MODULE,
            'event' => $event,
            'case_id' => $case->id,
            'case_ref' => $case->case_ref,
            'file_number' => $case->file_number,
            'file_name' => $case->file_title,
            'from_step' => $fromStep,
            'to_step' => $toStep,
            'reason' => trim((string) $remarks) ?: null,
            // Read by the bell's "Open file" link.
            'open_path' => route('cadastral-dept.cases.show', $case->id, false),
        ];

        foreach ($users as $userId) {
            $this->notifier->create($userId, self::TYPE, $title, $body, $data, ['module' => self::MODULE]);
        }
    }

    /**
     * The desk's officers: active users holding the desk's role (DeskAccess::rolesFor, the roles
     * that open the desk), plus anyone still set on its post in the officers directory. Desks open
     * by role since 2026-10-26 and no posts were ever set, so post-only sent nothing (2026-10-27).
     */
    private function officers(WorkflowDefinition $def, ?string $desk): array
    {
        if (!$desk) {
            return [];
        }
        $post = $def->desks()[$desk]['post'] ?? null;
        $byPost = $post ? CadastralOfficer::userIdsForPost($post) : [];

        return array_merge($byPost, $this->roleHolders($desk));
    }

    /** Active Super Admins and Central Admin role holders. */
    private function admins(): array
    {
        $central = mb_strtolower(DeskAccess::centralAdminRole());
        $this->rolesByUser ??= User::query()->whereNotNull('assign_role')->where('assign_role', '!=', '')
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get()
            ->mapWithKeys(fn ($u) => [(int) $u->id => array_map('mb_strtolower', $u->assignedRoleNames())])
            ->all();
        $ids = array_keys(array_filter($this->rolesByUser, fn ($roles) => (bool) array_intersect($roles, ['super admin', 'supper admin', $central])));
        // Super Admin by account type (users.type) too.
        $byType = User::query()->whereIn(DB::raw('LOWER(type)'), ['super admin', 'supper admin'])
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique(array_merge($ids, $byType)));
    }

    /** @var array<int, string[]>|null user id => lower-cased role names, read once per request */
    private ?array $rolesByUser = null;

    private function roleHolders(string $desk): array
    {
        $want = array_map('mb_strtolower', DeskAccess::rolesFor($desk));
        if (!$want) {
            return [];
        }
        $this->rolesByUser ??= User::query()->whereNotNull('assign_role')->where('assign_role', '!=', '')
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get()
            ->mapWithKeys(fn ($u) => [(int) $u->id => array_map('mb_strtolower', $u->assignedRoleNames())])
            ->all();

        return array_keys(array_filter($this->rolesByUser, fn ($roles) => (bool) array_intersect($roles, $want)));
    }

    private function without(array $users, ?int $actorId): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $users), fn ($id) => $id > 0 && $id !== (int) $actorId)));
    }
}
