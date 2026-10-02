<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecertificationController extends Controller
{  
    // ... (keeping all existing methods the same until assignSerialNumber)

    /**
     * Assign a serial number to a recertification application
     */
    public function assignSerialNumber(Request $request)
    {
        try {
            // Log the incoming request data for debugging
            Log::info('Serial number assignment request received', [
                'request_data' => $request->all(),
                'application_id_type' => gettype($request->input('application_id')),
                'serial_number_type' => gettype($request->input('serial_number'))
            ]);

            // More flexible validation - remove integer requirement
            $request->validate([
                'application_id' => 'required|exists:sqlsrv.recertification_applications,id',
                'serial_number' => 'required|string|regex:/^\d{6}$/'
            ]);
            
            $applicationId = (int) $request->input('application_id'); // Cast to integer
            $serialNumber = $request->input('serial_number');
            
            Log::info('Validation passed', [
                'application_id' => $applicationId,
                'serial_number' => $serialNumber
            ]);
            
            // Check if application exists and doesn't already have a serial number
            $application = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $applicationId)
                ->first();
                
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }
            
            if ($application->cofo_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'This application already has a serial number assigned: ' . $application->cofo_number
                ], 400);
            }
            
            // Check if serial number is already in use
            $existingUse = DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('cofo_number', $serialNumber)
                ->where('id', '!=', $applicationId)
                ->exists();
                
            if ($existingUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'This serial number is already in use by another application'
                ], 400);
            }
            
            // Also check the main Cofo table if it exists
            try {
                if (Schema::connection('sqlsrv')->hasTable('Cofo')) {
                    $existingInCofo = DB::connection('sqlsrv')
                        ->table('Cofo')
                        ->where('cofO_serialNo', $serialNumber)
                        ->exists();
                        
                    if ($existingInCofo) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This serial number is already in use in the main CofO system'
                        ], 400);
                    }
                }
            } catch (\Exception $e) {
                // If we can't check the Cofo table, log warning but continue
                Log::warning('Could not check Cofo table for duplicate serial number', [
                    'serial_number' => $serialNumber,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Assign the serial number
            DB::connection('sqlsrv')
                ->table('recertification_applications')
                ->where('id', $applicationId)
                ->update([
                    'cofo_number' => $serialNumber,
                    'cofo_assigned_date' => now(),
                    'cofo_assigned_by' => auth()->id(),
                    'updated_at' => now()
                ]);
            
            Log::info('Serial number assigned successfully', [
                'application_id' => $applicationId,
                'serial_number' => $serialNumber,
                'assigned_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Serial number assigned successfully',
                'serial_number' => $serialNumber,
                'application_id' => $applicationId
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for serial number assignment', [
                'request_data' => $request->all(),
                'validation_errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error assigning serial number', [
                'application_id' => $request->input('application_id'),
                'serial_number' => $request->input('serial_number'),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign serial number. Please try again.'
            ], 500);
        }
    }
}