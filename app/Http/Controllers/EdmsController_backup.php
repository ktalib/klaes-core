<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicationMother;
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EdmsController extends Controller
{
    /**
     * Display the EDMS workflow for a specific application
     */
    public function index($applicationId, $type = 'primary')
    {
        try {
            if ($type === 'sub') {
                // Handle sub-application
                $application = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                if (!$application) {
                    throw new Exception('Sub-application not found');
                }
                
                // Get the mother application for reference
                $motherApplication = DB::connection('sqlsrv')->table('mother_applications')->where('id', $application->main_application_id)->first();
                
                // Try to get file indexing for sub-application
                $fileIndexing = FileIndexing::on('sqlsrv')->where('subapplication_id', $applicationId)->first();
                
                $PageTitle = 'EDMS Workflow - Unit Application';
                $PageDescription = 'Electronic Document Management System for Unit Application';
                
                return view('primaryform.edms', compact(
                    'PageTitle',
                    'PageDescription', 
                    'application',
                    'motherApplication',
                    'fileIndexing',
                    'type'
                ));
            } else {
                // Handle primary application
                $application = ApplicationMother::on('sqlsrv')->find($applicationId);
                
                if (!$application) {
                    Log::error('Primary application not found for EDMS workflow', [
                        'application_id' => $applicationId
                    ]);
                    
                    return redirect()->back()->with('error', 'Primary application not found. Please ensure the application exists.');
                }
                
                // Try to get file indexing, but don't fail if it doesn't exist
                $fileIndexing = null;
                try {
                    // First try the relationship
                    $fileIndexing = $application->fileIndexing;
                } catch (Exception $e) {
                    Log::warning('File indexing relationship failed, trying direct query', [
                        'application_id' => $applicationId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // If relationship failed, try direct query
                if (!$fileIndexing) {
                    try {
                        $fileIndexing = FileIndexing::on('sqlsrv')->where('main_application_id', $applicationId)->first();
                    } catch (Exception $e) {
                        Log::warning('Direct file indexing query failed', [
                            'application_id' => $applicationId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $PageTitle = 'EDMS Workflow';
                $PageDescription = 'Electronic Document Management System';
                
                return view('primaryform.edms', compact(
                    'PageTitle',
                    'PageDescription', 
                    'application',
                    'fileIndexing',
                    'type'
                ));
            }
        } catch (Exception $e) {
            Log::error('Error loading EDMS workflow', [
                'application_id' => $applicationId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading EDMS workflow: ' . $e->getMessage());
        }
    }

    /**
     * Create file indexing record for an application
     */
    public function createFileIndexing($applicationId, $type = 'primary')
    {
        try {
            if ($type === 'sub') {
                // Handle sub-application
                $application = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                if (!$application) {
                    throw new Exception('Sub-application not found');
                }
                
                // Get the mother application for reference
                $motherApplication = DB::connection('sqlsrv')->table('mother_applications')->where('id', $application->main_application_id)->first();
                
                // Check if file indexing already exists for sub-application
                $existingFileIndexing = FileIndexing::on('sqlsrv')->where('subapplication_id', $applicationId)->first();
                if ($existingFileIndexing) {
                    return redirect()->route('edms.fileindexing', $existingFileIndexing->id)
                        ->with('info', 'File indexing already exists for this unit application.');
                }
                
                // Create file indexing record for sub-application
                $fileIndexing = FileIndexing::on('sqlsrv')->create([
                    'subapplication_id' => $application->id,
                    'main_application_id' => $application->main_application_id,
                    'file_number' => $application->fileno ?? 'Unit-' . $application->id,
                    'file_title' => $this->generateSubApplicationFileTitle($application, $motherApplication),
                    'land_use_type' => $motherApplication->land_use ?? 'Residential',
                    'plot_number' => $motherApplication->property_plot_no,
                    'district' => $motherApplication->property_district,
                    'lga' => $motherApplication->property_lga,
                    'has_cofo' => false,
                    'is_merged' => false,
                    'has_transaction' => false,
                    'is_problematic' => false,
                ]);
                
                Log::info('Sub-application file indexing created', [
                    'subapplication_id' => $applicationId,
                    'file_indexing_id' => $fileIndexing->id
                ]);
                
                return redirect()->route('edms.fileindexing', $fileIndexing->id)
                    ->with('success', 'Unit application file indexing record created successfully!');
                    
            } else {
                // Handle primary application
                $application = ApplicationMother::on('sqlsrv')->find($applicationId);
                
                if (!$application) {
                    Log::error('Primary application not found for file indexing', [
                        'application_id' => $applicationId
                    ]);
                    
                    return redirect()->back()->with('error', 'Primary application not found. Please ensure the application exists before creating file indexing.');
                }
                
                // Check if file indexing already exists
                $existingFileIndexing = FileIndexing::on('sqlsrv')->where('main_application_id', $applicationId)->first();
                if ($existingFileIndexing) {
                    return redirect()->route('edms.fileindexing', $existingFileIndexing->id)
                        ->with('info', 'File indexing already exists for this application.');
                }
                
                // Create file indexing record using application data
                $fileIndexing = FileIndexing::on('sqlsrv')->create([
                    'main_application_id' => $application->id,
                    'file_number' => $application->fileno ?? $application->np_fileno ?? 'APP-' . $application->id,
                    'file_title' => $this->generateFileTitle($application),
                    'land_use_type' => $application->land_use ?? 'Residential',
                    'plot_number' => $application->property_plot_no ?? null,
                    'district' => $application->property_district ?? null,
                    'lga' => $application->property_lga ?? null,
                    'has_cofo' => false,
                    'is_merged' => false,
                    'has_transaction' => false,
                    'is_problematic' => false,
                ]);
                
                Log::info('File indexing created', [
                    'application_id' => $applicationId,
                    'file_indexing_id' => $fileIndexing->id
                ]);
                
                return redirect()->route('edms.fileindexing', $fileIndexing->id)
                    ->with('success', 'File indexing record created successfully!');
            }
                
        } catch (Exception $e) {
            Log::error('Error creating file indexing', [
                'application_id' => $applicationId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error creating file indexing: ' . $e->getMessage());
        }
    }

    /**
     * Display file indexing interface
     */
    public function fileIndexing($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->with('mainApplication')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                Log::warning('File indexing not found', [
                    'file_indexing_id' => $fileIndexingId
                ]);
                
                return redirect()->route('edms.index', request()->get('application_id', 1))
                    ->with('error', 'File indexing record not found. Please create a new file index first.');
            }
            
            $PageTitle = 'File Indexing';
            $PageDescription = 'Digital File Index Management';
            
            return view('edms.fileindexing', compact(
                'PageTitle',
                'PageDescription',
                'fileIndexing'
            ));
        } catch (Exception $e) {
            Log::error('Error loading file indexing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading file indexing: ' . $e->getMessage());
        }
    }

    /**
     * Update file indexing record
     */
    public function updateFileIndexing(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return redirect()->back()->with('error', 'File indexing record not found.');
            }
            
            $validated = $request->validate([
                'file_title' => 'required|string|max:255',
                'land_use_type' => 'required|string|max:100',
                'plot_number' => 'nullable|string|max:100',
                'district' => 'nullable|string|max:100',
                'lga' => 'nullable|string|max:100',
                'has_cofo' => 'boolean',
                'is_merged' => 'boolean',
                'has_transaction' => 'boolean',
                'is_problematic' => 'boolean',
                'is_co_owned_plot' => 'boolean',
            ]);
            
            $fileIndexing->update($validated);
            
            Log::info('File indexing updated', [
                'file_indexing_id' => $fileIndexingId,
                'updated_by' => Auth::id()
            ]);
            
            return redirect()->route('edms.scanning', $fileIndexingId)
                ->with('success', 'File indexing updated successfully! Proceed to scanning.');
                
        } catch (Exception $e) {
            Log::error('Error updating file indexing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error updating file indexing: ' . $e->getMessage());
        }
    }

    /**
     * Display scanning interface
     */
    public function scanning($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->with(['mainApplication', 'scannings'])->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return redirect()->back()->with('error', 'File indexing record not found.');
            }
            
            $PageTitle = 'Document Scanning';
            $PageDescription = 'Upload Scanned Documents';
            
            return view('edms.scanning', compact(
                'PageTitle',
                'PageDescription',
                'fileIndexing'
            ));
        } catch (Exception $e) {
            Log::error('Error loading scanning interface', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading scanning interface: ' . $e->getMessage());
        }
    }

    /**
     * Upload scanned documents
     */
    public function uploadScannedDocuments(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.'
                ], 404);
            }
            
            $validator = \Validator::make($request->all(), [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first()
                ], 422);
            }
            
            $uploadedDocuments = [];
            
            foreach ($request->file('documents') as $document) {
                $path = $document->store('scanned_documents/' . $fileIndexingId, 'public');
                
                $scanning = Scanning::on('sqlsrv')->create([
                    'file_indexing_id' => $fileIndexingId,
                    'document_path' => $path,
                    'original_filename' => $document->getClientOriginalName(),
                    'paper_size' => 'A4',
                    'document_type' => 'Certificate',
                    'uploaded_by' => Auth::id(),
                    'status' => 'pending',
                ]);
                
                $uploadedDocuments[] = $scanning;
            }
            
            Log::info('Documents uploaded', [
                'file_indexing_id' => $fileIndexingId,
                'document_count' => count($uploadedDocuments),
                'uploaded_by' => Auth::id()
            ]);
            
            // Always return JSON response for AJAX uploads
            return response()->json([
                'success' => true,
                'message' => count($uploadedDocuments) . ' documents uploaded successfully!',
                'redirect' => route('edms.pagetyping', $fileIndexingId)
            ]);
                
        } catch (Exception $e) {
            Log::error('Error uploading documents', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error uploading documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display page typing interface
     */
    public function pageTyping($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->with(['mainApplication', 'scannings', 'pagetypings'])->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return redirect()->back()->with('error', 'File indexing record not found.');
            }
            
            $PageTitle = 'Page Typing';
            $PageDescription = 'Document Page Classification';
            
            return view('edms.pagetyping', compact(
                'PageTitle',
                'PageDescription',
                'fileIndexing'
            ));
        } catch (Exception $e) {
            Log::error('Error loading page typing interface', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading page typing interface: ' . $e->getMessage());
        }
    }

    /**
     * Save page typing data
     */
    public function savePageTyping(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return redirect()->back()->with('error', 'File indexing record not found.');
            }
            
            $validated = $request->validate([
                'page_types' => 'required|array',
                'page_types.*.page_type' => 'required|string|max:100',
                'page_types.*.page_subtype' => 'nullable|string|max:100',
                'page_types.*.serial_number' => 'required|integer',
                'page_types.*.page_code' => 'nullable|string|max:100',
                'page_types.*.file_path' => 'required|string|max:255',
            ]);
            
            // Delete existing page typing records for this file
            PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->delete();
            
            // Create new page typing records
            foreach ($validated['page_types'] as $pageType) {
                PageTyping::on('sqlsrv')->create([
                    'file_indexing_id' => $fileIndexingId,
                    'page_type' => $pageType['page_type'],
                    'page_subtype' => $pageType['page_subtype'],
                    'serial_number' => $pageType['serial_number'],
                    'page_code' => $pageType['page_code'],
                    'file_path' => $pageType['file_path'],
                    'typed_by' => Auth::id(),
                ]);
            }
            
            // Update scanning status to reviewed
            Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->update(['status' => 'reviewed']);
            
            Log::info('Page typing completed', [
                'file_indexing_id' => $fileIndexingId,
                'page_count' => count($validated['page_types']),
                'typed_by' => Auth::id()
            ]);
            
            return redirect()->route('edms.index', $fileIndexing->main_application_id)
                ->with('success', 'Page typing completed successfully! EDMS workflow is now complete.');
                
        } catch (Exception $e) {
            Log::error('Error saving page typing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error saving page typing: ' . $e->getMessage());
        }
    }

    /**
     * Update document details
     */
    public function updateDocumentDetails(Request $request, $scanningId)
    {
        try {
            $scanning = Scanning::on('sqlsrv')->find($scanningId);
            
            if (!$scanning) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found.'
                ], 404);
            }
            
            $validated = $request->validate([
                'paper_size' => 'nullable|string|max:20',
                'document_type' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);
            
            $scanning->update($validated);
            
            Log::info('Document details updated', [
                'scanning_id' => $scanningId,
                'updated_by' => Auth::id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Document details updated successfully!'
            ]);
            
        } catch (Exception $e) {
            Log::error('Error updating document details', [
                'scanning_id' => $scanningId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating document details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get EDMS status for an application
     */
    public function getEdmsStatus($applicationId)
    {
        try {
            $application = ApplicationMother::on('sqlsrv')->with('fileIndexing.scannings', 'fileIndexing.pagetypings')->find($applicationId);
            
            if (!$application) {
                return response()->json(['error' => 'Application not found'], 404);
            }
            
            $status = [
                'has_file_indexing' => false,
                'has_scanning' => false,
                'has_page_typing' => false,
                'current_stage' => 'Not Started',
                'file_indexing_id' => null,
            ];
            
            if ($application->fileIndexing) {
                $status['has_file_indexing'] = true;
                $status['file_indexing_id'] = $application->fileIndexing->id;
                $status['current_stage'] = 'Indexed';
                
                if ($application->fileIndexing->scannings->count() > 0) {
                    $status['has_scanning'] = true;
                    $status['current_stage'] = 'Scanned';
                    
                    if ($application->fileIndexing->pagetypings->count() > 0) {
                        $status['has_page_typing'] = true;
                        $status['current_stage'] = 'Typed';
                    }
                }
            }
            
            return response()->json($status);
            
        } catch (Exception $e) {
            Log::error('Error getting EDMS status', [
                'application_id' => $applicationId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Error getting EDMS status'], 500);
        }
    }

    /**
     * Generate file title from application data
     */
    private function generateFileTitle($application)
    {
        $name = '';
        
        if ($application->applicant_type === 'individual') {
            $name = trim($application->first_name . ' ' . $application->middle_name . ' ' . $application->surname);
        } elseif ($application->applicant_type === 'corporate') {
            $name = $application->corporate_name;
        } elseif ($application->applicant_type === 'multiple') {
            $names = json_decode($application->multiple_owners_names, true);
            if (is_array($names) && count($names) > 0) {
                $name = $names[0] . ' et al.';
            }
        }
        
        $landUse = $application->land_use ?? 'Property';
        
        return $name ? "{$name}'s {$landUse}" : "Application {$application->id}";
    }

    /**
     * Generate file title for sub-application
     */
    private function generateSubApplicationFileTitle($subApplication, $motherApplication)
    {
        $name = '';
        
        if ($subApplication->applicant_type === 'individual') {
            $name = trim(($subApplication->first_name ?? '') . ' ' . ($subApplication->middle_name ?? '') . ' ' . ($subApplication->surname ?? ''));
        } elseif ($subApplication->applicant_type === 'corporate') {
            $name = $subApplication->corporate_name ?? 'Corporate Applicant';
        } elseif ($subApplication->applicant_type === 'multiple') {
            $names = json_decode($subApplication->multiple_owners_names ?? '[]', true);
            if (is_array($names) && count($names) > 0) {
                $name = $names[0] . ' et al.';
            }
        }
        
        $unitInfo = '';
        if ($subApplication->unit_number) {
            $unitInfo = "Unit {$subApplication->unit_number}";
            if ($subApplication->block_number) {
                $unitInfo .= ", Block {$subApplication->block_number}";
            }
        }
        
        $landUse = $motherApplication->land_use ?? 'Property';
        
        if ($name && $unitInfo) {
            return "{$name}'s {$landUse} - {$unitInfo}";
        } elseif ($name) {
            return "{$name}'s Unit Application";
        } elseif ($unitInfo) {
            return "{$landUse} - {$unitInfo}";
        } else {
            return "Unit Application {$subApplication->id}";
        }
    }
}