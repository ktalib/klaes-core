<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * File Indexing Activity Log
 *
 * Per-user breakdown of file indexing work. Each row is a file number and shows:
 *   - Number of files .......... 1 (the file itself) + its related files
 *   - Indexing done ............ how many of those files actually have a file_indexings row
 *   - File history / txns ...... transactions captured for the file across the 3 staging tables
 *   - Total weight ............. number_of_files*W_FILES + indexing_done*W_INDEXED + transactions*W_TXN
 *
 * Rows are grouped by the user that created the file index (file_indexings.created_by).
 */
class FileIndexingActivityLogController extends Controller
{
    /** Weight each metric contributes to Total weight. */
    private const W_FILES = 1;
    private const W_INDEXED = 1.5;
    private const W_TXN = 1;

    /** File-number columns shared by the three transaction staging tables. */
    private const TXN_FILE_COLUMNS = ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'];
    private const TXN_TABLES = ['file_history_staging', 'CofO_staging', 'pra'];

    /** Normalized created_by key (id / full name / username / email) => user id. */
    private array $userByKey = [];
    /** user id => display name. */
    private array $userDisplay = [];
    /** user id => list of raw created_by values that map to this user. */
    private array $userKeys = [];

    /**
     * Load current users from the users table and index every identifier that a
     * file_indexings.created_by value might hold. Records authored by users that
     * no longer exist will not resolve and are excluded from the report.
     */
    private function loadUsers(): void
    {
        if (!empty($this->userDisplay)) {
            return;
        }

        $users = DB::connection('sqlsrv')->table('users')
            ->select('id', 'first_name', 'last_name', 'username', 'email')
            ->get();

        foreach ($users as $u) {
            $full = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            $display = $full !== '' ? $full : ($u->username ?: ($u->email ?: ('User #' . $u->id)));
            $this->userDisplay[$u->id] = $display;

            foreach ([(string) $u->id, $full, $u->username, $u->email] as $key) {
                $norm = strtoupper(trim((string) $key));
                if ($norm === '') {
                    continue;
                }
                // First user to claim a key wins (ids are unique; name clashes are rare).
                if (!isset($this->userByKey[$norm])) {
                    $this->userByKey[$norm] = $u->id;
                }
                $this->userKeys[$u->id][] = (string) $key;
            }
        }
    }

    /** Resolve a raw created_by value to a current user id, or null if not a live user. */
    private function resolveUserId($createdBy): ?int
    {
        $norm = strtoupper(trim((string) $createdBy));
        return $this->userByKey[$norm] ?? null;
    }

    /**
     * Normalize request filters, defaulting to today when nothing is supplied.
     * Scanning the whole file_indexings table (tens of thousands of rows) joined
     * against the staging tables is slow, so an unfiltered request would be costly.
     */
    private function normalizeFilters(Request $request): array
    {
        $user = trim((string) $request->input('user', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($user === '' && empty($dateFrom) && empty($dateTo)) {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }

        return [
            'user' => $user,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * Public entry point for other controllers (e.g. the leaderboard) that need the
     * same per-user grouped report, sorted by total weight. Reuses the optimized,
     * correct report builder so figures match the activity log page exactly.
     */
    public function report(Request $request): array
    {
        $this->loadUsers();

        return $this->buildReport($this->normalizeFilters($request));
    }

    public function index(Request $request)
    {
        $filters = $this->normalizeFilters($request);

        $this->loadUsers();
        $users = $this->availableUsers();
        $groups = $this->buildReport($filters);

        $totals = [
            'files' => array_sum(array_column($groups, 'files')),
            'related' => array_sum(array_column($groups, 'related')),
            'indexed' => array_sum(array_column($groups, 'indexed')),
            'transactions' => array_sum(array_column($groups, 'transactions')),
            'weight' => array_sum(array_column($groups, 'weight')),
            'records' => array_sum(array_map(fn ($g) => count($g['rows']), $groups)),
        ];

        return view('fileindexing.activity_log.index', [
            'groups' => $groups,
            'totals' => $totals,
            'users' => $users,
            'filters' => $filters,
            'weights' => [
                'files' => self::W_FILES,
                'indexed' => self::W_INDEXED,
                'transactions' => self::W_TXN,
            ],
        ]);
    }

    /**
     * Current users (from the users table) who authored at least one file index,
     * as [user_id => display name] for the filter dropdown.
     */
    private function availableUsers(): array
    {
        $this->loadUsers();

        $rawCreators = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->when($this->hasNotDeleted('file_indexings'), fn ($q) => $this->applyNotDeleted($q, 'file_indexings'))
            ->distinct()
            ->pluck('created_by');

        $userIds = [];
        foreach ($rawCreators as $createdBy) {
            $id = $this->resolveUserId($createdBy);
            if ($id !== null) {
                $userIds[$id] = $this->userDisplay[$id];
            }
        }

        asort($userIds, SORT_NATURAL | SORT_FLAG_CASE);

        return $userIds;
    }

    /**
     * Build the grouped report: [ ['user' => .., 'files' => .., ..., 'rows' => [...]], ... ].
     */
    private function buildReport(array $filters): array
    {
        // 1. Main file_indexings rows in scope.
        $query = DB::connection('sqlsrv')->table('file_indexings')
            ->select(['id', 'file_number', 'temp_file_no', 'file_title', 'general_registry', 'created_by', 'created_at']);

        if ($this->hasNotDeleted('file_indexings')) {
            $this->applyNotDeleted($query, 'file_indexings');
        }

        $this->loadUsers();

        // A selected user maps to one or more raw created_by spellings (id / name / email).
        if ($filters['user'] !== '') {
            $selectedKeys = $this->userKeys[(int) $filters['user']] ?? ['__no_such_user__'];
            $query->whereIn('created_by', $selectedKeys);
        }
        // Range comparisons (not whereDate) so the created_at index can be used.
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $records = $query->orderBy('created_by')->orderByDesc('created_at')->get();

        if ($records->isEmpty()) {
            return [];
        }

        // 2. Related links for all records in one query, grouped by parent id.
        $recordIds = $records->pluck('id')->all();
        $linksByRecord = $this->relatedLinksByRecord($recordIds);

        // 3. Build the full set of file numbers (main + related) to resolve in bulk.
        $allFileNumbers = [];
        foreach ($records as $rec) {
            $main = $this->primaryFileNumber($rec);
            if ($main !== '') {
                $allFileNumbers[strtoupper($main)] = $main;
            }
            foreach ($linksByRecord[$rec->id] ?? [] as $linkFileNo) {
                if ($linkFileNo !== '') {
                    $allFileNumbers[strtoupper($linkFileNo)] = $linkFileNo;
                }
            }
        }
        $fileNumberList = array_values($allFileNumbers);

        $indexedSet = $this->indexedFileNumberSet($fileNumberList);
        $txnCounts = $this->transactionCountsByFileNumber($fileNumberList);

        // 4. Assemble per-record rows, grouped by current user (skip orphans).
        $grouped = [];
        foreach ($records as $rec) {
            $userId = $this->resolveUserId($rec->created_by);
            if ($userId === null) {
                continue; // author no longer exists in the users table
            }

            $mainFileNo = $this->primaryFileNumber($rec);
            $relatedNos = $linksByRecord[$rec->id] ?? [];
            $relatedCount = count($relatedNos);
            $registry = trim((string) ($rec->general_registry ?? ''));

            $numberOfFiles = 1 + $relatedCount;

            // Indexing done: main always counts (it is a file_indexings row);
            // each related file counts if it also exists as a file_indexings row.
            $indexingDone = 1;
            foreach ($relatedNos as $no) {
                if ($no !== '' && isset($indexedSet[strtoupper($no)])) {
                    $indexingDone++;
                }
            }

            // Transactions: sum across this file and its related files.
            $transactions = 0;
            foreach (array_merge([$mainFileNo], $relatedNos) as $no) {
                if ($no !== '') {
                    $transactions += $txnCounts[strtoupper($no)] ?? 0;
                }
            }

            $weight = $numberOfFiles * self::W_FILES
                + $indexingDone * self::W_INDEXED
                + $transactions * self::W_TXN;

            $userKey = $userId;

            if (!isset($grouped[$userKey])) {
                $grouped[$userKey] = [
                    'user' => $this->userDisplay[$userId],
                    'files' => 0,
                    'related' => 0,
                    'indexed' => 0,
                    'transactions' => 0,
                    'weight' => 0,
                    'registries' => [],
                    'rows' => [],
                ];
            }

            $grouped[$userKey]['rows'][] = [
                'file_number' => $mainFileNo !== '' ? $mainFileNo : ($rec->file_title ?: '(untitled)'),
                'file_title' => $rec->file_title,
                'registry' => $registry,
                'related_files' => $relatedCount,
                'related_file_numbers' => array_values(array_filter($relatedNos, fn ($n) => $n !== '')),
                'number_of_files' => $numberOfFiles,
                'indexing_done' => $indexingDone,
                'transactions' => $transactions,
                'weight' => $weight,
                'created_at' => $rec->created_at,
            ];

            $grouped[$userKey]['files'] += $numberOfFiles;
            $grouped[$userKey]['related'] += $relatedCount;
            $grouped[$userKey]['indexed'] += $indexingDone;
            $grouped[$userKey]['transactions'] += $transactions;
            $grouped[$userKey]['weight'] += $weight;
            if ($registry !== '') {
                $grouped[$userKey]['registries'][$registry] = true;
            }
        }

        // Order users by total weight (most productive first).
        $groups = array_values($grouped);
        foreach ($groups as &$g) {
            $g['registries'] = array_keys($g['registries']);
        }
        unset($g);
        usort($groups, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return $groups;
    }

    /**
     * Map of parent file_indexing id => list of related file numbers (from file_indexing_links).
     */
    private function relatedLinksByRecord(array $recordIds): array
    {
        $map = [];

        if (empty($recordIds) || !Schema::connection('sqlsrv')->hasTable('file_indexing_links')) {
            return $map;
        }

        // SQL Server caps a statement at 2100 bound parameters, so chunk the IN list.
        foreach (array_chunk($recordIds, 1000) as $chunk) {
            $linkQuery = DB::connection('sqlsrv')->table('file_indexing_links')
                ->select(['file_indexing_id', 'file_number'])
                ->whereIn('file_indexing_id', $chunk);

            if ($this->hasNotDeleted('file_indexing_links')) {
                $this->applyNotDeleted($linkQuery, 'file_indexing_links');
            }

            foreach ($linkQuery->get() as $link) {
                $map[$link->file_indexing_id][] = trim((string) $link->file_number);
            }
        }

        return $map;
    }

    /**
     * Set (upper file number => true) of file numbers that exist as file_indexings rows.
     */
    private function indexedFileNumberSet(array $fileNumbers): array
    {
        $set = [];
        if (empty($fileNumbers)) {
            return $set;
        }

        foreach (array_chunk($fileNumbers, 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $chunk)
                ->when($this->hasNotDeleted('file_indexings'), fn ($q) => $this->applyNotDeleted($q, 'file_indexings'))
                ->pluck('file_number');

            foreach ($rows as $no) {
                $set[strtoupper(trim((string) $no))] = true;
            }
        }

        return $set;
    }

    /**
     * Map of upper file number => transaction count, aggregated across the staging tables.
     * A transaction row is attributed to the first file number it matches to avoid double counting.
     */
    private function transactionCountsByFileNumber(array $fileNumbers): array
    {
        $counts = [];
        if (empty($fileNumbers)) {
            return $counts;
        }

        // Lookup set for attributing a matched row back to a requested file number.
        $wanted = [];
        foreach ($fileNumbers as $no) {
            $wanted[strtoupper($no)] = true;
        }

        foreach (self::TXN_TABLES as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                self::TXN_FILE_COLUMNS,
                fn ($col) => Schema::connection('sqlsrv')->hasColumn($table, $col)
            ));

            if (empty($columns)) {
                continue;
            }

            $hasId = Schema::connection('sqlsrv')->hasColumn($table, 'id');

            // Query one column at a time so each WHERE ... IN can use a single-column
            // index (an OR across 4 columns forces a full table scan). A transaction
            // row is counted once per table — deduped by id when available.
            $seen = [];
            foreach ($columns as $col) {
                $select = $hasId ? ['id', $col] : [$col];

                foreach (array_chunk($fileNumbers, 1000) as $chunk) {
                    $query = DB::connection('sqlsrv')->table($table)
                        ->select($select)
                        ->whereIn($col, $chunk);

                    if ($this->hasNotDeleted($table)) {
                        $this->applyNotDeleted($query, $table);
                    }

                    foreach ($query->get() as $row) {
                        if ($hasId) {
                            if (isset($seen[$row->id])) {
                                continue; // already counted via another column
                            }
                            $seen[$row->id] = true;
                        }

                        $value = strtoupper(trim((string) ($row->$col ?? '')));
                        if ($value !== '' && isset($wanted[$value])) {
                            $counts[$value] = ($counts[$value] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        return $counts;
    }

    private function primaryFileNumber($record): string
    {
        $fileNo = trim((string) ($record->file_number ?? ''));
        if ($fileNo !== '') {
            return $fileNo;
        }

        return trim((string) ($record->temp_file_no ?? ''));
    }

    /** Whether a table has an is_deleted column (cached per request). */
    private function hasNotDeleted(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted');
        }

        return $cache[$table];
    }

    private function applyNotDeleted($query, string $table): void
    {
        $query->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });
    }
}
