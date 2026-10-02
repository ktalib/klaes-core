<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexWrcDuplicateFiles extends Command
{
    protected $signature = 'duplicate:index-wrc
        {--chunk=200 : Rows per insert chunk}
        {--dry-run : List what would be indexed without inserting}';

    protected $description = 'Index land files from duplicate_fileno whose comment is [WRC] or [WRC] CANCELLED. Idempotent — skips file numbers that are already indexed.';

    private const CREATED_BY = 'WRC Duplicate Backfill';
    private const SOURCE = 'wrc_duplicate_backfill';
    private const LAND_REGISTRY = 'Lands Registry';
    private const COMMENTS = ['[WRC]', '[WRC] CANCELLED'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $conn = DB::connection('sqlsrv');

        // 1. Pull duplicate_fileno rows tagged [WRC] / [WRC] CANCELLED.
        $rows = $conn->table('duplicate_fileno')
            ->whereIn('comment', self::COMMENTS)
            ->whereNotNull('file_number')
            ->where('file_number', '<>', '')
            ->orderBy('id')
            ->get(['file_number', 'file_title', 'plot_number', 'location', 'comment']);

        $this->info('duplicate_fileno rows matching ' . implode(' / ', self::COMMENTS) . ": {$rows->count()}");

        // 2. Collapse to distinct file number (first row wins).
        $candidates = [];
        foreach ($rows as $row) {
            $fileNo = trim((string) $row->file_number);
            if ($fileNo === '') {
                continue;
            }
            $key = strtoupper($fileNo);
            if (isset($candidates[$key])) {
                continue;
            }
            $candidates[$key] = [
                'file_number'   => $fileNo,
                'file_title'    => $row->file_title,
                'plot_number'   => $row->plot_number,
                'location'      => $row->location,
                'land_use_type' => $this->deriveLandUse($fileNo),
            ];
        }

        $this->info('Distinct file numbers: ' . count($candidates));

        if (empty($candidates)) {
            $this->warn('Nothing to do.');
            return self::SUCCESS;
        }

        // 3. Skip file numbers that already have an indexing record.
        $existing = [];
        foreach (array_chunk(array_keys($candidates), 500) as $keyChunk) {
            $conn->table('file_indexings')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keyChunk)
                ->pluck('file_number')
                ->each(function ($fn) use (&$existing) {
                    $existing[strtoupper(trim((string) $fn))] = true;
                });
        }

        $toInsert = array_filter(
            $candidates,
            fn ($key) => !isset($existing[$key]),
            ARRAY_FILTER_USE_KEY
        );

        $skipped = count($candidates) - count($toInsert);
        $this->info('Already indexed (skipped): ' . $skipped);
        $this->info(($dryRun ? 'Would index' : 'To index') . ': ' . count($toInsert));

        if ($dryRun) {
            foreach (array_slice($toInsert, 0, 25) as $row) {
                $this->line("  [land] {$row['file_number']}  ({$row['file_title']})  use={$row['land_use_type']}");
            }
            if (count($toInsert) > 25) {
                $this->line('  ... and ' . (count($toInsert) - 25) . ' more');
            }
            return self::SUCCESS;
        }

        // 4. Insert new land indexing records.
        $now = now();
        $inserted = 0;

        foreach (array_chunk($toInsert, $chunk) as $batch) {
            $conn->transaction(function () use ($batch, $now, &$inserted) {
                foreach ($batch as $row) {
                    FileIndexing::forceCreate([
                        'file_number'      => $row['file_number'],
                        'file_title'       => $row['file_title'],
                        'land_use_type'    => $row['land_use_type'],
                        'plot_number'      => $row['plot_number'],
                        'location'         => $row['location'],
                        'general_registry' => self::LAND_REGISTRY,
                        'indexing_type'    => 'Regular',
                        'source'           => self::SOURCE,
                        'created_by'       => self::CREATED_BY,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    $inserted++;
                }
            });

            $this->line("  inserted {$inserted}/" . count($toInsert));
        }

        $this->info("Done. Indexed {$inserted} land file(s).");

        return self::SUCCESS;
    }

    /**
     * Derive a land use type from the file-number prefix
     * (RES/COM/IND/AG/MISC, including CON- and -RC variants).
     */
    private function deriveLandUse(string $fileNumber): ?string
    {
        $f = strtoupper(trim($fileNumber));
        $f = preg_replace('/^CON-/', '', $f);

        $map = [
            'RES'  => 'RESIDENTIAL',
            'COM'  => 'COMMERCIAL',
            'IND'  => 'INDUSTRIAL',
            'AG'   => 'AGRICULTURAL',
            'MISC' => 'MISCELLANEOUS',
        ];

        foreach ($map as $prefix => $use) {
            if (preg_match('/^' . $prefix . '[-\/]/', $f)) {
                return $use;
            }
        }

        return null;
    }
}
