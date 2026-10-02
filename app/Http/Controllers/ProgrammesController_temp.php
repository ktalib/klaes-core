<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProgrammesController extends Controller
{
    private function getApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    public function FieldData()
    {
        $PageTitle = 'Field Data Collection';
        $PageDescription = 'Import, collect, and manage field data from Survey123';

        return view('programmes.field_data', compact('PageTitle', 'PageDescription'));
    }

    public function bills()
    {
        $PageTitle = 'Bills Management';
        $PageDescription = 'Manage Initial Bills, Betterment Bills, and Bill Balance';
        
        // Get all file numbers for Select2 dropdown
        $primaryFiles = DB::connection('sqlsrv')->table('mother_applications')
            ->select('id', 'fileno', 'first_name', 'surname', 'corporate_name')
            ->whereNotNull('fileno')
            ->get()
            ->map(function($item) {
                $ownerName = !empty($item->corporate_name) 
                    ? $item->corporate_name 
                    : trim($item->first_name . ' ' . $item->surname);
                return [
                    'id' => $item->id,
                    'fileno' => $item->fileno,
                    'owner_name' => $ownerName,
                    'type' => 'primary'
                ];
            });

        $unitFiles = DB::connection('sqlsrv')->table('subapplications')
            ->select('id', 'fileno', 'first_name', 'surname', 'corporate_name')
            ->whereNotNull('fileno')
            ->get()
            ->map(function($item) {
                $ownerName = !empty($item->corporate_name) 
                    ? $item->corporate_name 
                    : trim($item->first_name . ' ' . $item->surname);
                return [
                    'id' => $item->id,
                    'fileno' => $item->fileno,
                    'owner_name' => $ownerName,
                    'type' => 'unit'
                ];
            });

        $allFiles = $primaryFiles->merge($unitFiles)->sortBy('fileno');

        // Get initial bills data directly from mother_applications table for primary applications
        $primaryInitialBills = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'id',
                'id as application_id',
                'application_fee as primary_application_fee',
                'processing_fee as primary_processing_fee',
                'site_plan_fee as primary_site_plan_fee',
                'payment_date as primary_payment_date',
                'receipt_number as primary_receipt_number',
                'fileno as primary_fileno',
                'first_name as primary_first_name',
                'surname as primary_surname',
                'corporate_name as primary_corporate_name',
                'created_at',
                'Payment_Status'
            )
            ->where(function($query) {
                $query->whereNotNull('application_fee')
                      ->orWhereNotNull('processing_fee')
                      ->orWhereNotNull('site_plan_fee');
            })
            ->get();

        // Get initial bills data directly from subapplications table for unit applications
        $unitInitialBills = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'id',
                'id as sub_application_id',
                'application_fee as unit_application_fee',
                'processing_fee as unit_processing_fee',
                'site_plan_fee as unit_site_plan_fee',
                'payment_date as unit_payment_date',
                'receipt_number as unit_receipt_number',
                'fileno as unit_fileno',
                'first_name as unit_first_name',
                'surname as unit_surname',
                'corporate_name as unit_corporate_name',
                'created_at',
                'Payment_Status'
            )
            ->where(function($query) {
                $query->whereNotNull('application_fee')
                      ->orWhereNotNull('processing_fee')
                      ->orWhereNotNull('site_plan_fee');
            })
            ->get();

        // Combine all initial bills from application tables only (no billing table)
        $initialBills = $primaryInitialBills->merge($unitInitialBills);

        // Get betterment bills data
        $bettermentBills = DB::connection('sqlsrv')->table('billing')
            ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
            ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
            ->select(
                'billing.*',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.corporate_name as primary_corporate_name',
                'subapplications.fileno as unit_fileno',
                'subapplications.first_name as unit_first_name',
                'subapplications.surname as unit_surname',
                'subapplications.corporate_name as unit_corporate_name'
            )
            ->whereNotNull('billing.Betterment_Charges')
            ->get();

        // Get final bills data
        $finalBills = DB::connection('sqlsrv')->table('final_bills')
            ->leftJoin('mother_applications', 'final_bills.application_id', '=', 'mother_applications.id')
            ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
            ->select(
                'final_bills.*',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.corporate_name as primary_corporate_name',
                'subapplications.fileno as unit_fileno',
                'subapplications.first_name as unit_first_name',
                'subapplications.surname as unit_surname',
                'subapplications.corporate_name as unit_corporate_name'
            )
            ->get();

        // Process owner names for all bill types
        foreach ([$initialBills, $bettermentBills, $finalBills] as $billCollection) {
            foreach ($billCollection as $bill) {
                if (!empty($bill->primary_fileno)) {
                    $bill->fileno = $bill->primary_fileno;
                    $bill->owner_name = !empty($bill->primary_corporate_name) 
                        ? $bill->primary_corporate_name 
                        : trim(($bill->primary_first_name ?? '') . ' ' . ($bill->primary_surname ?? ''));
                } else {
                    $bill->fileno = $bill->unit_fileno;
                    $bill->owner_name = !empty($bill->unit_corporate_name) 
                        ? $bill->unit_corporate_name 
                        : trim(($bill->unit_first_name ?? '') . ' ' . ($bill->unit_surname ?? ''));
                }
            }
        }

        // Convert bill collections to arrays for blade filtering
        $initialBills = $initialBills->toArray();
        $bettermentBills = $bettermentBills->toArray();
        $finalBills = $finalBills->toArray();

        return view('programmes.bills', compact(
            'PageTitle', 
            'PageDescription', 
            'allFiles', 
            'initialBills', 
            'bettermentBills', 
            'finalBills'
        ));
    }

    /**
     * View bill details
     */
    public function viewBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);
            
            if (!$bill) {
                return response()->json(['error' => 'Bill not found'], 404);
            }

            return response()->json([
                'success' => true,
                'bill' => $bill,
                'html' => view('programmes.partials.bill-details', compact('bill', 'type'))->render()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load bill details: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Print bill
     */
    public function printBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);
            
            if (!$bill) {
                abort(404, 'Bill not found');
            }

            return view('programmes.partials.bill-print', compact('bill', 'type'));
        } catch (\Exception $e) {
            abort(500, 'Failed to load bill for printing: ' . $e->getMessage());
        }
    }

    /**
     * Download bill as PDF
     */
    public function downloadBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);
            
            if (!$bill) {
                abort(404, 'Bill not found');
            }

            // Generate PDF using DomPDF or similar
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('programmes.partials.bill-pdf', compact('bill', 'type'));
            
            $filename = $this->generateBillFilename($bill, $type);
            
            return $pdf->download($filename);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get bill data based on type and ID
     */
    private function getBillData($type, $id)
    {
        switch ($type) {
            case 'initial':
                // For initial bills, get data from application tables instead of billing table
                $primaryBill = DB::connection('sqlsrv')->table('mother_applications')
                    ->select(
                        'id',
                        'id as application_id',
                        'fileno',
                        'first_name',
                        'surname',
                        'corporate_name',
                        'property_street_name',
                        'property_lga',
                        'application_fee',
                        'processing_fee',
                        'site_plan_fee',
                        'payment_date',
                        'receipt_number',
                        'Payment_Status',
                        'created_at'
                    )
                    ->where('id', $id)
                    ->first();
                
                if ($primaryBill) {
                    // Map to expected structure
                    $primaryBill->primary_fileno = $primaryBill->fileno;
                    $primaryBill->primary_first_name = $primaryBill->first_name;
                    $primaryBill->primary_surname = $primaryBill->surname;
                    $primaryBill->primary_corporate_name = $primaryBill->corporate_name;
                    $primaryBill->primary_property_street = $primaryBill->property_street_name;
                    $primaryBill->primary_property_lga = $primaryBill->property_lga;
                    $primaryBill->Scheme_Application_Fee = $primaryBill->application_fee;
                    $primaryBill->Processing_Fee = $primaryBill->processing_fee;
                    $primaryBill->Site_Plan_Fee = $primaryBill->site_plan_fee;
                    return $primaryBill;
                }
                
                // Check subapplications
                $unitBill = DB::connection('sqlsrv')->table('subapplications')
                    ->select(
                        'id',
                        'id as sub_application_id',
                        'fileno',
                        'first_name',
                        'surname',
                        'corporate_name',
                        'property_location',
                        'application_fee',
                        'processing_fee',
                        'site_plan_fee',
                        'payment_date',
                        'receipt_number',
                        'Payment_Status',
                        'created_at'
                    )
                    ->where('id', $id)
                    ->first();
                
                if ($unitBill) {
                    // Map to expected structure
                    $unitBill->unit_fileno = $unitBill->fileno;
                    $unitBill->unit_first_name = $unitBill->first_name;
                    $unitBill->unit_surname = $unitBill->surname;
                    $unitBill->unit_corporate_name = $unitBill->corporate_name;
                    $unitBill->unit_property_location = $unitBill->property_location;
                    $unitBill->Unit_Application_Fees = $unitBill->application_fee;
                    $unitBill->Processing_Fee = $unitBill->processing_fee;
                    $unitBill->Site_Plan_Fee = $unitBill->site_plan_fee;
                    return $unitBill;
                }
                
                return null;

            case 'betterment':
                $bill = DB::connection('sqlsrv')->table('billing')
                    ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                    ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
                    ->select(
                        'billing.*',
                        'mother_applications.fileno as primary_fileno',
                        'mother_applications.first_name as primary_first_name',
                        'mother_applications.surname as primary_surname',
                        'mother_applications.corporate_name as primary_corporate_name',
                        'mother_applications.property_street_name as primary_property_street',
                        'mother_applications.property_lga as primary_property_lga',
                        'subapplications.fileno as unit_fileno',
                        'subapplications.first_name as unit_first_name',
                        'subapplications.surname as unit_surname',
                        'subapplications.corporate_name as unit_corporate_name',
                        'subapplications.property_location as unit_property_location'
                    )
                    ->where(function($query) use ($id) {
                        $query->where('billing.application_id', $id)
                              ->orWhere('billing.sub_application_id', $id);
                    })
                    ->whereNotNull('billing.Betterment_Charges')
                    ->first();
                
                // Add a fallback ID if the bill doesn't have one
                if ($bill && !isset($bill->id)) {
                    $bill->id = $bill->application_id ?? $bill->sub_application_id ?? $id;
                }
                return $bill;

            case 'balance':
                $bill = DB::connection('sqlsrv')->table('final_bills')
                    ->leftJoin('mother_applications', 'final_bills.application_id', '=', 'mother_applications.id')
                    ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
                    ->select(
                        'final_bills.*',
                        'mother_applications.fileno as primary_fileno',
                        'mother_applications.first_name as primary_first_name',
                        'mother_applications.surname as primary_surname',
                        'mother_applications.corporate_name as primary_corporate_name',
                        'mother_applications.property_street_name as primary_property_street',
                        'mother_applications.property_lga as primary_property_lga',
                        'subapplications.fileno as unit_fileno',
                        'subapplications.first_name as unit_first_name',
                        'subapplications.surname as unit_surname',
                        'subapplications.corporate_name as unit_corporate_name',
                        'subapplications.property_location as unit_property_location'
                    )
                    ->where('final_bills.id', $id)
                    ->first();
                
                // Add a fallback ID if the bill doesn't have one
                if ($bill && !isset($bill->id)) {
                    $bill->id = $bill->application_id ?? $bill->sub_application_id ?? $id;
                }
                return $bill;

            default:
                return null;
        }
    }

    /**
     * Generate filename for bill download
     */
    private function generateBillFilename($bill, $type)
    {
        $fileno = $bill->primary_fileno ?? $bill->unit_fileno ?? 'Unknown';
        $date = date('Y-m-d');
        $typeLabel = ucfirst($type);
        
        return "{$typeLabel}_Bill_{$fileno}_{$date}.pdf";
    }
 
    public function Payments()
    {
        $PageTitle = 'Payments';
        $PageDescription = '';

        // Get payment data
        $paymentData = $this->getPaymentData();

        return view('programmes.payments', array_merge(
            $paymentData,
            [
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription
            ]
        ));
    }

    /**
     * Filter payment data based on request parameters
     */
    public function filterPayments(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'payment_type' => 'nullable|string|in:all,initial,betterment,final',
            'payment_status' => 'nullable|string'
        ]);

        // Get filtered payment data
        $paymentData = $this->getPaymentData($request);

        return response()->json($paymentData);
    }

    /**
     * Get payment data with optional filters - NEW STRUCTURE
     */
    private function getPaymentData(Request $request = null)
    {
        // Query 1: Primary Application Payments (from mother_applications + billing for betterment charges)
        $primaryQuery = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('billing', 'mother_applications.id', '=', 'billing.application_id');

        // Apply date filters for primary applications
        if ($request && $request->filled('start_date')) {
            $primaryQuery->whereDate('mother_applications.created_at', '>=', $request->start_date);
        }
        if ($request && $request->filled('end_date')) {
            $primaryQuery->whereDate('mother_applications.created_at', '<=', $request->end_date);
        }

        // Apply payment status filter for primary applications
        if ($request && $request->filled('payment_status') && $request->payment_status !== 'all') {
            $primaryQuery->where('mother_applications.Payment_Status', $request->payment_status);
        }

        $primaryPayments = $primaryQuery
            ->select(
                'mother_applications.id',
                'mother_applications.fileno as Sectional_Title_File_No',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.application_fee',
                'mother_applications.processing_fee',
                'mother_applications.site_plan_fee',
                'mother_applications.payment_date',
                'mother_applications.receipt_number',
                'mother_applications.Payment_Status',
                'mother_applications.created_at',
                'billing.Betterment_Charges',
                'billing.Penalty_Fees'
            )
            ->where(function($query) {
                $query->whereNotNull('mother_applications.application_fee')
                      ->orWhereNotNull('mother_applications.processing_fee')
                      ->orWhereNotNull('mother_applications.site_plan_fee')
                      ->orWhereNotNull('billing.Betterment_Charges');
            })
            ->get();

        // Query 2: Unit Application Payments (from subapplications + final_bills for additional fees)
        $unitQuery = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('final_bills', 'subapplications.id', '=', 'final_bills.sub_application_id')
            ->leftJoin('billing', 'subapplications.id', '=', 'billing.sub_application_id'); // <-- add this join

        // Apply date filters for unit applications
        if ($request && $request->filled('start_date')) {
            $unitQuery->whereDate('subapplications.created_at', '>=', $request->start_date);
        }
        if ($request && $request->filled('end_date')) {
            $unitQuery->whereDate('subapplications.created_at', '<=', $request->end_date);
        }

        // Apply payment status filter for unit applications
        if ($request && $request->filled('payment_status') && $request->payment_status !== 'all') {
            $unitQuery->where('subapplications.Payment_Status', $request->payment_status);
        }

        $unitPayments = $unitQuery
            ->select(
                'subapplications.id',
                'subapplications.fileno as Sectional_Title_File_No',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.application_fee',
                'subapplications.processing_fee',
                'subapplications.site_plan_fee',
                'subapplications.payment_date',
                'subapplications.receipt_number',
                'subapplications.Payment_Status',
                'subapplications.created_at',
                'final_bills.recertification_fee',
                'final_bills.assignment_fee',
                'final_bills.dev_charges',
                'final_bills.bill_balance', // <-- add bill_balance
                'billing.Penalty_Fees'      // <-- add Penalty_Fees
            )
            ->where(function($query) {
                $query->whereNotNull('subapplications.application_fee')
                      ->orWhereNotNull('subapplications.processing_fee')
                      ->orWhereNotNull('subapplications.site_plan_fee')
                      ->orWhereNotNull('final_bills.recertification_fee')
                      ->orWhereNotNull('final_bills.assignment_fee')
                      ->orWhereNotNull('final_bills.dev_charges')
                      ->orWhereNotNull('final_bills.bill_balance')
                      ->orWhereNotNull('billing.Penalty_Fees');
            })
            ->get();

        // Format owner names for primary payments
        foreach ($primaryPayments as $payment) {
            $payment->owner_name = !empty($payment->corporate_name)
                ? $payment->corporate_name
                : trim($payment->first_name . ' ' . $payment->surname);
        }

        // Format owner names for unit payments
        foreach ($unitPayments as $payment) {
            $payment->owner_name = !empty($payment->corporate_name)
                ? $payment->corporate_name
                : trim($payment->first_name . ' ' . $payment->surname);
        }

        // Combined payments for the main summary section
        $payments = collect($primaryPayments)->merge($unitPayments);

        // Calculate general payment statistics
        $totalPayments = $payments->count();
        $pendingPayments = $payments->where('Payment_Status', 'Incomplete')->count();
        $paidPayments = $payments->where('Payment_Status', 'Complete')->count();

        // Calculate sums for each payment type with safe numeric conversion using TRY_CAST
        // For primary applications
        $schemeApplicationFeeSum = collect($primaryPayments)
            ->sum(function($payment) { return floatval($payment->application_fee ?? 0); });
        $sitePlanFeeSum = collect($primaryPayments)
            ->sum(function($payment) { return floatval($payment->site_plan_fee ?? 0); }) +
            collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->site_plan_fee ?? 0); });
        $processingFeeSum = collect($primaryPayments)
            ->sum(function($payment) { return floatval($payment->processing_fee ?? 0); }) +
            collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->processing_fee ?? 0); });
        $bettermentChargesSum = collect($primaryPayments)
            ->sum(function($payment) { return floatval($payment->Betterment_Charges ?? 0); });
        $unitApplicationFeesSum = collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->application_fee ?? 0); });
        $recertificationFeeSum = collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->recertification_fee ?? 0); });
        $assignmentFeeSum = collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->assignment_fee ?? 0); });
        $devChargesSum = collect($unitPayments)
            ->sum(function($payment) { return floatval($payment->dev_charges ?? 0); });
        $penaltyFeesSum = collect($primaryPayments)
            ->sum(function($payment) { return floatval($payment->Penalty_Fees ?? 0); });

        // Add total sum of all payment fields
        $totalPaymentSum = $schemeApplicationFeeSum + $sitePlanFeeSum + $processingFeeSum + 
                          $bettermentChargesSum + $unitApplicationFeesSum + $recertificationFeeSum + 
                          $assignmentFeeSum + $devChargesSum + $penaltyFeesSum;

        // Primary vs Unit statistics for charts
        $primaryTotalSum = collect($primaryPayments)->sum(function ($payment) {
            return
                floatval($payment->application_fee ?? 0) +
                floatval($payment->site_plan_fee ?? 0) +
                floatval($payment->processing_fee ?? 0) +
                floatval($payment->Betterment_Charges ?? 0) +
                floatval($payment->Penalty_Fees ?? 0);
        });

        $unitTotalSum = collect($unitPayments)->sum(function ($payment) {
            return
                floatval($payment->application_fee ?? 0) +
                floatval($payment->processing_fee ?? 0) +
                floatval($payment->site_plan_fee ?? 0) +
                floatval($payment->recertification_fee ?? 0) +
                floatval($payment->assignment_fee ?? 0) +
                floatval($payment->dev_charges ?? 0);
        });

        // Payment trends by month (for line chart)
        $paymentsByMonth = $payments->groupBy(function ($payment) {
            if (is_object($payment) && isset($payment->created_at) && $payment->created_at) {
                return \Carbon\Carbon::parse($payment->created_at)->format('Y-m');
            }
            return 'Unknown';
        })->map(function ($group) {
            return $group->count();
        });

        return [
            'payments' => $payments,
            'primaryPayments' => $primaryPayments,
            'unitPayments' => $unitPayments,
            'totalPayments' => $totalPayments,
            'pendingPayments' => $pendingPayments,
            'paidPayments' => $paidPayments,
            'totalPaymentSum' => $totalPaymentSum,
            'schemeApplicationFeeSum' => $schemeApplicationFeeSum,
            'sitePlanFeeSum' => $sitePlanFeeSum,
            'processingFeeSum' => $processingFeeSum,
            'bettermentChargesSum' => $bettermentChargesSum,
            'unitApplicationFeesSum' => $unitApplicationFeesSum,
            'recertificationFeeSum' => $recertificationFeeSum,
            'assignmentFeeSum' => $assignmentFeeSum,
            'devChargesSum' => $devChargesSum,
            'penaltyFeesSum' => $penaltyFeesSum,
            'primaryTotalSum' => $primaryTotalSum,
            'unitTotalSum' => $unitTotalSum,
            'paymentsByMonth' => $paymentsByMonth
        ];
    }

    // ... rest of the methods remain the same ...
    public function Others()
    {
        $PageTitle = 'OTHER DEPARTMENT APPROVALS';
        $PageDescription = '';

        // Fetch surveys with mother application owner information
        $surveys = DB::connection('sqlsrv')->table('surveyCadastralRecord')
            ->join('mother_applications', 'surveyCadastralRecord.application_id', '=', 'mother_applications.id')
            ->whereNull('surveyCadastralRecord.sub_application_id')
            ->select(
                'surveyCadastralRecord.fileno',
                'surveyCadastralRecord.application_id',
                'surveyCadastralRecord.survey_by',
                'surveyCadastralRecord.survey_by_date',
                'surveyCadastralRecord.drawn_by',
                'surveyCadastralRecord.drawn_by_date',
                'surveyCadastralRecord.checked_by',
                'surveyCadastralRecord.checked_by_date',
                'surveyCadastralRecord.approved_by',
                'surveyCadastralRecord.approved_by_date',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.multiple_owners_names'
            )->get();

        // Fetch unit surveys with subapplication owner information
        $Unitsurveys = DB::connection('sqlsrv')->table('surveyCadastralRecord')
            ->join('subapplications', 'surveyCadastralRecord.sub_application_id', '=', 'subapplications.id')
            ->whereNotNull('surveyCadastralRecord.sub_application_id')
            ->select(
                'surveyCadastralRecord.fileno',
                'surveyCadastralRecord.application_id',
                'surveyCadastralRecord.sub_application_id',
                'surveyCadastralRecord.survey_by',
                'surveyCadastralRecord.survey_by_date',
                'surveyCadastralRecord.drawn_by',
                'surveyCadastralRecord.drawn_by_date',
                'surveyCadastralRecord.checked_by',
                'surveyCadastralRecord.checked_by_date',
                'surveyCadastralRecord.approved_by',
                'surveyCadastralRecord.approved_by_date',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names'
            )->get();

        // Process owner names for main applications
        foreach ($surveys as $survey) {
            if (!empty($survey->multiple_owners_names)) {
                $ownerArray = json_decode($survey->multiple_owners_names, true);
                $survey->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($survey->corporate_name)) {
                $survey->owner_name = $survey->corporate_name;
            } else {
                $survey->owner_name = trim($survey->applicant_title . ' ' . $survey->first_name . ' ' . $survey->surname);
            }
        }

        // Process owner names for unit applications
        foreach ($Unitsurveys as $survey) {
            if (!empty($survey->multiple_owners_names)) {
                $ownerArray = json_decode($survey->multiple_owners_names, true);
                $survey->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($survey->corporate_name)) {
                $survey->owner_name = $survey->corporate_name;
            } else {
                $survey->owner_name = trim($survey->applicant_title . ' ' . $survey->first_name . ' ' . $survey->surname);
            }
        }

        return view('programmes.approvals.other_departments', compact('surveys', 'Unitsurveys', 'PageTitle', 'PageDescription'));
    }

    public function Deeds()
    {
        $PageTitle = 'DEEDS';
        $PageDescription = '';
        $deeds = DB::connection('sqlsrv')->table('landAdministration')
            ->join('mother_applications', 'landAdministration.application_id', '=', 'mother_applications.id')
            ->select(
                'landAdministration.*',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.multiple_owners_names'
            )
            ->get();

        // Process owner names for deeds
        foreach ($deeds as $deed) {
            if (!empty($deed->multiple_owners_names)) {
                $ownerArray = json_decode($deed->multiple_owners_names, true);
                $deed->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($deed->corporate_name)) {
                $deed->owner_name = $deed->corporate_name;
            } else {
                $deed->owner_name = trim($deed->applicant_title . ' ' . $deed->first_name . ' ' . $deed->surname);
            }
        }

        // Fetch deeds with subapplication owner information
        $unitDeeds = DB::connection('sqlsrv')->table('landAdministration')
            ->join('subapplications', 'landAdministration.sub_application_id', '=', 'subapplications.id')
            ->select(
                'landAdministration.*',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names'
            )
            ->get();
        foreach ($unitDeeds as $deed) {
            if (!empty($deed->multiple_owners_names)) {
                $ownerArray = json_decode($deed->multiple_owners_names, true);
                $deed->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($deed->corporate_name)) {
                $deed->owner_name = $deed->corporate_name;
            } else {
                $deed->owner_name = trim($deed->applicant_title . ' ' . $deed->first_name . ' ' . $deed->surname);
            }
        }

        return view('programmes.approvals.deeds', compact('deeds', 'unitDeeds', 'PageTitle', 'PageDescription'));
    }

    public function Lands()
    {
        $PageTitle = 'LANDS';
        $PageDescription = '';

        // Fetch lands data from the database
        $lands = DB::connection('sqlsrv')->table('eRegistry')
            ->select([
                'MLSFileNo',
                'KANGISFileNo',
                'NEWKangisFileNo',
                'Sectional_Title_File_No',
                'Commissioning_Date',
                'Decommissioning_Date',
                'Site_Plan_Approval',
                'Survey_Plan_Approval',
                'Expected_Return_Date',
                'Current_Office',
                'created_at'
            ])
            ->get();

        return view('programmes.approvals.lands', compact('lands', 'PageTitle', 'PageDescription'));
    }

    public function eRegistry()
    {
        $PageTitle = 'eRegistry';
        $PageDescription = 'Manages the workflow and status of sectional title applications';

        // Fetch lands data from the database
        $eRegistry = DB::connection('sqlsrv')->table('eRegistry')
            ->select([
                'MLSFileNo',
                'KANGISFileNo',
                'NEWKangisFileNo',
                'Sectional_Title_File_No',
                'Commissioning_Date',
                'Decommissioning_Date',
                'Site_Plan_Approval',
                'Survey_Plan_Approval',
                'Expected_Return_Date',
                'Current_Office',
                'created_at'
            ])
            ->get();

        return view('programmes.eRegistry', compact('eRegistry', 'PageTitle', 'PageDescription'));
    }

    public function PlanningRecomm()
    {
        $PageTitle = 'Planning Recommendation';
        $PageDescription = '';

        // Get mother applications
        $applications = DB::connection('sqlsrv')->table('mother_applications')->get();

        // Process owner names for primary applications
        foreach ($applications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Get unit applications
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')->get();

        // Process owner names for unit applications
        foreach ($unitApplications as $unitApplication) {
            if (!empty($unitApplication->multiple_owners_names)) {
                $ownerArray = json_decode($unitApplication->multiple_owners_names, true);
                $unitApplication->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($unitApplication->corporate_name)) {
                $unitApplication->owner_name = $unitApplication->corporate_name;
            } else {
                $unitApplication->owner_name = trim($unitApplication->applicant_title . ' ' . $unitApplication->first_name . ' ' . $unitApplication->surname);
            }
        }

        // Calculate statistics for primary applications
        $totalPrimaryApplications = count($applications);
        $approvedPrimaryApplications = collect($applications)->where('planning_recommendation_status', 'Approved')->count();
        $rejectedPrimaryApplications = collect($applications)->where('planning_recommendation_status', 'Declined')->count();
        $pendingPrimaryApplications = $totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications;

        // Calculate statistics for unit applications
        $totalUnitApplications = count($unitApplications);
        $approvedUnitApplications = collect($unitApplications)->where('planning_recommendation_status', 'Approved')->count();
        $rejectedUnitApplications = collect($unitApplications)->where('planning_recommendation_status', 'Declined')->count();
        $pendingUnitApplications = $totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications;

        return view('programmes.approvals.planning_recomm', compact(
            'applications',
            'unitApplications',
            'PageTitle',
            'PageDescription',
            'totalPrimaryApplications',
            'approvedPrimaryApplications',
            'rejectedPrimaryApplications',
            'pendingPrimaryApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'rejectedUnitApplications',
            'pendingUnitApplications'
        ));
    }

    public function Director_approval()
    {
        $PageTitle = 'Director\'s Approval';
        $PageDescription = '';

        // Get mother applications
        $applications = DB::connection('sqlsrv')->table('mother_applications')->get();

        // Process owner names for primary applications
        foreach ($applications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Get unit applications
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')->get();

        // Process owner names for unit applications
        foreach ($unitApplications as $unitApplication) {
            if (!empty($unitApplication->multiple_owners_names)) {
                $ownerArray = json_decode($unitApplication->multiple_owners_names, true);
                $unitApplication->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($unitApplication->corporate_name)) {
                $unitApplication->owner_name = $unitApplication->corporate_name;
            } else {
                $unitApplication->owner_name = trim($unitApplication->applicant_title . ' ' . $unitApplication->first_name . ' ' . $unitApplication->surname);
            }
        }

        // Calculate statistics for primary applications
        $totalPrimaryApplications = count($applications);
        $approvedPrimaryApplications = collect($applications)->where('application_status', 'Approved')->count();
        $rejectedPrimaryApplications = collect($applications)->where('application_status', 'Declined')->count();
        $pendingPrimaryApplications = $totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications;

        // Calculate statistics for unit applications
        $totalUnitApplications = count($unitApplications);
        $approvedUnitApplications = collect($unitApplications)->where('application_status', 'Approved')->count();
        $rejectedUnitApplications = collect($unitApplications)->where('application_status', 'Declined')->count();
        $pendingUnitApplications = $totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications;

        return view('programmes.approvals.director', compact(
            'applications',
            'unitApplications',
            'PageTitle',
            'PageDescription',
            'totalPrimaryApplications',
            'approvedPrimaryApplications',
            'rejectedPrimaryApplications',
            'pendingPrimaryApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'rejectedUnitApplications',
            'pendingUnitApplications'
        ));
    }

    public function ST_Report()
    {
        $PageTitle = 'Sectional Titling Report';
        $PageDescription = '';

        // Fetch primary applications with related billing data
        $primaryApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('billing', function ($join) {
                $join->on('mother_applications.id', '=', 'billing.application_id')
                    ->whereNull('billing.sub_application_id');
            })
            ->select(
                'mother_applications.id',
                'mother_applications.fileno',
                'mother_applications.applicant_type',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.rc_number',
                'mother_applications.multiple_owners_names',
                'mother_applications.property_lga',
                'mother_applications.NoOfUnits',
                'mother_applications.NoOfSections',
                'mother_applications.NoOfBlocks',
                'mother_applications.land_use',
                'mother_applications.residential_type',
                'mother_applications.commercial_type',
                'mother_applications.industrial_type',
                'mother_applications.mixed_type',
                'mother_applications.ownership_type',
                'mother_applications.application_status',
                'mother_applications.approval_date',
                'mother_applications.planning_recommendation_status',
                'mother_applications.planning_approval_date',
                'mother_applications.created_at',
                'mother_applications.updated_at',
                'billing.Payment_Status'
            )
            ->get();

        // Process owner names
        foreach ($primaryApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Calculate statistics
        $totalApplications = count($primaryApplications);
        $approvedApplications = collect($primaryApplications)->where('application_status', 'Approved')->count();
        $pendingApplications = collect($primaryApplications)->where('application_status', '!=', 'Approved')
            ->where('application_status', '!=', 'Declined')
            ->count();
        $rejectedApplications = collect($primaryApplications)->where('application_status', 'Declined')->count();

        // Planning recommendation stats
        $approvedPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', 'Approved')->count();
        $pendingPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', '!=', 'Approved')
            ->where('planning_recommendation_status', '!=', 'Declined')
            ->count();
        $rejectedPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', 'Declined')->count();

        // Group applications by LGA for geo chart
        $applicationsByLGA = collect($primaryApplications)
            ->groupBy('property_lga')
            ->map(function ($group) {
                return $group->count();
            });

        // Get monthly application trend data (last 12 months)
        $monthlyTrend = collect($primaryApplications)
            ->filter(function ($app) {
                return !empty($app->created_at);
            })
            ->groupBy(function ($app) {
                return \Carbon\Carbon::parse($app->created_at)->format('Y-m');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->sortKeys();

        // Prepare month labels for chart
        $monthLabels = [];
        $applicationCountByMonth = [];
        foreach ($monthlyTrend as $month => $count) {
            $monthLabels[] = $month;
            $applicationCountByMonth[] = $count;
        }

        // Fetch unit applications with related billing data and mother application context
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->leftJoin('billing', function ($join) {
                $join->on('subapplications.id', '=', 'billing.sub_application_id');
            })
            ->select(
                'subapplications.id',
                'subapplications.main_application_id',
                'subapplications.fileno',
                'subapplications.applicant_type',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.rc_number',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'subapplications.property_location',
                'subapplications.ownership',

                'subapplications.plot_size',
                'subapplications.commercial_type',
                'subapplications.industrial_type',
                'subapplications.ownership_type',
                'subapplications.residence_type',
                'subapplications.application_status',
                'subapplications.approval_date',
                'subapplications.planning_recommendation_status',
                'subapplications.planning_approval_date',
                'subapplications.created_at',
                'subapplications.updated_at',
                'mother_applications.land_use',
                'mother_applications.property_lga',
                'billing.Payment_Status'
            )
            ->get();

        // Process owner names for unit applications
        foreach ($unitApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Calculate unit application statistics
        $totalUnitApplications = count($unitApplications);
        $approvedUnitApplications = collect($unitApplications)->where('application_status', 'Approved')->count();
        $pendingUnitApplications = collect($unitApplications)->where('application_status', '!=', 'Approved')
            ->where('application_status', '!=', 'Declined')
            ->count();
        $rejectedUnitApplications = collect($unitApplications)->where('application_status', 'Declined')->count();

        // Planning recommendation stats for unit applications
        $approvedUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', 'Approved')->count();
        $pendingUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', '!=', 'Approved')
            ->where('planning_recommendation_status', '!=', 'Declined')
            ->count();
        $rejectedUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', 'Declined')->count();

        // Group unit applications by LGA for geo chart
        $unitApplicationsByLGA = collect($unitApplications)
            ->groupBy('property_lga')
            ->map(function ($group) {
                return $group->count();
            });

        // Get monthly unit application trend data
        $unitMonthlyTrend = collect($unitApplications)
            ->filter(function ($app) {
                return !empty($app->created_at);
            })
            ->groupBy(function ($app) {
                return \Carbon\Carbon::parse($app->created_at)->format('Y-m');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->sortKeys();

        // Prepare month labels for unit application chart
        $unitMonthLabels = [];
        $unitApplicationCountByMonth = [];
        foreach ($unitMonthlyTrend as $month => $count) {
            $unitMonthLabels[] = $month;
            $unitApplicationCountByMonth[] = $count;
        }

        return view('programmes.report', compact(
            'PageTitle',
            'PageDescription',
            'primaryApplications',
            'totalApplications',
            'approvedApplications',
            'pendingApplications',
            'rejectedApplications',
            'approvedPlanningRecommendations',
            'pendingPlanningRecommendations',
            'rejectedPlanningRecommendations',
            'applicationsByLGA',
            'monthLabels',
            'applicationCountByMonth',
            // Unit application data
            'unitApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'pendingUnitApplications',
            'rejectedUnitApplications',
            'approvedUnitPlanningRecommendations',
            'pendingUnitPlanningRecommendations',
            'rejectedUnitPlanningRecommendations',
            'unitApplicationsByLGA',
            'unitMonthLabels',
            'unitApplicationCountByMonth'
        ));
    }

    public function Entity($applicationId = null)
    {
        $PageTitle = 'Entities';
        $PageDescription = 'Application Entities by Type';

        // Fetch primary applications grouped by applicant type
        $individualApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'individual')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        $corporateApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'corporate')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        $multipleApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'multiple')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        // Fetch unit applications grouped by applicant type
        $unitIndividualApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'individual')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        $unitCorporateApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'corporate')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        $unitMultipleApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'multiple')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        // Process owner names for primary applications
        foreach ($individualApplications as $app) {
            $app->owner_name = trim($app->first_name . ' ' . $app->surname);
        }

        foreach ($corporateApplications as $app) {
            $app->owner_name = $app->corporate_name;
        }

        foreach ($multipleApplications as $app) {
            if (!empty($app->multiple_owners_names)) {
                $ownerArray = json_decode($app->multiple_owners_names, true);
                $app->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } else {
                $app->owner_name = 'Multiple Owners';
            }
        }

        // Process owner names for unit applications
        foreach ($unitIndividualApplications as $app) {
            $app->owner_name = trim($app->first_name . ' ' . $app->surname);
        }

        foreach ($unitCorporateApplications as $app) {
            $app->owner_name = $app->corporate_name;
        }

        foreach ($unitMultipleApplications as $app) {
            if (!empty($app->multiple_owners_names)) {
                $ownerArray = json_decode($app->multiple_owners_names, true);
                $app->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } else {
                $app->owner_name = 'Multiple Owners';
            }
        }

        // Prepare statistics
        $primaryStats = [
            'individual' => count($individualApplications),
            'corporate' => count($corporateApplications),
            'multiple' => count($multipleApplications),
            'total' => count($individualApplications) + count($corporateApplications) + count($multipleApplications)
        ];

        $unitStats = [
            'individual' => count($unitIndividualApplications),
            'corporate' => count($unitCorporateApplications),
            'multiple' => count($unitMultipleApplications),
            'total' => count($unitIndividualApplications) + count($unitCorporateApplications) + count($unitMultipleApplications)
        ];

        return view('programmes.entity.index', compact(
            'PageTitle',
            'PageDescription',
            'individualApplications',
            'corporateApplications',
            'multipleApplications',
            'unitIndividualApplications',
            'unitCorporateApplications',
            'unitMultipleApplications',
            'primaryStats',
            'unitStats'
        ));
    }

    public function getApplicationDetails($fileId, $fileType)
    {
        try {
            $application = null;
            $bettermentBill = null;
            
            if ($fileType === 'primary') {
                // Get primary application details
                $application = DB::connection('sqlsrv')->table('mother_applications')
                    ->select('id', 'plot_size', 'NoOfUnits', 'property_value')
                    ->where('id', $fileId)
                    ->first();
                    
                // Get existing betterment bill if any
                $bettermentBill = DB::connection('sqlsrv')->table('billing')
                    ->where('application_id', $fileId)
                    ->whereNotNull('Betterment_Charges')
                    ->first();
            } else {
                // Get unit application details
                $application = DB::connection('sqlsrv')->table('subapplications')
                    ->select('id', 'plot_size', 'NoOfUnits', 'property_value')
                    ->where('id', $fileId)
                    ->first();
                    
                // Get existing betterment bill if any
                $bettermentBill = DB::connection('sqlsrv')->table('billing')
                    ->where('sub_application_id', $fileId)
                    ->whereNotNull('Betterment_Charges')
                    ->first();
            }
            
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'application' => $application,
                'betterment_bill' => $bettermentBill
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching application details: ' . $e->getMessage()
            ], 500);
        }
    }
}
