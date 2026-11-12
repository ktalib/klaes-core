<?php

namespace App\Console\Commands;

use App\Services\Survey\ComputationSheetPromote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CR 11112026-2 item 9a: staged Computation Sheet + KMC Access farm records into the main Survey tables
 * (one project, a land per sheet, farms, lines, farmers). A dry run without --commit. --undo=<ref> removes it.
 */
class SurveyPromoteComputationSheet extends Command
{
    protected $signature = 'survey:promote-computation-sheet
        {--cs=CS-20261106-1 : Computation Sheet staging ref}
        {--kmc=KMC-20261111-1 : KMC Access staging ref}
        {--ref= : Batch reference (default CSM-<yyyymmdd>-1)}
        {--user= : users.id to stamp as created_by}
        {--commit : Write the batch; without this nothing is written}
        {--undo= : Remove the batch with this reference}';

    protected $description = 'Move the staged Computation Sheet and KMC Access farm records into Survey projects, lands, farms and farmers';

    public function handle(): int
    {
        if ($undo = $this->option('undo')) {
            foreach (ComputationSheetPromote::undo($undo) as $t => $n) $this->line("  $t: " . number_format($n));

            return self::SUCCESS;
        }

        $p = new ComputationSheetPromote([$this->option('cs'), $this->option('kmc')]);
        $s = $p->plan();
        $this->table(['', 'Count'], [
            ['Lines (Computation Sheet / KMC)', number_format($s['lines']) . ' (' . number_format($s['cs_lines']) . ' / ' . number_format($s['kmc_lines']) . ')'],
            ['Lands (one per sheet)', $s['lands']],
            ['Lines with no name (line only, no farm)', $s['no_name']],
            ['Farmers (distinct names)', number_format($s['persons'])],
            ['  linked to the one farmer on record', number_format($s['linked']) . ' (' . number_format($s['linked_lines']) . ' lines)'],
            ['  name on several farmers: new, flagged', number_format($s['ambiguous']) . ' (' . number_format($s['ambiguous_lines']) . ' lines)'],
            ['  not on record: new', number_format($s['new_persons'])],
            ['Lines with photo', number_format($s['photos'])],
            ['Area (ha)', $s['area_ha']],
        ]);

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing written. Add --commit to write it.');

            return self::SUCCESS;
        }

        $ref = $this->option('ref') ?: 'CSM-' . now()->format('Ymd') . '-1';
        if (DB::connection('sqlsrv')->table('survey_projects')->where('import_ref', $ref)->exists()) {
            $this->error("$ref is already loaded. Undo it first, or use another --ref.");

            return self::FAILURE;
        }

        // The farms on record that will get a Farmer ID: their ids, so the link can be checked or undone by hand.
        $backup = storage_path("app/survey/computation-sheet-backups/$ref-linked-farms.csv");
        @mkdir(dirname($backup), 0777, true);
        $h = fopen($backup, 'w');
        fputcsv($h, ['survey_beneficiary_id', 'survey_farmer_id_before']);
        foreach ($p->existingToLink() as $id) fputcsv($h, [$id, '']);
        fclose($h);
        $this->line("Farms to link saved to $backup");

        $c = $p->commit($ref, $this->option('user') ? (int) $this->option('user') : null,
            fn ($sheet, $n) => $this->line("  $sheet: lines " . number_format($n['lines']) . ', farms ' . number_format($n['farms'])));
        $this->info("Loaded $ref: " . json_encode($c));

        return self::SUCCESS;
    }
}
