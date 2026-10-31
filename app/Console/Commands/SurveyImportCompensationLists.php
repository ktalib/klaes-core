<?php

namespace App\Console\Commands;

use App\Services\Survey\CompensationListImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import the "ALL COMPENSATION" workbooks into the Survey records (user 2026-10-30).
 *
 *   php artisan survey:import-compensation "<folder>"                 dry run: report only
 *   php artisan survey:import-compensation "<folder>" --commit --user=1
 *   php artisan survey:import-compensation --undo=COMP-20261030-1     remove a batch
 *
 * See App\Services\Survey\CompensationListImport for what becomes what.
 */
class SurveyImportCompensationLists extends Command
{
    protected $signature = 'survey:import-compensation {dir? : Folder of .xlsx lists}
                            {--commit : Write the records (default is a dry run)}
                            {--ref= : Batch tag (default COMP-<date>-<n>)}
                            {--user= : users.id to stamp as created_by}
                            {--csv= : Also write the per-project summary to this CSV}
                            {--undo= : Remove the batch with this tag}';

    protected $description = 'Import the Survey ALL COMPENSATION farmer and plot allocation lists';

    public function handle(CompensationListImport $import): int
    {
        if ($ref = $this->option('undo')) {
            $out = CompensationListImport::undo($ref);
            $this->table(['Table', 'Rows removed'], collect($out)->map(fn ($n, $t) => [$t, $n])->values()->all());

            return self::SUCCESS;
        }

        $dir = (string) $this->argument('dir');
        if (! is_dir($dir)) {
            $this->error("Not a folder: {$dir}");

            return self::FAILURE;
        }

        $this->info('Reading the workbooks…');
        $s = $import->plan($dir);

        $this->line('');
        $this->info('Rows by file');
        $this->table(['File', 'Farmer lines', 'Plot lines', 'Totals', 'Summaries', 'Other'], collect($s['files'])->map(fn ($k, $f) => [
            $f, $k['farmer'] ?? 0, $k['plot'] ?? 0, $k['total'] ?? 0, $k['summary'] ?? 0, $k['other'] ?? 0,
        ])->values()->all());

        $this->info('Projects');
        $this->table(['Key', 'Plan', 'Name', 'Purpose', 'LGA', 'CO', 'Files', 'Lines', 'Area (ha)'], $s['projects']->map(fn ($p) => [
            $p['key'], $p['plan_no'], mb_strimwidth($p['name'], 0, 40, '…'), mb_strimwidth((string) $p['purpose'], 0, 28, '…'), $p['lga'],
            $p['co'] . (count($p['cos']) > 1 ? ' (+' . (count($p['cos']) - 1) . ')' : ''), $p['files'], $p['lines'], $p['area'] ?: '',
        ])->all());

        $this->info('Surveyors (farmer lists)');
        $this->table(['Project', 'Surveyor as written', 'Blocks', 'Staff match id'], $s['surveyors']->map(fn ($x) => [
            $x['plan_key'], $x['name'] ?? '(not stated)', $x['lands'], $x['staff_id'] ?? '—',
        ])->all());

        $this->line(sprintf('Rows %d · lands %d · likely duplicates across files %d · plot lists longer than 400 chars %d',
            $s['rows'], $s['lands'], $s['duplicates'], $s['long_plots']));
        $this->line('Kinds: ' . json_encode($s['kinds']));

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, ['key', 'plan_no', 'name', 'purpose', 'lga', 'co', 'cos', 'files', 'lines', 'area_ha']);
            foreach ($s['projects'] as $p) fputcsv($fh, [$p['key'], $p['plan_no'], $p['name'], $p['purpose'], $p['lga'], $p['co'], implode(' ', $p['cos']), $p['files'], $p['lines'], $p['area']]);
            fclose($fh);
            $this->line("Summary written to {$csv}");
        }

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing written. Add --commit to import.');

            return self::SUCCESS;
        }

        $ref = $this->option('ref') ?: $this->nextRef();
        if (DB::connection('sqlsrv')->table('survey_comp_import_rows')->where('import_ref', $ref)->exists()) {
            $this->error("Batch {$ref} already exists. Undo it first or use another --ref.");

            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;

        $this->info("Importing as batch {$ref}…");
        $bar = $this->output->createProgressBar($s['lands']);
        $counts = $import->commit($ref, $user, function () use ($bar) { $bar->advance(); });
        $bar->finish();
        $this->line('');
        $this->table(['Written', 'Count'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info("Done. To remove this batch: php artisan survey:import-compensation --undo={$ref}");

        return self::SUCCESS;
    }

    private function nextRef(): string
    {
        $base = 'COMP-' . now()->format('Ymd');
        $n = DB::connection('sqlsrv')->table('survey_comp_import_rows')->where('import_ref', 'like', $base . '-%')->distinct()->count('import_ref');

        return $base . '-' . ($n + 1);
    }
}
