<?php

namespace App\Console\Commands;

use App\Support\RecertificationFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stamp "Recertification" onto every RC file already commissioned through KLAES.
 *
 * An RC file carries its nature in the number (IND-RC-…, CON-RES-RC-…), but nothing wrote
 * that to `mls_file_no.source` — the column recorded the allocation route the officer used,
 * so recertifications sat there as "Direct Allocation" (and converted ones as "Conversion").
 * Two things read that column and got the wrong answer: the "(File Type)" bracket on the
 * commissioning sheet, and the Legal Search Root of Title rule, which treats a Direct
 * Allocation as its own root. A recertification is never its own root — the file it was
 * raised from is.
 *
 * Only `mls_file_no` is touched. The Land FC table derives the label from the number for
 * every RC file including the legacy ones, which have no such row and no source column of
 * their own, so there is nothing to write for them.
 *
 *   php artisan recert:backfill-source --dry-run
 *   php artisan recert:backfill-source
 *   php artisan recert:backfill-source --file=IND-RC-2026-1
 *
 * Idempotent: a row already reading "Recertification" is left alone.
 */
class BackfillRecertificationSource extends Command
{
    protected $signature = 'recert:backfill-source
                            {--dry-run : List what would change without writing}
                            {--file=* : Only this full file number (repeatable)}';

    protected $description = 'Set mls_file_no.source to "Recertification" for every RC-numbered file';

    public function handle(): int
    {
        $conn = DB::connection('sqlsrv');
        $dryRun = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', (array) $this->option('file')));

        $query = $conn->table('mls_file_no')
            ->select('id', 'full_file_number', 'source')
            ->orderBy('id');

        if (!empty($only)) {
            $query->whereIn('full_file_number', $only);
        } else {
            // Cheap SQL narrowing; the token test below is what actually decides, so a
            // number like "ARC-2020-1" that survives the LIKE is still rejected.
            $query->where('full_file_number', 'like', '%RC%');
        }

        $rows = $query->get();

        $changed = 0;
        $already = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!RecertificationFile::isRecertFileNumber($row->full_file_number)) {
                $skipped++;
                continue;
            }

            $current = trim((string) $row->source);

            if ($current === RecertificationFile::SOURCE_LABEL) {
                $already++;
                continue;
            }

            $this->line(sprintf(
                '  %-24s %s -> %s',
                $row->full_file_number,
                $current === '' ? '(blank)' : $current,
                RecertificationFile::SOURCE_LABEL
            ));

            if (!$dryRun) {
                $conn->table('mls_file_no')
                    ->where('id', $row->id)
                    ->update([
                        'source' => RecertificationFile::SOURCE_LABEL,
                        'updated_at' => now(),
                    ]);
            }

            $changed++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d file(s); %d already stamped, %d non-RC number(s) skipped.',
            $dryRun ? 'Would update' : 'Updated',
            $changed,
            $already,
            $skipped
        ));

        if ($dryRun && $changed > 0) {
            $this->comment('Re-run without --dry-run to write these.');
        }

        return self::SUCCESS;
    }
}
