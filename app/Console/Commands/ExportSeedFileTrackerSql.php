<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dumps a tagged file_tracker batch as a plain INSERT script, for environments where
 * artisan cannot be run and the rows have to go in through SSMS instead.
 *
 * The script is self-contained: it adds the seed_tag column if the target database has
 * not had the migration, clears any previous rows carrying the same tag, and inserts the
 * batch. `id` is left out so the target assigns its own identity values.
 *
 *   php artisan file-tracker:export-seed-sql
 *   php artisan file-tracker:export-seed-sql --tag=FAKE-LOGS --output=C:/Users/admin/Downloads/seed.sql
 */
class ExportSeedFileTrackerSql extends Command
{
    protected $signature = 'file-tracker:export-seed-sql
        {--tag=FAKE-LOGS : Which seed_tag batch to export}
        {--output= : Destination .sql path (default storage/app/file_tracker_<tag>_<date>.sql)}
        {--batch=50 : Rows per INSERT statement}
        {--no-delete : Omit the DELETE that clears the same tag on the target first}';

    protected $description = 'Export a tagged file_tracker batch as a runnable SQL INSERT script';

    private const CONNECTION = 'sqlsrv';

    /** Columns written by file-tracker:seed-fake-logs; `id` is deliberately absent. */
    private const COLUMNS = [
        'tracking_id', 'file_number', 'file_title', 'file_type', 'priority',
        'created_by', 'created_by_name', 'department', 'description', 'status',
        'date_created', 'date_requested', 'deadline', 'timeline_days', 'movement_log',
        'current_office_code', 'current_office_name', 'total_offices', 'completed_offices', 'notes',
        'created_at', 'updated_at', 'receiving_office_code', 'receiving_office_name', 'receiving_officer_id',
        'receiving_officer_name', 'assignment_status', 'assignment_accepted_at', 'origin_office_code', 'origin_office_name',
        'origin_office_department', 'module', 'printed', 'registry_code', 'num_pages',
        'returned_num_pages', 'request_purpose_id', 'request_purpose_name', 'file_request_type', 'seed_tag',
    ];

    /** Emitted unquoted; everything else goes out as an N'' literal. */
    private const NUMERIC = [
        'timeline_days', 'total_offices', 'completed_offices', 'receiving_officer_id',
        'printed', 'num_pages', 'returned_num_pages', 'request_purpose_id',
    ];

    public function handle(): int
    {
        $tag = trim((string) $this->option('tag'));
        if ($tag === '') {
            $this->error('--tag cannot be empty.');
            return self::FAILURE;
        }

        $total = DB::connection(self::CONNECTION)->table('file_tracker')->where('seed_tag', $tag)->count();
        if ($total === 0) {
            $this->error("No file_tracker rows carry seed_tag '{$tag}'.");
            return self::FAILURE;
        }

        $path = $this->option('output')
            ?: storage_path('app/file_tracker_' . strtolower($tag) . '_' . date('Y-m-d') . '.sql');

        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create directory {$dir}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'w');
        if (! $handle) {
            $this->error("Cannot write to {$path}");
            return self::FAILURE;
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $columnList = '[' . implode('], [', self::COLUMNS) . ']';

        fwrite($handle, $this->header($tag, $total));

        $written = 0;
        $rowsInBatch = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::connection(self::CONNECTION)->table('file_tracker')
            ->where('seed_tag', $tag)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($handle, $columnList, $batchSize, &$written, &$rowsInBatch, $bar) {
                foreach ($rows as $row) {
                    if ($rowsInBatch === 0) {
                        fwrite($handle, "INSERT INTO [file_tracker] ({$columnList}) VALUES\n");
                    }

                    $values = [];
                    foreach (self::COLUMNS as $column) {
                        $values[] = $this->literal($column, $row->{$column} ?? null);
                    }

                    $rowsInBatch++;
                    $written++;

                    $terminator = ($rowsInBatch >= $batchSize) ? ";\nGO\n\n" : ",\n";
                    fwrite($handle, '(' . implode(', ', $values) . ')' . $terminator);

                    if ($rowsInBatch >= $batchSize) {
                        $rowsInBatch = 0;
                    }

                    $bar->advance();
                }
            });

        // Close a part-filled final batch.
        if ($rowsInBatch > 0) {
            fseek($handle, -2, SEEK_END);   // drop the trailing ",\n"
            fwrite($handle, ";\nGO\n");
        }

        fwrite($handle, $this->footer($tag));
        fclose($handle);

        $bar->finish();
        $this->newLine(2);

        $this->info("Wrote {$written} INSERT row(s) to {$path} (" . $this->humanSize(filesize($path)) . ').');
        $this->line('Open it in SSMS with File > Open > File, then Execute — do not paste a script this size into a query window.');

        return self::SUCCESS;
    }

    private function header(string $tag, int $total): string
    {
        $sql = "-- file_tracker demo batch, seed_tag = '{$tag}'\n"
            . '-- ' . $total . " rows, generated " . date('Y-m-d H:i:s') . "\n"
            . "-- Generated by: php artisan file-tracker:export-seed-sql\n"
            . "-- Remove again at any time with: DELETE FROM [file_tracker] WHERE [seed_tag] = N'{$tag}';\n\n"
            . "SET NOCOUNT ON;\nSET XACT_ABORT ON;\nGO\n\n"
            . "-- The column may not exist yet on this database (migration 2026_07_31_090000).\n"
            . "IF COL_LENGTH('file_tracker', 'seed_tag') IS NULL\n"
            . "BEGIN\n"
            . "    ALTER TABLE [file_tracker] ADD [seed_tag] NVARCHAR(64) NULL;\n"
            . "END\nGO\n\n"
            . "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'file_tracker_seed_tag_index' AND object_id = OBJECT_ID('file_tracker'))\n"
            . "BEGIN\n"
            . "    CREATE INDEX [file_tracker_seed_tag_index] ON [file_tracker] ([seed_tag]);\n"
            . "END\nGO\n\n";

        if (! $this->option('no-delete')) {
            $sql .= "-- Clears a previous run of the same tag. Untagged (real) rows are untouched.\n"
                . "DELETE FROM [file_tracker] WHERE [seed_tag] = N'{$tag}';\nGO\n\n";
        }

        return $sql;
    }

    private function footer(string $tag): string
    {
        return "\nSELECT COUNT(*) AS seeded_rows FROM [file_tracker] WHERE [seed_tag] = N'{$tag}';\nGO\n";
    }

    /**
     * SQL Server escapes a quote by doubling it. N'' keeps the Hausa names and any
     * non-ASCII in the movement JSON intact regardless of the target's collation.
     */
    private function literal(string $column, $value): string
    {
        if ($value === null || $value === '') {
            return in_array($column, self::NUMERIC, true) || $value === null ? 'NULL' : "N''";
        }

        if (in_array($column, self::NUMERIC, true)) {
            return (string) (int) $value;
        }

        return "N'" . str_replace("'", "''", (string) $value) . "'";
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
