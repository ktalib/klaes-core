<?php

namespace App\Http\Controllers;
use App\Models\District;
use App\Models\FileNumber;
use App\Models\Lga;
use App\Models\LandsShadowFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandsFileNoMatchingController extends Controller
{
    /**
     * Display the Lands File Number Matching index.
     * Updated for new Shadow File Commissioning (matching-only) architecture.
     */
    public function index(Request $request)
    {
        $PageTitle = 'Match Existing FileNo (Lands)';
        $PageDescription = 'Manage Lands Shadow Files (LSF)';

        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $user = Auth::user();
        $search = $request->query('search');

        // Matched records now come from TWO sources:
        //  1. file_indexings (pp_lands_matching = 1) -> Source: "Indexed"
        //  2. fileNumber (pp_lands_matching = 1) that did NOT originate from an
        //     indexed file -> Source: "Matched"
        // We build a UNION of both so the list reflects every matched Land file.

        // Indexed files that have been matched to a Physical Planning file.
        $indexedSource = DB::connection('sqlsrv')->table('file_indexings')
            ->where('pp_lands_matching', 1)
            ->select([
                'id',
                'file_number as file_no',
                'file_title as title',
                'plot_number as plot_no',
                'location',
                'lga',
                'pp_lands_date_matched as date_matched',
                'pp_lands_time_matched as time_matched',
                DB::raw("'Indexed' as source"),
            ]);

        // Directly matched fileNumber records that were NOT sourced from indexing
        // (an indexed match mirrors onto fileNumber, so exclude those to avoid dupes).
        $matchedSource = DB::connection('sqlsrv')->table('fileNumber')
            ->where('pp_lands_matching', 1)
            ->whereNotIn('mlsfNo', function ($q) {
                $q->select('file_number')
                  ->from('file_indexings')
                  ->where('pp_lands_matching', 1)
                  ->whereNotNull('file_number');
            })
            ->select([
                'id',
                'mlsfNo as file_no',
                'FileName as title',
                'plot_no',
                'location',
                'lga',
                'pp_lands_date_matched as date_matched',
                'pp_lands_time_matched as time_matched',
                DB::raw("'Matched' as source"),
            ]);

        $union = $indexedSource->unionAll($matchedSource);

        $query = DB::connection('sqlsrv')->query()->fromSub($union, 'matched');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_no', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        $records = $query->orderByDesc('date_matched')
            ->orderByDesc('time_matched')
            ->paginate(20)
            ->appends(['search' => $search]);

        $this->enrichMissingLocationFieldsFromIndexing($records, 'file_no');

        // Calculate Statistics across both sources.
        $indexedStats = fn () => DB::connection('sqlsrv')->table('file_indexings')
            ->where('pp_lands_matching', 1);
        $matchedStats = fn () => DB::connection('sqlsrv')->table('fileNumber')
            ->where('pp_lands_matching', 1)
            ->whereNotIn('mlsfNo', function ($q) {
                $q->select('file_number')
                  ->from('file_indexings')
                  ->where('pp_lands_matching', 1)
                  ->whereNotNull('file_number');
            });

        $stats = [
            'total' => $indexedStats()->count() + $matchedStats()->count(),
            'today' => $indexedStats()->whereDate('pp_lands_date_matched', date('Y-m-d'))->count()
                + $matchedStats()->whereDate('pp_lands_date_matched', date('Y-m-d'))->count(),
            'month' => $indexedStats()->whereNotNull('pp_lands_date_matched')
                    ->whereMonth('pp_lands_date_matched', date('m'))
                    ->whereRaw('YEAR(pp_lands_date_matched) = ?', [date('Y')])->count()
                + $matchedStats()->whereNotNull('pp_lands_date_matched')
                    ->whereMonth('pp_lands_date_matched', date('m'))
                    ->whereRaw('YEAR(pp_lands_date_matched) = ?', [date('Y')])->count(),
        ];

        return view('lands_file_no_matching.index', compact(
            'PageTitle', 'PageDescription', 'records', 'districts', 'lgas', 'stats', 'search', 'user'
        ));
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
            'lga_id' => 'nullable|integer',
            'location' => 'required|string',
        ]);

        $fullFileNumber = $validated['full_file_number'];
        $entityName = $validated['file_title'];

        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Find the record in FileNumber (Record MUST exist in system to be matched)
            $existingFile = FileNumber::where('mlsfNo', $fullFileNumber)->first();

            if (!$existingFile) {
                 throw new \Exception("Record not found in system for File Number: {$fullFileNumber}. Only existing file numbers can be matched.");
            }

            if ($existingFile->pp_lands_matching == 1) {
                throw new \Exception("File Number: {$fullFileNumber} has already been matched in the Lands module.");
            }

            $trackingId = $existingFile->tracking_id ?? null;

            $now = now();
            $userId = Auth::id();
            
            // Fetch names for district and lga
            $lga = Lga::find($validated['lga_id']);
            $currentLgaName = $lga->name ?? null;

            // Update fileNumber table with matching timestamps (new matching-only architecture)
            FileNumber::where('mlsfNo', $fullFileNumber)->update([
                'pp_lands_matching' => 1,
                'pp_lands_date_matched' => $now->toDateString(),
                'pp_lands_time_matched' => $now->toTimeString()
            ]);

            // NOTE: Under the new Shadow File Commissioning architecture,
            // we do NOT create LandsShadowFile records anymore.
            // Only fileNumber table is updated with match timestamps.

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully matched record: {$fullFileNumber}",
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Lands Matching Failed: ' . $e->getMessage());
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
            $record = LandsShadowFile::findOrFail($id);
            
            // Adapt to frontend expectation
            $data = $record->toArray();
            $data['full_file_number'] = $record->full_number;
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
            'lga_id' => 'nullable|integer',
            'location' => 'required|string',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $record = LandsShadowFile::findOrFail($id);

            $lga = Lga::find($validated['lga_id']);

            // Update record
            $record->update([
                'file_name' => $validated['file_name'],
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

            // 1. Try fileNumber (Legacy Table) - Often contains most location info
            $file = DB::connection('sqlsrv')->table('fileNumber')
                ->where('mlsfNo', $fileNumber)
                ->first();

            if ($file) {
                $data = [
                    'title' => $file->FileName ?? null,
                    'plot_no' => $file->plot_no ?? null,
                    'location' => $file->location ?? null,
                    'lga' => $file->lga ?? null,
                    'type' => $file->type ?? null,
                    'tracking_id' => $file->tracking_id ?? null,
                ];
            }

            // 2. Try file_indexings (Dedicated Indexing Table)
            $cleanNumber = trim($fileNumber);
            $normalizedNumber = strtoupper(str_replace(['-', '/', ' ', '.'], '', $cleanNumber));

            $indexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where(function($query) use ($cleanNumber, $normalizedNumber, $data) {
                    $query->where('file_number', $cleanNumber)
                          ->orWhere('st_fillno', $cleanNumber)
                          ->orWhereRaw(
                              "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                              [$normalizedNumber]
                          )
                          ->orWhereRaw(
                              "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(st_fillno), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                              [$normalizedNumber]
                          );

                    if (!empty($data['tracking_id'])) {
                        $query->orWhere('tracking_id', $data['tracking_id']);
                    }
                })
                ->first();

            if ($indexing) {
                $data = array_merge($data ?? [], [
                    'title' => $data['title'] ?? $indexing->file_title,
                    'plot_no' => $data['plot_no'] ?? $indexing->plot_number,
                    'tp_no' => $indexing->tp_no ?? null,
                    'location' => $data['location'] ?? $indexing->location,
                    'district_name' => $indexing->district ?? null,
                    'lga_name' => $indexing->lga ?? null,
                    'customer_type' => $indexing->file_type ?? '-',
                    'tracking_id' => $data['tracking_id'] ?? $indexing->tracking_id ?? null,
                ]);
            }

            if (!$data) {
                return response()->json(['success' => false, 'message' => 'No record found for this file number']);
            }

            // Map LGA and District names to IDs if possible
            if (!empty($data['lga_name'])) {
                $lga = Lga::where('name', 'LIKE', '%' . trim($data['lga_name']) . '%')->first();
                if ($lga) $data['lga_id'] = $lga->id;
            }

            if (!empty($data['district_name'])) {
                $district = District::where('name', 'LIKE', '%' . trim($data['district_name']) . '%')->first();
                if ($district) $data['district_id'] = $district->id;
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
