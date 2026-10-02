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
 * mls_file_no is deliberately NOT part of the cascade — deleting the commissioning
 * row would de-commission the file and change its Legal Search timeline. When one
 * exists it is recorded as retained instead.
 */
class IndexingDuplicateService
{
    /**
     * References that represent real work: documents, money, or a file in
     * circulation. A duplicate carrying any of these is refused rather than
     * silently destroyed.
     *
     * scan_reassignment_logs is included because its two foreign keys on
     * file_indexings are NO_ACTION — the delete would fail on the constraint anyway,
     * and a clear message beats a rolled-back SQL error.
     */
    private const BLOCKING_REFERENCES = [
        'scannings'              => 'scanned document(s)',
        'blind_scannings'        => 'blind scan(s)',
        'thumbnails'             => 'thumbnail(s)',
        'pagetypings'            => 'page typing record(s)',
        'file_indexing_bills'    => 'bill(s)',
        'spa_applications'       => 'SPAS application(s)',
    ];

    /**
     * Tracker rows are deliberately neither blocked on nor deleted: the movement
     * history is the record of where the physical file went, and that stays true
     * whether or not the file turned out to be a duplicate.
     *
     * Keeping them is safe because both tables are self-contained — they carry
     * tracking_id, current_location, current_handler and movement_history of their
     * own, so a row stays readable after its file_indexings row is gone. They are
     * recorded in the snapshot as retained so the archive shows the link.
     */
    private const RETAINED_REFERENCES = [
        'indexed_file_trackers' => 'file tracker record(s)',
        'file_trackings'        => 'file tracking movement(s)',
    ];

    /**
     * Queue / link / derived rows that would be orphaned by the delete. These are
     * regenerable, so they are snapshotted and removed with the record.
     */
    private const CLEANABLE_REFERENCES = [
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
        'file_indexing_staging' => [
            'label'       => 'Staged indexing upload(s)',
            'columns'     => ['file_number'],
            'transaction' => false,
        ],
    ];

    /**
     * Record / transaction tables keyed by file number rather than by
     * file_indexing_id. They are never touched by the move — a dealing that was
     * registered against the file stays registered — but the operator has to see
     * them before confirming, because a file carrying transactions is usually the
     * ORIGINAL rather than the duplicate.
     *
     * @var array<string,array{label:string,columns:array<int,string>}>
     */
    private const TRANSACTION_TABLES = [
        'file_history_staging' => [
            'label'   => 'File history (FH)',
            'columns' => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
        ],
        'file_tracker' => [
            'label'   => 'File tracker requests',
            'columns' => ['file_number'],
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

        $blocking = $this->countBlockingReferences($fileIndexingId);
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

        // Kept on purpose. Trackers key off file_indexing_id; the transaction
        // tables key off the file number(s).
        $retained = [];
        foreach (self::RETAINED_REFERENCES as $table => $label) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            $this->addPreviewRow(
                $retained,
                $table,
                $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count(),
                ucfirst($label)
            );
        }

        foreach (self::TRANSACTION_TABLES as $table => $meta) {
            $this->addPreviewRow($retained, $table, $this->countByFileNumber($table, $meta['columns'], $numbers), $meta['label']);
        }

        $transactionTotal = 0;
        foreach ($retained as $entry) {
            if (isset(self::TRANSACTION_TABLES[$entry['table']])) {
                $transactionTotal += $entry['count'];
            }
        }

        return [
            'status'                    => empty($blocking) ? 'ok' : 'blocked',
            'file_number'               => $row->file_number,
            'file_title'                => $row->file_title ?? null,
            'matched_numbers'           => $numbers,
            'blocking'                  => $blocking,
            'deletions'                 => array_values($deletions),
            'retained'                  => array_values($retained),
            'transaction_total'         => $transactionTotal,
            'deleted_transaction_total' => $deletedTransactionTotal,
        ];
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
            'label' => $label ?? self::DELETION_LABELS[$table] ?? $this->humanizeTable($table),
            'count' => $count,
        ];
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
     * Still refuses a file carrying scans, page typings, bills or SPAS applications
     * (BLOCKING_REFERENCES) — being a Supper Admin does not make destroying scanned
     * documents safe, and scan_reassignment_logs' NO_ACTION foreign key would fail
     * the delete anyway.
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
        if (!$row) {
            // Already moved by someone else in a parallel session? Report that
            // instead of a bare "not found" so the operator understands. Only the
            // move flow can say this — a purge leaves no archive row to find, so for
            // it a missing record is simply gone.
            $archived = $archive
                ? $conn->table('indexing_duplicates')->where('file_indexing_id', $fileIndexingId)->first()
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

        $blocking = $this->countBlockingReferences($fileIndexingId);
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
        $retained     = $this->countRetainedReferences($fileIndexingId);

        // Record rows keyed by file number that move with the record. Snapshotted
        // here, before the transaction deletes them.
        $fileNumberRowsToDelete = $this->collectFileNumberDeletionRows($numbers);

        // Dealings registered against the file number. Left in place, but recorded
        // so the archive shows what the moved record was still attached to.
        $transactions = [];
        foreach (self::TRANSACTION_TABLES as $table => $meta) {
            $count = $this->countByFileNumber($table, $meta['columns'], $numbers);
            if ($count > 0) {
                $transactions[$table] = $count;
            }
        }

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
            'child_rows'        => $cleanable,
            // pra / CofO_staging / file_indexing_staging — deleted with the
            // record, full rows kept here so the move stays restorable.
            'file_number_rows'  => $fileNumberRowsToDelete,
            'matched_numbers'   => $numbers,
            'mls_file_no_retained' => $retainedMls,
            // Left in place on purpose — see RETAINED_REFERENCES.
            'retained_references'  => $retained,
            // Left in place on purpose — see TRANSACTION_TABLES.
            'transaction_references' => $transactions,
        ];

        $archiveId = $conn->transaction(function () use (
            $conn, $row, $fileIndexingId, $numbers, $fileNumberRows,
            $retainedMls, $actor, $reason, $duplicateOf, $archive, $snapshot, &$counts
        ) {
            $archiveId = null;

            if ($archive) {
                // Clone the record column-for-column. indexing_duplicates mirrors
                // file_indexings, so the archive lines up with the indexed files table
                // and a restore is a straight column-to-column insert. Anything the
                // archive does not have a column for is dropped (it survives in the
                // snapshot), which keeps this working if the two tables drift.
                $archiveRow = array_intersect_key((array) $row, array_flip($this->archiveColumns()));

                // created_at / updated_at stay as the ORIGINAL indexing timestamps —
                // the move has its own moved_at.
                unset($archiveRow['id']);

                $archiveRow += [
                    'file_indexing_id'     => $fileIndexingId,
                    'duplicate_of'         => $this->clip($duplicateOf, 100),
                    'reason'               => $this->clip($reason, 500),
                    'mls_file_no_retained' => $retainedMls ? 1 : 0,
                    'snapshot'             => json_encode($snapshot),
                    'deleted_counts'       => null,
                    'moved_by'             => $this->clip($actor, 150),
                    'moved_by_id'          => Auth::id(),
                    'moved_at'             => now(),
                ];

                // Archive first: if any delete below fails the whole move rolls back,
                // archive row included.
                $archiveId = $conn->table('indexing_duplicates')->insertGetId($archiveRow);
            }

            // Child queue/link rows first, so nothing is left pointing at a
            // file_indexings row that is about to disappear.
            $childCounts = [];
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
            ];

            $fileNumberIds = array_values(array_filter(array_map(fn ($r) => $r->id ?? null, $fileNumberRows)));
            if (!empty($fileNumberIds)) {
                $counts['fileNumber'] = $conn->table('fileNumber')->whereIn('id', $fileNumberIds)->delete();
            }

            $counts['file_indexings'] = $conn->table('file_indexings')->where('id', $fileIndexingId)->delete();

            if ($archive) {
                $conn->table('indexing_duplicates')
                    ->where('id', $archiveId)
                    ->update(['deleted_counts' => json_encode($counts)]);
            }

            return $archiveId;
        });

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
            $this->writeAudit($archiveId, $row, $counts, $reason);
        }

        return [
            'status' => $archive ? 'moved' : 'purged',
            'indexing_duplicate_id' => $archiveId,
            'file_number' => $row->file_number,
            'file_title' => $row->file_title ?? null,
            'counts' => $counts,
            'matched_numbers' => $numbers,
            'mls_file_no_retained' => $retainedMls,
            'retained_references' => $retained,
            'transaction_references' => $transactions,
            // Only a purge needs this back — nothing else survives it.
            'snapshot' => $archive ? null : $snapshot,
        ];
    }

    /** @return array<string,string> table => human count, only for tables with hits */
    private function countBlockingReferences(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $found = [];

        foreach (self::BLOCKING_REFERENCES as $table => $label) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $count = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count();
            if ($count > 0) {
                $found[$table] = $count . ' ' . $label;
            }
        }

        // Two NO_ACTION foreign keys, on two different columns.
        if (Schema::connection('sqlsrv')->hasTable('scan_reassignment_logs')) {
            $count = $conn->table('scan_reassignment_logs')
                ->where('from_file_indexing_id', $fileIndexingId)
                ->orWhere('to_file_indexing_id', $fileIndexingId)
                ->count();
            if ($count > 0) {
                $found['scan_reassignment_logs'] = $count . ' scan reassignment log(s)';
            }
        }

        return $found;
    }

    /**
     * Count the tracker rows left in place, so the archive records that the file's
     * movement history survives the move.
     *
     * @return array<string,int>
     */
    private function countRetainedReferences(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $found = [];

        foreach (array_keys(self::RETAINED_REFERENCES) as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $count = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count();
            if ($count > 0) {
                $found[$table] = $count;
            }
        }

        return $found;
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

    private function writeAudit(int $archiveId, object $row, array $counts, ?string $reason): void
    {
        try {
            app(AuditService::class)->logAction(
                'DELETED',
                'indexing_duplicate',
                $archiveId,
                ['file_indexing_id' => $row->id, 'file_number' => $row->file_number],
                ['counts' => $counts, 'reason' => $reason],
                sprintf('Moved indexed file %s to indexing duplicates', $row->file_number)
            );
        } catch (\Throwable $e) {
            // Audit must never block the move.
            Log::warning('IndexingDuplicateService: audit write failed', ['error' => $e->getMessage()]);
        }
    }
}
