<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use App\Models\FileTracking;
use App\Models\FileIndexing;
use App\Models\FileTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileTrackerController extends Controller
{
    /**
     * Display the file tracker dashboard
     */
    public function index(Request $request)
    {
        $PageTitle = 'File Tracker';
        $PageDescription = 'Track and manage files within the system using RFID & Normal Modes';

        try {
            // Get summary statistics for the dashboard
            $stats = [
                'total_tracked_files' => FileTracking::count(),
                'active_files' => FileTracking::where('status', 'active')->count(),
                'overdue_files' => FileTracking::overdue()->count(),
                'checked_out_files' => FileTracking::where('status', 'checked_out')->count(),
                'recent_activities' => FileTracking::with('fileIndexing')
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            // Get file trackings for the table with pagination
            $fileTrackings = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Add computed attributes
            $fileTrackings->getCollection()->transform(function ($tracking) {
                $tracking->is_overdue = $tracking->is_overdue;
                $tracking->days_until_due = $tracking->days_until_due;
                return $tracking;
            });

            // Get selected file for details sidebar
            $selectedFile = null;
            if ($request->has('selected')) {
                $selectedFile = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                    ->find($request->get('selected'));
                if ($selectedFile) {
                    $selectedFile->is_overdue = $selectedFile->is_overdue;
                    $selectedFile->days_until_due = $selectedFile->days_until_due;
                }
            }

            // If no specific file selected, get the first file (if any)
            if (!$selectedFile) {
                $selectedFile = $fileTrackings->first();
            }

            Log::info('File Tracker dashboard accessed', [
                'user_id' => auth()->id(),
                'stats' => $stats,
                'total_files' => $fileTrackings->total(),
                'selected_file_id' => $selectedFile ? $selectedFile->id : null
            ]);

            return view('filetracker.index', compact('PageTitle', 'PageDescription', 'stats', 'fileTrackings', 'selectedFile'));

        } catch (\Exception $e) {
            Log::error('Error loading File Tracker dashboard', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            // Fallback without stats
            $stats = [
                'total_tracked_files' => 0,
                'active_files' => 0,
                'overdue_files' => 0,
                'checked_out_files' => 0,
                'recent_activities' => collect()
            ];

            $fileTrackings = collect()->paginate(15);
            $selectedFile = null;

            return view('filetracker.index', compact('PageTitle', 'PageDescription', 'stats', 'fileTrackings', 'selectedFile'));
        }
    }

    /**
     * Display the print view for file tracker
     */
    public function print(Request $request)
    {
        $PageTitle = 'File Tracker - Print View';
        $PageDescription = 'Print view for file tracking reports';

        try {
            $tracking = null;
            $mlsNumber = 'N/A';
            $kangisNumber = 'N/A';
            $newKangisNumber = 'N/A';

            // Get specific tracking record if ID provided
            $trackingId = $request->get('id');
            if ($trackingId) {
                $tracking = FileTracking::with(['fileIndexing'])
                    ->find($trackingId);

                if ($tracking && $tracking->fileIndexing) {
                    $fileNumber = $tracking->fileIndexing->file_number;

                    // Step 1: Try to get file numbers from fileNumber table
                    try {
                        $fileNumberRecord = DB::connection('sqlsrv')->table('fileNumber')
                            ->where('fileNumber', $fileNumber)
                            ->first();

                        if ($fileNumberRecord) {
                            $mlsNumber = $fileNumberRecord->mlsfNo ?? 'N/A';
                            $kangisNumber = $fileNumberRecord->kangisFileNo ?? 'N/A';
                            $newKangisNumber = $fileNumberRecord->NewKANGISFileNo ?? 'N/A';
                        } else {
                            // Step 2: Use pattern recognition fallback
                            $fileClassification = $this->classifyFileNumber($fileNumber);
                            $mlsNumber = $fileClassification['mls'];
                            $kangisNumber = $fileClassification['kangis'];
                            $newKangisNumber = $fileClassification['newKangis'];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error accessing fileNumber table, using pattern recognition', [
                            'file_number' => $fileNumber,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback to pattern recognition
                        $fileClassification = $this->classifyFileNumber($fileNumber);
                        $mlsNumber = $fileClassification['mls'];
                        $kangisNumber = $fileClassification['kangis'];
                        $newKangisNumber = $fileClassification['newKangis'];
                    }
                }
            }

            Log::info('File Tracker print view accessed', [
                'user_id' => auth()->id(),
                'tracking_id' => $trackingId,
                'file_classification' => [
                    'mls' => $mlsNumber,
                    'kangis' => $kangisNumber,
                    'newKangis' => $newKangisNumber
                ]
            ]);

            return view('filetracker.print', compact(
                'PageTitle',
                'PageDescription',
                'tracking',
                'mlsNumber',
                'kangisNumber',
                'newKangisNumber'
            ));

        } catch (\Exception $e) {
            Log::error('Error loading File Tracker print view', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tracking_id' => $request->get('id')
            ]);

            $tracking = null;
            $mlsNumber = 'N/A';
            $kangisNumber = 'N/A';
            $newKangisNumber = 'N/A';

            return view('filetracker.print', compact(
                'PageTitle',
                'PageDescription',
                'tracking',
                'mlsNumber',
                'kangisNumber',
                'newKangisNumber'
            ));
        }
    }

    /**
     * Classify file number using pattern recognition
     */
    private function classifyFileNumber($fileNumber)
    {
        $classification = [
            'mls' => 'N/A',
            'kangis' => 'N/A',
            'newKangis' => 'N/A'
        ];

        if (!$fileNumber) {
            return $classification;
        }

        // Clean the file number
        $cleanFileNumber = trim($fileNumber);

        // Classification order (to avoid false hits)

        // 1. New KANGIS patterns first (no space)
        if (preg_match('/^KN\d{4}$/', $cleanFileNumber)) {
            $classification['newKangis'] = $cleanFileNumber;
            return $classification;
        }

        // 2. KANGIS patterns (with space)
        if (
            preg_match('/^KNML \d{5}$/', $cleanFileNumber) ||
            preg_match('/^MLKN \d{5,6}$/', $cleanFileNumber)
        ) {
            $classification['kangis'] = $cleanFileNumber;
            return $classification;
        }

        // 3. MLS patterns (comprehensive list based on your patterns)
        $mlsPatterns = [
            // AG patterns
            '/^AG-(19|20)\d{2}-\d{1,3}$/',           // AG-2021-001, AG-2023-02
            '/^AG-RC-(19|20)\d{2}-\d{2,3}$/',        // AG-RC-2014-001, AG-RC-2014-02
            '/^AG-RC-\d{2}-\d{1,3}$/',               // AG-RC-81-30, AG-RC-83-7 (legacy)

            // COM patterns (including CON-COM)
            '/^COM-(19|20)\d{2}-\d{1,3}$/',          // COM-2000-176
            '/^CON-COM-(19|20)\d{2}-\d{1,3}$/',      // CON-COM-2024-980
        ];

        foreach ($mlsPatterns as $pattern) {
            if (preg_match($pattern, $cleanFileNumber)) {
                $classification['mls'] = $cleanFileNumber;
                return $classification;
            }
        }

        return $classification;
    }

    /**
     * Display RFID scanner interface
     */
    public function rfidScanner()
    {
        $PageTitle = 'RFID Scanner';
        $PageDescription = 'Scan RFID tags to track file movements';

        Log::info('RFID Scanner accessed', ['user_id' => auth()->id()]);

        return view('filetracker.rfid-scanner', compact('PageTitle', 'PageDescription'));
    }

    /**
     * Display file tracking form
     */
    public function trackingForm($fileIndexingId = null)
    {
        $PageTitle = 'File Tracking Form';
        $PageDescription = 'Register or update file tracking information';

        $fileIndexing = null;
        $existingTracking = null;

        if ($fileIndexingId) {
            try {
                $fileIndexing = FileIndexing::find($fileIndexingId);
                if ($fileIndexing) {
                    $existingTracking = DB::connection('sqlsrv')->table('file_trackings')
                        ->where('file_indexing_id', $fileIndexingId)
                        ->first();
                }
            } catch (\Exception $e) {
                Log::error('Error loading file for tracking form', [
                    'file_indexing_id' => $fileIndexingId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('File Tracking Form accessed', [
            'user_id' => auth()->id(),
            'file_indexing_id' => $fileIndexingId
        ]);

        return view('filetracker.tracking-form', compact(
            'PageTitle',
            'PageDescription',
            'fileIndexing',
            'existingTracking'
        ));
    }

    /**
     * Display reports interface
     */
    public function reports()
    {
        $PageTitle = 'File Tracking Reports';
        $PageDescription = 'Generate and view file tracking reports';

        Log::info('File Tracking Reports accessed', ['user_id' => auth()->id()]);

        return view('filetracker.reports', compact('PageTitle', 'PageDescription'));
    }

    /**
     * Display overdue files
     */
    public function overdueFiles()
    {
        $PageTitle = 'Overdue Files';
        $PageDescription = 'Manage overdue file trackings';

        try {
            $overdueFiles = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                ->overdue()
                ->orderBy('due_date', 'asc')
                ->get();

            Log::info('Overdue Files view accessed', [
                'user_id' => auth()->id(),
                'overdue_count' => $overdueFiles->count()
            ]);

            return view('filetracker.overdue', compact('PageTitle', 'PageDescription', 'overdueFiles'));

        } catch (\Exception $e) {
            Log::error('Error loading overdue files', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            $overdueFiles = collect();
            return view('filetracker.overdue', compact('PageTitle', 'PageDescription', 'overdueFiles'));
        }
    }

    /**
     * Show the form for creating a new file tracking entry
     */
    public function create(Request $request)
    {
        $PageTitle = 'Track New File';
        $PageDescription = 'Register a new file for tracking';

        // Check if this is an update request
        $updateTrackingId = $request->get('update');
        $existingTracking = null;
        $fileIndexing = null;

        if ($updateTrackingId) {
            // Load existing tracking for update
            try {
                $existingTracking = FileTracking::with('fileIndexing')->find($updateTrackingId);
                if ($existingTracking) {
                    $fileIndexing = $existingTracking->fileIndexing;
                    $PageTitle = 'Update File Tracking';
                    $PageDescription = 'Update file tracking information';
                }
            } catch (\Exception $e) {
                Log::error('Error loading tracking for update', [
                    'tracking_id' => $updateTrackingId,
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id()
                ]);

                return redirect()->route('filetracker.index')
                    ->withErrors(['error' => 'File tracking not found']);
            }
        }

        Log::info('File Tracker create/update form accessed', [
            'user_id' => auth()->id(),
            'is_update' => !is_null($updateTrackingId),
            'tracking_id' => $updateTrackingId
        ]);

        return view('filetracker.create', compact(
            'PageTitle',
            'PageDescription',
            'existingTracking',
            'fileIndexing'
        ));
    }

    /**
     * Update an existing file tracking entry
     */
    public function update(Request $request, $id)
    {
        try {
            // Find the existing tracking
            $tracking = FileTracking::find($id);
            if (!$tracking) {
                return redirect()->back()
                    ->withErrors(['error' => 'File tracking not found'])
                    ->withInput();
            }

            // Validate the request
            $validatedData = $request->validate([
                'rfid_tag' => 'nullable|string|max:100',
                'qr_code' => 'nullable|string|max:100',
                'current_location' => 'required|string|max:255',
                'current_holder' => 'nullable|string|max:255',
                'current_handler' => 'required|string|max:255',
                'date_received' => 'required|date',
                'due_date' => 'nullable|date|after:date_received',
                'status' => 'required|string|in:in_process,pending,on_hold,completed',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Check for duplicate RFID tag (excluding current record)
            if (!empty($validatedData['rfid_tag'])) {
                $existingRfid = DB::connection('sqlsrv')->table('file_trackings')
                    ->where('rfid_tag', $validatedData['rfid_tag'])
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingRfid) {
                    return redirect()->back()
                        ->withErrors(['rfid_tag' => 'This RFID tag is already in use by another file.'])
                        ->withInput();
                }
            }

            // Check for duplicate QR code (excluding current record)
            if (!empty($validatedData['qr_code'])) {
                $existingQr = DB::connection('sqlsrv')->table('file_trackings')
                    ->where('qr_code', $validatedData['qr_code'])
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingQr) {
                    return redirect()->back()
                        ->withErrors(['qr_code' => 'This QR code is already in use by another file.'])
                        ->withInput();
                }
            }

            // Track changes for movement history
            $changes = [];
            $oldData = [
                'location' => $tracking->current_location,
                'holder' => $tracking->current_holder,
                'handler' => $tracking->current_handler,
                'status' => $tracking->status,
                'rfid_tag' => $tracking->rfid_tag,
                'qr_code' => $tracking->qr_code,
                'due_date' => $tracking->due_date
            ];

            // Update the tracking record
            DB::connection('sqlsrv')->table('file_trackings')
                ->where('id', $id)
                ->update([
                    'rfid_tag' => $validatedData['rfid_tag'],
                    'qr_code' => $validatedData['qr_code'],
                    'current_location' => $validatedData['current_location'],
                    'current_holder' => $validatedData['current_holder'],
                    'current_handler' => $validatedData['current_handler'],
                    'date_received' => $validatedData['date_received'],
                    'due_date' => $validatedData['due_date'],
                    'status' => $validatedData['status'],
                    'updated_at' => now()
                ]);

            // Reload the tracking to get updated data
            $tracking = $tracking->fresh();

            // Build change summary for movement history
            if ($oldData['location'] !== $validatedData['current_location']) {
                $changes[] = "Location: {$oldData['location']} → {$validatedData['current_location']}";
            }
            if ($oldData['handler'] !== $validatedData['current_handler']) {
                $changes[] = "Handler: {$oldData['handler']} → {$validatedData['current_handler']}";
            }
            if ($oldData['status'] !== $validatedData['status']) {
                $changes[] = "Status: {$oldData['status']} → {$validatedData['status']}";
            }

            // Add movement entry for the update
            $movementData = [
                'action' => 'updated',
                'changes' => implode(', ', $changes),
                'from_location' => $oldData['location'],
                'to_location' => $validatedData['current_location'],
                'from_handler' => $oldData['handler'],
                'to_handler' => $validatedData['current_handler'],
                'from_status' => $oldData['status'],
                'to_status' => $validatedData['status'],
                'reason' => 'File tracking updated via form'
            ];

            if (!empty($validatedData['notes'])) {
                $movementData['notes'] = $validatedData['notes'];
            }

            // Reload the tracking to get updated data before adding movement entry
            $tracking = $tracking->fresh();
            $tracking->addMovementEntry($movementData);

            Log::info('File tracking updated successfully', [
                'tracking_id' => $tracking->id,
                'file_indexing_id' => $tracking->file_indexing_id,
                'changes' => $changes,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('filetracker.index', ['selected' => $tracking->id])
                ->with('success', 'File tracking updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating file tracking', [
                'tracking_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while updating the file tracking. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Store a newly created file tracking entry
     */
    public function store(Request $request)
    {
        try {
            // Basic validation without unique constraints
            $validatedData = $request->validate([
                'file_indexing_id' => 'required|integer',
                'rfid_tag' => 'nullable|string|max:100',
                'qr_code' => 'nullable|string|max:100',
                'current_location' => 'required|string|max:255',
                'current_holder' => 'nullable|string|max:255',
                'current_handler' => 'required|string|max:255',
                'date_received' => 'required|date',
                'due_date' => 'nullable|date|after:date_received',
                'status' => 'required|string|in:in_process,pending,on_hold,completed',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Check if file_indexing_id exists in SQL Server
            $fileIndexingExists = DB::connection('sqlsrv')->table('file_indexings')
                ->where('id', $validatedData['file_indexing_id'])
                ->exists();

            if (!$fileIndexingExists) {
                return redirect()->back()
                    ->withErrors(['file_indexing_id' => 'The selected file does not exist.'])
                    ->withInput();
            }

            // Check if file is already being tracked using SQL Server connection
            $existingTracking = DB::connection('sqlsrv')->table('file_trackings')
                ->where('file_indexing_id', $validatedData['file_indexing_id'])
                ->first();
            if ($existingTracking) {
                return redirect()->back()
                    ->withErrors(['file_indexing_id' => 'This file is already being tracked.'])
                    ->withInput();
            }

            // Check for duplicate RFID tag using SQL Server connection
            if (!empty($validatedData['rfid_tag'] ?? null)) {
                $existingRfid = DB::connection('sqlsrv')->table('file_trackings')
                    ->where('rfid_tag', $validatedData['rfid_tag'])
                    ->first();
                if ($existingRfid) {
                    return redirect()->back()
                        ->withErrors(['rfid_tag' => 'This RFID tag is already in use.'])
                        ->withInput();
                }
            }

            // Check for duplicate QR code using SQL Server connection
            if (!empty($validatedData['qr_code'] ?? null)) {
                $existingQr = DB::connection('sqlsrv')->table('file_trackings')
                    ->where('qr_code', $validatedData['qr_code'])
                    ->first();
                if ($existingQr) {
                    return redirect()->back()
                        ->withErrors(['qr_code' => 'This QR code is already in use.'])
                        ->withInput();
                }
            }

            // Get file details for QR code generation
            $fileDetails = DB::connection('sqlsrv')->table('file_indexings')
                ->where('id', $validatedData['file_indexing_id'])
                ->first();

            // Generate QR code using file number and title if not provided
            if (empty($validatedData['qr_code']) && $fileDetails) {
                $titlePart = $fileDetails->file_title ? substr(str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $fileDetails->file_title), 0, 20) : 'UNTITLED';
                $validatedData['qr_code'] = $fileDetails->file_number . '-' . $titlePart;
            }

            // Create the tracking entry using raw SQL (insert selected status as-is)
            $insertData = [
                'file_indexing_id' => $validatedData['file_indexing_id'],
                'rfid_tag' => $validatedData['rfid_tag'] ?? null,
                'qr_code' => $validatedData['qr_code'] ?? null,
                'current_location' => $validatedData['current_location'],
                'current_holder' => $validatedData['current_holder'],
                'current_handler' => $validatedData['current_handler'],
                'date_received' => $validatedData['date_received'],
                'due_date' => $validatedData['due_date'],
                'status' => $validatedData['status'],
                'created_at' => now(),
                'updated_at' => now(),
                'movement_history' => json_encode([
                    [
                        'action' => 'created',
                        'timestamp' => now()->toISOString(),
                        'user_id' => auth()->id(),
                        'user_name' => auth()->user()->name ?? 'System',
                        'initial_location' => $validatedData['current_location'],
                        'initial_handler' => $validatedData['current_handler'],
                        'initial_status' => $validatedData['status']
                    ]
                ])
            ];
            $trackingId = DB::connection('sqlsrv')->table('file_trackings')->insertGetId($insertData);

            // Get the created tracking record
            $tracking = FileTracking::find($trackingId);

            // Add initial notes to movement history if provided
            if (!empty($validatedData['notes'])) {
                $tracking->addMovementEntry([
                    'action' => 'initial_notes',
                    'notes' => $validatedData['notes'],
                    'reason' => 'Initial tracking setup'
                ]);
            }

            Log::info('File tracking created successfully', [
                'tracking_id' => $tracking->id,
                'file_indexing_id' => $tracking->file_indexing_id,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('filetracker.index', ['selected' => $tracking->id])
                ->with('success', 'File tracking created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating file tracking', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while creating the file tracking. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Get indexed files that are not yet being tracked (AJAX endpoint)
     */
    public function getIndexedFiles(Request $request)
    {
        try {
            // Get files from file_indexings that are not yet being tracked
            $indexedFiles = FileIndexing::select(
                'id',
                'file_number',
                'file_title',
                'land_use_type',
                'district',
                'created_at'
            )
                // Show all indexed files regardless of tracking status
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            Log::info('Indexed files retrieved for tracking', [
                'count' => $indexedFiles->count(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $indexedFiles
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving indexed files', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving indexed files'
            ], 500);
        }
    }

    /**
     * Store multiple file tracking entries (batch processing)
     */
    public function storeBatch(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'files' => 'required|array|min:1',
                'files.*.file_indexing_id' => 'required|integer',
                'files.*.rfid_tag' => 'nullable|string|max:100',
                'files.*.qr_code' => 'nullable|string|max:100',
                'files.*.current_location' => 'required|string|max:255',
                'files.*.current_holder' => 'nullable|string|max:255',
                'files.*.current_handler' => 'required|string|max:255',
                'files.*.date_received' => 'required|date',
                'files.*.due_date' => 'nullable|date|after:files.*.date_received',
                'files.*.status' => 'required|string|in:in_process,pending,on_hold,completed',
                'files.*.notes' => 'nullable|string|max:1000'
            ]);

            $createdTrackings = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($validatedData['files'] as $index => $fileData) {
                try {
                    // Check if file_indexing_id exists in SQL Server
                    $fileIndexingExists = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $fileData['file_indexing_id'])
                        ->exists();

                    if (!$fileIndexingExists) {
                        $errors[] = "File {$fileData['file_indexing_id']} does not exist";
                        continue;
                    }

                    // Check if file is already being tracked using SQL Server connection
                    $existingTracking = DB::connection('sqlsrv')->table('file_trackings')
                        ->where('file_indexing_id', $fileData['file_indexing_id'])
                        ->first();
                    if ($existingTracking) {
                        $errors[] = "File {$fileData['file_indexing_id']} is already being tracked";
                        continue;
                    }

                    // Check for duplicate RFID tags using SQL Server connection
                    if (!empty($fileData['rfid_tag'] ?? null)) {
                        $existingRfid = DB::connection('sqlsrv')->table('file_trackings')
                            ->where('rfid_tag', $fileData['rfid_tag'])
                            ->first();
                        if ($existingRfid) {
                            $errors[] = "RFID tag {$fileData['rfid_tag']} is already in use";
                            continue;
                        }
                    }

                    // Check for duplicate QR codes using SQL Server connection
                    if (!empty($fileData['qr_code'] ?? null)) {
                        $existingQr = DB::connection('sqlsrv')->table('file_trackings')
                            ->where('qr_code', $fileData['qr_code'])
                            ->first();
                        if ($existingQr) {
                            $errors[] = "QR code {$fileData['qr_code']} is already in use";
                            continue;
                        }
                    }

                    // Get file details for QR code generation
                    $fileDetails = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $fileData['file_indexing_id'])
                        ->first();

                    // Generate QR code using file number and title if not provided
                    if (empty($fileData['qr_code']) && $fileDetails) {
                        $titlePart = $fileDetails->file_title ? substr(str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $fileDetails->file_title), 0, 20) : 'UNTITLED';
                        $fileData['qr_code'] = $fileDetails->file_number . '-' . $titlePart;
                    }

                    // Create the tracking entry using raw SQL (insert selected status as-is)
                    $insertData = [
                        'file_indexing_id' => $fileData['file_indexing_id'],
                        'rfid_tag' => $fileData['rfid_tag'] ?? null,
                        'qr_code' => $fileData['qr_code'] ?? null,
                        'current_location' => $fileData['current_location'],
                        'current_holder' => $fileData['current_holder'],
                        'current_handler' => $fileData['current_handler'],
                        'date_received' => $fileData['date_received'],
                        'due_date' => $fileData['due_date'],
                        'status' => $fileData['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'movement_history' => json_encode([
                            [
                                'action' => 'created',
                                'timestamp' => now()->toISOString(),
                                'user_id' => auth()->id(),
                                'user_name' => auth()->user()->name ?? 'System',
                                'initial_location' => $fileData['current_location'],
                                'initial_handler' => $fileData['current_handler'],
                                'initial_status' => $fileData['status']
                            ]
                        ])
                    ];
                    $trackingId = DB::connection('sqlsrv')->table('file_trackings')->insertGetId($insertData);

                    // Get the created tracking record
                    $tracking = FileTracking::find($trackingId);

                    // Add initial notes to movement history if provided
                    if (!empty($fileData['notes'])) {
                        $tracking->addMovementEntry([
                            'action' => 'initial_notes',
                            'notes' => $fileData['notes'],
                            'reason' => 'Initial batch tracking setup'
                        ]);
                    }

                    $createdTrackings[] = $tracking;

                } catch (\Exception $e) {
                    $errors[] = "Error creating tracking for file {$fileData['file_indexing_id']}: " . $e->getMessage();
                }
            }

            if (empty($createdTrackings)) {
                DB::rollBack();
                return redirect()->back()
                    ->withErrors(['batch' => 'No files were successfully tracked. ' . implode(', ', $errors)])
                    ->withInput();
            }

            DB::commit();

            Log::info('Batch file tracking created successfully', [
                'created_count' => count($createdTrackings),
                'errors_count' => count($errors),
                'user_id' => auth()->id()
            ]);

            $message = count($createdTrackings) . ' files tracked successfully';
            if (!empty($errors)) {
                $message .= '. Some files had errors: ' . implode(', ', $errors);
            }

            return redirect()->route('filetracker.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating batch file tracking', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while creating the batch file tracking. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Search for files to track (AJAX endpoint)
     */
    public function searchFiles(Request $request)
    {
        try {
            $query = $request->get('query', '');

            if (strlen($query) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Query must be at least 2 characters long'
                ]);
            }

            // Search in file_indexings table for files not already being tracked
            $files = FileIndexing::select('id', 'file_number', 'file_title', 'old_file_number', 'survey_plan_number')
                ->where(function ($q) use ($query) {
                    $q->where('file_number', 'LIKE', "%{$query}%")
                        ->orWhere('file_title', 'LIKE', "%{$query}%")
                        ->orWhere('old_file_number', 'LIKE', "%{$query}%")
                        ->orWhere('survey_plan_number', 'LIKE', "%{$query}%");
                })
                ->whereNotIn('id', function ($subQuery) {
                    $subQuery->select('file_indexing_id')
                        ->from('file_trackings')
                        ->whereNotNull('file_indexing_id');
                })
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_number' => $file->file_number,
                        'file_title' => $file->file_title,
                        'old_file_number' => $file->old_file_number,
                        'survey_plan_number' => $file->survey_plan_number,
                        'display_text' => $file->file_number . ' - ' . ($file->file_title ?? 'No Title')
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching files for tracking', [
                'error' => $e->getMessage(),
                'query' => $request->get('query'),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching files'
            ], 500);
        }
    }

    /**
     * Update file tracker status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            // Validate the request
            $validatedData = $request->validate([
                'status' => 'required|string|in:Log-in,Cancelled,Completed',
                'registry_office_code' => 'required|string|max:50',
                'registry_office_name' => 'required|string|max:255',
                'notes' => 'nullable|string|max:500',
                'num_pages' => 'nullable|integer|min:1|max:99999', // Pages returned to registry
                'delay_reason' => 'nullable|string|max:500', // Required client-side when Amber/Red at return time
                'table' => 'nullable|string|in:file_tracker,file_trackings,diit', // Added to identify source table
                'file_number' => 'nullable|string|max:255' // Addresses a DIIT file, which has no id yet
            ]);

            $table = $request->input('table', 'file_tracker'); // Default to file_tracker

            // Handle file_tracker table (the main system)
            if ($table === 'file_tracker' || $table === 'diit') {
                $fileTracker = null;

                // A DIIT file is commissioned but has never been logged out, so it
                // has no row to update — open real tracking for it first. That is
                // exactly what the log-back records: the file arriving at its
                // registry from the File Commissioning Office. materialize() hands
                // back an existing tracker if the file was logged out in the
                // meantime, so the two paths cannot double up.
                if ($table === 'diit') {
                    $commissioning = app(\App\Services\FileCommissioningTrackingService::class);
                    $identifier = trim((string) ($validatedData['file_number'] ?? '')) ?: $id;
                    $info = $commissioning->resolveIdentifier($identifier);

                    if (!$info) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No commissioned file found with identifier: ' . $identifier
                        ], 404);
                    }

                    $fileTracker = $commissioning->materialize($info);
                }

                if (!$fileTracker) {
                    $fileTracker = FileTracker::find($id);
                }

                if (!$fileTracker) {
                    // Try by tracking_id
                    $fileTracker = FileTracker::where('tracking_id', $id)
                        ->orWhereRaw('LOWER(tracking_id) = ?', [Str::lower($id)])
                        ->first();
                }

                if (!$fileTracker) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File tracker record not found with identifier: ' . $id
                    ], 404);
                }

                $oldStatus = $fileTracker->status;
                $now = now();
                $movementStatus = $validatedData['status'] === 'Log-in' ? 'active' : 'logged_out';
                $logId = sprintf('LOG-%s-%s', $now->format('YmdHis'), Str::upper(Str::random(4)));

                // ── Page-count reconciliation ──────────────────────────────────
                // Compare the pages being returned against the original count that was
                // recorded when the file was logged out. A difference is recorded for
                // auditing but never blocks the log-back.
                $originalNumPages = $fileTracker->num_pages;
                if ($originalNumPages === null) {
                    $existingLog = $fileTracker->movement_log;
                    if (!is_array($existingLog)) {
                        $existingLog = json_decode((string) $existingLog, true) ?: [];
                    }
                    foreach ($existingLog as $entry) {
                        if (is_array($entry) && isset($entry['num_pages']) && $entry['num_pages'] !== '' && $entry['num_pages'] !== null) {
                            $originalNumPages = (int) $entry['num_pages'];
                            break;
                        }
                    }
                }
                $originalNumPages = $originalNumPages !== null ? (int) $originalNumPages : null;
                $returnedNumPages = isset($validatedData['num_pages']) ? (int) $validatedData['num_pages'] : null;

                $pageDiscrepancy = null;     // signed: returned - original
                $pageDiscrepancyNote = null;
                if ($originalNumPages !== null && $returnedNumPages !== null) {
                    $pageDiscrepancy = $returnedNumPages - $originalNumPages;
                    if ($pageDiscrepancy < 0) {
                        $pageDiscrepancyNote = sprintf(
                            'Page discrepancy: %d page(s) MISSING (logged out with %d, returned %d).',
                            abs($pageDiscrepancy), $originalNumPages, $returnedNumPages
                        );
                    } elseif ($pageDiscrepancy > 0) {
                        $pageDiscrepancyNote = sprintf(
                            'Page discrepancy: %d page(s) ADDED (logged out with %d, returned %d).',
                            $pageDiscrepancy, $originalNumPages, $returnedNumPages
                        );
                    }
                }

                // Update the status and office information. num_pages is left as the
                // original log-out count; the returned count is stored separately.
                $fileTracker->status = $validatedData['status'];
                $fileTracker->current_office_code = $validatedData['registry_office_code'];
                $fileTracker->current_office_name = $validatedData['registry_office_name'];
                if ($returnedNumPages !== null) {
                    $fileTracker->returned_num_pages = $returnedNumPages;
                }
                $fileTracker->save();

                // Add to movement log (mirrors UI expectations)
                $delayReason = trim((string) ($validatedData['delay_reason'] ?? ''));
                $movementLogEntry = [
                    'log_id' => $logId,
                    'action' => 'status_updated',
                    'office_code' => $validatedData['registry_office_code'],
                    'office_name' => $validatedData['registry_office_name'],
                    'log_in_time' => $now->format('H:i'),
                    'log_in_date' => $now->format('Y-m-d'),
                    'log_out_time' => null,
                    'log_out_date' => null,
                    'notes' => trim(($validatedData['notes'] ?? 'File logged back to Registry (Origin)')
                        . ($pageDiscrepancyNote ? ' | ' . $pageDiscrepancyNote : '')
                        . ($delayReason !== '' ? ' | Delay reason: ' . $delayReason : '')),
                    'delay_reason' => $delayReason !== '' ? $delayReason : null,
                    'status' => $movementStatus,
                    'old_status' => $oldStatus,
                    'new_status' => $validatedData['status'],
                    'pages_original' => $originalNumPages,
                    'pages_returned' => $returnedNumPages,
                    'page_discrepancy' => $pageDiscrepancy,
                    'has_page_discrepancy' => $pageDiscrepancy !== null && $pageDiscrepancy !== 0,
                    'timestamp' => $now->toISOString(),
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name ?? 'System',
                    'destination' => $validatedData['registry_office_name'],
                    'receiving_office_code' => $validatedData['registry_office_code'],
                    'receiving_office_name' => $validatedData['registry_office_name'],
                    'receiving_officer_id' => auth()->id(),
                    'receiving_officer_name' => auth()->user()->name ?? 'System',
                    'origin_office_code' => $fileTracker->origin_office_code,
                    'origin_office_name' => $fileTracker->origin_office_name,
                    'origin_office_department' => $fileTracker->origin_office_department,
                    'manual_update' => true,
                    'manual_update_by' => auth()->id(),
                    'manual_update_at' => $now->toISOString(),
                ];

                $movementLog = $fileTracker->movement_log ?? [];
                if (!is_array($movementLog)) {
                    $movementLog = json_decode($movementLog, true) ?? [];
                }
                array_unshift($movementLog, $movementLogEntry);
                $fileTracker->movement_log = $movementLog;
                $fileTracker->save();

                Log::info('File tracker status updated', [
                    'tracking_identifier' => $id,
                    'database_id' => $fileTracker->id,
                    'old_status' => $oldStatus,
                    'new_status' => $validatedData['status'],
                    'updated_by' => auth()->id(),
                    'destination' => 'Registry (Origin)',
                    'pages_original' => $originalNumPages,
                    'pages_returned' => $returnedNumPages,
                    'page_discrepancy' => $pageDiscrepancy,
                    'table' => 'file_tracker'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'File status updated successfully - logged back to Registry (Origin)',
                    'data' => [
                        'old_status' => $oldStatus,
                        'new_status' => $validatedData['status'],
                        'updated_at' => $fileTracker->updated_at->format('Y-m-d H:i:s'),
                        'pages_original' => $originalNumPages,
                        'pages_returned' => $returnedNumPages,
                        'returned_num_pages' => $returnedNumPages,
                        'page_discrepancy' => $pageDiscrepancy,
                        'page_discrepancy_note' => $pageDiscrepancyNote,
                        'file_info' => [
                            'tracking_id' => $fileTracker->tracking_id,
                            'file_name' => $fileTracker->file_title,
                            'current_location' => $fileTracker->current_office_name,
                            'current_handler' => $fileTracker->receiving_officer_name
                        ]
                    ]
                ]);
            }

            // Handle file_trackings table (legacy/alternative system)
            $tracking = $this->findTrackingByIdentifier($id);
            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking record not found with identifier: ' . $id
                ], 404);
            }

            $oldStatus = $tracking->status;

            // Update the status with Registry office as destination
            DB::connection('sqlsrv')->table('file_trackings')
                ->where('id', $tracking->id)
                ->update([
                    'status' => $validatedData['status'],
                    'current_location' => $validatedData['registry_office_name'],
                    'updated_at' => now()
                ]);

            // Add movement entry for the status update
            $movementData = [
                'action' => 'status_updated',
                'changes' => "Status: {$oldStatus} → {$validatedData['status']}",
                'from_status' => $oldStatus,
                'to_status' => $validatedData['status'],
                'registry_office' => $validatedData['registry_office_name'] . ' (' . $validatedData['registry_office_code'] . ')',
                'reason' => 'File logged back to Registry (Origin)',
                'destination' => $validatedData['registry_office_name']
            ];

            if (!empty($validatedData['notes'])) {
                $movementData['notes'] = $validatedData['notes'];
            }

            // Reload the tracking to get updated data
            $tracking = $tracking->fresh();
            $tracking->addMovementEntry($movementData);

            // Get file information for response
            $fileInfo = [
                'tracking_id' => optional($tracking->fileIndexing)->tracking_id
                    ?: $tracking->rfid_tag
                    ?: $tracking->qr_code
                    ?: 'N/A',
                'file_name' => $tracking->fileIndexing ? $tracking->fileIndexing->file_name : 'N/A',
                'current_location' => $tracking->current_location,
                'current_handler' => $tracking->current_handler
            ];

            Log::info('File tracker status updated', [
                'tracking_identifier' => $id,
                'database_id' => $tracking->id,
                'old_status' => $oldStatus,
                'new_status' => $validatedData['status'],
                'updated_by' => auth()->id(),
                'destination' => 'Registry (Origin)',
                'table' => 'file_trackings'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File status updated successfully - logged back to Registry (Origin)',
                'data' => [
                    'old_status' => $oldStatus,
                    'new_status' => $validatedData['status'],
                    'updated_at' => $tracking->updated_at->format('Y-m-d H:i:s'),
                    'file_info' => $fileInfo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating file tracker status', [
                'tracking_identifier' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating file status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file information by tracking ID for status update preview
     */
    public function getFileInfoByTrackingId(Request $request, $trackingId)
    {
        try {
            $lookupType = Str::lower($request->query('by', 'tracking_id'));
            $normalizedIdentifier = trim($trackingId);

            // Q1 token guard.
            //
            // The QR scanner passes whatever it read straight through as a
            // tracking ID. Once a document prints a KLAES-Q1 token that lookup
            // finds nothing and the "Log to Next Department" workflow stops
            // working at the counter — so decode first and continue with the
            // tracking ID the token resolves to.
            //
            // A token that fails authentication is NOT treated as a miss: it
            // means the paper was altered or forged, and the officer needs to
            // be told that rather than "file not found".
            $tokens = app(\App\Services\DocumentQr\QrTokenService::class);

            if ($tokens->looksLikeQ1($normalizedIdentifier)) {
                try {
                    $claim = $tokens->verify($normalizedIdentifier);
                    $qr    = \App\Models\DocumentQrCode::find($claim['document_qr_id'] ?? 0);

                    if (!$qr) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This QR code is valid but its document record no longer exists.',
                        ], 404);
                    }

                    $resolved = $qr->tracking_id ?: $qr->file_number;

                    if (!$resolved) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This QR code resolves to a document with no tracking ID or file number recorded.',
                        ], 404);
                    }

                    $normalizedIdentifier = trim($resolved);
                    $lookupType = $qr->tracking_id ? 'tracking_id' : 'file_number';
                } catch (\App\Services\DocumentQr\InvalidQrToken $e) {
                    return response()->json([
                        'success' => false,
                        'tampered' => true,
                        'message' => 'This QR code failed authentication and may be forged or altered. '
                            . 'Retain the document and report it to the registry.',
                    ], 422);
                }
            }

            // First, try to find in file_tracker table (the main tracking system)
            $fileTrackerQuery = FileTracker::query();

            if ($lookupType === 'file_number') {
                $fileTrackerQuery->whereRaw('LOWER(file_number) = ?', [Str::lower($normalizedIdentifier)]);
            } else {
                $fileTrackerQuery->where('tracking_id', $normalizedIdentifier)
                    ->orWhereRaw('LOWER(tracking_id) = ?', [Str::lower($normalizedIdentifier)]);
            }

            $fileTracker = $fileTrackerQuery->first();

            // Fallback to opposite identifier if nothing found yet
            if (!$fileTracker && $lookupType !== 'file_number') {
                $fileTracker = FileTracker::whereRaw('LOWER(file_number) = ?', [Str::lower($normalizedIdentifier)])
                    ->first();
            } elseif (!$fileTracker && $lookupType === 'file_number') {
                $fileTracker = FileTracker::where('tracking_id', $normalizedIdentifier)
                    ->orWhereRaw('LOWER(tracking_id) = ?', [Str::lower($normalizedIdentifier)])
                    ->first();
            }

            if ($fileTracker) {
                // Resolve the page count recorded when the file was logged out.
                // Prefer the tracker column; fall back to the latest movement entry
                // that carried a num_pages value.
                $originalNumPages = $fileTracker->num_pages;
                if ($originalNumPages === null) {
                    $movementLog = $fileTracker->movement_log;
                    if (!is_array($movementLog)) {
                        $movementLog = json_decode((string) $movementLog, true) ?: [];
                    }
                    foreach ($movementLog as $entry) {
                        if (is_array($entry) && isset($entry['num_pages']) && $entry['num_pages'] !== '' && $entry['num_pages'] !== null) {
                            $originalNumPages = (int) $entry['num_pages'];
                            break;
                        }
                    }
                }

                $fileInfo = [
                    'id' => $fileTracker->id,
                    'tracking_id' => $fileTracker->tracking_id,
                    'file_name' => $fileTracker->file_title ?: 'N/A',
                    'file_number' => $fileTracker->file_number ?: 'N/A',
                    'current_location' => $fileTracker->current_office_name ?: 'N/A',
                    'current_handler' => $fileTracker->receiving_officer_name ?: 'N/A',
                    'current_status' => $fileTracker->status ?: 'N/A',
                    'date_received' => $fileTracker->date_created ? $fileTracker->date_created->format('Y-m-d H:i:s') : 'N/A',
                    'due_date' => $fileTracker->deadline ? $fileTracker->deadline->format('Y-m-d H:i:s') : 'N/A',
                    // Green/Amber/Red — drives whether the "Log File Back to Registry" modal
                    // requires a Reason for Delay. See FileTracker::getTimelineStatusAttribute().
                    'timeline_status' => $fileTracker->timeline_status,
                    'request_purpose_name' => $fileTracker->request_purpose_name,
                    'origin_registry' => $fileTracker->origin_office_name ?: 'N/A',
                    'origin_office_name' => $fileTracker->origin_office_name ?: 'N/A',
                    'origin_office_code' => $fileTracker->origin_office_code ?: '',
                    'assigned_office' => $fileTracker->current_office_name ?: 'N/A',
                    'num_pages' => $originalNumPages !== null ? (int) $originalNumPages : null,
                    'table' => 'file_tracker' // Add identifier for which table this came from
                ];

                Log::info('File info retrieved from file_tracker', [
                    'lookup_value' => $normalizedIdentifier,
                    'lookup_type' => $lookupType,
                    'database_id' => $fileTracker->id,
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $fileInfo
                ]);
            }

            // Fallback: Resolve from file_trackings table
            $tracking = $this->findTrackingByIdentifier($normalizedIdentifier);

            if (!$tracking) {
                // Fallback: a file still on Default In-process In-transit Tracking.
                //
                // A KLAES-commissioned file has no tracker row until it is first
                // logged out, so neither table above can answer for it — yet it is
                // a real file in transit that does come back to a registry. Answer
                // from the commissioning register instead, and let updateStatus()
                // open the tracker when the officer logs it back.
                $commissioning = app(\App\Services\FileCommissioningTrackingService::class);

                if ($info = $commissioning->resolveIdentifier($normalizedIdentifier)) {
                    /** @var \Carbon\Carbon $commissionedAt */
                    $commissionedAt = $info['commissioned_at'];

                    $fileInfo = [
                        // No database row exists yet: the log-back is addressed by
                        // file number (posted in the body), not by id.
                        'id' => 'diit',
                        'is_diit' => true,
                        'tracking_id' => $info['tracking_id'] ?: ('DIIT-' . $info['file_number']),
                        'file_name' => $info['file_title'] ?: 'N/A',
                        'file_number' => $info['file_number'],
                        'current_location' => \App\Services\FileCommissioningTrackingService::OFFICE_NAME,
                        'current_handler' => $info['commissioned_by'] ?: \App\Services\FileCommissioningTrackingService::OFFICE_NAME,
                        'current_status' => \App\Services\FileCommissioningTrackingService::LABEL,
                        'date_received' => $commissionedAt->format('Y-m-d H:i:s'),
                        'due_date' => 'N/A',
                        // A commissioned file carries no requested deadline, so the
                        // Reason for Delay field stays hidden.
                        'timeline_status' => null,
                        'origin_registry' => \App\Services\FileCommissioningTrackingService::OFFICE_NAME,
                        'origin_office_name' => \App\Services\FileCommissioningTrackingService::OFFICE_NAME,
                        'origin_office_code' => \App\Services\FileCommissioningTrackingService::OFFICE_CODE,
                        'assigned_office' => \App\Services\FileCommissioningTrackingService::OFFICE_NAME,
                        // Nothing was counted at log-out — there was no log-out.
                        'num_pages' => null,
                        'table' => 'diit',
                    ];

                    Log::info('File info retrieved from commissioning register (DIIT)', [
                        'lookup_value' => $normalizedIdentifier,
                        'lookup_type' => $lookupType,
                        'file_number' => $info['file_number'],
                        'user_id' => auth()->id()
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $fileInfo
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'No file found with tracking ID: ' . $trackingId
                ], 404);
            }

            // Prepare file information from file_trackings
            $fileInfo = [
                'id' => $tracking->id,
                'tracking_id' => optional($tracking->fileIndexing)->tracking_id
                    ?: $tracking->rfid_tag
                    ?: $tracking->qr_code
                    ?: $trackingId,
                'file_name' => $tracking->fileIndexing ? $tracking->fileIndexing->file_name : 'N/A',
                'file_number' => $tracking->fileIndexing ? $tracking->fileIndexing->file_number : 'N/A',
                'current_location' => $tracking->current_location,
                'current_handler' => $tracking->current_handler,
                'current_status' => $tracking->status,
                'date_received' => $tracking->date_received ? $tracking->date_received->format('Y-m-d H:i:s') : 'N/A',
                'due_date' => $tracking->due_date ? $tracking->due_date->format('Y-m-d H:i:s') : 'N/A',
                'origin_registry' => $tracking->origin_registry ?: 'N/A',
                'origin_office_name' => $tracking->origin_registry ?: 'N/A',
                'origin_office_code' => $tracking->origin_registry_code ?? $tracking->origin_office_code ?? '',
                'assigned_office' => $tracking->assigned_office,
                'table' => 'file_trackings'
            ];

            Log::info('File info retrieved from file_trackings', [
                'lookup_value' => $normalizedIdentifier,
                'lookup_type' => $lookupType,
                'database_id' => $tracking->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $fileInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting file info by tracking ID', [
                'tracking_identifier' => $normalizedIdentifier,
                'lookup_type' => $lookupType,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file information'
            ], 500);
        }
    }

    /**
     * Resolve a file tracking record from a numeric ID, RFID/QR, or linked file_indexings.tracking_id
     */
    private function findTrackingByIdentifier(string $identifier): ?FileTracking
    {
        $normalized = trim($identifier);

        if ($normalized === '') {
            Log::warning('Empty tracking identifier provided');
            return null;
        }

        Log::info('Searching for tracking record', [
            'identifier' => $identifier,
            'normalized' => $normalized
        ]);

        // Try numeric ID first
        if (is_numeric($normalized)) {
            $result = FileTracking::with('fileIndexing')
                ->where('file_trackings.id', (int) $normalized)
                ->first();

            if ($result) {
                Log::info('Found by numeric ID', ['id' => $result->id]);
                return $result;
            }
        }

        $lower = Str::lower($normalized);

        // Search in file_trackings fields (rfid_tag, qr_code)
        $result = FileTracking::with('fileIndexing')
            ->where(function ($builder) use ($normalized, $lower) {
                $builder
                    ->where('file_trackings.rfid_tag', $normalized)
                    ->orWhere('file_trackings.qr_code', $normalized)
                    ->orWhereRaw('LOWER(file_trackings.rfid_tag) = ?', [$lower])
                    ->orWhereRaw('LOWER(file_trackings.qr_code) = ?', [$lower]);
            })
            ->first();

        if ($result) {
            Log::info('Found by RFID/QR code', ['id' => $result->id]);
            return $result;
        }

        // Search via file_indexings.tracking_id or file_number
        $result = FileTracking::with('fileIndexing')
            ->whereHas('fileIndexing', function ($relation) use ($normalized, $lower) {
                $relation->where(function ($query) use ($normalized, $lower) {
                    $query->where('file_indexings.tracking_id', $normalized)
                        ->orWhere('file_indexings.file_number', $normalized)
                        ->orWhereRaw('LOWER(file_indexings.tracking_id) = ?', [$lower])
                        ->orWhereRaw('LOWER(file_indexings.file_number) = ?', [$lower]);
                });
            })
            ->first();

        if ($result) {
            Log::info('Found via file_indexings', [
                'id' => $result->id,
                'file_indexing_id' => $result->file_indexing_id
            ]);
            return $result;
        }

        // Last resort: try raw SQL query to find it
        try {
            $rawResult = DB::connection('sqlsrv')
                ->table('file_trackings as ft')
                ->join('file_indexings as fi', 'ft.file_indexing_id', '=', 'fi.id')
                ->where(function ($query) use ($normalized, $lower) {
                    $query->where('fi.tracking_id', $normalized)
                        ->orWhere('fi.file_number', $normalized)
                        ->orWhereRaw('LOWER(fi.tracking_id) = ?', [$lower])
                        ->orWhereRaw('LOWER(fi.file_number) = ?', [$lower]);
                })
                ->select('ft.id')
                ->first();

            if ($rawResult) {
                Log::info('Found via raw SQL query', ['id' => $rawResult->id]);
                return FileTracking::with('fileIndexing')->find($rawResult->id);
            }
        } catch (\Exception $e) {
            Log::error('Raw SQL fallback failed', [
                'error' => $e->getMessage(),
                'identifier' => $identifier
            ]);
        }

        // Check if this is a FileTracker (different table) tracking_id
        try {
            $fileTracker = FileTracker::where('tracking_id', $normalized)
                ->orWhereRaw('LOWER(tracking_id) = ?', [$lower])
                ->first();

            if ($fileTracker) {
                Log::info('Found in file_tracker table', [
                    'tracker_id' => $fileTracker->id,
                    'tracking_id' => $fileTracker->tracking_id
                ]);

                // Try to find corresponding file_trackings record by file_number
                if ($fileTracker->file_number) {
                    $tracking = FileTracking::with('fileIndexing')
                        ->whereHas('fileIndexing', function ($query) use ($fileTracker) {
                            $query->where('file_indexings.file_number', $fileTracker->file_number);
                        })
                        ->first();

                    if ($tracking) {
                        Log::info('Found file_trackings via file_number match', [
                            'tracking_id' => $tracking->id
                        ]);
                        return $tracking;
                    }
                }

                // If no file_trackings found, we'll need to handle this differently
                // For now, log and return null
                Log::warning('FileTracker found but no corresponding file_trackings record', [
                    'file_tracker_id' => $fileTracker->id,
                    'file_number' => $fileTracker->file_number
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FileTracker lookup failed', [
                'error' => $e->getMessage(),
                'identifier' => $identifier
            ]);
        }

        Log::warning('No tracking record found', [
            'identifier' => $identifier,
            'searched_as' => $lower
        ]);

        return null;
    }

    /**
     * Get all active offices for Registry dropdown
     */
    public function getOffices()
    {
        try {
            $offices = DB::connection('sqlsrv')
                ->table('offices')
                ->where('is_active', 1)
                ->select('office_code', 'office_name', 'office_abbreviation', 'department')
                ->orderBy('office_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $offices
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching offices', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching offices: ' . $e->getMessage()
            ], 500);
        }
    }
}