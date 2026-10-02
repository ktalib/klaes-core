<?php

namespace App\Http\Controllers;

use App\Models\JointSiteInspectionReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
 
class ActionsController extends Controller
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

    public function OtherDepartments($d)
    {
        $PageTitle = 'OTHER DEPARTMENTS';
        $PageDescription = '';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('actions.other_departments', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function Bill($d)
    {
        $PageTitle = 'Bill';
        $PageDescription = '';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('actions.bill', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function Payment($d)
    {
        $PageTitle = 'Payment';
        $PageDescription = '';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('actions.payments', compact('application', 'PageTitle', 'PageDescription'));
    }
    
    public function Recommendation($d)
    {
        $PageTitle = 'PLANNING RECOMMENDATION';
        $PageDescription = '';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        $jointInspectionReport = JointSiteInspectionReport::where('application_id', $application->id)
            ->select('*') // Ensure all columns including is_approved, approved_by, approved_at are loaded
            ->first();

        return view('actions.recommendation', compact('application', 'PageTitle', 'PageDescription', 'jointInspectionReport'));
    }

    public function FinalConveyance($d)
    {
        return app(PrimaryActionsController::class)->finalConveyance($d);
    }  
    
    
    public function  BuyersList($d)
    {
    $PageTitle = 'Add/Edit List of Buyers';
        $PageDescription = '';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        $titles = DB::connection('sqlsrv')
            ->table('dbo.titles')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        $isPlanningRecommendationApproved = ($application->planning_recommendation_status === 'Approved');

        return view('actions.buyers_list', compact(
            'application',
            'PageTitle',
            'PageDescription',
            'titles',
            'isPlanningRecommendationApproved'
        ));
    }

    public function DirectorApproval($d)
    {
    
        $PageTitle = 'Directors Approval';
        $PageDescription = 'This page is for directors to approve the application.';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

       return view('actions.director_approval', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function BettermentBill($d)
    {
        $PageTitle = 'Betterment Bill';
        $PageDescription = 'Generate and manage betterment bill for sectional title';
        
        $application = $this->getApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('actions.betterment_bill', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function updateArchitecturalDesign(Request $request, $applicationId)
    {
        $request->validate([
            'architectural_design' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);
    
        try {
            // Get the current application from the SQL Server database
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $applicationId)
                ->first();
                
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }
            
            // Parse the existing documents JSON
            $documents = json_decode($application->documents, true) ?? [];
            
            // Upload the new file
            $file = $request->file('architectural_design');
            $path = $file->store('documents', 'public');
            
            // Update only the architectural_design portion of the JSON
            $documents['architectural_design'] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'type' => $file->getClientOriginalExtension(),
                'uploaded_at' => now()->format('Y-m-d H:i:s')
            ];
            
            // Update the application in the SQL Server database
            DB::connection('sqlsrv')
                ->table('mother_applications')
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
                    'full_path' => asset('storage/' . $documents['architectural_design']['path'])
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

    public function generateActionSheet(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
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
            DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $id)
                ->update([
                    'action_sheet_generated' => 'Yes',
                    'action_sheet_generated_at' => now(),
                    'action_sheet_generated_by' => auth()->id()
                ]);

            if (Schema::connection('sqlsrv')->hasTable('action_sheet_decisions')) {
                DB::connection('sqlsrv')->table('action_sheet_decisions')->insert([
                    'mother_application_id' => $application->id,
                    'sub_application_id' => null,
                    'application_type' => 'PRIMARY',
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
            \Log::error('Error generating action sheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating action sheet. Please try again.'
            ], 500);
        }
    }

    public function viewActionSheet($id)
    {
        try {
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                abort(404, 'Application not found');
            }

            // Check if action sheet has been generated
            if (empty($application->action_sheet_generated) || $application->action_sheet_generated !== 'Yes') {
                return redirect()->back()->with('error', 'Action sheet has not been generated for this application.');
            }

            $ownerName = $this->formatOwnerName($application);
            $location = $this->formatLocation($application);

            // OSS inspection status (used by action sheet view)
            $application->oss_inspection_status = null;
            if (Schema::connection('sqlsrv')->hasTable('joint_site_inspection_reports')) {
                $jointInspection = DB::connection('sqlsrv')->table('joint_site_inspection_reports')
                    ->where('application_id', $id)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('created_at')
                    ->first();

                if ($jointInspection) {
                    $isApproved = (int)($jointInspection->is_approved ?? 0) === 1;
                    if ($isApproved) {
                        $application->oss_inspection_status = 'Approved';
                    } elseif (!is_null($jointInspection->is_approved)) {
                        $application->oss_inspection_status = 'Declined';
                    } else {
                        $application->oss_inspection_status = 'Pending';
                    }
                }
            }

            $plotNumber = null;
            if (!empty($application->property_plot_no)) {
                $plotNumber = $application->property_plot_no;
            } elseif (!empty($application->property_house_no)) {
                $plotNumber = $application->property_house_no;
            }

            $cofoNumber = null;
            $cofoTableCandidates = ['CofO', 'cofo'];
            foreach ($cofoTableCandidates as $cofoTable) {
                if (!Schema::connection('sqlsrv')->hasTable($cofoTable)) {
                    continue;
                }

                $candidate = DB::connection('sqlsrv')->table($cofoTable)
                    ->where('application_id', $id)
                    ->orderByDesc('id')
                    ->value('cofo_no');

                if ($candidate !== null && trim((string) $candidate) !== '') {
                    $cofoNumber = $candidate;
                    break;
                }
            }

            $application->computed_owner_name = $ownerName !== 'N/A' ? $ownerName : 'Piece of land';
            $application->computed_location = $location !== 'N/A' ? Str::upper($location) : 'Piece of land';
            $application->computed_plot_number = $plotNumber ? Str::upper($plotNumber) : 'Piece of land';
            $application->computed_cofo_number = $cofoNumber ? Str::upper($cofoNumber) : 'Piece of land';

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

            $PageTitle = 'Director\'s Action Sheet - ' . ($application->np_fileno ?? $application->fileno);
            
                                $directorDecision = $application->application_status ?? 'Approved';
                                if (Schema::connection('sqlsrv')->hasTable('action_sheet_decisions')) {
                                    $decisionRecord = DB::connection('sqlsrv')->table('action_sheet_decisions')
                                        ->where('mother_application_id', $application->id)
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
            return view('actions.action_sheet_standalone', compact('application', 'PageTitle'));

        } catch (\Exception $e) {
            \Log::error('Error viewing action sheet: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading action sheet. Please try again.');
        }
    }

    public function getActionSheetSummary($id)
    {
        $application = $this->getApplication($id);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        $ownerName = $this->formatOwnerName($application);

    // Site Plan status defaults to pending until a document is uploaded
    $sitePlanStatus = 'PENDING';
    $sitePlanDetails = 'No site plan uploaded';
        if (Schema::connection('sqlsrv')->hasTable('recommended_site_plans')) {
            $recommendedPlan = DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $id)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($recommendedPlan) {
                $planStatus = strtolower((string)($recommendedPlan->status ?? ''));
                $timestamp = $recommendedPlan->updated_at ?? $recommendedPlan->created_at ?? null;
                $formattedTimestamp = $timestamp ? Carbon::parse($timestamp)->format('d M Y H:i') : null;

                if (in_array($planStatus, ['uploaded', 'approved'])) {
                    $sitePlanStatus = 'PASSED';
                } elseif ($planStatus === 'pending') {
                    $sitePlanStatus = 'PENDING';
                } else {
                    $sitePlanStatus = 'DECLINED';
                }

                $sitePlanDetails = 'Latest status: ' . ($recommendedPlan->status ?? 'N/A');
                if ($formattedTimestamp) {
                    $sitePlanDetails .= ' • Updated ' . $formattedTimestamp;
                }
            }
        }

        // OSS Inspection Report
        $ossStatus = 'PENDING';
        $ossDetails = 'Awaiting OSS approval';
        if (Schema::connection('sqlsrv')->hasTable('joint_site_inspection_reports')) {
            $jointInspection = DB::connection('sqlsrv')->table('joint_site_inspection_reports')
                ->where('application_id', $id)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($jointInspection) {
                $isApproved = (int)($jointInspection->is_approved ?? 0) === 1;
                $ossStatus = $isApproved ? 'PASSED' : 'PENDING';
                $ossDetails = $isApproved ? 'Approved by OSS' : 'Pending OSS approval';

                $timestamp = $jointInspection->updated_at ?? $jointInspection->created_at ?? null;
                if ($timestamp) {
                    $ossDetails .= ' • Updated ' . Carbon::parse($timestamp)->format('d M Y H:i');
                }
            }
        }

        // Planning Advice
        $planningStatusRaw = strtolower((string)($application->planning_recommendation_status ?? ''));
        $planningStatus = $planningStatusRaw === 'approved' ? 'PASSED' : 'DECLINED';
    $planningDetails = ($application->planning_recommendation_status ?? 'N/A');

        // Outstanding Land Use Charges
        $landUseChargeStatus = 'NOT PAID';
        $landUseChargeDetails = 'No land use charge record found';
        if (Schema::connection('sqlsrv')->hasTable('billing')) {
            $billingRecord = DB::connection('sqlsrv')->table('billing')
                ->where('application_id', $id)
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
                    'np_fileno' => $application->np_fileno,
                    'fileno' => $application->fileno,
                    'owner_name' => $ownerName,
                    'land_use' => $application->land_use,
                    'location' => $this->formatLocation($application),
                    'planning_recommendation_status' => $application->planning_recommendation_status,
                    'application_status' => $application->application_status,
                ],
                'statuses' => $statuses,
            ]
        ]);
    }

    private function formatOwnerName($application)
    {
        if (!empty($application->multiple_owners_names)) {
            $owners = json_decode($application->multiple_owners_names, true);
            if (is_array($owners) && count($owners) > 0) {
                return implode(', ', $owners);
            }
        }

        if (!empty($application->corporate_name)) {
            return $application->corporate_name;
        }

        return trim(collect([
            $application->applicant_title ?? null,
            $application->first_name ?? null,
            $application->surname ?? null,
        ])->filter()->implode(' ')) ?: 'N/A';
    }

    private function formatLocation($application)
    {
        return trim(collect([
            $application->property_house_no ?? null,
            $application->property_plot_no ?? null,
            $application->property_street_name ?? null,
            $application->property_district ?? null,
            $application->property_lga ?? null,
            $application->property_state ?? null,
        ])->filter()->implode(', ')) ?: 'N/A';
    }
}
