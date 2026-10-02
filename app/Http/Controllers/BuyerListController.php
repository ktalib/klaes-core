<?php

namespace App\Http\Controllers;

use App\Support\BuyerListLog as Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * BuyerListController - Standalone controller for buyer list CRUD operations
 * 
 * This controller handles all buyer-related operations including:
 * - Creating/Adding buyers (manual and CSV import)
 * - Reading/Retrieving buyer lists
 * - Updating individual buyers
 * - Deleting buyers
 * - CSV template download
 * 
 * Database Tables:
 * - buyer_list: Main buyer information (buyer_title, buyer_name, unit_no, cubic_easurement, application_id)
 * - st_unit_measurements: Unit measurement details (measurement, buyer_id, application_id, unit_no)
 * 
 * Field Name Mapping (for compatibility with step4-buyers.blade.php):
 * - buyerTitle -> buyer_title
 * - firstName + middleName + surname -> buyer_name (concatenated with spaces)
 * - unit_no -> unit_no (unchanged)
 * - unitMeasurement -> measurement (in st_unit_measurements table)
 * - cubicMeasurement -> cubic_easurement (in buyer_list table)
 * - landUse -> stored in buyer_list for reference
 *
 * Logging: every action here writes to storage/logs/buyer_list.log via
 * {@see \App\Support\BuyerListLog}, interleaved with the browser's own trace from
 * {@see BuyerListDiagnosticsController}. Officers report the capture form emptying
 * itself mid-entry, which leaves no server-side trace at all — so the point of
 * logging the accepted payload shape (not just the failures) is to establish
 * whether a submit ever reached the server, and with how many rows.
 */
class BuyerListController extends Controller
{
    /**
     * Get all buyers for a specific application
     * 
     * @param int $applicationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBuyersList($applicationId)
    {
        try {
            // Query the buyer_list table and join with st_unit_measurements
            // Use DISTINCT to avoid duplicate records when multiple measurements exist for same buyer
            $records = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function($join) {
                    $join->on('bl.id', '=', 'sum.buyer_id')
                         ->on('bl.application_id', '=', 'sum.application_id');
                })
                ->where('bl.application_id', $applicationId)
                ->select(
                    'bl.id', 
                    'bl.buyer_title', 
                    'bl.buyer_name', 
                    'bl.unit_no', 
                    'bl.section_number',
                    'bl.block_no',
                    'bl.land_use',
                    'bl.cubic_easurement',
                    'bl.unit_measurement_id', 
                    'sum.measurement',
                    'bl.created_at',
                    'bl.updated_at'
                )
                ->distinct()
                ->orderBy('bl.created_at', 'desc')
                ->orderBy('bl.id', 'desc')
                ->get()
                ->toArray();

            return response()->json([
                'success' => true,
                'records' => $records,
                'count' => count($records)
            ]);
        } catch (\Exception $e) {
            Log::error('getBuyersList failed', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add buyers manually (from form submission)
     * Supports both single and multiple buyers
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBuyers(Request $request)
    {
        $traceId = $request->input('client_trace_id');

        try {
            // Always extract application_id from the request
            $applicationId = $request->input('application_id');

            // A CSV upload is the authoritative list for the file: the officer's
            // spreadsheet replaces whatever is stored rather than being merged into
            // it. Merging is what left COM-1991-46 holding two cohorts of the same
            // 249 buyers under different section formats ("U1 A/B" vs "U1"), which
            // the name+unit+section duplicate check could never match up. The manual
            // form does NOT replace - it keeps the old add-and-skip-duplicates path.
            $replaceExisting = filter_var(
                $request->input('replace_existing', false),
                FILTER_VALIDATE_BOOLEAN
            );

            Log::info('addBuyers received', [
                'trace_id' => $traceId,
                'application_id' => $applicationId,
                'source' => $request->input('client_source', 'form'),
                'content_type' => $request->header('Content-Type'),
                // The shape of what arrived, before any parsing: if the browser
                // lost rows before POSTing, the loss is visible right here.
                'records_type' => gettype($request->input('records')),
                'records_count' => is_array($request->input('records')) ? count($request->input('records')) : null,
                'payload_keys' => array_slice(array_keys($request->all()), 0, 25),
            ]);

            if (!$applicationId) {
                Log::warning('addBuyers rejected: no application_id', ['trace_id' => $traceId]);

                return response()->json([
                    'success' => false,
                    'message' => 'Application ID is required.',
                    'errors' => ['application_id' => ['Application ID is required.']]
                ], 422);
            }

            // Extract records array
            $records = $request->input('records');
            if (!is_array($records)) {
                // Try to parse from JSON if sent as a string
                $records = json_decode($request->input('records'), true);
            }
            
            // If still not an array, try to build from form data
            if (!is_array($records)) {
                $records = [];
                foreach ($request->all() as $key => $value) {
                    if (preg_match('/^records\[(\d+)\]\[(\w+)\]$/', $key, $matches)) {
                        $index = $matches[1];
                        $field = $matches[2];
                        $records[$index][$field] = $value;
                    }
                }
                // Re-index array
                $records = array_values($records);
            }

            // Validate records array
            if (!is_array($records) || count($records) < 1) {
                Log::warning('addBuyers rejected: empty records', [
                    'trace_id' => $traceId,
                    'application_id' => $applicationId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'At least one buyer record is required.',
                    'errors' => ['records' => ['At least one buyer record is required.']]
                ], 422);
            }

            // Validate each record
            foreach ($records as $i => $record) {
                // Check for either firstName+surname OR buyerName
                $hasNames = !empty($record['firstName'] ?? '') && !empty($record['surname'] ?? '');
                $hasBuyerName = !empty($record['buyerName'] ?? '');
                
                if (!$hasNames && !$hasBuyerName) {
                    Log::warning('addBuyers rejected: missing buyer name', [
                        'trace_id' => $traceId,
                        'application_id' => $applicationId,
                        'row' => $i + 1,
                        'row_count' => count($records),
                        'row_keys' => array_keys((array) $record),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Buyer name is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.buyerName" => ['Buyer name is required.']]
                    ], 422);
                }
                
                if (empty($record['unit_no'] ?? '')) {
                    Log::warning('addBuyers rejected: missing unit_no', [
                        'trace_id' => $traceId,
                        'application_id' => $applicationId,
                        'row' => $i + 1,
                        'row_count' => count($records),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Unit number is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.unit_no" => ['Unit number is required.']]
                    ], 422);
                }

                if (empty($record['sectionNumber'] ?? '')) {
                    Log::warning('addBuyers rejected: missing sectionNumber', [
                        'trace_id' => $traceId,
                        'application_id' => $applicationId,
                        'row' => $i + 1,
                        'row_count' => count($records),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Section number is required for buyer " . ($i + 1),
                        'errors' => ["records.$i.sectionNumber" => ['Section number is required.']]
                    ], 422);
                }
            }

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                Log::warning('addBuyers rejected: application not found', [
                    'trace_id' => $traceId,
                    'application_id' => $applicationId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' &&
                $application->planning_recommendation_status == 'Approved') {
                Log::warning('addBuyers rejected: application approved and locked', [
                    'trace_id' => $traceId,
                    'application_id' => $applicationId,
                    'row_count' => count($records),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add buyers - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            $insertedCount = 0;
            $skippedCount = 0;
            $deletedCount = 0;
            $errors = [];

            // Replace mode clears the application's buyers first. Everything from
            // here to the commit is one transaction: a half-done replace would leave
            // the file with fewer buyers than it started with, which is worse than
            // the duplication it is meant to fix.
            $usingTransaction = false;

            if ($replaceExisting) {
                $existingBuyers = DB::connection('sqlsrv')
                    ->table('buyer_list')
                    ->where('application_id', $applicationId)
                    ->pluck('id')
                    ->all();

                // st_file_numbers.buyer_list_id ties an allocated ST unit file number
                // to a specific buyer row. Deleting such a row orphans the allocation,
                // so refuse the whole replace rather than silently breaking it - the
                // officer needs to know units have already been given file numbers.
                $allocatedCount = empty($existingBuyers) ? 0 : DB::connection('sqlsrv')
                    ->table('st_file_numbers')
                    ->whereIn('buyer_list_id', $existingBuyers)
                    ->count();

                if ($allocatedCount > 0) {
                    Log::warning('addBuyers replace blocked: buyers hold ST file numbers', [
                        'trace_id' => $traceId,
                        'application_id' => $applicationId,
                        'existing' => count($existingBuyers),
                        'allocated' => $allocatedCount,
                    ]);

                    return response()->json([
                        'success' => false,
                        'blocked_reason' => 'st_file_numbers_allocated',
                        'message' => "Cannot replace the buyer list: $allocatedCount buyer(s) on this application already have an ST unit file number allocated. Replacing them would orphan those file numbers. Remove or correct those buyers individually instead.",
                    ], 409);
                }

                DB::connection('sqlsrv')->beginTransaction();
                $usingTransaction = true;

                if (!empty($existingBuyers)) {
                    DB::connection('sqlsrv')
                        ->table('st_unit_measurements')
                        ->where('application_id', $applicationId)
                        ->whereIn('buyer_id', $existingBuyers)
                        ->delete();

                    $deletedCount = DB::connection('sqlsrv')
                        ->table('buyer_list')
                        ->where('application_id', $applicationId)
                        ->delete();
                }

                Log::info('addBuyers replacing existing buyers', [
                    'trace_id' => $traceId,
                    'application_id' => $applicationId,
                    'deleted' => $deletedCount,
                    'incoming' => count($records),
                ]);
            }

            // Process each record
            foreach ($records as $record) {
                // Build buyer name (supports both formats)
                if (!empty($record['firstName'] ?? '') || !empty($record['surname'] ?? '')) {
                    $nameParts = [];
                    if (!empty($record['firstName'])) $nameParts[] = strtoupper(trim($record['firstName']));
                    if (!empty($record['middleName'])) $nameParts[] = strtoupper(trim($record['middleName']));
                    if (!empty($record['surname'])) $nameParts[] = strtoupper(trim($record['surname']));
                    $buyerName = implode(' ', $nameParts);
                } else {
                    $buyerName = strtoupper(trim($record['buyerName'] ?? ''));
                }
                
                $unitNo = strtoupper(trim($record['unit_no'] ?? ''));
                $sectionNumber = strtoupper(trim($record['sectionNumber'] ?? ''));
                // Optional. An older CSV has no blockNo column at all, and the
                // buyer_list.block_no column is nullable, so a blank stays NULL
                // rather than becoming an empty string that sorts oddly.
                $blockNo = strtoupper(trim($record['blockNo'] ?? ''));
                $cubicMeasurement = isset($record['cubicMeasurement'])
                    ? trim($record['cubicMeasurement'])
                    : null;
                
                // Check if this buyer already exists
                $existing = DB::connection('sqlsrv')
                    ->table('buyer_list')
                    ->where('application_id', $applicationId)
                    ->where('buyer_name', $buyerName)
                    ->where('unit_no', $unitNo)
                    ->when($sectionNumber !== '', function ($query) use ($sectionNumber) {
                        $query->where('section_number', $sectionNumber);
                    })
                    ->when($blockNo !== '', function ($query) use ($blockNo) {
                        $query->where('block_no', $blockNo);
                    })
                    ->first();

                if ($existing) {
                    // Silent skips are the one outcome a user reads as "my row
                    // vanished", so name the row that was dropped and why.
                    Log::info('addBuyers skipped duplicate', [
                        'trace_id' => $traceId,
                        'application_id' => $applicationId,
                        'buyer_name' => $buyerName,
                        'unit_no' => $unitNo,
                        'section_number' => $sectionNumber,
                        'block_no' => $blockNo,
                        'existing_buyer_id' => $existing->id ?? null,
                    ]);

                    $skippedCount++;
                    continue;
                }

                // Insert new buyer record
                $buyerId = DB::connection('sqlsrv')->table('buyer_list')->insertGetId([
                    'application_id' => $applicationId,
                    'buyer_title' => strtoupper(trim($record['buyerTitle'] ?? '')),
                    'buyer_name' => $buyerName,
                    'unit_no' => $unitNo,
                    'section_number' => $sectionNumber !== '' ? $sectionNumber : null,
                    'block_no' => $blockNo !== '' ? $blockNo : null,
                    'land_use' => !empty($record['landUse']) ? strtoupper(trim($record['landUse'])) : null,
                    'cubic_easurement' => $cubicMeasurement !== '' ? $cubicMeasurement : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Handle measurement data if provided
                if (!empty($record['unitMeasurement'] ?? '')) {
                    // Check if measurement record already exists
                    $existingMeasurement = DB::connection('sqlsrv')
                        ->table('st_unit_measurements')
                        ->where('application_id', $applicationId)
                        ->where('buyer_id', $buyerId)
                        ->first();

                    if ($existingMeasurement) {
                        // Update existing measurement
                        DB::connection('sqlsrv')
                            ->table('st_unit_measurements')
                            ->where('application_id', $applicationId)
                            ->where('buyer_id', $buyerId)
                            ->update([
                                'unit_no' => $unitNo,
                                'measurement' => $record['unitMeasurement'],
                                'updated_at' => now()
                            ]);
                    } else {
                        // Insert new measurement record
                        DB::connection('sqlsrv')->table('st_unit_measurements')->insert([
                            'application_id' => $applicationId,
                            'buyer_id' => $buyerId,
                            'unit_no' => $unitNo,
                            'measurement' => $record['unitMeasurement'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }

                $insertedCount++;
            }

            if ($usingTransaction) {
                DB::connection('sqlsrv')->commit();
                $usingTransaction = false;
            }

            // Get updated records list for response
            $updatedRecords = $this->getBuyersListData($applicationId);

            if ($replaceExisting) {
                // In replace mode the table was emptied first, so a "duplicate" can
                // only be a row repeated inside the uploaded file itself. Say that,
                // rather than implying it clashed with stored data.
                $message = "$insertedCount buyer(s) imported, replacing $deletedCount previous buyer(s).";
                if ($skippedCount > 0) {
                    $message .= " $skippedCount repeated row(s) in the file were imported once.";
                }
            } else {
                $message = "Buyers saved successfully.";
                if ($insertedCount > 0 && $skippedCount > 0) {
                    $message = "$insertedCount new buyer(s) added, $skippedCount duplicate(s) skipped.";
                } elseif ($insertedCount > 0) {
                    $message = "$insertedCount new buyer(s) added successfully.";
                } elseif ($skippedCount > 0) {
                    $message = "All buyers already exist. $skippedCount duplicate(s) skipped.";
                }
            }

            Log::info('addBuyers completed', [
                'trace_id' => $traceId,
                'application_id' => $applicationId,
                'mode' => $replaceExisting ? 'replace' : 'append',
                'submitted' => count($records),
                'deleted' => $deletedCount,
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'total_now' => count($updatedRecords),
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'records' => $updatedRecords,
                'count' => count($updatedRecords),
                'replaced' => $replaceExisting,
                'deleted' => $deletedCount,
                'inserted' => $insertedCount,
                'skipped' => $skippedCount
            ]);
        } catch (\Exception $e) {
            // Never leave the file with the old list deleted and the new one
            // half-inserted.
            if (!empty($usingTransaction)) {
                DB::connection('sqlsrv')->rollBack();
            }

            Log::error('addBuyers failed', [
                'trace_id' => $traceId,
                'application_id' => $request->input('application_id'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import buyers from CSV file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importCsv(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'application_id' => 'required|integer',
                'records' => 'required|array|min:1',
                'records.*.buyerTitle' => 'nullable|string',
                'records.*.firstName' => 'required|string',
                'records.*.surname' => 'required|string',
                'records.*.unit_no' => 'required|string',
                'records.*.sectionNumber' => 'required|string',
                'records.*.blockNo' => 'nullable|string|max:50',
                'records.*.middleName' => 'nullable|string',
                'records.*.landUse' => 'nullable|string',
                'records.*.unitMeasurement' => 'nullable|numeric',
                'records.*.cubicMeasurement' => 'nullable',
            ]);

            if ($validator->fails()) {
                Log::warning('importCsv rejected by validation', [
                    'trace_id' => $request->input('client_trace_id'),
                    'application_id' => $request->input('application_id'),
                    'rows' => is_array($request->input('records')) ? count($request->input('records')) : null,
                    'errors' => $validator->errors()->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Use the addBuyers method to process the CSV data
            return $this->addBuyers($request);

        } catch (\Exception $e) {
            Log::error('importCsv failed', [
                'trace_id' => $request->input('client_trace_id'),
                'application_id' => $request->input('application_id'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a single buyer's information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBuyer(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'buyer_id'       => 'required|integer',
                'buyer_title'    => 'nullable|string',
                'buyer_name'     => 'required|string',
                'unit_no'        => 'required|string',
                'section_number' => 'nullable|string|max:100',
                'block_no'       => 'nullable|string|max:50',
                'measurement'    => 'nullable|numeric',
                'land_use'       => 'nullable|string',
                'cubic_easurement' => 'nullable',
            ]);

            Log::info('updateBuyer received', [
                'trace_id' => $request->input('client_trace_id'),
                'application_id' => $validated['application_id'],
                'buyer_id' => $validated['buyer_id'],
                'unit_no' => $validated['unit_no'],
                'section_number' => $validated['section_number'] ?? null,
            ]);

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $validated['application_id'])
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' && 
                $application->planning_recommendation_status == 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update buyer - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            // Update the buyer record
            $updated = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->update([
                    'buyer_title' => strtoupper(trim($validated['buyer_title'] ?? '')),
                    'buyer_name'  => strtoupper(trim($validated['buyer_name'])),
                    'unit_no'     => strtoupper(trim($validated['unit_no'])),
                    'section_number' => !empty($validated['section_number']) ? strtoupper(trim($validated['section_number'])) : null,
                    'block_no'    => !empty($validated['block_no']) ? strtoupper(trim($validated['block_no'])) : null,
                    'land_use'    => !empty($validated['land_use']) ? strtoupper(trim($validated['land_use'])) : null,
                    'cubic_easurement' => array_key_exists('cubic_easurement', $validated)
                        ? ($validated['cubic_easurement'] !== null && $validated['cubic_easurement'] !== ''
                            ? trim($validated['cubic_easurement'])
                            : null)
                        : null,
                    'updated_at'  => now()
                ]);

            if (!$updated) {
                Log::warning('updateBuyer matched no row', [
                    'application_id' => $validated['application_id'],
                    'buyer_id' => $validated['buyer_id'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found or no changes made'
                ], 404);
            }

            // Handle measurement data if provided
            if (isset($validated['measurement']) && !empty($validated['measurement'])) {
                $existingMeasurement = DB::connection('sqlsrv')
                    ->table('st_unit_measurements')
                    ->where('application_id', $validated['application_id'])
                    ->where('buyer_id', $validated['buyer_id'])
                    ->first();

                if ($existingMeasurement) {
                    // Update existing measurement. unit_no is written, not
                    // matched on, so renaming a unit moves its measurement
                    // instead of orphaning the old row.
                    DB::connection('sqlsrv')
                        ->table('st_unit_measurements')
                        ->where('application_id', $validated['application_id'])
                        ->where('buyer_id', $validated['buyer_id'])
                        ->update([
                            'unit_no' => $validated['unit_no'],
                            'measurement' => $validated['measurement'],
                            'updated_at' => now()
                        ]);
                } else {
                    // Insert new measurement record
                    DB::connection('sqlsrv')->table('st_unit_measurements')->insert([
                        'application_id' => $validated['application_id'],
                        'buyer_id' => $validated['buyer_id'],
                        'unit_no' => $validated['unit_no'],
                        'measurement' => $validated['measurement'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            Log::info('updateBuyer saved', [
                'application_id' => $validated['application_id'],
                'buyer_id' => $validated['buyer_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Buyer information updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Without this arm the generic catch below turns a field error into a
            // 500 whose only message is "The given data was invalid", which tells
            // the officer at the screen nothing about which box to fix.
            Log::warning('updateBuyer rejected by validation', [
                'application_id' => $request->input('application_id'),
                'buyer_id' => $request->input('buyer_id'),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('updateBuyer failed', [
                'application_id' => $request->input('application_id'),
                'buyer_id' => $request->input('buyer_id'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a buyer from the list
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBuyer(Request $request)
    {
        try {
            $validated = $request->validate([
                'application_id' => 'required|integer',
                'buyer_id'       => 'required|integer',
            ]);

            // Check if the application exists and get its status
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $validated['application_id'])
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found.'
                ], 404);
            }

            // Check if both application status and planning recommendation are approved
            if ($application->application_status == 'Approved' && 
                $application->planning_recommendation_status == 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete buyer - Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.'
                ], 403);
            }

            // Delete the buyer record
            $deleted = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buyer not found'
                ], 404);
            }

            // Also delete the measurement record if it exists
            DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->where('buyer_id', $validated['buyer_id'])
                ->where('application_id', $validated['application_id'])
                ->delete();

            // Get remaining records
            $records = $this->getBuyersListData($validated['application_id']);

            Log::info('deleteBuyer removed row', [
                'application_id' => $validated['application_id'],
                'buyer_id' => $validated['buyer_id'],
                'remaining' => count($records),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Buyer deleted successfully',
                'records' => $records,
                'count' => count($records)
            ]);
        } catch (\Exception $e) {
            Log::error('deleteBuyer failed', [
                'application_id' => $request->input('application_id'),
                'buyer_id' => $request->input('buyer_id'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download CSV template for buyer import
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTemplate()
    {
        $headers = [
            'buyerTitle',
            'firstName',
            'middleName',
            'surname',
            'unit_no',
            'sectionNumber',
            'blockNo',
            'landUse',
            'unitMeasurement',
            'cubicMeasurement'
        ];
        
        $sampleData = [
            ['Mr.', 'JOHN', 'A', 'DOE', 'A101', 'SEC-01', 'BLK-A', 'RESIDENTIAL', '50.00', '15.00'],
            ['Mrs.', 'JANE', 'B', 'SMITH', 'A102', 'SEC-02', 'BLK-A', 'COMMERCIAL', '75.50', '20.00'],
            ['Dr.', 'ROBERT', '', 'JOHNSON', 'B201', 'SEC-03', '', 'INDUSTRIAL', '100.00', '']
        ];
        
        $filename = 'buyer_import_template_' . date('Y-m-d') . '.csv';
        
        return response()->streamDownload(function() use ($headers, $sampleData) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            fputcsv($file, $headers);
            
            // Write sample data
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper method to get buyers list data
     * 
     * @param int $applicationId
     * @return array
     */
    private function getBuyersListData($applicationId)
    {
        return DB::connection('sqlsrv')
            ->table('buyer_list as bl')
            ->leftJoin('st_unit_measurements as sum', function($join) {
                $join->on('bl.id', '=', 'sum.buyer_id')
                     ->on('bl.application_id', '=', 'sum.application_id');
            })
            ->where('bl.application_id', $applicationId)
            ->select(
                'bl.id', 
                'bl.buyer_title', 
                'bl.buyer_name', 
                'bl.unit_no', 
                'bl.section_number',
                'bl.block_no',
                'bl.land_use',
                'bl.unit_measurement_id', 
                'sum.measurement',
                'bl.created_at',
                'bl.updated_at'
            )
            ->distinct()
            ->orderBy('bl.created_at', 'desc')
            ->orderBy('bl.id', 'desc')
            ->get()
            ->toArray();
    }

     
}
