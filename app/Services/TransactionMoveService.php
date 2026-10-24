<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Move Transaction" on Indexed Files (user, 2026-10-24).
 *
 * Sometimes a file carries a transaction that is not in that physical file, or was
 * entered wrongly. This lists the file's transactions the way the Property Timeline
 * / Legal Search show them, and moves the ones the operator ticks: each is given a
 * system temp file no (the MOV-DUP-##### series shared with Move to Indexing
 * Duplicates), snapshotted whole into moved_transactions, and deleted from its
 * source table. The file itself and its other transactions are not touched.
 *
 * Only real captured rows move. Rows the operator sees but cannot move are listed
 * greyed out with the reason: the timeline's own derived rows (commissioning,
 * temporary file, decommissioning, related file links), registered deeds, and rows
 * recorded under a different file number (a linked file's dealing the timeline
 * folds in — moving it from here would strip the other file).
 */
class TransactionMoveService
{
    /** Source tables a transaction can be moved out of => label. */
    public const MOVABLE_SOURCES = [
        'pra'                  => 'PRA',
        'file_history_staging' => 'File History',
        'CofO_staging'         => 'CofO',
    ];

    /** Columns on the movable sources that can hold the row's file number. */
    private const FILE_NUMBER_COLUMNS = ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'];

    /** file_indexings columns that name the file. */
    private const INDEXING_NUMBER_COLUMNS = ['file_number', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no', 'temp_file_no'];

    public function __construct(private LegalSearchService $legalSearch)
    {
    }

    /**
     * The file's timeline rows, each flagged movable or not (with the reason).
     *
     * @return array{status:string, file?:array, rows?:array<int,array>}
     */
    public function listForFile(int $fileIndexingId): array
    {
        $file = DB::connection('sqlsrv')->table('file_indexings')->where('id', $fileIndexingId)->first();
        if (!$file) {
            return ['status' => 'not_found'];
        }

        $numbers = $this->fileNumbers($file);
        $timeline = $this->legalSearch->timelineRowsForFileNumber((string) $file->file_number);

        // Load every referenced source row in one query per table, so each
        // timeline row can be checked against the record it would actually delete.
        $ids = [];
        foreach ($timeline as $row) {
            $table = (string) ($row['source_table'] ?? '');
            $id = (int) ($row['source_id'] ?? 0);
            if (isset(self::MOVABLE_SOURCES[$table]) && $id > 0) {
                $ids[$table][$id] = $id;
            }
        }

        $sources = [];
        foreach ($ids as $table => $tableIds) {
            $sources[$table] = DB::connection('sqlsrv')->table($table)
                ->whereIn('id', array_values($tableIds))
                ->get()
                ->keyBy('id')
                ->all();
        }

        $rows = [];
        foreach ($timeline as $i => $row) {
            $table = (string) ($row['source_table'] ?? '');
            $id = (int) ($row['source_id'] ?? 0);
            $source = $sources[$table][$id] ?? null;

            [$movable, $blockedReason] = $this->assessRow($table, $id, $source, $numbers);

            $blank = static fn ($v) => (trim((string) $v) === '-' ? '' : trim((string) $v));

            $rows[] = [
                'key'              => $movable ? $table . ':' . $id : null,
                'sn'               => $i + 1,
                'source_table'     => $table,
                'source_label'     => self::MOVABLE_SOURCES[$table] ?? ($table === 'deed_registrations' ? 'Deed Reg.' : $table),
                'source_id'        => $id > 0 ? $id : null,
                'file_no'          => $blank($row['file_no'] ?? ''),
                'transaction_type' => $blank($row['instrument_type'] ?? ''),
                'party_1'          => $blank($row['grantor'] ?? ''),
                'party_2'          => $blank($row['grantee'] ?? ''),
                'transaction_date' => $blank($row['transaction_date'] ?? '') ?: $blank($row['reg_date'] ?? ''),
                'reg_no'           => $blank($row['reg_no'] ?? ''),
                'root_of_title'    => $blank($row['root_of_title'] ?? ''),
                'movable'          => $movable,
                'blocked_reason'   => $blockedReason,
            ];
        }

        return [
            'status' => 'ok',
            'file'   => [
                'id'          => (int) $file->id,
                'file_number' => $file->file_number,
                'file_title'  => $file->file_title ?? null,
                'numbers'     => array_values($numbers),
            ],
            'rows'   => $rows,
        ];
    }

    /**
     * Move the selected transactions. Every key must be a movable row of THIS
     * file's current timeline — the list is rebuilt here rather than trusting the
     * client, so a stale or forged key cannot delete another file's record.
     *
     * @param array<int,string> $keys "table:id"
     * @return array{status:string, ...} 'moved', 'not_found' or 'invalid'
     */
    public function move(int $fileIndexingId, array $keys, string $reason, ?string $belongsTo = null): array
    {
        $listing = $this->listForFile($fileIndexingId);
        if ($listing['status'] !== 'ok') {
            return $listing;
        }

        $keys = array_values(array_unique(array_filter(array_map('trim', $keys))));
        if (empty($keys)) {
            return ['status' => 'invalid', 'message' => 'Select at least one transaction to move.'];
        }

        $byKey = [];
        foreach ($listing['rows'] as $row) {
            if ($row['movable']) {
                $byKey[$row['key']] = $row;
            }
        }

        $unknown = array_values(array_filter($keys, fn ($k) => !isset($byKey[$k])));
        if (!empty($unknown)) {
            return [
                'status'  => 'invalid',
                'message' => 'Some selected transactions can no longer be moved (they changed or were moved by someone else). Reload and try again.',
                'keys'    => $unknown,
            ];
        }

        $file = $listing['file'];
        $conn = DB::connection('sqlsrv');
        $actor = $this->actorName();
        $tempNo = null;
        $moved = [];

        $conn->transaction(function () use ($conn, $keys, $byKey, $file, $reason, $belongsTo, $actor, &$tempNo, &$moved) {
            $tempNo = MoveTempFileNumber::next($conn);

            foreach ($keys as $key) {
                $row = $byKey[$key];
                $table = $row['source_table'];
                $id = (int) $row['source_id'];

                // Re-read under lock: the snapshot is the row as it is at delete time.
                $source = $conn->table($table)->lock('WITH (UPDLOCK, ROWLOCK)')->where('id', $id)->first();
                if (!$source) {
                    throw new \RuntimeException("{$row['source_label']} record #{$id} no longer exists.");
                }

                $snapshot = (array) $source;
                $snapshot['moved_temp_file_no'] = $tempNo;

                $movedId = $conn->table('moved_transactions')->insertGetId([
                    'temp_file_no'     => $tempNo,
                    'file_indexing_id' => $file['id'],
                    'file_number'      => $this->clip($file['file_number'], 100),
                    'source_table'     => $table,
                    'source_id'        => $id,
                    'transaction_type' => $this->clip($row['transaction_type'], 255),
                    'party_1'          => $this->clip($row['party_1'], 500),
                    'party_2'          => $this->clip($row['party_2'], 500),
                    'transaction_date' => $this->clip($row['transaction_date'], 50),
                    'reg_no'           => $this->clip($row['reg_no'], 50),
                    'belongs_to'       => $this->clip($belongsTo, 100),
                    'reason'           => $this->clip($reason, 500),
                    'snapshot'         => json_encode($snapshot),
                    'moved_by'         => $this->clip($actor, 150),
                    'moved_by_id'      => Auth::id(),
                    'moved_at'         => now(),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $deleted = $conn->table($table)->where('id', $id)->delete();
                if ($deleted !== 1) {
                    throw new \RuntimeException("{$row['source_label']} record #{$id} could not be deleted.");
                }

                $moved[] = [
                    'moved_transaction_id' => $movedId,
                    'source_table'         => $table,
                    'source_label'         => $row['source_label'],
                    'source_id'            => $id,
                    'transaction_type'     => $row['transaction_type'],
                    'reg_no'               => $row['reg_no'],
                ];
            }
        });

        $this->audit($file, $tempNo, $moved, $reason, $belongsTo);

        Log::warning('Transactions moved off indexed file', [
            'file_indexing_id' => $file['id'],
            'file_number'      => $file['file_number'],
            'temp_file_no'     => $tempNo,
            'moved'            => $moved,
            'moved_by'         => $actor,
        ]);

        return [
            'status'       => 'moved',
            'temp_file_no' => $tempNo,
            'file_number'  => $file['file_number'],
            'moved'        => $moved,
        ];
    }

    /** @return array{0:bool,1:?string} [movable, reason it is not] */
    private function assessRow(string $table, int $id, ?object $source, array $numbers): array
    {
        if ($table === 'deed_registrations') {
            return [false, 'Registered deed — cannot be moved here'];
        }

        if (!isset(self::MOVABLE_SOURCES[$table]) || $id <= 0) {
            return [false, 'Generated by the timeline — no record of its own'];
        }

        if (!$source) {
            return [false, 'Record not found'];
        }

        if ((int) ($source->is_deleted ?? 0) === 1) {
            return [false, 'Already deleted'];
        }

        // The row must be recorded under one of this file's own numbers. The
        // timeline also folds in a linked file's dealings (mother file, KANGIS
        // alias); moving one of those from here would strip the other file.
        foreach (self::FILE_NUMBER_COLUMNS as $column) {
            $value = $this->norm($source->{$column} ?? '');
            if ($value !== '' && isset($numbers[$value])) {
                return [true, null];
            }
        }

        $recordedUnder = '';
        foreach (self::FILE_NUMBER_COLUMNS as $column) {
            $recordedUnder = trim((string) ($source->{$column} ?? ''));
            if ($recordedUnder !== '') {
                break;
            }
        }

        return [false, 'Recorded under another file' . ($recordedUnder !== '' ? " ({$recordedUnder})" : '')];
    }

    /** @return array<string,string> normalised number => as stored */
    private function fileNumbers(object $file): array
    {
        $numbers = [];
        foreach (self::INDEXING_NUMBER_COLUMNS as $column) {
            $value = trim((string) ($file->{$column} ?? ''));
            if ($value !== '' && $value !== '-') {
                $numbers[$this->norm($value)] = $value;
            }
        }

        return $numbers;
    }

    /**
     * Upper-case and trim only. Separators are significant ("KN 1" is a legacy land
     * file, "KN1" a New KANGIS file), so they are never collapsed.
     */
    private function norm($value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function audit(array $file, ?string $tempNo, array $moved, string $reason, ?string $belongsTo): void
    {
        try {
            app(AuditService::class)->logAction(
                'DELETED',
                'moved_transaction',
                $file['id'],
                ['file_indexing_id' => $file['id'], 'file_number' => $file['file_number']],
                ['temp_file_no' => $tempNo, 'moved' => $moved, 'reason' => $reason, 'belongs_to' => $belongsTo],
                sprintf('Moved %d transaction(s) off %s as %s', count($moved), $file['file_number'], $tempNo)
            );
        } catch (\Throwable $e) {
            // Audit must never undo a committed move.
            Log::warning('TransactionMoveService: audit write failed', ['error' => $e->getMessage()]);
        }
    }

    private function actorName(): string
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

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
