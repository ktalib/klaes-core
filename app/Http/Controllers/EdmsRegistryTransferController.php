<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\EdmsRegistryTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Move a file's documents between registries.
 *
 * Shared by the Page Typing workspace and the File Archive (Doc-WARE) grid —
 * both hit the same three endpoints, so the two interfaces cannot drift apart.
 */
class EdmsRegistryTransferController extends Controller
{
    /** @var EdmsRegistryTransferService */
    private $transfers;

    /** @var EdmsDocumentPathResolver */
    private $paths;

    public function __construct(EdmsRegistryTransferService $transfers, EdmsDocumentPathResolver $paths)
    {
        $this->transfers = $transfers;
        $this->paths = $paths;
    }

    /**
     * Registries a file can be moved to.
     */
    public function options(Request $request)
    {
        $fileIndexingId = $request->query('file_indexing_id');
        $current = null;

        if ($fileIndexingId) {
            $file = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            $current = $file ? $this->paths->registryName($file->registry) : null;
        }

        $registries = collect($this->transfers->availableRegistries())
            ->map(function ($slug, $name) use ($current) {
                return [
                    'name' => $name,
                    'slug' => $slug,
                    'is_current' => $name === $current,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'current_registry' => $current,
                'registries' => $registries,
            ],
        ]);
    }

    /**
     * Find a file to move.
     *
     * The picker used elsewhere (ScanUploadsController::searchFileNumbers) does not
     * return the indexing id, which is what a transfer is keyed on — hence its own
     * lookup here, carrying the document counts so the operator can tell at a
     * glance whether a file has anything to move.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:5|max:50',
        ]);

        $search = trim($validated['search'] ?? '');
        $limit = $validated['limit'] ?? 20;

        $query = FileIndexing::on('sqlsrv')
            ->select('id', 'file_number', 'file_title', 'registry')
            ->withCount(['scannings', 'pagetypings']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%');
            });
        }

        // File numbers first — see EdmsFileTypeTransferController::search(). Plain
        // orderBy('file_number') sorts NULL and '' ahead of everything on SQL Server.
        $files = $query
            ->orderByRaw("CASE WHEN file_number IS NULL OR LTRIM(RTRIM(file_number)) = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('file_number')
            ->orderBy('file_title')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $files->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_number' => $file->file_number,
                    'file_title' => $file->file_title ?? '',
                    'registry' => $this->paths->registryName($file->registry),
                    'scannings_count' => (int) $file->scannings_count,
                    'pagetypings_count' => (int) $file->pagetypings_count,
                ];
            })->values(),
        ]);
    }

    /**
     * Show exactly what a move would do, without touching anything.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
            'target_registry' => 'required|string|max:100',
        ]);

        try {
            $file = FileIndexing::on('sqlsrv')->findOrFail($validated['file_indexing_id']);

            return response()->json([
                'success' => true,
                'data' => $this->transfers->preview($file, $validated['target_registry']),
            ]);
        } catch (Throwable $e) {
            Log::error('Registry transfer preview failed', [
                'file_indexing_id' => $validated['file_indexing_id'],
                'target_registry' => $validated['target_registry'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to preview the registry move: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Perform the move.
     */
    public function move(Request $request)
    {
        $validated = $request->validate([
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
            'target_registry' => 'required|string|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $file = FileIndexing::on('sqlsrv')->findOrFail($validated['file_indexing_id']);

            $result = $this->transfers->transfer(
                $file,
                $validated['target_registry'],
                $validated['reason'] ?? null
            );

            $message = sprintf(
                '%s moved to %s — %d file(s) relocated, %d scan(s) and %d typed page(s) re-linked.',
                $result['file_number'],
                $result['to_registry'],
                $result['moved']['files'],
                $result['moved']['scannings'],
                $result['moved']['pagetypings']
            );

            if ($result['counts']['missing_on_disk'] > 0) {
                $message .= sprintf(' (%d document(s) were not on disk; their records were still re-pointed.)', $result['counts']['missing_on_disk']);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            // Blocked, not broken — surface the reason verbatim.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Registry transfer failed', [
                'file_indexing_id' => $validated['file_indexing_id'],
                'target_registry' => $validated['target_registry'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to move the file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
