<?php

namespace App\Console\Commands;

use App\Services\PlotWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recover JSON-report parents whose only surviving source is deprecated_records. */
class RecoverRegrantJsonParents extends Command
{
    protected $signature = 'regrant:recover-json-parents
        {--file=storage/app/reports/Untitled-1.json}
        {--apply : Restore and decommission recoverable parents (default is a dry run)}';

    protected $description = 'Recover missing JSON Re-grant parents from deprecated_records and backfill their decommissioning lineage.';

    public function handle(PlotWorkflowService $workflow): int
    {
        $path = base_path($this->option('file'));
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $groups = [];
        foreach ($rows as $row) {
            $comment = trim((string) ($row['Comment'] ?? ''));
            if (in_array($comment, ['Not Regrant', 'This is a Change of Purpose'], true)) continue;
            $child = strtoupper(trim((string) ($row['regrant_file_number'] ?? '')));
            $parent = strtoupper(trim((string) ($row['parent_file_number'] ?? '')));
            if ($child === '' || $parent === '') continue;
            if ($comment === 'Flip') [$child, $parent] = [$parent, $child];
            $groups[$parent][$child] = true;
        }

        $db = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');
        $apply = (bool) $this->option('apply');
        $recovered = []; $unrecoverable = []; $skipped = [];

        foreach ($groups as $parent => $children) {
            $archived = $db->table('decommissioned_files')
                ->where(fn ($q) => $q->where('file_no', $parent)->orWhere('mls_file_no', $parent))
                ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
                ->exists();
            if ($archived) { $skipped[] = $parent; continue; }

            $live = $db->table('file_indexings')
                ->where(fn ($q) => $q->where('file_number', $parent)->orWhere('kangis_file_no', $parent))
                ->exists()
                || $db->table('fileNumber')
                ->where(fn ($q) => $q->where('mlsfNo', $parent)->orWhere('kangisFileNo', $parent))
                ->exists();
            if ($live) { $skipped[] = $parent; continue; }

            $source = $db->table('deprecated_records')->where('file_number', $parent)->orderByDesc('id')->first();
            if (!$source) { $unrecoverable[] = $parent; continue; }

            $successors = implode(', ', array_keys($children));
            if (!$apply) {
                $this->line("[DRY] {$parent} -> {$successors} (deprecated_records #{$source->id})");
                $recovered[] = $parent;
                continue;
            }

            $level = $db->transactionLevel();
            $db->beginTransaction();
            try {
                $payload = [
                    'file_number' => $parent, 'file_title' => $source->file_title, 'land_use_type' => $source->land_use_type,
                    'plot_number' => $source->plot_number, 'district' => $source->district, 'lga' => $source->lga,
                    'location' => $source->location, 'plot_size' => $source->plot_size, 'tp_no' => $source->tp_no,
                    'lpkn_no' => $source->lpkn_no, 'tracking_id' => $source->tracking_id, 'original_holder' => $source->original_holder,
                    'current_holder' => $source->current_holder, 'parent_prop_id' => $source->parent_prop_id,
                    'related_fileno' => $source->related_fileno, 'has_transaction' => $source->has_transaction ?? 0,
                    'serial_no' => $source->serial_no, 'batch_no' => $source->batch_no, 'workflow_status' => $source->workflow_status,
                    'registry' => $source->registry, 'general_registry' => $source->general_registry, 'prop_id' => $source->prop_id,
                    'phone' => $source->phone, 'residence_address' => $source->residence_address,
                    'created_by' => $source->created_by ?: 1, 'updated_by' => $source->updated_by,
                    'created_at' => $source->created_at ?? now(), 'updated_at' => now(), 'is_decommissioned' => 0,
                ];
                $payload = array_filter($payload, fn ($column) => $schema->hasColumn('file_indexings', $column), ARRAY_FILTER_USE_KEY);
                $db->table('file_indexings')->insert($payload);
                $summary = $workflow->decommissionFiles([$parent], 'Re-grant → ' . $successors, 'System (Re-grant report catch-up)', $successors);
                if (!in_array($parent, $summary['archived'] ?? [], true)) {
                    throw new \RuntimeException(implode('; ', $summary['errors'] ?: ['workflow did not archive parent']));
                }
                $db->commit();
                $recovered[] = $parent;
                $this->info("[OK]  {$parent} -> {$successors}");
            } catch (\Throwable $e) {
                if ($db->transactionLevel() > $level) $db->rollBack($level);
                $this->error("[FAIL] {$parent}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(['JSON parents', 'Recovered/backfilled', 'Already handled/live', 'No backup source'], [[count($groups), count($recovered), count($skipped), count($unrecoverable)]]);
        if ($unrecoverable) $this->warn('No recovery source: ' . implode(', ', $unrecoverable));
        return self::SUCCESS;
    }
}
