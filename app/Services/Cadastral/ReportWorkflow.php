<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralReport;
use App\Models\Cadastral\CadastralReportStep;
use App\Models\CadastralOfficer;
use App\Services\AuditService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The cadastral report stage chain.
 *
 * Verification runs eight steps, customary and statutory the same seven without
 * Field Inspection. The chain is seeded from config into cadastral_report_steps
 * at creation, so editing the config never rewrites a report already in flight.
 *
 * FORWARD ONLY. markDone() on a step that is already done is a no-op, not an
 * error and not a second advance — which makes a double-clicked button
 * harmless. A step cannot be completed while an earlier one is still waiting,
 * and approval and dispatch are only ever the completion of their own step, so
 * neither can jump the chain.
 *
 * WHO MAY ACT. A step that names a required_post can be completed (or sent
 * back) only by a user whose active cadastral_officers row holds that post, or
 * by a Super Admin — the permission layer already treats Super Admin as the
 * whole-system grant (ModulePermissions::allows), and this follows it through
 * User::isSuperAdmin(). A step with no post (Registration, Dispatch) is open to
 * anyone the route lets in. blockReason() is the single answer to "why can't I",
 * used by both the POST handlers and the screen's disabled buttons.
 *
 * Audit and notification failures are swallowed and logged: they are a record of
 * the move, not the move itself, and a notification table problem must not roll
 * back an approval the officer has already been told succeeded.
 */
class ReportWorkflow
{
    public function __construct(
        private AuditService $audit = new AuditService(),
        private UserNotificationService $notifications = new UserNotificationService(),
    ) {}

    /** The configured chain for a report type. */
    public function chainFor(string $reportType): array
    {
        return config("cadastral_module.stage_chains.{$reportType}", []);
    }

    /** "Report Officer" for REPORT_OFFICER. */
    public function postLabel(?string $post): ?string
    {
        return $post ? (config('cadastral_module.posts')[$post] ?? $post) : null;
    }

    /**
     * Create the step rows for a new report and open the first one.
     *
     * Called inside the controller's transaction.
     */
    public function seed(CadastralReport $report): void
    {
        if ($report->steps()->exists()) {
            return;   // re-seeding would duplicate the chain
        }

        $chain = $this->chainFor($report->report_type);

        foreach ($chain as $i => $step) {
            CadastralReportStep::create([
                'cadastral_report_id' => $report->id,
                'step_no'             => $i + 1,
                'step_key'            => $step['key'],
                'step_name'           => $step['name'],
                'required_post'       => $step['post'],
                'status'              => $i === 0 ? 'active' : 'waiting',
                'started_at'          => $i === 0 ? now() : null,
            ]);
        }

        $first = $chain[0] ?? null;

        $report->forceFill([
            'current_step'     => 1,
            'current_step_key' => $first['key'] ?? null,
            'assigned_post'    => $first['post'] ?? null,
            'status'           => 'In Progress',
        ])->save();
    }

    /* ------------------------------ who may act ------------------------------ */

    public function isSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    /**
     * Whether the signed-in user may act on a step.
     *
     * A step with no required_post is open to anyone who can reach the screen.
     * A step that names a post needs that post — or Super Admin.
     */
    public function userMayAct(CadastralReportStep $step, ?int $userId = null): bool
    {
        if (! $step->required_post) {
            return true;
        }

        if ($userId === null && $this->isSuperAdmin()) {
            return true;
        }

        return in_array($step->required_post, CadastralOfficer::postsFor($userId ?? Auth::id()), true);
    }

    /** The step the report is on: the active or returned one. */
    public function currentStep(CadastralReport $report): ?CadastralReportStep
    {
        $steps = $report->relationLoaded('steps') ? $report->steps : $report->steps()->get();

        return $steps->first(fn ($s) => in_array($s->status, ['active', 'returned'], true));
    }

    /**
     * Why the signed-in user cannot complete (or return) this step, or null when
     * they can. The same sentence the POST handler refuses with is shown beside
     * the disabled button, so the screen never offers what the server refuses.
     */
    public function blockReason(CadastralReport $report, CadastralReportStep $step): ?string
    {
        if ($step->cadastral_report_id !== $report->id) {
            return 'That step does not belong to this report.';
        }

        if ($report->isFinished()) {
            return "{$report->report_ref} is {$report->status}; its chain is closed.";
        }

        if ($step->isDone()) {
            return "{$step->step_name} is already completed.";
        }

        // No orderBy here: the steps() relation already orders by step_no, and
        // SQL Server rejects the same column twice in an ORDER BY list.
        $blocking = $report->steps()
            ->where('step_no', '<', $step->step_no)
            ->whereNotIn('status', ['done', 'skipped'])
            ->first();

        if ($blocking) {
            return "{$blocking->step_name} (step {$blocking->step_no}) has to be completed first.";
        }

        if (! $this->userMayAct($step)) {
            $label = $this->postLabel($step->required_post);
            $mine  = array_map(fn ($p) => $this->postLabel($p), CadastralOfficer::postsFor(Auth::id()));

            return "{$step->step_name} is the {$label}'s step. "
                . ($mine ? 'You hold: ' . implode(', ', $mine) . '.' : 'You hold no Cadastral post.');
        }

        return null;
    }

    /* -------------------------------- moves -------------------------------- */

    /**
     * Complete a step and open the next.
     *
     * @return array{ok: bool, message: string}
     */
    public function markDone(CadastralReport $report, CadastralReportStep $step, ?string $note = null): array
    {
        if ($step->cadastral_report_id !== $report->id) {
            return ['ok' => false, 'message' => 'That step does not belong to this report.'];
        }

        if ($step->isDone()) {
            // Forward-only: a double submit lands here and changes nothing.
            return ['ok' => true, 'message' => "{$step->step_name} was already completed."];
        }

        if ($reason = $this->blockReason($report, $step)) {
            return ['ok' => false, 'message' => $reason];
        }

        // The Report step produces the Report on Application. Once its columns
        // exist, the step cannot close with a headline question unanswered.
        if ($step->step_key === 'report' && CadastralReport::applicationInstalled()) {
            $missing = $report->unansweredApplicationQuestions();

            if ($missing) {
                return [
                    'ok'      => false,
                    'message' => 'Answer every question of the Report on Application first (missing: '
                        . implode(', ', array_map(fn ($c) => 'Q' . substr($c, 1, 1), $missing)) . ').',
                ];
            }
        }

        $previousStatus = $report->status;

        $step->forceFill([
            'status'        => 'done',
            'note'          => $note ?: $step->note,
            'actor_user_id' => Auth::id(),
            'actor_name'    => Auth::user()->name ?? null,
            'completed_at'  => now(),
            'started_at'    => $step->started_at ?: now(),
        ])->save();

        $next = $report->steps()
            ->where('step_no', '>', $step->step_no)
            ->whereIn('status', ['waiting', 'returned'])
            ->first();

        if ($next) {
            $next->forceFill(['status' => 'active', 'started_at' => now()])->save();

            $report->forceFill([
                'current_step'     => $next->step_no,
                'current_step_key' => $next->step_key,
                'assigned_post'    => $next->required_post,
                'assigned_user_id' => null,
                'status'           => $this->statusForStep($next->step_key),
            ])->save();

            $this->notifyPost($report, $next);
        } else {
            $report->forceFill([
                'current_step_key' => $step->step_key,
                'assigned_post'    => null,
                'assigned_user_id' => null,
                'status'           => 'Dispatched',
                'dispatched_at'    => $report->dispatched_at ?: now(),
            ])->save();
        }

        $this->log($report, 'STEP_COMPLETED', $previousStatus, [
            'step_no'  => $step->step_no,
            'step_key' => $step->step_key,
            'note'     => $note,
        ]);

        return [
            'ok'      => true,
            'message' => $next
                ? "{$step->step_name} completed. Now with {$next->step_name}."
                : "{$step->step_name} completed. The report is finished.",
        ];
    }

    /**
     * Send the report back from its current step to the previous one.
     *
     * $step is the step the report is ON; the officer holding it found a fault
     * in the work before it. The previous completed step reopens as "returned"
     * (it keeps who completed it; the return is in its note and the audit log),
     * and the current step goes back to waiting. A note is required — the
     * officer receiving it has to know what to fix.
     */
    public function markReturned(CadastralReport $report, CadastralReportStep $step, ?string $note = null): array
    {
        $note = trim((string) $note);

        if ($note === '') {
            return ['ok' => false, 'message' => 'Say what has to be corrected — a return needs a note.'];
        }

        if ($reason = $this->blockReason($report, $step)) {
            return ['ok' => false, 'message' => $reason];
        }

        $previous = $report->steps()
            ->where('step_no', '<', $step->step_no)
            ->where('status', 'done')
            ->get()
            ->last();

        if (! $previous) {
            return ['ok' => false, 'message' => "{$step->step_name} is the first step; there is nothing to return it to."];
        }

        $previousStatus = $report->status;
        $by = Auth::user()->name ?? 'an officer';

        $previous->forceFill([
            'status'     => 'returned',
            'note'       => "Returned from {$step->step_name} by {$by}: {$note}",
            'started_at' => now(),
        ])->save();

        $step->forceFill(['status' => 'waiting'])->save();

        $report->forceFill([
            'current_step'     => $previous->step_no,
            'current_step_key' => $previous->step_key,
            'assigned_post'    => $previous->required_post,
            'assigned_user_id' => null,
            'status'           => 'Returned',
        ])->save();

        $this->notifyPost($report, $previous);

        $this->log($report, 'STEP_RETURNED', $previousStatus, [
            'from_step_no'  => $step->step_no,
            'from_step_key' => $step->step_key,
            'to_step_no'    => $previous->step_no,
            'to_step_key'   => $previous->step_key,
            'note'          => $note,
        ]);

        return ['ok' => true, 'message' => "Returned to {$previous->step_name} for correction."];
    }

    /**
     * Approve or reject, from the approval step and only when it is the
     * report's current step — approval cannot be given ahead of checking.
     * Approving IS completing the approval step; rejecting closes the report.
     */
    public function decide(CadastralReport $report, string $decision, ?string $note = null): array
    {
        $approvalStep = $report->steps()->where('step_key', 'approval')->first();

        if (! $approvalStep) {
            return ['ok' => false, 'message' => "{$report->report_ref} has no approval step."];
        }

        if ($approvalStep->isDone()) {
            return ['ok' => true, 'message' => "{$report->report_ref} was already approved."];
        }

        if ($reason = $this->blockReason($report, $approvalStep)) {
            return ['ok' => false, 'message' => $reason];
        }

        $previousStatus = $report->status;

        if ($decision !== 'reject') {
            $result = $this->markDone($report, $approvalStep, $note);

            if ($result['ok']) {
                $this->log($report->refresh(), 'REPORT_APPROVED', $previousStatus, ['note' => $note]);
                $result['message'] = "{$report->report_ref} approved. " . $result['message'];
            }

            return $result;
        }

        $note = trim((string) $note);
        if ($note === '') {
            return ['ok' => false, 'message' => 'A reason is required when rejecting a report.'];
        }

        $approvalStep->forceFill([
            'status'        => 'returned',
            'note'          => $note,
            'actor_user_id' => Auth::id(),
            'actor_name'    => Auth::user()->name ?? null,
        ])->save();

        $report->forceFill([
            'status'           => 'Rejected',
            'assigned_post'    => null,
            'assigned_user_id' => null,
        ])->save();

        $this->log($report, 'REPORT_REJECTED', $previousStatus, ['note' => $note]);

        return ['ok' => true, 'message' => "{$report->report_ref} rejected."];
    }

    /**
     * Dispatch: complete the dispatch step, recording where it went. Only
     * possible once dispatch is the current step, i.e. after approval.
     */
    public function dispatch(CadastralReport $report, ?string $dispatchedTo, ?string $note = null): array
    {
        $dispatchStep = $report->steps()->where('step_key', 'dispatch')->first();

        if (! $dispatchStep) {
            return ['ok' => false, 'message' => "{$report->report_ref} has no dispatch step."];
        }

        if ($reason = $this->blockReason($report, $dispatchStep)) {
            return ['ok' => false, 'message' => $reason];
        }

        $dispatchedTo = trim((string) $dispatchedTo);
        if ($dispatchedTo === '') {
            return ['ok' => false, 'message' => 'Say where the report is being dispatched to.'];
        }

        $report->forceFill(['dispatched_to' => $dispatchedTo])->save();

        $details = trim("To {$dispatchedTo}. " . trim((string) $note));

        $result = $this->markDone($report, $dispatchStep, $details);

        if ($result['ok']) {
            $result['message'] = "{$report->report_ref} dispatched to {$dispatchedTo}.";
        }

        return $result;
    }

    /**
     * Put the current step in a named officer's hands.
     *
     * The officer must hold the step's post (any active officer with a login,
     * when the step names none), and the person assigning must be able to act
     * on the step themselves — or be a Super Admin.
     */
    public function assign(CadastralReport $report, int $userId): array
    {
        $step = $this->currentStep($report);

        if (! $step || $report->isFinished()) {
            return ['ok' => false, 'message' => "{$report->report_ref} has no open step to assign."];
        }

        if ($reason = $this->blockReason($report, $step)) {
            return ['ok' => false, 'message' => $reason];
        }

        if (! in_array($userId, array_map('intval', $this->assignableUserIds($step)), true)) {
            $label = $this->postLabel($step->required_post) ?? 'Cadastral officer';

            return ['ok' => false, 'message' => "That user does not hold the {$label} post."];
        }

        $before = $report->assigned_user_id;

        $report->forceFill(['assigned_user_id' => $userId])->save();

        $this->log($report, 'REPORT_ASSIGNED', $report->status, [
            'step_no'       => $step->step_no,
            'from_user_id'  => $before,
            'to_user_id'    => $userId,
        ]);

        return ['ok' => true, 'message' => "{$step->step_name} assigned."];
    }

    /** Users who may be given a step: holders of its post, or any active officer. */
    public function assignableUserIds(CadastralReportStep $step): array
    {
        if ($step->required_post) {
            return CadastralOfficer::userIdsForPost($step->required_post);
        }

        return CadastralOfficer::query()
            ->whereNotNull('user_id')
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->pluck('user_id')->unique()->values()->all();
    }

    /* ----------------------- Report on Application (§3a) ----------------------- */

    /** The Report step, when the chain has one. */
    public function reportStep(CadastralReport $report): ?CadastralReportStep
    {
        $steps = $report->relationLoaded('steps') ? $report->steps : $report->steps()->get();

        return $steps->firstWhere('step_key', 'report');
    }

    /**
     * Why the questionnaire cannot be edited now, or null when it can: it is
     * the Report step's work, so it is open while that step is the current one
     * and only to whoever may act on that step.
     */
    public function applicationBlockReason(CadastralReport $report): ?string
    {
        if (! CadastralReport::applicationInstalled()) {
            return 'The Report on Application columns are pending installation.';
        }

        $step = $this->reportStep($report);

        if (! $step) {
            return 'This report has no Report step.';
        }

        if (! in_array($step->status, ['active', 'returned'], true)) {
            return $step->isDone()
                ? "{$step->step_name} is completed; the questionnaire is closed unless the report is returned to it."
                : "The questionnaire opens when the report reaches {$step->step_name}.";
        }

        return $this->blockReason($report, $step);
    }

    /**
     * Save the questionnaire, audited with before and after.
     *
     * forceFill, not update(): the columns arrive with a pending migration, and
     * a mass-assignment of keys the model has not seen must not be dropped.
     */
    public function saveApplication(CadastralReport $report, array $values): array
    {
        if ($reason = $this->applicationBlockReason($report)) {
            return ['ok' => false, 'message' => $reason];
        }

        $values = array_intersect_key($values, array_flip(CadastralReport::APPLICATION_COLUMNS));

        $before = [];
        foreach (array_keys($values) as $c) {
            $before[$c] = $report->applicationValue($c);
        }

        $report->forceFill($values)->save();

        $after = [];
        foreach (array_keys($values) as $c) {
            $after[$c] = $report->applicationValue($c);
        }

        $changed = array_keys(array_filter($after, fn ($v, $k) => (string) $v !== (string) ($before[$k] ?? ''), ARRAY_FILTER_USE_BOTH));

        try {
            $this->audit->logAction(
                'CADASTRAL_APPLICATION_REPORT_SAVED',
                'cadastral_report',
                $report->id,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
                "{$report->report_ref} ({$report->file_number}) Report on Application"
            );
        } catch (\Throwable $e) {
            Log::warning('Cadastral application audit failed: ' . $e->getMessage(), ['report_id' => $report->id]);
        }

        return [
            'ok'      => true,
            'message' => $changed ? 'Report on Application saved.' : 'Report on Application saved (no changes).',
        ];
    }

    /** Where a report sits, for the progress component. */
    public function progress(CadastralReport $report): array
    {
        $steps = $report->steps()->get();
        $done  = $steps->filter(fn ($s) => $s->isDone())->count();
        $total = max(1, $steps->count());

        return [
            'steps'   => $steps,
            'done'    => $done,
            'total'   => $steps->count(),
            'percent' => (int) round(($done / $total) * 100),
        ];
    }

    /**
     * Report status implied by the step now open: Checked once checking is
     * done (approval open), Approved once approval is done (dispatch open).
     */
    private function statusForStep(string $stepKey): string
    {
        return match ($stepKey) {
            'approval' => 'Checked',
            'dispatch' => 'Approved',
            default    => 'In Progress',
        };
    }

    /** Tell whoever holds the next post that it has arrived. */
    private function notifyPost(CadastralReport $report, CadastralReportStep $step): void
    {
        try {
            foreach (CadastralOfficer::userIdsForPost($step->required_post) as $userId) {
                $this->notifications->create(
                    (int) $userId,
                    'cadastral_report_step',
                    "{$report->type_label} report {$report->report_ref}",
                    "{$step->step_name} is now with you for file {$report->file_number}.",
                    ['report_id' => $report->id, 'step_no' => $step->step_no],
                );
            }
        } catch (\Throwable $e) {
            // A notification is a record of the move, not the move. Never let it
            // roll back the caller's transaction.
            Log::warning('Cadastral report notification failed: ' . $e->getMessage(), [
                'report_id' => $report->id,
                'step_no'   => $step->step_no,
            ]);
        }
    }

    private function log(CadastralReport $report, string $action, ?string $previousStatus, array $extra = []): void
    {
        try {
            $this->audit->logAction(
                $action,
                'cadastral_report',
                $report->id,
                ['status' => $previousStatus],
                ['status' => $report->status] + $extra,
                "{$report->report_ref} ({$report->file_number})"
            );
        } catch (\Throwable $e) {
            Log::warning('Cadastral report audit failed: ' . $e->getMessage(), ['report_id' => $report->id]);
        }
    }
}
