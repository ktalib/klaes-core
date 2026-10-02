<?php

namespace App\Http\Controllers\Vfs;

use App\Http\Controllers\Controller;
use App\Models\FileIndexing;
use App\Services\Vfs\VfsFolioPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Virtual File Explorer.
 *
 * Two screens:
 *   GET /vfs                  the register — find an indexed file
 *   GET /vfs/folios/{file}    the explorer — one folio's virtual folder
 *
 * Read-only. This controller never writes; it assembles a logical view over the
 * physical EDMS through VfsFolioPresenter, which is the only thing that knows how
 * to turn stored paths into URLs.
 */
class VirtualFileExplorerController extends Controller
{
    public function __construct(private VfsFolioPresenter $presenter)
    {
    }

    /** The register. */
    public function index(Request $request)
    {
        $filters = [
            'q' => $request->query('q'),
            'registry' => $request->query('registry'),
            'state' => $request->query('state'),
            'scope' => $request->query('scope'),
        ];

        $files = $this->presenter->register($filters, 25);

        // An empty table under a narrowing scope is ambiguous: the file may not exist,
        // or it may exist and simply have no scanned pages — and "mapped" is the
        // default, covering only 5% of indexed files. Counting the same search under
        // "all" turns "not found" into "found, but hidden by this filter", which is
        // the difference between staff trusting the screen and not.
        // Only computed when the table is actually empty, so the normal path pays
        // nothing for it.
        $missedByScope = 0;

        if ($files->total() === 0 && ($filters['scope'] ?: VfsFolioPresenter::DEFAULT_SCOPE) !== 'all') {
            $missedByScope = $this->presenter->countUnderScope($filters, 'all');
        }

        return view('vfs.index', [
            'PageTitle' => 'Virtual Folder System',
            'PageDescription' => 'Find an indexed file, then open its virtual folder',
            'files' => $files,
            'missedByScope' => $missedByScope,
            'stats' => $this->presenter->registerStats(),
            'registries' => $this->presenter->registries(),
            'filters' => $filters,
        ]);
    }

    /** The explorer for one folio. */
    public function show(Request $request, FileIndexing $fileIndexing)
    {
        $folder = $request->query('folder');
        $folders = $this->presenter->folders($fileIndexing);

        // Land in the first folder that has something in it, so the grid is never
        // empty on arrival just because the first page type happens to be missing.
        if ($folder === null && !empty($folders)) {
            $folder = $folders[0]['key'];
        }

        return view('vfs.show', [
            'PageTitle' => 'Virtual Folder System',
            'PageDescription' => $fileIndexing->file_number,
            'file' => $fileIndexing,
            'summary' => $this->presenter->summary($fileIndexing),
            'status' => $this->presenter->status($fileIndexing),
            'folders' => $folders,
            'activeFolder' => $folder,
            'documents' => $this->presenter->documents($fileIndexing, $folder),
            'section' => $request->query('section', 'documents'),
            'lineage' => $this->presenter->lineage($fileIndexing),
            'timeline' => $this->presenter->statusTimeline($fileIndexing),
            'sources' => $this->presenter->physicalSources($fileIndexing),
            // The existing archive viewer, reused unchanged for full-page reading.
            // Scoped to the folder being browsed, so the viewer's page list matches
            // the folder the document was opened from rather than the whole folio.
            'pagesUrl' => route('filearchive.document-pages', $fileIndexing->id)
                . ($folder ? '?page_type=' . urlencode($folder) : ''),
        ]);
    }

    /** Paginated tiles for one virtual folder. */
    public function documents(Request $request, FileIndexing $fileIndexing): JsonResponse
    {
        $documents = $this->presenter->documents(
            $fileIndexing,
            $request->query('folder'),
            max((int) $request->query('page', 1), 1),
            (int) $request->query('per_page', VfsFolioPresenter::PER_PAGE)
        );

        return response()->json([
            'success' => true,
            'folder' => $request->query('folder'),
            'documents' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function lineage(FileIndexing $fileIndexing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'lineage' => $this->presenter->lineage($fileIndexing),
        ]);
    }

    public function statusTimeline(FileIndexing $fileIndexing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'timeline' => $this->presenter->statusTimeline($fileIndexing),
        ]);
    }

    public function physicalSources(FileIndexing $fileIndexing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'sources' => $this->presenter->physicalSources($fileIndexing),
        ]);
    }
}
