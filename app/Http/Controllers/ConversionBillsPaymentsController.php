<?php

namespace App\Http\Controllers;

use App\Services\ConversionBettermentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Handles Bills, Payments, and Payment Reports for Conversion Applications.
 *
 * Bills are stored in the `billing` table with source = 'PP_BETTERMENT_BILL'.
 * Betterment fee formula: Total = Base Fee + max(0, (Area − Base Area) × ₦5/m²).
 */
class ConversionBillsPaymentsController extends Controller
{
    protected ConversionBettermentService $bettermentService;

    public function __construct(ConversionBettermentService $bettermentService)
    {
        $this->bettermentService = $bettermentService;
    }

    /* ------------------------------------------------------------------ */
    /*  1. BILLS                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Bills index page — lists betterment bills for Conversion Applications.
     */
    public function bills()
    {
        $PageTitle = 'Conversion Applications - Betterment Bills';
        $PageDescription = 'Manage betterment bills for conversion applications';

        $rates = $this->bettermentService->getAllRates();

        return view('physical_planning.conversion.bills', compact(
            'PageTitle',
            'PageDescription',
            'rates'
        ));
    }

    /**
     * DataTable AJAX endpoint for bills listing.
     */
    public function billsData(Request $request)
    {
        $query = DB::connection('sqlsrv')
            ->table('billing as b')
            ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                $join->on('b.source_id', '=', DB::raw('CAST(jsi.id AS NVARCHAR(50))'));
            })
            ->where('b.source', 'PP_BETTERMENT_BILL')
            ->select([
                'b.ID as bill_id',
                'b.ref_id',
                'b.Sectional_Title_File_No as file_number',
                'b.Betterment_Charges as total_fee',
                'b.property_value as area',
                'b.betterment_rate as rate',
                'b.Payment_Status as status',
                'b.Betterment_receipt as receipt_no',
                'b.receipt_date',
                'b.created_at',
                'b.updated_at',
                'jsi.applicant_name',
                'jsi.applied_land_use',
                'jsi.prevailing_land_use',
                'jsi.plot_number',
                'jsi.location',
            ]);

        // Global search
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('b.ref_id', 'LIKE', "%{$search}%")
                  ->orWhere('b.Sectional_Title_File_No', 'LIKE', "%{$search}%")
                  ->orWhere('jsi.applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('b.Payment_Status', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('b.Payment_Status', $request->status);
        }

        $totalRecords = DB::connection('sqlsrv')
            ->table('billing')
            ->where('source', 'PP_BETTERMENT_BILL')
            ->count();

        $filteredRecords = $query->count();

        // Sorting
        $sortCols = [
            0 => 'b.ref_id',
            1 => 'b.Sectional_Title_File_No',
            2 => 'jsi.applicant_name',
            3 => 'jsi.applied_land_use',
            4 => 'b.property_value',
            5 => 'b.Betterment_Charges',
            6 => 'b.Payment_Status',
            7 => 'b.created_at',
        ];
        $orderCol = $request->input('order.0.column', 7);
        $orderDir = $request->input('order.0.dir', 'desc');
        $query->orderBy($sortCols[$orderCol] ?? 'b.created_at', $orderDir);

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $rows = $query->skip($start)->take($length)->get();

        $data = $rows->map(function ($row) {
            return [
                'bill_id'        => $row->bill_id,
                'ref_id'         => $row->ref_id,
                'file_number'    => $row->file_number,
                'applicant_name' => $row->applicant_name ?? '-',
                'land_use'       => $row->applied_land_use ?? '-',
                'area'           => $row->area ? number_format((float) $row->area, 2) . ' m²' : '-',
                'total_fee'      => $row->total_fee ? '₦' . number_format((float) $row->total_fee, 2) : '-',
                'status'         => $row->status ?? 'Pending',
                'receipt_no'     => $row->receipt_no ?? '-',
                'created_at'     => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y') : '-',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data->values(),
        ]);
    }

    /**
     * Look up JSI reports eligible for billing (not yet billed or with Pending bills).
     */
    public function searchJsiForBilling(Request $request)
    {
        $term = $request->input('q', '');

        $results = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports as jsi')
            ->leftJoin('billing as b', function ($join) {
                $join->on(DB::raw('CAST(jsi.id AS NVARCHAR(50))'), '=', 'b.source_id')
                     ->where('b.source', '=', 'PP_BETTERMENT_BILL');
            })
            ->whereNull('jsi.sub_application_id')
            ->where(function ($q) {
                $q->where('jsi.source', 'Conversion Applications')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-%');
            })
            ->where(function ($q) use ($term) {
                $q->where('jsi.file_number', 'LIKE', "%{$term}%")
                  ->orWhere('jsi.applicant_name', 'LIKE', "%{$term}%");
            })
            ->select([
                'jsi.id as jsi_report_id',
                'jsi.file_number',
                'jsi.applicant_name',
                'jsi.applied_land_use',
                'jsi.plot_number',
                'jsi.location',
                'jsi.application_id',
                'b.ID as existing_bill_id',
                'b.ref_id as existing_ref_id',
                'b.Payment_Status as existing_status',
            ])
            ->orderBy('jsi.file_number')
            ->take(20)
            ->get();

        return response()->json(['results' => $results]);
    }

    /**
     * Calculate fee (preview) without saving.
     */
    public function calculateFee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'area'     => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $calc = $this->bettermentService->calculate($request->category, (float) $request->area);

        return response()->json(['success' => true, 'data' => $calc]);
    }

    /**
     * Generate / update a betterment bill for a JSI report.
     */
    public function generateBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jsi_report_id' => 'required|integer',
            'file_number'   => 'required|string|max:100',
            'category'      => 'required|string',
            'area'          => 'required|numeric|min:0',
            'application_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->bettermentService->generateBill(
            $request->jsi_report_id,
            $request->file_number,
            $request->category,
            (float) $request->area,
            $request->application_id
        );

        return response()->json($result);
    }

    /**
     * Show a single bill detail (JSON).
     */
    public function showBill($id)
    {
        $bill = DB::connection('sqlsrv')
            ->table('billing as b')
            ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                $join->on('b.source_id', '=', DB::raw('CAST(jsi.id AS NVARCHAR(50))'));
            })
            ->where('b.ID', $id)
            ->where('b.source', 'PP_BETTERMENT_BILL')
            ->select([
                'b.*',
                'jsi.applicant_name',
                'jsi.applied_land_use',
                'jsi.prevailing_land_use',
                'jsi.plot_number',
                'jsi.location',
                'jsi.inspection_date',
                'jsi.compliance_status',
            ])
            ->first();

        if (!$bill) {
            return response()->json(['success' => false, 'message' => 'Bill not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $bill]);
    }

    /**
     * Print Schedule of Payment for a conversion application.
     */
    public function printScheduleOfPayment($applicationId)
    {
        $jsi = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->where('application_id', $applicationId)
            ->where(function ($query) {
                $query->where('source', 'Conversion Applications')
                    ->orWhere('file_number', 'LIKE', 'CON-%');
            })
            ->orderByDesc('id')
            ->first();

        if (!$jsi) {
            abort(404, 'Joint Site Inspection report not found for this conversion application.');
        }

        $bill = DB::connection('sqlsrv')
            ->table('billing')
            ->where('source', 'PP_BETTERMENT_BILL')
            ->where('source_id', (string) $jsi->id)
            ->orderByDesc('ID')
            ->first();

        $viewData = [
            'applicationId' => $applicationId,
            'name' => $jsi->applicant_name ?? 'N/A',
            'fileNumber' => $jsi->file_number ?? (string) $applicationId,
            'purposeOfPayment' => 'Conversion Betterment Charges - ' . ($jsi->applied_land_use ?? $jsi->purpose ?? 'Land Use'),
            'amount' => $bill ? (float) ($bill->Betterment_Charges ?? 0) : null,
            'billReference' => $bill->ref_id ?? '',
            'generatedDate' => now(),
        ];

        return view('physical_planning.conversion.print.schedule_of_payment', $viewData);
    }

    /**
     * Record payment against a bill.
     */
    public function recordPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no'   => 'required|string|max:100',
            'receipt_date' => 'required|date',
            'amount_paid'  => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $bill = DB::connection('sqlsrv')
            ->table('billing')
            ->where('ID', $id)
            ->where('source', 'PP_BETTERMENT_BILL')
            ->first();

        if (!$bill) {
            return response()->json(['success' => false, 'message' => 'Bill not found'], 404);
        }

        $totalFee = (float) ($bill->Betterment_Charges ?? 0);
        $amountPaid = (float) $request->amount_paid;

        $status = $amountPaid >= $totalFee ? 'Paid' : 'Partial';

        DB::connection('sqlsrv')
            ->table('billing')
            ->where('ID', $id)
            ->update([
                'Betterment_receipt' => $request->receipt_no,
                'receipt_date'       => $request->receipt_date,
                'Payment_Status'     => $status,
                'updated_at'         => now(),
            ]);

        Log::info("PP Betterment Payment recorded", [
            'bill_id' => $id,
            'receipt'  => $request->receipt_no,
            'amount'   => $amountPaid,
            'status'   => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Payment recorded — status: {$status}",
            'data'    => ['status' => $status],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  2. PAYMENTS                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Payments index page.
     */
    public function payments()
    {
        $PageTitle = 'Conversion Applications - Payments';
        $PageDescription = 'View payment records for conversion betterment bills';

        return view('physical_planning.conversion.payments', compact(
            'PageTitle',
            'PageDescription'
        ));
    }

    /**
     * DataTable AJAX endpoint for payments listing.
     */
    public function paymentsData(Request $request)
    {
        $query = DB::connection('sqlsrv')
            ->table('billing as b')
            ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                $join->on('b.source_id', '=', DB::raw('CAST(jsi.id AS NVARCHAR(50))'));
            })
            ->where('b.source', 'PP_BETTERMENT_BILL')
            ->whereNotNull('b.Betterment_receipt')
            ->select([
                'b.ID as bill_id',
                'b.ref_id',
                'b.Sectional_Title_File_No as file_number',
                'b.Betterment_Charges as total_fee',
                'b.Betterment_receipt as receipt_no',
                'b.receipt_date',
                'b.Payment_Status as status',
                'b.created_at',
                'b.updated_at',
                'jsi.applicant_name',
                'jsi.applied_land_use',
            ]);

        // Global search
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('b.ref_id', 'LIKE', "%{$search}%")
                  ->orWhere('b.Sectional_Title_File_No', 'LIKE', "%{$search}%")
                  ->orWhere('jsi.applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('b.Betterment_receipt', 'LIKE', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('b.receipt_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('b.receipt_date', '<=', $request->end_date);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('b.Payment_Status', $request->status);
        }

        $totalRecords = DB::connection('sqlsrv')
            ->table('billing')
            ->where('source', 'PP_BETTERMENT_BILL')
            ->whereNotNull('Betterment_receipt')
            ->count();

        $filteredRecords = $query->count();

        // Sorting
        $sortCols = [
            0 => 'b.ref_id',
            1 => 'b.Sectional_Title_File_No',
            2 => 'jsi.applicant_name',
            3 => 'b.Betterment_Charges',
            4 => 'b.Betterment_receipt',
            5 => 'b.receipt_date',
            6 => 'b.Payment_Status',
        ];
        $orderCol = $request->input('order.0.column', 5);
        $orderDir = $request->input('order.0.dir', 'desc');
        $query->orderBy($sortCols[$orderCol] ?? 'b.receipt_date', $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $rows = $query->skip($start)->take($length)->get();

        $data = $rows->map(function ($row) {
            return [
                'bill_id'        => $row->bill_id,
                'ref_id'         => $row->ref_id,
                'file_number'    => $row->file_number,
                'applicant_name' => $row->applicant_name ?? '-',
                'land_use'       => $row->applied_land_use ?? '-',
                'total_fee'      => $row->total_fee ? '₦' . number_format((float) $row->total_fee, 2) : '-',
                'receipt_no'     => $row->receipt_no ?? '-',
                'receipt_date'   => $row->receipt_date ? \Carbon\Carbon::parse($row->receipt_date)->format('d M Y') : '-',
                'status'         => $row->status ?? 'Pending',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data->values(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  3. PAYMENT REPORTS                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Payment Reports index page.
     */
    public function paymentReports()
    {
        $PageTitle = 'Conversion Applications - Payment Reports';
        $PageDescription = 'Summary and analytics for conversion betterment payments';

        // Summary stats
        $stats = $this->getPaymentStats();

        return view('physical_planning.conversion.payment_reports', compact(
            'PageTitle',
            'PageDescription',
            'stats'
        ));
    }

    /**
     * DataTable AJAX endpoint for payment reports.
     */
    public function paymentReportsData(Request $request)
    {
        $query = DB::connection('sqlsrv')
            ->table('billing as b')
            ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                $join->on('b.source_id', '=', DB::raw('CAST(jsi.id AS NVARCHAR(50))'));
            })
            ->where('b.source', 'PP_BETTERMENT_BILL')
            ->select([
                'b.ID as bill_id',
                'b.ref_id',
                'b.Sectional_Title_File_No as file_number',
                'b.Betterment_Charges as total_fee',
                'b.Betterment_receipt as receipt_no',
                'b.receipt_date',
                'b.Payment_Status as status',
                'b.property_value as area',
                'b.created_at',
                'b.updated_at',
                'jsi.applicant_name',
                'jsi.applied_land_use',
                'jsi.plot_number',
                'jsi.location',
            ]);

        // Global search
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('b.ref_id', 'LIKE', "%{$search}%")
                  ->orWhere('b.Sectional_Title_File_No', 'LIKE', "%{$search}%")
                  ->orWhere('jsi.applicant_name', 'LIKE', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('b.created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('b.created_at', '<=', $request->end_date);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('b.Payment_Status', $request->status);
        }

        $totalRecords = DB::connection('sqlsrv')
            ->table('billing')
            ->where('source', 'PP_BETTERMENT_BILL')
            ->count();

        $filteredRecords = $query->count();

        $sortCols = [
            0 => 'b.ref_id',
            1 => 'b.Sectional_Title_File_No',
            2 => 'jsi.applicant_name',
            3 => 'jsi.applied_land_use',
            4 => 'b.property_value',
            5 => 'b.Betterment_Charges',
            6 => 'b.Payment_Status',
            7 => 'b.receipt_date',
            8 => 'b.created_at',
        ];
        $orderCol = $request->input('order.0.column', 8);
        $orderDir = $request->input('order.0.dir', 'desc');
        $query->orderBy($sortCols[$orderCol] ?? 'b.created_at', $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $rows = $query->skip($start)->take($length)->get();

        $data = $rows->map(function ($row) {
            return [
                'bill_id'        => $row->bill_id,
                'ref_id'         => $row->ref_id,
                'file_number'    => $row->file_number,
                'applicant_name' => $row->applicant_name ?? '-',
                'land_use'       => $row->applied_land_use ?? '-',
                'location'       => $row->location ?? '-',
                'area'           => $row->area ? number_format((float) $row->area, 2) . ' m²' : '-',
                'total_fee'      => $row->total_fee ? '₦' . number_format((float) $row->total_fee, 2) : '-',
                'receipt_no'     => $row->receipt_no ?? '-',
                'receipt_date'   => $row->receipt_date ? \Carbon\Carbon::parse($row->receipt_date)->format('d M Y') : '-',
                'status'         => $row->status ?? 'Pending',
                'created_at'     => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y') : '-',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data->values(),
        ]);
    }

    /**
     * Export payment report summary stats (AJAX).
     */
    public function paymentStats()
    {
        return response()->json(['success' => true, 'data' => $this->getPaymentStats()]);
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                           */
    /* ------------------------------------------------------------------ */

    private function getPaymentStats(): array
    {
        $base = DB::connection('sqlsrv')
            ->table('billing')
            ->where('source', 'PP_BETTERMENT_BILL');

        $totalBills = (clone $base)->count();
        $totalBilled = (float) (clone $base)->sum(DB::raw('CAST(Betterment_Charges AS FLOAT)'));
        $paidCount = (clone $base)->where('Payment_Status', 'Paid')->count();
        $paidAmount = (float) (clone $base)->where('Payment_Status', 'Paid')->sum(DB::raw('CAST(Betterment_Charges AS FLOAT)'));
        $pendingCount = (clone $base)->whereIn('Payment_Status', ['Pending', ''])->orWhereNull('Payment_Status')->where('source', 'PP_BETTERMENT_BILL')->count();
        $pendingAmount = $totalBilled - $paidAmount;

        return [
            'total_bills'    => $totalBills,
            'total_billed'   => $totalBilled,
            'paid_count'     => $paidCount,
            'paid_amount'    => $paidAmount,
            'pending_count'  => $pendingCount,
            'pending_amount' => max(0, $pendingAmount),
        ];
    }
}
