<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only view over indexing_duplicates — indexed files that were found to be
 * duplicates and removed from the live tables by "Move to Indexing Duplicates".
 *
 * The records here no longer exist anywhere else, so this page is the only way to
 * see them. Each row's `snapshot` holds the full JSON of every deleted row, which
 * the details view renders per source table.
 */
class IndexingDuplicateController extends Controller
{
    public function index()
    {
        $PageTitle = 'Indexing Duplicates';
        $PageDescription = 'Indexed files removed from the live tables as duplicates.';

        return view('indexing_duplicates.index', compact('PageTitle', 'PageDescription'));
    }

    public function stats(): JsonResponse
    {
        $conn = DB::connection('sqlsrv');

        return response()->json([
            'total' => $conn->table('indexing_duplicates')->count(),
            // created_at is the cloned original indexing date, so "today" counts
            // moves, not indexings.
            'today' => $conn->table('indexing_duplicates')
                ->whereDate('moved_at', now()->toDateString())
                ->count(),
            'registries' => $conn->table('indexing_duplicates')
                ->whereNotNull('registry')
                ->distinct()
                ->count('registry'),
            'mls_retained' => $conn->table('indexing_duplicates')
                ->where('mls_file_no_retained', 1)
                ->count(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(5, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));

        // indexed_by / indexed_at are the indexed files table's labels for the
        // cloned created_by / created_at; moved_at is the move's own timestamp.
        $sortMap = [
            'file_indexing_id' => 'file_indexing_id',
            'file_number'      => 'file_number',
            'registry'         => 'registry',
            'file_title'       => 'file_title',
            'land_use_type'    => 'land_use_type',
            'plot_number'      => 'plot_number',
            'location'         => 'location',
            'district'         => 'district',
            'lga'              => 'lga',
            'duplicate_of'     => 'duplicate_of',
            'indexed_by'       => 'created_by',
            'indexed_at'       => 'created_at',
            'moved_by'         => 'moved_by',
            'moved_at'         => 'moved_at',
        ];
        $sortInput = (string) $request->input('sort', 'moved_at');
        $sortColumn = $sortMap[$sortInput] ?? 'moved_at';
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::connection('sqlsrv')->table('indexing_duplicates');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            // One OR group, so an added registry filter still ANDs against all of it.
            $query->where(function ($q) use ($like, $search) {
                $q->where('file_number', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('duplicate_of', 'like', $like)
                    ->orWhere('plot_number', 'like', $like)
                    ->orWhere('kangis_file_no', 'like', $like)
                    ->orWhere('new_kangis_file_no', 'like', $like)
                    ->orWhere('mls_file_no', 'like', $like)
                    ->orWhere('tracking_id', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('moved_by', 'like', $like)
                    ->orWhere('created_by', 'like', $like)
                    ->orWhere('district', 'like', $like)
                    ->orWhere('lga', 'like', $like);

                // Let the original indexing id be searched directly.
                if (ctype_digit($search)) {
                    $q->orWhere('file_indexing_id', (int) $search);
                }
            });
        }

        $registry = trim((string) $request->input('registry', ''));
        if ($registry !== '') {
            $query->where('registry', $registry);
        }

        $total = (clone $query)->count();

        // id breaks ties so pagination stays stable across pages.
        $rows = $query->orderBy($sortColumn, $direction)
            ->orderBy('id', $direction)
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(function ($row) {
            $counts = json_decode((string) $row->deleted_counts, true) ?: [];

            // The archive is a clone of file_indexings, so hand back the same
            // field names the indexed files table renders — indexed_by/indexed_at
            // are that table's labels for created_by/created_at.
            return [
                'id'                   => $row->id,
                'file_indexing_id'     => $row->file_indexing_id,
                'file_number'          => $row->file_number,
                'registry'             => $row->registry,
                'general_registry'     => $row->general_registry,
                'file_title'           => $row->file_title,
                'land_use_type'        => $row->land_use_type,
                'plot_number'          => $row->plot_number,
                'tp_no'                => $row->tp_no,
                'lpkn_no'              => $row->lpkn_no,
                'district'             => $row->district,
                'lga'                  => $row->lga,
                'location'             => $row->location,
                'shelf_location'       => $row->shelf_location,
                'registry_batch_no'    => $row->registry_batch_no,
                'tracking_id'          => $row->tracking_id,
                'prop_id'              => $row->prop_id,
                'kangis_file_no'       => $row->kangis_file_no,
                'new_kangis_file_no'   => $row->new_kangis_file_no,
                'mls_file_no'          => $row->mls_file_no,
                'corresponding_fileno' => $row->corresponding_fileno,
                'temp_file_no'         => $row->temp_file_no,
                'status'               => $row->status,
                'indexed_by'           => $row->created_by,
                'indexed_at'           => $this->formatDate($row->created_at),
                'duplicate_of'         => $row->duplicate_of,
                'reason'               => $row->reason,
                'moved_by'             => $row->moved_by,
                'moved_at'             => $this->formatDate($row->moved_at),
                'mls_file_no_retained' => (bool) $row->mls_file_no_retained,
                'restored_at'          => $this->formatDate($row->restored_at),
                'restored_by'          => $row->restored_by,
                'counts'               => [
                    'file_indexings'    => (int) ($counts['file_indexings'] ?? 0),
                    'fileNumber'        => (int) ($counts['fileNumber'] ?? 0),
                    'customers_staging' => (int) ($counts['customers_staging'] ?? 0),
                    'entities_staging'  => (int) ($counts['entities_staging'] ?? 0),
                ],
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                // Derived from the rows actually returned, so a page past the end
                // reports 0/0 instead of a from greater than to.
                'from' => $rows->isEmpty() ? 0 : (($page - 1) * $perPage) + 1,
                'to' => $rows->isEmpty() ? 0 : (($page - 1) * $perPage) + $rows->count(),
            ],
        ]);
    }

    /**
     * Full detail for one moved record, including the snapshot broken out per
     * source table so it is clear exactly what was deleted.
     */
    public function show($id): JsonResponse
    {
        $row = DB::connection('sqlsrv')->table('indexing_duplicates')->where('id', (int) $id)->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        $snapshot = json_decode((string) $row->snapshot, true) ?: [];

        $tables = [];
        foreach (['file_indexings' => 'file_indexing', 'fileNumber' => 'fileNumber',
                  'customers_staging' => 'customers_staging', 'entities_staging' => 'entities_staging'] as $label => $key) {
            $value = $snapshot[$key] ?? null;
            if ($value === null) {
                continue;
            }

            // file_indexing is a single row; the rest are lists.
            $rows = array_is_list($value) ? $value : [$value];
            $tables[$label] = array_map(fn ($r) => $this->presentRow($r), $rows);
        }

        $childRows = [];
        foreach (($snapshot['child_rows'] ?? []) as $table => $rows) {
            $childRows[$table] = count($rows);
        }

        return response()->json([
            'success' => true,
            'record' => [
                'id' => $row->id,
                'file_indexing_id' => $row->file_indexing_id,
                'file_number' => $row->file_number,
                'file_title' => $row->file_title,
                'registry' => $row->registry,
                'duplicate_of' => $row->duplicate_of,
                'reason' => $row->reason,
                'indexed_by' => $row->created_by,
                'indexed_at' => $this->formatDate($row->created_at),
                'moved_by' => $row->moved_by,
                'moved_at' => $this->formatDate($row->moved_at),
                'mls_file_no_retained' => (bool) $row->mls_file_no_retained,
                'matched_numbers' => $snapshot['matched_numbers'] ?? [],
                'deleted_counts' => json_decode((string) $row->deleted_counts, true) ?: [],
            ],
            'tables' => $tables,
            'child_rows' => $childRows,
            'retained_references' => $snapshot['retained_references'] ?? [],
            // Dealings that were registered against this file number and stayed
            // in place — only present on records moved after this was recorded.
            'transaction_references' => $snapshot['transaction_references'] ?? [],
        ]);
    }

    /**
     * Drop empty values so the details view shows only columns that held data —
     * these rows have 60+ columns, most of them null.
     */
    private function presentRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $out[$key] = (string) $value;
        }

        return $out;
    }

    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
