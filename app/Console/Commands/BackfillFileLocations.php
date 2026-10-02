<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use App\Services\FileLocationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillFileLocations extends Command
{
    protected $signature = 'files:backfill-locations
                            {--chunk=500 : Rows to process per batch}
                            {--only-empty : Only fill rows where tracking_status is NULL}
                            {--dry-run : Resolve and report without writing}';

    protected $description = 'Backfill file_indexings.tracking_status / current_location / file_tracker_id using FileLocationResolver (ACTIVE tracker=IN_TRANSIT, Completed=IN_ARCHIVE, else registry range).';

    public function handle(FileLocationResolver $resolver): int
    {
        if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'tracking_status')) {
            $this->error('file_indexings.tracking_status is missing. Run the migration first.');
            return self::FAILURE;
        }

        $chunk   = max(50, (int) $this->option('chunk'));
        $dryRun  = (bool) $this->option('dry-run');
        $counts  = [];
        $written = 0;

        $query = FileIndexing::on('sqlsrv')
            ->select(['id', 'file_number', 'new_kangis_file_no', 'kangis_file_no', 'mls_file_no', 'st_fillno', 'shelf_location', 'file_title'])
            ->whereNotNull('file_number')
            ->where('file_number', '!=', '');

        if ($this->option('only-empty')) {
            $query->whereNull('tracking_status');
        }

        $total = (clone $query)->count();
        $this->info("Resolving locations for {$total} indexed files (chunk {$chunk})" . ($dryRun ? ' [dry-run]' : '') . '…');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$counts, &$written, $bar) {
            foreach ($rows as $row) {
                $result = $resolver->resolve((string) $row->file_number);
                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;

                if (!$dryRun) {
                    $row->forceFill([
                        'tracking_status'  => $result['status'],
                        'current_location' => $result['current_location'],
                        'file_tracker_id'  => $result['file_tracker_id'],
                    ])->save();
                    $written++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('Outcome breakdown:');
        foreach ($counts as $status => $n) {
            $this->line(sprintf('  %-28s %d', $status, $n));
        }
        $this->info($dryRun ? 'Dry-run complete (no writes).' : "Backfill complete — {$written} rows updated.");

        return self::SUCCESS;
    }
}
