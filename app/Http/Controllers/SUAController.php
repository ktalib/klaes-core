<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SUAFileNumberService;
use App\Services\UnitFileIndexingService;
use Exception;

class SUAController extends Controller
{
    protected $suaFileNumberService;

    public function __construct(SUAFileNumberService $suaFileNumberService)
    {
        $this->suaFileNumberService = $suaFileNumberService;
    }

    /**
     * Display a listing of SUA applications
     */
    public function index()
    {
        $PageTitle = 'Standalone Unit Applications (SUA)';
        $PageDescription = 'Manage SUA Applications';

        try {
            // Get all SUA applications from subapplications table
            $suaApplications = DB::connection('sqlsrv')
                ->table('subapplications')
                ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                    $join->on('jsi.sub_application_id', '=', 'subapplications.id');
                })
                ->leftJoin('users as creator', DB::raw('TRY_CAST(subapplications.created_by AS INT)'), '=', 'creator.id')
                ->where('subapplications.unit_type', 'SUA')
                ->select([
                    'subapplications.*',
                    // Creator name for the "Created By" column's profile card — created_by
                    // holds a user id, so the name has to come from users.
                    DB::raw("LTRIM(RTRIM(COALESCE(creator.first_name, '') + ' ' + COALESCE(creator.last_name, ''))) as created_by_name"),
                    'jsi.is_generated as jsi_is_generated',
                    'jsi.is_submitted as jsi_is_submitted',
                    'jsi.is_approved as jsi_is_approved',
                    'jsi.approved_at as jsi_approved_at',
                    'jsi.inspection_date as jsi_inspection_date'
                ])
                ->orderBy('subapplications.created_at', 'desc')
                ->orderBy('subapplications.id', 'desc')
                ->paginate(20);

            // Get statistics
            $stats = [
                'total' => DB::connection('sqlsrv')->table('subapplications')->where('unit_type', 'SUA')->count(),
                'approved' => DB::connection('sqlsrv')->table('subapplications')->where('unit_type', 'SUA')->where('application_status', 'Approved')->count(),
                'pending' => DB::connection('sqlsrv')->table('subapplications')->where('unit_type', 'SUA')->where('application_status', 'Pending')->count(),
                'rejected' => DB::connection('sqlsrv')->table('subapplications')->where('unit_type', 'SUA')->where('application_status', 'Rejected')->count(),
            ];

            return view('sua.index', compact('PageTitle', 'PageDescription', 'suaApplications', 'stats'));

        } catch (Exception $e) {
            Log::error('Error fetching SUA applications: ' . $e->getMessage());
            return back()->with('error', 'Failed to load SUA applications.');
        }
    }

    /**
     * Show the form for creating a new SUA
     */
    /**
     * Show the form for creating a new SUA
     */
    public function create(Request $request)
    {
        $landUse = $request->query('landuse', 'Residential');

        // We no longer generate file numbers here. They are selected from the dropdown.

        return view('sectionaltitling.sub_application', [
            'PageTitle' => 'Standalone Unit Application',
            'PageDescription' => 'Create SUA',
            'selectedLandUse' => $landUse,
            'primaryFileNo' => null,
            'suaFileNo' => null,
            'mlsFileNo' => null,
            'isSUA' => true
        ]);
    }

    /**
     * Store a newly created SUA in storage
     */
    public function store(Request $request)
    {
        // Get the land use from the form field
        $landUse = $request->input('land_use', 'Residential');

        // Base validation rules
        $rules = [
            'allocation_source' => 'required|string|in:State Government,Local Government',
            'allocation_entity' => 'required|string',
            // Answered on the SuA File Number Commissioning form and read-only here,
            // so it cannot be typed in if that form left it blank.
            'allocation_ref_no' => 'nullable|string|max:255',
            'property_location' => 'required|string|max:1000',
            'land_use' => 'required|string|in:Residential,Commercial,Industrial',
            'unit_type' => 'required|string',
            'unit_area' => 'nullable|numeric|min:0',
            'scheme_no' => 'nullable|string|max:100',
            'date_captured' => 'nullable|date', // Date Captured field
            'payment_date' => 'required|date',
            'receipt_number' => 'required|string',
            // Individual payment fields
            'processing_fee_payment_date' => 'nullable|date',
            'processing_fee_receipt_no' => 'nullable|string|max:100',
            'application_fee_payment_date' => 'nullable|date',
            'application_fee_receipt_no' => 'nullable|string|max:100',
            'survey_fee_payment_date' => 'nullable|date',
            'survey_fee_receipt_no' => 'nullable|string|max:100',
            'mls_fileno' => 'nullable|string',
            'unit_number' => 'nullable|string',
            'unit_size' => 'nullable|string',
            'application_fee' => 'nullable|numeric',
            'processing_fee' => 'nullable|numeric',
            'site_plan_fee' => 'nullable|numeric',
            'applicant_type' => 'required|string|in:individual,corporate,multiple',
            'applicant_title' => 'nullable|string',
            'identification_type' => 'nullable|string',
            'block_number' => 'nullable|string',
            'floor_number' => 'nullable|string',
            'address_house_no' => 'nullable|string',
            'address_street_name' => 'nullable|string',
            'address_district' => 'nullable|string',
            'address_lga' => 'nullable|string',
            'address_state' => 'nullable|string',
            'passport' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'multiple_owners_passport.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'shared_areas' => 'nullable|array',
            'other_areas_detail' => 'nullable|string|max:500',
            // Validate the selected file number ID
            'sua_file_id' => 'required|integer|exists:sqlsrv.st_file_numbers,id',
            'improvement_value' => 'nullable|numeric|min:0',
        ];

        // Conditional validation based on applicant type
        $applicantType = $request->input('applicant_type');

        if ($applicantType === 'individual') {
            $rules['first_name'] = 'required|string|max:255';
            $rules['middle_name'] = 'nullable|string|max:255';
            $rules['surname'] = 'required|string|max:255';
            $rules['email'] = 'nullable|email';
            $rules['phone_number'] = 'nullable|array';
        } elseif ($applicantType === 'corporate') {
            $rules['corporate_name'] = 'required|string|max:255';

            $rules['rc_number'] = 'required|string';
        } elseif ($applicantType === 'multiple') {
            $rules['multiple_owners_names'] = 'required|array|min:1';
            $rules['multiple_owners_names.*'] = 'required|string|max:255';
            $rules['multiple_owners_address'] = 'required|array|min:1';
            $rules['multiple_owners_address.*'] = 'required|string';
        }

        $request->validate($rules);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Retrieve the selected file number record
            $stFileNumber = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('id', $request->input('sua_file_id'))
                ->first();

            if (!$stFileNumber) {
                throw new Exception("Selected file number is invalid or not found.");
            }

            // Check if already used (double check race condition)
            if (!empty($stFileNumber->subapplication_id)) {
                throw new Exception("This file number has already been allocated to another application.");
            }

            $primaryFileNo = $stFileNumber->np_fileno;
            $suaFileNo = $stFileNumber->fileno;
            // Use inputted MLS if provided, otherwise fallback to ST record
            $mlsFileNo = $request->input('mls_fileno') ?: $stFileNumber->mls_fileno;


            // Handle phone number array
            if ($request->has('phone_number') && is_array($request->input('phone_number'))) {
                $phoneNumber = implode(', ', array_filter($request->input('phone_number')));
            } elseif ($request->has('phone_number')) {
                $phoneNumber = $request->input('phone_number');
            } else {
                $phoneNumber = null;
            }

            // Create SUA application data using correct field names
            $suaData = [
                'main_application_id' => null, // No parent for SUA
                'fileno' => $suaFileNo,
                'np_fileno' => $primaryFileNo, // Store primary file number
                'mls_fileno' => $mlsFileNo,
                'is_sua_unit' => 1,
                'allocation_source' => $request->allocation_source,
                'allocation_entity' => $request->allocation_entity,
                'allocation_ref_no' => strtoupper(trim((string) $request->allocation_ref_no)),
                'property_location' => $request->property_location,
                'land_use' => $landUse, // Use the processed land use value
                'unit_type' => 'SUA',
                'unit_size' => $request->unit_size,
                'unit_number' => $request->unit_number,
                'floor_number' => $request->floor_number,
                'block_number' => $request->block_number,
                'scheme_no' => $request->scheme_no,
                'application_date' => $request->application_date,
                'date_captured' => $request->date_captured ?? now()->format('Y-m-d'), // Date Captured field

                // Unit location details
                'unit_district' => $request->unit_district,
                'unit_lga' => $request->unit_lga,
                'unit_state' => $request->unit_state ?: 'KANO',

                // Address information
                'address_house_no' => $request->address_house_no,
                'address_street_name' => $request->address_street_name,
                'address_district' => $request->address_district,
                'address_state' => $request->address_state,
                'address_lga' => $request->address_lga,

                // Additional fields
                'application_comment' => $request->application_comment,
                'improvement_value' => $request->improvement_value,

                // Process shared areas (enhanced version with custom areas support)
                'shared_areas' => $this->processSharedAreas($request),

            ];

            // Handle applicant information based on type
            $applicantData = [
                'applicant_type' => $request->applicant_type,
                'applicant_title' => $request->applicant_title,
                'identification_type' => $request->identification_type,

            ];

            // Add type-specific fields
            if ($request->applicant_type === 'individual') {
                $applicantData['first_name'] = $request->first_name;
                $applicantData['middle_name'] = $request->middle_name;
                $applicantData['surname'] = $request->surname;
                $applicantData['email'] = $request->email;
                $applicantData['phone_number'] = $phoneNumber;
            } elseif ($request->applicant_type === 'corporate') {
                $applicantData['first_name'] = null;
                $applicantData['surname'] = null;
                $applicantData['email'] = $request->email ?? $request->corporate_email;
                $applicantData['phone_number'] = $phoneNumber;
                $applicantData['corporate_name'] = $request->corporate_name;
                $applicantData['rc_number'] = $request->rc_number;
            } elseif ($request->applicant_type === 'multiple') {
                // For multiple owners, store first owner in main fields, others in JSON
                $ownerNames = $request->multiple_owners_names;
                $ownerAddresses = $request->multiple_owners_address;

                $applicantData['first_name'] = $ownerNames[0] ?? 'Multiple Owners';
                $applicantData['surname'] = 'Multiple Ownership';
                $applicantData['email'] = $request->multiple_owners_email[0] ?? null;
                $applicantData['phone_number'] = $request->multiple_owners_phone[0] ?? null;

                // Store all owners data as JSON
                $allOwners = [];
                for ($i = 0; $i < count($ownerNames); $i++) {
                    $allOwners[] = [
                        'name' => $ownerNames[$i] ?? '',
                        'address' => $ownerAddresses[$i] ?? '',
                        'email' => $request->multiple_owners_email[$i] ?? '',
                        'phone' => $request->multiple_owners_phone[$i] ?? ''
                    ];
                }
                $applicantData['multiple_owners_data'] = json_encode($allOwners);
            }

            // Handle document uploads
            $documentsData = [];
            $documentFields = ['application_letter', 'building_plan', 'architectural_design', 'surveyor_report', 'ownership_document', 'deed_of_assignment'];

            foreach ($documentFields as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $fileName = time() . '_' . $field . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('documents', $fileName, 'public');

                    $documentsData[$field] = [
                        'path' => $filePath,
                        'original_name' => $file->getClientOriginalName(),
                        'type' => $file->getClientOriginalExtension(),
                        'uploaded_at' => now()->format('Y-m-d H:i:s')
                    ];
                }
            }

            if (!empty($documentsData)) {
                $suaData['documents'] = json_encode($documentsData);
            } else {
                $suaData['documents'] = json_encode([]);
            }

            // Handle passport file uploads
            $passportPath = null;
            $multipleOwnersPassportPaths = [];

            if ($request->hasFile('passport')) {
                $passportFile = $request->file('passport');
                $passportFileName = time() . '_passport.' . $passportFile->getClientOriginalExtension();
                $passportPath = $passportFile->storeAs('passports', $passportFileName, 'public');
            }

            if ($request->hasFile('multiple_owners_passport')) {
                $passportFiles = $request->file('multiple_owners_passport');
                foreach ($passportFiles as $index => $passportFile) {
                    if ($passportFile) {
                        $passportFileName = time() . '_owner_' . $index . '_passport.' . $passportFile->getClientOriginalExtension();
                        $passportPath = $passportFile->storeAs('passports', $passportFileName, 'public');
                        $multipleOwnersPassportPaths[] = $passportPath;
                    }
                }
            }

            // Add payment and status information to SUA data
            $suaData = array_merge($suaData, [
                // Payment information
                'application_fee' => $request->application_fee,
                'processing_fee' => $request->processing_fee,
                'site_plan_fee' => $request->site_plan_fee,
                'payment_date' => $request->payment_date,
                'receipt_number' => $request->receipt_number,
                // Individual payment fields
                'processing_fee_payment_date' => $request->processing_fee_payment_date,
                'processing_fee_receipt_no' => $request->processing_fee_receipt_no,
                'application_fee_payment_date' => $request->application_fee_payment_date,
                'application_fee_receipt_no' => $request->application_fee_receipt_no,
                'survey_fee_payment_date' => $request->survey_fee_payment_date,
                'survey_fee_receipt_no' => $request->survey_fee_receipt_no,
                'Payment_Status' => 'Paid', // Assuming paid since receipt provided

                // Passport files
                'passport' => $passportPath,
                'multiple_owners_passport' => !empty($multipleOwnersPassportPaths) ? json_encode($multipleOwnersPassportPaths) : null,

                // Status fields
                'application_status' => 'Pending',
                'planning_recommendation_status' => 'Pending',
                'memo_status' => 'Pending',
                'deeds_status' => 'Pending',
                'deeds_completion_status' => 'Pending',

                // Audit fields
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'is_deleted' => 0
            ]);

            // Merge applicant data with SUA data
            $suaData = array_merge($suaData, $applicantData);

            $suaId = DB::connection('sqlsrv')->table('subapplications')->insertGetId($suaData);

            // Save shared utilities to shared_utilities table
            $this->saveSharedUtilitiesToTable($request, $request->main_application_id, $suaId);


            // Update st_file_numbers and mark as used
            DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('id', $stFileNumber->id)
                ->update([
                    'subapplication_id' => $suaId,
                    'status' => 'USED',
                    'used_at' => now(),
                    'updated_at' => now()
                ]);


            // Prepare FileName based on applicant type
            $fileName = $this->getApplicantName($request);

            // Ensure EDMS file indexing for SUA
            try {
                /** @var UnitFileIndexingService $unitIndexing */
                $unitIndexing = app(UnitFileIndexingService::class);

                $firstOwnerName = null;
                if ($request->applicant_type === 'multiple' && is_array($request->multiple_owners_names)) {
                    $firstOwnerName = $request->multiple_owners_names[0] ?? null;
                }

                $unitIndexing->ensure([
                    'main_application_id' => null,
                    'subapplication_id' => $suaId,
                    'file_number' => $suaFileNo,
                    'land_use_type' => $landUse,
                    'plot_number' => null,
                    'tp_no' => null,
                    'lpkn_no' => null,
                    'location' => $request->property_location,
                    'district' => $request->address_district,
                    'lga' => $request->address_lga,
                    'st_batch_no' => $request->input('st_batch_no'),
                    'tracking_id' => $request->input('tracking_id'),
                    'applicant_type' => $request->applicant_type,
                    'corporate_name' => $request->corporate_name,
                    'first_owner_name' => $firstOwnerName,
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name,
                    'surname' => $request->surname,
                ]);
            } catch (Exception $edmsError) {
                Log::warning('Failed to create EDMS file indexing for SUA', [
                    'subapplication_id' => $suaId,
                    'error' => $edmsError->getMessage(),
                ]);
            }

            // Insert into fileNumber table
            DB::connection('sqlsrv')->table('fileNumbers')->insert([
                'sub_application_id' => $suaId,
                'mlsfNo' => $mlsFileNo,
                'st_file_no' => $suaFileNo,
                'FileName' => $fileName,
                'created_at' => now(),
                'updated_at' => now(),
                'location' => $request->property_location,
                'type' => 'ST',
                'is_deleted' => 0,
                'SOURCE' => 'ST'
            ]);

            DB::connection('sqlsrv')->commit();

            $redirectUrl = route('sua.index');
            $successMessage = 'SUA Application created successfully';

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => $redirectUrl,
                    'sub_application_id' => $suaId,
                    'unit_file_no' => $suaFileNo,
                    'swal_success' => true,
                ]);
            }

            // Redirect to SUA index with SweetAlert success message
            return redirect()->to($redirectUrl)
                ->with('success', $successMessage)
                ->with('swal_success', true);

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('SUA Application creation failed: ' . $e->getMessage());

            $errorMessage = 'Failed to create SUA application: ' . $e->getMessage();

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'swal_error' => true,
                ], 500);
            }

            // Redirect back with SweetAlert error message
            return redirect()->back()
                ->withInput()
                ->with('error', $errorMessage)
                ->with('swal_error', true);
        }
    }

    /**
     * Generate the next available SUA file numbers
     */
    public function getNextFileNo(Request $request)
    {
        try {
            $landUse = $request->query('landuse', 'Residential');
            $fileNumbers = $this->suaFileNumberService->generateSUAFileNumbers($landUse);

            return response()->json([
                'success' => true,
                'primary_fileno' => $fileNumbers['main'],
                'sua_fileno' => $fileNumbers['sua'],
                'mls_fileno' => $fileNumbers['mls']
            ]);

        } catch (Exception $e) {
            Log::error('Error generating SUA file number: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate file number'
            ], 500);
        }
    }



    /**
     * Display the specified SUA application
     */
    public function show($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->where('unit_type', 'SUA')
                ->first();

            if (!$application) {
                return redirect()->route('sua.index')
                    ->with('error', 'SUA Application not found.');
            }

            $PageTitle = 'SUA Details - ' . $application->fileno;
            $PageDescription = 'View SUA Application Details';

            // Since SUA applications don't have mother applications, 
            // we need to set mother fields to null to avoid undefined variable errors
            $application->mother_applicant_type = null;
            $application->mother_applicant_title = null;
            $application->mother_first_name = null;
            $application->mother_middle_name = null;
            $application->mother_surname = null;
            $application->mother_passport = null;
            $application->mother_corporate_name = null;
            $application->mother_rc_number = null;
            $application->mother_multiple_owners_names = null;
            $application->mother_multiple_owners_passport = null;
            $application->mother_address = null;
            $application->mother_phone_number = null;
            $application->mother_email = null;
            $application->mother_land_use = null;
            $application->mother_plot_size = null;
            $application->mother_fileno = null;
            $application->mother_id = null;
            $application->mother_property_house_no = null;
            $application->mother_property_plot_no = null;
            $application->mother_property_street_name = null;
            $application->mother_property_district = null;
            $application->mother_property_lga = null;
            $application->main_application_id = null; // SUA has no main application

            // Initialize arrays for multiple owners (SUA specific)
            if (!empty($application->multiple_owners_names)) {
                // Try to decode as JSON first
                $decoded = json_decode($application->multiple_owners_names, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $application->multiple_owners_names_array = $decoded;
                } else {
                    $application->multiple_owners_names_array = array_map('trim', explode(',', $application->multiple_owners_names));
                }
            } else {
                $application->multiple_owners_names_array = [];
            }

            // Parse multiple_owners_passport (JSON)
            if (!empty($application->multiple_owners_passport)) {
                $application->multiple_owners_passport_array = json_decode($application->multiple_owners_passport, true) ?: [];
            } else {
                $application->multiple_owners_passport_array = [];
            }

            // Initialize mother multiple owners arrays (empty for SUA)
            $application->mother_multiple_owners_names_array = [];

            return view('sua.show', compact('PageTitle', 'PageDescription', 'application'));

        } catch (Exception $e) {
            Log::error('Error fetching SUA details: ' . $e->getMessage());
            return redirect()->route('sua.index')
                ->with('error', 'Failed to load SUA details.');
        }
    }

    /**
     * Show the form for editing the specified SUA
     */
    public function edit($id)
    {
        return redirect()->route('subapplication.index', ['sub_application_id' => $id, 'is_sua' => 1]);
    }

    /**
     * Update the specified SUA in storage
     */
    public function update(Request $request, $id)
    {
        // Get the land use from either field (prioritize hidden field for disabled select)
        $landUse = $request->input('land_use_hidden') ?: $request->input('land_use', 'Residential');

        // Base validation rules
        $rules = [
            'allocation_source' => 'required|string|in:State Government,Local Government',
            'allocation_entity' => 'required|string',
            'property_location' => 'required|string|max:1000',
            'land_use_hidden' => 'required|string|in:Residential,Commercial,Industrial',
            'unit_type' => 'required|in:SUA',
            'unit_area' => 'nullable|numeric|min:0',
            'scheme_no' => 'required|string',
            'payment_date' => 'required|date',
            'receipt_number' => 'required|string',
            // Individual payment fields
            'processing_fee_payment_date' => 'nullable|date',
            'processing_fee_receipt_no' => 'nullable|string|max:100',
            'application_fee_payment_date' => 'nullable|date',
            'application_fee_receipt_no' => 'nullable|string|max:100',
            'survey_fee_payment_date' => 'nullable|date',
            'survey_fee_receipt_no' => 'nullable|string|max:100',
            'mls_fileno' => 'nullable|string',
            'unit_number' => 'nullable|string',
            'unit_size' => 'nullable|string',
            'application_fee' => 'nullable|numeric',
            'processing_fee' => 'nullable|numeric',
            'site_plan_fee' => 'nullable|numeric',
            'applicant_type' => 'required|string|in:individual,corporate,multiple',
            'applicant_title' => 'nullable|string',
            'identification_type' => 'nullable|string',
            'block_number' => 'nullable|string',
            'floor_number' => 'nullable|string',
            'shared_areas' => 'nullable|array',
            'other_areas_detail' => 'nullable|string|max:500',
            'improvement_value' => 'nullable|numeric|min:0'
        ];

        // Conditional validation based on applicant type
        $applicantType = $request->input('applicant_type');

        if ($applicantType === 'individual') {
            $rules['first_name'] = 'required|string|max:255';
            $rules['middle_name'] = 'nullable|string|max:255';
            $rules['surname'] = 'required|string|max:255';
            $rules['email'] = 'nullable|email';
            $rules['phone_number'] = 'nullable|array';
        } elseif ($applicantType === 'corporate') {
            $rules['corporate_name'] = 'required|string|max:255';
            $rules['corporate_email'] = 'required|email';
            $rules['corporate_phone'] = 'required|string';
            $rules['corporate_address'] = 'required|string';
            $rules['rc_number'] = 'required|string';
        } elseif ($applicantType === 'multiple') {
            $rules['multiple_owners_names'] = 'required|array|min:1';
            $rules['multiple_owners_names.*'] = 'required|string|max:255';
            $rules['multiple_owners_address'] = 'required|array|min:1';
            $rules['multiple_owners_address.*'] = 'required|string';
        }

        $request->validate($rules);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Check if SUA exists
            $sua = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->where('unit_type', 'SUA')
                ->first();

            if (!$sua) {
                return response()->json([
                    'success' => false,
                    'message' => 'SUA Application not found.'
                ], 404);
            }

            // Handle phone number array
            if ($request->has('phone_number') && is_array($request->input('phone_number'))) {
                $phoneNumber = implode(', ', array_filter($request->input('phone_number')));
            } elseif ($request->has('phone_number')) {
                $phoneNumber = $request->input('phone_number');
            } else {
                $phoneNumber = null;
            }

            // Update SUA application data using correct field names
            $updateData = [
                'allocation_source' => $request->allocation_source,
                'allocation_entity' => $request->allocation_entity,
                'allocation_ref_no' => strtoupper(trim((string) $request->allocation_ref_no)),
                'property_location' => $request->property_location,
                'land_use' => $landUse, // Use the processed land use value
                'unit_type' => $request->unit_type,
                'unit_size' => $request->unit_size ?: $request->unit_area,
                'unit_number' => $request->unit_number,
                'floor_number' => $request->floor_number,
                'block_number' => $request->block_number,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'surname' => $request->surname, // Changed from last_name
                'email' => $request->email,
                'phone_number' => $phoneNumber, // Use processed phone number
                'scheme_no' => $request->scheme_no,
                'payment_date' => $request->payment_date,
                'receipt_number' => $request->receipt_number,
                // Individual payment fields
                'processing_fee_payment_date' => $request->processing_fee_payment_date,
                'processing_fee_receipt_no' => $request->processing_fee_receipt_no,
                'application_fee_payment_date' => $request->application_fee_payment_date,
                'application_fee_receipt_no' => $request->application_fee_receipt_no,
                'survey_fee_payment_date' => $request->survey_fee_payment_date,
                'survey_fee_receipt_no' => $request->survey_fee_receipt_no,
                'application_fee' => $request->application_fee,
                'processing_fee' => $request->processing_fee,
                'site_plan_fee' => $request->site_plan_fee,
                'applicant_type' => $request->applicant_type,
                'applicant_title' => $request->applicant_title,
                'identification_type' => $request->identification_type,
                'mls_fileno' => $request->input('mls_fileno') ?: $sua->fileno,
                'shared_areas' => $this->processSharedAreas($request),
                'improvement_value' => $request->improvement_value,
                'updated_at' => now(),
                'updated_by' => Auth::id()
            ];

            DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->update($updateData);

            // Update shared utilities in shared_utilities table
            $this->saveSharedUtilitiesToTable($request, $sua->main_application_id, $id);

            // Update fileNumber table record if it exists
            $fileName = $this->getApplicantName($request);

            DB::connection('sqlsrv')->table('fileNumbers')
                ->where('sub_application_id', $id)
                ->where('SOURCE', 'ST')
                ->update([
                    'mlsfNo' => $request->input('mls_fileno') ?: $sua->fileno,
                    'FileName' => $fileName,
                    'updated_at' => now(),
                    'location' => $request->property_location
                ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'SUA Application updated successfully!',
                'data' => [
                    'sua_id' => $id,
                    'fileno' => $sua->fileno
                ]
            ]);

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('SUA Application update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update SUA application: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified SUA application from storage.
     */
    public function destroy($id)
    {
        try {
            // Check if the SUA application exists
            $sua = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->where('application_type', 'SUA')
                ->first();

            if (!$sua) {
                return redirect()->back()->with('error', 'SUA Application not found.');
            }

            // Check permissions (super admin or appropriate role)
            if (Auth::user()->type != 'super admin' && !Auth::user()->can('delete_sua_applications')) {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }

            // Delete the SUA application
            DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->where('application_type', 'SUA')
                ->delete();

            return redirect()->route('sua.index')
                ->with('success', 'SUA Application deleted successfully.');

        } catch (Exception $e) {
            Log::error('SUA Application deletion failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete SUA application: ' . $e->getMessage());
        }
    }

    /**
     * Get applicant name based on applicant type
     */
    private function getApplicantName(Request $request): string
    {
        $applicantType = $request->input('applicant_type');

        switch ($applicantType) {
            case 'individual':
                $firstName = $request->input('first_name', '');
                $middleName = $request->input('middle_name', '');
                $surname = $request->input('surname', '');

                $fullName = trim($firstName . ' ' . $middleName . ' ' . $surname);
                return $fullName ?: 'Individual Applicant';

            case 'corporate':
                return $request->input('corporate_name', 'Corporate Applicant');

            case 'multiple':
                $ownerNames = $request->input('multiple_owners_names', []);
                if (!empty($ownerNames) && is_array($ownerNames)) {
                    $firstOwner = $ownerNames[0] ?? 'Multiple Owners';
                    $totalCount = count($ownerNames);
                    if ($totalCount > 1) {
                        return $firstOwner . ' & ' . ($totalCount - 1) . ' Others';
                    }
                    return $firstOwner;
                }
                return 'Multiple Owners';

            default:
                return 'Unknown Applicant';
        }
    }

    /**
     * Process shared areas with support for custom "other" areas
     */
    private function processSharedAreas(Request $request): ?string
    {
        $sharedAreas = null;
        if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
            $sharedAreasArray = $request->input('shared_areas');

            // Debug log the initial shared areas data
            Log::info('Processing shared areas in SUAController', [
                'shared_areas_array' => $sharedAreasArray,
                'other_areas_detail' => $request->input('other_areas_detail'),
                'has_other' => in_array('other', $sharedAreasArray),
                'other_detail_filled' => $request->filled('other_areas_detail')
            ]);

            // If "other" is selected and other_areas_detail is provided, process the custom areas
            if (in_array('other', $sharedAreasArray) && $request->filled('other_areas_detail')) {
                // Remove "other" from the array
                $sharedAreasArray = array_filter($sharedAreasArray, function ($area) {
                    return $area !== 'other';
                });

                // Parse the other_areas_detail and add each area to the array
                $otherAreas = $request->input('other_areas_detail');
                $customAreas = array_map('trim', explode(',', $otherAreas));
                $customAreas = array_filter($customAreas); // Remove empty values

                Log::info('Processing custom areas from other_areas_detail in SUAController', [
                    'other_areas_raw' => $otherAreas,
                    'custom_areas_parsed' => $customAreas,
                    'shared_areas_before_merge' => $sharedAreasArray
                ]);

                // Add custom areas to the shared areas array
                $sharedAreasArray = array_merge($sharedAreasArray, $customAreas);

                Log::info('After merging custom areas in SUAController', [
                    'final_shared_areas_array' => $sharedAreasArray
                ]);
            }

            $sharedAreas = json_encode(array_values($sharedAreasArray));

            Log::info('Final shared areas JSON in SUAController', [
                'shared_areas_json' => $sharedAreas
            ]);
        }

        return $sharedAreas;
    }

    /**
     * Save shared areas to the shared_utilities table
     */
    private function saveSharedUtilitiesToTable(Request $request, $applicationId, $subApplicationId = null): void
    {
        if (!$request->has('shared_areas') || !is_array($request->input('shared_areas'))) {
            return;
        }

        $sharedAreasArray = $request->input('shared_areas');

        // Process "other" areas if specified
        if (in_array('other', $sharedAreasArray) && $request->filled('other_areas_detail')) {
            // Remove "other" from the array
            $sharedAreasArray = array_filter($sharedAreasArray, function ($area) {
                return $area !== 'other';
            });

            // Add custom areas from other_areas_detail
            $otherAreas = array_map('trim', explode(',', $request->input('other_areas_detail')));
            $sharedAreasArray = array_merge($sharedAreasArray, $otherAreas);
        }

        // Remove existing utilities for this application/sub-application
        if ($subApplicationId) {
            DB::connection('sqlsrv')->table('shared_utilities')
                ->where('sub_application_id', $subApplicationId)
                ->delete();
        } else {
            DB::connection('sqlsrv')->table('shared_utilities')
                ->where('application_id', $applicationId)
                ->whereNull('sub_application_id')
                ->delete();
        }

        // Insert new utilities
        foreach (array_values($sharedAreasArray) as $index => $utility) {
            if (!empty(trim($utility))) {
                DB::connection('sqlsrv')->table('shared_utilities')->insert([
                    'application_id' => $applicationId,
                    'sub_application_id' => $subApplicationId,
                    'utility_type' => trim($utility),
                    'dimension' => null,
                    'count' => null,
                    'order' => $index + 1,
                    'created_by' => auth()->id() ?? 1,
                    'updated_by' => auth()->id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}
