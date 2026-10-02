<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class STMemoController extends Controller
{
    private function getApplication($id)
    {
        // Modified to join subapplications with mother_applications to get primary application details
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'subapplications.*', 
                'subapplications.id as applicationID', // Add alias for applicationID
                'subapplications.main_application_id as main_application_id', // Add main_application_id if it exists
                'mother_applications.fileno as primary_fileno',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.applicant_title as primary_applicant_title',
                'mother_applications.application_status as primary_application_status',
                'mother_applications.land_use as primary_land_use',
                'mother_applications.id as main_application_id',

                // Property fields with proper aliases
                'mother_applications.property_house_no as property_house_no',
                'mother_applications.property_plot_no as property_plot_no',
                'mother_applications.property_street_name as property_street_name',
                'mother_applications.property_lga as property_lga',
                 
                'mother_applications.applicationID as primary_applicationID' // Get primary app's applicationID
            )
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Sub application not found'], 404);
        }

        return $application;
    }

    private function getSecondaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
                'dbo.subapplications.*',
                'dbo.subapplications.id as id',
                'dbo.mother_applications.fileno as primary_fileno', // Changed alias to primary_fileno
                'dbo.mother_applications.passport as mother_passport',
                'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
                'dbo.mother_applications.applicant_title as primary_applicant_title', // Changed alias to primary_applicant_title
                'dbo.mother_applications.first_name as primary_first_name', // Changed alias to primary_first_name
                'dbo.mother_applications.surname as primary_surname', // Changed alias to primary_surname
                'dbo.mother_applications.np_fileno as primary_np_fileno',
                'dbo.mother_applications.mls_fileno as primary_mls_fileno',
                'dbo.mother_applications.corporate_name as mother_corporate_name',
                'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
                'dbo.mother_applications.land_use',
                'dbo.mother_applications.property_house_no',
                'dbo.mother_applications.property_plot_no',
                'dbo.mother_applications.property_street_name',
                'dbo.mother_applications.property_district',
                'dbo.mother_applications.property_lga',
                'dbo.mother_applications.id as main_application_id' // Add main_application_id for reference
            )
            ->where('dbo.subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    private function getPrimaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'mother_applications.*',
                'mother_applications.id as main_application_id',
                'mother_applications.id as applicationID'
            )
            ->where('mother_applications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Primary application not found'], 404);
        }

        return $application;
    }
  
    public function STmemo(Request $request)
    {
        $PageTitle = 'SECTIONAL TITLING MEMO';
        $PageDescription = 'processing of sectional titling memo';

        if ($request->has('id')) {
            $application = $this->getSecondaryApplication($request->get('id'));
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            return view('stmemo.view_application', compact('application', 'PageTitle', 'PageDescription'));
        }

        // Get count of applications with generated memos
        $generatedCount = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->join('memos', 'mother_applications.id', '=', 'memos.application_id')
            ->where('memos.memo_status', 'GENERATED')
            ->count();

        // Get count of applications without generated memos
        $notGeneratedCount = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->leftJoin('memos', function($join) {
                $join->on('mother_applications.id', '=', 'memos.application_id')
                     ->where('memos.memo_status', 'GENERATED');
            })
            ->whereNull('memos.id')
            ->count();

        // Filter applications based on selected status
        $status = $request->input('status', 'not_generated');
        
        if ($status == 'generated') {
            $PrimaryApplications = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->select('mother_applications.*')
                ->join('memos', 'mother_applications.id', '=', 'memos.application_id')
                ->where('memos.memo_status', 'GENERATED')
                ->orderBy('mother_applications.sys_date', 'desc')
                ->get();
        } else {
            $PrimaryApplications = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->select('mother_applications.*')
                ->leftJoin('memos', function($join) {
                    $join->on('mother_applications.id', '=', 'memos.application_id')
                         ->where('memos.memo_status', 'GENERATED');
                })
                ->whereNull('memos.id')
                ->orderBy('mother_applications.sys_date', 'desc')
                ->get();
        }

        return view('stmemo.stmemo', compact('PrimaryApplications', 'PageTitle', 'PageDescription', 'generatedCount', 'notGeneratedCount'));
    }

    

    public function SitePlan(Request $request)
    {
        $PageTitle = 'ST Applications';
        $PageDescription = '';

        // Fetch primary applications only from mother_applications
        $PrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'mother_applications.*',
                'mother_applications.id as id'
            )
            ->orderBy('mother_applications.sys_date', 'desc')
            ->get();

        // Check which applications have site plans
        $sitePlans = DB::connection('sqlsrv')->table('site_plans')
            ->pluck('application_id')
            ->toArray();

        // Check which applications have recommended site plan sketches
        $recommendedSitePlans = [];
        try {
            $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
            if ($tableExists) {
                $recommendedSitePlans = DB::connection('sqlsrv')->table('recommended_site_plans')
                    ->pluck('application_id')
                    ->toArray();
            }
        } catch (Exception $e) {
            // If there's an error, assume no recommended site plans
            $recommendedSitePlans = [];
        }

        // Add site_plan_status and recommended_site_plan_status to each application
        $PrimaryApplications = $PrimaryApplications->map(function($app) use ($sitePlans, $recommendedSitePlans) {
            $app->site_plan_status = in_array($app->id, $sitePlans) ? 'Uploaded' : 'Not Uploaded';
            $app->recommended_site_plan_status = in_array($app->id, $recommendedSitePlans) ? 'Uploaded' : 'Not Uploaded';
            return $app;
        });

        return view('stmemo.siteplan', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

    private function collectFileNumberCandidates($application, bool $isPrimary): array
    {
        $candidates = [];

        $potentialFields = [
            'fileno',
            'np_fileno',
            'mls_fileno',
            'primary_fileno',
            'primary_np_fileno',
            'primary_mls_fileno'
        ];

        foreach ($potentialFields as $field) {
            if (isset($application->{$field}) && !empty($application->{$field})) {
                $candidates[] = trim($application->{$field});
            }
        }

        if (!$isPrimary && !empty($application->main_application_id)) {
            $mother = DB::connection('sqlsrv')->table('mother_applications')
                ->select('fileno', 'np_fileno', 'mls_fileno')
                ->where('id', $application->main_application_id)
                ->first();

            if ($mother) {
                foreach (['fileno', 'np_fileno', 'mls_fileno'] as $field) {
                    if (!empty($mother->{$field})) {
                        $candidates[] = trim($mother->{$field});
                    }
                }
            }
        }

        $candidates = array_filter(array_unique($candidates));

        return array_values($candidates);
    }

    private function findFileIndexingRecord($application, bool $isPrimary)
    {
        $candidates = $this->collectFileNumberCandidates($application, $isPrimary);

        return DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($query) use ($application, $isPrimary, $candidates) {
                if ($isPrimary) {
                    $query->where('main_application_id', $application->id);
                } else {
                    $query->where('subapplication_id', $application->id);
                    if (!empty($application->main_application_id)) {
                        $query->orWhere('main_application_id', $application->main_application_id);
                    }
                }

                foreach ($candidates as $candidate) {
                    $query->orWhere('file_number', $candidate);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function resolveEdmsDirectory($fileIndexingId, string $fallbackFolder): string
    {
        $existingScan = DB::connection('sqlsrv')->table('scannings')
            ->where('file_indexing_id', $fileIndexingId)
            ->orderByDesc('id')
            ->first();

        if ($existingScan && !empty($existingScan->document_path)) {
            $path = str_replace('\\', '/', $existingScan->document_path);
            $directory = trim(dirname($path), '/');
            if (!empty($directory)) {
                return $directory;
            }
        }

        return 'EDMS/SCAN_UPLOAD/' . trim($fallbackFolder);
    }

    private function syncRecommendedSitePlanToEdms($application, bool $isPrimary, string $storedPath, string $originalFilename, ?string $description = null): array
    {
        try {
            if (!Storage::disk('public')->exists($storedPath)) {
                throw new \RuntimeException('Stored recommended site plan file not found.');
            }

            $fileIndexing = $this->findFileIndexingRecord($application, $isPrimary);

            if (!$fileIndexing) {
                return [
                    'synced' => false,
                    'message' => 'File indexing record not found for application. EDMS sync skipped.'
                ];
            }

            $fileNumberCandidates = $this->collectFileNumberCandidates($application, $isPrimary);
            $baseFileNumber = !empty($fileIndexing->file_number)
                ? trim($fileIndexing->file_number)
                : ($fileNumberCandidates[0] ?? ('APPLICATION_' . $application->id));

            $edmsDirectory = $this->resolveEdmsDirectory($fileIndexing->id, $baseFileNumber);

            if (!Storage::disk('public')->exists($edmsDirectory)) {
                Storage::disk('public')->makeDirectory($edmsDirectory);
            }

            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
            }
            if ($extension === '') {
                $extension = 'pdf';
            }

            $maxDisplayOrder = DB::connection('sqlsrv')->table('scannings')
                ->where('file_indexing_id', $fileIndexing->id)
                ->max('display_order') ?? 0;

            $nextDisplayOrder = $maxDisplayOrder + 1;
            $sequenceStr = str_pad($nextDisplayOrder, 4, '0', STR_PAD_LEFT);
            $folderBaseName = basename(str_replace('\\', '/', $edmsDirectory));
            $destinationFilename = $folderBaseName . '_' . $sequenceStr . '.' . $extension;
            $destinationPath = $edmsDirectory . '/' . $destinationFilename;

            if (Storage::disk('public')->exists($destinationPath)) {
                $destinationFilename = $folderBaseName . '_' . $sequenceStr . '_' . uniqid() . '.' . $extension;
                $destinationPath = $edmsDirectory . '/' . $destinationFilename;
            }

            Storage::disk('public')->copy($storedPath, $destinationPath);

            if (!Storage::disk('public')->exists($destinationPath)) {
                throw new \RuntimeException('Failed to copy recommended site plan into EDMS folder.');
            }

            $scanningData = [
                'file_indexing_id' => $fileIndexing->id,
                'document_path' => $destinationPath,
                'original_filename' => $originalFilename,
                'uploaded_by' => Auth::id(),
                'status' => 'pending',
                'paper_size' => null,
                'document_type' => 'Recommended Site Plan Sketch',
                'notes' => $description
                    ? 'Recommended Site Plan Sketch: ' . $description
                    : '.',
                'display_order' => $nextDisplayOrder,
                'created_at' => now(),
                'updated_at' => now()
            ];

            DB::connection('sqlsrv')->table('scannings')->insert($scanningData);

            return [
                'synced' => true,
                'edms_path' => $destinationPath
            ];
        } catch (\Throwable $exception) {
            Log::error('Failed to sync recommended site plan to EDMS', [
                'application_id' => $application->id ?? null,
                'stored_path' => $storedPath,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            return [
                'synced' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    
    public function uploadSitePlan($id)
    {
        $PageTitle = 'UPLOAD SITE PLAN';
        $PageDescription = 'Upload a site plan for a sectional titling application';
        
        // Check if primary or secondary application
        $isPrimary = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->exists();
        
        if ($isPrimary) {
            $application = $this->getPrimaryApplication($id);
        } else {
            $application = $this->getSecondaryApplication($id);
        }
        
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return redirect()->route('stmemo.siteplan')->with('error', 'Application not found');
        }
        
        // Check if site plan already exists
        $existingSitePlan = DB::connection('sqlsrv')->table('site_plans')
            ->where('application_id', $id)
            ->first();
        
        return view('stmemo.upload_siteplan', compact('application', 'existingSitePlan', 'PageTitle', 'PageDescription', 'isPrimary'));
    }
    
    public function saveSitePlan(Request $request)
    {
        $request->validate([
            'application_id' => 'required',
            'property_location' => 'required',
            'site_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        
        $applicationId = $request->application_id;
        
        // Get application details for naming the file
        $isPrimary = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->exists();
        
        if ($isPrimary) {
            $application = $this->getPrimaryApplication($applicationId);
        } else {
            $application = $this->getSecondaryApplication($applicationId);
        }
        
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Application not found']);
            }
            return redirect()->route('stmemo.siteplan')->with('error', 'Application not found');
        }
        
        // Generate applicant name for the file
        $applicantName = '';
        if ($isPrimary) {
            if (!empty($application->corporate_name)) {
                $applicantName = $application->corporate_name;
            } else {
                $applicantName = $application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname;
            }
        } else {
            if (!empty($application->corporate_name)) {
                $applicantName = $application->corporate_name;
            } else {
                $applicantName = $application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname;
            }
        }
        
        $applicantName = preg_replace('/[^a-zA-Z0-9]/', '_', $applicantName);
        
        // Create directory if it doesn't exist
        $uploadDir = 'site_plans/' . $applicationId;
        if (!Storage::disk('public')->exists($uploadDir)) {
            Storage::disk('public')->makeDirectory($uploadDir);
        }
        
        // Upload the file
        $file = $request->file('site_file');
        $extension = $file->getClientOriginalExtension();
        $fileName = $applicantName . '_site_plan_' . time() . '.' . $extension;
        $filePath = $file->storeAs($uploadDir, $fileName, 'public');
        
        // Check if record already exists
        $existingSitePlan = DB::connection('sqlsrv')->table('site_plans')
            ->where('application_id', $applicationId)
            ->first();
            
        $isUpdate = false;
        if ($existingSitePlan) {
            // Delete old file if exists
            if (Storage::disk('public')->exists($existingSitePlan->site_file)) {
                Storage::disk('public')->delete($existingSitePlan->site_file);
            }
            
            // Update record
            DB::connection('sqlsrv')->table('site_plans')
                ->where('application_id', $applicationId)
                ->update([
                    'property_location' => $request->property_location,
                    'site_file' => $filePath,
                     'uploaded_by' => Auth::id(),
                    'updated_at' => now()
                ]);
                
            $message = 'Site plan has been successfully updated';
            $isUpdate = true;
        } else {
            // Create new record
            DB::connection('sqlsrv')->table('site_plans')->insert([
                'application_id' => $applicationId,
                'property_location' => $request->property_location,
                'status' => 'Uploaded',
                'created_by' => Auth::id(),
                'site_file' => $filePath,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $message = 'Site plan has been successfully uploaded';
        }
        
        // Check if this is an AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'update' => $isUpdate,
                'file_path' => Storage::url($filePath)
            ]);
        }
        
        // If not AJAX, redirect as before
        return redirect()->route('stmemo.viewSitePlan', $applicationId)->with('success', $message);
    }
    
    public function viewSitePlan($id)
    {
        $PageTitle = 'VIEW SITE PLAN';
        $PageDescription = 'View site plan for a sectional titling application';
        
        // Check if primary or secondary application
        $isPrimary = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->exists();
        
        if ($isPrimary) {
            $application = $this->getPrimaryApplication($id);
        } else {
            $application = $this->getSecondaryApplication($id);
        }
        
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return redirect()->route('stmemo.siteplan')->with('error', 'Application not found');
        }
        
        // Get site plan details
        $sitePlan = DB::connection('sqlsrv')->table('site_plans')
            ->where('application_id', $id)
            ->first();
            
        if (!$sitePlan) {
            return redirect()->route('stmemo.uploadSitePlan', $id)->with('error', 'No site plan found for this application');
        }
        
        return view('stmemo.view_siteplan', compact('application', 'sitePlan', 'PageTitle', 'PageDescription', 'isPrimary'));
    }
    
    public function deleteSitePlan($id)
    {
        // Get site plan details
        $sitePlan = DB::connection('sqlsrv')->table('site_plans')
            ->where('application_id', $id)
            ->first();
            
        if (!$sitePlan) {
            return redirect()->route('stmemo.siteplan')->with('error', 'No site plan found for this application');
        }
        
        // Delete file
        if (Storage::disk('public')->exists($sitePlan->site_file)) {
            Storage::disk('public')->delete($sitePlan->site_file);
        }
        
        // Delete record
        DB::connection('sqlsrv')->table('site_plans')
            ->where('application_id', $id)
            ->delete();
            
        return redirect()->route('stmemo.siteplan')->with('success', 'Site plan has been successfully deleted');
    }

    
    public function MemoTemplate()
    {
        $PageTitle = 'ST MEMO TEMPLATE';
        $PageDescription = '';
         
        return view('stmemo.temotemplate', compact('PageTitle', 'PageDescription'));
    }   
    
    public function SecondarySurveyView($d)
    {
        $PageTitle = 'SECTIONAL TITLING SURVEY';
        $PageDescription = 'processing of sectional title survey applications for secondary applications';
        
        $application = $this->getSecondaryApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('other_departments.secondary_survey_view', compact('application', 'PageTitle', 'PageDescription'));
    }

    private function getUnitMeasurements($applicationId) 
    {
        // Get all measurements from the st_unit_measurements table based on application_id
        $measurements = DB::connection('sqlsrv')->table('st_unit_measurements')
            ->select(
                'application_id',
                'buyer_id',
                'unit_no',
                'measurement',
                'created_at',
                'updated_at'
            )
            ->where('application_id', $applicationId)
            ->get();
            
        return $measurements;
    }

    public function generateSTMemo($id)
    {
        $PageTitle = 'Generate Physical Planning Memo';
        $PageDescription = 'Create a new sectional titling memo';
        
        // Check if this is a primary application
        $isPrimary = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->exists();
        
        if ($isPrimary) {
            // Get the primary application details
            $application = $this->getPrimaryApplication($id);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            // Get conveyance data from the buyer_list table
            $conveyanceData = DB::connection('sqlsrv')->table('buyer_list')
                ->select('*')
                ->where('application_id', $id)
                ->get();
            
            // Get unit measurements if they exist
            $unitMeasurements = $this->getUnitMeasurements($id);
            
            return view('stmemo.generate', compact('application', 'conveyanceData', 'unitMeasurements', 'PageTitle', 'PageDescription', 'isPrimary'));
        } else {
            // Existing code for secondary application
            $application = $this->getSecondaryApplication($id);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            // Get conveyance data from the buyer_list table for the primary application
            $conveyanceData = DB::connection('sqlsrv')->table('buyer_list')
                ->select('*')
                ->where('application_id', $application->main_application_id)
                ->get();
            
            // Get unit measurements if they exist
            $unitMeasurements = $this->getUnitMeasurements($id);
            
            return view('stmemo.generate', compact('application', 'conveyanceData', 'unitMeasurements', 'PageTitle', 'PageDescription', 'isPrimary'));
        }
    }
    
    public function saveSTMemo(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'application_id' => 'required',
            'property_location' => 'required',
            'applicant_name' => 'required',
            'sections' => 'required|array',
            'shared_facilities' => 'required'
        ], [
            'sections.required' => 'Buyers list and Unit Measurements are Required'
        ]);
        
        // Generate next memo number
        $lastMemo = DB::connection('sqlsrv')->table('memos')
            ->orderBy('id', 'desc')
            ->first();
            
        $currentYear = date('Y');
        $memoNo = 'MEMO/' . $currentYear . '/01';
        
        if ($lastMemo) {
            $lastMemoNo = $lastMemo->memo_no;
            $parts = explode('/', $lastMemoNo);
            if (count($parts) == 3 && $parts[1] == $currentYear) {
                $nextNumber = intval($parts[2]) + 1;
                $memoNo = 'MEMO/' . $currentYear . '/' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
            }
        }
        
        // Create the memo record
        $memoId = DB::connection('sqlsrv')->table('memos')->insertGetId([
            'memo_no' => $memoNo,
            'application_id' => $request->application_id,
            'memo_type' => 'physical_planning',
            'memo_status' => 'GENERATED',
            'applicant_name' => $request->applicant_name,
            'property_location' => $request->property_location,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'shared_facilities' => $request->shared_facilities
        ]);
        
     
        
        // Update the application status
        if (isset($request->is_primary) && $request->is_primary == '1') {
            DB::connection('sqlsrv')->table('memos')
                ->where('id', $memoId)
                ->update(['memo_status' => 'GENERATED']);
        } else {
            
        }
        
        return redirect()->route('stmemo.view', $request->application_id)->with('success', 'Physical Planning Memo has been successfully generated');
    }
    
    public function viewSTMemo($id)
    {
        $PageTitle = 'View Physical Planning Memo';
        $PageDescription = 'View sectional titling memo details';
        
        // Get the memo details
        $memo = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $id)
            ->where('memo_type', 'physical_planning')
            ->first();
        if (!$memo) {
            return redirect()->route('stmemo.siteplan')->with('error', 'Memo not found');
        }
        
        // Get the unit measurements
        $measurements = DB::connection('sqlsrv')->table('st_unit_measurements')
            ->where('application_id', $id)
            ->get();
        
        // Check if primary or secondary application
        $isPrimary = false;
        $application = null;
        
        // Try to get application from mother_applications first
        $primaryApp = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $memo->application_id)
            ->first();
            
        if ($primaryApp) {
            $isPrimary = true;
            $application = $this->getPrimaryApplication($memo->application_id);
        } else {
            // If not found in primary, try secondary
            $application = $this->getSecondaryApplication($memo->application_id);
        }
        
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return redirect()->route('stmemo.stmemo')->with('error', 'Application not found');
        }
        
        return view('stmemo.view', compact('memo', 'measurements', 'application', 'PageTitle', 'PageDescription', 'isPrimary'));
    }
    
    private function getConveyanceData($mainApplicationId)
    {
        // Get all conveyance data from the buyer_list table based on application_id
        $buyerRecords = DB::connection('sqlsrv')->table('buyer_list')
            ->select(
                'application_id',
                'buyer_title',
                'buyer_name',
                'unit_no',
                'created_at',
                'updated_at'
            )
            ->where('application_id', $mainApplicationId)
            ->get();
            
        // Each item in the collection is an object, access properties with ->
        // For example: $buyerRecord->buyer_name, not $buyerRecord['buyer_name']
        return $buyerRecords;
    }

    // Recommended Site Plan Sketch Methods
    public function getRecommendedSitePlan($id)
    {
        try {
            // Check if table exists first
            $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
            
            if (!$tableExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database table not yet created. Please create the recommended_site_plans table first.',
                    'table_missing' => true
                ], 500);
            }

            $recommendedSitePlan = DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'recommendedSitePlan' => $recommendedSitePlan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving recommended site plan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveRecommendedSitePlan(Request $request)
    {
        // Check if table exists first
        try {
            $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
            
            if (!$tableExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database table not yet created. Please create the recommended_site_plans table first. Check create_recommended_site_plans_table.sql file.',
                    'table_missing' => true
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection error: ' . $e->getMessage()
            ], 500);
        }

        $request->validate([
            'application_id' => 'required',
            'recommended_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'description' => 'nullable|string|max:1000'
        ]);

        $applicationId = $request->application_id;

        // Get application details for naming the file
        $isPrimary = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->exists();
        
        if ($isPrimary) {
            $application = $this->getPrimaryApplication($applicationId);
        } else {
            $application = $this->getSecondaryApplication($applicationId);
        }

        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return response()->json(['success' => false, 'message' => 'Application not found'], 404);
        }

        // Generate applicant name for the file
        $applicantName = '';
        if ($isPrimary) {
            if (!empty($application->corporate_name)) {
                $applicantName = $application->corporate_name;
            } else {
                $applicantName = $application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname;
            }
        } else {
            if (!empty($application->corporate_name)) {
                $applicantName = $application->corporate_name;
            } else {
                $applicantName = $application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname;
            }
        }

        $applicantName = preg_replace('/[^a-zA-Z0-9]/', '_', $applicantName);

        // Create directory if it doesn't exist
        $uploadDir = 'recommended_site_plans/' . $applicationId;
        if (!Storage::disk('public')->exists($uploadDir)) {
            Storage::disk('public')->makeDirectory($uploadDir);
        }

        // Upload the file
        $file = $request->file('recommended_file');
        $extension = $file->getClientOriginalExtension();
        $fileName = $applicantName . '_recommended_site_plan_' . time() . '.' . $extension;
        $filePath = $file->storeAs($uploadDir, $fileName, 'public');

        try {
            // Check if record already exists
            $existingRecommended = DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $applicationId)
                ->first();

            $isUpdate = false;
            if ($existingRecommended) {
                // Delete old file if exists
                if (Storage::disk('public')->exists($existingRecommended->recommended_file)) {
                    Storage::disk('public')->delete($existingRecommended->recommended_file);
                }

                // Update record
                DB::connection('sqlsrv')->table('recommended_site_plans')
                    ->where('application_id', $applicationId)
                    ->update([
                        'recommended_file' => $filePath,
                        'description' => $request->description,
                        'uploaded_by' => Auth::id(),
                        'updated_at' => now()
                    ]);

                $message = 'Recommended site plan sketch has been successfully updated';
                $isUpdate = true;
            } else {
                // Create new record
                DB::connection('sqlsrv')->table('recommended_site_plans')->insert([
                    'application_id' => $applicationId,
                    'recommended_file' => $filePath,
                    'description' => $request->description,
                    'status' => 'Uploaded',
                    'created_by' => Auth::id(),
                    'uploaded_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $message = 'Recommended site plan sketch has been successfully uploaded';
            }

            $edmsSync = $this->syncRecommendedSitePlanToEdms(
                $application,
                $isPrimary,
                $filePath,
                $file->getClientOriginalName(),
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'update' => $isUpdate,
                'file_path' => Storage::url($filePath),
                'edms_synced' => $edmsSync['synced'] ?? false,
                'edms_message' => $edmsSync['message'] ?? null,
                'edms_path' => $edmsSync['edms_path'] ?? null
            ]);
        } catch (\Exception $e) {
            // Delete uploaded file if database operation failed
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteRecommendedSitePlan($id)
    {
        try {
            // Check if table exists first
            $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
            
            if (!$tableExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database table not yet created. Please create the recommended_site_plans table first.',
                    'table_missing' => true
                ], 500);
            }

            $recommendedSitePlan = DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $id)
                ->first();

            if (!$recommendedSitePlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recommended site plan sketch not found'
                ], 404);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($recommendedSitePlan->recommended_file)) {
                Storage::disk('public')->delete($recommendedSitePlan->recommended_file);
            }

            // Delete record from database
            DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Recommended site plan sketch has been successfully deleted'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting recommended site plan sketch: ' . $e->getMessage()
            ], 500);
        }
    }
}