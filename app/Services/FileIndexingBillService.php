<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\FileIndexingBill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists the File Indexing "Bill Balance" and "Grant Rent" entries.
 *
 * A file may have MANY bills of each type, submitted as parallel arrays
 * (bill_total_amount[], bill_from_year[], ...). Each bill writes to TWO tables:
 *   1. billing                 -> ref_id = file_number, source = bill type ("indexing-*")
 *   2. file_indexing_bills     -> the bill attributes, linked back via billing_id
 *
 * The PRIMARY (first) bill of each type is also mirrored onto the file_indexings
 * row itself (handled by the controller via the model column whitelist), purely
 * as a convenience for quick display / edit prefill. The authoritative,
 * multi-row store is file_indexing_bills.
 *
 * On every save we clear this file's previous indexing bills and re-insert the
 * current set, so the tables always reflect exactly what was submitted.
 */
class FileIndexingBillService
{
    private const BILLING_TABLE       = 'billing';
    private const BILLING_PRIMARY_KEY = 'ID';

    // billing.source values (the "bill type")
    private const SOURCE_BILL_BALANCE = 'indexing-bill-balance';
    private const SOURCE_GRANT_RENT   = 'indexing-grant-rent';

    public function syncForFile(FileIndexing $fileIndexing, Request $request): void
    {
        $fileNumber = trim((string) ($fileIndexing->file_number ?? ''));
        if ($fileNumber === '') {
            // No real file number yet (e.g. temp-only record) — skip silently.
            return;
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use ($fileIndexing, $fileNumber, $request) {
                $this->clearExisting($fileIndexing->id);

                $bills = $this->collectRows($request, [
                    'amount'       => 'bill_total_amount',
                    'from_year'    => 'bill_from_year',
                    'to_year'      => 'bill_to_year',
                    'receipt_no'   => 'bill_receipt_no',
                    'receipt_date' => 'bill_receipt_date',
                ]);
                foreach ($bills as $row) {
                    $this->insertBill($fileIndexing, $fileNumber, FileIndexingBill::TYPE_BILL_BALANCE, self::SOURCE_BILL_BALANCE, $row);
                }

                $rents = $this->collectRows($request, [
                    'amount'       => 'ground_rent_amount',
                    'from_year'    => 'ground_rent_from_year',
                    'to_year'      => 'ground_rent_to_year',
                    'receipt_no'   => 'ground_rent_receipt_no',
                    'receipt_date' => 'ground_rent_receipt_date',
                ]);
                foreach ($rents as $row) {
                    $this->insertBill($fileIndexing, $fileNumber, FileIndexingBill::TYPE_GRANT_RENT, self::SOURCE_GRANT_RENT, $row);
                }
            });
        } catch (\Throwable $e) {
            // A billing failure must not break file indexing — log and move on.
            Log::warning('FileIndexingBillService: failed to sync bills', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove this file's previously-saved indexing bills (and their billing rows)
     * so a re-save reflects exactly what was submitted.
     */
    private function clearExisting(int $fileIndexingId): void
    {
        $connection = DB::connection('sqlsrv');

        $billingIds = FileIndexingBill::where('file_indexing_id', $fileIndexingId)
            ->pluck('billing_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($billingIds)) {
            $connection->table(self::BILLING_TABLE)
                ->whereIn(self::BILLING_PRIMARY_KEY, $billingIds)
                ->whereIn('source', [self::SOURCE_BILL_BALANCE, self::SOURCE_GRANT_RENT])
                ->delete();
        }

        FileIndexingBill::where('file_indexing_id', $fileIndexingId)->delete();
    }

    private function insertBill(FileIndexing $fileIndexing, string $fileNumber, string $type, string $source, array $row): void
    {
        $billingId = $this->insertBillingRow($fileNumber, $fileIndexing->id, $source, $row);

        FileIndexingBill::create([
            'billing_id'       => $billingId,
            'file_indexing_id' => $fileIndexing->id,
            'file_number'      => $fileNumber,
            'bill_type'        => $type,
            'amount'           => $this->toAmount($row['amount']),
            'from_year'        => $this->toYear($row['from_year']),
            'to_year'          => $this->toYear($row['to_year']),
            'receipt_no'       => $this->nullableString($row['receipt_no']),
            'receipt_date'     => $this->normalizeDate($row['receipt_date']),
            'created_by'       => Auth::id(),
            'updated_by'       => Auth::id(),
        ]);
    }

    private function insertBillingRow(string $fileNumber, int $fileIndexingId, string $source, array $row): int
    {
        $now = Carbon::now();

        $payload = array_merge($this->baseBillingPayload(), [
            'ref_id'               => $fileNumber,
            'source'               => $source,
            'source_id'            => $fileIndexingId,
            'bill_balance_reciept' => $this->nullableString($row['receipt_no']),
            'bill_balance_date'    => $this->normalizeDate($row['receipt_date']),
            'Payment_Status'       => 'Pending',
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        return (int) DB::connection('sqlsrv')
            ->table(self::BILLING_TABLE)
            ->insertGetId($payload, self::BILLING_PRIMARY_KEY);
    }

    /**
     * Turn parallel request arrays into a list of associative rows, skipping
     * any row that has no meaningful data.
     */
    private function collectRows(Request $request, array $map): array
    {
        $cols  = [];
        $count = 0;
        foreach ($map as $field => $reqKey) {
            $val = $request->input($reqKey);
            $val = is_array($val) ? array_values($val) : [$val];
            $cols[$field] = $val;
            $count = max($count, count($val));
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($map as $field => $reqKey) {
                $row[$field] = $cols[$field][$i] ?? null;
            }
            if (!$this->isEmptyRow($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function isEmptyRow(array $row): bool
    {
        $amount    = $this->toAmount($row['amount'] ?? null);
        $receiptNo = $this->nullableString($row['receipt_no'] ?? null);
        $date      = $this->normalizeDate($row['receipt_date'] ?? null);

        return ($amount === null || $amount == 0) && $receiptNo === null && $date === null;
    }

    /**
     * Zero-filled fee columns so NOT NULL constraints on the billing table are satisfied.
     */
    private function baseBillingPayload(): array
    {
        return [
            'Site_Plan_Fee'      => 0,
            'survey_fee'         => 0,
            'Processing_Fee'     => 0,
            'Betterment_Charges' => 0,
            'Land_Use_Charge'    => 0,
            'property_value'     => 0,
            'Penalty_Fees'       => 0,
            'betterment_rate'    => 0,
        ];
    }

    private function toAmount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function toYear($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
