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
        [$subType, $allocationRoute] = $this->resolveType($data);

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

            // No correspondence file yet: make it now (2026-10-27); the step later only matches it.
            $csf = app(CorrespondenceStep::class)->atIntake($case, $userId);

            // The registration itself: Registry to the first desk, as one movement.
            $remarks = trim(implode(' ', array_filter([$data['remarks'] ?? null, $csf ? "({$csf})" : null])));
            $this->move($case, null, $start, 'registered', $remarks !== '' ? $remarks : null, $userId, 'registry');
            // A first step with a unit head (registry_intake): registering is the staff's send to the
            // head, so the file waits in AD CAD Registry > Approval and only the head sends it to D Cad.
            if (app(HeadApproval::class)->required($case)) {
                $this->move($case, $start, $start, HeadApproval::TO_HEAD, $data['remarks'] ?? null, $userId);
            }
            $this->audit->logAction('Cadastral Case Opened', 'CadastralCase', $case->id, null,
                $case->only(['case_ref', 'workflow', 'sub_type', 'file_type', 'file_source', 'file_number']),
                "{$case->case_ref} registered for {$fileNumber} and sent to " . $def->deskLabel($case->current_desk));

            return $case;
        });
    }

    /**
     * The application type checks shared by registration and Change application type: the
     * file type must allow the workflow and its number prefix, Verification's group must fit,
     * and the tenure / route answers come from the file type (asked only where it is open).
     *
     * @return array{0: ?string, 1: ?string} [sub_type, allocation_route]
     */
    private function resolveType(array $data): array
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
        // A file type with fixed prefixes (Conversion File: CON-) takes only those numbers.
        $prefixes = (array) ($fileType['prefixes'] ?? []);
        $number = strtoupper(trim((string) ($data['file_number'] ?? '')));
        if ($prefixes && $number !== '' && !collect($prefixes)->contains(fn ($p) => str_starts_with($number, strtoupper($p)))) {
            throw WorkflowGuardException::because("{$number} is not a {$fileType['label']}: its number must start with " . implode(' or ', $prefixes) . '.');
        }
        // Verification is Conversion or Direct Allocation (user, 2026-10-26): the file must be from that group.
        if ($workflow === 'verification' && !empty($data['verification_group'])) {
            $group = (string) $data['verification_group'];
            $groupPrefixes = IntakeLookup::prefixesFor($group);
            if (!in_array($group, (array) $fileType['workflows'], true)) {
                throw WorkflowGuardException::because("A {$fileType['label']} is not a " . $def->workflow($group)['label'] . ' file.');
            }
            // A file type with its own prefixes (ST-, SLTR-) belongs to both groups; the type checks above cover it.
            if ($groupPrefixes && !$prefixes && $number !== '' && !collect($groupPrefixes)->contains(fn ($p) => str_starts_with($number, $p))) {
                throw WorkflowGuardException::because("{$number} is not a " . $def->workflow($group)['label'] . ' file: its number must start with '
                    . implode(' / ', $groupPrefixes) . '.');
            }
        }

        // Statutory / Customary and Resettlement / Direct come from the file type (file_types
        // `tenure` and `route`, user's voice note 2026-10-20): a fixed value is set here whatever
        // the form sent; only an open one is asked. Direct Allocation: only OP asks its route.
        $subType = ($data['sub_type'] ?? '') ?: null;
        $allocationRoute = null;
        $route = $fileType['route'] ?? null;
        if ($workflow === 'verification') {
            $tenures = $def->subTypes('verification');
            if (($fileType['tenure'] ?? null) === false) {
                $subType = null;   // not asked for this file type (DCIV/LPCC)
            } elseif (!empty($fileType['tenure'])) {
                $subType = $fileType['tenure'];
            } elseif (!$subType || !isset($tenures[$subType])) {
                throw WorkflowGuardException::because("Choose whether this {$fileType['label']} is " . implode(' or ', $tenures) . '.');
            }
            $routesFrom = $def->workflow('verification')['asks_allocation_route'] ?? 'direct';
            if ($route === 'ask') {
                $allocationRoute = ($data['allocation_route'] ?? '') ?: null;
                if (!$allocationRoute || !isset($def->subTypes($routesFrom)[$allocationRoute])) {
                    throw WorkflowGuardException::because("Choose whether this {$fileType['label']} is " . implode(' or ', $def->subTypes($routesFrom)) . '.');
                }
            } elseif ($route) {
                $allocationRoute = $route;
            }
        } elseif ($route === 'ask' && $def->subTypes($workflow)) {
            if (!$subType || !isset($def->subTypes($workflow)[$subType])) {
                throw WorkflowGuardException::because("Choose whether this {$fileType['label']} is " . implode(' or ', $def->subTypes($workflow)) . '.');
            }
        } else {
            $subType = $route && isset($def->subTypes($workflow)[$route]) ? $route : $def->defaultSubType($workflow);
        }


        return [$subType, $allocationRoute];
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
            throw WorkflowGuardException::because('Use Commission Correspondence File; that ticks this task.');
        }
        if (in_array($taskKey, CorrespondenceCustody::TASKS, true) && CorrespondenceCustody::installed()) {
            throw WorkflowGuardException::because('Use Detach / Attach correspondence file; that ticks this task.');
        }
        if ($taskKey === PndBills::TASK) {
            throw WorkflowGuardException::because('Issue the Fees P&D bill; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JACKET) {
            throw WorkflowGuardException::because('Commission the Computation File; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_ITS) {
            throw WorkflowGuardException::because('Issue the I to S on the Computation File; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JOB) {
            throw WorkflowGuardException::because('Attach the job and the surveyor; that ticks this task.');
        }
        // Land 12 is ticked by saving the form from the file page (Land12Link); by hand only once one exists.
        if ($taskKey === Land12Link::TASK && !app(Land12Link::class)->latest($case)) {
            throw WorkflowGuardException::because('Fill Land 12 first; saving it ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_DETACH) {
            throw WorkflowGuardException::because('Use Detach Computation File; that ticks this task.');
        }
        if ($taskKey === FieldJackets::TASK_JOB_ITS) {
            throw WorkflowGuardException::because('Issue the Instruction to Surveyor on the job; that ticks this task.');
        }
        if (isset(ChartingCards::TASK_STAGE[$taskKey])) {
            throw WorkflowGuardException::because('Fill in the charting card; saving it ticks this task.');
        }
        if (isset(StepDocuments::TASKS[$taskKey])) {
            throw WorkflowGuardException::because(StepDocuments::TASKS[$taskKey]['sign']
                ? 'Sign the bill in the system, or upload the signed copy; that ticks this task.'
                : 'Upload the ' . StepDocuments::TASKS[$taskKey]['label'] . '; that ticks this task.');
        }
        if ($taskKey === PlanningAdvice::TASK) {
            throw WorkflowGuardException::because('Upload the Planning Advice; that ticks this task.');
        }
        if ($taskKey === EdmsUpdate::TASK) {
            if (app(PageCounts::class)->direction($case)) {
                $pages = app(PageCounts::class)->state($case);
                if ($pages['triggered'] === null) {
                    throw WorkflowGuardException::because('Enter the number of pages the main file came back with first.');
                }
                if ($pages['triggered'] === false) {
                    throw WorkflowGuardException::because('No new pages came back from Land, so DMS needs no update.');
                }
            }
            $edms = app(EdmsUpdate::class)->status($case);
            if (!$edms['indexed']) {
                throw WorkflowGuardException::because('This file is not in the file index, so DMS cannot be updated from here.');
            }
            if (!$edms['complete']) {
                throw WorkflowGuardException::because('Scan and upload the new pages first (DMS update).');
            }
            // Page typing is not required to move on (user, 2026-10-27); the note says where it stands.
            $note = "{$edms['new_pages']} new page(s) uploaded"
                . ($edms['typed_all'] ? ' and page-typed' : " ({$edms['typed']} page-typed; the rest on Page Typing)");
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
            $this->assertAccepted($case);

            $this->assertTasksDone($case);

            // In a unit, only the head sends the file across (HeadApproval).
            $heads = app(HeadApproval::class);
            if ($heads->required($case) && !$heads->isHead(User::find($userId), $case->current_desk)) {
                throw WorkflowGuardException::because('Only the ' . $heads->headOf($case->current_desk) . ' sends this file on. Use "Send to '
                    . $heads->headOf($case->current_desk) . '".');
            }

            $from = $case->current_step;
            // Final Charting: a Government survey goes to the SG, a private one skips it (SurveyRoute).
            app(SurveyRoute::class)->assertAllowed($case, $outcome);
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
                throw WorkflowGuardException::because(HeadApproval::enabled()
                    ? 'This step has no head-of-unit approval.'
                    : 'Head approval is switched off: send the file on directly. Reload the page to see its actions.');
            }
            if ($heads->awaiting($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is already with the " . $heads->headOf($case->current_desk) . '.');
            }
            $this->assertAccepted($case);
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
        // The main file's page count to and from Land (PageCounts): it must be entered, and
        // when no new pages came back there is nothing to update in DMS.
        $counts = app(PageCounts::class);
        if ($counts->direction($case)) {
            $pages = $counts->state($case);
            if (!$pages['recorded']) {
                throw WorkflowGuardException::because('Enter the number of pages in the main file first.');
            }
            if ($pages['triggered'] === false) {
                $missing = array_values(array_diff($missing, [EdmsUpdate::TASK]));
            }
        }
        if ($missing) {
            $labels = array_intersect_key((array) $def->step($case->workflow, $case->current_step)['tasks'], array_flip($missing));
            throw WorkflowGuardException::because('Finish this step first: ' . implode('; ', $labels) . '.');
        }
    }

    /** Movement outcome of an admin jump (jumpTo). */
    public const JUMP = 'admin_jump';

    /**
     * Super Admin / Central Admin: move the file straight to any step of its workflow, forward or
     * back, past the tasks and checks in between (user, 2026-10-26: "the steps should be clickable
     * ... can bypass any step"). Nothing in between is ticked or created; the move is one movement
     * with the reason, plus an audit row.
     */
    public function jumpTo(CadastralCase $case, string $step, ?string $reason, ?int $userId): string
    {
        $this->assertOpen($case);
        $def = $this->def();
        if (!DeskAccess::isCentralAdmin(User::find($userId))) {
            throw WorkflowGuardException::because('Only Super Admin or Central Admin can move a file to any step.');
        }
        if (!$def->hasStep($case->workflow, $step)) {
            throw WorkflowGuardException::because('That step is not part of this file\'s workflow.');
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw WorkflowGuardException::because('Give the reason for moving the file.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $step, $reason, $userId, $def) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertOpen($case);
            $from = $case->current_step;
            if ($from === $step) {
                throw WorkflowGuardException::because("{$case->case_ref} is already at that step.");
            }
            $desk = $def->step($case->workflow, $step)['desk'];
            $case->update(['current_step' => $step, 'current_desk' => $desk]);
            $this->move($case, $from, $step, self::JUMP, $reason, $userId);
            $this->audit->logAction('Cadastral Case Moved By Admin', 'CadastralCase', $case->id,
                ['current_step' => $from], ['current_step' => $step], "{$case->case_ref} moved by admin from {$from} to {$step}: {$reason}");

            return "{$case->case_ref} moved to " . $def->deskShort($desk) . ' (' . $def->step($case->workflow, $step)['label'] . ').';
        });
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
     * Change application type (user, 2026-10-28): Registry picked the wrong one (Verification
     * for a Direct Allocation File). Allowed while the file is still at Registry intake, before
     * it has been sent on, so no step of the old route has been worked. The same checks as
     * registration apply (resolveType); the file stays at the new route's first step.
     *
     * @param array{workflow:string, file_type?:?string, sub_type?:?string, allocation_route?:?string, verification_group?:?string} $data
     */
    public function changeType(CadastralCase $case, array $data, ?int $userId): string
    {
        $this->assertOpen($case);
        $def = $this->def();
        $start = $def->startStep($case->workflow);
        $sentOn = CadastralCaseMovement::query()->where('cadastral_case_id', $case->id)
            ->whereNotNull('from_step')->where('to_step', '!=', $start)->exists();
        if ($case->current_step !== $start || $sentOn) {
            throw WorkflowGuardException::because("{$case->case_ref} has left Registry intake, so its application type can no longer be changed. Master Delete it and register it again.");
        }

        $data['file_type'] = ($data['file_type'] ?? '') ?: $case->file_type;
        $data['file_number'] = $case->file_number;
        [$subType, $allocationRoute] = $this->resolveType($data);
        $workflow = $data['workflow'];
        $newStart = $def->startStep($workflow);

        $before = $case->only(['workflow', 'file_type', 'sub_type', 'allocation_route']);
        $after = ['workflow' => $workflow, 'file_type' => $data['file_type'], 'sub_type' => $subType, 'allocation_route' => $allocationRoute];
        if ($before == $after) {
            return 'Nothing changed: the file is already ' . $def->workflow($workflow)['label'] . '.';
        }

        DB::connection(self::CONN)->transaction(function () use ($case, $after, $newStart, $def, $workflow, $before, $userId) {
            $case->update($after + ['current_step' => $newStart, 'current_desk' => $def->step($workflow, $newStart)['desk']]);
            $this->audit->logAction('Cadastral Case Application Type Changed', 'CadastralCase', $case->id, $before, $after,
                "{$case->case_ref} application type changed from " . $def->workflow($before['workflow'])['label']
                . ' to ' . $def->workflow($workflow)['label'] . ($userId ? " by user {$userId}" : ''));
        });

        return "{$case->case_ref} is now " . $def->workflow($workflow)['label']
            . ($subType && isset($def->subTypes($workflow)[$subType]) ? ' (' . $def->subTypes($workflow)[$subType] . ')' : '') . '.';
    }

    /** True while the application type may still be changed (changeType). */
    public function canChangeType(CadastralCase $case): bool
    {
        if (!$case->isOpen()) {
            return false;
        }
        $start = $this->def()->startStep($case->workflow);

        return $case->current_step === $start && !CadastralCaseMovement::query()->where('cadastral_case_id', $case->id)
            ->whereNotNull('from_step')->where('to_step', '!=', $start)->exists();
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

    /** A file sent to a unit is accepted there before the unit sends it on (CadastralFileTracking). */
    private function assertAccepted(CadastralCase $case): void
    {
        if (CadastralFileTracking::awaiting($case)) {
            throw WorkflowGuardException::because("Accept {$case->case_ref} first: it was sent to " . $case->deskShort() . ' and has not been accepted there yet.');
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

        // The Ministry file tracker and the receiving unit's acceptance (2026-10-27).
        app(CadastralFileTracking::class)->moved($case, $from, $to, $outcome, $userId, $fromDesk);

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
