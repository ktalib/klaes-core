<?php

namespace App\Console\Commands;

use App\Services\Survey\ComputationSheetImport;
use App\Services\Survey\KmcAccessImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * KMC Phase1 Compensation.accdb's CSV export, with its passport photos, into the Computation Sheet staging table,
 * matched against what is already staged so nothing is there twice (user, 2026-11-11). A dry run without --commit.
 * --undo=<ref> removes the batch's rows, clears what it filled on staged rows, and deletes its photos.
 */
class SurveyImportKmcAccess extends Command
{
    protected $signature = 'survey:import-kmc-access
        {dir? : The export folder (the CSVs, attachments_index.csv and the table folders of images)}
        {--ref= : Batch reference (default KMC-<yyyymmdd>-1)}
        {--user= : users.id to stamp as created_by}
        {--backup= : Where to write the staged rows as they were before the batch fills them (CSV)}
        {--commit : Write the batch; without this nothing is written}
        {--undo= : Remove the batch with this reference: its rows, what it filled, and its photos}';

    protected $description = 'Load the KMC Phase1 Compensation Access export, with photos, into the Computation Sheet staging table without duplicating staged farmers';

    public function handle(): int
    {
        if ($undo = $this->option('undo')) {
            $r = KmcAccessImport::undo($undo);
            $this->info('Removed ' . number_format($r['removed']) . ' rows, cleared ' . number_format($r['cleared']) . ' staged rows, deleted the photos.');

            return self::SUCCESS;
        }

        $dir = (string) $this->argument('dir');
        if (! is_dir($dir)) {
            $this->error("No such folder: $dir");

            return self::FAILURE;
        }

        $import = new KmcAccessImport();
        $s = $import->plan($dir);
        $this->table(['', 'Count'], [
            ['Farm-record rows in the 3 versions', number_format($s['version_rows'])],
            ['Distinct farmers (Property ID + name)', number_format($s['farmers'])],
            ['Already staged: photo + fields onto their row(s), no new row', number_format($s['matched']) . ' farmers on ' . number_format($s['matched_lines']) . ' rows (' . number_format($s['photos_on_staged']) . ' with photo)'],
            ['Same Farm ID, other name: new row, flagged', number_format($s['clash'])],
            ['Not staged: new row', number_format($s['new']) . ' (' . number_format($s['photos_on_new']) . ' of the new rows with photo)'],
            ['Sulementry: rows / not already staged', $s['supp'] . ' / ' . $s['supp_new']],
            ['Areas1: rows / distinct (loaded)', number_format($s['areas_rows']) . ' / ' . number_format($s['areas_distinct'])],
        ]);

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing written. Add --commit to load it.');

            return self::SUCCESS;
        }

        $ref = $this->option('ref') ?: 'KMC-' . now()->format('Ymd') . '-1';
        $db = DB::connection('sqlsrv');
        if ($db->table(ComputationSheetImport::TABLE)->where('import_ref', $ref)->orWhere('enriched_ref', $ref)->exists()) {
            $this->error("$ref is already loaded. Undo it first, or use another --ref.");

            return self::FAILURE;
        }

        // The staged rows as they are now, before anything is filled on them.
        $backup = $this->option('backup') ?: storage_path("app/survey/computation-sheet-backups/$ref-staged-before.csv");
        @mkdir(dirname($backup), 0777, true);
        $before = $import->enrichedBefore();
        $h = fopen($backup, 'w');
        fputcsv($h, array_keys($before[0] ?? ['id' => null]));
        foreach ($before as $row) fputcsv($h, $row);
        fclose($h);
        $this->line('Staged rows before filling: ' . number_format(count($before)) . " saved to $backup");

        $done = $import->commit($ref, $this->option('user') ? (int) $this->option('user') : null,
            fn ($what, $n, $photos) => $this->line("  $what: " . number_format($n) . ", photos copied " . number_format($photos)));
        $this->info("Loaded $ref: filled " . number_format($done['filled']) . ' staged rows, added ' . number_format($done['inserted']) . ' rows, ' . number_format($done['photos']) . ' distinct photos.');

        return self::SUCCESS;
    }
}
