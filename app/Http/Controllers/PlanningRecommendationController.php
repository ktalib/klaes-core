<?php

namespace App\Http\Controllers;

use App\Models\JointSiteInspectionReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PlanningRecommendationController extends Controller
{
    public function getSitePlanDimensions($applicationId)
    {
        $dimensions = $this->assembleDimensionDataset($applicationId);

        return response()->json($dimensions);
    }

    public function getSharedUtilities($applicationId)
    {
        $utilities = $this->assembleUtilityDataset($applicationId);

        return response()->json($utilities);
    }

    public function saveSitePlanDimension(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer',
            'description' => 'required|string|max:255',
            'dimension' => 'required|numeric',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $request->input('id');
        $data = [
            'application_id' => $request->input('application_id'),
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
            'application_id' => 'required|integer',
            'utility_type' => 'required|string|max:255',
            'dimension' => 'required|numeric',
            'count' => 'required|integer',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $request->input('id');
        $applicationId = $request->input('application_id');
        $utilityType = $request->input('utility_type');
        $dimension = $request->input('dimension');
        $count = $request->input('count');
        $order = $request->input('order', 0);

        $data = [
            'application_id' => $applicationId,
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
                ->where('application_id', $applicationId)
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
                        'application_id' => $applicationId,
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

    /**
     * Batch update utilities and sync with physicalPlanning table
     */
    public function batchUpdateUtilities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer',
            'utilities' => 'required|array',
            'utilities.*.utility_type' => 'required|string|max:255',
            'utilities.*.dimension' => 'required|numeric',
            'utilities.*.count' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $applicationId = $request->input('application_id');
        $utilities = $request->input('utilities');
        $updatedUtilities = [];

        foreach ($utilities as $utilityData) {
            $id = $utilityData['id'] ?? null;
            $utilityType = $utilityData['utility_type'];
            $dimension = $utilityData['dimension'];
            $count = $utilityData['count'];

            $data = [
                'application_id' => $applicationId,
                'utility_type' => $utilityType,
                'dimension' => $dimension,
                'count' => $count,
                'order' => $utilityData['order'] ?? 0
            ];

            // Update or create in shared_utilities table
            if ($id) {
                DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('id', $id)
                    ->update($data);

                $utility = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('id', $id)
                    ->first();
            } else {
                $id = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->insertGetId($data);

                $utility = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('id', $id)
                    ->first();
            }

            // Update or create in physicalPlanning table
            try {
                $existingPP = DB::connection('sqlsrv')
                    ->table('physicalPlanning')
                    ->where('application_id', $applicationId)
                    ->where('Shared_Utilities_List', $utilityType)
                    ->first();

                if ($existingPP) {
                    DB::connection('sqlsrv')
                        ->table('physicalPlanning')
                        ->where('id', $existingPP->id)
                        ->update([
                            'Recommended_Size' => $dimension,
                            'count' => $count,
                        ]);
                } else {
                    DB::connection('sqlsrv')
                        ->table('physicalPlanning')
                        ->insertGetId([
                            'application_id' => $applicationId,
                            'Shared_Utilities_List' => $utilityType,
                            'Recommended_Size' => $dimension,
                            'count' => $count,
                            'Area_Under_Application' => 'Auto-generated from batch update'
                        ]);
                }
            } catch (\Exception $e) {
                // Continue without failing
            }

            $updatedUtilities[] = $utility;
        }

        return response()->json([
            'success' => true,
            'message' => 'All utilities updated successfully',
            'utilities' => $updatedUtilities
        ]);
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
    public function debugSharedAreas($applicationId)
    {
        // Get application data
        $application = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'Application not found',
                'application_id' => $applicationId
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
            ->where('application_id', $applicationId)
            ->get();

        // Get physical planning data
        $physicalPlanning = DB::connection('sqlsrv')
            ->table('physicalPlanning')
            ->where('application_id', $applicationId)
            ->get();

        return response()->json([
            'application_id' => $applicationId,
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

        if ($request->has('id')) {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $request->get('id'))
                ->first();

            if ($application) {
                $surveyRecord = DB::connection('sqlsrv')
                    ->table('surveyCadastralRecord')
                    ->where('application_id', $application->id)
                    ->first();

                // Retrieve additional observations
                $additionalObservations = DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->where('application_id', $application->id)
                    ->value('additional_observations');
            }
        }

        return view('pr_memos.approval', compact('PageTitle', 'PageDescription', 'application', 'surveyRecord', 'additionalObservations'));
    }

    // New method to save additional observations
    public function saveAdditionalObservations(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer',
            'additional_observations' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        try {
            // Check if record exists
            $exists = DB::connection('sqlsrv')
                ->table('planning_approval_details')
                ->where('application_id', $request->application_id)
                ->exists();

            if ($exists) {
                // Update existing record
                DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->where('application_id', $request->application_id)
                    ->update([
                        'additional_observations' => $request->additional_observations,
                        'updated_at' => now(),
                        'updated_by' => auth()->user()->name ?? 'system'
                    ]);
            } else {
                // Create new record
                DB::connection('sqlsrv')
                    ->table('planning_approval_details')
                    ->insert([
                        'application_id' => $request->application_id,
                        'additional_observations' => $request->additional_observations,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => auth()->user()->name ?? 'system',
                        'updated_by' => auth()->user()->name ?? 'system'
                    ]);
            }

            // Handle both AJAX and standard form requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Additional observations saved successfully'
                ]);
            } else {
                return redirect()->back()->with('success', 'Additional observations saved successfully!');
            }
        } catch (\Exception $e) {
            \Log::error('Error saving additional observations: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save additional observations: ' . $e->getMessage()
                ], 500);
            } else {
                return redirect()->back()->with('error', 'Failed to save additional observations: ' . $e->getMessage());
            }
        }
    }

    public function ApprovalMomeSave(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer',
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
                ->table('mother_applications')
                ->where('id', $request->application_id)
                ->whereNotIn('planning_recommendation_status', ['approve', 'approved'])
                ->update([
                    'planning_recommendation_status' => 'approved',
                    'planning_approval_date' => $request->memo_date,
                    'updated_by' => auth()->user()->name ?? 'system',
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

        if ($request->has('id')) {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $request->get('id'))
                ->first();

            if ($application) {
                $surveyRecord = DB::connection('sqlsrv')
                    ->table('surveyCadastralRecord')
                    ->where('application_id', $application->id)
                    ->first();

                $declineReasons = DB::connection('sqlsrv')
                    ->table('planning_decline_reasons')
                    ->where('application_id', $application->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }

        return view('pr_memos.declination', compact('PageTitle', 'PageDescription', 'application', 'surveyRecord', 'declineReasons'));
    }

    public function DeclinationSave(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer',
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
                    'application_id' => $request->application_id,
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
                    'created_by' => auth()->user()->name ?? 'system',
                    'updated_by' => auth()->user()->name ?? 'system'
                ]);

            // Update the application status
            DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $request->application_id)
                ->update([
                    'planning_recommendation_status' => 'declined',
                    'planning_approval_date' => $request->approval_date,
                    'recomm_comments' => $request->reason_summary,
                    'updated_by' => auth()->user()->name ?? 'system',
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
        // ...existing code...

        // Add retrieving additional observations
        $additionalObservations = DB::connection('sqlsrv')
            ->table('planning_approval_details')
            ->where('application_id', $id)
            ->value('additional_observations');

        return view('actions.recommendation', compact(
            'application',
            'additionalObservations'
            // ...other variables...
        ));
    }

    /**
     * Print planning recommendation
     */
    public function printRecommendation($id)
    {
        $application = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            abort(404, 'Application not found');
        }

        $dimensions = $this->assembleDimensionDataset($id);
        $utilities = $this->assembleUtilityDataset($id);

        $printMode = true;

        return view('actions.planning_recomm', [
            'application' => $application,
            'printMode' => $printMode,
            'dimensionsData' => $dimensions->values()->toArray(),
            'utilitiesData' => $utilities->values()->toArray(),
        ]);
    }

    protected function assembleDimensionDataset(int $applicationId)
    {
        // Table A lists every fragmented unit, which means every buyer row - not
        // only the units that happen to carry a stored measurement. Reading
        // st_unit_measurements alone dropped the buyers with none (230 of 249 on
        // COM-1991-46), so the Recommendation Report disagreed with the Joint Site
        // Inspection table, which is buyer-driven. Same LEFT JOIN on buyer_id the
        // JSI table uses: joining on unit_no fans rows out, because ST unit numbers
        // repeat (U2, U MD).
        // The buyer rows carry their own section number, so nothing may be looked
        // up (and overwritten) afterwards - see $sectionsAreAuthoritative below.
        $sectionsAreAuthoritative = true;

        $dimensionCollection = DB::connection('sqlsrv')
            ->table('buyer_list as bl')
            ->leftJoin('st_unit_measurements as sum', function ($join) {
                $join->on('bl.id', '=', 'sum.buyer_id')
                    ->on('bl.application_id', '=', 'sum.application_id');
            })
            ->where('bl.application_id', $applicationId)
            ->select('bl.id', 'bl.unit_no', 'bl.section_number', 'sum.measurement')
            // Same ORDER BY as BuyerListController::getBuyersList(), so Table A
            // reads in the exact order officers see on the Buyers List tab. Ordering
            // by unit_no instead sorted as text (U1, U10, U11 ... U19, U2, U20) and
            // by bl.id reversed the list wherever the sections were captured in
            // descending order.
            ->orderBy('bl.created_at', 'desc')
            ->orderBy('bl.id', 'desc')
            ->get()
            ->map(function ($record, $index) {
                $measurement = $record->measurement ?? null;
                $dimensionNumeric = is_numeric($measurement) ? (float) $measurement : null;

                return [
                    'sn' => $index + 1,
                    'description' => $record->unit_no ?? null,
                    'dimension' => $dimensionNumeric ?? $measurement,
                    'dimension_numeric' => $dimensionNumeric,
                    'dimension_raw' => $measurement,
                    'dimension_display' => $measurement,
                    'count' => 1,
                    'section' => $record->section_number ?? null,
                    'section_number' => $record->section_number ?? null,
                    'section_no' => $record->section_number ?? null,
                ];
            });

        // Older applications indexed before buyers were captured still rely on the
        // raw measurement rows.
        if ($dimensionCollection->isEmpty()) {
            $sectionsAreAuthoritative = false;

            $dimensionCollection = DB::connection('sqlsrv')
            ->table('st_unit_measurements')
            ->where('application_id', $applicationId)
            ->orderBy('unit_no')
            ->get()
            ->map(function ($record, $index) {
                $measurement = $record->measurement ?? null;
                $dimensionNumeric = is_numeric($measurement) ? (float) $measurement : null;

                return [
                    'sn' => $index + 1,
                    'description' => $record->unit_no ?? null,
                    'dimension' => $dimensionNumeric ?? $measurement,
                    'dimension_numeric' => $dimensionNumeric,
                    'dimension_raw' => $measurement,
                    'dimension_display' => $measurement,
                    'count' => $record->count ?? 1,
                    'section' => $record->section ?? null,
                    'section_number' => $record->section_number ?? null,
                    'section_no' => $record->section_no ?? null,
                ];
            });
        }

        // If no ST unit measurements, try site_plan_dimensions
        if ($dimensionCollection->isEmpty()) {
            $sectionsAreAuthoritative = false;

            $dimensionCollection = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('application_id', $applicationId)
                ->orderBy('order')
                ->get()
                ->map(function ($record, $index) {
                    $dimensionValue = $record->dimension ?? ($record->measurement ?? null);
                    $dimensionNumeric = is_numeric($dimensionValue) ? (float) $dimensionValue : null;

                    return [
                        'sn' => isset($record->sn) && is_numeric($record->sn)
                            ? (int) $record->sn
                            : ($record->order ?? ($index + 1)),
                        'description' => $record->description ?? ($record->unit_no ?? null),
                        'dimension' => $dimensionNumeric ?? $dimensionValue,
                        'dimension_numeric' => $dimensionNumeric,
                        'dimension_raw' => $dimensionValue,
                        'dimension_display' => isset($record->dimension_display)
                            ? ($record->dimension_display ?? $dimensionValue)
                            : $dimensionValue,
                        'count' => $record->count ?? 1,
                        'section' => $record->section ?? null,
                        'section_number' => $record->section_number ?? null,
                        'section_no' => $record->section_no ?? null,
                    ];
                });
        }

        // If still empty, try JSI report measurements
        if ($dimensionCollection->isEmpty()) {
            $sectionsAreAuthoritative = false;

            $report = JointSiteInspectionReport::where('application_id', $applicationId)->first();

            if ($report) {
                $measurementEntries = $this->parseExistingMeasurementEntries($report->existing_site_measurement_entries ?? null);

                if ($measurementEntries->isNotEmpty()) {
                    $dimensionCollection = $measurementEntries->map(function (array $entry, int $index) {
                        $dimensionNumeric = $entry['dimension_numeric'] ?? null;
                        $dimensionRaw = $entry['dimension_raw'] ?? ($entry['dimension'] ?? null);

                        if ($dimensionNumeric === null && isset($entry['dimension']) && is_numeric($entry['dimension'])) {
                            $dimensionNumeric = (float) $entry['dimension'];
                        }

                        return [
                            'sn' => $entry['sn'] ?? ($index + 1),
                            'description' => $entry['description'] ?? null,
                            'dimension' => $dimensionNumeric ?? ($entry['dimension'] ?? null),
                            'dimension_numeric' => $dimensionNumeric,
                            'dimension_raw' => $dimensionRaw,
                            'dimension_display' => $entry['dimension_display'] ?? ($dimensionRaw ?? ($entry['dimension'] ?? null)),
                            'count' => $entry['count'] ?? 1,
                            'section' => $entry['section'] ?? null,
                            'section_number' => $entry['section'] ?? null,
                            'section_no' => $entry['section'] ?? null,
                        ];
                    });
                }
            }
        }

        // Only the legacy sources (which carry no section of their own) may be
        // back-filled from the buyer list. Buyer-driven rows already hold the right
        // section, and looking it up by unit_no would clobber it: ST unit numbers
        // repeat across sections (U1 exists once per section), so a unit-keyed map
        // resolves every copy to whichever buyer row happened to be read last.
        $sectionMap = $sectionsAreAuthoritative ? [] : $this->getSectionNumberMapping($applicationId);

        return $this->applySectionNumbersToDimensions($dimensionCollection, $sectionMap)
            ->map(function ($item, $index) {
                $entry = (array) $item;

                $dimensionRaw = $entry['dimension_raw']
                    ?? $entry['dimension_display']
                    ?? $entry['dimension']
                    ?? $entry['measurement']
                    ?? null;

                $dimensionNumeric = $entry['dimension_numeric'] ?? null;
                if ($dimensionNumeric === null && isset($entry['dimension']) && is_numeric($entry['dimension'])) {
                    $dimensionNumeric = (float) $entry['dimension'];
                }

                $countRaw = $entry['count'] ?? 1;
                $count = 1;
                if (is_numeric($countRaw)) {
                    $count = (int) $countRaw;
                } elseif (is_string($countRaw) && trim($countRaw) !== '') {
                    $count = trim($countRaw);
                }

                $section = $entry['section']
                    ?? ($entry['section_number'] ?? ($entry['section_no'] ?? null));

                return [
                    'sn' => isset($entry['sn']) && is_numeric($entry['sn'])
                        ? (int) $entry['sn']
                        : ($index + 1),
                    'description' => $entry['description'] ?? ($entry['unit_no'] ?? null),
                    'dimension' => $dimensionNumeric ?? ($entry['dimension'] ?? null),
                    'dimension_numeric' => $dimensionNumeric,
                    'dimension_raw' => $dimensionRaw,
                    'dimension_display' => $entry['dimension_display'] ?? $dimensionRaw,
                    'count' => $count,
                    'section' => $section,
                    'section_number' => $section,
                ];
            })
            ->filter(function ($entry) {
                return !empty($entry['description'])
                    || !empty($entry['dimension_display'])
                    || !empty($entry['dimension']);
            })
            ->values();
    }

    protected function assembleUtilityDataset(int $applicationId)
    {
        $utilities = collect();

        $report = JointSiteInspectionReport::where('application_id', $applicationId)->first();

        if ($report) {
            $measurementEntries = $this->parseExistingMeasurementEntries($report->existing_site_measurement_entries ?? null);

            if ($measurementEntries->isNotEmpty()) {
                $utilities = $measurementEntries->map(function (array $entry, int $index) use ($applicationId) {
                    $dimensionNumeric = $entry['dimension_numeric'] ?? null;
                    $dimensionRaw = $entry['dimension_raw'] ?? ($entry['dimension'] ?? null);
                    $dimensionValue = $dimensionNumeric ?? $dimensionRaw;
                    $dimensionDisplay = $entry['dimension_display'] ?? ($dimensionRaw ?? ($dimensionNumeric !== null ? (string) $dimensionNumeric : null));

                    $countRaw = $entry['count'] ?? 1;
                    $countValue = 1;
                    if (is_numeric($countRaw)) {
                        $countValue = (int) $countRaw;
                    } elseif (is_string($countRaw) && trim($countRaw) !== '') {
                        $countValue = trim($countRaw);
                    }

                    return [
                        'id' => $entry['id'] ?? null,
                        'application_id' => $applicationId,
                        'sn' => $entry['sn'] ?? ($index + 1),
                        'utility_type' => $entry['description'] ?? null,
                        'dimension' => $dimensionValue,
                        'dimension_display' => $dimensionDisplay,
                        'dimension_raw' => $dimensionRaw,
                        'count' => $countValue,
                        'block' => $entry['block'] ?? null,
                        'section' => $entry['section'] ?? null,
                        'order' => $entry['sn'] ?? ($index + 1),
                    ];
                })->filter(function ($item) {
                    return !empty($item['utility_type']);
                })->values();
            }

            if ($utilities->isEmpty()) {
                $sharedUtilitiesRaw = $report->shared_utilities ?? [];
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

                if (!empty($sharedUtilities)) {
                    $utilities = collect($sharedUtilities)->map(function ($utility, int $index) use ($applicationId) {
                        if (is_array($utility)) {
                            $dimensionValue = $utility['dimension'] ?? ($utility['size'] ?? null);

                            return [
                                'id' => $utility['id'] ?? null,
                                'application_id' => $applicationId,
                                'sn' => $index + 1,
                                'utility_type' => $utility['utility_type']
                                    ?? $utility['name']
                                    ?? $utility['label']
                                    ?? null,
                                'dimension' => $dimensionValue,
                                'dimension_display' => $utility['dimension_display'] ?? ($dimensionValue ?? null),
                                'count' => $utility['count'] ?? 1,
                                'block' => $utility['block'] ?? ($utility['block_label'] ?? null),
                                'section' => $utility['section'] ?? ($utility['section_label'] ?? null),
                                'order' => $index + 1,
                            ];
                        }

                        if (!empty($utility)) {
                            return [
                                'id' => null,
                                'application_id' => $applicationId,
                                'sn' => $index + 1,
                                'utility_type' => $utility,
                                'dimension' => null,
                                'dimension_display' => null,
                                'count' => 1,
                                'block' => null,
                                'section' => null,
                                'order' => $index + 1,
                            ];
                        }

                        return null;
                    })->filter()->values();
                }
            }
        }

        if ($utilities->isEmpty()) {
            $utilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $applicationId)
                ->orderBy('order')
                ->get()
                ->map(function ($utility, $index) use ($applicationId) {
                    $dimensionValue = $utility->dimension ?? null;

                    return [
                        'id' => $utility->id ?? null,
                        'application_id' => $applicationId,
                        'sn' => isset($utility->sn) && is_numeric($utility->sn)
                            ? (int) $utility->sn
                            : ($utility->order ?? ($index + 1)),
                        'utility_type' => $utility->utility_type ?? null,
                        'dimension' => $dimensionValue,
                        'dimension_display' => property_exists($utility, 'dimension_display')
                            ? ($utility->dimension_display ?? $dimensionValue)
                            : $dimensionValue,
                        'count' => $utility->count ?? 1,
                        'block' => $utility->block ?? null,
                        'section' => $utility->section ?? null,
                        'order' => $utility->order ?? ($index + 1),
                    ];
                })
                ->filter(function ($item) {
                    return !empty($item['utility_type']);
                })
                ->values();
        }

        return $utilities->map(function ($item, $index) {
            $entry = (array) $item;

            $countRaw = $entry['count'] ?? 1;
            $count = 1;
            if (is_numeric($countRaw)) {
                $count = (int) $countRaw;
            } elseif (is_string($countRaw) && trim($countRaw) !== '') {
                $count = trim($countRaw);
            }

            return [
                'id' => $entry['id'] ?? null,
                'application_id' => $entry['application_id'] ?? null,
                // Always a display counter, never the stored sn. Dropping the
                // conversion sentinels leaves gaps in the original numbering
                // (4, 5 instead of 1, 2), and this is the printed SN column.
                'sn' => $index + 1,
                'utility_type' => $entry['utility_type'] ?? ($entry['description'] ?? null),
                'dimension' => $entry['dimension'] ?? ($entry['dimension_numeric'] ?? null),
                'dimension_display' => $entry['dimension_display'] ?? ($entry['dimension_raw'] ?? ($entry['dimension'] ?? null)),
                'count' => $count,
                'block' => $entry['block'] ?? null,
                'section' => $entry['section'] ?? null,
                'order' => $entry['order'] ?? ($index + 1),
            ];
        })->values();
    }

    /**
     * Persist Joint Site Inspection report details used for approval templates.
     */
    public function storeJointSiteInspectionReport(Request $request)
    {
        // Check the action type to route to appropriate method
        $action = $request->input('action', 'save');

        switch ($action) {
            case 'generate':
                return $this->generateJointSiteInspectionReport($request);
            case 'submit':
                return $this->submitJointSiteInspectionReport($request);
            case 'save':
            default:
                // Continue with save logic below
                break;
        }

        $validated = $request->validate([
            'application_id' => 'nullable|integer',
            'sub_application_id' => 'nullable|integer',
            'record_id' => 'nullable|integer',
            'file_number' => 'nullable|string|max:100',
            'inspection_date' => 'required|date',
            'lkn_number' => 'nullable|string|max:255',
            'applicant_name' => 'nullable|string|max:255',
            'applicant_address' => 'nullable|string|max:500',
            'applicant_address_components' => 'nullable|array',
            'applicant_address_components.plot_number' => 'nullable|string|max:255',
            'applicant_address_components.street' => 'nullable|string|max:255',
            'applicant_address_components.street_other' => 'nullable|string|max:255',
            'applicant_address_components.district' => 'nullable|string|max:255',
            'applicant_address_components.district_other' => 'nullable|string|max:255',
            'applicant_address_components.lga' => 'nullable|string|max:255',
            'applicant_address_components.state' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'location_components' => 'nullable|array',
            'location_components.plot_number' => 'nullable|string|max:255',
            'location_components.street' => 'nullable|string|max:255',
            'location_components.street_other' => 'nullable|string|max:255',
            'location_components.district' => 'nullable|string|max:255',
            'location_components.district_other' => 'nullable|string|max:255',
            'location_components.lga' => 'nullable|string|max:255',
            'location_components.state' => 'nullable|string|max:255',
            'plot_number' => 'nullable|string|max:255',
            'scheme_number' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'is_conversion_file' => 'nullable|boolean',
            'purpose' => 'required_if:source,Conversion Applications|nullable|string|max:50',
            'private_layout_number' => 'required_if:purpose,Private Layout|nullable|string|max:100',
            'conformity' => 'required_if:source,Conversion Applications|nullable|boolean',
            'accessibility' => 'required_if:source,Conversion Applications|nullable|boolean',
            'existing_road_reservation' => 'nullable|string|max:255',
            'recommended_road_reservation' => 'nullable|string|max:255',
            'observed_road_category' => 'nullable|string|max:255',
            'recommended_comment_status' => 'nullable|string|max:100',
            'how_many_meters' => 'nullable|string|max:100',
            'road_category' => 'nullable|string|max:255',
            'adequate_size_requirement' => 'required_if:source,Conversion Applications|nullable|boolean',
            'land_traversing_utility' => 'required_if:source,Conversion Applications|nullable|boolean',
            'existing_site_length' => 'nullable|string',
            'existing_site_width' => 'nullable|string',
            'existing_site_area' => 'nullable|numeric|min:0',
            'recommended_site_length' => 'nullable|string',
            'recommended_site_width' => 'nullable|string',
            'recommended_site_area' => 'nullable|numeric|min:0',
            'inspection_officer_id' => 'nullable|integer',
            'boundary_description' => 'nullable|string',
            'boundary_segments' => 'nullable|array',
            'boundary_segments.north' => 'nullable|string|max:1000',
            'boundary_segments.east' => 'nullable|string|max:1000',
            'boundary_segments.south' => 'nullable|string|max:1000',
            'boundary_segments.west' => 'nullable|string|max:1000',
            'unit_number' => 'nullable|string|max:255',
            'sections_count' => 'nullable|string|max:255',
            'unit_dimension' => 'nullable|string|max:255',
            'road_reservation' => 'nullable|string|max:255',
            'prevailing_land_use' => 'nullable|string|max:255',
            'applied_land_use' => 'nullable|string|max:255',
            'shared_utilities' => 'nullable|array',
            'shared_utilities.*' => 'nullable|string|max:255',
            'compliance_status' => 'nullable|string|in:obtainable,not_obtainable',
            'additional_observations' => 'nullable|string',
            'inspection_officer' => 'nullable|string|max:255',
            'existing_site_measurement_summary' => 'nullable|string',
            'existing_site_measurement_entries' => 'nullable|array',
            'existing_site_measurement_entries.*.description' => 'nullable|string|max:255',
            'existing_site_measurement_entries.*.measurement_dimension' => 'nullable|string',
            'existing_site_measurement_entries.*.dimension' => 'nullable|string',
            'existing_site_measurement_entries.*.count' => 'nullable|numeric|min:0',
            'existing_site_measurement_entries.*.sn' => 'nullable|integer|min:1',
        ]);

        $applicationId = isset($validated['application_id']) ? (int) $validated['application_id'] : null;
        if ($applicationId !== null && $applicationId <= 0) {
            $applicationId = null;
        }

        $subApplicationId = isset($validated['sub_application_id']) ? (int) $validated['sub_application_id'] : null;
        if ($subApplicationId !== null && $subApplicationId <= 0) {
            $subApplicationId = null;
        }

        $subApplication = null;

        if ($subApplicationId) {
            $subApplication = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if (!$subApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-application not found',
                ], 404);
            }

            $parentApplicationId = $subApplication->main_application_id
                ?? ($subApplication->application_id ?? null);

            if (!$applicationId && $parentApplicationId) {
                $applicationId = (int) $parentApplicationId;
            }

            if ($applicationId !== null && $applicationId <= 0 && $parentApplicationId) {
                $applicationId = (int) $parentApplicationId;
            }
        }

        // For sub-applications, we can create the report even without a parent application
        $application = null;

        if ($applicationId) {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $applicationId)
                ->first();

            if (!$application && $subApplication) {
                $fallbackParentId = $subApplication->main_application_id
                    ?? ($subApplication->application_id ?? null);

                if ($fallbackParentId) {
                    $application = DB::connection('sqlsrv')
                        ->table('mother_applications')
                        ->where('id', $fallbackParentId)
                        ->first();

                    if ($application) {
                        $applicationId = (int) $fallbackParentId;
                    }
                }
            }
        }

        // If we have a sub-application but no parent application, allow saving with null application_id
        // For Conversion Applications, allow saving with just a file_number (no application_id needed)
        $isConversion = $request->input('source') === 'Conversion Applications'
            || $request->boolean('is_conversion_file');
        $hasFileNumber = !empty($validated['file_number']);

        if (!$application && !$subApplication && !($isConversion && $hasFileNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        // Handle existing record updates if record_id is provided
        $recordId = $validated['record_id'] ?? null;
        $report = null;

        if ($recordId) {
            // Update existing record
            $report = JointSiteInspectionReport::find($recordId);

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joint Site Inspection Report not found for update',
                ], 404);
            }

            // Verify the record belongs to the correct application
            if ($subApplicationId && (int) $report->sub_application_id !== $subApplicationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record does not belong to the specified application',
                ], 403);
            }

            if (!$subApplicationId && $applicationId && (int) $report->application_id !== $applicationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record does not belong to the specified application',
                ], 403);
            }
        } else {
            // Create or find existing report by application/sub-application ID (or file_number for Conversion)
            $reportKey = [];
            if ($applicationId !== null) {
                $reportKey['application_id'] = $applicationId;
            }
            if ($subApplicationId !== null) {
                $reportKey['sub_application_id'] = $subApplicationId;
            }
            // For Conversion files without an application_id, key by file_number + source
            if (empty($reportKey) && $isConversion && $hasFileNumber) {
                $reportKey['file_number'] = $validated['file_number'];
                $reportKey['source'] = 'Conversion Applications';
            }

            $report = JointSiteInspectionReport::firstOrNew($reportKey);
        }

        // Set the application IDs mutually exclusively
        if ($subApplicationId) {
            // For sub-applications: only set sub_application_id, application_id should be NULL
            $report->application_id = null;
            $report->sub_application_id = $subApplicationId;
        } else {
            // For primary applications: only set application_id, sub_application_id should be NULL
            $report->application_id = $applicationId;
            $report->sub_application_id = null;
        }

        $report->inspection_date = Carbon::parse($validated['inspection_date'])->format('Y-m-d');
        $report->lkn_number = $validated['lkn_number'] ?? null;
        $report->applicant_name = $validated['applicant_name'] ?? null;
        $report->file_number = $validated['file_number'] ?? $report->file_number;

        $locationComponents = $this->normalizeAddressComponents($request->input('location_components'));
        $applicantAddressComponents = $this->normalizeAddressComponents($request->input('applicant_address_components'));

        $locationString = $this->compileAddressString($locationComponents, $validated['location'] ?? null);
        $applicantAddressString = $this->compileAddressString($applicantAddressComponents, $validated['applicant_address'] ?? null);

        $report->location_components = !empty($locationComponents) ? $locationComponents : null;
        $report->applicant_address_components = !empty($applicantAddressComponents) ? $applicantAddressComponents : null;
        $report->location = $locationString;
        $report->applicant_address = $applicantAddressString;
        $report->plot_number = $validated['plot_number'] ?? ($locationComponents['plot_number'] ?? null);
        $report->scheme_number = $validated['scheme_number'] ?? null;
        $report->available_on_ground = $request->boolean('available_on_ground');

        $report->source = $validated['source'] ?? ($request->boolean('is_conversion_file') ? 'Conversion Applications' : ($report->source ?? 'ST One Stop Shop'));
        $isConversionContext = $report->source === 'Conversion Applications' || $request->boolean('is_conversion_file');

        $report->purpose = $validated['purpose'] ?? null;
        $report->private_layout_number = $validated['private_layout_number'] ?? null;

        $toNullableBool = function ($key) use ($request) {
            if (!$request->has($key)) {
                return null;
            }
            return $request->boolean($key);
        };

        $report->conformity = $toNullableBool('conformity');
        $report->accessibility = $toNullableBool('accessibility');
        $report->adequate_size_requirement = $toNullableBool('adequate_size_requirement');
        $report->land_traversing_utility = $toNullableBool('land_traversing_utility');

        $report->existing_road_reservation = $validated['existing_road_reservation'] ?? null;
        $report->recommended_road_reservation = $validated['recommended_road_reservation'] ?? null;
        $report->observed_road_category = $validated['observed_road_category']
            ?? ($validated['road_category'] ?? null);
        $report->recommended_comment_status = $validated['recommended_comment_status'] ?? null;
        $report->how_many_meters = $validated['how_many_meters'] ?? null;

        if ($report->observed_road_category && !$report->existing_road_reservation) {
            $report->existing_road_reservation = $report->observed_road_category;
        }

        $rawUnitNumber = $validated['unit_number'] ?? null;
        $rawSectionsCount = $validated['sections_count'] ?? null;

        $normalizedUnitNumber = null;
        if ($rawUnitNumber !== null && $rawUnitNumber !== '') {
            $trimmedUnit = trim((string) $rawUnitNumber);
            $normalizedUnitNumber = $trimmedUnit === '' ? null : $trimmedUnit;
        }

        $normalizedSectionsCount = null;
        if ($rawSectionsCount !== null) {
            $normalizedSectionsCount = trim((string) $rawSectionsCount);
            if ($normalizedSectionsCount === '') {
                $normalizedSectionsCount = null;
            }
        }

        $report->unit_number = $normalizedUnitNumber;
        $report->sections_count = $normalizedSectionsCount;

        $unitNumberForSubSync = $normalizedUnitNumber;
        $unitDimensionRaw = $request->input('unit_dimension');
        if (is_string($unitDimensionRaw)) {
            $unitDimensionRaw = trim($unitDimensionRaw);
        }
        $report->unit_dimension = ($unitDimensionRaw === '' || $unitDimensionRaw === null)
            ? null
            : (is_string($unitDimensionRaw) ? $unitDimensionRaw : (string) $unitDimensionRaw);

        $boundarySegmentsInput = $request->input('boundary_segments', null);
        $preparedBoundarySegments = JointSiteInspectionReport::prepareBoundarySegments(
            $boundarySegmentsInput,
            $validated['boundary_description'] ?? null
        );

        $report->boundary_description = JointSiteInspectionReport::compileBoundaryDescription(
            $preparedBoundarySegments,
            $validated['boundary_description'] ?? null
        );
        $report->road_reservation = $validated['road_reservation'] ?? null;
        $report->prevailing_land_use = $validated['prevailing_land_use'] ?? null;
        $report->applied_land_use = $validated['applied_land_use'] ?? null;

        $sharedUtilities = $request->input('shared_utilities', []);
        if (is_array($sharedUtilities)) {
            $sharedUtilities = array_values(array_filter($sharedUtilities, function ($value) {
                return !is_null($value) && $value !== '';
            }));
        } else {
            $sharedUtilities = [];
        }

        $report->shared_utilities = $sharedUtilities;
        $report->compliance_status = $validated['compliance_status'] ?? null;
        $report->has_additional_observations = $request->boolean('has_additional_observations');
        $report->additional_observations = $validated['additional_observations'] ?? null;

        $inspectionOfficerId = $validated['inspection_officer_id'] ?? null;
        $inspectionOfficerLabel = $validated['inspection_officer'] ?? null;
        if ($inspectionOfficerId && $inspectionOfficerLabel) {
            $report->inspection_officer = trim($inspectionOfficerLabel) . ' [ID: ' . $inspectionOfficerId . ']';
        } elseif ($inspectionOfficerLabel) {
            $report->inspection_officer = $inspectionOfficerLabel;
        } else {
            $report->inspection_officer = null;
        }

        $rawMeasurementEntries = $request->input('existing_site_measurement_entries', []);
        if (!is_array($rawMeasurementEntries)) {
            $rawMeasurementEntries = [];
        }

        $processedMeasurementEntries = collect($rawMeasurementEntries)->map(function ($entry, $index) {
            if (!is_array($entry)) {
                return null;
            }

            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
            $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
            $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
            $countInput = isset($entry['count']) ? trim((string) $entry['count']) : '';
            $count = $countInput === '' ? '1' : $countInput;

            if ($description === '' && $dimension === '' && $measurementDimension === '') {
                return null;
            }

            return [
                'sn' => isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1),
                'description' => $description === '' ? null : $description,
                'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                'dimension' => $dimension === '' ? null : $dimension,
                'count' => $count,
            ];
        })->filter()->values();

        $report->existing_site_measurement_entries = $processedMeasurementEntries->all();

        $defaultMeasurementSummary = 'No recorded dimensions were submitted for this inspection.';
        if ($processedMeasurementEntries->isEmpty()) {
            $report->existing_site_measurement_summary = $validated['existing_site_measurement_summary'] ?? $defaultMeasurementSummary;
        } else {
            $report->existing_site_measurement_summary = $validated['existing_site_measurement_summary'] ?? null;
        }

        $report->existing_site_length = $validated['existing_site_length'] ?? null;
        $report->existing_site_width = $validated['existing_site_width'] ?? null;
        $report->existing_site_area = $validated['existing_site_area'] ?? null;
        $report->recommended_site_length = $validated['recommended_site_length'] ?? null;
        $report->recommended_site_width = $validated['recommended_site_width'] ?? null;
        $report->recommended_site_area = $validated['recommended_site_area'] ?? null;

        // Set workflow states - new reports start as saved but not generated/submitted
        if (!$report->exists) {
            $report->is_generated = false;
            $report->is_submitted = false;
        }

        if (Auth::check()) {
            if (!$report->exists || empty($report->created_by)) {
                $report->created_by = Auth::id();
            }
            $report->updated_by = Auth::id();
        }

        $report->save();

        if ($subApplicationId && $unitNumberForSubSync !== null) {
            $unitNumberForSub = trim((string) $unitNumberForSubSync);

            if ($unitNumberForSub !== '') {
                $subApplicationUpdate = [
                    'unit_number' => $unitNumberForSub,
                ];

                static $subApplicationsHasUpdatedAt = null;
                static $subApplicationsHasUpdatedBy = null;

                if ($subApplicationsHasUpdatedAt === null) {
                    $subApplicationsHasUpdatedAt = \Schema::connection('sqlsrv')->hasColumn('subapplications', 'updated_at');
                }

                if ($subApplicationsHasUpdatedAt) {
                    $subApplicationUpdate['updated_at'] = now();
                }

                if ($subApplicationsHasUpdatedBy === null) {
                    $subApplicationsHasUpdatedBy = \Schema::connection('sqlsrv')->hasColumn('subapplications', 'updated_by');
                }

                if ($subApplicationsHasUpdatedBy && Auth::check()) {
                    $subApplicationUpdate['updated_by'] = Auth::id();
                }

                try {
                    DB::connection('sqlsrv')
                        ->table('subapplications')
                        ->where('id', $subApplicationId)
                        ->update($subApplicationUpdate);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to sync unit number to subapplications from JSI report', [
                        'sub_application_id' => $subApplicationId,
                        'unit_number' => $unitNumberForSub,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Sync data to shared_utilities table
        $this->syncUtilitiesToSharedUtilitiesTable($report, $processedMeasurementEntries, $applicationId, $subApplicationId);

        $viewUrl = $subApplicationId
            ? route('sub-actions.planning-recommendation.joint-inspection.show', $subApplicationId)
            : route('planning-recommendation.joint-inspection.show', $report->application_id);

        return response()->json([
            'success' => true,
            'message' => $report->wasRecentlyCreated
                ? 'Joint site inspection report saved successfully.'
                : 'Joint site inspection report updated successfully.',
            'view_url' => $viewUrl,
            'report_id' => $report->id,
            'record_id' => $report->id, // Alias for compatibility
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'created' => $report->wasRecentlyCreated,
        ]);
    }

    /**
     * Sync utilities and dimensions from JSI report to shared_utilities table
     */
    private function syncUtilitiesToSharedUtilitiesTable($report, $measurementEntries, $applicationId, $subApplicationId)
    {
        try {
            // Only sync if we have measurement entries with both description and dimension
            if ($measurementEntries->isEmpty()) {
                return;
            }

            // Clean up any accidental meta entries from previous saves for this application
            try {
                DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('application_id', $applicationId)
                    ->where('sub_application_id', $subApplicationId)
                    ->whereRaw("LEFT(utility_type, 2) = '__'")
                    ->delete();
            } catch (\Exception $e) {
                \Log::warning('Failed to clean up legacy meta entries from shared_utilities', [
                    'error' => $e->getMessage(),
                    'application_id' => $applicationId
                ]);
            }

            foreach ($measurementEntries as $index => $entry) {
                $utilityType = $entry['description'] ?? null;
                $dimension = $entry['dimension'] ?? null;
                $measurementDimension = $entry['measurement_dimension'] ?? null;

                // Skip entries without utility type or meta entries starting with __
                if (empty($utilityType) || str_starts_with(trim($utilityType), '__')) {
                    continue;
                }

                // Check if record already exists
                $existingUtility = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('application_id', $applicationId)
                    ->where('sub_application_id', $subApplicationId)
                    ->where('utility_type', $utilityType)
                    ->first();

                $countValue = null;
                $rawCountValue = $entry['count'] ?? null;
                if ($rawCountValue !== null && $rawCountValue !== '') {
                    $rawCount = is_string($rawCountValue) ? trim($rawCountValue) : $rawCountValue;
                    if ($rawCount !== '' && $rawCount !== null && is_numeric($rawCount)) {
                        $countValue = (int) $rawCount;
                    }
                }

                $data = [
                    'application_id' => $applicationId,
                    'sub_application_id' => $subApplicationId,
                    'utility_type' => $utilityType,
                    'dimension' => $dimension,
                    'measurement_dimension' => $measurementDimension,
                    'count' => $countValue ?? 1,
                    'order' => $index + 1,
                    'updated_at' => now(),
                ];

                if (Auth::check()) {
                    $data['updated_by'] = Auth::id();
                }

                if ($existingUtility) {
                    // Update existing record
                    DB::connection('sqlsrv')
                        ->table('shared_utilities')
                        ->where('id', $existingUtility->id)
                        ->update($data);
                } else {
                    // Create new record
                    $data['created_at'] = now();
                    if (Auth::check()) {
                        $data['created_by'] = Auth::id();
                    }

                    DB::connection('sqlsrv')
                        ->table('shared_utilities')
                        ->insert($data);
                }
            }

            \Log::info('Successfully synced utilities to shared_utilities table', [
                'application_id' => $applicationId,
                'sub_application_id' => $subApplicationId,
                'entries_count' => $measurementEntries->count()
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the whole operation
            \Log::error('Failed to sync utilities to shared_utilities table', [
                'error' => $e->getMessage(),
                'application_id' => $applicationId,
                'sub_application_id' => $subApplicationId
            ]);
        }
    }

    /**
     * Mark JSI report as generated (ready for viewing/printing)
     */
    public function generateJointSiteInspectionReport(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'nullable|integer',
            'sub_application_id' => 'nullable|integer',
        ]);

        $applicationId = $validated['application_id'] ?? null;
        $subApplicationId = $validated['sub_application_id'] ?? null;

        $query = JointSiteInspectionReport::query();

        if ($subApplicationId) {
            $query->where('sub_application_id', $subApplicationId);
        } elseif ($applicationId) {
            $query->where('application_id', $applicationId);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Application ID or Sub-application ID is required',
            ], 400);
        }

        $report = $query->first();

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found. Please save the report first.',
            ], 404);
        }

        if ($report->is_submitted) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate - report has already been submitted.',
            ], 422);
        }

        if ($report->is_generated) {
            return response()->json([
                'success' => false,
                'message' => 'Report has already been generated.',
            ], 422);
        }

        $report->is_generated = true;
        $report->generated_at = now();
        $report->generated_by = Auth::id();
        $report->updated_by = Auth::id();
        $report->save();

        // Generate report URL if needed
        $reportUrl = $subApplicationId
            ? route('sub-actions.planning-recommendation.joint-inspection.show', $subApplicationId)
            : route('planning-recommendation.joint-inspection.show', $report->application_id);

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report generated successfully. You can now submit it.',
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'report_url' => $reportUrl,
        ]);
    }

    /**
     * Submit JSI report for final approval
     */
    public function submitJointSiteInspectionReport(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'nullable|integer',
            'sub_application_id' => 'nullable|integer',
        ]);

        $applicationId = $validated['application_id'] ?? null;
        $subApplicationId = $validated['sub_application_id'] ?? null;

        $query = JointSiteInspectionReport::query();

        if ($subApplicationId) {
            $query->where('sub_application_id', $subApplicationId);
        } elseif ($applicationId) {
            $query->where('application_id', $applicationId);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Application ID or Sub-application ID is required',
            ], 400);
        }

        $report = $query->first();

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found. Please save the report first.',
            ], 404);
        }

        if (!$report->is_generated) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit - report must be generated first.',
            ], 422);
        }

        if ($report->is_submitted) {
            return response()->json([
                'success' => false,
                'message' => 'Report has already been submitted.',
            ], 422);
        }

        $report->is_submitted = true;
        $report->submitted_at = now();
        $report->submitted_by = Auth::id();
        $report->updated_by = Auth::id();
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report submitted successfully.',
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'reload' => true, // Signal frontend to reload
        ]);
    }

    /**
     * Render the Joint Site Inspection Report template for printing.
     */
    public function showJointSiteInspectionReport(Request $request, $applicationId)
    {
        $report = JointSiteInspectionReport::where('application_id', $applicationId)->first();

        // Conversion fallback: some print links pass JSI report id directly.
        if (!$report) {
            $report = JointSiteInspectionReport::where('id', $applicationId)
                ->whereNull('sub_application_id')
                ->where(function ($q) {
                    $q->where('source', 'Conversion Applications')
                        ->orWhere('file_number', 'LIKE', 'CON-%')
                        ->orWhere('file_number', 'LIKE', 'RES-%');
                })
                ->first();
        }

        if (!$report) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Joint site inspection report not found'], 404);
            }
            abort(404, 'Joint site inspection report not found');
        }

        $resolvedApplicationId = $report->application_id ?: $applicationId;

        $application = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $resolvedApplicationId)
            ->first();

        if (!$application) {
            // Provide a lightweight fallback object for conversion records without mother_application linkage.
            $application = (object) [
                'id' => $resolvedApplicationId,
                'fileno' => $report->file_number,
                'lkn_number' => $report->lkn_number,
                'property_street_name' => null,
                'property_lga' => null,
                'property_location' => $report->location,
                'property_plot_no' => $report->plot_number,
                'scheme_no' => $report->scheme_number,
                'applicant_type' => null,
                'applicant_title' => null,
                'first_name' => null,
                'surname' => null,
                'rc_number' => null,
                'corporate_name' => $report->applicant_name,
                'multiple_owners_names' => null,
                'applicant_name' => $report->applicant_name,
                'unit_number' => null,
                'land_use' => $report->applied_land_use,
                'shared_areas' => null,
            ];
        }

        $dimensions = DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('application_id', $resolvedApplicationId)
            ->orderBy('order')
            ->get();

        $utilities = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $resolvedApplicationId)
            ->orderBy('order')
            ->get();

        // Get unit measurements from buyer_list joined with st_unit_measurements table
        $unitMeasurements = DB::connection('sqlsrv')
            ->table('buyer_list as bl')
            ->leftJoin('st_unit_measurements as sum', function ($join) {
                // Join on the buyer FK, not unit_no. ST unit numbers repeat
                // (e.g. "U2", "U MD"), so joining on unit_no fans the rows out
                // and inflates the count (249 buyers -> 313 rows).
                $join->on('bl.id', '=', 'sum.buyer_id')
                    ->on('bl.application_id', '=', 'sum.application_id');
            })
            ->where('bl.application_id', $resolvedApplicationId)
            ->select(
                'bl.unit_no',
                'bl.buyer_name',
                'bl.buyer_title',
                'bl.section_number',
                'sum.measurement as unit_size'
            )
            ->orderBy('bl.unit_no')
            ->get()
            ->map(function ($unit, $index) {
                return (object) [
                    'sn' => $index + 1,
                    'unit_no' => $unit->unit_no,
                    'unit_size' => $unit->unit_size,
                    'measurement' => $unit->unit_size, // For backward compatibility
                    'buyer_name' => $unit->buyer_name,
                    'buyer_title' => $unit->buyer_title,
                    'section_number' => $unit->section_number,
                    'section' => $unit->section_number,
                ];
            });

        $sharedAreasList = [];
        $sharedAreasRaw = $application->shared_areas ?? null;
        if (!empty($sharedAreasRaw)) {
            if (is_string($sharedAreasRaw)) {
                $decoded = json_decode($sharedAreasRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $sharedAreasList = $decoded;
                } elseif (str_contains($sharedAreasRaw, ',')) {
                    $sharedAreasList = array_map('trim', explode(',', $sharedAreasRaw));
                }
            } elseif (is_array($sharedAreasRaw)) {
                $sharedAreasList = $sharedAreasRaw;
            }
        }

        $sharedAreasList = array_values(array_filter(array_unique($sharedAreasList)));

        $reportDimensionEntries = collect($report->existing_site_measurement_entries ?? [])
            ->filter(function ($entry) {
                return is_array($entry);
            })
            ->map(function ($entry, $index) {
                $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
                $countInput = isset($entry['count']) ? trim((string) $entry['count']) : '';
                $count = $countInput === '' ? '1' : $countInput;

                if ($description === '' && $dimension === '' && $measurementDimension === '' && $countInput === '') {
                    return null;
                }

                $sn = isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1);

                return (object) [
                    'sn' => $sn,
                    'description' => $description === '' ? null : $description,
                    'dimension' => $dimension === '' ? null : $dimension,
                    'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                    'count' => $count,
                ];
            })
            ->filter()
            ->sortBy('sn')
            ->values();

        if ($reportDimensionEntries->isNotEmpty()) {
            $dimensions = $reportDimensionEntries;
        }

        if ((is_object($dimensions) && $dimensions instanceof \Illuminate\Support\Collection && $dimensions->isEmpty()) || (is_array($dimensions) && empty($dimensions))) {
            $stMeasurements = DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->where('application_id', $applicationId)
                ->orderBy('unit_no')
                ->get();

            if ($stMeasurements->isNotEmpty()) {
                $dimensions = $stMeasurements->map(function ($record, $index) {
                    return (object) [
                        'sn' => $index + 1,
                        'description' => $record->unit_no ?? null,
                        'dimension' => $record->measurement ?? null,
                        'measurement_dimension' => $record->measurement ?? null,
                        'count' => $record->count ?? '1',
                    ];
                });
            }
        }

        // Return JSON data for AJAX requests
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'data' => $report,
                'dimensions' => $dimensions,
                'utilities' => $utilities,
                'unitMeasurements' => $unitMeasurements,
                'sharedAreasList' => $sharedAreasList,
            ]);
        }

        $printMode = $request->boolean('print');

        if ($printMode) {
            return view('actions.JOINT-SITE-INSPECTION-REPORT', [
                'application' => $application,
                'report' => $report,
                'dimensions' => $dimensions,
                'utilities' => $utilities,
                'unitMeasurements' => $unitMeasurements,
                'sharedAreasList' => $sharedAreasList,
                'printMode' => $printMode,
            ]);
        }

        return view('actions.JOINT-SITE-INSPECTION-REPORT', [
            'application' => $application,
            'report' => $report,
            'dimensions' => $dimensions,
            'utilities' => $utilities,
            'unitMeasurements' => $unitMeasurements,
            'sharedAreasList' => $sharedAreasList,
            'printMode' => $printMode,
        ]);
    }

    public function showSubJointSiteInspectionReport(Request $request, $subApplicationId)
    {
        $subApplication = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->first();

        if (!$subApplication) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Sub-application not found'], 404);
            }
            abort(404, 'Sub-application not found');
        }

        $parentApplication = null;
        $parentApplicationId = null;

        if (!empty($subApplication->main_application_id)) {
            $parentApplication = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $subApplication->main_application_id)
                ->first();
            $parentApplicationId = $subApplication->main_application_id;
        }

        // Look for report - try parent application ID first, then fallback to sub-application ID, then null
        $report = null;
        if ($parentApplication) {
            $report = JointSiteInspectionReport::where('application_id', $parentApplicationId)
                ->where('sub_application_id', $subApplicationId)
                ->first();
        }

        // If no report found and no parent, try with sub-application ID as application ID
        if (!$report) {
            $report = JointSiteInspectionReport::where('application_id', $subApplicationId)
                ->where('sub_application_id', $subApplicationId)
                ->first();
        }

        // If still no report, try with null application_id
        if (!$report) {
            $report = JointSiteInspectionReport::whereNull('application_id')
                ->where('sub_application_id', $subApplicationId)
                ->first();
        }

        if (!$report) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Joint site inspection report not found'], 404);
            }
            abort(404, 'Joint site inspection report not found');
        }

        $dimensions = DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('sub_application_id', $subApplicationId)
            ->orderBy('order')
            ->get();

        $utilities = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->orderBy('order')
            ->get();

        // Get unit measurements from buyer_list joined with st_unit_measurements for sub-applications
        $unitMeasurements = collect();
        if ($parentApplicationId) {
            $buyerListData = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function ($join) {
                    // Join on the buyer FK, not unit_no (unit numbers repeat and fan out).
                    $join->on('bl.id', '=', 'sum.buyer_id')
                        ->on('bl.application_id', '=', 'sum.application_id');
                })
                ->where('bl.application_id', $parentApplicationId)
                ->where('bl.unit_no', $subApplication->unit_number)
                ->select(
                    'bl.unit_no',
                    'bl.buyer_name',
                    'bl.buyer_title',
                    'bl.section_number',
                    'sum.measurement as unit_size'
                )
                ->get();

            if ($buyerListData->count() > 0) {
                $unitMeasurements = $buyerListData->map(function ($unit, $index) {
                    return (object) [
                        'sn' => $index + 1,
                        'unit_no' => $unit->unit_no,
                        'unit_size' => $unit->unit_size,
                        'measurement' => $unit->unit_size, // For backward compatibility
                        'buyer_name' => $unit->buyer_name,
                        'buyer_title' => $unit->buyer_title,
                        'section_number' => $unit->section_number,
                        'section' => $unit->section_number,
                    ];
                });
            }
        }

        // Fallback to subapplication data if no buyer list data found
        if ($unitMeasurements->isEmpty()) {
            $unitMeasurements = collect([
                (object) [
                    'sn' => 1,
                    'unit_no' => $subApplication->unit_number,
                    'unit_size' => $subApplication->unit_size,
                    'measurement' => $subApplication->unit_size, // For backward compatibility
                    'buyer_name' => null,
                    'buyer_title' => null,
                    'section_number' => $subApplication->section_number ?? null,
                    'section' => $subApplication->section_number ?? null,
                ]
            ]);
        }

        if ($dimensions->isEmpty()) {
            $dimensions = $unitMeasurements->map(function ($unit, $index) {
                return (object) [
                    'sn' => $index + 1,
                    'description' => $unit->unit_no ?? null,
                    'dimension' => $unit->unit_size ?? $unit->measurement ?? null,
                    'count' => $unit->count ?? '1',
                ];
            });
        }

        $sharedAreasList = [];
        $sharedAreasRaw = $subApplication->shared_areas ?? null;
        if (!empty($sharedAreasRaw)) {
            if (is_string($sharedAreasRaw)) {
                $decoded = json_decode($sharedAreasRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $sharedAreasList = $decoded;
                } elseif (str_contains($sharedAreasRaw, ',')) {
                    $sharedAreasList = array_map('trim', explode(',', $sharedAreasRaw));
                }
            } elseif (is_array($sharedAreasRaw)) {
                $sharedAreasList = $sharedAreasRaw;
            }
        }

        $sharedAreasList = array_values(array_filter(array_unique($sharedAreasList)));

        $reportDimensionEntries = collect($report->existing_site_measurement_entries ?? [])
            ->filter(function ($entry) {
                return is_array($entry);
            })
            ->map(function ($entry, $index) {
                $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
                $countInput = isset($entry['count']) ? trim((string) $entry['count']) : '';
                $count = $countInput === '' ? '1' : $countInput;

                if ($description === '' && $dimension === '' && $measurementDimension === '' && $countInput === '') {
                    return null;
                }

                $sn = isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1);

                return (object) [
                    'sn' => $sn,
                    'description' => $description === '' ? null : $description,
                    'dimension' => $dimension === '' ? null : $dimension,
                    'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                    'count' => $count,
                ];
            })
            ->filter()
            ->sortBy('sn')
            ->values();

        if ($reportDimensionEntries->isNotEmpty()) {
            $dimensions = $reportDimensionEntries;
        }

        if ((is_object($dimensions) && $dimensions instanceof \Illuminate\Support\Collection && $dimensions->isEmpty()) || (is_array($dimensions) && empty($dimensions))) {
            $stMeasurements = collect();

            if ($parentApplicationId) {
                $stMeasurements = DB::connection('sqlsrv')
                    ->table('st_unit_measurements')
                    ->where('application_id', $parentApplicationId)
                    ->when($subApplication->unit_number, function ($query, $unitNumber) {
                        return $query->where('unit_no', $unitNumber);
                    })
                    ->orderBy('unit_no')
                    ->get();
            }

            if ($stMeasurements->isNotEmpty()) {
                $dimensions = $stMeasurements->map(function ($record, $index) {
                    return (object) [
                        'sn' => $index + 1,
                        'description' => $record->unit_no ?? null,
                        'dimension' => $record->measurement ?? null,
                        'measurement_dimension' => $record->measurement ?? null,
                        'count' => $record->count ?? '1',
                    ];
                });
            } elseif ($unitMeasurements->isNotEmpty()) {
                $dimensions = $unitMeasurements->map(function ($unit, $index) {
                    $measurementValue = $unit->unit_size ?? $unit->measurement ?? null;

                    return (object) [
                        'sn' => $index + 1,
                        'description' => $unit->unit_no ?? null,
                        'dimension' => $measurementValue,
                        'measurement_dimension' => $measurementValue,
                        'count' => $unit->count ?? '1',
                    ];
                });
            }
        }

        // Only inherit parent application attributes if parent exists
        if ($parentApplication) {
            foreach (['property_house_no', 'property_plot_no', 'property_street_name', 'property_lga', 'property_location', 'land_use', 'fileno'] as $attribute) {
                if ((empty($subApplication->{$attribute}) || is_null($subApplication->{$attribute})) && !empty($parentApplication->{$attribute})) {
                    $subApplication->{$attribute} = $parentApplication->{$attribute};
                }
            }
        }

        // Return JSON data for AJAX requests
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'data' => $report,
                'dimensions' => $dimensions,
                'utilities' => $utilities,
                'unitMeasurements' => $unitMeasurements,
                'sharedAreasList' => $sharedAreasList,
                'parentApplication' => $parentApplication,
            ]);
        }

        $printMode = $request->boolean('print');

        if ($printMode) {
            return view('actions.JOINT-SITE-INSPECTION-REPORT', [
                'application' => $subApplication,
                'report' => $report,
                'dimensions' => $dimensions,
                'utilities' => $utilities,
                'unitMeasurements' => $unitMeasurements,
                'sharedAreasList' => $sharedAreasList,
                'printMode' => $printMode,
                'parentApplication' => $parentApplication,
            ]);
        }

        return view('actions.JOINT-SITE-INSPECTION-REPORT', [
            'application' => $subApplication,
            'report' => $report,
            'dimensions' => $dimensions,
            'utilities' => $utilities,
            'unitMeasurements' => $unitMeasurements,
            'sharedAreasList' => $sharedAreasList,
            'printMode' => $printMode,
            'parentApplication' => $parentApplication,
        ]);
    }

    /**
     * Approve a Joint Site Inspection Report
     */
    public function approveJointSiteInspectionReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'application_id' => 'nullable|integer',
                'sub_application_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $applicationId = $request->input('application_id');
            $subApplicationId = $request->input('sub_application_id');

            // Find the JSI report based on application type
            $query = DB::connection('sqlsrv')->table('joint_site_inspection_reports');

            if ($subApplicationId) {
                $query->where('sub_application_id', $subApplicationId);
            } elseif ($applicationId) {
                $query->where('application_id', $applicationId);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Either application_id or sub_application_id is required'
                ], 400);
            }

            $report = $query->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joint Site Inspection Report not found'
                ], 404);
            }

            // Check if already approved
            if ($report->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joint Site Inspection Report is already approved'
                ], 400);
            }

            // Update the report to mark as approved
            $updateData = [
                'is_approved' => 1,
                'approved_by' => Auth::user()->name ?? 'System',
                'approved_at' => now()->format('Y-m-d H:i:s')
            ];

            $updated = $query->update($updateData);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Joint Site Inspection Report approved successfully',
                    'data' => [
                        'is_approved' => true,
                        'approved_by' => $updateData['approved_by'],
                        'approved_at' => $updateData['approved_at']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve Joint Site Inspection Report'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while approving the report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Joint Site Inspection Report status (is_generated, is_submitted)
     */
    public function updateJointInspectionStatus(Request $request)
    {
        try {
            $applicationId = $request->input('application_id');
            $subApplicationId = $request->input('sub_application_id');
            $isGenerated = $request->input('is_generated');
            $isSubmitted = $request->input('is_submitted');
            $generatedAt = $request->input('generated_at');
            $submittedAt = $request->input('submitted_at');

            if (!$applicationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application ID is required'
                ], 400);
            }

            // Build the query to find the report
            $query = JointSiteInspectionReport::where('application_id', $applicationId);

            if ($subApplicationId) {
                $query->where('sub_application_id', $subApplicationId);
            } else {
                $query->whereNull('sub_application_id');
            }

            $report = $query->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joint Site Inspection Report not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [];

            if ($isGenerated !== null) {
                $updateData['is_generated'] = (bool) $isGenerated;
                if ($generatedAt) {
                    $updateData['generated_at'] = date('Y-m-d H:i:s', strtotime($generatedAt));
                } else {
                    $updateData['generated_at'] = now()->format('Y-m-d H:i:s');
                }
                $updateData['generated_by'] = Auth::user()->name ?? 'System';
            }

            if ($isSubmitted !== null) {
                $updateData['is_submitted'] = (bool) $isSubmitted;
                if ($submittedAt) {
                    $updateData['submitted_at'] = date('Y-m-d H:i:s', strtotime($submittedAt));
                } else {
                    $updateData['submitted_at'] = now()->format('Y-m-d H:i:s');
                }
                $updateData['submitted_by'] = Auth::user()->name ?? 'System';
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No status updates provided'
                ], 400);
            }

            // Update the report
            $updated = $query->update($updateData);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Joint Site Inspection Report status updated successfully',
                    'data' => $updateData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update Joint Site Inspection Report status'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the report status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unit data from subapplications table for JSI modal
     */
    public function getUnitData($subApplicationId)
    {
        try {
            if (!$subApplicationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub Application ID is required'
                ], 400);
            }

            // Fetch unit data from subapplications table
            $unitData = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->select(
                    'id',
                    'main_application_id',
                    'unit_number',
                    'block_number',
                    'unit_type',
                    'unit_size',
                    'first_name',
                    'surname',
                    'applicant_title'
                )
                ->first();

            if (!$unitData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit data not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Unit data retrieved successfully',
                'data' => [
                    'unit_number' => $unitData->unit_number,
                    'block_number' => $unitData->block_number,
                    'unit_type' => $unitData->unit_type,
                    'unit_size' => $unitData->unit_size,
                    'unit_dimension' => $unitData->unit_size,
                    'buyer_name' => trim($unitData->first_name . ' ' . $unitData->surname),
                    'buyer_title' => $unitData->applicant_title,
                    'application_id' => $unitData->main_application_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching unit data: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function parseExistingMeasurementEntries($rawEntries)
    {
        if ($rawEntries === null || $rawEntries === '') {
            return collect();
        }

        if (is_string($rawEntries)) {
            $decoded = json_decode($rawEntries, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawEntries = $decoded;
            } else {
                return collect();
            }
        } elseif ($rawEntries instanceof \Traversable) {
            $rawEntries = iterator_to_array($rawEntries);
        }

        if (!is_array($rawEntries)) {
            return collect();
        }

        return collect($rawEntries)
            ->map(function ($entry, int $index) {
                if (is_object($entry)) {
                    $entry = (array) $entry;
                }

                if (!is_array($entry)) {
                    return null;
                }

                $description = isset($entry['description']) ? trim((string) $entry['description']) : '';

                // Conversion-only meta rows share this JSON with real measurements.
                // They are never units or utilities and must not reach an ST One
                // Stop Shop recommendation letter.
                if (preg_match('/^__.+__$/', $description) === 1) {
                    return null;
                }

                $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                if ($dimension === '' && isset($entry['measurement']) && !is_null($entry['measurement'])) {
                    $dimension = trim((string) $entry['measurement']);
                }

                $measurementDimensionSource = $entry['measurement_dimension']
                    ?? ($entry['measurementDimension'] ?? ($entry['dimension_label'] ?? ($entry['dimensionDisplay'] ?? null)));
                $measurementDimension = $measurementDimensionSource === null
                    ? ''
                    : trim((string) $measurementDimensionSource);

                $count = isset($entry['count']) ? $entry['count'] : null;
                $block = isset($entry['block']) ? trim((string) $entry['block']) : null;
                $section = isset($entry['section']) ? trim((string) $entry['section']) : null;

                if (
                    $description === ''
                    && $dimension === ''
                    && $measurementDimension === ''
                    && ($count === null || $count === '')
                ) {
                    return null;
                }

                $snRaw = $entry['sn'] ?? null;
                $sn = is_numeric($snRaw) ? (int) $snRaw : ($index + 1);

                $countValue = 1;
                if (is_numeric($count)) {
                    $countValue = (int) $count;
                } elseif (is_string($count) && trim($count) !== '') {
                    $countValue = trim($count);
                }

                $dimensionOriginal = $dimension === '' ? null : $dimension;
                $dimensionNumeric = null;

                if ($dimensionOriginal !== null && is_numeric($dimensionOriginal)) {
                    $dimensionNumeric = (float) $dimensionOriginal;
                }

                $dimensionDisplay = $dimensionOriginal;
                if ($dimensionDisplay === null && $dimensionNumeric !== null) {
                    $dimensionDisplay = (string) $dimensionNumeric;
                }

                return [
                    'sn' => $sn,
                    'description' => $description === '' ? null : $description,
                    'dimension' => $dimensionNumeric ?? $dimensionOriginal,
                    'dimension_numeric' => $dimensionNumeric,
                    'dimension_raw' => $dimensionOriginal,
                    'dimension_display' => $dimensionDisplay,
                    'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                    'measurement_dimension_display' => $measurementDimension === '' ? null : $measurementDimension,
                    'count' => $countValue,
                    // No invented block/section. A letter must not state a block
                    // number the inspecting officer never recorded.
                    'block' => $block === null || $block === '' ? null : $block,
                    'section' => $section === null || $section === '' ? null : $section,
                ];
            })
            ->filter()
            ->sortBy('sn')
            ->values();
    }

    protected function getSectionNumberMapping($applicationId): array
    {
        if (empty($applicationId)) {
            return [];
        }

        $records = DB::connection('sqlsrv')
            ->table('buyer_list')
            ->where('application_id', $applicationId)
            ->whereNotNull('section_number')
            ->select('unit_no', 'section_number')
            ->get();

        // A unit number is only a safe key when every buyer row that uses it sits in
        // the same section. ST unit numbers repeat across sections (U1 once per
        // section), and an ambiguous key used to resolve to whichever row was read
        // last, stamping one arbitrary section onto every copy of that unit.
        $candidates = [];

        foreach ($records as $record) {
            $section = $record->section_number;

            foreach ($this->generateUnitKeyVariants($record->unit_no) as $variant) {
                if (!array_key_exists($variant, $candidates)) {
                    $candidates[$variant] = $section;
                    continue;
                }

                if ($candidates[$variant] !== $section) {
                    $candidates[$variant] = null; // ambiguous - never map this key
                }
            }
        }

        return array_filter($candidates, function ($section) {
            return $section !== null && $section !== '';
        });
    }

    private function normalizeAddressComponents($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $allowedKeys = ['plot_number', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'];
        $normalized = [];

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $raw = $value[$key];
            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === '' || $raw === null) {
                continue;
            }

            $normalized[$key] = $raw;
        }

        return $normalized;
    }

    private function compileAddressString(array $components, ?string $fallback = null): ?string
    {
        $street = $components['street'] ?? null;
        if ($street === 'Other') {
            $street = $components['street_other'] ?? null;
        }

        $district = $components['district'] ?? null;
        if ($district === 'Other') {
            $district = $components['district_other'] ?? null;
        }

        $parts = [];

        if (!empty($components['plot_number'])) {
            $parts[] = $components['plot_number'];
        }

        if (!empty($street)) {
            $parts[] = $street;
        }

        if (!empty($district)) {
            $parts[] = $district;
        }

        if (!empty($components['lga'])) {
            $parts[] = $components['lga'] . ' LGA';
        }

        if (!empty($components['state'])) {
            $parts[] = $components['state'] . ' State';
        }

        $compiled = implode(', ', array_filter($parts, function ($value) {
            return $value !== null && $value !== '';
        }));

        if ($compiled !== '') {
            return $compiled;
        }

        if ($fallback === null) {
            return null;
        }

        $normalizedFallback = is_string($fallback) ? trim($fallback) : $fallback;

        return $normalizedFallback === '' ? null : $normalizedFallback;
    }

    protected function generateUnitKeyVariants($value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $variants = [
            $value,
            strtolower($value),
            strtoupper($value),
            preg_replace('/\s+/', '', strtolower($value)),
        ];

        $withoutUnit = trim(preg_replace('/\bunit\b/i', '', $value));
        if ($withoutUnit !== '' && $withoutUnit !== $value) {
            $variants[] = $withoutUnit;
            $variants[] = strtolower($withoutUnit);
            $variants[] = preg_replace('/\s+/', '', strtolower($withoutUnit));
        }

        $numericOnly = preg_replace('/\D+/', '', $value);
        if ($numericOnly !== '') {
            $trimmedNumeric = ltrim($numericOnly, '0');
            $variants[] = $trimmedNumeric !== '' ? $trimmedNumeric : '0';
            $variants[] = $numericOnly;
        }

        return array_values(array_unique(array_filter($variants, function ($variant) {
            return $variant !== '' && $variant !== null;
        })));
    }

    protected function applySectionNumbersToDimensions($dimensions, array $sectionMap)
    {
        $collection = collect($dimensions ?? []);

        if ($collection->isEmpty()) {
            return $collection->map(function ($item, $index) {
                $itemArray = (array) $item;
                if (!isset($itemArray['section']) && isset($itemArray['section_no'])) {
                    $itemArray['section'] = $itemArray['section_no'];
                }
                if (!isset($itemArray['section']) && isset($itemArray['section_number'])) {
                    $itemArray['section'] = $itemArray['section_number'];
                }
                if (!isset($itemArray['sn'])) {
                    $itemArray['sn'] = $itemArray['order'] ?? ($index + 1);
                }
                $itemArray['section_number'] = $itemArray['section'] ?? null;
                return $itemArray;
            });
        }

        return $collection->map(function ($item, $index) use ($sectionMap) {
            $itemArray = (array) $item;
            $candidates = [];

            foreach (['unit_no', 'description', 'unit', 'label'] as $field) {
                if (!empty($itemArray[$field])) {
                    $candidates = array_merge($candidates, $this->generateUnitKeyVariants($itemArray[$field]));
                }
            }

            $section = null;
            foreach ($candidates as $candidate) {
                if (isset($sectionMap[$candidate])) {
                    $section = $sectionMap[$candidate];
                    break;
                }
            }

            if ($section !== null && $section !== '') {
                $itemArray['section'] = $section;
            } else {
                if (isset($itemArray['section'])) {
                    $section = $itemArray['section'];
                } elseif (isset($itemArray['section_no'])) {
                    $section = $itemArray['section_no'];
                } elseif (isset($itemArray['section_number'])) {
                    $section = $itemArray['section_number'];
                } else {
                    $section = null;
                }
                $itemArray['section'] = $section;
            }

            if (!isset($itemArray['sn'])) {
                $itemArray['sn'] = $itemArray['order'] ?? ($index + 1);
            }

            $itemArray['section_number'] = $itemArray['section'];

            return $itemArray;
        });
    }
}