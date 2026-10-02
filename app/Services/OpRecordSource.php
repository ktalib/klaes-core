<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "an OP record".
 *
 * The set is a union of three origins, collapsed to one row per prop_id
 * (latest wins):
 *   1. instrument_capture rows of type Occupancy Permit (OP)
 *   2. OP Change of Name rows (mls_file_no -> source_pra_id -> pra)
 *   3. standalone pra rows not covered by (1) or (2)
 *
 * Used by the OPs Dashboard (All OPs tab) and by OP Verification so both
 * always resolve the same records.
 */
class OpRecordSource
{
    /**
     * The union of the three origins, before de-duplication.
     */
    public function unionQuery(?string $from = null, ?string $to = null, ?string $serial = null): Builder
    {
        // ── Part 1: instrument_capture ────────────────────────────────────────
        $captureQ = DB::connection('sqlsrv')
            ->table('instrument_capture as ic')
            ->leftJoin('mls_file_no as mls', 'mls.source_instrument_capture_id', '=', 'ic.id')
            ->leftJoin('fileNumber as fn', function ($join) {
                $join->on('fn.mlsfNo', '=', 'mls.full_file_number')
                    ->orOn('fn.mlsfNo', '=', 'ic.mlsFNo');
            })
            ->select([
                DB::raw("ISNULL(CAST(fn.id AS NVARCHAR(50)), '')            as id"),
                DB::raw("ISNULL(ic.temp_fileno, '')                        as temp_fileno"),
                DB::raw("ISNULL(ic.op_type, '')                             as op_type"),
                DB::raw("ISNULL(CAST(ic.op_serial_number AS NVARCHAR(50)),'') as op_serial_number"),
                DB::raw("ISNULL(ic.registration_number, '')                 as registration_number"),
                DB::raw("ISNULL(mls.full_file_number, '')                   as mls_file_number"),
                DB::raw("ISNULL(ic.party_1_name, '')                        as grantor"),
                DB::raw("ISNULL(ic.party_2_name, '')                        as allottee"),
                DB::raw("''                                                 as new_party_name"),
                DB::raw("ISNULL(ic.property_description, ISNULL(ic.property_location, '')) as property_description"),
                'ic.created_at',
                DB::raw("ISNULL(CAST(ic.prop_id AS NVARCHAR(50)), '')       as prop_id"),
                DB::raw("ISNULL(CAST(ic.id AS NVARCHAR(50)), '')            as source_capture_id"),
                DB::raw("''                                                 as pra_id"),
                DB::raw("ISNULL(ic.instrument_type, '')                     as instrument_type"),
                DB::raw("'0' as is_change_of_name"),
            ])
            ->where('ic.instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0); });

        // ── Part 2: OP Change of Name from mls_file_no -> source_pra_id -> pra ─
        $filenoQ = DB::connection('sqlsrv')
            ->table('mls_file_no as mfn')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'mfn.tracking_id')
            ->join('pra as p', function ($join) {
                $join->on('p.id', '=', 'mfn.source_pra_id')
                     ->whereIn('p.instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)']);
            })
            ->leftJoin('instrument_capture as src_ic', 'src_ic.id', '=', 'p.instrument_capture_id')
            ->select([
                DB::raw("ISNULL(CAST(fn.id AS NVARCHAR(50)), CAST(p.id AS NVARCHAR(50))) as id"),
                DB::raw("ISNULL(p.temp_fileno, ISNULL(src_ic.temp_fileno, '')) as temp_fileno"),
                DB::raw("ISNULL(p.op_type, ISNULL(mfn.source, '')) as op_type"),
                DB::raw("ISNULL(CAST(p.op_serial_number AS NVARCHAR(50)), '') as op_serial_number"),
                DB::raw("ISNULL(CAST(p.regNo AS NVARCHAR(50)), '') as registration_number"),
                DB::raw("ISNULL(fn.mlsfNo, ISNULL(mfn.full_file_number, '')) as mls_file_number"),
                DB::raw("ISNULL(src_ic.party_2_name, ISNULL(p.grantee, ISNULL(p.grantor, ISNULL(fn.FileName, '')))) as grantor"),
                DB::raw("ISNULL(fn.FileName, ISNULL(p.grantee, ISNULL(mfn.customer_type, ''))) as allottee"),
                DB::raw("ISNULL(fn.FileName, '') as new_party_name"),
                DB::raw("ISNULL(p.property_description, ISNULL(p.location, ISNULL(fn.location, ''))) as property_description"),
                DB::raw("ISNULL(fn.created_at, mfn.created_at) as created_at"),
                DB::raw("ISNULL(CAST(p.prop_id AS NVARCHAR(50)), ISNULL(CAST(src_ic.prop_id AS NVARCHAR(50)), '')) as prop_id"),
                DB::raw("ISNULL(CAST(p.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("ISNULL(CAST(p.id AS NVARCHAR(50)), '') as pra_id"),
                DB::raw("ISNULL(p.instrument_type, '') as instrument_type"),
                DB::raw("'1' as is_change_of_name"),
            ])
            ->where('mfn.sub_source', 'OP Change of Name')
            ->whereNotNull('mfn.source_pra_id')
            ->where(function ($q) { $q->whereNull('fn.is_deleted')->orWhere('fn.is_deleted', 0); });

        // ── Part 3: standalone PRA records (not in Part 1 or Part 2) ──────
        $praQ = DB::connection('sqlsrv')
            ->table('pra as p2')
            ->select([
                DB::raw("CAST(p2.id AS NVARCHAR(50))                        as id"),
                DB::raw("ISNULL(p2.temp_fileno, '')                        as temp_fileno"),
                DB::raw("ISNULL(p2.op_type, '')                             as op_type"),
                DB::raw("ISNULL(CAST(p2.op_serial_number AS NVARCHAR(50)),'') as op_serial_number"),
                DB::raw("ISNULL(CAST(p2.regNo AS NVARCHAR(50)), '')         as registration_number"),
                DB::raw("ISNULL(p2.mlsFNo, '')                              as mls_file_number"),
                DB::raw("ISNULL(p2.grantor, '')                             as grantor"),
                DB::raw("ISNULL(p2.grantee, '')                             as allottee"),
                DB::raw("''                                                 as new_party_name"),
                DB::raw("ISNULL(p2.property_description, ISNULL(p2.location, '')) as property_description"),
                'p2.created_at',
                DB::raw("ISNULL(CAST(p2.prop_id AS NVARCHAR(50)), '')       as prop_id"),
                DB::raw("ISNULL(CAST(p2.instrument_capture_id AS NVARCHAR(50)), '') as source_capture_id"),
                DB::raw("CAST(p2.id AS NVARCHAR(50))                        as pra_id"),
                DB::raw("ISNULL(p2.instrument_type, '')                      as instrument_type"),
                DB::raw("'0' as is_change_of_name"),
            ])
            ->whereIn('p2.instrument_type', ['Occupancy Permit (OP)', 'Transfer of Title (OP)'])
            ->where(function ($q) { $q->whereNull('p2.is_deleted')->orWhere('p2.is_deleted', 0); })
            ->whereNull('p2.instrument_capture_id')
            ->where(function ($q) {
                $q->whereNull('p2.system_source')
                  ->orWhere('p2.system_source', '!=', 'OSSOPCHANGEOFNAME');
            })
            ->whereNotIn('p2.id', function ($sub) {
                $sub->select('source_pra_id')
                    ->from('mls_file_no')
                    ->where('sub_source', 'OP Change of Name')
                    ->whereNotNull('source_pra_id');
            });

        // Apply filters before building the union
        if ($from) {
            $captureQ->whereDate('ic.created_at', '>=', $from);
            $filenoQ->whereDate('mfn.created_at', '>=', $from);
            $praQ->whereDate('p2.created_at', '>=', $from);
        }
        if ($to) {
            $captureQ->whereDate('ic.created_at', '<=', $to);
            $filenoQ->whereDate('mfn.created_at', '<=', $to);
            $praQ->whereDate('p2.created_at', '<=', $to);
        }

        // OP serial number is not unique — a partial match may return many rows.
        if ($serial !== null && $serial !== '') {
            $like = '%' . $serial . '%';
            $captureQ->whereRaw("CAST(ic.op_serial_number AS NVARCHAR(50)) LIKE ?", [$like]);
            $filenoQ->whereRaw("CAST(p.op_serial_number AS NVARCHAR(50)) LIKE ?", [$like]);
            $praQ->whereRaw("CAST(p2.op_serial_number AS NVARCHAR(50)) LIKE ?", [$like]);
        }

        return $captureQ->unionAll($filenoQ)->unionAll($praQ);
    }

    /**
     * The union collapsed to one row per prop_id (latest created_at wins).
     * Rows without a prop_id are never collapsed together.
     */
    public function rankedQuery(?string $from = null, ?string $to = null, ?string $serial = null): Builder
    {
        $union = $this->unionQuery($from, $to, $serial);

        $rankedSql = "SELECT *, "
            . "ROW_NUMBER() OVER (PARTITION BY CASE WHEN prop_id IS NOT NULL AND prop_id <> '' THEN prop_id ELSE CAST(NEWID() AS NVARCHAR(50)) END ORDER BY created_at DESC) as _rn "
            . "FROM ({$union->toSql()}) as u";

        return DB::connection('sqlsrv')
            ->table(DB::raw("({$rankedSql}) as ranked"))
            ->setBindings($union->getBindings())
            ->where('_rn', 1)
            ->select([
                'id', 'temp_fileno', 'op_type', 'op_serial_number',
                'registration_number', 'mls_file_number', 'grantor',
                'allottee', 'new_party_name', 'property_description',
                'created_at', 'prop_id', 'source_capture_id', 'pra_id',
                // Carried through so the dashboard can tell a permit from the transfer
                // it became: once an OP is matched, the row that survives the collapse
                // below is the Transfer of Title, and calling that an OP is wrong.
                'instrument_type',
                DB::raw("CAST(is_change_of_name AS NVARCHAR(1)) as is_change_of_name"),
            ]);
    }
}
