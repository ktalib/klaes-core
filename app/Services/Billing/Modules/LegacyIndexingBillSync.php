<?php

namespace App\Services\Billing\Modules;

use App\Models\BillItem;
use App\Models\BillLegacy;
use App\Services\Billing\BillMasterService;
use App\Services\Billing\BillRef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * File Indexing bills → Bill Legacy (phase G, docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * Legacy bills are the historical bills captured under File Indexing
 * (file_indexing_bills: bill balance and grant rent). They live in bill_legacy,
 * apart from the system bills, with LGB- refs, and keep the receipt captured at
 * indexing: a bill with a receipt is paid, one without is unpaid. Every row is
 * loaded, dirty ones included.
 *
 * FileIndexingBillService deletes and re-inserts a file's bills on every save,
 * so this syncs a whole FILE: each indexing bill is matched to the file's
 * bill_legacy rows by type + years + receipt and keeps its LGB- ref; a new one
 * gets the next LGB- ref; one that disappeared is cancelled, never deleted.
 */
class LegacyIndexingBillSync
{
    public const SOURCE_TABLE = 'file_indexing_bills';

    private const TYPES = ['bill-balance' => 'bill_balance', 'grant-rent' => 'grant_rent'];

    /** @return array{created: int, updated: int, cancelled: int} */
    public function syncFile(int $fileIndexingId, string $actor = 'system'): array
    {
        $db = DB::connection('sqlsrv');
        $counts = ['created' => 0, 'updated' => 0, 'cancelled' => 0];

        $sources = $db->table('file_indexing_bills')->where('file_indexing_id', $fileIndexingId)->orderBy('id')->get();
        $file = $db->table('file_indexings')->where('id', $fileIndexingId)
            ->first(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga']);

        $existing = BillLegacy::query()->where('file_indexing_id', $fileIndexingId)
            ->where('status', '<>', 'cancelled')->orderBy('id')->get();
        $unmatched = $existing->keyBy('id');

        foreach ($sources as $src) {
            $type = self::TYPES[$src->bill_type] ?? (string) $src->bill_type;
            $key = $this->key($type, $src->from_year, $src->to_year, $src->receipt_no);

            $match = $unmatched->first(fn ($b) => $this->key($b->bill_type_code, $b->from_year, $b->to_year, $b->receipt_no) === $key);
            $values = $this->values($src, $type, $file, $actor);

            if ($match) {
                $match->update($values);
                $unmatched->forget($match->id);
                $bill = $match;
                $counts['updated']++;
            } else {
                $bill = BillRef::retrying(fn () => BillLegacy::create($values + ['created_by' => $actor]));
                $counts['created']++;
            }

            BillItem::query()->where('bill_ref_id', $bill->bill_ref_id)->delete();
            BillItem::create([
                'bill_ref_id' => $bill->bill_ref_id,
                'item_code' => strtoupper($type),
                'name' => $this->lineName($type, $src->from_year, $src->to_year),
                'quantity' => 1,
                'rate' => (float) $values['amount'],
                'amount' => (float) $values['amount'],
                'sort_order' => 1,
            ]);
        }

        foreach ($unmatched as $gone) {
            $gone->update(['status' => 'cancelled', 'updated_by' => $actor,
                'notes' => trim(($gone->notes ? $gone->notes . "\n" : '') . 'Removed from File Indexing on ' . now()->toDateTimeString() . '.')]);
            $counts['cancelled']++;
        }

        return $counts;
    }

    /** After FileIndexingBillService saved a file's bills. Never throws into indexing. */
    public function afterIndexingSave(int $fileIndexingId): void
    {
        try {
            DB::connection('sqlsrv')->transaction(fn () => $this->syncFile($fileIndexingId, (string) (auth()->user()->name ?? auth()->id() ?? 'system')));
        } catch (\Throwable $e) {
            Log::warning('Indexing bills not synced to Bill Legacy', ['file_indexing_id' => $fileIndexingId, 'error' => $e->getMessage()]);
        }
    }

    private function values(object $src, string $type, ?object $file, string $actor): array
    {
        $amount = round(max(0, (float) $src->amount), 2);
        $receipt = trim((string) $src->receipt_no);
        $district = trim((string) ($file->district ?? ''));
        $lga = trim((string) ($file->lga ?? ''));

        return [
            'bill_type_code' => $type,
            'file_number' => $src->file_number ?: ($file->file_number ?? null),
            'file_indexing_id' => $src->file_indexing_id,
            'payer_name' => trim((string) ($file->file_title ?? '')) ?: null,
            'plot_no' => trim((string) ($file->plot_number ?? '')) ?: null,
            'district' => $district ?: null,
            'lga' => $lga ?: null,
            'location' => BillMasterService::location($district, $lga),
            'description' => $this->lineName($type, $src->from_year, $src->to_year),
            'amount' => $amount,
            'amount_paid' => $receipt !== '' ? $amount : 0,
            'from_year' => $src->from_year,
            'to_year' => $src->to_year,
            'bill_date' => $src->receipt_date ?: null,
            'status' => $receipt !== '' ? 'paid' : 'unpaid',
            'receipt_no' => $receipt !== '' ? $receipt : null,
            'receipt_date' => $src->receipt_date ?: null,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $src->id,
            'updated_by' => $actor,
        ];
    }

    private function lineName(string $type, $from, $to): string
    {
        $label = config('billing.legacy_types.' . $type, ucfirst(str_replace('_', ' ', $type)));

        return $label . ($from || $to ? ' ' . ($from ?: '?') . '–' . ($to ?: '?') : '');
    }

    private function key(string $type, $from, $to, $receipt): string
    {
        return implode('|', [$type, (int) $from, (int) $to, mb_strtolower(trim((string) $receipt))]);
    }
}
