<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubPlanningRecommendationController extends Controller
{
    public function getSitePlanDimensions($subApplicationId)
    {
        // First try to get from site_plan_dimensions with sub_application_id
        $dimensions = DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('sub_application_id', $subApplicationId)
            ->orderBy('order')
            ->get();

        // If no data found, use the SAME logic as joint site inspection for sub-applications
        if ($dimensions->isEmpty()) {
            // Get unit measurements from subapplications table (same as joint site inspection)
            $subApplication = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if ($subApplication) {
                $unitMeasurements = collect([
                    (object) [
                        'description' => $subApplication->unit_number,
                        'dimension' => $subApplication->unit_size,
                        'id' => $subApplication->id,
                        'order' => 1
                    ]
                ]);
                $dimensions = $unitMeasurements;
            }
        }

        // If still no data, try getting from parent application's st_unit_measurements
        if ($dimensions->isEmpty()) {
            $subApplication = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if ($subApplication && $subApplication->main_application_id) {
                $unitMeasurements = DB::connection('sqlsrv')
                    ->table('st_unit_measurements')
                    ->select('unit_no as description', 'measurement as dimension', 'id', 'application_id', DB::raw('1 as order'))
                    ->where('application_id', $subApplication->main_application_id)
                    ->orderBy('unit_no')
                    ->get();
                $dimensions = $unitMeasurements;
            }
        }

        return response()->json($dimensions);
    }

    public function getSharedUtilities($subApplicationId)
    {
        // First get utilities from Joint Site Inspection report (SAME as JSI)
        $jsiReport = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->where('sub_application_id', $subApplicationId)
            ->first();

        // If no report found with sub_application_id, try with parent application
        if (!$jsiReport) {
            $subApplication = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if ($subApplication && $subApplication->main_application_id) {
                $jsiReport = DB::connection('sqlsrv')
                    ->table('joint_site_inspection_reports')
                    ->where('application_id', $subApplication->main_application_id)
                    ->where('sub_application_id', $subApplicationId)
                    ->first();
            }
        }

        $result = collect([]);

        if ($jsiReport && !empty($jsiReport->shared_utilities)) {
            $sharedUtilitiesJson = $jsiReport->shared_utilities;
            $sharedUtilities = [];

            if (is_string($sharedUtilitiesJson)) {
                $sharedUtilities = json_decode($sharedUtilitiesJson, true) ?? [];
            } elseif (is_array($sharedUtilitiesJson)) {
                $sharedUtilities = $sharedUtilitiesJson;
            }

            foreach ($sharedUtilities as $index => $utility) {
                $result->push((object) [
                    'id' => null,
                    'sub_application_id' => $subApplicationId,
                    'utility_type' => $utility,
                    'dimension' => 0, // JSI doesn't store dimensions for utilities
                    'count' => 1,
                    'block' => '1',
                    'section' => '1',
                    'order' => $index + 1
                ]);
            }
        }

        // If no JSI utilities found, fall back to shared_utilities table
        if ($result->isEmpty()) {
            $existingUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('sub_application_id', $subApplicationId)
                ->orderBy('order')
                ->get();

            // If no data found with sub_application_id, try shared_utilities table directly
            if ($existingUtilities->isEmpty()) {
                $existingUtilities = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('application_id', $subApplicationId)
                    ->orderBy('order')
                    ->get();
            }

            $result = collect($existingUtilities);
        }

        // Get shared areas from subapplications
        $application = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->first();

        if (!$application) {
            return response()->json([]);
        }

        $sharedAreasJson = $application->shared_areas;

        $sharedAreas = [];
        if (!empty($sharedAreasJson)) {
            if (is_string($sharedAreasJson)) {
                // Try to decode JSON string
                $sharedAreas = json_decode($sharedAreasJson, true);
            } elseif (is_array($sharedAreasJson)) {
                // Already an array
                $sharedAreas = $sharedAreasJson;
            }

            // If not array (could be a string or null), convert to array 
            if (!is_array($sharedAreas)) {
                // Try to convert comma-separated string to array if needed
                if (is_string($sharedAreasJson) && strpos($sharedAreasJson, ',') !== false) {
                    $sharedAreas = array_map('trim', explode(',', $sharedAreasJson));
                } else {
                    $sharedAreas = [];
                }
            }
        }

        // Get physical planning data
        $physicalPlanningData = DB::connection('sqlsrv')
            ->table('physicalPlanning')
            ->where('sub_application_id', $subApplicationId)
            ->get();

        // Convert to collection and key by utility type
        $physicalPlanningDataKeyed = collect($physicalPlanningData)->keyBy('Shared_Utilities_List');

        // If JSI utilities found, return early
        if (!$result->isEmpty()) {
            return response()->json($result->values());
        }

        // Prepare result - start with existing utilities
        $result = collect($existingUtilities ?? []);

        // Add entries from shared_areas that don't exist in utilities yet
        foreach ($sharedAreas as $area) {
            if (empty($area))
                continue; // Skip empty entries

            // Skip if already exists in the result
            $exists = $result->where('utility_type', $area)->first();
            if ($exists) {
                continue;
            }

            // Create new utility entry from shared_area
            $newUtility = (object) [
                'id' => null,
                'sub_application_id' => $subApplicationId,
                'utility_type' => $area,
                'dimension' => 0,
                'count' => 1,  // Default to 1 count
                'order' => $result->count() + 1
            ];

            // Check if we have data in physicalPlanning
            if ($physicalPlanningDataKeyed->has($area)) {
                $ppData = $physicalPlanningDataKeyed[$area];
                $newUtility->dimension = $ppData->Recommended_Size ?? 0;
                $newUtility->count = $ppData->count ?? 1;
            }

            $result->push($newUtility);
        }

        // If we still have no utilities and no shared areas, try getting data from parent application
        if ($result->isEmpty() && empty($sharedAreas) && $application && $application->main_application_id) {
            $parentUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $application->main_application_id)
                ->orderBy('order')
                ->get();

            foreach ($parentUtilities as $utility) {
                $result->push((object) [
                    'id' => null,
                    'sub_application_id' => $subApplicationId,
                    'utility_type' => $utility->utility_type,
                    'dimension' => $utility->dimension ?? 0,
                    'count' => $utility->count ?? 1,
                    'block' => $utility->block ?? '1',
                    'section' => $utility->section ?? '1',
                    'order' => $utility->order ?? 1
                ]);
            }
        }

        // If we still have no utilities, create at least one with common default
        if ($result->isEmpty() && !empty($sharedAreas)) {
            $result->push((object) [
                'id' => null,
                'sub_application_id' => $subApplicationId,
                'utility_type' => $sharedAreas[0] ?? 'Common Area',
                'dimension' => 0,
                'count' => 1,
                'order' => 1
            ]);
        }

        return response()->json($result->values());
    }

    public function saveSitePlanDimension(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sub_application_id' => 'required|integer',
            'description' => 'required|string|max:255',
            'dimension' => 'required|numeric',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $request->input('id');
        $data = [
            'sub_application_id' => $request->input('sub_application_id'),
            'description' => $request->input('description'),
            'dimension' => $request->input('dimension'),
            'order' => $request->input('order', 0)
        ];

        if ($id) {
            // Update existing record
            DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('id', $id)
                ->update($data);

            $dimension = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('id', $id)
                ->first();
        } else {
            // Create new record
            $id = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->insertGetId($data);

            $dimension = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('id', $id)
                ->first();
        }

        return response()->json(['success' => true, 'dimension' => $dimension]);
    }

    public function saveSharedUtility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sub_application_id' => 'required|integer',
            'utility_type' => 'required|string|max:255',
            'dimension' => 'required|numeric',
            'count' => 'required|integer',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $request->input('id');
        $subApplicationId = $request->input('sub_application_id');
        $utilityType = $request->input('utility_type');
        $dimension = $request->input('dimension');
        $count = $request->input('count');
        $order = $request->input('order', 0);

        $data = [
            'sub_application_id' => $subApplicationId,
            'utility_type' => $utilityType,
            'dimension' => $dimension,
            'count' => $count,
            'order' => $order
        ];

        // Update or create in shared_utilities table
        if ($id) {
            // Update existing record
            DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('id', $id)
                ->update($data);

            $utility = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('id', $id)
                ->first();
        } else {
            // Create new record
            $id = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->insertGetId($data);

            $utility = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('id', $id)
                ->first();
        }

        // Now also update or create in physicalPlanning table - with improved error handling
        try {
            $existingPP = DB::connection('sqlsrv')
                ->table('physicalPlanning')
                ->where('sub_application_id', $subApplicationId)
                ->where('Shared_Utilities_List', $utilityType)
                ->first();

            $ppData = [
                'Recommended_Size' => $dimension,
                'count' => $count,
            ];

            if ($existingPP) {
                // Update existing record
                DB::connection('sqlsrv')
                    ->table('physicalPlanning')
                    ->where('id', $existingPP->id)
                    ->update($ppData);
            } else {
                // Create new record in physicalPlanning
                DB::connection('sqlsrv')
                    ->table('physicalPlanning')
                    ->insertGetId([
                        'sub_application_id' => $subApplicationId,
                        'Shared_Utilities_List' => $utilityType,
                        'Recommended_Size' => $dimension,
                        'count' => $count,
                        'Area_Under_Application' => 'Auto-generated from planning recommendation'
                    ]);
            }
        } catch (\Exception $e) {
            // Continue without failing the whole operation
        }

        return response()->json(['success' => true, 'utility' => $utility]);
    }

    public function deleteSitePlanDimension(Request $request)
    {
        $id = $request->input('id');

        DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function deleteSharedUtility(Request $request)
    {
        $id = $request->input('id');

        DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Debug endpoint to view shared areas data
     */
    public function debugSharedAreas($subApplicationId)
    {
        // Get application data
        $application = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'Sub application not found',
                'sub_application_id' => $subApplicationId
            ]);
        }

        // Get shared areas
        $sharedAreasRaw = $application->shared_areas;
        $sharedAreasParsed = null;

        if (is_string($sharedAreasRaw)) {
            // Try to parse as JSON
            $sharedAreasParsed = json_decode($sharedAreasRaw, true);
        }

        // Get all utilities for this application
        $utilities = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->get();

        // Get physical planning data
        $physicalPlanning = DB::connection('sqlsrv')
            ->table('physicalPlanning')
            ->where('sub_application_id', $subApplicationId)
            ->get();

        return response()->json([
            'sub_application_id' => $subApplicationId,
            'shared_areas_raw' => $sharedAreasRaw,
            'shared_areas_parsed' => $sharedAreasParsed,
            'utilities_count' => count($utilities),
            'utilities' => $utilities,
            'physical_planning_count' => count($physicalPlanning),
            'physical_planning' => $physicalPlanning
        ]);
    }


    public function ApprovalMome(Request $request)
    {
        $PageTitle = 'Application for planning recommendation approval';
        $PageDescription = '';

        // Load application data if ID is provided
        $application = null;
        $surveyRecord = null;
        $additionalObservations = null;
        $motherApplication = null;

        if ($request->has('id')) {
            $application = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $request->get('id'))
                ->first();

            if ($application) {
                // Retrieve parent application to get property details
                $motherApplication = DB::connection('sqlsrv')
                    ->table('mother_applications')
                    ->where('id', $application->main_application_id)
                    ->first();

                $surveyRecord = DB::connection('sqlsrv')
                    ->table('surveyCadastralRecord')
                    ->where('sub_application_id', $application->id)
                    ->first();

                // Retrieve additional observations
                $additionalObservations = DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->where('sub_application_id', $application->id)
                    ->value('additional_observations');
            }
        }

        return view('sub_pr_memos.approval', compact(
            'PageTitle',
            'PageDescription',
            'application',
            'motherApplication',
            'surveyRecord',
            'additionalObservations'
        ));
    }

    // Update method to save additional observations
    // public function saveAdditionalObservations(Request $request)
    // {
    //     // Log the incoming request for debugging
    //     \Log::info('Save observations request received', [
    //         'request' => $request->all()
    //     ]);

    //     // Validate the request
    //     $validator = Validator::make($request->all(), [
    //         'sub_application_id' => 'required|integer',
    //         'additional_observations' => 'nullable|string'
    //     ]);

    //     if ($validator->fails()) {
    //         \Log::error('Validation failed', [
    //             'errors' => $validator->errors()->toArray()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         // Check if record exists
    //         $exists = DB::connection('sqlsrv')
    //             ->table('planning_approval_details')
    //             ->where('sub_application_id', $request->sub_application_id)
    //             ->exists();

    //         \Log::info('Record exists check', [
    //             'sub_application_id' => $request->sub_application_id, 
    //             'exists' => $exists
    //         ]);

    //         if ($exists) {
    //             // Update existing record
    //             DB::connection('sqlsrv')
    //                 ->table('planning_approval_details')
    //                 ->where('sub_application_id', $request->sub_application_id)
    //                 ->update([
    //                     'additional_observations' => $request->additional_observations,
    //                     'updated_at' => now(),
    //                     'updated_by' => auth()->user()->name ?? 'system'
    //                 ]);

    //             \Log::info('Updated existing record');
    //         } else {
    //             // Create new record
    //             DB::connection('sqlsrv')
    //                 ->table('planning_approval_details')
    //                 ->insert([
    //                     'sub_application_id' => $request->sub_application_id,
    //                     'additional_observations' => $request->additional_observations,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                     'created_by' => auth()->user()->name ?? 'system',
    //                     'updated_by' => auth()->user()->name ?? 'system'
    //                 ]);

    //             \Log::info('Created new record');
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Additional observations saved successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('Error saving additional observations: ' . $e->getMessage(), [
    //             'exception' => $e,
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to save additional observations: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    // Update method to save additional observations
    public function saveAdditionalObservations(Request $request)
    {
        // Log the incoming request for debugging
        \Log::info('Save observations request received for sub-application', [
            'request' => $request->all(),
            'app_id' => $request->sub_application_id,
            'observations' => $request->additional_observations
        ]);

        // Validate the request
        $validator = Validator::make($request->all(), [
            'sub_application_id' => 'required|integer',
            'additional_observations' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if planning_approval_details table exists
            $tableExists = \Schema::connection('sqlsrv')->hasTable('planning_approval_details');

            if (!$tableExists) {
                // Create the table if it doesn't exist
                \Schema::connection('sqlsrv')->create('planning_approval_details', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('application_id')->nullable();
                    $table->unsignedBigInteger('sub_application_id')->nullable();
                    $table->text('additional_observations')->nullable();
                    $table->timestamps();
                    $table->string('created_by', 100)->nullable();
                    $table->string('updated_by', 100)->nullable();
                });

                \Log::info('Created planning_approval_details table');
            }

            // Check if record exists
            $exists = DB::connection('sqlsrv')
                ->table('planning_approval_details')
                ->where('sub_application_id', $request->sub_application_id)
                ->exists();

            \Log::info('Record exists check', [
                'sub_application_id' => $request->sub_application_id,
                'exists' => $exists
            ]);

            if ($exists) {
                // Update existing record
                DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->where('sub_application_id', $request->sub_application_id)
                    ->update([
                        'additional_observations' => $request->additional_observations,
                        'updated_at' => now(),
                        'updated_by' => auth()->user()->name ?? 'system'
                    ]);

                \Log::info('Updated existing record');
            } else {
                // Create new record
                DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->insert([
                        'sub_application_id' => $request->sub_application_id,
                        'additional_observations' => $request->additional_observations,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system'
                    ]);

                \Log::info('Created new record');
            }

            return response()->json([
                'success' => true,
                'message' => 'Additional observations saved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error saving additional observations: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save additional observations: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ApprovalMomeSave(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'sub_application_id' => 'required|integer',
            'memo_date' => 'required|date',
            'director_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update the application status if not already approved
            $updated = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $request->sub_application_id)
                ->whereNotIn('planning_recommendation_status', ['approve', 'approved'])
                ->update([
                    'planning_recommendation_status' => 'approved',
                    'planning_approval_date' => $request->memo_date,
                    'updated_by' => auth()->user()->id ?? 'system',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Approval memo saved successfully',
                'updated' => $updated > 0
            ]);
        } catch (\Exception $e) {
            \Log::error('Error saving approval memo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save approval memo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Declination(Request $request)
    {
        $PageTitle = 'Declination of Planning Recommendation for Sectional Titling';
        $PageDescription = '';

        // Load application data if ID is provided
        $application = null;
        $surveyRecord = null;
        $declineReasons = null;
        $motherApplication = null;

        if ($request->has('id')) {
            $application = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $request->get('id'))
                ->first();

            if ($application) {
                // Retrieve parent application to get property details
                $motherApplication = DB::connection('sqlsrv')
                    ->table('mother_applications')
                    ->where('id', $application->main_application_id)
                    ->first();

                $surveyRecord = DB::connection('sqlsrv')
                    ->table('surveyCadastralRecord')
                    ->where('sub_application_id', $application->id)
                    ->first();

                $declineReasons = DB::connection('sqlsrv')
                    ->table('planning_decline_reasons')
                    ->where('sub_application_id', $application->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }

        return view('sub_pr_memos.declination', compact(
            'PageTitle',
            'PageDescription',
            'application',
            'motherApplication',
            'surveyRecord',
            'declineReasons'
        ));
    }

    public function DeclinationSave(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'sub_application_id' => 'required|integer',
            'approval_date' => 'required|date',

            // Main reason flags
            'accessibility_selected' => 'nullable|boolean',
            'land_use_selected' => 'nullable|boolean',
            'utility_selected' => 'nullable|boolean',
            'road_reservation_selected' => 'nullable|boolean',

            // Simplified form fields for major reasons
            'access_road_details' => 'nullable|string',
            'pedestrian_details' => 'nullable|string',
            'zoning_details' => 'nullable|string',
            'density_details' => 'nullable|string',
            'overhead_details' => 'nullable|string',
            'underground_details' => 'nullable|string',
            'right_of_way_details' => 'nullable|string',
            'road_width_details' => 'nullable|string',

            // Complete formatted reason summary
            'reason_summary' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Save the decline reasons
            $declineId = DB::connection('sqlsrv')
                ->table('planning_decline_reasons')
                ->insertGetId([
                    'sub_application_id' => $request->sub_application_id,
                    'submitted_by' => $request->submitted_by ?? auth()->id() ?? 1,
                    'approval_date' => $request->approval_date,

                    // Main reason flags
                    'accessibility_selected' => $request->accessibility_selected ?? 0,
                    'land_use_selected' => $request->land_use_selected ?? 0,
                    'utility_selected' => $request->utility_selected ?? 0,
                    'road_reservation_selected' => $request->road_reservation_selected ?? 0,

                    // Simplified form fields mapped to database columns
                    'access_road_details' => $request->access_road_details,
                    'pedestrian_details' => $request->pedestrian_details,
                    'zoning_details' => $request->zoning_details,
                    'density_details' => $request->density_details,
                    'overhead_details' => $request->overhead_details,
                    'underground_details' => $request->underground_details,
                    'right_of_way_details' => $request->right_of_way_details,
                    'road_width_details' => $request->road_width_details,

                    // Complete formatted reason text
                    'reason_summary' => $request->reason_summary,

                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => auth()->user()->id ?? 'system',
                    'updated_by' => auth()->user()->id ?? 'system'
                ]);

            // Update the application status
            DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $request->sub_application_id)
                ->update([
                    'planning_recommendation_status' => 'declined',
                    'planning_approval_date' => $request->approval_date,
                    'planning_recomm_comments' => $request->reason_summary,
                    'updated_by' => auth()->user()->id ?? 'system',
                    'updated_at' => now()
                ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Declination memo saved successfully',
                'decline_id' => $declineId
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            \Log::error('Error saving declination memo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save declination memo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show recommendation page
     */
    public function showRecommendation(Request $request, $id)
    {
        // Get the sub-application
        $application = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            abort(404, 'Sub-application not found');
        }

        // Get mother application for property details
        $motherApplication = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $application->main_application_id)
            ->first();

        // Merge mother application property details with sub-application
        if ($motherApplication) {
            $application->property_house_no = $motherApplication->property_house_no;
            $application->property_plot_no = $motherApplication->property_plot_no;
            $application->property_street_name = $motherApplication->property_street_name;
            $application->property_district = $motherApplication->property_district;
            $application->property_lga = $motherApplication->property_lga;
            $application->land_use = $motherApplication->land_use;
        }

        [$dimensionsData, $utilitiesData] = $this->buildPlanningRecommendationDatasets($application);

        // Add retrieving additional observations
        $additionalObservations = DB::connection('sqlsrv')
            ->table('planning_approval_details')
            ->where('sub_application_id', $id)
            ->value('additional_observations');

        return view('sub_actions.recommendation', compact(
            'application',
            'motherApplication',
            'additionalObservations',
            'dimensionsData',
            'utilitiesData'
        ));
    }

    public function printRecommendation($id)
    {
        $application = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            abort(404, 'Sub application not found');
        }

        $motherApplication = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $application->main_application_id)
            ->first();

        if ($motherApplication) {
            $application->property_house_no = $motherApplication->property_house_no;
            $application->property_plot_no = $motherApplication->property_plot_no;
            $application->property_street_name = $motherApplication->property_street_name;
            $application->property_district = $motherApplication->property_district;
            $application->property_lga = $motherApplication->property_lga;
            $application->land_use = $motherApplication->land_use;
        }

        [$dimensions, $utilities] = $this->buildPlanningRecommendationDatasets($application);

        $printMode = true;

        return view('sub_actions.planning_recomm', [
            'application' => $application,
            'motherApplication' => $motherApplication,
            'printMode' => $printMode,
            'dimensionsData' => $dimensions,
            'utilitiesData' => $utilities,
        ]);
    }

    protected function buildPlanningRecommendationDatasets($application)
    {
        $subApplicationId = $application->id;
        $parentApplicationId = $application->main_application_id;

        $dimensionsRecords = DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('sub_application_id', $subApplicationId)
            ->orderBy('order')
            ->get();

        $dimensions = collect($dimensionsRecords)
            ->map(function ($record, $index) {
                $description = $record->description ?? ($record->unit_no ?? null);
                $dimensionValue = $record->dimension ?? ($record->measurement ?? null);

                if ($description === null && $dimensionValue === null) {
                    return null;
                }

                return [
                    'sn' => $record->order ?? ($index + 1),
                    'description' => $description,
                    'dimension' => $dimensionValue,
                ];
            })
            ->filter()
            ->values();

        if ($dimensions->isEmpty()) {
            $unitNumber = $application->unit_number ?? null;
            $unitSize = $application->unit_size ?? null;

            if (!empty($unitNumber) || !empty($unitSize)) {
                $dimensions = collect([
                    [
                        'sn' => 1,
                        'description' => $unitNumber ?: 'Unit 1',
                        'dimension' => $unitSize,
                    ],
                ]);
            }
        }

        if ($dimensions->isEmpty() && !empty($parentApplicationId)) {
            $dimensions = DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->select('unit_no as description', 'measurement as dimension', 'id')
                ->where('application_id', $parentApplicationId)
                ->orderBy('unit_no')
                ->get()
                ->map(function ($item, $index) {
                    if ((empty($item->description) && empty($item->dimension))) {
                        return null;
                    }

                    return [
                        'sn' => $index + 1,
                        'description' => $item->description,
                        'dimension' => $item->dimension,
                    ];
                })
                ->filter()
                ->values();
        }

        $utilities = collect();

        $jsiReport = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->where('sub_application_id', $subApplicationId)
            ->first();

        if (!$jsiReport && !empty($parentApplicationId)) {
            $jsiReport = DB::connection('sqlsrv')
                ->table('joint_site_inspection_reports')
                ->where('application_id', $parentApplicationId)
                ->where('sub_application_id', $subApplicationId)
                ->first();
        }

        if ($jsiReport && !empty($jsiReport->shared_utilities)) {
            $sharedUtilitiesRaw = $jsiReport->shared_utilities;
            $sharedUtilities = [];

            if (is_string($sharedUtilitiesRaw)) {
                $decoded = json_decode($sharedUtilitiesRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $sharedUtilities = $decoded;
                } elseif (str_contains($sharedUtilitiesRaw, ',')) {
                    $sharedUtilities = array_map('trim', explode(',', $sharedUtilitiesRaw));
                }
            } elseif (is_array($sharedUtilitiesRaw)) {
                $sharedUtilities = $sharedUtilitiesRaw;
            }

            foreach ($sharedUtilities as $utility) {
                if (is_array($utility)) {
                    $label = $utility['utility_type']
                        ?? $utility['name']
                        ?? $utility['label']
                        ?? null;

                    if (empty($label)) {
                        continue;
                    }

                    $utilities->push([
                        'utility_type' => $label,
                        'dimension' => $utility['dimension'] ?? ($utility['size'] ?? 0),
                        'count' => $utility['count'] ?? 1,
                        'block' => $utility['block'] ?? ($utility['block_label'] ?? '1'),
                        'section' => $utility['section'] ?? ($utility['section_label'] ?? '1'),
                    ]);
                } elseif (!empty($utility)) {
                    $utilities->push([
                        'utility_type' => $utility,
                        'dimension' => 0,
                        'count' => 1,
                        'block' => '1',
                        'section' => '1',
                    ]);
                }
            }
        }

        $existingUtilities = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->orderBy('order')
            ->get();

        if ($utilities->isEmpty() && $existingUtilities->isNotEmpty()) {
            $utilities = collect($existingUtilities)->map(function ($utility) {
                return [
                    'utility_type' => $utility->utility_type ?? null,
                    'dimension' => $utility->dimension ?? 0,
                    'count' => $utility->count ?? 1,
                    'block' => $utility->block ?? '1',
                    'section' => $utility->section ?? '1',
                ];
            })->filter(function ($item) {
                return !empty($item['utility_type']);
            })->values();
        }

        if ($utilities->isEmpty() && !empty($parentApplicationId)) {
            $parentUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $parentApplicationId)
                ->orderBy('order')
                ->get();

            if ($parentUtilities->isNotEmpty()) {
                $utilities = collect($parentUtilities)->map(function ($utility) {
                    return [
                        'utility_type' => $utility->utility_type ?? null,
                        'dimension' => $utility->dimension ?? 0,
                        'count' => $utility->count ?? 1,
                        'block' => $utility->block ?? '1',
                        'section' => $utility->section ?? '1',
                    ];
                })->filter(function ($item) {
                    return !empty($item['utility_type']);
                })->values();
            }
        }

        $sharedAreas = [];
        $sharedAreasRaw = $application->shared_areas ?? null;

        if (!empty($sharedAreasRaw)) {
            if (is_string($sharedAreasRaw)) {
                $decoded = json_decode($sharedAreasRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $sharedAreas = $decoded;
                } elseif (str_contains($sharedAreasRaw, ',')) {
                    $sharedAreas = array_map('trim', explode(',', $sharedAreasRaw));
                } else {
                    $sharedAreas = [trim($sharedAreasRaw)];
                }
            } elseif (is_array($sharedAreasRaw)) {
                $sharedAreas = $sharedAreasRaw;
            }
        }

        $physicalPlanningData = DB::connection('sqlsrv')
            ->table('physicalPlanning')
            ->where('sub_application_id', $subApplicationId)
            ->get()
            ->filter(function ($record) {
                return !empty($record->Shared_Utilities_List);
            })
            ->keyBy(function ($record) {
                return strtoupper(trim($record->Shared_Utilities_List));
            });

        if (!empty($sharedAreas)) {
            foreach ($sharedAreas as $area) {
                if (empty($area)) {
                    continue;
                }

                $normalizedArea = is_array($area)
                    ? ($area['utility_type'] ?? $area['name'] ?? $area['label'] ?? null)
                    : $area;

                if (empty($normalizedArea)) {
                    continue;
                }

                $alreadyExists = $utilities->contains(function ($item) use ($normalizedArea) {
                    return isset($item['utility_type'])
                        && strcasecmp($item['utility_type'], $normalizedArea) === 0;
                });

                if ($alreadyExists) {
                    continue;
                }

                $ppKey = strtoupper(trim($normalizedArea));
                $ppData = $physicalPlanningData->get($ppKey);

                $utilities->push([
                    'utility_type' => $normalizedArea,
                    'dimension' => $ppData->Recommended_Size ?? 0,
                    'count' => $ppData->count ?? 1,
                    'block' => $ppData->block ?? '1',
                    'section' => $ppData->section ?? '1',
                ]);
            }
        }

        return [
            $dimensions->values()->toArray(),
            $utilities->values()->toArray(),
        ];
    }
}
