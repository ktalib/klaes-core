<?php

namespace App\Console\Commands;

use App\Services\Survey\ComputationSheetImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Computation sheet.xlsx into the Computation Sheet tab of Farm Assessment, as written (user, 2026-11-06).
 * A dry run without --commit. --undo=<ref> removes the batch.
 */
class SurveyImportComputationSheet extends Command
{
    protected $signature = 'survey:import-computation-sheet
        {file? : The workbook}
        {--ref= : Batch reference (default CS-<yyyymmdd>-1)}
        {--user= : users.id to stamp as created_by}
        {--commit : Write the batch; without this nothing is written}
        {--undo= : Remove the batch with this reference}';

    protected $description = 'Load Computation sheet.xlsx as written, for the Computation Sheet tab of Farm Assessment';

    public function handle(): int
    {
        if ($undo = $this->option('undo')) {
            $this->info('Removed ' . number_format(ComputationSheetImport::undo($undo)) . ' rows.');

            return self::SUCCESS;
        }

        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("No such file: $file");

            return self::FAILURE;
        }

        $import = new ComputationSheetImport();
        $plan = $import->plan($file);
        $this->table(['Sheet', 'Rows', 'Lines', 'Total rows', 'Other', 'Too long'], collect($plan)->map(fn ($s, $n) => [$n, $s['rows'], $s['lines'], $s['totals'], $s['other'], $s['long']])->values()->all());
        $this->info('Rows ' . number_format(array_sum(array_column($plan, 'rows'))) . ', lines ' . number_format(array_sum(array_column($plan, 'lines'))) . ', sheets ' . count($plan));

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing written. Add --commit to load it.');

            return self::SUCCESS;
        }

        $ref = $this->option('ref') ?: 'CS-' . now()->format('Ymd') . '-1';
        if (DB::connection('sqlsrv')->table(ComputationSheetImport::TABLE)->where('import_ref', $ref)->exists()) {
            $this->error("$ref is already loaded. Undo it first, or use another --ref.");

            return self::FAILURE;
        }

        $n = $import->commit($ref, $this->option('user') ? (int) $this->option('user') : null, fn ($sheet, $total) => $this->line("  $sheet done: $total rows"));
        $this->info("Loaded $ref: " . number_format($n) . ' rows.');

        return self::SUCCESS;
    }
}
