<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Services\AuditService;
use App\Services\Cadastral\CorrespondenceFiles;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;

/**
 * "Create Correspondence File" at AD Cad Report (client brief: same function as
 * the old matching screen, renamed). The correspondence file can only be made
 * against a land file, so it uses the file the case already holds.
 *
 * The writes are the old Cadastral Module's CorrespondenceFiles::ensureFor(),
 * unchanged, so a correspondence file made here is identical to one made on the
 * Create Correspondence File screen or at old-module registration: it matches
 * an existing one first, and only creates when there is none. ensureFor() takes
 * an intake receipt; an unsaved one carrying the case's file is passed and
 * thrown away (nothing is written to cadastral_file_receipts).
 */
class CorrespondenceStep
{
    public const TASK = 'correspondence_created';

    public function __construct(
        private CorrespondenceFiles $files,
        private WorkflowDefinition $def,
        private AuditService $audit
    ) {
    }

    public function dueNow(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK]);
    }

    /** @return string the message for the officer */
    public function run(CadastralCase $case, ?int $userId): string
    {
        return DB::connection('sqlsrv')->transaction(function () use ($case, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->dueNow($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the correspondence file is created.");
            }
            $done = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', self::TASK)->exists();
            if ($done) {
                return 'The correspondence file was already created for this step.';
            }

            // Same lock order as old-module registration: the index row first.
            if ($case->file_indexing_id) {
                DB::connection('sqlsrv')->table('file_indexings')->where('id', $case->file_indexing_id)->lockForUpdate()->first();
            }

            $carrier = new CadastralFileReceipt();
            $carrier->forceFill([
                'file_number' => $case->file_number,
                'file_indexing_id' => $case->file_indexing_id,
                'file_title' => $case->file_title,
                'prop_plot' => $case->plot_no,
                'prop_lga' => $case->lga,
                'property_location' => $case->location,
            ]);
            $result = $this->files->ensureFor($carrier);

            $shadowRef = $result['shadow']?->ref_number;
            $case->update(array_filter([
                'correspondence_fileno' => $result['fileno'],
                'shadow_fileno' => CorrespondenceColumns::available() ? $shadowRef : null,
            ], fn ($v) => $v !== null));

            CadastralCaseTask::create([
                'cadastral_case_id' => $case->id,
                'step' => $case->current_step,
                'task_key' => self::TASK,
                'note' => trim(($result['action'] === 'created' ? 'Created ' : 'Matched ') . ($shadowRef ?: '') . ' ' . ($result['fileno'] ?: '')),
                'done_by' => $userId,
                'done_at' => now(),
            ]);

            $this->audit->logAction(
                $result['action'] === 'created' ? 'Cadastral Correspondence Created' : 'Cadastral Correspondence Matched',
                'CadastralCase', $case->id, null,
                ['action' => $result['action'], 'shadow_ref' => $shadowRef, 'corresponding_fileno' => $result['fileno']],
                "{$case->case_ref}: correspondence file {$result['action']}"
            );

            return $result['action'] === 'created'
                ? "Correspondence file {$shadowRef} created for {$case->file_number}."
                : "{$case->file_number} already had its correspondence file" . ($shadowRef ? " ({$shadowRef})" : " ({$result['fileno']})") . '; matched to it.';
        });
    }
}
