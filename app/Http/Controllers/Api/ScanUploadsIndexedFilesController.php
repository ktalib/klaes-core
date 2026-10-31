<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileIndexing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanUploadsIndexedFilesController extends Controller
{
    /**
     * Provide a lightweight list of indexed files for the Scan Uploads modal.
     */
    public function quick(Request $request)
    {
        $limit = 20;

        $searchTerm = trim((string) $request->input('search', ''));

        try {
            $query = FileIndexing::on('sqlsrv')
                ->select(['id', 'file_number', 'file_title', 'land_use_type', 'district', 'edms_file_type'])
                ->withCount('scannings')
                ->where(function ($builder) {
                    $builder->where('is_deleted', 0)
                        ->orWhereNull('is_deleted');
                })
                ->whereNotNull('file_number')
                ->where('file_number', '<>', '');

            if ($searchTerm !== '') {
                $query->where('file_number', 'LIKE', '%' . $this->escapeLike($searchTerm) . '%');
            }

            $records = $query
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $indexedFiles = $records->map(function (FileIndexing $file) {
                return [
                    'id' => $file->id,
                    'fileNumber' => $file->file_number,
                    'fileTitle' => $file->file_title,
                    'name' => $file->file_title,
                    'landUseType' => $file->land_use_type,
                    'district' => $file->district,
                    // Lets the Scan Upload dialog preselect the master folder the
                    // file is already filed under, so a second batch of scans lands
                    // beside the first instead of starting a new folder.
                    'edmsFileType' => \App\Services\Edms\EdmsFileType::normalize($file->edms_file_type),
                    'has_scans' => $file->scannings_count > 0,
                    'scans_count' => $file->scannings_count,
                ];
            })->values()->all();

            return response()->json([
                'success' => true,
                'indexed_files' => $indexedFiles,
                'metadata' => [
                    'limit' => $limit,
                    'count' => count($indexedFiles),
                    'search' => $searchTerm,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('ScanUploadsIndexedFilesController::quick failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load indexed files.',
            ], 500);
        }
    }

    /**
     * Scan Upload > Survey Registry (user, 2026-10-30): the file is picked in the global file number
     * selector (Survey: GKN / CO), because CO files are not in file_indexings. Returns the file's
     * indexing row in the dialog's shape; a Survey file that has none is indexed now, from its CO file
     * (survey_co_files) or its fileNumber row, so the scans have a file to hang on, as the GKN
     * generator indexes the numbers it commissions.
     */
    public function survey(Request $request)
    {
        $data = $request->validate(['file_number' => 'required|string|max:100']);
        $number = trim(preg_replace('/\s+/', ' ', $data['file_number']));
        // CO numbers written the paper way (CO/09/2018) are the same file as CO-9-2018.
        if (preg_match('/^\(?\s*CO\b/i', $number)) $number = \App\Models\Survey\SurveyCoFile::normaliseNumber($number) ?? $number;

        // file_number is unique across deleted rows too, so look at every row for the number.
        $existing = FileIndexing::on('sqlsrv')->withCount('scannings')->where('file_number', $number)->orderBy('id')->first();
        if ($existing && (int) $existing->is_deleted === 1) {
            return response()->json(['success' => false, 'message' => "{$number} was removed from File Indexing, so scans cannot be added to it here."], 409);
        }
        if ($existing) {
            return response()->json(['success' => true, 'created' => false, 'file' => $this->shape($existing)]);
        }

        $row = null;
        $co = \App\Models\Survey\SurveyCoFile::where('co_file_no', \App\Models\Survey\SurveyCoFile::normaliseNumber($number) ?? $number)->first();
        if ($co) {
            $row = [
                'file_number'  => $co->co_file_no,
                'file_title'   => $co->title ?: $co->co_file_no,
                'plot_number'  => $co->prop_plot,
                'district'     => \App\Support\AddressBuilder::resolve($co->prop_district, $co->prop_district_other),
                'lga'          => \App\Support\AddressBuilder::resolve($co->prop_lga),
                // Location is district + LGA (+ state), never the plot.
                'location'     => \App\Support\AddressBuilder::join([
                    \App\Support\AddressBuilder::resolve($co->prop_district, $co->prop_district_other),
                    \App\Support\AddressBuilder::resolve($co->prop_lga), $co->prop_state,
                ]) ?: null,
                'source'       => 'Survey CO File (Scan Upload)',
            ];
        } elseif (\App\Models\FileIndexing::detectRegistryFromFileNumber($number) === 'Survey Registry') {
            $fn = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('fileNumber')
                ->where(fn ($q) => $q->where('mlsfNo', $number)->orWhere('gkn_fileno', $number))->orderBy('id')->first();
            if ($fn) {
                $row = [
                    'file_number' => $number,
                    'file_title'  => $fn->FileName ?: $number,
                    'plot_number' => $fn->plot_no,
                    'lga'         => $fn->lga,
                    'location'    => $fn->location,
                    'tracking_id' => $fn->tracking_id,
                    'source'      => 'Survey File (Scan Upload)',
                ];
            }
        }

        if (! $row) {
            return response()->json(['success' => false,
                'message' => "{$number} is not a Survey file KLAES knows (no CO file and no GKN file number record)."], 404);
        }

        // forceCreate: every value is built here, and 'source' is not in the model's fillable list (create() drops it).
        $file = FileIndexing::on('sqlsrv')->forceCreate($row + [
            'registry'        => 'Survey',
            'workflow_status' => 'indexed',
            // Required column: the user's name, else email, else id (users often have no `name`).
            'created_by'      => ($u = $request->user()) ? (trim($u->name ?: ($u->first_name . ' ' . $u->last_name)) ?: ($u->email ?: (string) $u->id)) : 'System',
        ]);
        $file->loadCount('scannings');
        Log::info('Scan Upload indexed a Survey file on first use', ['file_number' => $file->file_number, 'id' => $file->id, 'source' => $row['source']]);

        return response()->json(['success' => true, 'created' => true, 'file' => $this->shape($file)]);
    }

    /** One file as the Scan Upload dialog lists it (same shape as quick()). */
    private function shape(FileIndexing $file): array
    {
        return [
            'id' => $file->id,
            'fileNumber' => $file->file_number,
            'fileTitle' => $file->file_title,
            'name' => $file->file_title,
            'landUseType' => $file->land_use_type,
            'district' => $file->district,
            'edmsFileType' => \App\Services\Edms\EdmsFileType::normalize($file->edms_file_type),
            'has_scans' => $file->scannings_count > 0,
            'scans_count' => $file->scannings_count,
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_[');
    }
}
