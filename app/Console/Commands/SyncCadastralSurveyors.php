<?php

namespace App\Console\Commands;

use App\Services\Cadastral\SurveyStaffSync;
use Illuminate\Console\Command;

/** Copy the Survey Department staff list into Cadastral Surveyors (dry run unless --apply). */
class SyncCadastralSurveyors extends Command
{
    protected $signature = 'cadastral:sync-surveyors {--apply : Write the changes; without it, only shows them}';

    protected $description = 'Add / refresh Cadastral Surveyors from the Survey Department staff list (surveyors_list)';

    public function handle(SurveyStaffSync $sync): int
    {
        if (!SurveyStaffSync::installed()) {
            $this->error('Run the 2026_10_27_110000 migration first.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $result = $sync->syncAll($apply);

        $this->table(['Action', 'Name', 'Staff ID'], array_map(fn ($r) => [$r['action'], $r['name'], $r['staff_id']],
            array_filter($result['rows'], fn ($r) => $r['action'] !== 'same')));
        $this->info(($apply ? 'Done' : 'Dry run') . ": {$result['add']} to add, {$result['update']} to update, {$result['same']} already in step."
            . ($apply ? '' : ' Run with --apply to write.'));

        if ($apply && ($result['add'] || $result['update'])) {
            app(\App\Services\AuditService::class)->logAction('Cadastral Surveyors Synced From Survey', 'CadastralSurveyor', null, null,
                ['added' => $result['add'], 'updated' => $result['update']], "Survey staff list copied to Cadastral Surveyors: {$result['add']} added, {$result['update']} updated");
        }

        return self::SUCCESS;
    }
}
