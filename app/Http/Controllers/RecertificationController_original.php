<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecertificationController extends Controller
{  
    public function index() {
        $PageTitle = 'Recertification Programme';
        $PageDescription = 'Manage approved certificate recertification and re-issuance applications';
        return view('recertification.index', compact('PageTitle', 'PageDescription'));
    }

    /**
     * Get applications data for DataTables
     */
    public function getApplicationsData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');

            // Search functionality
            if ($request->has('search') && !empty($request->search['value'])) {
                $searchValue = $request->search['value'];
                $query->where(function($q) use ($searchValue) {
                    $q->where('application_reference', 'like', "%{$searchValue}%")
                      ->orWhere('surname', 'like', "%{$searchValue}%")
                      ->orWhere('first_name', 'like', "%{$searchValue}%")
                      ->orWhere('organisation_name', 'like', "%{$searchValue}%")
                      ->orWhere('plot_number', 'like', "%{$searchValue}%")
                      ->orWhere('cofo_number', 'like', "%{$searchValue}%")
                      ->orWhere('file_number', 'like', "%{$searchValue}%");
                });
            }

            // Get total count before pagination
            $totalRecords = $query->count();

            // Apply ordering
            if ($request->has('order')) {
                $orderColumn = $request->order[0]['column'];
                $orderDir = $request->order[0]['dir'];
                
                $columns = ['id', 'file_number', 'applicant_type', 'applicant_name', 'plot_details', 'lga_name', 'created_at'];
                if (isset($columns[$orderColumn])) {
                    if ($orderColumn == 3) { // applicant_name
                        $query->orderBy('surname', $orderDir)->orderBy('first_name', $orderDir);
                    } else {
                        $query->orderBy($columns[$orderColumn], $orderDir);
                    }
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Apply pagination
            if ($request->has('start') && $request->has('length')) {
                $query->skip($request->start)->take($request->length);
            }

            $applications = $query->get();

            // Format data for DataTables
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check if CofO already captured (exists in legacy CofO table) using available file number
                $fileNo = $app->file_number ?? null;
                $cofoExists = false;
                if ($fileNo) {
                    try {
                        if (Schema::connection('sqlsrv')->hasTable('Cofo')) {
                            $cofoExists = DB::connection('sqlsrv')->table('Cofo')
                                ->where('fileNo', $fileNo)
                                ->orWhere('mlsFNo', $fileNo)
                                ->orWhere('kangisFileNo', $fileNo)
                                ->orWhere('NewKANGISFileno', $fileNo)
                                ->exists();
                        }
                    } catch (\Throwable $e) {
                        $cofoExists = false;
                    }
                }

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'application_reference' => $app->application_reference ?? 'N/A',
                    'file_number' => $fileNo ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'cofo_number' => $app->cofo_number ?? 'N/A',
                    'acknowledgement' => $app->acknowledgement ?? null,
                    'cofo_exists' => $cofoExists,
                ];
            });

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching applications data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'draw' => intval($request->draw ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to fetch applications data'
            ]);
        }
    }

    /**
     * View application details
     */
    public function view($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            // Get owners if Multiple Owners type
            $owners = [];
            if ($application->applicant_type === 'Multiple Owners') {
                $owners = DB::connection('sqlsrv')
                    ->table('recertification_owners')
                    ->where('application_id', $id)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'application' => $application,
                'owners' => $owners
            ]);

        } catch (\Exception $e) {
            Log::error('Error viewing application', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to load application'], 500);
        }
    }

    /**
     * Show application details page
     */
    public function details($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                abort(404, 'Application not found');
            }

            // Get owners if Multiple Owners type
            $owners = [];
            if ($application->applicant_type === 'Multiple Owners') {
                $owners = DB::connection('sqlsrv')
                    ->table('recertification_owners')
                    ->where('application_id', $id)
                    ->get();
            }

            $PageTitle = 'Application Details';
            $PageDescription = 'Complete application information';

            return view('recertification.details', compact('PageTitle', 'PageDescription', 'application', 'owners'));

        } catch (\Exception $e) {
            Log::error('Error loading application details', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return redirect()->route('recertification.index')
                ->with('error', 'Failed to load application details');
        }
    }

    /**
     * Delete application
     */
    public function destroy($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            // Delete owners first (cascade should handle this, but being explicit)
            DB::connection('sqlsrv')
                ->table('recertification_owners')
                ->where('application_id', $id)
                ->delete();

            // Delete application
            DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Application deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting application', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to delete application'], 500);
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                abort(404, 'Application not found');
            }

            // Get owners if Multiple Owners type
            $owners = [];
            if ($application->applicant_type === 'Multiple Owners') {
                $owners = DB::connection('sqlsrv')
                    ->table('recertification_owners')
                    ->where('application_id', $id)
                    ->get();
            }

            $PageTitle = 'Edit Recertification Application';
            $PageDescription = 'Update application details';

            return view('recertification.edit', compact('PageTitle', 'PageDescription', 'application', 'owners'));

        } catch (\Exception $e) {
            Log::error('Error loading edit form', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return redirect()->route('recertification.index')
                ->with('error', 'Failed to load application for editing');
        }
    }

    /**
     * Update an existing recertification application.
     */
    public function update(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            // Normalize applicant type
            $type = $request->input('applicantType');

            // Prepare payload (exclude files)
            $payload = $request->except(['owners', '_token', '_method', 'application_id']);

            // Map documents checkboxes (Step 6)
            $documents = $request->input('documents', []);

            // Prepare file uploads and merged payload
            $existingPayload = json_decode($application->payload ?? '{}', true) ?: [];
            $newPayload = array_merge($existingPayload, $payload);

            // Handle CAC document (Corporate/Government Body) - store path in payload
            if (in_array($application->applicant_type, ['Corporate', 'Government Body'], true) && $request->hasFile('cacDocument')) {
                $newPayload['cac_document_path'] = $request->file('cacDocument')->store('recertification/cac', 'public');
            }

            // Handle applicant passport photo (Individual/Government Body)
            $passportPath = $application->passport_photo_path ?? null;
            if ($request->hasFile('passportPhoto')) {
                $passportPath = $request->file('passportPhoto')->store('recertification/passports', 'public');
            }

            // Update application with all structured fields
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    // Meta
                    'application_date' => $request->input('applicationDate'),
                    'applicant_type' => $type,
                    'organisation_name' => $request->input('organisationName'),
                    'cac_registration_no' => $request->input('cacRegistrationNo'),
                    'type_of_organisation' => $request->input('typeOfOrganisation'),
                    'type_of_business' => $request->input('typeOfBusiness'),

                    // Step 1
                    'surname' => $request->input('surname'),
                    'first_name' => $request->input('firstName'),
                    'middle_name' => $request->input('middleName'),
                    'title' => $request->input('title'),
                    'occupation' => $request->input('occupation'),
                    'date_of_birth' => $request->input('dateOfBirth'),
                    'nationality' => $request->input('nationality'),
                    'state_of_origin' => $request->input('stateOfOrigin'),
                    'lga_of_origin' => $request->input('lgaOfOrigin'),
                    'nin' => $request->input('nin'),
                    'gender' => $request->input('gender'),
                    'marital_status' => $request->input('maritalStatus'),
                    'maiden_name' => $request->input('maidenName'),

                    // Step 2 - Applicant contact
                    'phone_no' => $request->input('phoneNo'),
                    'whatsapp_phone_no' => $request->input('whatsappPhoneNo'),
                    'alternate_phone_no' => $request->input('alternatePhoneNo'),
                    'address_line1' => $request->input('addressLine1'),
                    'address_line2' => $request->input('addressLine2'),
                    'city_town' => $request->input('cityTown'),
                    'state_name' => $request->input('state'),
                    'email_address' => $request->input('emailAddress'),

                    // Step 3 - Title Holder
                    'title_holder_surname' => $request->input('titleHolderSurname'),
                    'title_holder_first_name' => $request->input('titleHolderFirstName'),
                    'title_holder_middle_name' => $request->input('titleHolderMiddleName'),
                    'title_holder_title' => $request->input('titleHolderTitle'),
                    'cofo_number' => $request->input('cofoNumber'),
                    'reg_no' => $request->input('registrationNo'),
                    'reg_volume' => $request->input('registrationVolume'),
                    'reg_page' => $request->input('registrationPage'),
                    'reg_number' => $request->input('registrationNumber'),
                    'is_original_owner' => $request->input('isOriginalOwner') === 'yes' ? 1 : ($request->has('isOriginalOwner') ? 0 : null),
                    'instrument_type' => $request->input('instrumentType'),
                    'acquired_title_holder_name' => $request->input('titleHolderName'),
                    'commencement_date' => $request->input('commencementDate'),
                    'grant_term' => $request->input('grantTerm'),

                    // Step 4 - Mortgage & Encumbrance
                    'is_encumbered' => $request->input('isEncumbered') === 'yes' ? 1 : ($request->has('isEncumbered') ? 0 : null),
                    'encumbrance_reason' => $request->input('encumbranceReason'),
                    'has_mortgage' => $request->input('hasMortgage') === 'yes' ? 1 : ($request->has('hasMortgage') ? 0 : null),
                    'mortgagee_name' => $request->input('mortgageeName'),
                    'mortgage_registration_no' => $request->input('mortgageRegistrationNo'),
                    'mortgage_volume' => $request->input('mortgageVolume'),
                    'mortgage_page' => $request->input('mortgagePage'),
                    'mortgage_number' => $request->input('mortgageNumber'),
                    'mortgage_released' => $request->input('mortgageReleased') === 'yes' ? 1 : ($request->has('mortgageReleased') ? 0 : null),

                    // Step 5 - Plot Details
                    'plot_number' => $request->input('plotNumber'),
                    'file_number' => $request->input('fileNumber'),
                    'plot_size' => $request->input('plotSize'),
                    'layout_district' => $request->input('layoutDistrict'),
                    'lga_name' => $request->input('lga'),
                    'current_land_use' => $request->input('currentLandUse'),
                    'plot_status' => $request->input('plotStatus'),
                    'mode_of_allocation' => $request->input('modeOfAllocation'),
                    'start_date' => $request->input('startDate'),
                    'expiry_date' => $request->input('expiryDate'),
                    'plot_description' => $request->input('plotDescription'),

                    // Step 6 - Payment & Terms
                    'application_type' => $request->input('applicationType'),
                    'application_reason' => $request->input('applicationReason'),
                    'other_reason' => $request->input('otherReason'),
                    'payment_method' => $request->input('paymentMethod'),
                    'receipt_no' => $request->input('receiptNo'),
                    'bank_name' => $request->input('bankName'),
                    'payment_amount' => $request->input('paymentAmount'),
                    'payment_date' => $request->input('paymentDate'),
                    'documents_json' => json_encode($documents),
                    'agree_terms' => $request->boolean('agreeTerms'),
                    'confirm_accuracy' => $request->boolean('confirmAccuracy'),

                    // Raw payload
                    'payload' => json_encode($newPayload),
                    'passport_photo_path' => $passportPath,

                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'id' => $id,
                'reference' => $application->application_reference,
                'message' => 'Application updated successfully.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Recertification update error', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update application',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store a newly created recertification application.
     * Persists to SQL Server connection (sqlsrv).
     */
    public function store(Request $request)
    {
        try {
            // Normalize applicant type
            $type = $request->input('applicantType');

            // Base validation
            $rules = [
                'applicationDate' => 'nullable|date',
                'applicantType' => 'required|in:Individual,Corporate,Government Body,Multiple Owners',
            ];

            if ($type === 'Corporate') {
                $rules = array_merge($rules, [
                    'organisationName' => 'required|string|max:255',
                    'cacRegistrationNo' => 'required|string|max:100',
                    'typeOfOrganisation' => 'required|string|max:255',
                    'typeOfBusiness' => 'required|string|max:255',
                ]);
            } elseif ($type === 'Multiple Owners') {
                $rules = array_merge($rules, [
                    'owners' => 'required|array|min:1',
                    'owners.*.surname' => 'required|string|max:255',
                    'owners.*.firstName' => 'required|string|max:255',
                    'owners.*.occupation' => 'required|string|max:255',
                    'owners.*.dateOfBirth' => 'required|date',
                    'owners.*.nationality' => 'required|string|max:255',
                    'owners.*.stateOfOrigin' => 'required|string|max:255',
                    'owners.*.gender' => 'required|string|in:male,female',
                    'owners.*.maritalStatus' => 'required|string|in:single,married,divorced,widowed',
                    'owners.*.passportPhoto' => 'nullable|file|image|max:2048',
                ]);
            } else { // Individual or Government Body
                $rules = array_merge($rules, [
                    'surname' => 'required|string|max:255',
                    'firstName' => 'required|string|max:255',
                    'occupation' => 'required|string|max:255',
                    'dateOfBirth' => 'required|date',
                    'nationality' => 'required|string|max:255',
                    'stateOfOrigin' => 'required|string|max:255',
                    'gender' => 'required|string|in:male,female',
                    'maritalStatus' => 'required|string|in:single,married,divorced,widowed',
                ]);
            }

            $validated = $request->validate($rules);

            // Generate application reference
            $reference = 'RC-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            // Prepare payload (exclude files)
            $payload = $request->except(['owners', '_token']);

            // Include owners meta (without files) if present
            if ($type === 'Multiple Owners') {
                $ownersMeta = [];
                foreach ((array)$request->input('owners', []) as $idx => $owner) {
                    $ownerCopy = $owner;
                    unset($ownerCopy['passportPhoto']);
                    $ownersMeta[$idx] = $ownerCopy;
                }
                $payload['owners'] = $ownersMeta;
            }

            // Map documents checkboxes (Step 6)
            $documents = $request->input('documents', []);

            // Handle applicant passport photo (Individual/Government Body)
            $passportPath = null;
            if ($request->hasFile('passportPhoto')) {
                $passportPath = $request->file('passportPhoto')->store('recertification/passports', 'public');
            }

            // Handle CAC document (Corporate/Government Body) - store path in payload
            if (in_array($type, ['Corporate', 'Government Body'], true) && $request->hasFile('cacDocument')) {
                $payload['cac_document_path'] = $request->file('cacDocument')->store('recertification/cac', 'public');
            }

            // Insert application with all structured fields
            $appId = DB::connection('sqlsrv')->table('recertification_applications')->insertGetId([
                // Meta
                'application_reference' => $reference,
                'application_date' => $request->input('applicationDate'),
                'applicant_type' => $type,
                'organisation_name' => $request->input('organisationName'),
                'cac_registration_no' => $request->input('cacRegistrationNo'),
                'type_of_organisation' => $request->input('typeOfOrganisation'),
                'type_of_business' => $request->input('typeOfBusiness'),

                // Step 1
                'surname' => $request->input('surname'),
                'first_name' => $request->input('firstName'),
                'middle_name' => $request->input('middleName'),
                'title' => $request->input('title'),
                'occupation' => $request->input('occupation'),
                'date_of_birth' => $request->input('dateOfBirth'),
                'nationality' => $request->input('nationality'),
                'state_of_origin' => $request->input('stateOfOrigin'),
                'lga_of_origin' => $request->input('lgaOfOrigin'),
                'nin' => $request->input('nin'),
                'gender' => $request->input('gender'),
                'marital_status' => $request->input('maritalStatus'),
                'maiden_name' => $request->input('maidenName'),

                // Step 2 - Applicant contact
                'phone_no' => $request->input('phoneNo'),
                'whatsapp_phone_no' => $request->input('whatsappPhoneNo'),
                'alternate_phone_no' => $request->input('alternatePhoneNo'),
                'address_line1' => $request->input('addressLine1'),
                'address_line2' => $request->input('addressLine2'),
                'city_town' => $request->input('cityTown'),
                'state_name' => $request->input('state'),
                'email_address' => $request->input('emailAddress'),

                // Step 2 - Representative
                'rep_surname' => $request->input('repSurname'),
                'rep_first_name' => $request->input('repFirstName'),
                'rep_middle_name' => $request->input('repMiddleName'),
                'rep_title' => $request->input('repTitle'),
                'rep_relationship' => $request->input('repRelationship'),
                'rep_phone_no' => $request->input('repPhoneNo'),

                // Step 3 - Title Holder
                'title_holder_surname' => $request->input('titleHolderSurname'),
                'title_holder_first_name' => $request->input('titleHolderFirstName'),
                'title_holder_middle_name' => $request->input('titleHolderMiddleName'),
                'title_holder_title' => $request->input('titleHolderTitle'),
                'cofo_number' => $request->input('cofoNumber'),
                'reg_no' => $request->input('registrationNo'),
                'reg_volume' => $request->input('registrationVolume'),
                'reg_page' => $request->input('registrationPage'),
                'reg_number' => $request->input('registrationNumber'),
                'is_original_owner' => $request->input('isOriginalOwner') === 'yes' ? 1 : ($request->has('isOriginalOwner') ? 0 : null),
                'instrument_type' => $request->input('instrumentType'),
                'acquired_title_holder_name' => $request->input('titleHolderName'),
                'commencement_date' => $request->input('commencementDate'),
                'grant_term' => $request->input('grantTerm'),

                // Step 4 - Mortgage & Encumbrance
                'is_encumbered' => $request->input('isEncumbered') === 'yes' ? 1 : ($request->has('isEncumbered') ? 0 : null),
                'encumbrance_reason' => $request->input('encumbranceReason'),
                'has_mortgage' => $request->input('hasMortgage') === 'yes' ? 1 : ($request->has('hasMortgage') ? 0 : null),
                'mortgagee_name' => $request->input('mortgageeName'),
                'mortgage_registration_no' => $request->input('mortgageRegistrationNo'),
                'mortgage_volume' => $request->input('mortgageVolume'),
                'mortgage_page' => $request->input('mortgagePage'),
                'mortgage_number' => $request->input('mortgageNumber'),
                'mortgage_released' => $request->input('mortgageReleased') === 'yes' ? 1 : ($request->has('mortgageReleased') ? 0 : null),

                // Step 5 - Plot Details
                'plot_number' => $request->input('plotNumber'),
                'file_number' => $request->input('fileNumber'),
                'plot_size' => $request->input('plotSize'),
                'layout_district' => $request->input('layoutDistrict'),
                'lga_name' => $request->input('lga'),
                'current_land_use' => $request->input('currentLandUse'),
                'plot_status' => $request->input('plotStatus'),
                'mode_of_allocation' => $request->input('modeOfAllocation'),
                'start_date' => $request->input('startDate'),
                'expiry_date' => $request->input('expiryDate'),
                'plot_description' => $request->input('plotDescription'),

                // Step 6 - Payment & Terms
                'application_type' => $request->input('applicationType'),
                'application_reason' => $request->input('applicationReason'),
                'other_reason' => $request->input('otherReason'),
                'payment_method' => $request->input('paymentMethod'),
                'receipt_no' => $request->input('receiptNo'),
                'bank_name' => $request->input('bankName'),
                'payment_amount' => $request->input('paymentAmount'),
                'payment_date' => $request->input('paymentDate'),
                'documents_json' => json_encode($documents),
                'agree_terms' => $request->boolean('agreeTerms'),
                'confirm_accuracy' => $request->boolean('confirmAccuracy'),
                'passport_photo_path' => $passportPath,

                // Raw payload
                'payload' => json_encode($payload),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Handle owners if Multiple Owners
            if ($type === 'Multiple Owners') {
                foreach ((array)$request->input('owners', []) as $idx => $owner) {
                    $photoPath = null;
                    if ($request->hasFile("owners.$idx.passportPhoto")) {
                        $photoPath = $request->file("owners.$idx.passportPhoto")->store('recertification/passports', 'public');
                    }

                    DB::connection('sqlsrv')->table('recertification_owners')->insert([
                        'application_id' => $appId,
                        'surname' => $owner['surname'] ?? null,
                        'first_name' => $owner['firstName'] ?? null,
                        'middle_name' => $owner['middleName'] ?? null,
                        'title' => $owner['title'] ?? null,
                        'occupation' => $owner['occupation'] ?? null,
                        'date_of_birth' => $owner['dateOfBirth'] ?? null,
                        'nationality' => $owner['nationality'] ?? null,
                        'state_of_origin' => $owner['stateOfOrigin'] ?? null,
                        'lga_of_origin' => $owner['lgaOfOrigin'] ?? null,
                        'nin' => $owner['nin'] ?? null,
                        'gender' => $owner['gender'] ?? null,
                        'marital_status' => $owner['maritalStatus'] ?? null,
                        'maiden_name' => $owner['maidenName'] ?? null,
                        'passport_photo_path' => $photoPath,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'id' => $appId,
                'reference' => $reference,
                'message' => 'Recertification application stored successfully.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Recertification store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store application',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get the next file number for new applications
     */
    public function getNextFileNumber()
    {
        try {
            $lastFileNumber = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('file_number', 'like', 'KN%')
                ->orderBy('file_number', 'desc')
                ->value('file_number');
            
            if ($lastFileNumber) {
                // Extract the numeric part and increment
                $lastNumber = intval(substr($lastFileNumber, 2));
                $newNumber = $lastNumber + 1;
            } else {
                // Start from KN3000 if no previous records
                $newNumber = 3000;
            }
            
            $nextFileNumber = 'KN' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
            
            return response()->json([
                'success' => true,
                'file_number' => $nextFileNumber
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting next file number', [
                'message' => $e->getMessage()
            ]);
            
            // Return a safer default that avoids reusing KN3000 on failure
            return response()->json([
                'success' => true,
                'file_number' => 'KN3001'
            ]);
        }
    }

    /**
     * Show migrate data page
     */
    public function migrate()
    {
        $PageTitle = 'Migrate Data';
        $PageDescription = 'Import recertification applications from CSV file';
        return view('recertification.migrate', compact('PageTitle', 'PageDescription'));
    }

    /**
     * Download CSV template for migration
     */
    /**
     * Download CSV template for migration
     */
    public function downloadTemplate()
    {
        $filename = 'recertification_template.csv';
        
        $columns = [
            'application_date', 'applicant_type', 'file_number', 'surname', 'first_name', 'middle_name', 'title', 'occupation',
            'date_of_birth', 'nationality', 'state_of_origin', 'lga_of_origin', 'nin', 'gender', 'marital_status', 'maiden_name',
            'organisation_name', 'cac_registration_no', 'type_of_organisation', 'type_of_business', 'phone_no', 'whatsapp_phone_no',
            'alternate_phone_no', 'address_line1', 'address_line2', 'city_town', 'state_name', 'email_address', 'rep_surname',
            'rep_first_name', 'rep_middle_name', 'rep_title', 'rep_relationship', 'rep_phone_no', 'title_holder_surname',
            'title_holder_first_name', 'title_holder_middle_name', 'title_holder_title', 'cofo_number', 'reg_no', 'reg_volume',
            'reg_page', 'reg_number', 'is_original_owner', 'instrument_type', 'acquired_title_holder_name', 'commencement_date',
            'grant_term', 'is_encumbered', 'encumbrance_reason', 'has_mortgage', 'mortgagee_name', 'mortgage_registration_no',
            'mortgage_volume', 'mortgage_page', 'mortgage_number', 'mortgage_released', 'plot_number', 'plot_size',
            'layout_district', 'lga_name', 'current_land_use', 'plot_status', 'mode_of_allocation', 'start_date', 'expiry_date',
            'plot_description', 'application_type', 'application_reason', 'other_reason', 'payment_method', 'receipt_no',
            'bank_name', 'payment_amount', 'payment_date', 'agree_terms', 'confirm_accuracy',
            'application_status', 'recertification_date'
        ];

        $sampleData = [
            '2024-01-15', 'Individual', 'KN3001', 'Doe', 'John', 'Michael', 'Mr', 'Engineer', '1980-05-15', 'Nigerian',
            'Lagos', 'Lagos Island', '12345678901', 'male', 'married', '', '', '', '', '', '08012345678', '08012345678',
            '08087654321', '123 Main Street', 'Victoria Island', 'Lagos', 'Lagos', 'john.doe@email.com', '', '', '', '',
            '', '', 'Smith', 'Jane', 'Mary', 'Mrs', 'LAG/2023/001', 'REG001', 'Vol1', '25', 'RN001', 'yes', '', '',
            '2020-01-01', '99', 'no', '', 'no', '', '', '', '', '', '', 'Plot 123', '500', 'Victoria Island Layout',
            'Lagos Island', 'residential', 'allocated', 'direct-allocation', '2020-01-01', '2119-12-31',
            'Prime residential plot', 'Recertification', 'Certificate Renewal', '', 'bank-transfer', 'RCT001',
            'First Bank', '50000', '2024-01-10', '1', '1',
            'RECERTIFIED', '2024-01-15'
        ];

        return response()->stream(function () use ($columns, $sampleData) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add header row
            fputcsv($handle, $columns);
            
            // Add sample data row
            fputcsv($handle, $sampleData);
            
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Upload and process migration CSV file
     */
    public function uploadMigration(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            ]);

            $file = $request->file('csv_file');
            $csv = array_map('str_getcsv', file($file->getRealPath()));
            $header = array_shift($csv);
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            DB::connection('sqlsrv')->beginTransaction();
            
            foreach ($csv as $index => $row) {
                try {
                    if (empty(array_filter($row))) continue;
                    
                    $data = array_combine($header, $row);
                    $data['application_reference'] = $data['application_reference'] ?? 'RC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                    $data['is_original_owner'] = strtolower($data['is_original_owner'] ?? '') === 'yes' ? 1 : 0;
                    $data['is_encumbered'] = strtolower($data['is_encumbered'] ?? '') === 'yes' ? 1 : 0;
                    $data['has_mortgage'] = strtolower($data['has_mortgage'] ?? '') === 'yes' ? 1 : 0;
                    $data['mortgage_released'] = strtolower($data['mortgage_released'] ?? '') === 'yes' ? 1 : 0;
                    $data['agree_terms'] = ($data['agree_terms'] ?? '') == '1' ? 1 : 0;
                    $data['confirm_accuracy'] = ($data['confirm_accuracy'] ?? '') == '1' ? 1 : 0;
                    $data['created_at'] = now();
                    $data['updated_at'] = now();
                    
                    DB::connection('sqlsrv')->table('recertification_applications')->insert($data);
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    if (count($errors) > 50) break;
                }
            }
            
            if ($errorCount > 0 && $successCount == 0) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'Import failed.', 'errors' => $errors], 400);
            }
            
            DB::connection('sqlsrv')->commit();
            
            return response()->json([
                'success' => true,
                'message' => "Import completed. {$successCount} records imported successfully.",
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show verification sheet page
     */
    public function verificationSheet()
    {
        $PageTitle = 'Verification Sheet';
        $PageDescription = 'Review and verify recertification applications';
        return view('recertification.verification_sheet', compact('PageTitle', 'PageDescription'));
    }

    public function verificationView($id)
    {
        // Try to get application for the printable verification view
        $application = DB::connection('sqlsrv')
            ->table('recertification_applications')
            ->where('id', $id)
            ->first();
        if (!$application) {
            abort(404, 'Application not found');
        }
        // If a dedicated verification template exists, use it; otherwise render a simple fallback
        if (view()->exists('recertification.verification')) {
            return view('recertification.verification', compact('application'));
        }
        // Fallback minimal printable verification sheet
        return response()->view('recertification.verification_fallback', compact('application'));
    }

    /**
     * Get verification sheet data for DataTables
     */
    public function getVerificationData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');

            // Search functionality
            if ($request->has('search') && !empty($request->search['value'])) {
                $searchValue = $request->search['value'];
                $query->where(function($q) use ($searchValue) {
                    $q->where('file_number', 'like', "%{$searchValue}%")
                      ->orWhere('surname', 'like', "%{$searchValue}%")
                      ->orWhere('first_name', 'like', "%{$searchValue}%")
                      ->orWhere('organisation_name', 'like', "%{$searchValue}%")
                      ->orWhere('plot_number', 'like', "%{$searchValue}%")
                      ->orWhere('lga_name', 'like', "%{$searchValue}%")
                      ->orWhere('application_type', 'like', "%{$searchValue}%");
                });
            }

            // Get total count before pagination
            $totalRecords = $query->count();

            // Apply ordering
            if ($request->has('order')) {
                $orderColumn = $request->order[0]['column'];
                $orderDir = $request->order[0]['dir'];
                
                $columns = ['file_number', 'application_type', 'applicant_name', 'plot_details', 'lga_name', 'application_date'];
                if (isset($columns[$orderColumn])) {
                    if ($orderColumn == 2) { // applicant_name
                        $query->orderBy('surname', $orderDir)->orderBy('first_name', $orderDir);
                    } else {
                        $query->orderBy($columns[$orderColumn], $orderDir);
                    }
                }
            } else {
                $query->orderBy('application_date', 'desc');
            }

            // Apply pagination
            if ($request->has('start') && $request->has('length')) {
                $query->skip($request->start)->take($request->length);
            }

            $applications = $query->get();

            // Format data for DataTables
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'application_reference' => $app->application_reference ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'verification' => $app->verification ?? null,
                ];
            });

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching verification data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'draw' => intval($request->draw ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to fetch verification data'
            ]);
        }
    }

    /**
     * Show GIS Data Capture form for a specific recertification application
     */
    public function gisCapture($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                abort(404, 'Application not found');
            }

            $PageTitle = 'GIS Data Capture - Recertification';
            $PageDescription = 'Capture GIS data for recertification application ' . ($application->file_number ?? 'N/A');

            return view('recertification.gis_capture_form', compact('PageTitle', 'PageDescription', 'application'));

        } catch (\Exception $e) {
            Log::error('Error loading GIS capture form', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return redirect()->route('recertification.details', $id)
                ->with('error', 'Failed to load GIS capture form');
        }
    }

    /**
     * Store GIS data for a recertification application
     */
    public function storeGisCapture(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            // Validate the request data
            $validated = $request->validate([
                'gis_type' => 'nullable|string',
                'mlsfNo' => 'nullable|string',
                'kangisFileNo' => 'nullable|string',
                'NewKANGISFileno' => 'nullable|string',
                'fileno' => 'nullable|string',
                'plotNo' => 'nullable|string',
                'blockNo' => 'nullable|string',
                'approvedPlanNo' => 'nullable|string',
                'tpPlanNo' => 'nullable|string',
                'surveyedBy' => 'nullable|string',
                'drawnBy' => 'nullable|string',
                'checkedBy' => 'nullable|string',
                'passedBy' => 'nullable|string',
                'beaconControlName' => 'nullable|string',
                'beaconControlX' => 'nullable|string',
                'beaconControlY' => 'nullable|string',
                'metricSheetIndex' => 'nullable|string',
                'metricSheetNo' => 'nullable|string',
                'imperialSheet' => 'nullable|string',
                'imperialSheetNo' => 'nullable|string',
                'layoutName' => 'nullable|string',
                'districtName' => 'nullable|string',
                'lgaName' => 'nullable|string',
                'StateName' => 'nullable|string',
                'oldTitleSerialNo' => 'nullable|string',
                'oldTitlePageNo' => 'nullable|string',
                'oldTitleVolumeNo' => 'nullable|string',
                'deedsDate' => 'nullable|date',
                'deedsTime' => 'nullable',
                'certificateDate' => 'nullable|date',
                'originalAllottee' => 'nullable|string',
                'addressOfOriginalAllottee' => 'nullable|string',
                'titleIssuedYear' => 'nullable|integer',
                'changeOfOwnership' => 'nullable|string',
                'reasonForChange' => 'nullable|string',
                'currentAllottee' => 'nullable|string',
                'addressOfCurrentAllottee' => 'nullable|string',
                'titleOfCurrentAllottee' => 'nullable|string',
                'phoneNo' => 'nullable|string',
                'emailAddress' => 'nullable|email',
                'occupation' => 'nullable|string',
                'nationality' => 'nullable|string',
                'specifically' => 'nullable|string',
                'streetName' => 'nullable|string',
                'houseNo' => 'nullable|string',
                'houseType' => 'nullable|string',
                'tenancy' => 'nullable|string',
                'areaInHectares' => 'nullable|numeric',
                'SurveyorGeneralSignatureDate' => 'nullable|date',
                'CofOSerialNo' => 'nullable|string',
                'CompanyRCNo' => 'nullable|string',
                'transactionDocument' => 'nullable|file',
                'passportPhoto' => 'nullable|file',
                'nationalId' => 'nullable|file',
                'internationalPassport' => 'nullable|file',
                'businessRegCert' => 'nullable|file',
                'formCO7AndCO4' => 'nullable|file',
                'certOfIncorporation' => 'nullable|file',
                'memorandumAndArticle' => 'nullable|file',
                'letterOfAdmin' => 'nullable|file',
                'courtAffidavit' => 'nullable|file',
                'policeReport' => 'nullable|file',
                'newspaperAdvert' => 'nullable|file',
                'picture' => 'nullable|file',
                'SurveyPlan' => 'nullable|file',
                'recertification_application_id' => 'nullable|integer',
            ]);

            // Prepare data for GIS capture
            $data = $validated;
            
            // Set specific values for recertification
            $data['gis_type'] = 'recertification';
            $data['mlsfNo'] = $application->file_number;
            $data['recertification_application_id'] = $id;

            // Handle file uploads
            $fileFields = [
                'transactionDocument', 'passportPhoto', 'nationalId', 'internationalPassport',
                'businessRegCert', 'formCO7AndCO4', 'certOfIncorporation', 'memorandumAndArticle',
                'letterOfAdmin', 'courtAffidavit', 'policeReport', 'newspaperAdvert', 'picture', 'SurveyPlan'
            ];

            foreach ($fileFields as $field) {
                if ($request->hasFile($field) && $request->file($field)->isValid()) {
                    $path = $request->file($field)->store('gis_documents', 'public');
                    $data[$field] = $path;
                } else {
                    unset($data[$field]);
                }
            }

            // Add metadata
            $data['created_by'] = auth()->id();
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Store in GIS database
            $gisId = DB::connection('sqlsrv')->table('gisCapture')->insertGetId($data);

            // Update recertification application status
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    'gis_status' => 'captured',
                    'gis_captured_date' => now(),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'GIS data captured successfully!',
                'gis_id' => $gisId
            ]);

        } catch (\Exception $e) {
            Log::error('GIS Data Capture Error for Recertification', [
                'application_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to capture GIS data. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Acknowledgement for a recertification application
     */
    public function generateAcknowledgement($id)
    {
        // Open the modal first on the frontend, so backend here can be a no-op or can return a hint
        return response()->json(['success' => true, 'message' => 'Open modal', 'open_modal' => true]);
    }

    public function submitAcknowledgementDocs(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['success' => false, 'message' => 'Application not found'], 404);
            }

            // Extract flags from request
            $docs = [
                'doc_ro' => $request->boolean('doc_ro'),
                'doc_cofo' => $request->boolean('doc_cofo'),
                'doc_deed_assignment' => $request->boolean('doc_deed_assignment'),
                'doc_deed_sublease' => $request->boolean('doc_deed_sublease'),
                'doc_deed_mortgage' => $request->boolean('doc_deed_mortgage'),
                'doc_deed_gift' => $request->boolean('doc_deed_gift'),
                'doc_poa' => $request->boolean('doc_poa'),
                'doc_devolution' => $request->boolean('doc_devolution'),
                'doc_letter_admin' => $request->boolean('doc_letter_admin'),
                'doc_other' => $request->boolean('doc_other'),
                'doc_other_text' => $request->input('doc_other_text'),
            ];

            // Persist into recertification_applications
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    'acknowledgement' => 'Generated',
                    'ack_docs_json' => json_encode($docs),
                    'ack_docs_date' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Acknowledgement saved',
                'ack1_url' => route('recertification.acknowledgement.view', ['id' => $id]),
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting acknowledgement docs', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save acknowledgement docs'
            ], 500);
        }
    }

    /**
     * View Acknowledgement
     */
    public function viewAcknowledgement($id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                abort(404, 'Application not found');
            }

            // Render a combined view that includes acknowledgement_1 and acknowledgement pages
            return view('recertification.acknowledgement_full', compact('application'));
        } catch (\Exception $e) {
            Log::error('Error viewing acknowledgement', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);
            abort(500, 'Failed to load acknowledgement');
        }
    }

    /**
     * Update verification status for an application
     */
    public function verify(Request $request, $id)
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }

            $verification = $request->input('verification', 'Verified');

            // Update verification status
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    'verification' => $verification,
                    'verification_date' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification status updated successfully',
                'verification' => $verification
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating verification status', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to update verification status'], 500);
        }
    }

    /**
     * Get available serial numbers for CofO assignment
     */
    public function getAvailableSerialNumbers()
    {
        try {
            // Generate serial numbers from 000001 to 999999
            $totalNumbers = 999999;
            $startNumber = 1;
            
            // Get already used serial numbers from recertification_applications
            $usedNumbers = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->whereNotNull('cofo_number')
                ->pluck('cofo_number')
                ->toArray();
            
            // Also check the main Cofo table if it exists
            try {
                if (Schema::connection('sqlsrv')->hasTable('Cofo')) {
                    $usedFromCofo = DB::connection('sqlsrv')
                        ->table('Cofo')
                        ->whereNotNull('cofO_serialNo')
                        ->pluck('cofO_serialNo')
                        ->toArray();
                    
                    $usedNumbers = array_merge($usedNumbers, $usedFromCofo);
                }
            } catch (\Exception $e) {
                // If Cofo table doesn't exist or can't be accessed, continue with just recertification numbers
                Log::warning('Could not access Cofo table for serial number check', ['error' => $e->getMessage()]);
            }
            
            // Remove duplicates and convert to set for faster lookup
            $usedNumbers = array_unique($usedNumbers);
            $usedSet = array_flip($usedNumbers);
            
            // Generate available serial numbers (limit to first 1000 available for performance)
            $availableNumbers = [];
            $count = 0;
            $maxResults = 1000;
            
            for ($i = $startNumber; $i <= $totalNumbers && $count < $maxResults; $i++) {
                $serialNumber = str_pad($i, 6, '0', STR_PAD_LEFT);
                
                if (!isset($usedSet[$serialNumber])) {
                    $availableNumbers[] = $serialNumber;
                    $count++;
                }
            }
            
            return response()->json([
                'success' => true,
                'serialNumbers' => $availableNumbers,
                'total_available' => count($availableNumbers),
                'total_used' => count($usedNumbers)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting available serial numbers', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load available serial numbers',
                'serialNumbers' => []
            ], 500);
        }
    }

    /**
     * Assign a serial number to a recertification application
     */
    public function assignSerialNumber(Request $request)
    {
        try {
            $request->validate([
                'application_id' => 'required|integer|exists:sqlsrv.recertification_applications,id',
                'serial_number' => 'required|string|regex:/^\d{6}$/'
            ]);
            
            $applicationId = $request->input('application_id');
            $serialNumber = $request->input('serial_number');
            
            // Check if application exists and doesn't already have a serial number
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $applicationId)
                ->first();
                
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }
            
            if ($application->cofo_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'This application already has a serial number assigned: ' . $application->cofo_number
                ], 400);
            }
            
            // Check if serial number is already in use
            $existingUse = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('cofo_number', $serialNumber)
                ->where('id', '!=', $applicationId)
                ->exists();
                
            if ($existingUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'This serial number is already in use by another application'
                ], 400);
            }
            
            // Also check the main Cofo table if it exists
            try {
                if (Schema::connection('sqlsrv')->hasTable('Cofo')) {
                    $existingInCofo = DB::connection('sqlsrv')
                        ->table('Cofo')
                        ->where('cofO_serialNo', $serialNumber)
                        ->exists();
                        
                    if ($existingInCofo) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This serial number is already in use in the main CofO system'
                        ], 400);
                    }
                }
            } catch (\Exception $e) {
                // If we can't check the Cofo table, log warning but continue
                Log::warning('Could not check Cofo table for duplicate serial number', [
                    'serial_number' => $serialNumber,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Assign the serial number
            DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $applicationId)
                ->update([
                    'cofo_number' => $serialNumber,
                    'cofo_assigned_date' => now(),
                    'cofo_assigned_by' => auth()->id(),
                    'updated_at' => now()
                ]);
            
            Log::info('Serial number assigned successfully', [
                'application_id' => $applicationId,
                'serial_number' => $serialNumber,
                'assigned_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Serial number assigned successfully',
                'serial_number' => $serialNumber,
                'application_id' => $applicationId
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error assigning serial number', [
                'application_id' => $request->input('application_id'),
                'serial_number' => $request->input('serial_number'),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign serial number. Please try again.'
            ], 500);
        }
    }
}
