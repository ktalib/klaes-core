<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralFmc;
use App\Models\CadastralDept\CadastralFmcLog;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The File Movement Card (client brief, 2026-10-10).
 *
 * AD Cad Info commissions it once per case. Its header is filled from the
 * file's records (file_indexings, or the fileNumber row of a commissioned
 * file); only what the records leave blank, plus block and layout numbers, is
 * typed. It prints with a QR code. Units scan the code to log the file in and
 * out, inside or outside Cadastral, and every log-in must be closed by a
 * log-out before the next log-in. The card's log also shows the workflow's own
 * movements, so nobody re-types them.
 *
 * This does not write file_tracker.movement_log, the Ministry-wide tracker
 * (the old Cadastral Module made the same choice; see IndexCardMovements).
 */
class FileMovementCards
{
    /**
     * Seeing or printing a File Movement Card, its QR page included: only the roles in config
     * cadastral_dept.fmc_roles (Cad - D Cad, Cad - AD Cad, Cad - DD Cad; user, 2026-10-26).
     * Super Admin passes through canDo().
     */
    public static function canView(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }
        foreach ((array) config('cadastral_dept.fmc_roles', []) as $role) {
            if ($user->canDo($role, 'view')) {
                return true;
            }
        }

        return false;
    }

    private const CONN = 'sqlsrv';

    /** Header fields the records can supply, in display order (Plot No., District, LGA, Location). */
    public const RECORD_FIELDS = ['file_title', 'allottee_name', 'plot_no', 'district', 'lga', 'location'];

    public function __construct(
        private CadastralRegistryLookup $lookup,
        private WorkflowDefinition $def,
        private AuditService $audit
    ) {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_fmcs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Whether the case's current step is where the card is commissioned. */
    public function dueNow(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])['fmc_commissioned']);
    }

    /**
     * The header values the records hold for the case's file. Blank values are
     * left out, so the form leaves exactly those fields open.
     *
     * @return array<string, string>
     */
    public function backfill(CadastralCase $case): array
    {
        $v = [];

        if ($case->file_indexing_id && ($f = $this->lookup->sourceFile(null, (int) $case->file_indexing_id))) {
            $v = [
                'file_title' => $f['owner'],
                'allottee_name' => $f['owner'],
                'plot_no' => $f['plot'],
                'district' => $f['district'],
                'lga' => $f['lga'],
                'location' => $f['location'],
            ];
        } else {
            $row = DB::connection(self::CONN)->table('fileNumber')
                ->where(function ($w) use ($case) {
                    foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'gkn_fileno', 'dciv_fileno'] as $col) {
                        $w->orWhere($col, $case->file_number);
                    }
                })
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')->first();
            if ($row) {
                $v = [
                    'file_title' => $row->FileName,
                    'allottee_name' => $row->FileName,
                    'plot_no' => $row->plot_no,
                    'district' => $row->district,
                    'lga' => $row->lga,
                ];
            }
        }

        // The case's own copy fills anything the records no longer show.
        $v += array_filter([
            'file_title' => $case->file_title,
            'plot_no' => $case->plot_no ?? null,
            'district' => $case->district ?? null,
            'lga' => $case->lga ?? null,
            'location' => $case->location,
        ]);

        return array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== '');
    }

    /**
     * Commission the card and tick the step's "FMC commissioned" task, in one
     * transaction. Record values win over anything typed.
     */
    public function commission(CadastralCase $case, array $input, ?int $userId): CadastralFmc
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The File Movement Card tables have not been created yet.');
        }
        $backfill = $this->backfill($case);

        return DB::connection(self::CONN)->transaction(function () use ($case, $input, $userId, $backfill) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->dueNow($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the FMC is commissioned.");
            }
            if (CadastralFmc::query()->where('cadastral_case_id', $case->id)->exists()) {
                throw WorkflowGuardException::because("{$case->case_ref} already has its FMC.");
            }

            $values = [];
            foreach (self::RECORD_FIELDS as $f) {
                $values[$f] = $backfill[$f] ?? (Normalise::text($input[$f] ?? null) ?: null);
            }
            if (!$values['file_title']) {
                throw WorkflowGuardException::because('Enter the file title: the records do not hold one.');
            }

            $now = now();
            $fmc = CadastralFmc::create($values + [
                'cadastral_case_id' => $case->id,
                'fmc_no' => $this->nextNo($now->year),
                'qr_token' => bin2hex(random_bytes(20)),
                'file_number' => $case->file_number,
                'block_no' => Normalise::code($input['block_no'] ?? null),
                'layout_no' => Normalise::code($input['layout_no'] ?? null),
                'commissioned_at' => $now,
                'commissioned_by' => $userId,
            ]);

            $alreadyTicked = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', 'fmc_commissioned')->exists();
            if (!$alreadyTicked) {
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => 'fmc_commissioned',
                    'note' => $fmc->fmc_no,
                    'done_by' => $userId,
                    'done_at' => $now,
                ]);
            }

            $this->audit->logAction('Cadastral FMC Commissioned', 'CadastralFmc', $fmc->id, null,
                $fmc->only(['fmc_no', 'file_number', 'cadastral_case_id']), "{$fmc->fmc_no} commissioned for {$case->case_ref}");

            return $fmc;
        });
    }

    /**
     * Where the card says the file is: the unit it was last logged in to and not
     * yet logged out of, or null.
     *
     * @return array{unit_key:?string, unit_name:string, at:mixed}|null
     */
    public function loggedIn(CadastralFmc $fmc): ?array
    {
        $last = CadastralFmcLog::query()->where('cadastral_fmc_id', $fmc->id)->orderByDesc('logged_at')->orderByDesc('id')->first();

        return $last && $last->direction === 'in'
            ? ['unit_key' => $last->unit_key, 'unit_name' => $last->unit_name, 'at' => $last->logged_at]
            : null;
    }

    /**
     * Log the file in to a unit, or out of the unit it is in. A log-in needs a
     * unit and a reason; a log-out always closes the open log-in.
     */
    public function log(CadastralFmc $fmc, string $direction, ?string $unitKey, ?string $otherName, ?string $reason, ?int $userId): string
    {
        return DB::connection(self::CONN)->transaction(function () use ($fmc, $direction, $unitKey, $otherName, $reason, $userId) {
            $fmc = CadastralFmc::query()->lockForUpdate()->findOrFail($fmc->id);
            $open = $this->loggedIn($fmc);
            $reason = Normalise::text($reason) ?: null;

            if ($direction === 'out') {
                if (!$open) {
                    throw WorkflowGuardException::because('The file is not logged in to any unit.');
                }
                $key = $open['unit_key'];
                $name = $open['unit_name'];
            } elseif ($direction === 'in') {
                if ($open) {
                    throw WorkflowGuardException::because("The file is still logged in to {$open['unit_name']}. Log it out first.");
                }
                [$key, $name] = $this->unit($unitKey, $otherName);
                if (!$reason) {
                    throw WorkflowGuardException::because('Say why the file is going to ' . $name . '.');
                }
            } else {
                throw WorkflowGuardException::because('Choose log in or log out.');
            }

            CadastralFmcLog::create([
                'cadastral_fmc_id' => $fmc->id,
                'direction' => $direction,
                'unit_key' => $key,
                'unit_name' => $name,
                'reason' => $reason,
                'logged_at' => now(),
                'logged_by' => $userId,
            ]);

            return $direction === 'in' ? "{$fmc->file_number} logged in to {$name}." : "{$fmc->file_number} logged out of {$name}.";
        });
    }

    /**
     * Units a card can be logged in to: every desk in the workflow, plus Other.
     * A typed "Other" that matches a listed unit (any case, any spacing) is
     * taken as that unit, so the same unit never appears twice.
     *
     * @return array{0:?string, 1:string}  [unit key, unit name]
     */
    private function unit(?string $unitKey, ?string $otherName): array
    {
        $desks = $this->def->desks();

        if ($unitKey && $unitKey !== 'other') {
            if (!isset($desks[$unitKey])) {
                throw WorkflowGuardException::because('Choose a unit from the list.');
            }

            return [$unitKey, $desks[$unitKey]['label']];
        }

        $name = Normalise::name($otherName);
        if ($name === '') {
            throw WorkflowGuardException::because('Type the unit name for "Other".');
        }
        $flat = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        foreach ($desks as $key => $d) {
            if (in_array($flat($name), [$flat($d['label']), $flat($d['short'])], true)) {
                return [$key, $d['label']];
            }
        }

        return [null, $name];
    }

    /**
     * The card's log, oldest first: the workflow's movements and the QR
     * log-ins / log-outs, merged by time.
     *
     * @return array<int, array{at:mixed, unit:string, event:string, reason:?string, by:?string, source:string}>
     */
    public function timeline(CadastralFmc $fmc): array
    {
        $case = $fmc->case()->with('movements.actor', 'receiver')->first();
        $rows = [];

        foreach ($case->movements as $m) {
            $rows[] = [
                'at' => $m->acted_at,
                'unit' => $m->to_desk ? $this->def->deskShort($m->to_desk) : ($m->outcome === 'close' ? 'Closed' : '—'),
                'event' => match (true) {
                    $m->outcome === 'registered' => 'From Registry (registered)',
                    $m->outcome === 'close' => 'Closed at ' . $this->def->deskShort($m->from_desk),
                    // Head-of-unit approval (HeadApproval): within the unit.
                    $m->outcome === HeadApproval::TO_HEAD => 'Sent to the ' . ($this->def->desks()[$m->from_desk]['head'] ?? $this->def->deskShort($m->from_desk)) . ' for approval',
                    $m->outcome === CaseWorkflow::JUMP => 'Moved by admin from ' . $this->def->deskShort($m->from_desk),
                    $m->outcome === HeadApproval::RETURNED => 'Returned to staff by the ' . ($this->def->desks()[$m->from_desk]['head'] ?? $this->def->deskShort($m->from_desk)),
                    default => 'From ' . $this->def->deskShort($m->from_desk) . ($m->outcome !== 'forward' ? ' (' . str_replace('_', ' ', $m->outcome) . ')' : ''),
                },
                'reason' => $m->remarks,
                'by' => optional($m->actor)->name,
                'source' => 'workflow',
            ];
        }
        foreach ($fmc->logs()->with('logger')->get() as $l) {
            $rows[] = [
                'at' => $l->logged_at,
                'unit' => $l->unit_name,
                'event' => $l->direction === 'in' ? 'Logged in (QR)' : 'Logged out (QR)',
                'reason' => $l->reason,
                'by' => optional($l->logger)->name,
                'source' => 'qr',
            ];
        }

        usort($rows, fn ($a, $b) => $a['at'] <=> $b['at']);

        // The first row is always the registry the file came from (user, 2026-10-12).
        $source = $case->file_source === 'other' ? $case->fileSourceLabel() : $case->fileSourceLabel() . ' Registry';
        array_unshift($rows, [
            'at' => $case->received_at ?? $case->created_at,
            'unit' => $source,
            'event' => 'Received at Cadastral Registry',
            'reason' => $case->remarks,
            'by' => optional($case->receiver)->name,
            'source' => 'workflow',
        ]);

        return $rows;
    }

    /** FMC-2026-00001. Caller holds the transaction. */
    private function nextNo(int $year): string
    {
        $prefix = "FMC-{$year}-";
        $last = CadastralFmc::query()->where('fmc_no', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('id')->value('fmc_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
