<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Move a captured transaction between a file's MAIN number and its TEMPORARY "(T)" number.
 *
 * A property's main file number (e.g. "RES-2025-10000") may have a temporary sibling
 * ("RES-2025-10000(T)"). Both are the same physical file, and Legal Search already returns the
 * transactions of either when either is searched (LegalSearchService::fileNumberVariants), but
 * each transaction must carry the number it was actually recorded under. Capture officers
 * sometimes key a "(T)" transaction against the main number, or the reverse. This service
 * re-points ONE transaction row from one number to the other.
 *
 * What it touches: the file-number columns that hold the main or "(T)" number (mlsFNo, fileno),
 * plus updated_at / updated_by. What it never touches: prop_id. The row stays on the same
 * property, and the call fails rather than write if prop_id moved underneath it.
 *
 * Scope: the PRA (pra), File History (file_history_staging) and CofO (CofO_staging) tables.
 * Every correction is written to audit_logs in the same sqlsrv transaction as the update.
 */
class TransactionFileNumberCorrectionService
{
    /** Source table => display label. Anything else is refused. */
    public const TABLES = [
        'pra' => 'PRA',
        'file_history_staging' => 'File History',
        'CofO_staging' => 'CofO',
    ];

    /** The columns a main / "(T)" MLS-style number is stored in on these tables. */
    private const NUMBER_COLUMNS = ['mlsFNo', 'fileno'];

    public const AUDIT_ACTION = 'TRANSACTION FILE NUMBER CORRECTED';

    /**
     * Strip a trailing "(T)" marker: "RES-2025-10000 (T)" -> "RES-2025-10000".
     * Same rule as LegalSearchService::fileNumberVariants().
     */
    public static function baseOf(string $fileNo): string
    {
        return trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', trim($fileNo)));
    }

    public static function isTemp(string $fileNo): bool
    {
        return (bool) preg_match('/\(\s*T\s*\)\s*$/i', trim($fileNo));
    }

    /**
     * @return array{main: string, temp: string}
     */
    public static function pairFor(string $fileNo): array
    {
        $base = self::baseOf($fileNo);

        return ['main' => $base, 'temp' => $base . '(T)'];
    }

    /**
     * The number a transaction is assigned to, as Legal Search reads it: COALESCE(mlsFNo, fileno).
     */
    public static function assignedNumber(object $row): ?string
    {
        foreach (self::NUMBER_COLUMNS as $column) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Everything the correction dialog needs for the transaction ($table, $id): the main / "(T)"
     * pair it belongs to, and every PRA / File History / CofO transaction recorded under either.
     *
     * @return array{
     *     main: string, temp: string, temp_registered: bool,
     *     selected: array{table: string, id: int},
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function candidates(string $table, int $id): array
    {
        $this->assertTable($table);

        $row = $this->conn()->table($table)->where('id', $id)->first();
        if (!$row) {
            throw new RuntimeException('Transaction not found.');
        }

        $current = self::assignedNumber($row);
        if ($current === null) {
            throw new RuntimeException('This transaction has no main or temporary file number to correct.');
        }

        $pair = self::pairFor($current);

        return [
            'main' => $pair['main'],
            'temp' => $pair['temp'],
            'temp_registered' => $this->tempIsRegistered($pair),
            'selected' => ['table' => $table, 'id' => $id],
            'transactions' => $this->transactionsFor($pair),
        ];
    }

    /**
     * Re-point one transaction to the main ($target = 'main') or temporary ($target = 'temp')
     * number of its file. prop_id is left exactly as it is.
     *
     * @return array{table: string, id: int, prop_id: ?string, from: string, to: string}
     */
    public function correct(string $table, int $id, string $target, string $reason = '', $userId = null): array
    {
        $this->assertTable($table);

        if (!in_array($target, ['main', 'temp'], true)) {
            throw new RuntimeException('Target must be the main or the temporary file number.');
        }

        // Reason is optional (the dialog no longer asks for one); kept in the audit row when given.
        $reason = trim($reason);

        $conn = $this->conn();

        return $conn->transaction(function () use ($conn, $table, $id, $target, $reason, $userId) {
            $row = $conn->table($table)->where('id', $id)->lockForUpdate()->first();
            if (!$row) {
                throw new RuntimeException('Transaction not found.');
            }
            if ($this->isDeletedRow($row)) {
                throw new RuntimeException('This transaction has been deleted and cannot be corrected.');
            }

            $current = self::assignedNumber($row);
            if ($current === null) {
                throw new RuntimeException('This transaction has no main or temporary file number to correct.');
            }

            $pair = self::pairFor($current);
            $newNo = $pair[$target];

            // No "(T) must already exist" check: splitting a file's transactions onto a new
            // "(T)" number is the point of this tool, and the first move is what starts it.

            // Only columns that already hold this file's main or "(T)" number are rewritten.
            // A KANGIS number, an unrelated legacy number, or an empty column stays as it is.
            $variants = array_map('strtoupper', array_values($pair));
            $updates = [];
            $old = [];
            foreach (self::NUMBER_COLUMNS as $column) {
                $value = trim((string) ($row->{$column} ?? ''));
                if ($value === '') {
                    continue;
                }
                $normalized = strtoupper(self::isTemp($value) ? self::baseOf($value) . '(T)' : $value);
                if (in_array($normalized, $variants, true) && $value !== $newNo) {
                    $old[$column] = $row->{$column};
                    $updates[$column] = $newNo;
                }
            }

            if (empty($updates)) {
                throw new RuntimeException("This transaction is already recorded under {$newNo}.");
            }

            $propIdBefore = $row->prop_id ?? null;

            $updates['updated_at'] = now()->format('Y-m-d H:i:s');
            if ($userId !== null && Schema::connection('sqlsrv')->hasColumn($table, 'updated_by')) {
                $updates['updated_by'] = (string) $userId;
            }

            $conn->table($table)->where('id', $id)->update($updates);

            $propIdAfter = $conn->table($table)->where('id', $id)->value('prop_id');
            if ((string) $propIdAfter !== (string) $propIdBefore) {
                // A trigger or a concurrent writer moved the row to another property.
                throw new RuntimeException('prop_id changed during the correction; nothing was saved.');
            }

            // AuditLog casts old_values/new_values to json itself; AuditService::logAction()
            // json_encodes first, which stores a double-encoded string.
            AuditLog::create([
                'user_id' => $userId,
                'action' => self::AUDIT_ACTION,
                'resource_type' => $table,
                'resource_id' => $id,
                'old_values' => $old + ['prop_id' => $propIdBefore],
                'new_values' => array_intersect_key($updates, array_flip(self::NUMBER_COLUMNS)) + [
                    'prop_id' => $propIdBefore,
                    'from' => $current,
                    'to' => $newNo,
                    'reason' => $reason !== '' ? $reason : null,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'table' => $table,
                'id' => $id,
                'prop_id' => $propIdBefore !== null ? (string) $propIdBefore : null,
                'from' => $current,
                'to' => $newNo,
            ];
        });
    }

    /**
     * Whether the "(T)" number is already known: to indexing, to fileNumber or to PropID_Master,
     * or carrying transactions of its own. Informational only. The dialog uses it to say that
     * a move will start the "(T)" number; it does not block the move.
     *
     * @param  array{main: string, temp: string}  $pair
     */
    public function tempIsRegistered(array $pair): bool
    {
        $conn = $this->conn();
        $temp = $pair['temp'];
        $main = $pair['main'];

        $indexed = $conn->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($temp, $main) {
                $q->where('file_number', $temp)
                    ->orWhere('temp_file_no', $temp)
                    ->orWhere(function ($qq) use ($main) {
                        $qq->where('file_number', $main)->where('has_temp_file', 1);
                    });
            })
            ->exists();
        if ($indexed) {
            return true;
        }

        $numbered = $conn->table('fileNumber')
            ->where(function ($q) use ($temp, $main) {
                $q->where('mlsfNo', $temp)
                    ->orWhere('temp_file_no', $temp)
                    ->orWhere(function ($qq) use ($main) {
                        $qq->where('mlsfNo', $main)->where('has_temp_file', 1);
                    });
            })
            ->exists();
        if ($numbered) {
            return true;
        }

        $mastered = $conn->table('PropID_Master')
            ->where(function ($q) use ($temp) {
                $q->where('temp_fileno', $temp)->orWhere('mlsFNo', $temp)->orWhere('primary_file_number', $temp);
            })
            ->exists();
        if ($mastered) {
            return true;
        }

        foreach (array_keys(self::TABLES) as $table) {
            $query = $conn->table($table)->where(function ($q) use ($temp) {
                foreach (self::NUMBER_COLUMNS as $column) {
                    $q->orWhere($column, $temp);
                }
            });
            $this->applyNotDeleted($query);
            if ($query->exists()) {
                return true;
            }
        }

        return $conn->table('deed_registrations')->where('fileno', $temp)->exists();
    }

    /**
     * @param  array{main: string, temp: string}  $pair
     * @return list<array<string, mixed>>
     */
    private function transactionsFor(array $pair): array
    {
        $conn = $this->conn();
        $variants = array_values($pair);
        $rows = [];

        foreach (self::TABLES as $table => $label) {
            $query = $conn->table($table)
                ->where(function ($q) use ($variants) {
                    foreach (self::NUMBER_COLUMNS as $column) {
                        $q->orWhereIn($column, $variants);
                    }
                })
                ->select([
                    'id', 'mlsFNo', 'fileno', 'prop_id', 'transaction_type', 'instrument_type', 'transaction_date',
                    'party_1', 'party_2', 'Assignor', 'Assignee', 'Grantor', 'Grantee', 'Mortgagor', 'Mortgagee',
                    'regNo', 'serialNo', 'pageNo', 'volumeNo',
                ]);
            $this->applyNotDeleted($query);

            foreach ($query->orderBy('id')->get() as $row) {
                $assigned = self::assignedNumber($row);
                $registration = trim((string) ($row->regNo ?? ''));
                if ($registration === '' && ($row->serialNo || $row->pageNo || $row->volumeNo)) {
                    $registration = ($row->serialNo ?: '0') . '/' . ($row->pageNo ?: '0') . '/' . ($row->volumeNo ?: '0');
                }

                $rows[] = [
                    'table' => $table,
                    'source' => $label,
                    'id' => (int) $row->id,
                    'file_number' => $assigned,
                    'is_temp' => $assigned !== null && self::isTemp($assigned),
                    'prop_id' => $row->prop_id !== null ? (string) $row->prop_id : null,
                    'transaction_type' => $row->transaction_type ?: $row->instrument_type,
                    'transaction_date' => $row->transaction_date,
                    'party_1' => $row->party_1 ?: ($row->Assignor ?: ($row->Grantor ?: $row->Mortgagor)),
                    'party_2' => $row->party_2 ?: ($row->Assignee ?: ($row->Grantee ?: $row->Mortgagee)),
                    'registration' => $registration !== '' ? $registration : null,
                ];
            }
        }

        return $rows;
    }

    private function isDeletedRow(object $row): bool
    {
        $flag = strtolower(trim((string) ($row->is_deleted ?? '')));

        return in_array($flag, ['1', 'true', 'yes'], true);
    }

    /** Same rule Legal Search applies to these tables (LegalSearchService::applySoftDeleteFilter). */
    private function applyNotDeleted($query): void
    {
        $query->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });
    }

    private function assertTable(string $table): void
    {
        if (!array_key_exists($table, self::TABLES)) {
            throw new RuntimeException('Unsupported transaction source.');
        }
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }
}
