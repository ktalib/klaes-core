<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Services\Cadastral\CadastralDocuments;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KAMMA / Physical Planning's Planning Advice (transcript, 2026-10-16): apart from the comments,
 * what they find on the field, on the JSI (Conversion, ST, SLTR or Master JSI), is uploaded when
 * the file comes back, so the desks after can view or download it.
 *
 * The documents are filed into the file's EDMS folder by CadastralDocuments (a scannings row
 * under Cadastral Registry, so Page Typing sees them), owned by the case. Uploading ticks the
 * Planning Advice step's task, with the JSI and the comments as its note.
 */
class PlanningAdvice
{
    public const TASK = 'planning_advice_uploaded';

    public const JSI_TYPES = [
        'conversion' => 'Conversion JSI',
        'st' => 'ST JSI',
        'sltr' => 'SLTR JSI',
        'master' => 'Master JSI',
    ];

    public function __construct(private CadastralDocuments $documents)
    {
    }

    public static function installed(): bool
    {
        return CadastralDocuments::installed();
    }

    /** Whether the case is at a step that asks for the Planning Advice and it is not uploaded yet. */
    public function due(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(((new WorkflowDefinition())->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK])
            && !in_array(self::TASK, $case->doneTaskKeys(), true);
    }

    /** The Planning Advice documents filed for the case, newest first. */
    public function documents(CadastralCase $case): Collection
    {
        return $this->documents->for(CadastralDocuments::OWNER_DEPT_CASE, $case->id, CadastralDocuments::KIND_PLANNING_ADVICE);
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function upload(CadastralCase $case, array $files, string $jsi, ?string $comments, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The Cadastral documents table has not been created yet.');
        }
        if (!$files) {
            throw WorkflowGuardException::because('Choose the Planning Advice document(s) to upload.');
        }
        if (!isset(self::JSI_TYPES[$jsi])) {
            throw WorkflowGuardException::because('Choose the JSI the Planning Advice was done on.');
        }
        if (!$this->due($case)) {
            throw WorkflowGuardException::because("{$case->case_ref} is not at the Planning Advice step, or its Planning Advice is already uploaded.");
        }
        $comments = Normalise::text($comments) ?: null;
        try {
            $indexing = $this->documents->requireIndexing($case->file_indexing_id ? (int) $case->file_indexing_id : null, $case->file_number, 'documents');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw WorkflowGuardException::because(collect($e->errors())->flatten()->first() ?: 'This file has no EDMS folder.');
        }
        $note = 'Planning Advice (' . self::JSI_TYPES[$jsi] . ')' . ($comments ? ': ' . $comments : '');

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($case, $files, $indexing, $note, $userId) {
                foreach ($files as $file) {
                    $this->documents->file($file, $indexing, CadastralDocuments::OWNER_DEPT_CASE, (int) $case->id,
                        CadastralDocuments::KIND_PLANNING_ADVICE, "{$case->case_ref}: {$note}");
                }
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => self::TASK,
                    'note' => mb_substr($note, 0, 1000),
                    'done_by' => $userId,
                    'done_at' => now(),
                ]);

                return count($files) . " Planning Advice document(s) filed for {$case->file_number}.";
            });
        } catch (\Throwable $e) {
            // Disk writes do not roll back with the transaction.
            $this->documents->discardWritten();
            throw $e;
        }
    }
}
