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
                "{$case->case_ref}: file {$file} was sent back to " . $def->deskShort($toDesk) . " by {$by}. Now: {$to['label']}." . $reason];
        }

        if ($from && !empty($from['external'])) {
            return [$users, 'back', "{$case->file_number} back from " . $def->deskShort($from['desk']),
                "{$case->case_ref}: file {$file} is back from " . $def->deskLabel($from['desk']) . ' and is now at ' . $def->deskShort($toDesk) . ": {$to['label']}." . $reason];
        }

        // The next step on the same desk: the file has not moved, so nobody new is told.
        if ($fromStep && $def->actingDesk($wf, $fromStep) === $toDesk) {
            return [[], 'same_desk', '', ''];
        }

        $origin = $fromStep ? $def->deskShort($def->actingDesk($wf, $fromStep)) : 'Registry (registered)';

        return [$users, 'arrived', "{$case->file_number} received at " . $def->deskShort($toDesk),
            "{$case->case_ref}: file {$file} has reached " . $def->deskShort($toDesk) . " from {$origin}. Next: {$to['label']}." . $reason];
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
