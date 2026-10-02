<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the old-file-number mapping whole.
 *
 * OldFileNumberService mirrors onto file_indexings at the moment the old number is
 * entered — but a file is often indexed AFTER its number was generated, and those
 * rows are created with old_fileno null. This command closes that gap, and also
 * seeds the ledger from the mls_file_no.old_fileno values that predate the table.
 *
 * Re-runnable and additive: it never clears a value, only fills one in.
 */
class SyncOldFileNumbers extends Command
{
    protected $signature = 'old-fileno:sync
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Seed old_file_numbers from mls_file_no and map old numbers onto file_indexings.old_fileno';

    public function handle(): int
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('old_file_numbers')) {
            $this->error('old_file_numbers does not exist. Run the migration first.');

            return self::FAILURE;
        }

        if (!$schema->hasColumn('file_indexings', 'old_fileno')) {
            $this->error('file_indexings.old_fileno does not exist. Run the migration first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $seeded  = $this->seedLedgerFromMlsFileNo($dryRun);
        $linked  = $this->linkIndexingIds($dryRun);
        $mirrored = $this->mirrorOntoIndexings($dryRun);

        $this->newLine();
        $this->table(['Step', 'Rows'], [
            ['Ledger rows seeded from mls_file_no.old_fileno', $seeded],
            ['Ledger rows given a file_indexing_id', $linked],
            ['file_indexings.old_fileno filled', $mirrored],
        ]);

        if ($dryRun) {
            $this->warn('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * Every non-empty mls_file_no.old_fileno that has no ledger row yet.
     */
    private function seedLedgerFromMlsFileNo(bool $dryRun): int
    {
        if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
            return 0;
        }

        $rows = DB::connection('sqlsrv')
            ->table('mls_file_no')
            ->selectRaw('LTRIM(RTRIM(full_file_number)) AS file_number, LTRIM(RTRIM(old_fileno)) AS old_file_number')
            ->whereNotNull('old_fileno')
            ->whereRaw("LTRIM(RTRIM(old_fileno)) <> ''")
            ->whereNotNull('full_file_number')
            ->whereRaw("LTRIM(RTRIM(full_file_number)) <> ''")
            ->whereRaw('UPPER(LTRIM(RTRIM(old_fileno))) <> UPPER(LTRIM(RTRIM(full_file_number)))')
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            $exists = DB::connection('sqlsrv')
                ->table('old_file_numbers')
                ->where('file_number', $row->file_number)
                ->where('old_file_number', $row->old_file_number)
                ->exists();

            if ($exists) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            DB::connection('sqlsrv')->table('old_file_numbers')->insert([
                'file_number'     => $row->file_number,
                'old_file_number' => $row->old_file_number,
                'source'          => 'import',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return $count;
    }

    /**
     * Ledger rows written before the file was indexed carry a null file_indexing_id.
     */
    private function linkIndexingIds(bool $dryRun): int
    {
        $rows = DB::connection('sqlsrv')
            ->table('old_file_numbers')
            ->whereNull('file_indexing_id')
            ->get(['id', 'file_number']);

        $count = 0;

        foreach ($rows as $row) {
            $indexingId = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$row->file_number])
                ->orderBy('id')
                ->value('id');

            if (!$indexingId) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            DB::connection('sqlsrv')
                ->table('old_file_numbers')
                ->where('id', $row->id)
                ->update(['file_indexing_id' => $indexingId, 'updated_at' => now()]);
        }

        return $count;
    }

    /**
     * The mirror itself. Newest ledger entry wins when a file carries several.
     */
    private function mirrorOntoIndexings(bool $dryRun): int
    {
        $rows = DB::connection('sqlsrv')
            ->table('old_file_numbers')
            ->orderBy('id')
            ->get(['file_number', 'old_file_number']);

        // Later rows overwrite earlier ones, so the highest id per file wins.
        $latest = [];
        foreach ($rows as $row) {
            $latest[strtoupper($row->file_number)] = $row;
        }

        $count = 0;

        foreach ($latest as $row) {
            $affected = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$row->file_number])
                ->where(function ($q) use ($row) {
                    $q->whereNull('old_fileno')
                        ->orWhereRaw('UPPER(LTRIM(RTRIM(old_fileno))) <> UPPER(?)', [$row->old_file_number]);
                })
                ->count();

            if ($affected === 0) {
                continue;
            }

            $count += $affected;

            if ($dryRun) {
                continue;
            }

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$row->file_number])
                ->update(['old_fileno' => $row->old_file_number]);
        }

        return $count;
    }
}
