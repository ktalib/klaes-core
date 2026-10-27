<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Cadastral\CadastralSurveyor;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralFieldJacket;
use App\Models\CadastralDept\CadastralFieldJacketAssignment;
use App\Services\AuditService;
use App\Services\Cadastral\SurveyJobNumberGenerator;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Field Jacket, I to S, job and surveyor (client brief 2026-10-10, reshaped by the workflow
 * update transcript of 2026-10-16).
 *
 * AD P&D commissions a plain field jacket (number and file details; no job, no surveyor) and
 * issues a plain I to S, the instruction to AD Cad Info to attach a job and a surveyor. AD Cad
 * Info then attaches a job from the Job Book (cadastral_survey_jobs; the job takes the file's
 * location) and a surveyor, and issues the job's Instruction to Surveyor before the file goes
 * to Survey. The surveyor is then locked: changing it is a reassignment, with a reason, kept
 * in the jacket's history. Each action ticks its own task, so none is ticked by hand.
 *
 * Formats are defaults until the client confirms (plan Q9): jacket FJ-YYYY-NNNNN,
 * job and I to S numbers from the old module's settings.
 */
class FieldJackets
{
    private const CONN = 'sqlsrv';

    /** P&D: the plain field jacket and the plain I to S (transcript, 2026-10-16). */
    public const TASK_JACKET = 'field_jacket';
    public const TASK_ITS = 'its';
    /** AD Cad Info: the job and surveyor, and the job's Instruction to Surveyor. */
    public const TASK_JOB = 'job_attached';
    public const TASK_JOB_ITS = 'job_its';
    /** The external step that ends when the surveyor's work comes back. */
    public const FIELD_STEP = 'field_survey';
    /** Registry, last step: the Computation File comes off the correspondence file (change request 26-10-2026). */
    public const TASK_DETACH = 'computation_detached';

    private static ?bool $attachInstalled = null;

    public function __construct(
        private SurveyJobNumberGenerator $numbers,
        private WorkflowDefinition $def,
        private AuditService $audit
    ) {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_field_jackets');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Whether the case's current step has the given task (field_jacket, its, job_attached, job_its). */
    public function due(CadastralCase $case, string $task): bool
    {
        return $case->isOpen()
            && isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])[$task]);
    }

    public function forCase(CadastralCase $case): ?CadastralFieldJacket
    {
        return self::installed()
            ? CadastralFieldJacket::query()->with(['job', 'surveyor', 'assignments.assigner'])->where('cadastral_case_id', $case->id)->first()
            : null;
    }

    /** Surveyors a jacket can be given to: active ones, licence state shown in the list. */
    public function surveyors()
    {
        return CadastralSurveyor::query()->where('is_active', true)->orderBy('full_name')->get();
    }

    /**
     * Jobs registered and not yet attached to a file (user, 2026-10-12): what Assign
     * to Surveyor (I to S) offers to attach, with a surveyor.
     */
    public function waitingJobs()
    {
        return CadastralSurveyJob::query()
            ->where(fn ($w) => $w->whereNull('file_number')->orWhere('file_number', ''))
            ->whereNull('cadastral_case_id')
            ->where('status', 'Draft')
            ->orderBy('job_number')
            ->get();
    }

    /** The starting text of the job's Instruction to Surveyor, which AD Cad Info edits. */
    public function defaultInstructions(CadastralFieldJacket $jacket): string
    {
        $where = implode(', ', array_filter([
            $jacket->plot_no ? 'Plot ' . $jacket->plot_no : null, $jacket->district, $jacket->lga,
        ]));

        return "You are hereby instructed to carry out the survey of the property"
            . ($where ? " at {$where}" : '')
            . ", file {$jacket->file_number}" . ($jacket->file_title ? " ({$jacket->file_title})" : '')
            . ($jacket->job ? ", under survey job {$jacket->job->job_number}" : '')
            . " and Computation File {$jacket->field_jacket_no}.";
    }

    /** The starting text of P&D's plain I to S: AD Cad Info is to attach a job and a surveyor. */
    public function defaultPndInstruction(CadastralFieldJacket $jacket): string
    {
        return "AD Cadastral Information: attach a survey job (Job Book) and a surveyor to Computation File {$jacket->field_jacket_no}"
            . " for file {$jacket->file_number}" . ($jacket->file_title ? " ({$jacket->file_title})" : '')
            . ", and issue the Instruction to Surveyor.";
    }

    /**
     * P&D commissions a plain field jacket on the file (transcript, 2026-10-16): its number and
     * the file's details, no job and no surveyor. Ticks the task.
     */
    public function commission(CadastralCase $case, array $input, ?int $userId): CadastralFieldJacket
    {
        $this->assertInstalled();

        return DB::connection(self::CONN)->transaction(function () use ($case, $input, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->due($case, self::TASK_JACKET)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the Computation File is commissioned.");
            }
            if (CadastralFieldJacket::query()->where('cadastral_case_id', $case->id)->exists()) {
                throw WorkflowGuardException::because("{$case->case_ref} already has its Computation File.");
            }
            $now = now();
            $jacketNo = $this->nextNo($now->year);

            $jacket = CadastralFieldJacket::create([
                'cadastral_case_id' => $case->id,
                'field_jacket_no' => $jacketNo,
                'file_number' => $case->file_number,
                'file_title' => $case->file_title,
                'plot_no' => $case->plot_no,
                'district' => $case->district,
                'lga' => $case->lga,
                'location' => $case->location,
                'remarks' => Normalise::text($input['remarks'] ?? null) ?: null,
                'commissioned_by' => $userId,
                'commissioned_at' => $now,
            ]);
            $this->tick($case, self::TASK_JACKET, $jacketNo, $userId);
            // Commissioning attaches it to the correspondence file (change request 26-10-2026).
            $this->attachTo($jacket, $case, $userId);

            $this->audit->logAction('Cadastral Field Jacket Commissioned', 'CadastralFieldJacket', $jacket->id, null,
                ['field_jacket_no' => $jacketNo], "Computation File (Field Jacket) {$jacketNo} commissioned for {$case->case_ref} ({$case->file_number})");

            return $jacket;
        });
    }

    /**
     * P&D's plain I to S (transcript, 2026-10-16): an instruction to AD Cad Info to attach a job
     * and a surveyor to the jacket. Kept as the task's note; no number, nothing attached.
     */
    public function issueIts(CadastralCase $case, array $input, ?int $userId): string
    {
        $this->assertInstalled();
        $text = Normalise::text($input['its_instructions'] ?? null);
        if ($text === '') {
            throw WorkflowGuardException::because('Write the instruction text.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $text, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->due($case, self::TASK_ITS)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where P&D issues the I to S.");
            }
            $jacket = CadastralFieldJacket::query()->where('cadastral_case_id', $case->id)->first();
            if (!$jacket) {
                throw WorkflowGuardException::because('Commission the Computation File first; the I to S is issued on it.');
            }
            $this->tick($case, self::TASK_ITS, mb_substr($text, 0, 1000), $userId);
            $this->audit->logAction('Cadastral P&D I to S Issued', 'CadastralFieldJacket', $jacket->id, null,
                ['instruction' => $text], "P&D I to S issued on {$jacket->field_jacket_no} for {$case->case_ref}");

            return "I to S issued on {$jacket->field_jacket_no}. Send the file to AD Cad Info to attach the job and surveyor.";
        });
    }

    /**
     * AD Cad Info attaches a registered job (Job Book) and a surveyor to the field jacket
     * (transcript, 2026-10-16). The job takes the file's number, title and location: a job
     * has no location of its own. Ticks the task.
     */
    public function attachJob(CadastralCase $case, array $input, ?int $userId): CadastralFieldJacket
    {
        $this->assertInstalled();
        $jobId = (int) ($input['cadastral_survey_job_id'] ?? 0);
        if (!$jobId) {
            throw WorkflowGuardException::because('Choose the survey job to attach.');
        }
        $surveyor = $this->activeSurveyor($input['cadastral_surveyor_id'] ?? null);

        return DB::connection(self::CONN)->transaction(function () use ($case, $input, $userId, $jobId, $surveyor) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->due($case, self::TASK_JOB)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the job and surveyor are attached.");
            }
            $jacket = CadastralFieldJacket::query()->lockForUpdate()->where('cadastral_case_id', $case->id)->first();
            if (!$jacket) {
                throw WorkflowGuardException::because("{$case->case_ref} has no Computation File. P&D commissions it first.");
            }
            if ($jacket->cadastral_survey_job_id) {
                throw WorkflowGuardException::because("{$jacket->field_jacket_no} already has its job.");
            }

            $job = CadastralSurveyJob::query()->lockForUpdate()->find($jobId);
            if (!$job) {
                throw WorkflowGuardException::because('That survey job no longer exists.');
            }
            if (trim((string) $job->file_number) !== '' || $job->cadastral_case_id) {
                throw WorkflowGuardException::because("{$job->job_number} is already attached to " . ($job->file_number ?: 'another file') . '.');
            }
            if ($job->status !== 'Draft') {
                throw WorkflowGuardException::because("{$job->job_number} is {$job->status} and cannot be attached.");
            }
            $scope = Normalise::text($input['job_scope'] ?? null);
            $job->update([
                'cadastral_case_id' => $case->id,
                'field_jacket_no' => $jacket->field_jacket_no,
                'file_number' => $case->file_number,
                'file_title' => $case->file_title,
                // The job's location is the file's (it prints on the Instruction to Surveyor).
                'prop_plot' => $case->plot_no,
                'prop_district' => $case->district,
                'prop_lga' => $case->lga,
                'cadastral_surveyor_id' => $surveyor->id,
                'surveyor_name' => $surveyor->full_name,
                'firm_name' => $surveyor->firm_name,
            ] + ($scope !== '' ? ['job_scope' => $scope] : []));

            $jacket->update(['cadastral_survey_job_id' => $job->id, 'cadastral_surveyor_id' => $surveyor->id]);
            $this->assign($jacket, $surveyor, null, $userId);
            $this->tick($case, self::TASK_JOB, $job->job_number . ' · ' . $surveyor->full_name, $userId);

            $this->audit->logAction('Cadastral Job Attached', 'CadastralFieldJacket', $jacket->id, null,
                ['job_number' => $job->job_number, 'surveyor' => $surveyor->full_name],
                "Job {$job->job_number} and surveyor {$surveyor->full_name} attached to {$jacket->field_jacket_no} ({$case->case_ref})");

            return $jacket->fresh(['job']);
        });
    }

    /** Give the jacket (and its job) to another surveyor. The reason is required and kept. */
    public function reassign(CadastralFieldJacket $jacket, $surveyorId, ?string $reason, ?int $userId): string
    {
        $this->assertInstalled();
        $reason = Normalise::text($reason);
        if ($reason === '') {
            throw WorkflowGuardException::because('Give the reason for the reassignment.');
        }
        $surveyor = $this->activeSurveyor($surveyorId);

        return DB::connection(self::CONN)->transaction(function () use ($jacket, $surveyor, $reason, $userId) {
            $jacket = CadastralFieldJacket::query()->lockForUpdate()->findOrFail($jacket->id);
            $case = CadastralCase::findOrFail($jacket->cadastral_case_id);
            $job = CadastralSurveyJob::query()->lockForUpdate()->findOrFail($jacket->cadastral_survey_job_id);
            if (!$case->isOpen() || in_array($job->status, ['Accepted', 'Cancelled'], true)) {
                throw WorkflowGuardException::because("{$jacket->field_jacket_no} can no longer be reassigned.");
            }
            if ((int) $jacket->cadastral_surveyor_id === (int) $surveyor->id) {
                throw WorkflowGuardException::because("{$surveyor->full_name} already holds {$jacket->field_jacket_no}.");
            }
            if ($job->its_number && !$surveyor->canReceiveInstruction()) {
                throw WorkflowGuardException::because("The I to S is already issued, and {$surveyor->full_name}'s licence is {$surveyor->licence_status}.");
            }

            $before = $job->surveyor_name;
            CadastralFieldJacketAssignment::query()->where('cadastral_field_jacket_id', $jacket->id)->whereNull('ended_at')
                ->update(['ended_at' => now(), 'ended_by' => $userId, 'updated_at' => now()]);
            $this->assign($jacket, $surveyor, $reason, $userId);

            $jacket->update(['cadastral_surveyor_id' => $surveyor->id]);
            $job->update([
                'cadastral_surveyor_id' => $surveyor->id,
                'surveyor_name' => $surveyor->full_name,
                'firm_name' => $surveyor->firm_name,
            ] + ($job->its_number
                // An issued instruction is re-addressed to the new surveyor.
                ? ['its_recipient' => $surveyor->display_name]
                : []));

            $this->audit->logAction('Cadastral Field Jacket Reassigned', 'CadastralFieldJacket', $jacket->id,
                ['surveyor' => $before], ['surveyor' => $surveyor->full_name, 'reason' => $reason],
                "{$jacket->field_jacket_no} reassigned from {$before} to {$surveyor->full_name}: {$reason}");

            return "{$jacket->field_jacket_no} reassigned to {$surveyor->full_name}.";
        });
    }

    /** AD Cad Info issues the job's Instruction to Surveyor and ticks the task (transcript, 2026-10-16). */
    public function issueJobIts(CadastralCase $case, array $input, ?int $userId): CadastralSurveyJob
    {
        $this->assertInstalled();
        $instructions = trim((string) ($input['its_instructions'] ?? ''));
        if ($instructions === '') {
            throw WorkflowGuardException::because('Write the instruction text.');
        }
        $post = $input['its_officer_post'] ?? null;
        if ($post !== null && $post !== '' && !array_key_exists($post, (array) config('cadastral_module.posts'))) {
            throw WorkflowGuardException::because('Choose the signing officer from the list.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $instructions, $post, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->due($case, self::TASK_JOB_ITS)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the Instruction to Surveyor is issued.");
            }
            $jacket = CadastralFieldJacket::query()->where('cadastral_case_id', $case->id)->first();
            if (!$jacket || !$jacket->cadastral_survey_job_id) {
                throw WorkflowGuardException::because('Attach the job and the surveyor first; the instruction is issued on the job.');
            }
            $job = CadastralSurveyJob::query()->lockForUpdate()->findOrFail($jacket->cadastral_survey_job_id);
            if ($job->its_number) {
                throw WorkflowGuardException::because("{$job->job_number} already carries I to S {$job->its_number}.");
            }
            $surveyor = CadastralSurveyor::find($job->cadastral_surveyor_id);
            if (!$surveyor || !$surveyor->canReceiveInstruction()) {
                throw WorkflowGuardException::because(($surveyor ? "{$surveyor->full_name}'s licence is {$surveyor->licence_status}" : 'The surveyor is no longer in the directory')
                    . '. Reassign the jacket to a surveyor who may practise, then issue the I to S.');
            }

            $job->update([
                'its_number' => $this->numbers->nextItsNumber(),
                'its_issued_at' => now()->toDateString(),
                'its_recipient' => $surveyor->display_name,
                'its_instructions' => $instructions,
                'its_officer_post' => $post ?: null,
                'its_issued_by' => auth()->user()->name ?? null,
                'its_issued_by_user_id' => $userId,
                'status' => 'Issued',
                'issued_at' => now(),
            ]);
            $this->tick($case, self::TASK_JOB_ITS, $job->its_number, $userId);

            $this->audit->logAction('Cadastral I to S Issued', 'CadastralSurveyJob', $job->id, null,
                ['its_number' => $job->its_number, 'job_number' => $job->job_number, 'surveyor' => $surveyor->full_name],
                "I to S {$job->its_number} ({$job->job_number}) issued to {$surveyor->full_name} for {$case->case_ref}");

            return $job;
        });
    }

    /**
     * The file came back from the field: the job is marked submitted. Called by
     * CaseWorkflow inside its transaction when a case leaves the field step.
     */
    public function markReturned(CadastralCase $case): void
    {
        if (!self::installed()) {
            return;
        }
        $jacket = CadastralFieldJacket::query()->where('cadastral_case_id', $case->id)->first();
        $job = $jacket ? CadastralSurveyJob::find($jacket->cadastral_survey_job_id) : null;
        if ($job && in_array($job->status, ['Issued', 'In Field'], true)) {
            $job->update(['status' => 'Submitted', 'submitted_at' => now()]);
        }
    }

    public function countPrint(CadastralFieldJacket $jacket): void
    {
        CadastralFieldJacket::query()->whereKey($jacket->id)->increment('print_count');
    }

    private function assign(CadastralFieldJacket $jacket, CadastralSurveyor $surveyor, ?string $reason, ?int $userId): void
    {
        CadastralFieldJacketAssignment::create([
            'cadastral_field_jacket_id' => $jacket->id,
            'cadastral_surveyor_id' => $surveyor->id,
            'surveyor_name' => $surveyor->full_name,
            'firm_name' => $surveyor->firm_name,
            'reason' => $reason,
            'assigned_by' => $userId,
            'assigned_at' => now(),
        ]);
    }

    /** The 2026_10_27_120000 migration has run (attach / detach columns exist). */
    public static function attachInstalled(): bool
    {
        return self::$attachInstalled ??= Schema::connection(self::CONN)->hasColumn('cadastral_field_jackets', 'attached_at');
    }

    /**
     * Attach the Computation File to the case's correspondence file, if it has one and the jacket is
     * not attached yet. A jacket commissioned before the correspondence file exists is attached when
     * the correspondence file is created (CorrespondenceStep). Returns whether it attached now.
     */
    public function attachTo(CadastralFieldJacket $jacket, CadastralCase $case, ?int $userId): bool
    {
        if (!self::attachInstalled() || $jacket->attached_at || $jacket->detached_at
            || (!$case->correspondence_fileno && !($case->shadow_fileno ?? null))) {
            return false;
        }
        $jacket->update([
            'correspondence_fileno' => $case->correspondence_fileno,
            'shadow_fileno' => $case->shadow_fileno ?? null,
            'attached_at' => now(),
            'attached_by' => $userId,
        ]);
        $this->audit->logAction('Cadastral Computation File Attached', 'CadastralFieldJacket', $jacket->id, null,
            ['correspondence_fileno' => $case->correspondence_fileno, 'shadow_fileno' => $case->shadow_fileno ?? null],
            "Computation File {$jacket->field_jacket_no} attached to correspondence file " . (($case->shadow_fileno ?? null) ?: $case->correspondence_fileno) . " ({$case->case_ref})");

        return true;
    }

    /** Attach the case's waiting jacket, if any (the correspondence file was just created). */
    public function attachWaiting(CadastralCase $case, ?int $userId): void
    {
        if (self::installed() && self::attachInstalled() && ($jacket = $this->forCase($case))) {
            $this->attachTo($jacket, $case, $userId);
        }
    }

    /**
     * Registry detaches the Computation File from the correspondence file before the file goes to
     * Deeds (step 31). It stays recorded as having travelled with that correspondence file. A file
     * with no attached Computation File (commissioned before 2026-10-27, or none) just ticks the task.
     */
    public function detach(CadastralCase $case, ?string $note, ?int $userId): string
    {
        return DB::connection(self::CONN)->transaction(function () use ($case, $note, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->due($case, self::TASK_DETACH) || in_array(self::TASK_DETACH, $case->doneTaskKeys($case->current_step), true)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the Computation File is detached, or it is already done.");
            }
            $note = Normalise::text($note) ?: null;
            $jacket = self::installed() ? $this->forCase($case) : null;

            if ($jacket && self::attachInstalled() && $jacket->attached_at && !$jacket->detached_at) {
                $jacket->update(['detached_at' => now(), 'detached_by' => $userId, 'detach_note' => $note]);
                $from = $jacket->shadow_fileno ?: $jacket->correspondence_fileno;
                $this->tick($case, self::TASK_DETACH, mb_substr("{$jacket->field_jacket_no} detached from {$from}" . ($note ? ": {$note}" : ''), 0, 1000), $userId);
                $this->audit->logAction('Cadastral Computation File Detached', 'CadastralFieldJacket', $jacket->id, null,
                    ['detached_from' => $from, 'note' => $note], "Computation File {$jacket->field_jacket_no} detached from {$from} ({$case->case_ref})");

                return "Computation File {$jacket->field_jacket_no} detached from correspondence file {$from}.";
            }

            $this->tick($case, self::TASK_DETACH, 'No Computation File attached' . ($note ? ": {$note}" : ''), $userId);

            return 'No Computation File was attached to the correspondence file; the task is ticked.';
        });
    }

    private function tick(CadastralCase $case, string $task, string $note, ?int $userId): void
    {
        $ticked = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
            ->where('step', $case->current_step)->where('task_key', $task)->exists();
        if (!$ticked) {
            CadastralCaseTask::create([
                'cadastral_case_id' => $case->id,
                'step' => $case->current_step,
                'task_key' => $task,
                'note' => $note,
                'done_by' => $userId,
                'done_at' => now(),
            ]);
        }
    }

    private function activeSurveyor($id): CadastralSurveyor
    {
        $surveyor = $id ? CadastralSurveyor::find((int) $id) : null;
        if (!$surveyor) {
            throw WorkflowGuardException::because('Choose the surveyor from the list.');
        }
        if (!$surveyor->is_active) {
            throw WorkflowGuardException::because("{$surveyor->full_name} is not active in the surveyors directory.");
        }

        return $surveyor;
    }

    private function assertInstalled(): void
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The Computation File tables have not been created yet.');
        }
    }

    private function nextNo(int $year): string
    {
        $prefix = "FJ-{$year}-";
        $last = CadastralFieldJacket::query()->where('field_jacket_no', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('id')->value('field_jacket_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
