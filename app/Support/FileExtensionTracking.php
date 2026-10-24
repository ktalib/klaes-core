<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tracking IDs for extensions (Change Request 22102026, item 23).
 *
 * An extension is its own file: a File Extension carries its own number
 * ("RES-2025-23 AND EXTENSION"), a Plot Extension keeps the mother's number. Neither
 * has a grouping row of its own, so both used to borrow the MOTHER's tracking ID from
 * grouping — every join keyed on tracking ID then mixed the two files up, and the
 * extension's number was written onto the mother's grouping row. Each extension now
 * gets a tracking ID generated on the fly instead, and never touches the mother's
 * grouping row.
 */
class FileExtensionTracking
{
    /** "X AND EXTENSION" — a File Extension number. */
    public static function isExtensionNumber(?string $fileNumber): bool
    {
        return (bool) preg_match('/\s+AND\s+EXTENSION\s*$/i', trim((string) $fileNumber));
    }

    /** The mother's number for a File Extension number. */
    public static function baseNumber(?string $fileNumber): string
    {
        return trim((string) preg_replace('/\s*AND\s+EXTENSION\s*$/i', '', trim((string) $fileNumber)));
    }

    /**
     * The tracking ID this extension number already has on its OWN rows
     * (fileNumber / mls_file_no / file_indexings), if it differs from the mother's;
     * null when it has none of its own yet.
     */
    public static function existingFor(string $extensionNumber): ?string
    {
        $db = DB::connection('sqlsrv');
        $base = self::baseNumber($extensionNumber);
        $motherIds = collect([
            $db->table('grouping')->where('awaiting_fileno', $base)->pluck('tracking_id'),
            $db->table('fileNumber')->where('mlsfNo', $base)->pluck('tracking_id'),
            $db->table('file_indexings')->where('file_number', $base)->pluck('tracking_id'),
        ])->flatten()->filter()->map(fn ($v) => strtoupper(trim($v)))->unique()->all();

        foreach ([
            ['fileNumber', 'mlsfNo'],
            ['mls_file_no', 'full_file_number'],
            ['file_indexings', 'file_number'],
        ] as [$table, $column]) {
            $own = $db->table($table)->where($column, $extensionNumber)
                ->whereNotNull('tracking_id')->where('tracking_id', '!=', '')
                ->orderByDesc('id')->pluck('tracking_id')
                ->first(fn ($id) => !in_array(strtoupper(trim($id)), $motherIds, true));
            if ($own) {
                return trim($own);
            }
        }

        return null;
    }

    /**
     * The extension's own tracking ID; else the one the form showed (when it is a
     * well-formed ID that neither the mother nor any other file uses); else a fresh one.
     */
    public static function resolveFor(string $extensionNumber, ?string $preferred = null): string
    {
        $own = self::existingFor($extensionNumber);
        if ($own) {
            return $own;
        }

        $preferred = strtoupper(trim((string) $preferred));
        if ($preferred !== '' && preg_match('/^TRK-[A-Z0-9]{8}-[A-Z0-9]{5}$/', $preferred) && self::isFree($preferred, $extensionNumber)) {
            return $preferred;
        }

        return self::generate();
    }

    /** Used by no grouping row and by no file record other than this extension's own. */
    private static function isFree(string $trackingId, string $extensionNumber): bool
    {
        $db = DB::connection('sqlsrv');
        if ($db->table('grouping')->where('tracking_id', $trackingId)->exists()
            || $db->table('plot_extensions')->where('tracking_id', $trackingId)->exists()) {
            return false;
        }
        foreach ([['fileNumber', 'mlsfNo'], ['mls_file_no', 'full_file_number'], ['file_indexings', 'file_number']] as [$table, $column]) {
            if ($db->table($table)->where('tracking_id', $trackingId)->where($column, '!=', $extensionNumber)->exists()) {
                return false;
            }
        }
        return true;
    }

    /** A TRK-XXXXXXXX-XXXXX not used by any file record or grouping row. */
    public static function generate(): string
    {
        $db = DB::connection('sqlsrv');
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $pick = function (int $n) use ($alphabet) {
            $s = '';
            for ($i = 0; $i < $n; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            return $s;
        };

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'TRK-' . $pick(8) . '-' . $pick(5);
            $taken = false;
            foreach (['grouping', 'fileNumber', 'mls_file_no', 'file_indexings', 'plot_extensions'] as $table) {
                if ($db->table($table)->where('tracking_id', $candidate)->exists()) {
                    $taken = true;
                    break;
                }
            }
            if (!$taken) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not generate a unique tracking ID.');
    }
}
