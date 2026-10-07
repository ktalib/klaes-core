<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The investigation hold on a cadastral receipt (rebuild plan Phase 3).
 *
 * An officer places a hold by hand, and a held receipt cannot be registered
 * until an officer clears it with a remark. Duplicate-register and same-plot
 * findings are recorded as duplicate_flag / duplicate_note but, since
 * 2026-10-07, no longer hold the file automatically (by request).
 *
 * WORKS BEFORE ITS MIGRATION. The hold_* columns arrive with
 * 2026_10_01_110000_add_hold_columns_to_cadastral_file_receipts. Until then
 * available() is false: nothing here writes a hold column, the queue shows no
 * hold, and registration goes ahead on a flagged file exactly as in Phase 2 —
 * the duplicate flag and note are still kept, as they always were.
 *
 * The hold_* columns carry the current hold only; audit_logs carries each
 * placement and clearing.
 */
class ReceiptHolds
{
    /** hasColumn costs a round trip; one answer per request (per process). */
    private static ?bool $available = null;

    public function __construct(
        private CadastralRegistryLookup $lookup,
        private AuditService $audit = new AuditService(),
    ) {}

    public static function available(): bool
    {
        return self::$available ??= Schema::connection('sqlsrv')->hasColumn('cadastral_file_receipts', 'hold_status');
    }

    /** Forget the cached answer — for a script that adds the columns mid-run. */
    public static function refresh(): void
    {
        self::$available = null;
    }

    /**
     * Duplicate-register and double-allocation findings for one file number.
     *
     * Slow (several reads of 170k-row tables), so callers run it before they
     * open their transaction.
     *
     * @return array{flag: bool, note: ?string}
     */
    public function findings(?string $fileNumber): array
    {
        $notes = [];

        $duplicates = $this->lookup->duplicates($fileNumber);
        if ($duplicates->isNotEmpty()) {
            $notes[] = $duplicates->count() . ' entry(ies) in the duplicate register.';
        }

        $doubles = $this->lookup->possibleDoubleAllocations($fileNumber);
        if ($doubles->isNotEmpty()) {
            $notes[] = $doubles->count() . ' other file(s) on the same plot number: '
                . $doubles->pluck('file_number')->take(5)->implode(', ')
                . ($doubles->count() > 5 ? ' …' : '') . '.';
        }

        return [
            'flag' => $notes !== [],
            'note' => $notes === [] ? null : implode(' ', $notes),
        ];
    }

    /**
     * The hold_* values for a new receipt. Always empty: findings no longer
     * hold a file automatically (2026-10-07, by request). They are kept as
     * duplicate_flag / duplicate_note, and an officer can still hold by hand.
     */
    public function intakeHold(array $findings): array
    {
        return [];
    }

    /**
     * Apply registration-time findings to a LOCKED receipt, in memory.
     *
     * Always refreshes duplicate_flag / duplicate_note. Returns true only when
     * the receipt is already on hold (placed by hand, or an automatic hold from
     * before 2026-10-07 not yet cleared); findings alone never hold it.
     */
    public function holdForRegistration(CadastralFileReceipt $receipt, array $findings): bool
    {
        $receipt->duplicate_flag = $findings['flag'];
        $receipt->duplicate_note = $findings['note'];

        return self::available() && $receipt->isOnHold();
    }

    /** Put the hold on a receipt, in memory. */
    public function fillHold(CadastralFileReceipt $receipt, string $reason): void
    {
        $receipt->forceFill([
            'hold_status'     => CadastralFileReceipt::HOLD_ON,
            'hold_reason'     => mb_substr($reason, 0, 1000),
            'hold_placed_by'  => auth()->id(),
            'hold_placed_at'  => now(),
            // A new hold starts with no clearing; the previous one is in audit_logs.
            'hold_cleared_by' => null,
            'hold_cleared_at' => null,
            'hold_clear_note' => null,
        ]);
    }

    /** Lift the hold, in memory. The reason and who placed it are kept. */
    public function fillClear(CadastralFileReceipt $receipt, string $note): void
    {
        $receipt->forceFill([
            'hold_status'     => CadastralFileReceipt::HOLD_CLEARED,
            'hold_cleared_by' => auth()->id(),
            'hold_cleared_at' => now(),
            'hold_clear_note' => mb_substr($note, 0, 1000),
        ]);
    }

    /**
     * Audit a placement or clearing. Called after the transaction commits: the
     * audit is a record of the change, not the change, and a failure here must
     * not undo a hold the officer has been told is set.
     */
    public function audit(CadastralFileReceipt $receipt, string $action, array $old, array $new): void
    {
        try {
            $this->audit->logAction(
                $action,
                'cadastral_file_receipt',
                $receipt->id,
                $old,
                $new,
                "{$receipt->file_number} ({$receipt->receipt_ref})"
            );
        } catch (\Throwable $e) {
            Log::warning("Cadastral {$action} audit failed: " . $e->getMessage(), ['receipt_id' => $receipt->id]);
        }
    }
}
