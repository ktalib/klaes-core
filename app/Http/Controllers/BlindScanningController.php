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

    /** Image types a blind-scan folder may hold; everything else (Thumbs.db, desktop.ini, .lnk) is skipped. */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'webp', 'pdf'];

    /**
     * Administrators see every user's uploads. Same rule the rest of KLAES
     * uses: "Supper Admin" (the app's spelling) in users.assign_role.
     */
    private function isBlindScanAdmin(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return collect(explode(',', (string) $user->assign_role))
            ->map(fn ($role) => strtolower(trim($role)))
            ->contains('supper admin');
    }

    /**
     * Resolve the registry picked on the page to its BLIND_SCAN folder.
     *
     * The dropdown sends registries.code (LANDS, CAD, DCIV, ...); older callers
     * sent "lands"/"cadastral". Before this, anything other than those two words
     * fell through to Lands, so every upload landed in Lands_Registry_Raw.
     *
     * @return array{0:string,1:string,2:string} [folder, registry_type, canonical name]
     */
    private function resolveBlindRegistry(?string $value): array
    {
        $value = trim((string) $value);
        $name = $value;

        if ($value !== '') {
            $row = DB::connection('sqlsrv')->table('registries')
                ->where('code', $value)
                ->orWhere('name', $value)
                ->first(['name']);
            if ($row) {
                $name = $row->name;
            }
        }

        $paths = app(\App\Services\Edms\EdmsDocumentPathResolver::class);
        $canonical = $paths->registryName($name !== '' ? $name : 'Lands Registry');
        $folder = $paths->blindScanRegistryFolder($canonical);
        $type = strtolower(trim(preg_replace('/\s+registry$/i', '', $canonical)));

        return [$folder, mb_substr($type, 0, 50), $canonical];
    }

    /** Folder names under BLIND_SCAN that hold registry trees, e.g. Lands_Registry_Raw. */
    private function isRegistryRawFolder(string $name): bool
    {
        return (bool) preg_match('/_Raw$/i', $name);
    }

    /**
     * Whether the current user uploaded anything into this folder
     * ("Lands_Registry_Raw/RES-1981-141"). Seeks on the status + uploaded_by
     * index before comparing the (unindexable) path column.
     */
    private function userOwnsFolder(string $relativeFolder): bool
    {
        return BlindScanning::whereIn('status', [BlindScanning::STATUS_PENDING, BlindScanning::STATUS_CONVERTED, BlindScanning::STATUS_ARCHIVED])
            ->where('uploaded_by', (string) Auth::id())
            ->where('local_pc_path', $relativeFolder)
            ->exists();
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
     * Handle migration of blind scanning files.
     *
     * The page sends a folder in batches (batch_index / batch_total), so a large
     * folder never hits the request-size limit and the progress bar is real.
     * A batch can be retried safely: a file already on the server with the same
     * size is skipped, never overwritten, and only files written by this request
     * are recorded in blind_scannings. Adding scans to a file that already has a
     * folder is allowed — they join it rather than being refused.
     */
    public function migrate(Request $request)
    {
        try {
            $rawFileNumber = trim((string) $request->input('folderName'));
            if ($rawFileNumber === '') {
                return response()->json(['ok' => false, 'error' => 'Select a file number first.'], 400);
            }

            $folderName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $rawFileNumber);
            $folderName = trim($folderName, '._');
            if ($folderName === '') {
                return response()->json(['ok' => false, 'error' => 'That file number cannot be used as a folder name.'], 422);
            }

            $uploadMethod = $request->input('uploadMethod', 'direct');
            $registryInput = $request->input('registry', $request->input('registry_type'));
            if (trim((string) $registryInput) === '') {
                return response()->json(['ok' => false, 'error' => 'Select the registry first.'], 422);
            }
            [$registrySubfolder, $registryType] = $this->resolveBlindRegistry($registryInput);

            $storagePath = file_storage_path('app/public/EDMS/BLIND_SCAN/' . $registrySubfolder);
            if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
                return response()->json(['ok' => false, 'error' => 'Could not create the registry folder on the server.'], 500);
            }

            $targetPath = $storagePath . DIRECTORY_SEPARATOR . $folderName;
            if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                return response()->json(['ok' => false, 'error' => 'Could not create the file folder on the server.'], 500);
            }

            $batchIndex = max(0, (int) $request->input('batch_index', 0));
            $batchTotal = max(1, (int) $request->input('batch_total', 1));
            $isFinalBatch = $batchIndex >= $batchTotal - 1;
            $localPcPath = $registrySubfolder . '/' . $folderName;

            if ($uploadMethod === 'direct') {
                $result = $this->handleDirectUpload($request, $targetPath);
                if (!$result['saved'] && !$result['skipped']) {
                    return response()->json([
                        'ok' => false,
                        'error' => $result['failed']
                            ? 'The server could not save: ' . implode(', ', array_slice($result['failed'], 0, 5))
                            : 'No image files were received.',
                    ], 422);
                }

                $registered = $result['saved']
                    ? $this->saveFilesToDatabase($targetPath, $folderName, $registryType, $localPcPath, $result['saved'], $registrySubfolder)
                    : 0;

                if ($isFinalBatch) {
                    $this->logMigration($localPcPath, $targetPath);
                }

                return response()->json([
                    'ok' => true,
                    'message' => $isFinalBatch ? 'Migration completed successfully' : 'Batch saved',
                    'serverPath' => '/storage/EDMS/BLIND_SCAN/' . $registrySubfolder . '/' . $folderName,
                    'registryFolder' => $registrySubfolder,
                    'saved' => count($result['saved']),
                    'skipped' => count($result['skipped']),
                    'renamed' => $result['renamed'],
                    'rejected' => $result['rejected'],
                    'failed' => $result['failed'],
                    'registered' => $registered,
                    'final' => $isFinalBatch,
                ]);
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

                // Files already in the folder were recorded earlier; only the
                // ones the archive adds get rows.
                $before = array_flip($this->relativeFilesIn($targetPath));

                if (!$this->extractZipFile($zipPath, $targetPath)) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Failed to extract ZIP file'
                    ], 500);
                }

                $added = array_values(array_filter(
                    $this->relativeFilesIn($targetPath),
                    fn ($relative) => !isset($before[$relative])
                ));
            }

            // Log the migration
            $this->logMigration($localPcPath, $targetPath);

            // Save files to database
            $this->saveFilesToDatabase($targetPath, $folderName, $registryType, $localPcPath, $added ?? [], $registrySubfolder);

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
    /**
     * Every file under a folder, as forward-slash paths relative to it.
     */
    private function relativeFilesIn(string $folder): array
    {
        if (!is_dir($folder)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
            }
        }

        return $files;
    }

    /**
     * Record a folder's files in blind_scannings.
     *
     * @param array|null $onlyRelativePaths When given, only these files (paths
     *        relative to $targetPath) are recorded — the ones this upload wrote.
     *        That keeps a second upload into an existing folder from recording
     *        its earlier files again, and keeps PDF page images the conversion
     *        writes from being recorded twice.
     * @return int rows inserted
     */
    private function saveFilesToDatabase($targetPath, $folderName, $registryType = 'lands', $localPcPath = null, ?array $onlyRelativePaths = null, ?string $registrySubfolder = null)
    {
        try {
            $fileNumber = $this->extractFileNumberFromFolderName($folderName);
            $dbPathValue = $localPcPath ?: $folderName;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($targetPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $registrySubfolder = $registrySubfolder
                ?: ($registryType === 'cadastral' ? 'Cadastral_Registry_Raw' : 'Lands_Registry_Raw');
            $onlySet = $onlyRelativePaths === null
                ? null
                : array_flip(array_map(fn ($p) => strtolower(str_replace('\\', '/', $p)), $onlyRelativePaths));
            if ($onlySet !== null && !$onlySet) {
                return 0;
            }
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

                if ($onlySet !== null && !isset($onlySet[strtolower($relativePath)])) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }
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

            return $totalInserted;
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
     * Record folders copied onto the server outside this page (e.g. by Windows
     * Explorer) that have no blind_scannings rows yet. Admin only.
     *
     * Uploads through the page record their own rows, so this is only for
     * bulk copies. It used to walk all 140k+ folders inside one web request
     * and was fired automatically by every open page, timing out each time.
     * Now it hands the work to blind-scan:register for recently added folders;
     * the scheduler runs the same command every night.
     */
    public function syncMigrationLog(Request $request)
    {
        if (!$this->isBlindScanAdmin()) {
            return response()->json(['ok' => false, 'error' => 'Only an administrator can register copied folders.'], 403);
        }

        try {
            @set_time_limit(900);
            $days = max(1, min((int) $request->input('days', 2), 30));

            \Illuminate\Support\Facades\Artisan::call('blind-scan:register', [
                '--registry' => 'all',
                '--since' => now()->subDays($days)->toDateString(),
                '--user' => (string) Auth::id(),
            ]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return response()->json([
                'ok' => true,
                'message' => 'Registered folders added in the last ' . $days . ' day(s).',
                'output' => $output,
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

            $isAdmin = $this->isBlindScanAdmin();
            $currentUserId = Auth::id();
            // Admins may narrow the browser to their own uploads.
            $mineOnly = !$isAdmin || $request->boolean('mine');
            $segments = $subPath === '' ? [] : explode('/', str_replace('\\', '/', $subPath));

            // Inside a file folder: only its uploader (or an admin) may look.
            if (!$isAdmin && count($segments) >= 2 && $this->isRegistryRawFolder($segments[0])) {
                if (!$this->userOwnsFolder($segments[0] . '/' . $segments[1])) {
                    return response()->json(['ok' => false, 'error' => 'You can only open folders you uploaded.'], 403);
                }
            }

            // A registry folder (Lands_Registry_Raw) holds 140k+ file folders:
            // never list it from disk. Show recent uploads from the database,
            // or search the folder names when the user types a file number.
            if (count($segments) === 1 && $this->isRegistryRawFolder($segments[0])) {
                return response()->json($this->listRegistryFolder($segments[0], trim((string) $request->get('q', '')), $mineOnly, $isAdmin));
            }

            $items = [];
            $files = scandir($fullPath);

            // Inside a file folder: who uploaded each file, from its rows.
            $fileUploaders = [];
            $folderUploader = null;
            if (count($segments) >= 2 && $this->isRegistryRawFolder($segments[0])) {
                $rows = DB::connection('sqlsrv')->table('blind_scannings')
                    ->where('local_pc_path', $segments[0] . '/' . $segments[1])
                    ->get(['document_path', 'uploaded_by']);
                $userNames = $this->userNames($rows->pluck('uploaded_by'));
                foreach ($rows as $row) {
                    $label = $row->uploaded_by === null ? null : ($userNames[(string) $row->uploaded_by] ?? 'User #' . $row->uploaded_by);
                    $fileUploaders[strtolower(str_replace('\\', '/', (string) $row->document_path))] = $label;
                    $folderUploader = $folderUploader ?? $label;
                }
            }

            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || $file === '_migrations.json') {
                        continue;
                    }

                    $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
                    $isDir = is_dir($filePath);

                    // At the top level show only the registry folders; stray
                    // files/folders there are not a user's uploads.
                    if (empty($subPath) && (!$isDir || !$this->isRegistryRawFolder($file))) {
                        continue;
                    }

                    // Build the public URL for files - storage symlink points to storage/app/public
                    $publicPath = 'storage/EDMS/BLIND_SCAN/' . ($subPath ? $subPath . '/' : '') . $file;
                    $publicUrl = $isDir ? null : url($publicPath);

                    $relativeDoc = strtolower('EDMS/BLIND_SCAN/' . ($subPath ? str_replace('\\', '/', $subPath) . '/' : '') . $file);
                    $items[] = [
                        'name' => $file,
                        'type' => $isDir ? 'dir' : 'file',
                        'size' => $isDir ? null : filesize($filePath),
                        'mtime' => filemtime($filePath),
                        'href' => $publicUrl,
                        // A subfolder (A4/A3) shows the folder's uploader.
                        'uploaded_by' => $isDir ? $folderUploader : ($fileUploaders[$relativeDoc] ?? null),
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
                'mineOnly' => $mineOnly,
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
     * The folders shown for one registry folder (e.g. Lands_Registry_Raw).
     *
     * Without a search: the most recently uploaded folders, from the database
     * (indexed on created_at, so it is quick). With a search: folder names on
     * disk containing the text, matched by the filesystem itself. Non-admins
     * only ever get their own folders.
     */
    private function listRegistryFolder(string $registryFolder, string $search, bool $mineOnly, bool $isAdmin): array
    {
        $limit = 300;
        $base = file_storage_path('app/public/EDMS/BLIND_SCAN/' . $registryFolder);
        $items = [];
        $note = null;

        if ($search !== '') {
            $needle = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $search);
            $matches = $needle === '' ? [] : (glob($base . DIRECTORY_SEPARATOR . '*' . $needle . '*', GLOB_ONLYDIR) ?: []);
            $names = array_map('basename', $matches);
            natcasesort($names);
            $names = array_values($names);

            if ($mineOnly && $names) {
                $owned = BlindScanning::whereIn('status', [BlindScanning::STATUS_PENDING, BlindScanning::STATUS_CONVERTED, BlindScanning::STATUS_ARCHIVED])
                    ->where('uploaded_by', (string) Auth::id())
                    ->where('local_pc_path', 'like', $registryFolder . '/%')
                    ->distinct()
                    ->pluck('local_pc_path')
                    ->map(fn ($p) => strtolower(basename(str_replace('\\', '/', $p))))
                    ->flip();
                $names = array_values(array_filter($names, fn ($n) => isset($owned[strtolower($n)])));
            }

            if (count($names) > $limit) {
                $note = 'Showing the first ' . $limit . ' of ' . count($names) . ' folders matching "' . $search . '". Type more of the file number to narrow it.';
                $names = array_slice($names, 0, $limit);
            }

            // Who uploaded each matching folder (one query for all of them).
            $uploaders = [];
            if ($names) {
                $uploaders = DB::connection('sqlsrv')->table('blind_scannings')
                    ->whereIn('local_pc_path', array_map(fn ($n) => $registryFolder . '/' . $n, $names))
                    ->groupBy('local_pc_path')
                    ->select('local_pc_path', DB::raw('MAX(uploaded_by) AS uploaded_by'))
                    ->pluck('uploaded_by', 'local_pc_path')
                    ->mapWithKeys(fn ($u, $p) => [strtolower(basename(str_replace('\\', '/', $p))) => $u])
                    ->all();
            }
            $userNames = $this->userNames(array_values($uploaders));

            foreach ($names as $name) {
                $path = $base . DIRECTORY_SEPARATOR . $name;
                $uploader = $uploaders[strtolower($name)] ?? null;
                $items[] = [
                    'name' => $name, 'type' => 'dir', 'size' => null, 'mtime' => @filemtime($path) ?: null, 'href' => null,
                    'uploaded_by' => $uploader === null ? null : ($userNames[(string) $uploader] ?? 'User #' . $uploader),
                ];
            }
        } else {
            $query = DB::connection('sqlsrv')->table('blind_scannings')
                ->where('created_at', '>=', now()->subDays(60))
                ->where('local_pc_path', 'like', $registryFolder . '/%');
            if ($mineOnly) {
                $query->whereIn('status', [BlindScanning::STATUS_PENDING, BlindScanning::STATUS_CONVERTED, BlindScanning::STATUS_ARCHIVED])
                    ->where('uploaded_by', (string) Auth::id());
            }
            $rows = $query->groupBy('local_pc_path')
                ->select('local_pc_path', DB::raw('MAX(created_at) AS last_at'), DB::raw('COUNT(*) AS files'), DB::raw('MAX(uploaded_by) AS uploaded_by'))
                ->orderByDesc(DB::raw('MAX(created_at)'))
                ->limit($limit)
                ->get();
            $userNames = $this->userNames($rows->pluck('uploaded_by'));

            foreach ($rows as $row) {
                $name = basename(str_replace('\\', '/', $row->local_pc_path));
                $items[] = [
                    'name' => $name,
                    'type' => 'dir',
                    'size' => null,
                    'mtime' => $row->last_at ? strtotime($row->last_at) : null,
                    'href' => null,
                    'files' => (int) $row->files,
                    'uploaded_by' => $row->uploaded_by === null ? null : ($userNames[(string) $row->uploaded_by] ?? 'User #' . $row->uploaded_by),
                ];
            }
            $note = ($mineOnly ? 'Your' : 'All') . ' uploads in the last 60 days, newest first. Search by file number to find older folders.';
        }

        return [
            'ok' => true,
            'root' => '/storage/EDMS/BLIND_SCAN',
            'sub' => $registryFolder,
            'crumbs' => [['name' => $registryFolder, 'path' => $registryFolder]],
            'items' => $items,
            'note' => $note,
            'searchable' => true,
            'isAdmin' => $isAdmin,
            'mineOnly' => $mineOnly,
            'userId' => Auth::id(),
        ];
    }

    /**
     * API endpoint to get migration logs
     */
    public function apiLogs(Request $request)
    {
        try {
            $isAdmin = $this->isBlindScanAdmin();
            $mineOnly = !$isAdmin || $request->boolean('mine');
            $logs = $this->uploadSummary($mineOnly, (int) $request->get('days', 30), 300);

            return response()->json([
                'ok' => true,
                'logs' => $logs,
                'isAdmin' => $isAdmin,
                'mineOnly' => $mineOnly,
                'userId' => Auth::id()
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
    /**
     * Per-folder summary of recent uploads: pages by paper size, how many have
     * already been taken into Scan Upload (status converted), uploader, dates.
     * Grouped in SQL over the created_at index — this used to load every row
     * in blind_scannings (1.7M+) into PHP on each page load.
     */
    private function uploadSummary(bool $mineOnly, int $days, int $limit): array
    {
        $days = max(1, min($days ?: 30, 365));

        $query = DB::connection('sqlsrv')->table('blind_scannings')
            ->where('created_at', '>=', now()->subDays($days));
        if ($mineOnly) {
            $query->whereIn('status', [BlindScanning::STATUS_PENDING, BlindScanning::STATUS_CONVERTED, BlindScanning::STATUS_ARCHIVED])
                ->where('uploaded_by', (string) Auth::id());
        }

        $rows = $query->groupBy('local_pc_path')
            ->select(
                'local_pc_path',
                DB::raw('MAX(file_number) AS file_number'),
                DB::raw('MAX(registry_type) AS registry_type'),
                DB::raw('MAX(uploaded_by) AS uploaded_by'),
                DB::raw('COUNT(*) AS files'),
                DB::raw("SUM(CASE WHEN paper_size = 'A3' THEN 1 ELSE 0 END) AS a3"),
                DB::raw("SUM(CASE WHEN paper_size = 'A4' OR paper_size IS NULL THEN 1 ELSE 0 END) AS a4"),
                DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted"),
                DB::raw('MIN(created_at) AS first_at'),
                DB::raw('MAX(created_at) AS last_at')
            )
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($limit)
            ->get();

        $userIds = $rows->pluck('uploaded_by')->filter(fn ($id) => is_numeric($id))->unique()->values();
        $names = $userIds->isEmpty()
            ? collect()
            : \App\Models\User::whereIn('id', $userIds->all())->get()->mapWithKeys(fn ($u) => [(string) $u->id => $u->name]);

        return $rows->map(function ($row) use ($names) {
            $folder = str_replace('\\', '/', (string) $row->local_pc_path);
            $files = (int) $row->files;
            $converted = (int) $row->converted;

            return [
                'folder' => $folder,
                'file_number' => $row->file_number ?: basename($folder),
                'registry' => str_contains($folder, '/') ? str_replace('_', ' ', preg_replace('/_Raw$/i', '', explode('/', $folder)[0])) : ucfirst((string) $row->registry_type),
                'registry_type' => $row->registry_type,
                'user' => $names[(string) $row->uploaded_by] ?? ($row->uploaded_by ? 'User #' . $row->uploaded_by : 'System'),
                'files' => $files,
                'a4' => (int) $row->a4,
                'a3' => (int) $row->a3,
                'converted' => $converted,
                'progress' => $converted === 0 ? 'Awaiting Scan Upload' : ($converted >= $files ? 'Moved to Scan Upload' : "Partly moved ({$converted}/{$files})"),
                'when' => $row->last_at,
                'first_at' => $row->first_at,
                'serverPath' => '/storage/EDMS/BLIND_SCAN/' . $folder,
            ];
        })->values()->all();
    }

    /**
     * Dashboard figures: pages and file folders uploaded today, this week and
     * in total, and how many pages are still waiting for Scan Upload. All
     * served by indexes (created_at, status + uploaded_by), so they are cheap.
     */
    private function dashboardStats(bool $mineOnly): array
    {
        $base = function () use ($mineOnly) {
            $query = DB::connection('sqlsrv')->table('blind_scannings');
            if ($mineOnly) {
                $query->whereIn('status', [BlindScanning::STATUS_PENDING, BlindScanning::STATUS_CONVERTED, BlindScanning::STATUS_ARCHIVED])
                    ->where('uploaded_by', (string) Auth::id());
            }
            return $query;
        };
        $counts = fn ($query) => $query->selectRaw('COUNT(*) AS pages, COUNT(DISTINCT file_number) AS folders')->first();

        $today = $counts($base()->where('created_at', '>=', now()->startOfDay()));
        $week = $counts($base()->where('created_at', '>=', now()->startOfWeek()));
        // The all-users total walks 1.7M+ rows for its folder count (~2.5s) and
        // hardly moves, so it is cached briefly. Personal totals are instant.
        $total = $mineOnly
            ? $counts($base())
            : (object) \Illuminate\Support\Facades\Cache::remember('blind_scan.dashboard.total_all', 300, function () use ($counts, $base) {
                return (array) $counts($base());
            });
        $byStatus = $base()->groupBy('status')->select('status', DB::raw('COUNT(*) AS n'))->pluck('n', 'status');

        $topToday = [];
        if (!$mineOnly) {
            $rows = DB::connection('sqlsrv')->table('blind_scannings')
                ->where('created_at', '>=', now()->startOfDay())
                ->groupBy('uploaded_by')
                ->select('uploaded_by', DB::raw('COUNT(*) AS pages'), DB::raw('COUNT(DISTINCT file_number) AS folders'))
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(5)
                ->get();
            $names = $this->userNames($rows->pluck('uploaded_by'));
            $topToday = $rows->map(fn ($r) => [
                'user' => $names[(string) $r->uploaded_by] ?? ($r->uploaded_by ? 'User #' . $r->uploaded_by : 'System'),
                'pages' => (int) $r->pages,
                'folders' => (int) $r->folders,
            ])->all();
        }

        return [
            'today' => ['pages' => (int) ($today->pages ?? 0), 'folders' => (int) ($today->folders ?? 0)],
            'week' => ['pages' => (int) ($week->pages ?? 0), 'folders' => (int) ($week->folders ?? 0)],
            'total' => ['pages' => (int) ($total->pages ?? 0), 'folders' => (int) ($total->folders ?? 0)],
            'awaiting' => (int) ($byStatus[BlindScanning::STATUS_PENDING] ?? 0),
            'moved' => (int) ($byStatus[BlindScanning::STATUS_CONVERTED] ?? 0),
            'top_today' => $topToday,
            'scope' => $mineOnly ? 'mine' : 'all',
        ];
    }

    /** users.id => display name for the given uploaded_by values. */
    private function userNames($ids): array
    {
        $ids = collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return \App\Models\User::whereIn('id', $ids->all())->get()
            ->mapWithKeys(fn ($u) => [(string) $u->id => $u->name])
            ->all();
    }

    public function aggregates(Request $request)
    {
        try {
            $isAdmin = $this->isBlindScanAdmin();
            $mineOnly = !$isAdmin || $request->boolean('mine');

            // Expanding a row loads that folder's files on demand.
            $folder = trim(str_replace('\\', '/', (string) $request->get('folder', '')), '/');
            if ($folder !== '') {
                if (!$isAdmin && !$this->userOwnsFolder($folder)) {
                    return response()->json(['success' => false, 'message' => 'You can only view folders you uploaded.'], 403);
                }
                // The summary row passes its first upload time so the lookup can
                // seek on the created_at index; the path column is unindexable.
                $since = strtotime((string) $request->get('since', '')) ?: null;
                $records = BlindScanning::where('local_pc_path', $folder)
                    ->when($since, fn ($q) => $q->where('created_at', '>=', date('Y-m-d H:i:s', $since - 60)))
                    ->orderBy('id')
                    ->limit(2000)
                    ->get(['id', 'original_filename', 'paper_size', 'document_path', 'status', 'created_at'])
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'original_filename' => $r->original_filename,
                        'paper_size' => $r->paper_size ?? 'A4',
                        'document_path' => $r->document_path,
                        'status' => $r->status,
                        'created_at' => $r->created_at,
                    ]);

                return response()->json(['success' => true, 'records' => $records]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->uploadSummary($mineOnly, (int) $request->get('days', 30), 1000),
                'stats' => $this->dashboardStats($mineOnly),
                'isAdmin' => $isAdmin,
                'mineOnly' => $mineOnly,
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
    /**
     * Save one batch of uploaded files into the file's folder.
     *
     * @return array{saved:array, skipped:array, renamed:array, rejected:array, failed:array}
     *   saved    relative paths written (these get blind_scannings rows)
     *   skipped  already on the server with the same size — a retried batch
     *   renamed  "old => new" when a different file already had the name
     *   rejected not an image/PDF (Thumbs.db, desktop.ini, shortcuts)
     *   failed   could not be written
     */
    private function handleDirectUpload(Request $request, $targetPath): array
    {
        $result = ['saved' => [], 'skipped' => [], 'renamed' => [], 'rejected' => [], 'failed' => []];
        $paths = $request->input('paths', []);
        $files = $request->file('files', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $index => $file) {
            $outcome = $this->processUploadedFile($file, $paths[$index] ?? null, $targetPath);
            $result[$outcome['status']][] = $outcome['status'] === 'renamed'
                ? $outcome['from'] . ' => ' . $outcome['path']
                : $outcome['path'];
            if ($outcome['status'] === 'renamed') {
                $result['saved'][] = $outcome['path'];
            }
        }

        if ($result['failed'] || $result['renamed']) {
            Log::warning('Blind scan upload batch had issues', [
                'target_path' => $targetPath,
                'failed' => $result['failed'],
                'renamed' => $result['renamed'],
            ]);
        }

        return $result;
    }

    /**
     * Save a single uploaded file.
     *
     * Never overwrites: the same name with the same size is a re-sent file and
     * is skipped; the same name with different content is kept beside the
     * original as "name (2).jpg".
     *
     * @return array{status:string, path:string, from?:string}
     */
    private function processUploadedFile($file, $relativePath, $targetPath): array
    {
        $name = $file ? $file->getClientOriginalName() : '';
        $filePath = str_replace('\\', '/', (string) ($relativePath ?: $name));
        // Strip traversal and drive/absolute prefixes.
        $segments = array_values(array_filter(explode('/', $filePath), fn ($s) => $s !== '' && $s !== '.' && $s !== '..'));
        $filePath = implode('/', $segments);

        if (!$file || !$file->isValid() || $filePath === '') {
            return ['status' => 'failed', 'path' => $filePath ?: ($name ?: 'unknown')];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['status' => 'rejected', 'path' => $filePath];
        }

        try {
            $destinationPath = $targetPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
                return ['status' => 'failed', 'path' => $filePath];
            }

            $status = 'saved';
            $from = $filePath;
            if (is_file($destinationPath)) {
                if (filesize($destinationPath) === $file->getSize()) {
                    return ['status' => 'skipped', 'path' => $filePath];
                }
                $base = pathinfo($destinationPath, PATHINFO_FILENAME);
                $n = 2;
                do {
                    $candidate = $destinationDir . DIRECTORY_SEPARATOR . $base . ' (' . $n . ').' . $extension;
                    $n++;
                } while (is_file($candidate));
                $destinationPath = $candidate;
                $filePath = (str_contains($filePath, '/') ? dirname($filePath) . '/' : '') . basename($candidate);
                $status = 'renamed';
            }

            $file->move($destinationDir, basename($destinationPath));

            return $status === 'renamed'
                ? ['status' => 'renamed', 'path' => $filePath, 'from' => $from]
                : ['status' => 'saved', 'path' => $filePath];
        } catch (\Throwable $e) {
            Log::error('Blind scan file could not be saved', ['path' => $filePath, 'error' => $e->getMessage()]);
            return ['status' => 'failed', 'path' => $filePath];
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
