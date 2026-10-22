<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Services\Billing\BillMasterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ST final bills (final_bills) → Bill Master as a Bill Balance (phase E,
 * docs/plans/KLAES_REVM_Bill_Master_Plan.md; final_bills = "the Bill Balance
 * generated for PuA", decided 2026-10-19).
 *
 * A unit's final bill and the Deeds Bill Balance raised for the same ST file are
 * ONE bill, tied by the shared reference ST-BILL-{sub_application_id}-{Ymd}
 * (StBillBalanceResolver::stBillReference). It is kept as bill_master.legacy_ref:
 * whichever side bills first owns the Bill Master row, and the other does not
 * bill the file again (Deeds refuses; this mirror leaves the Deeds row alone).
 * No payment is taken here; the bill is paid under REV-M.
 */
class StFinalBillMirror
{
    public const SOURCE_TABLE = 'final_bills';
    public const TYPE = 'bill_balance';

    public function __construct(private BillMasterService $bills)
    {
    }

    /** After a final bill is saved. Never throws into the save. */
    public function syncFor(?int $applicationId, ?int $subApplicationId): ?BillMaster
    {
        try {
            $row = DB::connection('sqlsrv')->table(self::SOURCE_TABLE)
                ->when($subApplicationId, fn ($q) => $q->where('sub_application_id', $subApplicationId),
                    fn ($q) => $q->where('application_id', $applicationId))
                ->orderByDesc('id')->first();

            return $row ? $this->sync($row) : null;
        } catch (\Throwable $e) {
            Log::warning('ST final bill not mirrored to Bill Master', ['application' => $applicationId, 'sub_application' => $subApplicationId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function sync(object $row): ?BillMaster
    {
        $stRef = $this->stRef($row);
        $mine = BillMaster::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $row->id)->first();

        if (!$mine && $stRef) {
            $shared = BillMaster::query()->where('legacy_ref', $stRef)->where('status', '<>', BillMaster::STATUS_CANCELLED)->first();
            if ($shared) {
                return $shared; // already billed from Deeds Bill Balance: one bill, not two
            }
        }
        if ($mine && $mine->status === BillMaster::STATUS_CANCELLED) {
            return $mine;
        }

        $app = $row->sub_application_id
            ? DB::connection('sqlsrv')->table('subapplications')->where('id', $row->sub_application_id)->first()
            : DB::connection('sqlsrv')->table('mother_applications')->where('id', $row->application_id)->first();
        if (!$app) {
            return null; // the application no longer exists (e.g. the orphan test row): nothing to bill
        }

        $fileNumber = $app->fileno ?? ($app->np_fileno ?? null);
        $file = $fileNumber ? $this->bills->fileDetails($fileNumber) : [];
        $district = $row->sub_application_id ? ($app->unit_district ?? null) : ($app->property_district ?? null);
        $lga = $row->sub_application_id ? ($app->unit_lga ?? null) : ($app->property_lga ?? null);
        $district = $district ?: ($file['district'] ?? null);
        $lga = $lga ?: ($file['lga'] ?? null);
        $name = ($app->applicant_type ?? '') === 'corporate' && !empty($app->corporate_name)
            ? $app->corporate_name
            : trim(($app->first_name ?? '') . ' ' . ($app->surname ?? ''));

        $lines = [
            ['code' => 'PROCESSING', 'name' => 'Processing fee', 'amount' => (float) $row->processing_fee],
            ['code' => 'SURVEY', 'name' => 'Survey fee', 'amount' => (float) $row->survey_fee],
            ['code' => 'ASSIGNMENT', 'name' => 'Assignment fee', 'amount' => (float) $row->assignment_fee],
            ['code' => 'BILL_BALANCE', 'name' => 'Bill balance', 'amount' => (float) $row->bill_balance],
            ['code' => 'RECERTIFICATION', 'name' => 'Recertification fee', 'amount' => (float) $row->recertification_fee],
            ['code' => 'DEVELOPMENT', 'name' => 'Development charges', 'amount' => (float) $row->dev_charges],
        ];

        $attrs = [
            'file_number' => $fileNumber,
            'file_indexing_id' => $file['file_indexing_id'] ?? null,
            'payer_name' => $name ?: ($file['payer_name'] ?? null),
            'plot_no' => $row->sub_application_id ? ($file['plot_no'] ?? null) : (($app->property_plot_no ?? null) ?: ($file['plot_no'] ?? null)),
            'district' => $district,
            'lga' => $lga,
            'location' => BillMasterService::location($district, $lga),
            'description' => 'ST final bill (Bill Balance' . ($row->sub_application_id ? ', unit' : ', primary') . ')',
            'bill_date' => $row->bill_date ? \Illuminate\Support\Carbon::parse($row->bill_date)->toDateString() : now()->toDateString(),
            'legacy_ref' => $stRef,
        ];

        $bill = $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, (int) $row->id, $attrs, $lines, (string) (auth()->user()->name ?? auth()->id() ?? 'system'));

        if (($row->bill_status ?? null) === 'cancelled') {
            $this->bills->cancelMirror(self::SOURCE_TABLE, (int) $row->id, (string) (auth()->user()->name ?? auth()->id() ?? 'system'));
        }

        return $bill;
    }

    /** ST-BILL-{sub}-{Ymd}, byte-identical to StBillBalanceResolver::stBillReference. */
    private function stRef(object $row): ?string
    {
        if (empty($row->sub_application_id) || empty($row->bill_date)) {
            return null;
        }
        try {
            return 'ST-BILL-' . $row->sub_application_id . '-' . \Illuminate\Support\Carbon::parse($row->bill_date)->format('Ymd');
        } catch (\Throwable) {
            return null;
        }
    }
}
