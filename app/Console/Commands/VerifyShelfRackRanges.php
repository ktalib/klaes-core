<?php

namespace App\Console\Commands;

use App\Services\ShelfRackLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Measures how far the shelf_rack_ranges map can be trusted, by predicting a
 * shelf for every file that ALREADY has a recorded shelf_location and comparing.
 *
 * This is the check that surfaced the registry-1 one-rack drift; re-run it after
 * any re-import, and on production before relying on the /filearchive fallback.
 */
class VerifyShelfRackRanges extends Command
{
    protected $signature = 'shelf-racks:verify
                            {--registry= : Limit to one registry (e.g. 1 or 3)}
                            {--by-series : Break the report down per file-number series}
                            {--show-drift : Show which racks the mismatches drift between}';

    protected $description = 'Compare shelf_rack_ranges predictions against recorded shelf_location values';

    public function handle(ShelfRackLocator $locator)
    {
        $query = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereNotNull('shelf_location')
            ->where('shelf_location', '<>', '')
            ->select(['file_number', 'registry', 'shelf_location']);

        if ($this->option('registry')) {
            $query->where('registry', $this->option('registry'));
        }

        $files = $query->get();

        if ($files->isEmpty()) {
            $this->warn('No files with a recorded shelf_location to verify against.');
            return Command::SUCCESS;
        }

        $stats = [];
        $drift = [];

        foreach ($files as $file) {
            $predicted = $locator->resolve($file->file_number, $file->registry);
            $actual = trim((string) $file->shelf_location);

            $bucket = 'registry ' . ($file->registry ?? '(none)');
            $stats[$bucket]['total'] = ($stats[$bucket]['total'] ?? 0) + 1;

            if ($predicted === null) {
                $stats[$bucket]['unresolved'] = ($stats[$bucket]['unresolved'] ?? 0) + 1;
                continue;
            }

            if (strcasecmp($predicted, $actual) === 0) {
                $stats[$bucket]['ok'] = ($stats[$bucket]['ok'] ?? 0) + 1;
                continue;
            }

            $stats[$bucket]['bad'] = ($stats[$bucket]['bad'] ?? 0) + 1;

            if ($this->option('show-drift')
                && preg_match('/^([A-Z]+)\d+$/i', $actual, $a)
                && preg_match('/^([A-Z]+)\d+$/i', $predicted, $p)) {
                $label = strtoupper($a[1]) === strtoupper($p[1])
                    ? 'same rack, wrong shelf'
                    : 'rack ' . strtoupper($a[1]) . ' -> ' . strtoupper($p[1]);
                $drift[$label] = ($drift[$label] ?? 0) + 1;
            }

            if ($this->option('by-series') && preg_match('/^(.*)-\d+$/', trim($file->file_number), $m)) {
                $key = $bucket . ' ' . strtoupper($m[1]);
                $stats[$key]['bad'] = ($stats[$key]['bad'] ?? 0) + 1;
                $stats[$key]['total'] = ($stats[$key]['total'] ?? 0) + 1;
            }
        }

        ksort($stats);

        $rows = [];
        foreach ($stats as $bucket => $s) {
            $ok = $s['ok'] ?? 0;
            $bad = $s['bad'] ?? 0;
            $compared = $ok + $bad;

            $rows[] = [
                $bucket,
                $s['total'] ?? 0,
                $ok,
                $bad,
                $s['unresolved'] ?? 0,
                $compared > 0 ? round(100 * $ok / $compared) . '%' : 'n/a',
            ];
        }

        $this->table(['Bucket', 'Files', 'Predicted OK', 'Wrong', 'No prediction', 'Accuracy'], $rows);

        if ($this->option('show-drift') && $drift) {
            arsort($drift);
            $this->newLine();
            $this->line('Mismatch shape (actual rack -> predicted rack):');
            foreach (array_slice($drift, 0, 12, true) as $label => $count) {
                $this->line('  ' . str_pad($label, 24) . $count);
            }
        }

        $this->newLine();
        $this->line('Accuracy is measured only on files that already have a recorded');
        $this->line('shelf_location. The /filearchive fallback applies to files that do not,');
        $this->line('so treat these figures as the expected hit rate, not a guarantee.');

        return Command::SUCCESS;
    }
}
