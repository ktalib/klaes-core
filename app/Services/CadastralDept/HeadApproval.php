<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use App\Models\User;
use App\Services\Cadastral\CadastralSettings;

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
 * The head is the user picked for the unit in Configurable Entries > Cadastral > Head of Unit
 * (setting dept.unit_head.<desk>, 2026-10-27; before, it was the holders of the desk's `post`,
 * which mixed heads up with the officers notified). The switch there (dept.head_approval)
 * turns the whole thing on or off; config cadastral_dept.head_approval is only the default.
 * While a unit has no head picked, the admin (Super Admin) acts as head (user, 2026-10-16).
 * A Central Admin (DeskAccess::holdsCentralAdminRole, 2026-10-26) stands in for every head.
 */
class HeadApproval
{
    public const TO_HEAD = 'to_head';
    public const RETURNED = 'head_return';

    /** cadastral_settings keys (Configurable Entries > Cadastral > Head of Unit). */
    public const SETTING_ON = 'dept.head_approval';
    public const SETTING_HEAD = 'dept.unit_head.';

    public function __construct(private WorkflowDefinition $def)
    {
    }

    /** The head's name for a desk (Head of Registry, AD Cad Report, ...), or null when not gated. */
    public function headOf(?string $desk): ?string
    {
        return $desk ? ($this->def->desks()[$desk]['head'] ?? null) : null;
    }

    /** Head approval switched on: the Head of Unit switch in Configurable Entries, else config. */
    public static function enabled(): bool
    {
        return (bool) (new CadastralSettings())->get(self::SETTING_ON, config('cadastral_dept.head_approval', true));
    }

    /** The user picked as the unit's head in Configurable Entries, or null while none is. */
    public static function headUserId(?string $desk): ?int
    {
        $id = $desk ? (int) (new CadastralSettings())->get(self::SETTING_HEAD . $desk, 0) : 0;

        return $id > 0 ? $id : null;
    }

    /** Whether the case's current step goes through the unit head before the file is sent on. */
    public function required(CadastralCase $case): bool
    {
        if (!self::enabled()) {
            return false;
        }
        if (!$case->isOpen() || !$case->current_step || !$this->def->hasStep($case->workflow, $case->current_step)) {
            return false;
        }
        $step = $this->def->step($case->workflow, $case->current_step);

        return empty($step['external']) && $this->headOf($step['desk']) !== null;
    }

    /** Staff have sent it to the head, and it is waiting there. */
    public function awaiting(CadastralCase $case): bool
    {
        if (!self::enabled()) {
            return false;   // switched off: nothing waits for a head
        }
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
        if (DeskAccess::holdsCentralAdminRole($user)) {
            return true;
        }
        $head = self::headUserId($desk);
        if ($head) {
            return (int) $user->id === $head;
        }

        // No head picked: the admin holds it until it is assigned to a user (user, 2026-10-16).
        return $user->isSuperAdmin();
    }

    /** Users to tell when a file is sent to the head: the unit's head, or the admins while none is picked. */
    public function headUserIds(?string $desk): array
    {
        if (!$desk || !$this->headOf($desk)) {
            return [];
        }
        if ($head = self::headUserId($desk)) {
            return [$head];
        }

        return User::query()->where('is_active', 1)->get()
            ->filter(fn (User $u) => $u->isSuperAdmin())->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
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
        $vacant = self::headUserId($desk) === null;
        $info = ['head' => $head, 'awaiting' => $awaiting, 'is_head' => $isHead, 'required' => true, 'vacant' => $vacant] + $info;

        if ($isHead) {
            // The head sends it on; a file the staff sent can also go back to them. Sending it on
            // is the approval, so the button says "Approve" (user, 2026-10-17); "to D Cad" shows under it.
            if ($awaiting) {
                foreach ($info['actions'] as &$a) {
                    if (empty($a['reason']) && str_starts_with($a['label'], 'Send to ')) {
                        $a['label'] = 'Approve';
                    }
                }
                unset($a);
                $info['actions'][] = ['outcome' => self::RETURNED, 'label' => 'Return to ' . $this->def->deskShort($desk) . ' staff', 'next' => null, 'ends' => null,
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
