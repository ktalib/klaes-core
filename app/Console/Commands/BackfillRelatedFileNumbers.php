<?php

namespace App\Console\Commands;

use App\Services\RelatedFileNumberRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register the related file numbers that only ever reached file_indexings.related_fileno.
 *
 * File Indexing never wrote to related_file_number, and MLS commissioning registered only the
 * typed related-files widget, so every plain related number saved since the 2026-05-29 seed
 * migration is invisible to Legal Search and the Related File Numbers page. This replays those
 * rows through RelatedFileNumberRegistrar, which is the same writer the live paths now use.
 *
 * Idempotent: a number already registered for the indexing row is updated in place, never
 * duplicated, and nothing is ever deleted (prune is off).
 *
 *   php artisan related-files:backfill --dry-run
 *   php artisan related-files:backfill --since=2026-05-29
 *
 * Or scoped to a single file, which is the safe way to repair one reported case
 * without replaying the whole backlog on a live database:
 *
 *   php artisan related-files:backfill --file="KNML 1200" --dry-run
 *   php artisan related-files:backfill --file="KNML 1200"
 *
 * --file matches file_indexings.file_number. A KANGIS alias and its land file are
 * SEPARATE indexing rows, and the related_fileno payload usually sits on the
 * KANGIS row, so pass the number that actually carries the link (both is fine).
 */
class BackfillRelatedFileNumbers extends Command
{
    protected $signature = 'related-files:backfill
                            {--dry-run : Report what would be written without touching the register}
                            {--since= : Only file_indexings rows created on/after this date (Y-m-d)}
                            {--file=* : Only this file number (repeatable). Registers one file rather than the whole backlog}
                            {--id=* : Only this file_indexings id (repeatable)}
                            {--limit=0 : Stop after this many indexing rows (0 = no limit)}
                            {--chunk=500 : Rows read per batch}';

    protected $description = 'Backfill related_file_number from file_indexings.related_fileno for rows that were never registered';

    public function handle(RelatedFileNumberRegistrar $registrar): int
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable(RelatedFileNumberRegistrar::TABLE)) {
            $this->error('related_file_number table not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $chunk  = max(50, (int) $this->option('chunk'));

        $query = $conn->table('file_indexings')
            ->select('id', 'file_number', 'file_title', 'prop_id', 'location', 'related_fileno', 'created_at')
            ->whereNotNull('related_fileno')
            // '[]' and '""' are the empty shapes the JSON column takes; LEN() > 2 skips both.
            ->whereRaw('LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 2')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from(RelatedFileNumberRegistrar::TABLE . ' AS t')
                    ->whereColumn('t.source_id', 'file_indexings.id')
                    ->where('t.source_table', 'file_indexings');
            })
            ->orderBy('id');

        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', $since);
        }

        // Scope to specific rows. Deliberately narrowing filters: with either of
        // these set the command touches nothing else, so a single reported file can
        // be repaired without replaying thousands of rows on a live database.
        $files = array_values(array_filter(array_map('trim', (array) $this->option('file'))));
        $ids   = array_values(array_filter(array_map('intval', (array) $this->option('id'))));

        if ($files) {
            $query->whereIn('file_number', $files);
            $this->line('Scoped to file number(s): ' . implode(', ', $files));
        }

        if ($ids) {
            $query->whereIn('id', $ids);
            $this->line('Scoped to file_indexings id(s): ' . implode(', ', $ids));
        }

        // A scoped run that matches nothing is worth saying out loud: silence would
        // read as "already registered" when it may mean the number was mistyped.
        if (($files || $ids) && (clone $query)->count() === 0) {
            $anyRow = $conn->table('file_indexings')
                ->when($files, fn ($q) => $q->whereIn('file_number', $files))
                ->when($ids, fn ($q) => $q->whereIn('id', $ids))
                ->first(['id', 'file_number', 'related_fileno']);

            if (!$anyRow) {
                $this->warn('No file_indexings row matches that filter — check the number.');
            } elseif (trim((string) $anyRow->related_fileno) === ''
                || strlen(trim((string) $anyRow->related_fileno)) <= 2) {
                $this->warn(sprintf(
                    'Row %s (%s) has no related_fileno payload, so there is nothing to register.',
                    $anyRow->id, $anyRow->file_number
                ));
            } else {
                $this->info(sprintf(
                    'Row %s (%s) is already registered in %s — nothing to do.',
                    $anyRow->id, $anyRow->file_number, RelatedFileNumberRegistrar::TABLE
                ));
            }

            return self::SUCCESS;
        }

        $total = (clone $query)->count();
        $this->info(($dryRun ? '[dry-run] ' : '') . "Indexing rows to register: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $links     = 0;
        $skipped   = 0;

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        // Walked with an id cursor, not an offset: registered rows stop matching the
        // whereNotExists, so an offset-based chunk would step over unprocessed rows.
        $lastId = 0;
        while (true) {
            $rows = (clone $query)->where('id', '>', $lastId)->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = $row->id;

                $decoded = json_decode((string) $row->related_fileno, true);
                if (!is_array($decoded)) {
                    // Legacy bare string (the 2026-07-03 normalisation missed rows added since).
                    $decoded = [(string) $row->related_fileno];
                }

                $wanted = $registrar->normalizeInput($decoded);
                if (empty($wanted)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (!$dryRun) {
                    $registrar->sync(
                        (int) $row->id,
                        $row->file_number,
                        $row->file_title,
                        $row->prop_id ?? null,
                        $decoded,
                        ['location' => $row->location ?? null]
                    );
                }

                $links += count($wanted);
                $processed++;
                $bar->advance();

                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[dry-run] ' : '') . "Indexing rows registered: {$processed}");
        $this->info(($dryRun ? '[dry-run] ' : '') . "Register rows written: {$links}");

        if ($skipped > 0) {
            $this->warn("Rows skipped (related_fileno held no usable number): {$skipped}");
        }

        return self::SUCCESS;
    }
}
