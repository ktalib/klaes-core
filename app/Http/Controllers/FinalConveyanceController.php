<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FinalConveyanceController extends Controller
{
    /**
     * Display applicant information modal
     */
    public function show($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Check if final conveyance is already generated
            if ($application->final_conveyance_generated == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Final Conveyance has already been generated for this application'
                ], 400);
            }

            // Get buyer titles from titles table
            $titles = DB::connection('sqlsrv')
                ->table('titles')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->select('id', 'title', 'display_name')
                ->get();

            // Get applicant information
            $applicantInfo = [
                'id' => $application->id,
                'file_no' => $application->fileno,
                'np_file_no' => $application->np_fileno,
                'applicant_name' => $this->getApplicantName($application),
                'property_location' => $this->getPropertyLocation($application),
                'land_use' => $application->land_use,
                'units_count' => $this->getUnitsCount($application->id),
                'application_date' => Carbon::parse($application->created_at)->format('Y-m-d'),
                'titles' => $titles
            ];

            return response()->json([
                'success' => true,
                'data' => $applicantInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error in FinalConveyanceController show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving application information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate final conveyance
     */
    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer'
            ]);

            $applicationId = $validated['application_id'];

            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Check if final conveyance is already generated
            if ($application->final_conveyance_generated == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Final Conveyance has already been generated for this application'
                ], 400);
            }

            // Check if application is approved
            $isApproved = $application->application_status === 'Approved' && 
                         $application->planning_recommendation_status === 'Approved';

            if (!$isApproved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Both Application Status and Planning Recommendation must be approved before generating Final Conveyance'
                ], 400);
            }

            // Get buyers for the application
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('application_id', $applicationId)
                ->get();

            if ($buyers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No buyers found. Please add buyers first.'
                ], 400);
            }

            // Generate agreement content
            $agreementContent = $this->generateAgreementContent($application, $buyers);

            // Insert into final_conveyance table
            $finalConveyanceId = DB::connection('sqlsrv')->table('final_conveyance')->insertGetId([
                'application_id' => $applicationId,
                'fileno' => $application->fileno,
                'agreement_content' => $agreementContent,
                'generated_date' => now(),
                'status' => 'generated',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update mother_applications table
            DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $applicationId)
                ->update([
                    'final_conveyance_generated' => 1,
                    'final_conveyance_generated_at' => now(),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Final Conveyance generated successfully',
                'data' => [
                    'final_conveyance_id' => $finalConveyanceId,
                    'generated_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating final conveyance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating final conveyance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get applicant name
     */
    private function getApplicantName($application)
    {
        if ($application->corporate_name) {
            return $application->corporate_name;
        } elseif ($application->multiple_owners_names) {
            $ownerNames = json_decode($application->multiple_owners_names, true);
            if (is_array($ownerNames) && !empty($ownerNames)) {
                return $ownerNames[0] . (count($ownerNames) > 1 ? ' & Others' : '');
            }
            return $application->multiple_owners_names;
        } else {
            return trim($application->first_name . ' ' . $application->middle_name . ' ' . $application->surname);
        }
    }

    /**
     * Get property location
     */
    private function getPropertyLocation($application)
    {
        $location = trim($application->property_plot_no . ' ' . $application->property_street_name);
        if ($application->property_lga) {
            $location .= ', ' . $application->property_lga;
        }
        if ($application->property_district) {
            $location .= ', ' . $application->property_district;
        }
        return $location ?: 'N/A';
    }

    /**
     * Get units count
     */
    private function getUnitsCount($applicationId)
    {
        $totalUnits = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->value('NoOfUnits') ?? 0;

        return $totalUnits . ' Units';
    }

    /**
     * Get buyers list for an application
     */
    public function getBuyers($applicationId)
    {
        try {
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', 'bl.unit_measurement_id', '=', 'sum.id')
                ->select(
                    'bl.id',
                    'bl.application_id',
                    'bl.unit_measurement_id',
                    'bl.buyer_title',
                    'bl.buyer_name',
                    'bl.unit_no',
                    'bl.created_at',
                    'bl.updated_at',
                    'bl.final_conveyance_generated',
                    'bl.final_conveyance_generated_at',
                    'bl.land_use',
                    'sum.measurement',
                    'sum.dimension'
                )
                ->where('bl.application_id', $applicationId)
                ->orderBy('bl.unit_no')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $buyers
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching buyers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching buyers: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Update a buyer
     */
    public function updateBuyer(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'buyer_title' => 'nullable|string|max:10',
                'buyer_name' => 'required|string|max:255',
                'unit_no' => 'required|string|max:50',
                'measurement' => 'nullable|numeric',
                'dimension' => 'nullable|string|max:100'
            ]);

            DB::connection('sqlsrv')->beginTransaction();

            $buyer = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $id)
                ->first();

            if (!$buyer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found'
                ], 404);
            }

            // Check if unit number already exists for this application (excluding current buyer)
            $existingBuyer = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('application_id', $buyer->application_id)
                ->where('unit_no', $validated['unit_no'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingBuyer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit number already exists for this application'
                ], 400);
            }

            // Update buyer
            DB::connection('sqlsrv')->table('buyer_list')
                ->where('id', $id)
                ->update([
                    'buyer_title' => $validated['buyer_title'],
                    'buyer_name' => $validated['buyer_name'],
                    'unit_no' => $validated['unit_no'],
                    'updated_at' => now()
                ]);

            // Handle unit measurements
            if ($buyer->unit_measurement_id) {
                // Update existing measurement
                DB::connection('sqlsrv')->table('st_unit_measurements')
                    ->where('id', $buyer->unit_measurement_id)
                    ->update([
                        'unit_no' => $validated['unit_no'],
                        'measurement' => $validated['measurement'],
                        'dimension' => $validated['dimension'],
                        'updated_at' => now()
                    ]);
            } elseif (!empty($validated['measurement']) || !empty($validated['dimension'])) {
                // Create new measurement
                $unitMeasurementId = DB::connection('sqlsrv')->table('st_unit_measurements')->insertGetId([
                    'application_id' => $buyer->application_id,
                    'buyer_id' => $id,
                    'unit_no' => $validated['unit_no'],
                    'measurement' => $validated['measurement'],
                    'dimension' => $validated['dimension'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Update buyer with unit_measurement_id
                DB::connection('sqlsrv')->table('buyer_list')
                    ->where('id', $id)
                    ->update(['unit_measurement_id' => $unitMeasurementId]);
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Buyer updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('Error updating buyer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating buyer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a buyer
     */
    public function deleteBuyer($id)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $buyer = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $id)
                ->first();

            if (!$buyer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found'
                ], 404);
            }

            // Delete unit measurement if exists
            if ($buyer->unit_measurement_id) {
                DB::connection('sqlsrv')
                    ->table('st_unit_measurements')
                    ->where('id', $buyer->unit_measurement_id)
                    ->delete();
            }

            // Delete buyer
            DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $id)
                ->delete();

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Buyer deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('Error deleting buyer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting buyer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate agreement content
     */
    private function generateAgreementContent($application, $buyers)
    {
        $content = "<h1>FINAL CONVEYANCE AGREEMENT</h1>";
        $content .= "<p>(For Sectional Titling and Decommissioning of Original Certificate of Occupancy)</p>";
        $content .= "<p>This Final Conveyance Agreement is made this " . date('jS \d\a\y \of F, Y') . ", between:</p>";
        
        // Original Owner
        $ownerName = $this->getApplicantName($application);
        
        $content .= "<ul>";
        $content .= "<li>- The Original Owner: " . $ownerName . "</li>";
        $content .= "<li>- Property Location: " . $this->getPropertyLocation($application) . "</li>";
        $content .= "<li>- Decommissioned Certificate of Occupancy (CofO) Number: " . ($application->fileno ?? '[No CofO Number Available]') . "</li>";
        $content .= "<li>- Total Land Area: " . ($application->plot_size ? $application->plot_size . ' Square Meters' : '[Not Specified]') . "</li>";
        $content .= "</ul>";

        // Buyers List
        $content .= "<h2>BUYERS LIST</h2>";
        $content .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        $content .= "<thead>";
        $content .= "<tr><th>SN</th><th>BUYER NAME</th><th>UNIT NO.</th><th>MEASUREMENT (SQM)</th></tr>";
        $content .= "</thead>";
        $content .= "<tbody>";
        
        foreach ($buyers as $index => $buyer) {
            $content .= "<tr>";
            $content .= "<td>" . ($index + 1) . "</td>";
            $content .= "<td>" . ($buyer->buyer_title ? $buyer->buyer_title . ' ' : '') . $buyer->buyer_name . "</td>";
            $content .= "<td>" . $buyer->unit_no . "</td>";
            $content .= "<td>" . ($buyer->measurement ?? 'N/A') . "</td>";
            $content .= "</tr>";
        }
        
        $content .= "</tbody>";
        $content .= "</table>";
        
        $content .= "<br><p>This agreement constitutes the final conveyance for the sectional titling of the above-mentioned property.</p>";
        $content .= "<p>Generated on: " . now()->format('F j, Y \a\t g:i A') . "</p>";

        return $content;
    }
}