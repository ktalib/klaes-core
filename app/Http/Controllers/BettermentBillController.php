<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use NumberFormatter;

class BettermentBillController extends Controller
{
    /**
     * Calculate betterment charges without saving
     */
    public function calculate(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'property_value' => 'required|numeric',
            'betterment_rate' => 'required|numeric',
            'land_size' => 'nullable|numeric',
            'land_size_factor' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Parse values and calculate betterment charges
            $propertyValue = $request->input('property_value');
            $bettermentRate = $request->input('betterment_rate') / 100; // Convert percentage to decimal
            $landSizeFactor = $request->input('land_size_factor', 1.0); // Default to 1.0 if not provided

            // Calculate betterment charges using the new formula
            $bettermentCharges = $propertyValue * $bettermentRate * $landSizeFactor;

            return response()->json([
                'success' => true,
                'betterment_charges' => number_format($bettermentCharges, 2),
                'betterment_charges_raw' => $bettermentCharges,
                'land_size_factor' => $landSizeFactor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating betterment charges: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'application_id' => 'nullable',
            'property_value' => 'nullable|numeric',
            'betterment_rate' => 'nullable|numeric',
            'betterment_charges' => 'nullable|numeric',
            'ref_id' => 'nullable',
            'Sectional_Title_File_No' => 'nullable',
            'sub_application_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Log incoming request data for debugging
            \Log::info('Betterment bill store request:', $request->all());

            // Parse values and calculate betterment charges
            $propertyValue = floatval($request->input('property_value', 0));
            $bettermentRate = floatval($request->input('betterment_rate', 0));
            $landSize = floatval($request->input('land_size', 1200)); // Default land size

            // Calculate land size factor (always calculate for logging and potential use)
            $landSizeFactor = $this->calculateLandSizeFactor($landSize);

            // Check if charges are pre-calculated
            if ($request->has('betterment_charges')) {
                $bettermentCharges = floatval($request->input('betterment_charges'));
            } else {
                // Calculate betterment charges with the old formula
                $bettermentCharges = $propertyValue * ($bettermentRate / 100) * $landSizeFactor;
            }

            \Log::info('Calculated values:', [
                'property_value' => $propertyValue,
                'betterment_rate' => $bettermentRate,
                'land_size' => $landSize,
                'land_size_factor' => $landSizeFactor,
                'betterment_charges' => $bettermentCharges
            ]);

            // Check if a betterment bill already exists for this application
            $applicationId = $request->input('application_id');
            $subApplicationId = $request->input('sub_application_id');

            \Log::info('Looking for existing bill with:', [
                'application_id' => $applicationId,
                'sub_application_id' => $subApplicationId
            ]);

            // Remove filtering on Betterment_Charges so we always find the bill for update
            $existingBill = DB::connection('sqlsrv')
                ->table('billing')
                ->where(function ($query) use ($applicationId, $subApplicationId) {
                    if ($applicationId) {
                        $query->where('application_id', $applicationId);
                    }
                    if ($subApplicationId) {
                        $query->orWhere('sub_application_id', $subApplicationId);
                    }
                })
                ->first();

            \Log::info('Existing bill found: ' . ($existingBill ? 'Yes (ID: ' . $existingBill->ID . ')' : 'No'));

            // Generate reference ID if not provided
            $refId = $request->input('ref_id') ?: 'BB-' . ($applicationId ?: $subApplicationId) . '-' . date('Ymd');
            $fileNo = $request->input('Sectional_Title_File_No') ?: 'ST-' . ($applicationId ?: $subApplicationId);

            if ($existingBill) {
                // Update existing bill
                $updateData = [
                    'property_value' => (string) $propertyValue,
                    'betterment_rate' => (string) $bettermentRate,
                    'Betterment_Charges' => (string) $bettermentCharges,
                    'ref_id' => $refId,
                    'Sectional_Title_File_No' => $fileNo,
                    'updated_at' => now()
                ];

                \Log::info('Updating existing bill with data:', $updateData);

                $result = DB::connection('sqlsrv')
                    ->table('billing')
                    ->where('ID', $existingBill->ID)
                    ->update($updateData);

                \Log::info('Update result:', ['success' => $result]);

                $message = 'Betterment bill updated successfully';
            } else {
                // Insert new bill
                $insertData = [
                    'application_id' => $applicationId,
                    'sub_application_id' => $subApplicationId,
                    'property_value' => (string) $propertyValue,
                    'betterment_rate' => (string) $bettermentRate,
                    'Betterment_Charges' => (string) $bettermentCharges,
                    'ref_id' => $refId,
                    'Sectional_Title_File_No' => $fileNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'Payment_Status' => 'Pending'
                ];

                \Log::info('Inserting new betterment bill:', $insertData);

                $result = DB::connection('sqlsrv')
                    ->table('billing')
                    ->insert($insertData);

                \Log::info('Insert result:', ['success' => $result]);

                $message = 'Betterment bill created successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'betterment_charges' => number_format($bettermentCharges, 2),
                'betterment_charges_raw' => $bettermentCharges,
                'ref_id' => $refId,
                'data' => [
                    'property_value' => $propertyValue,
                    'betterment_rate' => $bettermentRate,
                    'betterment_charges' => $bettermentCharges,
                    'ref_id' => $refId
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error processing betterment bill: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error processing betterment bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate land size factor based on the land size
     */
    private function calculateLandSizeFactor($landSize)
    {
        // This should match the JavaScript calculation for consistency
        $size = floatval($landSize);

        if ($size <= 500)
            return 0.8;       // Small land plots
        else if ($size <= 1000)
            return 1.0; // Medium land plots
        else if ($size <= 2000)
            return 1.2; // Large land plots
        else
            return 1.5;                    // Very large land plots
    }

    /**
     * Calculate betterment rate based on land use type
     */
    private function calculateBettermentRate($landUse, $propertySize)
    {
        $landUse = strtolower($landUse);

        // Rates for Primary Applications Only
        if (in_array($landUse, ['commercial', 'industrial'])) {
            return 300; // N300 per sqm
        } else {
            // Residential and others
            return 150; // N150 per sqm
        }
    }

    public function show($id)
    {
        try {
            \Log::info('Show betterment bill for ID: ' . $id);
            $idStr = (string) $id;

            // Get the betterment bill - order by ID desc to get the latest one
            $bettermentBill = DB::connection('sqlsrv')
                ->table('billing')
                ->where(function ($query) use ($idStr) {
                    $query->where('application_id', $idStr)
                        ->orWhere('sub_application_id', $idStr);
                })
                ->whereNotNull('Betterment_Charges')
                ->orderBy('ID', 'desc')
                ->first();

            \Log::info('Bill query result: ' . ($bettermentBill ? 'Found (ID: ' . $bettermentBill->ID . ')' : 'Not found'));

            // Get application details with plot_size and NoOfUnits (property_value is in billing table)
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->select(
                    'id',
                    'fileno',
                    'first_name',
                    'surname',
                    'corporate_name',
                    'property_street_name',
                    'property_lga',
                    'plot_size',
                    'NoOfUnits'
                )
                ->where('id', $id)
                ->first();

            // If not found in mother_applications, check in subapplications
            if (!$application) {
                $application = DB::connection('sqlsrv')
                    ->table('subapplications as s')
                    ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                    ->select(
                        's.id',
                        's.fileno',
                        's.first_name',
                        's.surname',
                        's.corporate_name',
                        's.plot_size',
                        'm.property_street_name',
                        'm.property_lga',
                        'm.plot_size as main_plot_size',
                        'm.NoOfUnits as main_NoOfUnits'
                    )
                    ->where('s.id', $id)
                    ->first();

                // Use main application data if available
                if ($application) {
                    $application->plot_size = $application->main_plot_size ?? $application->plot_size;
                    $application->NoOfUnits = $application->main_NoOfUnits ?? 1;
                }
            }

            \Log::info('Application found: ' . ($application ? 'Yes (File: ' . $application->fileno . ')' : 'No'));

            if ($application) {
                \Log::info('Application details: ', [
                    'fileno' => $application->fileno,
                    'plot_size' => $application->plot_size ?? 'N/A',
                    'NoOfUnits' => $application->NoOfUnits ?? 'N/A'
                ]);
            }

            if ($bettermentBill) {
                \Log::info('Bill details: ', [
                    'ID' => $bettermentBill->ID,
                    'application_id' => $bettermentBill->application_id,
                    'sub_application_id' => $bettermentBill->sub_application_id,
                    'Betterment_Charges' => $bettermentBill->Betterment_Charges,
                    'property_value' => $bettermentBill->property_value,
                    'betterment_rate' => $bettermentBill->betterment_rate,
                    'ref_id' => $bettermentBill->ref_id
                ]);
            }

            if (!$bettermentBill) {
                // If no bill is found, create a placeholder to avoid breaking the frontend
                $bettermentBill = (object) [
                    'ID' => null,
                    'application_id' => $idStr,
                    'sub_application_id' => null,
                    'Betterment_Charges' => null,
                    'property_value' => null,
                    'betterment_rate' => null,
                    'ref_id' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'bill' => $bettermentBill,
                'application' => $application
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in show method: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving betterment bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a printable receipt for the betterment bill
     */
    public function printReceipt($id)
    {
        try {
            \Log::info('Print receipt requested for ID: ' . $id);

            // Get the application
            // Check in mother_applications first
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $id)
                ->first();

            \Log::info('Mother application found: ' . ($application ? 'Yes' : 'No'));

            // If not found in mother_applications, check in subapplications
            if (!$application) {
                $application = DB::connection('sqlsrv')
                    ->table('subapplications as s')
                    ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                    ->where('s.id', $id)
                    ->select(
                        's.*',
                        's.id as sub_id',
                        'm.property_house_no',
                        'm.property_plot_no',
                        'm.property_street_name',
                        'm.property_district',
                        'm.property_lga',
                        'm.property_state',
                        'm.fileno as main_fileno'
                    )
                    ->first();

                \Log::info('Sub application found: ' . ($application ? 'Yes' : 'No'));
            }

            if (!$application) {
                \Log::error('Application not found for ID: ' . $id);
                return redirect()->back()->with('error', 'Application not found');
            }

            // Get the betterment bill - convert ID to string for comparison
            $idStr = (string) $id;
            // Get the latest betterment bill with charges
            $bill = DB::connection('sqlsrv')
                ->table('billing')
                ->where(function ($query) use ($idStr) {
                    $query->where('application_id', $idStr)
                        ->orWhere('sub_application_id', $idStr);
                })
                ->whereNotNull('Betterment_Charges')
                ->orderBy('ID', 'desc')
                ->first();

            \Log::info('Bill found: ' . ($bill ? 'Yes' : 'No'));

            if ($bill) {
                \Log::info('Bill data: ', [
                    'id' => $bill->id ?? 'N/A',
                    'ref_id' => $bill->ref_id ?? 'N/A',
                    'property_value' => $bill->property_value ?? 'N/A',
                    'betterment_charges' => $bill->Betterment_Charges ?? 'N/A',
                    'betterment_rate' => $bill->betterment_rate ?? 'N/A'
                ]);
            }

            if (!$bill) {
                \Log::error('Betterment bill not found for ID: ' . $id);
                return redirect()->back()->with('error', 'Betterment bill not found. Please generate a bill first.');
            }

            // Ensure bill has required data
            if (!$bill->Betterment_Charges || $bill->Betterment_Charges <= 0) {
                \Log::warning('Bill found but charges are zero or null');
            }

            // Return the print view
            return view('components.print_betterment', compact('application', 'bill'));
        } catch (\Exception $e) {
            \Log::error('Error printing betterment bill: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error generating print view: ' . $e->getMessage());
        }
    }
}