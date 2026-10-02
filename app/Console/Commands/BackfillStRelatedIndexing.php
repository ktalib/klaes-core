<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Backfills the two-row indexing footprint for every commissioned ST primary
 * (mother_applications). Each scheme should surface as TWO separate rows in
 * BOTH file_indexings and fileNumber:
 *
 *   NEW row  -> the new ST primary number (np_fileno). tracking_id = st_file_numbers.tra
 *   OLD row  -> the pre-existing selected file number (fileno / applied_file_number),
 *               indexed standalone (no ST link). tracking_id = grouping-table tracking_id
 *
 * NEW tracking id is only filled when MISSING (never overwrites a real value).
 * OLD tracking id is CORRECTED to the grouping id (overwrites, per spec — the old
 * file's authority is the grouping registry, not the ST tra).
 *
 * Dry-run by default. Pass --commit to write (inside a transaction).
 */
class BackfillStRelatedIndexing extends Command
{
    protected $signature = 'st:backfill-related-indexing
                            {--connection=sqlsrv : Database connection to run against}
                            {--mother= : Restrict to a single mother_applications id}
                            {--commit : Actually write (default is a dry run that writes nothing)}';

    protected $description = 'Ensure every ST primary has a NEW-number row (tra from st_file_numbers) and an OLD-number standalone row (tracking_id from the grouping table) in file_indexings and fileNumber.';

    /** grouping table => [candidate file-number columns], searched in order */
    private array $groupMap = [
        'grouping'          => ['awaiting_fileno', 'mls_fileno'],
        'kn_grouping'       => ['kn_awaiting_fileno', 'kn_fileno'],
        'gkn_grouping'      => ['gkn_awaiting_fileno', 'gkn_fileno'],
        'kangis_grouping'   => ['kangis_awaiting_fileno', 'kangis_fileno'],
        'lpkn_grouping'     => ['lpkn_awaiting_fileno', 'lpkn_fileno'],
        'sit_grouping'      => ['sit_awaiting_fileno', 'sit_fileno'],
        'miscs_kn_grouping' => ['miscs_kn_awaiting_fileno', 'miscs_kn_fileno'],
    ];

    private $conn;
    private bool $commit = false;
    /** @var array<int,array> planned actions for the summary */
    private array $plan = [];
    /** @var array<int,\Closure> deferred writes, applied together in one transaction */
    private array $apply = [];

    public function handle(): int
    {
        $this->conn   = DB::connection($this->option('connection'));
        $this->commit = (bool) $this->option('commit');

        foreach (['mother_applications', 'st_file_numbers', 'file_indexings', 'fileNumber'] as $t) {
            if (!Schema::connection($this->option('connection'))->hasTable($t)) {
                $this->error("Required table missing: {$t}");
                return self::FAILURE;
            }
        }

        $this->line('Connection : ' . $this->conn->getName());
        $this->line('Mode       : ' . ($this->commit ? 'COMMIT (writing)' : 'DRY RUN (no writes)'));
        $this->newLine();

        $mothersQ = $this->conn->table('mother_applications')
            ->orderBy('id')
            ->select('id', 'np_fileno', 'fileno', 'applied_file_number');
        if ($this->option('mother')) {
            $mothersQ->where('id', (int) $this->option('mother'));
        }
        $mothers = $mothersQ->get();

        if ($mothers->isEmpty()) {
            $this->warn('No mother_applications rows matched.');
            return self::SUCCESS;
        }

        $skipped = [];

        foreach ($mothers as $m) {
            $new = trim((string) $m->np_fileno);
            $old = trim((string) ($m->fileno ?: $m->applied_file_number));

            if ($new === '') {
                $skipped[] = "#{$m->id}: no np_fileno";
                continue;
            }

            $stRow  = $this->conn->table('st_file_numbers')
                ->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [strtoupper($new)])
                ->where('file_no_type', 'PRIMARY')
                ->orderBy('id')
                ->first();
            $newTrk = $stRow->tra ?? null;

            if (empty($newTrk)) {
                $skipped[] = "#{$m->id} ({$new}): no tra in st_file_numbers";
                continue;
            }

            [$grpTable, $oldTrk] = $old !== '' ? $this->findGroupingTrk($old) : [null, null];

            $fileName = $this->displayName($stRow);
            $landUse  = trim((string) ($stRow->land_use ?? '')) ?: null;
            $createdBy = (string) ($stRow->created_by ?? 0);
            $commAt   = $stRow->date_commissioned ?? ($stRow->created_at ?? now());

            $ctx = compact('m', 'new', 'old', 'newTrk', 'grpTable', 'oldTrk', 'fileName', 'landUse', 'createdBy', 'commAt');

            $this->handleFileIndexings($ctx);
            $this->handleFileNumber($ctx);
        }

        $this->renderPlan();

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn('Skipped ' . \count($skipped) . ' mother(s):');
            foreach ($skipped as $s) {
                $this->line('  - ' . $s);
            }
        }

        if (!$this->commit) {
            $this->newLine();
            $this->warn('DRY RUN — nothing written. Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        // Apply every planned write atomically — all or nothing.
        try {
            $this->conn->transaction(function () {
                foreach ($this->apply as $fn) {
                    $fn();
                }
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('COMMIT FAILED — transaction rolled back, nothing written.');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('COMMIT complete. ' . \count($this->apply) . ' action(s) applied in one transaction.');

        return self::SUCCESS;
    }

    // ---- file_indexings ------------------------------------------------------

    private function handleFileIndexings(array $ctx): void
    {
        extract($ctx);

        // NEW row is keyed on file_number = the ST number itself (file_number is
        // UNIQUE, so the new ST file gets its own dedicated row — same as the
        // commissioning controller). It never collides with the old-number row.
        $newRow = $this->conn->table('file_indexings')
            ->where($this->notDeleted())
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($new)])
            ->orderByDesc('id')
            ->first();

        if (!$newRow) {
            $this->addPlan('file_indexings', 'INSERT', 'NEW', $m->id, $new, $newTrk, function () use ($new, $old, $fileName, $landUse, $createdBy, $newTrk, $commAt, $m) {
                $this->conn->table('file_indexings')->insert($this->fiRow([
                    'file_number'         => $new,
                    'st_fillno'           => $new,
                    'related_fileno'      => $old ?: null,
                    'tracking_id'         => $newTrk,
                    'file_type'           => 'PRIMARY',
                    'st_application_type' => 'primary',
                ], $fileName, $landUse, $createdBy, $commAt, $m->id));
            });
        } elseif (empty($newRow->tracking_id)) {
            // Fill NEW tracking id only when missing.
            $this->addPlan('file_indexings', 'UPDATE trk', 'NEW', $m->id, $new, $newTrk, function () use ($newRow, $newTrk) {
                $this->conn->table('file_indexings')->where('id', $newRow->id)
                    ->update(['tracking_id' => $newTrk, 'updated_at' => now()]);
            });
        }

        if ($old === '') {
            return;
        }

        // OLD row is keyed on file_number = the old number. Because that column is
        // UNIQUE, the old number can live in ONLY ONE row — so if a (possibly
        // combined) row already exists we correct ITS tracking_id to the grouping
        // id rather than inserting a duplicate (which would hit the unique index).
        $oldRow = $this->conn->table('file_indexings')
            ->where($this->notDeleted())
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($old)])
            ->orderByDesc('id')
            ->first();

        if (!$oldRow) {
            $note = $oldTrk ? null : 'no grouping trk';
            $this->addPlan('file_indexings', 'INSERT', 'OLD', $m->id, $old, $oldTrk ?: '(null)', function () use ($old, $fileName, $landUse, $createdBy, $oldTrk, $commAt, $m) {
                $this->conn->table('file_indexings')->insert($this->fiRow([
                    'file_number'   => $old,
                    'st_fillno'     => null,
                    'tracking_id'   => $oldTrk ?: null,
                    'file_type'     => null,
                ], $fileName, $landUse, $createdBy, $commAt, $m->id));
            }, $note);
        } elseif ($oldTrk && $oldRow->tracking_id !== $oldTrk) {
            // Correct the existing old-number row to the grouping id.
            $this->addPlan('file_indexings', 'UPDATE trk', 'OLD', $m->id, $old, ($oldRow->tracking_id ?? 'NULL') . ' -> ' . $oldTrk, function () use ($oldRow, $oldTrk) {
                $this->conn->table('file_indexings')->where('id', $oldRow->id)
                    ->update(['tracking_id' => $oldTrk, 'updated_at' => now()]);
            });
        }
    }

    // ---- fileNumber ----------------------------------------------------------

    private function handleFileNumber(array $ctx): void
    {
        extract($ctx);

        // NEW representation: a row whose st_file_no = the ST number.
        $newRow = $this->conn->table('fileNumber')
            ->where($this->notDeleted())
            ->whereRaw('UPPER(LTRIM(RTRIM(st_file_no))) = ?', [strtoupper($new)])
            ->orderByDesc('id')
            ->first();

        if (!$newRow) {
            $this->addPlan('fileNumber', 'INSERT', 'NEW', $m->id, $new, $newTrk, function () use ($new, $fileName, $newTrk, $commAt, $createdBy) {
                $this->conn->table('fileNumber')->insert($this->fnRow([
                    'st_file_no'  => $new,
                    'mlsfNo'      => null,
                    'tracking_id' => $newTrk,
                    'type'        => 'PRIMARY',
                ], $fileName, $createdBy, $commAt));
            });
        } elseif (empty($newRow->tracking_id)) {
            $this->addPlan('fileNumber', 'UPDATE trk', 'NEW', $m->id, $new, $newTrk, function () use ($newRow, $newTrk) {
                $this->conn->table('fileNumber')->where('id', $newRow->id)
                    ->update(['tracking_id' => $newTrk, 'updated_at' => now()]);
            });
        }

        if ($old === '') {
            return;
        }

        // OLD standalone representation: mlsfNo = old number, no ST link.
        $oldRow = $this->conn->table('fileNumber')
            ->where($this->notDeleted())
            ->where(function ($q) use ($old) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = ?', [strtoupper($old)])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = ?', [strtoupper($old)]);
            })
            ->where(function ($q) {
                $q->whereNull('st_file_no')->orWhereRaw("LTRIM(RTRIM(st_file_no)) = ''");
            })
            ->orderByDesc('id')
            ->first();

        if (!$oldRow) {
            $note = $oldTrk ? null : 'no grouping trk';
            $this->addPlan('fileNumber', 'INSERT', 'OLD', $m->id, $old, $oldTrk ?: '(null)', function () use ($old, $fileName, $oldTrk, $commAt, $createdBy) {
                $this->conn->table('fileNumber')->insert($this->fnRow([
                    'mlsfNo'      => $old,
                    'st_file_no'  => null,
                    'tracking_id' => $oldTrk ?: null,
                    'type'        => null,
                ], $fileName, $createdBy, $commAt));
            }, $note);
        } elseif ($oldTrk && $oldRow->tracking_id !== $oldTrk) {
            $this->addPlan('fileNumber', 'UPDATE trk', 'OLD', $m->id, $old, ($oldRow->tracking_id ?? 'NULL') . ' -> ' . $oldTrk, function () use ($oldRow, $oldTrk) {
                $this->conn->table('fileNumber')->where('id', $oldRow->id)
                    ->update(['tracking_id' => $oldTrk, 'updated_at' => now()]);
            });
        }
    }

    // ---- row builders --------------------------------------------------------

    private function fiRow(array $overrides, ?string $fileName, ?string $landUse, string $createdBy, $commAt, int $motherId): array
    {
        return array_merge([
            'main_application_id' => $motherId,
            'file_title'          => $fileName ?: 'ST Applicant',
            'land_use_type'       => $landUse,
            'source'              => 'ST Backfill',
            'status'              => 'ACTIVE',
            'is_deleted'          => 0,
            'created_by'          => $createdBy,
            'created_at'          => $this->carbon($commAt),
            'updated_at'          => now(),
        ], $overrides);
    }

    private function fnRow(array $overrides, ?string $fileName, string $createdBy, $commAt): array
    {
        return array_merge([
            'FileName'           => $fileName ?: 'ST Applicant',
            'SOURCE'             => 'ST Backfill',
            'commissioning_date' => $this->carbon($commAt)->toDateTimeString(),
            'is_deleted'         => 0,
            'created_by'         => is_numeric($createdBy) ? (int) $createdBy : 0,
            'created_at'         => $this->carbon($commAt),
            'updated_at'         => now(),
        ], $overrides);
    }

    // ---- helpers -------------------------------------------------------------

    private function notDeleted(): \Closure
    {
        return static function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        };
    }

    private function findGroupingTrk(string $num): array
    {
        $needle = strtoupper(trim($num));
        foreach ($this->groupMap as $table => $cols) {
            if (!Schema::connection($this->option('connection'))->hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                $hit = $this->conn->table($table)
                    ->whereRaw("UPPER(LTRIM(RTRIM($col))) = ?", [$needle])
                    ->select('tracking_id')
                    ->first();
                if ($hit && !empty($hit->tracking_id)) {
                    return [$table, trim((string) $hit->tracking_id)];
                }
            }
        }
        return [null, null];
    }

    private function displayName($stRow): ?string
    {
        if (!$stRow) {
            return null;
        }
        $type = strtolower((string) ($stRow->applicant_type ?? ''));
        if ($type === 'corporate' && !empty($stRow->corporate_name)) {
            return trim((string) $stRow->corporate_name);
        }
        $name = trim(implode(' ', array_filter([
            $stRow->applicant_title ?? null,
            $stRow->first_name ?? null,
            $stRow->middle_name ?? null,
            $stRow->surname ?? null,
        ], static fn ($v) => trim((string) $v) !== '')));
        if ($name === '' && !empty($stRow->corporate_name)) {
            $name = trim((string) $stRow->corporate_name);
        }
        return $name !== '' ? $name : null;
    }

    private function carbon($v): Carbon
    {
        if ($v instanceof Carbon) {
            return $v;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return now();
        }
    }

    private function addPlan(string $table, string $action, string $side, int $motherId, string $number, ?string $trk, \Closure $apply, ?string $note = null): void
    {
        $this->plan[]  = compact('table', 'action', 'side', 'motherId', 'number', 'trk', 'note');
        $this->apply[] = $apply; // deferred; run together in one transaction on --commit
    }

    private function renderPlan(): void
    {
        if (empty($this->plan)) {
            $this->info('Nothing to do — every mother already has both rows with correct tracking ids.');
            return;
        }

        $rows = [];
        foreach ($this->plan as $p) {
            $rows[] = [
                $p['table'],
                $p['action'],
                $p['side'],
                $p['motherId'],
                $p['number'],
                $p['trk'] ?? '',
                $p['note'] ?? '',
            ];
        }
        $this->table(['table', 'action', 'side', 'mother', 'number', 'tracking_id', 'note'], $rows);

        // counts
        $ins = count(array_filter($this->plan, fn ($p) => $p['action'] === 'INSERT'));
        $upd = count($this->plan) - $ins;
        $noTrk = count(array_filter($this->plan, fn ($p) => ($p['note'] ?? '') === 'no grouping trk'));
        $this->line("Planned: {$ins} insert(s), {$upd} tracking-id update(s).");
        if ($noTrk) {
            $this->warn("{$noTrk} OLD row(s) have NO grouping tracking id and will be written with tracking_id = NULL.");
        }
    }
}
