<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\ConsentApplication;
use App\Services\Billing\BillMasterService;
use App\Services\ConsentBillCalculator;
use Illuminate\Support\Facades\Log;

/**
 * Consent bills → Bill Master (phase E, docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * A consent bill is a set of columns on consent_applications, computed in
 * several places, so it is mirrored from the model's save
 * (ConsentApplication::booted). A bill computed for the first time is created
 * in Bill Master; a recomputed one already there is updated. The lines add up
 * to bill_total: stamp duty only when no transaction type routes it to
 * KIRS/FIRS (ConsentBillCalculator::compute). Consent takes no payment itself;
 * the bill is paid under REV-M.
 */
class ConsentBillMirror
{
    public const SOURCE_TABLE = 'consent_applications';
    public const TYPE = 'consent_bill';

    private const BILL_COLUMNS = ['bill_total', 'stamp_duty_amount', 'registration_fee', 'processing_fee', 'transaction_type', 'applicant_name', 'file_number'];

    public function __construct(private BillMasterService $bills)
    {
    }

    /** Called after a consent application is saved. Never throws into the save. */
    public function onSaved(ConsentApplication $app): void
    {
        try {
            $mirrored = BillMaster::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $app->id)->exists();
            $hasBill = $app->bill_total !== null && (float) $app->bill_total > 0;

            if ($mirrored) {
                if ($hasBill && ($app->wasRecentlyCreated || $app->wasChanged(self::BILL_COLUMNS))) {
                    $this->sync($app);
                }
                return;
            }

            // First bill on this application (older bills wait for the approved consolidation, phase F).
            $firstBill = $hasBill && ($app->wasRecentlyCreated || ($app->wasChanged('bill_total') && $app->getOriginal('bill_total') === null));
            if ($firstBill) {
                $this->sync($app);
            }
        } catch (\Throwable $e) {
            Log::warning('Consent bill not mirrored to Bill Master', ['consent_application' => $app->id, 'error' => $e->getMessage()]);
        }
    }

    public function sync(ConsentApplication $app): BillMaster
    {
        $file = $app->file_number ? $this->bills->fileDetails($app->file_number) : [];
        $toKirs = (bool) ConsentBillCalculator::transactionType($app->transaction_type);

        $lines = [
            ['code' => 'REGISTRATION', 'name' => 'Registration fee', 'amount' => (float) $app->registration_fee],
            ['code' => 'PROCESSING', 'name' => 'Processing fee', 'amount' => (float) $app->processing_fee],
        ];
        if (!$toKirs) {
            array_unshift($lines, ['code' => 'STAMP_DUTY', 'name' => 'Stamp duty', 'amount' => (float) $app->stamp_duty_amount]);
        }

        // Keep Bill Master's total exactly bill_total, whatever path computed it.
        $diff = round((float) $app->bill_total - array_sum(array_column($lines, 'amount')), 2);
        if ($diff != 0.0) {
            $lines[] = ['code' => 'ADJUSTMENT', 'name' => 'Adjustment', 'amount' => $diff];
        }

        return $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, (int) $app->id, [
            'file_number' => $app->file_number ?: null,
            'file_indexing_id' => $file['file_indexing_id'] ?? null,
            'payer_name' => $app->applicant_name ?: ($file['payer_name'] ?? null),
            'payer_phone' => $app->applicant_phone ?: null,
            'payer_address' => $app->applicant_address ?: null,
            'plot_no' => $file['plot_no'] ?? null,
            'district' => $file['district'] ?? null,
            'lga' => $file['lga'] ?? null,
            'location' => $file['location'] ?? null,
            'description' => 'Consent' . ($app->consent_type ? " ({$app->consent_type})" : '')
                . ($app->application_tracking_no ? " · {$app->application_tracking_no}" : ''),
        ], $lines, (string) (auth()->user()->name ?? auth()->id() ?? 'system'));
    }

    public function cancel(int $consentApplicationId): void
    {
        $this->bills->cancelMirror(self::SOURCE_TABLE, $consentApplicationId, (string) (auth()->user()->name ?? auth()->id() ?? 'system'));
    }
}
