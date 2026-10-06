<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Copies each indexed file's shelf/rack from the print-label batch tables of every
 * registry onto file_indexings.shelf_location, the latest label winning.
 *
 * The per-batch sync job (SyncShelfLocationToFileIndexings) only covers MLS and
 * matches on file_indexing_id, so labels printed before the file was indexed
 * (placeholder id <= 0) and every KANGIS / SLTR / ST / DCIV label never reached
 * file_indexings. This command matches on the id and on each of the file's own
 * number columns, and is safe to re-run.
 *
 * Every change is written to storage/app/shelf-backfill/<timestamp>.csv
 * (id, file_number, old, new) before the update, so a run can be undone.
 */
class BackfillShelfLocationFromLabels extends Command
{
    protected $signature = 'shelf:backfill-from-labels
        {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill file_indexings.shelf_location from every registry\'s print-label batches (latest label wins)';

    private const LABEL_TABLES = [
        'kangis_print_label_batch_items',
        'sltr_print_label_batch_items',
        'st_print_label_batch_items',
        'cadastral_print_label_batch_items',
        'dciv_print_label_batch_items',
        'print_label_batch_items',
    ];

    /** The file's own number columns - never related / successor numbers. */
    private const NUMBER_COLUMNS = [
        'file_number',
        'kangis_fileno_placeholder',
        'new_kangis_file_no',
        'kangis_file_no',
        'mls_file_no',
        'temp_file_no',
        'st_fillno',
        'dciv_fileno',
    ];

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');
        $dryRun = (bool) $this->option('dry-run');

        // 1. Latest label per file_indexing_id and per normalised file number.
        $byId = [];
        $byNo = [];
        foreach (self::LABEL_TABLES as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            $count = 0;
            $rows = $db->table($table)
                ->select('id', 'file_indexing_id', 'file_number', 'shelf_location', 'created_at')
                ->whereNotNull('shelf_location')
                ->where('shelf_location', '<>', '')
                ->cursor();
            foreach ($rows as $row) {
                $shelf = trim((string) $row->shelf_location);
                if ($shelf === '' || stripos($shelf, 'N/A') !== false) {
                    continue;
                }
                $label = [
                    'shelf' => $shelf,
                    'no' => $this->normalizeKey($row->file_number),
                    // created_at orders labels across tables; id breaks ties within one.
                    'rank' => sprintf('%s|%012d', (string) $row->created_at, (int) $row->id),
                ];
                if ((int) $row->file_indexing_id > 0) {
                    $this->keepLatest($byId, (int) $row->file_indexing_id, $label);
                }
                if ($label['no'] !== '') {
                    $this->keepLatest($byNo, $label['no'], $label);
                }
                $count++;
            }
            $this->line(sprintf('  %-34s %7d labels', $table, $count));
        }

        // 2. Compare against every indexed file.
        $columns = array_values(array_intersect(
            self::NUMBER_COLUMNS,
            Schema::connection('sqlsrv')->getColumnListing('file_indexings')
        ));
        $changes = [];
        $filled = 0;
        $replaced = 0;

        $files = $db->table('file_indexings')
            ->select(array_merge(['id', 'shelf_location'], array_diff($columns, ['file_number']), ['file_number']))
            ->orderBy('id')
            ->cursor();
        foreach ($files as $file) {
            $keys = [];
            foreach ($columns as $column) {
                $key = $this->normalizeKey($file->{$column} ?? null);
                if ($key !== '') {
                    $keys[$key] = true;
                }
            }

            $best = null;
            // An id match counts only when the label's number belongs to this file,
            // so a label mis-linked to the wrong file_indexing_id is ignored.
            $idLabel = $byId[(int) $file->id] ?? null;
            if ($idLabel && ($idLabel['no'] === '' || isset($keys[$idLabel['no']]))) {
                $best = $idLabel;
            }
            foreach (array_keys($keys) as $key) {
                if (isset($byNo[$key]) && ($best === null || strcmp($byNo[$key]['rank'], $best['rank']) > 0)) {
                    $best = $byNo[$key];
                }
            }
            if ($best === null) {
                continue;
            }

            $stored = trim((string) $file->shelf_location);
            if ($stored === $best['shelf']) {
                continue;
            }
            $stored === '' || $stored === '-' ? $filled++ : $replaced++;
            $changes[] = [(int) $file->id, (string) $file->file_number, $file->shelf_location, $best['shelf']];
        }

        $this->info(sprintf('Blank shelf to fill: %d   Older shelf to replace: %d   Total: %d', $filled, $replaced, count($changes)));

        if ($dryRun || empty($changes)) {
            if ($dryRun) {
                foreach (array_slice($changes, 0, 10) as [$id, $no, $old, $new]) {
                    $this->line(sprintf('  #%d %s: %s -> %s', $id, $no, $old === null ? 'NULL' : "'{$old}'", $new));
                }
                $this->warn('Dry run - nothing written.');
            }
            return 0;
        }

        // 3. Undo log first, then update in chunks.
        $undoPath = 'shelf-backfill/' . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['id', 'file_number', 'old_shelf_location', 'new_shelf_location']);
        foreach ($changes as $change) {
            fputcsv($handle, [$change[0], $change[1], $change[2] ?? 'NULL', $change[3]]);
        }
        rewind($handle);
        Storage::disk('local')->put($undoPath, stream_get_contents($handle));
        fclose($handle);
        $this->line('Undo log: ' . Storage::disk('local')->path($undoPath));

        $updated = 0;
        foreach (array_chunk($changes, 500) as $chunk) {
            $cases = [];
            $bindings = [];
            foreach ($chunk as [$id, , , $new]) {
                $cases[] = 'WHEN ? THEN ?';
                array_push($bindings, $id, $new);
            }
            $ids = array_column($chunk, 0);
            $updated += $db->update(
                'UPDATE [dbo].[file_indexings] SET [shelf_location] = CASE [id] ' . implode(' ', $cases) . ' END'
                . ' WHERE [id] IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                array_merge($bindings, $ids)
            );
        }

        $this->info("Updated {$updated} file_indexings rows.");

        return 0;
    }

    private function keepLatest(array &$map, $key, array $label): void
    {
        if (!isset($map[$key]) || strcmp($label['rank'], $map[$key]['rank']) > 0) {
            $map[$key] = $label;
        }
    }

    /** Same normalisation as IndexedFileTableController::normalizeFileNoKey(). */
    private function normalizeKey($value): string
    {
        $normalized = strtoupper(trim((string) $value));
        if ($normalized === '' || $normalized === '-' || $normalized === 'NONE') {
            return '';
        }

        return preg_replace('/[\s\-\/\._]+/', '', $normalized);
    }
}
