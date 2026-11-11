<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Moves an indexed file that turned out to be a duplicate into indexing_duplicates
 * and removes it from the live tables.
 *
 * The record is hard-deleted, not soft-deleted: file_indexings has no SoftDeletes
 * trait and IndexedFileTableController::list() does not filter is_deleted, so
 * flagging a row would leave it sitting in the table. indexing_duplicates keeps a
 * full JSON snapshot of everything removed so the move stays auditable/restorable.
 *
 * Everything else attached to the file goes with it (user, 2026-10-23): PRA, CofO,
 * file history (FH), file tracker records, movements and tracker requests are
 * snapshotted into the archive and then removed from the live tables.
 *
 * mls_file_no is deliberately NOT part of the cascade — deleting the commissioning
 * row would de-commission the file and change its Legal Search timeline. When one
 * exists it is recorded as retained instead.
 */
class IndexingDuplicateService
{
    /**
     * Scans, page typings, thumbnails and bills used to refuse the move and Master Delete.
     * Since 2026-11-04 both take them with the file (user: "the movement should move
     * everything", then "Master Delete, remove the block too").
     *
     * Snapshotted into child_rows and deleted, children before parents (page typings and
     * thumbnails point at scannings), with the scan_reassignment_logs rows whose
     * file_indexings foreign keys are NO_ACTION. A move keeps the snapshot in
     * indexing_duplicates; a Master Delete hands it to its audit entry. Files on disk are
     * not touched.
     */
    private const MOVE_ARCHIVED_REFERENCES = [
        'pagetypings',
        'thumbnails',
        'file_indexing_bills',
        'scannings',
    ];

    /**
     * Detached, not deleted: a blind scan is the scan of a physical folder and a SPAS
     * application carries notices, payments and certificates of its own, so they live on
     * with file_indexing_id cleared (the old link is in the snapshot's detached_rows).
     */
    private const MOVE_DETACHED_REFERENCES = [
        'blind_scannings'  => 'Blind scan(s) — unlinked, kept on the Blind Scan page',
        'spa_applications' => 'SPAS application(s) — unlinked, application kept',
    ];

    /**
     * Rows keyed by file_indexing_id that would be orphaned by the delete. They are
     * snapshotted (`child_rows`) and removed with the record.
     *
     * The two tracker tables used to be kept as the physical file's movement
     * history; since 2026-10-23 they go with the record like everything else, and
     * the snapshot is their surviving copy.
     */
    private const CLEANABLE_REFERENCES = [
        'indexed_file_trackers',
        'file_trackings',
        'print_label_batch_items',
        'kangis_print_label_batch_items',
        'sltr_print_label_batch_items',
        'st_print_label_batch_items',
        'dciv_print_label_batch_items',
        'file_indexing_links',
        'barcodes',
        'page_typing_tool_logs',
        'deprecated_records',
    ];

    /** Prefix of the system temp file no a move assigns (MOV-DUP-00001, ...). */
    public const MOVE_TEMP_PREFIX = MoveTempFileNumber::PREFIX;

    /** fileNumber columns that can hold the indexed file's number. */
    private const FILE_NUMBER_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'];

    /**
     * Record tables keyed by file number rather than by file_indexing_id that move
     * WITH the record: their rows are snapshotted into the archive and deleted from
     * the live tables, the same as the indexing row itself.
     *
     * pra and CofO_staging carry real dealings, so the confirmation counts them
     * separately (`deleted_transaction_total`) — a file with registered
     * transactions is usually the ORIGINAL rather than the duplicate, and the
     * operator has to see that before committing.
     *
     * @var array<string,array{label:string,columns:array<int,string>,transaction:bool}>
     */
    private const FILE_NUMBER_DELETIONS = [
        'pra' => [
            'label'       => 'Property records (PRA)',
            'columns'     => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'transaction' => true,
        ],
        'CofO_staging' => [
            'label'       => 'Certificates of Occupancy',
            'columns'     => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'transaction' => true,
        ],
        'file_history_staging' => [
            'label'       => 'File history (FH)',
            'columns'     => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'transaction' => true,
        ],
        // The other transaction sources the Legal Search / Property Timeline report reads
        // (user, 2026-11-04). Deeds on the file's own fileno only: a deed naming this file
        // as its parent_fileno belongs to the child file.
        'deed_registrations' => [
            'label'       => 'Registered deeds / instruments',
            'columns'     => ['fileno'],
            'transaction' => true,
        ],
        'caveats' => [
            'label'       => 'Caveats',
            'columns'     => ['file_number_mlsf', 'file_number_kangis', 'file_number_new_kangis', 'file_number'],
            'transaction' => true,
        ],
        'file_tracker' => [
            'label'       => 'File tracker requests',
            'columns'     => ['file_number'],
            'transaction' => false,
        ],
        'file_indexing_staging' => [
            'label'       => 'Staged indexing upload(s)',
            'columns'     => ['file_number'],
            'transaction' => false,
        ],
    ];

    /**
     * The file's commissioning row is matched on full_file_number only — old_fileno
     * holds a PREDECESSOR's number, so it would match the successor file's row.
     * Recorded as retained; never deleted.
     */
    private const MLS_FILE_NO_COLUMNS = ['full_file_number'];

    /** Human labels for the tables the move deletes from. */
    private const DELETION_LABELS = [
        'file_indexings'    => 'Indexed file record',
        'fileNumber'        => 'File number registry',
        'customers_staging' => 'Customer record(s)',
        'entities_staging'  => 'Entity record(s)',
        'indexed_file_trackers' => 'File tracker record(s)',
        'file_trackings'        => 'File tracking movement(s)',
        'scannings'             => 'Scanned page(s) (Digital Archive)',
        'pagetypings'           => 'Page typing record(s)',
        'thumbnails'            => 'Thumbnail(s)',
        'file_indexing_bills'   => 'Bill(s)',
        'scan_reassignment_logs' => 'Scan reassignment log(s)',
    ];

    /**
     * Summary-card groups, in display order. A table not listed falls under
     * "Linked rows" (labels, barcodes, links and other regenerable rows).
     */
    private const SUMMARY_GROUPS = [
        'Indexing record'      => ['file_indexings', 'file_indexing_staging'],
        'File number registry' => ['fileNumber'],
        'Property & title'     => ['pra', 'CofO_staging', 'file_history_staging', 'deed_registrations', 'caveats'],
        'File tracking'        => ['indexed_file_trackers', 'file_trackings', 'file_tracker'],
        'Scans & page typing'  => ['scannings', 'pagetypings', 'thumbnails', 'scan_reassignment_logs'],
        'Bills'                => ['file_indexing_bills'],
        'Parties'              => ['customers_staging', 'entities_staging'],
    ];

    /**
     * Everything the move would touch, counted without changing anything, so the
     * confirmation can spell it out before the operator commits.
     *
     * @return array{status:string, ...} status is 'ok', 'not_found' or 'blocked'
     */
    public function preview(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');

        $row = $conn->table('file_indexings')->where('id', $fileIndexingId)->first();
        if (!$row) {
            return ['status' => 'not_found'];
        }

        $blocking = $this->countMoveBlockers($fileIndexingId);
        /** @var array<int,object> $fileNumberRows */
        $fileNumberRows = [];
        $numbers = $this->resolveNumbers($row, $fileNumberRows);

        $deletions = [];
        $this->addPreviewRow($deletions, 'file_indexings', 1);
        $this->addPreviewRow($deletions, 'fileNumber', count($fileNumberRows));
        $this->addPreviewRow(
            $deletions,
            'customers_staging',
            empty($numbers) ? 0 : $conn->table('customers_staging')->whereIn('file_number', $numbers)->count()
        );
        $this->addPreviewRow(
            $deletions,
            'entities_staging',
            empty($numbers) ? 0 : $conn->table('entities_staging')->whereIn('file_number', $numbers)->count()
        );

        foreach (self::CLEANABLE_REFERENCES as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            $this->addPreviewRow(
                $deletions,
                $table,
                $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count()
            );
        }

        // Record tables keyed by file number that move with the record.
        $deletedTransactionTotal = 0;
        foreach (self::FILE_NUMBER_DELETIONS as $table => $meta) {
            $count = $this->countByFileNumber($table, $meta['columns'], $numbers);
            $this->addPreviewRow($deletions, $table, $count, $meta['label']);
            if ($meta['transaction']) {
                $deletedTransactionTotal += $count;
            }
        }

        // Scans, page typings, thumbnails and bills go with the file; blind scans and SPAS
        // applications are unlinked.
        $scanTotal = 0;
        $billTotal = 0;
        foreach ($this->moveArchivedCounts($fileIndexingId) as $table => $count) {
            $this->addPreviewRow($deletions, $table, $count);
            if ($table === 'file_indexing_bills') {
                $billTotal += $count;
            } elseif ($table === 'scannings') {
                $scanTotal += $count;
            }
        }

        // The only thing left in place: the commissioning row (see MLS_FILE_NO_COLUMNS).
        $retained = [];
        $this->addPreviewRow(
            $retained,
            'mls_file_no',
            count($this->findMlsFileNoRows($numbers)),
            'Commissioning record (file stays commissioned)'
        );
        foreach (self::MOVE_DETACHED_REFERENCES as $table => $label) {
            if (Schema::connection('sqlsrv')->hasTable($table)) {
                $this->addPreviewRow($retained, $table, $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count(), $label);
            }
        }

        return [
            'status'                    => empty($blocking) ? 'ok' : 'blocked',
            'file_number'               => $row->file_number,
            'file_title'                => $row->file_title ?? null,
            'matched_numbers'           => $numbers,
            'blocking'                  => $blocking,
            'deletions'                 => array_values($deletions),
            'summary'                   => $this->summarise($deletions),
            'retained'                  => array_values($retained),
            // Registered dealings (PRA / CofO / FH) on this file. They are deleted
            // with it now, so both keys carry the same number; transaction_total is
            // what the Master Delete dialog reads.
            'transaction_total'         => $deletedTransactionTotal,
            'deleted_transaction_total' => $deletedTransactionTotal,
            // Move only: scanned pages and bills that go into the archive with the file.
            'scan_total'                => $scanTotal,
            'bill_total'                => $billTotal,
        ];
    }

    /**
     * Rows a move archives from MOVE_ARCHIVED_REFERENCES, plus the scan reassignment log
     * rows that point at this file or its scans (their file_indexings foreign keys are
     * NO_ACTION, so they must go before the record does).
     *
     * @return array<string,int> table => count, only tables with rows
     */
    private function moveArchivedCounts(int $fileIndexingId): array
    {
        $out = [];
        foreach ($this->moveArchivedQueries($fileIndexingId) as $table => $query) {
            $count = $query->count();
            if ($count > 0) {
                $out[$table] = $count;
            }
        }

        return $out;
    }

    /** @return array<string,\Illuminate\Database\Query\Builder> in deletion order */
    private function moveArchivedQueries(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');
        $id = (string) $fileIndexingId;
        $queries = [];

        if ($schema->hasTable('scan_reassignment_logs')) {
            $queries['scan_reassignment_logs'] = $conn->table('scan_reassignment_logs')
                ->where(function ($q) use ($id) {
                    $q->where('from_file_indexing_id', $id)
                        ->orWhere('to_file_indexing_id', $id)
                        ->orWhereIn('scanning_id', function ($s) use ($id) {
                            $s->select('id')->from('scannings')->where('file_indexing_id', $id);
                        });
                });
        }
        foreach (self::MOVE_ARCHIVED_REFERENCES as $table) {
            if ($schema->hasTable($table)) {
                $queries[$table] = $conn->table($table)->where('file_indexing_id', $id);
            }
        }

        return $queries;
    }

    /**
     * What still stops a MOVE: scans filed under another file that were derived from this
     * file's scans (scannings.parent_scan_id is NO_ACTION, so the delete would fail).
     *
     * @return array<string,string>
     */
    private function countMoveBlockers(int $fileIndexingId): array
    {
        if (!Schema::connection('sqlsrv')->hasTable('scannings')) {
            return [];
        }
        $id = (string) $fileIndexingId;
        $count = DB::connection('sqlsrv')->table('scannings')
            ->whereIn('parent_scan_id', function ($s) use ($id) {
                $s->select('id')->from('scannings')->where('file_indexing_id', $id);
            })
            ->where(function ($q) use ($id) {
                $q->whereNull('file_indexing_id')->orWhere('file_indexing_id', '!=', $id);
            })
            ->count();

        return $count > 0 ? ['scannings' => $count . ' scan(s) in another file derived from this file\'s scans'] : [];
    }

    /**
     * Count rows in a record/transaction table that carry any of this file's
     * numbers, skipping columns the table does not actually have (these tables
     * differ between installs) and rows already soft-deleted.
     */
    private function countByFileNumber(string $table, array $columns, array $numbers): int
    {
        $query = $this->fileNumberQuery($table, $columns, $numbers);

        return $query ? $query->count() : 0;
    }

    /**
     * The rows of $table carrying any of this file's numbers, or null when the
     * table/columns are not present on this install.
     *
     * Rows already soft-deleted are excluded so the count shown in the
     * confirmation, the snapshot written to the archive and the rows actually
     * deleted are all the same set.
     */
    private function fileNumberQuery(string $table, array $columns, array $numbers): ?\Illuminate\Database\Query\Builder
    {
        if (empty($numbers) || !Schema::connection('sqlsrv')->hasTable($table)) {
            return null;
        }

        $columns = array_values(array_filter(
            $columns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        if (empty($columns)) {
            return null;
        }

        $query = DB::connection('sqlsrv')->table($table)
            ->where(function ($q) use ($columns, $numbers) {
                foreach ($columns as $column) {
                    $q->orWhereIn($column, $numbers);
                }
            });

        if (Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
            $query->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
        }

        return $query;
    }

    /**
     * Snapshot of every row the file-number deletions will remove, taken before
     * anything is deleted.
     *
     * @return array<string,array<int,array>>
     */
    private function collectFileNumberDeletionRows(array $numbers): array
    {
        $out = [];

        foreach (self::FILE_NUMBER_DELETIONS as $table => $meta) {
            $query = $this->fileNumberQuery($table, $meta['columns'], $numbers);
            if (!$query) {
                continue;
            }

            $rows = $query->get();
            if ($rows->isNotEmpty()) {
                $out[$table] = $rows->map(fn ($r) => (array) $r)->all();
            }
        }

        return $out;
    }

    /** @param array<string,array{table:string,label:string,count:int}> $bucket */
    private function addPreviewRow(array &$bucket, string $table, int $count, ?string $label = null): void
    {
        if ($count <= 0) {
            return;
        }

        $bucket[$table] = [
            'table' => $table,
            'label' => $label ?? $this->tableLabel($table),
            'group' => $this->tableGroup($table),
            'count' => $count,
        ];
    }

    private function tableLabel(string $table): string
    {
        return self::DELETION_LABELS[$table]
            ?? self::FILE_NUMBER_DELETIONS[$table]['label']
            ?? $this->humanizeTable($table);
    }

    private function tableGroup(string $table): string
    {
        foreach (self::SUMMARY_GROUPS as $group => $tables) {
            if (in_array($table, $tables, true)) {
                return $group;
            }
        }

        return 'Linked rows';
    }

    /**
     * Preview / count rows folded into the summary card: one entry per non-empty
     * group, in SUMMARY_GROUPS order, each with its total and its per-table rows.
     *
     * @param array<string,array{table:string,label:string,group:string,count:int}> $rows
     * @return array<int,array{group:string,total:int,tables:array<int,array>}>
     */
    private function summarise(array $rows): array
    {
        $groups = [];
        foreach (array_merge(array_keys(self::SUMMARY_GROUPS), ['Linked rows']) as $group) {
            $groups[$group] = ['group' => $group, 'total' => 0, 'tables' => []];
        }

        foreach ($rows as $row) {
            $groups[$row['group']]['total'] += $row['count'];
            $groups[$row['group']]['tables'][] = $row;
        }

        return array_values(array_filter($groups, fn ($g) => $g['total'] > 0));
    }

    /**
     * The summary card for a completed move, built from its deleted_counts. Used by
     * the move response and by the Indexing Duplicates snapshot view.
     *
     * @return array<int,array{group:string,total:int,tables:array<int,array>}>
     */
    public function summariseCounts(array $counts): array
    {
        $rows = [];
        foreach (['file_indexings', 'fileNumber', 'customers_staging', 'entities_staging'] as $table) {
            $this->addPreviewRow($rows, $table, (int) ($counts[$table] ?? 0));
        }
        foreach (['child_rows', 'file_number_rows'] as $bucket) {
            foreach ((array) ($counts[$bucket] ?? []) as $table => $count) {
                $this->addPreviewRow($rows, (string) $table, (int) $count);
            }
        }

        return $this->summarise($rows);
    }

    private function humanizeTable(string $table): string
    {
        return ucfirst(str_replace('_', ' ', $table));
    }

    /**
     * @return array{status:string, ...} status is 'moved', 'not_found', 'blocked' or 'already_moved'
     */
    public function move(int $fileIndexingId, ?string $reason = null, ?string $duplicateOf = null): array
    {
        return $this->execute($fileIndexingId, $reason, $duplicateOf, true);
    }

    /**
     * Master Delete: the same removal as move(), with no indexing_duplicates archive
     * behind it.
     *
     * Deliberately the SAME code path rather than a second purge engine — the set of
     * tables an indexed file lives in is hard-won (see the constants above) and a
     * parallel implementation would drift from it the first time that set changes.
     * The only difference is that nothing is written to indexing_duplicates: a master
     * delete is not a duplicate finding, so the record must not reappear on the
     * Indexing Duplicates screen as though it were one.
     *
     * The returned `snapshot` is the whole pre-delete record; the caller writes it to
     * the audit log, which is then the only surviving account of the file.
     *
     * Since 2026-11-04 (user) it no longer refuses files carrying scans, page typings,
     * thumbnails or bills: they are deleted with the file and their full rows ride in the
     * snapshot, hence in the audit entry. Blind scans and SPAS applications are unlinked.
     *
     * @return array{status:string, ...} status is 'purged', 'not_found' or 'blocked'
     */
    public function purge(int $fileIndexingId, ?string $reason = null): array
    {
        return $this->execute($fileIndexingId, $reason, null, false);
    }

    /**
     * The shared body of move() and purge().
     *
     * @param bool $archive true to write (and keep) an indexing_duplicates row
     */
    private function execute(int $fileIndexingId, ?string $reason, ?string $duplicateOf, bool $archive): array
    {
        $conn = DB::connection('sqlsrv');

        $row = $conn->table('file_indexings')->where('id', $fileIndexingId)->first();
        if (!$row || !empty($row->is_deleted)) {
            // Already moved by someone else in a parallel session? Report that
            // instead of a bare "not found" so the operator understands. Only the
            // move flow can say this — a purge leaves no archive row to find, so for
            // it a missing record is simply gone.
            $archived = $archive
                ? $conn->table('indexing_duplicates')->where('file_indexing_id', $fileIndexingId)->whereNull('restored_at')->first()
                : null;

            if ($archived) {
                return [
                    'status' => 'already_moved',
                    'file_number' => $archived->file_number,
                    'moved_by' => $archived->moved_by,
                    'moved_at' => $archived->created_at,
                ];
            }

            return ['status' => 'not_found'];
        }

        // A move takes scans, page typings, thumbnails and bills with it and unlinks blind
        // scans / SPAS applications; a purge (no archive) still refuses them.
        $blocking = $this->countMoveBlockers($fileIndexingId);
        if (!empty($blocking)) {
            return [
                'status' => 'blocked',
                'file_number' => $row->file_number,
                'dependencies' => $blocking,
            ];
        }

        $numbers = $this->resolveNumbers($row, $fileNumberRows);

        $customerRows = $conn->table('customers_staging')->whereIn('file_number', $numbers)->get();
        $entityRows   = $conn->table('entities_staging')->whereIn('file_number', $numbers)->get();
        $cleanable    = $this->collectCleanableRows($fileIndexingId);

        // Scans, page typings, thumbnails, bills (deleted) and blind scans / SPAS
        // applications (unlinked): full rows into the snapshot first.
        $detached = [];
        foreach ($this->moveArchivedQueries($fileIndexingId) as $table => $query) {
            $rows = $query->get();
            if ($rows->isNotEmpty()) {
                $cleanable[$table] = $rows->map(fn ($r) => (array) $r)->all();
            }
        }
        foreach (array_keys(self::MOVE_DETACHED_REFERENCES) as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            $rows = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->get();
            if ($rows->isNotEmpty()) {
                $detached[$table] = $rows->map(fn ($r) => (array) $r)->all();
            }
        }

        // Record rows keyed by file number that move with the record. Snapshotted
        // here, before the transaction deletes them.
        $fileNumberRowsToDelete = $this->collectFileNumberDeletionRows($numbers);

        // Recorded, never deleted — the file stays commissioned.
        $retainedMls = !empty($this->findMlsFileNoRows($numbers));

        $actor = $this->resolveActorName();
        $counts = [];
        // Built before the transaction: for a move it becomes indexing_duplicates.snapshot,
        // for a purge it is all the caller has left to write to the audit log.
        $snapshot = [
            'file_indexing'     => (array) $row,
            'fileNumber'        => array_map(fn ($r) => (array) $r, $fileNumberRows),
            'customers_staging' => $customerRows->map(fn ($r) => (array) $r)->all(),
            'entities_staging'  => $entityRows->map(fn ($r) => (array) $r)->all(),
            // Labels, barcodes, links and the two tracker tables.
            'child_rows'        => $cleanable,
            // pra / CofO_staging / file_history_staging / file_tracker /
            // file_indexing_staging — deleted with the record, full rows kept here
            // so the move stays restorable.
            'file_number_rows'  => $fileNumberRowsToDelete,
            // Move only: blind scans / SPAS applications unlinked from the file (rows as
            // they were, file_indexing_id included).
            'detached_rows'     => $detached,
            'matched_numbers'   => $numbers,
            'mls_file_no_retained' => $retainedMls,
        ];

        $moveTempNo = null;

        $archiveId = $conn->transaction(function () use (
            $conn, $row, $fileIndexingId, $numbers, $fileNumberRows,
            $retainedMls, $actor, $reason, $duplicateOf, $archive, $snapshot, &$counts, &$moveTempNo
        ) {
            $archiveId = null;

            if ($archive) {
                // System temp file no for the moved file and its transactions
                // (user, 2026-10-24): stamped on the archive row and on every row
                // in the snapshot, so the duplicate keeps an identity of its own
                // once the real number is free for the original.
                $moveTempNo = $this->nextMoveTempNo($conn);
                $snapshot = $this->stampSnapshot($snapshot, $moveTempNo);

                // Clone the record column-for-column. indexing_duplicates mirrors
                // file_indexings, so the archive lines up with the indexed files table
                // and a restore is a straight column-to-column insert. Anything the
                // archive does not have a column for is dropped (it survives in the
                // snapshot), which keeps this working if the two tables drift.
                $archiveRow = array_intersect_key((array) $row, array_flip($this->archiveColumns()));

                // created_at / updated_at stay as the ORIGINAL indexing timestamps —
                // the move has its own moved_at.
                unset($archiveRow['id']);

                // array_merge, not +=, so these override the cloned has_temp_file /
                // temp_file_no instead of being ignored.
                $archiveRow = array_merge($archiveRow, [
                    'file_indexing_id'     => $fileIndexingId,
                    'duplicate_of'         => $this->clip($duplicateOf, 100),
                    'reason'               => $this->clip($reason, 500),
                    'mls_file_no_retained' => $retainedMls ? 1 : 0,
                    // Replaces any temp no cloned from the indexing row; the
                    // original value is still in snapshot.file_indexing.
                    'has_temp_file'        => 1,
                    'temp_file_no'         => $moveTempNo,
                    'snapshot'             => json_encode($snapshot),
                    'deleted_counts'       => null,
                    'moved_by'             => $this->clip($actor, 150),
                    'moved_by_id'          => Auth::id(),
                    'moved_at'             => now(),
                ]);

                // Archive first: if any delete below fails the whole move rolls back,
                // archive row included.
                $archiveId = $conn->table('indexing_duplicates')->insertGetId($archiveRow);
            }

            // Child queue/link rows first, so nothing is left pointing at a
            // file_indexings row that is about to disappear.
            $childCounts = [];
            $detachCounts = [];

            // Scan reassignment logs, page typings, thumbnails, bills, then scans. Same
            // queries the snapshot was taken from.
            foreach ($this->moveArchivedQueries($fileIndexingId) as $table => $query) {
                if ($table === 'scannings') {
                    // Parent links inside this file first (parent_scan_id is NO_ACTION).
                    $conn->table('scannings')->where('file_indexing_id', (string) $fileIndexingId)
                        ->whereNotNull('parent_scan_id')->update(['parent_scan_id' => null]);
                }
                $deleted = $query->delete();
                if ($deleted > 0) {
                    $childCounts[$table] = $deleted;
                }
            }
            foreach (array_keys(self::MOVE_DETACHED_REFERENCES) as $table) {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }
                $n = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->update(['file_indexing_id' => null]);
                if ($n > 0) {
                    $detachCounts[$table] = $n;
                }
            }
            foreach (self::CLEANABLE_REFERENCES as $table) {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }
                $deleted = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->delete();
                if ($deleted > 0) {
                    $childCounts[$table] = $deleted;
                }
            }

            // Record rows keyed by file number. Deleted against the same query the
            // snapshot was taken from, so nothing is removed that was not archived.
            $fileNumberCounts = [];
            foreach (self::FILE_NUMBER_DELETIONS as $table => $meta) {
                $query = $this->fileNumberQuery($table, $meta['columns'], $numbers);
                if (!$query) {
                    continue;
                }

                $deleted = $query->delete();
                if ($deleted > 0) {
                    $fileNumberCounts[$table] = $deleted;
                }
            }

            $counts = [
                'customers_staging' => empty($numbers) ? 0 : $conn->table('customers_staging')->whereIn('file_number', $numbers)->delete(),
                'entities_staging'  => empty($numbers) ? 0 : $conn->table('entities_staging')->whereIn('file_number', $numbers)->delete(),
                'fileNumber'        => 0,
                'file_indexings'    => 0,
                'child_rows'        => $childCounts,
                'file_number_rows'  => $fileNumberCounts,
                'detached'          => $detachCounts,
            ];

            $fileNumberIds = array_values(array_filter(array_map(fn ($r) => $r->id ?? null, $fileNumberRows)));
            if (!empty($fileNumberIds)) {
                $counts['fileNumber'] = $archive
                    ? $conn->table('fileNumber')->whereIn('id', $fileNumberIds)->delete()
                    : app(\App\Services\FileNumber\CrossedOutFiles::class)->crossOutFileNumberRows($fileNumberIds);
            }

            // A move archives the row; a Master Delete leaves it crossed out on its table.
            $counts['file_indexings'] = $archive
                ? $conn->table('file_indexings')->where('id', $fileIndexingId)->delete()
                : app(\App\Services\FileNumber\CrossedOutFiles::class)->crossOutIndexings([$fileIndexingId]);

            if ($archive) {
                $conn->table('indexing_duplicates')
                    ->where('id', $archiveId)
                    ->update(['deleted_counts' => json_encode($counts)]);
            }

            return $archiveId;
        });

        // KANGIS Block Indexing (user, 2026-10-20): a KANGIS record that is gone takes its
        // name and plot back off its land files, and their KANGIS count is redone. After the
        // commit, so the land files no longer see this record; a failure here only logs.
        try {
            app(\App\Services\FileIndexing\KangisBlockIndexing::class)->removeKangisFromLandFiles($row);
        } catch (\Throwable $e) {
            Log::warning('IndexingDuplicateService: KANGIS land file clean-up failed', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
            ]);
        }

        // A file number the indexing row claims but fileNumber does not hold is not
        // fatal — but it leaves the registry untouched, so make it visible.
        if ($counts['fileNumber'] === 0) {
            Log::warning('IndexingDuplicateService: no fileNumber row matched; registry left untouched', [
                'file_indexing_id' => $fileIndexingId,
                'file_number' => $row->file_number,
                'numbers_tried' => $numbers,
            ]);
        }

        Log::warning(
            $archive
                ? 'Indexed file moved to indexing_duplicates'
                : 'Indexed file MASTER DELETED (no archive kept)',
            [
                'indexing_duplicate_id' => $archiveId,
                'file_indexing_id' => $fileIndexingId,
                'file_number' => $row->file_number,
                'counts' => $counts,
                'moved_by' => $actor,
            ]
        );

        // The move writes its own audit entry here. A purge does not: its caller owns
        // the audit, because ExecutesMasterDelete::logMasterDelete() is the entry every
        // other Master Delete in the system writes, and one shape beats two.
        if ($archive) {
            $this->writeAudit($archiveId, $row, $counts, $reason, $moveTempNo);
        }

        return [
            'status' => $archive ? 'moved' : 'purged',
            'indexing_duplicate_id' => $archiveId,
            'file_number' => $row->file_number,
            'file_title' => $row->file_title ?? null,
            'counts' => $counts,
            'matched_numbers' => $numbers,
            'mls_file_no_retained' => $retainedMls,
            'temp_file_no' => $moveTempNo,
            'summary' => $this->summariseCounts($counts),
            // Only a purge needs this back — nothing else survives it.
            'snapshot' => $archive ? null : $snapshot,
        ];
    }

    /**
     * Every number this record is known by, plus the fileNumber rows holding them.
     *
     * Matching is exact by design: file numbers here differ by whitespace alone
     * ("KN 1" is a legacy land file, "KN1" is a New KANGIS file), so normalising
     * separators before comparing would sweep up a different file's rows.
     *
     * @param array<int,object>|null $fileNumberRows out-param, filled with the matched fileNumber rows
     * @return array<int,string>
     */
    private function resolveNumbers(object $row, ?array &$fileNumberRows = null): array
    {
        $numbers = $this->collectFileNumbers($row);
        $fileNumberRows = $this->findFileNumberRows($numbers, $row->tracking_id ?? null);

        // fileNumber rows can carry numbers the indexing row does not know about.
        foreach ($fileNumberRows as $fnRow) {
            foreach (self::FILE_NUMBER_COLUMNS as $column) {
                $value = trim((string) ($fnRow->{$column} ?? ''));
                if ($value !== '') {
                    $numbers[$value] = $value;
                }
            }
        }

        return array_values($numbers);
    }

    /** @return array<string,string> keyed by value so duplicates collapse */
    private function collectFileNumbers(object $row): array
    {
        $numbers = [];

        foreach (['file_number', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no'] as $column) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '' && $value !== '-') {
                $numbers[$value] = $value;
            }
        }

        return $numbers;
    }

    /** @return array<int,object> */
    private function findFileNumberRows(array $numbers, ?string $trackingId): array
    {
        $conn = DB::connection('sqlsrv');
        $values = array_values($numbers);
        $rows = [];

        if (!empty($values)) {
            $rows = $conn->table('fileNumber')
                ->where(function ($query) use ($values) {
                    foreach (self::FILE_NUMBER_COLUMNS as $column) {
                        $query->orWhereIn($column, $values);
                    }
                })
                ->get()
                ->all();
        }

        // Fall back to the tracking id, which survives a file-number correction.
        $trackingId = trim((string) $trackingId);
        if (empty($rows) && $trackingId !== '') {
            $rows = $conn->table('fileNumber')->where('tracking_id', $trackingId)->get()->all();
        }

        return $rows;
    }

    /**
     * The commissioning rows carried by this file's number(s).
     *
     * @return array<int,object>
     */
    private function findMlsFileNoRows(array $numbers): array
    {
        if (empty($numbers)) {
            return [];
        }

        return DB::connection('sqlsrv')->table('mls_file_no')
            ->where(function ($query) use ($numbers) {
                foreach (self::MLS_FILE_NO_COLUMNS as $column) {
                    $query->orWhereIn($column, $numbers);
                }
            })
            ->get()
            ->all();
    }

    /** @return array<string,array<int,array>> */
    private function collectCleanableRows(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $out = [];

        foreach (self::CLEANABLE_REFERENCES as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $rows = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->get();
            if ($rows->isNotEmpty()) {
                $out[$table] = $rows->map(fn ($r) => (array) $r)->all();
            }
        }

        return $out;
    }

    /**
     * Column list of indexing_duplicates, used to decide which of the source row's
     * columns the archive can actually hold.
     *
     * @return array<int,string>
     */
    private function archiveColumns(): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::connection('sqlsrv')->getColumnListing('indexing_duplicates');
        }

        return $columns;
    }

    private function resolveActorName(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'system';
        }

        foreach (['name', 'full_name', 'username', 'email'] as $attribute) {
            $value = trim((string) ($user->{$attribute} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'user #' . $user->id;
    }

    private function clip(?string $value, int $length): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    /**
     * Next MOV-DUP-##### number, from the series shared with Move Transaction.
     * Must be called inside the move's transaction.
     */
    private function nextMoveTempNo($conn): string
    {
        return MoveTempFileNumber::next($conn);
    }

    /**
     * Stamp the move's temp file no on the snapshot and on every row it holds
     * (indexing, registry, parties, PRA / CofO / FH / tracking, linked rows). The
     * rows' own columns are left untouched so a restore still has the originals.
     */
    private function stampSnapshot(array $snapshot, string $tempNo): array
    {
        $snapshot['moved_temp_file_no'] = $tempNo;
        $snapshot['file_indexing']['moved_temp_file_no'] = $tempNo;

        foreach (['fileNumber', 'customers_staging', 'entities_staging'] as $key) {
            foreach ($snapshot[$key] as $i => $r) {
                $snapshot[$key][$i]['moved_temp_file_no'] = $tempNo;
            }
        }

        foreach (['child_rows', 'file_number_rows', 'detached_rows'] as $bucket) {
            foreach ($snapshot[$bucket] ?? [] as $table => $rows) {
                foreach ($rows as $i => $r) {
                    $snapshot[$bucket][$table][$i]['moved_temp_file_no'] = $tempNo;
                }
            }
        }

        return $snapshot;
    }

    /**
     * Manual capture (CR 11112026-2, item 3): a duplicate physical file that was never
     * indexed -- its number is held by the main file's indexed record, so there is
     * nothing to move -- is entered straight into indexing_duplicates.
     *
     * Only what the officer typed is stored: the main file's indexed details are NOT
     * copied in, because they describe the other file. No live table is touched, the
     * row has no snapshot and source 'manual', so Reverse Duplicate does not apply.
     *
     * @param  array{file_number:string, duplicate_of:?string, file_title:string, plot_number:?string,
     *               district:?string, lga:?string, location:?string, land_use_type:?string,
     *               registry:?string, reason:string}  $data
     * @return array{id:int, temp_file_no:string}
     */
    public function captureManual(array $data): array
    {
        $conn = DB::connection('sqlsrv');
        $actor = $this->resolveActorName();
        $now = now();

        $result = $conn->transaction(function () use ($conn, $data, $actor, $now) {
            $tempNo = $this->nextMoveTempNo($conn);

            $id = $conn->table('indexing_duplicates')->insertGetId([
                'file_indexing_id' => null,
                'file_number' => $this->clip($data['file_number'], 100),
                'duplicate_of' => $this->clip($data['duplicate_of'] ?? null, 100) ?? $this->clip($data['file_number'], 100),
                'file_title' => $this->clip($data['file_title'], 4000),
                'plot_number' => $this->clip($data['plot_number'] ?? null, 100),
                'district' => $this->clip($data['district'] ?? null, 100),
                'lga' => $this->clip($data['lga'] ?? null, 100),
                'location' => $this->clip($data['location'] ?? null, 255),
                'land_use_type' => $this->clip($data['land_use_type'] ?? null, 100),
                'physical_registry' => $this->clip($data['registry'] ?? null, 50),
                'reason' => $this->clip($data['reason'], 500),
                'is_duplicate' => '1',
                'has_temp_file' => 1,
                'temp_file_no' => $tempNo,
                'status' => 'Manual Entry',
                'source' => 'manual',
                'mls_file_no_retained' => 0,
                'created_by' => $this->clip($actor, 50),
                'created_at' => $now,
                'updated_at' => $now,
                'moved_by' => $this->clip($actor, 150),
                'moved_by_id' => Auth::id(),
                'moved_at' => $now,
            ]);

            return ['id' => (int) $id, 'temp_file_no' => $tempNo];
        });

        try {
            app(AuditService::class)->logAction(
                'CREATED',
                'indexing_duplicate',
                $result['id'],
                null,
                ['file_number' => $data['file_number'], 'temp_file_no' => $result['temp_file_no'], 'reason' => $data['reason'], 'source' => 'manual'],
                sprintf('Captured duplicate file %s manually under Indexing Duplicates', $data['file_number'])
            );
        } catch (\Throwable $e) {
            Log::warning('IndexingDuplicateService: audit write failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    private function writeAudit(int $archiveId, object $row, array $counts, ?string $reason, ?string $tempFileNo = null): void
    {
        try {
            app(AuditService::class)->logAction(
                'DELETED',
                'indexing_duplicate',
                $archiveId,
                ['file_indexing_id' => $row->id, 'file_number' => $row->file_number],
                ['counts' => $counts, 'reason' => $reason, 'temp_file_no' => $tempFileNo],
                sprintf('Moved indexed file %s to indexing duplicates', $row->file_number)
            );
        } catch (\Throwable $e) {
            // Audit must never block the move.
            Log::warning('IndexingDuplicateService: audit write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse Duplicate: what putting a moved file back would restore, without changing
     * anything. Refused ('blocked') while a live record holds the number, as most moved
     * files were indexed again afterwards and restoring would make a real duplicate.
     *
     * @return array{status:string, ...} status is 'ok', 'blocked', 'not_found', 'already_reversed' or 'unreadable'
     */
    public function previewReverse(int $archiveId): array
    {
        $archive = DB::connection('sqlsrv')->table('indexing_duplicates')->where('id', $archiveId)->first();
        if (!$archive) {
            return ['status' => 'not_found'];
        }
        if (!empty($archive->restored_at)) {
            return [
                'status' => 'already_reversed',
                'file_number' => $archive->file_number,
                'restored_by' => $archive->restored_by,
                'restored_at' => $archive->restored_at,
            ];
        }

        $snapshot = $this->decodeSnapshot($archive);
        if ($snapshot === null) {
            return ['status' => 'unreadable', 'file_number' => $archive->file_number];
        }

        $holders = $this->liveHolders($snapshot);

        return [
            'status' => empty($holders) ? 'ok' : 'blocked',
            'file_number' => $archive->file_number,
            'file_title' => $archive->file_title,
            'temp_file_no' => $archive->temp_file_no,
            'held_by' => $holders,
            'summary' => $this->summariseCounts($this->snapshotCounts($snapshot)),
            'relink' => array_map('count', (array) ($snapshot['detached_rows'] ?? [])),
        ];
    }

    /**
     * Reverse Duplicate: puts everything a move took back into the live tables with its
     * original ids, in one transaction, and relinks blind scans / SPAS applications and
     * the KANGIS land files. The archive row stays, flagged restored_at / restored_by,
     * so the move and its reversal both stay on record. mls_file_no was never touched and
     * files on disk were never moved, so neither needs anything here.
     *
     * @return array{status:string, ...} status is 'reversed', or a previewReverse() status
     */
    public function reverse(int $archiveId, string $reason): array
    {
        $preview = $this->previewReverse($archiveId);
        if ($preview['status'] !== 'ok') {
            return $preview;
        }

        $conn = DB::connection('sqlsrv');
        $archive = $conn->table('indexing_duplicates')->where('id', $archiveId)->first();
        $snapshot = $this->decodeSnapshot($archive);
        $actor = $this->resolveActorName();
        $restored = [];
        $skipped = [];
        $relinked = [];

        $done = $conn->transaction(function () use ($conn, $archiveId, $snapshot, $actor, &$restored, &$skipped, &$relinked) {
            // Two people reversing at once, or the number taken since the preview.
            $lock = $conn->table('indexing_duplicates')->where('id', $archiveId)->lockForUpdate()->first(['id', 'restored_at']);
            if (!$lock || !empty($lock->restored_at) || !empty($this->liveHolders($snapshot))) {
                return false;
            }

            $this->restoreRows($conn, 'file_indexings', [$snapshot['file_indexing']], $restored, $skipped);
            $this->restoreFileNumberRows($conn, (array) ($snapshot['fileNumber'] ?? []), $restored, $skipped);
            $this->restoreRows($conn, 'customers_staging', (array) ($snapshot['customers_staging'] ?? []), $restored, $skipped);
            $this->restoreRows($conn, 'entities_staging', (array) ($snapshot['entities_staging'] ?? []), $restored, $skipped);

            foreach ((array) ($snapshot['file_number_rows'] ?? []) as $table => $rows) {
                $this->restoreRows($conn, (string) $table, (array) $rows, $restored, $skipped);
            }

            // Parents before children, the reverse of the order the move deleted them in.
            $childRows = (array) ($snapshot['child_rows'] ?? []);
            $this->restoreScans($conn, (array) ($childRows['scannings'] ?? []), $restored, $skipped);
            $reassignLogs = (array) ($childRows['scan_reassignment_logs'] ?? []);
            unset($childRows['scannings'], $childRows['scan_reassignment_logs']);
            foreach ($childRows as $table => $rows) {
                $this->restoreRows($conn, (string) $table, (array) $rows, $restored, $skipped);
            }
            $this->restoreReassignLogs($conn, $reassignLogs, $restored, $skipped);

            // Only where the row is still unlinked: it may have been linked elsewhere since.
            $fileIndexingId = (string) $snapshot['file_indexing']['id'];
            foreach ((array) ($snapshot['detached_rows'] ?? []) as $table => $rows) {
                if (!Schema::connection('sqlsrv')->hasTable((string) $table)) {
                    continue;
                }
                $ids = array_values(array_filter(array_column((array) $rows, 'id')));
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $relinked[$table] = ($relinked[$table] ?? 0) + $conn->table((string) $table)
                        ->whereIn('id', $chunk)->whereNull('file_indexing_id')
                        ->update(['file_indexing_id' => $fileIndexingId]);
                }
            }

            $conn->table('indexing_duplicates')->where('id', $archiveId)->update([
                'restored_at' => now(),
                'restored_by' => $this->clip($actor, 150),
            ]);

            return true;
        });

        if (!$done) {
            return $this->previewReverse($archiveId);
        }

        $this->reattachKangis($snapshot['file_indexing']);

        Log::warning('Indexed file reversed out of indexing_duplicates', [
            'indexing_duplicate_id' => $archiveId,
            'file_number' => $archive->file_number,
            'restored' => $restored,
            'skipped' => $skipped,
            'relinked' => $relinked,
            'reversed_by' => $actor,
        ]);

        try {
            app(AuditService::class)->logAction(
                'RESTORED',
                'indexing_duplicate',
                $archiveId,
                ['temp_file_no' => $archive->temp_file_no, 'moved_at' => $archive->moved_at, 'moved_by' => $archive->moved_by],
                ['file_number' => $archive->file_number, 'restored' => $restored, 'skipped' => $skipped, 'relinked' => $relinked, 'reason' => $reason],
                sprintf('Reversed indexing duplicate %s back to the live tables', $archive->file_number)
            );
        } catch (\Throwable $e) {
            // Audit must never undo a reversal that has committed.
            Log::warning('IndexingDuplicateService: reverse audit write failed', ['error' => $e->getMessage()]);
        }

        return [
            'status' => 'reversed',
            'file_number' => $archive->file_number,
            'file_indexing_id' => (int) $snapshot['file_indexing']['id'],
            'restored' => $restored,
            'skipped' => $skipped,
            'relinked' => $relinked,
        ];
    }

    private function decodeSnapshot(object $archive): ?array
    {
        $snapshot = json_decode((string) $archive->snapshot, true);

        return is_array($snapshot) && !empty($snapshot['file_indexing']['id']) ? $snapshot : null;
    }

    /**
     * Live indexed files now holding this record's id or any of its numbers. The move
     * freed the number, so the same file indexed again shows up here.
     *
     * @return array<int,array{id:int,file_number:?string,file_title:?string}>
     */
    private function liveHolders(array $snapshot): array
    {
        $row = (object) $snapshot['file_indexing'];
        $numbers = array_values(array_unique(array_merge(
            array_values($this->collectFileNumbers($row)),
            array_values(array_filter(array_map(fn ($n) => trim((string) $n), (array) ($snapshot['matched_numbers'] ?? []))))
        )));
        $temp = trim((string) ($row->temp_file_no ?? ''));

        return DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($q) use ($row, $numbers, $temp) {
                $q->where('id', (int) $row->id);
                if (!empty($numbers)) {
                    foreach (['file_number', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no'] as $column) {
                        $q->orWhereIn($column, $numbers);
                    }
                }
                if ($temp !== '' && $temp !== '-') {
                    $q->orWhere('temp_file_no', $temp);
                }
            })
            ->orderBy('id')->limit(10)
            ->get(['id', 'file_number', 'file_title'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'file_number' => $r->file_number, 'file_title' => $r->file_title])
            ->all();
    }

    /** The snapshot's rows, counted in the deleted_counts shape summariseCounts() reads. */
    private function snapshotCounts(array $snapshot): array
    {
        $bucket = fn (string $key) => array_map(fn ($rows) => count((array) $rows), (array) ($snapshot[$key] ?? []));

        return [
            'file_indexings' => 1,
            'fileNumber' => count((array) ($snapshot['fileNumber'] ?? [])),
            'customers_staging' => count((array) ($snapshot['customers_staging'] ?? [])),
            'entities_staging' => count((array) ($snapshot['entities_staging'] ?? [])),
            'child_rows' => $bucket('child_rows'),
            'file_number_rows' => $bucket('file_number_rows'),
        ];
    }

    /** Scans, then their parent links: a parent can be a later row, or another file's scan that has gone. */
    private function restoreScans($conn, array $rows, array &$restored, array &$skipped): void
    {
        $parents = [];
        foreach ($rows as $i => $row) {
            if (!empty($row['parent_scan_id'])) {
                $parents[(int) $row['id']] = (int) $row['parent_scan_id'];
                $rows[$i]['parent_scan_id'] = null;
            }
        }

        $this->restoreRows($conn, 'scannings', $rows, $restored, $skipped);

        foreach ($parents as $id => $parentId) {
            if ($conn->table('scannings')->where('id', $parentId)->exists()) {
                $conn->table('scannings')->where('id', $id)->update(['parent_scan_id' => $parentId]);
            }
        }
    }

    /** Registry rows; one whose number is on a live row again stays out, so it is not doubled. */
    private function restoreFileNumberRows($conn, array $rows, array &$restored, array &$skipped): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $numbers = array_values(array_filter(array_map(
                fn ($column) => trim((string) ($row[$column] ?? '')),
                self::FILE_NUMBER_COLUMNS
            )));
            if (!empty($numbers) && !empty($this->findFileNumberRows($numbers, null))) {
                $skipped['fileNumber'] = ($skipped['fileNumber'] ?? 0) + 1;
                continue;
            }
            $keep[] = $row;
        }

        $this->restoreRows($conn, 'fileNumber', $keep, $restored, $skipped);
    }

    /** Log rows have foreign keys to files and scans that may be gone; those are left out. */
    private function restoreReassignLogs($conn, array $rows, array &$restored, array &$skipped): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $linked = true;
            foreach (['from_file_indexing_id' => 'file_indexings', 'to_file_indexing_id' => 'file_indexings', 'scanning_id' => 'scannings'] as $column => $table) {
                if (!empty($row[$column]) && !$conn->table($table)->where('id', (int) $row[$column])->exists()) {
                    $linked = false;
                }
            }
            if ($linked) {
                $keep[] = $row;
            } else {
                $skipped['scan_reassignment_logs'] = ($skipped['scan_reassignment_logs'] ?? 0) + 1;
            }
        }

        $this->restoreRows($conn, 'scan_reassignment_logs', $keep, $restored, $skipped);
    }

    /**
     * Rows back under their original ids. Columns the table no longer has, computed
     * columns and the moved_temp_file_no stamp are dropped; a row whose id is already
     * taken is skipped and counted. A table that is gone is counted as skipped too.
     */
    private function restoreRows($conn, string $table, array $rows, array &$restored, array &$skipped): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = $this->insertableColumns($conn, $table);
        if (empty($columns)) {
            $skipped[$table] = ($skipped[$table] ?? 0) + count($rows);
            return;
        }

        $taken = [];
        foreach (array_chunk(array_values(array_filter(array_column($rows, 'id'))), 1000) as $chunk) {
            foreach ($conn->table($table)->whereIn('id', $chunk)->pluck('id') as $id) {
                $taken[(int) $id] = true;
            }
        }

        $hasIdentity = in_array(true, $columns, true);
        $quoted = '[' . str_replace(']', ']]', $table) . ']';
        if ($hasIdentity) {
            $conn->unprepared("SET IDENTITY_INSERT {$quoted} ON");
        }

        try {
            foreach ($rows as $row) {
                if (isset($taken[(int) ($row['id'] ?? 0)])) {
                    $skipped[$table] = ($skipped[$table] ?? 0) + 1;
                    continue;
                }
                $conn->table($table)->insert(array_intersect_key((array) $row, $columns));
                $restored[$table] = ($restored[$table] ?? 0) + 1;
            }
        } finally {
            if ($hasIdentity) {
                try {
                    $conn->unprepared("SET IDENTITY_INSERT {$quoted} OFF");
                } catch (\Throwable $e) {
                    // The insert's own failure is the one worth reporting.
                    Log::warning('IndexingDuplicateService: IDENTITY_INSERT OFF failed', ['table' => $table, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** @return array<string,bool> column => is identity, computed and rowversion columns left out */
    private function insertableColumns($conn, string $table): array
    {
        $out = [];
        $rows = $conn->select(
            "select c.name, c.is_identity from sys.columns c join sys.types t on t.user_type_id = c.user_type_id
             where c.object_id = object_id(?) and c.is_computed = 0 and t.name not in ('timestamp', 'rowversion')",
            [$table]
        );
        foreach ($rows as $row) {
            $out[$row->name] = (bool) $row->is_identity;
        }

        return $out;
    }

    /** Undoes removeKangisFromLandFiles() for a KANGIS record back in the live table; a failure only logs. */
    private function reattachKangis(array $row): void
    {
        $indexing = \App\Services\FileIndexing\KangisBlockIndexing::class;
        if (!$indexing::isKangisRegistry($row['general_registry'] ?? null)) {
            return;
        }

        try {
            $service = app($indexing);
            $id = (int) $row['id'];
            $landFiles = $indexing::landFilesFrom($row['related_fileno'] ?? null);
            foreach ($landFiles as $landFile) {
                $service->addKangisToLandRelated($landFile, $id);
                $service->rebuildLandFile($landFile);
            }
            if (!empty($landFiles)) {
                app(RelatedFileNumberRegistrar::class)->attachKangis((string) ($row['file_number'] ?? ''), $id, $landFiles);
            }
        } catch (\Throwable $e) {
            Log::warning('IndexingDuplicateService: KANGIS land file re-link failed', [
                'file_indexing_id' => $row['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
