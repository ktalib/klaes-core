<?php

namespace App\Console\Commands;

use App\Services\Sltr\SltrTotGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SLTR special case for files already on record: a Sales Agreement and a CofO with no
 * Transfer of Title between them get the system-generated one (SltrTotGenerator).
 * New captures get it on save in Add Property Transaction Details.
 *
 * Preview by default: writes a CSV of what would be generated and skipped, no data.
 * --commit inserts, one file at a time (klas has no snapshot isolation, so no long
 * transaction). Each inserted id is in the CSV, so the run can be undone by id.
 */
class GenerateSltrTransferOfTitle extends Command
{
    protected $signature = 'sltr:generate-tot
        {--commit : Insert the Transfer of Title rows (default: preview only)}
        {--file= : One SLTR file number}
        {--limit=0 : Stop after this many files (0 = all)}';

    protected $description = 'Preview / generate the SLTR Transfer of Title between a Sales Agreement and a CofO';

    public function handle(SltrTotGenerator $generator): int
    {
        $commit = (bool) $this->option('commit');
        $files = $this->option('file')
            ? collect([trim($this->option('file'))])
            : $this->candidates();
        if ((int) $this->option('limit') > 0) {
            $files = $files->take((int) $this->option('limit'));
        }

        $dir = storage_path('app/backfills');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/sltr-tot-' . ($commit ? 'commit' : 'preview') . '-' . now()->format('Ymd_His') . '.csv';
        $csv = fopen($path, 'w');
        fputcsv($csv, ['file_number', 'action', 'reason', 'party_1 (SA buyer)', 'party_2 (name on CofO)', 'same_person', 'sale', 'cofo', 'inserted_id']);

        $counts = ['generate' => 0, 'skip' => []];
        $bar = $this->output->createProgressBar($files->count());
        foreach ($files as $fileNumber) {
            $plan = $generator->plan($fileNumber);
            $insertedId = null;
            if ($plan['action'] === 'generate') {
                $counts['generate']++;
                if ($commit) {
                    $insertedId = $generator->generate($fileNumber, null);
                }
            } else {
                $counts['skip'][$plan['reason']] = ($counts['skip'][$plan['reason']] ?? 0) + 1;
            }
            $row = $plan['row'] ?? [];
            $key = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v));
            fputcsv($csv, [
                $fileNumber, $plan['action'], $plan['reason'],
                $row['party_1'] ?? '', $row['party_2'] ?? '',
                $row ? ($key($row['party_1']) === $key($row['party_2']) ? 'yes' : 'no') : '',
                $plan['sale'] ?? '', $plan['cofo'] ?? '', $insertedId,
            ]);
            $bar->advance();
        }
        $bar->finish();
        fclose($csv);
        $this->newLine(2);

        $this->info(($commit ? 'Generated: ' : 'Would generate: ') . $counts['generate']);
        foreach ($counts['skip'] as $reason => $n) {
            $this->line("Skipped ({$reason}): {$n}");
        }
        $this->line('CSV: ' . $path);

        return self::SUCCESS;
    }

    /**
     * SLTR files with a Sale Agreement and a CofO, and no Transfer
     * of Title: a cheap pre-filter, so plan() (the rule itself) runs only where it can act.
     */
    private function candidates()
    {
        $sale = $this->sltrFiles(['file_history_staging', 'pra'],
            "(UPPER(LTRIM(RTRIM(transaction_type))) IN ('SALE AGREEMENT','SALES AGREEMENT','AGREEMENT OF SALE'))");
        $cofo = $this->sltrFiles(['CofO_staging', 'file_history_staging'],
            "UPPER(LTRIM(RTRIM(transaction_type))) IN ('CERTIFICATE OF OCCUPANCY','SLTR CERTIFICATE OF OCCUPANCY','ST CERTIFICATE OF OCCUPANCY')");
        $tot = $this->sltrFiles(['file_history_staging', 'pra', 'CofO_staging'], "transaction_type LIKE '%transfer of title%'");

        return collect(array_keys(array_diff_key(array_intersect_key($sale, $cofo), $tot)))->sort()->values();
    }

    /** SLTR file numbers (as keys) with a live row of this kind in any of the tables. */
    private function sltrFiles(array $tables, string $typeSql): array
    {
        $files = [];
        foreach ($tables as $table) {
            DB::connection('sqlsrv')->table($table)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->whereNull('deleted_at')
                ->whereRaw($typeSql)
                ->where(fn ($q) => $q->where('fileno', 'like', 'SLTR-%')->orWhere('mlsFNo', 'like', 'SLTR-%'))
                ->select('id', 'fileno', 'mlsFNo')
                ->orderBy('id')
                ->chunk(5000, function ($rows) use (&$files) {
                    foreach ($rows as $r) {
                        foreach ([$r->fileno, $r->mlsFNo] as $f) {
                            if (preg_match('/^\s*SLTR-/i', (string) $f)) {
                                $files[strtoupper(trim($f))] = true;
                            }
                        }
                    }
                });
        }

        return $files;
    }
}
