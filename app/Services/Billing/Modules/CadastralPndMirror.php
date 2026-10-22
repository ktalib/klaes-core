<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\Cadastral\CadastralBill;
use App\Services\Billing\BillMasterService;

/**
 * Cadastral Fees, Plan & Description bills → Bill Master (phase E,
 * docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * The P&D bill is its own REV-M bill (user decision, 2026-10-19): its bill_ref
 * is the Bill Ref ID, its lines are the fee sheet's lines (CadastralBill::LINES),
 * and it is paid under REV-M. Deeds Bill Balance no longer pulls it in as the
 * survey fee, so the file is not billed twice.
 */
class CadastralPndMirror
{
    public const SOURCE_TABLE = 'cadastral_bills';
    public const TYPE = 'fees_plan_description';

    public function __construct(private BillMasterService $bills)
    {
    }

    public function sync(CadastralBill $bill, string $actor, bool $create = false): ?BillMaster
    {
        if (!$create && !self::inBillMaster($bill->id)) {
            return null; // older CBL- bills come in through the approved consolidation (phase F)
        }

        $file = $bill->file_number ? $this->bills->fileDetails($bill->file_number) : [];

        $attrs = [
            'file_number' => $bill->file_number ?: null,
            'file_indexing_id' => $file['file_indexing_id'] ?? null,
            'payer_name' => $file['payer_name'] ?? null,
            'plot_no' => $file['plot_no'] ?? null,
            'district' => $file['district'] ?? null,
            'lga' => $file['lga'] ?? null,
            'location' => $file['location'] ?? null,
            'description' => 'Fees, Plan & Description' . ($bill->re_cad_ref ? " ({$bill->re_cad_ref})" : ''),
            'bill_date' => optional($bill->bill_date)->toDateString() ?? now()->toDateString(),
        ];
        if ($create) {
            $attrs['bill_ref_id'] = $bill->bill_ref;
        }

        $lines = [];
        foreach (CadastralBill::LINES as $key => $label) {
            $lines[] = ['code' => strtoupper($key), 'name' => $label, 'amount' => (float) ($bill->{"{$key}_amount"} ?? 0)];
        }
        // Fallback for a bill without the fee-sheet lines: its grand total as one line.
        if (round(array_sum(array_column($lines, 'amount')), 2) == 0.0 && (float) $bill->grand_total > 0) {
            $lines = [['code' => 'PND', 'name' => 'Fees, Plan & Description', 'amount' => (float) $bill->grand_total]];
        }

        $mirrored = $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, $bill->id, $attrs, $lines, $actor);

        if ($bill->status === 'Cancelled') {
            $this->bills->cancelMirror(self::SOURCE_TABLE, (int) $bill->id, $actor);
        }

        return $mirrored;
    }

    public static function inBillMaster(int $cadastralBillId): ?BillMaster
    {
        return BillMaster::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $cadastralBillId)
            ->where('status', '<>', BillMaster::STATUS_CANCELLED)->first();
    }

    public function cancel(int $cadastralBillId, string $actor): void
    {
        $this->bills->cancelMirror(self::SOURCE_TABLE, $cadastralBillId, $actor);
    }
}
