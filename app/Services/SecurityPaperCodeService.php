<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single place where a security paper code is released from a document.
 *
 * Land RofO, SLTR RofO and ST/Programmes RofO all draw from the same
 * `global_security_paper_codes` pool, so the release rules have to agree —
 * otherwise a sheet voided on one screen reappears as available on another.
 */
class SecurityPaperCodeService
{
    public const REASON_MISTAKE_OUTPUT = 'mistake_output';
    public const REASON_SPC_MISMATCH   = 'spc_mismatch';
    public const REASON_DROP_SPC       = 'drop_spc';

    /**
     * Reason => [label, returns the code to the pool?]
     * Mutilated/mis-printed sheets are physically unusable, so they are retired.
     */
    public const REASONS = [
        self::REASON_MISTAKE_OUTPUT => ['label' => 'Mistake on Output / Mutilated Paper', 'returns_to_pool' => false],
        self::REASON_SPC_MISMATCH   => ['label' => 'SPC Mismatch',                        'returns_to_pool' => true],
        self::REASON_DROP_SPC       => ['label' => 'Drop SPC',                            'returns_to_pool' => true],
    ];

    public static function isValidReason(?string $reason): bool
    {
        return $reason !== null && array_key_exists($reason, self::REASONS);
    }

    public static function returnsToPool(string $reason): bool
    {
        return self::REASONS[$reason]['returns_to_pool'] ?? true;
    }

    public static function label(?string $reason): string
    {
        return self::REASONS[$reason]['label'] ?? (string) $reason;
    }

    /**
     * Release $paperCode from whatever document held it.
     *
     * Callers own the surrounding transaction and are responsible for clearing
     * the serial column on their own record — this only touches the shared pool
     * and the `security_codes` tracking table.
     *
     * @param string      $paperCode    the code being released
     * @param string      $reason       one of the REASON_* constants
     * @param string|null $assignedTo   `security_codes.assigned_to` value for this screen
     * @param string|null $note         optional free-text detail
     */
    public static function release(string $paperCode, string $reason, ?string $assignedTo = null, ?string $note = null): void
    {
        if (!self::isValidReason($reason)) {
            throw new \InvalidArgumentException("Unknown security paper reset reason [{$reason}].");
        }

        $connection = DB::connection('sqlsrv');

        if (self::returnsToPool($reason)) {
            // The sheet is intact — put the code back and wipe the assignment.
            $connection->table('global_security_paper_codes')
                ->where('paper_code', $paperCode)
                ->update([
                    'is_used'          => false,
                    'status'           => 'available',
                    'assigned_to_type' => null,
                    'assigned_to_id'   => null,
                    'assigned_by'      => null,
                    'assigned_at'      => null,
                    'void_reason'      => null,
                    'void_note'        => null,
                    'voided_by'        => null,
                    'voided_at'        => null,
                    'updated_at'       => now(),
                ]);
        } else {
            // The sheet is destroyed. Keep is_used = 1 so every existing
            // `where is_used = false` pool query keeps excluding it.
            $connection->table('global_security_paper_codes')
                ->where('paper_code', $paperCode)
                ->update([
                    'is_used'          => true,
                    'status'           => 'voided',
                    'assigned_to_type' => null,
                    'assigned_to_id'   => null,
                    'void_reason'      => $reason,
                    'void_note'        => $note,
                    'voided_by'        => Auth::id(),
                    'voided_at'        => now(),
                    'updated_at'       => now(),
                ]);
        }

        // Drop the tracking row so the code stops showing as linked to the file.
        $tracking = $connection->table('security_codes')->where('security_paper_code', $paperCode);
        if ($assignedTo !== null) {
            $tracking->where('assigned_to', $assignedTo);
        }
        $tracking->delete();
    }

    /**
     * Codes that may still be assigned. Voided codes carry is_used = 1 so they
     * are already excluded, but this states the intent explicitly.
     */
    public static function availableQuery()
    {
        return DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->where('is_used', false)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'voided');
            })
            ->orderBy('paper_code', 'asc');
    }

    /**
     * Guard for assign endpoints: a voided code must never be re-assigned, even
     * if someone types it in by hand.
     */
    public static function isVoided(string $paperCode): bool
    {
        return DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->where('paper_code', $paperCode)
            ->where('status', 'voided')
            ->exists();
    }
}
