<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use App\Models\FileTracking;
use App\Models\FileIndexing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FileTrackerController extends Controller
{ 
    /**
     * Store a newly created file tracking entry
     */
    public function store(Request $request) {
        try {
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
            if (!empty($validatedData['rfid_tag'])) {
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
            if (!empty($validatedData['qr_code'])) {
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
                $validatedData['qr_code'] = $fileDetails->file_number . '-' . substr(str_replace(' ', '-', $fileDetails->file_title ?? 'UNTITLED'), 0, 20);
            }

            // Create the tracking entry using raw SQL to bypass constraints
            $trackingId = DB::connection('sqlsrv')->table('file_trackings')->insertGetId([
                'file_indexing_id' => $validatedData['file_indexing_id'],
                'rfid_tag' => $validatedData['rfid_tag'],
                'qr_code' => $validatedData['qr_code'],
                'current_location' => $validatedData['current_location'],
                'current_holder' => $validatedData['current_holder'],
                'current_handler' => $validatedData['current_handler'],
                'date_received' => $validatedData['date_received'],
                'due_date' => $validatedData['due_date'],
                'status' => $validatedData['status'],
                'created_at' => now(),
                'updated_at' => now(),
                'movement_history' => json_encode([[
                    'action' => 'created',
                    'timestamp' => now()->toISOString(),
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name ?? 'System',
                    'initial_location' => $validatedData['current_location'],
                    'initial_handler' => $validatedData['current_handler'],
                    'initial_status' => $validatedData['status']
                ]])
            ]);
            
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
     * Store multiple file tracking entries (batch processing)
     */
    public function storeBatch(Request $request) {
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
                'files.*.notes' => 'nullable|string|max:1000',
                'batch_no' => 'required|integer'
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

                    // Check for duplicate RFID tags in this batch using SQL Server connection
                    if (!empty($fileData['rfid_tag'])) {
                        $existingRfid = DB::connection('sqlsrv')->table('file_trackings')
                            ->where('rfid_tag', $fileData['rfid_tag'])
                            ->first();
                        if ($existingRfid) {
                            $errors[] = "RFID tag {$fileData['rfid_tag']} is already in use";
                            continue;
                        }
                    }

                    // Check for duplicate QR codes using SQL Server connection
                    if (!empty($fileData['qr_code'])) {
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
                        $fileData['qr_code'] = $fileDetails->file_number . '-' . substr(str_replace(' ', '-', $fileDetails->file_title ?? 'UNTITLED'), 0, 20);
                    }

                    // Create the tracking entry using raw SQL to bypass constraints
                    $trackingId = DB::connection('sqlsrv')->table('file_trackings')->insertGetId([
                        'file_indexing_id' => $fileData['file_indexing_id'],
                        'rfid_tag' => $fileData['rfid_tag'],
                        'qr_code' => $fileData['qr_code'],
                        'current_location' => $fileData['current_location'],
                        'current_holder' => $fileData['current_holder'],
                        'current_handler' => $fileData['current_handler'],
                        'date_received' => $fileData['date_received'],
                        'due_date' => $fileData['due_date'],
                        'status' => $fileData['status'],
                        'batch_no' => $validatedData['batch_no'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'movement_history' => json_encode([[
                            'action' => 'created',
                            'timestamp' => now()->toISOString(),
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()->name ?? 'System',
                            'initial_location' => $fileData['current_location'],
                            'initial_handler' => $fileData['current_handler'],
                            'initial_status' => $fileData['status']
                        ]])
                    ]);
                    
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
                'batch_no' => $validatedData['batch_no'],
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
}