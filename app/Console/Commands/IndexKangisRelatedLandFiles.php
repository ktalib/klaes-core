<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexKangisRelatedLandFiles extends Command
{
    protected $signature = 'kangis:index-related-land
        {--chunk=200 : Rows per insert chunk}
        {--purge : Delete all rows previously created by this backfill, then exit}
        {--dry-run : List what would be indexed/deleted without writing}';

    protected $description = 'Index land files referenced by KANGIS-registry indexing records (general_registry LIKE KANGIS% AND related_fileno IS NOT NULL). Idempotent — skips land files that are already indexed.';

    private const CREATED_BY = 'KANGIS Related Land Backfill';
    private const SOURCE = 'kangis_related_backfill';
    private const LAND_REGISTRY = 'Lands Registry';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $conn = DB::connection('sqlsrv');

        // --purge: remove everything this backfill previously created, then stop.
        if ($this->option('purge')) {
            $purgeQuery = $conn->table('file_indexings')->where('created_by', self::CREATED_BY);
            $count = (clone $purgeQuery)->count();

            if ($dryRun) {
                $this->warn("Would delete {$count} row(s) where created_by = '" . self::CREATED_BY . "'.");
                return self::SUCCESS;
            }

            $deleted = $purgeQuery->delete();
            $this->info("Deleted {$deleted} row(s) created by '" . self::CREATED_BY . "'.");
            return self::SUCCESS;
        }

        // 1. Pull every KANGIS indexing record that carries a related file number.
        $kangisRows = $conn->table('file_indexings')
            ->where('general_registry', 'like', 'KANGIS%')
            ->whereNotNull('related_fileno')
            ->where('related_fileno', '<>', '')
            ->orderBy('id')
            ->get(['id', 'file_number', 'file_title', 'land_use_type', 'related_fileno', 'plot_number', 'location', 'district', 'lga']);

        $this->info("KANGIS records with a related file number: {$kangisRows->count()}");

        // 2. Build a map of land file number => carry-over data (first KANGIS row wins).
        $candidates = [];
        $tempSkipped = 0;
        foreach ($kangisRows as $row) {
            foreach ($this->parseRelatedFilenos($row->related_fileno) as $landNo) {
                // Ignore temporary file numbers e.g. IND-RC-1983-11(T).
                if (preg_match('/\(\s*T\s*\)\s*$/i', $landNo)) {
                    $tempSkipped++;
                    continue;
                }
                $key = strtoupper($landNo);
                if (isset($candidates[$key])) {
                    continue;
                }
                $candidates[$key] = [
                    'file_number'    => $landNo,
                    'file_title'     => $row->file_title,
                    'land_use_type'  => $row->land_use_type,
                    'plot_number'    => $row->plot_number,
                    'location'       => $row->location,
                    'district'       => $row->district,
                    'lga'            => $row->lga,
                    'kangis_fileno'  => trim((string) $row->file_number),
                ];
            }
        }

        $this->info('Distinct related land file numbers: ' . count($candidates));
        $this->info('Temporary (T) file numbers ignored: ' . $tempSkipped);

        if (empty($candidates)) {
            $this->warn('Nothing to do.');
            return self::SUCCESS;
        }

        // 3. Find which land file numbers are already indexed so we can skip them.
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
                $this->line("  [land] {$row['file_number']}  <- KANGIS {$row['kangis_fileno']}  ({$row['file_title']})");
            }
            if (count($toInsert) > 25) {
                $this->line('  ... and ' . (count($toInsert) - 25) . ' more');
            }
            return self::SUCCESS;
        }

        // 4. Insert new land indexing records.
        // Backdate to 2 days ago so the batch does not surface as today's indexing activity.
        $now = now()->subDays(2);
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
                        'district'         => $row['district'],
                        'lga'              => $row['lga'],
                        'general_registry' => self::LAND_REGISTRY,
                        'indexing_type'    => 'Regular',
                        'related_fileno'   => json_encode([$row['kangis_fileno']]),
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
     * related_fileno is usually a JSON array string (e.g. ["RES-1982-1302"]),
     * but can be a plain file number. Returns clean, non-empty file numbers.
     */
    private function parseRelatedFilenos($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $out = [];
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $out[] = $item;
                }
            }
        } else {
            $out[] = $value;
        }

        return $out;
    }
}
