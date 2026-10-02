<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\SectionalTitleHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Exception;

class PrimaryApplicationController extends Controller
{

    /**
     * Persist shared utilities into the shared_utilities table for downstream processes.
     */
    private function saveSharedUtilitiesToTable(Request $request, int $applicationId): void
    {
        if (!$request->has('shared_areas') || !is_array($request->input('shared_areas'))) {
            return;
        }

        $sharedAreasArray = $request->input('shared_areas');

        DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->delete();

        foreach (array_values($sharedAreasArray) as $index => $utility) {
            if (!empty(trim((string) $utility))) {
                DB::connection('sqlsrv')->table('shared_utilities')->insert([
                    'application_id' => $applicationId,
                    'sub_application_id' => null,
                    'utility_type' => trim((string) $utility),
                    'dimension' => null,
                    'count' => null,
                    'order' => $index + 1,
                    'created_by' => Auth::id() ?? 1,
                    'updated_by' => Auth::id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Display the primary application form
     */
    public function index(Request $request)
    {
        // Simple controller - no file number generation logic
        // Values will come from ST API through JavaScript
        
        return view('primaryform.index', [
            'PageTitle' => 'Primary Application Form',
            'PageDescription' => 'Submit a new sectional title application',
            'landUse' => $request->query('landuse', 'COMMERCIAL'),
            'currentYear' => date('Y'),
            'serialNo' => '',
            'npFileNo' => '',
            'draftMeta' => [
                'last_saved_at' => null,
                'progress_percent' => 0,
                'collaborators' => [],
                'mode' => 'fresh',
                'drafts' => []
            ]
        ]);
    }

    /**
     * Store a new primary application
     */
    public function store(Request $request)
    {
        try {
            $this->hydrateLegacyFields($request);

            // Debug: Log ALL incoming request data
            Log::info('=== PRIMARY APPLICATION FORM DEBUG ===');
            Log::info('All Request Data:', $request->all());
            Log::info('Files:', array_keys($request->allFiles()));
            Log::info('Form Field Count: ' . count($request->all()));
            Log::info('Buyer payload snapshot:', [
                'records_count' => is_array($request->input('records')) ? count($request->input('records')) : 0,
                'records' => $request->input('records', []),
                'buyers_json_present' => $request->filled('buyers_json')
            ]);
            
            // Debug: Check specific expected fields
            $expectedFields = [
                'np_fileno', 'fileno', 'land_use', 'applicant_type', 'title', 'fname', 'lname',
                'email', 'phone', 'address', 'address_house_no', 'owner_street_name', 'owner_lga', 'owner_state',
                'scheme_no', 'property_house_no', 'property_plot_no', 'property_street_name', 'property_district',
                'property_lga', 'property_state', 'units_count', 'blocks_count', 'sections_count'
            ];
            foreach ($expectedFields as $field) {
                Log::info("Field {$field}:", ['value' => $request->input($field, 'NOT PROVIDED')]);
            }
            Log::info('=== END DEBUG ===');

            // Validate required fields
            $validated = $request->validate([
                // API-provided identifiers (required)
                'np_fileno' => 'required|string|max:50',
                'fileno' => 'required|string|max:1000',
                'land_use' => 'required|string|max:1000',
                'applicant_type' => 'required|string|in:individual,corporate,multiple,Individual,Corporate,Multiple',

                // Applicant information (conditional based on type)
                'applicant_title' => 'nullable|string|max:1000',
                'first_name' => 'nullable|string|max:1000',
                'middle_name' => 'nullable|string|max:1000',
                'surname' => 'nullable|string|max:1000',
                'corporate_name' => 'nullable|string|max:1000',
                'rc_number' => 'nullable|string|max:1000',
                'multiple_owners_names' => 'nullable|array',
                'multiple_owners_address' => 'nullable|array',
                'multiple_owners_email' => 'nullable|array',
                'multiple_owners_phone' => 'nullable|array',
                'multiple_owners_identification_type' => 'nullable|array',
                'multiple_owners_passport' => 'nullable|array',
                'multiple_owners_passport.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'multiple_owners_identification_image' => 'nullable|array',
                'multiple_owners_identification_image.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',

                // Contact information
                'address_house_no' => 'nullable|string|max:1000',
                'address_plot_no' => 'nullable|string|max:1000',
                'owner_street_name' => 'nullable|string|max:1000',
                'owner_district' => 'nullable|string|max:1000',
                'owner_lga' => 'nullable|string|max:1000',
                'owner_state' => 'nullable|string|max:1000',
                'phone_number' => 'nullable|array',
                'phone' => 'nullable|string|max:255',
                'phone_alternate' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:1000',
                'owner_email' => 'nullable|email|max:1000',
                'idType' => 'nullable|string|max:255',
                'identification_type' => 'nullable|string|max:255',
                'identification_others' => 'nullable|string|max:500',

                // Property information
                'scheme_no' => 'nullable|string|max:1000',
                'scheme_number' => 'nullable|string|max:1000',
                'property_house_no' => 'nullable|string|max:1000',
                'property_plot_no' => 'nullable|string|max:1000',
                'property_street_name' => 'nullable|string|max:1000',
                'property_district' => 'nullable|string|max:1000',
                'property_lga' => 'nullable|string|max:1000',
                'property_state' => 'nullable|string|max:1000',
                'plot_size' => 'nullable|string|max:1000',

                // Property details
                'units_count' => 'nullable|integer',
                'blocks_count' => 'nullable|integer',
                'sections_count' => 'nullable|integer',
                'residential_type' => 'nullable|string|max:1000',
                'residenceType' => 'nullable|string|max:255',
                'otherResidenceType' => 'nullable|string|max:255',
                'commercial_type' => 'nullable|string|max:1000',
                'commercial_type_others' => 'nullable|string|max:255',
                'industrial_type' => 'nullable|string|max:1000',
                'industrial_type_others' => 'nullable|string|max:255',
                'ownership_type' => 'nullable|string|max:1000',
                'ownershipType' => 'nullable|string|max:1000',
                'ownership_type_others_text' => 'nullable|string|max:1000',
                'otherOwnership' => 'nullable|string|max:255',

                // Payment information
                'application_fee' => 'nullable|string|max:1000',
                'processing_fee' => 'nullable|string|max:1000',
                'site_plan_fee' => 'nullable|string|max:1000',

                // Individual payment tracking
                'application_fee_payment_date' => 'nullable|date',
                'application_fee_receipt_number' => 'nullable|string|max:100',
                'processing_fee_payment_date' => 'nullable|date',
                'processing_fee_receipt_number' => 'nullable|string|max:100',
                'site_plan_fee_payment_date' => 'nullable|date',
                'site_plan_fee_receipt_number' => 'nullable|string|max:100',

                // Legacy payment fields (for backward compatibility)
                'payment_date' => 'nullable|string|max:1000',
                'receipt_number' => 'nullable|string|max:1000',

                // Additional fields
                'comments' => 'nullable|string|max:1000',
                'shared_areas' => 'nullable|array',
                'shared_areas.*' => 'nullable|string|max:255',
                'other_areas_detail' => 'nullable|string|max:500',
                'documents' => 'nullable|array',
                'application_date' => 'nullable|date',

                // Files
                'passport' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'id_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'rc_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'scan_upload_files' => 'nullable|array',
                'scan_upload_files.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                'csv_file' => 'nullable|file|max:5120|mimes:csv,txt',

                // Document uploads (accompanying documents)
                'application_letter' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'building_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'architectural_design' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'ownership_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
                'survey_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',

                // ST API metadata
                'selected_file_data' => 'nullable',
                'selected_file_id' => 'nullable|string|max:255',
                'selected_file_type' => 'nullable|string|max:255',
                'applied_file_number' => 'nullable|string|max:255',
                'tracking_id' => 'nullable|string',
                'primary_file_id' => 'nullable|string',

                // Buyer records
                'records' => 'nullable|array',
                'records.*.buyerTitle' => 'nullable|string|max:255',
                'records.*.firstName' => 'required_with:records|string|max:255',
                'records.*.middleName' => 'nullable|string|max:255',
                'records.*.surname' => 'required_with:records|string|max:255',
                'records.*.buyerName' => 'nullable|string|max:1000',
                'records.*.unit_no' => 'required_with:records|string|max:255',
                'records.*.unitNumber' => 'nullable|string|max:255',
                'records.*.unitMeasurement' => 'nullable|string|max:255',
                'records.*.cubicMeasurement' => 'nullable|string|max:255',
                'records.*.sectionNumber' => 'required_with:records|string|max:255',
                'records.*.section_number' => 'nullable|string|max:255',
                'records.*.landUse' => 'nullable|string|max:255',
                'buyers_json' => 'nullable|string'
            ]);

            // Handle file uploads - handle passport, id_document, and rc_document separately
            $passportPath = null;
            $idDocumentPath = null;
            $rcDocumentPath = null;
            
            if ($request->hasFile('passport')) {
                $passportPath = $request->file('passport')->store('passports', 'public');
                Log::info('Passport file uploaded:', [
                    'path' => $passportPath,
                    'original_name' => $request->file('passport')->getClientOriginalName(),
                    'size' => $request->file('passport')->getSize()
                ]);
            }
            
            if ($request->hasFile('id_document')) {
                $idDocumentPath = $request->file('id_document')->store('id_documents', 'public');
                Log::info('ID document file uploaded:', [
                    'path' => $idDocumentPath,
                    'original_name' => $request->file('id_document')->getClientOriginalName(),
                    'size' => $request->file('id_document')->getSize()
                ]);
            }
            
            if ($request->hasFile('rc_document')) {
                $rcDocumentPath = $request->file('rc_document')->store('rc_documents', 'public');
                Log::info('RC document file uploaded:', [
                    'path' => $rcDocumentPath,
                    'original_name' => $request->file('rc_document')->getClientOriginalName(),
                    'size' => $request->file('rc_document')->getSize()
                ]);
            }

            $multipleOwnersIdImages = [];
            if ($request->hasFile('multiple_owners_identification_image')) {
                foreach ($request->file('multiple_owners_identification_image') as $file) {
                    if ($file && $file->isValid()) {
                        $multipleOwnersIdImages[] = $file->store('multiple_owners_ids', 'public');
                    }
                }
            }

            $multipleOwnersPassportPaths = [];
            if ($request->hasFile('multiple_owners_passport')) {
                foreach ($request->file('multiple_owners_passport') as $file) {
                    if ($file && $file->isValid()) {
                        $multipleOwnersPassportPaths[] = $file->store('multiple_owners_passports', 'public');
                    }
                }
            }

            // Handle document uploads (accompanying documents)
            $documentPayload = [];
            $edmsDocuments = [];
            $documentTypes = ['application_letter', 'building_plan', 'architectural_design', 'ownership_document', 'survey_plan'];
            
            foreach ($documentTypes as $docType) {
                if ($request->hasFile($docType)) {
                    $file = $request->file($docType);
                    if ($file && $file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $path = $file->store('documents', 'public');
                        
                        // Add detailed info about each document
                        $documentMeta = [
                            'path' => $path,
                            'original_name' => $originalName,
                            'type' => $extension,
                            'uploaded_at' => now()->toDateTimeString()
                        ];
                        $documentPayload[$docType] = $documentMeta;
                        
                        // Add to EDMS documents array for processing
                        $edmsDocuments[] = array_merge($documentMeta, [
                            'doc_type' => $docType,
                            'source' => 'accompanying'
                        ]);
                        
                        Log::info('Document uploaded', [
                            'docType' => $docType,
                            'path' => $path,
                            'original_name' => $originalName
                        ]);
                    }
                }
            }

            // Process scan uploads (drag & drop interface)
            $scanUploadPayload = [];
            foreach (Arr::wrap($request->file('scan_upload_files')) as $index => $scanFile) {
                if ($scanFile && $scanFile->isValid()) {
                    $originalName = $scanFile->getClientOriginalName();
                    $extension = $scanFile->getClientOriginalExtension();
                    $path = $scanFile->store('scan_uploads', 'public');

                    $scanMeta = [
                        'path' => $path,
                        'original_name' => $originalName,
                        'type' => $extension,
                        'uploaded_at' => now()->toDateTimeString()
                    ];

                    $scanUploadPayload[] = $scanMeta;
                    $edmsDocuments[] = array_merge($scanMeta, [
                        'doc_type' => 'scan_upload_' . ($index + 1),
                        'source' => 'scan_upload'
                    ]);
                }
            }

            if (!empty($scanUploadPayload)) {
                $documentPayload['scan_uploads'] = $scanUploadPayload;
            }

            // Normalize applicant_type to lowercase for consistency
            $applicantType = strtolower($validated['applicant_type']);

            // Decode selected file metadata for legacy payloads
            $selectedFileData = null;
            $selectedFileRaw = $request->input('selected_file_data');
            if (is_array($selectedFileRaw)) {
                $selectedFileData = $selectedFileRaw;
            } elseif (is_string($selectedFileRaw) && trim($selectedFileRaw) !== '') {
                $decoded = json_decode($selectedFileRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $selectedFileData = $decoded;
                }
            }

            $fileNo = $validated['fileno'] ?? ($selectedFileData['fileno'] ?? null);
            $npFileNo = $validated['np_fileno'] ?? ($selectedFileData['np_fileno'] ?? $selectedFileData['fileno'] ?? null);
            $mlsFileNo = $selectedFileData['mls_fileno'] ?? $request->input('mlsPreviewFileNumber');
            $kangisFileNo = $selectedFileData['kangis_fileno'] ?? $request->input('kangisPreviewFileNumber');
            $newKangisFileNo = $selectedFileData['new_kangis_fileno'] ?? $request->input('newKangisPreviewFileNumber');
            $schemeNumber = $validated['scheme_no'] ?? $request->input('scheme_number') ?? ($selectedFileData['scheme_no'] ?? null);

            $landUseInput = $selectedFileData['land_use'] ?? $validated['land_use'] ?? 'Residential';
            $landUseNormalized = strtoupper(trim($landUseInput ?: 'Residential'));
            if ($landUseNormalized === '') {
                $landUseNormalized = 'RESIDENTIAL';
            }
            $landUseForStorage = ucwords(strtolower($landUseInput ?: 'Residential'));

            $residentialTypeInput = $request->input('residential_type') ?? $request->input('residenceType');
            $residentialTypeResolved = ($residentialTypeInput === 'others' && $request->filled('otherResidenceType'))
                ? $request->input('otherResidenceType')
                : $residentialTypeInput;

            $commercialTypeInput = $request->input('commercial_type');
            $commercialTypeResolved = ($commercialTypeInput === 'Others' && $request->filled('commercial_type_others'))
                ? $request->input('commercial_type_others')
                : $commercialTypeInput;

            $industrialTypeInput = $request->input('industrial_type');
            $industrialTypeResolved = ($industrialTypeInput === 'Others' && $request->filled('industrial_type_others'))
                ? $request->input('industrial_type_others')
                : $industrialTypeInput;

            $ownershipTypeValue = $request->input('ownership_type') ?? $request->input('ownershipType');
            $ownershipTypeOther = $request->input('ownership_type_others_text') ?? $request->input('otherOwnership');

            $mixedTypeDetails = null;
            if ($landUseNormalized === 'MIXED') {
                $mixedTypeDetails = [
                    'residential' => $residentialTypeResolved,
                    'commercial' => $commercialTypeResolved,
                ];
            }
            $mixedTypeJson = $mixedTypeDetails
                ? json_encode($mixedTypeDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            // Shared areas
            $sharedAreasJson = null;
            if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
                $sharedAreasArray = array_values(array_filter($request->input('shared_areas'), function ($value) {
                    return $value !== null && $value !== '';
                }));

                if (in_array('other', $sharedAreasArray, true) && $request->filled('other_areas_detail')) {
                    $sharedAreasArray['other_details'] = $request->input('other_areas_detail');
                }

                $sharedAreasJson = json_encode($sharedAreasArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Phone numbers (deduplicated)
            $phoneValues = [];
            $phoneInput = $request->input('phone_number');
            if (is_array($phoneInput)) {
                foreach ($phoneInput as $value) {
                    if ($value !== null) {
                        $trimmed = trim((string) $value);
                        if ($trimmed !== '') {
                            $phoneValues[] = $trimmed;
                        }
                    }
                }
            } elseif ($phoneInput !== null) {
                $trimmed = trim((string) $phoneInput);
                if ($trimmed !== '') {
                    $phoneValues[] = $trimmed;
                }
            }

            foreach (['phone', 'phone_alternate'] as $legacyPhoneField) {
                if ($request->filled($legacyPhoneField)) {
                    $trimmed = trim((string) $request->input($legacyPhoneField));
                    if ($trimmed !== '') {
                        $phoneValues[] = $trimmed;
                    }
                }
            }
            $phoneValues = array_values(array_unique($phoneValues));
            $phoneNumber = !empty($phoneValues) ? implode(', ', $phoneValues) : null;

            // Normalize multi-owner arrays
            $trimArray = static function ($value) {
                return is_string($value) ? trim($value) : $value;
            };

            $multipleOwnersNames = is_array($request->input('multiple_owners_names'))
                ? array_map($trimArray, $request->input('multiple_owners_names'))
                : null;
            $multipleOwnersAddresses = is_array($request->input('multiple_owners_address'))
                ? array_map($trimArray, $request->input('multiple_owners_address'))
                : null;
            $multipleOwnersEmails = is_array($request->input('multiple_owners_email'))
                ? array_map($trimArray, $request->input('multiple_owners_email'))
                : null;
            $multipleOwnersPhones = is_array($request->input('multiple_owners_phone'))
                ? array_map($trimArray, $request->input('multiple_owners_phone'))
                : null;
            $multipleOwnersIdentificationTypes = is_array($request->input('multiple_owners_identification_type'))
                ? array_map($trimArray, $request->input('multiple_owners_identification_type'))
                : null;

            $arrayHasValues = static function (?array $items): bool {
                if (!is_array($items)) {
                    return false;
                }
                return count(array_filter($items, function ($value) {
                    return $value !== null && trim((string) $value) !== '';
                })) > 0;
            };

            $multipleOwnersNamesHasValue = $arrayHasValues($multipleOwnersNames);
            $multipleOwnersAddressesHasValue = $arrayHasValues($multipleOwnersAddresses);
            $multipleOwnersEmailsHasValue = $arrayHasValues($multipleOwnersEmails);
            $multipleOwnersPhonesHasValue = $arrayHasValues($multipleOwnersPhones);
            $multipleOwnersIdentificationTypesHasValue = $arrayHasValues($multipleOwnersIdentificationTypes);
            $multipleOwnersPassportHasValue = !empty(array_filter($multipleOwnersPassportPaths));
            $multipleOwnersIdImagesHasValue = !empty(array_filter($multipleOwnersIdImages));

            // Resolve owner fullname
            $ownerFullNameContext = $validated;
            if ($multipleOwnersNamesHasValue) {
                $ownerFullNameContext['multiple_owners_names'] = $multipleOwnersNames;
            }
            $ownerFullName = $this->generateOwnerFullName($ownerFullNameContext, $applicantType);
            if ($applicantType === 'multiple' && $multipleOwnersNamesHasValue) {
                $nonEmptyNames = array_values(array_filter($multipleOwnersNames, function ($value) {
                    return $value !== null && trim((string) $value) !== '';
                }));
                if (!empty($nonEmptyNames)) {
                    $ownerFullName = count($nonEmptyNames) > 1
                        ? $nonEmptyNames[0] . ' et al.'
                        : $nonEmptyNames[0];
                }
            }

            $contactAddress = $request->input('contact_address') ?? $request->input('address');
            if (!$contactAddress) {
                $contactAddress = implode(', ', array_filter([
                    $validated['address_house_no'] ?? null,
                    $request->input('address_plot_no'),
                    $validated['owner_street_name'] ?? null,
                    $validated['owner_district'] ?? null,
                    $validated['owner_lga'] ?? null,
                    $validated['owner_state'] ?? null
                ]));
            }

            $ownerEmail = $request->input('owner_email') ?? $validated['email'] ?? null;
            $identificationType = $request->input('identification_type') ?? $request->input('idType');
            $identificationOthers = ($identificationType === 'others')
                ? $request->input('identification_others')
                : null;

            $selectedFileId = $validated['selected_file_id'] ?? ($selectedFileData['id'] ?? $request->input('primary_file_id'));
            $selectedFileType = $validated['selected_file_type']
                ?? ($selectedFileData['file_no_type'] ?? ($selectedFileData['system'] ?? null));
            if (is_string($selectedFileType) && $selectedFileType !== '') {
                $selectedFileType = strtoupper($selectedFileType);
            } elseif ($selectedFileType === null && $selectedFileData) {
                $fallbackType = $selectedFileData['file_no_type'] ?? 'ST_FILE_NUMBER';
                $selectedFileType = is_string($fallbackType) ? strtoupper($fallbackType) : 'ST_FILE_NUMBER';
            }

            $selectedFileDataJson = $selectedFileData
                ? json_encode($selectedFileData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (is_string($selectedFileRaw) && trim($selectedFileRaw) !== '' ? $selectedFileRaw : null);

            $appliedFileNumber = $validated['applied_file_number'] ?? ($selectedFileData['fileno'] ?? $fileNo);
            $trackingId = $validated['tracking_id'] ?? ($selectedFileData['tracking_id'] ?? $selectedFileData['tra'] ?? null);
            $primaryFileId = $validated['primary_file_id'] ?? ($selectedFileData['id'] ?? null);

            $paymentDate = $request->input('application_fee_payment_date')
                ?? $request->input('processing_fee_payment_date')
                ?? $request->input('site_plan_fee_payment_date')
                ?? $validated['payment_date'] ?? null;
            $receiptNumber = $request->input('application_fee_receipt_number')
                ?? $request->input('processing_fee_receipt_number')
                ?? $request->input('site_plan_fee_receipt_number')
                ?? $validated['receipt_number'] ?? null;
            $receiptDate = $request->input('application_fee_payment_date')
                ?? $request->input('processing_fee_payment_date')
                ?? $request->input('site_plan_fee_payment_date')
                ?? null;
            $paymentStatus = $paymentDate ? 'Paid' : 'Pending';

            $documentsJson = !empty($documentPayload)
                ? json_encode($documentPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
            $surveyPlanPath = $documentPayload['survey_plan']['path'] ?? null;
            $applicationDate = $validated['application_date'] ?? now()->format('Y-m-d');

            // Prepare data for database insertion - exact column mapping
            $applicationData = [
                'np_fileno' => $npFileNo,
                'fileno' => $fileNo,
                'land_use' => $landUseForStorage,
                'applicant_type' => $applicantType,
                'applicant_title' => ($applicantType === 'individual') ? ($validated['applicant_title'] ?? null) : null,
                'first_name' => ($applicantType === 'individual') ? ($validated['first_name'] ?? null) : null,
                'middle_name' => ($applicantType === 'individual') ? ($validated['middle_name'] ?? null) : null,
                'surname' => ($applicantType === 'individual') ? ($validated['surname'] ?? null) : null,
                'corporate_name' => ($applicantType === 'corporate') ? ($validated['corporate_name'] ?? null) : null,
                'rc_number' => ($applicantType === 'corporate') ? ($validated['rc_number'] ?? null) : null,
                'multiple_owners_names' => ($applicantType === 'multiple' && $multipleOwnersNamesHasValue)
                    ? json_encode($multipleOwnersNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_address' => ($applicantType === 'multiple' && $multipleOwnersAddressesHasValue)
                    ? json_encode($multipleOwnersAddresses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_passport' => ($applicantType === 'multiple' && $multipleOwnersPassportHasValue)
                    ? json_encode($multipleOwnersPassportPaths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_email' => ($applicantType === 'multiple' && $multipleOwnersEmailsHasValue)
                    ? json_encode($multipleOwnersEmails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_phone' => ($applicantType === 'multiple' && $multipleOwnersPhonesHasValue)
                    ? json_encode($multipleOwnersPhones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_identification_type' => ($applicantType === 'multiple' && $multipleOwnersIdentificationTypesHasValue)
                    ? json_encode($multipleOwnersIdentificationTypes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'multiple_owners_identification_image' => ($applicantType === 'multiple' && $multipleOwnersIdImagesHasValue)
                    ? json_encode($multipleOwnersIdImages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'address_house_no' => $validated['address_house_no'] ?? null,
                'address_plot_no' => $request->input('address_plot_no'),
                'address_street_name' => $validated['owner_street_name'] ?? null,
                'address_district' => $validated['owner_district'] ?? null,
                'address_lga' => $validated['owner_lga'] ?? null,
                'address_state' => $validated['owner_state'] ?? null,
                'address' => $contactAddress ?: null,
                'phone_number' => $phoneNumber,
                'email' => $ownerEmail,
                'identification_type' => $identificationType,
                'identification_others' => $identificationOthers,
                'owner_fullname' => $ownerFullName,
                'scheme_no' => $schemeNumber,
                'scheme_number' => $schemeNumber,
                'property_house_no' => $validated['property_house_no'] ?? null,
                'property_plot_no' => $validated['property_plot_no'] ?? null,
                'property_street_name' => $validated['property_street_name'] ?? null,
                'property_district' => $validated['property_district'] ?? null,
                'property_lga' => $validated['property_lga'] ?? null,
                'property_state' => $validated['property_state'] ?? null,
                'plot_size' => $validated['plot_size'] ?? null,
                'NoOfUnits' => $validated['units_count'] ?? null,
                'NoOfBlocks' => $validated['blocks_count'] ?? null,
                'NoOfSections' => $validated['sections_count'] ?? null,
                'residential_type' => $residentialTypeResolved ?? ($validated['residential_type'] ?? null),
                'commercial_type' => $commercialTypeResolved ?? ($validated['commercial_type'] ?? null),
                'industrial_type' => $industrialTypeResolved ?? ($validated['industrial_type'] ?? null),
                'ownership_type' => $ownershipTypeValue,
                'ownershipType' => $ownershipTypeValue,
                'ownership' => $ownershipTypeValue,
                'ownership_type_others_text' => $ownershipTypeOther,
                'mixed_type' => $mixedTypeJson,
                'application_fee' => $validated['application_fee'] ?? null,
                'processing_fee' => $validated['processing_fee'] ?? null,
                'site_plan_fee' => $validated['site_plan_fee'] ?? null,
                'application_fee_payment_date' => $request->input('application_fee_payment_date') ?? null,
                'application_fee_receipt_number' => $request->input('application_fee_receipt_number') ?? null,
                'processing_fee_payment_date' => $request->input('processing_fee_payment_date') ?? null,
                'processing_fee_receipt_number' => $request->input('processing_fee_receipt_number') ?? null,
                'site_plan_fee_payment_date' => $request->input('site_plan_fee_payment_date') ?? null,
                'site_plan_fee_receipt_number' => $request->input('site_plan_fee_receipt_number') ?? null,
                'payment_date' => $paymentDate,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $receiptDate,
                'Payment_Status' => $paymentStatus,
                'comments' => $validated['comments'] ?? null,
                'shared_areas' => $sharedAreasJson,
                'documents' => $documentsJson,
                'application_date' => $applicationDate,
                'survey_plan' => $surveyPlanPath,
                'id_document' => $idDocumentPath,
                'passport' => $passportPath,
                'rc_document' => $rcDocumentPath,
                'selected_file_data' => $selectedFileDataJson,
                'selected_file_id' => $selectedFileId,
                'selected_file_type' => $selectedFileType,
                'applied_file_number' => $appliedFileNumber,
                'tracking_id' => $trackingId,
                'primary_file_id' => $primaryFileId,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'application_status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
                'sys_date' => now()
            ];

            // Debug log file upload values before database insert
            Log::info('File upload values before database insert:', [
                'passport_path' => $passportPath,
                'id_document_path' => $idDocumentPath,
                'rc_document_path' => $rcDocumentPath,
                'passport_has_file' => $request->hasFile('passport'),
                'id_document_has_file' => $request->hasFile('id_document'),
                'rc_document_has_file' => $request->hasFile('rc_document'),
                'documents_uploaded' => array_keys($documentPayload),
                'document_count' => count($documentPayload)
            ]);

            // Insert into database
            $applicationId = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->insertGetId($applicationData);

            DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $applicationId)
                ->update([
                    'applicationID' => date('Y') . '-' . str_pad($applicationId, 2, '0', STR_PAD_LEFT),
                ]);

            try {
                $this->saveSharedUtilitiesToTable($request, $applicationId);
            } catch (Throwable $utilitiesException) {
                Log::warning('Failed to sync shared utilities for primary application', [
                    'application_id' => $applicationId,
                    'error' => $utilitiesException->getMessage()
                ]);
            }

            // Update file number status to USED to prevent duplicate usage
            try {
                $fileNumberUpdated = DB::connection('sqlsrv')
                    ->table('st_file_numbers')
                    ->where('np_fileno', $validated['np_fileno'])
                    ->where('fileno', $validated['fileno'])
                    ->update([
                        'status' => 'USED',
                        'used_at' => now(),
                        'mother_application_id' => $applicationId
                    ]);
                
                if ($fileNumberUpdated > 0) {
                    Log::info('File number status updated to USED', [
                        'np_fileno' => $validated['np_fileno'],
                        'fileno' => $validated['fileno'],
                        'application_id' => $applicationId
                    ]);
                } else {
                    Log::warning('File number not found for status update', [
                        'np_fileno' => $validated['np_fileno'],
                        'fileno' => $validated['fileno'],
                        'application_id' => $applicationId
                    ]);
                }
            } catch (Exception $e) {
                Log::error('Failed to update file number status to USED', [
                    'np_fileno' => $validated['np_fileno'],
                    'fileno' => $validated['fileno'],
                    'application_id' => $applicationId,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the entire submission for this - just log the error
            }

            // Persist sectional title file numbers and eRegistry data
            try {
                $createdByName = Auth::user()->first_name ?? (Auth::user()->name ?? 'System');
                SectionalTitleHelper::generateAndInsertFileNumber($applicationId, $createdByName, [
                    'mlsFileNo' => $mlsFileNo,
                    'kangisFileNo' => $kangisFileNo,
                    'newKangisFileNo' => $newKangisFileNo
                ]);
            } catch (Throwable $fileNumberException) {
                Log::error('Failed to generate sectional title file number', [
                    'application_id' => $applicationId,
                    'error' => $fileNumberException->getMessage()
                ]);
            }

            try {
                $eRegistryUpdateData = [
                    'application_id' => $applicationId,
                    'sub_application_id' => null,
                    'MLSFileNo' => $mlsFileNo,
                    'KANGISFileNo' => $kangisFileNo,
                    'NEWKangisFileNo' => $newKangisFileNo,
                    'npFileno' => $npFileNo,
                    'ST_fileNO' => $fileNo,
                    'Commissioning_Date' => now(),
                    'Current_Office' => 'ST Registry',
                    'updated_at' => now()
                ];

                DB::connection('sqlsrv')->table('eRegistry')
                    ->where('application_id', $applicationId)
                    ->update($eRegistryUpdateData);
            } catch (Exception $eRegistryError) {
                Log::error('Error updating eRegistry for primary application', [
                    'application_id' => $applicationId,
                    'error' => $eRegistryError->getMessage()
                ]);
            }

            // Persist buyer list records when provided
            try {
                $buyerRecords = $this->prepareBuyerRecords($request);
                if (!empty($buyerRecords)) {
                    SectionalTitleHelper::insertBuyers($applicationId, $buyerRecords);
                    DB::connection('sqlsrv')
                        ->table('mother_applications')
                        ->where('id', $applicationId)
                        ->update([
                            'final_conveyance' => json_encode($buyerRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        ]);
                    Log::info('Buyer records processed for application', [
                        'application_id' => $applicationId,
                        'buyer_count' => count($buyerRecords)
                    ]);
                } else {
                    Log::info('No buyer records supplied for application', ['application_id' => $applicationId]);
                }
            } catch (Throwable $buyerException) {
                Log::error('Failed to insert buyer records for primary application', [
                    'application_id' => $applicationId,
                    'error' => $buyerException->getMessage()
                ]);
            }

            // Create file indexing entry
            try {
                $fileIndexingData = [
                    'main_application_id' => $applicationId,
                    'st_fillno' => $validated['np_fileno'],
                    'file_number' => $validated['fileno'],
                    'file_title' => $validated['corporate_name'] ?? $validated['first_name'] . ' ' . $validated['surname'],
                    'land_use_type' => $validated['land_use'],
                    'tracking_id' => $validated['tracking_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id()
                ];

                $fileIndexingId = DB::connection('sqlsrv')
                    ->table('file_indexings')
                    ->insertGetId($fileIndexingData);

                Log::info('File indexing created successfully', [
                    'file_indexing_id' => $fileIndexingId,
                    'application_id' => $applicationId
                ]);
                
                // Process uploaded documents for EDMS workflow
                if ($fileIndexingId && !empty($edmsDocuments)) {
                    Log::info('Starting EDMS document processing', [
                        'file_indexing_id' => $fileIndexingId,
                        'file_number' => $validated['np_fileno'], // Using NP file number for EDMS
                        'document_count' => count($edmsDocuments)
                    ]);
                    
                    $this->processDocumentsForEDMS($fileIndexingId, $edmsDocuments, $validated['np_fileno']);
                    
                    Log::info('EDMS document processing completed', [
                        'file_indexing_id' => $fileIndexingId,
                        'application_id' => $applicationId
                    ]);
                } else {
                    Log::warning('EDMS document processing skipped', [
                        'application_id' => $applicationId,
                        'file_indexing_created' => $fileIndexingId ? true : false,
                        'edms_documents_count' => count($edmsDocuments),
                        'reason' => !$fileIndexingId ? 'No file indexing created' : 'No EDMS documents'
                    ]);
                }
                
            } catch (Throwable $indexingException) {
                Log::error('Failed to create file indexing for primary application', [
                    'application_id' => $applicationId,
                    'error' => $indexingException->getMessage()
                ]);
            }

            Log::info('Primary application created successfully', [
                'application_id' => $applicationId,
                'np_fileno' => $validated['np_fileno'],
                'fileno' => $validated['fileno'],
                'applicant_type' => $validated['applicant_type']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Primary application submitted successfully.',
                'redirect_url' => '/blind-scanning?url=st_edms&app_id=' . $applicationId,
                'data' => [
                    'application_id' => $applicationId,
                    'np_fileno' => $validated['np_fileno'],
                    'fileno' => $validated['fileno'],
                    'applicant_info' => [
                        'title' => $validated['title'] ?? '',
                        'fname' => $validated['fname'] ?? '',
                        'lname' => $validated['lname'] ?? '',
                        'applicant_type' => $validated['applicant_type'] ?? '',
                        'land_use' => $validated['land_use'] ?? ''
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for primary application', [
                'errors' => $e->errors(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error creating primary application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting the application. Please try again.'
            ], 500);
        }
    }

    /**
     * Download a simple buyers CSV template for bulk uploads.
     */
    public function downloadBuyersTemplate()
    {
        $csvContent = "title,first name,middle name,surname,land use,unit number,section number,unit measurement,cubic measurement\r\n";
        $csvContent .= "Mr.,John,Michael,Doe,Residential,A001,SEC-01,50,15\r\n";
        $csvContent .= "Mrs.,Jane,Elizabeth,Smith,Commercial,B002,SEC-02,75,20\r\n";
        $csvContent .= "Dr.,Robert,James,Brown,Mixed,C003,SEC-03,100,35\r\n";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="buyers_template.csv"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Transfer-Encoding' => 'binary',
            'Expires' => '0',
        ];

        return response($csvContent, 200, $headers);
    }

    /**
     * Lightweight application lookup used by the legacy autofill widget.
     */
    public function getApplicationDetails($fileno)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('fileno', $fileno)
                ->orWhere('np_fileno', $fileno)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $application->id,
                    'fileno' => $application->fileno,
                    'np_fileno' => $application->np_fileno,
                    'scheme_no' => $application->scheme_no,
                    'property_street_name' => $application->property_street_name,
                    'property_lga' => $application->property_lga,
                    'property_state' => $application->property_state,
                    'property_house_no' => $application->property_house_no,
                    'property_plot_no' => $application->property_plot_no,
                    'property_district' => $application->property_district,
                    'applicant_type' => $application->applicant_type,
                    'applicant_title' => $application->applicant_title,
                    'first_name' => $application->first_name,
                    'middle_name' => $application->middle_name,
                    'surname' => $application->surname,
                    'corporate_name' => $application->corporate_name,
                    'rc_number' => $application->rc_number,
                    'land_use' => $application->land_use
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching application details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize legacy field names and ensure critical ST API data is present.
     */
    protected function hydrateLegacyFields(Request $request): void
    {
        $selectedMeta = null;
        $selectedRaw = $request->input('selected_file_data');

        if (!empty($selectedRaw)) {
            if (is_array($selectedRaw)) {
                $selectedMeta = $selectedRaw;
            } elseif (is_string($selectedRaw)) {
                $decoded = json_decode($selectedRaw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $selectedMeta = $decoded;
                }
            }
        }

        $merge = [];

        $merge['applicant_type'] = $request->input('applicant_type')
            ?? $request->input('applicantType')
            ?? ($selectedMeta['applicant_type'] ?? null);

        $merge['applicant_title'] = $request->input('applicant_title')
            ?? $request->input('title')
            ?? ($selectedMeta['applicant_title'] ?? null);

        $merge['first_name'] = $request->input('first_name')
            ?? $request->input('fname')
            ?? ($selectedMeta['first_name'] ?? null);

        $merge['middle_name'] = $request->input('middle_name')
            ?? $request->input('mname')
            ?? ($selectedMeta['middle_name'] ?? null);

        $merge['surname'] = $request->input('surname')
            ?? $request->input('lname')
            ?? ($selectedMeta['surname'] ?? null);

        $merge['corporate_name'] = $request->input('corporate_name')
            ?? ($selectedMeta['corporate_name'] ?? null);

        $merge['rc_number'] = $request->input('rc_number')
            ?? ($selectedMeta['rc_number'] ?? null);

        $merge['np_fileno'] = $request->input('np_fileno')
            ?? ($selectedMeta['np_fileno'] ?? $selectedMeta['fileno'] ?? null);

        $merge['fileno'] = $request->input('fileno')
            ?? ($selectedMeta['fileno'] ?? $selectedMeta['full_file_number'] ?? null);

        $merge['land_use'] = $request->input('land_use')
            ?? ($selectedMeta['land_use'] ?? $selectedMeta['land_use_code'] ?? null);

        $merge['tracking_id'] = $request->input('tracking_id')
            ?? ($selectedMeta['tra'] ?? $selectedMeta['tracking_id'] ?? null);

        $merge['primary_file_id'] = $request->input('primary_file_id')
            ?? ($selectedMeta['id'] ?? null);

        $merge['selected_file_id'] = $request->input('selected_file_id')
            ?? ($selectedMeta['id'] ?? null);

        $merge['selected_file_type'] = $request->input('selected_file_type')
            ?? ($selectedMeta['file_no_type'] ?? null);

        $merge['applied_file_number'] = $request->input('applied_file_number')
            ?? ($selectedMeta['fileno'] ?? $selectedMeta['full_file_number'] ?? null);

        // Ensure phone_number array is available when only phone field exists
        if (!$request->has('phone_number') && $request->filled('phone')) {
            $merge['phone_number'] = [$request->input('phone')];
        }

        // Ensure contact address fallback
        // Only merge non-null values to avoid overriding intentionally empty fields
        $merge = array_filter($merge, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }

            return $value !== null && $value !== '';
        });

        if (!empty($merge)) {
            $request->merge($merge);
        }
    }

    /**
     * Generate owner full name based on applicant type
     */
    protected function generateOwnerFullName(array $validated, string $applicantType): ?string
    {
        if ($applicantType === 'corporate') {
            return $validated['corporate_name'] ?? null;
        } elseif ($applicantType === 'multiple') {
            // For multiple owners, join all names
            if (isset($validated['multiple_owners_names']) && is_array($validated['multiple_owners_names'])) {
                return implode(', ', array_filter($validated['multiple_owners_names']));
            }
            return 'Multiple Owners';
        } else {
            // Individual - combine title, first name, middle name, surname
            $nameParts = array_filter([
                $validated['applicant_title'] ?? null,
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['surname'] ?? null
            ]);
            return !empty($nameParts) ? implode(' ', $nameParts) : null;
        }
    }

    /**
     * Prepare buyer records from request payloads (dynamic form + JSON imports).
     */
    protected function prepareBuyerRecords(Request $request): array
    {
        $records = $request->input('records', []);
        if (!is_array($records)) {
            $records = [];
        }

        if ($request->filled('buyers_json')) {
            $decoded = json_decode($request->input('buyers_json'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $records = array_merge($records, $decoded);
            }
        }

        if ($request->hasFile('csv_file')) {
            try {
                $csvFile = $request->file('csv_file');
                if (($handle = fopen($csvFile->getRealPath(), 'r')) !== false) {
                    $headers = fgetcsv($handle, 1000, ',');
                    if ($headers) {
                        $headers = array_map(function ($value) {
                            return strtolower(trim((string) $value));
                        }, $headers);

                        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                            if (count($row) < 1) {
                                continue;
                            }

                            $buyer = [];
                            foreach ($headers as $index => $header) {
                                $value = $row[$index] ?? '';
                                $value = is_string($value) ? trim($value) : $value;

                                switch ($header) {
                                    case 'title':
                                        $buyer['buyerTitle'] = $value;
                                        break;
                                    case 'first name':
                                    case 'first_name':
                                        $buyer['firstName'] = $value;
                                        break;
                                    case 'middle name':
                                    case 'middle_name':
                                        $buyer['middleName'] = $value;
                                        break;
                                    case 'surname':
                                    case 'last name':
                                    case 'last_name':
                                        $buyer['surname'] = $value;
                                        break;
                                    case 'address':
                                        $buyer['buyerAddress'] = $value;
                                        break;
                                    case 'email':
                                        $buyer['buyerEmail'] = $value;
                                        break;
                                    case 'phone':
                                        $buyer['buyerPhone'] = $value;
                                        break;
                                    case 'unit number':
                                    case 'unit_number':
                                    case 'unit no':
                                    case 'unit_no':
                                        $buyer['unitNumber'] = $value;
                                        $buyer['unit_no'] = $value;
                                        break;
                                    case 'unit type':
                                    case 'unit_type':
                                        $buyer['unitType'] = $value;
                                        break;
                                    case 'unit measurement':
                                    case 'unit_measurement':
                                    case 'measurement':
                                        $buyer['unitMeasurement'] = $value;
                                        break;
                                    case 'unit cubic measurement':
                                    case 'unit cubic measurement (cbm)':
                                    case 'cubic measurement':
                                    case 'cubic_measurement':
                                    case 'cubic measurement (cbm)':
                                        $buyer['cubicMeasurement'] = $value;
                                        break;
                                    case 'section number':
                                    case 'section_number':
                                    case 'section':
                                    case 'sectionno':
                                        $buyer['sectionNumber'] = $value;
                                        break;
                                }
                            }

                            $fullName = trim(implode(' ', array_filter([
                                $buyer['buyerTitle'] ?? '',
                                $buyer['firstName'] ?? '',
                                $buyer['middleName'] ?? '',
                                $buyer['surname'] ?? ''
                            ])));
                            if ($fullName !== '') {
                                $buyer['buyerName'] = $fullName;
                            }

                            $records[] = $buyer;
                        }
                    }
                    fclose($handle);
                }
            } catch (Throwable $csvException) {
                Log::error('Failed to parse buyers CSV file', [
                    'error' => $csvException->getMessage()
                ]);
            }
        }

        if (empty($records)) {
            return [];
        }

        $normalised = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $buyerTitle = $record['buyerTitle'] ?? ($record['buyer_title'] ?? null);
            $firstName = $record['firstName'] ?? ($record['first_name'] ?? null);
            $middleName = $record['middleName'] ?? ($record['middle_name'] ?? null);
            $surname = $record['surname'] ?? ($record['lastName'] ?? $record['last_name'] ?? null);
            $buyerName = $record['buyerName'] ?? ($record['buyer_name'] ?? null);
            $unitNo = $record['unit_no'] ?? ($record['unitNo'] ?? $record['unitNumber'] ?? null);
            $unitMeasurement = $record['unitMeasurement'] ?? ($record['measurement'] ?? null);
            $cubicMeasurement = $record['cubicMeasurement'] ?? ($record['cubic_measurement'] ?? null);
            $sectionNumber = $record['sectionNumber'] ?? ($record['section_number'] ?? null);
            if (is_string($sectionNumber)) {
                $sectionNumber = trim($sectionNumber);
                if ($sectionNumber === '') {
                    $sectionNumber = null;
                }
            }
            $landUse = $record['landUse'] ?? ($record['land_use'] ?? null);

            if (!$buyerName && ($buyerTitle || $firstName || $middleName || $surname)) {
                $buyerName = trim(implode(' ', array_filter([
                    $buyerTitle,
                    $firstName,
                    $middleName,
                    $surname
                ])));
            }

            $normalised[] = [
                'buyerTitle' => $buyerTitle,
                'firstName' => $firstName,
                'middleName' => $middleName,
                'surname' => $surname,
                'buyerName' => $buyerName,
                'unit_no' => $unitNo,
                'unitMeasurement' => $unitMeasurement,
                'cubicMeasurement' => $cubicMeasurement,
                'sectionNumber' => $sectionNumber,
                'landUse' => $landUse
            ];
        }

        return array_values(array_filter($normalised, function (array $record) {
            $hasBuyer = !empty($record['buyerName']) || !empty($record['firstName']) || !empty($record['surname']);
            $hasUnit = !empty($record['unit_no']);
            $hasSection = !empty($record['sectionNumber']);

            return $hasBuyer && $hasUnit && $hasSection;
        }));
    }

    /**
     * Process uploaded documents for EDMS workflow
     * Uses NP File Number for folder creation and file naming
     */
    private function processDocumentsForEDMS($fileIndexingId, $documents, $npFileNumber)
    {
        try {
            Log::info('Processing documents for EDMS workflow', [
                'file_indexing_id' => $fileIndexingId,
                'np_file_number' => $npFileNumber,
                'documents_count' => count($documents)
            ]);

            // Create EDMS directory structure: EDMS/SCAN_UPLOAD/{npFileNumber}/
            $edmsBasePath = "EDMS/SCAN_UPLOAD/{$npFileNumber}";
            if (!Storage::disk('public')->exists($edmsBasePath)) {
                Storage::disk('public')->makeDirectory($edmsBasePath);
                Log::info('Created EDMS directory', [
                    'path' => Storage::disk('public')->path($edmsBasePath)
                ]);
            }

            // Get current maximum display_order for this file_indexing_id
            $maxDisplayOrder = DB::connection('sqlsrv')
                ->table('scannings')
                ->where('file_indexing_id', $fileIndexingId)
                ->max('display_order') ?? 0;

            $displayOrder = $maxDisplayOrder + 1;
            $sequenceNumber = 1;

            foreach ($documents as $document) {
                try {
                    $docType = $document['doc_type'] ?? 'document';
                    $source = $document['source'] ?? 'accompanying';
                    $relativeSourcePath = $document['path'] ?? null;
                    $originalName = $document['original_name'] ?? null;

                    if (!$relativeSourcePath || !$originalName) {
                        Log::warning('Skipping document with incomplete metadata', [
                            'doc_type' => $docType,
                            'source' => $source,
                            'metadata' => $document
                        ]);
                        continue;
                    }

                    // Verify the source file exists
                    if (!Storage::disk('public')->exists($relativeSourcePath)) {
                        Log::warning('Original document file not found', [
                            'doc_type' => $docType,
                            'source' => $source,
                            'path' => $relativeSourcePath,
                            'full_path' => Storage::disk('public')->path($relativeSourcePath)
                        ]);
                        continue;
                    }

                    // Extract file extension
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $extension = $extension ?: ($document['type'] ?? 'pdf');
                    $extension = strtolower(ltrim((string) $extension, '.'));
                    if ($extension === '') {
                        $extension = 'pdf';
                    }

                    // Generate sequential filename using NP file number like: ST-COM-2025-01-002_0001.pdf
                    $sequenceStr = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);
                    $newFilename = "{$npFileNumber}_{$sequenceStr}.{$extension}";
                    $destinationPath = "{$edmsBasePath}/{$newFilename}";

                    // Copy file to EDMS structure with collision handling
                    try {
                        if (!Storage::disk('public')->exists($destinationPath)) {
                            Storage::disk('public')->copy($relativeSourcePath, $destinationPath);
                        } else {
                            // Handle filename collision by adding unique identifier
                            $newFilename = "{$npFileNumber}_{$sequenceStr}_" . uniqid() . ".{$extension}";
                            $destinationPath = "{$edmsBasePath}/{$newFilename}";
                            Storage::disk('public')->copy($relativeSourcePath, $destinationPath);
                        }
                    } catch (Exception $copyError) {
                        Log::error('Failed to copy document to EDMS structure', [
                            'doc_type' => $docType,
                            'source' => $source,
                            'error' => $copyError->getMessage(),
                            'original_path' => $relativeSourcePath,
                            'destination_path' => $destinationPath
                        ]);
                        continue;
                    }

                    // Verify the copy was successful
                    if (!Storage::disk('public')->exists($destinationPath)) {
                        Log::error('File copy failed verification', [
                            'original_path' => $relativeSourcePath,
                            'destination_path' => $destinationPath,
                            'doc_type' => $docType,
                            'source' => $source
                        ]);
                        continue;
                    }

                    // Map document type and generate notes
                    $documentTypeLabel = $source === 'scan_upload'
                        ? 'Scan Upload Document'
                        : $this->mapDocumentType($docType);

                    $notes = $source === 'scan_upload'
                        ? 'Uploaded from Scan Upload (File Documents) section during primary application submission.'
                        : "Uploaded from primary application form - Document type: {$docType}. Original filename: {$originalName}";

                    // Prepare scanning record data - mapped to match database structure
                    $scanningData = [
                        'file_indexing_id' => $fileIndexingId,
                        'document_path' => $destinationPath, // Full path within public storage
                        'original_filename' => $originalName,
                        'uploaded_by' => Auth::id(),
                        'status' => 'pending', // Status: pending, scanned, reviewed, approved
                        'paper_size' => $this->detectPaperSize($originalName),
                        'document_type' => $documentTypeLabel,
                        'notes' => $notes,
                        'display_order' => $displayOrder,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    // Insert scanning record using raw DB query for better error handling
                    try {
                        $scanningId = DB::connection('sqlsrv')->table('scannings')->insertGetId($scanningData);

                        if (!$scanningId) {
                            Log::error('Failed to insert scanning record - no ID returned', [
                                'scanning_data' => $scanningData
                            ]);
                            continue;
                        }

                        Log::info('Scanning record created successfully', [
                            'scanning_id' => $scanningId,
                            'file_indexing_id' => $fileIndexingId,
                            'document_type' => $documentTypeLabel,
                            'display_order' => $displayOrder
                        ]);

                    } catch (Exception $scanningError) {
                        Log::error('Database error inserting scanning record', [
                            'error' => $scanningError->getMessage(),
                            'trace' => $scanningError->getTraceAsString(),
                            'scanning_data' => $scanningData
                        ]);
                        continue;
                    }

                    Log::info('Document processed successfully for EDMS', [
                        'doc_type' => $docType,
                        'source' => $source,
                        'original_name' => $originalName,
                        'new_filename' => $newFilename,
                        'edms_path' => $destinationPath,
                        'scanning_id' => $scanningId,
                        'display_order' => $displayOrder,
                        'file_size' => Storage::disk('public')->size($destinationPath)
                    ]);

                    $sequenceNumber++;
                    $displayOrder++;
                    
                } catch (Exception $docError) {
                    Log::error('Error processing individual document', [
                        'error' => $docError->getMessage(),
                        'trace' => $docError->getTraceAsString(),
                        'document' => $document
                    ]);
                    continue;
                }
            }

            Log::info('EDMS document processing completed', [
                'file_indexing_id' => $fileIndexingId,
                'np_file_number' => $npFileNumber,
                'total_documents' => count($documents),
                'processed_documents' => $sequenceNumber - 1,
                'final_display_order' => $displayOrder - 1
            ]);

        } catch (Exception $e) {
            Log::error('Error processing documents for EDMS', [
                'file_indexing_id' => $fileIndexingId,
                'np_file_number' => $npFileNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Map document type to scanning document type
     */
    private function mapDocumentType($docType)
    {
        $mapping = [
            // Primary application form documents
            'application_letter' => 'Application Letter',
            'building_plan' => 'Building Plan',
            'architectural_design' => 'Architectural Design',
            'ownership_document' => 'Ownership Document',
            'survey_plan' => 'Survey Plan',
            
            // Scan upload documents (from drag & drop section)
            'scan_upload_1' => 'Scan Upload Document',
            'scan_upload_2' => 'Scan Upload Document',
            'scan_upload_3' => 'Scan Upload Document',
            'scan_upload_4' => 'Scan Upload Document',
            'scan_upload_5' => 'Scan Upload Document',
            
            // Additional document types
            'passport' => 'Passport Photograph',
            'id_document' => 'Identification Document',
            'certificate' => 'Certificate',
            'receipt' => 'Payment Receipt',
            'memo' => 'Official Memo',
            'correspondence' => 'Correspondence',
            'plan' => 'Plan/Drawing',
            'report' => 'Report',
            'form' => 'Official Form',
            'contract' => 'Contract/Agreement',
            'deed' => 'Deed/Title Document',
            'clearance' => 'Clearance Certificate',
            'approval' => 'Approval Document',
            'permit' => 'Permit/License'
        ];

        // Handle scan upload pattern (scan_upload_N)
        if (strpos($docType, 'scan_upload_') === 0) {
            return 'Scan Upload Document';
        }

        return $mapping[$docType] ?? 'Document';
    }

    /**
     * Detect paper size from filename (basic heuristic)
     */
    private function detectPaperSize($filename)
    {
        // Basic paper size detection - can be enhanced
        $filename = strtolower($filename);
        
        if (strpos($filename, 'a4') !== false) {
            return 'A4';
        } elseif (strpos($filename, 'a3') !== false) {
            return 'A3';
        } elseif (strpos($filename, 'a2') !== false) {
            return 'A2';
        } elseif (strpos($filename, 'a1') !== false) {
            return 'A1';
        } elseif (strpos($filename, 'a0') !== false) {
            return 'A0';
        }
        
        return 'A4'; // Default
    }
}
