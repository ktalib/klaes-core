<?php

namespace App\Services\Billing;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bill Master's numbering (docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 *   system   BILL-{year}-{000001}  bill_master.bill_ref_id   every bill a module generates
 *   legacy   LGB-{year}-{000001}   bill_legacy.bill_ref_id   bills captured under File Indexing
 *   receipt  RCT-{year}-{000001}   bill_receipts.receipt_no  official Ministry receipts
 *
 * Numbers run per year. Each column has a unique index, so two users drawing
 * the same number at once cannot both save; wrap the insert in retrying().
 */
class BillRef
{
    public const SYSTEM = 'system';
    public const LEGACY = 'legacy';
    public const RECEIPT = 'receipt';

    private const KINDS = [
        self::SYSTEM => ['BILL', 'bill_master', 'bill_ref_id'],
        self::LEGACY => ['LGB', 'bill_legacy', 'bill_ref_id'],
        self::RECEIPT => ['RCT', 'bill_receipts', 'receipt_no'],
    ];

    /** The next free number of $kind for $year (this year by default). */
    public static function next(string $kind, ?int $year = null): string
    {
        [$prefix, $table, $column] = self::KINDS[$kind] ?? throw new InvalidArgumentException("Unknown bill ref kind {$kind}.");
        $stem = $prefix . '-' . ($year ?? now()->year) . '-';

        $last = DB::connection('sqlsrv')->table($table)
            ->where($column, 'like', $stem . '%')
            ->orderByDesc($column)
            ->value($column);

        $serial = $last ? ((int) substr($last, strlen($stem))) + 1 : 1;

        return $stem . str_pad((string) $serial, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Run $insert, retrying when a number was taken by someone else in the
     * meantime. $insert must draw its number afresh on every call.
     */
    public static function retrying(callable $insert, int $attempts = 3)
    {
        for ($try = 1; ; $try++) {
            try {
                return $insert();
            } catch (QueryException $e) {
                // 2627 / 2601: SQL Server unique-key violation.
                $duplicate = in_array((int) ($e->errorInfo[1] ?? 0), [2627, 2601], true);
                if (!$duplicate || $try >= $attempts) {
                    throw $e;
                }
            }
        }
    }
}
