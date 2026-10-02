<?php

namespace App\Console\Commands;

use App\Services\PlotWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRegrantJson extends Command
{
    protected $signature = 'regrant:backfill-json {--file=storage/app/reports/Untitled-1.json}';
    protected $description = 'Backfill regrant parent decommission records from the reviewed JSON report';

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
        $backup = storage_path('app/reports/regrant_normalized_backfill_2026-09-21.csv');
        $handle = fopen($backup, 'w');
        fputcsv($handle, ['regrant_file_number', 'parent_file_number']);
        foreach ($groups as $parent => $children) foreach (array_keys($children) as $child) fputcsv($handle, [$child, $parent]);
        fclose($handle);

        $done = 0; $skipped = 0; $errors = [];
        foreach ($groups as $parent => $children) {
            $already = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('mls_file_no', $parent)->where('decommissioned_by', 'System (Re-grant JSON backfill)')->exists();
            if ($already) { $skipped++; continue; }
            try {
                $successor = implode(', ', array_keys($children));
                $summary = $workflow->decommissionFiles([$parent], 'Re-grant JSON backfill -> ' . $successor, 'System (Re-grant JSON backfill)', $successor);
                if (in_array($parent, $summary['archived'] ?? [], true)) $done++; else $skipped++;
                if (!empty($summary['errors'])) $errors[$parent] = $summary['errors'];
            } catch (\Throwable $e) { $errors[$parent] = [$e->getMessage()]; }
        }
        $this->info(json_encode(['parents' => count($groups), 'rows' => array_sum(array_map('count', $groups)), 'archived' => $done, 'skipped' => $skipped, 'errors' => $errors, 'backup' => $backup], JSON_PRETTY_PRINT));
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
