<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes a case's moves: intake, task ticks, forwarding/decisions, sub-type.
 * Every write is one sqlsrv transaction and leaves an append-only history row.
 *
 * Who acts at a desk is not checked here. Desks are normal users, given the
 * desk's screen through user roles once the desk sidebar entries exist.
 */
class CaseWorkflow
{
    private const CONN = 'sqlsrv';

    public function __construct(
        private WorkflowEngine $engine,
        private AuditService $audit
    ) {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_cases');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function def(): WorkflowDefinition
    {
        return $this->engine->definition();
    }

    /**
     * Registry intake. Registering the file opens the case and sends it straight
     * to the workflow's first desk (D Cad). The Registry row is then view-only
     * until the file comes back to Registry.
     *
     * The caller (CaseController::store) has already re-read the file from the
     * file index or fileNumber, so number, title and location are the records'
     * values, not the browser's.
     *
     * @param array{workflow:string, file_type:string, sub_type?:?string, file_source:string,
     *              file_number:string, file_indexing_id?:?int, file_system?:?string,
     *              tracking_id?:?string, file_title?:?string, location?:?string,
     *              correspondence_fileno?:?string, duplicate_flag?:bool, duplicate_note?:?string,
     *              received_at?:mixed, remarks?:?string} $data
     */
    public function open(array $data, ?int $userId): CadastralCase
    {
        $def = $this->def();
        $workflow = $data['workflow'] ?? '';

        if (!$def->has($workflow)) {
            throw WorkflowGuardException::because('Choose an application type.');
        }
        $fileType = $def->fileTypes()[$data['file_type'] ?? ''] ?? null;
        if (!$fileType) {
            throw WorkflowGuardException::because('Choose the file type.');
        }
        if (!in_array($workflow, (array) $fileType['workflows'], true)) {
            throw WorkflowGuardException::because("A {$fileType['label']} cannot go through " . $def->workflow($workflow)['label'] . '.');
        }

        // Verification always asks (Statutory / Customary); under Direct Allocation only OP
        // asks, and every other file takes the default (Direct).
        $subType = ($data['sub_type'] ?? '') ?: null;
        if (!empty($def->workflow($workflow)['asks_sub_type'])) {
            if (!$subType || !isset($def->subTypes($workflow)[$subType])) {
                throw WorkflowGuardException::because('Choose whether this ' . $def->workflow($workflow)['label'] . ' is ' . implode(' or ', $def->subTypes($workflow)) . '.');
            }
        } elseif (!empty($fileType['asks_sub_type'])) {
            if (!$subType || !isset($def->subTypes($workflow)[$subType])) {
                throw WorkflowGuardException::because("Choose whether this {$fileType['label']} is " . implode(' or ', $def->subTypes($workflow)) . '.');
            }
        } else {
            $subType = $def->defaultSubType($workflow);
        }

        // Verification: Resettlement or Direct as well, from Direct Allocation's sub-types.
        $allocationRoute = null;
        if ($routesFrom = $def->workflow($workflow)['asks_allocation_route'] ?? null) {
            $allocationRoute = ($data['allocation_route'] ?? '') ?: null;
            if (!$allocationRoute || !isset($def->subTypes($routesFrom)[$allocationRoute])) {
                throw WorkflowGuardException::because('Choose whether this ' . $def->workflow($workflow)['label'] . ' file is ' . implode(' or ', $def->subTypes($routesFrom)) . '.');
            }
        }

        if (!isset($def->fileSources()[$data['file_source'] ?? ''])) {
            throw WorkflowGuardException::because('Choose where the file came from.');
        }
        // "Other" needs its specify text (tidied: trimmed, single spaces); any other source keeps none.
        $sourceOther = $data['file_source'] === 'other'
            ? (preg_replace('/\s+/', ' ', trim((string) ($data['file_source_other'] ?? ''))) ?: null)
            : null;
        if ($data['file_source'] === 'other' && $sourceOther === null) {
            throw WorkflowGuardException::because('Specify where the file came from.');
        }
        $fileNumber = trim((string) ($data['file_number'] ?? ''));
        if ($fileNumber === '') {
            throw WorkflowGuardException::because('Pick the file with the file number selector.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($data, $def, $workflow, $subType, $fileNumber, $userId, $sourceOther, $allocationRoute) {
            $open = CadastralCase::query()->open()
                ->where('file_number', $fileNumber)
                ->lockForUpdate()
                ->first();
            if ($open) {
                throw WorkflowGuardException::because("{$fileNumber} is already in the department as {$open->case_ref} ({$open->deskShort()}: {$open->stepLabel()}).");
            }

            $start = $def->startStep($workflow);
            $now = now();

            $case = CadastralCase::create([
                'case_ref' => $this->nextRef($now->year),
                'workflow' => $workflow,
                'sub_type' => $subType,
                'file_type' => $data['file_type'],
                'file_source' => $data['file_source'],
                'file_source_other' => $sourceOther,
            ] + ($allocationRoute !== null ? ['allocation_route' => $allocationRoute] : []) + [
                'file_number' => $fileNumber,
                'file_indexing_id' => $data['file_indexing_id'] ?? null,
                'file_system' => $data['file_system'] ?? null,
                'tracking_id' => $data['tracking_id'] ?? null,
                'file_title' => $data['file_title'] ?? null,
                'location' => $data['location'] ?? null,
            ] + $this->propertyColumns($data) + [
                'correspondence_fileno' => $data['correspondence_fileno'] ?? null,
            ] + (CorrespondenceColumns::available() ? ['shadow_fileno' => $data['shadow_fileno'] ?? null] : []) + [
                'duplicate_flag' => (bool) ($data['duplicate_flag'] ?? false),
                'duplicate_note' => $data['duplicate_note'] ?? null,
                'current_step' => $start,
                'current_desk' => $def->step($workflow, $start)['desk'],
                'status' => CadastralCase::STATUS_IN_PROGRESS,
                'received_at' => $data['received_at'] ?? $now,
                'received_by' => $userId,
                'remarks' => $data['remarks'] ?? null,
            ]);

            // The registration itself: Registry to the first desk, as one movement.
            $this->move($case, null, $start, 'registered', $data['remarks'] ?? null, $userId, 'registry');
            $this->audit->logAction('Cadastral Case Opened', 'CadastralCase', $case->id, null,
                $case->only(['case_ref', 'workflow', 'sub_type', 'file_type', 'file_source', 'file_number']),
                "{$case->case_ref} registered for {$fileNumber} and sent to " . $def->deskLabel($case->current_desk));

            return $case;
        });
    }

    /** Tick a task of the current step. Ticking twice is a no-op. */
    public function completeTask(CadastralCase $case, string $taskKey, ?string $note, ?int $userId): string
    {
        $this->assertOpen($case);
        $tasks = (array) ($this->def()->step($case->workflow, $case->current_step)['tasks'] ?? []);
        if (!isset($tasks[$taskKey])) {
            throw WorkflowGuardException::because('That task does not belong to the current step.');
        }
        if ($taskKey === 'fmc_commissioned') {
            throw WorkflowGuardException::because('Commission the File Movement Card; that ticks this task.');
        }
        if ($taskKey === CorrespondenceStep::TASK) {
            throw WorkflowGuardException::because('Use Create Correspondence File; that ticks this task.');
        }
        if (in_array($taskKey, CorrespondenceCustody::TASKS, true) && CorrespondenceCustody::installed()) {
            throw WorkflowGuardException::because('Use Detach / Attach correspondence file; that ticks this task.');
        }
        if ($taskKey === PndBills::TASK) {
            throw WorkflowGuardException::because('Issue the Fees P&D bill; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JACKET) {
            throw WorkflowGuardException::because('Commission the field jacket; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_ITS) {
            throw WorkflowGuardException::because('Issue the I to S on the field jacket; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JOB) {
            throw WorkflowGuardException::because('Attach the job and the surveyor; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JOB_ITS) {
            throw WorkflowGuardException::because('Issue the Instruction to Surveyor on the job; that ticks this task.');
        }
        if (isset(ChartingCards::TASK_STAGE[$taskKey])) {
            throw WorkflowGuardException::because('Fill in the charting card; saving it ticks this task.');
        }
        // Land 12 has no screen here, so its tick stays manual, but only once the Yes/No form is answered.
        if ($taskKey === YesNoForms::TASK && YesNoForms::installed() && !app(YesNoForms::class)->isComplete($case)) {
            throw WorkflowGuardException::because('Answer all seven questions on the Yes/No form first.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $taskKey, $tasks, $note, $userId) {
            $exists = CadastralCaseTask::query()
                ->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)
                ->where('task_key', $taskKey)
                ->exists();
            if ($exists) {
                return "\"{$tasks[$taskKey]}\" was already done.";
            }

            CadastralCaseTask::create([
                'cadastral_case_id' => $case->id,
                'step' => $case->current_step,
                'task_key' => $taskKey,
                'note' => $note,
                'done_by' => $userId,
                'done_at' => now(),
            ]);

            return "Done: {$tasks[$taskKey]}.";
        });
    }

    /** Forward, decide, or close. Every task of the step must be done first. */
    public function advance(CadastralCase $case, string $outcome, ?string $remarks, ?int $userId): string
    {
        $this->assertOpen($case);
        $def = $this->def();

        return DB::connection(self::CONN)->transaction(function () use ($case, $outcome, $remarks, $userId, $def) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertOpen($case);

            $this->assertTasksDone($case);

            // In a unit, only the head sends the file across (HeadApproval).
            $heads = app(HeadApproval::class);
            if ($heads->required($case) && !$heads->isHead(User::find($userId), $case->current_desk)) {
                throw WorkflowGuardException::because('Only the ' . $heads->headOf($case->current_desk) . ' sends this file on. Use "Send to '
                    . $heads->headOf($case->current_desk) . '".');
            }

            $from = $case->current_step;
            if (isset($def->step($case->workflow, $from)['outcomes'][$outcome])
                && $def->outcomeNeedsReason($case->workflow, $from, $outcome)
                && trim((string) $remarks) === '') {
                throw WorkflowGuardException::because('Give the reason before sending the file back.');
            }
            $next = $this->engine->nextStep($case->workflow, $from, $outcome, $case->sub_type);

            // Send to Deeds (and the requester return) only when its checks pass.
            if ($failing = app(DeedsDispatchChecks::class)->failing($case)) {
                throw WorkflowGuardException::because('Not yet: ' . implode('; ', $failing) . '.');
            }

            if ($next === null) {
                $end = $def->step($case->workflow, $from)['end'];
                $case->update(['status' => $end, 'closed_at' => now()]);
                $this->move($case, $from, null, 'close', $remarks, $userId);
                $this->audit->logAction('Cadastral Case Closed', 'CadastralCase', $case->id,
                    ['status' => CadastralCase::STATUS_IN_PROGRESS], ['status' => $end], "{$case->case_ref} closed: {$end}");

                return $end === 'returned'
                    ? "{$case->case_ref} sent back to " . $case->fileSourceLabel() . '.'
                    : "{$case->case_ref} completed.";
            }

            if ($from === FieldJackets::FIELD_STEP) {
                app(FieldJackets::class)->markReturned($case);
            }
            $desk = $def->step($case->workflow, $next)['desk'];
            $case->update(['current_step' => $next, 'current_desk' => $desk]);
            $this->move($case, $from, $next, $outcome, $remarks, $userId);

            // Name the choice and the receiving desk, so the officer sees what happened.
            $chose = $def->step($case->workflow, $from)['outcomes'][$outcome]['label'] ?? null;

            return ($chose ? "{$chose}: " : '') . "{$case->case_ref} sent to " . $def->deskShort($desk)
                . ' (' . $def->deskLabel($desk) . ').';
        });
    }

    /**
     * Staff hand the finished step to the unit head (HeadApproval): a movement on the same
     * step, so the file stays where it is and shows on the head's Approval list.
     */
    public function sendToHead(CadastralCase $case, ?string $remarks, ?int $userId): string
    {
        $this->assertOpen($case);
        $heads = app(HeadApproval::class);

        return DB::connection(self::CONN)->transaction(function () use ($case, $remarks, $userId, $heads) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertOpen($case);
            if (!$heads->required($case)) {
                throw WorkflowGuardException::because('This step has no head-of-unit approval.');
            }
            if ($heads->awaiting($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is already with the " . $heads->headOf($case->current_desk) . '.');
            }
            $this->assertTasksDone($case);

            $this->move($case, $case->current_step, $case->current_step, HeadApproval::TO_HEAD, $remarks, $userId);

            return "{$case->case_ref} sent to the " . $heads->headOf($case->current_desk) . ' for approval.';
        });
    }

    /** The head sends a file back to the unit's staff, with the reason. */
    public function returnToStaff(CadastralCase $case, ?string $remarks, ?int $userId): string
    {
        $this->assertOpen($case);
        $heads = app(HeadApproval::class);
        if (trim((string) $remarks) === '') {
            throw WorkflowGuardException::because('Give the reason before returning the file to the staff.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $remarks, $userId, $heads) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$heads->awaiting($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not waiting for approval.");
            }
            if (!$heads->isHead(User::find($userId), $case->current_desk)) {
                throw WorkflowGuardException::because('Only the ' . $heads->headOf($case->current_desk) . ' returns it.');
            }

            $this->move($case, $case->current_step, $case->current_step, HeadApproval::RETURNED, $remarks, $userId);

            return "{$case->case_ref} returned to the staff.";
        });
    }

    /** Every task of the current step done (a file with no correspondence file skips detach/attach). */
    private function assertTasksDone(CadastralCase $case): void
    {
        $def = $this->def();
        $done = CadastralCaseTask::query()
            ->where('cadastral_case_id', $case->id)
            ->where('step', $case->current_step)
            ->pluck('task_key')->all();
        $missing = $this->engine->missingTasks($case->workflow, $case->current_step, $done);
        // No correspondence file (a Verification file can have none): nothing to detach or attach.
        if (!trim((string) $case->correspondence_fileno)) {
            $missing = array_values(array_diff($missing, CorrespondenceCustody::TASKS));
        }
        if ($missing) {
            $labels = array_intersect_key((array) $def->step($case->workflow, $case->current_step)['tasks'], array_flip($missing));
            throw WorkflowGuardException::because('Finish this step first: ' . implode('; ', $labels) . '.');
        }
    }

    /** A Direct Allocation file's sub-type. Changeable only until the file has been routed on it. */
    public function setSubType(CadastralCase $case, string $subType, ?int $userId): string
    {
        $this->assertOpen($case);
        $def = $this->def();
        if (empty($def->fileTypes()[$case->file_type]['asks_sub_type']) && empty($def->workflow($case->workflow)['asks_sub_type'])) {
            throw WorkflowGuardException::because('This file does not choose a sub-type.');
        }
        if (!isset($def->subTypes($case->workflow)[$subType])) {
            throw WorkflowGuardException::because('That sub-type does not belong to this application type.');
        }

        $routed = CadastralCaseMovement::query()
            ->where('cadastral_case_id', $case->id)
            ->whereIn('from_step', $this->routingSteps($case->workflow))
            ->exists();
        if ($routed) {
            throw WorkflowGuardException::because('The file has already been routed on its sub-type.');
        }

        $old = $case->sub_type;
        $case->update(['sub_type' => $subType]);
        $this->audit->logAction('Cadastral Case Sub-type Set', 'CadastralCase', $case->id,
            ['sub_type' => $old], ['sub_type' => $subType], "{$case->case_ref} sub-type set");

        return 'Sub-type set to ' . $def->subTypes($case->workflow)[$subType] . '.';
    }

    /**
     * Plot No., District and LGA, once the FMC migration has added their
     * columns (2026_10_10_110000). Before that they are simply not saved.
     */
    private function propertyColumns(array $data): array
    {
        static $has = null;
        $has ??= Schema::connection(self::CONN)->hasColumn('cadastral_cases', 'plot_no');

        return $has ? [
            'plot_no' => ($data['plot_no'] ?? null) ?: null,
            'district' => ($data['district'] ?? null) ?: null,
            'lga' => ($data['lga'] ?? null) ?: null,
        ] : [];
    }

    private function routingSteps(string $workflow): array
    {
        return array_keys(array_filter($this->def()->steps($workflow), fn ($s) => isset($s['route_by'])));
    }

    private function assertOpen(CadastralCase $case): void
    {
        if (!$case->isOpen()) {
            throw WorkflowGuardException::because("{$case->case_ref} is closed.");
        }
    }

    private function move(CadastralCase $case, ?string $from, ?string $to, string $outcome, ?string $remarks, ?int $userId, ?string $fromDesk = null): void
    {
        $def = $this->def();
        CadastralCaseMovement::create([
            'cadastral_case_id' => $case->id,
            'from_step' => $from,
            'from_desk' => $from ? $def->step($case->workflow, $from)['desk'] : $fromDesk,
            'to_step' => $to,
            'to_desk' => $to ? $def->step($case->workflow, $to)['desk'] : null,
            'outcome' => $outcome,
            'remarks' => $remarks,
            'acted_by' => $userId,
            'acted_at' => now(),
        ]);

        // In-app notification to the desk concerned, sent once the move commits.
        app(WorkflowNotifications::class)->moved($case, $from, $to, $outcome, $remarks, $userId);
    }

    /** CAD-2026-00001: next sequence for the year. Caller holds the transaction. */
    private function nextRef(int $year): string
    {
        $format = (string) config('cadastral_dept.case_ref_format', 'CAD-{Y}-{N}');
        $pad = (int) config('cadastral_dept.case_ref_pad', 5);
        $prefix = str_replace('{Y}', (string) $year, strstr($format, '{N}', true) ?: '');

        $last = CadastralCase::query()
            ->where('case_ref', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('case_ref');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return str_replace(['{Y}', '{N}'], [(string) $year, str_pad((string) $n, $pad, '0', STR_PAD_LEFT)], $format);
    }
}
