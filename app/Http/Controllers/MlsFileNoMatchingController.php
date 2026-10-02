<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FileIndexing;
use App\Models\FileNumber;
use App\Models\Lga;
use App\Models\CadastralShadowFile;
use App\Services\Cadastral\CorrespondenceFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MlsFileNoMatchingController extends Controller
{
    /**
     * Display the MLS File Number Matching index.
     */
    public function index(Request $request)
    {
        $PageTitle = 'Match Existing FileNo (MLSFileNo)';
        $PageDescription = 'Manage Cadastral Shadow Files (CSF)';

        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $user = Auth::user();

        // Calculate Statistics using CadastralShadowFile
        $stats = [
            'total' => FileIndexing::where('is_corresponding_file', 1)->count(),
            'today' => CadastralShadowFile::where('is_deleted', 0)->whereDate('date_matched', date('Y-m-d'))->count(),
            'month' => CadastralShadowFile::where('is_deleted', 0)
                ->whereMonth('date_matched', date('m'))
                ->whereRaw('YEAR(date_matched) = ?', [date('Y')])
                ->count(),
        ];

        return view('mls_file_no_matching.index', compact(
            'PageTitle', 'PageDescription', 'districts', 'lgas', 'stats', 'user'
        ));
    }

    /**
     * Check if a file number exists in the system (FileNumber table).
     * Also used for preview compatibility.
     */
    public function getAvailableMls(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        if ($fileNumber === '') {
            return response()->json(['success' => true, 'data' => [], 'message' => 'Manual matching enabled']);
        }

        $exists = FileNumber::mlsfExists($fileNumber);
        return response()->json([
            'success' => true,
            'exists'  => $exists,
            'message' => $exists ? 'File number found in system.' : 'File number not found in system.',
        ]);
    }

    /**
     * Store the matched file numbers.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_file_number' => 'required|string|max:255',
            'file_title' => 'required|string|max:500',
            'plot_number' => 'nullable|string|max:255',
            'tp_no' => 'nullable|string|max:255',
            'district_id' => 'nullable|integer',
            'lga_id' => 'required|integer',
            'location' => 'required|string',
        ]);

        $fullFileNumber = $validated['full_file_number'];
        $entityName     = $validated['file_title'];

        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Select only the columns we actually use. UPDLOCK (here and on the
            // index row below) holds until commit, so a second click on the
            // same number waits for this one and then sees its shadow file.
            $existingFile = FileNumber::select('id', 'tracking_id', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no')
                ->where('mlsfNo', $fullFileNumber)
                ->lockForUpdate()
                ->first();

            if (!$existingFile) {
                throw new \Exception("Record not found in system for File Number: {$fullFileNumber}. Only existing file numbers can be matched.");
            }

            DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', $fullFileNumber)
                ->lockForUpdate()
                ->pluck('id');

            // One correspondence file per file: refuse if it already has one,
            // either a live shadow row under any of its numbers or the index
            // flag (set at indexing from corresponding_fileno; those files are
            // already in this screen's list). Same check as the Cadastral Module.
            $existing = (new CorrespondenceFiles())->existingForNumber($fullFileNumber, [
                $existingFile->kangisFileNo, $existingFile->NewKANGISFileNo, $existingFile->st_file_no,
            ]);

            if ($existing) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $existing['shadow']
                        ? "{$fullFileNumber} already has correspondence file {$existing['shadow']->ref_number} ({$existing['fileno']}); nothing was created."
                        : "{$fullFileNumber} is already marked as a corresponding file (Corresponding FileNo {$existing['fileno']}); nothing was created.",
                ], 409);
            }

            $trackingId = $existingFile->tracking_id ?? null;
            $now        = now();
            $userId     = Auth::id();

            // Fetch only the name column — avoids loading the full model
            $lgaName      = Lga::where('id', $validated['lga_id'])->value('name');
            $districtName = $validated['district_id']
                ? District::where('id', $validated['district_id'])->value('name')
                : null;

            // Reuse the already-loaded model — eliminates a second SELECT round-trip
            $existingFile->update(['csf' => '1']);

            CadastralShadowFile::create([
                'ref_number'   => 'CSF-' . $now->format('Ymd') . '-' . $now->format('His'),
                'full_number'  => $fullFileNumber,
                'file_name'    => $entityName,
                'plot_no'      => $validated['plot_number'],
                'location'     => $validated['location'],
                'lga'          => $lgaName,
                'tracking_id'  => $trackingId,
                'created_by'   => $userId,
                'date_matched' => $now->toDateString(),
                'time_matched' => $now->toTimeString(),
                'is_deleted'   => 0,
            ]);

            // Single UPDATE — OR lets SQL Server use an index union on both columns
            DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', $fullFileNumber)
                ->orWhere('st_fillno', $fullFileNumber)
                ->update([
                    'is_corresponding_file' => 1,
                    'corresponding_fileno'  => $fullFileNumber,
                ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully matched record: {$fullFileNumber}",
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('MLS Matching Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to match record: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Fetch a single record for editing.
     */
    public function edit($id)
    {
        try {
            // Updated to use CadastralShadowFile as per new logic
            $record = CadastralShadowFile::findOrFail($id);
            
            // Adapt to frontend expectation
            $data = $record->toArray();
            $data['full_file_number'] = $record->full_number;
            // tp_no is not in CadastralShadowFile, but frontend expects it. Send null or empty if needed.
            $data['tp_no'] = null; 

            // Fetch customer type from file_indexings for display
            $indexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', $record->full_number)
                ->orWhere('st_fillno', $record->full_number)
                ->first();
            
            $data['customer_type'] = $indexing->file_type ?? 'null';
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Update record metadata.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'file_name' => 'required|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'district_id' => 'nullable|integer',
            'lga_id' => 'required|integer',
            'location' => 'required|string',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Updated to use CadastralShadowFile
            $record = CadastralShadowFile::findOrFail($id);
            // $oldFileNo = $record->full_number; // CadastralShadowFile uses full_number

            $district = District::find($validated['district_id'] ?? null);
            $lga = Lga::find($validated['lga_id']);

            // Update record
            $record->update([
                'file_name' => $validated['file_name'],
                'plot_no' => $validated['plot_no'],
                // 'tp_no' => $validated['tp_no'], //   be at field does not exist in CadastralShadowFile
                'location' => $validated['location'],
                'lga' => $lga->name ?? $record->lga,
            ]);

             // Sync with fileNumber
            FileNumber::where('mlsfNo', $record->full_number)->update([
                'FileName' => $validated['file_name'],
                'plot_no' => $validated['plot_no'],
                'location' => $validated['location'],
                'lga' => $lga->name ?? null,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch complete file details for a given file number.
     */
    public function getFileDetails(Request $request)
    {
        try {
            $fileNumber = $request->query('file_number');
            if (!$fileNumber) {
                return response()->json(['success' => false, 'message' => 'File number is required'], 400);
            }

            $data = null;

            // 1. Try fileNumber (Legacy Table) — select only needed columns
            $file = DB::connection('sqlsrv')->table('fileNumber')
                ->select('FileName', 'plot_no', 'location', 'lga', 'district', 'type', 'tracking_id')
                ->where('mlsfNo', $fileNumber)
                ->first();

            if ($file) {
                $data = [
                    'title' => $file->FileName ?? null,
                    'plot_no' => $file->plot_no ?? null,
                    'location' => $file->location ?? null,
                    'lga' => $file->lga ?? null,
                    'lga_name' => $file->lga ?? null,
                    'district_name' => $file->district ?? null,
                    'type' => $file->type ?? null,
                    'tracking_id' => $file->tracking_id ?? null,
                ];
            }

            // 2. Try file_indexings — exact matches only (LIKE with leading wildcard is a full scan)
            $cleanNumber  = trim($fileNumber);
            $indexingQuery = DB::connection('sqlsrv')->table('file_indexings')
                ->select('file_title', 'plot_number', 'tp_no', 'location', 'district',
                         'lga', 'file_type', 'tracking_id')
                ->where('file_number', $cleanNumber)
                ->orWhere('st_fillno', $cleanNumber);

            if (!empty($data['tracking_id'])) {
                $indexingQuery->orWhere('tracking_id', $data['tracking_id']);
            }

            $indexing = $indexingQuery->first();

            if ($indexing) {
                $data = $data ?? [];
                $data['title'] = (!empty($data['title']) ? $data['title'] : null) ?? $indexing->file_title;
                $data['plot_no'] = (!empty($data['plot_no']) ? $data['plot_no'] : null) ?? $indexing->plot_number;
                $data['tp_no'] = (!empty($data['tp_no']) ? $data['tp_no'] : null) ?? $indexing->tp_no ?? null;
                $data['location'] = (!empty($data['location']) ? $data['location'] : null) ?? $indexing->location;
                $data['district_name'] = (!empty($data['district_name']) ? $data['district_name'] : null) ?? $indexing->district ?? null;
                $data['lga_name'] = (!empty($data['lga_name']) ? $data['lga_name'] : null) ?? $indexing->lga ?? null;
                $data['customer_type'] = (!empty($data['customer_type']) ? $data['customer_type'] : null) ?? $indexing->file_type ?? '-';
                $data['tracking_id'] = (!empty($data['tracking_id']) ? $data['tracking_id'] : null) ?? $indexing->tracking_id ?? null;
            } else if (isset($data['type'])) {
                // Fallback for customer_type if indexing not found but legacy type exists
                $data['customer_type'] = $data['type'];
            }

            // 3. Try file_indexing_links (Latest Link Table)
            $link = DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_number', $fileNumber)
                ->first();

            if ($link) {
                $data = $data ?? [];
                $data['title'] = (!empty($data['title']) ? $data['title'] : null) ?? $link->file_title;
                $data['plot_no'] = (!empty($data['plot_no']) ? $data['plot_no'] : null) ?? $link->plot_number;
                $data['tp_no'] = (!empty($data['tp_no']) ? $data['tp_no'] : null) ?? $link->tp_no ?? null;
                $data['location'] = (!empty($data['location']) ? $data['location'] : null) ?? $link->location;
                $data['tracking_id'] = (!empty($data['tracking_id']) ? $data['tracking_id'] : null) ?? $link->tracking_id ?? null;
            }



            if (!$data) {
                return response()->json(['success' => false, 'message' => 'No record found for this file number']);
            }

            // Map LGA and District names to IDs — exact match; LIKE with % is a full scan
            if (!empty($data['lga_name'])) {
                $lgaId = Lga::where('name', trim($data['lga_name']))->value('id');
                if ($lgaId) $data['lga_id'] = $lgaId;
            }

            if (!empty($data['district_name'])) {
                $districtId = District::where('name', trim($data['district_name']))->value('id');
                if ($districtId) $data['district_id'] = $districtId;
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function enrichMissingLocationFieldsFromIndexing($records, string $fileNumberField): void
    {
        foreach ($records as $record) {
            $plotMissing = $this->isMissingValue($record->plot_no ?? null);
            $lgaMissing = $this->isMissingValue($record->lga ?? null);

            if (!$plotMissing && !$lgaMissing) {
                continue;
            }

            $fileNumber = trim((string) ($record->{$fileNumberField} ?? ''));
            if ($fileNumber === '') {
                continue;
            }

            $normalizedNumber = strtoupper(str_replace(['-', '/', ' ', '.'], '', $fileNumber));

            $indexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where(function ($query) use ($fileNumber, $normalizedNumber, $record) {
                    $query->where('file_number', $fileNumber)
                        ->orWhere('st_fillno', $fileNumber)
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                            [$normalizedNumber]
                        )
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(st_fillno), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                            [$normalizedNumber]
                        );

                    if (!empty($record->tracking_id)) {
                        $query->orWhere('tracking_id', $record->tracking_id);
                    }
                })
                ->first();

            if (!$indexing) {
                continue;
            }

            if ($plotMissing && !$this->isMissingValue($indexing->plot_number ?? null)) {
                $record->plot_no = $indexing->plot_number;
            }

            if ($lgaMissing && !$this->isMissingValue($indexing->lga ?? null)) {
                $record->lga = $indexing->lga;
            }
        }
    }

    private function isMissingValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        $normalized = trim((string) $value);

        return $normalized === '' || $normalized === '-' || $normalized === '—';
    }
}
