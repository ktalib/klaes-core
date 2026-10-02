<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\EdmsFileType;
use App\Services\Edms\EdmsFileTypeTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * File a document set into one of the EDMS master folders.
 *
 * Shared by Scan Upload, the Page Typing workspace and the File Archive
 * (Doc-WARE) grid — all three hit the same endpoints, so a file typed in one
 * place is filed the same way in the others.
 *
 * Mirrors EdmsRegistryTransferController deliberately: same shape, same
 * options/preview/move contract, one modal component each.
 */
class EdmsFileTypeTransferController extends Controller
{
    /** @var EdmsFileTypeTransferService */
    private $transfers;

    /** @var EdmsDocumentPathResolver */
    private $paths;

    public function __construct(EdmsFileTypeTransferService $transfers, EdmsDocumentPathResolver $paths)
    {
        $this->transfers = $transfers;
        $this->paths = $paths;
    }

    /**
     * The master folders a file can be filed into, plus where it sits now.
     */
    public function options(Request $request)
    {
        $fileIndexingId = $request->query('file_indexing_id');

        $current = null;
        $currentLabel = null;
        $registry = null;

        if ($fileIndexingId) {
            $file = FileIndexing::on('sqlsrv')->find($fileIndexingId);

            if ($file) {
                $current = EdmsFileType::normalize($file->edms_file_type);
                $currentLabel = EdmsFileType::label($current) ?? 'Unclassified';
                $registry = $this->paths->registryName($file->registry);
            }
        }

        $types = collect($this->transfers->availableFileTypes())
            ->map(function ($type) use ($current) {
                return $type + ['is_current' => $type['key'] === $current];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'current_file_type' => $current,
                'current_file_type_label' => $currentLabel,
                'current_registry' => $registry,
                'file_types' => $types,
                // The same catalogue shaped for the Category -> Type -> Old/New
                // cascade the dialog picks with. `file_types` stays for the flat
                // lookups (labels, folders, is_current).
                'file_type_tree' => EdmsFileType::tree(),
            ],
        ]);
    }

    /**
     * Find a file to file away.
     *
     * Carries the document counts and the current master folder so the operator
     * can tell at a glance whether a file has anything to move and whether it has
     * been classified already.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:5|max:50',
            'unclassified_only' => 'nullable|boolean',
        ]);

        $search = trim($validated['search'] ?? '');
        $limit = $validated['limit'] ?? 20;

        $query = FileIndexing::on('sqlsrv')
            ->select('id', 'file_number', 'file_title', 'registry', 'edms_file_type')
            ->withCount(['scannings', 'pagetypings']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%');
            });
        }

        // The backlog view: everything still sitting naked under its registry.
        if (!empty($validated['unclassified_only'])) {
            $query->whereNull('edms_file_type');
        }

        // File numbers first. Plain orderBy('file_number') sorts NULL and '' ahead
        // of everything on SQL Server, so a list of numbered files was being pushed
        // off the bottom by title-only records the operator cannot identify anyway.
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
                    'file_type' => EdmsFileType::normalize($file->edms_file_type),
                    'file_type_label' => EdmsFileType::label($file->edms_file_type) ?? 'Unclassified',
                    'scannings_count' => (int) $file->scannings_count,
                    'pagetypings_count' => (int) $file->pagetypings_count,
                ];
            })->values(),
        ]);
    }

    /**
     * The file's cover page, as a URL the browser can show.
     *
     * The instruction that decides a file's type ("subdivision — mother",
     * "extension") is written on the cover, so the operator needs to see it while
     * choosing. The cover is the first page: display_order, then id, and the
     * first one that is actually on disk wins — an unresolvable path would just
     * render a broken image.
     *
     * Sorting stops at display_order and id on purpose. `definition` looks like a
     * page ordinal but is nvarchar and holds file numbers on some rows, so
     * ordering by it numerically fails outright on SQL Server.
     */
    public function cover(Request $request)
    {
        $validated = $request->validate([
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
        ]);

        $file = FileIndexing::on('sqlsrv')->find($validated['file_indexing_id']);

        $scans = Scanning::on('sqlsrv')
            ->where('file_indexing_id', $validated['file_indexing_id'])
            ->orderByRaw('CASE WHEN display_order IS NULL THEN 1 ELSE 0 END ASC, display_order ASC')
            ->orderBy('id')
            ->get();

        $pages = $scans->map(function ($scan) use ($file) {
            $url = $this->paths->resolveUrl(
                $scan->document_path,
                $this->paths->contextFromScanning($scan, $file)
            );

            if (!$url) {
                return null;
            }

            return [
                'url' => $url,
                'scanning_id' => $scan->id,
                'filename' => $scan->original_filename,
                'is_pdf' => (bool) preg_match('/\.pdf$/i', (string) $scan->document_path),
            ];
        })->filter()->values();

        if ($pages->isNotEmpty()) {
            $firstPage = $pages->first();

            return response()->json([
                'success' => true,
                'data' => $firstPage + [
                    'pages' => $pages,
                    'total_scans' => $scans->count(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => null,
                'message' => $scans->isEmpty()
                    ? 'Nothing has been scanned for this file yet.'
                    : 'The scanned pages for this file could not be found on disk.',
            ],
        ]);
    }

    /**
     * Show exactly what filing this file would do, without touching anything.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
            'target_file_type' => 'nullable|string|' . EdmsFileType::validationRule(),
        ]);

        try {
            $file = FileIndexing::on('sqlsrv')->findOrFail($validated['file_indexing_id']);

            return response()->json([
                'success' => true,
                'data' => $this->transfers->preview($file, $validated['target_file_type'] ?? null),
            ]);
        } catch (Throwable $e) {
            Log::error('EDMS file-type preview failed', [
                'file_indexing_id' => $validated['file_indexing_id'],
                'target_file_type' => $validated['target_file_type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to preview the move: ' . $e->getMessage(),
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
            'target_file_type' => 'nullable|string|' . EdmsFileType::validationRule(),
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $file = FileIndexing::on('sqlsrv')->findOrFail($validated['file_indexing_id']);

            $result = $this->transfers->transfer(
                $file,
                $validated['target_file_type'] ?? null,
                $validated['reason'] ?? null
            );

            $message = sprintf(
                '%s filed under %s — %d file(s) relocated, %d scan(s) and %d typed page(s) re-linked.',
                $result['file_number'],
                $result['to_file_type_label'],
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
            Log::error('EDMS file-type move failed', [
                'file_indexing_id' => $validated['file_indexing_id'],
                'target_file_type' => $validated['target_file_type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to move the file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
