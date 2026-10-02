<?php

namespace App\Http\Controllers;

use App\Models\JointSiteInspectionReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubActionsController extends Controller
{
    private function getApplication($id)
    {
        // Modified to join subapplications with mother_applications to get primary application details
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'subapplications.*',
                'subapplications.id as applicationID',
                'subapplications.main_application_id as main_application_id',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.applicant_type as primary_applicant_type',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.applicant_title as primary_applicant_title',
                'mother_applications.id as primary_id',
                'mother_applications.application_status as primary_application_status',
                'mother_applications.planning_recommendation_status as mother_planning_status',
                'mother_applications.land_use as primary_land_use',
                'mother_applications.id as main_application_id',
                'mother_applications.corporate_name as primary_corporate_name',
                'mother_applications.multiple_owners_names as primary_multiple_owners_names',

                // Property fields with proper aliases
                'mother_applications.property_house_no as property_house_no',
                'mother_applications.property_plot_no as property_plot_no',
                'mother_applications.property_street_name as property_street_name',
                'mother_applications.property_lga as property_lga',
                'mother_applications.property_state as property_state',

                'mother_applications.np_fileno as np_fileno'
            )
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Sub application not found'], 404);
        }

        return $application;
    }

    public function OtherDepartments(Request $request, $id)
    {
        if ($request->query('is') === 'survey') {
            $PageTitle = 'SECTIONAL TITLE SURVEY';
            $PageDescription = 'Manage Survey Department Actions (Add/Update Survey Records)';
        } else {
            $PageTitle = 'OTHER DEPARTMENTS';
            $PageDescription = 'Sub-Application Departmental Actions';
        }

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.other_departments', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function Bill($id)
    {
        $PageTitle = 'Bill';
        $PageDescription = 'Sub-Application Billing Details';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.bill', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function Payment($id)
    {
        $PageTitle = 'Payment';
        $PageDescription = 'Sub-Application Bills Management';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.payments', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function Recommendation($id)
    {
        $PageTitle = 'PLANNING RECOMMENDATION';
        $PageDescription = 'Sub-Application Planning Recommendation';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        // Load JSI report for sub-application with approval status
        $jointInspectionReport = JointSiteInspectionReport::where('sub_application_id', $application->id)
            ->select('*') // Ensure all columns including is_approved, approved_by, approved_at are loaded
            ->first();

        return view('sub_actions.recommendation', compact('application', 'PageTitle', 'PageDescription', 'jointInspectionReport'));
    }


    public function DirectorApproval($id)
    {
        $PageTitle = 'Director\'s Approval';
        $PageDescription = 'Sub-Application Director Approval';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.director_approval', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function getActionSheetSummary($id)
    {
        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        $ownerName = $this->formatOwnerName($application);

        // Site Plan is auto-passed for sub-applications
        $sitePlanStatus = 'PASSED';
        $sitePlanDetails = 'Inherited from primary application';

        // OSS Inspection Report
        $ossStatus = 'PENDING';
        $ossDetails = 'Awaiting OSS approval';
        if (Schema::connection('sqlsrv')->hasTable('joint_site_inspection_reports')) {
            $jointInspection = DB::connection('sqlsrv')->table('joint_site_inspection_reports')
                ->where('sub_application_id', $id)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($jointInspection) {
                $isApproved = (int) ($jointInspection->is_approved ?? 0) === 1;
                $ossStatus = $isApproved ? 'PASSED' : 'PENDING';
                $ossDetails = $isApproved ? 'Approved by OSS' : 'Pending OSS approval';

                $timestamp = $jointInspection->updated_at ?? $jointInspection->created_at ?? null;
                if ($timestamp) {
                    $ossDetails .= ' • Updated ' . Carbon::parse($timestamp)->format('d M Y H:i');
                }
            }
        }

        // Planning Advice
        $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
        $planningStatusRaw = strtolower((string) ($application->planning_recommendation_status ?? ''));
        $motherPlanningStatusRaw = strtolower((string) ($application->mother_planning_status ?? ''));

        $isPlanningApproved = ($planningStatusRaw === 'approved') ||
            (!$isSua_local && $motherPlanningStatusRaw === 'approved');

        $planningStatus = $isPlanningApproved ? 'PASSED' : 'DECLINED';
        $planningDetails = $planningStatusRaw === 'approved' ? 'Approved' :
            (!$isSua_local && $motherPlanningStatusRaw === 'approved' ? 'Mother Approved' : ($application->planning_recommendation_status ?? 'N/A'));

        // Outstanding Land Use Charges
        $landUseChargeStatus = 'NOT PAID';
        $landUseChargeDetails = 'No land use charge record found';
        if (Schema::connection('sqlsrv')->hasTable('billing')) {
            $billingRecord = DB::connection('sqlsrv')->table('billing')
                ->where('sub_application_id', $id)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($billingRecord) {
                $landUseCharge = $billingRecord->Land_Use_Charge ?? null;
                if (is_null($landUseCharge)) {
                    $landUseChargeStatus = 'NOT PAID';
                    $landUseChargeDetails = 'No land use charge recorded';
                } elseif (is_numeric($landUseCharge)) {
                    $amount = floatval($landUseCharge);
                    if ($amount >= 0) {
                        $landUseChargeStatus = 'PAID';
                        $landUseChargeDetails = 'Amount recorded: ₦' . number_format($amount, 2);
                    } else {
                        $landUseChargeStatus = 'DECLINED';
                        $landUseChargeDetails = 'Negative land use charge recorded: ₦' . number_format($amount, 2);
                    }
                } else {
                    $landUseChargeStatus = 'DECLINED';
                    $landUseChargeDetails = 'Land use charge flagged with status: ' . $landUseCharge;
                }

                $timestamp = $billingRecord->updated_at ?? $billingRecord->created_at ?? null;
                if ($timestamp) {
                    $landUseChargeDetails .= ' • Updated ' . Carbon::parse($timestamp)->format('d M Y H:i');
                }
            }
        }

        $statuses = [
            [
                'code' => 'application_requirements',
                'label' => 'a) Application Requirements',
                'status' => 'PASSED',
                'details' => 'All prerequisites uploaded during intake'
            ],
            [
                'code' => 'site_plan',
                'label' => 'b) Site Plan',
                'status' => $sitePlanStatus,
                'details' => $sitePlanDetails
            ],
            [
                'code' => 'oss_inspection_report',
                'label' => 'c) OSS Inspection Report',
                'status' => $ossStatus,
                'details' => $ossDetails
            ],
            [
                'code' => 'planning_advice',
                'label' => 'd) Planning Advice',
                'status' => $planningStatus,
                'details' => $planningDetails
            ],
            [
                'code' => 'application_processing_fees',
                'label' => 'e) Application and Processing Fees',
                'status' => 'PAID',
                'details' => 'Initial payments confirmed at submission'
            ],
            [
                'code' => 'outstanding_land_use_charges',
                'label' => 'f) Outstanding Land Use Charges',
                'status' => $landUseChargeStatus,
                'details' => $landUseChargeDetails
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'application' => [
                    'id' => $application->id,
                    'np_fileno' => $application->np_fileno ?? $application->primary_fileno,
                    'fileno' => $application->fileno,
                    'owner_name' => $ownerName,
                    'land_use' => $application->land_use,
                    'location' => $this->formatLocation($application),
                    'planning_recommendation_status' => $application->planning_recommendation_status,
                    'application_status' => $application->application_status,
                    'primary_application_id' => $application->main_application_id,
                ],
                'statuses' => $statuses,
            ]
        ]);
    }

    private function formatOwnerName($application)
    {
        $name = null;

        if (!empty($application->multiple_owners_names)) {
            $owners = json_decode($application->multiple_owners_names, true);
            if (is_array($owners) && count($owners) > 0) {
                $name = implode(', ', array_filter(array_map('trim', $owners)));
            }
        }

        if (!$name && !empty($application->corporate_name)) {
            $name = trim($application->corporate_name);
        }

        if (!$name) {
            $name = trim(collect([
                $application->applicant_title ?? null,
                $application->first_name ?? null,
                $application->surname ?? null,
            ])->filter()->implode(' '));
        }

        if ($name !== null && $name !== '') {
            return $name;
        }

        // Fallback to primary application details when sub-application fields are empty
        if (!empty($application->primary_multiple_owners_names)) {
            $primaryOwners = json_decode($application->primary_multiple_owners_names, true);
            if (is_array($primaryOwners) && count($primaryOwners) > 0) {
                $primaryName = implode(', ', array_filter(array_map('trim', $primaryOwners)));
                if ($primaryName !== '') {
                    return $primaryName;
                }
            }
        }

        if (!empty($application->primary_corporate_name)) {
            $corpName = trim($application->primary_corporate_name);
            if ($corpName !== '') {
                return $corpName;
            }
        }

        $primaryIndividual = trim(collect([
            $application->primary_applicant_title ?? null,
            $application->primary_first_name ?? null,
            $application->primary_surname ?? null,
        ])->filter()->implode(' '));

        return $primaryIndividual !== '' ? $primaryIndividual : 'N/A';
    }

    private function formatLocation($application)
    {
        if (!empty($application->property_location)) {
            return $application->property_location;
        }

        return trim(collect([
            $application->property_house_no ?? null,
            $application->property_plot_no ?? null,
            $application->property_street_name ?? null,
            $application->property_district ?? null,
            $application->property_lga ?? null,
            $application->property_state ?? null,
        ])->filter()->implode(', ')) ?: 'N/A';
    }

    public function updateArchitecturalDesign(Request $request, $applicationId)
    {
        $request->validate([
            'architectural_design' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        try {
            // Get the current application from the SQL Server database
            $application = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-application not found.'
                ], 404);
            }

            // Parse the existing documents JSON
            $documents = json_decode($application->documents, true) ?? [];

            // Upload the new file
            $file = $request->file('architectural_design');
            $path = $file->store('documents/subapplications', 'public');

            // Update only the architectural_design portion of the JSON
            $documents['architectural_design'] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'type' => $file->getClientOriginalExtension(),
                'uploaded_at' => now()->format('Y-m-d H:i:s')
            ];

            // Update the application in the SQL Server database
            DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $applicationId)
                ->update([
                    'documents' => json_encode($documents),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Architectural design has been updated successfully.',
                'design' => [
                    'path' => $documents['architectural_design']['path'],
                    'uploaded_at' => $documents['architectural_design']['uploaded_at'],
                    'full_path' => asset('storage/app/public/' . $documents['architectural_design']['path'])
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating architectural design: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating architectural design. Please try again.'
            ], 500);
        }
    }

    // New method to update planning recommendation via AJAX
    public function updatePlanningRecommendation(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'status' => 'required|string|in:Approved,Declined',
                'approval_date' => 'required|date',
                'comments' => 'nullable|string'
            ]);

            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $validated['application_id'])
                ->update([
                    'planning_recommendation_status' => $validated['status'],
                    'planning_approval_date' => $validated['approval_date'],
                    'planning_recomm_comments' => $validated['comments'],
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Planning recommendation has been updated successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating planning recommendation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating planning recommendation. Please try again.'
            ], 500);
        }
    }

    // New method to update director approval via AJAX
    public function updateDirectorApproval(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'status' => 'required|string|in:Approved,Declined',
                'approval_date' => 'required|date',
                'comments' => 'nullable|string'
            ]);

            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $validated['application_id'])
                ->update([
                    'application_status' => $validated['status'],
                    'approval_date' => $validated['approval_date'],

                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Director approval has been updated successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating director approval: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating director approval. Please try again.'
            ], 500);
        }
    }

    // New method to store survey info via AJAX
    public function storeSurvey(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'sub_application_id' => 'required|integer',
            'fileno' => 'required|string|max:255',
            // Survey personnel information
            'survey_by' => 'required|string|max:255',
            'survey_by_date' => 'required|date',
            'drawn_by' => 'required|string|max:255',
            'drawn_by_date' => 'required|date',
            'checked_by' => 'required|string|max:255',
            'checked_by_date' => 'required|date',
            'approved_by' => 'required|string|max:255',
            'approved_by_date' => 'required|date',
            // Property Identification
            'plot_no' => 'nullable|string|max:255',
            'block_no' => 'nullable|string|max:255',
            'approved_plan_no' => 'nullable|string|max:255',
            'tp_plan_no' => 'nullable|string|max:255',
            // Beacon Control Information
            'beacon_control_name' => 'nullable|string|max:255',
            'Control_Beacon_Coordinate_X' => 'nullable|string|max:255',
            'Control_Beacon_Coordinate_Y' => 'nullable|string|max:255',
            // Sheet Information
            'Metric_Sheet_Index' => 'nullable|string|max:255',
            'Metric_Sheet_No' => 'nullable|string|max:255',
            'Imperial_Sheet' => 'nullable|string|max:255',
            'Imperial_Sheet_No' => 'nullable|string|max:255',
            // Location Information
            'layout_name' => 'nullable|string|max:255',
            'district_name' => 'nullable|string|max:255',
            'lga_name' => 'nullable|string|max:255',
        ]);

        // Insert the data into the database
        DB::connection('sqlsrv')->table('surveyCadastralRecord')->insert($validatedData);

        return redirect()->back()->with('success', 'Survey submitted successfully!');
    }
    // New method to store deeds info via AJAX
    public function storeDeeds(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'serial_no' => 'required|string|max:255',
                'page_no' => 'required|string|max:255',
                'volume_no' => 'required|string|max:255',
                'deeds_time' => 'required|string',
                'deeds_date' => 'required|date'
            ]);

            $deedsData = [
                'serial_no' => $validated['serial_no'],
                'page_no' => $validated['page_no'],
                'volume_no' => $validated['volume_no'],
                'deeds_time' => $validated['deeds_time'],
                'deeds_date' => $validated['deeds_date'],
                'updated_at' => now()
            ];

            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $validated['application_id'])
                ->update($deedsData);

            return response()->json([
                'success' => true,
                'message' => 'Deeds information has been saved successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error saving deeds information: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error saving deeds information. Please try again.'
            ], 500);
        }
    }

    // Method to get related subapplications for a primary application
    public function getRelatedSubApplications($primaryId)
    {
        try {
            $subapplications = DB::connection('sqlsrv')->table('subapplications')
                ->where('main_application_id', $primaryId)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subapplications
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching related subapplications: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching related subapplications. Please try again.'
            ], 500);
        }
    }

    public function FinalConveyance($id)
    {
        $PageTitle = 'Final Conveyance';
        $PageDescription = 'Sub-Application Final Conveyance Agreement';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.final_conveyance', compact('application', 'PageTitle', 'PageDescription'));
    }

    // Method to get conveyance data
    public function getConveyance($applicationId)
    {
        try {
            $application = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-application not found'
                ], 404);
            }

            $conveyanceData = json_decode($application->conveyance, true) ?? [];
            $records = $conveyanceData['records'] ?? [];

            return response()->json([
                'success' => true,
                'records' => $records
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving conveyance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving conveyance data. Please try again.'
            ], 500);
        }
    }

    // New method to update final conveyance via AJAX
    public function updateFinalConveyance(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'records' => 'required|array',
                'records.*.buyerTitle' => 'nullable|string',
                'records.*.buyerName' => 'required|string',
                'records.*.sectionNo' => 'required|string'
            ]);

            $conveyanceData = [
                'records' => $validated['records'],
                'updated_at' => now()->format('Y-m-d H:i:s')
            ];

            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $validated['application_id'])
                ->update([
                    'conveyance' => json_encode($conveyanceData),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Final conveyance has been updated successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating final conveyance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating final conveyance. Please try again.'
            ], 500);
        }
    }

    // New method to finalize conveyance via AJAX
    public function finalizeFinalConveyance(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'status' => 'required|string|in:completed,pending'
            ]);

            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $validated['application_id'])
                ->update([
                    'conveyance_status' => $validated['status'],
                    'conveyance_completed_at' => now(),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Final conveyance has been marked as ' . $validated['status'] . '.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error finalizing conveyance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error finalizing conveyance. Please try again.'
            ], 500);
        }
    }

    public function printPlanningRecommendation($id)
    {
        $PageTitle = 'PLANNING RECOMMENDATION';
        $PageDescription = 'Print Planning Recommendation for Sub-Application';

        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('sub_actions.print_planning_recommendation', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function generateActionSheet(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit application not found.'
                ], 404);
            }

            // Check if application is approved (required for action sheet generation)
            if ($application->application_status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Action sheet can only be generated for approved applications.'
                ], 400);
            }

            $decision = strtoupper(trim((string) $request->input('decision', '')));
            if (!in_array($decision, ['APPROVED', 'DECLINED'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select a valid director decision before generating the action sheet.'
                ], 422);
            }

            // Update the action_sheet_generated field
            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $id)
                ->update([
                    'action_sheet_generated' => 'Yes',
                    'action_sheet_generated_at' => now(),
                    'action_sheet_generated_by' => auth()->id()
                ]);

            if (Schema::connection('sqlsrv')->hasTable('action_sheet_decisions')) {
                DB::connection('sqlsrv')->table('action_sheet_decisions')->insert([
                    'mother_application_id' => null,
                    'sub_application_id' => $application->id,
                    'application_type' => 'UNIT',
                    'decision' => $decision,
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Action sheet generated successfully.',
                'data' => [
                    'action_sheet_generated' => 'Yes',
                    'action_sheet_generated_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generating unit action sheet: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error generating action sheet. Please try again.'
            ], 500);
        }
    }

    public function viewActionSheet($id)
    {
        try {
            $application = $this->getApplication($id);

            if ($application instanceof \Illuminate\Http\JsonResponse) {
                abort(404, 'Unit application not found');
            }

            // Check if action sheet has been generated
            if (empty($application->action_sheet_generated) || $application->action_sheet_generated !== 'Yes') {
                return redirect()->back()->with('error', 'Action sheet has not been generated for this unit application.');
            }

            $ownerName = $this->formatOwnerName($application);
            $location = $this->formatLocation($application);

            $directorDecision = $application->application_status ?? 'Approved';
            if (Schema::connection('sqlsrv')->hasTable('action_sheet_decisions')) {
                $decisionRecord = DB::connection('sqlsrv')->table('action_sheet_decisions')
                    ->where('sub_application_id', $application->id)
                    ->orderByDesc('decided_at')
                    ->orderByDesc('id')
                    ->first();

                if ($decisionRecord && !empty($decisionRecord->decision)) {
                    $directorDecision = $decisionRecord->decision;
                }
            }

            $directorDecisionNormalized = strtoupper(trim((string) $directorDecision));
            $application->computed_director_decision = in_array($directorDecisionNormalized, ['APPROVED', 'DECLINED'], true)
                ? $directorDecisionNormalized
                : 'APPROVED';

            $plotNumber = null;
            if (!empty($application->property_plot_no)) {
                $plotNumber = $application->property_plot_no;
            } elseif (!empty($application->property_house_no)) {
                $plotNumber = $application->property_house_no;
            } elseif (!empty($application->unit_number)) {
                $plotNumber = $application->unit_number;
            }

            $application->computed_owner_name = $ownerName !== 'N/A' ? $ownerName : 'Piece of land';
            $application->computed_location = $location !== 'N/A' ? Str::upper($location) : 'Piece of land';
            $application->computed_plot_number = $plotNumber ? Str::upper($plotNumber) : 'Piece of land';

            $approvalDateDisplay = null;
            if (!empty($application->approval_date)) {
                try {
                    $approvalDateDisplay = Carbon::parse($application->approval_date)->format('d/m/Y');
                } catch (\Exception $e) {
                    $approvalDateDisplay = null;
                }
            }
            $application->computed_approval_date = $approvalDateDisplay ?? now()->format('d/m/Y');

            $generatedByName = 'System';
            if (!empty($application->action_sheet_generated_by)) {
                try {
                    $user = DB::connection('sqlsrv')->table('users')
                        ->where('id', $application->action_sheet_generated_by)
                        ->select('name')
                        ->first();

                    if ($user && !empty($user->name)) {
                        $generatedByName = $user->name;
                    }
                } catch (\Throwable $e) {
                    // Leave default when lookup fails
                }
            }

            $generatedAtDisplay = null;
            if (!empty($application->action_sheet_generated_at)) {
                try {
                    $generatedAtDisplay = Carbon::parse($application->action_sheet_generated_at)->format('d/m/Y H:i');
                } catch (\Exception $e) {
                    $generatedAtDisplay = null;
                }
            }

            $application->computed_generated_by = $generatedByName;
            $application->computed_generated_at = $generatedAtDisplay ?? now()->format('d/m/Y H:i');

            $PageTitle = 'Director\'s Action Sheet - Unit Application - ' . ($application->mls_fileno ?? $application->fileno);

            return view('sub_actions.action_sheet_standalone', compact('application', 'PageTitle'));

        } catch (\Exception $e) {
            \Log::error('Error viewing unit action sheet: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading action sheet. Please try again.');
        }
    }
}
