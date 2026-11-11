<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralFmc;
use App\Models\CadastralDept\CadastralFmcLog;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use App\Http\Controllers\Survey\LookupController;
use Illuminate\Support\Facades\Cache;
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
     * Why the card cannot be commissioned yet, or null: the tasks listed before
     * "FMC commissioned" in its step come first. On Direct Allocation that is 1st
     * charting, then the layout check (recording 365, 2026-11-09).
     */
    public function blocker(CadastralCase $case): ?string
    {
        $tasks = (array) ($this->def->step($case->workflow, $case->current_step)['tasks'] ?? []);
        if (!isset($tasks['fmc_commissioned'])) {
            return null;
        }
        $before = array_slice($tasks, 0, array_search('fmc_commissioned', array_keys($tasks), true), true);
        $open = array_diff_key($before, array_flip($case->doneTaskKeys()));

        return $open ? 'Finish first: ' . implode('; ', $open) . '.' : null;
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
                // Layout No. is the TP No. (2026-11-09).
                'layout_no' => DB::connection(self::CONN)->table('file_indexings')->where('id', $case->file_indexing_id)->value('tp_no'),
            ];
        } else {
            $ids = $this->fileNumberIds((string) $case->file_number);
            $row = $ids ? DB::connection(self::CONN)->table('fileNumber')->whereIn('id', $ids)
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')->first() : null;
            if ($row) {
                $v = [
                    'file_title' => $row->FileName,
                    'allottee_name' => $row->FileName,
                    'plot_no' => $row->plot_no,
                    'district' => $row->district,
                    'lga' => $row->lga,
                    'layout_no' => $row->tp_no,
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

        $v = array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== '');
        if (isset($v['location'])) {
            $v['location'] = mb_strtoupper($v['location']);   // Location in capitals (2026-11-09)
        }

        return $v;
    }

    /** The case file type whose card carries the OP No. (config cadastral_dept.file_types). */
    public const OP_FILE_TYPE = 'op';

    /**
     * The card's fixed details (Survey Update call, 2026-11-10): OP No., File Type and File No.
     * are shown on the card and never edited from it, nor is the File Title (user, same day);
     * Plot No., District, LGA and Location are (EDIT_FIELDS). The OP No. is only for an OP
     * File (user: "only when the file is an OP"), read from the Deeds OPs filed under the
     * file number.
     *
     * @return array{is_op: bool, op_no: ?string, file_type: ?string, file_number: string}
     */
    public function fixedDetails(?CadastralCase $case, string $fileNumber): array
    {
        $isOp = $case && $case->file_type === self::OP_FILE_TYPE;
        if (!$isOp) {
            return ['is_op' => false, 'op_no' => null, 'file_type' => $case ? ($case->fileTypeLabel() ?: null) : null, 'file_number' => $fileNumber];
        }
        try {
            $serials = collect(app(\App\Services\Survey\OpLookup::class)->forFileNumber($fileNumber))
                ->pluck('serial')->filter()->unique()->values()->all();
        } catch (\Throwable $e) {
            report($e);
            $serials = [];
        }

        return [
            'is_op' => true,
            'op_no' => $serials ? implode(', ', $serials) : null,
            'file_type' => $case ? ($case->fileTypeLabel() ?: null) : null,
            'file_number' => $fileNumber,
        ];
    }

    /** The "Other" choice on the card's dropdowns. */
    public const OTHER = '__other__';

    /**
     * Allottee Name is hidden for now (user, 2026-11-09): off the commission and edit
     * forms, the card and its print. Commissioning still stores it from the records.
     */
    public const SHOW_ALLOTTEE = false;

    /**
     * Dropdown lists for the card (2026-11-09): District and LGA from the shared lookups
     * (Survey LookupController), Layout No. from tp_lookups, the TP No. register.
     *
     * @return array{districts: string[], lgas: string[], layouts: string[]}
     */
    public function options(): array
    {
        return [
            'districts' => LookupController::options('districts'),
            'lgas' => LookupController::options('lgas'),
            'layouts' => Cache::remember('cadastral_dept.fmc_layouts', 600, fn () => DB::connection(self::CONN)->table('tp_lookups')
                ->whereNotNull('tp_no')->orderBy('tp_no')->pluck('tp_no')
                ->map(fn ($t) => trim((string) $t))->filter()->unique(fn ($t) => mb_strtoupper($t))->values()->all()),
        ];
    }

    /**
     * The form's values, cleaned (development standard 3) and resolved against the
     * dropdown lists. District and Layout No. take "Other" plus a typed value; a typed
     * value already on the list is taken as that entry. LGA must come from the list.
     * Location is kept in capitals.
     *
     * @return array<string, ?string>  keyed by EDIT_FIELDS
     */
    public function cleanInput(array $input): array
    {
        $opts = $this->options();
        $inList = function (array $list, string $v): ?string {
            foreach ($list as $o) {
                if (mb_strtoupper($o) === mb_strtoupper($v)) {
                    return $o;
                }
            }

            return null;
        };
        $choice = function (string $field, string $label, callable $list, ?callable $other) use ($input): ?string {
            $v = Normalise::text($input[$field] ?? null);
            if ($v === '') {
                return null;
            }
            if ($v === self::OTHER) {
                if (!$other) {
                    throw WorkflowGuardException::because("Choose the {$label} from the list.");
                }
                $typed = Normalise::text($input[$field . '_other'] ?? null);
                if ($typed === '') {
                    throw WorkflowGuardException::because("Type the {$label} for \"Other\".");
                }

                return $list($typed) ?? $other($typed);
            }

            return $list($v) ?? throw WorkflowGuardException::because("Choose the {$label} from the list.");
        };
        $location = Normalise::text($input['location'] ?? null);

        return [
            'file_title' => Normalise::text($input['file_title'] ?? null) ?: null,
            'allottee_name' => Normalise::text($input['allottee_name'] ?? null) ?: null,
            'block_no' => Normalise::code($input['block_no'] ?? null),
            'layout_no' => $choice('layout_no', 'Layout No.', fn ($v) => $inList($opts['layouts'], $v), fn ($v) => Normalise::code($v)),
            'plot_no' => Normalise::text($input['plot_no'] ?? null) ?: null,
            'district' => $choice('district', 'District', fn ($v) => LookupController::canonicalOption('districts', $v), fn ($v) => mb_strtoupper($v)),
            'lga' => $choice('lga', 'LGA', fn ($v) => LookupController::canonicalOption('lgas', $v), null),
            'location' => $location === '' ? null : mb_strtoupper($location),
        ];
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
        $typed = $this->cleanInput($input);

        return DB::connection(self::CONN)->transaction(function () use ($case, $typed, $userId, $backfill) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!$this->dueNow($case)) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the FMC is commissioned.");
            }
            if ($why = $this->blocker($case)) {
                throw WorkflowGuardException::because($why);
            }
            if (CadastralFmc::query()->where('cadastral_case_id', $case->id)->exists()) {
                throw WorkflowGuardException::because("{$case->case_ref} already has its FMC.");
            }

            $values = [];
            foreach (self::RECORD_FIELDS as $f) {
                $values[$f] = $backfill[$f] ?? $typed[$f];
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
                'block_no' => $typed['block_no'],
                'layout_no' => $typed['layout_no'],   // the dropdown, prefilled with the records' TP No.
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

    /** Header fields the edit modal changes, in display order. */
    public const EDIT_FIELDS = ['file_title', 'allottee_name', 'block_no', 'layout_no', 'plot_no', 'district', 'lga', 'location'];

    /** Card field => the same detail on the file's records (recording 365: fix the source too). */
    private const SOURCE_COLUMNS = [
        'cadastral_cases' => ['file_title' => 'file_title', 'plot_no' => 'plot_no', 'district' => 'district', 'lga' => 'lga', 'location' => 'location'],
        'file_indexings' => ['file_title' => 'file_title', 'plot_no' => 'plot_number', 'district' => 'district', 'lga' => 'lga', 'location' => 'location'],
        'fileNumber' => ['file_title' => 'FileName', 'plot_no' => 'plot_no', 'district' => 'district', 'lga' => 'lga', 'location' => 'location'],
    ];

    /** fileNumber column widths narrower than the card's: a longer value is left off that table. */
    private const FILENUMBER_WIDTH = ['FileName' => 255, 'plot_no' => 50, 'district' => 50, 'lga' => 100, 'location' => 255];

    /**
     * Correct the card's header after commissioning (recording 365, 2026-11-09:
     * the commissioning-sheet details are often wrong). The changed record fields
     * are written back to the case, the file's file_indexings row and its
     * fileNumber rows, as the Indexing edit does. A cleared field is cleared on
     * the card only; the records are never blanked. Before and after go to the
     * audit log.
     *
     * @return array{changed: string[], sources: array<string, int>}
     */
    /** The card's Location: District, LGA, in capitals; never the plot. Null when both are blank. */
    public static function locationOf(?string $district, ?string $lga): ?string
    {
        $parts = array_values(array_filter([trim((string) $district), trim((string) $lga)], fn ($p) => $p !== ''));

        return $parts ? mb_strtoupper(implode(', ', $parts)) : null;
    }

    public function edit(CadastralFmc $fmc, array $input, ?\App\Models\User $user): array
    {
        // The File Title is not edited from the card (user, 2026-11-10): it keeps the card's value.
        unset($input['file_title']);
        $values = $this->cleanInput($input);
        // A field the form does not show (File Title; Allottee Name, for now) keeps the card's value.
        foreach (self::EDIT_FIELDS as $f) {
            if (!array_key_exists($f, $input)) {
                $values[$f] = trim((string) $fmc->{$f}) ?: null;
            }
        }
        // Location is made from the District and LGA, never typed (user, 2026-11-10).
        $values['location'] = self::locationOf($values['district'], $values['lga']);
        // Found before the transaction: the lookup scans fileNumber (see fileNumberIds).
        $fnIds = $this->fileNumberIds((string) $fmc->file_number);

        return DB::connection(self::CONN)->transaction(function () use ($fmc, $values, $user, $fnIds) {
            $fmc = CadastralFmc::query()->lockForUpdate()->findOrFail($fmc->id);
            $case = CadastralCase::query()->findOrFail($fmc->cadastral_case_id);

            $changed = array_keys(array_filter($values, fn ($v, $f) => (string) $v !== trim((string) $fmc->{$f}), ARRAY_FILTER_USE_BOTH));
            if (!$changed) {
                throw WorkflowGuardException::because('Nothing was changed.');
            }

            $before = ['fmc' => $fmc->only($changed)];
            $after = ['fmc' => array_intersect_key($values, array_flip($changed))];
            $fmc->update($after['fmc']);

            // Only non-blank record fields go back to the records.
            $toSource = array_filter(array_intersect_key($after['fmc'], self::SOURCE_COLUMNS['cadastral_cases']), fn ($v) => $v !== null);
            $sources = [];

            if ($set = $this->columns('cadastral_cases', $toSource)) {
                $before['case'] = $case->only(array_keys($set));
                $case->forceFill($set)->save();
                $after['case'] = $set;
                $sources['cadastral_cases'] = 1;
            }

            if ($case->file_indexing_id && ($set = $this->columns('file_indexings', $toSource))) {
                $q = DB::connection(self::CONN)->table('file_indexings')->where('id', $case->file_indexing_id);
                $row = (clone $q)->first(array_keys($set));
                if ($row) {
                    $before['file_indexings'] = (array) $row;
                    $q->update($set + ['updated_at' => now(), 'updated_by' => mb_substr((string) optional($user)->name, 0, 50) ?: null]);
                    $after['file_indexings'] = $set;
                    $sources['file_indexings'] = 1;
                }
            }

            $set = array_filter($this->columns('fileNumber', $toSource), fn ($v, $c) => mb_strlen($v) <= self::FILENUMBER_WIDTH[$c], ARRAY_FILTER_USE_BOTH);
            if ($set && $fnIds) {
                $rows = DB::connection(self::CONN)->table('fileNumber')->whereIn('id', $fnIds)
                    ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                    ->get(array_merge(['id'], array_keys($set)));
                if ($rows->isNotEmpty()) {
                    $before['fileNumber'] = $rows->map(fn ($r) => (array) $r)->all();
                    $sources['fileNumber'] = DB::connection(self::CONN)->table('fileNumber')->whereIn('id', $rows->pluck('id'))
                        ->update($set + ['updated_at' => now(), 'updated_by' => (string) optional($user)->id]);
                    $after['fileNumber'] = $set;
                }
            }

            $this->audit->logAction('Cadastral FMC Edited', 'CadastralFmc', $fmc->id, $before, $after,
                "{$fmc->fmc_no} ({$fmc->file_number}) corrected: " . implode(', ', $changed));

            return ['changed' => $changed, 'sources' => $sources];
        });
    }

    /**
     * fileNumber ids for a file number, in any of its number columns. Read WITH (NOLOCK):
     * the six-column match scans the table, and a scan waits on any row another session has
     * locked, which would hold the edit's transaction open. The rows are then read and
     * updated by id, so only these rows' own locks matter.
     *
     * @return int[]
     */
    private function fileNumberIds(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return [];
        }
        $cols = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'gkn_fileno', 'dciv_fileno'];
        $rows = DB::connection(self::CONN)->select(
            'SELECT id FROM fileNumber WITH (NOLOCK) WHERE ' . implode(' OR ', array_map(fn ($c) => "$c = ?", $cols)),
            array_fill(0, count($cols), $fileNumber)
        );

        return array_map(fn ($r) => (int) $r->id, $rows);
    }

    /** The card's values renamed to one table's columns. */
    private function columns(string $table, array $values): array
    {
        $out = [];
        foreach (self::SOURCE_COLUMNS[$table] as $field => $col) {
            if (array_key_exists($field, $values)) {
                $out[$col] = $values[$field];
            }
        }

        return $out;
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
