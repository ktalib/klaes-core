<?php

namespace App\Console\Commands;

use App\Models\BillLegacy;
use App\Services\Billing\BillRef;
use App\Services\Billing\Modules\LegacyIndexingBillSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M phase G (docs/plans/KLAES_REVM_Bill_Master_Plan.md): load the bills
 * captured under File Indexing (file_indexing_bills) into bill_legacy, LGB- refs.
 *
 * --preview runs LegacyIndexingBillSync for every file inside transactions that
 * are always rolled back and writes a CSV of the bills it would create.
 * --load does it for real, a few files per transaction. Only bill_legacy and
 * bill_items are written; no existing data is changed.
 */
class BillsLegacyLoad extends Command
{
    protected $signature = 'bills:legacy
        {--preview : Write the preview CSV only; nothing is saved}
        {--load : Load the legacy bills for real}
        {--force : With --load, do not ask for confirmation}';

    protected $description = 'REV-M phase G: load File Indexing bills into Bill Legacy (preview or load)';

    private const PREVIEW_CHUNK = 200;
    private const LOAD_CHUNK = 50;
    private const ACTOR = 'legacy-load';

    public function handle(LegacyIndexingBillSync $sync): int
    {
        $preview = (bool) $this->option('preview');
        if (!$preview && !$this->option('load')) {
            $this->error('Choose --preview (nothing saved) or --load.');

            return self::FAILURE;
        }

        $files = DB::connection('sqlsrv')->table('file_indexing_bills')->distinct()->orderBy('file_indexing_id')->pluck('file_indexing_id')->all();
        $this->info(count($files) . ' files with indexing bills (' . DB::connection('sqlsrv')->table('file_indexing_bills')->count() . ' bills).');

        if (!$preview && !$this->option('force') && !$this->confirm('Load them into Bill Legacy now?')) {
            return self::FAILURE;
        }

        $rows = [];
        $errors = [];
        $bar = $this->output->createProgressBar(count($files));
        foreach (array_chunk($files, $preview ? self::PREVIEW_CHUNK : self::LOAD_CHUNK) as $chunk) {
            DB::connection('sqlsrv')->beginTransaction();
            try {
                foreach ($chunk as $fileId) {
                    try {
                        $sync->syncFile((int) $fileId, self::ACTOR);
                    } catch (\Throwable $e) {
                        $errors[] = "file_indexing {$fileId}: " . $e->getMessage();
                    }
                    $bar->advance();
                }
                foreach (BillLegacy::query()->whereIn('file_indexing_id', $chunk)->where('status', '<>', 'cancelled')->orderBy('id')->get() as $b) {
                    $rows[] = $this->row($b);
                }
                $preview ? DB::connection('sqlsrv')->rollBack() : DB::connection('sqlsrv')->commit();
            } catch (\Throwable $e) {
                DB::connection('sqlsrv')->rollBack();
                $errors[] = 'chunk starting ' . $chunk[0] . ': ' . $e->getMessage();
            }
        }
        $bar->finish();
        $this->newLine(2);

        if ($preview) {
            // The LGB- refs the load would issue, in this order.
            $next = (int) substr(BillRef::next(BillRef::LEGACY), -6);
            foreach ($rows as &$row) {
                $row['lgb_ref'] = sprintf('LGB-%d-%06d', now()->year, $next++);
            }
            unset($row);
        }

        $path = $this->write($rows, $preview ? 'legacy_preview_' : 'legacy_load_');
        $this->summary($rows);
        foreach (array_slice($errors, 0, 20) as $e) {
            $this->error($e);
        }
        $this->info(($preview ? 'Preview' : 'Results') . " written: {$path}");

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    private function row(BillLegacy $b): array
    {
        return [
            'lgb_ref' => $b->bill_ref_id,
            'bill_type' => $b->bill_type_code,
            'status' => $b->status,
            'amount' => number_format((float) $b->amount, 2, '.', ''),
            'period' => ($b->from_year || $b->to_year) ? ($b->from_year ?: '?') . '–' . ($b->to_year ?: '?') : '',
            'receipt_no' => (string) $b->receipt_no,
            'receipt_date' => optional($b->receipt_date)->toDateString() ?? '',
            'file_number' => (string) $b->file_number,
            'payer' => (string) $b->payer_name,
            'plot_no' => (string) $b->plot_no,
            'district' => (string) $b->district,
            'lga' => (string) $b->lga,
            'location' => (string) $b->location,
            'file_indexing_id' => $b->file_indexing_id,
            'source_id' => $b->source_id,
            'note' => trim(((float) $b->amount <= 0 ? 'No amount captured. ' : '') . (!$b->payer_name ? 'No indexing record found for this file.' : '')),
        ];
    }

    private function write(array $rows, string $prefix): string
    {
        $dir = storage_path('app/revm');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . now()->format('Ymd_His') . '.csv';
        $columns = ['lgb_ref', 'bill_type', 'status', 'amount', 'period', 'receipt_no', 'receipt_date', 'file_number', 'payer',
            'plot_no', 'district', 'lga', 'location', 'file_indexing_id', 'source_id', 'note'];

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_merge(['s_n'], $columns));
        foreach ($rows as $i => $row) {
            fputcsv($fh, array_merge([$i + 1], array_map(fn ($c) => $row[$c] ?? '', $columns)));
        }
        fclose($fh);

        return $path;
    }

    private function summary(array $rows): void
    {
        $table = [];
        foreach (collect($rows)->groupBy('bill_type') as $type => $group) {
            $table[] = [
                config('billing.legacy_types.' . $type, $type),
                $group->count(),
                $group->where('status', 'paid')->count(),
                $group->where('status', 'unpaid')->count(),
                $group->filter(fn ($r) => (float) $r['amount'] <= 0)->count(),
                number_format($group->sum(fn ($r) => (float) $r['amount']), 2),
            ];
        }
        $this->table(['Type', 'Bills', 'Paid (receipt)', 'Unpaid', 'No amount', 'Amount (₦)'], $table);
    }
}
