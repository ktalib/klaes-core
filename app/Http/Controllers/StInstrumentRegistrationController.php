<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StInstrumentRegistrationController extends Controller
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

    private function generateSTMReference()
    {
        $year = date('Y');
        $latestRef = DB::connection('sqlsrv')->table('registered_instruments')
            ->where('STM_Ref', 'like', "STM-$year-%")
            ->orderBy('id', 'desc')
            ->value('STM_Ref');

        if ($latestRef) {
            $matches = [];
            if (preg_match('/STM-\\d{4}-(\\d{4})/', $latestRef, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }
        } else {
            $sequence = 1;
        }

        return "STM-{$year}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function StInstrumentRegistration()
    {
        $PageTitle = 'ST Deeds Registration ';
        $PageDescription = '';

        try {
            // Initialize default completion status for subapplications that don't have it set
            $this->initializeDefaultCompletionStatus();

            // Automatically insert ST Fragmentation details for approved mother applications
            $this->autoRegisterSTFragmentation();

            // Automatically register Express ST Assignments for units owned by the same owner
            $this->autoRegisterExpressSTAssignments();

            // Get approved subapplications and create both ST Assignment and Sectional Titling records for each
            $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('users', 's.created_by', '=', 'users.id')
                ->where('s.planning_recommendation_status', 'Approved')
                ->where('s.application_status', 'Approved')
                ->select(
                    's.id',
                    's.fileno',
                    's.deeds_completion_status',
                    's.main_application_id',
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                        WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                        ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''))
                    END, '  ', ' ')) as sub_applicant"),
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                        WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                        ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                    END, '  ', ' ')) as mother_applicant"),
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.np_fileno',
                    'm.property_house_no',
                    'm.property_street_name',
                    's.created_by as reg_created_by',
                    's.created_at',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            // Get approved mother applications for ST Fragmentation records
            $approvedMotherApplications = DB::connection('sqlsrv')->table('mother_applications as m')
                ->leftJoin('users', 'm.created_by', '=', 'users.id')
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved')
                ->select(
                    'm.id',
                    'm.fileno',
                    'm.np_fileno',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.rc_number',
                    'm.multiple_owners_names',
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                        WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                        ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                    END, '  ', ' ')) as mother_applicant"),
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.created_by as reg_created_by',
                    'm.created_at',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            // Create collection for all instruments
            $allInstruments = collect();

            // For each approved subapplication, create both ST Assignment and Sectional Titling records
            foreach ($approvedSubapplications as $subApp) {
                // Determine if this is an "Express" registration (same owner)
                // Two proofs: name match OR ST Fragmentation already registered for this file
                $hasFragRegistered = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $subApp->fileno)
                    ->where('instrument_type', 'ST Fragmentation')
                    ->where('status', 'registered')
                    ->exists();
                $isExpressAssignment = $this->isSameOwner($subApp->mother_applicant, $subApp->sub_applicant)
                    || $hasFragRegistered;

                // Get registration details from registered_instruments table
                $stRegistration = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $subApp->fileno)
                    ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                    ->where('status', 'registered')
                    ->first();

                $sectionalRegistration = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $subApp->fileno)
                    ->where('instrument_type', 'Sectional Titling CofO')
                    ->where('status', 'registered')
                    ->first();

                // Build property description from mother application details
                $propertyDescription = '';
                $propertyParts = [];

                if (!empty($subApp->property_house_no)) {
                    $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                }
                if (!empty($subApp->plotNumber)) {
                    $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                }
                if (!empty($subApp->property_street_name)) {
                    $propertyParts[] = $subApp->property_street_name;
                }
                if (!empty($subApp->district)) {
                    $propertyParts[] = $subApp->district;
                }
                if (!empty($subApp->lga)) {
                    $propertyParts[] = $subApp->lga;
                }

                $propertyDescription = implode(', ', $propertyParts);
                if (empty($propertyDescription)) {
                    $propertyDescription = 'Property details not available';
                }

                // Create ST Assignment (Transfer of Title) record - ONLY if not express
                if (!$isExpressAssignment) {
                    $stAssignmentRecord = (object) [
                        'id' => $subApp->id . '_st_assignment',
                        'fileno' => $subApp->fileno, // fileno from subapplications table
                        'parent_fileNo' => $subApp->np_fileno, // np_fileno from mother_applications table
                        'Deeds_Serial_No' => $stRegistration->particularsRegistrationNumber ?? null,
                        'instrument_type' => 'ST Assignment (Transfer of Title)',
                        'Grantor' => $subApp->mother_applicant, // Grantor should be from mother application applicant details
                        'Grantee' => $subApp->sub_applicant,
                        'GrantorAddress' => '',
                        'GranteeAddress' => '',
                        'duration' => '',
                        'leasePeriod' => '',
                        'propertyDescription' => $propertyDescription,
                        'lga' => $subApp->lga,
                        'district' => $subApp->district,
                        'size' => $subApp->size,
                        'plotNumber' => $subApp->plotNumber,
                        'deeds_date' => $stRegistration->instrumentDate ?? null,
                        'solicitorName' => '',
                        'solicitorAddress' => '',
                        'status' => $stRegistration ? 'registered' : 'pending',
                        'land_use' => '',
                        'reg_created_by' => $subApp->reg_created_by,
                        'created_at' => $subApp->created_at,
                        'reg_creator_name' => $subApp->reg_creator_name,
                        'instrument_category' => 'ST Assignment',
                        'STM_Ref' => $stRegistration->STM_Ref ?? null,
                        'original_subapp_id' => $subApp->id
                    ];
                    $allInstruments->push($stAssignmentRecord);
                }

                // Create Sectional Titling CofO record
                $sectionalRecord = (object) [
                    'id' => $subApp->id . '_sectional_cofo',
                    'fileno' => $subApp->fileno, // fileno from subapplications table
                    'parent_fileNo' => $subApp->np_fileno, // np_fileno from mother_applications table
                    'Deeds_Serial_No' => $sectionalRegistration->particularsRegistrationNumber ?? null,
                    'instrument_type' => 'Sectional Titling CofO',
                    'Grantor' => 'Kano State Government', // Always Kano State Government for Sectional Titling CofO
                    'Grantee' => $subApp->sub_applicant,
                    'GrantorAddress' => '',
                    'GranteeAddress' => '',
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $propertyDescription,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'size' => $subApp->size,
                    'plotNumber' => $subApp->plotNumber,
                    'deeds_date' => $sectionalRegistration->instrumentDate ?? null,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => $sectionalRegistration ? 'registered' : 'pending',
                    'land_use' => '',
                    'reg_created_by' => $subApp->reg_created_by,
                    'created_at' => $subApp->created_at,
                    'reg_creator_name' => $subApp->reg_creator_name,
                    'instrument_category' => 'Sectional Titling',
                    'STM_Ref' => $sectionalRegistration->STM_Ref ?? null,
                    'original_subapp_id' => $subApp->id
                ];

                $allInstruments->push($sectionalRecord);
            }

            // Fetch unregistered generic ST instruments from instrument_registration table
            $unregisteredSTInstruments = DB::connection('sqlsrv')->table('instrument_registration')
                ->leftJoin('users', 'instrument_registration.created_by', '=', 'users.id')
                ->where(function ($q) {
                    $q->where('instrument_registration.status', '!=', 'registered')
                        ->orWhereNull('instrument_registration.status');
                })
                ->where(function ($q) {
                    $q->where('instrument_registration.MLSFileNo', 'like', 'ST-%')
                        ->orWhere('instrument_registration.KAGISFileNO', 'like', 'ST-%')
                        ->orWhere('instrument_registration.NewKANGISFileNo', 'like', 'ST-%')
                        ->orWhere('instrument_registration.StFileNo', 'like', 'ST-%');
                })
                ->select(
                    'instrument_registration.*',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            foreach ($unregisteredSTInstruments as $instrument) {
                $fileNo = $instrument->StFileNo ?? $instrument->MLSFileNo ?? $instrument->KAGISFileNO ?? $instrument->NewKANGISFileNo ?? 'N/A';

                $instrumentRecord = (object) [
                    'id' => 'instr_reg_' . $instrument->id,
                    'fileno' => $fileNo,
                    'parent_fileNo' => null,
                    'Deeds_Serial_No' => $instrument->particularsRegistrationNumber ?? null,
                    'volume_no' => $instrument->volumeNo ?? $instrument->volume_no ?? $instrument->oldTitleVolumeNo ?? null,
                    'instrument_type' => $instrument->instrument_type ?? 'Other Instrument',
                    'Grantor' => $instrument->Grantor ?? 'N/A',
                    'Grantee' => $instrument->Grantee ?? 'N/A',
                    'GrantorAddress' => $instrument->GrantorAddress ?? '',
                    'GranteeAddress' => $instrument->GranteeAddress ?? '',
                    'duration' => $instrument->duration ?? $instrument->leasePeriod ?? '',
                    'leasePeriod' => $instrument->leasePeriod ?? '',
                    'propertyDescription' => $instrument->propertyDescription ?? '',
                    'lga' => $instrument->lga ?? '',
                    'district' => $instrument->district ?? '',
                    'size' => $instrument->size ?? '',
                    'plotNumber' => $instrument->plotNumber ?? '',
                    'deeds_date' => $instrument->instrumentDate ?? null,
                    'solicitorName' => $instrument->solicitorName ?? '',
                    'solicitorAddress' => $instrument->solicitorAddress ?? '',
                    'status' => $instrument->status ?? 'pending',
                    'land_use' => $instrument->landUseType ?? '',
                    'reg_created_by' => $instrument->created_by,
                    'created_at' => $instrument->created_at,
                    'reg_creator_name' => $instrument->reg_creator_name ?? '',
                    'instrument_category' => 'Other Instruments',
                    'STM_Ref' => null,
                    'original_instrument_id' => $instrument->id,
                    'registered_instrument_id' => null,
                    'application_type' => 'Other',
                    'captured_date' => $instrument->created_at ?? $instrument->instrumentDate,
                    'reg_date' => null,
                    'reg_time' => null
                ];

                $allInstruments->push($instrumentRecord);
            }

            // Fetch unregistered captured ST instruments from instrument_capture table
            $capturedSTInstruments = DB::connection('sqlsrv')->table('instrument_capture')
                ->leftJoin('users', 'instrument_capture.created_by', '=', 'users.id')
                ->where(function ($q) {
                    $q->where('instrument_capture.is_deed_registered', 0)
                        ->orWhereNull('instrument_capture.is_deed_registered');
                })
                ->where(function ($q) {
                    $q->where('instrument_capture.mlsFNo', 'like', 'ST-%')
                        ->orWhere('instrument_capture.kangisFileNo', 'like', 'ST-%')
                        ->orWhere('instrument_capture.NewKANGISFileno', 'like', 'ST-%')
                        ->orWhere('instrument_capture.temp_fileno', 'like', 'ST-%');
                })
                ->select(
                    'instrument_capture.*',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            foreach ($capturedSTInstruments as $instrument) {
                $fileNo = $instrument->mlsFNo ?? $instrument->kangisFileNo ?? $instrument->NewKANGISFileno ?? $instrument->temp_fileno ?? 'N/A';
                $mappedType = $this->mapCaptureTypeToRegistry($instrument->instrument_type);

                $instrumentRecord = (object) [
                    'id' => 'instr_capture_' . $instrument->id,
                    'fileno' => $fileNo,
                    'parent_fileNo' => null,
                    'Deeds_Serial_No' => $instrument->registration_number ?? null,
                    'volume_no' => $instrument->volume_no ?? null,
                    'instrument_type' => $mappedType,
                    'Grantor' => $instrument->party_1_name ?? 'N/A',
                    'Grantee' => $instrument->party_2_name ?? 'N/A',
                    'GrantorAddress' => $instrument->party_1_address ?? '',
                    'GranteeAddress' => $instrument->party_2_address ?? '',
                    'duration' => $instrument->duration ?? '',
                    'leasePeriod' => $instrument->duration ?? '',
                    'propertyDescription' => $instrument->property_description ?? '',
                    'lga' => $instrument->lga ?? '',
                    'district' => $instrument->district ?? '',
                    'size' => $instrument->plot_size ?? '',
                    'plotNumber' => $instrument->plot_number ?? '',
                    'deeds_date' => $instrument->instrument_date ?? null,
                    'solicitorName' => $instrument->solicitor_name ?? '',
                    'solicitorAddress' => $instrument->solicitor_address ?? '',
                    'status' => 'pending',
                    'land_use' => $instrument->land_use ?? '',
                    'reg_created_by' => $instrument->created_by,
                    'created_at' => $instrument->created_at,
                    'reg_creator_name' => $instrument->reg_creator_name ?? '',
                    'instrument_category' => 'Captured Instruments',
                    'STM_Ref' => null,
                    'original_instrument_id' => $instrument->id,
                    'registered_instrument_id' => null,
                    'application_type' => 'Captured',
                    'captured_date' => $instrument->created_at,
                    'reg_date' => null,
                    'reg_time' => null,
                    'op_type' => (!empty($instrument->op_type) && !str_starts_with($instrument->op_type, 'OP ')) ? 'OP ' . $instrument->op_type : ($instrument->op_type ?? null),
                ];

                $allInstruments->push($instrumentRecord);
            }

            // Fetch registered instruments from deed_registrations table starting with ST-
            $registeredSTOtherInstruments = DB::connection('sqlsrv')->table('deed_registrations')
                ->leftJoin('users', 'deed_registrations.created_by', '=', 'users.id')
                ->leftJoin('instrument_capture', function ($join) {
                    $join->on('deed_registrations.fileno', '=', 'instrument_capture.mlsFNo')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.temp_fileno')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.kangisFileNo')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.NewKANGISFileno');
                })
                ->where('deed_registrations.fileno', 'like', 'ST-%')
                ->select(
                    'deed_registrations.*',
                    'deed_registrations.rds_exists as rds_exists_db',
                    'deed_registrations.cor_exists as cor_exists_db',
                    'instrument_capture.op_type as captured_op_type',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            foreach ($registeredSTOtherInstruments as $reg) {
                // Prevent duplication with subapp-based ones in $allInstruments
                $duplicate = $allInstruments->first(function ($item) use ($reg) {
                    return strtolower($item->fileno) === strtolower($reg->fileno)
                        && strtolower($item->instrument_type) === strtolower($reg->instrument_type);
                });

                if ($duplicate) {
                    continue;
                }

                $instrumentRecord = (object) [
                    'id' => 'deed_reg_' . $reg->id,
                    'fileno' => $reg->fileno,
                    'parent_fileNo' => $reg->parent_fileno,
                    'Deeds_Serial_No' => $reg->registration_number,
                    'volume_no' => $reg->volume_no ?? null,
                    'instrument_type' => $reg->instrument_type,
                    'Grantor' => $reg->grantor ?? 'N/A',
                    'Grantee' => $reg->grantee ?? 'N/A',
                    'GrantorAddress' => '',
                    'GranteeAddress' => '',
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $reg->property_description ?? '',
                    'lga' => $reg->lga ?? '',
                    'district' => $reg->district ?? '',
                    'size' => $reg->size ?? '',
                    'plotNumber' => $reg->plot_number ?? '',
                    'deeds_date' => $reg->deeds_date,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => 'registered',
                    'land_use' => '',
                    'reg_created_by' => $reg->created_by,
                    'created_at' => $reg->created_at,
                    'reg_creator_name' => $reg->reg_creator_name ?? '',
                    'instrument_category' => 'Deeds',
                    'STM_Ref' => null,
                    'original_instrument_id' => null,
                    'registered_instrument_id' => 'deed_reg_' . $reg->id,
                    'application_type' => 'Deed',
                    'captured_date' => $reg->created_at ?? $reg->instrument_date,
                    'reg_date' => $reg->deeds_date,
                    'reg_time' => $reg->deeds_time ? date('g:i A', strtotime($reg->deeds_time)) : ($reg->deeds_date ? date('g:i A', strtotime($reg->deeds_date)) : null),
                    'rds_exists' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists' => (bool) ($reg->cor_exists ?? false),
                    'rds_exists_db' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists_db' => (bool) ($reg->cor_exists ?? false),
                    'op_type' => $reg->captured_op_type ?? null,
                ];

                if ($instrumentRecord->instrument_type === 'Occupancy Permit (OP)' && $instrumentRecord->op_type) {
                    if (!str_starts_with($instrumentRecord->op_type, 'OP ')) {
                        $instrumentRecord->op_type = 'OP ' . $instrumentRecord->op_type;
                    }
                }

                $allInstruments->push($instrumentRecord);
            }

            // Fetch registered instruments from registered_instruments table starting with ST-
            $registeredSTInstruments = DB::connection('sqlsrv')->table('registered_instruments')
                ->leftJoin('users', 'registered_instruments.created_by', '=', 'users.id')
                ->where('registered_instruments.StFileNo', 'like', 'ST-%')
                ->whereNotIn('registered_instruments.instrument_type', [
                    'ST Assignment (Transfer of Title)',
                    'Sectional Titling CofO',
                    'ST Fragmentation'
                ])
                ->select(
                    'registered_instruments.*',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            foreach ($registeredSTInstruments as $reg) {
                // Prevent duplication
                $duplicate = $allInstruments->first(function ($item) use ($reg) {
                    return strtolower($item->fileno) === strtolower($reg->StFileNo)
                        && strtolower($item->instrument_type) === strtolower($reg->instrument_type);
                });

                if ($duplicate) {
                    continue;
                }

                $instrumentRecord = (object) [
                    'id' => 'registered_inst_' . $reg->id,
                    'fileno' => $reg->StFileNo,
                    'parent_fileNo' => $reg->MLSFileNo ?? $reg->KAGISFileNO ?? $reg->NewKANGISFileNo ?? null,
                    'Deeds_Serial_No' => $reg->particularsRegistrationNumber ?? null,
                    'volume_no' => $reg->volumeNo ?? $reg->volume_no ?? null,
                    'instrument_type' => $reg->instrument_type,
                    'Grantor' => $reg->Grantor ?? 'N/A',
                    'Grantee' => $reg->Grantee ?? 'N/A',
                    'GrantorAddress' => $reg->GrantorAddress ?? '',
                    'GranteeAddress' => $reg->GranteeAddress ?? '',
                    'duration' => $reg->duration ?? $reg->leasePeriod ?? '',
                    'leasePeriod' => $reg->leasePeriod ?? '',
                    'propertyDescription' => $reg->propertyDescription ?? '',
                    'lga' => $reg->lga ?? '',
                    'district' => $reg->district ?? '',
                    'size' => $reg->size ?? '',
                    'plotNumber' => $reg->plotNumber ?? '',
                    'deeds_date' => $reg->instrumentDate ?? null,
                    'solicitorName' => $reg->solicitorName ?? '',
                    'solicitorAddress' => $reg->solicitorAddress ?? '',
                    'status' => 'registered',
                    'land_use' => $reg->landUseType ?? '',
                    'reg_created_by' => $reg->created_by,
                    'created_at' => $reg->created_at,
                    'reg_creator_name' => $reg->reg_creator_name ?? '',
                    'instrument_category' => 'Deeds',
                    'STM_Ref' => $reg->STM_Ref ?? null,
                    'original_instrument_id' => null,
                    'registered_instrument_id' => 'registered_inst_' . $reg->id,
                    'application_type' => 'Deed',
                    'captured_date' => $reg->created_at ?? $reg->instrumentDate,
                    'reg_date' => $reg->instrumentDate ?? null,
                    'reg_time' => $reg->deeds_time ? date('g:i A', strtotime($reg->deeds_time)) : ($reg->instrumentDate ? date('g:i A', strtotime($reg->instrumentDate)) : null),
                    'rds_exists' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists' => (bool) ($reg->cor_exists ?? false),
                    'rds_exists_db' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists_db' => (bool) ($reg->cor_exists ?? false),
                ];

                $allInstruments->push($instrumentRecord);
            }


            // Exclude ST Fragmentation records from the main listing as they are registered automatically
            // and do not require manual interaction in this view.
            /*
            foreach ($approvedMotherApplications as $motherApp) {
                // Check if ST Fragmentation is already registered
                $stFragmentationRegistration = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('MLSFileNo', $motherApp->fileno)
                    ->where('instrument_type', 'ST Fragmentation')
                    ->where('status', 'registered')
                    ->first();

                // Build property description for ST Fragmentation
                $propertyDescription = '';
                $propertyParts = [];

                if (!empty($motherApp->property_house_no)) {
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                }
                if (!empty($motherApp->plotNumber)) {
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                }
                if (!empty($motherApp->property_street_name)) {
                    $propertyParts[] = $motherApp->property_street_name;
                }
                if (!empty($motherApp->district)) {
                    $propertyParts[] = $motherApp->district;
                }
                if (!empty($motherApp->lga)) {
                    $propertyParts[] = $motherApp->lga;
                }

                $propertyDescription = implode(', ', $propertyParts);
                if (empty($propertyDescription)) {
                    $propertyDescription = 'Property details not available';
                }

                // Build mother applicant name properly for ST Fragmentation Grantee
                $motherApplicantName = '';
                $motherApplicantParts = [];

                if (!empty($motherApp->applicant_title)) {
                    $motherApplicantParts[] = $motherApp->applicant_title;
                }
                if (!empty($motherApp->first_name)) {
                    $motherApplicantParts[] = $motherApp->first_name;
                }
                if (!empty($motherApp->middle_name)) {
                    $motherApplicantParts[] = $motherApp->middle_name;
                }
                if (!empty($motherApp->surname)) {
                    $motherApplicantParts[] = $motherApp->surname;
                }
                if (!empty($motherApp->corporate_name)) {
                    $motherApplicantParts[] = $motherApp->corporate_name;
                }
                if (!empty($motherApp->rc_number)) {
                    $motherApplicantParts[] = $motherApp->rc_number;
                }
                if (!empty($motherApp->multiple_owners_names)) {
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                }

                $motherApplicantName = implode(' ', $motherApplicantParts);
                if (empty($motherApplicantName)) {
                    $motherApplicantName = $motherApp->mother_applicant ?? 'N/A';
                }

                // Create ST Fragmentation record
                $stFragmentationRecord = (object)[
                    'id' => $motherApp->id . '_st_fragmentation',
                    'fileno' => $motherApp->np_fileno ?? $motherApp->fileno, // np_fileno from mother_applications table as fileNo
                    'parent_fileNo' => $motherApp->fileno, // fileno from mother_applications table as parent_fileNo
                    'Deeds_Serial_No' => $stFragmentationRegistration->particularsRegistrationNumber ?? null,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government', // As specified in requirements
                    'Grantee' => $motherApplicantName, // Use properly built mother applicant name
                    'GrantorAddress' => '',
                    'GranteeAddress' => '',
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $propertyDescription,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'size' => $motherApp->size,
                    'plotNumber' => $motherApp->plotNumber,
                    'deeds_date' => $stFragmentationRegistration->instrumentDate ?? null,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => $stFragmentationRegistration ? 'registered' : 'pending',
                    'land_use' => '',
                    'reg_created_by' => $motherApp->reg_created_by,
                    'created_at' => $motherApp->created_at,
                    'reg_creator_name' => $motherApp->reg_creator_name,
                    'instrument_category' => 'ST Fragmentation',
                    'STM_Ref' => $stFragmentationRegistration->STM_Ref ?? null,
                    'original_mother_app_id' => $motherApp->id
                ];

                $allInstruments->push($stFragmentationRecord);
            }
            */

            Log::info('Instrument Registration data loaded', [
                'approved_subapplications' => $approvedSubapplications->count(),
                'approved_mother_applications' => $approvedMotherApplications->count(),
                'total_instruments' => $allInstruments->count(),
                'st_assignment_count' => $allInstruments->where('instrument_type', 'ST Assignment (Transfer of Title)')->count(),
                'sectional_titling_count' => $allInstruments->where('instrument_type', 'Sectional Titling CofO')->count()
            ]);

            // Count statuses
            $pendingCount = $allInstruments->where('status', 'pending')->count();
            $registeredCount = $allInstruments->where('status', 'registered')->count();
            $rejectedCount = 0; // No rejected status in this context
            $totalCount = $allInstruments->count();

            // Process property descriptions and durations
            foreach ($allInstruments as $application) {
                if (empty($application->propertyDescription)) {
                    $application->property_description =
                        (!empty($application->district) ? $application->district . ', ' : '') .
                        (!empty($application->lga) ? $application->lga . ', ' : '') .
                        (!empty($application->state) ? $application->state : '');
                } else {
                    $application->property_description = $application->propertyDescription;
                }

                $application->duration = $application->duration ?? $application->leasePeriod ?? 'N/A';
            }

            Log::info('Final instrument counts', [
                'total_count' => $totalCount,
                'pending_count' => $pendingCount,
                'registered_count' => $registeredCount,
                'rejected_count' => $rejectedCount,
            ]);

            $approvedApplications = $allInstruments;

            $instrumentTypes = [
                'ST Assignment (Transfer of Title)',
                'Sectional Titling CofO'
            ];

            return view('st_deeds.index', compact(
                'approvedApplications',
                'PageTitle',
                'PageDescription',
                'pendingCount',
                'registeredCount',
                'rejectedCount',
                'totalCount',
                'instrumentTypes'
            ));

        } catch (\Exception $e) {
            Log::error('Error in InstrumentRegistration method', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $approvedApplications = collect();
            $pendingCount = $registeredCount = $rejectedCount = $totalCount = 0;

            return view('st_deeds.index', compact(
                'approvedApplications',
                'PageTitle',
                'PageDescription',
                'pendingCount',
                'registeredCount',
                'rejectedCount',
                'totalCount'
            ))->with('error', 'Error loading instrument data: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        $PageTitle = 'View Instrument Registration';
        $PageDescription = '';

        try {
            $application = null;

            // Handle composite IDs for ST Assignment and Sectional Titling
            if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
                $instrumentType = strpos($id, '_st_assignment') !== false ? 'ST Assignment (Transfer of Title)' : 'Sectional Titling CofO';

                // Get the subapplication details
                $subApplication = DB::connection('sqlsrv')->table('subapplications as s')
                    ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                    ->leftJoin('users', 's.created_by', '=', 'users.id')
                    ->where('s.id', $originalId)
                    ->select(
                        's.*',
                        DB::raw("TRIM(REPLACE(CASE 
                            WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                            WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                            ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''))
                        END, '  ', ' ')) as sub_applicant"),
                        'm.property_lga as lga',
                        'm.property_district as district',
                        'm.plot_size as size',
                        'm.property_plot_no as plotNumber',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->first();

                if (!$subApplication) {
                    Log::error('Subapplication not found', ['id' => $originalId]);
                    return redirect()->route('st_deeds.index')->with('error', 'Instrument not found');
                }

                // Check if this instrument is registered and get registration details
                $registeredInstrument = DB::connection('sqlsrv')->table('registered_instruments')
                    ->leftJoin('users', 'registered_instruments.created_by', '=', 'users.id')
                    ->where('registered_instruments.StFileNo', $subApplication->fileno)
                    ->where('registered_instruments.instrument_type', $instrumentType)
                    ->where('registered_instruments.status', 'registered')
                    ->select(
                        'registered_instruments.*',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->first();

                // Create a combined application object
                $application = (object) [
                    'id' => $id,
                    'fileno' => $subApplication->fileno,
                    'instrument_type' => $instrumentType,
                    'Grantor' => $subApplication->sub_applicant,
                    'Grantee' => $subApplication->sub_applicant,
                    'Applicant_Name' => $subApplication->sub_applicant,
                    'lga' => $subApplication->lga,
                    'district' => $subApplication->district,
                    'size' => $subApplication->size,
                    'plotNumber' => $subApplication->plotNumber,
                    'reg_creator_name' => $subApplication->reg_creator_name,
                    'created_at' => $subApplication->created_at,
                    'updated_at' => $subApplication->updated_at ?? $subApplication->created_at,
                    'source_type' => 'subapplication',
                    // Registration details if available
                    'particularsRegistrationNumber' => $registeredInstrument->particularsRegistrationNumber ?? null,
                    'Deeds_Serial_No' => $registeredInstrument->particularsRegistrationNumber ?? null,
                    'STM_Ref' => $registeredInstrument->STM_Ref ?? null,
                    'instrumentDate' => $registeredInstrument->instrumentDate ?? null,
                    'deeds_date' => $registeredInstrument->deeds_date ?? $registeredInstrument->instrumentDate ?? null,
                    'deeds_time' => $registeredInstrument->deeds_time ?? null,
                    'status' => $registeredInstrument ? 'registered' : 'pending',
                    'reg_status' => $registeredInstrument ? 'registered' : 'pending',
                    'propertyDescription' => $registeredInstrument->propertyDescription ?? '',
                    'GrantorAddress' => $registeredInstrument->GrantorAddress ?? '',
                    'GranteeAddress' => $registeredInstrument->GranteeAddress ?? '',
                    'duration' => $registeredInstrument->duration ?? '',
                    'solicitorName' => $registeredInstrument->solicitorName ?? '',
                    'solicitorAddress' => $registeredInstrument->solicitorAddress ?? '',
                    // Additional properties that might be referenced in the view
                    'Tenure_Period' => $registeredInstrument->Tenure_Period ?? null,
                    'serial_no' => $registeredInstrument->serial_no ?? null,
                    'page_no' => $registeredInstrument->page_no ?? null,
                    'reg_page_no' => $registeredInstrument->page_no ?? null,
                    'volume_no' => $registeredInstrument->volume_no ?? null,
                    'Occupation' => $subApplication->occupation ?? null,
                    'NoOfUnits' => null,
                    'NoOfBlocks' => null,
                    'NoOfSections' => null,
                    'property_street_name' => null,
                    'property_district' => $subApplication->district,
                    'property_lga' => $subApplication->lga,
                    'land_use' => null,
                    'commercial_type' => null,
                    'industrial_type' => null,
                    'residential_type' => null
                ];

            } else {
                // Regular registered instrument ID
                $application = DB::connection('sqlsrv')
                    ->table('registered_instruments')
                    ->leftJoin('users', 'registered_instruments.created_by', '=', 'users.id')
                    ->select(
                        'registered_instruments.*',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->where('registered_instruments.id', $id)
                    ->first();

                if ($application) {
                    $application->source_type = 'registered_instruments';
                    $application->fileno = $application->MLSFileNo ?? $application->KAGISFileNO ?? $application->NewKANGISFileNo ?? $application->StFileNo;
                    // Ensure all required properties exist
                    $application->Deeds_Serial_No = $application->particularsRegistrationNumber ?? null;
                    $application->reg_status = $application->status ?? 'pending';
                    $application->Applicant_Name = $application->Grantor ?? $application->Grantee ?? 'N/A';
                    $application->reg_page_no = $application->page_no ?? null;
                    $application->property_district = $application->district ?? null;
                    $application->property_lga = $application->lga ?? null;
                    // Set default values for properties that might not exist
                    $application->Tenure_Period = $application->Tenure_Period ?? null;
                    $application->Occupation = $application->Occupation ?? null;
                    $application->NoOfUnits = $application->NoOfUnits ?? null;
                    $application->NoOfBlocks = $application->NoOfBlocks ?? null;
                    $application->NoOfSections = $application->NoOfSections ?? null;
                    $application->property_street_name = $application->property_street_name ?? null;
                    $application->land_use = $application->land_use ?? null;
                    $application->commercial_type = $application->commercial_type ?? null;
                    $application->industrial_type = $application->industrial_type ?? null;
                    $application->residential_type = $application->residential_type ?? null;
                }
            }

            if (!$application) {
                Log::error('Instrument not found', ['id' => $id]);
                return redirect()->route('st_deeds.index')->with('error', 'Instrument not found');
            }

            return view('st_deeds.view', compact('application', 'PageTitle', 'PageDescription'));
        } catch (\Exception $e) {
            Log::error('Error in view method', [
                'id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('st_deeds.index')
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Check registration status for ST Assignment and Sectional Titling CofO for a given file number
     */
    public function checkRegistrationStatus(Request $request)
    {
        try {
            $fileNo = $request->query('file_no');

            if (empty($fileNo)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File number is required'
                ], 400);
            }

            $registrations = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('StFileNo', $fileNo)
                ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                ->select('instrument_type', 'status', 'particularsRegistrationNumber', 'STM_Ref', 'created_at')
                ->get();

            $stAssignment = $registrations->firstWhere('instrument_type', 'ST Assignment (Transfer of Title)');
            $sectionalTitling = $registrations->firstWhere('instrument_type', 'Sectional Titling CofO');

            $response = [
                'success' => true,
                'file_no' => $fileNo,
                'st_assignment' => [
                    'registered' => !is_null($stAssignment),
                    'status' => $stAssignment->status ?? null,
                    'registration_number' => $stAssignment->particularsRegistrationNumber ?? null,
                    'stm_ref' => $stAssignment->STM_Ref ?? null,
                    'registered_date' => $stAssignment->created_at ?? null
                ],
                'sectional_titling' => [
                    'registered' => !is_null($sectionalTitling),
                    'status' => $sectionalTitling->status ?? null,
                    'registration_number' => $sectionalTitling->particularsRegistrationNumber ?? null,
                    'stm_ref' => $sectionalTitling->STM_Ref ?? null,
                    'registered_date' => $sectionalTitling->created_at ?? null
                ],
                'both_registered' => !is_null($stAssignment) && !is_null($sectionalTitling),
                'total_registrations' => $registrations->count()
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error checking registration status', [
                'file_no' => $request->query('file_no'),
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check registration status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getNextSerialNumber()
    {
        try {
            $latest = DB::connection('sqlsrv')->table('registered_instruments')
                ->select('volume_no', 'page_no', 'serial_no')
                ->orderBy('volume_no', 'desc')
                ->orderBy('page_no', 'desc')
                ->first();

            if (!$latest) {
                return response()->json([
                    'serial_no' => 1,
                    'page_no' => 1,
                    'volume_no' => 1,
                    'deeds_serial_no' => '1/1/1'
                ]);
            }

            $volumeNo = $latest->volume_no;
            $pageNo = $latest->page_no;
            $serialNo = $latest->serial_no;

            if ($pageNo >= 100) {
                $volumeNo++;
                $pageNo = 1;
                $serialNo = 1;
            } else {
                $pageNo++;
                $serialNo++;
            }

            $deedsSerialNo = "$serialNo/$pageNo/$volumeNo";

            return response()->json([
                'serial_no' => $serialNo,
                'page_no' => $pageNo,
                'volume_no' => $volumeNo,
                'deeds_serial_no' => $deedsSerialNo
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating next serial number', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to generate serial number: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBatchData(Request $request)
    {
        try {
            $filter = $request->query('filter', 'batch');
            $data = collect();

            switch ($filter) {
                case 'other':
                    // Keep other instruments available for registration modals
                    $data = DB::connection('sqlsrv')->table('st_deeds')
                        ->where(function ($q) {
                            $q->where('status', '!=', 'registered')
                                ->orWhereNull('status');
                        })
                        ->select(
                            'id',
                            DB::raw("COALESCE(MLSFileNo, KAGISFileNO, NewKANGISFileNo) as fileno"),
                            'instrument_type',
                            'Grantor as grantor',
                            'Grantee as grantee',
                            'lga',
                            'district',
                            'size',
                            'plotNumber',
                            'created_at',
                            DB::raw("COALESCE(status, 'pending') as status"),
                            DB::raw("'Other Instruments' as source_type")
                        )
                        ->get();
                    break;

                case 'stAssignment':
                    // ST Assignment from subapplications where both statuses are approved
                    // Only show PENDING ST Assignment instruments
                    $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                                WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                                ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''))
                            END, '  ', ' ')) as sub_applicant"),
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                                WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                                ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''))
                            END, '  ', ' ')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create ST Assignment records for each subapplication, but only if it's PENDING
                    // Check registered_instruments directly to get actual registration status
                    $registeredStFiles = DB::connection('sqlsrv')->table('registered_instruments')
                        ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                        ->where('status', 'registered')
                        ->pluck('StFileNo')
                        ->map(fn($v) => strtolower(trim($v)))
                        ->toArray();

                    // Files with ST Fragmentation registered are proven same-owner (express)
                    // ST Assignment for these should be auto-registered, not shown in the modal
                    $registeredFragFiles = DB::connection('sqlsrv')->table('registered_instruments')
                        ->where('instrument_type', 'ST Fragmentation')
                        ->where('status', 'registered')
                        ->pluck('StFileNo')
                        ->filter()
                        ->map(fn($v) => strtolower(trim($v)))
                        ->toArray();

                    $data = collect();
                    foreach ($approvedSubapplications as $subApp) {
                        $fileKey = strtolower(trim($subApp->fileno));

                        // Skip if already registered
                        if (in_array($fileKey, $registeredStFiles)) {
                            continue;
                        }

                        // Skip if ST Fragmentation is registered for this file (proves same-owner/express)
                        if (in_array($fileKey, $registeredFragFiles)) {
                            continue;
                        }

                        // Also skip express assignments (same owner by name match)
                        if ($this->isSameOwner($subApp->mother_applicant, $subApp->sub_applicant)) {
                            continue;
                        }

                        $data->push((object) [
                            'id' => $subApp->id . '_st_assignment',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'ST Assignment (Transfer of Title)',
                            'grantor' => $subApp->mother_applicant,
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'ST Assignment',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }
                    break;

                case 'regular':
                case 'sltr':
                    // Keep these available for other instrument types in modals
                    $data = collect([
                        (object) [
                            'id' => null,
                            'fileno' => 'No Record',
                            'grantor' => 'No Record',
                            'grantee' => 'No Record',
                            'lga' => 'No Record',
                            'district' => 'No Record',
                            'size' => 'No Record',
                            'plotNumber' => 'No Record',
                            'created_at' => null,
                            'status' => 'unavailable'
                        ]
                    ]);
                    break;

                case 'sectional':
                    // Sectional Titling from subapplications where both statuses are approved
                    // Only show PENDING Sectional Titling instruments
                    $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                                WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                                ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''))
                            END, '  ', ' ')) as sub_applicant"),
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                                WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                                ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''))
                            END, '  ', ' ')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create Sectional Titling records for each subapplication, but only if it's PENDING
                    // Check registered_instruments directly to get actual registration status
                    $registeredSectionalFiles = DB::connection('sqlsrv')->table('registered_instruments')
                        ->where('instrument_type', 'Sectional Titling CofO')
                        ->where('status', 'registered')
                        ->pluck('StFileNo')
                        ->map(fn($v) => strtolower(trim($v)))
                        ->toArray();

                    $data = collect();
                    foreach ($approvedSubapplications as $subApp) {
                        // Skip if already registered in registered_instruments (real source of truth)
                        if (in_array(strtolower(trim($subApp->fileno)), $registeredSectionalFiles)) {
                            continue;
                        }

                        $data->push((object) [
                            'id' => $subApp->id . '_sectional_cofo',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'Sectional Titling CofO',
                            'grantor' => 'Kano State Government',
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'Sectional Titling',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }
                    break;

                case 'batch':
                default:
                    // For batch registration, include other instruments plus the two main types from subapplications
                    $instrumentData = DB::connection('sqlsrv')->table('st_deeds')
                        ->where(function ($q) {
                            $q->where('status', '!=', 'registered')
                                ->orWhereNull('status');
                        })
                        ->select('id', DB::raw("COALESCE(MLSFileNo, KAGISFileNO, NewKANGISFileNo) as fileno"), 'instrument_type', 'Grantor as grantor', 'Grantee as grantee', 'lga', 'district', 'size', 'plotNumber', 'created_at', DB::raw("COALESCE(status, 'pending') as status"), DB::raw("'Other Instruments' as source_type"))->get();

                    // Automatically register Express ST Assignments
                    $this->autoRegisterExpressSTAssignments();

                    // Get approved subapplications
                    $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                                WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                                ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''))
                            END, '  ', ' ')) as sub_applicant"),
                            DB::raw("TRIM(REPLACE(CASE 
                                WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                                WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                                ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                            END, '  ', ' ')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create both ST Assignment and Sectional Titling records for each subapplication
                    // Use registered_instruments as the authoritative source of truth for registration status
                    // Fetch ALL registered file numbers for all three instrument types in one query
                    $registeredLookup = DB::connection('sqlsrv')->table('registered_instruments')
                        ->where('status', 'registered')
                        ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO', 'ST Fragmentation'])
                        ->select('StFileNo', 'instrument_type')
                        ->get()
                        ->groupBy('instrument_type')
                        ->map(
                            fn($rows) => $rows->pluck('StFileNo')
                                ->filter()
                                ->map(fn($v) => strtolower(trim($v)))
                                ->toArray()
                        );

                    $registeredStAssignment = $registeredLookup->get('ST Assignment (Transfer of Title)', []);
                    $registeredSectional = $registeredLookup->get('Sectional Titling CofO', []);
                    $registeredFragmentation = $registeredLookup->get('ST Fragmentation', []);

                    $stAssignmentData = collect();
                    $subData = collect();

                    foreach ($approvedSubapplications as $subApp) {
                        $fileKey = strtolower(trim($subApp->fileno));

                        // Determine if express: name match OR ST Fragmentation already registered for this file
                        $isExpressAssignment = $this->isSameOwner($subApp->mother_applicant, $subApp->sub_applicant)
                            || in_array($fileKey, $registeredFragmentation);

                        // ST Assignment: skip if already registered OR express (same owner / fragmentation registered)
                        if (!$isExpressAssignment && !in_array($fileKey, $registeredStAssignment)) {
                            $stAssignmentData->push((object) [
                                'id' => $subApp->id . '_st_assignment',
                                'fileno' => $subApp->fileno,
                                'instrument_type' => 'ST Assignment (Transfer of Title)',
                                'grantor' => $subApp->mother_applicant,
                                'grantee' => $subApp->sub_applicant,
                                'lga' => $subApp->lga,
                                'district' => $subApp->district,
                                'size' => $subApp->size,
                                'plotNumber' => $subApp->plotNumber,
                                'created_at' => $subApp->created_at,
                                'status' => 'pending',
                                'source_type' => 'ST Assignment',
                                'original_subapp_id' => $subApp->id
                            ]);
                        }

                        // Sectional Titling CofO: skip if already registered
                        if (!in_array($fileKey, $registeredSectional)) {
                            $subData->push((object) [
                                'id' => $subApp->id . '_sectional_cofo',
                                'fileno' => $subApp->fileno,
                                'instrument_type' => 'Sectional Titling CofO',
                                'grantor' => 'Kano State Government',
                                'grantee' => $subApp->sub_applicant,
                                'lga' => $subApp->lga,
                                'district' => $subApp->district,
                                'size' => $subApp->size,
                                'plotNumber' => $subApp->plotNumber,
                                'created_at' => $subApp->created_at,
                                'status' => 'pending',
                                'source_type' => 'Sectional Titling',
                                'original_subapp_id' => $subApp->id
                            ]);
                        }
                    }

                    $data = $instrumentData->merge($stAssignmentData)->merge($subData);
                    break;
            }

            return response()->json($data->values()->toArray());

        } catch (\Exception $e) {
            Log::error('Error in getBatchData', ['filter' => $request->query('filter'), 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch batch data: ' . $e->getMessage()], 500);
        }
    }

    public function registerSingle(Request $request)
    {
        try {
            // Validate ST Assignment and Sectional Titling CofO requirements
            $instrumentType = $request->instrument_type;
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                // For these instrument types, we need to ensure both StFileNo and instrument type are properly validated
                $request->validate([
                    'instrument_type' => 'required|string',
                    'file_no' => 'required|string', // This will be used as StFileNo
                ], [
                    'instrument_type.required' => 'Instrument type is required for ST Assignment and Sectional Titling CofO',
                    'file_no.required' => 'File number (StFileNo) is required for ST Assignment and Sectional Titling CofO',
                ]);

                // Additional validation to ensure both types are registered for each application
                $fileNo = $request->file_no;
                $existingRegistrations = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $fileNo)
                    ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                    ->pluck('instrument_type')
                    ->toArray();

                // Check if we're trying to register the same type twice for the same file
                if (in_array($instrumentType, $existingRegistrations)) {
                    return response()->json([
                        'success' => false,
                        'error' => "A {$instrumentType} registration already exists for file number {$fileNo}"
                    ], 422);
                }

                // Log the registration attempt for tracking
                Log::info('ST/Sectional Titling registration attempt', [
                    'file_no' => $fileNo,
                    'instrument_type' => $instrumentType,
                    'existing_registrations' => $existingRegistrations
                ]);
            }

            $applicationId = $request->mother_application_id;
            $sourceRecord = null;
            $sourceTable = null;

            // Handle composite IDs for ST Assignment and Sectional Titling
            if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'subapplications';
                    // Add the original ID for proper status update
                    $sourceRecord->original_id = $originalId;
                }
            } else {
                $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'subapplications';
                } else {
                    $sourceRecord = DB::connection('sqlsrv')->table('st_deeds')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'st_deeds';
                    } else {
                        $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'mother_applications';
                        }
                    }
                }
            }

            if (!$sourceRecord) {
                return response()->json(['success' => false, 'error' => 'Source record not found in any table'], 404);
            }

            $serialData = $this->getNextSerialNumber()->getData(true);
            $stmReference = $this->generateSTMReference();
            $dataToInsert = $this->prepareRegistrationData($sourceRecord, $sourceTable, $request, $serialData, $stmReference);

            $newId = DB::connection('sqlsrv')->table('registered_instruments')->insertGetId($dataToInsert);

            // Update status using original ID if it's a composite ID
            $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;
            $this->updateSourceRecordStatus($updateId, $sourceTable);

            // Update instrument completion status for ST Assignment and Sectional Titling
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']) && $sourceTable === 'subapplications') {
                $this->updateInstrumentCompletionStatus($updateId, $instrumentType, 'Registered');
            }

            // Check if both ST Assignment and Sectional Titling are now registered for this file
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                $this->checkBothTypesRegistered($request->file_no ?? $sourceRecord->fileno);
            }

            return response()->json([
                'success' => true,
                'message' => 'Instrument registered successfully',
                'serial_data' => $serialData,
                'stm_ref' => $stmReference,
                'record_id' => $newId,
                'source_table' => $sourceTable
            ]);
        } catch (\Exception $e) {
            Log::error('Error in registerSingle', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to register: ' . $e->getMessage()], 500);
        }
    }

    public function registerBatch(Request $request)
    {
        try {
            $request->validate([
                'batch_entries' => 'required|array',
                'deeds_time' => 'required|string',
                'deeds_date' => 'required|date'
            ]);

            // Pre-validate ST Assignment and Sectional Titling entries
            $stFileValidation = [];
            foreach ($request->batch_entries as $entry) {
                $instrumentType = $entry['instrument_type'] ?? '';
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $fileNo = $entry['file_no'] ?? '';
                    if (empty($fileNo)) {
                        return response()->json([
                            'success' => false,
                            'error' => "File number (StFileNo) is required for {$instrumentType}"
                        ], 422);
                    }

                    // Track what we're trying to register for each file
                    if (!isset($stFileValidation[$fileNo])) {
                        $stFileValidation[$fileNo] = [];
                    }
                    $stFileValidation[$fileNo][] = $instrumentType;
                }
            }

            // Check for existing registrations and duplicates within the batch
            foreach ($stFileValidation as $fileNo => $types) {
                // Check for duplicates within the batch
                if (count($types) !== count(array_unique($types))) {
                    return response()->json([
                        'success' => false,
                        'error' => "Duplicate instrument types found in batch for file number {$fileNo}"
                    ], 422);
                }

                // Check existing registrations in database
                $existingRegistrations = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $fileNo)
                    ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                    ->pluck('instrument_type')
                    ->toArray();

                foreach ($types as $type) {
                    if (in_array($type, $existingRegistrations)) {
                        return response()->json([
                            'success' => false,
                            'error' => "A {$type} registration already exists for file number {$fileNo}"
                        ], 422);
                    }
                }
            }

            $serialData = $this->getNextSerialNumber()->getData(true);
            $results = [];
            $processedRecords = [];
            $registeredFiles = []; // Track files for final validation

            DB::connection('sqlsrv')->beginTransaction();

            foreach ($request->batch_entries as $index => $entry) {
                if ($index > 0) {
                    if (++$serialData['page_no'] > 100) {
                        $serialData['volume_no']++;
                        $serialData['page_no'] = 1;
                        $serialData['serial_no'] = 1;
                    } else {
                        $serialData['serial_no']++;
                    }
                    $serialData['deeds_serial_no'] = "{$serialData['serial_no']}/{$serialData['page_no']}/{$serialData['volume_no']}";
                }

                $applicationId = $entry['application_id'];
                $sourceRecord = null;
                $sourceTable = null;

                // Handle composite IDs for ST Assignment and Sectional Titling
                if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                    $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                        $sourceRecord->original_id = $originalId;
                    }
                } else {
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                    } else {
                        $sourceRecord = DB::connection('sqlsrv')->table('st_deeds')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'st_deeds';
                        } else {
                            $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                            if ($sourceRecord) {
                                $sourceTable = 'mother_applications';
                            }
                        }
                    }
                }

                if (!$sourceRecord) {
                    Log::warning('Source record not found for batch entry', ['application_id' => $applicationId]);
                    continue;
                }

                $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;
                $processedRecords[] = ['id' => $updateId, 'table' => $sourceTable];
                $stmReference = $this->generateSTMReference();

                $entryRequest = new \Illuminate\Http\Request();
                $entryRequest->merge([
                    'instrument_type' => $entry['instrument_type'] ?? '',
                    'Grantor' => $entry['grantor'] ?? '',
                    'Grantee' => $entry['grantee'] ?? '',
                    'duration' => $entry['duration'] ?? '',
                    'propertyDescription' => $entry['propertyDescription'] ?? '',
                    'lga' => $entry['lga'] ?? '',
                    'district' => $entry['district'] ?? '',
                    'plotSize' => $entry['size'] ?? '',
                    'plotNumber' => $entry['plotNumber'] ?? '',
                    'deeds_date' => $request->deeds_date,
                    'deeds_time' => $request->deeds_time,
                    'file_no' => $entry['file_no'] ?? ''
                ]);

                $dataToInsert = $this->prepareRegistrationData($sourceRecord, $sourceTable, $entryRequest, $serialData, $stmReference);
                $newId = DB::connection('sqlsrv')->table('registered_instruments')->insertGetId($dataToInsert);

                // Update instrument completion status for ST Assignment and Sectional Titling
                $instrumentType = $entry['instrument_type'] ?? '';
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']) && $sourceTable === 'subapplications') {
                    $this->updateInstrumentCompletionStatus($updateId, $instrumentType, 'Registered');
                }

                // Track registered files for final validation
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $fileNo = $entry['file_no'] ?? $sourceRecord->fileno;
                    $registeredFiles[] = $fileNo;
                }

                $results[] = [
                    'application_id' => $applicationId,
                    'new_id' => $newId,
                    'deeds_serial_no' => $serialData['deeds_serial_no'],
                    'stm_ref' => $stmReference,
                    'source_table' => $sourceTable
                ];
            }

            foreach ($processedRecords as $record) {
                $this->updateSourceRecordStatus($record['id'], $record['table']);
            }

            // Check if both types are registered for each file
            foreach (array_unique($registeredFiles) as $fileNo) {
                $this->checkBothTypesRegistered($fileNo);
            }

            DB::connection('sqlsrv')->commit();

            return response()->json(['success' => true, 'message' => count($results) . ' instruments registered successfully', 'results' => $results]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Error in registerBatch', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to register batch: ' . $e->getMessage()], 500);
        }
    }

    private function prepareRegistrationData($sourceRecord, $sourceTable, $request, $serialData, $stmReference)
    {
        // Convert array inputs to comma-separated strings
        if (is_array($request->instrument_type)) {
            $request->instrument_type = implode(',', $request->instrument_type);
        }
        if (is_array($request->Grantor)) {
            $request->Grantor = implode(',', $request->Grantor);
        }
        if (is_array($request->Grantee)) {
            $request->Grantee = implode(',', $request->Grantee);
        }

        // Determine StFileNo based on instrument type and source
        $stFileNo = null;
        if (in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
            $stFileNo = $request->file_no ?? $sourceRecord->fileno ?? null;
        }

        // Override grantor for ST Assignment and Sectional Titling CofO
        $grantor = $request->Grantor;
        if (in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
            $grantor = 'Kano State Government';
        }

        $baseData = [
            'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
            'STM_Ref' => $stmReference,
            'instrument_type' => $request->instrument_type,
            'Grantor' => $grantor, // Use the overridden grantor value
            'Grantee' => $request->Grantee,
            'instrumentDate' => $request->deeds_date,
            'deeds_date' => $request->deeds_date,
            'deeds_time' => $request->deeds_time,
            'serial_no' => $serialData['serial_no'],
            'page_no' => $serialData['page_no'],
            'volume_no' => $serialData['volume_no'],
            'status' => 'registered',
            'StFileNo' => $stFileNo, // Add StFileNo field
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        switch ($sourceTable) {
            case 'st_deeds':
                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->MLSFileNo ?? $request->file_no,
                    'KAGISFileNO' => $sourceRecord->KAGISFileNO ?? null,
                    'NewKANGISFileNo' => $sourceRecord->NewKANGISFileNo ?? null,
                    'rootRegistrationNumber' => $sourceRecord->rootRegistrationNumber ?? null,
                    'GrantorAddress' => $request->GrantorAddress ?? $sourceRecord->GrantorAddress ?? '',
                    'GranteeAddress' => $request->GranteeAddress ?? $sourceRecord->GranteeAddress ?? '',
                    'mortgagor' => $sourceRecord->mortgagor ?? null,
                    'mortgagorAddress' => $sourceRecord->mortgagorAddress ?? null,
                    'mortgagee' => $sourceRecord->mortgagee ?? null,
                    'mortgageeAddress' => $sourceRecord->mortgageeAddress ?? null,
                    'loanAmount' => $sourceRecord->loanAmount ?? null,
                    'interestRate' => $sourceRecord->interestRate ?? null,
                    'duration' => $request->duration ?? $sourceRecord->duration ?? null,
                    'assignor' => $sourceRecord->assignor ?? null,
                    'assignorAddress' => $sourceRecord->assignorAddress ?? null,
                    'assignee' => $sourceRecord->assignee ?? null,
                    'assigneeAddress' => $sourceRecord->assigneeAddress ?? null,
                    'lessor' => $sourceRecord->lessor ?? null,
                    'lessorAddress' => $sourceRecord->lessorAddress ?? null,
                    'lessee' => $sourceRecord->lessee ?? null,
                    'lesseeAddress' => $sourceRecord->lesseeAddress ?? null,
                    'leasePeriod' => $sourceRecord->leasePeriod ?? null,
                    'leaseTerms' => $sourceRecord->leaseTerms ?? null,
                    'propertyDescription' => $request->propertyDescription ?? $sourceRecord->propertyDescription ?? '',
                    'propertyAddress' => $sourceRecord->propertyAddress ?? null,
                    'lga' => $request->lga ?? $sourceRecord->lga ?? '',
                    'district' => $request->district ?? $sourceRecord->district ?? '',
                    'size' => $request->plotSize ?? $sourceRecord->size ?? '',
                    'plotNumber' => $request->plotNumber ?? $sourceRecord->plotNumber ?? '',
                    'landUseType' => $sourceRecord->landUseType ?? null,
                    'solicitorName' => $sourceRecord->solicitorName ?? null,
                    'solicitorAddress' => $sourceRecord->solicitorAddress ?? null,
                ]);

            case 'mother_applications':
                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->fileno ?? $request->file_no,
                    'lga' => $sourceRecord->property_lga ?? '',
                    'district' => $sourceRecord->property_district ?? '',
                    'size' => $sourceRecord->plot_size ?? '',
                    'plotNumber' => $sourceRecord->property_plot_no ?? '',
                ]);

            case 'subapplications':
                $motherApp = DB::connection('sqlsrv')->table('mother_applications')->where('id', $sourceRecord->main_application_id)->first();

                // Build property description for ST Assignment and Sectional Titling CofO
                $propertyDescription = $request->propertyDescription ?? '';
                if (empty($propertyDescription) && in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $propertyParts = [];

                    if (!empty($motherApp->property_house_no)) {
                        $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                    }
                    if (!empty($motherApp->property_plot_no)) {
                        $propertyParts[] = 'Plot No: ' . $motherApp->property_plot_no;
                    }
                    if (!empty($motherApp->property_street_name)) {
                        $propertyParts[] = $motherApp->property_street_name;
                    }
                    if (!empty($motherApp->property_district)) {
                        $propertyParts[] = $motherApp->property_district;
                    }
                    if (!empty($motherApp->property_lga)) {
                        $propertyParts[] = $motherApp->property_lga;
                    }

                    $propertyDescription = implode(', ', $propertyParts);
                    if (empty($propertyDescription)) {
                        $propertyDescription = 'Property details not available';
                    }
                }

                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->fileno ?? $request->file_no,
                    'lga' => $motherApp->property_lga ?? '',
                    'district' => $motherApp->property_district ?? '',
                    'size' => $motherApp->plot_size ?? '',
                    'plotNumber' => $motherApp->property_plot_no ?? '',
                    'propertyDescription' => $propertyDescription,
                ]);

            default:
                return $baseData;
        }
    }

    private function updateSourceRecordStatus($id, $sourceTable)
    {
        $updateData = [
            'updated_by' => Auth::id(),
            'updated_at' => now()
        ];

        switch ($sourceTable) {
            case 'st_deeds':
                $updateData['status'] = 'registered';
                DB::connection('sqlsrv')->table('st_deeds')->where('id', $id)->update($updateData);
                break;

            case 'mother_applications':
                $updateData['deeds_status'] = 'registered';
                DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->update($updateData);
                break;

            case 'subapplications':
                $updateData['deeds_status'] = 'registered';
                DB::connection('sqlsrv')->table('subapplications')->where('id', $id)->update($updateData);
                break;
        }
    }

    /**
     * Automatically register ST Fragmentation for approved mother applications
     * This function is called when the Instrument Registration page loads
     */
    private function autoRegisterSTFragmentation()
    {
        try {
            // Get approved mother applications that don't have ST Fragmentation registered yet
            $approvedMotherApplications = DB::connection('sqlsrv')->table('mother_applications as m')
                ->leftJoin('registered_instruments as ri', function ($join) {
                    $join->on('m.fileno', '=', 'ri.MLSFileNo')
                        ->where('ri.instrument_type', '=', 'ST Fragmentation')
                        ->where('ri.status', '=', 'registered');
                })
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved')
                ->whereNull('ri.id') // Only get applications without existing ST Fragmentation
                ->select(
                    'm.id',
                    'm.fileno',
                    'm.np_fileno',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.rc_number',
                    'm.multiple_owners_names',
                    'm.owner_fullname as mother_applicant',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name'
                )
                ->get();

            $registeredCount = 0;

            foreach ($approvedMotherApplications as $motherApp) {
                // Generate STM reference for ST Fragmentation
                $stmReference = $this->generateSTMReference();

                // Build mother applicant name properly for ST Fragmentation Grantee
                $motherApplicantParts = [];
                if (!empty($motherApp->applicant_title))
                    $motherApplicantParts[] = $motherApp->applicant_title;
                if (!empty($motherApp->first_name))
                    $motherApplicantParts[] = $motherApp->first_name;
                if (!empty($motherApp->middle_name))
                    $motherApplicantParts[] = $motherApp->middle_name;
                if (!empty($motherApp->surname))
                    $motherApplicantParts[] = $motherApp->surname;
                if (!empty($motherApp->corporate_name))
                    $motherApplicantParts[] = $motherApp->corporate_name;
                if (!empty($motherApp->rc_number))
                    $motherApplicantParts[] = $motherApp->rc_number;
                if (!empty($motherApp->multiple_owners_names))
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                $motherApplicantName = implode(' ', $motherApplicantParts) ?: ($motherApp->mother_applicant ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($motherApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                if (!empty($motherApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                if (!empty($motherApp->property_street_name))
                    $propertyParts[] = $motherApp->property_street_name;
                if (!empty($motherApp->district))
                    $propertyParts[] = $motherApp->district;
                if (!empty($motherApp->lga))
                    $propertyParts[] = $motherApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                // Prepare registration data for ST Fragmentation
                $registrationData = [
                    'particularsRegistrationNumber' => '0/0/0', // ST Fragmentation always uses 0/0/0
                    'STM_Ref' => $stmReference,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government', // As specified in requirements
                    'Grantee' => $motherApplicantName, // Use properly built mother applicant name
                    'instrumentDate' => now(),
                    'deeds_date' => now(),
                    'deeds_time' => now()->format('H:i:s'),
                    'serial_no' => 0, // ST Fragmentation uses 0
                    'page_no' => 0, // ST Fragmentation uses 0
                    'volume_no' => 0, // ST Fragmentation uses 0
                    'status' => 'registered',
                    'parent_fileNo' => $motherApp->fileno, // fileno from mother_applications table as parent_fileNo
                    'fileNo' => $motherApp->np_fileno ?? $motherApp->fileno, // np_fileno from mother_applications table as fileNo
                    'StFileNo' => $motherApp->np_fileno ?? $motherApp->fileno,
                    'MLSFileNo' => $motherApp->fileno,
                    'lga' => $motherApp->lga ?? '',
                    'district' => $motherApp->district ?? '',
                    'size' => $motherApp->size ?? '',
                    'plotNumber' => $motherApp->plotNumber ?? '',
                    'propertyDescription' => $propertyDescription,
                    'created_by' => Auth::id() ?? 1, // Use current user or system user
                    'updated_by' => Auth::id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Insert into registered_instruments table
                DB::connection('sqlsrv')->table('registered_instruments')->insert($registrationData);
                $registeredCount++;

                Log::info('ST Fragmentation automatically registered', [
                    'application_id' => $motherApp->id,
                    'fileno' => $motherApp->fileno,
                    'np_fileno' => $motherApp->np_fileno ?? 'N/A',
                    'stm_ref' => $stmReference
                ]);
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records', [
                    'registered_count' => $registeredCount
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error auto-registering ST Fragmentation', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Auto-register instruments for approved applications that don't have them yet
     * This handles the case where applications were approved before this feature was added
     */
    private function autoRegisterApprovedInstruments()
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Get approved subapplications that don't have registered instruments yet
            $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('registered_instruments as ri_st', function ($join) {
                    $join->on('s.fileno', '=', 'ri_st.StFileNo')
                        ->where('ri_st.instrument_type', '=', 'ST Assignment (Transfer of Title)')
                        ->where('ri_st.status', '=', 'registered');
                })
                ->leftJoin('registered_instruments as ri_sec', function ($join) {
                    $join->on('s.fileno', '=', 'ri_sec.StFileNo')
                        ->where('ri_sec.instrument_type', '=', 'Sectional Titling CofO')
                        ->where('ri_sec.status', '=', 'registered');
                })
                ->where('s.planning_recommendation_status', 'Approved')
                ->where('s.application_status', 'Approved')
                ->where(function ($query) {
                    $query->whereNull('ri_st.id')
                        ->orWhereNull('ri_sec.id');
                })
                ->select(
                    's.id',
                    's.fileno',
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                        WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                        ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''))
                    END, '  ', ' ')) as sub_applicant"),
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                        WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                        ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                    END, '  ', ' ')) as mother_applicant"),
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.np_fileno',
                    'm.property_house_no',
                    'm.property_street_name',
                    's.main_application_id',
                    'ri_st.id as has_st_assignment',
                    'ri_sec.id as has_sectional_titling'
                )
                ->get();

            // Get approved mother applications that don't have ST Fragmentation registered yet
            $approvedMotherApplications = DB::connection('sqlsrv')->table('mother_applications as m')
                ->leftJoin('registered_instruments as ri', function ($join) {
                    $join->on('m.fileno', '=', 'ri.MLSFileNo')
                        ->where('ri.instrument_type', '=', 'ST Fragmentation')
                        ->where('ri.status', '=', 'registered');
                })
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved')
                ->whereNull('ri.id')

                ->select(
                    'm.id',
                    'm.fileno',
                    'm.np_fileno',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.rc_number',
                    'm.multiple_owners_names',
                    'm.owner_fullname as mother_applicant',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name'
                )
                ->get();

            $registeredCount = 0;

            // Auto-register ST Assignment and Sectional Titling for subapplications
            foreach ($approvedSubapplications as $subApp) {
                $serialData = $this->getNextSerialNumber()->getData(true);

                // Register ST Assignment if not exists
                if (!$subApp->has_st_assignment) {
                    $stmReference = $this->generateSTMReference();

                    // Build property description
                    $propertyParts = [];
                    if (!empty($subApp->property_house_no))
                        $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                    if (!empty($subApp->plotNumber))
                        $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                    if (!empty($subApp->property_street_name))
                        $propertyParts[] = $subApp->property_street_name;
                    if (!empty($subApp->district))
                        $propertyParts[] = $subApp->district;
                    if (!empty($subApp->lga))
                        $propertyParts[] = $subApp->lga;
                    $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                    $stAssignmentData = [
                        'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
                        'STM_Ref' => $stmReference,
                        'instrument_type' => 'ST Assignment (Transfer of Title)',
                        'Grantor' => 'Kano State Government',
                        'Grantee' => $subApp->sub_applicant,
                        'instrumentDate' => now(),
                        'deeds_date' => now(),
                        'deeds_time' => now()->format('H:i:s'),
                        'serial_no' => $serialData['serial_no'],
                        'page_no' => $serialData['page_no'],
                        'volume_no' => $serialData['volume_no'],
                        'status' => 'registered',
                        'StFileNo' => $subApp->fileno,
                        'MLSFileNo' => $subApp->fileno,
                        'lga' => $subApp->lga,
                        'district' => $subApp->district,
                        'size' => $subApp->size,
                        'plotNumber' => $subApp->plotNumber,
                        'propertyDescription' => $propertyDescription,
                        'created_by' => 1, // System user
                        'updated_by' => 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    DB::connection('sqlsrv')->table('registered_instruments')->insert($stAssignmentData);
                    $registeredCount++;

                    // Update next serial number for sectional titling
                    if (++$serialData['page_no'] > 100) {
                        $serialData['volume_no']++;
                        $serialData['page_no'] = 1;
                        $serialData['serial_no'] = 1;
                    } else {
                        $serialData['serial_no']++;
                    }
                    $serialData['deeds_serial_no'] = "{$serialData['serial_no']}/{$serialData['page_no']}/{$serialData['volume_no']}";
                }

                // Register Sectional Titling if not exists
                if (!$subApp->has_sectional_titling) {
                    $stmReference = $this->generateSTMReference();

                    // Build property description
                    $propertyParts = [];
                    if (!empty($subApp->property_house_no))
                        $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                    if (!empty($subApp->plotNumber))
                        $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                    if (!empty($subApp->property_street_name))
                        $propertyParts[] = $subApp->property_street_name;
                    if (!empty($subApp->district))
                        $propertyParts[] = $subApp->district;
                    if (!empty($subApp->lga))
                        $propertyParts[] = $subApp->lga;
                    $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                    $sectionalTitlingData = [
                        'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
                        'STM_Ref' => $stmReference,
                        'instrument_type' => 'Sectional Titling CofO',
                        'Grantor' => 'Kano State Government',
                        'Grantee' => $subApp->sub_applicant,
                        'instrumentDate' => now(),
                        'deeds_date' => now(),
                        'deeds_time' => now()->format('H:i:s'),
                        'serial_no' => $serialData['serial_no'],
                        'page_no' => $serialData['page_no'],
                        'volume_no' => $serialData['volume_no'],
                        'status' => 'registered',
                        'StFileNo' => $subApp->fileno,
                        'MLSFileNo' => $subApp->fileno,
                        'lga' => $subApp->lga,
                        'district' => $subApp->district,
                        'size' => $subApp->size,
                        'plotNumber' => $subApp->plotNumber,
                        'propertyDescription' => $propertyDescription,
                        'created_by' => 1, // System user
                        'updated_by' => 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    DB::connection('sqlsrv')->table('registered_instruments')->insert($sectionalTitlingData);
                    $registeredCount++;
                }

                // Update completion status
                $this->updateInstrumentCompletionStatus($subApp->id, 'ST Assignment (Transfer of Title)', 'Registered');
                $this->updateInstrumentCompletionStatus($subApp->id, 'Sectional Titling CofO', 'Registered');
            }

            // Auto-register ST Fragmentation for mother applications
            foreach ($approvedMotherApplications as $motherApp) {
                $serialData = $this->getNextSerialNumber()->getData(true);
                $stmReference = $this->generateSTMReference();

                // Build mother applicant name
                $motherApplicantParts = [];
                if (!empty($motherApp->applicant_title))
                    $motherApplicantParts[] = $motherApp->applicant_title;
                if (!empty($motherApp->first_name))
                    $motherApplicantParts[] = $motherApp->first_name;
                if (!empty($motherApp->middle_name))
                    $motherApplicantParts[] = $motherApp->middle_name;
                if (!empty($motherApp->surname))
                    $motherApplicantParts[] = $motherApp->surname;
                if (!empty($motherApp->corporate_name))
                    $motherApplicantParts[] = $motherApp->corporate_name;
                if (!empty($motherApp->rc_number))
                    $motherApplicantParts[] = $motherApp->rc_number;
                if (!empty($motherApp->multiple_owners_names))
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                $motherApplicantName = implode(' ', $motherApplicantParts) ?: ($motherApp->mother_applicant ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($motherApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                if (!empty($motherApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                if (!empty($motherApp->property_street_name))
                    $propertyParts[] = $motherApp->property_street_name;
                if (!empty($motherApp->district))
                    $propertyParts[] = $motherApp->district;
                if (!empty($motherApp->lga))
                    $propertyParts[] = $motherApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                $stFragmentationData = [
                    'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
                    'STM_Ref' => $stmReference,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government',
                    'Grantee' => $motherApplicantName,
                    'instrumentDate' => now(),
                    'deeds_date' => now(),
                    'deeds_time' => now()->format('H:i:s'),
                    'serial_no' => $serialData['serial_no'],
                    'page_no' => $serialData['page_no'],
                    'volume_no' => $serialData['volume_no'],
                    'status' => 'registered',
                    'MLSFileNo' => $motherApp->fileno,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'size' => $motherApp->size,
                    'plotNumber' => $motherApp->plotNumber,
                    'propertyDescription' => $propertyDescription,
                    'created_by' => 1, // System user
                    'updated_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                DB::connection('sqlsrv')->table('registered_instruments')->insert($stFragmentationData);
                $registeredCount++;
            }

            DB::connection('sqlsrv')->commit();

            if ($registeredCount > 0) {
                Log::info('Auto-registered instruments for approved applications', [
                    'registered_count' => $registeredCount,
                    'subapplications_processed' => $approvedSubapplications->count(),
                    'mother_applications_processed' => $approvedMotherApplications->count()
                ]);
            }

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Error auto-registering approved instruments', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Initialize the default deeds_completion_status for subapplications that don't have it set
     */
    private function initializeDefaultCompletionStatus()
    {
        try {
            // Get subapplications without deeds_completion_status
            $subapplicationsWithoutStatus = DB::connection('sqlsrv')->table('subapplications')
                ->where(function ($query) {
                    $query->whereNull('deeds_completion_status')
                        ->orWhere('deeds_completion_status', '');
                })
                ->where('planning_recommendation_status', 'Approved')
                ->where('application_status', 'Approved')
                ->get();

            foreach ($subapplicationsWithoutStatus as $subApp) {
                $defaultStatus = [
                    'instruments' => [
                        [
                            'name' => 'ST Assignment (Transfer of Title)',
                            'status' => 'pending'
                        ],
                        [
                            'name' => 'Sectional Titling CofO',
                            'status' => 'pending'
                        ]
                    ]
                ];

                DB::connection('sqlsrv')->table('subapplications')
                    ->where('id', $subApp->id)
                    ->update([
                        'deeds_completion_status' => json_encode($defaultStatus),
                        'updated_at' => now()
                    ]);
            }

            if ($subapplicationsWithoutStatus->count() > 0) {
                Log::info('Initialized default completion status', [
                    'count' => $subapplicationsWithoutStatus->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error initializing default completion status', [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create missing ST Fragmentation records for already approved applications
     * This is a one-time migration to handle applications approved before this feature
     */
    private function createMissingSTFragmentationRecords()
    {
        try {
            // Get approved mother applications that don't have ST Fragmentation registered yet
            $approvedMotherApplications = DB::connection('sqlsrv')->table('mother_applications as m')
                ->leftJoin('registered_instruments as ri', function ($join) {
                    $join->on('m.fileno', '=', 'ri.MLSFileNo')
                        ->where('ri.instrument_type', '=', 'ST Fragmentation')
                        ->where('ri.status', '=', 'registered');
                })
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved')
                ->whereNull('ri.id')
                ->select(
                    'm.id',
                    'm.fileno',
                    'm.np_fileno',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.rc_number',
                    'm.multiple_owners_names',
                    'm.owner_fullname as mother_applicant',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.approval_date'
                )
                ->get();

            if ($approvedMotherApplications->isEmpty()) {
                return; // No missing records to create
            }

            $registeredCount = 0;

            foreach ($approvedMotherApplications as $motherApp) {
                // Get next serial number
                $latest = DB::connection('sqlsrv')->table('registered_instruments')
                    ->select('volume_no', 'page_no', 'serial_no')
                    ->orderBy('volume_no', 'desc')
                    ->orderBy('page_no', 'desc')
                    ->first();

                if (!$latest) {
                    $serialData = [
                        'serial_no' => 1,
                        'page_no' => 1,
                        'volume_no' => 1,
                        'deeds_serial_no' => '1/1/1'
                    ];
                } else {
                    $volumeNo = $latest->volume_no;
                    $pageNo = $latest->page_no;
                    $serialNo = $latest->serial_no;

                    if ($pageNo >= 100) {
                        $volumeNo++;
                        $pageNo = 1;
                        $serialNo = 1;
                    } else {
                        $pageNo++;
                        $serialNo++;
                    }

                    $serialData = [
                        'serial_no' => $serialNo,
                        'page_no' => $pageNo,
                        'volume_no' => $volumeNo,
                        'deeds_serial_no' => "$serialNo/$pageNo/$volumeNo"
                    ];
                }

                $stmReference = $this->generateSTMReference();

                // Build mother applicant name properly for ST Fragmentation Grantee
                $motherApplicantParts = [];
                if (!empty($motherApp->applicant_title))
                    $motherApplicantParts[] = $motherApp->applicant_title;
                if (!empty($motherApp->first_name))
                    $motherApplicantParts[] = $motherApp->first_name;
                if (!empty($motherApp->middle_name))
                    $motherApplicantParts[] = $motherApp->middle_name;
                if (!empty($motherApp->surname))
                    $motherApplicantParts[] = $motherApp->surname;
                if (!empty($motherApp->corporate_name))
                    $motherApplicantParts[] = $motherApp->corporate_name;
                if (!empty($motherApp->rc_number))
                    $motherApplicantParts[] = $motherApp->rc_number;
                if (!empty($motherApp->multiple_owners_names))
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                $motherApplicantName = implode(' ', $motherApplicantParts) ?: ($motherApp->mother_applicant ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($motherApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                if (!empty($motherApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                if (!empty($motherApp->property_street_name))
                    $propertyParts[] = $motherApp->property_street_name;
                if (!empty($motherApp->district))
                    $propertyParts[] = $motherApp->district;
                if (!empty($motherApp->lga))
                    $propertyParts[] = $motherApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                $stFragmentationData = [
                    'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
                    'STM_Ref' => $stmReference,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government',
                    'Grantee' => $motherApplicantName,
                    'instrumentDate' => $motherApp->approval_date ?? now(),
                    'deeds_date' => $motherApp->approval_date ?? now(),
                    'deeds_time' => now()->format('H:i:s'),
                    'serial_no' => $serialData['serial_no'],
                    'page_no' => $serialData['page_no'],
                    'volume_no' => $serialData['volume_no'],
                    'status' => 'registered',
                    'MLSFileNo' => $motherApp->fileno,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'size' => $motherApp->size,
                    'plotNumber' => $motherApp->plotNumber,
                    'propertyDescription' => $propertyDescription,
                    'created_by' => 1, // System user
                    'updated_by' => 1,
                    'created_at' => $motherApp->approval_date ?? now(),
                    'updated_at' => now()
                ];

                DB::connection('sqlsrv')->table('registered_instruments')->insert($stFragmentationData);
                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Created missing ST Fragmentation records', [
                    'created_count' => $registeredCount
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error creating missing ST Fragmentation records', [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update the deeds_completion_status JSON field for a specific instrument type
     */
    private function updateInstrumentCompletionStatus($subApplicationId, $instrumentType, $status = 'Registered')
    {
        try {
            // Get current completion status
            $currentRecord = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $subApplicationId)
                ->select('deeds_completion_status')
                ->first();

            // Initialize or parse existing JSON
            $completionStatus = [
                'instruments' => [
                    [
                        'name' => 'ST Assignment (Transfer of Title)',
                        'status' => 'Pending'
                    ],
                    [
                        'name' => 'Sectional Titling CofO',
                        'status' => 'Pending'
                    ]
                ]
            ];

            if ($currentRecord && !empty($currentRecord->deeds_completion_status)) {
                $existingStatus = json_decode($currentRecord->deeds_completion_status, true);
                if ($existingStatus && isset($existingStatus['instruments'])) {
                    $completionStatus = $existingStatus;
                }
            }

            // Update the specific instrument status
            foreach ($completionStatus['instruments'] as &$instrument) {
                if ($instrument['name'] === $instrumentType) {
                    $instrument['status'] = $status;
                    break;
                }
            }

            // Update the database
            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $subApplicationId)
                ->update([
                    'deeds_completion_status' => json_encode($completionStatus),
                    'updated_at' => now(),
                    'updated_by' => Auth::id()
                ]);

            Log::info('Updated instrument completion status', [
                'subapplication_id' => $subApplicationId,
                'instrument_type' => $instrumentType,
                'status' => $status,
                'completion_status' => $completionStatus
            ]);

            return $completionStatus;

        } catch (\Exception $e) {
            Log::error('Error updating instrument completion status', [
                'subapplication_id' => $subApplicationId,
                'instrument_type' => $instrumentType,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Helper to compare names to determine if it is the same owner
     */
    private function isSameOwner($name1, $name2)
    {
        if (empty($name1) || empty($name2))
            return false;

        $n1 = strtoupper(preg_replace('/\s+/', ' ', trim($name1)));
        $n2 = strtoupper(preg_replace('/\s+/', ' ', trim($name2)));

        return $n1 === $n2;
    }

    /**
     * Automatically register Express ST Assignments for units owned by the same mother property owner
     */
    private function autoRegisterExpressSTAssignments()
    {
        try {
            // Get subapplications that are approved but ST Assignment is not registered
            $subApps = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('registered_instruments as ri', function ($join) {
                    $join->on('s.fileno', '=', 'ri.StFileNo')
                        ->where('ri.instrument_type', '=', 'ST Assignment (Transfer of Title)')
                        ->where('ri.status', '=', 'registered');
                })
                ->where('s.planning_recommendation_status', 'Approved')
                ->where('s.application_status', 'Approved')
                ->whereNull('ri.id')
                ->select(
                    's.id',
                    's.fileno',
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                        WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                        ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''))
                    END, '  ', ' ')) as sub_applicant"),
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                        WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                        ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                    END, '  ', ' ')) as mother_applicant"),
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name'
                )
                ->get();

            $registeredCount = 0;

            foreach ($subApps as $subApp) {
                // Express if names match OR if ST Fragmentation is already registered for this file
                $hasFragRegistered = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $subApp->fileno)
                    ->where('instrument_type', 'ST Fragmentation')
                    ->where('status', 'registered')
                    ->exists();

                if ($this->isSameOwner($subApp->mother_applicant, $subApp->sub_applicant) || $hasFragRegistered) {
                    $stmReference = $this->generateSTMReference();

                    // Build property description
                    $propertyParts = [];
                    if (!empty($subApp->property_house_no))
                        $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                    if (!empty($subApp->plotNumber))
                        $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                    if (!empty($subApp->property_street_name))
                        $propertyParts[] = $subApp->property_street_name;
                    if (!empty($subApp->district))
                        $propertyParts[] = $subApp->district;
                    if (!empty($subApp->lga))
                        $propertyParts[] = $subApp->lga;
                    $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                    $registrationData = [
                        'particularsRegistrationNumber' => '0/0/0', // Express Assignment also uses 0/0/0
                        'STM_Ref' => $stmReference,
                        'instrument_type' => 'ST Assignment (Transfer of Title)',
                        'Grantor' => $subApp->mother_applicant,
                        'Grantee' => $subApp->sub_applicant,
                        'instrumentDate' => now(),
                        'deeds_date' => now(),
                        'deeds_time' => now()->format('H:i:s'),
                        'serial_no' => 0,
                        'page_no' => 0,
                        'volume_no' => 0,
                        'status' => 'registered',
                        'StFileNo' => $subApp->fileno,
                        'MLSFileNo' => $subApp->fileno,
                        'lga' => $subApp->lga ?? '',
                        'district' => $subApp->district ?? '',
                        'size' => $subApp->size ?? '',
                        'plotNumber' => $subApp->plotNumber ?? '',
                        'propertyDescription' => $propertyDescription,
                        'created_by' => Auth::id() ?? 1,
                        'updated_by' => Auth::id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    DB::connection('sqlsrv')->table('registered_instruments')->insert($registrationData);

                    // Update completion status to Registered
                    $this->updateInstrumentCompletionStatus($subApp->id, 'ST Assignment (Transfer of Title)', 'Registered');

                    // Check if both types are registered
                    $this->checkBothTypesRegistered($subApp->fileno);

                    $registeredCount++;

                    Log::info('Express ST Assignment automatically registered', [
                        'subapp_id' => $subApp->id,
                        'fileno' => $subApp->fileno,
                        'stm_ref' => $stmReference
                    ]);
                }
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered express ST Assignment records', ['count' => $registeredCount]);
            }

        } catch (\Exception $e) {
            Log::error('Error auto-registering express ST Assignments', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function mapCaptureTypeToRegistry($captureType)
    {
        $mapping = [
            'Power of Attorney' => 'Power of Attorney',
            'Irrevocable Power of Attorney' => 'Power of Attorney',
            'Deed of Mortgage' => 'Deed of Mortgage',
            'Tripartite Mortgage' => 'Deed of Mortgage',
            'Deed of Assignment' => 'Deed of Assignment',
            'Deed of Lease' => 'Deed of Lease',
            'Deed of Sub-Lease' => 'Sub Lease',
            'Deed of Surrender / Release' => 'Deed of Surrender and Release',
            'Occupancy Permit' => 'Occupancy Permit (OP)',
            'Devolution Order' => 'Devolution Order',
            'Deed of Gift' => 'Deed of Gift',
            'Deed of Sub-Division' => 'Deed of Sub-Division',
            'Deed of Merger' => 'Deed of Merger',
        ];

        return $mapping[$captureType] ?? $captureType;
    }
}

