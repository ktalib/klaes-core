<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;

class RDSController extends Controller
{
    /**
     * Where an RDS renders and where it sends the user afterwards.
     *
     * Declared as properties rather than written inline so a second registry can
     * reuse this whole controller with its own template and its own register
     * screen. The defaults are the Deeds ones, so nothing here changes for them.
     *
     * @see \App\Http\Controllers\LandRegistration\LandRdsController
     */
    protected string $printView = 'instrument_registration.rds.print';
    protected string $printRouteName = 'rds.print';
    protected string $indexRouteName = 'instrument_registration.index';

    /**
     * Generate RDS (Registered Document Sheet) for an instrument
     */
    public function generateRDS($id)
    {
        try {
            Log::info("Generating RDS for ID: " . $id);

            $instrument = null;
            $sourceTable = 'registered_instruments';
            $originalId = $id;

            // Check if it's a generic instrument from deed_registrations
            if (strpos($id, 'deed_reg_') === 0) {
                $realId = substr($id, 9); // Remove 'deed_reg_' prefix
                $instrument = DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $realId)
                    ->first();
                $sourceTable = 'deed_registrations';
                // Do NOT strip prefix here for originalId, need it for tracking
                // But need realId for DB update logic
                // $originalId = $realId; // REMOVED THIS LINE to preserve prefix in tracking
            } else {
                // Handling composite IDs for ST instruments (legacy fallback, should be handled by prefix now)
                if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
                    $id = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
                }

                // Standard instrument
                $instrument = DB::connection('sqlsrv')
                    ->table('registered_instruments')
                    ->where('id', $id)
                    ->first();
            }

            if (!$instrument) {
                return response()->json([
                    'success' => false,
                    'error' => 'Instrument not found'
                ], 404);
            }

            // Map generic instrument fields to match registered_instruments structure if needed
            if ($sourceTable === 'deed_registrations') {
                $instrument->STM_Ref = null; // Generic instruments don't have STM_Ref usually
                $instrument->Grantor = $instrument->grantor ?? 'N/A';
                $instrument->Grantee = $instrument->grantee ?? 'N/A';
                $instrument->instrumentDate = $instrument->deeds_date;
                $instrument->status = 'registered'; // Assumed registered if in this table
            } elseif ($instrument->status !== 'registered') {
                return response()->json([
                    'success' => false,
                    'error' => 'Only registered instruments can have RDS generated'
                ], 400);
            }

            // Check if RDS already exists
            $existingRDS = DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('instrument_id', $originalId) // Use the original prefixed ID for tracking
                ->first();

            if ($existingRDS) {
                return response()->json([
                    'success' => false,
                    'error' => 'RDS has already been generated for this instrument',
                    'rds_id' => $existingRDS->id,
                    'generated_at' => $existingRDS->generated_at
                ], 400);
            }

            // Generate RDS reference number
            $rdsReference = $this->generateRDSReference();

            // Get additional details for the RDS
            $instrumentDetails = $this->getInstrumentDetails($instrument);

            // Create RDS tracking record
            $rdsId = DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->insertGetId([
                    'instrument_id' => $originalId, // Store the prefixed ID
                    'stm_ref' => $instrument->STM_Ref,
                    'rds_reference' => $rdsReference,
                    'instrument_type' => $instrument->instrument_type,
                    'grantor' => $instrument->Grantor,
                    'grantee' => $instrument->Grantee,
                    'file_number' => $instrument->fileno ?? '',
                    'registration_date' => $instrument->instrumentDate ?? null,
                    'generated_by' => Auth::id(),
                    'generated_at' => now(),
                    'status' => 'generated',
                    'print_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

            // Log the generation
            Log::info("RDS generated", [
                'rds_id' => $rdsId,
                'instrument_id' => $originalId,
                'stm_ref' => $instrument->STM_Ref,
                'rds_reference' => $rdsReference,
                'user_id' => Auth::id()
            ]);

            // Update database flag for faster checking if it's a deed registration
            if ($sourceTable === 'deed_registrations') {
                $this->updateRdsExistsFlag($originalId);
            }

            return response()->json([
                'success' => true,
                'message' => 'RDS generated successfully',
                'rds_id' => $rdsId,
                'rds_reference' => $rdsReference,
                'rds_url' => route('rds.view', ['id' => $originalId])
            ]);

        } catch (\Exception $e) {
            Log::error("Error generating RDS: " . $e->getMessage(), [
                'instrument_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while generating RDS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View/Print RDS for an instrument
     */
    public function viewRDS($id)
    {
        // Handle composite IDs for ST instruments (legacy fallback)
        if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
            $id = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
        }

        $forwardedQuery = request()->only([
            'autoprint',
            'batch_session_id',
            'batch_scope',
            'batch_total',
            'batch_doc_id',
            'batch_token',
            'batch_complete_url',
        ]);

        $forwardedQuery = array_filter($forwardedQuery, function ($value) {
            return !is_null($value) && $value !== '';
        });

        $queryString = http_build_query($forwardedQuery);

        try {
            // Get the RDS tracking record
            $rds = DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('instrument_id', $id)
                ->first();

            if (!$rds) {
                // Check if it's an AJAX request
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'RDS has not been generated yet for this instrument'
                    ], 404);
                }

                // Direct access - redirect with error
                return redirect()->route($this->indexRouteName)
                    ->with('error', 'RDS has not been generated yet for this instrument');
            }

            // Get the instrument details
            $instrument = null;
            if (strpos($id, 'deed_reg_') === 0) {
                $realId = substr($id, 9);
                $instrument = DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $realId)
                    ->first();

                if ($instrument) {
                    $instrument->Grantor = $instrument->grantor ?? 'N/A';
                    $instrument->Grantee = $instrument->grantee ?? 'N/A';
                    $instrument->instrumentDate = $instrument->deeds_date;
                    $instrument->status = 'registered';
                }
            } else {
                $instrument = DB::connection('sqlsrv')
                    ->table('registered_instruments')
                    ->where('id', $id)
                    ->first();

                // Fallback: If not found in registered_instruments, try deed_registrations (assuming generic instrument with stripped ID)
                if (!$instrument && is_numeric($id)) {
                    $instrument = DB::connection('sqlsrv')
                        ->table('deed_registrations')
                        ->where('id', $id)
                        ->first();

                    if ($instrument) {
                        $instrument->status = 'registered'; // Assume registered if found here
                        // Map other fields as needed if they differ significantly
                    }
                }
            }

            if (!$instrument) {
                // Check if it's an AJAX request
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Instrument not found'
                    ], 404);
                }

                // Direct access - redirect with error
                return redirect()->route($this->indexRouteName)
                    ->with('error', 'Instrument not found');
            }

            // Increment print count
            DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('id', $rds->id)
                ->update([
                    'print_count' => DB::raw('print_count + 1'),
                    'last_printed_at' => now(),
                    'last_printed_by' => Auth::id(),
                    'updated_at' => now()
                ]);

            // Check if it's an AJAX request
            if (request()->ajax() || request()->wantsJson()) {
                $rdsUrl = route($this->printRouteName, ['id' => $id]);
                if ($queryString !== '') {
                    $rdsUrl .= '?' . $queryString;
                }

                return response()->json([
                    'success' => true,
                    'rds_url' => $rdsUrl
                ]);
            }

            // Direct access - redirect to print template
            if (strpos($id, 'deed_reg_') === 0) {
                $this->updateRdsExistsFlag($id);
            }

            $printUrl = route($this->printRouteName, ['id' => $id]);
            if ($queryString !== '') {
                $printUrl .= '?' . $queryString;
            }

            return redirect($printUrl);

        } catch (\Exception $e) {
            Log::error("Error viewing RDS: " . $e->getMessage(), [
                'instrument_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            // Check if it's an AJAX request
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'An error occurred while viewing RDS'
                ], 500);
            }

            // Direct access - redirect with error
            return redirect()->route($this->indexRouteName)
                ->with('error', 'An error occurred while viewing RDS');
        }
    }

    /**
     * Print/Display RDS HTML template
     */
    public function printRDS($id)
    {
        try {
            $data = $this->getPrintViewData($id);
            return view($this->printView, $data);
        } catch (\Exception $e) {
            Log::error("Error printing RDS: " . $e->getMessage(), [
                'instrument_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            abort(500, 'An error occurred while loading RDS');
        }
    }

    public function getPrintViewData($id): array
    {
        // Handle composite IDs for ST instruments
        if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
            $id = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
        }

        // Get the RDS tracking record
        $rds = DB::connection('sqlsrv')
            ->table('rds_tracking')
            ->where('instrument_id', $id)
            ->first();

        if (!$rds) {
            abort(404, 'RDS not found');
        }

        // Get the instrument details
        $instrument = null;
        if (strpos($id, 'deed_reg_') === 0) {
            $realId = substr($id, 9);
            $instrument = DB::connection('sqlsrv')
                ->table('deed_registrations')
                ->where('id', $realId)
                ->first();

            if ($instrument) {
                $instrument->Grantor = $instrument->grantor ?? 'N/A';
                $instrument->Grantee = $instrument->grantee ?? 'N/A';
                $instrument->instrumentDate = $instrument->deeds_date;
                $instrument->status = 'registered';
            }
        } else {
            $instrument = DB::connection('sqlsrv')
                ->table('registered_instruments')
                ->where('id', $id)
                ->first();
        }

        if (!$instrument) {
            abort(404, 'Instrument not found');
        }

        // Update flag if it's a deed registration
        if (strpos($id, 'deed_reg_') === 0) {
            $this->updateRdsExistsFlag($id);
        }

        // Fetch party addresses and OP serial from instrument_capture
        if (!empty($instrument->fileno)) {
            $capture = DB::connection('sqlsrv')->table('instrument_capture')
                ->where(function ($q) use ($instrument) {
                    $q->where('mlsFNo', $instrument->fileno)
                        ->orWhere('kangisFileNo', $instrument->fileno)
                        ->orWhere('NewKANGISFileno', $instrument->fileno)
                        ->orWhere('temp_fileno', $instrument->fileno);
                })
                ->where('instrument_type', $instrument->instrument_type)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($capture) {
                $instrument->party_1_address = $capture->party_1_address;
                $instrument->party_2_address = $capture->party_2_address;
                $instrument->op_serial_number = $capture->op_serial_number ?? null;
                $instrument->op_type = $capture->op_type ?? null;
                $instrument->consideration_amount = $capture->consideration_amount ?? null;
                $instrument->receipt_no = $capture->receipt_no ?? null;
            } else {
                $capture = DB::connection('sqlsrv')->table('instrument_capture')
                    ->where(function ($q) use ($instrument) {
                        $q->where('mlsFNo', $instrument->fileno)
                            ->orWhere('kangisFileNo', $instrument->fileno)
                            ->orWhere('NewKANGISFileno', $instrument->fileno)
                            ->orWhere('temp_fileno', $instrument->fileno);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($capture) {
                    $instrument->party_1_address = $capture->party_1_address;
                    $instrument->party_2_address = $capture->party_2_address;
                    $instrument->op_serial_number = $capture->op_serial_number ?? null;
                    $instrument->op_type = $capture->op_type ?? null;
                    $instrument->consideration_amount = $capture->consideration_amount ?? null;
                    $instrument->receipt_no = $capture->receipt_no ?? null;
                }
            }
        }

        $details = $this->getInstrumentDetails($instrument);

        $documentTitle = 'ASSIGNMENT';
        $documentSubtitle = 'THIS IS A DEED OF ASSIGNMENT';
        $assignmentMortgageText = 'is Assigned';

        if ($instrument->instrument_type) {
            $type = strtoupper($instrument->instrument_type);

            if (strpos($type, 'MORTGAGE') !== false) {
                $documentTitle = 'MORTGAGE';
                $documentSubtitle = 'THIS IS A DEED OF MORTGAGE';
                $assignmentMortgageText = 'is Mortgaged';
            } elseif (strpos($type, 'ASSIGNMENT') !== false) {
                $documentTitle = 'ASSIGNMENT';
                $documentSubtitle = 'THIS IS A DEED OF ASSIGNMENT';
                $assignmentMortgageText = 'is Assigned';
            } else {
                $documentTitle = $type;
                $documentSubtitle = 'THIS IS A DEED OF ' . $type;
                $assignmentMortgageText = 'is ' . $type;
            }
        }

        return [
            'rds' => $rds,
            'instrument' => $instrument,
            'details' => $details,
            'documentTitle' => $documentTitle,
            'documentSubtitle' => $documentSubtitle,
            'assignmentMortgageText' => $assignmentMortgageText,
            'printCount' => $rds->print_count,
            'watermark' => $rds->print_count > 1 ? 'COPY' : 'ORIGINAL'
        ];
    }

    /**
     * Get RDS status for an instrument
     */
    public function getRDSStatus($id)
    {
        try {
            $rds = DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('instrument_id', $id)
                ->first();

            // Determine if CoR exists
            $corExists = false;
            if (strpos($id, 'deed_reg_') === 0) {
                $realId = substr($id, 9);
                $corExists = (bool) DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $realId)
                    ->value('cor_exists');
            } else {
                $corExists = (bool) DB::connection('sqlsrv')
                    ->table('registered_instruments')
                    ->where('id', $id)
                    ->value('cor_exists');
            }

            if (!$rds) {
                return response()->json([
                    'success' => true,
                    'exists' => false,
                    'cor_exists' => $corExists,
                    'message' => 'RDS not generated'
                ]);
            }

            return response()->json([
                'success' => true,
                'exists' => true,
                'cor_exists' => $corExists,
                'rds' => [
                    'id' => $rds->id,
                    'rds_reference' => $rds->rds_reference,
                    'generated_at' => $rds->generated_at,
                    'generated_by' => $rds->generated_by,
                    'print_count' => $rds->print_count,
                    'last_printed_at' => $rds->last_printed_at,
                    'status' => $rds->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting RDS status: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking RDS status'
            ], 500);
        }
    }

    /**
     * Generate unique RDS reference number
     */
    private function generateRDSReference()
    {
        $year = date('Y');
        $prefix = "RDS-{$year}-";

        // Get the latest RDS reference for the current year
        $latestRef = DB::connection('sqlsrv')
            ->table('rds_tracking')
            ->where('rds_reference', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('rds_reference');

        if ($latestRef) {
            // Extract the sequence number
            $matches = [];
            if (preg_match('/RDS-\d{4}-(\d{5})/', $latestRef, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get detailed information about the instrument
     */
    private function getInstrumentDetails($instrument)
    {
        $details = [
            'registration_date' => $instrument->instrumentDate ? Carbon::parse($instrument->instrumentDate)->format('d/m/Y') : '',
            'registration_time' => $instrument->instrumentDate ? Carbon::parse($instrument->instrumentDate)->format('H:i:s') : '',
            'instrument_date' => $instrument->deeds_date ?? '',
            'instrument_time' => $instrument->deeds_time ?? '',
            'consideration' => $instrument->consideration ?? 'N/A',
            'stamp_duty' => $instrument->stamp_duty ?? 'N/A',
            'registration_fee' => $instrument->registration_fee ?? 'N/A',
            'plot_number' => $instrument->plotNumber ?? '',
            'plot_size' => $instrument->size ?? '',
            'plot_description' => $instrument->propertyDescription ?? '',
            'lga' => $instrument->lga ?? '',
            'district' => $instrument->district ?? '',
            'duration' => $instrument->duration ?? $instrument->leasePeriod ?? '',
            'root_registration_number' => $instrument->rootRegistrationNumber ?? $instrument->Deeds_Serial_No ?? '',
            'solicitor_name' => $instrument->solicitorName ?? '',
            'solicitor_address' => $instrument->solicitorAddress ?? '',
            'land_use_type' => $instrument->landUseType ?? $instrument->land_use ?? ''
        ];

        return $details;
    }

    /**
     * Delete/Cancel RDS (admin only)
     */
    public function deleteRDS($id)
    {
        // Handle composite IDs for ST instruments
        if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
            $id = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
        }

        try {
            // Check admin permission
            if (!Auth::user() || Auth::user()->type !== 'super admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only administrators can delete RDS records.'
                ], 403);
            }

            // Get the RDS tracking record
            $rds = DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('instrument_id', $id)
                ->first();

            if (!$rds) {
                return response()->json([
                    'success' => false,
                    'error' => 'RDS not found for this instrument'
                ], 404);
            }

            // Determine if it's a deed registration to unset the flag
            // Check for deed_reg_ prefix first
            if (strpos($id, 'deed_reg_') === 0) {
                $realId = substr($id, 9);
                DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $realId)
                    ->update(['rds_exists' => false, 'updated_at' => now()]);
            } else {
                // Try to find in deed_registrations by ID if no prefix (legacy/fallback)
                $deedReg = DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $id)
                    ->first();

                if ($deedReg) {
                    DB::connection('sqlsrv')
                        ->table('deed_registrations')
                        ->where('id', $id)
                        ->update(['rds_exists' => false, 'updated_at' => now()]);
                }
            }

            // Delete the RDS tracking record
            DB::connection('sqlsrv')
                ->table('rds_tracking')
                ->where('id', $rds->id)
                ->delete();

            Log::info("RDS deleted successfully for instrument ID: " . $id);

            return response()->json([
                'success' => true,
                'message' => 'RDS deleted successfully'
            ]);



        } catch (\Exception $e) {
            Log::error("Error deleting RDS: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while deleting RDS'
            ], 500);
        }
    }

    /**
     * List all RDS records with filters
     */
    public function listRDS(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')
                ->table('rds_tracking as rds')
                ->leftJoin('registered_instruments as ri', 'rds.instrument_id', '=', 'ri.id')
                ->select(
                    'rds.*',
                    'ri.status as instrument_status',
                    'ri.instrument_type',
                    'ri.fileno'
                );

            // Apply filters
            if ($request->has('status')) {
                $query->where('rds.status', $request->status);
            }

            if ($request->has('instrument_type')) {
                $query->where('ri.instrument_type', $request->instrument_type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('rds.generated_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('rds.generated_at', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('rds.rds_reference', 'like', "%{$search}%")
                        ->orWhere('rds.stm_ref', 'like', "%{$search}%")
                        ->orWhere('ri.fileno', 'like', "%{$search}%")
                        ->orWhere('rds.grantor', 'like', "%{$search}%")
                        ->orWhere('rds.grantee', 'like', "%{$search}%");
                });
            }

            $records = $query->orderBy('rds.generated_at', 'desc')
                ->paginate($request->get('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => $records
            ]);

        } catch (\Exception $e) {
            Log::error("Error listing RDS: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while fetching RDS records'
            ], 500);
        }
    }

    private function updateRdsExistsFlag($id)
    {
        try {
            $realId = (strpos($id, 'deed_reg_') === 0) ? substr($id, 9) : $id;
            if (is_numeric($realId)) {
                DB::connection('sqlsrv')
                    ->table('deed_registrations')
                    ->where('id', $realId)
                    ->update(['rds_exists' => true, 'updated_at' => now()]);
                Log::info("Updated rds_exists flag for deed_reg ID: " . $realId);
            }
        } catch (\Exception $e) {
            Log::error("Error updating rds_exists flag: " . $e->getMessage());
        }
    }
}
