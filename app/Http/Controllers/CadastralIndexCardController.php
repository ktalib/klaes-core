<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CadastralIndexCardController extends Controller
{
    private const REGISTRY_PATH = 'EDMS/UPLOAD/Cadastral_Index_card';
    private const ALLOWED_EXTS  = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'pdf'];

    public function index()
    { 
        return view('cadastral.index_cards', [
            'PageTitle' => 'Cadastral Index Cards',
        ]);
    }

    /** Returns the list of folders (one card per folder). Cached for 5 min. */
    public function data(Request $request)
    {
        try {
            $search = strtolower(trim($request->input('search', '')));
            $disk   = Storage::disk('public');

            if (!$disk->exists(self::REGISTRY_PATH)) {
                return response()->json(['success' => true, 'data' => [], 'total' => 0]);
            }

            // Build (and cache) the full folder index once — avoids N allFiles() calls per request
            $index = Cache::remember('cadastral_index_card_folders', 300, function () use ($disk) {
                $folders = collect();

                // Root-level files → virtual "__root__" folder
                $rootFiles = collect($disk->files(self::REGISTRY_PATH))
                    ->filter(fn($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::ALLOWED_EXTS, true));

                if ($rootFiles->isNotEmpty()) {
                    $first    = $rootFiles->first();
                    $firstExt = strtolower(pathinfo($first, PATHINFO_EXTENSION));
                    $folders->push([
                        'folder'       => '__root__',
                        'display_name' => 'Uncategorized',
                        'file_count'   => $rootFiles->count(),
                        'thumbnail'    => $firstExt !== 'pdf' ? asset('storage/' . $first) : null,
                        'is_pdf_first' => $firstExt === 'pdf',
                        'modified_at'  => date('d M Y', filemtime($disk->path($first))),
                        'sort_ts'      => filemtime($disk->path($first)),
                    ]);
                }

                foreach ($disk->directories(self::REGISTRY_PATH) as $dir) {
                    $name   = basename($dir);
                    $absDir = $disk->path($dir);

                    // allFiles() per folder is the expensive call — done once and cached
                    $files = collect($disk->allFiles($dir))
                        ->filter(fn($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::ALLOWED_EXTS, true));

                    $count    = $files->count();
                    $first    = $files->first();
                    $firstExt = $first ? strtolower(pathinfo($first, PATHINFO_EXTENSION)) : null;
                    $ts       = is_dir($absDir) ? (int) filemtime($absDir) : 0;

                    $folders->push([
                        'folder'       => $name,
                        'display_name' => $name,
                        'file_count'   => $count,
                        'thumbnail'    => ($first && $firstExt !== 'pdf') ? asset('storage/' . $first) : null,
                        'is_pdf_first' => $firstExt === 'pdf',
                        'modified_at'  => $ts ? date('d M Y', $ts) : null,
                        'sort_ts'      => $ts,
                    ]);
                }

                return $folders->sortByDesc('sort_ts')->values()->all();
            });

            $result = collect($index);

            // Apply search after cache retrieval — cheap string filter
            if ($search !== '') {
                $result = $result->filter(
                    fn($f) => str_contains(strtolower($f['display_name']), $search)
                        || ($f['folder'] === '__root__' && str_contains('uncategorized', $search))
                )->values();
            }

            // Strip internal sort_ts before sending to client
            $result = $result->map(fn($f) => array_diff_key($f, ['sort_ts' => true]))->values();

            return response()->json([
                'success' => true,
                'data'    => $result,
                'total'   => $result->count(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** Returns paginated files inside a specific folder. Files list cached 5 min. */
    public function folderFiles(Request $request)
    {
        try {
            $folder  = $request->input('folder', '');
            $perPage = min(max((int) $request->input('per_page', 24), 6), 120);
            $page    = max((int) $request->input('page', 1), 1);
            $search  = strtolower(trim($request->input('search', '')));

            // Security: prevent path traversal
            if (str_contains($folder, '..') || preg_match('#[/\\\\]#', $folder)) {
                return response()->json(['success' => false, 'message' => 'Invalid folder name.'], 400);
            }

            $disk    = Storage::disk('public');
            $dirPath = $folder === '__root__'
                ? self::REGISTRY_PATH
                : self::REGISTRY_PATH . '/' . $folder;

            if (!$disk->exists($dirPath)) {
                return response()->json(['success' => false, 'message' => 'Folder not found.'], 404);
            }

            // Cache the sorted file list for this folder — avoids allFiles() on every page turn
            $cacheKey = 'cadastral_index_card_folder_' . md5($folder);
            $allPaths = Cache::remember($cacheKey, 300, function () use ($disk, $dirPath, $folder) {
                $method = $folder === '__root__' ? 'files' : 'allFiles';
                return collect($disk->{$method}($dirPath))
                    ->filter(fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), self::ALLOWED_EXTS, true))
                    ->sortBy(fn($p) => strtolower(basename($p)))
                    ->values()
                    ->all();
            });

            $allPaths = collect($allPaths);

            // Apply search after cache — cheap
            if ($search !== '') {
                $allPaths = $allPaths->filter(
                    fn($p) => str_contains(strtolower(basename($p)), $search)
                )->values();
            }

            $total  = $allPaths->count();
            $sliced = $allPaths->forPage($page, $perPage)->map(function ($path) use ($disk) {
                $filename = basename($path);
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $absPath  = $disk->path($path);

                return [
                    'id'                => md5($path),
                    'original_filename' => $filename,
                    'document_type'     => strtoupper($ext),
                    'file_size'         => is_file($absPath) ? filesize($absPath) : null,
                    'is_pdf'            => $ext === 'pdf',
                    'url'               => asset('storage/' . $path),
                    'created_at'        => is_file($absPath) ? date('d M Y', filemtime($absPath)) : null,
                ];
            })->values();

            $paginator = new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => $request->url()]);

            return response()->json([
                'success' => true,
                'data'    => $sliced,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'from'         => $paginator->firstItem() ?? 0,
                    'to'           => $paginator->lastItem()  ?? 0,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
