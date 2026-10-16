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
        // 'yes': Compensation's "falls on acquisition" (now to DSG); 'rejected': D Cad returns it;
        // head_return: the unit head returns it to the staff (HeadApproval).
        if (!$m || $m->to_step !== $this->current_step || !in_array($m->outcome, ['not_recommended', 'yes', 'rejected', 'head_return'], true)) {
            return null;
        }
        $def = new WorkflowDefinition();
        $desk = $m->outcome === 'head_return' ? ($def->desks()[$m->from_desk]['head'] ?? $def->deskShort($m->from_desk)) : $def->deskShort($m->from_desk);

        return ['desk' => $desk, 'reason' => $m->remarks, 'at' => $m->acted_at];
    }

    /**
     * Compensation's answer and comment, shown to DSG and then D Cad (transcript, 2026-10-16),
     * with DSG's minute once DSG has sent it on. Null when the file is not at those steps.
     *
     * @return array{answer:string, comment:?string, dsg_minute:?string}|null
     */
    public function compensationAnswer(): ?array
    {
        if (!in_array($this->current_step, ['dsg_review', 'dc_buffer'], true)) {
            return null;
        }
        $comp = $this->movements()->where('from_step', 'compensation_check')->reorder()->orderByDesc('id')->first();
        if (!$comp) {
            return null;
        }
        $dsg = $this->current_step === 'dc_buffer'
            ? $this->movements()->where('from_step', 'dsg_review')->where('id', '>', $comp->id)->reorder()->orderByDesc('id')->first()
            : null;

        return [
            'answer' => $comp->outcome === 'yes' ? 'Yes: falls on Government acquisition' : 'No: does not fall on Government acquisition',
            'yes' => $comp->outcome === 'yes',
            'comment' => $comp->remarks,
            'dsg_minute' => $dsg?->remarks,
        ];
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
     * Only what the signed-in user may do (Cad - Records or a desk role).
     */
    public function rowActions(): array
    {
        $items = [['View', 'eye', route('cadastral-dept.cases.show', $this->id)]];
        $user = auth()->user();

        if (\App\Services\CadastralDept\FileMovementCards::installed() && ($fmc = $this->fmc)) {
            $items[] = ['File Movement Card ' . $fmc->fmc_no, 'id-card', route('cadastral-dept.cases.fmc.show', $this->id)];
            if (\App\Services\CadastralDept\DeskAccess::can($user, 'print')) {
                $items[] = ['Print FMC', 'printer', route('cadastral-dept.cases.fmc.print', $this->id), true];
            }
        }
        $items = array_merge($items, $this->verificationReportActions());
        if (\App\Services\CadastralDept\FieldJackets::installed()
            && ($jacket = \App\Models\CadastralDept\CadastralFieldJacket::query()->where('cadastral_case_id', $this->id)->first(['id', 'field_jacket_no']))) {
            $items[] = ['Field Jacket ' . $jacket->field_jacket_no, 'briefcase', route('cadastral-dept.field-jackets.show', $jacket->id)];
        }
        // No P&D bill on a Verification file for now (user, 2026-10-12); a bill found under the same
        // file number belongs to the file's Conversion / Direct case.
        if ($this->workflow !== 'verification' && ($bill = \App\Services\CadastralDept\PndBills::liveBill((string) $this->file_number))) {
            $items[] = ['P&D bill ' . $bill->bill_ref, 'receipt', route('cadastral-dept.cases.bill.show', $this->id)];
        }
        if ($js = $this->masterDeleteJs()) {
            $items[] = ['Master Delete', 'shield-alert', null, false, $js];
        }
        return $items;
    }

    /**
     * The Verification Report Sheet items of a row menu (user, 2026-10-15): generate /
     * edit while the file is open, view once closed, and print for Cad - Records or a desk role.
     *
     * @return list<array>
     */
    public function verificationReportActions(): array
    {
        if ($this->workflow !== 'verification' || ! \App\Services\CadastralDept\VerificationReports::installed()) {
            return [];
        }
        $saved = \App\Models\CadastralDept\CadastralVerificationReport::query()->where('cadastral_case_id', $this->id)->exists();
        $label = $this->isOpen() ? ($saved ? 'Edit Report Sheet' : 'Generate Report Sheet') : 'View Report Sheet';
        $items = [[$label, 'clipboard-check', route('cadastral-dept.cases.verification-report.show', $this->id)]];
        $user = auth()->user();
        if (\App\Services\CadastralDept\DeskAccess::can($user, 'print')) {
            $items[] = ['Print Report Sheet', 'printer', route('cadastral-dept.cases.verification-report.print', $this->id), true];
        }

        return $items;
    }

    /**
     * The click handler for Master Delete, or null when the user may not run one
     * (Supper Admin only; the endpoint checks the same). $after: where to go once done.
     */
    public function masterDeleteJs(?string $after = null): ?string
    {
        if ((auth()->user()->assign_role ?? null) !== 'Supper Admin') {
            return null;
        }

        // Raw JS: the views print it with {{ }} into onclick, which escapes it once.
        return 'cdMasterDelete(' . json_encode(route('cadastral-dept.cases.master-destroy', $this->id)) . ', '
            . json_encode($this->case_ref) . ', ' . json_encode((string) $this->file_number) . ', '
            . json_encode($after) . ')';
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
        $label = (new WorkflowDefinition())->fileSources()[$this->file_source]['label'] ?? (string) $this->file_source;

        return $this->file_source === 'other' && $this->file_source_other ? "{$label}: {$this->file_source_other}" : $label;
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
