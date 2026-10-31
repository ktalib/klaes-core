<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The Grantee (party 2) of an Occupancy Permit is its original allottee. Once a
 * Transfer of Title hangs off the OP, the new owner lives on the ToT, and the OP's
 * Grantee must stay the person who transferred (the ToT's Party 1).
 *
 * Edit screens kept writing the file's current holder onto the OP instead: the OSS
 * application form's OP Grantee box and the FEFR Update OP save (which used
 * file_name). By 2026-10-29 that had overwritten the allottee on dozens of OPs.
 */
final class OpAllottee
{
    /**
     * Party 1 names of the live Transfer of Title rows linked to an OP, either by
     * source_op_table/source_op_id or by the OP's prop_id (parent_prop_id, or the
     * shared prop_id older rows used). Empty means no ToT, so the Grantee is free.
     *
     * @return array<int, string> upper-cased, whitespace-collapsed names (may contain '')
     */
    public static function transferParty1Names(string $opTable, ?int $opId, $propId = null): array
    {
        $propId = trim((string) $propId);
        if (!$opId && $propId === '') {
            return [];
        }

        $rows = DB::connection('sqlsrv')->table('pra')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->where('instrument_type', 'like', '%Transfer of Title%')
                ->orWhere('transaction_type', 'like', '%Transfer of Title%'))
            ->where(function ($q) use ($opTable, $opId, $propId) {
                if ($opId) {
                    $q->orWhere(fn ($w) => $w->where('source_op_table', $opTable)
                        ->where('source_op_id', $opId));
                }
                if ($propId !== '' && $opTable === 'pra') {
                    $q->orWhere('parent_prop_id', $propId)->orWhere('prop_id', $propId);
                }
            })
            ->when($opId && $opTable === 'pra', fn ($q) => $q->where('id', '<>', $opId))
            ->get(['Grantor', 'party_1']);

        return $rows->map(fn ($r) => self::norm(($r->Grantor ?? '') ?: ($r->party_1 ?? '')))->all();
    }

    public static function norm($name): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $name)));
    }
}
