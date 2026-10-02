<?php

namespace App\Console\Commands;

use App\Services\ShelfRackLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Loads the "FileNo Combination" rack workbooks in docs/data/shalf_racks into
 * shelf_rack_ranges.
 *
 * Two workbook quirks drive the parsing here:
 *  - Several _2_ sheets hold TWO tables side by side (columns A-G and again from
 *    column K/L/M onward). The second block has no header row, so blocks are
 *    located structurally: a column c starts a block if some row has a number in
 *    c and a rack/shelf label like "A29" in c+4.
 *  - Serial ranges are inconsistently spaced ("701 -800"), so the raw text is
 *    kept alongside the parsed from/to.
 */
class ImportShelfRackRanges extends Command
{
    protected $signature = 'shelf-racks:import
                            {--path= : Directory of .xlsx workbooks (default docs/data/shalf_racks)}
                            {--fresh : Truncate shelf_rack_ranges before importing}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import rack/shelf file-number ranges from the FileNo Combination workbooks';

    private const HEADER_WIDTH = 7;

    public function handle()
    {
        $path = $this->option('path') ?: base_path('docs/data/shalf_racks');

        if (!is_dir($path)) {
            $this->error("Directory does not exist: {$path}");
            return Command::FAILURE;
        }

        $files = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.xlsx');
        if (empty($files)) {
            $this->warn("No .xlsx workbooks found in {$path}");
            return Command::SUCCESS;
        }

        $labels = $this->shelfLabelIds();
        $rows = [];

        foreach ($files as $file) {
            $parsed = $this->parseWorkbook($file);
            $this->line(sprintf('%-42s %4d rows', basename($file), count($parsed)));
            $rows = array_merge($rows, $parsed);
        }

        foreach ($rows as &$row) {
            $row['shelf_label_id'] = $labels[strtoupper($row['rack_shelf'])] ?? null;
        }
        unset($row);

        $this->report($rows);

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing written.');
            return Command::SUCCESS;
        }

        if ($this->option('fresh')) {
            DB::connection('sqlsrv')->table('shelf_rack_ranges')->delete();
            $this->warn('Cleared existing shelf_rack_ranges rows.');
        }

        $written = 0;
        $now = now();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::connection('sqlsrv')->transaction(function () use ($chunk, $now, &$written) {
                foreach ($chunk as $row) {
                    DB::connection('sqlsrv')->table('shelf_rack_ranges')->updateOrInsert(
                        [
                            'source_file' => $row['source_file'],
                            'rack' => $row['rack'],
                            'shelf' => $row['shelf'],
                        ],
                        $row + ['updated_at' => $now, 'created_at' => $now]
                    );
                    $written++;
                }
            });
        }

        ShelfRackLocator::flushCache();

        $this->info("Imported {$written} rows into shelf_rack_ranges.");

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseWorkbook(string $file): array
    {
        $sheetRows = [];
        $source = basename($file);
        $setVersion = str_contains($source, 'Combination_2_') ? 2 : 1;

        $spreadsheet = IOFactory::load($file);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $data = $sheet->toArray(null, true, false, false);

            foreach ($this->blockStarts($data) as $start) {
                foreach ($data as $row) {
                    $cells = $this->slice($row, $start);
                    if ($cells === null) {
                        continue;
                    }

                    [$from, $to] = $this->parseRange($cells[6]);

                    $sheetRows[] = [
                        'registry_id' => (int) $cells[1],
                        'rack' => $cells[2],
                        'shelf' => (int) $cells[3],
                        'rack_shelf' => $cells[4],
                        'file_no' => $cells[5] !== '' ? $cells[5] : null,
                        'serial_from' => $from,
                        'serial_to' => $to,
                        'serial_range' => $cells[6] !== '' ? $cells[6] : null,
                        'source_file' => $source,
                        'set_version' => $setVersion,
                        'source_sn' => (int) $cells[0],
                    ];
                }
            }
        }

        return $sheetRows;
    }

    /**
     * Columns at which a 7-wide table block begins. The second block on the _2_
     * sheets has no header, so this keys off the rack/shelf label in c+4.
     *
     * @return array<int, int>
     */
    private function blockStarts(array $data): array
    {
        $starts = [];

        foreach ($data as $row) {
            foreach ($row as $col => $value) {
                if (is_numeric($value)
                    && isset($row[$col + 4])
                    && $this->isShelfLabel(trim((string) $row[$col + 4]))) {
                    $starts[$col] = true;
                }
            }
        }

        ksort($starts);

        return array_keys($starts);
    }

    /**
     * A data row's 7 cells at $start, or null if this isn't a data row.
     *
     * @return array<int, string>|null
     */
    private function slice(array $row, int $start): ?array
    {
        $cells = array_slice($row, $start, self::HEADER_WIDTH) + array_fill(0, self::HEADER_WIDTH, '');
        $cells = array_map(fn($v) => trim((string) $v), $cells);

        if (!is_numeric($cells[0]) || !$this->isShelfLabel($cells[4]) || !is_numeric($cells[1])) {
            return null;
        }

        return $cells;
    }

    private function isShelfLabel(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]{1,2}\d+$/', $value);
    }

    /**
     * "101 - 200" / "701 -800" -> [101, 200].
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function parseRange(string $range): array
    {
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }

    /**
     * full_label => id from Rack_Shelf_Labels.
     *
     * @return array<string, int>
     */
    private function shelfLabelIds(): array
    {
        return DB::connection('sqlsrv')
            ->table('Rack_Shelf_Labels')
            ->get()
            ->mapWithKeys(fn($r) => [strtoupper(trim($r->full_label)) => (int) $r->id])
            ->all();
    }

    private function report(array $rows): void
    {
        $v2 = array_filter($rows, fn($r) => $r['set_version'] === 2);
        $allocated = array_filter($rows, fn($r) => $r['file_no'] !== null);
        $unlinked = array_filter($rows, fn($r) => $r['shelf_label_id'] === null);

        $conflicts = [];
        foreach ($rows as $r) {
            $conflicts[$r['registry_id'] . '|' . $r['rack'] . '|' . $r['shelf']][] = $r['file_no'];
        }
        $conflicts = array_filter($conflicts, fn($v) => count(array_unique($v)) > 1);

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Total rows', count($rows)],
            ['From _2_ set (set_version 2)', count($v2)],
            ['From older set (set_version 1)', count($rows) - count($v2)],
            ['Shelves with a file number', count($allocated)],
            ['Empty shelves', count($rows) - count($allocated)],
            ['No matching Rack_Shelf_Labels row', count($unlinked)],
            ['Shelves claimed by conflicting file numbers', count($conflicts)],
        ]);
    }
}
