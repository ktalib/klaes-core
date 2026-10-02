<?php

namespace App\Services;

use App\Models\BillBalance;
use App\Models\FileIndexingBill;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an already-recorded Bill Balance for a Sectional Titling unit
 * (PuA / SuA) so the Bills screen can backfill it instead of re-keying.
 *
 * ST unit numbers (ST-MIXED-2025-1-022) never appear in any balance store —
 * those are keyed by land file number (CON-COM-2004-45). So we try the unit's
 * own fileno first, then fall back to its parent scheme's fileno via
 * subapplications.main_application_id -> mother_applications.fileno.
 *
 * The `billing` table is the lookup hub: ref_id = file number, source = bill
 * type. It does NOT hold the amount — every indexing-bill-balance row is
 * zero-filled — so the figure is read from the child row that billing points
 * at (file_indexing_bills.amount, or the Deeds metadata via summaryForFile).
 *
 * READ-ONLY BY DESIGN. This never writes to billing, so backfilling can not
 * create a duplicate billing row.
 */
class StBillBalanceResolver
{
    public const SOURCE_INDEXING = 'indexing-bill-balance';
    public const SOURCE_DEEDS    = 'DEEDS_BILL_BALANCE';

    /**
     * @return array{
     *   found: bool, amount: float|null, source: string|null,
     *   file_number: string|null, matched_on: string|null,
     *   billing_id: int|null, detail: array
     * }
     */
    public function forSubApplication(int $subApplicationId): array
    {
        $sub = DB::connection('sqlsrv')->table('subapplications')
            ->select('id', 'fileno', 'main_application_id', 'unit_type', 'is_sua_unit')
            ->where('id', $subApplicationId)
            ->first();

        if (!$sub) {
            return $this->miss();
        }

        // Candidates in priority order: the unit's own file, then its parent scheme's.
        $candidates = [];
        if (!empty($sub->fileno)) {
            $candidates[] = ['file_number' => trim($sub->fileno), 'matched_on' => 'unit'];
        }

        if (!empty($sub->main_application_id)) {
            $parentFileNo = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $sub->main_application_id)
                ->value('fileno');

            if (!empty($parentFileNo)) {
                $candidates[] = ['file_number' => trim($parentFileNo), 'matched_on' => 'parent'];
            }
        }

        foreach ($candidates as $candidate) {
            $hit = $this->forFileNumber($candidate['file_number']);

            if ($hit['found']) {
                $hit['matched_on'] = $candidate['matched_on'];
                return $hit;
            }
        }

        return $this->miss();
    }

    /**
     * Look a single file number up across both balance stores, indexing first
     * (2,204 rows vs 203 — it is the fuller set).
     */
    public function forFileNumber(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return $this->miss();
        }

        // 1) File Indexing capture — amount lives in file_indexing_bills.
        $indexed = FileIndexingBill::amountsForFile($fileNumber);

        if ($indexed['bill_balance'] !== null) {
            return [
                'found'       => true,
                'amount'      => (float) $indexed['bill_balance'],
                'source'      => self::SOURCE_INDEXING,
                'source_label' => 'File Indexing',
                'file_number' => $fileNumber,
                'matched_on'  => null,
                'billing_id'  => $this->billingIdFor($fileNumber, self::SOURCE_INDEXING),
                'detail'      => [
                    'grant_rent' => $indexed['grant_rent'],
                ],
            ];
        }

        // 2) Deeds Bill Balance certificate — use the computed balance still due.
        $deeds = BillBalance::summaryForFile($fileNumber);

        if ($deeds !== null) {
            return [
                'found'        => true,
                'amount'       => (float) ($deeds['balance_due'] ?? 0),
                'source'       => self::SOURCE_DEEDS,
                'source_label' => 'Deeds Bill Balance',
                'file_number'  => $fileNumber,
                'matched_on'   => null,
                'billing_id'   => $this->billingIdFor($fileNumber, self::SOURCE_DEEDS),
                'detail'       => [
                    'reference'        => $deeds['reference'] ?? null,
                    'total_amount'     => $deeds['total_amount'] ?? null,
                    'amount_deposited' => $deeds['amount_deposited'] ?? null,
                    'payment_status'   => $deeds['payment_status'] ?? null,
                ],
            ];
        }

        return $this->miss();
    }

    /**
     * Canonical ST -> Deeds Bill Balance fee mapping.
     *
     *   Deeds field (billing column)   <-  ST Initial Bill column
     *   Registration Fees (Site_Plan_Fee)  <-  ST Application Fee
     *   Survey Fees       (survey_fee)     <-  ST Site Plan Fee
     *   Preparation Fees  (Processing_Fee) <-  ST Processing Fee
     *
     * The names cross over — ST's *site plan* fee becomes the Deeds *survey*
     * fee, and ST's *application* fee becomes the Deeds *registration* fee —
     * so keep this table as the single source of truth for the mapping.
     *
     * Development Charges (Land_Use_Charge) comes from final_bills.dev_charges
     * instead, because ST records it on the balance bill, not the initial bill.
     */
    public const ST_FEE_MAP = [
        'Site_Plan_Fee'  => 'application_fee',
        'survey_fee'     => 'site_plan_fee',
        'Processing_Fee' => 'processing_fee',
    ];

    /**
     * Identify an ST file and map its recorded fees onto the Deeds Bill
     * Balance "Fees & Charges" fields.
     *
     * ST file numbers live on subapplications (ST-MIXED-2025-1-017); the Deeds
     * form otherwise deals in land files, so this is what tells the two apart.
     *
     * @return array{is_st: bool, unit_type: string|null, fileno: string|null,
     *               sub_application_id: int|null, fees: array, has_fees: bool}
     */
    public function stFeeProfile(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return $this->notSt();
        }

        // Some filenos carry stray newlines (e.g. "ST-COM-2025-8-001\n"),
        // so strip CR/LF as well as surrounding spaces before comparing.
        $normalised = "LTRIM(RTRIM(REPLACE(REPLACE(fileno, CHAR(13), ''), CHAR(10), '')))";

        $sub = DB::connection('sqlsrv')->table('subapplications')
            ->select('id', 'fileno', 'unit_type', 'is_sua_unit', 'application_fee', 'processing_fee', 'site_plan_fee')
            ->whereRaw("UPPER($normalised) = ?", [strtoupper($fileNumber)])
            ->orderByDesc('id')
            ->first();

        if (!$sub) {
            return $this->notSt();
        }

        // ST Development Charges live on the balance bill, not the initial bill.
        $balanceBill = DB::connection('sqlsrv')->table('final_bills')
            ->where('sub_application_id', $sub->id)
            ->orderByDesc('id')
            ->first();

        $devCharges = $balanceBill->dev_charges ?? null;

        $fees = [];
        foreach (self::ST_FEE_MAP as $deedsColumn => $stColumn) {
            $value = $this->toAmount($sub->{$stColumn} ?? null);
            if ($value !== null) {
                $fees[$deedsColumn] = $value;
            }
        }

        $dev = $this->toAmount($devCharges);
        if ($dev !== null) {
            $fees['Land_Use_Charge'] = $dev;
        }

        $isSua = ((int) ($sub->is_sua_unit ?? 0) === 1)
            || strtoupper((string) ($sub->unit_type ?? '')) === 'SUA';

        $billReference = $this->stBillReference($balanceBill);

        return [
            'is_st'              => true,
            'unit_type'          => $isSua ? 'SuA' : 'PuA',
            'fileno'             => trim((string) $sub->fileno),
            'sub_application_id' => (int) $sub->id,
            'fees'               => $fees,
            'has_fees'           => !empty($fees),
            'bill_reference'     => $billReference,
            // reference is UNIQUE on deeds_bill_balances_metadata, so tell the
            // form when the ST id is already spoken for rather than letting the
            // insert blow up on a constraint violation.
            'bill_reference_taken' => $billReference !== null && $this->referenceExists($billReference),
        ];
    }

    /**
     * Rebuild the ST bill's reference id from its stored balance bill.
     *
     * Must stay byte-identical to stBillReference() in programmes/bills.blade.php
     * — that is what makes the two modules share one reference.
     */
    private function stBillReference(?object $balanceBill): ?string
    {
        if (!$balanceBill || empty($balanceBill->bill_date)) {
            return null;
        }

        try {
            $date = \Illuminate\Support\Carbon::parse($balanceBill->bill_date)->format('Ymd');
        } catch (\Throwable $e) {
            return null;
        }

        return 'ST-BILL-' . $balanceBill->sub_application_id . '-' . $date;
    }

    private function referenceExists(string $reference): bool
    {
        return DB::connection('sqlsrv')->table('deeds_bill_balances_metadata')
            ->where('reference', $reference)
            ->exists();
    }

    /**
     * The ST fee columns are nvarchar, so guard against blanks and stray text.
     */
    private function toAmount($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(str_replace([',', '₦'], '', (string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function notSt(): array
    {
        return [
            'is_st'                => false,
            'unit_type'            => null,
            'fileno'               => null,
            'sub_application_id'   => null,
            'fees'                 => [],
            'has_fees'             => false,
            'bill_reference'       => null,
            'bill_reference_taken' => false,
        ];
    }

    /**
     * The billing row this balance hangs off — carried through purely so the
     * UI can show the linkage and the save path can update rather than insert.
     */
    private function billingIdFor(string $fileNumber, string $source): ?int
    {
        $id = DB::connection('sqlsrv')->table('billing')
            ->whereRaw('UPPER(LTRIM(RTRIM(ref_id))) = ?', [strtoupper($fileNumber)])
            ->where('source', $source)
            ->orderByDesc('ID')
            ->value('ID');

        return $id !== null ? (int) $id : null;
    }

    private function miss(): array
    {
        return [
            'found'        => false,
            'amount'       => null,
            'source'       => null,
            'source_label' => null,
            'file_number'  => null,
            'matched_on'   => null,
            'billing_id'   => null,
            'detail'       => [],
        ];
    }
}
