<?php

namespace App\Console\Commands;

use App\Models\Survey\SurveyTpNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * tp_lookups, the TP No dropdown on Capture OP, MLS commissioning, Land Recommendation and Land Registration,
 * becomes the Public Layout Plans register (user, 2026-11-05: "backup the tp_lookups table and empty the tp
 * numbers and import the ones [in] survey_tp_numbers, cus they are the latest").
 *
 * Backs up every row first, to a copy table (same ids) and a CSV, then in one short transaction empties the
 * table and loads each distinct TP No of the listed register rows (kano_layouts + manual), first spelling in
 * register order. New ids carry on from the old ones, so nothing still holding an old id can meet a new row.
 * Dry run by default; --undo puts the backup back as it was.
 */
class TpLookupsFromRegister extends Command
{
    protected $signature = 'survey:tp-lookups-from-register {--apply : Back up, empty and reload tp_lookups} {--undo : Restore tp_lookups from the backup table}';

    protected $description = 'Back up tp_lookups, then replace its TP numbers with the Public Layout Plans register';

    private const BACKUP = 'tp_lookups_backup_20261105';
    private const SOURCE = 'survey_tp_numbers';

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');

        if ($this->option('undo')) return $this->undo($db);

        $values = [];
        foreach (SurveyTpNumber::listed()->whereNotNull('tp_no')->orderBy('register_sn')->orderBy('id')->get(['tp_no']) as $t) {
            $v = trim((string) $t->tp_no);
            if ($v !== '' && ! isset($values[mb_strtoupper($v)])) $values[mb_strtoupper($v)] = mb_substr($v, 0, 255);
        }
        $current = $db->table('tp_lookups')->count();
        $this->line("tp_lookups now: {$current} rows. Public Layout Plans: " . count($values) . ' distinct TP numbers to load.');
        $this->line('First ten: ' . implode(', ', array_slice(array_values($values), 0, 10)));

        if (! $this->option('apply')) {
            $this->warn('Dry run. Nothing written. Add --apply to back up, empty and reload.');
            return self::SUCCESS;
        }
        if ($db->selectOne('SELECT OBJECT_ID(?) id', [self::BACKUP])->id) {
            $this->error('The backup table ' . self::BACKUP . ' already exists. Nothing done; check it (or --undo) first.');
            return self::FAILURE;
        }

        // 1. Backups: a copy table with the same ids, and a CSV beside it.
        $db->statement('SELECT * INTO [' . self::BACKUP . '] FROM [tp_lookups]');
        $copied = $db->table(self::BACKUP)->count();
        if ($copied !== $current) {
            $this->error("Backup holds {$copied} rows, tp_lookups {$current}. Stopped before any change.");
            return self::FAILURE;
        }
        $csv = 'tp_lookups_backup_20261105.csv';
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['id', 'tp_no', 'source_tables', 'created_at']);
        foreach ($db->table(self::BACKUP)->orderBy('id')->cursor() as $r) fputcsv($out, [$r->id, $r->tp_no, $r->source_tables, $r->created_at]);
        rewind($out);
        file_put_contents(storage_path('app/' . $csv), stream_get_contents($out));
        $this->info("Backed up {$copied} rows to table " . self::BACKUP . ' and storage/app/' . $csv);

        // 2. Swap in one short transaction, so the dropdown is never seen empty.
        $now = now()->toDateTimeString();
        $db->transaction(function () use ($db, $values, $now) {
            $db->table('tp_lookups')->delete();
            foreach (array_chunk(array_values($values), 200) as $chunk) {
                $db->table('tp_lookups')->insert(array_map(fn ($v) => ['tp_no' => $v, 'source_tables' => self::SOURCE, 'created_at' => $now], $chunk));
            }
        });

        $this->info('tp_lookups now holds ' . $db->table('tp_lookups')->count() . ' TP numbers from Public Layout Plans (ids from '
            . $db->table('tp_lookups')->min('id') . ').');

        return self::SUCCESS;
    }

    private function undo($db): int
    {
        if (! $db->selectOne('SELECT OBJECT_ID(?) id', [self::BACKUP])->id) {
            $this->error('No backup table ' . self::BACKUP . '. Nothing to restore.');
            return self::FAILURE;
        }
        $n = $db->table(self::BACKUP)->count();
        $this->line("Restoring {$n} rows from " . self::BACKUP . ' (the rows loaded from the register and any added since are removed).');
        if (! $this->option('apply')) {
            $this->warn('Dry run. Add --apply with --undo to restore.');
            return self::SUCCESS;
        }
        $db->transaction(function () use ($db) {
            $db->table('tp_lookups')->delete();
            $db->unprepared('SET IDENTITY_INSERT [tp_lookups] ON; INSERT INTO [tp_lookups] (id, tp_no, source_tables, created_at) SELECT id, tp_no, source_tables, created_at FROM [' . self::BACKUP . ']; SET IDENTITY_INSERT [tp_lookups] OFF;');
        });
        $this->info('tp_lookups restored: ' . $db->table('tp_lookups')->count() . ' rows. The backup table is kept.');

        return self::SUCCESS;
    }
}
