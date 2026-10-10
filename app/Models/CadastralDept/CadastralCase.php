<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use App\Services\CadastralDept\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * One main file's journey through the Cadastral Department (config/cadastral_dept.php).
 */
class CadastralCase extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_cases';

    protected $guarded = ['id'];

    protected $casts = [
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
        'duplicate_flag' => 'boolean',
    ];

    public function movements()
    {
        return $this->hasMany(CadastralCaseMovement::class, 'cadastral_case_id')->orderBy('acted_at')->orderBy('id');
    }

    public function fmc()
    {
        return $this->hasOne(CadastralFmc::class, 'cadastral_case_id')->latestOfMany('id');
    }

    public function latestMovement()
    {
        return $this->hasOne(CadastralCaseMovement::class, 'cadastral_case_id')->latestOfMany('id');
    }

    /**
     * When the file came to its current step because someone refused it (DD Cad
     * back to D Cad, D Cad back to Registry, Compensation's "yes"): who, and why.
     * Needs latestMovement loaded or loadable.
     *
     * @return array{desk:string, reason:?string, at:mixed}|null
     */
    public function sentBack(): ?array
    {
        $m = $this->latestMovement;
        if (!$m || $m->to_step !== $this->current_step || !in_array($m->outcome, ['not_recommended', 'yes'], true)) {
            return null;
        }

        return ['desk' => (new WorkflowDefinition())->deskShort($m->from_desk), 'reason' => $m->remarks, 'at' => $m->acted_at];
    }

    public function tasks()
    {
        return $this->hasMany(CadastralCaseTask::class, 'cadastral_case_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', self::STATUS_IN_PROGRESS);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /** Task keys done at the current step. */
    public function doneTaskKeys(?string $step = null): array
    {
        $step ??= $this->current_step;

        return $this->tasks->where('step', $step)->pluck('task_key')->unique()->values()->all();
    }

    public function workflowLabel(): string
    {
        return (new WorkflowDefinition())->workflows()[$this->workflow]['label'] ?? $this->workflow;
    }

    /**
     * Items for the row action menu on listings: [label, icon, url, new tab?].
     * Only what the signed-in user may do (Cad - Records permissions).
     */
    public function rowActions(): array
    {
        $items = [['Open file', 'folder-open', route('cadastral-dept.cases.show', $this->id)]];
        $user = auth()->user();

        if (\App\Services\CadastralDept\FileMovementCards::installed() && ($fmc = $this->fmc)) {
            $items[] = ['File Movement Card ' . $fmc->fmc_no, 'id-card', route('cadastral-dept.cases.fmc.show', $this->id)];
            if ($user && $user->canDo('Cad - Records', 'print')) {
                $items[] = ['Print FMC', 'printer', route('cadastral-dept.cases.fmc.print', $this->id), true];
            }
        }
        if (\App\Services\CadastralDept\FieldJackets::installed()
            && ($jacket = \App\Models\CadastralDept\CadastralFieldJacket::query()->where('cadastral_case_id', $this->id)->first(['id', 'field_jacket_no']))) {
            $items[] = ['Field Jacket ' . $jacket->field_jacket_no, 'briefcase', route('cadastral-dept.field-jackets.show', $jacket->id)];
        }
        if ($bill = \App\Services\CadastralDept\PndBills::liveBill((string) $this->file_number)) {
            $items[] = ['P&D bill ' . $bill->bill_ref, 'receipt', route('cadastral-dept.cases.bill.show', $this->id)];
        }
        if ($this->isOpen()) {
            // The desk holding the file, where its workflow actions are.
            $def = new WorkflowDefinition();
            $desk = $def->actingDesk($this->workflow, $this->current_step);
            $items[] = ['Go to ' . $def->deskShort($desk) . ' desk', 'inbox', route('cadastral-dept.desks.show', $desk)];
        }

        return $items;
    }

    /** Short form of the desk holding the file (D Cad, DD Cad, ...). */
    public function deskShort(): string
    {
        return (new WorkflowDefinition())->desks()[$this->current_desk]['short'] ?? (string) $this->current_desk;
    }

    public function fileTypeLabel(): string
    {
        return (new WorkflowDefinition())->fileTypes()[$this->file_type]['label'] ?? (string) $this->file_type;
    }

    public function fileSourceLabel(): string
    {
        return (new WorkflowDefinition())->fileSources()[$this->file_source]['label'] ?? (string) $this->file_source;
    }

    /**
     * Registry may act only while the file is at Registry. Anywhere else the
     * Registry row is frozen: visible, but with no actions.
     */
    public function frozenAtRegistry(): bool
    {
        return $this->isOpen() && $this->current_desk !== 'registry';
    }

    public function stepLabel(): string
    {
        $def = new WorkflowDefinition();

        return $def->hasStep($this->workflow, $this->current_step)
            ? $def->step($this->workflow, $this->current_step)['label']
            : $this->current_step;
    }
}
