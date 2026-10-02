<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CommissioningSheetController extends Controller
{
    /**
     * Store a new commissioning sheet
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number' => 'required|string|max:255',
                'file_name' => 'nullable|string|max:500',
                'name_or_allottee' => 'nullable|string|max:500',
                'plot_number' => 'nullable|string|max:255',
                'tp_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:500',
                'lga' => 'nullable|string|max:100',
                'date_created' => 'nullable|date',
                'created_by' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['created_user_id'] = Auth::id();
            $data['status'] = 'Draft';
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Insert into database
            $id = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->insertGetId($data);

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet saved successfully',
                'data' => ['id' => $id]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error saving commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate and save commissioning sheet data (PDF generated on frontend)
     */
    public function generateAndPrint(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number'        => 'required|string|max:255',
                'file_name'          => 'nullable|string|max:500',
                'name_or_allottee'   => 'nullable|string|max:500',
                'plot_number'        => 'nullable|string|max:255',
                'tp_number'          => 'nullable|string|max:255',
                'location'           => 'nullable|string|max:500',
                'lga'                => 'nullable|string|max:100',
                'date_created'       => 'nullable|string|max:255',
                'created_by'         => 'nullable|string|max:255',
                'commissioning_time' => 'nullable|string|max:50',
                'related_file_number'=> 'nullable|string|max:255',
                'related_file_title' => 'nullable|string|max:255',
                'original_file_no'   => 'nullable|string|max:255',
                'original_op_holder' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['created_user_id'] = Auth::id();
            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::user()->name ?? Auth::user()->email ?? '';
            }
            if (empty($data['date_created'])) {
                $data['date_created'] = now();
            } else {
                try {
                    $data['date_created'] = Carbon::parse($data['date_created']);
                } catch (\Throwable $e) {
                    $data['date_created'] = now();
                }
            }

            // Auto-lookup related file info:
            //   - Application type (Merger, Subdivision, etc.) from mls_file_no.source
            //   - Related file number from fileNumber.related_fileno
            $fileNo = $data['file_number'];

            // 1. Get application type from mls_file_no.source
            if (empty($data['related_file_title'])) {
                $mlsRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNo)
                    ->select('source')
                    ->first();

                if ($mlsRow && !empty($mlsRow->source)) {
                    // Source values are already human-readable labels
                    // (Conversion, Change of Purpose, Subdivision, Merger, ...)
                    $data['related_file_title'] = trim($mlsRow->source);
                }
            }

            // A Plot Extension keeps the original file number, so a file already
            // indexed/commissioned under that number has no mls_file_no.source of its
            // own. Prefer the Plot Extension reason over a blank/guessed label.
            if (empty($data['related_file_title'])) {
                $hasPlotExtension = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('original_file_no', $fileNo)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->exists();

                if ($hasPlotExtension) {
                    $data['related_file_title'] = 'Plot Extension';
                }
            }

            // 2. Get related file number from fileNumber.related_fileno
            if (empty($data['related_file_number'])) {
                $fnRow = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $fileNo)
                    ->select('related_fileno')
                    ->first();

                if ($fnRow && !empty($fnRow->related_fileno)) {
                    $data['related_file_number'] = $fnRow->related_fileno;
                }
            }

            // commissioning_time is not a DB column; store it temporarily for the print view
            $commissioningTime = $data['commissioning_time'] ?? null;
            unset($data['commissioning_time']);
            // original_file_no is not a DB column either
            unset($data['original_file_no']);
            // original_op_holder is not a DB column either
            $originalOpHolder = $data['original_op_holder'] ?? null;
            unset($data['original_op_holder']);
            $data['status'] = 'Draft';
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Insert into database
            $id = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->insertGetId($data);

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet data saved successfully',
                'data' => [
                    'id' => $id,
                    'commissioning_time' => $commissioningTime,
                    'original_op_holder' => $originalOpHolder
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error saving commissioning sheet data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save commissioning sheet data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all commissioning sheets
     */
    public function index()
    {
        try {
            $sheets = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sheets
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching commissioning sheets: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commissioning sheets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific commissioning sheet
     */
    public function show($id)
    {
        try {
            $sheet = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->first();

            if (!$sheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $sheet
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a commissioning sheet
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number' => 'required|string|max:255',
                'file_name' => 'nullable|string|max:500',
                'name_or_allottee' => 'nullable|string|max:500',
                'plot_number' => 'nullable|string|max:255',
                'tp_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:500',
                'lga' => 'nullable|string|max:100',
                'date_created' => 'nullable|date',
                'created_by' => 'nullable|string|max:255',
                'approved_by' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:Draft,Approved,Printed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['updated_at'] = now();

            $affected = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->update($data);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found or no changes made'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a commissioning sheet
     */
    public function destroy($id)
    {
        try {
            $affected = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print a specific commissioning sheet.
     */
    public function print($id)
    {
        try {
            $sheet = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->first();

            if (!$sheet) {
                abort(404, 'Commissioning sheet not found');
            }

            $data = (array) $sheet;

            // Source commissioning date/time from DCIV metadata table.
            $dcivMeta = DB::connection('sqlsrv')
                ->table('dciv_file_no')
                ->select('commissioning_date', 'commissioning_time', 'tracking_id')
                ->where('full_file_number', $data['file_number'] ?? null)
                ->orderByDesc('id')
                ->first();

            if ($dcivMeta) {
                $data['commissioning_date'] = $dcivMeta->commissioning_date;
                $data['commissioning_time'] = $dcivMeta->commissioning_time;
                $data['tracking_id'] = $dcivMeta->tracking_id;
            }

            // Accept commissioning_time from query param (passed from OSS table)
            if (empty($data['commissioning_time']) && request()->has('commissioning_time')) {
                $data['commissioning_time'] = request()->input('commissioning_time');
            }

            // Accept original_op_holder from query param (passed from OSS table)
            if (empty($data['original_op_holder']) && request()->has('original_op_holder')) {
                $data['original_op_holder'] = request()->input('original_op_holder');
            }

            // Fallback: use created_at for time/date when DCIV data is unavailable
            if (empty($data['commissioning_time']) && !empty($data['created_at'])) {
                try {
                    $data['commissioning_time'] = \Carbon\Carbon::parse($data['created_at'])->format('h:i A');
                } catch (\Throwable $e) {}
            }
            if (empty($data['commissioning_date']) && empty($data['date_created']) && !empty($data['created_at'])) {
                try {
                    $data['date_created'] = \Carbon\Carbon::parse($data['created_at'])->format('Y-m-d');
                } catch (\Throwable $e) {}
            }

            // Resolve user name from created_user_id when created_by is empty
            if (empty($data['created_by']) && !empty($data['created_user_id'])) {
                $user = DB::connection('sqlsrv')->table('users')->where('id', $data['created_user_id'])->first();
                if ($user) {
                    $data['created_by'] = $user->name ?? $user->email ?? '';
                }
            }

            // ── Auto-resolve related file info ────────────────────────────────
            //   - Application type from mls_file_no.source (e.g. Merger, Subdivision)
            //   - Related file number from fileNumber.related_fileno
            $fileNo = $data['file_number'] ?? '';

            // 1. Get application type from mls_file_no.source
            if (empty($data['related_file_title'])) {
                $mlsRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNo)
                    ->select('source')
                    ->first();

                if ($mlsRow && !empty($mlsRow->source)) {
                    // Source values are already human-readable labels
                    // (Conversion, Change of Purpose, Subdivision, Merger, ...)
                    $data['related_file_title'] = trim($mlsRow->source);
                }
            }

            // A Plot Extension keeps the original file number, so a file already
            // indexed/commissioned under that number has no mls_file_no.source of its
            // own. Prefer the Plot Extension reason over a blank/guessed label.
            if (empty($data['related_file_title'])) {
                $hasPlotExtension = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('original_file_no', $fileNo)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->exists();

                if ($hasPlotExtension) {
                    $data['related_file_title'] = 'Plot Extension';
                }
            }

            // 2. Get related file number from fileNumber.related_fileno
            if (empty($data['related_file_number'])) {
                $fnRow = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $fileNo)
                    ->select('related_fileno')
                    ->first();

                if ($fnRow && !empty($fnRow->related_fileno)) {
                    $data['related_file_number'] = $fnRow->related_fileno;
                }
            }

            // 3. SIT files carry a reason that prints after the Location
            if (empty($data['sit_reason']) && stripos($fileNo, 'SIT-') === 0) {
                $sitRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNo)
                    ->select('sit_reason')
                    ->first();

                if ($sitRow && !empty($sitRow->sit_reason)) {
                    $data['sit_reason'] = $sitRow->sit_reason;
                }
            }

            return view('commissioning_sheet.pdf', compact('data'));

        } catch (\Exception $e) {
            \Log::error('Error printing commissioning sheet: ' . $e->getMessage());
            abort(500, 'Failed to print commissioning sheet');
        }
    }
}
