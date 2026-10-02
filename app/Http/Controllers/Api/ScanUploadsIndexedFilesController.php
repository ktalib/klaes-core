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

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_[');
    }
}
