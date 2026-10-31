<?php

namespace App\Http\Controllers;

use App\Services\TransactionMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Move Transaction (Indexed Files action menu) and the Moved Transactions page.
 *
 * The move is Super Admin only, checked here as well as by hiding the menu entry:
 * the endpoint deletes PRA / File History / CofO rows and is reachable directly.
 */
class MovedTransactionController extends Controller
{
    /** The file's timeline rows for the Move Transaction modal. */
    public function forFile($id, TransactionMoveService $service): JsonResponse
    {
        if ($denied = $this->denyUnlessSuperAdmin()) {
            return $denied;
        }

        $result = $service->listForFile((int) $id);
        if ($result['status'] === 'not_found') {
            return response()->json(['success' => false, 'message' => 'Indexed file not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'file'    => $result['file'],
            'rows'    => $result['rows'],
        ]);
    }

    public function move(Request $request, $id, TransactionMoveService $service): JsonResponse
    {
        if ($denied = $this->denyUnlessSuperAdmin()) {
            return $denied;
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'message' => 'A reason is required.'], 422);
        }

        $keys = (array) $request->input('keys', []);
        $belongsTo = trim((string) $request->input('belongs_to', ''));

        try {
            $result = $service->move((int) $id, $keys, $reason, $belongsTo !== '' ? $belongsTo : null);
        } catch (\Throwable $e) {
            Log::error('Move Transaction failed', ['file_indexing_id' => (int) $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'The move failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }

        if ($result['status'] === 'not_found') {
            return response()->json(['success' => false, 'message' => 'Indexed file not found.'], 404);
        }

        if ($result['status'] === 'invalid') {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success'      => true,
            'message'      => sprintf('%d transaction(s) moved off %s as %s.', count($result['moved']), $result['file_number'], $result['temp_file_no']),
            'temp_file_no' => $result['temp_file_no'],
            'moved'        => $result['moved'],
        ]);
    }

    public function index()
    {
        $PageTitle = 'Moved Transactions';
        $PageDescription = 'Transactions moved off indexed files as wrong or wrongly entered.';

        return view('moved_transactions.index', compact('PageTitle', 'PageDescription'));
    }

    public function list(Request $request): JsonResponse
    {
        $perPage = max(5, min((int) $request->input('per_page', 20), 100));
        $page = max(1, (int) $request->input('page', 1));

        $sortMap = [
            'temp_file_no'     => 'temp_file_no',
            'file_number'      => 'file_number',
            'source_table'     => 'source_table',
            'transaction_type' => 'transaction_type',
            'transaction_date' => 'transaction_date',
            'belongs_to'       => 'belongs_to',
            'moved_by'         => 'moved_by',
            'moved_at'         => 'moved_at',
        ];
        $sortColumn = $sortMap[(string) $request->input('sort', 'moved_at')] ?? 'moved_at';
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::connection('sqlsrv')->table('moved_transactions');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                foreach (['temp_file_no', 'file_number', 'transaction_type', 'party_1', 'party_2', 'reg_no', 'belongs_to', 'reason', 'moved_by'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        // Paged by file, not by transaction: one file's moves share a temp file no
        // and would otherwise repeat down the list (and split across pages). Each
        // group is ordered by the sorted column's latest/earliest value in it.
        $groupKey = "ISNULL(file_number, '')";
        $aggregate = $direction === 'asc' ? 'MIN' : 'MAX';

        $totalTransactions = (clone $query)->count();
        $total = (int) ((clone $query)->selectRaw("COUNT(DISTINCT $groupKey) AS c")->value('c') ?? 0);

        $groups = (clone $query)
            ->selectRaw("$groupKey AS group_key, COUNT(*) AS tx_count, MAX(moved_at) AS last_moved_at")
            ->groupBy(DB::raw($groupKey))
            ->orderByRaw("$aggregate($sortColumn) $direction")
            ->orderByRaw("$groupKey asc")
            ->forPage($page, $perPage)
            ->get();

        $groupKeys = $groups->pluck('group_key')->all();

        $rows = empty($groupKeys) ? collect() : (clone $query)
            ->whereIn(DB::raw($groupKey), $groupKeys)
            ->orderBy($sortColumn, $direction)
            ->orderBy('id', $direction)
            ->get([
                'id', 'temp_file_no', 'file_indexing_id', 'file_number', 'source_table', 'source_id',
                'transaction_type', 'party_1', 'party_2', 'transaction_date', 'reg_no',
                'belongs_to', 'reason', 'moved_by', 'moved_at',
            ]);

        $mapRow = fn ($row) => [
            'id'               => $row->id,
            'temp_file_no'     => $row->temp_file_no,
            'file_number'      => $row->file_number,
            'source_label'     => TransactionMoveService::MOVABLE_SOURCES[$row->source_table] ?? $row->source_table,
            'source_id'        => $row->source_id,
            'transaction_type' => $row->transaction_type,
            'party_1'          => $row->party_1,
            'party_2'          => $row->party_2,
            'transaction_date' => $row->transaction_date,
            'reg_no'           => $row->reg_no,
            'belongs_to'       => $row->belongs_to,
            'reason'           => $row->reason,
            'moved_by'         => $row->moved_by,
            'moved_at'         => $this->formatDate($row->moved_at),
        ];

        $rowsByGroup = $rows->groupBy(fn ($row) => (string) ($row->file_number ?? ''));

        $data = $groups->map(function ($group) use ($rowsByGroup, $mapRow) {
            $members = $rowsByGroup->get((string) $group->group_key, collect());

            return [
                'file_number'   => $group->group_key !== '' ? $group->group_key : null,
                'tx_count'      => (int) $group->tx_count,
                'temp_file_nos' => $members->pluck('temp_file_no')->filter()->unique()->values()->all(),
                'sources'       => $members->map(fn ($r) => TransactionMoveService::MOVABLE_SOURCES[$r->source_table] ?? $r->source_table)
                    ->filter()->unique()->values()->all(),
                'moved_by'      => $members->pluck('moved_by')->filter()->unique()->values()->all(),
                'last_moved_at' => $this->formatDate($group->last_moved_at),
                'rows'          => $members->map($mapRow)->values()->all(),
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'page'               => $page,
                'per_page'           => $perPage,
                'total'              => $total,
                'total_transactions' => $totalTransactions,
                'last_page'          => max(1, (int) ceil($total / $perPage)),
                'from'               => $groups->isEmpty() ? 0 : (($page - 1) * $perPage) + 1,
                'to'                 => $groups->isEmpty() ? 0 : (($page - 1) * $perPage) + $groups->count(),
            ],
        ]);
    }

    /** One moved transaction with its snapshot (empty columns dropped). */
    public function show($id): JsonResponse
    {
        $row = DB::connection('sqlsrv')->table('moved_transactions')->where('id', (int) $id)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $snapshot = json_decode((string) $row->snapshot, true) ?: [];
        $fields = [];
        foreach ($snapshot as $key => $value) {
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }
            $fields[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return response()->json([
            'success' => true,
            'record'  => [
                'id'               => $row->id,
                'temp_file_no'     => $row->temp_file_no,
                'file_number'      => $row->file_number,
                'source_label'     => TransactionMoveService::MOVABLE_SOURCES[$row->source_table] ?? $row->source_table,
                'source_table'     => $row->source_table,
                'source_id'        => $row->source_id,
                'transaction_type' => $row->transaction_type,
                'belongs_to'       => $row->belongs_to,
                'reason'           => $row->reason,
                'moved_by'         => $row->moved_by,
                'moved_at'         => $this->formatDate($row->moved_at),
            ],
            'snapshot' => $fields,
        ]);
    }

    private function denyUnlessSuperAdmin(): ?JsonResponse
    {
        $user = Auth::user();
        if ($user && $user->isSuperAdmin()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action. Only a Super Admin can move transactions.',
        ], 403);
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
