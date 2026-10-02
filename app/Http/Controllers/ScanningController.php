<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\Registry;
use Exception;

class ScanningController extends Controller
{
    /**
     * Display the scanning dashboard
     */
    public function index(Request $request)
    {
        try {
            $PageTitle = 'Document Upload';
            $PageDescription = 'Upload scanned documents to their digital folders';

            // Get file_indexing_id from request if provided
            $fileIndexingId = $request->get('file_indexing_id');
            $selectedFileIndexing = null;

            if ($fileIndexingId) {
                $selectedFileIndexing = FileIndexing::on('sqlsrv')
                    ->with(['mainApplication', 'scannings'])
                    ->find($fileIndexingId);
            }

            // Get statistics for dashboard
            $stats = [
                'uploads_today' => $this->getUploadsTodayCount(),
                'pending_page_typing' => $this->getPendingPageTypingCount(),
                'total_scanned' => Scanning::on('sqlsrv')->count(),
            ];

            // Get recent scanning records grouped by FileNo (one row per FileNo)
            $recentScans = $this->getGroupedScannedFiles();

            return view('scanning.index', compact(
                'PageTitle',
                'PageDescription',
                'stats',
                'recentScans',
                'selectedFileIndexing'
            ));
        } catch (Exception $e) {
            Log::error('Error loading scanning dashboard', [
                'error' => $e->getMessage()
            ]);

            return view('scanning.index', [
                'PageTitle' => 'Document Upload',
                'PageDescription' => 'Upload scanned documents to their digital folders',
                'stats' => ['uploads_today' => 0, 'pending_page_typing' => 0, 'total_scanned' => 0],
                'recentScans' => collect(),
                'selectedFileIndexing' => null
            ]);
        }
    }

    /**
     * Upload scanned documents
     */
    public function upload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'nullable|integer|exists:sqlsrv.file_indexings,id',
                'documents' => 'required|array|min:1',
                'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png,tiff|max:20480', // 20MB max
                'custom_names' => 'nullable|array',
                'custom_names.*' => 'nullable|string|max:255',
                'paper_sizes' => 'nullable|array',
                'paper_sizes.*' => 'nullable|string|max:20',
                'document_types' => 'nullable|array',
                'document_types.*' => 'nullable|string|max:100',
                'notes' => 'nullable|array',
                'notes.*' => 'nullable|string|max:1000',
                // Optional workflow extras
                'batch_id' => 'nullable|integer',
                'pra.instrument_type' => 'nullable|string|max:100',
                'pra.reg_no' => 'nullable|string|max:100',
                'pra.reg_date' => 'nullable|date',
                'pra.grantor' => 'nullable|string|max:255',
                'pra.grantee' => 'nullable|string|max:255',
                'pra.extras' => 'nullable|string',
                'barcode_value' => 'nullable|string|max:150',
                'qr_payload' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileIndexingId = $request->file_indexing_id;

            // If no file_indexing_id provided, create a temporary one for the new interface
            if (!$fileIndexingId) {
                // Create a temporary file indexing entry for the new interface
                $fileIndexing = FileIndexing::on('sqlsrv')->create([
                    'file_number' => 'TEMP-' . time(),
                    'file_title' => 'Temporary Upload - ' . date('Y-m-d H:i:s'),
                    'created_by' => Auth::id(),
                ]);
                $fileIndexingId = $fileIndexing->id;

                Log::info('Created temporary file indexing for new upload interface', [
                    'file_indexing_id' => $fileIndexingId,
                    'created_by' => Auth::id()
                ]);
            } else {
                $fileIndexing = FileIndexing::on('sqlsrv')->findOrFail($fileIndexingId);
            }

            // Optional: attach to batch if provided
            if ($request->filled('batch_id')) {
                try {
                    FileIndexing::on('sqlsrv')->where('id', $fileIndexingId)->update(['batch_id' => $request->batch_id]);
                } catch (Exception $e) {
                    Log::warning('Could not set batch_id on file_indexings (column or FK may be missing).', [
                        'file_indexing_id' => $fileIndexingId,
                        'batch_id' => $request->batch_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Optional: store barcode/QR info
            if ($request->filled('barcode_value')) {
                try {
                    \DB::connection('sqlsrv')->table('barcodes')->insert([
                        'file_indexing_id' => $fileIndexingId,
                        'barcode_value' => $request->barcode_value,
                        'qr_payload' => $request->qr_payload,
                        'printed_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (Exception $e) {
                    Log::warning('Could not insert barcode record (table or columns may be missing).', [
                        'file_indexing_id' => $fileIndexingId,
                        'barcode_value' => $request->barcode_value,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $customNames = $request->input('custom_names', []);
            $paperSizes = $request->input('paper_sizes', []);
            $documentTypes = $request->input('document_types', []);
            $notes = $request->input('notes', []);

            $uploadedDocuments = [];
            $errors = [];

            foreach ($request->file('documents') as $index => $document) {
                try {
                    // Use custom name if provided, otherwise use original name
                    $customName = $customNames[$index] ?? null;
                    $originalName = $customName ?: $document->getClientOriginalName();

                    // Get paper size, document type, and notes from form or use defaults
                    $paperSize = $paperSizes[$index] ?? $this->detectPaperSize($document);
                    $documentType = $documentTypes[$index] ?? $this->detectDocumentType($originalName);
                    $documentNotes = $notes[$index] ?? null;

                    // Generate incremental ScanId
                    $existingCount = \App\Models\Scanning::where('file_indexing_id', $fileIndexingId)->count();
                    $scanId = str_pad($existingCount + $index + 1, 4, '0', STR_PAD_LEFT);
                    $extension = $document->getClientOriginalExtension();
                    $filename = $fileIndexing->file_number . '_' . $scanId . '.' . $extension;

                    // Determine folder path based on registry and paper size
                    $registryFolder = $this->getRegistryFolderName($fileIndexing->registry) ?? 'General_Raw';
                    $uploadPath = 'EDMS/SCAN_UPLOAD/' . $registryFolder . '/' . $fileIndexing->file_number . '/' . $paperSize;

                    // Store file
                    $path = $document->storeAs(
                        $uploadPath,
                        $filename,
                        'public'
                    );

                    // Create scanning record
                    $scanning = Scanning::on('sqlsrv')->create([
                        'file_indexing_id' => $fileIndexingId,
                        'document_path' => $path,
                        'original_filename' => $originalName, // Use custom name as original filename
                        'paper_size' => $paperSize,
                        'document_type' => $documentType,
                        'uploaded_by' => Auth::id(),
                        'status' => 'pending',
                        'notes' => $documentNotes,
                    ]);

                    $uploadedDocuments[] = [
                        'id' => $scanning->id,
                        'filename' => $originalName,
                        'path' => $path,
                        'size' => $document->getSize(),
                        'type' => $extension,
                        'paper_size' => $paperSize,
                        'document_type' => $documentType,
                        'notes' => $documentNotes,
                    ];

                } catch (Exception $e) {
                    $errors[] = "Error uploading {$originalName}: " . $e->getMessage();
                    Log::error('Error uploading document', [
                        'file_indexing_id' => $fileIndexingId,
                        'filename' => $originalName ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Mark file_indexings.is_updated = 1 if column exists
            try {
                FileIndexing::on('sqlsrv')->where('id', $fileIndexingId)->update(['is_updated' => 1]);
            } catch (Exception $e) {
                Log::warning('Could not update file_indexings.is_updated (column may be missing). Generate and run EDMS schema SQL.', [
                    'file_indexing_id' => $fileIndexingId,
                    'error' => $e->getMessage()
                ]);
            }

            // Optional: capture PRA if present
            $pra = $request->input('pra', []);
            if (!empty($pra['instrument_type'] ?? null)) {
                try {
                    \DB::connection('sqlsrv')->table('property_records')->insert([
                        'file_indexing_id' => $fileIndexingId,
                        'instrument_type' => $pra['instrument_type'],
                        'reg_no' => $pra['reg_no'] ?? null,
                        'reg_date' => $pra['reg_date'] ?? null,
                        'grantor' => $pra['grantor'] ?? null,
                        'grantee' => $pra['grantee'] ?? null,
                        'extras' => $pra['extras'] ?? null,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (Exception $e) {
                    Log::warning('Could not insert PRA property record (table or columns may be missing).', [
                        'file_indexing_id' => $fileIndexingId,
                        'pra' => $pra,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Documents uploaded', [
                'file_indexing_id' => $fileIndexingId,
                'successful_uploads' => count($uploadedDocuments),
                'errors' => count($errors),
                'uploaded_by' => Auth::id()
            ]);

            $response = [
                'success' => count($uploadedDocuments) > 0,
                'message' => count($uploadedDocuments) . ' documents uploaded successfully!',
                'uploaded_documents' => $uploadedDocuments,
                'redirect' => route('pagetyping.index', ['file_indexing_id' => $fileIndexingId])
            ];

            if (count($errors) > 0) {
                $response['errors'] = $errors;
                $response['message'] .= ' ' . count($errors) . ' uploads failed.';
            }

            return response()->json($response);

        } catch (Exception $e) {
            Log::error('Error in document upload', [
                'error' => $e->getMessage(),
                'request_data' => $request->except('documents')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error uploading documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload unindexed documents with metadata extraction
     */
    public function uploadUnindexed(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'documents' => 'required|array|min:1',
                'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png,tiff|max:20480', // 20MB max
                'extracted_metadata' => 'required|array',
                'extracted_metadata.*' => 'required|array',
                'extracted_metadata.*.extractedFileNumber' => 'required|string|max:100',
                'extracted_metadata.*.detectedOwner' => 'required|string|max:255',
                'extracted_metadata.*.registry' => 'required|string|max:100',
                'extracted_metadata.*.serialNo' => 'required|string|max:50',
                'extracted_metadata.*.batchNo' => 'required|string|max:50',
                'extracted_metadata.*.shelfLocation' => 'required|string|max:100',
                'extracted_metadata.*.tpNumber' => 'nullable|string|max:255',
                'extracted_metadata.*.lpknNo' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                // Create a more user-friendly error message
                $errorMessages = $validator->errors()->all();
                $errorSummary = "Please provide all required fields: ";

                if (count($errorMessages) > 3) {
                    // Just list the first few errors to avoid too long messages
                    $errorSummary .= implode(", ", array_slice($errorMessages, 0, 3)) . "... and others";
                } else {
                    $errorSummary .= implode(", ", $errorMessages);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: Required fields are missing',
                    'errors' => $validator->errors(),
                    'error_summary' => $errorSummary
                ], 422);
            }

            $extractedMetadata = $request->input('extracted_metadata', []);
            $uploadedDocuments = [];
            $createdIndexings = [];
            $errors = [];

            // Determine one file number for all uploaded files.
            // Always prefer the file number from extractedFileNumber field
            $firstMetadata = $extractedMetadata[0] ?? [];
            $providedFileNumber = '';
            if (!empty($firstMetadata['extractedFileNumber'])) {
                $providedFileNumber = trim($firstMetadata['extractedFileNumber']);
            } elseif (!empty($firstMetadata['detectedOwner'])) {
                // fallback to detectedOwner if extractedFileNumber not present (backwards compatibility)
                $providedFileNumber = trim($firstMetadata['detectedOwner']);
            }

            if (!empty($providedFileNumber)) {
                // Sanitize provided name to form a safe file_number and path segment
                // Remove dangerous characters, allow letters, numbers, spaces, dashes and slashes
                $sanitized = preg_replace('/[^A-Za-z0-9 \-\/]/', '', $providedFileNumber);
                // Replace slashes and spaces with dashes to avoid directory traversal and filesystem issues
                $sanitized = str_replace(['/', ' '], ['-', '-'], $sanitized);
                $fileNumberCandidate = $sanitized;

                $suffixAttempt = 0;
                while (FileIndexing::on('sqlsrv')->where('file_number', $fileNumberCandidate)->exists()) {
                    $suffixAttempt++;
                    // Append a short timestamp + attempt counter to avoid collisions
                    $fileNumberCandidate = $sanitized . '-' . time() . ($suffixAttempt > 1 ? '-' . $suffixAttempt : '');
                    if ($suffixAttempt > 10)
                        break;
                }

                $fileNumber = $fileNumberCandidate;
            } else {
                // Fallback to the autogenerated UNINDEXED-xxxxxx code
                $fileNumber = $this->generateFileNumberForUnindexed();
            }

            // Create folder path for this file number
            $registryName = $firstMetadata['registry'] ?? null;
            $registryFolder = $this->getRegistryFolderName($registryName) ?? 'General_Raw';
            $folderPathBase = 'EDMS/BLIND_SCAN/' . $registryFolder . '/' . $fileNumber;

            // Ensure the base folder exists
            Storage::disk('public')->makeDirectory($folderPathBase);

            // Extract metadata from the first file or use defaults
            $firstMetadata = $extractedMetadata[0] ?? [];
            $fileTitle = $firstMetadata['detectedOwner'] ?? 'Multiple Documents Upload';
            $plotNumber = $firstMetadata['plotNumber'] ?? '';
            $landUseType = $firstMetadata['landUseType'] ?? 'Unknown';
            $district = $firstMetadata['district'] ?? 'Unknown';

            // Log the values for debugging
            Log::info('Creating file indexing record', [
                'file_number' => $fileNumber,
                'file_title' => $fileTitle,
                'extracted_file_number' => $firstMetadata['extractedFileNumber'] ?? 'not provided',
                'detected_owner' => $firstMetadata['detectedOwner'] ?? 'not provided'
            ]);

            // Create single file indexing record for all files
            $fileIndexing = FileIndexing::on('sqlsrv')->create([
                'file_number' => $fileNumber,
                'file_title' => $fileTitle,
                'plot_number' => $plotNumber,
                'land_use_type' => $landUseType,
                'district' => $district,
                'lga' => $district, // Use same as district if LGA not provided
                'has_cofo' => false,
                'is_merged' => false,
                'has_transaction' => true, // Since it's from uploaded document
                'is_problematic' => false,
                'is_co_owned_plot' => false,
                'registry' => $firstMetadata['registry'] ?? null,
                'serial_no' => $firstMetadata['serialNo'] ?? null,
                'batch_no' => $firstMetadata['batchNo'] ?? null,
                'shelf_location' => $firstMetadata['shelfLocation'] ?? null,
                'tp_number' => $firstMetadata['tpNumber'] ?? null,
                'lpkn_no' => $firstMetadata['lpknNo'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($request->file('documents') as $index => $document) {
                try {
                    $originalName = $document->getClientOriginalName();
                    $metadata = $extractedMetadata[$index] ?? [];

                    // Generate unique filename for storage within the filenumber folder
                    $extension = $document->getClientOriginalExtension();
                    $filename = time() . '_' . $index . '_' . uniqid() . '.' . $extension;

                    // Determine paper size to put in correct subfolder
                    $paperSize = $this->detectPaperSize($document);
                    $folderPath = $folderPathBase . '/' . $paperSize;

                    // Store file in the filenumber/papersize folder
                    $path = $document->storeAs($folderPath, $filename, 'public');

                    // Create scanning record linked to the single file indexing
                    $scanning = Scanning::on('sqlsrv')->create([
                        'file_indexing_id' => $fileIndexing->id,
                        'document_path' => $path,
                        'original_filename' => $originalName,
                        'paper_size' => $paperSize,
                        'document_type' => $this->detectDocumentType($originalName),
                        'uploaded_by' => Auth::id(),
                        'status' => 'scanned',
                        'notes' => 'Unindex Upload',
                    ]);

                    // Create property record if we have enough metadata (only for first file to avoid duplicates)
                    if ($index === 0 && (!empty($metadata['extractedFileNumber']) || !empty($metadata['detectedOwner']))) {
                        try {
                            $propertyRecord = new \App\Models\PropertyRecord();
                            $propertyRecord->setConnection('sqlsrv');
                            $propertyRecord->fill([
                                'kangisFileNo' => $fileNumber,
                                'NewKANGISFileno' => $fileNumber,
                                'title_type' => 'Certificate of Occupancy',
                                'transaction_type' => 'Original Grant',
                                'transaction_date' => now(),
                                'instrument_type' => $metadata['documentType'] ?? 'Land Document',
                                'Grantee' => $metadata['detectedOwner'] ?? 'Unknown',
                                'property_description' => $fileTitle,
                                'location' => $district,
                                'plot_no' => $plotNumber,
                                'lgsaOrCity' => $district,
                                'created_by' => Auth::id(),
                                'updated_by' => Auth::id(),
                            ]);
                            $propertyRecord->save();
                        } catch (Exception $e) {
                            Log::warning('Could not create property record for unindexed upload', [
                                'file_indexing_id' => $fileIndexing->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    $uploadedDocuments[] = [
                        'id' => $scanning->id,
                        'file_indexing_id' => $fileIndexing->id,
                        'filename' => $originalName,
                        'path' => $path,
                        'size' => $document->getSize(),
                        'type' => $extension,
                        'file_number' => $fileNumber,
                        'metadata' => $metadata,
                    ];

                } catch (Exception $e) {
                    $errors[] = "Error uploading {$originalName}: " . $e->getMessage();
                    Log::error('Error uploading unindexed document', [
                        'filename' => $originalName ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $createdIndexings[] = $fileIndexing;

            Log::info('Unindexed documents uploaded to single file number', [
                'file_number' => $fileNumber,
                'successful_uploads' => count($uploadedDocuments),
                'created_indexings' => count($createdIndexings),
                'errors' => count($errors),
                'uploaded_by' => Auth::id()
            ]);

            $response = [
                'success' => count($uploadedDocuments) > 0,
                'message' => count($uploadedDocuments) . ' documents uploaded and indexed under file number: ' . $fileNumber,
                'uploaded_documents' => $uploadedDocuments,
                'created_indexings' => collect($createdIndexings)->map(function ($indexing) {
                    return [
                        'id' => $indexing->id,
                        'file_number' => $indexing->file_number,
                        'file_title' => $indexing->file_title,
                    ];
                }),
                'file_number' => $fileNumber,
                'redirect' => count($createdIndexings) > 0 ? route('pagetyping.index', ['file_indexing_id' => $createdIndexings[0]->id]) : null
            ];

            if (count($errors) > 0) {
                $response['errors'] = $errors;
                $response['message'] .= ' ' . count($errors) . ' uploads failed.';
            }

            return response()->json($response);

        } catch (Exception $e) {
            Log::error('Error in unindexed document upload', [
                'error' => $e->getMessage(),
                'request_data' => $request->except('documents')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error uploading documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique file number for unindexed uploads
     */
    private function generateFileNumberForUnindexed()
    {
        try {
            // Get the last file number from file_indexings table
            $lastFileNumber = FileIndexing::on('sqlsrv')
                ->where('file_number', 'like', 'UNINDEXED-%')
                ->orderBy('file_number', 'desc')
                ->value('file_number');

            if ($lastFileNumber) {
                // Extract the numeric part and increment
                $lastNumber = intval(substr($lastFileNumber, 10)); // Remove 'UNINDEXED-' prefix
                $newNumber = $lastNumber + 1;
            } else {
                // Start from 1000 if no previous records
                $newNumber = 1000;
            }

            $nextFileNumber = 'UNINDEXED-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);

            return $nextFileNumber;

        } catch (\Exception $e) {
            Log::error('Error generating file number for unindexed upload', [
                'message' => $e->getMessage()
            ]);

            // Return a fallback with timestamp
            return 'UNINDEXED-' . time() . '-' . rand(1000, 9999);
        }
    }

    /**
     * Display the unindexed file upload interface
     */
    public function unindexed(Request $request)
    {
        try {
            $PageTitle = 'File Upload - EDMS';
            $PageDescription = 'Upload digital files to the registry';

            // Get statistics for dashboard
            $stats = [
                'uploads_today' => $this->getUploadsTodayCount(),
                'pending_indexing' => $this->getPendingIndexingCount(),
                'total_unindexed' => $this->getTotalUnindexedCount()
            ];

            return view('scanning.unindexed', compact('PageTitle', 'PageDescription', 'stats'));
        } catch (Exception $e) {
            Log::error('Error loading unindexed file upload interface', [
                'error' => $e->getMessage()
            ]);

            return view('scanning.unindexed', [
                'PageTitle' => 'File Upload - EDMS',
                'PageDescription' => 'Upload digital files to the registry',
                'stats' => ['uploads_today' => 0, 'pending_indexing' => 0, 'total_unindexed' => 0]
            ]);
        }
    }

    /**
     * View all scanned documents for a FileNo (file_indexing_id)
     */
    public function view($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings.uploader'])
                ->findOrFail($fileIndexingId);

            // Get all scanned documents for this FileNo
            $allScans = Scanning::on('sqlsrv')
                ->with(['uploader'])
                ->where('file_indexing_id', $fileIndexingId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get folder path for file manager
            $registryFolder = $this->getRegistryFolderName($fileIndexing->registry) ?? 'General_Raw';
            $folderPath = 'EDMS/SCAN_UPLOAD/' . $registryFolder . '/' . $fileIndexing->file_number;

            // Check if folder exists and get files (recursive for A4/A3 support)
            $folderFiles = [];
            if (Storage::disk('public')->exists($folderPath)) {
                $files = Storage::disk('public')->allFiles($folderPath);
                foreach ($files as $file) {
                    // Calculate relative path for display (e.g., "A4/document.jpg")
                    $relativePath = str_replace($folderPath . '/', '', $file);

                    $folderFiles[] = [
                        'name' => $relativePath,
                        'path' => $file,
                        'url' => Storage::disk('public')->url($file),
                        'size' => Storage::disk('public')->size($file),
                        'modified' => Storage::disk('public')->lastModified($file),
                    ];
                }
            }

            $PageTitle = 'View Scanned Files - ' . $fileIndexing->file_number;
            $PageDescription = 'All scanned documents for ' . $fileIndexing->file_title;

            return view('scanning.view', compact(
                'PageTitle',
                'PageDescription',
                'fileIndexing',
                'allScans',
                'folderPath',
                'folderFiles'
            ));
        } catch (Exception $e) {
            Log::error('Error loading scanned files for FileNo', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('scanning.index')
                ->with('error', 'File not found');
        }
    }

    /**
     * Get document details for editing
     */
    public function details($id)
    {
        try {
            $scanning = Scanning::on('sqlsrv')
                ->with(['fileIndexing', 'uploader'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $scanning->id,
                    'original_filename' => $scanning->original_filename,
                    'paper_size' => $scanning->paper_size,
                    'document_type' => $scanning->document_type,
                    'notes' => $scanning->notes,
                    'status' => $scanning->status,
                    'file_indexing' => $scanning->fileIndexing ? [
                        'id' => $scanning->fileIndexing->id,
                        'file_number' => $scanning->fileIndexing->file_number,
                        'file_title' => $scanning->fileIndexing->file_title,
                    ] : [
                        'id' => null,
                        'file_number' => 'Unknown',
                        'file_title' => 'File not found',
                    ],
                    'uploaded_at' => $scanning->created_at->format('M d, Y H:i'),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting document details', [
                'scanning_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }
    }

    /**
     * Delete a scanned document
     */
    public function delete($id)
    {
        try {
            $scanning = Scanning::on('sqlsrv')->findOrFail($id);

            // Check if document has page typings
            if ($scanning->fileIndexing && $scanning->fileIndexing->pagetypings()->where('file_path', $scanning->document_path)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete document that has been page typed'
                ], 409);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($scanning->document_path)) {
                Storage::disk('public')->delete($scanning->document_path);
            }

            // Delete database record
            $scanning->delete();

            Log::info('Scanned document deleted', [
                'scanning_id' => $id,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting scanned document', [
                'scanning_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a scanned document (alternative method for destroy route)
     */
    public function destroy($id)
    {
        return $this->delete($id);
    }

    /**
     * Update document details
     */
    public function updateDetails(Request $request, $id)
    {
        try {
            $scanning = Scanning::on('sqlsrv')->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'original_filename' => 'nullable|string|max:255',
                'paper_size' => 'nullable|string|max:20',
                'document_type' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scanning->update($validator->validated());

            Log::info('Document details updated', [
                'scanning_id' => $id,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document details updated successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Error updating document details', [
                'scanning_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating document details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get registry code from registry name
     */
    private function getRegistryCode($registryName)
    {
        if (empty($registryName)) {
            return null;
        }

        // Try to find in DB first
        $registry = Registry::where('name', $registryName)->orWhere('code', $registryName)->first();
        if ($registry) {
            return $registry->code;
        }

        // Fallback for hardcoded common names if DB lookup fails (or legacy data)
        $map = [
            'Lands Registry' => 'LANDS',
            'Cadastral Registry' => 'CAD',
            'DCIV Registry' => 'DCIV',
            'Secret Registry' => 'SECRET',
            'KANGIS Registry' => 'KANGIS',
            'SLTR Registry' => 'SLTR',
            'ST Registry' => 'ST',
            'Deeds Registry' => 'DEEDS',
        ];

        return $map[$registryName] ?? null;
    }

    /**
     * Get registry folder name (e.g., Lands_Registry_Raw)
     */
    private function getRegistryFolderName($registryName)
    {
        if (empty($registryName)) {
            return null;
        }

        // Standardize format: Title Case, spaces to underscores, append _Raw
        // Lands Registry -> Lands_Registry_Raw
        return str_replace(' ', '_', $registryName) . '_Raw';
    }

    /**
     * Get scanned files list for a file indexing (AJAX)
     */
    public function getScannedFiles(Request $request)
    {
        try {
            $fileIndexingId = $request->get('file_indexing_id');
            $search = $request->get('search', '');

            $query = Scanning::on('sqlsrv')
                ->with(['fileIndexing', 'uploader']);

            if ($fileIndexingId) {
                $query->where('file_indexing_id', $fileIndexingId);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('original_filename', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            $scannedFiles = $query->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'scanned_files' => $scannedFiles->map(function ($scan) {
                    return [
                        'id' => $scan->id,
                        'filename' => $scan->original_filename,
                        'document_path' => $scan->document_path,
                        'paper_size' => $scan->paper_size,
                        'document_type' => $scan->document_type,
                        'status' => $scan->status,
                        'notes' => $scan->notes,
                        'file_indexing' => $scan->fileIndexing ? [
                            'id' => $scan->fileIndexing->id,
                            'file_number' => $scan->fileIndexing->file_number,
                            'file_title' => $scan->fileIndexing->file_title,
                        ] : [
                            'id' => null,
                            'file_number' => 'Unknown',
                            'file_title' => 'File not found',
                        ],
                        'uploader_name' => $scan->uploader ? $scan->uploader->name : 'Unknown',
                        'uploaded_at' => $scan->created_at->format('M d, Y H:i'),
                        'file_url' => url('storage/' . $scan->document_path),
                    ];
                })
            ]);

        } catch (Exception $e) {
            Log::error('Error getting scanned files', [
                'error' => $e->getMessage(),
                'file_indexing_id' => $request->get('file_indexing_id'),
                'search' => $request->get('search', '')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading scanned files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias for getScannedFiles to match route name scanning.list
     */
    public function list(Request $request)
    {
        return $this->getScannedFiles($request);
    }

    /**
     * Get uploads today count
     */
    private function getUploadsTodayCount()
    {
        try {
            return Scanning::on('sqlsrv')
                ->whereRaw("CAST(notes AS VARCHAR(MAX)) = ?", ['Unindex Upload'])
                ->whereDate('created_at', today())
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get pending page typing count
     */
    private function getPendingPageTypingCount()
    {
        try {
            return Scanning::on('sqlsrv')
                ->whereDoesntHave('fileIndexing.pagetypings')
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Helper method to detect paper size from file
     */
    private function detectPaperSize($file)
    {
        // Default to A4, could be enhanced with actual dimension detection
        return 'A4';
    }

    /**
     * Helper method to detect document type from filename
     */
    private function detectDocumentType($filename)
    {
        $filename = strtolower($filename);

        if (strpos($filename, 'certificate') !== false) {
            return 'Certificate';
        } elseif (strpos($filename, 'deed') !== false) {
            return 'Deed';
        } elseif (strpos($filename, 'receipt') !== false) {
            return 'Receipt';
        } elseif (strpos($filename, 'survey') !== false) {
            return 'Survey Plan';
        } elseif (strpos($filename, 'map') !== false) {
            return 'Map';
        } else {
            return 'Document';
        }
    }

    /**
     * Get pending indexing count - returns 0 since we upload and index simultaneously
     */
    private function getPendingIndexingCount()
    {
        try {
            // Since we upload and index at the same time, there are no pending indexing files
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get total unindexed count
     */
    private function getTotalUnindexedCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->where(function ($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                        ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Upload More - Set is_updated = 1 for additional uploads
     */
    public function uploadMore($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->findOrFail($fileIndexingId);

            // Set is_updated = 1 to mark file for additional uploads
            try {
                $fileIndexing->update(['is_updated' => 1]);

                Log::info('File marked for Upload More', [
                    'file_indexing_id' => $fileIndexingId,
                    'file_number' => $fileIndexing->file_number,
                    'marked_by' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'File marked for additional uploads successfully!',
                    'file' => [
                        'id' => $fileIndexing->id,
                        'file_number' => $fileIndexing->file_number,
                        'file_title' => $fileIndexing->file_title,
                        'is_updated' => 1
                    ]
                ]);

            } catch (Exception $e) {
                Log::warning('Could not update file_indexings.is_updated (column may be missing)', [
                    'file_indexing_id' => $fileIndexingId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Upload More feature requires database schema update. Please run the EDMS schema SQL script.'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error in Upload More', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error marking file for additional uploads: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show unindexed files upload interface
     */
    public function unindexedFiles()
    {
        try {
            $PageTitle = 'Unindexed File Upload';
            $PageDescription = 'Upload scanned documents without existing indexing records';

            // Get statistics for unindexed uploads
            $stats = [
                'unindexed_uploads_today' => $this->getUnindexedUploadsTodayCount(),
                'pending_processing' => $this->getPendingProcessingCount(),
                'total_processed' => $this->getTotalProcessedCount(),
            ];

            // Get recent processed files (files created from unindexed uploads)
            $recentProcessed = FileIndexing::on('sqlsrv')
                ->with(['scannings', 'uploader'])
                ->where('file_number', 'like', 'AUTO-%')
                ->orWhere('file_number', 'like', 'TEMP-%')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return view('scanning.unindexed_files_scans', compact(
                'PageTitle',
                'PageDescription',
                'stats',
                'recentProcessed'
            ));
        } catch (Exception $e) {
            Log::error('Error loading unindexed files interface', [
                'error' => $e->getMessage()
            ]);

            return view('scanning.unindexed_files_scans', [
                'PageTitle' => 'Unindexed File Upload',
                'PageDescription' => 'Upload scanned documents without existing indexing records',
                'stats' => ['unindexed_uploads_today' => 0, 'pending_processing' => 0, 'total_processed' => 0],
                'recentProcessed' => collect()
            ]);
        }
    }

    /**
     * Get unindexed uploads today count
     */
    private function getUnindexedUploadsTodayCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->where(function ($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                        ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->whereDate('created_at', today())
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get pending processing count
     */
    private function getPendingProcessingCount()
    {
        try {
            // Files that are temporary but haven't been fully processed
            return FileIndexing::on('sqlsrv')
                ->where('file_number', 'like', 'TEMP-%')
                ->whereDoesntHave('scannings')
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get total processed count
     */
    private function getTotalProcessedCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->where(function ($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                        ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->whereHas('scannings')
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get grouped scanned files (one row per FileNo)
     */
    private function getGroupedScannedFiles()
    {
        try {
            // Get file indexings that have scanned documents, grouped by file_number
            $groupedScans = FileIndexing::on('sqlsrv')
                ->with([
                    'scannings' => function ($query) {
                        $query->with('uploader')->orderBy('created_at', 'desc');
                    }
                ])
                ->whereHas('scannings')
                ->orderBy('updated_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($fileIndexing) {
                    $latestScan = $fileIndexing->scannings->first();
                    $scanCount = $fileIndexing->scannings->count();

                    return (object) [
                        'id' => $fileIndexing->id,
                        'fileIndexing' => $fileIndexing,
                        'file_number' => $fileIndexing->file_number,
                        'file_title' => $fileIndexing->file_title,
                        'scan_count' => $scanCount,
                        'latest_scan_date' => $latestScan ? $latestScan->created_at : null,
                        'uploader' => $latestScan && $latestScan->uploader ? $latestScan->uploader : null,
                        'status' => $this->determineFileStatus($fileIndexing),
                        'created_at' => $latestScan ? $latestScan->created_at : $fileIndexing->created_at,
                    ];
                });

            return $groupedScans;
        } catch (Exception $e) {
            Log::error('Error getting grouped scanned files', [
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Determine file status based on workflow progress
     */
    private function determineFileStatus($fileIndexing)
    {
        try {
            // Check if file has page typings
            if ($fileIndexing->pagetypings && $fileIndexing->pagetypings->count() > 0) {
                return 'typed';
            }

            // Check if file has scannings
            if ($fileIndexing->scannings && $fileIndexing->scannings->count() > 0) {
                return 'scanned';
            }

            return 'indexed';
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Get unindexed files for the uploaded files tab
     */
    public function getUnindexedFiles(Request $request)
    {
        try {
            $files = Scanning::on('sqlsrv')
                ->with(['fileIndexing', 'uploader'])
                ->whereRaw("CAST(notes AS VARCHAR(MAX)) = ?", ['Unindex Upload'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($scan) {
                    $fileSize = 0;
                    if ($scan->document_path && Storage::disk('public')->exists($scan->document_path)) {
                        $fileSize = Storage::disk('public')->size($scan->document_path);
                    }

                    $extension = strtolower(pathinfo($scan->original_filename, PATHINFO_EXTENSION));
                    $mimeType = $this->getMimeTypeFromExtension($extension);

                    return [
                        'id' => $scan->id,
                        'name' => $scan->original_filename ?? 'Unknown File',
                        'file_number' => $scan->fileIndexing ? $scan->fileIndexing->file_number : null,
                        'type' => $mimeType,
                        'size' => $this->formatFileSize($fileSize),
                        'status' => 'Uploaded',
                        'date' => $scan->created_at->format('M d, Y H:i'),
                        'uploaded_by' => $scan->uploader ? $scan->uploader->name : 'Unknown',
                        'document_path' => $scan->document_path
                    ];
                });

            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => $files->count()
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching unindexed files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching files',
                'files' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeTypeFromExtension($extension)
    {
        switch ($extension) {
            case 'pdf':
                return 'application/pdf';
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            case 'bmp':
                return 'image/bmp';
            case 'tiff':
            case 'tif':
                return 'image/tiff';
            default:
                return 'application/octet-stream';
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes)
    {
        if ($bytes == 0)
            return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
