<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the Land Registration instrument type, data and all.
 *
 * The type string is not a label — it is the key every part of this module is
 * scoped by: the vault row is looked up by it, both lookup tables store it, and
 * every captured, registered and PRA row carries it. Editing
 * config('land_registration.instrument_type') on its own therefore does not
 * rename anything; it orphans everything. The register goes empty, and the next
 * capture fails with "not found in Manage Instrument Types" because the vault is
 * still filed under the old name.
 *
 * So: change the config first, then run this to move the data to meet it.
 *
 * Idempotent, and safe to run when there is nothing to move — every UPDATE is
 * scoped to rows still holding the old name.
 */
class RenameLandRegistrationType extends Command
{
    protected $signature = 'land-registration:rename-type
                            {--from= : The name currently stored in the data}
                            {--to= : The new name (defaults to config land_registration.instrument_type)}
                            {--pretend : Report what would change without writing}';

    protected $description = 'Rename the Land Registration instrument type across every table that stores it';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) ($this->option('to') ?: config('land_registration.instrument_type'));
        $pretend = (bool) $this->option('pretend');

        if ($from === '') {
            $this->error('--from is required: the name the data currently holds.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->warn('--from and --to are the same; nothing to do.');

            return self::SUCCESS;
        }

        $db = DB::connection('sqlsrv');

        // Every place the type string is stored, as column => table.
        $targets = [
            ['instrument_number_vaults', 'instrument_type'],
            ['new_instrument_types', 'name'],
            ['InstrumentTypes', 'InstrumentName'],
            ['instrument_capture', 'instrument_type'],
            ['deed_registrations', 'instrument_type'],
            ['pra', 'transaction_type'],
            ['pra', 'instrument_type'],
        ];

        $this->info($pretend ? "DRY RUN — nothing will be written" : "Renaming the instrument type");
        $this->line("  from: {$from}");
        $this->line("  to:   {$to}");
        $this->newLine();

        // A vault already filed under the NEW name would collide: renaming the
        // old one onto it would leave two rows and an ambiguous lookup.
        if ($db->table('instrument_number_vaults')->where('instrument_type', $to)->exists()
            && $db->table('instrument_number_vaults')->where('instrument_type', $from)->exists()) {
            $this->error("Both '{$from}' and '{$to}' already have a vault row.");
            $this->error('Merge them by hand first — this command will not guess which numbering is live.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($targets as [$table, $column]) {
            if (!Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                $this->line(sprintf('  %-46s (no such column, skipped)', "{$table}.{$column}"));
                continue;
            }

            $count = $db->table($table)->where($column, $from)->count();

            if ($count === 0) {
                $this->line(sprintf('  %-46s nothing to rename', "{$table}.{$column}"));
                continue;
            }

            if (!$pretend) {
                $db->table($table)->where($column, $from)->update([$column => $to]);
            }

            $total += $count;
            $this->info(sprintf('  %-46s %d row(s)%s', "{$table}.{$column}", $count, $pretend ? ' would change' : ' renamed'));
        }

        $this->newLine();

        if ($pretend) {
            $this->info("{$total} row(s) would be renamed. Re-run without --pretend to apply.");

            return self::SUCCESS;
        }

        $this->info("{$total} row(s) renamed.");

        $vault = $db->table('instrument_number_vaults')->where('instrument_type', $to)->first();
        if ($vault) {
            $this->line(sprintf(
                '  vault now reads %d/%d/%d — next issue %d/%d/%d',
                $vault->current_serial,
                $vault->current_page,
                $vault->current_volume,
                $vault->current_serial + 1,
                $vault->current_page + 1,
                $vault->current_volume
            ));
        } else {
            $this->warn("  No vault row under '{$to}' — run land-registration:install.");
        }

        return self::SUCCESS;
    }
}
