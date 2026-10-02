<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\OpResettlementBill;
use App\Models\Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class OpResettlementBillController extends Controller
{
    public function index(): View
    {
        $bills = Billing::query()
            ->select([
                'billing.*',
                'oss.applicant_name',
                'oss.application_type',
            ])
            ->leftJoin('oss_applications as oss', 'oss.id', '=', 'billing.source_id')
            ->where('billing.source', 'OSS_OP_APPLICATION_FEE')
            ->orderByDesc('billing.created_at')
            ->limit(200)
            ->get();

        $totalBills = $bills->count();
        $paidBills  = $bills->where('Payment_Status', 'paid')->count();
        $unpaidBills = $totalBills - $paidBills;
        $totalAmount = $bills->sum(function ($b) {
            return ($b->Change_of_Name ?? 0) + ($b->Processing_Fee ?? 0) + ($b->survey_fee ?? 0) + ($b->Application_Form ?? 0);
        });

        return view('lands_one_stop_shop.bill', [
            'pageTitle'   => 'Lands One Stop Shop Bill',
            'tagline'     => '',
            'bills'       => $bills,
            'totalBills'  => $totalBills,
            'paidBills'   => $paidBills,
            'unpaidBills' => $unpaidBills,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function printBill(int $id): View
    {
        // Support both entry points:
        // 1) Direct billing ID from Bill page actions
        // 2) OSS application/source ID from Applications page actions
        $bill = Billing::query()
            ->where('ID', $id)
            ->where('source', 'OSS_OP_APPLICATION_FEE')
            ->first();

        if (!$bill) {
            $bill = Billing::query()
                ->where('source', 'OSS_OP_APPLICATION_FEE')
                ->where('source_id', $id)
                ->orderByDesc('ID')
                ->firstOrFail();
        }

        $application = DB::connection('sqlsrv')
            ->table('oss_applications')
            ->where('id', $bill->source_id)
            ->first();

        if (!$application) {
            $application = DB::connection('sqlsrv')
                ->table('oss_applications')
                ->where('instrument_capture_id', $bill->source_id)
                ->orderByDesc('id')
                ->first();
        }

        $captureId = $application->instrument_capture_id ?? $bill->source_id;
        $capture = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->where('id', $captureId)
            ->first();

        $fileNumberRow = DB::connection('sqlsrv')
            ->table('fileNumber as fn')
            ->leftJoin('mls_file_no as mfn', 'fn.tracking_id', '=', 'mfn.tracking_id')
            ->leftJoin('instrument_capture as source_capture', 'mfn.source_instrument_capture_id', '=', 'source_capture.id')
            ->leftJoin('pra as source_pra', 'mfn.source_pra_id', '=', 'source_pra.id')
            ->where('fn.id', $bill->source_id)
            ->select([
                'fn.id',
                'fn.mlsfNo',
                'fn.FileName',
                'fn.location',
                'fn.lga',
                'fn.plot_no',
                'fn.tp_no',
                'mfn.land_use as mls_land_use',
                'mfn.customer_type as mls_customer_type',
                'mfn.source as mls_source',
                'mfn.source_instrument_capture_id',
                'mfn.source_pra_id',
                DB::raw('COALESCE(source_capture.property_description, source_capture.property_location, source_pra.property_description) as source_property_description'),
                DB::raw('COALESCE(source_capture.land_use, source_pra.land_use) as source_land_use'),
            ])
            ->first();

        return view('lands_one_stop_shop.print.bill_receipt', [
            'bill'        => $bill,
            'application' => $application,
            'capture'     => $capture,
            'fileNumberRow' => $fileNumberRow,
        ]);
    }
}
