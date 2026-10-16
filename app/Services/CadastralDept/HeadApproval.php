<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use App\Models\CadastralOfficer;
use App\Models\User;

/**
 * Head-of-unit approval (transcript of the workflow update, 2026-10-16).
 *
 * In a unit (desk with a `head` in config cadastral_dept.desks: Registry, Cadastral
 * Information, Report, P&D, Drawing & Charting, GIS Unit) the staff do the work, but only
 * the head sends the file to another unit or department. Staff get "Send to <head>" once
 * the step's tasks are done; the step's real actions are shown greyed out for them. The
 * head acts on it (alone or in a batch), or returns it to the staff with a reason.
 * Directors (D Cad, DD Cad) and the return of a file from another department are not gated.
 *
 * "Sent to the head" is a movement on the same step (outcome to_head), so it is in the
 * file's history and FMC; a file waits for the head while its latest movement is that one.
 *
 * The head is whoever holds the desk's `post` in Configurable Entries > Cadastral > Officers.
 * While nobody holds it, Cad - Records (and Supper Admin, always) stand in, so no file is stuck.
 */
class HeadApproval
{
    public const TO_HEAD = 'to_head';
    public const RETURNED = 'head_return';

    public function __construct(private WorkflowDefinition $def)
    {
    }

    /** The head's name for a desk (Head of Registry, AD Cad Report, ...), or null when not gated. */
    public function headOf(?string $desk): ?string
    {
        return $desk ? ($this->def->desks()[$desk]['head'] ?? null) : null;
    }

    /** Whether the case's current step goes through the unit head before the file is sent on. */
    public function required(CadastralCase $case): bool
    {
        if (!$case->isOpen() || !$case->current_step || !$this->def->hasStep($case->workflow, $case->current_step)) {
            return false;
        }
        $step = $this->def->step($case->workflow, $case->current_step);

        return empty($step['external']) && $this->headOf($step['desk']) !== null;
    }

    /** Staff have sent it to the head, and it is waiting there. */
    public function awaiting(CadastralCase $case): bool
    {
        $last = $case->relationLoaded('latestMovement')
            ? $case->latestMovement
            : CadastralCaseMovement::query()->where('cadastral_case_id', $case->id)->orderByDesc('id')->first(['id', 'outcome', 'to_step']);

        return $last && $last->outcome === self::TO_HEAD && $last->to_step === $case->current_step;
    }

    public function isHead(?User $user, ?string $desk): bool
    {
        if (!$user || !$desk) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        $holders = array_map('intval', CadastralOfficer::userIdsForPost($this->def->desks()[$desk]['post'] ?? null));
        if ($holders) {
            return in_array((int) $user->id, $holders, true);
        }

        // Post vacant: the department role stands in.
        return $user->canDo(DeskAccess::departmentRole(), 'view');
    }

    /** Users to tell when a file is sent to the head (the post holders; none while vacant). */
    public function headUserIds(?string $desk): array
    {
        return CadastralOfficer::userIdsForPost($desk ? ($this->def->desks()[$desk]['post'] ?? null) : null);
    }

    /**
     * The step's actions as this user may use them.
     *
     * @param  array  $actions  WorkflowEngine::actions() rows, with 'to' added
     * @return array{actions:array, locked:array, head:?string, awaiting:bool, is_head:bool, required:bool}
     *         actions: what the user can press; locked: the real actions shown greyed out
     */
    public function shape(CadastralCase $case, array $actions, ?User $user): array
    {
        $info = ['actions' => $actions, 'locked' => [], 'head' => null, 'awaiting' => false, 'is_head' => false, 'required' => false];
        if (!$this->required($case)) {
            return $info;
        }

        $desk = $case->current_desk;
        $head = $this->headOf($desk);
        $awaiting = $this->awaiting($case);
        $isHead = $this->isHead($user, $desk);
        $info = ['head' => $head, 'awaiting' => $awaiting, 'is_head' => $isHead, 'required' => true] + $info;

        if ($isHead) {
            // The head sends it on; a file the staff sent can also go back to them.
            if ($awaiting) {
                $info['actions'][] = ['outcome' => self::RETURNED, 'label' => 'Return to staff', 'next' => null, 'ends' => null,
                    'reason' => true, 'to' => $this->def->deskShort($desk) . ' staff', 'head_return' => true];
            }

            return $info;
        }

        $info['locked'] = $actions;
        $info['actions'] = $awaiting ? [] : [[
            'outcome' => self::TO_HEAD, 'label' => 'Send to ' . $head, 'next' => null, 'ends' => null,
            'reason' => false, 'to' => $head, 'to_head' => true,
        ]];

        return $info;
    }
}
