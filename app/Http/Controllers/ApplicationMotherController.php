<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\SubApplicationDraft;

class ApplicationMotherController extends Controller
{



    public function AssignRole()
    {
        $user = Auth::user();
        $role = $user->assign_role;

        if ($role == 'owner') {
            return 'owner';
        } elseif (strpos($role, 'Programmes -') !== false) {
            return 'programmes';
        } else {
            return 'access denied';
        }
    }

    public function index()
    {
        $role = $this->AssignRole();

        if ($role == 'access denied') {
            return redirect('/')->with('error', 'You do not have permission to access this resource.');
        }

        $Main_application = DB::connection('sqlsrv')->table('dbo.mother_applications')->get();

        return view('sectionaltitling.index', compact('Main_application', 'role'));
    }
    public function subApplication()
    {
        $PageTitle = 'Create Unit Application ';
        $PageDescription = 'Unit application for sectional title';

        // Get the main application ID from request
        $mainApplicationId = request()->get('application_id');

        // Get the mother application to determine land use
        $motherApplication = null;
        $landUse = request()->get('land_use');

        if ($mainApplicationId) {
            $motherApplication = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $mainApplicationId)
                ->first();

            if ($motherApplication) {
                $landUse = $motherApplication->land_use;
            }
        }

        // Generate New Primary FileNo (NPFN): ST-Landuse-Year-SerialNo
        $npFileNo = '';
        $unitFileNo = '';

        if ($landUse) {
            // Determine the land use code
            $landUseCode = match (strtoupper($landUse)) {
                'COMMERCIAL' => 'COM',
                'INDUSTRIAL' => 'IND',
                'RESIDENTIAL' => 'RES',
                default => 'RES'
            };

            // Get the current year
            $currentYear = date('Y');

            // For NP FileNo, use the main application ID as serial number
            $serialNo = $mainApplicationId ? str_pad($mainApplicationId, 2, '0', STR_PAD_LEFT) : '01';

            // Generate NP FileNo (New Primary FileNo)
            $npFileNo = "ST-{$landUseCode}-{$currentYear}-{$serialNo}";

            // Find the next unit serial number for this NP FileNo
            $lastUnitRecord = DB::connection('sqlsrv')
                ->table('dbo.subapplications')
                ->where('main_application_id', $mainApplicationId)
                ->orderBy('id', 'desc')
                ->first();

            $nextUnitSerial = 1;
            if ($lastUnitRecord) {
                // Count existing units for this main application
                $unitCount = DB::connection('sqlsrv')
                    ->table('dbo.subapplications')
                    ->where('main_application_id', $mainApplicationId)
                    ->count();
                $nextUnitSerial = $unitCount + 1;
            }

            // Generate Unit FileNo (NP FileNo + Unit Serial)
            $unitSerial = str_pad($nextUnitSerial, 3, '0', STR_PAD_LEFT);
            $unitFileNo = $npFileNo . '-' . $unitSerial;
        }

        // Legacy variables for backward compatibility
        $prefix = 'ST-' . ($landUseCode ?? 'RES');
        $formattedSerialNumber = $nextUnitSerial ?? 1;

        // Check for existing drafts
        $userId = Auth::id();
        $existingDraft = null;
        $userDrafts = collect();

        if ($userId) {
            // Check for existing draft for this main application
            $existingDraft = SubApplicationDraft::where('main_application_id', $mainApplicationId)
                ->where('is_sua', false)
                ->where('last_saved_by', $userId)
                ->latest('last_saved_at')
                ->first();

            // Get user's drafts
            $userDrafts = SubApplicationDraft::where('last_saved_by', $userId)
                ->where('is_sua', false)
                ->latest('last_saved_at')
                ->get();

            // Debug logging
            \Log::info('[SubApplication Controller] User drafts query result', [
                'user_id' => $userId,
                'draft_count' => $userDrafts->count(),
                'existing_draft' => $existingDraft ? $existingDraft->draft_id : null,
            ]);
        }

        // Initialize draft bootstrap data
        $draftBootstrap = [
            'draft_id' => $existingDraft?->draft_id,
            'version' => $existingDraft?->version ?? 1,
            'form_state' => $existingDraft?->form_state ?? [],
            'progress_percent' => $existingDraft?->progress_percent ?? 0,
            'last_completed_step' => $existingDraft?->last_completed_step ?? 1,
            'auto_save_frequency' => $existingDraft?->auto_save_frequency ?? 30,
            'last_saved_at' => $existingDraft?->last_saved_at?->toIso8601String(),
            'unit_file_no' => $existingDraft?->unit_file_no ?? $unitFileNo,
            'is_sua' => false,
            'collaborators' => $existingDraft?->collaborators ?? [],
            'drafts' => $userDrafts->map(function ($draft) {
                return [
                    'draft_id' => $draft->draft_id,
                    'unit_file_no' => $draft->unit_file_no,
                    'progress_percent' => $draft->progress_percent,
                    'last_completed_step' => $draft->last_completed_step,
                    'last_saved_at' => $draft->last_saved_at?->toIso8601String(),
                    'label' => $draft->unit_file_no ?: 'Draft ' . substr($draft->draft_id, 0, 8),
                    'is_current' => false,
                ];
            })->toArray(),
            'mode' => $existingDraft ? 'draft' : 'fresh',
        ];

        return view('sectionaltitling.sub_application', compact(
            'npFileNo',
            'unitFileNo',
            'nextUnitSerial',
            'prefix',
            'currentYear',
            'formattedSerialNumber',
            'PageTitle',
            'PageDescription',
            'draftBootstrap'
        ));
    }

    public function create()
    {


        // Check user role
        $role = $this->AssignRole();

        if ($role == 'access denied') {
            return redirect('/')->with('error', 'You do not have permission to access this resource.');
        }
        return view('sectionaltitling.create');
    }


    public function Subapplications(Request $request)
    {
        $subApplications = collect(); // Initialize as an empty collection to avoid undefined variable error

        $query = DB::connection('sqlsrv')
            ->table('dbo.subapplications AS sub')
            ->join('dbo.mother_applications AS main', 'sub.main_application_id', '=', 'main.id')
            ->select([
                'sub.*',
                'main.fileno as main_fileno',
                'main.plot_size',
                'main.land_use',
                // Use correct address fields
                'main.address_house_no',
                'main.address_plot_no',
                'main.address_street_name',
                'main.address_district',
                'main.address_lga',
                'main.address_state',
                // Property address fields if needed
                'main.property_house_no',
                'main.property_plot_no',
                'main.property_street_name',
                'main.property_district',
                'main.property_lga',
                'main.property_state',
                'main.address',
                'main.approval_date',
                'main.applicant_type as main_applicant_type',
                'main.applicant_title as main_applicant_title',
                'main.first_name as main_first_name',
                'main.middle_name as main_middle_name',
                'main.surname as main_surname',
                'main.corporate_name as main_corporate_name',
                'main.multiple_owners_names as main_multiple_owners_names',
                'main.planning_recommendation_status as mother_planning_status',
                'main.application_status as main_status'
            ]);

        // Filter by main_application_id if provided in the URL
        if ($request->has('main_application_id')) {
            $mainId = $request->main_application_id;

            // Check if main application exists
            $mainAppExists = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $mainId)
                ->exists();

            if (!$mainAppExists) {
                return redirect()->back()->with('sweet_alert', [
                    'type' => 'error',
                    'title' => 'Error',
                    'text' => 'Mother application with ID ' . $mainId . ' not found.'
                ]);
            }

            $query->where('sub.main_application_id', $mainId);
        }

        $subApplications = $query->get();

        // If filtering by main_application_id and no results found
        if ($request->has('main_application_id') && $subApplications->isEmpty()) {
            return redirect()->route('sectionaltitling.index')->with('error', 'No sub-applications found for mother application ID.');

        }


        // Return the view with the sub-applications data

        return view('sectionaltitling.sub_applications', compact('subApplications'));
    }

    public function GenerateBill(Request $request, $id = null)
    {
        // Use either route parameter or query parameter for ID
        $id = $id ?? $request->get('id');

        // Fetch application data from database if ID is provided
        if ($id) {
            $application = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $id)
                ->first();

            if ($application) {
                // Process owner name based on application type
                $ownerName = '';
                if ($application->applicant_type == 'corporate' && $application->corporate_name) {
                    $ownerName = $application->corporate_name;
                } elseif ($application->applicant_type == 'multiple' && $application->multiple_owners_names) {
                    // Try to decode JSON; if it fails, use as a string
                    $multipleOwners = json_decode($application->multiple_owners_names, true);
                    $ownerName = is_array($multipleOwners) ? implode(', ', $multipleOwners) : $application->multiple_owners_names;
                } else {
                    $ownerName = trim($application->first_name . ' ' . $application->middle_name . ' ' . $application->surname);
                }

                // Calculate fees based on land use
                $processingFee = 0;
                $surveyFee = 0;
                $assignmentFee = 0;
                $billBalance = 30525.00; // Default bill balance
                $groundRent = 0;

                if (strtolower($application->land_use) == 'residential') {
                    $processingFee = 20000.00;
                    $surveyFee = isset($application->NoOfUnits) && $application->NoOfUnits > 1 ? 50000.00 : 70000.00; // Block of flats or apartment
                    $assignmentFee = 50000.00;
                } else { // Commercial or others
                    $processingFee = 50000.00;
                    $surveyFee = 100000.00;
                    $assignmentFee = 100000.00;
                }

                // Calculate total
                $total = $processingFee + $surveyFee + $assignmentFee + $billBalance + $groundRent;

                // Format date
                $approvalDate = $request->get('approval_date') ??
                    (isset($application->approval_date) ? date('Y-m-d', strtotime($application->approval_date)) : date('Y-m-d'));

                // Convert plot size to more readable format if needed
                $plotSize = $application->plot_size;
                if (is_numeric($plotSize) && $plotSize > 0) {
                    // Format plot size (example: convert to square meters notation if needed)
                    $plotSize = number_format($plotSize, 2) . ' sqm';
                }

                // Prepare data for the view
                $data = [
                    'id' => $application->id,
                    'fileno' => $application->fileno,
                    'applicant_title' => $application->applicant_title,
                    'owner_name' => $ownerName,
                    'plot_size' => $plotSize,
                    'land_use' => $application->land_use,
                    'plot_house_no' => $application->plot_house_no,
                    'plot_plot_no' => $application->plot_plot_no,
                    'plot_street_name' => $application->plot_street_name,
                    'owner_district' => $application->plot_district,
                    'address' => $application->address,
                    'approval_date' => $approvalDate,
                    'processing_fee' => $processingFee,
                    'survey_fee' => $surveyFee,
                    'assignment_fee' => $assignmentFee,
                    'bill_balance' => $billBalance,
                    'recertification_fee' => $groundRent,
                    'total' => $total,
                    'total_words' => $this->numberToWords($total),
                    'application_type' => $application->applicant_type,
                ];

                return view('sectionaltitling.generate_bill', $data);
            }
        }

        // Fallback to request data if no database record found
        $data = $request->only([
            'id',
            'fileno',
            'applicant_title',
            'owner_name',
            'plot_house_no',
            'plot_street_name',
            'address',
            'owner_district',
            'approval_date',
            'plot_size',
            'land_use'
        ]);

        return view('sectionaltitling.generate_bill', $data);
    }



    public function GenerateBill2(Request $request, $id = null)
    {
        // Use either route parameter or query parameter for ID
        $id = $id ?? $request->get('id');

        // Fetch application data from database if ID is provided
        if ($id) {
            $application = DB::connection('sqlsrv')
                ->table('dbo.subapplications')
                ->where('id', $id)
                ->first();

            if ($application) {
                // Process owner name based on application type
                $ownerName = '';
                if ($application->applicant_type == 'corporate' && $application->corporate_name) {
                    $ownerName = $application->corporate_name;
                } elseif ($application->applicant_type == 'multiple' && $application->multiple_owners_names) {
                    // Try to decode JSON; if it fails, use as a string
                    $multipleOwners = json_decode($application->multiple_owners_names, true);
                    $ownerName = is_array($multipleOwners) ? implode(', ', $multipleOwners) : $application->multiple_owners_names;
                } else {
                    $ownerName = trim(($application->applicant_title ?? '') . ' ' .
                        ($application->first_name ?? '') . ' ' .
                        ($application->middle_name ?? '') . ' ' .
                        ($application->surname ?? ''));
                }

                // Calculate fees based on land use
                $processingFee = 0;
                $surveyFee = 0;
                $assignmentFee = 0;
                $billBalance = 30525.00; // Default bill balance
                $groundRent = 0;

                // Determine land use from application data or file number prefix
                $landUse = '';
                if (!empty($application->land_use)) {
                    $landUse = strtolower($application->land_use);
                } elseif (!empty($application->fileno)) {
                    if (strpos($application->fileno, 'ST-COM') === 0) {
                        $landUse = 'commercial';
                    } elseif (strpos($application->fileno, 'ST-RES') === 0) {
                        $landUse = 'residential';
                    } elseif (strpos($application->fileno, 'ST-IND') === 0) {
                        $landUse = 'industrial';
                    }
                }

                if ($landUse == 'residential') {
                    $processingFee = 20000.00;
                    $surveyFee = isset($application->NoOfUnits) && $application->NoOfUnits > 1 ? 50000.00 : 70000.00; // Block of flats or apartment
                    $assignmentFee = 50000.00;
                } else { // Commercial or others
                    $processingFee = 50000.00;
                    $surveyFee = 100000.00;
                    $assignmentFee = 100000.00;
                }

                // Calculate total
                $total = $processingFee + $surveyFee + $assignmentFee + $billBalance + $groundRent;

                // Format date
                $approvalDate = $request->get('approval_date') ??
                    (isset($application->approval_date) ? date('Y-m-d', strtotime($application->approval_date)) : date('Y-m-d'));

                // Prepare location string from available fields
                $location = '';
                $locationParts = [];

                if (!empty($application->block_number)) {
                    $locationParts[] = 'Block ' . $application->block_number;
                }

                if (!empty($application->floor_number)) {
                    $locationParts[] = 'Floor ' . $application->floor_number;
                }

                if (!empty($application->unit_number)) {
                    $locationParts[] = 'Unit ' . $application->unit_number;
                }

                if (!empty($application->property_location)) {
                    $locationParts[] = $application->property_location;
                } elseif (!empty($application->address)) {
                    $locationParts[] = $application->address;
                }

                $location = implode(', ', $locationParts);

                // Prepare data for the view
                $data = [
                    'id' => $application->id,
                    'fileno' => $application->fileno,
                    'applicant_title' => $application->applicant_title,
                    'owner_name' => $ownerName,
                    'plot_size' => $application->plot_size ?? '',
                    'land_use' => $landUse,
                    'location' => $location,
                    'block_number' => $application->block_number,
                    'floor_number' => $application->floor_number,
                    'unit_number' => $application->unit_number,
                    'property_location' => $application->property_location,
                    'address' => $application->address,
                    'approval_date' => $approvalDate,
                    'processing_fee' => $processingFee,
                    'survey_fee' => $surveyFee,
                    'assignment_fee' => $assignmentFee,
                    'bill_balance' => $billBalance,
                    'recertification_fee' => $groundRent,
                    'total' => $total,
                    'total_words' => $this->numberToWords($total),
                    'application_type' => $application->applicant_type,
                ];

                return view('sectionaltitling.generate_bill_sub', $data);
            }
        }

        // Fallback to request data if no database record found
        $data = $request->only([
            'id',
            'fileno',
            'applicant_title',
            'owner_name',
            'block_number',
            'floor_number',
            'unit_number',
            'property_location',
            'address',
            'approval_date',
            'plot_size',
            'land_use'
        ]);

        return view('sectionaltitling.generate_bill_sub', $data);
    }

    /**
     * Convert a number to words
     * @param float $number The number to convert
     * @return string The number in words
     */
    private function numberToWords($number)
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
        return strtoupper($formatter->format($number) . ' NAIRA ONLY');
    }

    public function AcceptLetter()
    {
        return view('sectionaltitling.AcceptLetter');
    }

    public function storeSub(Request $request)
    {
        $validatedData = $request->validate([
            'main_application_id' => 'required',
            'applicant_type' => 'required|in:individual,corporate,multiple',
            'fileno' => 'required',
            'np_fileno' => 'nullable', // New Primary FileNo
            'date_captured' => 'nullable|date', // Date Captured field
            'applicant_title' => 'nullable',
            'first_name' => 'nullable',
            'middle_name' => 'nullable',
            'surname' => 'nullable',
            'passport' => 'nullable',
            'corporate_name' => 'nullable',
            'rc_number' => 'nullable',
            'multiple_owners_names' => 'nullable',
            'multiple_owners_passport' => 'nullable',
            'multiple_owners_data' => 'nullable',
            'address' => 'nullable',
            'phone_number' => 'nullable',
            'email' => 'nullable',
            'identification_type' => 'nullable',
            'identification_others' => 'nullable',
            'block_number' => 'nullable',
            'floor_number' => 'nullable',
            'unit_number' => 'nullable',
            'property_location' => 'nullable',
            'ownership' => 'nullable',
            'application_status' => 'nullable|in:pending',
            'comments' => 'nullable',
            'approval_date' => 'nullable',
            'planning_recommendation_status' => 'nullable|in:pending',
            'commercial_type' => 'nullable',
            'industrial_type' => 'nullable',
            'ownershipType' => 'nullable',
            'residenceType' => 'nullable',
            'application_fee' => 'nullable',
            'processing_fee' => 'nullable',
            'site_plan_fee' => 'nullable',
            'payment_date' => 'nullable',
            'address_street_name' => 'nullable',
            'address_district' => 'nullable',
            'address_lga' => 'nullable',
            'address_state' => 'nullable',
            'allocation_ref_no' => 'nullable|string|max:255',
            'improvement_value' => 'nullable|string',
            'specific_land_use' => 'nullable|string',



        ]);

        try {
            // Handle multiple_owners_names as JSON if it's an array
            if (isset($validatedData['multiple_owners_names']) && is_array($validatedData['multiple_owners_names'])) {
                $validatedData['multiple_owners_names'] = json_encode($validatedData['multiple_owners_names']);
            }

            // Handle multiple_owners_data as JSON if it's an array
            if (isset($validatedData['multiple_owners_data']) && is_array($validatedData['multiple_owners_data'])) {
                $validatedData['multiple_owners_data'] = json_encode($validatedData['multiple_owners_data']);
            }

            // Handle multiple_owners_passport as JSON if it's an array
            if (isset($validatedData['multiple_owners_passport']) && is_array($validatedData['multiple_owners_passport'])) {
                $validatedData['multiple_owners_passport'] = json_encode($validatedData['multiple_owners_passport']);
            }

            if (!empty($validatedData['allocation_ref_no'])) {
                $validatedData['allocation_ref_no'] = strtoupper(trim($validatedData['allocation_ref_no']));
            }

            $unitFileNo = $request->input('fileno'); // This is the Unit FileNo
            $npFileNo = $request->input('np_fileno'); // This is the NP FileNo (NPFN)

            // Insert into StFileNo table with new structure
            DB::connection('sqlsrv')->table('dbo.StFileNo')->insert([
                'file_prefix' => $request->input('prefix', 'ST'), // Legacy field
                'serial_number' => $request->input('serial_number', 1), // Legacy field
                'year' => $request->input('year', date('Y')), // Legacy field
                'fileno' => $unitFileNo, // Unit FileNo
                'np_fileno' => $npFileNo, // New Primary FileNo (NPFN)
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->hasFile('passport')) {
                $validatedData['passport'] = $request->file('passport')->store('sub_applications/passports', 'public');
            }

            // Check and handle any potential arrays in remaining fields
            foreach ($validatedData as $key => $value) {
                if (is_array($value)) {
                    $validatedData[$key] = json_encode($value);
                }
            }

            // Add NP FileNo to sub application data
            $validatedData['np_fileno'] = $npFileNo;
            $validatedData['fileno'] = $unitFileNo;

            // Remove file related fields before inserting into subapplications table
            $fileData = Arr::only($validatedData, ['file_prefix', 'serial_number', 'year', 'fileno', 'np_fileno']);
            $subAppData = Arr::except($validatedData, ['file_prefix', 'serial_number', 'year']);

            // Set default status if not provided
            if (!isset($subAppData['application_status'])) {
                $subAppData['application_status'] = 'pending';
            }
            if (!isset($subAppData['planning_recommendation_status'])) {
                $subAppData['planning_recommendation_status'] = 'pending';
            }

            // Add timestamps
            $subAppData['created_at'] = now();
            $subAppData['updated_at'] = now();

            DB::connection('sqlsrv')->table('dbo.subapplications')->insert($subAppData);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("storeSub: Error inserting sub-application - " . $e->getMessage(), ['exception' => $e]);
            return redirect()->back()->with('error', 'Error creating Sub-application.');
        }

        return redirect()->route('sectionaltitling.Subapplications')->with('success', 'Sub-application created successfully with NP FileNo: ' . $npFileNo . ' and Unit FileNo: ' . $unitFileNo);
    }


    public function decisionSubApplication(Request $request)
    {
        $id = $request->input('id');
        $decision = $request->input('decision');
        $approval_date = date("Y-m-d H:i:s", strtotime(str_replace('T', ' ', $request->input('approval_date'))));
        $comments = $request->input('comments');

        $subApp = DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->first();

        if (!$subApp) {
            return response()->json(['message' => 'Sub-application not found.'], 404);
        }

        $updateData = ['application_status' => $decision == 'approve' ? 'Approved' : 'Declined', 'approval_date' => $approval_date];
        if ($decision == 'decline') {
            $updateData['comments'] = $comments;
        }

        DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->update($updateData);

        return response()->json([
            'message' => $decision == 'approve'
                ? "Approval for Subdivision of Fileno {$subApp->fileno} has been granted."
                : "Sub-application has been declined."
        ]);
    }

    public function decisionMotherApplication(Request $request)
    {
        $id = $request->input('id');
        $decision = $request->input('decision');
        $approval_date = date("Y-m-d H:i:s", strtotime(str_replace('T', ' ', $request->input('approval_date'))));
        $comments = $request->input('comments');

        $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

        if (!$app) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $updateData = ['application_status' => $decision == 'approve' ? 'Approved' : 'Declined', 'approval_date' => $approval_date];
        if ($decision == 'decline') {
            $updateData['comments'] = $comments;
        }

        DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);

        return response()->json([
            'message' => $decision == 'approve'
                ? "Approval has been sent to fragmentation of File Number {$app->fileno}."
                : "Application has been declined."
        ]);
    }

    public function planningRecommendation(Request $request)
    {
        $id = $request->input('id');
        $decision = $request->input('decision');
        $approval_date = date("Y-m-d H:i:s", strtotime(str_replace('T', ' ', $request->input('approval_date'))));
        $comments = $request->input('comments');
        $fileno = $request->input('fileno');
        $owner_name = $request->input('owner_name');
        $plot_location = $request->input('plot_location');
        $land_use = $request->input('land_use');
        $plot_size = $request->input('plot_size');

        $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

        if (!$app) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $updateData = [
            'planning_recommendation_status' => $decision == 'approve' ? 'Approved' : 'Declined',
            'planning_approval_date' => $approval_date,
            // 'planning_officer' => Auth::user()->name, 
            // Store the name of the planning officer
        ];

        if ($decision == 'decline') {
            $updateData['planning_comments'] = $comments;
        }

        DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);

        // Store the planning recommendation for printing
        $recommendationData = [
            'application_id' => $id,
            'fileno' => $fileno,
            'owner_name' => $owner_name,
            'plot_location' => $plot_location,
            'land_use' => $land_use,
            'plot_size' => $plot_size,
            'recommendation' => $decision == 'approve' ? 'Approved' : 'Declined',
            'comments' => $comments,
            'approved_by' => Auth::user()->name,
            'approval_date' => $approval_date,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Check if the planning_recommendations table exists, if not we can just skip this part
        try {
            DB::connection('sqlsrv')->table('dbo.planning_recommendations')->insert($recommendationData);
        } catch (\Exception $e) {
            // Table might not exist, continue without error
        }

        return response()->json([
            'message' => $decision == 'approve'
                ? "Planning recommendation has been approved for File Number {$app->fileno}."
                : "Planning recommendation has been declined.",
            'data' => $recommendationData
        ]);
    }

    public function departmentApproval(Request $request)
    {
        $id = $request->input('id');
        $department = $request->input('department');
        $action = $request->input('action');
        $comments = $request->input('comments');
        $approval_date = date("Y-m-d H:i:s", strtotime(str_replace('T', ' ', $request->input('approval_date'))));

        $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

        if (!$app) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $updateField = '';
        $message = '';

        switch ($department) {
            case 'finance':
                $updateField = 'finance_status';
                $message = "Finance approval has been updated";
                break;
            case 'survey':
                $updateField = 'survey_status';
                $message = "Survey approval has been updated";
                break;
            case 'lands':
                $updateField = 'lands_status';
                $message = "Lands approval has been updated";
                break;
            case 'deeds':
                $updateField = 'deeds_status';
                $message = "Deeds approval has been updated";
                break;
            default:
                return response()->json(['message' => 'Invalid department specified.'], 400);
        }

        $updateData = [
            $updateField => $action == 'approve' ? 'Approved' : 'Declined',
            "{$department}_approval_date" => $approval_date
        ];

        if ($action == 'decline') {
            $updateData["{$department}_comments"] = $comments;
        }

        DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);

        return response()->json([
            'message' => $message . " for File Number {$app->fileno}."
        ]);
    }

    public function getBillingData($id)
    {
        try {
            $application = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

            if (!$application) {
                \Log::error("getBillingData: Application with ID {$id} not found");
                return response()->json(['error' => 'Application not found'], 404);
            }

            \Log::info("getBillingData: Successfully retrieved data for application ID {$id}");
            return response()->json([
                'application_fee' => $application->application_fee,
                'processing_fee' => $application->processing_fee,
                'site_plan_fee' => $application->site_plan_fee,
                'payment_date' => $application->payment_date,
                'receipt_number' => $application->receipt_number
            ]);
        } catch (\Exception $e) {
            \Log::error("getBillingData: Error retrieving data for application ID {$id}: " . $e->getMessage());
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }


    public function getBillingData2($id)
    {
        try {
            $application = DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->first();

            if (!$application) {
                \Log::error("getBillingData2: Application with ID {$id} not found");
                return response()->json(['error' => 'Application not found'], 404);
            }

            \Log::info("getBillingData2: Successfully retrieved data for application ID {$id}");
            return response()->json([
                'application_fee' => $application->application_fee,
                'processing_fee' => $application->processing_fee,
                'site_plan_fee' => $application->site_plan_fee,
                'payment_date' => $application->payment_date,
                'receipt_number' => $application->receipt_number
            ]);
        } catch (\Exception $e) {
            \Log::error("getBillingData2: Error retrieving data for application ID {$id}: " . $e->getMessage());
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function saveBillingData(Request $request)
    {
        try {
            $request->validate([
                'application_id' => 'required|integer',
                'application_fee' => 'nullable|numeric',
                'processing_fee' => 'nullable|numeric',
                'site_plan_fee' => 'nullable|numeric',
                'payment_date' => 'nullable|date',
                'receipt_number' => 'nullable|string',
            ]);

            $id = $request->input('application_id');
            \Log::info("saveBillingData: Attempting to save billing data for application ID {$id}");

            $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

            if (!$app) {
                \Log::error("saveBillingData: Application with ID {$id} not found");
                return response()->json(['error' => 'Application not found'], 404);
            }

            $updateData = [
                'application_fee' => $request->input('application_fee'),
                'processing_fee' => $request->input('processing_fee'),
                'site_plan_fee' => $request->input('site_plan_fee'),
                'payment_date' => $request->input('payment_date'),
                'receipt_number' => $request->input('receipt_number'),
            ];

            DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);
            \Log::info("saveBillingData: Successfully saved billing data for application ID {$id}");

            return response()->json([
                'message' => "Billing information has been updated for File Number {$app->fileno}."
            ]);
        } catch (\Exception $e) {
            \Log::error("saveBillingData: Error saving data: " . $e->getMessage());
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save E-Registry information for an application
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveERegistry(Request $request)
    {
        try {
            $request->validate([
                'application_id' => 'required|integer',
                'file_location' => 'nullable|string',
                'commission_date' => 'nullable|date',
                'decommission_date' => 'nullable|date',
            ]);

            $id = $request->input('application_id');
            \Log::info("saveERegistry: Attempting to save E-Registry data for application ID {$id}");

            $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

            if (!$app) {
                \Log::error("saveERegistry: Application with ID {$id} not found");
                return response()->json(['error' => 'Application not found'], 404);
            }

            $updateData = [
                'file_location' => $request->input('file_location'),
                'file_commission_date' => $request->input('commission_date'),
                'file_decommission_date' => $request->input('decommission_date'),
                'updated_at' => now(),
            ];

            DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);
            \Log::info("saveERegistry: Successfully saved E-Registry data for application ID {$id}");

            return response()->json([
                'message' => "E-Registry information has been updated for File Number {$app->fileno}."
            ]);
        } catch (\Exception $e) {
            \Log::error("saveERegistry: Error saving data: " . $e->getMessage());
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function Veiwrecords(Request $request)
    {
        $id = $request->query('id');
        $PageTitle = 'Application Details ';
        $PageDescription = 'View Application Details';
        if (!$id) {
            return redirect()->route('sectionaltitling.index')->with('error', 'No record ID provided');
        }

        $application = DB::connection('sqlsrv')
            ->table('dbo.mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return redirect()->route('sectionaltitling.index')->with('error', 'Record not found');
        }

        // Get titles from lookup table
        $titles = DB::connection('sqlsrv')
            ->table('dbo.titles')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        return view('sectionaltitling.viewrecorddetail', compact('application', 'titles', 'PageTitle', 'PageDescription'));
    }

    public function edit($id)
    {
        $PageTitle = 'Edit Application';
        $PageDescription = 'Edit Application Details';

        $application = DB::connection('sqlsrv')
            ->table('dbo.mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return redirect()->route('sectionaltitling.index')->with('error', 'Record not found');
        }

        // Check if application is approved (both application status and planning recommendation)
        $isApproved = ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved');

        if ($isApproved) {
            return redirect()->route('sectionaltitling.viewrecorddetail', ['id' => $id])
                ->with('error', 'Cannot edit application - Both Application Status and Planning Recommendation have been approved.');
        }

        return view('sectionaltitling.edit', compact('application', 'PageTitle', 'PageDescription'));
    }

    public function update(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return redirect()->route('sectionaltitling.index')->with('error', 'Record not found');
            }

            // Check if application is approved (both application status and planning recommendation)
            $isApproved = ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved');

            if ($isApproved) {
                return redirect()->route('sectionaltitling.viewrecorddetail', ['id' => $id])
                    ->with('error', 'Cannot edit application - Both Application Status and Planning Recommendation have been approved.');
            }

            $rules = [
                'applicant_type' => 'nullable|in:individual,corporate,multiple',
                'applicant_title' => 'nullable|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'surname' => 'nullable|string|max:255',
                'corporate_name' => 'nullable|string|max:255',
                'rc_number' => 'nullable|string|max:255',
                'multiple_owners_names' => 'nullable|array',
                'multiple_owners_names.*' => 'nullable|string',
                'address_house_no' => 'nullable|string|max:255',
                'address_street_name' => 'nullable|string|max:255',
                'address_district' => 'nullable|string|max:255',
                'address_lga' => 'nullable|string|max:255',
                'address_state' => 'nullable|string|max:255',
                'phone_number' => 'nullable',
                'email' => 'nullable|email',
                'scheme_no' => 'nullable|string|max:255',
                'property_house_no' => 'nullable|string|max:255',
                'property_plot_no' => 'nullable|string|max:255',
                'property_street_name' => 'nullable|string|max:255',
                'property_district' => 'nullable|string|max:255',
                'property_lga' => 'nullable|string|max:255',
                'property_state' => 'nullable|string|max:255',
                'NoOfUnits' => 'nullable|integer',
                'NoOfBlocks' => 'nullable|integer',
                'NoOfSections' => 'nullable|integer',
                'plot_size' => 'nullable|string|max:255',
                'land_use' => 'nullable|string|max:255',
                'residential_type' => 'nullable|string|max:255',
                'commercial_type' => 'nullable|string|max:255',
                'industrial_type' => 'nullable|string|max:255',
                'ownership_type' => 'nullable|string|max:255',
                'ownership_type_others_text' => 'nullable|string',
                'application_fee' => 'nullable|numeric',
                'processing_fee' => 'nullable|numeric',
                'site_plan_fee' => 'nullable|numeric',
                'application_fee_payment_date' => 'nullable|date',
                'processing_fee_payment_date' => 'nullable|date',
                'site_plan_fee_payment_date' => 'nullable|date',
                'application_fee_receipt_number' => 'nullable|string|max:255',
                'processing_fee_receipt_number' => 'nullable|string|max:255',
                'site_plan_fee_receipt_number' => 'nullable|string|max:255',
                'payment_date' => 'nullable|date',
                'receipt_number' => 'nullable|string|max:255',
                'comments' => 'nullable|string',
                'shared_areas' => 'nullable|array',
                'shared_areas.*' => 'nullable|string',
                'passport' => 'nullable|image|max:5120',
                'id_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'rc_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'application_letter' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'building_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'architectural_design' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'ownership_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'survey_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
            ];

            $validated = $request->validate($rules);

            // Handle passport upload
            if ($request->hasFile('passport')) {
                // Delete old passport if exists
                if ($application->passport) {
                    Storage::disk('public')->delete($application->passport);
                }
                $validated['passport'] = $request->file('passport')->store('passports', 'public');
            }

            // Handle ID document upload
            if ($request->hasFile('id_document')) {
                // Delete old ID document if exists
                if ($application->id_document) {
                    Storage::disk('public')->delete($application->id_document);
                }
                $validated['id_document'] = $request->file('id_document')->store('id_documents', 'public');
            }

            // Handle RC document upload
            if ($request->hasFile('rc_document')) {
                if ($application->rc_document) {
                    Storage::disk('public')->delete($application->rc_document);
                }
                $validated['rc_document'] = $request->file('rc_document')->store('rc_documents', 'public');
            }

            // Format phone numbers if it's an array
            if ($request->has('phone_number') && is_array($request->input('phone_number'))) {
                $validated['phone_number'] = implode(', ', array_filter($request->input('phone_number')));
            }

            // Handle multiple owners names
            if ($request->has('multiple_owners_names') && is_array($request->input('multiple_owners_names'))) {
                $multipleOwnersNames = array_filter($request->input('multiple_owners_names'));
                $validated['multiple_owners_names'] = !empty($multipleOwnersNames) ? json_encode($multipleOwnersNames) : null;
            }

            // Handle shared areas
            if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
                $sharedAreas = array_values(array_filter($request->input('shared_areas'), function ($value) {
                    return $value !== null && $value !== '';
                }));
                $validated['shared_areas'] = !empty($sharedAreas) ? json_encode($sharedAreas) : null;
            } else {
                $validated['shared_areas'] = null;
            }

            // Handle supporting documents (application_letter, building_plan, etc.)
            $existingDocuments = $application->documents
                ? (is_array($application->documents) ? $application->documents : json_decode($application->documents, true))
                : [];
            if (!is_array($existingDocuments)) {
                $existingDocuments = [];
            }

            $documentFields = [
                'application_letter',
                'building_plan',
                'architectural_design',
                'ownership_document',
                'survey_plan',
            ];

            foreach ($documentFields as $docField) {
                if ($request->hasFile($docField)) {
                    if (!empty($existingDocuments[$docField]['path'])) {
                        Storage::disk('public')->delete($existingDocuments[$docField]['path']);
                    }

                    $file = $request->file($docField);
                    $path = $file->store('documents', 'public');

                    $existingDocuments[$docField] = [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'type' => $file->getClientOriginalExtension(),
                        'uploaded_at' => now()->toDateTimeString(),
                    ];

                    if ($docField === 'survey_plan') {
                        $validated['survey_plan'] = $path;
                    }
                }
            }

            if (!empty($existingDocuments)) {
                $validated['documents'] = json_encode($existingDocuments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $moneyFields = ['application_fee', 'processing_fee', 'site_plan_fee'];
            foreach ($moneyFields as $moneyField) {
                if (array_key_exists($moneyField, $validated)) {
                    $validated[$moneyField] = $this->normalizeMoneyValue($validated[$moneyField]);
                }
            }

            $validated['updated_at'] = now();

            // Update the application
            DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $id)
                ->update($validated);

            Log::info('Application updated successfully', [
                'application_id' => $id,
                'updated_by' => Auth::user() ? Auth::user()->first_name : null
            ]);

            return redirect()->route('sectionaltitling.viewrecorddetail', ['id' => $id])
                ->with('success', 'Application updated successfully!');

        } catch (Exception $e) {
            Log::error('Error updating application', [
                'error' => $e->getMessage(),
                'application_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating application: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }

            // Check if application is approved (both application status and planning recommendation)
            $isApproved = ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved');

            if ($isApproved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete application - Both Application Status and Planning Recommendation have been approved.'
                ], 403);
            }

            // Delete associated files
            if ($application->passport) {
                Storage::disk('public')->delete($application->passport);
            }
            if ($application->id_document) {
                Storage::disk('public')->delete($application->id_document);
            }

            // Delete documents if they exist
            if ($application->documents) {
                $documents = json_decode($application->documents, true);
                if (is_array($documents)) {
                    foreach ($documents as $document) {
                        if (isset($document['path'])) {
                            Storage::disk('public')->delete($document['path']);
                        }
                    }
                }
            }

            // Delete the application record
            DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $id)
                ->delete();

            Log::info('Application deleted successfully', [
                'application_id' => $id,
                'deleted_by' => Auth::user() ? Auth::user()->first_name : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application deleted successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting application', [
                'error' => $e->getMessage(),
                'application_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting application: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Save Application Data for Planning Recommendation
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveApplicationData(Request $request)
    {
        $redirectTarget = $this->determineRedirectTarget($request, route('programmes.approvals.planning_recomm'));
        $expectsJson = $request->expectsJson() || $request->ajax();

        try {
            $request->validate([
                'application_id' => 'required|integer',
                'lkn_number' => 'nullable|string|max:255',
                'tp_plan_number' => 'nullable|string|max:255',
                'approved_plan_number' => 'nullable|string|max:255',
                'scheme_number' => 'nullable|string|max:255',
                'property_house_no' => 'nullable|string|max:255',
                'property_plot_no' => 'nullable|string|max:255',
            ]);

            $id = $request->input('application_id');
            Log::info("saveApplicationData: Attempting to save application data for application ID {$id}");

            // Check if application exists
            $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();

            if (!$app) {
                Log::error("saveApplicationData: Application with ID {$id} not found");
                $message = __('Application not found');

                if ($expectsJson) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 404);
                }

                return redirect()->to($redirectTarget)
                    ->with('error', $message)
                    ->with('swal_error', true);
            }

            // Prepare update data for mother_applications table
            $updateData = [
                'scheme_no' => $request->input('scheme_number'),
                'property_house_no' => $request->input('property_house_no'),
                'property_plot_no' => $request->input('property_plot_no'),
                'updated_at' => now(),
            ];

            // Remove null values to avoid overwriting existing data with nulls
            $updateData = array_filter($updateData, function ($value) {
                return $value !== null && $value !== '';
            });

            // Update mother_applications table
            if (!empty($updateData)) {
                DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->update($updateData);
            }

            // Handle survey record data (LKN, TP Plan, Approved Plan numbers)
            $surveyData = [
                'tp_plan_no' => $request->input('lkn_number') ?: $request->input('tp_plan_number'),
                'approved_plan_no' => $request->input('approved_plan_number'),
                'updated_at' => now(),
            ];

            // Remove null values
            $surveyData = array_filter($surveyData, function ($value) {
                return $value !== null && $value !== '';
            });

            if (!empty($surveyData)) {
                // Check if survey record exists
                $surveyRecord = DB::connection('sqlsrv')
                    ->table('surveyCadastralRecord')
                    ->where('application_id', $id)
                    ->first();

                if ($surveyRecord) {
                    // Update existing record
                    DB::connection('sqlsrv')
                        ->table('surveyCadastralRecord')
                        ->where('application_id', $id)
                        ->update($surveyData);
                } else {
                    // Create new record
                    $surveyData['application_id'] = $id;
                    $surveyData['created_at'] = now();
                    DB::connection('sqlsrv')
                        ->table('surveyCadastralRecord')
                        ->insert($surveyData);
                }
            }

            Log::info("saveApplicationData: Successfully saved application data for application ID {$id}");

            $message = "Application data has been updated successfully for File Number {$app->fileno}.";

            if ($expectsJson) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->to($redirectTarget)
                ->with('success', $message)
                ->with('swal_success', true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("saveApplicationData: Validation error: " . json_encode($e->errors()));
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }

            $firstError = collect($e->errors())->flatten()->first() ?? __('Validation failed');

            return redirect()->to($redirectTarget)
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', $firstError)
                ->with('swal_error', true);
        } catch (\Exception $e) {
            Log::error("saveApplicationData: Error saving data: " . $e->getMessage());
            Log::error("saveApplicationData: Stack trace: " . $e->getTraceAsString());
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->to($redirectTarget)
                ->with('error', __('Unable to save application data at the moment.'))
                ->with('swal_error', true);
        }
    }

    /**
     * Normalize money/string values into numeric strings acceptable for DB.
     */
    protected function normalizeMoneyValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $value;
        }

        $sanitized = preg_replace('/[^\d\.\-]/', '', (string) $value);
        return $sanitized === '' ? null : $sanitized;
    }

    private function determineRedirectTarget(Request $request, string $fallback): string
    {
        $candidate = $request->input('redirect_to');

        if ($candidate && Str::startsWith($candidate, url('/'))) {
            return $candidate;
        }

        $previous = url()->previous();
        if ($previous && Str::startsWith($previous, url('/'))) {
            return $previous;
        }

        return $fallback;
    }
}
