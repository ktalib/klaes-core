<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\Billing;
use App\Models\LandsOneStopShopApplication;
use App\Services\Billing\BillMasterService;
use Illuminate\Support\Facades\Log;

/**
 * OSS application-fee bills → Bill Master (phase E, docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * The billing row's ref_id is the Bill Ref ID (it used to be OSS-1-#####). The
 * bill goes to REV-M unpaid and is paid there; the module's own Payment_Status
 * is left as the OSS screens set it, so the OSS workflow is not held up.
 */
class OssBillMirror
{
    public const SOURCE_TABLE = 'billing';
    public const TYPE = 'oss_bill';

    public function __construct(private BillMasterService $bills)
    {
    }

    /** After an OSS bill is generated. Never throws into the caller. */
    public function sync(Billing $row): ?BillMaster
    {
        try {
            $app = LandsOneStopShopApplication::find($row->source_id);
            $fileNumber = $app->file_no ?? null;
            $file = $fileNumber ? $this->bills->fileDetails($fileNumber) : [];
            $district = ($app->district ?? null) ?: ($file['district'] ?? null);
            $lga = ($app->lga ?? null) ?: ($file['lga'] ?? null);

            return $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, (int) $row->ID, [
                'bill_ref_id' => $row->ref_id,
                'file_number' => $fileNumber,
                'file_indexing_id' => $file['file_indexing_id'] ?? null,
                'payer_name' => ($app->applicant_name ?? null) ?: (($app->business_name ?? null) ?: ($file['payer_name'] ?? null)),
                'payer_phone' => $app->phone ?? null,
                'payer_email' => $app->email ?? null,
                'plot_no' => ($app->plot_no ?? null) ?: ($file['plot_no'] ?? null),
                'district' => $district,
                'lga' => $lga,
                'location' => BillMasterService::location($district, $lga),
                'description' => 'OSS application fees',
            ], [
                ['code' => 'CHANGE_OF_NAME', 'name' => 'Change of name', 'amount' => (float) $row->Change_of_Name],
                ['code' => 'PROCESSING', 'name' => 'Processing fee', 'amount' => (float) $row->Processing_Fee],
                ['code' => 'SURVEY', 'name' => 'Survey fee', 'amount' => (float) $row->survey_fee],
                ['code' => 'APPLICATION_FORM', 'name' => 'Application form', 'amount' => (float) $row->Application_Form],
            ], (string) (auth()->user()->name ?? auth()->id() ?? 'system'));
        } catch (\Throwable $e) {
            Log::warning('OSS bill not mirrored to Bill Master', ['billing_id' => $row->ID ?? null, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
