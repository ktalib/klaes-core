<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralCorrespondenceCustody as Custody;
use App\Services\AuditService;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registry's Attach / Detach correspondence file (client brief, 2026-10-10).
 *
 * The correspondence file travels with the main file inside Cadastral and is
 * detached whenever the main file leaves (to Land, to Deeds, back to a
 * requester), then attached again when it comes back. Each action is a row in
 * cadastral_correspondence_custody, and it ticks the step's
 * correspondence_detached / correspondence_attached task, so neither is ticked
 * by hand once the table exists.
 */
class CorrespondenceCustody
{
    private const CONN = 'sqlsrv';

    public const TASKS = [
        Custody::DETACH => 'correspondence_detached',
        Custody::ATTACH => 'correspondence_attached',
    ];

    public function __construct(private WorkflowDefinition $def, private AuditService $audit)
    {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_correspondence_custody');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** detach | attach: what the current step asks for, or null. */
    public function due(CadastralCase $case): ?string
    {
        if (!$case->isOpen()) {
            return null;
        }
        $tasks = (array) ($this->def->step($case->workflow, $case->current_step)['tasks'] ?? []);
        foreach (self::TASKS as $action => $task) {
            if (isset($tasks[$task])) {
                return $action;
            }
        }

        return null;
    }

    public function history(CadastralCase $case)
    {
        return self::installed()
            ? Custody::query()->with('doer')->where('cadastral_case_id', $case->id)->orderBy('id')->get()
            : collect();
    }

    /** True when the correspondence file is detached now (the last row is a detach). */
    public function isDetached(CadastralCase $case): bool
    {
        return self::installed()
            && Custody::query()->where('cadastral_case_id', $case->id)->orderByDesc('id')->value('action') === Custody::DETACH;
    }

    public function record(CadastralCase $case, string $action, ?string $note, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The correspondence custody table has not been created yet.');
        }
        if (!isset(self::TASKS[$action])) {
            throw WorkflowGuardException::because('Unknown action.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $action, $note, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($this->due($case) !== $action) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at a step where the correspondence file is " . ($action === Custody::DETACH ? 'detached' : 'attached') . '.');
            }
            if (!trim((string) $case->correspondence_fileno)) {
                throw WorkflowGuardException::because("{$case->case_ref} has no correspondence file yet (AD Cad Report creates it).");
            }
            $detached = $this->isDetached($case);
            if ($action === Custody::DETACH && $detached) {
                throw WorkflowGuardException::because('The correspondence file is already detached.');
            }
            if ($action === Custody::ATTACH && !$detached) {
                throw WorkflowGuardException::because('The correspondence file is not detached, so there is nothing to attach.');
            }

            $row = Custody::create([
                'cadastral_case_id' => $case->id,
                'action' => $action,
                'step' => $case->current_step,
                'correspondence_fileno' => $case->correspondence_fileno,
                'shadow_fileno' => $case->shadow_fileno,
                'note' => Normalise::text($note) ?: null,
                'done_by' => $userId,
                'done_at' => now(),
            ]);

            $task = self::TASKS[$action];
            $ticked = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', $task)->exists();
            if (!$ticked) {
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => $task,
                    'note' => $row->note,
                    'done_by' => $userId,
                    'done_at' => $row->done_at,
                ]);
            }

            $verb = $action === Custody::DETACH ? 'detached' : 'attached';
            // The correspondence file is known by its own (CSF) number; the main file number is the fallback.
            $name = $case->shadow_fileno ?: $case->correspondence_fileno;
            $this->audit->logAction('Cadastral Correspondence ' . ucfirst($verb), 'CadastralCase', $case->id, null,
                ['action' => $action, 'correspondence_fileno' => $case->correspondence_fileno, 'step' => $case->current_step],
                "Correspondence file {$name} {$verb} for {$case->case_ref}");

            return "Correspondence file {$name} {$verb}.";
        });
    }
}
