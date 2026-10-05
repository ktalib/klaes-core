<?php

namespace App\Console\Commands;

use App\Services\OpSerialRepairPlanner;
use App\Support\OpSerial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuditOpSerialIntegrity extends Command
{
    protected $signature = 'op:serial-integrity {--apply : Apply only verified blank/zero repairs} {--table= : Limit targets to pra, instrument_capture, or oss_applications} {--rollback= : Restore a previous applied JSON report}';
    protected $description = 'Audit OP serial integrity; dry-run by default, with before-images and guarded rollback';

    public function handle(): int
    {
        if ($this->option('rollback') && $this->option('apply')) {
            $this->error('Choose either --apply or --rollback.');
            return self::FAILURE;
        }
        $db = DB::connection('sqlsrv');
        if ($this->option('table') && !in_array($this->option('table'), ['pra', 'instrument_capture', 'oss_applications'], true)) {
            $this->error('Unknown target table.');
            return self::FAILURE;
        }
        if ($path = $this->option('rollback')) {
            $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $restored = $db->transaction(function () use ($db, $report) {
                $count = 0;
                foreach ($report['repairs'] ?? [] as $repair) {
                    if (($repair['status'] ?? '') !== 'applied') continue;
                    $current = $db->table($repair['table'])->where('id', $repair['id'])->lockForUpdate()->first();
                    if ($current && ($current->op_serial_number ?? null) === $repair['before']) continue;
                    if (!$current || (string) $current->op_serial_number !== $repair['proposed']
                        || OpSerialRepairPlanner::files((array) $current) !== $repair['files']) {
                        throw new RuntimeException('Rollback stopped: a repaired record changed since this run.');
                    }
                    $db->table($repair['table'])->where('id', $repair['id'])->update(['op_serial_number' => $repair['before']]);
                    $this->forget($repair);
                    $count++;
                }
                return $count;
            });
            $this->info("Restored {$restored} serial values. Original report retained: {$path}");
            return self::SUCCESS;
        }

        $tables = [];
        $fields = ['id', 'instrument_type', 'transaction_type', 'system_source', 'op_serial_number', 'is_deleted',
            'file_no', 'mlsFNo', 'fileno', 'temp_fileno', 'resolved_fileno', 'kangisFileNo', 'NewKANGISFileno',
            'source_op_table', 'source_op_id', 'source_pra_id', 'source_instrument_capture_id', 'instrument_capture_id',
            'prop_id', 'Grantor', 'Grantee', 'party_1', 'party_2', 'party_2_name', 'serialNo', 'pageNo', 'volumeNo',
            'serial_no', 'page_no', 'volume_no', 'merger_group_id', 'is_merger_op'];
        foreach (['pra', 'instrument_capture', 'oss_applications'] as $table) {
            $columns = $db->getSchemaBuilder()->getColumnListing($table);
            $query = $db->table($table)->select(array_values(array_intersect($fields, $columns)));
            if (in_array('is_deleted', $columns, true)) $query->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
            if ($table === 'oss_applications') $query->where('system_source', 'OSSOPCHANGEOFNAME');
            else $query->where(function ($q) use ($columns) {
                $q->where('instrument_type', 'like', '%Occupancy Permit%')->orWhere('instrument_type', 'like', '%Transfer%Title%OP%');
                if (in_array('transaction_type', $columns, true)) $q->orWhere('transaction_type', 'like', '%Occupancy Permit%')->orWhere('transaction_type', 'like', '%Transfer%Title%OP%');
            });
            $tables[$table] = $query->get()->map(fn ($row) => (array) $row)->all();
        }
        $commissions = $db->table('mls_file_no')->select(['full_file_number', 'source_pra_id', 'source_instrument_capture_id'])->get()->map(fn ($row) => (array) $row)->all();
        $planner = new OpSerialRepairPlanner($tables, $commissions);
        $repairs = [];
        $summary = [];
        foreach ($tables as $table => $rows) {
            if ($this->option('table') && $this->option('table') !== $table) continue;
            foreach ($rows as $row) {
                if (!OpSerial::isOp($row)) continue;
                $repair = $planner->plan($table, $row);
                $summary[$table][$repair['status']] = ($summary[$table][$repair['status']] ?? 0) + 1;
                if (!in_array($repair['status'], ['consistent', 'valid_unlinked'], true)) $repairs[] = $repair;
            }
        }
        $run = date('Ymd-His').'-'.bin2hex(random_bytes(3));
        $dir = storage_path('app/op-serial-integrity');
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) throw new RuntimeException('Cannot create report directory.');
        $path = $dir.'/'.$run.($this->option('apply') ? '-apply' : '-dry-run').'.json';
        $report = ['run_id' => $run, 'mode' => $this->option('apply') ? 'apply' : 'dry-run', 'summary' => $summary, 'repairs' => $repairs];
        $this->save($path, $report); // Before-images persisted before any write.
        $applied = 0;
        if ($this->option('apply')) {
            foreach ($report['repairs'] as &$repair) {
                if ($repair['status'] !== 'safe') continue;
                $db->transaction(function () use ($db, &$repair, &$report, $path, $tables, $commissions) {
                    $source = $db->table($repair['source']['table'])->where('id', $repair['source']['id'])->lockForUpdate()->first();
                    $target = $db->table($repair['table'])->where('id', $repair['id'])->lockForUpdate()->first();
                    if (!$target || !$source || !empty($target->is_deleted) || !empty($source->is_deleted)
                        || !OpSerial::isMother((array) $source) || !OpSerial::isOp((array) $target)
                        || ($target->op_serial_number ?? null) !== $repair['before']
                        || trim((string) $source->op_serial_number) !== $repair['proposed']
                        || OpSerialRepairPlanner::files((array) $target) !== $repair['files']) {
                        $repair['status'] = 'changed_since_audit';
                        return;
                    }
                    // Recheck source selection, not just the old serial: pointers and
                    // additional OP rows may have changed since the dry-run snapshot.
                    $freshTables = $tables;
                    foreach (['pra', 'instrument_capture'] as $sourceTable) {
                        $files = array_unique(array_merge($repair['files'], OpSerialRepairPlanner::files((array) $source)));
                        $fileColumns = array_intersect(['mlsFNo', 'fileno', 'temp_fileno', 'resolved_fileno', 'kangisFileNo', 'NewKANGISFileno'], $db->getSchemaBuilder()->getColumnListing($sourceTable));
                        $matches = $db->table($sourceTable)->where(function ($q) use ($files, $fileColumns) {
                            foreach ($fileColumns as $column) $q->orWhereIn($column, $files);
                        })->lockForUpdate()->get();
                        $replaceIds = array_fill_keys($matches->pluck('id')->all(), true);
                        $freshTables[$sourceTable] = array_values(array_filter($freshTables[$sourceTable], fn ($row) => !isset($replaceIds[$row['id']])));
                        foreach ($matches as $match) $freshTables[$sourceTable][] = (array) $match;
                    }
                    $freshCommissions = $db->table('mls_file_no')->whereIn('full_file_number', $repair['files'])->lockForUpdate()->get()->map(fn ($row) => (array) $row)->all();
                    $otherCommissions = array_filter($commissions, fn ($row) => !in_array(strtoupper(trim($row['full_file_number'])), $repair['files'], true));
                    $fresh = (new OpSerialRepairPlanner($freshTables, array_merge($otherCommissions, $freshCommissions)))->plan($repair['table'], (array) $target);
                    if ($fresh['status'] !== 'safe' || $fresh['source'] != $repair['source'] || $fresh['proposed'] !== $repair['proposed']) {
                        $repair['status'] = 'changed_since_audit';
                        return;
                    }
                    $query = $db->table($repair['table'])->where('id', $repair['id']);
                    if ($repair['before'] === null) $query->whereNull('op_serial_number');
                    else $query->where('op_serial_number', $repair['before']);
                    if ($query->update(['op_serial_number' => $repair['proposed']]) !== 1) throw new RuntimeException('Conditional repair failed.');
                    if ((string) $db->table($repair['table'])->where('id', $repair['id'])->value('op_serial_number') !== $repair['proposed']) throw new RuntimeException('Repair verification failed.');
                    $repair['status'] = 'applied';
                    $this->forget($repair);
                    $this->save($path, $report); // Persist completion before commit; rollback tolerates an already-restored row.
                });
                if ($repair['status'] === 'applied') $applied++;
                $this->save($path, $report);
            }
            unset($repair);
        }
        $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        $this->info(($this->option('apply') ? "Applied {$applied} verified repairs." : 'Dry run: no records changed.')." Report: {$path}");
        return self::SUCCESS;
    }

    private function save(string $path, array $report): void
    {
        if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Cannot persist repair report.');
    }

    private function forget(array $repair): void
    {
        foreach ($repair['files'] as $file) Cache::forget('pra:lookup:'.$file);
    }
}
