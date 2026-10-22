<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\SpaBill;
use App\Services\Billing\BillMasterService;

/**
 * SPAS contravention bills → Bill Master (phase E, docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * A SPAS bill's reference_id is its Bill Ref ID (SpaBill::nextReference now
 * draws from Bill Master). The lines are the bill's own spa_bill_lines; an
 * older hand-entered bill with no lines is one line of its type. SPAS keeps
 * its own payments (spa_payments) as it always has.
 */
class SpasBillMirror
{
    public const SOURCE_TABLE = 'spa_bills';
    public const TYPE = 'contravention_bill';

    public function __construct(private BillMasterService $bills)
    {
    }

    public function sync(SpaBill $bill, string $actor, bool $create = false): ?BillMaster
    {
        // Older SPAS-BILL- bills come in through the approved consolidation (phase F).
        if (!$create && !BillMaster::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $bill->id)->exists()) {
            return null;
        }

        $bill->loadMissing(['lines', 'application']);
        $app = $bill->application;
        $file = $app && $app->file_number ? $this->bills->fileDetails($app->file_number) : null;
        $district = $app->district ?? null ?: ($file['district'] ?? null);
        $lga = $app->lga ?? null ?: ($file['lga'] ?? null);

        $attrs = [
            'file_number' => $app->file_number ?? null,
            'file_indexing_id' => $app->file_indexing_id ?? ($file['file_indexing_id'] ?? null),
            'payer_name' => $app->owner_name ?? null,
            'payer_phone' => $app->phone ?? null,
            'plot_no' => $file['plot_no'] ?? null,
            'district' => $district ?: null,
            'lga' => $lga ?: null,
            'location' => BillMasterService::location($district, $lga),
            'description' => $bill->bill_type ?: 'Contravention',
            'bill_date' => optional($bill->created_at)->toDateString() ?? now()->toDateString(),
            'due_date' => $bill->due_date ? \Illuminate\Support\Carbon::parse($bill->due_date)->toDateString() : null,
        ];
        if ($create) {
            $attrs['bill_ref_id'] = $bill->reference_id;
        }

        $lines = $bill->lines->isNotEmpty()
            ? $bill->lines->map(fn ($l) => ['name' => $l->name, 'amount' => (float) $l->amount, 'code' => $l->spa_bill_item_id ? 'SPA_' . $l->spa_bill_item_id : null])->values()->all()
            : [['name' => $bill->bill_type ?: 'Contravention', 'amount' => (float) $bill->amount, 'code' => null]];

        return $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, $bill->id, $attrs, $lines, $actor);
    }

    /**
     * SPAS bills that live in Bill Master (not cancelled), as spa_bill_id => bill_master row.
     * Such a bill is paid under REV-M, never on the SPAS screens, and the SPAS
     * workflow does not wait for its payment (user decision, 2026-10-19).
     *
     * @param iterable<int>|int $spaBillIds
     * @return \Illuminate\Support\Collection<int, BillMaster>
     */
    public static function inBillMaster($spaBillIds)
    {
        $ids = collect(is_iterable($spaBillIds) ? $spaBillIds : [$spaBillIds])->map(fn ($v) => (int) $v)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return BillMaster::query()->where('source_table', self::SOURCE_TABLE)->whereIn('source_id', $ids)
            ->where('status', '<>', BillMaster::STATUS_CANCELLED)->get()->keyBy(fn ($b) => (int) $b->source_id);
    }

    public function cancel(int $spaBillId, string $actor): void
    {
        $this->bills->cancelMirror(self::SOURCE_TABLE, $spaBillId, $actor);
    }
}
