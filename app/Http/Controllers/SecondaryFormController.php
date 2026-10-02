<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\UnitFileIndexingService;

class SecondaryFormController extends Controller
{
    public function edit($id)
    {
        return redirect()->route('subapplication.index', ['sub_application_id' => $id]);
    }

    public function update(Request $request, $id)
    {
        try {
            if (!$request->filled('applicantType') && $request->filled('applicant_type')) {
                $request->merge(['applicantType' => $request->input('applicant_type')]);
            }

            if (!$request->filled('applicant_type') && $request->filled('applicantType')) {
                $request->merge(['applicant_type' => $request->input('applicantType')]);
            }

            // Normalize newer field aliases before validation
            if (!$request->filled('owner_email') && $request->filled('email')) {
                $request->merge(['owner_email' => $request->input('email')]);
            }

            if (!$request->filled('address') && ($request->filled('address_house_no') || $request->filled('address_street_name') || $request->filled('address_district') || $request->filled('address_lga') || $request->filled('address_state'))) {
                $request->merge([
                    'address' => collect([
                        $request->input('address_house_no'),
                        $request->input('address_street_name'),
                        $request->input('address_district'),
                        $request->input('address_lga'),
                        $request->input('address_state'),
                    ])->filter()->implode(', '),
                ]);
            }

            // Validate the form data
            $validated = $request->validate([
                'applicantType' => 'required',
                'applicant_title' => 'nullable',
                'first_name' => 'nullable',
                'middle_name' => 'nullable',
                'surname' => 'nullable',
                'corporate_name' => 'nullable',
                'rc_number' => 'nullable',
                'multiple_owners_names' => 'nullable|array',
                'multiple_owners_address' => 'nullable|array',
                'multiple_owners_email' => 'nullable|array',
                'date_captured' => 'nullable|date', // Date Captured field
                'multiple_owners_email.*' => 'nullable|email',
                'multiple_owners_phone' => 'nullable|array',
                'multiple_owners_passport' => 'nullable|array',
                'multiple_owners_passport.*' => 'nullable|image|max:5120',
                'multiple_owners_identification_image' => 'nullable|array',
                'multiple_owners_identification_image.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'address' => 'nullable',
                'address_street_name' => 'nullable',
                'address_district' => 'nullable',
                'address_lga' => 'nullable',
                'address_state' => 'nullable',
                'phone_number' => 'nullable',
                'owner_email' => 'nullable|email',
                'identification_type' => 'nullable',
                'id_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'residence_type' => 'nullable',
                'block_number' => 'nullable',
                'floor_number' => 'nullable',
                'unit_number' => 'nullable',
                'unit_size' => 'nullable|string|max:255',
                'application_fee' => 'nullable',
                'processing_fee' => 'nullable',
                'site_plan_fee' => 'nullable',
                'payment_date' => 'nullable',
                'receipt_number' => 'nullable',
                'application_comment' => 'nullable',
                'commercial_type' => 'nullable',
                'industrial_type' => 'nullable',
                'ownership_type' => 'nullable',
                'ownershipType' => 'nullable',
                'otherOwnership' => 'nullable',
                'shared_areas' => 'nullable|array',
                'other_areas_detail' => 'nullable|string|max:500',
                'scheme_no' => 'nullable',
                'improvement_value' => 'nullable|numeric|min:0',
                'application_letter' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'building_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'architectural_design' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'survey_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'ownership_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'property_location' => 'nullable|string',
            ]);

            // Get existing application data
            $existingApplication = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->first();

            if (!$existingApplication) {
                return redirect()->route('sectionaltitling.index')
                    ->with('error', 'Sub-application not found.');
            }

            // Handle passport upload (keep existing if no new file)
            $passportPath = $existingApplication->passport;
            if ($request->hasFile('passport')) {
                // Delete old passport if exists
                if ($passportPath) {
                    Storage::disk('public')->delete($passportPath);
                }
                $passport = $request->file('passport');
                $passportPath = $passport->store('passports', 'public');
            }

            // Handle main identification image upload
            $identificationImagePath = $existingApplication->id_document;
            if ($request->hasFile('id_document')) {
                // Delete old identification image if exists
                if ($identificationImagePath) {
                    Storage::disk('public')->delete($identificationImagePath);
                }
                $identificationImage = $request->file('id_document');
                $identificationImagePath = $identificationImage->store('identification_images', 'public');
            }

            // Handle multiple owners passports upload
            $multipleOwnersPassportPaths = $existingApplication->multiple_owners_passport ?
                json_decode($existingApplication->multiple_owners_passport, true) : [];

            if ($request->hasFile('multiple_owners_passport')) {
                // Delete old passports
                foreach ($multipleOwnersPassportPaths as $oldPath) {
                    if ($oldPath) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $multipleOwnersPassportPaths = [];
                foreach ($request->file('multiple_owners_passport') as $passport) {
                    if ($passport && $passport->isValid()) {
                        $path = $passport->store('multiple_owners_passports', 'public');
                        $multipleOwnersPassportPaths[] = $path;
                    }
                }
            }

            // Handle multiple owners identification images upload
            $multipleOwnersIdentificationPaths = $existingApplication->multiple_owners_identification_image ?
                json_decode($existingApplication->multiple_owners_identification_image, true) : [];

            if ($request->hasFile('multiple_owners_identification_image')) {
                // Delete old identification images
                foreach ($multipleOwnersIdentificationPaths as $oldPath) {
                    if ($oldPath) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $multipleOwnersIdentificationPaths = [];
                foreach ($request->file('multiple_owners_identification_image') as $identificationImage) {
                    if ($identificationImage && $identificationImage->isValid()) {
                        $path = $identificationImage->store('multiple_owners_identification', 'public');
                        $multipleOwnersIdentificationPaths[] = $path;
                    }
                }
            }

            // Process multiple owners identification types
            $multipleOwnersIdentificationTypes = [];
            $allInputs = $request->all();
            foreach ($allInputs as $key => $value) {
                if (strpos($key, 'multiple_owners_identification_type_') === 0) {
                    $multipleOwnersIdentificationTypes[] = $value;
                }
            }

            // Process document uploads
            $documents = $existingApplication->documents ? json_decode($existingApplication->documents, true) : [];
            $documentTypes = ['application_letter', 'building_plan', 'architectural_design', 'survey_plan', 'ownership_document'];

            foreach ($documentTypes as $docType) {
                if ($request->hasFile($docType)) {
                    // Delete old document if exists
                    if (isset($documents[$docType]['path'])) {
                        Storage::disk('public')->delete($documents[$docType]['path']);
                    }

                    $file = $request->file($docType);
                    if ($file && $file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $path = $file->store('documents', 'public');

                        $documents[$docType] = [
                            'path' => $path,
                            'original_name' => $originalName,
                            'type' => $extension,
                            'uploaded_at' => now()->toDateTimeString()
                        ];
                    }
                }
            }

            // Format phone numbers - handle both string and array input
            $phoneNumber = null;
            if ($request->has('phone_number')) {
                $phoneInput = $request->input('phone_number');
                if (is_array($phoneInput)) {
                    $phoneNumber = implode(', ', array_filter($phoneInput));
                } else {
                    $phoneNumber = $phoneInput;
                }
            }

            // Process shared areas
            $sharedAreas = null;
            if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
                $sharedAreasArray = $request->input('shared_areas');

                // Debug log the initial shared areas data
                Log::info('Processing shared areas in update method', [
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

                    Log::info('Processing custom areas from other_areas_detail in update method', [
                        'other_areas_raw' => $otherAreas,
                        'custom_areas_parsed' => $customAreas,
                        'shared_areas_before_merge' => $sharedAreasArray
                    ]);

                    // Add custom areas to the shared areas array
                    $sharedAreasArray = array_merge($sharedAreasArray, $customAreas);

                    Log::info('After merging custom areas in update method', [
                        'final_shared_areas_array' => $sharedAreasArray
                    ]);
                }

                $sharedAreas = json_encode(array_values($sharedAreasArray));

                Log::info('Final shared areas JSON in update method', [
                    'shared_areas_json' => $sharedAreas
                ]);
            }

            // Process ownership type
            $ownershipType = $request->input('ownershipType');
            if ($ownershipType === 'others' && $request->filled('otherOwnership')) {
                $ownershipType = $request->input('otherOwnership');
            }

            // Get the mother application to build main_id
            $motherApplication = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $existingApplication->main_application_id)
                ->first();

            $mainId = null;
            if ($motherApplication) {
                $mainYear = date('Y', strtotime($motherApplication->created_at ?? now()));
                $mainAppId = $motherApplication->id ?? '';
                $mainId = sprintf('ST-%s-%03d', $mainYear, $mainAppId);
            }

            $applicantType = $request->input('applicantType');
            $firstName = $request->input('first_name');
            $middleName = $request->input('middle_name');
            $surname = $request->input('surname');

            if ($applicantType === 'corporate') {
                $firstName = null;
                $middleName = null;
                $surname = null;
            }

            $email = $request->input('owner_email') ?? $request->input('email');

            $address = $request->input('address');
            if (blank($address)) {
                $address = collect([
                    $request->input('address_house_no'),
                    $request->input('address_street_name'),
                    $request->input('address_district'),
                    $request->input('address_lga'),
                    $request->input('address_state'),
                ])->filter()->implode(', ');
            }

            $updateData = [
                'applicant_type' => $applicantType,
                'applicant_title' => $request->input('applicant_title'),
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'surname' => $surname,
                'passport' => $passportPath,
                'corporate_name' => $request->input('corporate_name'),
                'rc_number' => $request->input('rc_number'),
                'multiple_owners_names' => $request->has('multiple_owners_names') ? json_encode($request->input('multiple_owners_names')) : null,
                'multiple_owners_address' => $request->has('multiple_owners_address') ? json_encode($request->input('multiple_owners_address')) : null,
                'multiple_owners_email' => $request->has('multiple_owners_email') ? json_encode($request->input('multiple_owners_email')) : null,
                'multiple_owners_phone' => $request->has('multiple_owners_phone') ? json_encode($request->input('multiple_owners_phone')) : null,
                'multiple_owners_passport' => !empty($multipleOwnersPassportPaths) ? json_encode($multipleOwnersPassportPaths) : null,
                'multiple_owners_identification_type' => !empty($multipleOwnersIdentificationTypes) ? json_encode($multipleOwnersIdentificationTypes) : null,
                'multiple_owners_identification_image' => !empty($multipleOwnersIdentificationPaths) ? json_encode($multipleOwnersIdentificationPaths) : null,
                'address' => $address,
                'phone_number' => $phoneNumber,
                'email' => $email,
                'identification_type' => $request->input('identification_type'),
                'id_document' => $identificationImagePath,
                'block_number' => $request->input('block_number'),
                'floor_number' => $request->input('floor_number'),
                'unit_number' => $request->input('unit_number'),
                'unit_size' => $request->input('unit_size'),
                'main_id' => $mainId, // always set from server-side
                'application_fee' => $request->input('application_fee'),
                'processing_fee' => $request->input('processing_fee'),
                'site_plan_fee' => $request->input('site_plan_fee'),
                'application_date' => $request->input('application_date') ?? $request->input('date_captured'),
                'payment_date' => $request->input('payment_date'),
                'receipt_number' => $request->input('receipt_number'),
                'commercial_type' => $request->input('commercial_type'),
                'industrial_type' => $request->input('industrial_type'),
                'residence_type' => $request->input('residence_type'),
                'ownership_type' => $ownershipType,
                'address_street_name' => $request->input('address_street_name'),
                'address_district' => $request->input('address_district'),
                'address_lga' => $request->input('address_lga'),
                'address_state' => $request->input('address_state'),
                'property_location' => $request->input('property_location'),
                'scheme_no' => $request->input('scheme_no'),
                'improvement_value' => $request->input('improvement_value'),
                'shared_areas' => $sharedAreas,
                'documents' => !empty($documents) ? json_encode($documents) : null,
                'application_comment' => $request->input('application_comment'),
                'updated_at' => now(),
                'updated_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
            ];

            // Update the sub-application
            DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $id)
                ->update($updateData);

            // Update shared utilities in shared_utilities table
            $this->saveSharedUtilitiesToTable($request, $existingApplication->main_application_id, $id);

            Log::info('Sub-application updated successfully', [
                'application_id' => $id,
                'updated_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name
            ]);

            return redirect()->route('sectionaltitling.viewrecorddetail_sub', $id)
                ->with('success', 'Unit application updated successfully!');

        } catch (Exception $e) {
            Log::error('Error updating sub-application', [
                'error' => $e->getMessage(),
                'application_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('sectionaltitling.edit_sub', $id)
                ->withInput()
                ->with('error', 'Error updating application: ' . $e->getMessage());
        }
    }

    public function save(Request $request)
    {
        try {
            if (!$request->filled('applicantType') && $request->filled('applicant_type')) {
                $request->merge(['applicantType' => $request->input('applicant_type')]);
            }

            if (!$request->filled('applicant_type') && $request->filled('applicantType')) {
                $request->merge(['applicant_type' => $request->input('applicantType')]);
            }

            // Debug log all request data to see what's being sent
            Log::info('SecondaryFormController save method called', [
                'all_request_data' => $request->all(),
                'shared_areas' => $request->input('shared_areas'),
                'other_areas_detail' => $request->input('other_areas_detail'),
                'has_shared_areas' => $request->has('shared_areas'),
                'has_other_areas_detail' => $request->has('other_areas_detail'),
                'shared_areas_is_array' => is_array($request->input('shared_areas')),
            ]);

            // Normalize newer field aliases before validation
            if (!$request->filled('owner_email') && $request->filled('email')) {
                $request->merge(['owner_email' => $request->input('email')]);
            }

            if (!$request->filled('address') && ($request->filled('address_house_no') || $request->filled('address_street_name') || $request->filled('address_district') || $request->filled('address_lga') || $request->filled('address_state'))) {
                $request->merge([
                    'address' => collect([
                        $request->input('address_house_no'),
                        $request->input('address_street_name'),
                        $request->input('address_district'),
                        $request->input('address_lga'),
                        $request->input('address_state'),
                    ])->filter()->implode(', '),
                ]);
            }

            // Validate the form data - making some fields optional to improve form submission success
            $validated = $request->validate([
                'applicantType' => 'required',
                'applicant_title' => 'nullable',
                'first_name' => 'nullable',
                'middle_name' => 'nullable',
                'surname' => 'nullable',
                'corporate_name' => 'nullable',
                'rc_number' => 'nullable',
                'multiple_owners_names' => 'nullable|array',
                'multiple_owners_address' => 'nullable|array',
                'multiple_owners_email' => 'nullable|array',
                'multiple_owners_email.*' => 'nullable|email',
                'multiple_owners_phone' => 'nullable|array',
                'multiple_owners_passport' => 'nullable|array',
                'multiple_owners_passport.*' => 'nullable|image|max:5120',
                'multiple_owners_identification_image' => 'nullable|array',
                'multiple_owners_identification_image.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'address_house_no' => 'nullable',
                'address_street_name' => 'nullable',
                'address_district' => 'nullable',
                'address_lga' => 'nullable',
                'address_state' => 'nullable',
                'phone_number' => 'nullable|array',
                'owner_email' => 'nullable|email',
                'identification_type' => 'nullable',
                'id_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'residence_type' => 'nullable',
                'block_number' => 'nullable',
                'floor_number' => 'nullable',
                'unit_number' => 'nullable',
                'unit_size' => 'nullable|string|max:255',
                'application_fee' => 'nullable',
                'processing_fee' => 'nullable',
                'site_plan_fee' => 'nullable',
                'payment_date' => 'nullable',
                'receipt_number' => 'nullable',
                // Individual payment fields
                'processing_fee_payment_date' => 'nullable|date',
                'processing_fee_receipt_no' => 'nullable|string|max:100',
                'application_fee_payment_date' => 'nullable|date',
                'application_fee_receipt_no' => 'nullable|string|max:100',
                'survey_fee_payment_date' => 'nullable|date',
                'survey_fee_receipt_no' => 'nullable|string|max:100',
                'application_comment' => 'nullable',
                'commercial_type' => 'nullable',
                'industrial_type' => 'nullable',
                'ownership_type' => 'nullable',
                'ownershipType' => 'nullable',
                'otherOwnership' => 'nullable',
                'shared_areas' => 'nullable|array',
                'other_areas_detail' => 'nullable|string|max:500',
                'main_application_id' => 'required',
                'main_id' => 'nullable|string',
                'scheme_no' => 'nullable',
                'improvement_value' => 'nullable|numeric|min:0',
                'application_date' => 'nullable|date', // Date Captured field
                'prefix' => 'required',
                'year' => 'required',
                'serial_number' => 'required',
                'fileno' => 'required',
                'application_letter' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'building_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'architectural_design' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'survey_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'ownership_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'property_location' => 'nullable|string',
            ]);

            // Debug log to check what's being received
            Log::info('Form data received', [
                'owner_fullname' => $request->input('fullname'),
                'multiple_owners_data' => [
                    'names' => $request->input('multiple_owners_names'),
                    'addresses' => $request->input('multiple_owners_address'),
                    'emails' => $request->input('multiple_owners_email'),
                    'phones' => $request->input('multiple_owners_phone'),
                    'identification_types' => $request->all()
                ],
                'all_data' => $request->all()
            ]);

            // Handle passport upload
            $passportPath = null;
            if ($request->hasFile('passport')) {
                $passport = $request->file('passport');
                $passportPath = $passport->store('passports', 'public');
            }

            // Handle main identification image upload
            $identificationImagePath = null;
            if ($request->hasFile('id_document')) {
                $identificationImage = $request->file('id_document');
                $identificationImagePath = $identificationImage->store('identification_images', 'public');
            }

            // Handle multiple owners passports upload
            $multipleOwnersPassportPaths = [];
            if ($request->hasFile('multiple_owners_passport')) {
                foreach ($request->file('multiple_owners_passport') as $passport) {
                    if ($passport && $passport->isValid()) {
                        $path = $passport->store('multiple_owners_passports', 'public');
                        $multipleOwnersPassportPaths[] = $path;
                    }
                }
            }

            // Handle multiple owners identification images upload
            $multipleOwnersIdentificationPaths = [];
            if ($request->hasFile('multiple_owners_identification_image')) {
                foreach ($request->file('multiple_owners_identification_image') as $identificationImage) {
                    if ($identificationImage && $identificationImage->isValid()) {
                        $path = $identificationImage->store('multiple_owners_identification', 'public');
                        $multipleOwnersIdentificationPaths[] = $path;
                    }
                }
            }

            // Process multiple owners identification types
            $multipleOwnersIdentificationTypes = [];
            $allInputs = $request->all();
            foreach ($allInputs as $key => $value) {
                if (strpos($key, 'multiple_owners_identification_type_') === 0) {
                    $multipleOwnersIdentificationTypes[] = $value;
                }
            }

            // Process document uploads
            $documents = [];
            $documentTypes = ['application_letter', 'building_plan', 'architectural_design', 'survey_plan', 'ownership_document'];

            foreach ($documentTypes as $docType) {
                if ($request->hasFile($docType)) {
                    $file = $request->file($docType);
                    if ($file && $file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $path = $file->store('documents', 'public');

                        // Add detailed info about each document
                        $documents[$docType] = [
                            'path' => $path,
                            'original_name' => $originalName,
                            'type' => $extension,
                            'uploaded_at' => now()->toDateTimeString()
                        ];

                        Log::info('Document uploaded', [
                            'docType' => $docType,
                            'path' => $path,
                            'original_name' => $originalName
                        ]);
                    }
                }
            }

            // Format phone numbers
            $phoneNumber = null;
            if ($request->has('phone_number') && is_array($request->input('phone_number'))) {
                $phoneNumber = implode(', ', array_filter($request->input('phone_number')));
            } elseif ($request->has('phone_number')) {
                $phoneNumber = $request->input('phone_number');
            }

            // Process shared areas
            $sharedAreas = null;
            if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
                $sharedAreasArray = $request->input('shared_areas');

                // Debug log the initial shared areas data
                Log::info('Processing shared areas in save method', [
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

                    Log::info('Processing custom areas from other_areas_detail in save method', [
                        'other_areas_raw' => $otherAreas,
                        'custom_areas_parsed' => $customAreas,
                        'shared_areas_before_merge' => $sharedAreasArray
                    ]);

                    // Add custom areas to the shared areas array
                    $sharedAreasArray = array_merge($sharedAreasArray, $customAreas);

                    Log::info('After merging custom areas in save method', [
                        'final_shared_areas_array' => $sharedAreasArray
                    ]);
                }

                $sharedAreas = json_encode(array_values($sharedAreasArray));

                Log::info('Final shared areas JSON in save method', [
                    'shared_areas_json' => $sharedAreas
                ]);
            }

            // Process ownership type
            $ownershipType = $request->input('ownershipType');
            if ($ownershipType === 'others' && $request->filled('otherOwnership')) {
                $ownershipType = $request->input('otherOwnership');
            }

            // Get the mother application to build main_id
            $motherApplication = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $request->input('main_application_id'))
                ->first();

            $mainId = null;
            if ($motherApplication) {
                $mainYear = date('Y', strtotime($motherApplication->created_at ?? now()));
                $mainAppId = $motherApplication->id ?? '';
                $mainId = sprintf('ST-%s-%03d', $mainYear, $mainAppId);

                // Debug log to check main_id generation
                Log::info('Main ID generation', [
                    'mother_application_id' => $request->input('main_application_id'),
                    'mother_application_found' => $motherApplication ? 'yes' : 'no',
                    'main_year' => $mainYear,
                    'main_app_id' => $mainAppId,
                    'generated_main_id' => $mainId
                ]);
            } else {
                Log::warning('Mother application not found for main_application_id: ' . $request->input('main_application_id'));
            }

            $applicantType = $request->input('applicantType');
            $firstName = $request->input('first_name');
            $middleName = $request->input('middle_name');
            $surname = $request->input('surname');

            if ($applicantType === 'corporate') {
                $firstName = null;
                $middleName = null;
                $surname = null;
            }

            $email = $request->input('owner_email') ?? $request->input('email');

            $address = $request->input('address');
            if (blank($address)) {
                $address = collect([
                    $request->input('address_house_no'),
                    $request->input('address_street_name'),
                    $request->input('address_district'),
                    $request->input('address_lga'),
                    $request->input('address_state'),
                ])->filter()->implode(', ');
            }

            // Create data array for subapplications table
            $subApplicationData = [
                'main_application_id' => $request->input('main_application_id'),
                'applicant_type' => $applicantType,
                'fileno' => $request->input('fileno'), // Unit File Number
                'np_fileno' => $motherApplication->np_fileno ?? null, // NP FileNo from mother application
                'applicant_title' => $request->input('applicant_title'),
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'surname' => $surname,
                'passport' => $passportPath,
                'corporate_name' => $request->input('corporate_name'),
                'rc_number' => $request->input('rc_number'),
                'multiple_owners_names' => $request->has('multiple_owners_names') ? json_encode($request->input('multiple_owners_names')) : null,
                'multiple_owners_address' => $request->has('multiple_owners_address') ? json_encode($request->input('multiple_owners_address')) : null,
                'multiple_owners_email' => $request->has('multiple_owners_email') ? json_encode($request->input('multiple_owners_email')) : null,
                'multiple_owners_phone' => $request->has('multiple_owners_phone') ? json_encode($request->input('multiple_owners_phone')) : null,
                'multiple_owners_passport' => !empty($multipleOwnersPassportPaths) ? json_encode($multipleOwnersPassportPaths) : null,
                'multiple_owners_identification_type' => !empty($multipleOwnersIdentificationTypes) ? json_encode($multipleOwnersIdentificationTypes) : null,
                'multiple_owners_identification_image' => !empty($multipleOwnersIdentificationPaths) ? json_encode($multipleOwnersIdentificationPaths) : null,
                'address' => $address,
                'phone_number' => $phoneNumber,
                'email' => $email,
                'identification_type' => $request->input('identification_type'),
                'id_document' => $identificationImagePath,
                'block_number' => $request->input('block_number'),
                'floor_number' => $request->input('floor_number'),
                'unit_number' => $request->input('unit_number'),
                'unit_size' => $request->input('unit_size'),
                'main_id' => $mainId, // always set from server-side
                'application_status' => 'Pending',
                'planning_recommendation_status' => 'Pending',
                'application_fee' => $request->input('application_fee'),
                'processing_fee' => $request->input('processing_fee'),
                'site_plan_fee' => $request->input('site_plan_fee'),
                'application_date' => $request->input('application_date') ?? $request->input('date_captured'),
                'payment_date' => $request->input('payment_date'),
                'receipt_number' => $request->input('receipt_number'),
                'commercial_type' => $request->input('commercial_type'),
                'industrial_type' => $request->input('industrial_type'),
                'residence_type' => $request->input('residence_type'),
                'ownership_type' => $ownershipType,
                'address_street_name' => $request->input('address_street_name'),
                'address_district' => $request->input('address_district'),
                'address_lga' => $request->input('address_lga'),
                'address_state' => $request->input('address_state'),
                'property_location' => $request->input('property_location'),
                'scheme_no' => $request->input('scheme_no'),
                'improvement_value' => $request->input('improvement_value'),
                'shared_areas' => $sharedAreas,
                'documents' => !empty($documents) ? json_encode($documents) : null,
                'application_comment' => $request->input('application_comment'),
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];

            // Get the land use from the mother application (reuse the already fetched data)
            if ($motherApplication) {
                $subApplicationData['land_use'] = $motherApplication->land_use;
            }

            // Log the data being inserted into subapplications
            Log::info('Data being inserted into subapplications table', [
                'main_id' => $subApplicationData['main_id'],
                'main_application_id' => $subApplicationData['main_application_id'],
                'fileno' => $subApplicationData['fileno'],
                'documents' => $subApplicationData['documents'],
                'document_count' => count($documents),
                'multiple_owners_data' => [
                    'names' => $subApplicationData['multiple_owners_names'],
                    'addresses' => $subApplicationData['multiple_owners_address'],
                    'emails' => $subApplicationData['multiple_owners_email'],
                    'phones' => $subApplicationData['multiple_owners_phone'],
                    'identification_types' => $subApplicationData['multiple_owners_identification_type'],
                    'identification_images' => $subApplicationData['multiple_owners_identification_image']
                ]
            ]);

            // Log the exact SQL that would be executed
            Log::info('About to insert subapplication data', [
                'data_keys' => array_keys($subApplicationData),
                'main_id_value' => $subApplicationData['main_id'],
                'main_id_type' => gettype($subApplicationData['main_id']),
                'main_id_length' => strlen($subApplicationData['main_id'] ?? ''),
            ]);

            // Add individual payment fields to the data array
            $subApplicationData['processing_fee_payment_date'] = $request->input('processing_fee_payment_date');
            $subApplicationData['processing_fee_receipt_no'] = $request->input('processing_fee_receipt_no');
            $subApplicationData['application_fee_payment_date'] = $request->input('application_fee_payment_date');
            $subApplicationData['application_fee_receipt_no'] = $request->input('application_fee_receipt_no');
            $subApplicationData['survey_fee_payment_date'] = $request->input('survey_fee_payment_date');
            $subApplicationData['survey_fee_receipt_no'] = $request->input('survey_fee_receipt_no');

            // Insert data into the subapplications table
            $subApplicationId = DB::connection('sqlsrv')->table('subapplications')->insertGetId($subApplicationData);

            // Save shared utilities to the normalized table
            $this->saveSharedUtilitiesToTable($request, null, $subApplicationId);

            // Verify the main_id was saved correctly
            $savedRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $subApplicationId)->first();
            Log::info('Verification after insert', [
                'subapplication_id' => $subApplicationId,
                'saved_main_id' => $savedRecord->main_id ?? 'NULL',
                'saved_st_fillno' => $savedRecord->st_fillno ?? 'NULL',
                'saved_fileno' => $savedRecord->fileno ?? 'NULL',
                'expected_main_id' => $mainId,
                'all_saved_fields' => array_keys((array) $savedRecord)
            ]);

            // If main_id is still null, try to update it directly
            if (empty($savedRecord->main_id) && !empty($mainId)) {
                Log::warning('Main ID was not saved during insert, attempting direct update');
                $updateResult = DB::connection('sqlsrv')
                    ->table('subapplications')
                    ->where('id', $subApplicationId)
                    ->update(['main_id' => $mainId]);

                Log::info('Direct update result', [
                    'update_result' => $updateResult,
                    'main_id_to_update' => $mainId
                ]);

                // Verify the update worked
                $updatedRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $subApplicationId)->first();
                Log::info('After direct update verification', [
                    'updated_main_id' => $updatedRecord->main_id ?? 'NULL'
                ]);
            }


            // Add Duplicate Check for StFileNo table
            // Ensure that the file number is unique before inserting

            // Now insert into StFileNo table
            $stFileNoData = [
                'file_prefix' => $request->input('prefix'),
                'year' => $request->input('year'),
                'serial_number' => $request->input('serial_number'),
                'fileno' => $request->input('fileno'),
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => Auth::user()->first_name . ' ' . Auth::user()->last_name
            ];

            // Insert data into the StFileNo table
            DB::connection('sqlsrv')->table('StFileNo')->insert($stFileNoData);

            // Generate a unique Sectional_Title_File_No using SQL to avoid duplicates
            $currentYear = date('Y');
            $lastNumberQuery = DB::connection('sqlsrv')
                ->table('eRegistry')
                ->where('Sectional_Title_File_No', 'like', "ST/{$currentYear}/%")
                ->orderByRaw("CAST(SUBSTRING(Sectional_Title_File_No, 9, 3) AS INT) DESC")
                ->value(DB::raw("SUBSTRING(Sectional_Title_File_No, 9, 3)"));

            $nextNumber = $lastNumberQuery ? (int) $lastNumberQuery + 1 : 1;
            $sectionalTitleFileNo = 'ST/' . $currentYear . '/' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Get the file number from the request
            $fileNo = $request->input('fileno');

            // Get authenticated user information
            $createdBy = Auth::user() ? Auth::user()->first_name : null;

            // Create eRegistry data array with required fields
            $eRegistryData = [
                'application_id' => $motherApplication->id ?? null, // Main application ID
                'sub_application_id' => $subApplicationId, // Unit application ID
                'MLSFileNo' => $motherApplication->fileno ?? null, // From mother application if MLS
                'KANGISFileNo' => $motherApplication->fileno ?? null, // From mother application if KANGIS
                'NEWKangisFileNo' => $motherApplication->fileno ?? null, // From mother application if New KANGIS
                'npFileno' => $motherApplication->np_fileno ?? null, // NP FileNo from mother application
                'ST_fileNO' => $request->input('fileno'), // Unit application file number
                'Sectional_Title_File_No' => $sectionalTitleFileNo,
                'Commissioning_Date' => now(), // Current date
                'Decommissioning_Date' => now(),
                'Site_Plan_Approval' => null,
                'Survey_Plan_Approval' => null,
                'Expected_Return_Date' => null,
                'Current_Office' => 'ST Registry', // Set to ST Registry
                'created_by' => $createdBy,
                'modify_by' => null,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insert data into eRegistry table
            DB::connection('sqlsrv')->table('eRegistry')->insert($eRegistryData);

            Log::info('eRegistry data inserted for unit application', [
                'sub_application_id' => $subApplicationId,
                'application_id' => $motherApplication->id ?? null,
                'ST_fileNO' => $request->input('fileno'),
                'npFileno' => $motherApplication->np_fileno ?? null
            ]);

            // Insert billing record for unit application
            $billingData = [
                'Sectional_Title_File_No' => $sectionalTitleFileNo,
                'ref_id' => $request->input('fileno'),
                'application_id' => null,
                'sub_application_id' => $subApplicationId,
                'Scheme_Application_Fee' => $request->input('application_fee'),
                'Site_Plan_Fee' => null,
                'Processing_Fee' => $request->input('processing_fee'),
                'survey_fee' => $request->input('site_plan_fee'), // For unit, site_plan_fee maps to survey_fee
                'Betterment_Charges' => null,
                'Unit_Application_Fees' => $request->input('application_fee'),
                'Land_Use_Charge' => null,
                'property_value' => null,
                'Penalty_Fees' => null,
                'Payment_Status' => 'Paid',
                'created_at' => now(),
                'updated_at' => now(),
                'betterment_rate' => null
            ];

            // Insert billing record
            DB::connection('sqlsrv')->table('billing')->insert($billingData);

            // Auto-create EDMS file indexing (always enabled)
            try {
                /** @var UnitFileIndexingService $unitIndexing */
                $unitIndexing = app(UnitFileIndexingService::class);

                $unitIndexing->ensure([
                    'main_application_id' => $request->input('main_application_id'),
                    'subapplication_id' => $subApplicationId,
                    'file_number' => $request->input('fileno'),
                    'land_use_type' => $motherApplication->land_use ?? 'Residential',
                    'plot_number' => $motherApplication->property_plot_no ?? null,
                    'tp_no' => $motherApplication->tp_no ?? null,
                    'lpkn_no' => $motherApplication->lpkn_no ?? null,
                    'location' => $motherApplication->location ?? null,
                    'district' => $motherApplication->property_district ?? null,
                    'lga' => $motherApplication->property_lga ?? null,
                    'st_batch_no' => $request->input('st_batch_no'),
                    'tracking_id' => $request->input('tracking_id'),
                    'applicant_type' => $subApplicationData['applicant_type'] ?? null,
                    'corporate_name' => $subApplicationData['corporate_name'] ?? null,
                    'first_owner_name' => $this->extractFirstOwnerName($subApplicationData),
                    'first_name' => $subApplicationData['first_name'] ?? null,
                    'middle_name' => $subApplicationData['middle_name'] ?? null,
                    'surname' => $subApplicationData['surname'] ?? null,
                ]);

                Log::info('EDMS file indexing ensured for sub-application', [
                    'subapplication_id' => $subApplicationId,
                ]);
            } catch (Exception $edmsError) {
                Log::warning('Failed to create EDMS file indexing', [
                    'subapplication_id' => $subApplicationId,
                    'error' => $edmsError->getMessage()
                ]);
            }

            // Always redirect to EDMS workflow after successful submission
            $redirectUrl = route('sectionaltitling.units');
            $successMessage = 'Unit application submitted successfully! EDMS workflow has been initialized.';

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => $redirectUrl,
                    'sub_application_id' => $subApplicationId,
                    'unit_file_no' => $request->input('fileno'),
                ]);
            }

            return redirect()->to($redirectUrl)
                ->with('success', $successMessage);

        } catch (Exception $e) {
            // Enhanced error logging for debugging
            Log::error('Error submitting sub-application form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_data' => $request->all()
            ]);

            $errorMessage = 'Error submitting application: ' . $e->getMessage();

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }

            // Return with error message
            return redirect()->back()
                ->withInput()
                ->with('error', $errorMessage);
        }
    }

    /**
     * Generate file title for sub-application
     */
    private function generateSubApplicationFileTitle($subApplicationData, $motherApplication)
    {
        // Deprecated in favour of UnitFileIndexingService applicant name rules.
        $type = $subApplicationData['applicant_type'] ?? null;

        if ($type === 'corporate') {
            return trim((string) ($subApplicationData['corporate_name'] ?? '')) ?: 'Applicant';
        }

        if ($type === 'multiple') {
            return $this->extractFirstOwnerName($subApplicationData) ?: 'Applicant';
        }

        $first = trim((string) ($subApplicationData['first_name'] ?? ''));
        $middle = trim((string) ($subApplicationData['middle_name'] ?? ''));
        $surname = trim((string) ($subApplicationData['surname'] ?? ''));

        $parts = array_filter([$first, $middle, $surname], fn($p) => $p !== '');

        return $parts ? implode(' ', $parts) : 'Applicant';
    }

    private function extractFirstOwnerName(array $subApplicationData): ?string
    {
        if (isset($subApplicationData['multiple_owners_names'])) {
            $names = $subApplicationData['multiple_owners_names'];

            if (is_string($names)) {
                $decoded = json_decode($names, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $names = $decoded;
                }
            }

            if (is_array($names) && count($names) > 0) {
                return trim((string) $names[0]);
            }
        }

        return null;
    }

    /**
     * Display the print page for a sub-application
     */
    public function printPage(Request $request)
    {
        // Get all the application data from the request
        $data = [
            'applicationId' => $request->get('applicationId', 'SUB-' . rand(10000, 99999)),
            'isSUA' => $request->get('isSUA', false),
            'landUse' => $request->get('landUse', 'Residential'),
            'applicantType' => $request->get('applicantType'),
            'applicantName' => $request->get('applicantName'),
            'applicantEmail' => $request->get('applicantEmail'),
            'applicantPhone' => $request->get('applicantPhone'),
            'applicantAddress' => $request->get('applicantAddress'),
            'unitType' => $request->get('unitType'),
            'units' => $request->get('units'),
            'blocks' => $request->get('blocks'),
            'sections' => $request->get('sections'),
            'unitSize' => $request->get('unitSize'),
            'fileNumber' => $request->get('fileNumber'),
            'primaryFileNo' => $request->get('primaryFileNo'),
            'mlsFileNo' => $request->get('mlsFileNo'),
            'propertyLocation' => $request->get('propertyLocation'),
            'mainApplicationId' => $request->get('mainApplicationId'),
            'schemeNo' => $request->get('schemeNo'),
            'applicationFee' => $request->get('applicationFee'),
            'processingFee' => $request->get('processingFee'),
            'surveyFee' => $request->get('surveyFee'),
            'totalFee' => $request->get('totalFee'),
            'receiptNumber' => $request->get('receiptNumber'),
            'paymentDate' => $request->get('paymentDate'),
            'documents' => $request->get('documents', [])
        ];

        return view('sectionaltitling.sub-application-print', $data);
    }

    /**
     * Save shared areas to the shared_utilities table
     */
    private function saveSharedUtilitiesToTable(Request $request, $applicationId, $subApplicationId): void
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
            $sharedAreasArray = array_merge($sharedAreasArray, array_filter($otherAreas));
        }

        // Remove existing utilities for this sub-application
        DB::connection('sqlsrv')->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->delete();

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
