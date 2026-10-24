<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

/**
 * The MOV-DUP-##### system temp file no given to whatever a move takes out of the
 * live tables: a whole indexed file (Move to Indexing Duplicates, stored on
 * indexing_duplicates) or individual transactions (Move Transaction, stored on
 * moved_transactions). One series across both, so a number names one move only.
 */
class MoveTempFileNumber
{
    public const PREFIX = 'MOV-DUP-';

    /** Tables that hold issued numbers, all in temp_file_no. */
    private const ISSUERS = ['indexing_duplicates', 'moved_transactions'];

    /**
     * Next number: the highest issued plus one, gapless. The UPDLOCK/HOLDLOCK
     * reads make two moves running at once queue on each other instead of
     * reading the same maximum, so this MUST be called inside the move's
     * transaction, before the row carrying the number is inserted.
     */
    public static function next(ConnectionInterface $conn): string
    {
        $max = 0;

        foreach (self::ISSUERS as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $n = (int) $conn->selectOne(
                "SELECT MAX(TRY_CONVERT(INT, SUBSTRING(temp_file_no, 9, 20))) AS n
                   FROM {$table} WITH (UPDLOCK, HOLDLOCK)
                  WHERE temp_file_no LIKE 'MOV-DUP-%'"
            )->n;

            $max = max($max, $n);
        }

        return self::PREFIX . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }
}
