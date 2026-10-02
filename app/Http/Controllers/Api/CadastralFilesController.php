<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CadastralFilesController extends Controller
{
    /**
     * Base folder (relative to the "public" storage disk) that holds the
     * scanned cadastral registry documents, one sub-folder per file number.
     */
    private const BASE_DIR = 'EDMS/UPLOAD/Cadastral_Registry1';

    /**
     * List the files stored in the cadastral registry folder for a given
     * file number. Replaces the old http://10.50.1.2:7000 folder browser.
     *
     * The scanned documents are stored in a folder named after the
     * "corresponding_fileno" recorded in the file_indexings table (the
     * physical registry file a document belongs to), so we resolve the
     * incoming file number to its corresponding_fileno before looking on disk.
     */
    public function index(Request $request)
    {
        $requested = (string) $request->query('folder_name', '');

        // Resolve the matching-table file number to the registry folder name
        // (file_indexings.corresponding_fileno). Falls back to the raw value.
        $folderName = $this->resolveCorrespondingFileNo($requested);

        // Guard against path traversal: keep only the last path segment and
        // strip anything that could escape the base directory.
        $folderName = str_replace(['\\', '/'], '-', trim($folderName));
        $folderName = preg_replace('/\.\.+/', '', $folderName);
        $folderName = trim($folderName);

        if ($folderName === '') {
            return response()->json([
                'success' => false,
                'message' => 'A file number (folder name) is required.',
                'files'   => [],
            ], 422);
        }

        $disk    = Storage::disk('public');
        $dirPath = self::BASE_DIR . '/' . $folderName;

        if (! $disk->exists($dirPath)) {
            return response()->json([
                'success'     => false,
                'message'     => "No folder found for \"{$folderName}\".",
                'folder_name' => $folderName,
                'files'       => [],
            ], 404);
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tif', 'tiff'];

        $files = collect($disk->files($dirPath))
            ->map(function ($path) use ($disk, $imageExtensions) {
                $name = basename($path);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                return [
                    'name'     => $name,
                    'url'      => asset('storage/' . $path),
                    'ext'      => $ext,
                    'is_image' => in_array($ext, $imageExtensions, true),
                    'is_pdf'   => $ext === 'pdf',
                    'size'     => $disk->size($path),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'success'     => true,
            'folder_name' => $folderName,
            'requested'   => $requested,
            'count'       => $files->count(),
            'files'       => $files,
        ]);
    }

    /**
     * Resolve a matching-table file number (e.g. FileNumber.mlsfNo) to the
     * registry folder name stored in file_indexings.corresponding_fileno,
     * matching on file_number / st_fillno like the matching controller does.
     * Returns the original value when no corresponding file number is found.
     */
    private function resolveCorrespondingFileNo(string $fileNumber): string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return $fileNumber;
        }

        $normalized = strtoupper(str_replace(['-', '/', ' ', '.'], '', $fileNumber));

        try {
            $corresponding = DB::connection('sqlsrv')->table('file_indexings')
                ->where('is_corresponding_file', 1)
                ->whereNotNull('corresponding_fileno')
                ->where('corresponding_fileno', '<>', '')
                ->where(function ($query) use ($fileNumber, $normalized) {
                    $query->where('file_number', $fileNumber)
                        ->orWhere('st_fillno', $fileNumber)
                        ->orWhere('corresponding_fileno', $fileNumber)
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                            [$normalized]
                        )
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(st_fillno), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                            [$normalized]
                        )
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(corresponding_fileno), '-', ''), '/', ''), ' ', ''), '.', '') = ?",
                            [$normalized]
                        );
                })
                ->value('corresponding_fileno');
        } catch (\Throwable $e) {
            $corresponding = null;
        }

        return $corresponding !== null && trim((string) $corresponding) !== ''
            ? trim((string) $corresponding)
            : $fileNumber;
    }
}
