<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralDocuments;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Documents a step's task asks for (change request 26-10-2026, handwritten list):
 *   final_plan_uploaded  "SG upload Final Survey Plan": at the SG step, the signed plan is uploaded.
 *   bill_signed          "AD upload Signed Fees, P&D": AD Cad Info signs the Fees P&D bill. Signed in
 *                        the system it needs no upload ("if it signs digitally it means it's in system,
 *                        there's no need to upload again"); signed on paper, the scanned copy is uploaded.
 *
 * Filed into the file's EDMS folder like the Planning Advice (CadastralDocuments: a scannings row
 * under Cadastral Registry, so Page Typing sees it), owned by the case. Doing it ticks the task.
 */
class StepDocuments
{
    public const FINAL_PLAN = 'final_plan_uploaded';
    public const BILL_SIGNED = 'bill_signed';

    /** task key => what it is, the document kind, and whether it can be signed in the system instead. */
    public const TASKS = [
        self::FINAL_PLAN => ['label' => 'Final Survey Plan', 'hint' => 'The Final Survey Plan signed by the Surveyor General.',
            'kind' => CadastralDocuments::KIND_FINAL_SURVEY_PLAN, 'sign' => false],
        self::BILL_SIGNED => ['label' => 'Signed Fees, Plan and Description', 'hint' => 'The Fees P&D bill signed by AD Cad Info.',
            'kind' => CadastralDocuments::KIND_SIGNED_PND_BILL, 'sign' => true],
    ];

    public function __construct(private CadastralDocuments $documents, private AuditService $audit)
    {
    }

    public static function installed(): bool
    {
        return CadastralDocuments::installed();
    }

    /** Whether the case's current step asks for this task and it is not done yet. */
    public function due(CadastralCase $case, string $task): bool
    {
        return isset(self::TASKS[$task]) && $case->isOpen()
            && isset(((new WorkflowDefinition())->step($case->workflow, $case->current_step)['tasks'] ?? [])[$task])
            && !in_array($task, $case->doneTaskKeys($case->current_step), true);
    }

    /** Every step document filed for the case, newest first, with its label. */
    public function documents(CadastralCase $case): Collection
    {
        if (!self::installed()) {
            return collect();
        }
        $labels = collect(self::TASKS)->mapWithKeys(fn ($t) => [$t['kind'] => $t['label']]);

        return $this->documents->for(CadastralDocuments::OWNER_DEPT_CASE, $case->id)
            ->filter(fn ($d) => $labels->has($d->kind))
            ->map(function ($d) use ($labels) {
                $d->kind_label = $labels[$d->kind];

                return $d;
            })->values();
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function upload(CadastralCase $case, string $task, array $files, ?string $comments, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The Cadastral documents table has not been created yet.');
        }
        $meta = self::TASKS[$task] ?? throw WorkflowGuardException::because('Unknown document.');
        if (!$files) {
            throw WorkflowGuardException::because("Choose the {$meta['label']} to upload.");
        }
        $this->assertDue($case, $task);
        $comments = Normalise::text($comments) ?: null;
        try {
            $indexing = $this->documents->requireIndexing($case->file_indexing_id ? (int) $case->file_indexing_id : null, $case->file_number, 'documents');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw WorkflowGuardException::because(collect($e->errors())->flatten()->first() ?: 'This file has no EDMS folder.');
        }
        $note = $meta['label'] . ' uploaded' . ($comments ? ': ' . $comments : '');
        if ($task === self::BILL_SIGNED) {
            $bill = PndBills::liveBill($case->file_number)
                ?? throw WorkflowGuardException::because('There is no issued Fees P&D bill to sign.');
            $note .= " (bill {$bill->bill_ref})";
        }

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($case, $task, $meta, $files, $indexing, $note, $userId) {
                foreach ($files as $file) {
                    $this->documents->file($file, $indexing, CadastralDocuments::OWNER_DEPT_CASE, (int) $case->id,
                        $meta['kind'], "{$case->case_ref}: {$note}");
                }
                $this->tick($case, $task, $note, $userId);
                $this->audit->logAction('Cadastral Step Document Uploaded', 'CadastralCase', $case->id, null,
                    ['task' => $task, 'files' => count($files)], "{$case->case_ref}: {$note}");

                return count($files) . " {$meta['label']} document(s) filed for {$case->file_number}.";
            });
        } catch (\Throwable $e) {
            // Disk writes do not roll back with the transaction.
            $this->documents->discardWritten();
            throw $e;
        }
    }

    /** AD Cad Info signs the Fees P&D bill in the system: no upload needed. */
    public function signInSystem(CadastralCase $case, ?int $userId): string
    {
        $this->assertDue($case, self::BILL_SIGNED);
        $bill = PndBills::liveBill($case->file_number);
        if (!$bill) {
            throw WorkflowGuardException::because('There is no issued Fees P&D bill to sign.');
        }
        $name = optional(User::find($userId))->name ?: 'User #' . $userId;
        $note = "Signed in the system by {$name} (bill {$bill->bill_ref})";

        return DB::connection('sqlsrv')->transaction(function () use ($case, $note, $userId, $bill) {
            $this->tick($case, self::BILL_SIGNED, $note, $userId);
            $this->audit->logAction('Cadastral P&D Bill Signed', 'CadastralBill', $bill->id, null,
                ['bill_ref' => $bill->bill_ref, 'case' => $case->case_ref], "{$case->case_ref}: {$note}");

            return "Bill {$bill->bill_ref} signed. No scanned copy is needed.";
        });
    }

    /** How the live bill was signed, for its print: the task row of the signing step, or null. */
    public static function signature(string $fileNumber, string $billRef): ?CadastralCaseTask
    {
        $case = CadastralCase::query()->where('file_number', $fileNumber)->orderByDesc('id')->first();
        if (!$case) {
            return null;
        }

        return CadastralCaseTask::query()->with('doer')->where('cadastral_case_id', $case->id)
            ->where('task_key', self::BILL_SIGNED)->where('note', 'like', '%' . $billRef . '%')
            ->orderByDesc('id')->first();
    }

    private function tick(CadastralCase $case, string $task, string $note, ?int $userId): void
    {
        CadastralCaseTask::create([
            'cadastral_case_id' => $case->id,
            'step' => $case->current_step,
            'task_key' => $task,
            'note' => mb_substr($note, 0, 1000),
            'done_by' => $userId,
            'done_at' => now(),
        ]);
    }

    private function assertDue(CadastralCase $case, string $task): void
    {
        if (!$this->due($case, $task)) {
            throw WorkflowGuardException::because("{$case->case_ref} is not at the step for the " . self::TASKS[$task]['label'] . ', or it is already done.');
        }
    }
}
