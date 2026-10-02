<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;

class MemoController extends Controller
{
    private function parseOwnerNameEntries($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $decoded = preg_split('/[\r\n,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (!is_array($decoded)) {
            return [];
        }

        $names = [];
        foreach ($decoded as $entry) {
            if (is_string($entry)) {
                $name = trim($entry);
                if ($name !== '') {
                    $names[] = $name;
                }
                continue;
            }

            if (is_array($entry)) {
                foreach (['full_name', 'fullName', 'name', 'owner_name'] as $key) {
                    if (!empty($entry[$key])) {
                        $name = trim((string) $entry[$key]);
                        if ($name !== '') {
                            $names[] = $name;
                        }
                        break;
                    }
                }
                continue;
            }

            if (is_object($entry)) {
                foreach (['full_name', 'fullName', 'name', 'owner_name'] as $key) {
                    if (!empty($entry->{$key})) {
                        $name = trim((string) $entry->{$key});
                        if ($name !== '') {
                            $names[] = $name;
                        }
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function buildOwnerNameData($record): array
    {
        $applicantType = strtolower(trim($record->applicant_type ?? ''));

        $names = $this->parseOwnerNameEntries($record->multiple_owners_names ?? null);

        $structuredOwners = $record->multiple_owners_data ?? null;
        if ($structuredOwners) {
            if (is_string($structuredOwners)) {
                $decoded = json_decode($structuredOwners, true);
            } else {
                $decoded = $structuredOwners;
            }

            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    if (is_array($entry)) {
                        foreach (['full_name', 'fullName', 'name', 'owner_name', 'ownerName'] as $key) {
                            if (!empty($entry[$key])) {
                                $candidate = trim((string) $entry[$key]);
                                if ($candidate !== '') {
                                    $names[] = $candidate;
                                }
                                break;
                            }
                        }
                    } elseif (is_string($entry)) {
                        $candidate = trim($entry);
                        if ($candidate !== '') {
                            $names[] = $candidate;
                        }
                    }
                }
            }
        }

        if (empty($names)) {
            $corporateName = trim((string) ($record->corporate_name ?? ''));
            if ($corporateName !== '' && (Str::contains($applicantType, 'corporate') || $applicantType === '')) {
                $names[] = $corporateName;
            }
        }

        if (empty($names)) {
            $personalName = trim(implode(' ', array_filter([
                $record->applicant_title ?? null,
                $record->first_name ?? null,
                $record->middle_name ?? null,
                $record->surname ?? null,
            ])));

            if ($personalName !== '') {
                $names[] = $personalName;
            }
        }

        if (empty($names)) {
            $fallbackFields = [
                'owner_name',
                'unit_owner',
                'unit_owner_name',
                'unit_owner_fullname',
                'unit_owner_full_name',
                'unit_owner_title',
                'applicant_name',
                'memo_applicant_name',
            ];

            foreach ($fallbackFields as $field) {
                if (!empty($record->{$field})) {
                    $candidate = trim((string) $record->{$field});
                    if ($candidate !== '') {
                        $names[] = $candidate;
                        break;
                    }
                }
            }
        }

        if (empty($names)) {
            $compositeSets = [
                ['unit_owner_title', 'unit_owner_first_name', 'unit_owner_middle_name', 'unit_owner_surname'],
                ['owner_title', 'owner_first_name', 'owner_middle_name', 'owner_surname'],
                ['unit_owner_title', 'unit_owner'],
            ];

            foreach ($compositeSets as $fields) {
                $parts = [];
                foreach ($fields as $field) {
                    if (!empty($record->{$field})) {
                        $parts[] = trim((string) $record->{$field});
                    }
                }

                if (!empty($parts)) {
                    $candidate = trim(implode(' ', $parts));
                    if ($candidate !== '') {
                        $names[] = $candidate;
                        break;
                    }
                }
            }
        }

        if (!empty($names)) {
            $names = array_values(array_unique($names));
        }

        return [
            'names' => $names,
            'display' => count($names) ? implode(', ', $names) : null,
        ];
    }

    private function resolvePropertyLocation($primaryRecord, $fallbackRecord = null): ?string
    {
        foreach ([$primaryRecord, $fallbackRecord] as $record) {
            if (!$record) {
                continue;
            }

            if (isset($record->property_location)) {
                $candidate = trim((string) $record->property_location);
                if ($candidate !== '') {
                    return trim(preg_replace('/\s+/', ' ', $candidate));
                }
            }
        }

        $target = $fallbackRecord ?? $primaryRecord;

        if (!$target) {
            return null;
        }

        $parts = [];

        $labeledParts = [
            'block_number' => 'Block %s',
            'floor_number' => 'Floor %s',
            'unit_number' => 'Unit %s',
        ];

        foreach ($labeledParts as $property => $label) {
            if (!empty($target->{$property})) {
                $parts[] = sprintf($label, trim((string) $target->{$property}));
            }
        }

        $plainParts = [
            'unit_district',
            'unit_lga',
            'unit_state',
            'mother_lga',
            'property_district',
            'property_lga',
        ];

        foreach ($plainParts as $property) {
            if (!empty($target->{$property})) {
                $parts[] = trim((string) $target->{$property});
            }
        }

        $parts = array_values(array_unique(array_filter(array_map(static function ($value) {
            return trim(preg_replace('/\s+/', ' ', (string) $value));
        }, $parts), static function ($value) {
            return $value !== '';
        })));

        if (empty($parts)) {
            return null;
        }

        return implode(', ', $parts);
    }

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
 
    //Memo
    public function Memo()
    {
        $PageTitle = 'ST Memo';
        $PageDescription = 'Sectional Titling Memo';

        // Select from mother_applications table

        $motherApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'id',
                
                'fileno',
                'applicant_title',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'planning_approval_date',
                
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();
             // Process owner names for mother applications
        foreach ($motherApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        return view('programmes.memo', compact('motherApplications', 'PageTitle', 'PageDescription'));
    } 

    //Unit Scheme Memo
    public function UnitSchemeMemo()
    {
        $PageTitle = 'Unit (Scheme) ST Memo';
        $PageDescription = 'Sectional Titling Memo for Unit Applications';

        // Fetch unit applications (subapplications) with their primary application details
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where(function ($query) {
                $query->whereNull('subapplications.unit_type')
                    ->orWhereRaw('LOWER(subapplications.unit_type) != ?', ['sua']);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('memos')
                    ->whereColumn('memos.application_id', 'subapplications.main_application_id')
                    ->where('memos.memo_type', 'primary');
            })
            ->select(
                'subapplications.id',
                'subapplications.scheme_no',
                'subapplications.main_application_id',
                'subapplications.fileno',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.applicant_type',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'subapplications.application_status',
                'subapplications.planning_recommendation_status',
                'subapplications.planning_approval_date',
                'subapplications.approval_date',
                'subapplications.created_at',
                'subapplications.unit_type',
                'mother_applications.property_lga',
                'mother_applications.land_use',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.np_fileno as primary_np_fileno'
            )
            ->get();

        $uploadTableExists = Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads');

        // Process owner names for unit applications
        foreach ($unitApplications as $application) {
            $ownerData = $this->buildOwnerNameData($application);
            $application->owner_names_list = $ownerData['names'];
            $application->owner_name = $ownerData['display'] ?? 'N/A';

            // Primary memo existence already ensured by query filter
            $application->has_st_memo = true;

            // Check if unit-specific memo is uploaded
            if ($uploadTableExists) {
                $application->has_unit_memo_upload = DB::connection('sqlsrv')->table('unit_st_memo_uploads')
                    ->where('unit_application_id', $application->id)
                    ->exists();
            } else {
                $application->has_unit_memo_upload = false;
            }
        }

        return view('programmes.unit_scheme_memo', compact('unitApplications', 'PageTitle', 'PageDescription', 'uploadTableExists'));
    }

    // View Unit ST Memo
    public function viewUnitSTMemo($unitId)
    {
        if (!Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
            return response()->json(['error' => 'Unit memo upload storage is not initialized. Please run the latest migrations.'], 503);
        }

        $memoUpload = DB::connection('sqlsrv')->table('unit_st_memo_uploads')
            ->where('unit_application_id', $unitId)
            ->orderBy('uploaded_at', 'desc')
            ->first();

        if (!$memoUpload) {
            return response()->json(['error' => 'No ST Memo found for this unit'], 404);
        }

        $filePath = storage_path('app/public/' . $memoUpload->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Memo file not found'], 404);
        }

        return response()->file($filePath);
    }

    //Unit Non-Scheme Memo (SUA)
    public function UnitNonSchemeMemo()
    {
        $PageTitle = 'Unit (Non-Scheme) ST Memo';
        $PageDescription = 'Sectional Titling Memo for SUA Unit Applications';

        // Fetch SUA unit applications (subapplications with unit_type = 'sua') 
        $suaApplications = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.unit_type', 'sua')
            ->select(
                'subapplications.id',
                'subapplications.scheme_no',
                'subapplications.main_application_id',
                'subapplications.fileno',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'subapplications.unit_size',
                'subapplications.application_status',
                'subapplications.planning_recommendation_status',
                'subapplications.planning_approval_date',
                'subapplications.planning_recomm_comments',
                'subapplications.approval_date',
                'subapplications.created_at',
                'subapplications.unit_type',
                'subapplications.unit_lga',
                'subapplications.land_use',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.np_fileno as primary_np_fileno'
            )
            ->get();

        $unitIds = $suaApplications->pluck('id')->filter()->all();
        $memoRecords = collect();
        if (!empty($unitIds)) {
            $memoRecords = DB::connection('sqlsrv')->table('memos')
                ->where('memo_type', 'SUA')
                ->whereIn('unit_id', $unitIds)
                ->get()
                ->keyBy('unit_id');
        }

        $pendingApplications = [];
        $generatedApplications = [];

        foreach ($suaApplications as $application) {
            $ownerData = $this->buildOwnerNameData($application);
            $application->owner_names_list = $ownerData['names'];
            $application->owner_name = $ownerData['display'] ?? 'N/A';

            $memoRecord = $memoRecords->get($application->id);
            $hasMemo = $memoRecord !== null;
            $application->has_st_memo = $hasMemo;
            $application->memo_record = $memoRecord;

            $directorStatus = strtolower(trim((string) ($application->application_status ?? '')));
            $planningStatus = strtolower(trim((string) ($application->planning_recommendation_status ?? '')));
            $planningApprovalDate = $application->planning_approval_date;

            $directorApproved = $directorStatus === 'approved';
            $recommendationApproved = $planningStatus === 'approved';

            $application->prerequisites = [
                'director' => [
                    'label' => "ST Director's Approval",
                    'met' => $directorApproved,
                    'status' => $application->application_status ?? 'Pending',
                    'date' => $application->approval_date,
                ],
                'planning_recommendation' => [
                    'label' => 'Planning Recommendation Approval',
                    'met' => $recommendationApproved,
                    'status' => $application->planning_recommendation_status ?? 'Pending',
                    'date' => $planningApprovalDate,
                    'comments' => $application->planning_recomm_comments ?? null,
                ],
            ];

            $missingPrerequisites = [];
            if (!$directorApproved) {
                $missingPrerequisites[] = "ST Director's Approval";
            }
            if (!$recommendationApproved) {
                $missingPrerequisites[] = 'Planning Recommendation Approval';
            }

            $application->missing_prerequisites = $missingPrerequisites;
            $application->can_generate_memo = !$hasMemo && $directorApproved && $recommendationApproved;

            if ($hasMemo) {
                $application->status_label = 'Generated';
                $application->status_style = 'bg-green-100 text-green-700';
                $generatedApplications[] = $application;
            } elseif ($application->can_generate_memo) {
                $application->status_label = 'Ready';
                $application->status_style = 'bg-blue-100 text-blue-700';
                $pendingApplications[] = $application;
            } else {
                $application->status_label = 'Pending';
                $application->status_style = 'bg-amber-100 text-amber-700';
                $pendingApplications[] = $application;
            }
        }

        $totalUnits = $suaApplications->count();
        $pendingCount = count($pendingApplications);
        $generatedCount = count($generatedApplications);

        return view('programmes.unit_nonscheme_memo', compact(
            'pendingApplications',
            'generatedApplications',
            'PageTitle',
            'PageDescription',
            'totalUnits',
            'pendingCount',
            'generatedCount'
        ));
    }

    // Show Generate SUA Memo Form
    public function generateSUAMemoForm($unitId)
    {
        $PageTitle = 'Generate SUA Memo';
        $PageDescription = 'Generate Sectional Titling Memo for SUA Unit Application';

        // Get SUA application details
        $suaApplication = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $unitId)
            ->where('subapplications.unit_type', 'sua')
            ->select(
                'subapplications.*',
                'mother_applications.property_lga as mother_lga',
                'mother_applications.land_use as mother_land_use',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.np_fileno as primary_np_fileno'
            )
            ->first();

        if (!$suaApplication) {
            return redirect()->route('programmes.unit_nonscheme_memo')->with('error', 'SUA application not found');
        }

        // Check if memo already exists
        $existingMemo = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $unitId)
            ->where('memo_type', 'SUA')
            ->first();
            
        if ($existingMemo) {
            return redirect()->route('programmes.view_sua_memo', $unitId)->with('info', 'SUA memo already exists for this application');
        }

        // Process owner name(s)
        $ownerData = $this->buildOwnerNameData($suaApplication);
        $ownerNames = $ownerData['names'];
        $ownerName = $ownerData['display'] ?? '';

        if ($ownerName === '' && count($ownerNames) > 0) {
            $ownerName = $ownerNames[0];
        }

        if ($ownerName === '' && !empty($suaApplication->owner_name)) {
            $ownerName = trim((string) $suaApplication->owner_name);
            if ($ownerName !== '') {
                $ownerNames = [$ownerName];
            }
        }

        if ($ownerName === '' && !empty($suaApplication->unit_owner)) {
            $ownerName = trim((string) $suaApplication->unit_owner);
            if ($ownerName !== '') {
                $ownerNames = [$ownerName];
            }
        }

        if ($ownerName === '') {
            $ownerName = trim(
                ($suaApplication->applicant_title ?? '') . ' ' .
                ($suaApplication->first_name ?? '') . ' ' .
                ($suaApplication->middle_name ?? '') . ' ' .
                ($suaApplication->surname ?? '')
            );

            if ($ownerName !== '') {
                $ownerNames = [$ownerName];
            }
        }

        if (empty($ownerNames) && $ownerName !== '') {
            $ownerNames = [$ownerName];
        }

        $propertyLocation = $this->resolvePropertyLocation($suaApplication);

        // Auto-generate numbers for preview
        $memoNumber = $this->generateMemoNumber();
        $certificateNumber = $this->generateCertificateNumber();

        return view('programmes.generate_sua_memo', compact(
            'suaApplication', 
            'PageTitle', 
            'PageDescription', 
            'ownerName', 
            'ownerNames',
            'propertyLocation',
            'memoNumber', 
            'certificateNumber'
        ));
    }

    // Generate SUA Memo
    public function generateSUAMemo(Request $request)
    {
        $request->validate([
            'application_id' => 'required|integer',
            'memo_type' => 'required|string',
            'memo_no' => 'required|string',
            'page_no' => 'nullable|string',
            'site_plan_no' => 'required|string',
            'arc_design_page_no' => 'nullable|string',
            'certificate_number' => 'nullable|string',
            'allocation_ref_no' => 'required|string|max:255',
            'applicant_name' => 'required|string',
            'property_location' => 'nullable|string',
            'commencement_date' => 'nullable|date',
            'term_years' => 'nullable|integer',
            'residual_years' => 'nullable|integer',
            'expiry_date' => 'nullable|date',
            'planner_recommendation' => 'nullable|string',
            'is_planning_recommended' => 'nullable|boolean'
        ]);

        try {
            $unitId = $request->application_id; // This is the subapplication ID
            
            // Get the subapplication
            $subApplication = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $unitId)
                ->first();
                
            if (!$subApplication) {
                return redirect()->back()->with('error', 'SUA application not found');
            }
            
            // Check if memo already exists for this unit
            $existingMemo = DB::connection('sqlsrv')->table('memos')
                ->where('memo_type', 'SUA')
                ->where('unit_id', $unitId) // Look for memo specific to this unit
                ->first();
                
            if ($existingMemo) {
                return redirect()->route('programmes.view_sua_memo', $unitId)->with('info', 'SUA memo already exists for this unit application');
            }

            // Filter out non-database fields like _token and _method
            $data = $request->except(['_token', '_method']);

            if (isset($data['allocation_ref_no'])) {
                $data['allocation_ref_no'] = strtoupper(trim((string) $data['allocation_ref_no']));
            }
            
            if (isset($data['site_plan_no'])) {
                $data['site_plan_no'] = trim((string) $data['site_plan_no']);
            }
            
            $data['property_location'] = trim((string) ($data['property_location'] ?? ''));
            if ($data['property_location'] === '') {
                $resolvedLocation = $this->resolvePropertyLocation($subApplication);
                if ($resolvedLocation !== null) {
                    $data['property_location'] = $resolvedLocation;
                } else {
                    unset($data['property_location']);
                }
            }

            // For SUA applications, set application_id to NULL since they are standalone
            // The foreign key constraint allows NULL values
            $data['application_id'] = null;
            
            $data['unit_id'] = $unitId; // Store the unit ID for reference
            
            // Add created_by field
            $data['created_by'] = Auth::id();
            $data['created_at'] = now();
            $data['updated_at'] = now();
            
            // Insert memo record
            DB::connection('sqlsrv')->table('memos')->insert($data);

            return redirect()->route('programmes.view_sua_memo', $unitId)->with('success', 'SUA Memo generated successfully');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to generate memo: ' . $e->getMessage());
        }
    }

    // View SUA Memo
    public function viewSUAMemo($unitId)
    {
        // For SUA applications, find memo by unit_id since application_id is NULL
        $memo = DB::connection('sqlsrv')->table('memos')
            ->where('memo_type', 'SUA')
            ->where('unit_id', $unitId) // Look for memo specific to this unit
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$memo) {
            return redirect()->back()->with('error', 'No SUA Memo found for this unit application');
        }

        // Get SUA application details
        $suaApplication = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $unitId)
            ->select(
                'subapplications.*',
                'mother_applications.property_lga as mother_lga',
                'mother_applications.land_use as mother_land_use',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.np_fileno as primary_np_fileno'
            )
            ->first();

        if (!$suaApplication) {
            return redirect()->back()->with('error', 'SUA application not found');
        }

        $ownerData = $this->buildOwnerNameData($suaApplication);
        $ownerName = $ownerData['display'] ?? '';
        $ownerNamesList = $ownerData['names'];

        if ($ownerName === '' && count($ownerNamesList) > 0) {
            $ownerName = $ownerNamesList[0];
        }

        // Add processed owner name(s) to memo object
        $memo->owner_name = $ownerName;
        $memo->owner_names_list = $ownerNamesList;
        $memo->property_lga = $suaApplication->unit_lga ?? $suaApplication->mother_lga ?? 'N/A';
        $memo->land_use = $suaApplication->land_use ?? $suaApplication->mother_land_use ?? 'Commercial';
        $memo->property_location = $this->resolvePropertyLocation($memo, $suaApplication) ?? $memo->property_lga;

        // Create buyers list for single SUA unit
        $buyersList = [];

        if (!empty($ownerNamesList)) {
            foreach ($ownerNamesList as $index => $name) {
                $buyersList[] = (object) [
                    'buyer_name' => $name,
                    'unit_no' => $suaApplication->unit_number ?? 'N/A',
                    'measurement' => $suaApplication->unit_size ?? 'N/A',
                    'owner_name' => $name,
                    'sequence' => $index + 1,
                ];
            }
        } else {
            $buyersList[] = (object) [
                'buyer_name' => $ownerName !== '' ? $ownerName : null,
                'unit_no' => $suaApplication->unit_number ?? 'N/A',
                'measurement' => $suaApplication->unit_size ?? 'N/A',
                'owner_name' => $ownerName !== '' ? $ownerName : null,
                'sequence' => 1,
            ];
        }

        // Calculate term years (default 40 for commercial, 25 for residential)
        $totalYears = ($memo->land_use === 'Residential') ? 25 : 40;
        $residualYears = $totalYears; // Assume full term for new applications

        // Fetch shared utilities data for this SUA application (single record)
        $sharedUtility = DB::connection('sqlsrv')->table('shared_utilities')
            ->where('sub_application_id', $unitId)
            ->select('utility_type', 'dimension')
            ->first();

        return view('programmes.sua_memo_template_new', compact(
            'memo', 
            'suaApplication', 
            'buyersList', 
            'totalYears', 
            'residualYears',
            'ownerNamesList',
            'sharedUtility'
        ));
    }
    
    private function generateCertificateNumber()
    {
        $currentYear = date('Y');
        $prefix = 'COM'; // Prefix for commercial properties
        
        // Get the highest number for the current year
        $lastMemo = DB::connection('sqlsrv')->table('memos')
            ->where('certificate_number', 'like', $prefix . '/' . $currentYear . '/%')
            ->orderByRaw('LEN(certificate_number) DESC, certificate_number DESC')
            ->first();
        
        if ($lastMemo) {
            // Extract the numeric part and increment
            $parts = explode('/', $lastMemo->certificate_number);
            $lastNumber = (int)end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            // First record for this year
            $newNumber = 1;
        }
        
        // Format with leading zeros (4 digits)
        return $prefix . '/' . $currentYear . '/' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    private function generateMemoNumber()
    {
        $currentYear = date('Y');
        $prefix = 'Memo';
        
        // Get the highest number for the current year
        $lastMemo = DB::connection('sqlsrv')->table('memos')
            ->where('memo_no', 'like', $prefix . '/' . $currentYear . '/%')
            ->orderByRaw('LEN(memo_no) DESC, memo_no DESC')
            ->first();
        
        if ($lastMemo) {
            // Extract the numeric part and increment
            $parts = explode('/', $lastMemo->memo_no);
            $lastNumber = (int)end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            // First record for this year
            $newNumber = 1;
        }
        
        // Format with leading zeros (2 digits)
        return $prefix . '/' . $currentYear . '/' . str_pad($newNumber, 2, '0', STR_PAD_LEFT);
    }
    
    // Generate memo form
    public function generateMemo($id)
    {
       
        $PageTitle = request()->query('edit') === 'yes' ? 'Edit Memo' : 'Generate Memo';
        $PageDescription = '';
 
        
        // Fetch the mother application data
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $id)
            ->first();
            
        if (!$application) {
            return back()->with('error', 'Application not found');
        }
        
        // Fetch land administration data if it exists
        $landAdmin = DB::connection('sqlsrv')->table('landAdministration')
            ->where('application_id', $id)
            ->first();
        
        // Process owner name
        if (!empty($application->multiple_owners_names)) {
            $ownerArray = json_decode($application->multiple_owners_names, true);
            $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
        } elseif (!empty($application->corporate_name)) {
            $application->owner_name = $application->corporate_name;
        } else {
            $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->middle_name . ' ' . $application->surname);
        }
        
        // Calculate default residual years
        $startDate = \Carbon\Carbon::parse($application->approval_date ?? now());
        $totalYears = 40; // Default value
        $currentYear = now()->year;
        $elapsedYears = $currentYear - $startDate->year;
        $residualYears = max(0, $totalYears - $elapsedYears);
        $expiryDate = $startDate->copy()->addYears($totalYears);
        
        // Check if a memo already exists
        $existingMemo = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $id)
            ->where('memo_type', 'primary')
            ->first();
        
        $certificateNumber = $existingMemo->certificate_number ?? null;

        $cofoNumber = null;
        $cofoTableCandidates = ['CofO', 'cofo'];
        foreach ($cofoTableCandidates as $tableCandidate) {
            if (Schema::connection('sqlsrv')->hasTable($tableCandidate)) {
                $cofoNumber = DB::connection('sqlsrv')
                    ->table($tableCandidate)
                    ->where('application_id', $id)
                    ->orderByDesc('id')
                    ->value('cofo_no');
                if ($cofoNumber !== null && trim((string) $cofoNumber) !== '') {
                    break;
                }
            }
        }

        if ($cofoNumber !== null && trim((string) $cofoNumber) !== '') {
            $certificateNumber = $cofoNumber;
        }

        if ($certificateNumber === null || trim((string) $certificateNumber) === '') {
            $certificateNumber = 'N/A';
        }

        $certificateLabel = 'Extant CofO No';
        $certificateFieldValue = $certificateNumber;

        if (strtoupper(trim((string) $certificateNumber)) === 'N/A') {
            $certificateLabel = 'Old Statutory File No';
            $certificateFieldValue = trim((string) ($application->fileno ?? '')) !== ''
                ? $application->fileno
                : 'N/A';
        }
        
        // Generate a memo number if creating a new memo
        $memoNumber = $existingMemo ? $existingMemo->memo_no : $this->generateMemoNumber();
            
        return view('programmes.generate_memo', compact(
            'application',
            'landAdmin',
            'totalYears',
            'residualYears',
            'expiryDate',
            'existingMemo',
            'PageTitle',
            'PageDescription',
            'certificateNumber',
            'certificateLabel',
            'certificateFieldValue',
            'memoNumber'
        ));
    }
    
    // Save memo data
    public function saveMemo(Request $request)
    {
        $request->validate([
            'application_id' => 'required|integer',
            'memo_type' => 'required|string',
            'page_no' => 'nullable|string',
            'site_plan_no' => 'required|string',
            'arc_design_page_no' => 'nullable|string',
            'certificate_number' => 'nullable|string',
            'applicant_name' => 'required|string',
            'property_location' => 'nullable|string',
            'commencement_date' => 'nullable|date',
            'term_years' => 'nullable|integer',
            'residual_years' => 'nullable|integer',
            'expiry_date' => 'nullable|date',
            'planner_recommendation' => 'nullable|string',
            'is_planning_recommended' => 'nullable|boolean'
        ]);
        
        // Filter out non-database fields like _token and _method
        $data = $request->except(['_token', '_method']);

        if (isset($data['site_plan_no'])) {
            $data['site_plan_no'] = trim((string) $data['site_plan_no']);
        }
        
        // Add created_by field
        $data['created_by'] = Auth::id();
        
        // Check if record exists, update if it does
        $existingMemo = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $request->application_id)
            ->where('memo_type', $request->memo_type)
            ->first();
            
        if ($existingMemo) {
            // Update existing record
            $data['updated_at'] = now();
            DB::connection('sqlsrv')->table('memos')
                ->where('id', $existingMemo->id)
                ->update($data);
                
            $message = 'Memo updated successfully!';
        } else {
            // Create new record
            $data['created_at'] = now();
            DB::connection('sqlsrv')->table('memos')->insert($data);
            $message = 'Memo created successfully!';
        }
        
        return redirect()->route('programmes.view_memo_new', $request->application_id)
            ->with('success', $message);
    }
   
    public function viewMemoPrimary($id)
    {
        $PageTitle = 'ST Memo';
        $PageDescription = '';
 
        try {
            // First try to fetch from memos table
            $memoData = DB::connection('sqlsrv')->table('memos')
                ->where('application_id', $id)
                ->where('memo_type', 'primary')
                ->first();
                
            // Fetch the mother application data
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $id)
                ->select('*')
                ->first();

            if (!$application) {
                return back()->with('error', 'Application not found');
            }

            // Fetch land administration data
            $landAdmin = DB::connection('sqlsrv')->table('landAdministration')
                ->where('application_id', $id)
                ->first();

            // If we have saved memo data, use it
            if ($memoData) {
                $memo = $application; // Base data from application
                
                // Override with saved memo data
                $memo->application_id = $memoData->application_id;
                $memo->memo_no = $memoData->memo_no;
                $memo->page_no = $memoData->page_no;
                $memo->site_plan_no = $memoData->site_plan_no ?? null;
                $memo->site_plan_no = $memoData->site_plan_no ?? null;
                $memo->arc_design_page_no = $memoData->arc_design_page_no;
                $memo->certificate_number = $memoData->certificate_number;
                $memo->property_location = $memoData->property_location;
                $memo->term_years = $memoData->term_years;
                $memo->residual_years = $memoData->residual_years;
                $memo->commencement_date = $memoData->commencement_date;
                $memo->expiry_date = $memoData->expiry_date;
                $memo->planner_recommendation = $memoData->planner_recommendation;
                $memo->is_planning_recommended = $memoData->is_planning_recommended;
                
                // Director info
                $memo->director_name = $memoData->director_name;
                $memo->director_rank = $memoData->director_rank;
                
                // Use the saved applicant name
                $memo->memo_applicant_name = $memoData->applicant_name;
            } else {
                // Calculate defaults if we don't have saved data
                $memo = $application;
                
                // Calculate residual years
                $startDate = \Carbon\Carbon::parse($memo->approval_date ?? now());
                $totalYears = 40; // Default value
                $currentYear = now()->year;
                $elapsedYears = $currentYear - $startDate->year;
                $residualYears = max(0, $totalYears - $elapsedYears); 
                
                $memo->residual_years = $residualYears;
                $memo->term_years = $totalYears;
                $memo->site_plan_no = optional($landAdmin)->site_plan_page_no ?? optional($landAdmin)->page_no ?? null;
                
                // Process owner names
                if (!empty($memo->multiple_owners_names)) {
                    $ownerArray = json_decode($memo->multiple_owners_names, true);
                    $memo->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
                } elseif (!empty($memo->corporate_name)) {
                    $memo->owner_name = $memo->corporate_name;
                } else {
                    $memo->owner_name = trim($memo->applicant_title . ' ' . $memo->first_name . ' ' . $memo->surname);
                }
                
                $memo->memo_applicant_name = $memo->owner_name;
                $memo->site_plan_no = optional($landAdmin)->site_plan_page_no ?? optional($landAdmin)->page_no ?? null;
            }

            // Fetch buyers list from proper tables
            $buyersList = DB::connection('sqlsrv')->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function($join) {
                    $join->on('bl.id', '=', 'sum.buyer_id');
                })
                ->where('bl.application_id', $id)
                ->select(
                    'bl.buyer_title',
                    'bl.buyer_name', 
                    'bl.unit_no',
                    'bl.land_use',
                    'sum.measurement',
                    'bl.id'
                )
                ->get();

            $sharedUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $id)
                ->whereNull('sub_application_id')
                ->orderBy('order')
                ->select('utility_type', 'dimension')
                ->get();

            $siteMeasurements = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('application_id', $id)
                ->orderBy('order')
                ->select('description', 'dimension')
                ->get();
                
            // Calculate totalYears and residualYears for the view
            $totalYears = $memo->term_years ?? 40;
            $residualYears = $memo->residual_years ?? 40;

            // Set default page info if landAdmin data is missing
            $pageNo = $landAdmin->page_no ?? '01';

            return view('programmes.view_memo_primary', compact(
                'memo',
                'landAdmin',
                'memoData',
                'PageTitle',
                'PageDescription',
                'pageNo',
                'buyersList',
                'totalYears',
                'residualYears',
                'sharedUtilities',
                'siteMeasurements'
            ));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in viewMemoPrimary: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while fetching memo data');
        }
    }
    
    public function viewMemoNew($id)
    {
        try {
            // First try to fetch from memos table
            $memoData = DB::connection('sqlsrv')->table('memos')
                ->where('application_id', $id)
                ->where('memo_type', 'primary')
                ->first();
                
            // Fetch the mother application data
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $id)
                ->select('*')
                ->first();

            if (!$application) {
                return back()->with('error', 'Application not found');
            }

            // Fetch land administration data
            $landAdmin = DB::connection('sqlsrv')->table('landAdministration')
                ->where('application_id', $id)
                ->first();

            // Fetch buyers list from proper tables
            $buyers = [];
            $buyersData = DB::connection('sqlsrv')->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function($join) {
                    $join->on('bl.id', '=', 'sum.buyer_id');
                })
                ->where('bl.application_id', $id)
                ->select(
                    'bl.buyer_title',
                    'bl.buyer_name', 
                    'bl.unit_no',
                    'bl.land_use',
                    'sum.measurement'
                )
                ->get();
            
            foreach ($buyersData as $buyer) {
                $buyers[] = [
                    'buyer_name' => $this->sanitizeDocxText(trim(($buyer->buyer_title ?? '') . ' ' . ($buyer->buyer_name ?? '')), 'N/A'),
                    'unit_number' => $this->sanitizeDocxText($buyer->unit_no, 'N/A'),
                    'measurement' => $this->sanitizeDocxText($buyer->measurement, 'N/A'),
                    'land_use' => $this->sanitizeDocxText($buyer->land_use, 'COMMERCIAL')
                ];
            }

            // If we have saved memo data, use it
            if ($memoData) {
                $memo = $application; // Base data from application
                
                // Override with saved memo data
                $memo->application_id = $memoData->application_id;
                $memo->memo_no = $memoData->memo_no;
                $memo->page_no = $memoData->page_no;
                $memo->site_plan_no = $memoData->site_plan_no;
                $memo->arc_design_page_no = $memoData->arc_design_page_no;
                
                $memo->certificate_number = $memoData->certificate_number;
                $memo->property_location = $memoData->property_location;
                $memo->term_years = $memoData->term_years;
                $memo->residual_years = $memoData->residual_years;
                $memo->commencement_date = $memoData->commencement_date;
                $memo->expiry_date = $memoData->expiry_date;
                $memo->planner_recommendation = $memoData->planner_recommendation;
                $memo->is_planning_recommended = $memoData->is_planning_recommended;
                
                // Director info
                $memo->director_name = $memoData->director_name;
                $memo->director_rank = $memoData->director_rank;
                
                // Use the saved applicant name
                $memo->memo_applicant_name = $memoData->applicant_name;
                
                // Add buyers to memo
                $memo->buyers = $buyers;
            } else {
                // Calculate defaults if we don't have saved data
                $memo = $application;
                
                // Calculate residual years
                $startDate = \Carbon\Carbon::parse($memo->approval_date ?? now());
                $totalYears = 40; // Default value
                $currentYear = now()->year;
                $elapsedYears = $currentYear - $startDate->year;
                $residualYears = max(0, $totalYears - $elapsedYears); 
                
                $memo->residual_years = $residualYears;
                $memo->term_years = $totalYears;
                $memo->site_plan_no = optional($landAdmin)->site_plan_page_no ?? optional($landAdmin)->page_no ?? null;
                
                // Process owner names
                if (!empty($memo->multiple_owners_names)) {
                    $ownerArray = json_decode($memo->multiple_owners_names, true);
                    $memo->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
                } elseif (!empty($memo->corporate_name)) {
                    $memo->owner_name = $memo->corporate_name;
                } else {
                    $memo->owner_name = trim($memo->applicant_title . ' ' . $memo->first_name . ' ' . $memo->surname);
                }
                
                $memo->memo_applicant_name = $memo->owner_name;
                
                // Add buyers to memo
                $memo->buyers = $buyers;
            }

            // Calculate totalYears and residualYears for the view
            $totalYears = $memo->term_years ?? 40;
            $residualYears = $memo->residual_years ?? 40;
            
            // Pass buyers list for template compatibility
            $buyersList = $buyersData;

            // Fetch shared utilities and site measurements for the new template
            $sharedUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $id)
                ->whereNull('sub_application_id')
                ->orderBy('order')
                ->select('utility_type', 'dimension')
                ->get();

            $siteMeasurements = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('application_id', $id)
                ->orderBy('order')
                ->select('description', 'dimension')
                ->get();

            $jointSiteInspectionReport = DB::connection('sqlsrv')
                ->table('joint_site_inspection_reports')
                ->where('application_id', $id)
                ->orderByDesc('id')
                ->select('shared_utilities', 'existing_site_measurement_summary')
                ->first();

            $cofoNumberDisplay = null;
            $hasCofoRecord = false;
            $cofoTableCandidates = ['CofO', 'cofo', 'CoFO', 'COFO'];

            foreach ($cofoTableCandidates as $tableCandidate) {
                if (!Schema::connection('sqlsrv')->hasTable($tableCandidate)) {
                    continue;
                }

                $cofoQueryResult = DB::connection('sqlsrv')
                    ->table($tableCandidate)
                    ->where('application_id', $id)
                    ->orderByDesc('id')
                    ->value('cofo_no');

                if ($cofoQueryResult === null) {
                    continue;
                }

                $normalizedCofo = trim((string) $cofoQueryResult);
                if ($normalizedCofo === '') {
                    continue;
                }

                $cofoNumberDisplay = $normalizedCofo;
                $hasCofoRecord = true;
                break;
            }

            $primaryFileNumberCandidates = [
                $memo->fileno ?? null,
                $memo->primary_application_no ?? null,
                $memo->primaryApplicationNo ?? null,
                $memo->applicationID ?? null,
                $memo->application_id ?? null,
            ];

            $primaryFileNumberDisplay = '-';
            foreach ($primaryFileNumberCandidates as $candidate) {
                if (!is_string($candidate) && !is_numeric($candidate)) {
                    continue;
                }

                $trimmed = trim((string) $candidate);
                if ($trimmed === '') {
                    continue;
                }

                $primaryFileNumberDisplay = $trimmed;
                break;
            }

            return view('programmes.memo_template_new', compact(
                'memo',
                'landAdmin',
                'totalYears',
                'residualYears',
                'buyersList',
                'sharedUtilities',
                'siteMeasurements',
                'jointSiteInspectionReport',
                'cofoNumberDisplay',
                'hasCofoRecord',
                'primaryFileNumberDisplay'
            ));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in viewMemoNew: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while fetching memo data');
        }
    }
    
    public function exportMemoToWordNew($id)
    {
        try {
            \Log::info('Starting memo Word export for application ID: ' . $id);
            
            // Fetch application data
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return back()->with('error', 'Application not found.');
            }

            // Fetch memo data
            $memoData = DB::connection('sqlsrv')
                ->table('memos')
                ->where('application_id', $id)
                ->first();

            // Create PHPWord document
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            
            // Set document properties
            $properties = $phpWord->getDocInfo();
            $properties->setCreator('KLAES GIS EDMS');
            $properties->setLastModifiedBy('KLAES GIS EDMS');
            $properties->setTitle($this->sanitizeDocxText('Sectional Titling Memo'));
            $properties->setDescription($this->sanitizeDocxText('Sectional Titling Memo - Application ID: ' . $id));

            // Add section with margins
            $section = $phpWord->addSection([
                'marginTop' => 720,
                'marginRight' => 720,
                'marginBottom' => 720,
                'marginLeft' => 720,
            ]);

            // Define styles
            $headerStyle = ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => '000000'];
            $normalStyle = ['name' => 'Arial', 'size' => 11, 'color' => '000000'];
            
            // Add header
            $section->addText('SECTIONAL TITLING MEMO', $headerStyle, ['alignment' => 'center']);
            $section->addTextBreak();

            // Add memo details if available
            if ($memoData) {
                $section->addText('MEMO NO: ' . $this->sanitizeDocxText($memoData->memo_no ?? 'N/A'), $normalStyle);
                $section->addText('PAGE NO: ' . $this->sanitizeDocxText($memoData->page_no ?? 'N/A'), $normalStyle);
                $section->addText('SITE PLAN NO: ' . $this->sanitizeDocxText($memoData->site_plan_no ?? 'N/A'), $normalStyle);
                $section->addText('ARC. DESIGN PAGE NO: ' . $this->sanitizeDocxText($memoData->arc_design_page_no ?? 'N/A'), $normalStyle);
                $section->addTextBreak();
                
                $section->addText('Certificate Number: ' . $this->sanitizeDocxText($memoData->certificate_number ?? 'N/A'), $normalStyle);
                $section->addText('Property Location: ' . $this->sanitizeDocxText($memoData->property_location ?? 'N/A'), $normalStyle);
                $section->addTextBreak();
            }

            // Add application details
            $section->addText('Application ID: ' . $this->sanitizeDocxText($id), $normalStyle);
            $section->addText('File Number: ' . $this->sanitizeDocxText($application->fileno ?? 'N/A'), $normalStyle);
            
            // Add applicant name
            $applicantName = '';
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $applicantName = $ownerArray ? implode(', ', $ownerArray) : '';
            } elseif (!empty($application->corporate_name)) {
                $applicantName = $application->corporate_name;
            } else {
                $applicantName = trim(($application->applicant_title ?? '') . ' ' . 
                                     ($application->first_name ?? '') . ' ' . 
                                     ($application->surname ?? ''));
            }
            $section->addText('Applicant Name: ' . $this->sanitizeDocxText($applicantName ?: 'N/A'), $normalStyle);
            $section->addTextBreak();

            // Fetch buyers list
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function($join) {
                    $join->on('bl.id', '=', 'sum.buyer_id');
                })
                ->where('bl.application_id', $id)
                ->select(
                    'bl.buyer_name', 
                    'bl.unit_no',
                    'bl.land_use',
                    'sum.measurement'
                )
                ->orderBy('bl.unit_no')
                ->get();

            // Add buyers list table if we have buyers
            if ($buyers && $buyers->count() > 0) {
                $section->addText('BUYERS LIST', $headerStyle);
                $section->addTextBreak();

                // Create table
                $table = $section->addTable([
                    'borderSize' => 6, 
                    'borderColor' => '000000', 
                    'cellMargin' => 80
                ]);
                
                // Add header row
                $table->addRow();
                $table->addCell(2500)->addText('Buyer Name', ['bold' => true, 'size' => 10]);
                $table->addCell(1500)->addText('Unit Number', ['bold' => true, 'size' => 10]);  
                $table->addCell(1500)->addText('Measurement', ['bold' => true, 'size' => 10]);
                $table->addCell(1500)->addText('Land Use', ['bold' => true, 'size' => 10]);

                // Add buyer rows
                foreach ($buyers as $buyer) {
                    $table->addRow();
                    $table->addCell(2500)->addText($this->sanitizeDocxText($buyer->buyer_name ?? 'N/A'), ['size' => 9]);
                    $table->addCell(1500)->addText($this->sanitizeDocxText($buyer->unit_no ?? 'N/A'), ['size' => 9]);
                    $table->addCell(1500)->addText($this->sanitizeDocxText($buyer->measurement ?? 'N/A'), ['size' => 9]);
                    $table->addCell(1500)->addText($this->sanitizeDocxText($buyer->land_use ?? 'COMMERCIAL'), ['size' => 9]);
                }
                $section->addTextBreak();
            }

            // Fetch shared utilities
            $sharedUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $id)
                ->whereNull('sub_application_id')
                ->orderBy('order')
                ->select('utility_type', 'dimension')
                ->get();

            // Add shared utilities section
            if ($sharedUtilities && $sharedUtilities->count() > 0) {
                $section->addText('SHARED UTILITIES', $headerStyle);
                $section->addTextBreak();
                
                foreach ($sharedUtilities as $utility) {
                    $utilityText = $this->sanitizeDocxText($utility->utility_type ?? 'Utility', 'Utility') . 
                                   ': ' . $this->sanitizeDocxText($utility->dimension ?? 'N/A', 'N/A');
                    $section->addText($utilityText, $normalStyle);
                }
                $section->addTextBreak();
            }

            // Fetch site measurements
            $siteMeasurements = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('application_id', $id)
                ->orderBy('order')
                ->select('description', 'dimension')
                ->get();

            // Add site measurements section
            if ($siteMeasurements && $siteMeasurements->count() > 0) {
                $section->addText('SITE MEASUREMENTS', $headerStyle);
                $section->addTextBreak();
                
                foreach ($siteMeasurements as $measurement) {
                    $measurementText = $this->sanitizeDocxText($measurement->description ?? 'Measurement', 'Measurement') . 
                                       ': ' . $this->sanitizeDocxText($measurement->dimension ?? 'N/A', 'N/A');
                    $section->addText($measurementText, $normalStyle);
                }
                $section->addTextBreak();
            }

            // Add planning recommendation if available
            if ($memoData && !empty($memoData->planner_recommendation)) {
                $section->addText('PLANNER RECOMMENDATION', $headerStyle);
                $section->addTextBreak();
                
                $recommendation = $this->sanitizeDocxText($memoData->planner_recommendation ?? '', '');
                if ($recommendation !== '') {
                    // Split by line breaks and add each line
                    $lines = preg_split('/\r\n|\n|\r/', $recommendation);
                    foreach ($lines as $line) {
                        $cleanLine = $this->sanitizeDocxText(trim($line), '');
                        if ($cleanLine !== '') {
                            $section->addText($cleanLine, $normalStyle);
                        }
                    }
                }
                $section->addTextBreak(2);
            }

            // Add director signature section
            $section->addTextBreak();
            $section->addText('DIRECTOR', $headerStyle);
            $section->addTextBreak();
            
            $directorName = $memoData->director_name ?? '_____________________';
            $directorRank = $memoData->director_rank ?? '_____________________';
            
            $section->addText('Name: ' . $this->sanitizeDocxText($directorName), $normalStyle);
            $section->addText('Rank: ' . $this->sanitizeDocxText($directorRank), $normalStyle);
            $section->addText('Signature: _____________________', $normalStyle);
            $section->addText('Date: _____________________', $normalStyle);
            $section->addTextBreak();
            
            $section->addText('Generated at: ' . now()->toDateTimeString(), ['size' => 9, 'color' => '666666']);
            
            // Save to temporary file
            $filename = 'Memo_Test_' . $id . '_' . date('Y-m-d_H-i-s') . '.docx';
            $tempFile = tempnam(sys_get_temp_dir(), 'memo') . '.docx';
            
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempFile);
            
            \Log::info('Memo Word document generated successfully: ' . $filename);

            // Return download response
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Error in exportMemoToWordNew: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while exporting memo to Word: ' . $e->getMessage());
        }
    }

    private function sanitizeDocxText($value, $fallback = 'N/A')
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return $fallback;
        }

        // Decode existing entities so PHPWord can escape properly
        $stringValue = html_entity_decode($stringValue, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Strip any remaining HTML tags
        $stringValue = strip_tags($stringValue);

        // Re-encode to UTF-8 strictly (drops invalid byte sequences)
        $stringValue = mb_convert_encoding($stringValue, 'UTF-8', 'UTF-8');

        // Remove any character outside XML 1.0 allowed ranges
        $stringValue = preg_replace('/[^\x09\x0A\x0D\x20-\xD7FF\xE000-\xFFFD]/u', '', $stringValue);

        // Normalise whitespace (collapse multiple spaces, trim)
        $stringValue = preg_replace('/\s+/u', ' ', $stringValue);
        $stringValue = trim($stringValue);

        if ($stringValue === '') {
            return $fallback;
        }

        return $stringValue;
    }

    /**
     * View uploaded memo document inline
     */
    public function viewUploadedMemo(Request $request, $uploadId)
    {
        try {
            $upload = DB::connection('sqlsrv')
                ->table('memo_uploads')
                ->where('id', $uploadId)
                ->where('status', 'active')
                ->first();

            if (!$upload) {
                abort(404, 'Memo file not found.');
            }

            if (!Storage::disk('local')->exists($upload->file_path)) {
                abort(404, 'File not found on disk.');
            }

            $filePath = Storage::path($upload->file_path);

            \Log::info('Memo viewed inline', [
                'upload_id' => $uploadId,
                'application_id' => $upload->application_id,
                'file_no' => $upload->file_no,
                'filename' => $upload->original_filename,
                'viewed_by' => Auth::user()->name ?? 'Unknown User',
                'user_id' => Auth::id()
            ]);

            return response()->file($filePath, [
                'Content-Type' => $upload->mime_type ?? 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . addslashes($upload->original_filename) . '"'
            ]);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $httpException) {
            throw $httpException;
        } catch (\Exception $e) {
            \Log::error('Memo inline view failed', [
                'error' => $e->getMessage(),
                'upload_id' => $uploadId,
                'user_id' => Auth::id()
            ]);

            abort(500, 'Unable to open memo file.');
        }
    }

    /**
     * Download uploaded memo document
     */
    public function downloadMemo(Request $request, $uploadId)
    {
        try {
            // Get the memo upload record
            $upload = DB::connection('sqlsrv')
                ->table('memo_uploads')
                ->where('id', $uploadId)
                ->where('status', 'active')
                ->first();

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Memo file not found.'
                ], 404);
            }

            // Check if file exists in storage
            if (!Storage::disk('local')->exists($upload->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on disk.'
                ], 404);
            }

            $filePath = Storage::path($upload->file_path);

            // Log the download
            \Log::info("Memo downloaded", [
                'upload_id' => $uploadId,
                'application_id' => $upload->application_id,
                'file_no' => $upload->file_no,
                'filename' => $upload->original_filename,
                'downloaded_by' => Auth::user()->name ?? 'Unknown User',
                'user_id' => Auth::id()
            ]);

            // Return the file for download
            return response()->download($filePath, $upload->original_filename, [
                'Content-Type' => $upload->mime_type,
            ]);

        } catch (\Exception $e) {
            \Log::error('Memo download failed', [
                'error' => $e->getMessage(),
                'upload_id' => $uploadId,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Download failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Upload memo document (PDF only)
     */
    public function uploadMemo(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'memo_file' => 'required|file|mimes:pdf|max:10240', // 10MB max
                'application_id' => 'required|integer|exists:sqlsrv.mother_applications,id',
                'description' => 'nullable|string|max:1000',
                'file_no' => 'required|string',
                'memo_type' => 'nullable|string|in:primary,sua,pua,unit'
            ]);

            $applicationId = $request->input('application_id');
            $fileNo = $request->input('file_no');
            $description = $request->input('description', '');
            $memoType = $request->input('memo_type', 'primary');

            // Check if application exists
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

            // Handle file upload
            $file = $request->file('memo_file');
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            
            // Create unique filename
            $timestamp = now()->format('Y_m_d_H_i_s');
            $sanitizedFileNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', $fileNo);
            $fileName = "memo_{$sanitizedFileNo}_{$timestamp}.pdf";

            // Store the file in storage/app/memos directory
            $storagePath = $file->storeAs('memos', $fileName, 'local');

            // Save upload information to database
            $uploadId = DB::connection('sqlsrv')->table('memo_uploads')->insertGetId([
                'application_id' => $applicationId,
                'memo_type' => $memoType,
                'file_no' => $fileNo,
                'original_filename' => $originalName,
                'stored_filename' => $fileName,
                'file_path' => $storagePath,
                'file_size' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'description' => $description,
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Log the activity
            $userName = Auth::user()->name ?? 'Unknown User';
            \Log::info("Memo uploaded", [
                'upload_id' => $uploadId,
                'application_id' => $applicationId,
                'file_no' => $fileNo,
                'memo_type' => $memoType,
                'filename' => $originalName,
                'uploaded_by' => $userName,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Memo uploaded successfully.',
                'data' => [
                    'upload_id' => $uploadId,
                    'filename' => $originalName,
                    'file_size' => $fileSize,
                    'upload_date' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Memo upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'application_id' => $request->input('application_id'),
                'file_no' => $request->input('file_no')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed. Please try again.'
            ], 500);
        }
    }
}