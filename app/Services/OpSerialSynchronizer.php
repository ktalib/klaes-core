<?php

namespace App\Services;

use App\Support\OpSerial;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Call inside the writer's SQL Server transaction, after updating the exact OP. */
class OpSerialSynchronizer
{
    public function sync(string $table, $id, $before = null): void
    {
        $db = DB::connection('sqlsrv');
        $source = $db->table($table)->where('id', $id)->lockForUpdate()->first();
        if (!$source || !OpSerial::isMother((array) $source)) return;
        $serial = OpSerial::require($source->op_serial_number ?? null);
        $references = [[$table, $id]];
        if ($table === 'pra' && !empty($source->instrument_capture_id)) $references[] = ['instrument_capture', $source->instrument_capture_id];
        $targets = [];
        foreach ($references as [$sourceTable, $sourceId]) {
            if ($sourceTable !== $table || (string) $sourceId !== (string) $id) {
                $paired = $db->table($sourceTable)->where('id', $sourceId)->lockForUpdate()->first();
                if ($paired && empty($paired->is_deleted) && OpSerial::isMother((array) $paired)) $targets[$sourceTable.':'.$sourceId] = [$sourceTable, (array) $paired];
            }
            $children = $db->table('pra')->where(function ($q) use ($sourceTable, $sourceId) {
                $q->where(fn ($w) => $w->where('source_op_table', $sourceTable)->where('source_op_id', $sourceId));
                if ($sourceTable === 'instrument_capture') $q->orWhere('instrument_capture_id', $sourceId);
            })->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))->lockForUpdate()->get();
            foreach ($children as $child) {
                if (!OpSerial::isOp((array) $child)) continue;
                if (OpSerial::isMother((array) $child) && $sourceTable !== 'instrument_capture') continue;
                if ($table === 'pra' && (string) $child->id === (string) $id) continue;
                $targets['pra:'.$child->id] = ['pra', (array) $child];
            }
            $commissionFiles = $db->table('mls_file_no')->where($sourceTable === 'pra' ? 'source_pra_id' : 'source_instrument_capture_id', $sourceId)->pluck('full_file_number')->all();
            $applications = $db->table('oss_applications')->where('system_source', 'OSSOPCHANGEOFNAME')
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(function ($q) use ($commissionFiles, $sourceTable, $sourceId) {
                    $q->whereIn('file_no', $commissionFiles);
                    if ($sourceTable === 'instrument_capture') $q->orWhere('instrument_capture_id', $sourceId);
                })->lockForUpdate()->get();
            foreach ($applications as $application) $targets['oss_applications:'.$application->id] = ['oss_applications', (array) $application];
        }
        if (!empty($source->merger_group_id)) {
            $mergerTransfers = $db->table('pra')->where('merger_group_id', $source->merger_group_id)->where('is_merger_op', 0)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))->lockForUpdate()->get();
            foreach ($mergerTransfers as $transfer) {
                if (OpSerial::isOp((array) $transfer) && !OpSerial::isMother((array) $transfer)) $targets['pra:'.$transfer->id] = ['pra', (array) $transfer];
            }
        }
        foreach ($targets as [$targetTable, $target]) {
            $targetSerial = $serial;
            $priorSerial = trim((string) $before);
            if ($targetTable === 'pra' && !OpSerial::isMother($target) && !empty($target['merger_group_id'])) {
                $resolver = app(OpSerialSourceResolver::class);
                $targetSerial = $resolver->mergerSerial($target['merger_group_id']);
                $priorSerial = $resolver->mergerSerial($target['merger_group_id'], ['id' => $id, 'serial' => $before ?? $serial]);
            }
            $current = trim((string) ($target['op_serial_number'] ?? ''));
            if ($current === $targetSerial) continue;
            if ($current !== '' && $current !== '0' && $current !== $priorSerial) {
                throw ValidationException::withMessages(['op_serial_number' => 'A linked record has a conflicting OP serial. Review the source links before correcting this OP.']);
            }
            $db->table($targetTable)->where('id', $target['id'])->update(['op_serial_number' => $targetSerial, 'updated_at' => now()]);
            Log::info('OP serial synchronized', ['source_table' => $table, 'source_id' => $id, 'target_table' => $targetTable,
                'target_id' => $target['id'], 'before' => $current, 'after' => $targetSerial, 'user_id' => auth()->id()]);
            foreach (OpSerialRepairPlanner::files($target) as $file) Cache::forget('pra:lookup:'.$file);
        }
        if ((string) $before !== $serial) Log::info('OP serial saved', ['table' => $table, 'id' => $id, 'before' => $before, 'after' => $serial, 'user_id' => auth()->id()]);
        foreach (OpSerialRepairPlanner::files((array) $source) as $file) Cache::forget('pra:lookup:'.$file);
    }
}
