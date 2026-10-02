<?php

namespace App\Console\Commands;

use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Services\DuplexSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Undo a duplex commissioning so it can be run again.
 *
 * Testing the duplex means commissioning it repeatedly, and each run scatters rows
 * across a dozen registry tables, burns serials and decommissions the source. Undoing
 * that by hand is slow and easy to get wrong — hence this.
 *
 * It deletes ONLY rows keyed to the file numbers this duplex created, restores the
 * source file, and returns the duplex to `in_land`. It never touches a file number the
 * duplex did not produce.
 *
 *   php artisan duplex:rollback DPX-2026-0007
 *   php artisan duplex:rollback --all --dry-run
 */
class DuplexRollback extends Command
{
    protected $signature = 'duplex:rollback
                            {duplex? : Duplex ID, e.g. DPX-2026-0007}
                            {--all : Roll back every committed duplex}
                            {--dry-run : Report what would be removed, change nothing}';

    protected $description = 'Undo a duplex commissioning so it can be commissioned again';

    /**
     * The tables a Change of Purpose RENAMES rather than adds to. A file that only
     * exists in them under a new name must be renamed back, never deleted.
     */
    private const RENAME_TABLES = ['fileNumber', 'file_indexings', 'mls_file_no'];

    /** Table => the column holding a file number, in deletion order. */
    private const TABLES = [
        'pra'               => 'fileno',
        'oss_applications'  => 'file_no',
        'mls_file_no'       => 'full_file_number',
        'file_tracker'      => 'file_number',
        'file_indexings'    => 'file_number',
        'fileNumber'        => 'mlsfNo',
        'customers_staging' => 'file_number',
        'entities_staging'  => 'file_number',
    ];

    public function handle(DuplexSummaryService $summaries): int
    {
        $dry = (bool) $this->option('dry-run');

        $targets = $this->option('all')
            ? DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_COMMITTED)->get()
            : DuplexParcelUpdate::where('duplex_id', $this->argument('duplex'))->get();

        if ($targets->isEmpty()) {
            $this->warn('Nothing to roll back.');
            return self::SUCCESS;
        }

        foreach ($targets as $duplex) {
            $this->rollback($duplex, $summaries, $dry);
        }

        return self::SUCCESS;
    }

    private function rollback(DuplexParcelUpdate $duplex, DuplexSummaryService $summaries, bool $dry): void
    {
        $conn = DB::connection('sqlsrv');
        $plan = $summaries->build($duplex);

        $sources = array_values(array_filter($plan['sources']));

        // A CARRIED file was not created by this duplex — a stage let it travel on
        // under its own registry number, which is why that number is what it reports.
        // Deleting those would delete live registry files the duplex merely read.
        $created = collect($plan['stages'])
            ->flatMap(fn ($s) => collect($s['files'])
                ->reject(fn ($f) => !empty($f['carried']))
                ->pluck('final'))
            ->filter()->unique()->values()->all();

        // Belt and braces: a source file is never something this run created.
        $created = array_values(array_diff($created, $sources));

        /*
         * A Change of Purpose does NOT mint a row — the commissioning engine renames
         * the existing one in place (fileNumber.mlsfNo, file_indexings.file_number,
         * mls_file_no.full_file_number) and records the old number only in
         * decommissioned_files. The "new" file therefore IS the old file's row.
         *
         * Deleting it would destroy the original registry record, which is how
         * COM-RC-1982-420 was lost. Those files are renamed back instead.
         *
         * Detected from the audit row the engine leaves behind: it names the old
         * number and points at the new one as its successor.
         */
        $renamedBack = [];

        foreach ($created as $no) {
            $audit = $conn->table('decommissioned_files')
                ->where('successor_file_no', $no)
                ->where('decommissioning_reason', 'LIKE', 'Change of Purpose%')
                ->orderByDesc('id')
                ->first();

            if ($audit && $audit->file_no) {
                $renamedBack[$no] = $audit->file_no;
            }
        }

        // Everything else was genuinely minted and can go.
        $deletable = array_values(array_diff($created, array_keys($renamedBack)));

        $this->line('');
        $this->info($duplex->duplex_id . '  (' . $duplex->status . ')');

        if (!$created) {
            $this->warn('  nothing was commissioned — skipped');
            return;
        }

        $this->line('  created : ' . implode(', ', $created));
        $this->line('  sources : ' . implode(', ', $sources));

        foreach ($renamedBack as $new => $old) {
            $this->line("  renamed  : {$new} is {$old} under a new name — it will be renamed back, not deleted");
        }

        if ($dry) {
            foreach (self::TABLES as $table => $col) {
                $list = in_array($table, self::RENAME_TABLES, true) ? $deletable : $created;
                $n = $conn->table($table)->whereIn($col, $list)->count();
                if ($n) $this->line(sprintf('    would delete %-22s %d', $table, $n));
            }

            $n = $conn->table('related_file_number')
                ->whereIn('file_number', $created)
                ->orWhereIn('related_fileno', $created)
                ->count();
            if ($n) $this->line(sprintf('    would delete %-22s %d', 'related_file_number', $n));

            return;
        }

        $conn->beginTransaction();
        try {
            $deleted = [];

            foreach (self::TABLES as $table => $col) {
                // The three tables a Change of Purpose renames in place must never be
                // deleted for a renamed file — that row is the original.
                $list = in_array($table, self::RENAME_TABLES, true) ? $deletable : $created;
                $deleted[$table] = $conn->table($table)->whereIn($col, $list)->delete();
            }

            // A Change of Purpose is SUPPOSED to rename in place, which is why the new
            // number is renamed back rather than deleted. It does not always: on
            // DPX-2026-0009 every one of the four renames left the original row alive
            // and added a second one, so both numbers were in the registry at once —
            // the retired one still active, which is its own problem.
            //
            // So the rename is not assumed. Each table is asked whether the old number
            // is still there: if it is, the new row is a DUPLICATE and is deleted; if it
            // is not, the rename really happened and is undone. Getting this wrong in
            // the first direction is what made the rollback fail outright — renaming
            // onto a number that still exists trips the unique index on
            // file_indexings.file_number.
            foreach ($renamedBack as $new => $old) {
                // The number being renamed BACK TO may itself be one this duplex
                // created — a subdivision child that a later Change of Purpose
                // renamed. Restoring it would re-create a file number that is supposed
                // to disappear with the rest of the run, and the bulk delete above has
                // already removed it. Only a rename back onto a PRE-EXISTING file is a
                // restoration; anything else is just another row to delete.
                if (in_array($old, $created, true)) {
                    foreach ([
                        ['fileNumber',     'mlsfNo'],
                        ['file_indexings', 'file_number'],
                        ['mls_file_no',    'full_file_number'],
                    ] as [$table, $col]) {
                        $deleted[$table . ' (renamed child)'] = ($deleted[$table . ' (renamed child)'] ?? 0)
                            + $conn->table($table)->where($col, $new)->delete();
                    }

                    continue;
                }

                $landUse = \App\Support\FileNumberLandUse::codeFor($old);

                $back = ['file_number' => $old, 'related_fileno' => null, 'updated_at' => now()];
                if ($landUse !== '') {
                    $back['land_use_type'] = $landUse;
                }

                foreach ([
                    ['fileNumber',     'mlsfNo',            ['mlsfNo' => $old, 'updated_at' => now()]],
                    ['file_indexings', 'file_number',       $back],
                    ['mls_file_no',    'full_file_number',  ['full_file_number' => $old, 'updated_at' => now()]],
                ] as [$table, $col, $rename]) {
                    $originalSurvives = $conn->table($table)->where($col, $old)->exists();

                    if ($originalSurvives) {
                        $deleted[$table . ' (duplicate)'] = ($deleted[$table . ' (duplicate)'] ?? 0)
                            + $conn->table($table)->where($col, $new)->delete();
                        continue;
                    }

                    $conn->table($table)->where($col, $new)->update($rename);
                }

                $deleted['renamed back'] = ($deleted['renamed back'] ?? 0) + 1;
            }

            foreach (['primary_file_number', 'mlsFNo'] as $col) {
                $deleted['PropID_Master'] = ($deleted['PropID_Master'] ?? 0)
                    + $conn->table('PropID_Master')->whereIn($col, $created)->delete();
            }

            // The lineage links the commissioning wrote — one row per source the new
            // file was linked to, plus the extension's link back to the file it
            // extended. Missed until now, and they are not inert: Legal Search reads
            // related_file_number directly, so a rolled-back run left "Related File of
            // …" rows on the SOURCE file's timeline pointing at file numbers that no
            // longer exist anywhere else in the registry.
            //
            // Matched on BOTH endpoints: the row is stored one way round for a merger
            // source and the other way round for an extension.
            $deleted['related_file_number'] = $conn->table('related_file_number')
                ->where(function ($q) use ($created) {
                    $q->whereIn('file_number', $created)
                        ->orWhereIn('related_fileno', $created);
                })
                ->delete();

            // Both the source and any child a later stage replaced were archived.
            $deleted['decommissioned_files'] = $conn->table('decommissioned_files')
                ->whereIn('file_no', array_merge($created, $sources))->delete();

            // The application rows the commit materialised, found by their duplex tag.
            foreach ([
                'plot_subdivision_applications',
                'plot_merger_applications',
                'plot_separation_applications',
                'plot_extension_applications',
                'change_of_purpose_applications',
            ] as $table) {
                $deleted[$table] = $conn->table($table)
                    ->where('remarks', 'LIKE', '%' . $duplex->duplex_id . '%')->delete();
            }

            // Bring the source file back to life.
            // fileNumber carries TWO decommissioning stamps — the legacy
            // decommissioning_* trio and the decommissioned_at/by pair the current
            // engine writes. Clearing only the first left the file looking retired.
            $conn->table('fileNumber')->whereIn('mlsfNo', $sources)->update([
                'is_decommissioned'      => 0,
                'decommissioning_date'   => null,
                'decommissioning_reason' => null,
                'decommissioned_at'      => null,
                'decommissioned_by'      => null,
                'successor_file_no'      => null,
                'updated_at'             => now(),
            ]);

            $conn->table('file_indexings')->whereIn('file_number', $sources)->update([
                'is_decommissioned'      => 0,
                'decommissioned_at'      => null,
                // Cleared with the rest of the stamp. Left behind, it named whoever ran
                // the commissioning as the officer who decommissioned a file that is
                // demonstrably still active - the fileNumber side already clears it.
                'decommissioned_by'      => null,
                'decommissioning_reason' => null,
                'successor_file_no'      => null,
                'updated_at'             => now(),
            ]);

            $conn->commit();

            foreach ($deleted as $table => $n) {
                if ($n) $this->line(sprintf('    deleted %-30s %d', $table, $n));
            }
        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->error('  FAILED, rolled back: ' . $e->getMessage());
            return;
        }

        // The duplex's own rows: clear the real numbers, keep the plan and the
        // holding numbers so it can be commissioned again unchanged.
        DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->where('role', '!=', DuplexParcelUpdateFile::ROLE_SOURCE)
            ->update(['final_file_no' => null]);

        DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->where('role', DuplexParcelUpdateFile::ROLE_RESULT)
            ->update(['role' => DuplexParcelUpdateFile::ROLE_HOLDING]);

        $duplex->update([
            'status'       => DuplexParcelUpdate::STATUS_IN_LAND,
            'committed_at' => null,
            'committed_by' => null,
        ]);

        $this->info('    -> ' . $duplex->duplex_id . ' is in_land again, ready to commission');
    }
}
