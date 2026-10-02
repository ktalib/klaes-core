<?php
 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicationMother;
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use App\Models\PageType;
use App\Models\PageSubType;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * Display the EDMS workflow for a recertification application
     */
    public function recertificationIndex($applicationId)
    {
        try {
            Log::info('EDMS Recertification Index called', ['application_id' => $applicationId]);
            
            // Get recertification application
            $application = DB::connection('sqlsrv')->table('recertification_applications')->where('id', $applicationId)->first();
            
            if (!$application) {
                Log::error('Recertification application not found for EDMS workflow', [
                    'application_id' => $applicationId
                ]);
                
                return redirect()->back()->with('error', 'Recertification application not found. Please ensure the application exists.');
            }
            
            // Try to get file indexing for recertification application
            $fileIndexing = null;
            try {
                $fileIndexing = FileIndexing::on('sqlsrv')->where('recertification_application_id', $applicationId)->first();
            } catch (Exception $e) {
                Log::warning('File indexing query failed for recertification', [
                    'application_id' => $applicationId,
                    'error' => $e->getMessage()
                ]);
            }
            
            $PageTitle = 'EDMS Workflow - Recertification';
            $PageDescription = 'Electronic Document Management System for Recertification Application';
            
            return view('primaryform.edms', compact(
                'PageTitle',
                'PageDescription', 
                'application',
                'fileIndexing'
            ))->with('applicationType', 'recertification');
            
        } catch (Exception $e) {
            Log::error('Error loading EDMS workflow for recertification', [
                'application_id' => $applicationId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading EDMS workflow: ' . $e->getMessage());
        }
    }

    /**
     * Create file indexing record for a recertification application
     */
    public function createRecertificationFileIndexing($applicationId)
    {
        try {
            // Get recertification application
            $application = DB::connection('sqlsrv')->table('recertification_applications')->where('id', $applicationId)->first();
            
            if (!$application) {
                Log::error('Recertification application not found for file indexing', [
                    'application_id' => $applicationId
                ]);
                
                return redirect()->back()->with('error', 'Recertification application not found. Please ensure the application exists before creating file indexing.');
            }
            
            // Check if file indexing already exists
            $existingFileIndexing = FileIndexing::on('sqlsrv')->where('recertification_application_id', $applicationId)->first();
            if ($existingFileIndexing) {
                return redirect()->route('edms.fileindexing', $existingFileIndexing->id)
                    ->with('info', 'File indexing already exists for this recertification application.');
            }
            
            // Create file indexing record using recertification application data
            $fileIndexing = FileIndexing::on('sqlsrv')->create([
                'recertification_application_id' => $application->id,
                'file_number' => $application->file_number ?? 'RECERT-' . $application->id,
                'file_title' => $this->generateRecertificationFileTitle($application),
                'land_use_type' => $application->land_use ?? 'Residential',
                'plot_number' => $application->plot_number ?? null,
                'district' => $application->layout_district ?? null,
                'lga' => $application->lga_name ?? null,
                'has_cofo' => false,
                'is_merged' => false,
                'has_transaction' => false,
                'is_problematic' => false,
            ]);
            
            Log::info('Recertification file indexing created', [
                'application_id' => $applicationId,
                'file_indexing_id' => $fileIndexing->id
            ]);
            
            return redirect()->route('edms.fileindexing', $fileIndexing->id)
                ->with('success', 'Recertification file indexing record created successfully!');
                
        } catch (Exception $e) {
            Log::error('Error creating recertification file indexing', [
                'application_id' => $applicationId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error creating file indexing: ' . $e->getMessage());
        }
    }

    /**
     * Generate file title for recertification application
     */
    private function generateRecertificationFileTitle($application)
    {
        $name = '';
        
        if ($application->applicant_type === 'Corporate') {
            $name = $application->organisation_name ?? 'Corporate Applicant';
        } else {
            $name = trim(($application->surname ?? '') . ' ' . ($application->first_name ?? ''));
            if (empty($name)) {
                $name = 'Individual Applicant';
            }
        }
        
        return $name ?: "Recertification Application {$application->id}";
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
                    'file_title' => $application->fileno ?? 'Unit-' . $application->id,
                    'land_use_type' => $motherApplication ? ($motherApplication->land_use ?? 'Residential') : 'Residential',
                    'plot_number' => $motherApplication ? $motherApplication->property_plot_no : null,
                    'tp_no' => $motherApplication ? ($motherApplication->tp_no ?? null) : null,
                    'location' => $motherApplication ? ($motherApplication->location ?? null) : null,
                    'district' => $motherApplication ? $motherApplication->property_district : null,
                    'lga' => $motherApplication ? $motherApplication->property_lga : null,
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
                    'tp_no' => $application->tp_no ?? null,
                    'location' => $application->location ?? null,
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
                
                return redirect()->back()
                    ->with('error', 'File indexing record not found. Please create a new file index first.');
            }
            
            // Validate and fix file number if needed
            if ($fileIndexing->mainApplication && $fileIndexing->mainApplication->np_fileno) {
                $correctFileNumber = $fileIndexing->mainApplication->np_fileno;
                
                if ($fileIndexing->file_number !== $correctFileNumber) {
                    Log::info('File number mismatch detected, fixing...', [
                        'file_indexing_id' => $fileIndexingId,
                        'current_file_number' => $fileIndexing->file_number,
                        'correct_file_number' => $correctFileNumber
                    ]);
                    
                    $fileIndexing->file_number = $correctFileNumber;
                    $fileIndexing->save();
                    
                    // Reload the model to get updated data
                    $fileIndexing = FileIndexing::on('sqlsrv')->with('mainApplication')->find($fileIndexingId);
                    
                    Log::info('File number corrected', [
                        'file_indexing_id' => $fileIndexingId,
                        'updated_file_number' => $fileIndexing->file_number
                    ]);
                }
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
                'tracking_id' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^TRK-[A-Z0-9]{8}-[A-Z0-9]{5}$/',
                ],
                'file_title' => 'required|string|max:255',
                'land_use_type' => 'required|string|max:100',
                'plot_number' => 'nullable|string|max:100',
                'tp_no' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'district' => 'nullable|string|max:100',
                'lga' => 'nullable|string|max:100',
                'serial_no' => 'nullable|string|max:100',
                'registry' => 'nullable|string|max:255',
                'batch_no' => 'nullable|string|max:100',
                'shelf_location' => 'nullable|string|max:255',
                'shelf_label_id' => 'nullable|integer',
                'location' => 'nullable|string',
                'has_cofo' => 'boolean',
                'is_merged' => 'boolean',
                'has_transaction' => 'boolean',
                'is_problematic' => 'boolean',
                'is_co_owned_plot' => 'boolean',
            ]);

            // Custom validation for tracking_id uniqueness using SQL Server connection
            $existingTrackingId = FileIndexing::on('sqlsrv')
                ->where('tracking_id', $validated['tracking_id'])
                ->where('id', '!=', $fileIndexingId)
                ->first();

            if ($existingTrackingId) {
                return redirect()->back()
                    ->withErrors(['tracking_id' => 'The tracking ID has already been taken.'])
                    ->withInput();
            }
            
            $fileIndexing->update($validated);
            
            // Mark the specific shelf label as used if provided (shelf_label_id)
            if (isset($validated['shelf_label_id']) && !empty($validated['shelf_label_id'])) {
                try {
                    // Check if the Rack_Shelf_Labels table exists
                    $tableExists = DB::connection('sqlsrv')->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Rack_Shelf_Labels'");

                    if (!empty($tableExists)) {
                        DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                            ->where('id', $validated['shelf_label_id'])
                            ->update(['is_used' => 1]);
                        
                        Log::info('Shelf label marked as used', [
                            'shelf_label_id' => $validated['shelf_label_id'],
                            'file_indexing_id' => $fileIndexingId
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log the error but don't fail the file indexing update
                    Log::error('Failed to mark shelf_label as used: ' . $e->getMessage(), [
                        'shelf_label_id' => $validated['shelf_label_id'],
                        'file_indexing_id' => $fileIndexingId
                    ]);
                }
            }
            
            Log::info('File indexing updated', [
                'file_indexing_id' => $fileIndexingId,
                'updated_by' => Auth::id(),
                'batch_no' => $validated['batch_no'] ?? null,
                'shelf_location' => $validated['shelf_location'] ?? null
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
            $fileNumber = $fileIndexing->file_number ?? 'FILE-' . $fileIndexingId;
            
            foreach ($request->file('documents') as $index => $document) {
                // Generate sequential filename like: ST-COM-2025-01-002_0008.pdf
                $extension = $document->getClientOriginalExtension();
                $sequentialNumber = str_pad($index + 1, 4, '0', STR_PAD_LEFT);
                $fileName = $fileNumber . '_' . $sequentialNumber . '.' . $extension;
                
                // Store in EDMS/SCAN_UPLOAD/{fileNumber}/ directory
                $uploadPath = 'EDMS/SCAN_UPLOAD/' . $fileNumber;
                $path = $document->storeAs($uploadPath, $fileName, 'public');
                
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
                'file_number' => $fileNumber,
                'upload_path' => $uploadPath,
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
            $fileIndexing = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.pageType', 'pagetypings.pageSubType'])
                ->find($fileIndexingId);
            
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
                'page_types.*.file_path' => 'required|string|max:255',
                'page_types.*.page_number' => 'required|integer|min:1',
                'page_types.*.scanning_id' => 'nullable|integer|exists:scannings,id',
                'page_types.*.cover_type_id' => 'nullable|integer|exists:CoverType,Id',
                'page_types.*.page_type' => 'required',
                'page_types.*.page_type_others' => 'nullable|string|max:100',
                'page_types.*.page_subtype' => 'nullable',
                'page_types.*.page_subtype_others' => 'nullable|string|max:100',
                'page_types.*.serial_number' => 'required|string|max:10',
                'page_types.*.page_code' => 'nullable|string|max:100',
            ]);

            // Delete existing page typing records for this file
            PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->delete();

            $savedCount = 0;
            $errors = [];

            foreach ($validated['page_types'] as $index => $pageTypeData) {
                try {
                    $this->persistClassification($pageTypeData, $fileIndexing);
                    $savedCount++;
                } catch (ValidationException $validationException) {
                    $errors[$index] = $validationException->errors();
                } catch (Exception $classificationException) {
                    $errors[$index] = [$classificationException->getMessage()];

                    Log::error('Error saving page typing entry', [
                        'file_indexing_id' => $fileIndexingId,
                        'index' => $index,
                        'error' => $classificationException->getMessage()
                    ]);
                }
            }

            // Update scanning status to reviewed
            Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->update(['status' => 'scanned']);

            Log::info('Page typing completed', [
                'file_indexing_id' => $fileIndexingId,
                'page_count' => count($validated['page_types']),
                'saved_count' => $savedCount,
                'error_count' => count($errors),
                'typed_by' => Auth::id()
            ]);

            $successMessage = 'Page typing completed successfully! EDMS workflow is now complete.';
            if (!empty($errors)) {
                $successMessage = sprintf(
                    'Page typing completed with %d of %d pages saved. Please review any flagged pages.',
                    $savedCount,
                    count($validated['page_types'])
                );
            }

            // Determine redirect based on application type
            if ($fileIndexing->recertification_application_id) {
                return redirect()->route('edms.recertification.index', $fileIndexing->recertification_application_id)
                    ->with('success', $successMessage)
                    ->with('page_typing_errors', $errors);
            } elseif ($fileIndexing->subapplication_id) {
                return redirect()->route('edms.index', [$fileIndexing->main_application_id, 'sub'])
                    ->with('success', $successMessage)
                    ->with('page_typing_errors', $errors);
            } elseif ($fileIndexing->main_application_id) {
                return redirect()->route('edms.index', $fileIndexing->main_application_id)
                    ->with('success', $successMessage)
                    ->with('page_typing_errors', $errors);
            }

            // Fallback if no application ID is found
            return redirect()->back()
                ->with('success', $successMessage)
                ->with('page_typing_errors', $errors);
                
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
        
        return $name ?: "Application {$application->id}";
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
        
        return $name ?: "Unit Application {$subApplication->id}";
    }

    /**
     * Get PDF page information including page count
     */
    public function getPdfPageInfo($documentPath)
    {
        try {
            // Default response
            $result = [
                'page_count' => 1,
                'file_size' => 0,
                'file_type' => 'unknown',
                'error' => null
            ];
            
            // Check if file exists
            $fullPath = file_storage_path('app/public/' . $documentPath);
            
            if (!file_exists($fullPath)) {
                Log::warning('PDF file not found for page info', ['path' => $fullPath]);
                $result['error'] = 'File not found';
                return $result;
            }
            
            // Get file info
            $result['file_size'] = filesize($fullPath);
            $result['file_type'] = mime_content_type($fullPath) ?: 'application/octet-stream';
            
            // Check if it's actually a PDF
            if (!str_contains(strtolower($documentPath), '.pdf') && !str_contains($result['file_type'], 'pdf')) {
                // Not a PDF, return single page
                $result['file_type'] = 'image';
                return $result;
            }
            
            // Try to get PDF page count using different methods
            $pageCount = $this->getPdfPageCount($fullPath);
            
            if ($pageCount > 0) {
                $result['page_count'] = $pageCount;
            }
            
            Log::info('PDF page info retrieved', [
                'path' => $documentPath,
                'page_count' => $result['page_count'],
                'file_size' => $result['file_size']
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Error getting PDF page info', [
                'path' => $documentPath,
                'error' => $e->getMessage()
            ]);
            
            return [
                'page_count' => 1,
                'file_size' => 0,
                'file_type' => 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get PDF page count using multiple methods
     */
    private function getPdfPageCount($filePath)
    {
        try {
            // Method 1: Try using regex to count pages (fastest)
            $pageCount = $this->getPdfPageCountRegex($filePath);
            if ($pageCount > 0) {
                return $pageCount;
            }
            
            // Method 2: Try using shell command if available
            if (function_exists('shell_exec') && !empty(shell_exec('which pdfinfo'))) {
                $pageCount = $this->getPdfPageCountShell($filePath);
                if ($pageCount > 0) {
                    return $pageCount;
                }
            }
            
            // Method 3: Try using Imagick if available
            if (extension_loaded('imagick')) {
                $pageCount = $this->getPdfPageCountImagick($filePath);
                if ($pageCount > 0) {
                    return $pageCount;
                }
            }
            
            // Default to 1 page if all methods fail
            return 1;
            
        } catch (Exception $e) {
            Log::warning('Error counting PDF pages', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Get PDF page count using regex pattern matching
     */
    private function getPdfPageCountRegex($filePath)
    {
        try {
            // Read first 1MB of file to find page count
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                return 0;
            }
            
            $content = fread($handle, 1024 * 1024); // Read first 1MB
            fclose($handle);
            
            if (!$content) {
                return 0;
            }
            
            // Look for /Count pattern in PDF
            if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
                return (int)$matches[1];
            }
            
            // Alternative pattern: look for /N pattern
            if (preg_match('/\/N\s+(\d+)/', $content, $matches)) {
                return (int)$matches[1];
            }
            
            // Count page objects (less reliable but worth trying)
            $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $content);
            if ($pageCount > 0) {
                return $pageCount;
            }
            
            return 0;
            
        } catch (Exception $e) {
            Log::warning('Regex PDF page count failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get PDF page count using shell command
     */
    private function getPdfPageCountShell($filePath)
    {
        try {
            $escapedPath = escapeshellarg($filePath);
            $output = shell_exec("pdfinfo $escapedPath 2>/dev/null | grep Pages | awk '{print $2}'");
            
            if ($output && is_numeric(trim($output))) {
                return (int)trim($output);
            }
            
            return 0;
            
        } catch (Exception $e) {
            Log::warning('Shell PDF page count failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get PDF page count using Imagick
     */
    private function getPdfPageCountImagick($filePath)
    {
        try {
            $imagick = new \Imagick();
            $imagick->pingImage($filePath);
            $pageCount = $imagick->getNumberImages();
            $imagick->clear();
            
            return $pageCount;
            
        } catch (Exception $e) {
            Log::warning('Imagick PDF page count failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Save single page typing data (AJAX endpoint)
     */
    public function saveSinglePageTyping(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.'
                ], 404);
            }

            $validated = $request->validate([
                'file_path' => 'required|string',
                'page_number' => 'required|integer|min:1',
                'scanning_id' => 'required|integer|exists:scannings,id',
                'cover_type_id' => 'nullable|integer|exists:CoverType,Id',
                'page_type' => 'required',
                'page_type_others' => 'nullable|string|max:100',
                'page_subtype' => 'nullable',
                'page_subtype_others' => 'nullable|string|max:100',
                'serial_number' => 'required|string|max:10',
                'page_code' => 'nullable|string|max:100'
            ]);

            $result = $this->persistClassification($validated, $fileIndexing);

            /** @var PageTyping $pageTyping */
            $pageTyping = $result['page_typing'];
            $sync = $result['sync'];
            $pageTypeName = $result['page_type_name'];
            $pageSubTypeName = $result['page_subtype_name'];

            return response()->json([
                'success' => true,
                'message' => 'Page classification saved successfully!',
                'page_typing_id' => $pageTyping->id,
                'updated_file_path' => $sync['path'] ?? $pageTyping->file_path,
                'updated_display_name' => $sync['display_name'] ?? basename($pageTyping->file_path),
                'updated_file_url' => $sync['public_url'] ?? null,
                'page_type_name' => $pageTypeName,
                'page_subtype_name' => $pageSubTypeName,
                'locked_at' => optional($pageTyping->updated_at)->toDateTimeString(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error saving single page typing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving page classification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch save page typing data (AJAX endpoint)
     */
    public function batchSavePageTyping(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.'
                ], 404);
            }
            
            $validated = $request->validate([
                'pages' => 'required|array',
                'pages.*.file_path' => 'required|string',
                'pages.*.page_number' => 'required|integer|min:1',
                'pages.*.scanning_id' => 'required|integer|exists:scannings,id',
                'pages.*.cover_type_id' => 'nullable|integer|exists:CoverType,Id',
                'pages.*.page_type' => 'required',
                'pages.*.page_type_others' => 'nullable|string|max:100',
                'pages.*.page_subtype' => 'nullable',
                'pages.*.page_subtype_others' => 'nullable|string|max:100',
                'pages.*.serial_number' => 'required|string|max:10',
                'pages.*.page_code' => 'nullable|string|max:100'
            ]);

            $savedCount = 0;
            $errors = [];
            $updates = [];

            foreach ($validated['pages'] as $index => $pageData) {
                try {
                    $result = $this->persistClassification($pageData, $fileIndexing);
                    $savedCount++;

                    $pageTyping = $result['page_typing'];
                    $sync = $result['sync'];

                    $updates[] = [
                        'index' => $index,
                        'page_typing_id' => $pageTyping->id,
                        'updated_file_path' => $sync['path'] ?? $pageTyping->file_path,
                        'updated_display_name' => $sync['display_name'] ?? basename($pageTyping->file_path),
                        'page_type_name' => $result['page_type_name'],
                        'page_subtype_name' => $result['page_subtype_name'],
                    ];
                } catch (ValidationException $validationException) {
                    $errors[] = [
                        'index' => $index,
                        'page_number' => $pageData['page_number'] ?? 'unknown',
                        'error' => $validationException->errors()
                    ];
                } catch (Exception $pageException) {
                    $errors[] = [
                        'index' => $index,
                        'page_number' => $pageData['page_number'] ?? 'unknown',
                        'error' => $pageException->getMessage()
                    ];

                    Log::error('Error saving page in batch', [
                        'file_indexing_id' => $fileIndexingId,
                        'page_index' => $index,
                        'error' => $pageException->getMessage()
                    ]);
                }
            }

            Log::info('Batch page typing completed', [
                'file_indexing_id' => $fileIndexingId,
                'total_pages' => count($validated['pages']),
                'saved_count' => $savedCount,
                'error_count' => count($errors),
                'typed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Batch save completed! {$savedCount} pages saved successfully.",
                'saved_count' => $savedCount,
                'total_count' => count($validated['pages']),
                'errors' => $errors,
                'updates' => $updates
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error in batch save page typing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error in batch save operation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finish page typing and complete EDMS workflow
     */
    public function finishPageTyping(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.'
                ], 404);
            }
            
            $validated = $request->validate([
                'classifications' => 'required|array',
                'classifications.*.file_path' => 'required|string',
                'classifications.*.page_number' => 'required|integer|min:1',
                'classifications.*.scanning_id' => 'required|integer|exists:scannings,id',
                'classifications.*.cover_type_id' => 'nullable|integer|exists:CoverType,Id',
                'classifications.*.page_type' => 'required',
                'classifications.*.page_type_others' => 'nullable|string|max:100',
                'classifications.*.page_subtype' => 'nullable',
                'classifications.*.page_subtype_others' => 'nullable|string|max:100',
                'classifications.*.serial_number' => 'required|string|max:10',
                'classifications.*.page_code' => 'nullable|string|max:100'
            ]);

            // Delete existing page typing records for this file to ensure clean slate
            PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->delete();

            $savedCount = 0;
            $errors = [];

            foreach ($validated['classifications'] as $index => $classification) {
                try {
                    $this->persistClassification($classification, $fileIndexing);
                    $savedCount++;
                } catch (ValidationException $validationException) {
                    $errors[$index] = $validationException->errors();
                } catch (Exception $classificationException) {
                    $errors[$index] = [$classificationException->getMessage()];

                    Log::error('Error saving classification in finish', [
                        'file_indexing_id' => $fileIndexingId,
                        'classification' => $classification,
                        'error' => $classificationException->getMessage()
                    ]);
                }
            }

            // Update scanning status to completed
            Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->update(['status' => 'completed']);

            Log::info('Page typing workflow completed', [
                'file_indexing_id' => $fileIndexingId,
                'total_classifications' => count($validated['classifications']),
                'saved_count' => $savedCount,
                'error_count' => count($errors),
                'typed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Page typing completed successfully! {$savedCount} pages classified.",
                'saved_count' => $savedCount,
                'total_count' => count($validated['classifications']),
                'errors' => $errors
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error finishing page typing', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error completing page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Persist a single page classification entry and synchronize related metadata.
     *
     * @throws ValidationException
     */
    protected function persistClassification(array $data, FileIndexing $fileIndexing): array
    {
        $authUserId = Auth::id();

        return DB::connection('sqlsrv')->transaction(function () use ($data, $fileIndexing, $authUserId) {
            $scanning = $this->resolveScanningForClassification($data, $fileIndexing);

            $pageTypeContext = $this->determinePageTypeContext($data['page_type'] ?? null, $data['page_type_others'] ?? null);
            $pageSubTypeContext = $this->determinePageSubTypeContext(
                $data['page_subtype'] ?? null,
                $pageTypeContext['id'],
                $data['page_subtype_others'] ?? null
            );

            $pageNumber = isset($data['page_number']) ? (int)$data['page_number'] : 1;
            $serialNumber = isset($data['serial_number']) ? trim((string)$data['serial_number']) : '';
            $pageCode = isset($data['page_code']) ? trim((string)$data['page_code']) : null;
            $coverTypeId = isset($data['cover_type_id']) ? (int)$data['cover_type_id'] : null;

            if ($serialNumber === '') {
                throw ValidationException::withMessages([
                    'serial_number' => ['Serial number is required.']
                ]);
            }

            $attributes = [
                'file_indexing_id' => $fileIndexing->id,
                'scanning_id' => $scanning->id,
                'page_number' => $pageNumber,
                'cover_type_id' => $coverTypeId,
                'page_type' => $pageTypeContext['id'],
                'page_type_others' => $pageTypeContext['id'] ? null : $pageTypeContext['name'],
                'page_subtype' => $pageSubTypeContext['id'],
                'page_subtype_others' => $pageSubTypeContext['id'] ? null : $pageSubTypeContext['name'],
                'serial_number' => $serialNumber,
                'page_code' => $pageCode,
                'file_path' => $scanning->document_path,
                'typed_by' => $authUserId,
            ];

            $existingPageTyping = PageTyping::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexing->id)
                ->where(function ($query) use ($scanning, $data) {
                    $query->where('scanning_id', $scanning->id);

                    if (!empty($data['file_path'])) {
                        $query->orWhere('file_path', $this->normalizeDocumentPath($data['file_path']));
                    }
                })
                ->where('page_number', $pageNumber)
                ->first();

            if ($existingPageTyping) {
                $existingPageTyping->fill($attributes);
                $existingPageTyping->updated_at = now();
                $existingPageTyping->save();
                $pageTyping = $existingPageTyping->fresh();
            } else {
                $pageTyping = PageTyping::on('sqlsrv')->create($attributes);
            }

            $sync = $this->syncClassifiedFileMetadata(
                $pageTyping,
                $scanning,
                $pageTypeContext['name'],
                $pageSubTypeContext['name']
            );

            $pageTyping->refresh();

            return [
                'page_typing' => $pageTyping,
                'scanning' => $scanning->fresh(),
                'page_type_name' => $pageTypeContext['name'],
                'page_subtype_name' => $pageSubTypeContext['name'],
                'sync' => $sync,
            ];
        });
    }

    /**
     * Resolve the scanning record associated with a classification payload.
     *
     * @throws ValidationException
     */
    protected function resolveScanningForClassification(array $data, FileIndexing $fileIndexing): Scanning
    {
        $normalizedPath = $this->normalizeDocumentPath($data['file_path'] ?? null);
        $scanning = null;

        if (!empty($data['scanning_id'])) {
            $scanning = Scanning::on('sqlsrv')->find((int)$data['scanning_id']);
        }

        if (!$scanning && $normalizedPath) {
            $scanning = Scanning::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexing->id)
                ->where(function ($query) use ($normalizedPath) {
                    $query->where('document_path', $normalizedPath)
                        ->orWhere('document_path', 'like', '%' . $normalizedPath)
                        ->orWhere('original_filename', basename($normalizedPath));
                })
                ->first();
        }

        if (!$scanning || $scanning->file_indexing_id !== $fileIndexing->id) {
            throw ValidationException::withMessages([
                'scanning_id' => ['Unable to locate the document for this page selection.']
            ]);
        }

        return $scanning;
    }

    /**
     * Determine the page type context (ID and display name) from raw input.
     */
    protected function determinePageTypeContext($rawValue, ?string $others = null): array
    {
        if ($rawValue === null || $rawValue === '') {
            throw ValidationException::withMessages([
                'page_type' => ['Page type is required.']
            ]);
        }

        if (is_numeric($rawValue)) {
            $pageType = PageType::on('sqlsrv')->find((int)$rawValue);
            if (!$pageType) {
                throw ValidationException::withMessages([
                    'page_type' => ['The selected page type is invalid.']
                ]);
            }

            return ['id' => (int)$pageType->id, 'name' => $pageType->PageType];
        }

        if ($rawValue === 'others') {
            $name = trim((string)$others);
            if ($name === '') {
                throw ValidationException::withMessages([
                    'page_type_others' => ['Please specify the page type when selecting Others.']
                ]);
            }

            return ['id' => null, 'name' => $name];
        }

        $legacy = PageType::on('sqlsrv')->where('PageType', trim((string)$rawValue))->first();
        if ($legacy) {
            return ['id' => (int)$legacy->id, 'name' => $legacy->PageType];
        }

        throw ValidationException::withMessages([
            'page_type' => ['The selected page type is invalid.']
        ]);
    }

    /**
     * Determine the page subtype context (ID and display name) from raw input.
     */
    protected function determinePageSubTypeContext($rawValue, ?int $pageTypeId = null, ?string $others = null): array
    {
        if ($rawValue === null || $rawValue === '' || $rawValue === 'null') {
            $name = trim((string)$others);
            return ['id' => null, 'name' => $name !== '' ? $name : null];
        }

        if ($rawValue === 'others') {
            $name = trim((string)$others);
            if ($name === '') {
                throw ValidationException::withMessages([
                    'page_subtype_others' => ['Please specify the page subtype when selecting Others.']
                ]);
            }

            return ['id' => null, 'name' => $name];
        }

        if (is_numeric($rawValue)) {
            $subType = PageSubType::on('sqlsrv')->find((int)$rawValue);
            if (!$subType) {
                throw ValidationException::withMessages([
                    'page_subtype' => ['The selected page subtype is invalid.']
                ]);
            }

            if ($pageTypeId && (int)$subType->PageTypeId !== $pageTypeId) {
                throw ValidationException::withMessages([
                    'page_subtype' => ['The selected subtype does not belong to the chosen page type.']
                ]);
            }

            return ['id' => (int)$subType->id, 'name' => $subType->PageSubType];
        }

        $query = PageSubType::on('sqlsrv')->where('PageSubType', trim((string)$rawValue));
        if ($pageTypeId) {
            $query->where('PageTypeId', $pageTypeId);
        }

        $legacy = $query->first();
        if ($legacy) {
            return ['id' => (int)$legacy->id, 'name' => $legacy->PageSubType];
        }

        $name = trim((string)$rawValue);
        return ['id' => null, 'name' => $name !== '' ? $name : null];
    }

    /**
     * Rename the underlying document to reflect its classification and keep references in sync.
     */
    protected function syncClassifiedFileMetadata(PageTyping $pageTyping, Scanning $scanning, string $pageTypeName, ?string $pageSubTypeName = null): array
    {
        $disk = Storage::disk('public');
        $originalPath = $this->normalizeDocumentPath($scanning->document_path);

        if (!$originalPath) {
            $existingPath = $this->normalizeDocumentPath($pageTyping->file_path);
            return [
                'path' => $existingPath,
                'display_name' => $existingPath ? basename($existingPath) : null,
                'public_url' => $existingPath && $disk->exists($existingPath) ? $disk->url($existingPath) : null,
            ];
        }

        $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'pdf');
        $newFileName = $this->generateClassifiedFilename($pageTypeName, $pageSubTypeName, $extension);

        $directory = trim(str_replace('\\', '/', dirname($originalPath)), '.');
        $directory = ltrim($directory, './');
        $relativeDirectory = $directory ? $directory . '/' : '';
        $baseName = pathinfo($newFileName, PATHINFO_FILENAME);
        $targetPath = $relativeDirectory . $newFileName;

        $targetPath = $this->resolveFilenameCollision(
            $disk,
            $relativeDirectory,
            $baseName,
            $extension,
            (int)$scanning->id,
            $targetPath
        );

        if ($originalPath !== $targetPath && $disk->exists($originalPath)) {
            if ($relativeDirectory && !$disk->exists($relativeDirectory)) {
                $disk->makeDirectory($relativeDirectory);
            }

            $disk->move($originalPath, $targetPath);
        } elseif (!$disk->exists($originalPath)) {
            Log::warning('Original document missing during classification rename', [
                'scanning_id' => $scanning->id,
                'expected_path' => $originalPath
            ]);
            $targetPath = $originalPath;
        }

        $publicUrl = $disk->exists($targetPath) ? $disk->url($targetPath) : null;

        $scanning->document_path = $targetPath;
        $scanning->original_filename = basename($targetPath);
        $scanning->save();

        PageTyping::on('sqlsrv')
            ->where('scanning_id', $scanning->id)
            ->update(['file_path' => $targetPath]);

        $pageTyping->file_path = $targetPath;
        $pageTyping->save();

        return [
            'path' => $targetPath,
            'display_name' => basename($targetPath),
            'public_url' => $publicUrl,
        ];
    }

    /**
     * Generate a sanitized filename reflecting the page type/subtype combination.
     */
    protected function generateClassifiedFilename(string $pageTypeName, ?string $pageSubTypeName, string $extension): string
    {
        $parts = array_filter([trim($pageTypeName), $pageSubTypeName ? trim($pageSubTypeName) : null]);
        $label = implode(' ', $parts);
        if ($label === '') {
            $label = 'Classified Page';
        }

        $label = preg_replace('/[<>:"\\\\\/\|\?\*]/', '', $label);
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);
        $label = (string) Str::of($label)->limit(120, '');

        if ($label === '') {
            $label = 'Classified Page';
        }

        return '[' . $label . '].' . strtolower($extension ?: 'pdf');
    }

    /**
     * Resolve filename collisions by appending the scanning identifier.
     */
    protected function resolveFilenameCollision($disk, string $directory, string $baseName, string $extension, int $scanningId, string $initialPath): string
    {
        $path = $initialPath;
        $counter = 1;
        $prefix = $directory ? $directory : '';

        while ($disk->exists($path)) {
            $suffix = $counter === 1 ? "-scan{$scanningId}" : "-scan{$scanningId}-{$counter}";
            $path = $prefix . $baseName . $suffix . '.' . $extension;
            $counter++;
        }

        return $path;
    }

    /**
     * Normalize stored document paths for consistent comparisons.
     */
    protected function normalizeDocumentPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('/^storage\//', '', $normalized);

        return $normalized;
    }

    /**
     * Get page typing data for dropdowns (AJAX endpoint)
     */
    public function getPageTypingData()
    {
        try {
            // Load Cover Types from database
            $coverTypes = DB::connection('sqlsrv')
                ->table('CoverType')
                ->select('Id as id', 'Name as name')
                ->get()
                ->map(function ($coverType) {
                    // Generate code from name (FC for Front Cover, BC for Back Cover)
                    $code = '';
                    if (stripos($coverType->name, 'front') !== false) {
                        $code = 'FC';
                    } elseif (stripos($coverType->name, 'back') !== false) {
                        $code = 'BC';
                    } else {
                        // Generate code from first letters of words
                        $words = explode(' ', $coverType->name);
                        $code = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    }
                    
                    return [
                        'id' => (string)$coverType->id,
                        'name' => $coverType->name,
                        'code' => $code
                    ];
                })
                ->toArray();

            // Load Page Types from database
            $pageTypes = DB::connection('sqlsrv')
                ->table('PageType')
                ->select('id', 'PageType as name')
                ->get()
                ->map(function ($pageType) {
                    // Generate code from name
                    $words = explode(' ', $pageType->name);
                    $code = '';
                    foreach ($words as $word) {
                        $code .= strtoupper(substr($word, 0, 1));
                    }
                    // Limit to 4 characters max
                    $code = substr($code, 0, 4);
                    
                    return [
                        'id' => (string)$pageType->id,
                        'name' => $pageType->name,
                        'code' => $code
                    ];
                })
                ->toArray();

            // Load Page Sub Types from database grouped by PageTypeId
            $pageSubTypesRaw = DB::connection('sqlsrv')
                ->table('PageSubType')
                ->select('id', 'PageTypeId', 'PageSubType as name')
                ->get();

            $pageSubTypes = [];
            foreach ($pageSubTypesRaw as $subType) {
                $pageTypeId = (string)$subType->PageTypeId;
                if (!isset($pageSubTypes[$pageTypeId])) {
                    $pageSubTypes[$pageTypeId] = [];
                }
                
                // Generate code from name
                $words = explode(' ', $subType->name);
                $code = '';
                foreach ($words as $word) {
                    $code .= strtoupper(substr($word, 0, 1));
                }
                // Limit to 4 characters max
                $code = substr($code, 0, 4);
                
                $pageSubTypes[$pageTypeId][] = [
                    'id' => (string)$subType->id,
                    'name' => $subType->name,
                    'code' => $code
                ];
            }

            return response()->json([
                'success' => true,
                'coverTypes' => $coverTypes,
                'pageTypes' => $pageTypes,
                'pageSubTypes' => $pageSubTypes
            ]);

        } catch (Exception $e) {
            Log::error('Error getting page typing data from database', [
                'error' => $e->getMessage()
            ]);

            // Fallback to default data if database query fails
            $coverTypes = [
                ['id' => '1', 'code' => 'FC', 'name' => 'Front Cover'],
                ['id' => '2', 'code' => 'BC', 'name' => 'Back Cover']
            ];

            $pageTypes = [
                ['id' => '1', 'code' => 'FC', 'name' => 'File Cover'],
                ['id' => '2', 'code' => 'APP', 'name' => 'Application'],
                ['id' => '3', 'code' => 'BN', 'name' => 'Bill Notice'],
                ['id' => '4', 'code' => 'COR', 'name' => 'Correspondence'],
                ['id' => '5', 'code' => 'LT', 'name' => 'Land Title'],
                ['id' => '6', 'code' => 'LEG', 'name' => 'Legal'],
                ['id' => '7', 'code' => 'PE', 'name' => 'Payment Evidence'],
                ['id' => '8', 'code' => 'REP', 'name' => 'Report'],
                ['id' => '9', 'code' => 'SUR', 'name' => 'Survey'],
                ['id' => '10', 'code' => 'MISC', 'name' => 'Miscellaneous'],
                ['id' => '11', 'code' => 'IMG', 'name' => 'Image'],
                ['id' => '12', 'code' => 'TP', 'name' => 'Town Planning']
            ];

            $pageSubTypes = [
                '1' => [
                    ['id' => '1', 'code' => 'NFC', 'name' => 'New File Cover'],
                    ['id' => '2', 'code' => 'OFC', 'name' => 'Old File Cover']
                ],
                '2' => [
                    ['id' => '3', 'code' => 'CO', 'name' => 'Certificate of Occupancy'],
                    ['id' => '4', 'code' => 'REV', 'name' => 'Revalidation']
                ],
                '3' => [
                    ['id' => '7', 'code' => 'DGR', 'name' => 'Demand for Ground Rent'],
                    ['id' => '34', 'code' => 'DN', 'name' => 'Demand Notice']
                ],
                '4' => [
                    ['id' => '8', 'code' => 'AL', 'name' => 'Acknowledgment Letter'],
                    ['id' => '9', 'code' => 'ASR', 'name' => 'Application Submission']
                ],
                '5' => [
                    ['id' => '5', 'code' => 'CO', 'name' => 'Certificate of Occupancy'],
                    ['id' => '6', 'code' => 'SP', 'name' => 'Survey Plan']
                ],
                '6' => [
                    ['id' => '18', 'code' => 'AGR', 'name' => 'Agreement'],
                    ['id' => '44', 'code' => 'POA', 'name' => 'Power of Attorney']
                ]
            ];

            return response()->json([
                'success' => true,
                'coverTypes' => $coverTypes,
                'pageTypes' => $pageTypes,
                'pageSubTypes' => $pageSubTypes
            ]);

        } catch (Exception $e) {
            Log::error('Error getting page typing data', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading page typing data: ' . $e->getMessage()
            ], 500);
        }
    }
}

