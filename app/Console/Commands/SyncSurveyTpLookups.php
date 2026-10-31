<?php

namespace App\Console\Commands;

use App\Models\Survey\SurveyTpNumber as TP;
use App\Services\Survey\TpLookupSync;
use Illuminate\Console\Command;

/**
 * Add the valid TP numbers in tp_lookups to Survey > Public Survey > TP Nos and fill their
 * District / LGA (rules on TpLookupSync). The TP Nos page does this by itself for new
 * lookups; this command is the full pass. Dry run unless --apply; --undo removes the rows
 * added from the lookups.
 */
class SyncSurveyTpLookups extends Command
{
    protected $signature = 'survey:sync-tp-lookups {--apply : Write the changes; without it, only shows them} {--undo : Remove the rows added from tp_lookups}';

    protected $description = 'Add the valid TP numbers in tp_lookups to the Survey TP Nos register and fill their District / LGA';

    public function handle(TpLookupSync $sync): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('undo')) {
            $n = TP::whereNotNull('tp_lookup_id')->where('remarks', TpLookupSync::REMARK)->count();
            if ($apply) {
                TP::whereNotNull('tp_lookup_id')->where('remarks', TpLookupSync::REMARK)->forceDelete();
                TP::whereNotNull('tp_lookup_id')->update(['tp_lookup_id' => null]);
            }
            $this->info(($apply ? 'Removed' : 'Would remove') . " {$n} rows.");

            return self::SUCCESS;
        }

        $plan = $sync->plan();
        $this->info(sprintf('%d to add, %d on the register to link, %d already in, %d repeats skipped, %d not valid TP numbers.',
            count($plan['add']), count($plan['link']), $plan['already'], $plan['repeats'], $plan['rejected']));
        if ($apply) $sync->apply($plan);

        [$updates, $before] = $sync->fillLocations(false);
        $this->info(sprintf('Location: %d LGAs and %d districts to fill, from the records with the same TP No.',
            count(array_filter($updates, fn ($s) => isset($s['prop_lga']))), count(array_filter($updates, fn ($s) => isset($s['prop_district'])))));

        if ($apply && $updates) {
            // Before-image of what is being filled, so the step can be reversed.
            $backup = storage_path('app/survey_tp_location_fill_' . now()->format('Ymd_His') . '.csv');
            $fh = fopen($backup, 'w');
            fputcsv($fh, ['id', 'tp_no', 'prop_district', 'prop_district_other', 'prop_lga', 'new_district', 'new_district_other', 'new_lga']);
            foreach ($before->whereIn('id', array_keys($updates)) as $t) {
                $u = $updates[$t->id];
                fputcsv($fh, [$t->id, $t->tp_no, $t->prop_district, $t->prop_district_other, $t->prop_lga,
                    $u['prop_district'] ?? '', $u['prop_district_other'] ?? '', $u['prop_lga'] ?? '']);
            }
            fclose($fh);
            $this->line("Before-image: {$backup}");
            $sync->fillLocations(true);
        }

        if (! $apply) $this->line('Dry run (rows not yet added are not counted in the location line). Run with --apply to write.');

        return self::SUCCESS;
    }
}
