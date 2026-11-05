<?php

namespace App\Console\Commands;

use App\Services\Survey\OpTpGroups;
use Illuminate\Console\Command;

/**
 * Group OPs by TP number (CR 03112026 item 2). A dry run unless --apply. Scheduled with --apply, so a
 * spelling first written on a new OP joins its layout overnight. Deeds rows are never written.
 */
class SurveySyncOpTpGroups extends Command
{
    protected $signature = 'survey:sync-op-tp-groups {--apply : Save the groups; without it nothing is written}';

    protected $description = 'Group Occupancy Permits by layout from the TP numbers written on them';

    public function handle(OpTpGroups $groups): int
    {
        $s = $groups->sync((bool) $this->option('apply'));

        $this->line("OPs: {$s['total']}  with a TP No.: {$s['with_tp']}  without: {$s['no_tp']}");
        $this->line("Spellings: {$s['spellings']}  groups: {$s['groups']}");
        $this->line("Groups in Public Layout Plans: {$s['in_register']} ({$s['in_register_ops']} OPs)");
        $this->line("New groups: {$s['new_groups']}  new spellings: {$s['new_spellings']}");
        $this->option('apply') ? $this->info('Saved.') : $this->warn('Dry run: nothing written. Use --apply to save.');

        return self::SUCCESS;
    }
}
