<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserActivityLog;
use App\Models\BlindScanning;
use App\Models\FileIndexing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use App\Services\PdfToImageService;

class BlindScanningController extends Controller
{
    private const DB_INSERT_CHUNK_SIZE = 500;
    private const PDF_PAGE_SCAN_LIMIT_BYTES = 5242880; // 5 MB cap when parsing
    private const PDF_PAGE_SIZE_THRESHOLD_BYTES = 10485760; // Skip parsing PDFs larger than 10 MB

    private PdfToImageService $pdfToImageService;

    public function __construct(PdfToImageService $pdfToImageService)
    {
        $this->pdfToImageService = $pdfToImageService;
    }

    /**
     * Display the blind scanning interface
     */
    public function index(Request $request)
    {
        // Check if this is ST EDMS context
        $urlContext = $request->query('url');
        $isSTEDMS = $urlContext === 'st_edms';

        // Get application ID from URL parameter (for direct links)
        $applicationId = $request->query('app_id');

        if ($isSTEDMS) {
            $PageTitle = "ST BLIND SCANNING";
            $PageDescription = "Sectional Titling - Blind scan and manage EDMS documents";
        } else {
            $PageTitle = "BLIND SCANNING";
            $PageDescription = "Upload and manage blind scanned documents";
        }

        // Fetch registries for the dropdown
        $registries = \App\Models\Registry::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('scanning.blind_scans', compact('PageTitle', 'PageDescription', 'isSTEDMS', 'urlContext', 'applicationId', 'registries'));
    }

    /**
     * Insert blind scan records in chunks to reduce per-file overhead
     */
    private function insertBlindScanChunk(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // SQL Server caps a statement at 2100 bound parameters, so split the
        // chunk into batches of rows x columns that stay under it.
        $rowsPerInsert = max(1, intdiv(2000, max(1, count(reset($records)))));
        if (count($records) > $rowsPerInsert) {
            foreach (array_chunk($records, $rowsPerInsert) as $batch) {
                $this->insertBlindScanChunk($batch);
            }
            return;
        }

        try {
            BlindScanning::insert($records);
        } catch (\Exception $e) {
            Log::error('Failed to insert blind scan chunk', [
                'message' => $e->getMessage(),
                'count' => count($records)
            ]);

            // Fall back to individual inserts for the chunk to avoid data loss
            foreach ($records as $record) {
                $recordForCreate = $record;
                unset($recordForCreate['created_at'], $recordForCreate['updated_at']);
                try {
                    BlindScanning::create($recordForCreate);
                } catch (\Exception $inner) {
                    Log::error('Failed to insert blind scan record after chunk fallback', [
                        'message' => $inner->getMessage(),
                        'record' => $record['original_filename'] ?? 'unknown'
                    ]);
                }
            }
        }
    }

    /**
     * Store blind scanning files to database
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_no' => 'required|string',
                'files' => 'required|array',
                'files.*.name' => 'required|string',
                'files.*.path' => 'required|string',
                'files.*.size' => 'required|integer',
                'files.*.paper_size' => 'nullable|string',
                'files.*.document_type' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedFiles = [];

            foreach ($request->files as $fileData) {
                $blindScanning = BlindScanning::create([
                    'original_filename' => $fileData['name'],
                    'document_path' => $fileData['path'],
                    'paper_size' => $fileData['paper_size'] ?? $this->detectPaperSizeFromPath($fileData['path']),
                    'document_type' => $fileData['document_type'] ?? $this->detectDocumentType($fileData['name']),
                    'notes' => $request->notes,
                    'status' => BlindScanning::STATUS_PENDING,
                    'uploaded_by' => Auth::id(),
                ]);

                $uploadedFiles[] = $blindScanning;
            }

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'data' => [
                    'uploaded_count' => count($uploadedFiles),
                    'files' => $uploadedFiles
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Blind scanning store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List blind scanning records with pagination and filtering
     */
    public function list(Request $request)
    {
        try {
            $query = BlindScanning::with(['uploader', 'fileIndexing']);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('paper_size')) {
                $query->where('paper_size', $request->paper_size);
            }

            if ($request->filled('document_type')) {
                $query->where('document_type', $request->document_type);
            }

            if ($request->filled('registry_type')) {
                $query->where('registry_type', $request->registry_type);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('original_filename', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Order by latest first
            $query->orderBy('created_at', 'desc');

            // Paginate
            $perPage = $request->get('per_page', 15);
            $records = $query->paginate($perPage);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $records
                ]);
            }

            return view('scanning.blind_scans_list', compact('records'));

        } catch (\Exception $e) {
            Log::error('Blind scanning list failed', [
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load records: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to load records');
        }
    }

    /**
     * Show specific blind scanning record
     */
    public function show($id)
    {
        try {
            $record = BlindScanning::with(['uploader', 'fileIndexing'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $record
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found'
            ], 404);
        }
    }

    /**
     * Delete blind scanning record
     */
    public function destroy($id)
    {
        try {
            $record = BlindScanning::findOrFail($id);

            // Delete the actual file if it exists
            if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                Storage::disk('public')->delete($record->document_path);
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Blind scanning delete failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record'
            ], 500);
        }
    }

    /**
     * Convert blind scan to regular upload
     */
    public function convertToUpload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'blind_scan_id' => 'required|integer|exists:blind_scannings,id',
                'file_indexing_id' => 'required|integer|exists:file_indexings,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $blindScan = BlindScanning::findOrFail($request->blind_scan_id);

            // Update the blind scan record
            $blindScan->update([
                'file_indexing_id' => $request->file_indexing_id,
                'status' => BlindScanning::STATUS_CONVERTED,
                'converted_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully converted to upload workflow',
                'data' => $blindScan
            ]);

        } catch (\Exception $e) {
            Log::error('Convert to upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect paper size from file path
     */
    private function detectPaperSizeFromPath($path)
    {
        // Check for A3 first (more specific)
        if (strpos($path, '/A3/') !== false || strpos($path, '\\A3\\') !== false || stripos($path, 'A3/') === 0) {
            return 'A3';
        } elseif (strpos($path, '/A4/') !== false || strpos($path, '\\A4\\') !== false || stripos($path, 'A4/') === 0) {
            return 'A4';
        }
        return 'A4'; // Default
    }

    /**
     * Detect document type from filename
     */
    private function detectDocumentType($filename)
    {
        $filename = strtolower($filename);

        if (strpos($filename, 'deed') !== false) {
            return 'Deed';
        } elseif (strpos($filename, 'survey') !== false) {
            return 'Survey Plan';
        } elseif (strpos($filename, 'certificate') !== false) {
            return 'Certificate';
        } elseif (strpos($filename, 'application') !== false) {
            return 'Application';
        } elseif (strpos($filename, 'receipt') !== false) {
            return 'Receipt';
        }

        return 'Document'; // Default
    }

    /**
     * Create folder for blind scanning
     */
    public function createFolder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'folder_name' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $folderName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $request->folder_name);
            $storagePath = file_storage_path('app/public/EDMS/BLIND_SCAN');
            $targetPath = $storagePath . DIRECTORY_SEPARATOR . $folderName;

            if (is_dir($targetPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder already exists'
                ], 409);
            }

            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0775, true);
            }

            if (mkdir($targetPath, 0775, true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Folder created successfully',
                    'data' => [
                        'folder_name' => $folderName,
                        'path' => $targetPath
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create folder'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Create folder failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create folder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle migration of blind scanning files
     */
    public function migrate(Request $request)
    {
        try {
            // Validate the request
            if (empty($request->input('folderName'))) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Missing folderName'
                ], 400);
            }

            $folderName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $request->input('folderName'));
            $uploadMethod = $request->input('uploadMethod', 'zip');
            $registryType = $request->input('registry_type', 'lands');

            if (!in_array($registryType, ['lands', 'cadastral'])) {
                $registryType = 'lands';
            }

            $registrySubfolder = $registryType === 'cadastral' ? 'Cadastral_Registry_Raw' : 'Lands_Registry_Raw';

            // Create storage directory structure
            $storagePath = file_storage_path('app/public/EDMS/BLIND_SCAN/' . $registrySubfolder);
            if (!is_dir($storagePath)) {
                if (!mkdir($storagePath, 0775, true)) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Could not create storage directory'
                    ], 500);
                }
            }

            // Target directory
            $targetPath = $storagePath . DIRECTORY_SEPARATOR . $folderName;
            if (is_dir($targetPath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Folder already exists on server'
                ], 409);
            }

            if (!mkdir($targetPath, 0775, true)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Could not create target folder'
                ], 500);
            }

            // Handle different upload methods
            if ($uploadMethod === 'direct') {
                // Handle direct file uploads
                if (!$this->handleDirectUpload($request, $targetPath)) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Failed to upload files directly'
                    ], 500);
                }
            } else {
                // Handle ZIP upload (fallback)
                if (!$request->hasFile('zip')) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Missing zip file'
                    ], 400);
                }

                $uploadedFile = $request->file('zip');
                if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Upload failed (error code: ' . $uploadedFile->getError() . ')'
                    ], 400);
                }

                // Extract ZIP file using alternative method
                $zipPath = $uploadedFile->getPathname();

                if (!$this->extractZipFile($zipPath, $targetPath)) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Failed to extract ZIP file'
                    ], 500);
                }
            }

            // Log the migration
            $this->logMigration($folderName, $targetPath);

            // Save files to database
            $this->saveFilesToDatabase($targetPath, $folderName, $registryType, $registrySubfolder . '/' . $folderName);

            // Generate public URL
            $publicUrl = '/storage/EDMS/BLIND_SCAN/' . $registrySubfolder . '/' . $folderName;

            return response()->json([
                'ok' => true,
                'message' => 'Migration completed successfully',
                'serverPath' => $publicUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Blind scanning migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save migrated files to database
     */
    private function saveFilesToDatabase($targetPath, $folderName, $registryType = 'lands', $localPcPath = null)
    {
        try {
            $fileNumber = $this->extractFileNumberFromFolderName($folderName);
            $dbPathValue = $localPcPath ?: $folderName;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($targetPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $registrySubfolder = $registryType === 'cadastral' ? 'Cadastral_Registry_Raw' : 'Lands_Registry_Raw';
            $totalA4Count = 0;
            $totalA3Count = 0;
            $totalPages = 0;
            $uploaderId = Auth::id() ?? 1;
            $pendingRecords = [];
            $totalInserted = 0;
            $chunkTimestamp = now();

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relativePath = str_replace($targetPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                // Skip archived originals to avoid re-processing
                if (str_contains(strtolower($relativePath), '/_pdf_originals/') || str_contains(strtolower($relativePath), '\\_pdf_originals\\')) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if ($extension === 'pdf') {
                    $conversionResult = $this->convertPdfAndQueuePages(
                        $file,
                        $targetPath,
                        $folderName,
                        $registrySubfolder,
                        $dbPathValue,
                        $fileNumber,
                        $registryType,
                        $chunkTimestamp,
                        $pendingRecords,
                        $totalA4Count,
                        $totalA3Count,
                        $totalPages
                    );

                    if ($conversionResult) {
                        // PDF handled (converted or failed gracefully)
                        if (count($pendingRecords) >= self::DB_INSERT_CHUNK_SIZE) {
                            $this->insertBlindScanChunk($pendingRecords);
                            $totalInserted += count($pendingRecords);
                            $pendingRecords = [];
                            $chunkTimestamp = now();
                        }
                        continue;
                    }
                }

                $documentPath = 'EDMS/BLIND_SCAN/' . $registrySubfolder . '/' . $folderName . '/' . $relativePath;

                $paperSize = $this->detectPaperSizeFromPath($relativePath);
                $documentType = $this->detectDocumentType($file->getFilename());

                $pageCount = $this->getPageCountFromFile($file);
                $a4Count = 0;
                $a3Count = 0;

                if (stripos($paperSize, 'A4') !== false) {
                    $a4Count = $pageCount;
                    $totalA4Count += $pageCount;
                } elseif (stripos($paperSize, 'A3') !== false) {
                    $a3Count = $pageCount;
                    $totalA3Count += $pageCount;
                } else {
                    $a4Count = $pageCount;
                    $totalA4Count += $pageCount;
                }

                $totalPages += $pageCount;

                $pendingRecords[] = [
                    'temp_file_id' => 'MIGRATE_' . microtime(true) . '_' . uniqid() . '_' . str_replace([' ', '.'], ['_', ''], $file->getFilename()),
                    'file_number' => $fileNumber,
                    'local_pc_path' => $dbPathValue,
                    'original_filename' => $file->getFilename(),
                    'document_path' => $documentPath,
                    'paper_size' => $paperSize,
                    'document_type' => $documentType,
                    'a4_count' => $a4Count,
                    'a3_count' => $a3Count,
                    'total_pages' => $pageCount,
                    'notes' => "Migrated from folder: {$folderName}",
                    'status' => BlindScanning::STATUS_PENDING,
                    'uploaded_by' => $uploaderId,
                    'created_at' => $chunkTimestamp,
                    'updated_at' => $chunkTimestamp,
                    'registry_type' => $registryType,
                ];

                if (count($pendingRecords) >= self::DB_INSERT_CHUNK_SIZE) {
                    $this->insertBlindScanChunk($pendingRecords);
                    $totalInserted += count($pendingRecords);
                    $pendingRecords = [];
                    $chunkTimestamp = now();
                }
            }

            if (!empty($pendingRecords)) {
                $this->insertBlindScanChunk($pendingRecords);
                $totalInserted += count($pendingRecords);
            }

            Log::info('Files saved to database', [
                'folder' => $folderName,
                'file_number' => $fileNumber,
                'target_path' => $targetPath,
                'total_a4_pages' => $totalA4Count,
                'total_a3_pages' => $totalA3Count,
                'total_pages' => $totalPages,
                'records_inserted' => $totalInserted
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save files to database', [
                'folder' => $folderName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Convert a PDF into page PNGs and queue the new pages for insertion.
     * Returns true when the PDF was handled (converted or enqueued as original fallback).
     */
    private function convertPdfAndQueuePages(
        \SplFileInfo $file,
        string $targetPath,
        string $folderName,
        string $registrySubfolder,
        string $dbPathValue,
        string $fileNumber,
        string $registryType,
        $chunkTimestamp,
        array &$pendingRecords,
        int &$totalA4Count,
        int &$totalA3Count,
        int &$totalPages
    ): bool {
        $relativePath = str_replace($targetPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);
        $paperSize = $this->detectPaperSizeFromPath($relativePath);
        $documentType = $this->detectDocumentType($file->getFilename());

        $outputDir = $file->getPath() . DIRECTORY_SEPARATOR . pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $conversion = $this->pdfToImageService->convert($file->getPathname(), $outputDir);

        if ($conversion['success'] && !empty($conversion['pages'])) {
            $this->archiveOriginalPdf($targetPath, $relativePath, $file->getFilename());

            foreach ($conversion['pages'] as $pageMeta) {
                $normalizedTarget = str_replace('\\', '/', $targetPath);
                $normalizedPagePath = str_replace('\\', '/', $pageMeta['path']);
                $pageRelative = ltrim(str_replace($normalizedTarget, '', $normalizedPagePath), '/');
                $documentPath = 'EDMS/BLIND_SCAN/' . $registrySubfolder . '/' . $folderName . '/' . $pageRelative;

                $pendingRecords[] = [
                    'temp_file_id' => 'MIGRATE_IMG_' . microtime(true) . '_' . uniqid() . '_' . $pageMeta['page'],
                    'file_number' => $fileNumber,
                    'local_pc_path' => $dbPathValue,
                    'original_filename' => $file->getFilename() . ' (page ' . $pageMeta['page'] . ')',
                    'document_path' => $documentPath,
                    'paper_size' => $paperSize,
                    'document_type' => $documentType,
                    'a4_count' => stripos($paperSize, 'A3') !== false ? 0 : 1,
                    'a3_count' => stripos($paperSize, 'A3') !== false ? 1 : 0,
                    'total_pages' => 1,
                    'notes' => "Converted from PDF: {$file->getFilename()}",
                    'status' => BlindScanning::STATUS_PENDING,
                    'uploaded_by' => Auth::id() ?? 1,
                    'created_at' => $chunkTimestamp,
                    'updated_at' => $chunkTimestamp,
                    'registry_type' => $registryType,
                ];

                if (stripos($paperSize, 'A3') !== false) {
                    $totalA3Count += 1;
                } else {
                    $totalA4Count += 1;
                }
                $totalPages += 1;
            }

            return true;
        }

        // Conversion failed; keep the original PDF as a single record to avoid data loss.
        if (!$conversion['success']) {
            Log::warning('PDF conversion failed, falling back to storing original PDF', [
                'pdf' => $file->getPathname(),
                'error' => $conversion['error'] ?? 'unknown'
            ]);
        }

        $documentPath = 'EDMS/BLIND_SCAN/' . $registrySubfolder . '/' . $folderName . '/' . $relativePath;
        $pageCount = $this->getPageCountFromFile($file);
        $a4Count = stripos($paperSize, 'A3') !== false ? 0 : $pageCount;
        $a3Count = stripos($paperSize, 'A3') !== false ? $pageCount : 0;

        $pendingRecords[] = [
            'temp_file_id' => 'MIGRATE_' . microtime(true) . '_' . uniqid() . '_' . str_replace([' ', '.'], ['_', ''], $file->getFilename()),
            'file_number' => $fileNumber,
            'local_pc_path' => $dbPathValue,
            'original_filename' => $file->getFilename(),
            'document_path' => $documentPath,
            'paper_size' => $paperSize,
            'document_type' => $documentType,
            'a4_count' => $a4Count,
            'a3_count' => $a3Count,
            'total_pages' => $pageCount,
            'notes' => "Migrated from folder: {$folderName} (conversion unavailable)",
            'status' => BlindScanning::STATUS_PENDING,
            'uploaded_by' => Auth::id() ?? 1,
            'created_at' => $chunkTimestamp,
            'updated_at' => $chunkTimestamp,
            'registry_type' => $registryType,
        ];

        if ($a3Count > 0) {
            $totalA3Count += $a3Count;
        } else {
            $totalA4Count += $a4Count;
        }
        $totalPages += $pageCount;

        return true;
    }

    /**
     * Move the original PDF into an archive folder to keep the source while avoiding double ingestion.
     */
    private function archiveOriginalPdf(string $targetPath, string $relativePath, string $filename): void
    {
        $archiveDir = $targetPath . DIRECTORY_SEPARATOR . '_pdf_originals';

        $relativeDir = trim(str_replace(['/' . $filename, '\\' . $filename], '', $relativePath), '/\\');
        if (!empty($relativeDir)) {
            $archiveDir .= DIRECTORY_SEPARATOR . $relativeDir;
        }

        File::ensureDirectoryExists($archiveDir, 0775, true);

        $destination = $archiveDir . DIRECTORY_SEPARATOR . $filename;
        $sourcePath = $targetPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        @rename($sourcePath, $destination);
    }

    /**
     * Log migration activity
     */
    private function logMigration($folderName, $targetPath)
    {
        try {
            $logFile = file_storage_path('app/public/EDMS/BLIND_SCAN/_migrations.json');
            $logs = [];

            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                if ($content) {
                    $logs = json_decode($content, true) ?: [];
                }
            }

            $newEntry = [
                'when' => date('Y-m-d H:i:s'),
                'folder' => $folderName,
                'serverPath' => '/storage/EDMS/BLIND_SCAN/' . $folderName,
                'user' => auth()->user()->name ?? 'System'
            ];

            array_unshift($logs, $newEntry);

            // Keep only last 100 entries
            $logs = array_slice($logs, 0, 100);

            file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            Log::warning('Failed to log migration', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Synchronize the migration log with folders present on disk
     */
    public function syncMigrationLog(Request $request)
    {
        try {
            $basePath = file_storage_path('app/public/EDMS/BLIND_SCAN');

            if (!is_dir($basePath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'BLIND_SCAN storage path is missing.',
                    'resolved_path' => $basePath
                ], 404);
            }

            $logFile = file_storage_path('app/public/EDMS/BLIND_SCAN/_migrations.json');
            $existingLogs = [];

            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                if ($content) {
                    $existingLogs = json_decode($content, true) ?: [];
                }
            }

            $knownFolders = collect($existingLogs)
                ->pluck('folder')
                ->filter()
                ->map(function ($folder) {
                    return strtolower($folder);
                })
                ->values()
                ->all();

            $newEntries = [];
            $dbAddedCount = 0;
            $dbSkippedCount = 0;
            $errors = [];
            $scanDirs = [
                '' => $basePath,
                'Lands_Registry_Raw' => $basePath . DIRECTORY_SEPARATOR . 'Lands_Registry_Raw',
                'Cadastral_Registry_Raw' => $basePath . DIRECTORY_SEPARATOR . 'Cadastral_Registry_Raw'
            ];

            foreach ($scanDirs as $subName => $dirPath) {
                if (!is_dir($dirPath))
                    continue;

                $items = scandir($dirPath) ?: [];
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..' || $item === '_migrations.json' || str_contains(strtolower($item), 'registry_raw')) {
                        continue;
                    }

                    $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
                    if (!is_dir($fullPath))
                        continue;

                    // Compute relative path for folder identification
                    $relativeFolder = $subName ? $subName . '/' . $item : $item;

                    if (in_array(strtolower($relativeFolder), $knownFolders, true)) {
                        continue;
                    }

                    $timestamp = @filemtime($fullPath) ?: time();
                    $entry = [
                        'when' => date('Y-m-d H:i:s', $timestamp),
                        'folder' => $relativeFolder,
                        'serverPath' => '/storage/EDMS/BLIND_SCAN/' . ($subName ? $subName . '/' : '') . $item,
                        'user' => auth()->user()->name ?? 'System'
                    ];

                    try {
                        $alreadySynced = BlindScanning::where('local_pc_path', $relativeFolder)->exists();

                        if (!$alreadySynced) {
                            $registryType = 'lands';
                            if (str_contains(strtolower($subName), 'cadastral'))
                                $registryType = 'cadastral';

                            $this->saveFilesToDatabase($fullPath, $item, $registryType, $relativeFolder);
                            $dbAddedCount++;
                            $entry['db_status'] = 'synced';
                        } else {
                            $dbSkippedCount++;
                            $entry['db_status'] = 'already_synced';
                        }

                        $newEntries[] = $entry;
                    } catch (\Exception $syncException) {
                        Log::error('Failed to sync blind scan folder to database', [
                            'folder' => $relativeFolder,
                            'error' => $syncException->getMessage()
                        ]);

                        $errors[] = [
                            'folder' => $relativeFolder,
                            'message' => $syncException->getMessage()
                        ];
                    }
                }
            }

            if (empty($newEntries)) {
                return response()->json([
                    'ok' => true,
                    'added' => [],
                    'added_count' => 0,
                    'db_added_count' => $dbAddedCount,
                    'db_skipped_count' => $dbSkippedCount,
                    'logs' => $existingLogs,
                    'errors' => $errors,
                    'message' => empty($errors) ? 'No new folders detected.' : 'No new entries logged. See errors for details.'
                ]);
            }

            $combined = array_merge($newEntries, $existingLogs);

            usort($combined, function ($a, $b) {
                return strcmp($b['when'] ?? '', $a['when'] ?? '');
            });

            $combined = array_slice($combined, 0, 100);

            file_put_contents($logFile, json_encode($combined, JSON_PRETTY_PRINT));

            return response()->json([
                'ok' => true,
                'added' => $newEntries,
                'added_count' => count($newEntries),
                'db_added_count' => $dbAddedCount,
                'db_skipped_count' => $dbSkippedCount,
                'errors' => $errors,
                'logs' => $combined
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync blind scan migration log', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Failed to sync migration log. ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint to list server files
     */
    public function apiList(Request $request)
    {
        try {
            $subPath = trim($request->get('path', ''), "/\\");

            // Prevent directory traversal
            if (strpos($subPath, '..') !== false) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid path'
                ], 400);
            }

            $basePath = file_storage_path('app/public/EDMS/BLIND_SCAN');
            $fullPath = $subPath ? $basePath . DIRECTORY_SEPARATOR . $subPath : $basePath;

            if (!is_dir($fullPath)) {
                return response()->json([
                    'ok' => true,
                    'root' => '/storage/EDMS/BLIND_SCAN',
                    'sub' => $subPath,
                    'crumbs' => [],
                    'items' => []
                ]);
            }

            // Check if user is admin
            $isAdmin = Auth::check() && Auth::user()->type == 'super admin';
            $currentUserId = Auth::id();

            // Get user's allowed folders from database
            $allowedFolders = [];
            if (!$isAdmin) {
                // Get folders uploaded by this user
                $userFolders = BlindScanning::where('uploaded_by', $currentUserId)
                    ->select('local_pc_path')
                    ->distinct()
                    ->pluck('local_pc_path')
                    ->toArray();

                $allowedFolders = array_map('strtolower', $userFolders);
            }

            $items = [];
            $files = scandir($fullPath);

            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || $file === '_migrations.json') {
                        continue;
                    }

                    $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
                    $isDir = is_dir($filePath);

                    // Filter directories based on user permissions
                    if ($isDir && !$isAdmin) {
                        $lowerFile = strtolower($file);
                        $relativeItemPath = strtolower(($subPath ? str_replace('\\', '/', $subPath) . '/' : '') . $file);

                        if (empty($subPath)) {
                            // Root level: Allow system folders, filter others
                            $isSystemFolder = in_array($lowerFile, ['lands_registry_raw', 'cadastral_registry_raw']);
                            if (!$isSystemFolder && !in_array($relativeItemPath, $allowedFolders)) {
                                continue;
                            }
                        } else {
                            // Subfolder level: If inside a system folder, apply filter
                            $lowerSubPath = strtolower($subPath);
                            if (in_array($lowerSubPath, ['lands_registry_raw', 'cadastral_registry_raw'])) {
                                if (!in_array($relativeItemPath, $allowedFolders)) {
                                    continue;
                                }
                            }
                        }
                    }

                    // Build the public URL for files - storage symlink points to storage/app/public
                    $publicPath = 'storage/EDMS/BLIND_SCAN/' . ($subPath ? $subPath . '/' : '') . $file;
                    $publicUrl = $isDir ? null : url($publicPath);

                    $items[] = [
                        'name' => $file,
                        'type' => $isDir ? 'dir' : 'file',
                        'size' => $isDir ? null : filesize($filePath),
                        'mtime' => filemtime($filePath),
                        'href' => $publicUrl
                    ];
                }
            }

            // Sort: directories first, then files
            usort($items, function ($a, $b) {
                if ($a['type'] === $b['type']) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $a['type'] === 'dir' ? -1 : 1;
            });

            // Build breadcrumbs
            $crumbs = [];
            if ($subPath) {
                $parts = explode('/', str_replace('\\', '/', $subPath));
                $acc = '';
                foreach ($parts as $i => $part) {
                    $acc .= ($i === 0 ? '' : '/') . $part;
                    $crumbs[] = ['name' => $part, 'path' => $acc];
                }
            }

            return response()->json([
                'ok' => true,
                'root' => '/storage/EDMS/BLIND_SCAN',
                'sub' => $subPath,
                'crumbs' => $crumbs,
                'items' => $items,
                'isAdmin' => $isAdmin,
                'userId' => $currentUserId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Failed to list files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint to get migration logs
     */
    public function apiLogs()
    {
        try {
            $logFile = file_storage_path('app/public/EDMS/BLIND_SCAN/_migrations.json');
            $logs = [];

            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                if ($content) {
                    $logs = json_decode($content, true) ?: [];
                }
            }

            // Check if user is admin
            $isAdmin = Auth::check() && Auth::user()->type == 'super admin';
            $currentUserId = Auth::id();
            $currentUserName = Auth::user()->name ?? '';

            // Filter logs based on user permissions
            if (!$isAdmin) {
                $logs = array_filter($logs, function ($log) use ($currentUserName, $currentUserId) {
                    // Match by user name in the log OR by checking BlindScanning records
                    if (isset($log['user']) && $log['user'] === $currentUserName) {
                        return true;
                    }

                    // Check if the folder belongs to the current user
                    if (isset($log['folder'])) {
                        $folderExists = BlindScanning::where('local_pc_path', $log['folder'])
                            ->where('uploaded_by', $currentUserId)
                            ->exists();
                        return $folderExists;
                    }

                    return false;
                });

                // Re-index array after filtering
                $logs = array_values($logs);
            }

            return response()->json([
                'ok' => true,
                'logs' => $logs,
                'isAdmin' => $isAdmin,
                'userId' => $currentUserId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Failed to get logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint returning aggregated counts per file_number and paper_size
     */
    public function aggregates()
    {
        try {
            // Fetch minimal fields to avoid heavy payloads
            $rows = BlindScanning::select('id', 'file_number', 'paper_size', 'original_filename', 'document_path', 'created_at')
                ->orderBy('file_number')
                ->orderBy('created_at', 'desc')
                ->get();

            $grouped = [];
            foreach ($rows as $r) {
                $fn = $r->file_number ?? 'UNASSIGNED';
                if (!isset($grouped[$fn])) {
                    $grouped[$fn] = [
                        'file_number' => $fn,
                        'counts' => [],
                        'total' => 0,
                        'records' => []
                    ];
                }

                $size = $r->paper_size ?? 'A4';
                if (!isset($grouped[$fn]['counts'][$size])) {
                    $grouped[$fn]['counts'][$size] = 0;
                }
                $grouped[$fn]['counts'][$size]++;
                $grouped[$fn]['total']++;

                $grouped[$fn]['records'][] = [
                    'id' => $r->id,
                    'original_filename' => $r->original_filename,
                    'paper_size' => $size,
                    'document_path' => $r->document_path,
                    'created_at' => $r->created_at,
                ];
            }

            // Re-index by numeric array preserving order
            $result = array_values($grouped);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Aggregates endpoint failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to compute aggregates'
            ], 500);
        }
    }

    /**
     * Extract ZIP file using alternative methods
     */
    private function extractZipFile($zipPath, $targetPath)
    {
        Log::info('Starting ZIP extraction', [
            'zip_path' => $zipPath,
            'target_path' => $targetPath,
            'os_family' => PHP_OS_FAMILY,
            'ziparchive_exists' => class_exists('ZipArchive')
        ]);

        try {
            // Method 1: Try using PowerShell on Windows (most reliable on Windows)
            if (PHP_OS_FAMILY === 'Windows') {
                Log::info('Attempting PowerShell extraction');

                // Use PowerShell with proper escaping for Windows paths
                $command = sprintf(
                    'powershell -Command "Expand-Archive -Path \'%s\' -DestinationPath \'%s\' -Force"',
                    str_replace("'", "''", $zipPath),
                    str_replace("'", "''", $targetPath)
                );

                Log::info('PowerShell command', ['command' => $command]);

                exec($command, $output, $returnCode);

                Log::info('PowerShell result', [
                    'return_code' => $returnCode,
                    'output' => $output
                ]);

                if ($returnCode === 0) {
                    return true;
                }
            }

            // Method 2: Try using 7-Zip on Windows
            if (PHP_OS_FAMILY === 'Windows') {
                Log::info('Attempting 7-Zip extraction');

                $sevenZipPaths = [
                    'C:\\Program Files\\7-Zip\\7z.exe',
                    'C:\\Program Files (x86)\\7-Zip\\7z.exe'
                ];

                foreach ($sevenZipPaths as $sevenZipPath) {
                    if (file_exists($sevenZipPath)) {
                        Log::info('Found 7-Zip', ['path' => $sevenZipPath]);

                        $command = sprintf(
                            '"%s" x "%s" -o"%s" -y',
                            $sevenZipPath,
                            $zipPath,
                            $targetPath
                        );

                        Log::info('7-Zip command', ['command' => $command]);

                        exec($command, $output, $returnCode);

                        Log::info('7-Zip result', [
                            'return_code' => $returnCode,
                            'output' => $output
                        ]);

                        if ($returnCode === 0) {
                            return true;
                        }
                    }
                }
            }

            // Method 3: Try using system unzip command (Linux/Unix)
            if (PHP_OS_FAMILY !== 'Windows' && $this->commandExists('unzip')) {
                Log::info('Attempting unzip command');

                $command = sprintf(
                    'unzip -q %s -d %s',
                    escapeshellarg($zipPath),
                    escapeshellarg($targetPath)
                );

                exec($command, $output, $returnCode);

                Log::info('Unzip result', [
                    'return_code' => $returnCode,
                    'output' => $output
                ]);

                if ($returnCode === 0) {
                    return true;
                }
            }

            // Method 4: Try using ZipArchive if available (last resort)
            if (class_exists('ZipArchive')) {
                Log::info('Attempting ZipArchive extraction');

                try {
                    $zip = new \ZipArchive();
                    if ($zip->open($zipPath) === true) {
                        // Security check: prevent directory traversal
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (strpos($filename, '..') !== false || preg_match('#^[/\\\\]#', $filename)) {
                                $zip->close();
                                throw new \Exception('Unsafe path in ZIP file');
                            }
                        }

                        $result = $zip->extractTo($targetPath);
                        $zip->close();

                        Log::info('ZipArchive result', ['success' => $result]);

                        if ($result) {
                            return true;
                        }
                    }
                } catch (\Error $e) {
                    Log::warning('ZipArchive failed', ['error' => $e->getMessage()]);
                }
            }

            // Method 5: Fallback - just copy the ZIP file and let user know
            Log::info('All extraction methods failed, using fallback');

            $fallbackPath = $targetPath . DIRECTORY_SEPARATOR . 'uploaded.zip';
            if (copy($zipPath, $fallbackPath)) {
                // Create a readme file explaining the situation
                $readmePath = $targetPath . DIRECTORY_SEPARATOR . 'README.txt';
                $readmeContent = "ZIP extraction failed due to missing ZIP libraries or tools.\n";
                $readmeContent .= "The uploaded ZIP file has been saved as 'uploaded.zip'.\n";
                $readmeContent .= "Please extract it manually or contact your system administrator.\n";
                $readmeContent .= "Attempted methods: PowerShell, 7-Zip, ZipArchive\n";
                $readmeContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
                file_put_contents($readmePath, $readmeContent);

                Log::warning('ZIP extraction failed, saved as uploaded.zip', [
                    'target_path' => $targetPath,
                    'zip_path' => $zipPath
                ]);

                return true; // Return true so the process continues
            }

            Log::error('Even fallback copy failed');
            return false;

        } catch (\Exception $e) {
            Log::error('ZIP extraction failed with exception', [
                'error' => $e->getMessage(),
                'zip_path' => $zipPath,
                'target_path' => $targetPath
            ]);
            return false;
        }
    }

    /**
     * Handle direct file uploads without zipping
     */
    private function handleDirectUpload(Request $request, $targetPath)
    {
        try {
            Log::info('Starting direct file upload', [
                'target_path' => $targetPath,
                'files_count' => count($request->allFiles())
            ]);

            $files = $request->allFiles();
            $paths = $request->input('paths', []);

            if (empty($files)) {
                Log::error('No files found in request');
                return false;
            }

            $uploadedCount = 0;

            // Process each uploaded file
            foreach ($files as $key => $fileArray) {
                if (is_array($fileArray)) {
                    foreach ($fileArray as $index => $file) {
                        $this->processUploadedFile($file, $paths[$index] ?? null, $targetPath);
                        $uploadedCount++;
                    }
                } else {
                    $this->processUploadedFile($fileArray, $paths[0] ?? null, $targetPath);
                    $uploadedCount++;
                }
            }

            Log::info('Direct upload completed', [
                'uploaded_count' => $uploadedCount,
                'target_path' => $targetPath
            ]);

            return $uploadedCount > 0;

        } catch (\Exception $e) {
            Log::error('Direct upload failed', [
                'error' => $e->getMessage(),
                'target_path' => $targetPath
            ]);
            return false;
        }
    }

    /**
     * Process a single uploaded file
     */
    private function processUploadedFile($file, $relativePath, $targetPath)
    {
        try {
            if (!$file || !$file->isValid()) {
                Log::warning('Invalid file skipped', ['relative_path' => $relativePath]);
                return false;
            }

            // Use the relative path if provided, otherwise use the original name
            $filePath = $relativePath ?: $file->getClientOriginalName();

            // Sanitize the file path to prevent directory traversal
            $filePath = str_replace(['../', '..\\'], '', $filePath);
            $filePath = ltrim($filePath, '/\\');

            // Create the full destination path
            $destinationPath = $targetPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);

            // Create directory structure if it doesn't exist
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir)) {
                if (!mkdir($destinationDir, 0775, true)) {
                    Log::error('Failed to create directory', ['dir' => $destinationDir]);
                    return false;
                }
            }

            // Capture file metadata before moving, as the temp file will be gone after move
            $fileSize = $file->getSize();
            $originalName = $file->getClientOriginalName();

            // Move the uploaded file to its destination
            if ($file->move($destinationDir, basename($destinationPath))) {
                Log::info('File uploaded successfully', [
                    'original_name' => $originalName,
                    'destination' => $destinationPath,
                    'size' => $fileSize
                ]);
                return true;
            } else {
                Log::error('Failed to move file', [
                    'original_name' => $originalName,
                    'destination' => $destinationPath
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error processing uploaded file', [
                'error' => $e->getMessage(),
                'relative_path' => $relativePath
            ]);
            return false;
        }
    }

    /**
     * Check if a command exists on the system
     */
    private function commandExists($command)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("where $command 2>nul");
            return !empty($output);
        } else {
            $output = shell_exec("which $command 2>/dev/null");
            return !empty($output);
        }
    }

    /**
     * Extract file number from folder name
     */
    private function extractFileNumberFromFolderName($folderName)
    {
        // Remove any timestamp suffixes first (e.g., _123456 at the end)
        $cleanFolderName = preg_replace('/_\d{6}$/', '', $folderName);

        // Pattern 1 & 2 & 3 & 5: Word followed by separator then one or more number groups with separators
        // This will match things like IND-2025-34589, KANGIS/123, etc.
        if (preg_match('/^([A-Za-z]+)[\-_\/](\d+(?:[\-_\/]\d+)*)/', $cleanFolderName, $matches)) {
            // Convert all separators in the file number part to dashes for consistency
            $prefix = $matches[1];
            $suffix = str_replace(['/', '_'], '-', $matches[2]);
            return $prefix . '-' . $suffix;
        }

        // Pattern 4: Just numbers (6 or more digits)
        if (preg_match('/(\d{6,})/', $cleanFolderName, $matches)) {
            return $matches[1];
        }

        // Fallback: return the original folder name but cleaned
        return $cleanFolderName;
    }

    /**
     * Get page count from file (estimate based on file size for now)
     */
    private function getPageCountFromFile($file)
    {
        // For now, estimate 1 page per file
        // This could be enhanced to actually count pages in PDFs, count images, etc.
        $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            if ($file->getSize() > self::PDF_PAGE_SIZE_THRESHOLD_BYTES) {
                return 1;
            }
            return $this->getPdfPageCount($file->getPathname());
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp'])) {
            // Images are typically 1 page each
            return 1;
        } else {
            // Default to 1 page for other file types
            return 1;
        }
    }

    /**
     * Get PDF page count
     */
    private function getPdfPageCount($filePath)
    {
        try {
            $handle = @fopen($filePath, 'rb');
            if (!$handle) {
                return 1;
            }

            $buffer = '';
            $bytesRead = 0;
            $chunkSize = 8192;

            while (!feof($handle) && $bytesRead < self::PDF_PAGE_SCAN_LIMIT_BYTES) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    break;
                }
                $buffer .= $chunk;
                $bytesRead += strlen($chunk);
            }

            fclose($handle);

            if ($buffer === '') {
                return 1;
            }

            preg_match_all('/\/Type\s*\/Page[^s]/', $buffer, $matches);
            $pageCount = count($matches[0]);

            if ($pageCount === 0) {
                preg_match('/\/Count\s+(\d+)/', $buffer, $matches);
                if (isset($matches[1])) {
                    $pageCount = (int) $matches[1];
                }
            }

            return $pageCount > 0 ? $pageCount : 1;
        } catch (\Exception $e) {
            Log::warning('Could not count PDF pages', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return 1; // Default to 1 page if counting fails
    }

    /**
     * API endpoint to save edited image
     */
    public function apiSaveImage(Request $request)
    {
        try {
            if (empty($request->input('filePath')) || !$request->hasFile('image')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Missing filePath or image data'
                ], 400);
            }

            $filePath = $request->input('filePath');
            $imageFile = $request->file('image');

            // Parse the file path - it comes from href which may be like /storage/EDMS/BLIND_SCAN/...
            // Remove /storage/ prefix if present
            $cleanPath = preg_replace('#^/?storage/EDMS/BLIND_SCAN/#', '', $filePath);

            // Also handle if it's already relative
            $cleanPath = preg_replace('#^EDMS/BLIND_SCAN/#', '', $cleanPath);

            // Security: Validate file path is within storage directory
            $storageRoot = file_storage_path('app/public/EDMS/BLIND_SCAN');
            $targetPath = $this->safeJoinUnderStorage($storageRoot, $cleanPath);

            // Ensure the target path is actually within storage
            $realStorageRoot = realpath($storageRoot);
            $realTargetPath = realpath($targetPath);

            if (!$realStorageRoot || !$realTargetPath || strpos($realTargetPath, $realStorageRoot) !== 0) {
                Log::warning('Invalid file path attempt', [
                    'original_path' => $filePath,
                    'clean_path' => $cleanPath,
                    'target_path' => $targetPath,
                    'storage_root' => $storageRoot,
                    'real_storage_root' => $realStorageRoot,
                    'real_target_path' => $realTargetPath
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid file path'
                ], 403);
            }

            // Check if file exists and is writable
            if (!file_exists($targetPath)) {
                Log::error('File not found for save', [
                    'original_path' => $filePath,
                    'target_path' => $targetPath,
                    'storage_root' => $storageRoot
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'File not found: ' . basename($filePath)
                ], 404);
            }

            if (!is_writable($targetPath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'File is not writable'
                ], 403);
            }

            // Validate uploaded image
            if ($imageFile->getError() !== UPLOAD_ERR_OK) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Image upload failed'
                ], 400);
            }

            // Check if it's actually an image
            $imageInfo = getimagesize($imageFile->getPathname());
            if (!$imageInfo) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid image file'
                ], 400);
            }

            // Move uploaded file to replace original
            if ($imageFile->move(dirname($targetPath), basename($targetPath))) {
                // Clear file stat cache and get fresh file info
                clearstatcache(true, $targetPath);

                return response()->json([
                    'ok' => true,
                    'message' => 'Image saved successfully',
                    'filePath' => $filePath,
                    'fileSize' => filesize($targetPath),
                    'mtime' => filemtime($targetPath),
                    'cacheBuster' => time() // Add cache buster to force refresh
                ]);
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'Failed to save image'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Save image failed', [
                'error' => $e->getMessage(),
                'file_path' => $request->input('filePath')
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Failed to save image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint to delete file
     */
    public function apiDeleteFile(Request $request)
    {
        try {
            if (empty($request->input('filePath'))) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Missing filePath'
                ], 400);
            }

            $filePath = $request->input('filePath');

            // Parse the file path - it comes from href which may be like /storage/EDMS/BLIND_SCAN/...
            // Remove /storage/ prefix if present
            $cleanPath = preg_replace('#^/?storage/EDMS/BLIND_SCAN/#', '', $filePath);

            // Also handle if it's already relative
            $cleanPath = preg_replace('#^EDMS/BLIND_SCAN/#', '', $cleanPath);

            // Security: Validate file path is within storage directory
            $storageRoot = file_storage_path('app/public/EDMS/BLIND_SCAN');
            $targetPath = $this->safeJoinUnderStorage($storageRoot, $cleanPath);

            // Ensure the target path is actually within storage
            $realStorageRoot = realpath($storageRoot);
            $realTargetPath = realpath($targetPath);

            if (!$realStorageRoot || !$realTargetPath || strpos($realTargetPath, $realStorageRoot) !== 0) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid file path'
                ], 403);
            }

            // Check if file exists
            if (!file_exists($targetPath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'File not found'
                ], 404);
            }

            // Prevent deletion of directories via this endpoint
            if (is_dir($targetPath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Cannot delete directories'
                ], 400);
            }

            // Delete the file
            if (unlink($targetPath)) {
                // Also delete from database if it exists
                $relativePath = 'EDMS/BLIND_SCAN/' . $filePath;
                BlindScanning::where('document_path', $relativePath)->delete();

                return response()->json([
                    'ok' => true,
                    'message' => 'File deleted successfully',
                    'filePath' => $filePath
                ]);
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'Failed to delete file'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Delete file failed', [
                'error' => $e->getMessage(),
                'file_path' => $request->input('filePath')
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Failed to delete file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Safe join path under storage directory (similar to your original implementation)
     */
    private function safeJoinUnderStorage($storageRoot, $subPath)
    {
        $path = $storageRoot . DIRECTORY_SEPARATOR . $subPath;
        $realRoot = realpath($storageRoot) ?: $storageRoot;
        $realPath = realpath($path) ?: $path; // allow non-existing
        $realRootNorm = rtrim(str_replace('\\', '/', $realRoot), '/');
        $realPathNorm = rtrim(str_replace('\\', '/', $realPath), '/');

        if (strpos($realPathNorm, $realRootNorm) !== 0) {
            return $realRoot;
        }

        return $realPath;
    }
}
