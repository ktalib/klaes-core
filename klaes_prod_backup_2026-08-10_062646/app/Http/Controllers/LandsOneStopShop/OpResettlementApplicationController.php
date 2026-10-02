<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\StreetName;
use App\Services\Pra\PraRecordService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;


class OpResettlementApplicationController extends Controller
{
    /**
     * Collapse a batch's first/last file numbers into a range label.
     *
     * Port of the renderer in generate_fileno/mls_js.blade.php: RES-2026-2385 + RES-2026-2392
     * becomes RES-2026-2385-2392. Falls back to the plain file number when the two do not
     * share a prefix or the batch holds a single file.
     */
    private function buildBatchRangeLabel(string $firstFile, string $lastFile): string
    {
        $first = strtoupper(trim($firstFile));
        $last = strtoupper(trim($lastFile));

        if ($first === '' || $last === '' || $first === $last) {
            return $first !== '' ? $first : ($last !== '' ? $last : '—');
        }

        $firstParts = explode('-', $first);
        $lastParts = explode('-', $last);

        if (count($lastParts) < 2 || count($firstParts) !== count($lastParts)) {
            return $last;
        }

        $firstPrefix = implode('-', array_slice($firstParts, 0, -1));
        $lastPrefix = implode('-', array_slice($lastParts, 0, -1));

        if ($firstPrefix !== $lastPrefix) {
            return $last;
        }

        return $lastPrefix . '-' . end($firstParts) . '-' . end($lastParts);
    }

    /**
     * Lean record set for the OP Batch Commissioning view.
     *
     * The main FC query carries per-row correlated subqueries (MAX(id) on mls_file_no,
     * several OUTER APPLYs) that are affordable for a 25-row page and ruinous for the
     * full 374-row batch set — it measured ~73s. The grouped view only needs the file
     * number, title, land use, batch and commissioning stamp, so fetch exactly that.
     *
     * INNER JOIN to pra deliberately: it drops BATCH-20260610-1781088970, whose two files
     * have no pra row, matching the pra-driven behaviour of the main query. That batch is
     * flagged status='ignored' in pra_tot_staging2.
     */
    private function fetchOpBatchRecords(): \Illuminate\Support\Collection
    {
        return DB::connection('sqlsrv')
            ->table('mls_file_no as m')
            ->join('pra as p', function ($join) {
                $join->whereRaw("UPPER(LTRIM(RTRIM(p.mlsFNo))) = UPPER(LTRIM(RTRIM(m.full_file_number)))")
                    ->whereRaw('p.op_batch IS NOT NULL');
            })
            ->whereNotNull('m.op_batch')
            ->select([
                'm.id as mls_id',
                'm.full_file_number',
                'm.batch_no',
                'm.op_batch',
                'm.serial_number',
                'm.file_name',
                'm.created_by',
                'm.customer_type',
                'm.land_use as mls_land_use',
                'm.con_commissioned_at',
                'm.commissioning_date',
                'm.commissioning_time',
                'm.created_at as mls_created_at',
                'p.id as pra_id',
                'p.Grantee',
                'p.land_use as pra_land_use',
                // Location: pra first, mls_file_no as fallback. tp_no comes from
                // mls_file_no only — pra.tp_no is NULL on every one of these rows.
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.plot_no)), ''), NULLIF(LTRIM(RTRIM(m.plot_no)), '')) as plot_no"),
                DB::raw("NULLIF(LTRIM(RTRIM(m.tp_no)), '') as tp_no"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.lgsaOrCity)), ''), NULLIF(LTRIM(RTRIM(m.lga)), '')) as lga"),
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(p.location)), ''),
                    NULLIF(LTRIM(RTRIM(p.property_description)), ''),
                    NULLIF(LTRIM(RTRIM(m.location)), '')
                ) as location"),
            ])
            ->orderByDesc('m.batch_no')
            ->orderBy('m.serial_number')
            ->get()
            ->map(function ($row) {
                // con_commissioned_at is NULL on every batch row — the stamp lives in
                // commissioning_date + commissioning_time.
                $stamp = $row->con_commissioned_at ?: ($row->commissioning_date ?: $row->mls_created_at);
                $date = null;
                if ($stamp) {
                    try { $date = Carbon::parse($stamp); } catch (\Throwable $e) { $date = null; }
                }

                $time = null;
                if ($row->commissioning_time) {
                    try { $time = Carbon::parse((string) $row->commissioning_time); } catch (\Throwable $e) { $time = null; }
                } elseif ($row->con_commissioned_at) {
                    try { $time = Carbon::parse($row->con_commissioned_at); } catch (\Throwable $e) { $time = null; }
                }

                $created = null;
                if ($row->mls_created_at) {
                    try { $created = Carbon::parse($row->mls_created_at); } catch (\Throwable $e) { $created = null; }
                }

                $rawLandUse = strtoupper(trim((string) ($row->mls_land_use ?: $row->pra_land_use ?: '')));
                $landUseMap = [
                    'RES' => 'RESIDENTIAL', 'COM' => 'COMMERCIAL',
                    'IND' => 'INDUSTRIAL', 'AGR' => 'AGRICULTURAL',
                ];

                return [
                    'id' => $row->mls_id,
                    'pra_id' => $row->pra_id,
                    'mls_file_no' => strtoupper((string) $row->full_file_number),
                    'customer_type' => $row->customer_type ? strtoupper((string) $row->customer_type) : '—',
                    'file_title' => $row->Grantee
                        ? strtoupper((string) $row->Grantee)
                        : ($row->file_name ? strtoupper((string) $row->file_name) : '—'),
                    'land_use' => $rawLandUse !== '' ? ($landUseMap[$rawLandUse] ?? $rawLandUse) : '—',
                    'tp_no' => $row->tp_no ? strtoupper((string) $row->tp_no) : '—',
                    'plot_no' => $row->plot_no ? strtoupper((string) $row->plot_no) : '—',
                    'lga' => $row->lga ? strtoupper((string) $row->lga) : '—',
                    'location' => $row->location ? strtoupper((string) $row->location) : '—',
                    'commissioned_by' => $row->created_by ? strtoupper((string) $row->created_by) : '—',
                    'time_commissioned' => $time ? strtoupper($time->format('g:i A')) : '—',
                    'date_commissioned' => $date ? strtoupper($date->format('M d, Y')) : '—',
                    'date_created' => $created ? strtoupper($created->format('M d, Y')) : '—',
                    'batch_no' => $row->batch_no ? trim((string) $row->batch_no) : null,
                    'op_batch' => $row->op_batch ? trim((string) $row->op_batch) : null,
                    'serial_number' => $row->serial_number !== null ? (int) $row->serial_number : null,
                ];
            });
    }

    /**
     * Collapse one field across a batch's records into a single cell value.
     *
     * A batch row stands for N files. Where every file agrees, show the value; where they
     * differ, say so rather than showing the first file's value as if it spoke for all —
     * 13 of 68 batches genuinely carry different plots. '—' means no file had a value.
     */
    private function collapseBatchField($records, string $key): string
    {
        $values = collect($records)
            ->pluck($key)
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '' && $v !== '—')
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return '—';
        }
        if ($values->count() === 1) {
            return (string) $values->first();
        }

        return 'MULTIPLE (' . $values->count() . ')';
    }

    public function index(Request $request)
    {
        set_time_limit(120);
        $limit = (int) $request->input('limit', 25);
        if ($request->query('format') === 'json') {
            $limit = 5000;
        } else {
            $limit = max(10, min($limit, 200));
        }
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $limit;
        $isChangeOfName = trim((string) $request->query('type')) === 'change-of-name';
        $recordType = $request->query('record_type'); // fc or fefr

        // OP Batch Commissioning view: only records flagged with op_batch, grouped by
        // batch_no. These were commissioned through the Batch Mode that was enabled by
        // mistake, so they are scattered across the FC listing by commissioning date.
        // The set is small (~374) — show it whole rather than paginating a grouped view.
        $opBatchMode = $request->boolean('op_batch');
        $opBatchStart = microtime(true);
        if ($opBatchMode) {
            $limit = 500;
            $page = 1;
            $offset = 0;
        }

        $hasSqlsrvColumn = static function (string $table, string $column): bool {
            static $cache = [];
            $key = "$table.$column";
            if (isset($cache[$key])) {
                return $cache[$key];
            }
            // Batch-load all columns for the table in one query
            if (!isset($cache["_loaded_$table"])) {
                try {
                    $cols = DB::connection('sqlsrv')
                        ->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?", [$table]);
                    foreach ($cols as $col) {
                        $cache["$table." . $col->COLUMN_NAME] = true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                $cache["_loaded_$table"] = true;
            }
            return $cache[$key] ?? false;
        };

        $csHasFileNumber = $hasSqlsrvColumn('customers_staging', 'file_number');
        $csHasCustomerType = $hasSqlsrvColumn('customers_staging', 'customer_type');

        $fiHasFileType = $hasSqlsrvColumn('file_indexings', 'file_type');
        $fiHasCustomerType = $hasSqlsrvColumn('file_indexings', 'customer_type');
        $fiResolvedTypeExpr = $fiHasFileType && $fiHasCustomerType
            ? 'COALESCE(file_type, customer_type)'
            : ($fiHasFileType ? 'file_type' : ($fiHasCustomerType ? 'customer_type' : null));

        // PRA file number expression: prefer mlsFNo, fall back to fileno
        $praFileNoExpr = "COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)";

        // Resolved customer type: prefer mls_file_no.customer_type, then join-based lookups
        $resolvedParts = ["NULLIF(LTRIM(RTRIM(mfn.customer_type)), '')"];
        if ($csHasFileNumber && $csHasCustomerType) {
            $resolvedParts[] = "NULLIF(LTRIM(RTRIM(cs_agg.customer_type)), '')";
        }
        if ($fiHasFileType || $fiHasCustomerType) {
            $resolvedParts[] = "NULLIF(LTRIM(RTRIM(fi_agg.resolved_type)), '')";
        }
        $resolvedCustomerTypeSql = "COALESCE(" . implode(', ', $resolvedParts) . ")";

        $instrumentFilter = $isChangeOfName
            ? "AND (instrument_type LIKE '%Transfer of Title%' OR transaction_type LIKE '%Transfer of Title%')"
            : "AND (instrument_type IS NULL OR instrument_type NOT LIKE '%Transfer of Title%') AND (transaction_type IS NULL OR transaction_type NOT LIKE '%Transfer of Title%')";

        // ── Main query: PRA + IC as combined source, fileNumber/mls_file_no as supporting ──
        // Include IC OP records that have a prop_id but may not have a matching PRA row
        // (since OP rows are no longer duplicated into PRA when IC/DR is the source).
        $query = DB::connection('sqlsrv')
            ->table(DB::raw("(
                SELECT id, prop_id, parent_prop_id, mlsFNo, fileno, temp_fileno, instrument_type,
                       Grantor, Grantee, party_1, party_2, regNo, op_type,
                       op_serial_number, property_description, location,
                       created_by, created_at, plot_no, tp_no, lgsaOrCity,
                       land_use, system_source,
                       ROW_NUMBER() OVER (PARTITION BY COALESCE(NULLIF(parent_prop_id, ''), prop_id), instrument_type ORDER BY id DESC) as rn
                FROM pra
                WHERE system_source = 'OSSOPCHANGEOFNAME'
                  AND prop_id IS NOT NULL AND prop_id != ''
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  $instrumentFilter

                UNION ALL

                SELECT ic.id, ic.prop_id, NULL as parent_prop_id, ic.mlsFNo, NULL as fileno, ic.temp_fileno,
                       ic.instrument_type,
                       'Kano State Government' as Grantor, ic.party_1_name as Grantee,
                       'Kano State Government' as party_1, ic.party_1_name as party_2,
                       ic.registration_number as regNo, ic.op_type,
                       ic.op_serial_number, ic.property_description, ic.property_location as location,
                       CAST(ic.created_by AS nvarchar(100)) as created_by, ic.created_at,
                       ic.plot_number as plot_no, ic.tp_no, ic.lga as lgsaOrCity,
                       ic.land_use, 'IC_SOURCE' as system_source,
                       ROW_NUMBER() OVER (PARTITION BY ic.prop_id, ic.instrument_type ORDER BY ic.id DESC) as rn
                FROM instrument_capture ic
                LEFT JOIN pra px ON px.prop_id = ic.prop_id
                                AND px.system_source = 'OSSOPCHANGEOFNAME'
                                -- Only treat OP-type PRA rows as IC duplicates.
                                -- A ToT row sharing the same prop_id (legacy
                                -- pre-lineage data) does NOT mean the OP is
                                -- already represented in PRA \u2014 the OP still
                                -- lives only in instrument_capture and must be
                                -- surfaced from this branch.
                                AND (px.instrument_type IS NULL
                                     OR px.instrument_type NOT LIKE '%Transfer of Title%')
                                AND (px.transaction_type IS NULL
                                     OR px.transaction_type NOT LIKE '%Transfer of Title%')
                WHERE ic.instrument_type = 'Occupancy Permit (OP)'
                  AND ic.prop_id IS NOT NULL AND ic.prop_id != 0
                  AND (ic.is_deleted IS NULL OR ic.is_deleted = 0)
                  AND px.id IS NULL
                  AND '0' = '" . ($isChangeOfName ? '1' : '0') . "'
            ) as p"))
            ->where('p.rn', 1)
            ->leftJoin(DB::raw("(
                SELECT *, ROW_NUMBER() OVER (PARTITION BY mlsfNo ORDER BY id DESC) as fn_rn
                FROM fileNumber
            ) as fn"), function ($join) {
                $join->whereRaw("fn.mlsfNo = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                    ->where('fn.fn_rn', 1);
            })
            ->leftJoin('mls_file_no as mfn', function ($join) {
                // Correlated MAX(id) lets SQL Server seek by index per row of p
                // instead of scanning the entire table with ROW_NUMBER().
                $join->whereRaw("mfn.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                     ->whereRaw("mfn.id = (
                         SELECT MAX(x.id) FROM mls_file_no x
                         WHERE x.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)
                     )");
            })
            ->leftJoin(DB::raw("(
                SELECT
                    COALESCE(NULLIF(p1.parent_prop_id, ''), p1.prop_id) as op_prop_id,
                    MAX(p1.created_at) as latest_tot_created_at
                FROM pra p1
                WHERE p1.system_source = 'OSSOPCHANGEOFNAME'
                  AND (p1.is_deleted IS NULL OR p1.is_deleted = 0)
                  AND (
                      p1.instrument_type LIKE '%Transfer of Title%'
                      OR p1.transaction_type LIKE '%Transfer of Title%'
                  )
                GROUP BY COALESCE(NULLIF(p1.parent_prop_id, ''), p1.prop_id)
            ) as tot_agg"), 'tot_agg.op_prop_id', '=', DB::raw("COALESCE(NULLIF(p.parent_prop_id, ''), p.prop_id)"))
            ->leftJoin('instrument_capture as source_capture', 'source_capture.id', '=', 'mfn.source_instrument_capture_id')
            // Resolve the human-readable Commissioned By by joining users on the
            // PRA / fileNumber created_by user ids. Without this the column shows
            // the raw user id (e.g. "1").
            ->leftJoin('users as pra_user', DB::raw('TRY_CAST(p.created_by AS INT)'), '=', 'pra_user.id')
            ->leftJoin('users as fn_user', DB::raw('TRY_CAST(fn.created_by AS INT)'), '=', 'fn_user.id')
            ->leftJoin(DB::raw("(
                SELECT p2_inner.prop_id, MIN(p2_inner.temp_fileno) as earliest_temp_fileno
                FROM pra p2_inner
                WHERE p2_inner.system_source = 'OSSOPCHANGEOFNAME'
                  AND (p2_inner.is_deleted IS NULL OR p2_inner.is_deleted = 0)
                  AND p2_inner.temp_fileno IS NOT NULL AND p2_inner.temp_fileno != ''
                  AND (p2_inner.instrument_type IS NULL OR p2_inner.instrument_type NOT LIKE '%Transfer of Title%')
                GROUP BY p2_inner.prop_id
            ) as tf_agg"), 'tf_agg.prop_id', '=', DB::raw("COALESCE(NULLIF(p.parent_prop_id, ''), p.prop_id)"))
            ->when($csHasFileNumber && $csHasCustomerType, function ($builder) {
                $builder->leftJoin(DB::raw("(
                    SELECT file_number, MAX(customer_type) as customer_type
                    FROM customers_staging
                    GROUP BY file_number
                ) as cs_agg"), function ($join) {
                    $join->whereRaw("cs_agg.file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)");
                });
            })
            ->when(!empty($fiResolvedTypeExpr), function ($builder) use ($fiResolvedTypeExpr) {
                $builder->leftJoin(DB::raw("(
                    SELECT file_number, MAX(" . $fiResolvedTypeExpr . ") as resolved_type
                    FROM file_indexings
                    GROUP BY file_number
                ) as fi_agg"), function ($join) {
                    $join->whereRaw("fi_agg.file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)");
                });
            })
            ->select([
                DB::raw("COALESCE(fn.id, 0) as id"),
                'p.id as pra_id',
                'p.prop_id',
                DB::raw("$praFileNoExpr as mlsfNo"),
                DB::raw("COALESCE(p.Grantee, fn.FileName) as FileName"),
                'p.instrument_type as pra_instrument_type',
                'p.Grantor',
                'p.Grantee',
                'p.party_1 as pra_party_1',
                'p.party_2 as pra_party_2',
                'p.regNo',
                'p.op_type',
                'p.op_serial_number',
                'p.property_description',
                'p.location as pra_location',
                'p.created_by as pra_created_by',
                'p.created_at as pra_created_at',
                'tot_agg.latest_tot_created_at as tot_created_at',
                'fn.SOURCE as file_number_source',
                'fn.temp_fileno as fn_temp_fileno',
                DB::raw("COALESCE(p.plot_no, fn.plot_no) as plot_no"),
                DB::raw("COALESCE(p.tp_no, fn.tp_no) as tp_no"),
                DB::raw("COALESCE(p.lgsaOrCity, fn.lga) as lga"),
                DB::raw("COALESCE(
                    NULLIF(NULLIF(UPPER(LTRIM(RTRIM(p.location))), 'OTHER'), 'OTHERS'),
                    p.property_description,
                    NULLIF(NULLIF(UPPER(LTRIM(RTRIM(fn.location))), 'OTHER'), 'OTHERS'),
                    p.location
                ) as location"),
                'fn.created_at as fn_created_at',
                'fn.commissioning_date',
                'fn.is_deleted',
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(pra_user.first_name, ''), ' ', COALESCE(pra_user.last_name, '')))), ''),
                    NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(fn_user.first_name,  ''), ' ', COALESCE(fn_user.last_name,  '')))), ''),
                    NULLIF(LTRIM(RTRIM(p.created_by)), ''),
                    NULLIF(LTRIM(RTRIM(CAST(fn.created_by AS nvarchar(100)))), ''),
                    N'—'
                ) as created_by_name"),
                'mfn.source as mls_source',
                'mfn.id as mls_file_no_id',
                DB::raw($resolvedCustomerTypeSql . ' as resolved_customer_type'),
                'mfn.full_file_number as mfn_full_file_number',
                DB::raw("COALESCE(
                    mfn.land_use,
                    p.land_use,
                    CASE
                        WHEN $praFileNoExpr LIKE 'RES%' THEN 'RES'
                        WHEN $praFileNoExpr LIKE 'COM%' THEN 'COM'
                        WHEN $praFileNoExpr LIKE 'CON%' THEN 'COM'
                        WHEN $praFileNoExpr LIKE 'IND%' THEN 'IND'
                        WHEN $praFileNoExpr LIKE 'AGR%' THEN 'AGR'
                        ELSE NULL
                    END
                ) as land_use"),
                'mfn.sub_source',
                'mfn.source_instrument_capture_id',
                'mfn.source_pra_id',
                'mfn.con_commissioned_at',
                'mfn.batch_no',
                'mfn.op_batch',
                'mfn.serial_number as mfn_serial_number',
                'source_capture.purpose as source_purpose',
                'source_capture.land_use as source_land_use',
                'source_capture.district as source_district',
                'source_capture.party_1_phone as source_party_1_phone',
                'source_capture.party_1_address as source_party_1_address',
                'source_capture.party_2_phone as source_party_2_phone',
                'source_capture.party_2_address as source_party_2_address',
                DB::raw("COALESCE(source_capture.party_1_name, p.Grantor, p.party_1) as source_party_1_name_fallback"),
                DB::raw("COALESCE(source_capture.party_2_name, p.Grantee, p.party_2) as source_party_2_name_fallback"),
                // Temp fileno: from this PRA or earliest PRA for same prop_id
                DB::raw("COALESCE(
                    NULLIF(p.temp_fileno, ''),
                    tf_agg.earliest_temp_fileno,
                    fn.temp_fileno
                ) as source_temp_fileno"),
            ])
            ->where(function ($builder) {
                // Allow PRA rows with/without a matching fileNumber;
                // exclude only those with a soft-deleted fileNumber
                $builder->whereNull('fn.is_deleted')
                    ->orWhere('fn.is_deleted', 0);
            })
            ->orderByRaw('COALESCE(mfn.con_commissioned_at, p.created_at) DESC');

        // Filter by mls_file_no presence via EXISTS rather than touching mfn.full_file_number
        // directly: the mfn LEFT JOIN uses a per-row correlated MAX(id) subquery, and
        // adding a WHERE on its column forces SQL Server to materialize that MAX for every
        // candidate row of p — turning a ~1s query into a minutes-long one. The EXISTS
        // form is a plain indexed seek on mls_file_no.full_file_number.
        if ($recordType === 'fc') {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mls_file_no as mfn_flt')
                    ->whereRaw("mfn_flt.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                    ->whereNotNull('mfn_flt.full_file_number')
                    ->where('mfn_flt.full_file_number', '!=', '');
            });
        } elseif ($recordType === 'fefr') {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mls_file_no as mfn_flt')
                    ->whereRaw("mfn_flt.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                    ->whereNotNull('mfn_flt.full_file_number')
                    ->where('mfn_flt.full_file_number', '!=', '');
            });
        }

        // OP Batch Commissioning: restrict to op_batch-flagged files. Uses EXISTS for the
        // same reason as the fc/fefr filters above — a WHERE on the mfn join column forces
        // SQL Server to materialize the correlated MAX(id) for every candidate row.
        if ($opBatchMode) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mls_file_no as mfn_ob')
                    ->whereRaw("mfn_ob.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                    ->whereNotNull('mfn_ob.op_batch');
            });
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($builder) use ($search, $praFileNoExpr, $resolvedCustomerTypeSql) {
                $builder
                    ->whereRaw("$praFileNoExpr LIKE ?", ["%{$search}%"])
                    ->orWhere('p.Grantee', 'LIKE', "%{$search}%")
                    ->orWhere('p.Grantor', 'LIKE', "%{$search}%")
                    ->orWhere('fn.FileName', 'LIKE', "%{$search}%")
                    ->orWhere('mfn.source', 'LIKE', "%{$search}%")
                    ->orWhere('p.plot_no', 'LIKE', "%{$search}%")
                    ->orWhere('p.tp_no', 'LIKE', "%{$search}%")
                    ->orWhere('p.lgsaOrCity', 'LIKE', "%{$search}%")
                    ->orWhere('p.location', 'LIKE', "%{$search}%")
                    ->orWhere('p.property_description', 'LIKE', "%{$search}%")
                    ->orWhere('p.created_by', 'LIKE', "%{$search}%")
                    ->orWhereRaw($resolvedCustomerTypeSql . ' LIKE ?', ["%{$search}%"])
                    ->orWhere('mfn.land_use', 'LIKE', "%{$search}%")
                    ->orWhere('p.land_use', 'LIKE', "%{$search}%");
            });
        }

        // For fc/fefr, the full cloned query is too expensive to count (many LEFT JOINs +
        // ROW_NUMBER subqueries). Use a lightweight count on pra directly instead.
        if ($recordType === 'fc' || $recordType === 'fefr') {
            $instrumentWhereClause = $isChangeOfName
                ? "(p.instrument_type LIKE '%Transfer of Title%' OR p.transaction_type LIKE '%Transfer of Title%')"
                : "((p.instrument_type IS NULL OR p.instrument_type NOT LIKE '%Transfer of Title%') AND (p.transaction_type IS NULL OR p.transaction_type NOT LIKE '%Transfer of Title%'))";

            $lightCountQuery = DB::connection('sqlsrv')
                ->table('pra as p')
                ->where('p.system_source', 'OSSOPCHANGEOFNAME')
                ->whereNotNull('p.prop_id')
                ->where('p.prop_id', '!=', '')
                ->whereRaw($instrumentWhereClause);

            if ($opBatchMode) {
                $lightCountQuery->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('mls_file_no as mfn_ob')
                        ->whereRaw("mfn_ob.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                        ->whereNotNull('mfn_ob.op_batch');
                });
            }

            if ($recordType === 'fc') {
                $lightCountQuery->join('mls_file_no as mfn_c', function ($j) {
                    $j->whereRaw("mfn_c.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                      ->whereNotNull('mfn_c.full_file_number');
                });
            } else {
                $lightCountQuery->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('mls_file_no as mfn_c')
                      ->whereRaw("mfn_c.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)")
                      ->whereNotNull('mfn_c.full_file_number');
                });
            }

            $totalRecords = $lightCountQuery->distinct()->count('p.prop_id');
        } else {
            $totalRecords = (clone $query)->count();
        }

        $totalPages = max(1, (int) ceil($totalRecords / $limit));
        $page = min($page, $totalPages);

        // OP Batch mode uses a lean query — see fetchOpBatchRecords(). Running the main
        // query over all 374 rows costs ~73s because of its per-row correlated subqueries.
        if ($opBatchMode) {
            $records = $this->fetchOpBatchRecords();
            $totalRecords = $records->count();
            $totalPages = 1;
            $page = 1;
        } else {
        $records = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($isChangeOfName) {
                $conCommissionedAt = $row->con_commissioned_at
                    ? Carbon::parse($row->con_commissioned_at)
                    : null;
                $commissionedAt = $conCommissionedAt
                    ?? ($row->commissioning_date ? Carbon::parse($row->commissioning_date) : null)
                    ?? ($row->fn_created_at ? Carbon::parse($row->fn_created_at) : null);

                $dateCreatedAt = null;
                // Prefer the Transfer of Title created_at; fall back to the OP PRA created_at
                $totCreatedAtRaw = $row->tot_created_at ?? $row->pra_created_at;
                if ($totCreatedAtRaw) {
                    try {
                        $dateCreatedAt = Carbon::parse($totCreatedAtRaw);
                    } catch (\Throwable $e) {
                        $dateCreatedAt = null;
                    }
                }

                // When Time/Date Commissioned are empty, fall back to Full Commissioned Date (created_at)
                if (!$commissionedAt && $dateCreatedAt) {
                    $commissionedAt = $dateCreatedAt;
                }

                $rawLandUse = strtoupper(trim((string) ($row->land_use ?? '')));
                $landUseMap = [
                    'RES' => 'RESIDENTIAL',
                    'RESIDENTIAL' => 'RESIDENTIAL',
                    'COM' => 'COMMERCIAL',
                    'COMMERCIAL' => 'COMMERCIAL',
                    'IND' => 'INDUSTRIAL',
                    'INDUSTRIAL' => 'INDUSTRIAL',
                    'AGR' => 'AGRICULTURAL',
                    'AGRICULTURAL' => 'AGRICULTURAL',
                ];
                $landUse = $rawLandUse !== '' ? ($landUseMap[$rawLandUse] ?? $rawLandUse) : '—';
                $fileTitle = $isChangeOfName
                    ? ($row->Grantee ? strtoupper((string) $row->Grantee) : '—')
                    : ($row->FileName ? strtoupper((string) $row->FileName) : '—');
                $displayMlsFileNo = $row->mlsfNo
                    ? strtoupper((string) $row->mlsfNo)
                    : ($row->source_temp_fileno ? strtoupper((string) $row->source_temp_fileno) : '—');
                $displaySourceTempFileno = $row->source_temp_fileno
                    ? strtoupper((string) $row->source_temp_fileno)
                    : '—';

                if ($isChangeOfName && $displaySourceTempFileno === $displayMlsFileNo) {
                    $displaySourceTempFileno = '—';
                }

                // Use instrument_type from PRA directly
                $resolvedSource = $isChangeOfName
                    ? 'TRANSFER OF TITLE (OP)'
                    : ($row->pra_instrument_type ?: ($row->mls_source ?: ($row->file_number_source ?? null)));

                return [
                    'id' => $row->id,
                    'pra_id' => $row->pra_id,
                    'sn' => $row->pra_id,
                    'customer_type' => $row->resolved_customer_type ? strtoupper((string) $row->resolved_customer_type) : '—',
                    'source' => $resolvedSource ? strtoupper((string) $resolvedSource) : '—',
                    'mls_file_no' => $displayMlsFileNo,
                    'file_title' => $fileTitle,
                    'land_use' => $landUse,
                    'tp_no' => $row->tp_no ? strtoupper((string) $row->tp_no) : '—',
                    'plot_no' => $row->plot_no ? strtoupper((string) $row->plot_no) : '—',
                    'lga' => $row->lga ? strtoupper((string) $row->lga) : '—',
                    'location' => $row->location ? strtoupper((string) $row->location) : '—',
                    'commissioned_by' => $row->created_by_name ? strtoupper((string) $row->created_by_name) : '—',
                    'time_commissioned' => $commissionedAt ? strtoupper($commissionedAt->format('g:i A')) : '—',
                    'date_commissioned' => $commissionedAt ? strtoupper($commissionedAt->format('M d, Y')) : '—',
                    'date_created' => $dateCreatedAt ? strtoupper($dateCreatedAt->format('M d, Y')) : '—',
                    'date_created_sort' => $dateCreatedAt ? $dateCreatedAt->timestamp : 0,
                    'con_commissioned_sort' => $conCommissionedAt ? $conCommissionedAt->timestamp : ($commissionedAt ? $commissionedAt->timestamp : 0),

                    // Compatibility fields used by existing action handlers
                    'file_no' => $row->mlsfNo ? strtoupper((string) $row->mlsfNo) : '—',
                    'party_2' => $fileTitle,
                    'party_3' => '—',
                    'plan_no' => $row->tp_no ? strtoupper((string) $row->tp_no) : '—',
                    'property_description' => $row->location ? strtoupper((string) $row->location) : '—',
                    'time_captured' => $commissionedAt ? strtoupper($commissionedAt->format('g:i A')) : '—',
                    'date_captured' => $commissionedAt ? strtoupper($commissionedAt->format('M d, Y')) : '—',
                    'source_instrument_capture_id' => $row->source_instrument_capture_id ?? null,
                    'source_pra_id' => $row->source_pra_id ?? $row->pra_id,
                    'source_temp_fileno' => $displaySourceTempFileno,
                    'source_prop_id' => $row->prop_id ?? null,
                    'purpose' => $row->source_purpose ? strtoupper((string) $row->source_purpose) : '—',
                    'party_1_name' => $row->source_party_1_name_fallback ? strtoupper((string) $row->source_party_1_name_fallback) : '—',
                    'party_2_name' => $row->source_party_2_name_fallback ? strtoupper((string) $row->source_party_2_name_fallback) : $fileTitle,
                    'party_1_phone' => $row->source_party_1_phone ? (string) $row->source_party_1_phone : '—',
                    'party_1_address' => $row->source_party_1_address ? (string) $row->source_party_1_address : '—',
                    'party_2_phone' => $row->source_party_2_phone ? (string) $row->source_party_2_phone : '—',
                    'party_2_address' => $row->source_party_2_address ? (string) $row->source_party_2_address : '—',
                    'district' => $row->source_district ? strtoupper((string) $row->source_district) : '—',
                    'applicant_phone' => $row->source_party_2_phone ? (string) $row->source_party_2_phone : '—',
                    'applicant_address' => $row->source_party_2_address ? (string) $row->source_party_2_address : '—',
                    'source_mls_file_no_id' => $row->mls_file_no_id ?? null,
                    'mfn_full_file_number' => $row->mfn_full_file_number ?? null,
                    'op_serial_number' => $row->op_serial_number ? strtoupper((string) $row->op_serial_number) : '—',
                    'batch_no' => $row->batch_no ? trim((string) $row->batch_no) : null,
                    'op_batch' => $row->op_batch ? trim((string) $row->op_batch) : null,
                    'serial_number' => $row->mfn_serial_number !== null ? (int) $row->mfn_serial_number : null,
                    'scenario_type' => 'standard', // placeholder; overwritten below
                ];
            });

        // ── Detect OP→ToT scenario type for each record ──
        // Subdivision: same op_serial_number produced > 1 ToT rows on this page.
        // Merger: the plot_no for a single ToT contains multiple plot numbers (e.g. "3, 4").
        $opSerialCounts = $records
            ->filter(fn($r) => ($r['op_serial_number'] ?? '—') !== '—')
            ->groupBy('op_serial_number')
            ->map(fn($grp) => $grp->count());

        $records = $records->map(function ($record) use ($opSerialCounts) {
            $opSerial = $record['op_serial_number'] ?? '—';
            $plotNo = $record['plot_no'] ?? '';

            // Merger: single ToT that references multiple plots (contains /, ,, &, or "and" between numbers)
            $isMerger = (bool) preg_match('/\d[\s]*[,\/&][\s]*\d|\d\s+and\s+\d/i', $plotNo);

            // Count the number of source OPs for a merger by splitting plot_no on separators
            $mergerCount = 1;
            if ($isMerger) {
                $parts = preg_split('/[,\/&]|\s+and\s+/i', $plotNo);
                $mergerCount = max(2, count(array_filter($parts, fn($p) => trim($p) !== '')));
            }

            // Subdivision: the same OP serial produced more than one ToT in this result set
            $isSubdivision = !$isMerger
                && $opSerial !== '—'
                && ($opSerialCounts[$opSerial] ?? 0) > 1;

            $record['scenario_type'] = $isMerger ? 'merger' : ($isSubdivision ? 'subdivision' : 'standard');
            $record['scenario_count'] = $isMerger ? $mergerCount : ($isSubdivision ? ($opSerialCounts[$opSerial] ?? 2) : 1);

            return $record;
        });
        } // end !$opBatchMode

        // ── OP Batch Commissioning: collapse to one entry per batch ──
        // Mirrors the grouping in generate_fileno/mls_js.blade.php: a batch renders as a
        // single serial-range row (PREFIX-FIRST-LAST) plus a Group (N) affordance.
        $opBatchGroups = collect();
        if ($opBatchMode) {
            $opBatchGroups = $records
                ->filter(fn($r) => !empty($r['batch_no']))
                ->groupBy('batch_no')
                ->map(function ($grp, $batchNo) {
                    $sorted = $grp->sortBy(fn($r) => $r['serial_number'] ?? PHP_INT_MAX)->values();
                    $first = $sorted->first();
                    $last = $sorted->last();

                    // Sortable timestamps for the batch as a whole, so the table can order
                    // batches by date the same way the non-batch view orders files.
                    $conCommissionedSort = (int) $sorted->max('con_commissioned_sort');
                    $dateCreatedSort = (int) $sorted->max('date_created_sort');

                    return [
                        'batch_no' => $batchNo,
                        'count' => $sorted->count(),
                        'con_commissioned_sort' => $conCommissionedSort,
                        'date_created_sort' => $dateCreatedSort,
                        'first_file' => $first['mls_file_no'] ?? '—',
                        'last_file' => $last['mls_file_no'] ?? '—',
                        'range_label' => $this->buildBatchRangeLabel(
                            $first['mls_file_no'] ?? '',
                            $last['mls_file_no'] ?? ''
                        ),
                        'customer_type' => $this->collapseBatchField($sorted, 'customer_type'),
                        'file_title' => $this->collapseBatchField($sorted, 'file_title'),
                        'land_use' => $this->collapseBatchField($sorted, 'land_use'),
                        'tp_no' => $this->collapseBatchField($sorted, 'tp_no'),
                        'plot_no' => $this->collapseBatchField($sorted, 'plot_no'),
                        'lga' => $this->collapseBatchField($sorted, 'lga'),
                        'location' => $this->collapseBatchField($sorted, 'location'),
                        'commissioned_by' => $this->collapseBatchField($sorted, 'commissioned_by'),
                        'time_commissioned' => $this->collapseBatchField($sorted, 'time_commissioned'),
                        'date_commissioned' => $this->collapseBatchField($sorted, 'date_commissioned'),
                        'date_created' => $this->collapseBatchField($sorted, 'date_created'),
                        'records' => $sorted->all(),
                    ];
                })
                ->sortByDesc(fn($g) => $g['con_commissioned_sort'])
                ->values();

            Log::channel('op_batch')->info('OP Batch view built', [
                'user' => Auth::id(),
                'records' => $records->count(),
                'batches' => $opBatchGroups->count(),
                'total_records' => $totalRecords,
                'build_ms' => (int) round((microtime(true) - $opBatchStart) * 1000),
                'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'largest_batch' => $opBatchGroups->max('count'),
            ]);

        }

        // Data for the instrument-capture modal
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // ── Card counts via a single SQL query ──
        // Ensure stats match the filtered table view (OP vs ToT) and include
        // instrument_capture records when applicable.
        //
        // land_use is resolved per prop_id rather than read straight off the selected row.
        // The Transfer of Title row frequently carries no land_use even though the property
        // is classified elsewhere, which used to leave the four land-use cards summing short
        // of Total Commissioned. Fallback order: the row itself → the file's mls_file_no row
        // → any other non-blank pra row for the same prop_id → the file number's land-use
        // prefix. The prefix is a last resort because it is inferred rather than recorded,
        // but it agrees with the recorded land_use in 1,553 of the 1,559 cases where both
        // exist, and it is only consulted when nothing was recorded at all. TEMP- numbers
        // are deliberately not matched: they encode no land use.
        $fileNoForPrefix = "COALESCE(NULLIF(raw_stats.mlsFNo, ''), raw_stats.fileno)";
        $prefixLandUse = "CASE
                              WHEN $fileNoForPrefix LIKE 'RES-%' THEN 'RESIDENTIAL'
                              WHEN $fileNoForPrefix LIKE 'COM-%' THEN 'COMMERCIAL'
                              WHEN $fileNoForPrefix LIKE 'IND-%' THEN 'INDUSTRIAL'
                              WHEN $fileNoForPrefix LIKE 'AGR-%' THEN 'AGRICULTURAL'
                          END";

        $statsBaseQuery = DB::connection('sqlsrv')
            ->table(DB::raw("(
                SELECT raw_stats.prop_id,
                       COALESCE(
                           NULLIF(LTRIM(RTRIM(raw_stats.land_use)), ''),
                           NULLIF(LTRIM(RTRIM(mfn_lu.land_use)), ''),
                           NULLIF(LTRIM(RTRIM(sib_lu.land_use)), ''),
                           $prefixLandUse
                       ) as land_use,
                       raw_stats.mlsFNo,
                       raw_stats.fileno
                FROM (
                    SELECT prop_id, land_use, mlsFNo, fileno
                    FROM pra
                    WHERE system_source = 'OSSOPCHANGEOFNAME'
                      AND prop_id IS NOT NULL AND prop_id != ''
                      AND (is_deleted IS NULL OR is_deleted = 0)
                      $instrumentFilter

                    " . (!$isChangeOfName ? "
                    UNION ALL
                    SELECT prop_id, land_use, mlsFNo, NULL as fileno
                    FROM instrument_capture
                    WHERE instrument_type = 'Occupancy Permit (OP)'
                      AND prop_id IS NOT NULL AND prop_id != 0
                      AND (is_deleted IS NULL OR is_deleted = 0)
                      AND id NOT IN (SELECT CAST(source_op_id AS INT) FROM pra WHERE source_op_table = 'instrument_capture' AND source_op_id IS NOT NULL)
                    " : "") . "
                ) as raw_stats
                LEFT JOIN mls_file_no as mfn_lu
                       ON mfn_lu.full_file_number = COALESCE(NULLIF(raw_stats.mlsFNo, ''), raw_stats.fileno)
                LEFT JOIN (
                    SELECT CAST(prop_id AS nvarchar(100)) as prop_id, MAX(land_use) as land_use
                    FROM pra
                    WHERE prop_id IS NOT NULL AND prop_id != ''
                      AND LTRIM(RTRIM(COALESCE(land_use, ''))) != ''
                      AND (is_deleted IS NULL OR is_deleted = 0)
                    GROUP BY CAST(prop_id AS nvarchar(100))
                ) as sib_lu
                       ON sib_lu.prop_id = CAST(raw_stats.prop_id AS nvarchar(100))
            ) as stats_source"));

        if ($recordType === 'fc') {
            // INNER JOIN instead of correlated EXISTS — SQL Server can use indexes on both sides
            $statsBaseQuery->join('mls_file_no as mfn_stat', function ($join) {
                $join->whereRaw("mfn_stat.full_file_number = COALESCE(NULLIF(stats_source.mlsFNo, ''), stats_source.fileno)")
                     ->whereNotNull('mfn_stat.full_file_number');
            });
        } elseif ($recordType === 'fefr') {
            $statsBaseQuery->leftJoin('mls_file_no as mfn_stat', function ($join) {
                $join->whereRaw("mfn_stat.full_file_number = COALESCE(NULLIF(stats_source.mlsFNo, ''), stats_source.fileno)");
            })->whereNull('mfn_stat.full_file_number');
        }

        // Normalise once so the bucket tests are collation-independent, and so the
        // "Unclassified" bucket is the exact complement of the other four.
        $luExpr = "UPPER(LTRIM(RTRIM(COALESCE(stats_source.land_use, ''))))";

        $cardCountRows = $statsBaseQuery
            ->selectRaw("
                COUNT(DISTINCT stats_source.prop_id) as total_count,
                COUNT(DISTINCT CASE WHEN $luExpr LIKE '%RES%' THEN stats_source.prop_id END) as res_count,
                COUNT(DISTINCT CASE WHEN $luExpr LIKE '%COM%' THEN stats_source.prop_id END) as com_count,
                COUNT(DISTINCT CASE WHEN $luExpr LIKE '%IND%' THEN stats_source.prop_id END) as ind_count,
                COUNT(DISTINCT CASE WHEN $luExpr LIKE '%AGR%' THEN stats_source.prop_id END) as agr_count,
                COUNT(DISTINCT CASE WHEN $luExpr NOT LIKE '%RES%'
                                     AND $luExpr NOT LIKE '%COM%'
                                     AND $luExpr NOT LIKE '%IND%'
                                     AND $luExpr NOT LIKE '%AGR%'
                                    THEN stats_source.prop_id END) as unclassified_count
            ")
            ->first();

        $cardCounts = [
            'Residential' => (int) ($cardCountRows->res_count ?? 0),
            'Commercial' => (int) ($cardCountRows->com_count ?? 0),
            'Industrial' => (int) ($cardCountRows->ind_count ?? 0),
            'Agriculture' => (int) ($cardCountRows->agr_count ?? 0),
            'Unclassified' => (int) ($cardCountRows->unclassified_count ?? 0),
        ];

        $totalCommissioned = (int) ($cardCountRows->total_count ?? array_sum($cardCounts));

        // ── Today's count: distinct commissioned OPs commissioned today ──
        // Mirrors the cards' filter (system_source = OSSOPCHANGEOFNAME) but scoped to today
        // by date-truncating the same timestamp the table sorts by:
        // COALESCE(mfn.con_commissioned_at, pra.created_at).
        $todayCount = (int) DB::connection('sqlsrv')
            ->table('pra as p')
            ->leftJoin('mls_file_no as mfn', function ($join) {
                $join->whereRaw("mfn.full_file_number = COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)");
            })
            ->where('p.system_source', 'OSSOPCHANGEOFNAME')
            ->whereNotNull('p.prop_id')
            ->where('p.prop_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0);
            })
            ->whereRaw('CAST(COALESCE(mfn.con_commissioned_at, p.created_at) AS DATE) = CAST(GETDATE() AS DATE)')
            ->when($recordType === 'fc', function ($q) {
                $q->whereNotNull('mfn.full_file_number');
            })
            ->when($recordType === 'fefr', function ($q) {
                $q->whereNull('mfn.full_file_number');
            })
            ->distinct()
            ->count('p.prop_id');

        // Total OSS application forms submitted.
        // In some production environments this table may be empty/not actively populated,
        // so fallback to the OP module dataset count to avoid showing a misleading zero.
        $totalOssRecords = 0;
        try {
            if (Schema::connection('sqlsrv')->hasTable('oss_applications')) {
                $ossQuery = DB::connection('sqlsrv')->table('oss_applications');
                if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted')) {
                    $ossQuery->where(function ($builder) {
                        $builder->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });
                }
                $totalOssRecords = (int) $ossQuery->count();
            }
        } catch (\Throwable $e) {
            $totalOssRecords = 0;
        }

        if ($totalOssRecords === 0) {
            $totalOssRecords = (int) $records->count();
        }

        // Page weight watch. The browser OOM on this screen is driven by the shared dropdown
        // lists, not by the rows: every district <select> across the included modals renders
        // one <option> per district, ~23 selects deep. Log the inputs so a regression here is
        // visible instead of guessed at.
        if ($opBatchMode) {
            $districtCount = is_countable($districts) ? count($districts) : 0;
            $estimatedOptionNodes = $districtCount * 23;
            Log::channel('op_batch')->info('Page weight inputs', [
                'districts' => $districtCount,
                'lgas' => is_countable($lgas) ? count($lgas) : 0,
                'street_names' => is_countable($streetNames) ? count($streetNames) : 0,
                'states' => is_countable($states) ? count($states) : 0,
                'est_district_option_nodes' => $estimatedOptionNodes,
            ]);
            if ($estimatedOptionNodes > 20000) {
                Log::channel('op_batch')->warning('Dropdown option count is in browser-OOM territory', [
                    'est_district_option_nodes' => $estimatedOptionNodes,
                    'hint' => 'District lists are duplicated across the included modals. Pre-existing, not caused by the OP Batch view. Populate them on demand instead of server-rendering every <option>.',
                ]);
            }
        }

        return view('lands_one_stop_shop.applications', [
            'pageTitle' => 'Applications (Occupancy Permit)',
            'records' => $records,
            'limit' => $limit,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'states' => $states,
            'lgas' => $lgas,
            'districts' => $districts,
            'streetNames' => $streetNames,
            'cardCounts' => $cardCounts,
            'totalCommissioned' => $totalCommissioned,
            'totalOssRecords' => $totalOssRecords,
            'totalOssRecords' => $totalOssRecords,
            'todayCount' => $todayCount,
            'recordType' => $recordType,
            'opBatchMode' => $opBatchMode,
            'opBatchGroups' => $opBatchGroups,
        ]);
    }

    /**
     * An OP is available for matching only when nothing already claims it.
     *
     * Two link representations exist in this schema and they disagree in the wild (266 of
     * 312 TOTs carrying source_op_id do NOT share their OP's prop_id), so both are checked:
     * an explicit source_op_id pointer, and the shared-prop_id pairing directOpCapture uses.
     * Either one means the OP is spoken for and must not be matched to a second TOT.
     */
    private function applyUnlinkedOpFilter($query, string $table, string $idColumn, string $propColumn): void
    {
        $query->whereNotExists(function ($q) use ($table, $idColumn) {
            $q->select(DB::raw(1))->from('pra as lnk')
                ->whereRaw("lnk.source_op_id = $idColumn")
                ->where('lnk.source_op_table', $table);
        })->whereNotExists(function ($q) use ($propColumn) {
            $q->select(DB::raw(1))->from('pra as lnk2')
                ->whereRaw("lnk2.prop_id = CAST($propColumn AS nvarchar(100))")
                ->where('lnk2.instrument_type', 'LIKE', '%Transfer of Title%');
        });
    }

    /**
     * Search unlinked OPs by serial number, across pra and instrument_capture.
     *
     * Serials are heavily reused — ~3 OPs per serial on average, some serials carry 20+ —
     * so this routinely returns several candidates and the caller must pick one.
     */
    public function opSearchBySerial(Request $request): JsonResponse
    {
        $serial = trim((string) $request->query('serial'));
        if ($serial === '') {
            return response()->json(['success' => false, 'message' => 'serial is required'], 422);
        }

        $praQuery = DB::connection('sqlsrv')->table('pra as o')
            ->whereRaw("LTRIM(RTRIM(o.op_serial_number)) = ?", [$serial])
            ->where('o.instrument_type', 'LIKE', '%Occupancy Permit%')
            ->where(function ($q) { $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0); });
        $this->applyUnlinkedOpFilter($praQuery, 'pra', 'o.id', 'o.prop_id');

        $praOps = $praQuery->select([
            'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.fileno',
            'o.temp_fileno', 'o.party_1', 'o.party_2', 'o.transaction_date', 'o.regNo',
            'o.serialNo', 'o.pageNo', 'o.volumeNo', 'o.plot_no', 'o.tp_no',
            'o.lgsaOrCity as lga', 'o.location', 'o.property_description', 'o.land_use', 'o.created_at',
        ])->orderByDesc('o.id')->limit(50)->get()
          ->map(fn($r) => [
              'source_table' => 'pra',
              'op_id' => $r->id,
              'prop_id' => $r->prop_id ?: '—',
              'op_type' => $r->op_type ?: '—',
              'op_serial_number' => $r->op_serial_number ?: '—',
              'file_no' => $r->mlsFNo ?: ($r->fileno ?: ($r->temp_fileno ?: '—')),
              'grantor' => $r->party_1 ?: '—',
              'allottee' => $r->party_2 ?: '—',   // OP Part 2 — becomes the TOT's Part 1
              'transaction_date' => $r->transaction_date ?: null,
              'reg_no' => $r->regNo ?: '—',
              'serial_no' => $r->serialNo ?: '',
              'page_no' => $r->pageNo ?: '',
              'volume_no' => $r->volumeNo ?: '',
              'plot_no' => $r->plot_no ?: '—',
              'tp_no' => $r->tp_no ?: '—',
              'lga' => $r->lga ?: '—',
              'location' => $r->location ?: ($r->property_description ?: '—'),
              'land_use' => $r->land_use ?: '',
              'created_at' => $r->created_at,
          ]);

        $icQuery = DB::connection('sqlsrv')->table('instrument_capture as o')
            ->whereRaw("LTRIM(RTRIM(o.op_serial_number)) = ?", [$serial])
            ->where('o.instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) { $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0); });
        $this->applyUnlinkedOpFilter($icQuery, 'instrument_capture', 'o.id', 'o.prop_id');

        $icOps = $icQuery->select([
            'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.temp_fileno',
            'o.party_1_name', 'o.party_2_name', 'o.instrument_date', 'o.registration_number',
            'o.serial_no', 'o.page_no', 'o.volume_no', 'o.plot_number', 'o.tp_no',
            'o.lga', 'o.property_location', 'o.property_description', 'o.land_use', 'o.created_at',
        ])->orderByDesc('o.id')->limit(50)->get()
          ->map(fn($r) => [
              'source_table' => 'instrument_capture',
              'op_id' => $r->id,
              'prop_id' => $r->prop_id ?: '—',
              'op_type' => $r->op_type ?: '—',
              'op_serial_number' => $r->op_serial_number ?: '—',
              'file_no' => $r->mlsFNo ?: ($r->temp_fileno ?: '—'),
              'grantor' => $r->party_1_name ?: '—',
              'allottee' => $r->party_2_name ?: '—',
              'transaction_date' => $r->instrument_date ?: null,
              'reg_no' => $r->registration_number ?: '—',
              'serial_no' => $r->serial_no ?: '',
              'page_no' => $r->page_no ?: '',
              'volume_no' => $r->volume_no ?: '',
              'plot_no' => $r->plot_number ?: '—',
              'tp_no' => $r->tp_no ?: '—',
              'lga' => $r->lga ?: '—',
              'location' => $r->property_location ?: ($r->property_description ?: '—'),
              'land_use' => $r->land_use ?: '',
              'created_at' => $r->created_at,
          ]);

        $all = $praOps->concat($icOps)->values();

        Log::channel('op_batch')->info('OP serial search', [
            'user' => Auth::id(),
            'serial' => $serial,
            'pra_hits' => $praOps->count(),
            'ic_hits' => $icOps->count(),
            'total' => $all->count(),
        ]);

        return response()->json([
            'success' => true,
            'serial' => $serial,
            'count' => $all->count(),
            'data' => $all,
        ]);
    }

    /**
     * Resolve the Awaiting TOT and refuse anything that is not a valid target.
     *
     * Returns [$tot, null] on success or [null, JsonResponse] on rejection.
     */
    private function resolveAwaitingTot($totPraId): array
    {
        $tot = DB::connection('sqlsrv')->table('pra')->where('id', $totPraId)->first();

        if (!$tot) {
            return [null, response()->json(['success' => false, 'message' => 'Awaiting TOT not found.'], 404)];
        }
        if (empty($tot->op_batch)) {
            return [null, response()->json([
                'success' => false,
                'message' => 'That row is not part of the OP batch remediation.',
            ], 422)];
        }
        // Prevent duplicate links: a TOT that already points at an OP is done.
        if (!empty($tot->source_op_id)) {
            return [null, response()->json([
                'success' => false,
                'message' => 'This TOT is already linked to an OP (' . $tot->source_op_table . ' #' . $tot->source_op_id . ').',
            ], 409)];
        }

        return [$tot, null];
    }

    /**
     * Write the link. Deliberately does NOT touch prop_id on either row — the explicit
     * source_op_id pointer is the record of truth here, and propids:rebuild-all is the tool
     * that reconciles parcel identity from that lineage.
     */
    private function linkOpToTot(object $tot, string $opTable, $opId, string $allottee, ?string $location = null, ?string $tempFileno = null): void
    {
        $now = now();

        $totUpdate = [
            'source_op_table' => $opTable,
            'source_op_id' => $opId,
            // The whole point: the TOT's Part 1 is the OP's allottee (OP Part 2).
            'party_1' => $allottee,
            'Grantor' => $allottee,
            'updated_at' => $now->toDateTimeString(),
            'updated_by' => (string) Auth::id(),
        ];
        // Carry the OP's / captured location onto the TOT so a blank TOT location gets filled.
        $location = $location !== null ? trim($location) : '';
        if ($location !== '') {
            $totUpdate['location'] = $location;
            $totUpdate['property_description'] = $location;
        }
        // The ToT shares the OP's TEMP file number (correct pair shape) — it keeps its own
        // commissioned mlsFNo, but the temp_fileno links it to the OP.
        $tempFileno = $tempFileno !== null ? trim($tempFileno) : '';
        if ($tempFileno !== '') {
            $totUpdate['temp_fileno'] = $tempFileno;
        }

        DB::connection('sqlsrv')->table('pra')->where('id', $tot->id)->update($totUpdate);

        DB::connection('sqlsrv')->table('pra_tot_staging2')
            ->where('op_batch', $tot->op_batch)
            ->update([
                'status' => 'linked',
                'is_processed' => 1,
                'processed_at' => $now,
                'processed_by' => Auth::id(),
                'has_op_row' => 1,
                'remarks' => 'Linked to ' . $opTable . ' #' . $opId . ' on ' . $now->toDateString()
                    . '; party_1 set to OP allottee',
            ]);
    }

    /**
     * Match an EXISTING unlinked OP to an Awaiting TOT. Creates no OP record.
     */
    public function opMatchExisting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tot_pra_id' => 'required|integer',
            'op_table' => 'required|string|in:pra,instrument_capture',
            'op_id' => 'required|integer',
        ]);

        [$tot, $error] = $this->resolveAwaitingTot($validated['tot_pra_id']);
        if ($error) {
            Log::channel('op_batch')->warning('Match OP rejected', [
                'user' => Auth::id(), 'tot_pra_id' => $validated['tot_pra_id'],
                'status' => $error->getStatusCode(),
            ]);
            return $error;
        }

        // Re-check availability at write time: the candidate list may be stale.
        $opTable = $validated['op_table'];
        $opQuery = DB::connection('sqlsrv')->table($opTable . ' as o')->where('o.id', $validated['op_id']);
        $this->applyUnlinkedOpFilter($opQuery, $opTable, 'o.id', 'o.prop_id');
        $op = $opQuery->first();

        if (!$op) {
            Log::channel('op_batch')->warning('Match OP rejected: OP unavailable', [
                'user' => Auth::id(), 'op_table' => $opTable, 'op_id' => $validated['op_id'],
            ]);
            return response()->json([
                'success' => false,
                'message' => 'That OP is no longer available — it may have been linked to another TOT. Search again.',
            ], 409);
        }

        $allottee = trim((string) ($opTable === 'pra' ? ($op->party_2 ?? '') : ($op->party_2_name ?? '')));
        if ($allottee === '') {
            return response()->json([
                'success' => false,
                'message' => 'That OP has no allottee name, so the TOT allottee cannot be set from it.',
            ], 422);
        }

        // The OP's own location (pra.location / IC.property_location), backfilled onto the TOT.
        $opLocation = $opTable === 'pra'
            ? ($op->location ?? $op->property_description ?? null)
            : ($op->property_location ?? $op->property_description ?? null);
        $opTemp = $op->temp_fileno ?? null;

        try {
            DB::connection('sqlsrv')->transaction(function () use ($tot, $opTable, $op, $allottee, $opLocation, $opTemp) {
                $this->linkOpToTot($tot, $opTable, $op->id, $allottee, $opLocation, $opTemp);
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Match OP failed', [
                'user' => Auth::id(), 'tot_pra_id' => $tot->id,
                'op_table' => $opTable, 'op_id' => $op->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Link failed: ' . $e->getMessage()], 500);
        }

        Log::channel('op_batch')->info('Matched existing OP to Awaiting TOT', [
            'user' => Auth::id(),
            'op_batch' => $tot->op_batch,
            'tot_pra_id' => $tot->id,
            'op_table' => $opTable,
            'op_id' => $op->id,
            'party_1_before' => $tot->party_1,
            'party_1_after' => $allottee,
            'created_op' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OP matched and linked. Allottee updated to ' . $allottee . '.',
            'op_batch' => $tot->op_batch,
            'allottee' => $allottee,
        ]);
    }

    /**
     * Allocate the next temporary file number (TEMP-XXXXX) from temp_fileno_sequence.
     * Same allocation the standalone Capture OP card uses (InstrumentController::getNextTempFileNo):
     * insert a row marked used so the id can never be handed out twice. Shown as the card's
     * System FileNo, then submitted back so the captured OP carries exactly that temp number.
     */
    public function opNextTempFileno(): JsonResponse
    {
        try {
            $seqId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                'created_by' => Auth::id(),
                'is_used' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'temp_fileno' => 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT),
            ]);
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('opNextTempFileno failed', ['user' => Auth::id(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not allocate a temp file number.'], 500);
        }
    }

    /**
     * Capture a NEW OP and link it to the Awaiting TOT in one transaction.
     */
    public function opCaptureAndLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tot_pra_id' => 'required|integer',
            'grantee' => 'required|string|max:255',        // the allottee
            'op_type' => 'required|string|in:OP Resettlement,OP Direct Allocation',
            'status' => 'required|string|in:Normal',
            'system_fileno' => 'nullable|string|max:50',   // TEMP-XXXXX the OP will carry
            'op_serial_number' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date',
            'land_use' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:1000',
            'plot_no' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:50',
            'page_no' => 'nullable|string|max:50',
            'volume_no' => 'nullable|string|max:50',
            'deeds_date' => 'nullable|string|max:100',
            'deeds_time' => 'nullable|string|max:100',
        ]);

        [$tot, $error] = $this->resolveAwaitingTot($validated['tot_pra_id']);
        if ($error) {
            Log::channel('op_batch')->warning('Capture & Link rejected', [
                'user' => Auth::id(), 'tot_pra_id' => $validated['tot_pra_id'],
                'status' => $error->getStatusCode(),
            ]);
            return $error;
        }

        $allottee = trim($validated['grantee']);
        // Hand-entered location falls back to whatever the TOT already had.
        $location = trim((string) ($validated['location'] ?? '')) ?: ($tot->location ?: $tot->property_description);
        // The OP carries a TEMP file number — NOT the TOT's commissioned file number, which
        // belongs to the TOT alone. Trust the one allocated when the card opened; if it is
        // missing/malformed, allocate a fresh one now.
        $systemFileno = trim((string) ($validated['system_fileno'] ?? ''));
        if (!preg_match('/^TEMP-\d+$/i', $systemFileno)) {
            $seqId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                'created_by' => Auth::id(), 'is_used' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $systemFileno = 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT);
        }
        $now = now();

        try {
            $opId = DB::connection('sqlsrv')->transaction(function () use ($tot, $validated, $allottee, $location, $systemFileno, $now) {
                // New OP mirrors directOpCapture's Row 1: Grantor is the State, Grantee is the
                // allottee, mlsFNo stays NULL, and the OP carries its own TEMP file number —
                // the commissioned file number belongs to the TOT, never the OP.
                $opId = DB::connection('sqlsrv')->table('pra')->insertGetId([
                    'mlsFNo' => null,
                    'fileno' => $systemFileno,
                    'temp_fileno' => $systemFileno,
                    'prop_id' => $tot->prop_id,
                    'transaction_type' => 'Occupancy Permit (OP)',
                    'instrument_type' => 'Occupancy Permit (OP)',
                    'status' => $validated['status'],       // required, currently only 'Normal'
                    // ?? throughout: validate() returns only the keys actually submitted,
                    // so a partially-filled form would otherwise throw on the absent ones.
                    'op_type' => $validated['op_type'],     // required
                    'op_serial_number' => ($validated['op_serial_number'] ?? null) ?: null,
                    'transaction_date' => ($validated['transaction_date'] ?? null) ?: null,
                    'serialNo' => ($validated['serial_no'] ?? null) ?: null,
                    'pageNo' => ($validated['page_no'] ?? null) ?: null,
                    'volumeNo' => ($validated['volume_no'] ?? null) ?: null,
                    'deeds_date' => ($validated['deeds_date'] ?? null) ?: null,
                    'deeds_time' => ($validated['deeds_time'] ?? null) ?: null,
                    'location' => $location,
                    'property_description' => $location,
                    'plot_no' => ($validated['plot_no'] ?? null) ?: $tot->plot_no,
                    'tp_no' => $tot->tp_no,
                    'lgsaOrCity' => $tot->lgsaOrCity,
                    'land_use' => ($validated['land_use'] ?? null) ?: $tot->land_use,
                    'source' => 'OP Batch Commissioning',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'Grantor' => 'Kano State Government',
                    'Grantee' => $allottee,
                    'party_1' => 'Kano State Government',
                    'party_2' => $allottee,
                    'created_by' => (string) Auth::id(),
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                    'is_deleted' => 0,
                ]);

                $this->linkOpToTot($tot, 'pra', $opId, $allottee, $location, $systemFileno);

                return $opId;
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Capture & Link failed', [
                'user' => Auth::id(), 'tot_pra_id' => $tot->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Capture failed: ' . $e->getMessage()], 500);
        }

        Log::channel('op_batch')->info('Captured new OP and linked to Awaiting TOT', [
            'user' => Auth::id(),
            'op_batch' => $tot->op_batch,
            'tot_pra_id' => $tot->id,
            'op_table' => 'pra',
            'op_id' => $opId,
            'party_1_before' => $tot->party_1,
            'party_1_after' => $allottee,
            'created_op' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OP captured and linked. Allottee updated to ' . $allottee . '.',
            'op_batch' => $tot->op_batch,
            'op_id' => $opId,
            'temp_fileno' => $systemFileno,
            'allottee' => $allottee,
        ]);
    }

    /**
     * Batch Capture OP — create N UNLINKED Occupancy Permit rows sharing one Batch ID.
     *
     * Launched from the OSS Commission New File Number card in Batch Mode. Each OP carries its
     * own TEMP file number (mlsFNo NULL) and a fresh prop_id; none are linked to a TOT yet
     * (source_op_id stays NULL). A later TOT commissioning matches them by batch sequence
     * (matchTotBatchToOps), where a shared prop_id is assigned per pair.
     *
     * Row shape mirrors opCaptureAndLink's OP insert; the only differences are the shared
     * op_batch, the per-OP fresh prop_id, and no linkage.
     */
    /**
     * Flag OP rows that already exist with the same identifying fields, so the user can be
     * warned before saving a batch that would duplicate an existing Occupancy Permit.
     *
     * "Exact match" = an existing OP pra row (instrument_type 'Occupancy Permit (OP)', not
     * deleted) whose OP Serial Number, Serial No, Page No, Vol No, Plot No AND Party 2 all
     * equal the ones entered. Comparison is trimmed; SQL Server's default collation is
     * case-insensitive. Blank incoming fields are skipped (never treated as a wildcard).
     *
     * Returns, per submitted OP, whether a duplicate exists and a short description of the
     * matched record(s). This is advisory only — it never writes and never blocks the save.
     */
    public function opCheckDuplicates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ops' => 'required|array|min:1',
            'ops.*.sequence' => 'nullable',
            'ops.*.op_serial_number' => 'nullable|string|max:100',
            'ops.*.serial_no' => 'nullable|string|max:50',
            'ops.*.page_no' => 'nullable|string|max:50',
            'ops.*.volume_no' => 'nullable|string|max:50',
            'ops.*.plot_no' => 'nullable|string|max:100',
            'ops.*.party_2' => 'nullable|string|max:255',
        ]);

        // The identifying fields, mapped to their pra columns. Plot No is intentionally
        // EXCLUDED — a property's plot may be missing/unknown, so it must not gate the match
        // (nor be required for one). It is still returned on each match for the user to see.
        $map = [
            'op_serial_number' => 'op_serial_number',
            'serial_no'        => 'serialNo',
            'page_no'          => 'pageNo',
            'volume_no'        => 'volumeNo',
            'party_2'          => 'party_2',
        ];

        $duplicates = [];
        foreach ($validated['ops'] as $i => $op) {
            // Only compare on fields the user actually filled; require the core ones so we
            // don't match a sparse historical row on a single shared value.
            $conds = [];
            foreach ($map as $key => $col) {
                $val = trim((string) ($op[$key] ?? ''));
                if ($val === '') continue;
                $conds[$col] = $val;
            }
            // Need the full identifying set present to call something an exact duplicate.
            if (count($conds) < count($map)) continue;

            $q = DB::connection('sqlsrv')->table('pra')
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->where(function ($w) {
                    $w->where('is_deleted', 0)->orWhereNull('is_deleted');
                });
            foreach ($conds as $col => $val) {
                $q->whereRaw("LTRIM(RTRIM([$col])) = ?", [$val]);
            }

            $matches = $q->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'fileno', 'temp_fileno', 'mlsFNo', 'op_batch', 'op_serial_number', 'plot_no', 'party_2', 'created_at']);

            if ($matches->isNotEmpty()) {
                $duplicates[] = [
                    'sequence' => $op['sequence'] ?? ($i + 1),
                    'entered'  => [
                        'op_serial_number' => $op['op_serial_number'] ?? '',
                        'serial_no' => $op['serial_no'] ?? '',
                        'page_no' => $op['page_no'] ?? '',
                        'volume_no' => $op['volume_no'] ?? '',
                        'plot_no' => $op['plot_no'] ?? '',
                        'party_2' => $op['party_2'] ?? '',
                    ],
                    'matches' => $matches->map(fn ($m) => [
                        'id' => $m->id,
                        'file' => $m->mlsFNo ?: ($m->fileno ?: $m->temp_fileno),
                        'op_batch' => $m->op_batch,
                        'party_2' => $m->party_2,
                        'plot_no' => $m->plot_no,
                    ])->values(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'has_duplicates' => count($duplicates) > 0,
            'duplicates' => $duplicates,
        ]);
    }

    public function opBatchCapture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'op_batch' => 'nullable|string|max:50',   // resume an existing uncommissioned batch
            'ops' => 'required|array|min:1',
            'ops.*.op_type' => 'required|string|in:OP Resettlement,OP Direct Allocation',
            'ops.*.status' => 'required|string|in:Normal',
            'ops.*.grantee' => 'required|string|max:255',       // the allottee (becomes TOT Part 1 later)
            'ops.*.update_op_id' => 'nullable|integer',          // existing OP row to backfill instead of inserting
            'ops.*.system_fileno' => 'nullable|string|max:50',  // TEMP-XXXXX the OP carries
            'ops.*.op_serial_number' => 'nullable|string|max:100',
            'ops.*.transaction_date' => 'nullable|date',
            'ops.*.land_use' => 'nullable|string|max:100',
            'ops.*.purpose_id' => 'nullable',                     // commission purpose id (for backfill parity)
            'ops.*.purpose' => 'nullable|string|max:255',         // purpose name → pra.purpose
            'ops.*.lga' => 'nullable|string|max:150',             // → pra.lgsaOrCity
            'ops.*.location' => 'nullable|string|max:1000',
            'ops.*.plot_no' => 'nullable|string|max:100',
            'ops.*.district' => 'nullable|string|max:150',       // already folded into location; kept for reference
            'ops.*.serial_no' => 'nullable|string|max:50',
            'ops.*.page_no' => 'nullable|string|max:50',
            'ops.*.volume_no' => 'nullable|string|max:50',
            'ops.*.deeds_date' => 'nullable|string|max:100',
            'ops.*.deeds_time' => 'nullable|string|max:100',
        ]);

        // One shared Batch ID for every OP created in this save. When resuming an existing
        // batch, keep its id so added records join the same group — but only while that batch
        // is still uncommissioned.
        $resume = trim((string) ($validated['op_batch'] ?? ''));
        if ($resume !== '') {
            if ($this->opBatchIsCommissioned($resume)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch ' . $resume . ' has already been commissioned and can no longer be changed.',
                ], 422);
            }
            $opBatch = $resume;
        } else {
            $opBatch = 'OPB-' . date('Ymd') . '-' . time();
        }
        $allocator = app(\App\Services\PropertyIdAllocationService::class);
        $now = now();
        $created = [];

        try {
            DB::connection('sqlsrv')->transaction(function () use ($validated, $opBatch, $allocator, $now, &$created) {
                $seq = 0;
                foreach ($validated['ops'] as $op) {
                    $seq++;

                    $allottee = trim($op['grantee']);
                    $location = trim((string) ($op['location'] ?? '')) ?: null;

                    // Duplicate that the user chose to backfill: UPDATE the existing OP row in
                    // place instead of inserting a new one. Keep its identity (fileno, prop_id,
                    // op_batch, mlsFNo) — only complete the captured/detail fields.
                    $updateId = isset($op['update_op_id']) ? (int) $op['update_op_id'] : 0;
                    if ($updateId > 0) {
                        $existing = DB::connection('sqlsrv')->table('pra')
                            ->where('id', $updateId)
                            ->where('instrument_type', 'Occupancy Permit (OP)')
                            ->first();
                        if ($existing) {
                            DB::connection('sqlsrv')->table('pra')->where('id', $updateId)->update([
                                'status' => $op['status'],
                                'op_type' => $op['op_type'],
                                'op_serial_number' => ($op['op_serial_number'] ?? null) ?: null,
                                'transaction_date' => ($op['transaction_date'] ?? null) ?: null,
                                'serialNo' => ($op['serial_no'] ?? null) ?: null,
                                'pageNo' => ($op['page_no'] ?? null) ?: null,
                                'volumeNo' => ($op['volume_no'] ?? null) ?: null,
                                'deeds_date' => ($op['deeds_date'] ?? null) ?: null,
                                'deeds_time' => ($op['deeds_time'] ?? null) ?: null,
                                'location' => $location,
                                'property_description' => $location,
                                'plot_no' => ($op['plot_no'] ?? null) ?: null,
                                'lgsaOrCity' => ($op['lga'] ?? null) ?: null,
                                'land_use' => ($op['land_use'] ?? null) ?: null,
                                'purpose' => ($op['purpose'] ?? null) ?: null,
                                'purpose_id' => ($op['purpose_id'] ?? null) ?: null,
                                'Grantee' => $allottee,
                                'party_2' => $allottee,
                                'updated_at' => $now->toDateTimeString(),
                            ]);
                            $created[] = [
                                'sequence' => $seq, 'op_id' => $updateId, 'action' => 'updated',
                                'temp_fileno' => $existing->temp_fileno ?: ($existing->mlsFNo ?: $existing->fileno),
                                'prop_id' => $existing->prop_id,
                            ];
                            continue;
                        }
                        // update_op_id given but the row vanished/isn't an OP — fall through to insert.
                    }

                    // Trust the TEMP allocated when the card opened; re-allocate if missing/malformed.
                    $systemFileno = trim((string) ($op['system_fileno'] ?? ''));
                    if (!preg_match('/^TEMP-\d+$/i', $systemFileno)) {
                        $seqId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                            'created_by' => Auth::id(), 'is_used' => 1, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $systemFileno = 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT);
                    }

                    // Fresh parcel identity per OP, keyed on its TEMP. The shared prop_id with a
                    // TOT is assigned later, at match time.
                    $propId = $allocator->allocateOrRetrievePropId(
                        $systemFileno, null, null, null,
                        ['temp_fileno' => $systemFileno, 'allow_temp_only' => true]
                    );

                    $opId = DB::connection('sqlsrv')->table('pra')->insertGetId([
                        'mlsFNo' => null,
                        'fileno' => $systemFileno,
                        'temp_fileno' => $systemFileno,
                        'prop_id' => $propId,
                        'transaction_type' => 'Occupancy Permit (OP)',
                        'instrument_type' => 'Occupancy Permit (OP)',
                        'status' => $op['status'],
                        'op_type' => $op['op_type'],
                        'op_serial_number' => ($op['op_serial_number'] ?? null) ?: null,
                        'transaction_date' => ($op['transaction_date'] ?? null) ?: null,
                        'serialNo' => ($op['serial_no'] ?? null) ?: null,
                        'pageNo' => ($op['page_no'] ?? null) ?: null,
                        'volumeNo' => ($op['volume_no'] ?? null) ?: null,
                        'deeds_date' => ($op['deeds_date'] ?? null) ?: null,
                        'deeds_time' => ($op['deeds_time'] ?? null) ?: null,
                        'location' => $location,
                        'property_description' => $location,
                        'plot_no' => ($op['plot_no'] ?? null) ?: null,
                        'lgsaOrCity' => ($op['lga'] ?? null) ?: null,
                        'land_use' => ($op['land_use'] ?? null) ?: null,
                        // Purpose is persisted here (2026_07_19_140000) so a saved batch can be
                        // reopened for editing with its Purpose intact. It is still ALSO carried
                        // through the UI backfill into the commission record's purpose_id.
                        'purpose' => ($op['purpose'] ?? null) ?: null,
                        'purpose_id' => ($op['purpose_id'] ?? null) ?: null,
                        // Shared batch group id; capture order (pra.id asc) is the batch sequence.
                        'op_batch' => $opBatch,
                        'source' => 'OP Batch Commissioning',
                        'system_source' => 'OSSOPCHANGEOFNAME',
                        'Grantor' => 'Kano State Government',
                        'Grantee' => $allottee,
                        'party_1' => 'Kano State Government',
                        'party_2' => $allottee,
                        // Explicitly unlinked — matched to a TOT later.
                        'source_op_id' => null,
                        'source_op_table' => null,
                        'created_by' => (string) Auth::id(),
                        'created_at' => $now->toDateTimeString(),
                        'updated_at' => $now->toDateTimeString(),
                        'is_deleted' => 0,
                    ]);

                    $created[] = [
                        'sequence' => $seq, 'op_id' => $opId, 'action' => 'created',
                        'temp_fileno' => $systemFileno, 'prop_id' => $propId,
                    ];
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Batch Capture OP failed', [
                'user' => Auth::id(), 'op_batch' => $opBatch, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Batch save failed: ' . $e->getMessage()], 500);
        }

        $updatedCount = count(array_filter($created, fn ($c) => ($c['action'] ?? '') === 'updated'));
        $newCount = count($created) - $updatedCount;

        Log::channel('op_batch')->info('Batch Capture OP saved', [
            'user' => Auth::id(), 'op_batch' => $opBatch,
            'count' => count($created), 'updated' => $updatedCount, 'new' => $newCount,
        ]);

        $message = count($created) . ' OPs saved under batch ' . $opBatch . '.';
        if ($updatedCount > 0) {
            $message = $newCount . ' new OP' . ($newCount === 1 ? '' : 's') . ' captured and '
                . $updatedCount . ' existing OP' . ($updatedCount === 1 ? '' : 's') . ' backfilled'
                . ' (batch ' . $opBatch . ').';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'op_batch' => $opBatch,
            'count' => count($created),
            'updated_count' => $updatedCount,
            'new_count' => $newCount,
            'ops' => $created,
        ]);
    }

    /**
     * Base query for the OP rows of a batch: captured by Batch Capture, still unlinked.
     */
    private function opBatchRowsQuery(string $opBatch)
    {
        return DB::connection('sqlsrv')->table('pra')
            ->where('op_batch', $opBatch)
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->whereNull('mlsFNo')
            ->whereNull('source_op_id')
            ->where(function ($w) {
                $w->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
    }

    /**
     * A batch is "commissioned" once file numbers have been generated against it — either a
     * mls_file_no row carries the batch id, or one of its pra rows has picked up an mlsFNo.
     * Editing and deleting are only permitted while a batch is still uncommissioned.
     */
    private function opBatchIsCommissioned(string $opBatch): bool
    {
        $inMls = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('op_batch', $opBatch)->exists();
        if ($inMls) return true;

        return DB::connection('sqlsrv')->table('pra')
            ->where('op_batch', $opBatch)
            ->whereNotNull('mlsFNo')
            ->exists();
    }

    /**
     * List every uncommissioned OP batch, so the user can resume one instead of starting over.
     *
     * Each entry carries enough to identify the batch on sight: its id, when it was captured,
     * how many records it currently holds, and a preview of the first few allottee names.
     */
    public function opUncommissionedBatches(): JsonResponse
    {
        try {
            // Candidate batches: captured by Batch Capture OP and not yet commissioned.
            $groups = DB::connection('sqlsrv')->table('pra')
                ->whereNotNull('op_batch')
                ->where('source', 'OP Batch Commissioning')
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->whereNull('mlsFNo')
                ->whereNull('source_op_id')
                ->where(function ($w) {
                    $w->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->groupBy('op_batch')
                ->select(
                    'op_batch',
                    DB::raw('COUNT(*) as cnt'),
                    DB::raw('MIN(created_at) as created_at'),
                    // Whoever captured the batch — the first OP's creator (pra.created_by
                    // holds the user id as a string).
                    DB::raw('MIN(created_by) as created_by')
                )
                ->orderByRaw('MIN(created_at) DESC')
                ->get();

            // Resolve capturer names in one query rather than per batch.
            $userIds = $groups->pluck('created_by')
                ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique()->values();
            $userNames = $userIds->isEmpty()
                ? collect()
                : DB::connection('sqlsrv')->table('users')
                    ->whereIn('id', $userIds)
                    ->get(['id', 'first_name', 'last_name', 'username'])
                    ->mapWithKeys(function ($u) {
                        $name = trim(trim((string) $u->first_name) . ' ' . trim((string) $u->last_name));
                        return [(int) $u->id => ($name !== '' ? $name : (string) $u->username)];
                    });

            $batches = [];
            foreach ($groups as $g) {
                $opBatch = trim((string) $g->op_batch);
                if ($opBatch === '' || $this->opBatchIsCommissioned($opBatch)) continue;

                $names = $this->opBatchRowsQuery($opBatch)
                    ->orderBy('id')->limit(5)->pluck('party_2')
                    ->map(fn ($n) => trim((string) $n))->filter()->values();

                $byId = is_numeric($g->created_by) ? (int) $g->created_by : null;

                $batches[] = [
                    'op_batch'    => $opBatch,
                    'count'       => (int) $g->cnt,
                    'created_at'  => $g->created_at,
                    'captured_by' => ($byId !== null ? ($userNames[$byId] ?? null) : null),
                    'allottees'   => $names,
                    'more'        => max(0, (int) $g->cnt - $names->count()),
                ];
            }

            return response()->json(['success' => true, 'batches' => $batches]);
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Listing uncommissioned batches failed', [
                'user' => Auth::id(), 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Could not load batches.'], 500);
        }
    }

    /**
     * Full records of one uncommissioned batch, shaped like the stepper's in-memory forms so
     * they can be loaded straight back into the Batch Capture OP card for editing.
     */
    public function opUncommissionedBatchRecords(Request $request): JsonResponse
    {
        $validated = $request->validate(['op_batch' => 'required|string|max:50']);
        $opBatch = trim($validated['op_batch']);

        if ($this->opBatchIsCommissioned($opBatch)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ' . $opBatch . ' has already been commissioned and can no longer be edited.',
            ], 422);
        }

        $rows = $this->opBatchRowsQuery($opBatch)->orderBy('id')->get();

        $forms = $rows->map(fn ($r) => [
            'op_id'            => (int) $r->id,
            'op_type'          => $r->op_type ?: '',
            'status'           => $r->status ?: 'Normal',
            'system_fileno'    => $r->temp_fileno ?: ($r->fileno ?: ''),
            'op_serial_number' => (string) ($r->op_serial_number ?? ''),
            'transaction_date' => $r->transaction_date ? substr((string) $r->transaction_date, 0, 10) : '',
            'land_use'         => $r->land_use ?: '',
            'land_use_id'      => '',   // resolved client-side from the code, against the loaded options
            'grantee'          => $r->party_2 ?: ($r->Grantee ?: ''),
            'purpose_id'       => $r->purpose_id ? (string) $r->purpose_id : '',
            'purpose_name'     => (string) ($r->purpose ?? ''),
            'lga'              => $r->lgsaOrCity ?: '',
            'plot'             => (string) ($r->plot_no ?? ''),
            'street'           => '',
            'district'         => '',
            'district_other'   => '',
            'location'         => $r->location ?: '',
            'serial_no'        => (string) ($r->serialNo ?? ''),
            'page_no'          => (string) ($r->pageNo ?? ''),
            'volume_no'        => (string) ($r->volumeNo ?? ''),
            'deeds_date'       => (string) ($r->deeds_date ?? ''),
            'deeds_time'       => (string) ($r->deeds_time ?? ''),
        ])->values();

        return response()->json([
            'success'  => true,
            'op_batch' => $opBatch,
            'count'    => $forms->count(),
            'forms'    => $forms,
        ]);
    }

    /**
     * Permanently remove one record from an uncommissioned batch.
     *
     * These file numbers were never commissioned, so the row is HARD deleted rather than
     * flagged — along with the parcel identity minted for it, provided nothing else claims
     * that prop_id. The consumed temp_fileno_sequence id is intentionally left burned: TEMP
     * numbers are never reissued.
     */
    public function opBatchDeleteRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'op_batch' => 'required|string|max:50',
            'op_id'    => 'required|integer',
        ]);
        $opBatch = trim($validated['op_batch']);
        $opId = (int) $validated['op_id'];

        if ($this->opBatchIsCommissioned($opBatch)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ' . $opBatch . ' has been commissioned — its records can no longer be deleted.',
            ], 422);
        }

        $row = $this->opBatchRowsQuery($opBatch)->where('id', $opId)->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'That record is not part of uncommissioned batch ' . $opBatch . '.',
            ], 404);
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use ($row, $opId) {
                DB::connection('sqlsrv')->table('pra')->where('id', $opId)->delete();

                // Drop the parcel identity too, but only if this OP was its sole claimant.
                $propId = $row->prop_id;
                if ($propId) {
                    $stillUsed = DB::connection('sqlsrv')->table('pra')
                            ->where('prop_id', $propId)->exists()
                        || DB::connection('sqlsrv')->table('file_indexings')
                            ->where('prop_id', $propId)->exists();
                    if (!$stillUsed) {
                        DB::connection('sqlsrv')->table('PropID_Master')
                            ->where('prop_id', $propId)->delete();
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Batch record delete failed', [
                'user' => Auth::id(), 'op_batch' => $opBatch, 'op_id' => $opId, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()], 500);
        }

        $remaining = $this->opBatchRowsQuery($opBatch)->count();

        Log::channel('op_batch')->info('Hard-deleted record from uncommissioned batch', [
            'user' => Auth::id(), 'op_batch' => $opBatch, 'op_id' => $opId,
            'temp_fileno' => $row->temp_fileno, 'prop_id' => $row->prop_id, 'remaining' => $remaining,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Record removed from batch ' . $opBatch . '.',
            'op_batch'  => $opBatch,
            'remaining' => $remaining,
        ]);
    }

    /**
     * Match a saved OP batch to freshly-commissioned TOT file numbers, in sequence.
     *
     * Given the batch's op_batch id and an ORDERED list of TOT pra ids (TOT 1..N in the same
     * order the OPs were captured), pair OP i <-> TOT i, assign both a single shared prop_id,
     * and link them (TOT.source_op_id = OP.id, TOT.party_1 = OP.party_2 via linkOpToTot).
     *
     * NOTE: the TOT-commissioning trigger/UI is a follow-up; this endpoint provides the matching
     * capability and is safe to drive directly (e.g. from a match action once TOTs exist).
     */
    public function matchTotBatchToOps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'op_batch' => 'required|string|max:50',
            'tot_pra_ids' => 'required|array|min:1',
            'tot_pra_ids.*' => 'integer',
        ]);

        // OPs of the batch, in capture order (id asc == sequence), still unlinked.
        $ops = DB::connection('sqlsrv')->table('pra')
            ->where('op_batch', $validated['op_batch'])
            ->whereNull('source_op_id')
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->orderBy('id')
            ->get();

        if ($ops->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No unlinked OPs found for batch ' . $validated['op_batch'] . '.',
            ], 404);
        }

        $totIds = array_values($validated['tot_pra_ids']);
        if (count($totIds) !== $ops->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Count mismatch: ' . $ops->count() . ' OPs vs ' . count($totIds)
                    . ' TOTs. They must pair 1:1 in order.',
            ], 422);
        }

        $pairs = [];
        try {
            DB::connection('sqlsrv')->transaction(function () use ($ops, $totIds, &$pairs) {
                foreach ($ops as $i => $op) {
                    $tot = DB::connection('sqlsrv')->table('pra')->where('id', $totIds[$i])->first();
                    if (!$tot) {
                        throw new \RuntimeException('TOT pra #' . $totIds[$i] . ' not found.');
                    }

                    // Both records share one prop_id — the OP<->TOT linking convention.
                    $sharedPropId = $tot->prop_id ?: $op->prop_id;
                    DB::connection('sqlsrv')->table('pra')->where('id', $op->id)
                        ->update(['prop_id' => $sharedPropId, 'updated_at' => now()->toDateTimeString()]);
                    if ($tot->prop_id != $sharedPropId) {
                        DB::connection('sqlsrv')->table('pra')->where('id', $tot->id)
                            ->update(['prop_id' => $sharedPropId, 'updated_at' => now()->toDateTimeString()]);
                        $tot->prop_id = $sharedPropId;
                    }

                    $allottee = trim((string) ($op->party_2 ?? ''));
                    $this->linkOpToTot(
                        $tot, 'pra', $op->id, $allottee,
                        $op->location ?? $op->property_description, $op->temp_fileno
                    );

                    $pairs[] = ['op_id' => $op->id, 'tot_id' => $tot->id, 'prop_id' => $sharedPropId];
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('TOT batch match failed', [
                'user' => Auth::id(), 'op_batch' => $validated['op_batch'], 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Matching failed: ' . $e->getMessage()], 500);
        }

        Log::channel('op_batch')->info('TOT batch matched to OPs', [
            'user' => Auth::id(), 'op_batch' => $validated['op_batch'], 'pairs' => count($pairs),
        ]);

        return response()->json([
            'success' => true,
            'message' => count($pairs) . ' OP/TOT pairs linked under batch ' . $validated['op_batch'] . '.',
            'op_batch' => $validated['op_batch'],
            'pairs' => $pairs,
        ]);
    }

    /**
     * Link a saved OP batch to the file numbers just commissioned for it, in sequence.
     *
     * Called right after MlsFileNoController::generateBatch succeeds when the commissioning was
     * launched from a Batch OP Capture (the client passes the ordered `files` array it returns).
     * Pairs OP i <-> file i and completes the OP -> ToT pair the rest of this module expects:
     *
     *   OP  (pra, mlsFNo NULL, TEMP-xxxxx, prop_id P, party_2 = allottee)
     *     └─ source_op_id ─┐
     *   ToT (pra, mlsFNo = the commissioned number, temp_fileno = the OP's TEMP,
     *        prop_id = P  ← SHARED, party_1 = the OP's allottee, party_2 = the new holder)
     *
     * The OP is the only side that has a prop_id (plain commissioning allocates none — the
     * file's file_indexings.prop_id is NULL), so the commissioned file ADOPTS the OP's prop_id:
     * onto the new ToT row and onto file_indexings. `mls_file_no.op_batch` is stamped so the
     * batch is discoverable in the OP Batch Commissioning view. Idempotent: re-running updates
     * an existing ToT for the file instead of creating a second one.
     */
    public function linkOpBatchToCommissioned(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'op_batch' => 'required|string|max:50',
            'files' => 'required|array|min:1',
            'files.*' => 'nullable|string|max:100',
        ]);

        $opBatch = $validated['op_batch'];

        $ops = DB::connection('sqlsrv')->table('pra')
            ->where('op_batch', $opBatch)
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->orderBy('id')
            ->get();

        if ($ops->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No OPs found for batch ' . $opBatch . '.',
            ], 404);
        }

        $files = array_values(array_filter(
            $validated['files'],
            fn ($f) => trim((string) $f) !== ''
        ));

        $now = now();
        $pairs = [];
        try {
            DB::connection('sqlsrv')->transaction(function () use ($ops, $files, $opBatch, $now, &$pairs) {
                foreach ($ops as $i => $op) {
                    if (!isset($files[$i])) {
                        break; // fewer commissioned files than OPs — link what we can, in order
                    }
                    $fileNo = trim($files[$i]);

                    $mfn = DB::connection('sqlsrv')->table('mls_file_no')
                        ->where('full_file_number', $fileNo)->first();

                    $allottee  = trim((string) ($op->party_2 ?? ''));        // OP Part 2 -> ToT Part 1
                    $newHolder = trim((string) ($mfn->file_name ?? ''));     // commissioned applicant
                    $location  = ($mfn && !empty($mfn->location))
                        ? $mfn->location
                        : ($op->location ?: $op->property_description);

                    // Idempotency: update an existing ToT for this file rather than duplicating.
                    $existingTot = DB::connection('sqlsrv')->table('pra')
                        ->where('mlsFNo', $fileNo)
                        ->where('instrument_type', 'Transfer of Title (OP)')
                        ->first();

                    if ($existingTot) {
                        DB::connection('sqlsrv')->table('pra')->where('id', $existingTot->id)->update([
                            'prop_id' => $op->prop_id,
                            'temp_fileno' => $op->temp_fileno,
                            'source_op_table' => 'pra',
                            'source_op_id' => $op->id,
                            'party_1' => $allottee,
                            'Grantor' => $allottee,
                            'op_batch' => $opBatch,
                            'updated_at' => $now->toDateTimeString(),
                            'updated_by' => (string) Auth::id(),
                        ]);
                        $totId = $existingTot->id;
                    } else {
                        $totId = DB::connection('sqlsrv')->table('pra')->insertGetId([
                            'mlsFNo' => $fileNo,
                            'fileno' => $fileNo,
                            'temp_fileno' => $op->temp_fileno,   // pairs the ToT to its OP
                            'prop_id' => $op->prop_id,           // SHARED with the OP
                            'transaction_type' => 'Transfer of Title (OP)',
                            'instrument_type' => 'Transfer of Title (OP)',
                            'status' => 'Normal',
                            'op_type' => $op->op_type,
                            'op_serial_number' => $op->op_serial_number,
                            'transaction_date' => $op->transaction_date,
                            'location' => $location,
                            'property_description' => $location,
                            'plot_no' => ($mfn->plot_no ?? null) ?: $op->plot_no,
                            'tp_no' => ($mfn->tp_no ?? null) ?: $op->tp_no,
                            'lgsaOrCity' => ($mfn->lga ?? null) ?: $op->lgsaOrCity,
                            'land_use' => ($mfn->land_use ?? null) ?: $op->land_use,
                            'op_batch' => $opBatch,
                            'source_op_table' => 'pra',
                            'source_op_id' => $op->id,
                            'source' => 'OP Batch Commissioning',
                            'system_source' => 'OSSOPCHANGEOFNAME',
                            'Grantor' => $allottee,
                            'Grantee' => $newHolder,
                            'party_1' => $allottee,
                            'party_2' => $newHolder,
                            'created_by' => (string) Auth::id(),
                            'created_at' => $now->toDateTimeString(),
                            'updated_at' => $now->toDateTimeString(),
                            'is_deleted' => 0,
                        ]);
                    }

                    // The commissioned file adopts the OP's parcel identity.
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('file_number', $fileNo)
                        ->update(['prop_id' => $op->prop_id, 'updated_at' => $now]);

                    // Make the batch discoverable in the OP Batch Commissioning view.
                    DB::connection('sqlsrv')->table('mls_file_no')
                        ->where('full_file_number', $fileNo)
                        ->update(['op_batch' => $opBatch, 'updated_at' => $now]);

                    $pairs[] = [
                        'op_id' => $op->id,
                        'op_temp' => $op->temp_fileno,
                        'tot_id' => $totId,
                        'file_number' => $fileNo,
                        'prop_id' => $op->prop_id,
                        'party_1' => $allottee,
                        'party_2' => $newHolder,
                    ];
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('Link OP batch to commissioned failed', [
                'user' => Auth::id(), 'op_batch' => $opBatch, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Linking failed: ' . $e->getMessage()], 500);
        }

        Log::channel('op_batch')->info('Linked OP batch to commissioned files', [
            'user' => Auth::id(), 'op_batch' => $opBatch, 'pairs' => count($pairs),
        ]);

        return response()->json([
            'success' => true,
            'message' => count($pairs) . ' OP/ToT pair(s) linked under batch ' . $opBatch . '.',
            'op_batch' => $opBatch,
            'pairs' => $pairs,
        ]);
    }

    /**
     * Active districts, for the Location Builder's searchable dropdown. Values are the names
     * (that is what the composed location string uses).
     */
    public function opDistricts(): JsonResponse
    {
        $names = DB::connection('sqlsrv')->table('districts')
            ->where(function ($q) { $q->whereNull('is_active')->orWhere('is_active', 1); })
            ->whereRaw("NULLIF(LTRIM(RTRIM(name)), '') IS NOT NULL")
            ->orderBy('name')
            ->pluck('name')
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        return response()->json(['success' => true, 'count' => $names->count(), 'data' => $names]);
    }

    /**
     * Active LGAs, for the ToT card's searchable LGA dropdown.
     */
    public function opLgas(): JsonResponse
    {
        $names = DB::connection('sqlsrv')->table('lgas')
            ->where(function ($q) { $q->whereNull('is_active')->orWhere('is_active', 1); })
            ->whereRaw("NULLIF(LTRIM(RTRIM(name)), '') IS NOT NULL")
            ->orderBy('name')
            ->pluck('name')
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        return response()->json(['success' => true, 'count' => $names->count(), 'data' => $names]);
    }

    /**
     * Update an Awaiting TOT's details (Part 1/2, land use, TP/plot, location). Allowed whether
     * or not the TOT is already linked — this edits the ToT record itself. op_batch-guarded.
     */
    public function opUpdateTot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tot_pra_id' => 'required|integer',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'nullable|string|max:255',
            'land_use' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'plot_no' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:150',
            'location' => 'nullable|string|max:1000',
        ]);

        $tot = DB::connection('sqlsrv')->table('pra')->where('id', $validated['tot_pra_id'])->first();
        if (!$tot) {
            return response()->json(['success' => false, 'message' => 'TOT not found.'], 404);
        }
        if (empty($tot->op_batch)) {
            return response()->json(['success' => false, 'message' => 'That row is not part of the OP batch remediation.'], 422);
        }

        $update = ['updated_at' => now()->toDateTimeString(), 'updated_by' => (string) Auth::id()];
        // Only overwrite a field when the form actually submitted a non-empty value.
        $set = function (string $key) use (&$update, $validated) {
            $v = isset($validated[$key]) ? trim((string) $validated[$key]) : '';
            return $v !== '' ? $v : null;
        };
        if ($p1 = $set('party_1')) { $update['party_1'] = $p1; $update['Grantor'] = $p1; }
        if ($p2 = $set('party_2')) { $update['party_2'] = $p2; $update['Grantee'] = $p2; }
        if ($lu = $set('land_use')) { $update['land_use'] = $lu; }
        if ($tp = $set('tp_no')) { $update['tp_no'] = $tp; }
        if ($plot = $set('plot_no')) { $update['plot_no'] = $plot; }
        if ($loc = $set('location')) { $update['location'] = $loc; $update['property_description'] = $loc; }

        DB::connection('sqlsrv')->table('pra')->where('id', $tot->id)->update($update);

        Log::channel('op_batch')->info('TOT details updated', [
            'user' => Auth::id(), 'op_batch' => $tot->op_batch, 'tot_pra_id' => $tot->id,
            'fields' => array_keys(array_diff_key($update, ['updated_at' => 1, 'updated_by' => 1])),
        ]);

        return response()->json(['success' => true, 'message' => 'TOT details updated.', 'op_batch' => $tot->op_batch]);
    }

    /**
     * Rows belonging to one op_batch batch, for the OP Batch Commissioning modal.
     *
     * These are Transfer of Title (OP) rows awaiting an OP: the batch run created the ToT
     * but never the paired OP row, so every row here has no OP sibling on its prop_id.
     */
    public function opBatchRecords(Request $request): JsonResponse
    {
        $batchNo = trim((string) $request->query('batch_no'));
        if ($batchNo === '') {
            Log::channel('op_batch')->warning('opBatchRecords called without batch_no', [
                'user' => Auth::id(),
            ]);
            return response()->json(['success' => false, 'message' => 'batch_no is required'], 422);
        }

        $startedAt = microtime(true);

        $rows = DB::connection('sqlsrv')
            ->table('mls_file_no as m')
            ->leftJoin('pra as p', function ($join) {
                $join->whereRaw("UPPER(LTRIM(RTRIM(p.mlsFNo))) = UPPER(LTRIM(RTRIM(m.full_file_number)))")
                    ->whereRaw("p.op_batch IS NOT NULL");
            })
            ->where('m.batch_no', $batchNo)
            ->whereNotNull('m.op_batch')
            ->select([
                'm.op_batch',
                'm.full_file_number',
                'm.batch_no',
                'm.serial_number',
                'm.file_name',
                'm.land_use as mls_land_use',
                'p.id as pra_id',
                'p.prop_id',
                'p.instrument_type',
                'p.transaction_type',
                'p.op_type',
                'p.op_serial_number',
                'p.party_1',
                'p.party_2',
                'p.source_op_id',
                'p.source_op_table',
                // pra first, mls_file_no behind it — so values saved onto the pra ToT row
                // (via Update ToT) are reflected here.
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.plot_no)), ''), NULLIF(LTRIM(RTRIM(m.plot_no)), '')) as plot_no"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.tp_no)), ''), NULLIF(LTRIM(RTRIM(m.tp_no)), '')) as tp_no"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.land_use)), ''), NULLIF(LTRIM(RTRIM(m.land_use)), '')) as land_use"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.lgsaOrCity)), ''), NULLIF(LTRIM(RTRIM(m.lga)), '')) as lga"),
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(p.location)), ''),
                    NULLIF(LTRIM(RTRIM(p.property_description)), ''),
                    NULLIF(LTRIM(RTRIM(m.location)), '')
                ) as location"),
            ])
            ->orderBy('m.serial_number')
            ->get()
            ->map(function ($r) {
                // Linked either explicitly (source_op_id — how this screen links them) or by
                // the shared-prop_id pairing directOpCapture uses. Resolve the paired OP row so
                // its TEMP file number can be shown/clicked, checking both mechanisms.
                $opRow = null;
                if (!empty($r->source_op_id) && $r->source_op_table === 'pra') {
                    $opRow = DB::connection('sqlsrv')->table('pra')->where('id', $r->source_op_id)
                        ->select('id', 'temp_fileno', 'fileno', 'prop_id')->first();
                } elseif (!empty($r->source_op_id) && $r->source_op_table === 'instrument_capture') {
                    $opRow = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $r->source_op_id)
                        ->select('id', 'temp_fileno', 'prop_id')->first();
                }
                if (!$opRow && !empty($r->prop_id)) {
                    $opRow = DB::connection('sqlsrv')->table('pra')
                        ->where('prop_id', $r->prop_id)
                        ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
                        ->select('id', 'temp_fileno', 'fileno', 'prop_id')
                        ->orderByDesc('id')->first();
                }
                $hasOp = (bool) $opRow || !empty($r->source_op_id);
                $opTemp = $opRow ? ($opRow->temp_fileno ?: ($opRow->fileno ?? null)) : null;

                return [
                    'source_op_id' => $r->source_op_id,
                    'source_op_table' => $r->source_op_table,
                    'op_pra_id' => $opRow->id ?? ($r->source_op_id ?: null),
                    'op_temp_fileno' => $opTemp ?: null,
                    'op_batch' => $r->op_batch,
                    'file_number' => strtoupper((string) $r->full_file_number),
                    'batch_no' => $r->batch_no,
                    'serial_number' => $r->serial_number,
                    'file_name' => $r->file_name ? strtoupper((string) $r->file_name) : '—',
                    'pra_id' => $r->pra_id,
                    'prop_id' => $r->prop_id ?: '—',
                    'instrument_type' => $r->instrument_type ?: '—',
                    'transaction_type' => $r->transaction_type ?: '—',
                    'op_type' => $r->op_type ?: '—',
                    'op_serial_number' => $r->op_serial_number ?: '—',
                    'party_1' => $r->party_1 ?: '—',
                    'party_2' => $r->party_2 ?: '—',
                    'plot_no' => $r->plot_no ?: '—',
                    'tp_no' => $r->tp_no ?: '—',
                    'land_use' => $r->land_use ?: '—',
                    'lga' => $r->lga ?: '—',
                    'location' => $r->location ?: '—',
                    'has_op' => $hasOp,
                    'awaiting_op' => !$hasOp,
                ];
            });

        $payload = [
            'success' => true,
            'batch_no' => $batchNo,
            'count' => $rows->count(),
            'awaiting_op' => $rows->where('awaiting_op', true)->count(),
            'data' => $rows->values(),
        ];

        Log::channel('op_batch')->info('opBatchRecords served', [
            'user' => Auth::id(),
            'batch_no' => $batchNo,
            'count' => $payload['count'],
            'awaiting_op' => $payload['awaiting_op'],
            'no_pra_row' => $rows->whereNull('pra_id')->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'payload_kb' => round(strlen(json_encode($payload)) / 1024, 1),
        ]);

        if ($rows->isEmpty()) {
            Log::channel('op_batch')->warning('Batch resolved to zero rows', ['batch_no' => $batchNo]);
        }

        return response()->json($payload);
    }

    /**
     * Update the land_use on an instrument_capture record.
     * Called from the Bill modal when a record has no land_use set.
     */
    public function updateLandUse(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'land_use' => 'required|string|in:residential,commercial,industrial,agricultural',
        ]);

        $table = DB::connection('sqlsrv')->table('instrument_capture');
        $existing = $table->where('id', $id)->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        $newLandUse = ucfirst($request->input('land_use'));
        $currentLandUse = trim((string) ($existing->land_use ?? ''));

        if (strcasecmp($currentLandUse, $newLandUse) === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Land use already up to date.',
            ]);
        }

        $table
            ->where('id', $id)
            ->update([
                'land_use' => $newLandUse,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Land use updated successfully.',
        ]);
    }

    /**
     * Registration number for the OP Details card: the stored regNo, or — when it is blank —
     * composed from serialNo/pageNo/volumeNo (that is exactly the regNo format, e.g. 48/48/27).
     */
    private function composeRegNo($row): ?string
    {
        $reg = trim((string) ($row->regNo ?? ''));
        if ($reg !== '') {
            return $reg;
        }
        $parts = array_filter([
            trim((string) ($row->serialNo ?? '')),
            trim((string) ($row->pageNo ?? '')),
            trim((string) ($row->volumeNo ?? '')),
        ], fn($v) => $v !== '');

        return $parts ? implode('/', $parts) : null;
    }

    /**
     * Return the first non-empty (trimmed) value from the given candidates.
     *
     * PHP's `??` only falls through on null, so a role column that holds an
     * empty string (e.g. Grantee = '') would short-circuit the chain and hide
     * a populated generic party_1/party_2. OP rows store the allottee in
     * party_2 while leaving Grantee/Assignee blank, so we must skip empties.
     */
    private function firstFilledParty(...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) ($value ?? ''));
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Return all PRA transactions for a given prop_id, ordered chronologically.
     */
    public function praTransactions(Request $request): JsonResponse
    {
        $propId = $request->input('prop_id');
        $parentPropId = $request->input('parent_prop_id');
        $mlsFileno = $request->input('mls_fileno');
        $tempFileno = $request->input('temp_fileno');
        $sourcePraId = $request->input('source_pra_id');

        if (!$propId && !$parentPropId && !$mlsFileno && !$tempFileno && !$sourcePraId) {
            return response()->json(['success' => false, 'message' => 'prop_id, mls_fileno, temp_fileno, or source_pra_id is required.'], 422);
        }

        // Auto-discover parent_prop_id if not provided
        if ($propId && !$parentPropId) {
            $parentPropId = DB::connection('sqlsrv')->table('pra')
                ->where('prop_id', $propId)
                ->whereNotNull('parent_prop_id')
                ->value('parent_prop_id');
        }

        // PRA rows
        $praRows = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) {
                // Exclude soft-deleted PRA rows so the OP Details modal does not
                // show stale/cancelled transactions.
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) use ($propId, $mlsFileno, $tempFileno, $sourcePraId, $parentPropId) {
                // Primary identifiers
                $hasPrimary = false;
                if ($propId) {
                    $q->orWhere('prop_id', $propId);
                    $hasPrimary = true;
                }
                if ($parentPropId) {
                    $q->orWhere('prop_id', $parentPropId);
                    $q->orWhere('parent_prop_id', $parentPropId);
                    $hasPrimary = true;
                }

                // Also search for children if this is the parent
                if ($propId) {
                    $q->orWhere('parent_prop_id', $propId);
                }

                // Fallback to file numbers only if no prop_id was found or if we want to discover siblings.
                // To avoid the Plot 62 vs Plot C-97 conflict, we only include the file number 
                // match if we don't have a specific property context yet.
                if (!$hasPrimary) {
                    if ($mlsFileno) {
                        $q->orWhere('mlsFNo', $mlsFileno)
                            ->orWhere('fileno', $mlsFileno);
                    }
                    if ($tempFileno) {
                        $q->orWhere('temp_fileno', $tempFileno);
                    }
                }
            })
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'source_table' => 'pra',
                    'prop_id' => $row->prop_id ?? null,
                    'fileno' => $row->fileno ?? null,
                    'mlsFNo' => $row->mlsFNo ?? null,
                    'temp_fileno' => $row->temp_fileno ?? null,
                    'instrument_type' => $row->instrument_type ?? null,
                    'transaction_type' => $row->transaction_type ?? null,
                    'op_type' => $row->op_type ?? null,
                    'op_serial_number' => $row->op_serial_number ?? null,
                    'registration_number' => $this->composeRegNo($row),
                    'party_1_name' => $this->firstFilledParty($row->Grantor ?? null, $row->Assignor ?? null, $row->Mortgagor ?? null, $row->party_1 ?? null),
                    'party_2_name' => $this->firstFilledParty($row->Grantee ?? null, $row->Assignee ?? null, $row->Mortgagee ?? null, $row->party_2 ?? null),
                    'property_description' => $row->property_description ?? null,
                    'land_use' => $row->land_use ?? null,
                    'lga' => $row->lgsaOrCity ?? null,
                    'location' => $row->location ?? null,
                    'tp_no' => $row->tp_no ?? null,
                    'plot_number' => $row->plot_no ?? null,
                    'merger_group_id' => $row->merger_group_id ?? null,
                    'is_merger_op' => isset($row->is_merger_op) ? (int) $row->is_merger_op : 0,
                    'transaction_date' => $row->transaction_date ?? null,
                    'created_at' => $row->created_at ?? null,
                ];
            });

        // If the matched records are in a merger group, include all OP rows in the same group.
        // This ensures OP Details shows the full merged OP history.
        $mergerGroupIds = $praRows
            ->pluck('merger_group_id')
            ->filter(fn($v) => !empty($v))
            ->unique()
            ->values();

        if ($mergerGroupIds->isNotEmpty()) {
            $mergerPraRows = DB::connection('sqlsrv')
                ->table('pra')
                ->whereIn('merger_group_id', $mergerGroupIds->all())
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function ($q) {
                    $q->where('is_merger_op', 1)
                        ->orWhere('instrument_type', 'LIKE', '%Occupancy Permit%');
                })
                ->orderBy('id')
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'source_table' => 'pra',
                        'prop_id' => $row->prop_id ?? null,
                        'fileno' => $row->fileno ?? null,
                        'mlsFNo' => $row->mlsFNo ?? null,
                        'temp_fileno' => $row->temp_fileno ?? null,
                        'instrument_type' => $row->instrument_type ?? null,
                        'transaction_type' => $row->transaction_type ?? null,
                        'op_type' => $row->op_type ?? null,
                        'op_serial_number' => $row->op_serial_number ?? null,
                        'registration_number' => $this->composeRegNo($row),
                        'party_1_name' => $this->firstFilledParty($row->Grantor ?? null, $row->Assignor ?? null, $row->Mortgagor ?? null, $row->party_1 ?? null),
                        'party_2_name' => $this->firstFilledParty($row->Grantee ?? null, $row->Assignee ?? null, $row->Mortgagee ?? null, $row->party_2 ?? null),
                        'property_description' => $row->property_description ?? null,
                        'land_use' => $row->land_use ?? null,
                        'lga' => $row->lgsaOrCity ?? null,
                        'location' => $row->location ?? null,
                        'tp_no' => $row->tp_no ?? null,
                        'plot_number' => $row->plot_no ?? null,
                        'merger_group_id' => $row->merger_group_id ?? null,
                        'is_merger_op' => isset($row->is_merger_op) ? (int) $row->is_merger_op : 0,
                        'transaction_date' => $row->transaction_date ?? null,
                        'created_at' => $row->created_at ?? null,
                    ];
                });

            $praRows = $praRows
                ->concat($mergerPraRows)
                ->unique('id')
                ->values();
        }

        // IC (instrument_capture) rows — the OP source of truth
        $icRows = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) use ($propId, $mlsFileno, $tempFileno, $parentPropId) {
                $hasPrimary = false;
                if ($propId) {
                    $q->orWhere('prop_id', $propId);
                    $hasPrimary = true;
                }
                if ($parentPropId) {
                    $q->orWhere('prop_id', $parentPropId);
                    $hasPrimary = true;
                }

                if (!$hasPrimary) {
                    if ($mlsFileno) {
                        $q->orWhere('mlsFNo', $mlsFileno)
                            ->orWhere('kangisFileNo', $mlsFileno)
                            ->orWhere('NewKANGISFileno', $mlsFileno);
                    }
                    if ($tempFileno) {
                        $q->orWhere('temp_fileno', $tempFileno);
                    }
                }
            })
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'source_table' => 'instrument_capture',
                    'prop_id' => $row->prop_id ?? null,
                    'fileno' => $row->mlsFNo ?? $row->kangisFileNo ?? $row->NewKANGISFileno ?? null,
                    'mlsFNo' => $row->mlsFNo ?? null,
                    'temp_fileno' => $row->temp_fileno ?? null,
                    'instrument_type' => $row->instrument_type ?? null,
                    'transaction_type' => $row->transaction_type ?? $row->instrument_type ?? null,
                    'op_type' => $row->op_type ?? null,
                    'op_serial_number' => $row->op_serial_number ?? null,
                    'registration_number' => $row->registration_number ?? null,
                    'party_1_name' => $row->party_1_name ?? null,
                    'party_2_name' => $row->party_2_name ?? null,
                    'property_description' => $row->property_location ?? null,
                    'land_use' => $row->land_use ?? null,
                    'lga' => $row->lga ?? null,
                    'location' => $row->property_location ?? null,
                    'tp_no' => $row->tp_no ?? null,
                    'plot_number' => $row->plot_number ?? null,
                    'merger_group_id' => $row->merger_group_id ?? null,
                    'is_merger_op' => isset($row->is_merger_op) ? (int) $row->is_merger_op : 0,
                    'transaction_date' => $row->instrument_date ?? null,
                    'created_at' => $row->created_at ?? null,
                ];
            });

        // Follow explicit OP<->ToT links (source_op_id) even when prop_id differs. The OP Details
        // card groups strictly by prop_id, so an OP that was linked to its ToT but never had its
        // prop_id aligned (the "two rival linking mechanisms" case) would otherwise be invisible.
        // Pull those linked rows in explicitly, in both directions.
        $currentPraIds = $praRows->pluck('id')->filter()->values();
        if ($currentPraIds->isNotEmpty()) {
            $links = DB::connection('sqlsrv')->table('pra')
                ->whereIn('id', $currentPraIds->all())
                ->whereNotNull('source_op_id')
                ->get(['source_op_id', 'source_op_table']);

            // Forward: OP rows that our ToT rows point at.
            $linkedPraIds = $links->where('source_op_table', 'pra')->pluck('source_op_id')->filter()->unique();
            $linkedIcIds  = $links->where('source_op_table', 'instrument_capture')->pluck('source_op_id')->filter()->unique();

            // Reverse: ToT rows that point AT any pra row currently in the result (opened via the OP).
            $reverseTots = DB::connection('sqlsrv')->table('pra')
                ->where('source_op_table', 'pra')
                ->whereIn('source_op_id', $currentPraIds->all())
                ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                ->pluck('id');
            $linkedPraIds = $linkedPraIds->concat($reverseTots)->unique();

            $havePraIds = $praRows->pluck('id')->all();
            $missingPraIds = $linkedPraIds->reject(fn ($id) => in_array($id, $havePraIds))->values();
            if ($missingPraIds->isNotEmpty()) {
                $extraPra = DB::connection('sqlsrv')->table('pra')
                    ->whereIn('id', $missingPraIds->all())
                    ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                    ->orderBy('id')->get()
                    ->map(fn ($row) => $this->mapPraTransactionRow($row));
                $praRows = $praRows->concat($extraPra)->unique('id')->values();
            }

            $haveIcIds = $icRows->pluck('id')->all();
            $missingIcIds = $linkedIcIds->reject(fn ($id) => in_array($id, $haveIcIds))->values();
            if ($missingIcIds->isNotEmpty()) {
                $extraIc = DB::connection('sqlsrv')->table('instrument_capture')
                    ->whereIn('id', $missingIcIds->all())
                    ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                    ->orderBy('id')->get()
                    ->map(fn ($row) => $this->mapIcTransactionRow($row));
                $icRows = $icRows->concat($extraIc)->unique('id')->values();
            }
        }

        // Merge: IC (source OP) first, then PRA (Transfer of Title etc.)
        // Sort so Occupancy Permit records appear before Transfer of Title.
        $rows = $icRows->merge($praRows)->sortBy(function ($item) {
            return stripos($item['instrument_type'] ?? '', 'Transfer of Title') !== false ? 1 : 0;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Shape a raw pra row into the flat transaction array praTransactions returns.
     * (Mirrors the inline map used for the primary pra query; shared by the source_op_id follow.)
     */
    private function mapPraTransactionRow($row): array
    {
        return [
            'id' => $row->id,
            'source_table' => 'pra',
            'prop_id' => $row->prop_id ?? null,
            'fileno' => $row->fileno ?? null,
            'mlsFNo' => $row->mlsFNo ?? null,
            'temp_fileno' => $row->temp_fileno ?? null,
            'instrument_type' => $row->instrument_type ?? null,
            'transaction_type' => $row->transaction_type ?? null,
            'op_type' => $row->op_type ?? null,
            'op_serial_number' => $row->op_serial_number ?? null,
            'registration_number' => $this->composeRegNo($row),
            'party_1_name' => $this->firstFilledParty($row->Grantor ?? null, $row->Assignor ?? null, $row->Mortgagor ?? null, $row->party_1 ?? null),
            'party_2_name' => $this->firstFilledParty($row->Grantee ?? null, $row->Assignee ?? null, $row->Mortgagee ?? null, $row->party_2 ?? null),
            'property_description' => $row->property_description ?? null,
            'land_use' => $row->land_use ?? null,
            'lga' => $row->lgsaOrCity ?? null,
            'location' => $row->location ?? null,
            'tp_no' => $row->tp_no ?? null,
            'plot_number' => $row->plot_no ?? null,
            'merger_group_id' => $row->merger_group_id ?? null,
            'is_merger_op' => isset($row->is_merger_op) ? (int) $row->is_merger_op : 0,
            'transaction_date' => $row->transaction_date ?? null,
            'created_at' => $row->created_at ?? null,
        ];
    }

    /**
     * Shape a raw instrument_capture OP row into the flat transaction array praTransactions returns.
     */
    private function mapIcTransactionRow($row): array
    {
        return [
            'id' => $row->id,
            'source_table' => 'instrument_capture',
            'prop_id' => $row->prop_id ?? null,
            'fileno' => $row->mlsFNo ?? $row->kangisFileNo ?? $row->NewKANGISFileno ?? null,
            'mlsFNo' => $row->mlsFNo ?? null,
            'temp_fileno' => $row->temp_fileno ?? null,
            'instrument_type' => $row->instrument_type ?? null,
            'transaction_type' => $row->transaction_type ?? $row->instrument_type ?? null,
            'op_type' => $row->op_type ?? null,
            'op_serial_number' => $row->op_serial_number ?? null,
            'registration_number' => $row->registration_number ?? null,
            'party_1_name' => $row->party_1_name ?? null,
            'party_2_name' => $row->party_2_name ?? null,
            'property_description' => $row->property_location ?? null,
            'land_use' => $row->land_use ?? null,
            'lga' => $row->lga ?? null,
            'location' => $row->property_location ?? null,
            'tp_no' => $row->tp_no ?? null,
            'plot_number' => $row->plot_number ?? null,
            'merger_group_id' => $row->merger_group_id ?? null,
            'is_merger_op' => isset($row->is_merger_op) ? (int) $row->is_merger_op : 0,
            'transaction_date' => $row->instrument_date ?? null,
            'created_at' => $row->created_at ?? null,
        ];
    }

    /**
     * Update OP Change-of-Name row details from Edit modal.
     */
    public function updateDetails(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_type' => 'required|string|in:Individual,Corporate,Multiple',
            'land_use' => 'nullable|string|max:50',
            'purpose' => 'nullable|string|max:255',
            'instrument_type' => 'nullable|string|max:255',
            'file_name' => 'required|string|max:500',
            'plot_number' => 'nullable|string|max:100',
            'tp_number' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:1000',
            'lga' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'party_1_name' => 'nullable|string|max:500',
            'party_1_phone' => 'nullable|string|max:50',
            'party_1_address' => 'nullable|string|max:1000',
            'party_2_name' => 'nullable|string|max:500',
            'party_2_phone' => 'nullable|string|max:50',
            'party_2_address' => 'nullable|string|max:1000',
            'applicant_phone' => 'nullable|string|max:50',
            'applicant_address' => 'nullable|string|max:1000',
            'pra_id' => 'nullable|integer',
            'row_type' => 'nullable|string|in:op,transfer_of_title',
            'op_type' => 'nullable|string|max:50',
            'op_serial_number' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:100',
            'page_no' => 'nullable|string|max:100',
            'volume_no' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:255',
            'reg_date' => 'nullable|date',
            'reg_time' => 'nullable|string|max:10',
            'transaction_date' => 'nullable|date',
            'instrument_date' => 'nullable|date',
        ]);

        // Support pra-{praId} and ic-{icId} format for records without a fileNumber row
        $praOnlyMode = false;
        $praDirectId = null;
        $icOnlyMode = false;
        $icDirectId = null;
        if (str_starts_with($id, 'pra-')) {
            $praOnlyMode = true;
            $praDirectId = (int) substr($id, 4);
        } elseif (str_starts_with($id, 'ic-')) {
            $icOnlyMode = true;
            $icDirectId = (int) substr($id, 3);
        } else {
            $id = (int) $id;
        }

        $base = null;

        // IC-only: build a minimal $base from the instrument_capture record
        if ($icOnlyMode) {
            $icRow = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $icDirectId)->first();
            if (!$icRow) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }
            $mlsfNo = $icRow->mlsFNo ?: ($icRow->temp_fileno ?? null);
            $base = (object) [
                'id' => 0,
                'mlsfNo' => $mlsfNo,
                'tracking_id' => null,
                'mls_file_no_id' => null,
                'source_instrument_capture_id' => $icDirectId,
                'source_pra_id' => null,
            ];
        }

        if (!$praOnlyMode && !$icOnlyMode) {
            $base = DB::connection('sqlsrv')
                ->table('fileNumber as fn')
                ->leftJoin('mls_file_no as mfn', 'fn.tracking_id', '=', 'mfn.tracking_id')
                ->select([
                    'fn.id',
                    'fn.mlsfNo',
                    'fn.tracking_id',
                    'mfn.id as mls_file_no_id',
                    'mfn.source_instrument_capture_id',
                    'mfn.source_pra_id',
                ])
                ->where('fn.id', $id)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('pra')
                        ->where('pra.system_source', 'OSSOPCHANGEOFNAME')
                        ->whereRaw("(pra.mlsFNo = fn.mlsfNo OR pra.fileno = fn.mlsfNo)");
                })
                ->first();
        }

        // PRA-only fallback: build a minimal $base from the PRA record
        if ($praOnlyMode) {
            $praRow = DB::connection('sqlsrv')->table('pra')->where('id', $praDirectId)->first();
            if (!$praRow) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }
            $mlsfNo = $praRow->mlsFNo ?: ($praRow->fileno ?? null);
            $base = (object) [
                'id' => 0,
                'mlsfNo' => $mlsfNo,
                'tracking_id' => null,
                'mls_file_no_id' => null,
                'source_instrument_capture_id' => null,
                'source_pra_id' => $praDirectId,
            ];
            // Set pra_id in validated so PRA updates target this row
            $validated['pra_id'] = $praDirectId;
        }

        // Numeric-id fallback: the strict whereExists above only matches
        // fileNumber rows whose linked PRA carries `system_source =
        // 'OSSOPCHANGEOFNAME'`. Older / re-imported PRA rows may not have
        // that tag, which made saves return "Record not found." even though
        // the transactions list loaded fine. If the caller already selected
        // a specific transaction (`pra_id` in payload), trust that pointer
        // and build a minimal $base from the PRA row.
        if (!$base && !$praOnlyMode && !$icOnlyMode && !empty($validated['pra_id'])) {
            $praRow = DB::connection('sqlsrv')->table('pra')
                ->where('id', (int) $validated['pra_id'])
                ->first();
            if ($praRow) {
                $mlsfNo = $praRow->mlsFNo ?: ($praRow->fileno ?? null);
                $base = (object) [
                    'id' => 0,
                    'mlsfNo' => $mlsfNo,
                    'tracking_id' => null,
                    'mls_file_no_id' => null,
                    'source_instrument_capture_id' => null,
                    'source_pra_id' => (int) $validated['pra_id'],
                ];
            }
        }

        if (!$base) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        $fileNumberTable = 'fileNumber';
        $captureTable = 'instrument_capture';



        // Normalise all optional keys so every subsequent access is safe
        // regardless of which card (OP or ToT) submitted the request.
        $validated += [
            'plot_number' => null,
            'tp_number' => null,
            'location' => null,
            'lga' => null,
            'district' => null,
            'purpose' => null,
            'instrument_type' => null,
            'land_use' => null,
            'op_type' => null,
            'op_serial_number' => null,
            'serial_no' => null,
            'page_no' => null,
            'volume_no' => null,
            'registration_number' => null,
            'reg_date' => null,
            'reg_time' => null,
            'transaction_date' => null,
            'instrument_date' => null,
            'party_1_name' => null,
            'party_1_phone' => null,
            'party_1_address' => null,
            'party_2_name' => null,
            'party_2_phone' => null,
            'party_2_address' => null,
            'applicant_phone' => null,
            'applicant_address' => null,
            'pra_id' => null,
            'row_type' => null,
        ];

        $rowType = $validated['row_type'] ?? null;
        $isTransferOfTitle = ($rowType === 'transfer_of_title');
        $submittedPayload = $request->all();
        $wasSubmitted = static function (string $key) use ($submittedPayload): bool {
            return array_key_exists($key, $submittedPayload);
        };

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // 1) Update fileNumber table (core listing source) — skip for PRA-only
            $fileUpdates = [];
            if (Schema::connection('sqlsrv')->hasColumn($fileNumberTable, 'FileName')) {
                $fileUpdates['FileName'] = strtoupper(trim((string) $validated['file_name']));
            }
            if ($wasSubmitted('plot_number') && Schema::connection('sqlsrv')->hasColumn($fileNumberTable, 'plot_no')) {
                $fileUpdates['plot_no'] = ($validated['plot_number'] ?? null) ?: null;
            }
            if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn($fileNumberTable, 'tp_no')) {
                $fileUpdates['tp_no'] = ($validated['tp_number'] ?? null) ?: null;
            }
            if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn($fileNumberTable, 'location')) {
                $fileUpdates['location'] = $validated['location'] ?: null;
            }
            if ($wasSubmitted('lga') && Schema::connection('sqlsrv')->hasColumn($fileNumberTable, 'lga')) {
                $fileUpdates['lga'] = $validated['lga'] ?: null;
            }
            // Ensure dedicated source is always stamped
            $fileUpdates['SOURCE'] = 'OSS_CHANGE_OF_NAME';
            if ($base->id) {
                DB::connection('sqlsrv')->table($fileNumberTable)->where('id', $base->id)->update($fileUpdates);
            }

            // 2) Intentionally skip mls_file_no updates in Update OP flow.
            // Requirement: updating OP/ToT from this page must not mutate mls_file_no.

            // 3) Update linked instrument_capture for extra fields (purpose, district, phone, address)
            $captureId = $base->source_instrument_capture_id;
            if (!$captureId && !empty($base->mlsfNo)) {
                $captureId = DB::connection('sqlsrv')
                    ->table($captureTable)
                    ->where('mlsFNo', $base->mlsfNo)
                    ->orderByDesc('id')
                    ->value('id');
            }

            // Do NOT create a skeleton instrument_capture record here.
            // OP Change-of-Name data (phone, address, district, etc.) is persisted
            // on PRA and mls_file_no. IC rows are only created by the Deeds
            // Registration flow (InstrumentCaptureService::capture).

            if ($captureId && !$isTransferOfTitle) {
                $captureUpdates = [];
                // Party 1 on the source IC row (OP) is always Kano State Government.
                // Party 2 on IC is the original allottee = file_name.
                $icParty1Name = 'KANO STATE GOVERNMENT';
                $icParty2Name = strtoupper(trim((string) $validated['file_name']));
                $party2Name = !empty($validated['party_2_name'])
                    ? strtoupper(trim((string) $validated['party_2_name']))
                    : $icParty2Name;
                $party1Phone = !empty($validated['party_1_phone']) ? trim((string) $validated['party_1_phone']) : null;
                $party1Address = !empty($validated['party_1_address']) ? trim((string) $validated['party_1_address']) : null;
                $party2Phone = !empty($validated['party_2_phone'])
                    ? trim((string) $validated['party_2_phone'])
                    : (!empty($validated['applicant_phone']) ? trim((string) $validated['applicant_phone']) : null);
                $party2Address = !empty($validated['party_2_address'])
                    ? trim((string) $validated['party_2_address'])
                    : (!empty($validated['applicant_address']) ? trim((string) $validated['applicant_address']) : null);

                if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'customer_type')) {
                    $captureUpdates['customer_type'] = $validated['customer_type'];
                }
                if ($wasSubmitted('land_use') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'land_use')) {
                    $captureUpdates['land_use'] = !empty($validated['land_use']) ? strtoupper(trim((string) $validated['land_use'])) : null;
                }
                if ($wasSubmitted('purpose') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'purpose')) {
                    $captureUpdates['purpose'] = $validated['purpose'] ?: null;
                }
                if ($wasSubmitted('instrument_type') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'instrument_type')) {
                    $captureUpdates['instrument_type'] = $validated['instrument_type'] ?: null;
                }
                if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_name')) {
                    $captureUpdates['party_1_name'] = $icParty1Name;
                }
                if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_name')) {
                    $captureUpdates['party_2_name'] = $icParty2Name;
                }
                if ($wasSubmitted('plot_number') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'plot_number')) {
                    $captureUpdates['plot_number'] = ($validated['plot_number'] ?? null) ?: null;
                }
                if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'tp_no')) {
                    $captureUpdates['tp_no'] = $validated['tp_number'] ?: null;
                }
                if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'survey_plan_no')) {
                    $captureUpdates['survey_plan_no'] = $validated['tp_number'] ?: null;
                }
                if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'property_description')) {
                    $captureUpdates['property_description'] = $validated['location'] ?: null;
                }
                if ($wasSubmitted('lga') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'lga')) {
                    $captureUpdates['lga'] = $validated['lga'] ?: null;
                }
                if ($wasSubmitted('district') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'district')) {
                    $captureUpdates['district'] = $validated['district'] ?: null;
                }
                if ($wasSubmitted('op_type') && !empty($validated['op_type']) && Schema::connection('sqlsrv')->hasColumn($captureTable, 'op_type')) {
                    $captureUpdates['op_type'] = $validated['op_type'];
                }
                if ($wasSubmitted('op_serial_number') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'op_serial_number')) {
                    $captureUpdates['op_serial_number'] = $validated['op_serial_number'] ?: null;
                }
                if ($wasSubmitted('serial_no') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'serial_no')) {
                    $captureUpdates['serial_no'] = $validated['serial_no'] ?: null;
                }
                if ($wasSubmitted('page_no') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'page_no')) {
                    $captureUpdates['page_no'] = $validated['page_no'] ?: null;
                }
                if ($wasSubmitted('volume_no') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'volume_no')) {
                    $captureUpdates['volume_no'] = $validated['volume_no'] ?: null;
                }
                if ($wasSubmitted('registration_number') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'registration_number')) {
                    $captureUpdates['registration_number'] = $validated['registration_number'] ?: null;
                }
                if ($wasSubmitted('reg_date')) {
                    if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'deeds_date')) {
                        $captureUpdates['deeds_date'] = $validated['reg_date'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($captureTable, 'reg_date')) {
                        $captureUpdates['reg_date'] = $validated['reg_date'] ?: null;
                    }
                }
                if ($wasSubmitted('reg_time')) {
                    if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'deeds_time')) {
                        $captureUpdates['deeds_time'] = $validated['reg_time'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($captureTable, 'reg_time')) {
                        $captureUpdates['reg_time'] = $validated['reg_time'] ?: null;
                    }
                }
                if ($wasSubmitted('transaction_date') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'transaction_date')) {
                    $captureUpdates['transaction_date'] = $validated['transaction_date'] ?: null;
                }
                if ($wasSubmitted('instrument_date') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'instrument_date')) {
                    $captureUpdates['instrument_date'] = $validated['instrument_date'] ?: null;
                }
                if ($wasSubmitted('party_1_phone') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_phone')) {
                    $captureUpdates['party_1_phone'] = $party1Phone;
                }
                if ($wasSubmitted('party_1_address') && Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_address')) {
                    $captureUpdates['party_1_address'] = $party1Address;
                }
                if (($wasSubmitted('party_2_phone') || $wasSubmitted('applicant_phone')) && Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_phone')) {
                    $captureUpdates['party_2_phone'] = $party2Phone;
                }
                if (($wasSubmitted('party_2_address') || $wasSubmitted('applicant_address')) && Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_address')) {
                    $captureUpdates['party_2_address'] = $party2Address;
                }
                if (Schema::connection('sqlsrv')->hasColumn($captureTable, 'updated_at')) {
                    $captureUpdates['updated_at'] = now();
                }

                if (!empty($captureUpdates)) {
                    DB::connection('sqlsrv')->table($captureTable)->where('id', $captureId)->update($captureUpdates);
                }
            }

            // 4) Update PRA rows tied to this source so parties/land/purpose stay aligned.
            $praTable = 'pra';
            $targetPraId = !empty($validated['pra_id']) ? (int) $validated['pra_id'] : null;
            $praPropId = null;

            // When a specific PRA row is selected, target it directly
            if ($targetPraId) {
                $targetPraRow = DB::connection('sqlsrv')
                    ->table($praTable)
                    ->where('id', $targetPraId)
                    ->first();
                $praPropId = $targetPraRow->prop_id ?? null;

                // Derive row type from the actual PRA row rather than trusting the
                // client-submitted row_type. The Edit modal auto-selects a
                // transaction card on open, and a stale/mismatched client
                // selection must never be allowed to write OP-only party rules
                // (Grantor = Kano State Government) onto what is really a
                // Transfer of Title row (or vice versa).
                if ($targetPraRow) {
                    $targetRowInstrumentType = (string) ($targetPraRow->instrument_type ?? $targetPraRow->transaction_type ?? '');
                    $isTransferOfTitle = stripos($targetRowInstrumentType, 'Transfer of Title') !== false;
                }
            }

            if (!$praPropId && !empty($base->source_pra_id)) {
                $praPropId = DB::connection('sqlsrv')
                    ->table($praTable)
                    ->where('id', $base->source_pra_id)
                    ->value('prop_id');
            }
            if (!$praPropId && !empty($captureId)) {
                $praPropId = DB::connection('sqlsrv')
                    ->table($captureTable)
                    ->where('id', $captureId)
                    ->value('prop_id');
            }

            // Fallback: resolve PRA by MLS file number when linkage columns are missing.
            if (!$praPropId && !empty($base->mlsfNo)) {
                $praPropId = DB::connection('sqlsrv')
                    ->table($praTable)
                    ->where('system_source', 'OSSOPCHANGEOFNAME')
                    ->where(function ($q) use ($base) {
                        $q->where('mlsFNo', $base->mlsfNo)
                            ->orWhere('fileno', $base->mlsfNo);
                    })
                    ->orderByDesc('id')
                    ->value('prop_id');
            }

            if ($praPropId) {
                // For the OP row, Party 1 (Grantor) is always Kano State Government,
                // and Party 2 (Grantee) is the allottee — taken from file_name, not party_2_name.
                // party_1_name and party_2_name from the form are used only for the Transfer of Title sibling.
                $opGrantor = 'KANO STATE GOVERNMENT';
                $opGrantee = strtoupper(trim((string) $validated['file_name']));
                // party_1_name = previous owner (grantor on Transfer of Title)
                $party1Name = !empty($validated['party_1_name']) ? strtoupper(trim((string) $validated['party_1_name'])) : null;
                // party_2_name = new owner (grantee on Transfer of Title)
                $party2Name = !empty($validated['party_2_name'])
                    ? strtoupper(trim((string) $validated['party_2_name']))
                    : $opGrantee;

                $praUpdates = [];
                if ($wasSubmitted('land_use') && Schema::connection('sqlsrv')->hasColumn($praTable, 'land_use')) {
                    $praUpdates['land_use'] = !empty($validated['land_use']) ? strtoupper(trim((string) $validated['land_use'])) : null;
                }
                if ($wasSubmitted('purpose') && Schema::connection('sqlsrv')->hasColumn($praTable, 'purpose')) {
                    $praUpdates['purpose'] = $validated['purpose'] ?: null;
                }
                // Do NOT overwrite instrument_type on existing PRA rows — this form only
                // edits OP rows and must never change Transfer of Title rows to 'Occupancy Permit (OP)'.
                // Grantor/party_1 on OP rows is always Kano State Government.
                if ($isTransferOfTitle) {
                    // Transfer of Title row: use the form's party fields directly
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'Grantor')) {
                        $praUpdates['Grantor'] = $party1Name;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'Grantee')) {
                        $praUpdates['Grantee'] = $party2Name;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'party_1')) {
                        $praUpdates['party_1'] = $party1Name;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'party_2')) {
                        $praUpdates['party_2'] = $party2Name;
                    }
                } else {
                    // OP row: Grantor is always Kano State Government
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'Grantor')) {
                        $praUpdates['Grantor'] = $opGrantor;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'Grantee')) {
                        $praUpdates['Grantee'] = $opGrantee;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'party_1')) {
                        $praUpdates['party_1'] = $opGrantor;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'party_2')) {
                        $praUpdates['party_2'] = $opGrantee;
                    }
                }
                // These fields are read from pra with COALESCE priority in the index query,
                // so they must be updated here to take effect in the table display.
                if ($wasSubmitted('plot_number') && Schema::connection('sqlsrv')->hasColumn($praTable, 'plot_no')) {
                    $praUpdates['plot_no'] = ($validated['plot_number'] ?? null) ?: null;
                }
                if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn($praTable, 'tp_no')) {
                    $praUpdates['tp_no'] = ($validated['tp_number'] ?? null) ?: null;
                }
                if ($wasSubmitted('lga') && Schema::connection('sqlsrv')->hasColumn($praTable, 'lgsaOrCity')) {
                    $praUpdates['lgsaOrCity'] = $validated['lga'] ?: null;
                }
                if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn($praTable, 'location')) {
                    $praUpdates['location'] = $validated['location'] ?: null;
                }
                if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn($praTable, 'property_description')) {
                    $praUpdates['property_description'] = $validated['location'] ?: null;
                }
                // OP type and serial number — write for both row types
                if ($wasSubmitted('op_type') && !empty($validated['op_type']) && Schema::connection('sqlsrv')->hasColumn($praTable, 'op_type')) {
                    $praUpdates['op_type'] = $validated['op_type'];
                }
                if ($wasSubmitted('op_serial_number') && Schema::connection('sqlsrv')->hasColumn($praTable, 'op_serial_number')) {
                    $praUpdates['op_serial_number'] = $validated['op_serial_number'] ?: null;
                }
                // Registration particulars are Common fields and should be applied identically
                if ($wasSubmitted('serial_no')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'serialNo')) {
                        $praUpdates['serialNo'] = $validated['serial_no'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'serial_no')) {
                        $praUpdates['serial_no'] = $validated['serial_no'] ?: null;
                    }
                }
                if ($wasSubmitted('page_no')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'pageNo')) {
                        $praUpdates['pageNo'] = $validated['page_no'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'page_no')) {
                        $praUpdates['page_no'] = $validated['page_no'] ?: null;
                    }
                }
                if ($wasSubmitted('volume_no')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'volumeNo')) {
                        $praUpdates['volumeNo'] = $validated['volume_no'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'volume_no')) {
                        $praUpdates['volume_no'] = $validated['volume_no'] ?: null;
                    }
                }
                if ($wasSubmitted('registration_number')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'regNo')) {
                        $praUpdates['regNo'] = $validated['registration_number'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'registration_number')) {
                        $praUpdates['registration_number'] = $validated['registration_number'] ?: null;
                    }
                }
                // Persist registration date/time into PRA using schema-safe writes
                if ($wasSubmitted('reg_date')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'deeds_date')) {
                        $praUpdates['deeds_date'] = $validated['reg_date'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'reg_date')) {
                        $praUpdates['reg_date'] = $validated['reg_date'] ?: null;
                    }
                }
                if ($wasSubmitted('reg_time')) {
                    if (Schema::connection('sqlsrv')->hasColumn($praTable, 'deeds_time')) {
                        $praUpdates['deeds_time'] = $validated['reg_time'] ?: null;
                    } elseif (Schema::connection('sqlsrv')->hasColumn($praTable, 'reg_time')) {
                        $praUpdates['reg_time'] = $validated['reg_time'] ?: null;
                    }
                }
                if ($wasSubmitted('transaction_date') && Schema::connection('sqlsrv')->hasColumn($praTable, 'transaction_date')) {
                    $praUpdates['transaction_date'] = $validated['transaction_date'] ?: null;
                }
                if (Schema::connection('sqlsrv')->hasColumn($praTable, 'updated_at')) {
                    $praUpdates['updated_at'] = now();
                }
                // Always stamp system_source for Change of Name
                $praUpdates['system_source'] = 'OSSOPCHANGEOFNAME';

                if (!empty($praUpdates)) {
                    // If user selected a specific transaction card, target only that PRA row
                    if ($targetPraId) {
                        DB::connection('sqlsrv')
                            ->table($praTable)
                            ->where('id', $targetPraId)
                            ->update($praUpdates);
                    } else {
                        $updatedRows = DB::connection('sqlsrv')
                            ->table($praTable)
                            ->where('system_source', 'OSSOPCHANGEOFNAME')
                            ->where(function ($q) use ($praPropId, $base) {
                                $q->where('prop_id', $praPropId);

                                if (!empty($base->mlsfNo)) {
                                    $q->orWhere('mlsFNo', $base->mlsfNo)
                                        ->orWhere('fileno', $base->mlsfNo);
                                }
                            })
                            ->where(function ($q) {
                                $q->where('instrument_type', 'like', '%Occupancy Permit%')
                                    ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
                            })
                            ->where('instrument_type', 'not like', '%Transfer of Title%')
                            ->where('transaction_type', 'not like', '%Transfer of Title%')
                            ->update($praUpdates);

                        // Some historical Change-of-Name rows don't keep OP transaction labels.
                        // If strict OP matching updates nothing, retry — but still exclude Transfer of Title rows.
                        if ((int) $updatedRows === 0) {
                            DB::connection('sqlsrv')
                                ->table($praTable)
                                ->where('system_source', 'OSSOPCHANGEOFNAME')
                                ->where(function ($q) use ($praPropId, $base) {
                                    $q->where('prop_id', $praPropId);

                                    if (!empty($base->mlsfNo)) {
                                        $q->orWhere('mlsFNo', $base->mlsfNo)
                                            ->orWhere('fileno', $base->mlsfNo);
                                    }
                                })
                                ->where(function ($q) {
                                    $q->where('instrument_type', 'not like', '%Transfer of Title%')
                                        ->where('transaction_type', 'not like', '%Transfer of Title%');
                                })
                                ->update($praUpdates);
                        }
                    }
                }

                // Sync shared property + party fields to any Transfer of Title sibling row(s)
                // Only do this when saving the OP card — the ToT card has its own Save button.
                // Do not remap ToT party fields here; those must be updated only from the ToT form.
                if (($praPropId || !empty($base->mlsfNo)) && !$isTransferOfTitle) {
                    $siblingUpdates = [];
                    // Sync all shared property details and ALL Registration Particulars to sibling ToT rows
                    $syncCols = [
                        'plot_no',
                        'tp_no',
                        'lgsaOrCity',
                        'location',
                        'property_description',
                        'land_use',
                        'purpose',
                        'op_type',
                        'op_serial_number',
                        'serialNo',
                        'serial_no',
                        'pageNo',
                        'page_no',
                        'volumeNo',
                        'volume_no',
                        'regNo',
                        'registration_number',
                        'deeds_date',
                        'reg_date',
                        'deeds_time',
                        'reg_time'
                    ];
                    foreach ($syncCols as $col) {
                        // Use array_key_exists so null values are cleared correctly
                        if (array_key_exists($col, $praUpdates) && Schema::connection('sqlsrv')->hasColumn($praTable, $col)) {
                            $siblingUpdates[$col] = $praUpdates[$col];
                        }
                    }
                    if (!empty($siblingUpdates)) {
                        if (Schema::connection('sqlsrv')->hasColumn($praTable, 'updated_at')) {
                            $siblingUpdates['updated_at'] = now();
                        }
                        DB::connection('sqlsrv')
                            ->table($praTable)
                            ->where('system_source', 'OSSOPCHANGEOFNAME')
                            ->where(function ($q) use ($praPropId, $base) {
                                if ($praPropId) {
                                    $q->where('prop_id', $praPropId);
                                }
                                if (!empty($base->mlsfNo)) {
                                    if ($praPropId) {
                                        $q->orWhere('mlsFNo', $base->mlsfNo)
                                            ->orWhere('fileno', $base->mlsfNo);
                                    } else {
                                        $q->where('mlsFNo', $base->mlsfNo)
                                            ->orWhere('fileno', $base->mlsfNo);
                                    }
                                }
                            })
                            ->where(function ($q) {
                                $q->where('instrument_type', 'like', '%Transfer of Title%')
                                    ->orWhere('transaction_type', 'like', '%Transfer of Title%');
                            })
                            ->update($siblingUpdates);
                    }
                }
            }

            // 5) Synchronize other related stores used across modules.
            $fileNo = !empty($base->mlsfNo) ? strtoupper(trim((string) $base->mlsfNo)) : null;
            $fileNameUpper = strtoupper(trim((string) $validated['file_name']));

            if (!empty($fileNo)) {
                // customers_staging
                if (Schema::connection('sqlsrv')->hasTable('customers_staging')) {
                    $customersUpdates = [];
                    if (!$isTransferOfTitle && Schema::connection('sqlsrv')->hasColumn('customers_staging', 'customer_type')) {
                        $customersUpdates['customer_type'] = $validated['customer_type'];
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('customers_staging', 'customer_name')) {
                        $customersUpdates['customer_name'] = $fileNameUpper;
                    }
                    if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn('customers_staging', 'property_address')) {
                        $customersUpdates['property_address'] = $validated['location'] ?: null;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('customers_staging', 'updated_at')) {
                        $customersUpdates['updated_at'] = now();
                    }

                    if (!empty($customersUpdates)) {
                        DB::connection('sqlsrv')
                            ->table('customers_staging')
                            ->where('file_number', $fileNo)
                            ->update($customersUpdates);
                    }
                }

                // entities_staging
                if (Schema::connection('sqlsrv')->hasTable('entities_staging')) {
                    $entitiesUpdates = [];
                    if (Schema::connection('sqlsrv')->hasColumn('entities_staging', 'entity_name')) {
                        $entitiesUpdates['entity_name'] = $fileNameUpper;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('entities_staging', 'updated_at')) {
                        $entitiesUpdates['updated_at'] = now();
                    }

                    if (!empty($entitiesUpdates)) {
                        DB::connection('sqlsrv')
                            ->table('entities_staging')
                            ->where('file_number', $fileNo)
                            ->update($entitiesUpdates);
                    }
                }

                // file_indexings
                if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
                    $indexingUpdates = [];
                    if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'current_holder')) {
                        $indexingUpdates['current_holder'] = $fileNameUpper;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'customer_name')) {
                        $indexingUpdates['customer_name'] = $fileNameUpper;
                    }
                    if (!$isTransferOfTitle && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'file_type')) {
                        $indexingUpdates['file_type'] = $validated['customer_type'];
                    }
                    if (!$isTransferOfTitle && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'customer_type')) {
                        $indexingUpdates['customer_type'] = $validated['customer_type'];
                    }
                    if ($wasSubmitted('plot_number') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'plot_no')) {
                        $indexingUpdates['plot_no'] = ($validated['plot_number'] ?? null) ?: null;
                    }
                    if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'tp_no')) {
                        $indexingUpdates['tp_no'] = ($validated['tp_number'] ?? null) ?: null;
                    }
                    if ($wasSubmitted('lga') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'lga')) {
                        $indexingUpdates['lga'] = $validated['lga'] ?: null;
                    }
                    if ($wasSubmitted('district') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'district')) {
                        $indexingUpdates['district'] = $validated['district'] ?: null;
                    }
                    if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'location')) {
                        $indexingUpdates['location'] = $validated['location'] ?: null;
                    }
                    if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn('file_indexings', 'property_description')) {
                        $indexingUpdates['property_description'] = $validated['location'] ?: null;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'updated_by')) {
                        $indexingUpdates['updated_by'] = auth()->id();
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'updated_at')) {
                        $indexingUpdates['updated_at'] = now();
                    }

                    if (!empty($indexingUpdates)) {
                        $indexingQuery = DB::connection('sqlsrv')->table('file_indexings');
                        $indexingQuery->where(function ($q) use ($fileNo, $base) {
                            $q->where('file_number', $fileNo);
                            if (!empty($base->tracking_id)) {
                                $q->orWhere('tracking_id', $base->tracking_id);
                            }
                            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'full_file_number')) {
                                $q->orWhere('full_file_number', $fileNo);
                            }
                        })->update($indexingUpdates);
                    }
                }

                // oss_applications (if active in this environment)
                if (Schema::connection('sqlsrv')->hasTable('oss_applications')) {
                    $ossUpdates = [];
                    if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'applicant_name')) {
                        $ossUpdates['applicant_name'] = $fileNameUpper;
                    }
                    if ($wasSubmitted('plot_number') && Schema::connection('sqlsrv')->hasColumn('oss_applications', 'plot_no')) {
                        $ossUpdates['plot_no'] = ($validated['plot_number'] ?? null) ?: null;
                    }
                    if ($wasSubmitted('tp_number') && Schema::connection('sqlsrv')->hasColumn('oss_applications', 'plan_no')) {
                        $ossUpdates['plan_no'] = ($validated['tp_number'] ?? null) ?: null;
                    }
                    if ($wasSubmitted('location') && Schema::connection('sqlsrv')->hasColumn('oss_applications', 'location')) {
                        $ossUpdates['location'] = $validated['location'] ?: null;
                    }
                    if ($wasSubmitted('applicant_phone') && Schema::connection('sqlsrv')->hasColumn('oss_applications', 'phone')) {
                        $ossUpdates['phone'] = $validated['applicant_phone'] ?: null;
                    }
                    if ($wasSubmitted('applicant_address') && Schema::connection('sqlsrv')->hasColumn('oss_applications', 'residential_address')) {
                        $ossUpdates['residential_address'] = $validated['applicant_address'] ?: null;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'updated_at')) {
                        $ossUpdates['updated_at'] = now();
                    }
                    // Always stamp system_source for Change of Name records
                    if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'system_source')) {
                        $ossUpdates['system_source'] = 'OSSOPCHANGEOFNAME';
                    }

                    if (!empty($ossUpdates)) {
                        DB::connection('sqlsrv')
                            ->table('oss_applications')
                            ->where('file_no', $fileNo)
                            ->update($ossUpdates);
                    }
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the dedicated Capture OP edit page for a Change of Name record.
     *
     * Loads the standalone Update OP form page with pre-populated data
     * from fileNumber, mls_file_no, instrument_capture & PRA tables.
     */
    public function captureEdit(string $id): View
    {
        // Support fileNumber.id (numeric), pra-{praId}, and ic-{icId} formats
        $praOnly = false;
        $praId = null;
        $icOnly = false;
        $icDirectId = null;
        if (str_starts_with($id, 'pra-')) {
            $praOnly = true;
            $praId = (int) substr($id, 4);
        } elseif (str_starts_with($id, 'ic-')) {
            $icOnly = true;
            $icDirectId = (int) substr($id, 3);
        } else {
            $id = (int) $id;
        }

        // Land-use fallback from file-number prefix when source fields are empty.
        $deriveLandUseFromPrefix = static function (?string $fileNo): ?string {
            $fileNo = strtoupper(trim((string) $fileNo));
            if ($fileNo === '') {
                return null;
            }

            $prefix = strtok($fileNo, '-');
            if ($prefix === false || $prefix === '') {
                return null;
            }

            return match ($prefix) {
                'RES', 'R3S' => 'RES',
                'COM', 'C0M' => 'COM',
                'IND' => 'IND',
                'AGR' => 'AGR',
                'MIX' => 'MIX',
                'INS', 'INST' => 'INS',
                default => null,
            };
        };

        // Normalize any land-use text/code to the short code used by the dropdown.
        $normalizeLandUseCode = static function (?string $rawLandUse, ?string $fileNo = null) use ($deriveLandUseFromPrefix): ?string {
            $raw = strtoupper(trim((string) $rawLandUse));
            if ($raw === '') {
                return $deriveLandUseFromPrefix($fileNo);
            }

            if (in_array($raw, ['RES', 'COM', 'IND', 'AGR', 'MIX', 'INS'], true)) {
                return $raw;
            }

            if ($raw === 'R3S' || str_contains($raw, 'RESIDENTIAL'))
                return 'RES';
            if ($raw === 'C0M' || str_contains($raw, 'COMMERCIAL'))
                return 'COM';
            if (str_contains($raw, 'INDUSTRIAL'))
                return 'IND';
            if (str_contains($raw, 'AGRICULT'))
                return 'AGR';
            if (str_contains($raw, 'MIXED'))
                return 'MIX';
            if (str_contains($raw, 'INSTITUTION'))
                return 'INS';

            return $deriveLandUseFromPrefix($fileNo) ?: null;
        };

        $captureTable = 'instrument_capture';
        $hasDeedsDate = Schema::connection('sqlsrv')->hasColumn($captureTable, 'deeds_date');
        $hasDeedsTime = Schema::connection('sqlsrv')->hasColumn($captureTable, 'deeds_time');
        $hasRegDate = Schema::connection('sqlsrv')->hasColumn($captureTable, 'reg_date');
        $hasRegTime = Schema::connection('sqlsrv')->hasColumn($captureTable, 'reg_time');
        $hasTransactionDate = Schema::connection('sqlsrv')->hasColumn($captureTable, 'transaction_date');

        // Prefer deeds_* columns on production; fall back to legacy reg_* only when needed.
        $icRegDateColumn = $hasDeedsDate ? 'deeds_date' : ($hasRegDate ? 'reg_date' : null);
        $icRegTimeColumn = $hasDeedsTime ? 'deeds_time' : ($hasRegTime ? 'reg_time' : null);

        $icFallbackCols = [
            'serial_no',
            'page_no',
            'volume_no',
            'registration_number',
            'instrument_date',
            'op_type',
            'op_serial_number',
            'land_use',
            'purpose',
            'party_1_name',
            'party_2_name',
            'plot_number',
            'tp_no',
            'property_description',
            'lga',
            'district',
        ];
        if ($hasTransactionDate) {
            $icFallbackCols[] = 'transaction_date';
        }
        if ($icRegDateColumn) {
            $icFallbackCols[] = $icRegDateColumn;
        }
        if ($icRegTimeColumn) {
            $icFallbackCols[] = $icRegTimeColumn;
        }

        // Helper: extract district and LGA from a location/property_description string
        // by matching known names from the dropdowns against comma-separated segments.
        $extractFromLocation = function (string $location, $lgasList, $districtsList): array {
            $result = ['lga' => null, 'district' => null, 'district_is_custom' => false];
            if (!$location)
                return $result;

            $locationUpper = strtoupper($location);

            // Try matching each LGA name in the location string
            foreach ($lgasList as $lgaItem) {
                $name = strtoupper(trim($lgaItem->name));
                if ($name && str_contains($locationUpper, $name)) {
                    $result['lga'] = $lgaItem->name;
                    break;
                }
            }

            // Parse comma-separated location and prefer the middle segment as district.
            // Example: "PLOT 3007, BEHIND AIRPORT, UNGOGO, KANO" => district "BEHIND AIRPORT".
            $segments = array_map('trim', explode(',', $location));
            $lgaUpper = $result['lga'] ? strtoupper($result['lga']) : null;
            $candidates = [];
            foreach ($segments as $seg) {
                $segUpper = strtoupper($seg);
                if (!$seg)
                    continue;
                if (preg_match('/^\s*PLOT\s/i', $seg))
                    continue;
                if ($segUpper === 'KANO' || $segUpper === 'KANO STATE')
                    continue;
                if ($lgaUpper && $segUpper === $lgaUpper)
                    continue;
                $candidates[] = trim($seg);
            }

            if (!empty($candidates)) {
                $candidateName = $candidates[0];
                $candidateUpper = strtoupper($candidateName);
                $foundInList = false;
                foreach ($districtsList as $distItem) {
                    if (strtoupper(trim($distItem->name)) === $candidateUpper) {
                        $result['district'] = $distItem->name;
                        $foundInList = true;
                        break;
                    }
                }
                if (!$foundInList) {
                    $result['district'] = $candidateName;
                    $result['district_is_custom'] = true;
                }
            }

            // Fallback: if parsing could not determine district, try name matching in full location.
            if (!$result['district']) {
                foreach ($districtsList as $distItem) {
                    $name = strtoupper(trim($distItem->name));
                    if ($name && str_contains($locationUpper, $name)) {
                        $result['district'] = $distItem->name;
                        break;
                    }
                }
            }

            // Final fallback: if still no district, use LGA name as district
            if (!$result['district'] && $result['lga']) {
                $result['district'] = $result['lga'];
            }

            return $result;
        };

        $base = null;

        if (!$praOnly && !$icOnly && $id > 0) {
            // Standard lookup by fileNumber.id
            $base = DB::connection('sqlsrv')
                ->table('fileNumber as fn')
                ->leftJoin('mls_file_no as mfn', 'fn.tracking_id', '=', 'mfn.tracking_id')
                ->leftJoin('purposes as p', 'mfn.purpose_id', '=', 'p.id')
                ->leftJoin('instrument_capture as ic', function ($join) {
                    $join->on('mfn.source_instrument_capture_id', '=', 'ic.id')
                        ->where('ic.instrument_type', 'Occupancy Permit (OP)');
                })
                ->select([
                    'fn.id',
                    'fn.mlsfNo',
                    'fn.tracking_id',
                    'fn.FileName',
                    'fn.plot_no',
                    'fn.tp_no',
                    'fn.lga',
                    'fn.location',
                    'mfn.id as mls_file_no_id',
                    'mfn.customer_type as mfn_customer_type',
                    'mfn.land_use as mfn_land_use',
                    'mfn.purpose_id as mfn_purpose_id',
                    'p.name as mfn_purpose_name',
                    'mfn.source_instrument_capture_id',
                    'ic.id as ic_id',
                    'ic.op_type',
                    'ic.op_serial_number',
                    'ic.land_use as ic_land_use',
                    'ic.purpose as ic_purpose',
                    'ic.party_1_name',
                    'ic.party_2_name',
                    'ic.plot_number',
                    'ic.tp_no as ic_tp_no',
                    'ic.property_description',
                    'ic.lga as ic_lga',
                    'ic.district as ic_district',
                    'ic.serial_no as ic_serial_no',
                    'ic.page_no as ic_page_no',
                    'ic.volume_no as ic_volume_no',
                    'ic.registration_number as ic_registration_number',
                    DB::raw(($icRegDateColumn ? "ic.{$icRegDateColumn}" : 'NULL') . ' as ic_reg_date'),
                    DB::raw(($icRegTimeColumn ? "ic.{$icRegTimeColumn}" : 'NULL') . ' as ic_reg_time'),
                    DB::raw(($hasTransactionDate ? 'ic.transaction_date' : 'NULL') . ' as ic_transaction_date'),
                    'ic.instrument_date as ic_instrument_date',
                ])
                ->where('fn.id', $id)
                ->first();
        }

        // IC-only fallback: no fileNumber record, resolve from instrument_capture.id directly
        if ($icOnly) {
            $icRow = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $icDirectId)->first();
            if (!$icRow) {
                abort(404, 'Record not found.');
            }
            $mlsfNo = $icRow->mlsFNo ?: ($icRow->temp_fileno ?? null);

            $icRegDate = $icRegDateColumn ? ($icRow->{$icRegDateColumn} ?? null) : null;
            $icRegTime = $icRegTimeColumn ? ($icRow->{$icRegTimeColumn} ?? null) : null;

            // Check for phone/address columns
            $hasP1Phone = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_phone');
            $hasP1Address = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_address');
            $hasP2Phone = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_phone');
            $hasP2Address = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_address');

            $record = [
                'mlsfNo' => $mlsfNo,
                'op_type' => $icRow->op_type ?? null,
                'op_serial_number' => $icRow->op_serial_number ?? null,
                'customer_type' => $icRow->customer_type ?? 'Individual',
                'land_use' => $normalizeLandUseCode($icRow->land_use ?? null, $mlsfNo),
                'purpose' => $icRow->purpose ?? null,
                'file_name' => $icRow->party_2_name ?? $icRow->party_1_name ?? '',
                'plot_number' => $icRow->plot_number ?? null,
                'tp_number' => $icRow->tp_no ?? null,
                'location' => $icRow->property_description ?? ($icRow->property_location ?? null),
                'lga' => $icRow->lga ?? null,
                'district' => $icRow->district ?? null,
                'party_1_name' => $icRow->party_1_name ?? null,
                'party_2_name' => $icRow->party_2_name ?? null,
                'party_1_phone' => $hasP1Phone ? ($icRow->party_1_phone ?? null) : null,
                'party_2_phone' => $hasP2Phone ? ($icRow->party_2_phone ?? null) : null,
                'party_1_address' => $hasP1Address ? ($icRow->party_1_address ?? null) : null,
                'party_2_address' => $hasP2Address ? ($icRow->party_2_address ?? null) : null,
                'applicant_phone' => $hasP2Phone ? ($icRow->party_2_phone ?? null) : null,
                'applicant_address' => $hasP2Address ? ($icRow->party_2_address ?? null) : null,
                'serial_no' => $icRow->serial_no ?? null,
                'page_no' => $icRow->page_no ?? null,
                'volume_no' => $icRow->volume_no ?? null,
                'registration_number' => $icRow->registration_number ?? null,
                'reg_date' => $icRegDate,
                'reg_time' => $icRegTime,
                'transaction_date' => $hasTransactionDate ? ($icRow->transaction_date ?? null) : null,
                'instrument_date' => $icRow->instrument_date ?? null,
            ];

            $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
            $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
            $landUses = \App\Models\LandUse::orderBy('landuse')->get();

            $districtIsCustom = false;
            if (empty($record['lga']) || empty($record['district'])) {
                $extracted = $extractFromLocation($record['location'] ?? '', $lgas, $districts);
                if (empty($record['lga']))
                    $record['lga'] = $extracted['lga'];
                if (empty($record['district'])) {
                    $record['district'] = $extracted['district'];
                    $districtIsCustom = $extracted['district_is_custom'] ?? false;
                }
            }

            $PageTitle = 'Update OP';
            $PageDescription = 'Update Occupancy Permit for ' . ($mlsfNo ?: 'record');
            $returnUrl = route('lands-one-stop-shop.applications.index') . '?source=lands-one-stop-shop&type=change-of-name';
            $fileNumberId = 'ic-' . $icDirectId;

            // Load all PRA rows for this property so each card can be shown
            $allPraRows = collect();
            $icPropId = $icRow->prop_id ?? null;
            if ($icPropId) {
                $allPraRows = DB::connection('sqlsrv')->table('pra')
                    ->where('prop_id', $icPropId)
                    ->orderBy('id')
                    ->get();
            } elseif (!empty($mlsfNo)) {
                $allPraRows = DB::connection('sqlsrv')->table('pra')
                    ->where(function ($q) use ($mlsfNo) {
                        $q->where('mlsFNo', $mlsfNo)->orWhere('fileno', $mlsfNo);
                    })
                    ->orderBy('id')
                    ->get();
            }

            return view('lands_one_stop_shop.op_capture_edit', compact(
                'PageTitle',
                'PageDescription',
                'record',
                'lgas',
                'districts',
                'landUses',
                'returnUrl',
                'fileNumberId',
                'districtIsCustom',
                'allPraRows',
            ));
        }

        // PRA-only fallback: no fileNumber record, resolve from pra.id directly
        if ($praOnly) {
            $pra = DB::connection('sqlsrv')->table('pra')->where('id', $praId)->first();
            if (!$pra) {
                abort(404, 'Record not found.');
            }
            $mlsfNo = $pra->mlsFNo ?: ($pra->fileno ?? null);

            // Try to find an instrument_capture record linked by prop_id or mlsFNo
            $praIcFallback = null;
            if (!empty($pra->prop_id)) {
                $praIcFallback = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('prop_id', $pra->prop_id)
                    ->orderByDesc('id')
                    ->first($icFallbackCols);
            }
            if (!$praIcFallback && !empty($mlsfNo)) {
                $praIcFallback = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('mlsFNo', $mlsfNo)
                    ->orderByDesc('id')
                    ->first($icFallbackCols);
            }

            $praIcRegDate = ($praIcFallback && $icRegDateColumn) ? ($praIcFallback->{$icRegDateColumn} ?? null) : null;
            $praIcRegTime = ($praIcFallback && $icRegTimeColumn) ? ($praIcFallback->{$icRegTimeColumn} ?? null) : null;

            $record = [
                'mlsfNo' => $mlsfNo,
                'op_type' => $pra->op_type ?? ($praIcFallback->op_type ?? null),
                'op_serial_number' => $pra->op_serial_number ?? ($praIcFallback->op_serial_number ?? null),
                'customer_type' => 'Individual',
                'land_use' => $normalizeLandUseCode($pra->land_use ?? ($praIcFallback->land_use ?? null), $mlsfNo),
                'purpose' => $praIcFallback->purpose ?? null,
                'file_name' => $pra->Grantee ?? $pra->party_2 ?? '',
                'plot_number' => $pra->plot_no ?? ($praIcFallback->plot_number ?? null),
                'tp_number' => $pra->tp_no ?? ($praIcFallback->tp_no ?? null),
                'location' => $pra->location ?? $pra->property_description ?? ($praIcFallback->property_description ?? null),
                'lga' => $pra->lgsaOrCity ?? ($praIcFallback->lga ?? null),
                'district' => $praIcFallback->district ?? null,
                'party_1_name' => $pra->Grantor ?? $pra->party_1 ?? ($praIcFallback->party_1_name ?? null),
                'party_2_name' => $pra->Grantee ?? $pra->party_2 ?? ($praIcFallback->party_2_name ?? null),
                'party_1_phone' => null,
                'party_2_phone' => null,
                'party_1_address' => null,
                'party_2_address' => null,
                'applicant_phone' => null,
                'applicant_address' => null,
                'serial_no' => $praIcFallback->serial_no ?? $pra->serialNo ?? $pra->serial_no ?? null,
                'page_no' => $praIcFallback->page_no ?? $pra->pageNo ?? $pra->page_no ?? null,
                'volume_no' => $praIcFallback->volume_no ?? $pra->volumeNo ?? $pra->volume_no ?? null,
                'registration_number' => $praIcFallback->registration_number ?? $pra->regNo ?? $pra->registration_number ?? null,
                'reg_date' => $praIcRegDate ?: ($pra->deeds_date ?? ($pra->reg_date ?? null)),
                'reg_time' => $praIcRegTime ?: ($pra->deeds_time ?? ($pra->reg_time ?? null)),
                'transaction_date' => $praIcFallback->transaction_date ?? ($pra->transaction_date ?? null),
                'instrument_date' => $pra->created_at ?? ($praIcFallback->instrument_date ?? null),
            ];

            $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
            $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
            $landUses = \App\Models\LandUse::orderBy('landuse')->get();

            // Backfill LGA/District from location string when not explicitly set
            $districtIsCustom = false;
            if (empty($record['lga']) || empty($record['district'])) {
                $extracted = $extractFromLocation($record['location'] ?? '', $lgas, $districts);
                if (empty($record['lga']))
                    $record['lga'] = $extracted['lga'];
                if (empty($record['district'])) {
                    $record['district'] = $extracted['district'];
                    $districtIsCustom = $extracted['district_is_custom'] ?? false;
                }
            }

            $PageTitle = 'Update OP';
            $PageDescription = 'Update Occupancy Permit for ' . ($mlsfNo ?: 'record');
            $returnUrl = route('lands-one-stop-shop.applications.index') . '?source=lands-one-stop-shop&type=change-of-name';
            $fileNumberId = 'pra-' . $praId;

            // Load all PRA rows for this property so each card can be shown
            $allPraRows = collect();
            if (!empty($pra->prop_id)) {
                $allPraRows = DB::connection('sqlsrv')->table('pra')
                    ->where('prop_id', $pra->prop_id)
                    ->orderBy('id')
                    ->get();
            } elseif (!empty($mlsfNo)) {
                $allPraRows = DB::connection('sqlsrv')->table('pra')
                    ->where(function ($q) use ($mlsfNo) {
                        $q->where('mlsFNo', $mlsfNo)->orWhere('fileno', $mlsfNo);
                    })
                    ->orderBy('id')
                    ->get();
            }

            return view('lands_one_stop_shop.op_capture_edit', compact(
                'PageTitle',
                'PageDescription',
                'record',
                'lgas',
                'districts',
                'landUses',
                'returnUrl',
                'fileNumberId',
                'districtIsCustom',
                'allPraRows',
            ));
        }

        if (!$base) {
            abort(404, 'Record not found.');
        }

        // Check for party phone/address columns safely
        $hasP1Phone = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_phone');
        $hasP1Address = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_1_address');
        $hasP2Phone = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_phone');
        $hasP2Address = Schema::connection('sqlsrv')->hasColumn($captureTable, 'party_2_address');

        $icExtra = null;
        if ($base->ic_id && ($hasP1Phone || $hasP1Address || $hasP2Phone || $hasP2Address)) {
            $extraCols = [];
            if ($hasP1Phone)
                $extraCols[] = 'party_1_phone';
            if ($hasP1Address)
                $extraCols[] = 'party_1_address';
            if ($hasP2Phone)
                $extraCols[] = 'party_2_phone';
            if ($hasP2Address)
                $extraCols[] = 'party_2_address';
            $icExtra = DB::connection('sqlsrv')->table($captureTable)
                ->where('id', $base->ic_id)
                ->first($extraCols);
        }

        // Resolve PRA data for fallback.
        // Keep both latest row (any type) and OP-specific row (excluding Transfer of Title).
        $latestPra = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($base) {
                $q->where('mlsFNo', $base->mlsfNo)
                    ->orWhere('fileno', $base->mlsfNo);
            })
            ->orderByDesc('id')
            ->first();

        $opPra = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($base, $latestPra) {
                $propId = $latestPra->prop_id ?? null;
                if ($propId) {
                    $q->where('prop_id', $propId);
                } else {
                    $q->where('mlsFNo', $base->mlsfNo)
                        ->orWhere('fileno', $base->mlsfNo);
                }
            })
            ->where(function ($q) {
                $q->where('instrument_type', 'like', '%Occupancy Permit%')
                    ->orWhere('transaction_type', 'like', '%Occupancy Permit%')
                    ->orWhereNotNull('op_serial_number');
            })
            ->orderByRaw("CASE 
                WHEN (instrument_type NOT LIKE '%Transfer of Title%' OR instrument_type IS NULL) 
                     AND (regNo IS NOT NULL AND regNo != '0/0/0') THEN 0 
                WHEN (instrument_type NOT LIKE '%Transfer of Title%' OR instrument_type IS NULL) THEN 1
                ELSE 2 END")
            ->orderByDesc('id')
            ->first();

        $hasIc = !empty($base->ic_id);

        // Fallback: if the JOIN didn't find an ic record, look up by mlsFNo or prop_id
        $icFallback = null;
        if (!$hasIc) {
            // Try by mlsFNo first
            if (!empty($base->mlsfNo)) {
                $icFallback = DB::connection('sqlsrv')
                    ->table($captureTable)
                    ->where('instrument_type', 'Occupancy Permit (OP)')
                    ->where('mlsFNo', $base->mlsfNo)
                    ->orderByDesc('id')
                    ->first($icFallbackCols);
            }
            // Fallback by prop_id via PRA linkage
            $praForIcLookup = $opPra ?: $latestPra;
            if (!$icFallback && $praForIcLookup && !empty($praForIcLookup->prop_id)) {
                $icFallback = DB::connection('sqlsrv')
                    ->table($captureTable)
                    ->where('instrument_type', 'Occupancy Permit (OP)')
                    ->where('prop_id', $praForIcLookup->prop_id)
                    ->orderByDesc('id')
                    ->first($icFallbackCols);
            }
        }

        $icFallbackRegDate = ($icFallback && $icRegDateColumn) ? ($icFallback->{$icRegDateColumn} ?? null) : null;
        $icFallbackRegTime = ($icFallback && $icRegTimeColumn) ? ($icFallback->{$icRegTimeColumn} ?? null) : null;

        $resolvedOpParty1 = $hasIc
            ? ($opPra->Grantor ?? $opPra->party_1 ?? $base->party_1_name ?? null)
            : ($icFallback->party_1_name ?? $opPra->Grantor ?? $opPra->party_1 ?? null);

        $resolvedOpParty2 = $hasIc
            ? ($opPra->Grantee ?? $opPra->party_2 ?? $base->party_2_name ?? $base->party_1_name ?? null)
            : ($icFallback->party_2_name ?? $icFallback->party_1_name ?? $opPra->Grantee ?? $opPra->party_2 ?? null);

        $resolvedOpFileName = $resolvedOpParty2 ?? ($base->FileName ?? '');

        // Build a flat array with all data the standalone form needs
        $record = [
            'mlsfNo' => $base->mlsfNo,
            'op_type' => $hasIc ? $base->op_type : ($icFallback->op_type ?? $opPra->op_type ?? null),
            'op_serial_number' => $hasIc ? $base->op_serial_number : ($icFallback->op_serial_number ?? $opPra->op_serial_number ?? null),
            'customer_type' => $base->mfn_customer_type ?? 'Individual',
            'land_use' => $normalizeLandUseCode(
                $hasIc ? ($base->ic_land_use ?? null) : ($icFallback->land_use ?? $opPra->land_use ?? $base->mfn_land_use ?? null),
                $base->mlsfNo
            ),
            'purpose' => $hasIc ? $base->ic_purpose : ($icFallback->purpose ?? $opPra->purpose ?? $base->mfn_purpose_name ?? null),
            'file_name' => $resolvedOpFileName,
            'plot_number' => $hasIc ? $base->plot_number : ($icFallback->plot_number ?? $opPra->plot_no ?? $base->plot_no ?? null),
            'tp_number' => $hasIc ? $base->ic_tp_no : ($icFallback->tp_no ?? $opPra->tp_no ?? $base->tp_no ?? null),
            'location' => $hasIc ? $base->property_description : ($icFallback->property_description ?? $opPra->location ?? $opPra->property_description ?? $base->location ?? null),
            'lga' => $hasIc ? $base->ic_lga : ($icFallback->lga ?? $opPra->lgsaOrCity ?? $base->lga ?? null),
            'district' => $hasIc ? $base->ic_district : ($icFallback->district ?? null),
            'party_1_name' => $resolvedOpParty1,
            'party_2_name' => $resolvedOpParty2,
            'party_1_phone' => ($icExtra->party_1_phone ?? null),
            'party_2_phone' => ($icExtra->party_2_phone ?? null),
            'party_1_address' => ($icExtra->party_1_address ?? null),
            'party_2_address' => ($icExtra->party_2_address ?? null),
            'applicant_phone' => ($icExtra->party_2_phone ?? null),
            'applicant_address' => ($icExtra->party_2_address ?? null),
            'serial_no' => $hasIc ? ($base->ic_serial_no ?? null) : ($icFallback->serial_no ?? $opPra->serialNo ?? $opPra->serial_no ?? null),
            'page_no' => $hasIc ? ($base->ic_page_no ?? null) : ($icFallback->page_no ?? $opPra->pageNo ?? $opPra->page_no ?? null),
            'volume_no' => $hasIc ? ($base->ic_volume_no ?? null) : ($icFallback->volume_no ?? $opPra->volumeNo ?? $opPra->volume_no ?? null),
            'registration_number' => $hasIc ? ($base->ic_registration_number ?? null) : ($icFallback->registration_number ?? $opPra->regNo ?? $opPra->registration_number ?? null),
            'reg_date' => ($hasIc ? ($base->ic_reg_date ?? null) : $icFallbackRegDate) ?: ($opPra->deeds_date ?? ($opPra->reg_date ?? null)),
            'reg_time' => ($hasIc ? ($base->ic_reg_time ?? null) : $icFallbackRegTime) ?: ($opPra->deeds_time ?? ($opPra->reg_time ?? null)),
            'transaction_date' => $hasIc ? ($base->ic_transaction_date ?? null) : ($icFallback->transaction_date ?? ($opPra->transaction_date ?? null)),
            'instrument_date' => $opPra->created_at ?? ($hasIc ? ($base->ic_instrument_date ?? null) : ($icFallback->instrument_date ?? null)),
        ];

        // Dropdown data
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $landUses = \App\Models\LandUse::orderBy('landuse')->get();

        // Backfill LGA/District from location string when not explicitly set
        $districtIsCustom = false;
        if (empty($record['lga']) || empty($record['district'])) {
            $extracted = $extractFromLocation($record['location'] ?? '', $lgas, $districts);
            if (empty($record['lga']))
                $record['lga'] = $extracted['lga'];
            if (empty($record['district'])) {
                $record['district'] = $extracted['district'];
                $districtIsCustom = $extracted['district_is_custom'] ?? false;
            }
        }

        $PageTitle = 'Update OP';
        $PageDescription = 'Update Occupancy Permit for ' . ($base->mlsfNo ?: 'record');
        $returnUrl = route('lands-one-stop-shop.applications.index') . '?source=lands-one-stop-shop&type=change-of-name';
        $fileNumberId = $id;

        // Load all PRA rows for this property so each card can be shown
        $allPraRows = collect();
        $resolvedPropId = $opPra->prop_id ?? ($latestPra->prop_id ?? null);
        if (!$resolvedPropId && !empty($base->ic_id)) {
            $resolvedPropId = DB::connection('sqlsrv')->table($captureTable)->where('id', $base->ic_id)->value('prop_id');
        }
        if ($resolvedPropId) {
            $allPraRows = DB::connection('sqlsrv')->table('pra')
                ->where('prop_id', $resolvedPropId)
                ->orderBy('id')
                ->get();
        } elseif (!empty($base->mlsfNo)) {
            $allPraRows = DB::connection('sqlsrv')->table('pra')
                ->where(function ($q) use ($base) {
                    $q->where('mlsFNo', $base->mlsfNo)->orWhere('fileno', $base->mlsfNo);
                })
                ->orderBy('id')
                ->get();
        }

        return view('lands_one_stop_shop.op_capture_edit', compact(
            'PageTitle',
            'PageDescription',
            'record',
            'lgas',
            'districts',
            'landUses',
            'returnUrl',
            'fileNumberId',
            'districtIsCustom',
            'allPraRows',
        ));
    }

    /**
     * Preview the OP record that will be matched, and the resolved current holder.
     * GET /lands-one-stop-shop/applications/match-op/preview?file_no=RES-2026-7
     */
    public function matchOpPreview(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->query('file_no', ''));
        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'file_no is required.'], 422);
        }

        $db = DB::connection('sqlsrv');

        // Find un-matched OP rows for this file number (any system_source except OSSOPCHANGEOFNAME)
        $opRows = $db->table('pra')
            ->where(function ($q) use ($fileNo) {
                $q->where('mlsFNo', $fileNo)->orWhere('fileno', $fileNo);
            })
            ->where(function ($q) {
                $q->where('instrument_type', 'like', '%Occupancy Permit%')
                    ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
            })
            ->where(function ($q) {
                $q->whereNull('system_source')
                    ->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
            })
            ->select([
                'id',
                'prop_id',
                'mlsFNo',
                'fileno',
                'temp_fileno',
                'instrument_type',
                'transaction_type',
                'op_type',
                'op_serial_number',
                'system_source',
                'Grantor',
                'Grantee',
                'party_1',
                'party_2',
                'land_use',
                'location',
                'plot_no',
                'tp_no',
                'lgsaOrCity'
            ])
            ->orderBy('id')
            ->get();

        if ($opRows->isEmpty()) {
            // Check if it's already matched
            $alreadyMatched = $db->table('pra')
                ->where(function ($q) use ($fileNo) {
                    $q->where('mlsFNo', $fileNo)->orWhere('fileno', $fileNo);
                })
                ->where('system_source', 'OSSOPCHANGEOFNAME')
                ->where(function ($q) {
                    $q->where('instrument_type', 'like', '%Occupancy Permit%')
                        ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
                })
                ->exists();

            if ($alreadyMatched) {
                return response()->json(['success' => false, 'message' => 'This file already has a matched OP (system_source = OSSOPCHANGEOFNAME).']);
            }

            return response()->json(['success' => false, 'message' => 'No unmatched Occupancy Permit row found for this file number.']);
        }

        // Resolve current holder from fileNumber table
        $fileNumberRow = $db->table('fileNumber')
            ->where('mlsfNo', $fileNo)
            ->select(['id', 'mlsfNo', 'FileName', 'plot_no', 'tp_no', 'lga', 'location'])
            ->first();

        $currentHolder = $fileNumberRow ? ($fileNumberRow->FileName ?? '') : '';

        return response()->json([
            'success' => true,
            'op_rows' => $opRows->values(),
            'current_holder' => $currentHolder,
            'file_number_row' => $fileNumberRow,
        ]);
    }

    /**
     * Execute the Match OP operation:
     * 1. Allocate a TEMP-XXXXX from temp_fileno_sequence.
     * 2. Update the existing OP PRA row (system_source, temp_fileno, fileno).
     * 3. Create the Transfer of Title (OP) PRA row with the same prop_id and temp_fileno.

     * POST /lands-one-stop-shop/applications/match-op
     * Body: { pra_id: int, current_holder?: string }
     */

    public function matchOp(Request $request, PraRecordService $praService): JsonResponse
    {
        // Merger path: pra_ids[] (multiple OPs → grouped match)
        if ($request->has('pra_ids')) {
            return $this->matchOpMerger($request, $praService);
        }

        $validated = $request->validate([
            'pra_id' => 'required|integer|min:1',
            'current_holder' => 'nullable|string|max:255',
            'allottee' => 'nullable|string|max:255',
            'override_holder' => 'nullable|boolean',
        ]);

        $praId = (int) $validated['pra_id'];
        $currentHolder = trim((string) ($validated['current_holder'] ?? ''));
        $overrideHolder = (bool) ($validated['override_holder'] ?? false);
        $overrideAllottee = trim((string) ($validated['allottee'] ?? ''));

        $db = DB::connection('sqlsrv');
        $userId = auth()->id();

        // Load the OP PRA row
        $opRow = $db->table('pra')->where('id', $praId)->first();

        if (!$opRow) {
            return response()->json(['success' => false, 'message' => 'PRA record not found.'], 404);
        }

        // Guard: must be an OP
        $instrumentType = $opRow->instrument_type ?? $opRow->transaction_type ?? '';
        if (stripos($instrumentType, 'Occupancy Permit') === false) {
            return response()->json(['success' => false, 'message' => 'PRA record is not an Occupancy Permit (OP).'], 422);
        }

        // Guard: not already matched
        if (($opRow->system_source ?? '') === 'OSSOPCHANGEOFNAME') {
            // Check if Transfer of Title sibling also exists. Match by the
            // new lineage columns first (parent_prop_id / source_op_id) and
            // fall back to the legacy shared prop_id pattern.
            $totExists = $db->table('pra')
                ->where('system_source', 'OSSOPCHANGEOFNAME')
                ->where(function ($q) {
                    $q->where('instrument_type', 'like', '%Transfer of Title%')
                        ->orWhere('transaction_type', 'like', '%Transfer of Title%');
                })
                ->where(function ($q) use ($opRow) {
                    // Lineage (Phase 3+): ToT rows reference this OP via
                    // parent_prop_id + source_op_id.
                    $q->where(function ($qq) use ($opRow) {
                        $qq->where('parent_prop_id', $opRow->prop_id)
                            ->orWhere('source_op_id', (int) $opRow->id);
                    });
                    // Legacy: ToT inherited the OP's prop_id.
                    if (!empty($opRow->prop_id)) {
                        $q->orWhere(function ($qq) use ($opRow) {
                            $qq->where('prop_id', $opRow->prop_id)
                                ->whereNull('parent_prop_id');
                        });
                    }
                })
                ->exists();

            if ($totExists) {
                $normalizedRows = $this->normalizeOssTotRegistrationFields($db, $opRow->prop_id ?? null);

                return response()->json([
                    'success' => false,
                    'message' => 'This OP is already matched and has a Transfer of Title row.',
                    'data' => [
                        'normalized_tot_rows' => $normalizedRows,
                    ],
                ], 422);
            }
        }

        // Resolve the file number to use
        $mlsFNo = $opRow->mlsFNo ?: $opRow->fileno;
        if (!$mlsFNo) {
            return response()->json(['success' => false, 'message' => 'PRA record has no file number (mlsFNo/fileno).'], 422);
        }

        // Resolve current holder: caller-supplied → fileNumber table → fallback to OP Grantee
        if ($currentHolder === '') {
            $fileNumberRow = $db->table('fileNumber')->where('mlsfNo', $mlsFNo)->first();
            $currentHolder = $fileNumberRow ? ($fileNumberRow->FileName ?? '') : '';
        }
        // Grantee/party_2 may be an empty string rather than NULL, so ?? is not enough
        // here — an empty Grantee must still fall through to party_2.
        $opAllottee = trim((string) ($opRow->Grantee ?? '')) ?: trim((string) ($opRow->party_2 ?? ''));

        if ($currentHolder === '') {
            $currentHolder = $opAllottee;
        }

        // The allottee is the OP's Grantee (party_2), or the override value if provided
        $allottee = ($overrideAllottee !== '') ? $overrideAllottee : $opAllottee;

        // Without an allottee the ToT's Part 1 would be written blank, which is the
        // state this flow exists to populate. Fail loudly instead of creating it empty.
        if (trim($allottee) === '') {
            return response()->json([
                'success' => false,
                'message' => 'That OP has no allottee name (Grantee/Part 2), so the Transfer of Title Part 1 cannot be set from it.',
            ], 422);
        }

        try {
            $result = $db->transaction(function () use ($db, $praService, $praId, $opRow, $mlsFNo, $allottee, $currentHolder, $userId, $overrideHolder) {
                // ── Step 1: Allocate TEMP file numbers ──
                // OP keeps its own temp (allocated here if not already assigned).
                // ToT gets a SEPARATE temp so it never shares an identifier with
                // the source OP (prevents the OP↔ToT prop_id/temp contamination
                // documented in op-tot-mismatch-rootcause.md).
                $opSeqId = $db->table('temp_fileno_sequence')->insertGetId([
                    'created_by' => $userId,
                    'is_used' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $opTempFileno = 'TEMP-' . str_pad((string) $opSeqId, 5, '0', STR_PAD_LEFT);

                $totSeqId = $db->table('temp_fileno_sequence')->insertGetId([
                    'created_by' => $userId,
                    'is_used' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $totTempFileno = 'TEMP-' . str_pad((string) $totSeqId, 5, '0', STR_PAD_LEFT);

                // ── Step 2: Update the existing OP PRA row ──
                $db->table('pra')->where('id', $praId)->update([
                    'temp_fileno' => $opTempFileno,
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'fileno' => $opRow->fileno ?: $mlsFNo,
                    'mlsFNo' => $mlsFNo,
                    'updated_at' => now(),
                ]);

                // ── Step 3: Create Transfer of Title row ──
                // Do NOT pass prop_id from the OP. Instead set
                // force_fresh_prop_id=true so PraRecordService mints a brand-new
                // prop_id, and record OP lineage via parent_prop_id +
                // source_op_table/source_op_id.
                $opLocation = $opRow->location ?? $opRow->property_description ?? null;
                $opPropertyDescription = $opRow->property_description ?? $opRow->location ?? null;
                $totPra = $praService->createRecord([
                    'mlsFNo' => $mlsFNo,
                    'fileno' => $mlsFNo,
                    'temp_fileno' => $totTempFileno,
                    'force_fresh_prop_id' => true,
                    'parent_prop_id' => $opRow->prop_id,
                    'source_op_table' => 'pra',
                    'source_op_id' => (int) $opRow->id,
                    'transaction_type' => 'Transfer of Title (OP)',
                    'instrument_type' => 'Transfer of Title (OP)',
                    'op_type' => $opRow->op_type ?? null,
                    'op_serial_number' => $opRow->op_serial_number ?? null,
                    'transaction_date' => $opRow->transaction_date ?? null,
                    'regNo' => '0/0/0',
                    'serialNo' => '0',
                    'pageNo' => '0',
                    'volumeNo' => '0',
                    'reg_date' => $opRow->deeds_date ?? $opRow->reg_date ?? null,
                    'reg_time' => $opRow->deeds_time ?? $opRow->reg_time ?? null,
                    'location' => $opLocation,
                    'property_description' => $opPropertyDescription,
                    'plot_no' => $opRow->plot_no ?? $opRow->plot_number ?? null,
                    'tp_no' => $opRow->tp_no ?? null,
                    'lgsaOrCity' => $opRow->lgsaOrCity ?? null,
                    'land_use' => $opRow->land_use ?? null,
                    'purpose' => $opRow->purpose ?? null,
                    'source' => 'Match OP',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'Grantor' => $allottee,
                    'Grantee' => $currentHolder,
                    'party_1' => $allottee,
                    'party_2' => $currentHolder,
                    'parties' => [
                        'grantor' => $allottee,
                        'grantee' => $currentHolder,
                        'party_1' => $allottee,
                        'party_2' => $currentHolder,
                    ],
                ], $userId);

                // ── Step 4 (optional): Override — sync Current Holder name across tables ──
                $overrideSynced = false;
                if ($overrideHolder && $currentHolder !== '') {
                    $now = now();

                    $this->matchOpSafeUpdate(
                        'fileNumber',
                        ['mlsfNo', 'full_file_number', 'file_number'],
                        $mlsFNo,
                        ['FileName' => $currentHolder, 'updated_at' => $now]
                    );

                    $this->matchOpSafeUpdate(
                        'file_indexings',
                        ['file_number', 'full_file_number', 'mlsfNo'],
                        $mlsFNo,
                        ['file_title' => $currentHolder, 'current_holder' => $currentHolder, 'updated_by' => $userId, 'updated_at' => $now]
                    );

                    $this->matchOpSafeUpdate(
                        'customers_staging',
                        ['file_number', 'full_file_number', 'mlsfNo'],
                        $mlsFNo,
                        ['customer_name' => $currentHolder, 'updated_by' => $userId, 'updated_at' => $now]
                    );

                    $this->matchOpSafeUpdate(
                        'mls_file_no',
                        ['full_file_number', 'file_number', 'mlsfNo'],
                        $mlsFNo,
                        ['file_name' => $currentHolder, 'updated_at' => $now]
                    );

                    $overrideSynced = true;

                    \Illuminate\Support\Facades\Log::info('Match OP — override holder synced', [
                        'mlsFNo' => $mlsFNo,
                        'current_holder' => $currentHolder,
                        'user_id' => $userId,
                    ]);
                }

                // Synchronize to entities_staging during file number commissioning
                $this->syncEntitiesStaging($mlsFNo, $currentHolder);

                return [
                    'op_temp_fileno' => $opTempFileno,
                    'tot_temp_fileno' => $totTempFileno,
                    // Backwards-compat alias used by older callers (was the
                    // shared temp before this fix).
                    'temp_fileno' => $totTempFileno,
                    'op_pra_id' => $praId,
                    'tot_pra_id' => data_get($totPra, 'id'),
                    'op_prop_id' => $opRow->prop_id,
                    'tot_prop_id' => data_get($totPra, 'prop_id'),
                    // Backwards-compat alias.
                    'prop_id' => data_get($totPra, 'prop_id'),
                    'mlsFNo' => $mlsFNo,
                    'allottee' => $allottee,
                    'current_holder' => $currentHolder,
                    'override_synced' => $overrideSynced,
                ];
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Match OP failed', [
                'pra_id' => $praId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Match OP failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OP matched successfully. Transfer of Title row created.',
            'data' => $result,
        ]);
    }

    /**
     * Handle merger OP match: flag all OPs as a group, create one ToT row
     * with concatenated Party 2 names, nulling out OP-specific identifiers.
     */
    private function matchOpMerger(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'pra_ids' => 'required|array|min:2',
            'pra_ids.*' => 'required|integer|min:1',
        ]);

        $praIds = array_unique(array_map('intval', $validated['pra_ids']));
        $db = DB::connection('sqlsrv');
        $userId = auth()->id();

        // Load all PRA rows
        $opRows = $db->table('pra')->whereIn('id', $praIds)->get();

        if ($opRows->count() < 2) {
            return response()->json(['success' => false, 'message' => 'At least 2 valid PRA records are required for a merger match.'], 422);
        }

        // Validate all are OPs
        foreach ($opRows as $row) {
            $type = $row->instrument_type ?? $row->transaction_type ?? '';
            if (stripos($type, 'Occupancy Permit') === false) {
                return response()->json(['success' => false, 'message' => 'PRA record ID ' . $row->id . ' is not an Occupancy Permit.'], 422);
            }
        }

        // Use first OP as the anchor record
        $firstOp = $opRows->first();
        $mlsFNo = $firstOp->mlsFNo ?: $firstOp->fileno;

        if (!$mlsFNo) {
            return response()->json(['success' => false, 'message' => 'PRA records have no file number.'], 422);
        }

        // Build Party 1 of the ToT = concatenated Party 2 names of all OPs
        $party1Names = $opRows->map(function ($r) {
            return trim((string) ($r->Grantee ?? $r->party_2 ?? ''));
        })->filter()->unique()->values()->implode(' / ');

        if ($party1Names === '') {
            $party1Names = 'Multiple OP Holders';
        }

        $mergerGroupId = (string) Str::uuid();

        try {
            $result = $db->transaction(function () use ($db, $praService, $praIds, $opRows, $firstOp, $mlsFNo, $party1Names, $mergerGroupId, $userId) {
                // ── Step 1: Allocate TEMP file numbers ──
                // All OPs in the merger share one OP-side temp; the ToT gets a
                // SEPARATE temp so it never shares an identifier with the OPs.
                $opSeqId = $db->table('temp_fileno_sequence')->insertGetId([
                    'created_by' => $userId,
                    'is_used' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $opTempFileno = 'TEMP-' . str_pad((string) $opSeqId, 5, '0', STR_PAD_LEFT);

                $totSeqId = $db->table('temp_fileno_sequence')->insertGetId([
                    'created_by' => $userId,
                    'is_used' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $totTempFileno = 'TEMP-' . str_pad((string) $totSeqId, 5, '0', STR_PAD_LEFT);

                // ── Step 2: Update all OP PRA rows ──
                $db->table('pra')->whereIn('id', $praIds)->update([
                    'temp_fileno' => $opTempFileno,
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'merger_group_id' => $mergerGroupId,
                    'is_merger_op' => 1,
                    'updated_at' => now(),
                ]);

                // ── Step 3: Create one Transfer of Title row (merger) ──
                // Lineage points to the anchor OP; merger_group_id keeps the
                // full set discoverable. Brand-new prop_id (no inheritance).
                $firstOpLocation = $firstOp->location ?? $firstOp->property_description ?? null;
                $firstOpPropertyDescription = $firstOp->property_description ?? $firstOp->location ?? null;
                $totPra = $praService->createRecord([
                    'mlsFNo' => $mlsFNo,
                    'fileno' => $mlsFNo,
                    'temp_fileno' => $totTempFileno,
                    'force_fresh_prop_id' => true,
                    'parent_prop_id' => $firstOp->prop_id,
                    'source_op_table' => 'pra',
                    'source_op_id' => (int) $firstOp->id,
                    'transaction_type' => 'Transfer of Title (OP)',
                    'instrument_type' => 'Transfer of Title (OP)',
                    'op_type' => $firstOp->op_type ?? null,
                    'op_serial_number' => $firstOp->op_serial_number ?? null,
                    'transaction_date' => $firstOp->transaction_date ?? null,
                    'regNo' => '0/0/0',
                    'serialNo' => '0',
                    'pageNo' => '0',
                    'volumeNo' => '0',
                    'reg_date' => $firstOp->deeds_date ?? $firstOp->reg_date ?? null,
                    'reg_time' => $firstOp->deeds_time ?? $firstOp->reg_time ?? null,
                    'location' => $firstOpLocation,
                    'property_description' => $firstOpPropertyDescription,
                    'plot_no' => $firstOp->plot_no ?? $firstOp->plot_number ?? null,
                    'tp_no' => $firstOp->tp_no ?? null,
                    'lgsaOrCity' => $firstOp->lgsaOrCity ?? null,
                    'land_use' => $firstOp->land_use ?? null,
                    'purpose' => $firstOp->purpose ?? null,
                    'source' => 'Match Merger OP',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'merger_group_id' => $mergerGroupId,
                    'is_merger_op' => 1,
                    'Grantor' => $party1Names,
                    'Grantee' => null,
                    'party_1' => $party1Names,
                    'party_2' => null,
                    'parties' => [
                        'grantor' => $party1Names,
                        'party_1' => $party1Names,
                    ],
                ], $userId);

                // Synchronize to entities_staging during merger file number commissioning
                $this->syncEntitiesStaging($mlsFNo, $party1Names);

                return [
                    'op_temp_fileno' => $opTempFileno,
                    'tot_temp_fileno' => $totTempFileno,
                    'temp_fileno' => $totTempFileno,
                    'merger_group_id' => $mergerGroupId,
                    'op_pra_ids' => $praIds,
                    'tot_pra_id' => data_get($totPra, 'id'),
                    'op_prop_id' => $firstOp->prop_id,
                    'tot_prop_id' => data_get($totPra, 'prop_id'),
                    'prop_id' => data_get($totPra, 'prop_id'),
                    'mlsFNo' => $mlsFNo,
                    'party_1' => $party1Names,
                    'op_count' => count($praIds),
                ];
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Match Merger OP failed', [
                'pra_ids' => $praIds,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Match Merger OP failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Merger OP matched. ' . count($praIds) . ' OPs grouped. Transfer of Title row created.',
            'data' => $result,
        ]);
    }

    /**
     * Update $table rows matching $value against the first $candidateColumns entry
     * that actually exists in the table schema. Silently skips missing columns.
     */
    private function matchOpSafeUpdate(
        string $table,
        array $candidateColumns,
        string $value,
        array $payload
    ): int {
        try {
            $existing = array_map(
                'strtolower',
                DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($table)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('matchOpSafeUpdate: schema fetch failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        // Filter payload to only columns that actually exist
        $safePayload = [];
        foreach ($payload as $col => $val) {
            if (in_array(strtolower($col), $existing, true)) {
                $safePayload[$col] = $val;
            }
        }
        if (empty($safePayload)) {
            return 0;
        }

        // Use the first lookup column that exists
        foreach ($candidateColumns as $col) {
            if (in_array(strtolower($col), $existing, true)) {
                return DB::connection('sqlsrv')
                    ->table($table)
                    ->where($col, $value)
                    ->update($safePayload);
            }
        }

        \Illuminate\Support\Facades\Log::warning('matchOpSafeUpdate: no valid lookup column found', [
            'table' => $table,
            'candidates' => $candidateColumns,
        ]);
        return 0;
    }

    private function normalizeOssTotRegistrationFields($db, $propId): int
    {
        if ($propId === null || $propId === '') {
            return 0;
        }

        $updates = ['updated_at' => now()];

        if (Schema::connection('sqlsrv')->hasColumn('pra', 'regNo')) {
            $updates['regNo'] = '0/0/0';
        }
        if (Schema::connection('sqlsrv')->hasColumn('pra', 'serialNo')) {
            $updates['serialNo'] = '0';
        }
        if (Schema::connection('sqlsrv')->hasColumn('pra', 'pageNo')) {
            $updates['pageNo'] = '0';
        }
        if (Schema::connection('sqlsrv')->hasColumn('pra', 'volumeNo')) {
            $updates['volumeNo'] = '0';
        }

        if (count($updates) === 1) {
            return 0;
        }

        return (int) $db->table('pra')
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->where(function ($q) {
                $q->where('instrument_type', 'like', '%Transfer of Title%')
                    ->orWhere('transaction_type', 'like', '%Transfer of Title%');
            })
            ->where(function ($q) use ($propId) {
                // Lineage (Phase 3+) and legacy fallback (where ToT inherited
                // the OP's prop_id).
                $q->where('parent_prop_id', $propId)
                    ->orWhere(function ($qq) use ($propId) {
                    $qq->where('prop_id', $propId)
                        ->whereNull('parent_prop_id');
                });
            })
            ->update($updates);
    }

    /**
     * Flag multiple PRA rows (all OP records for the same prop_id) as a merger group.
     * Assigns a shared merger_group_id UUID and sets is_merger_op = 1 on each row.
     *
     * POST /lands-one-stop-shop/applications/op-resettlement/pra/flag-merger
     * Body: { pra_ids: [1, 2, ...] }
     */
    public function flagMergerOp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pra_ids' => 'required|array|min:2',
            'pra_ids.*' => 'required|integer|min:1',
        ]);

        $praIds = array_map('intval', $validated['pra_ids']);
        $mergerGroupId = (string) Str::uuid();

        $updated = DB::connection('sqlsrv')
            ->table('pra')
            ->whereIn('id', $praIds)
            ->update([
                'merger_group_id' => $mergerGroupId,
                'is_merger_op' => 1,
                'updated_at' => now(),
            ]);

        \Illuminate\Support\Facades\Log::info('FEFR flagMergerOp — rows stamped', [
            'pra_ids' => $praIds,
            'merger_group_id' => $mergerGroupId,
            'rows_updated' => $updated,
        ]);

        return response()->json([
            'success' => true,
            'merger_group_id' => $mergerGroupId,
            'rows_updated' => $updated,
            'message' => "Flagged {$updated} OP record(s) as Merger OP.",
        ]);
    }

    /**
     * Delete the Transfer of Title (ToT) record and detach the original
     * Occupancy Permit (OP) record(s) from it.
     *
     * DELETE /lands-one-stop-shop/applications/delete-master/{id}
     */
    public function deleteMaster(Request $request, $id): JsonResponse
    {
        $db = DB::connection('sqlsrv');
        $userId = auth()->id();

        // Security gate: only Supper Admin can execute master delete
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($role) => trim($role))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($role) => strcasecmp($role, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.'
            ], 403);
        }

        // The ID passed can be of form "pra-123" or just "123". Parse it:
        $totPraId = $id;
        if (is_string($id) && str_starts_with($id, 'pra-')) {
            $totPraId = (int) substr($id, 4);
        } else {
            $totPraId = (int) $id;
        }

        // 1. Retrieve the Transfer of Title (ToT) record
        $totRecord = $db->table('pra')->where('id', $totPraId)->first();

        if (!$totRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer of Title record not found.'
            ], 404);
        }

        // Verify that this is indeed a Transfer of Title row
        $instrumentType = $totRecord->instrument_type ?? $totRecord->transaction_type ?? '';
        if (stripos($instrumentType, 'Transfer of Title') === false) {
            return response()->json([
                'success' => false,
                'message' => 'This record is not a Transfer of Title row.'
            ], 422);
        }

        try {
            $db->transaction(function () use ($db, $totRecord, $userId) {
                // 2. Identify the parent OP record(s) and detach them
                // We detach by setting:
                // - system_source = NULL
                // - temp_fileno = NULL
                // - merger_group_id = NULL
                // - is_merger_op = 0

                if (!empty($totRecord->merger_group_id)) {
                    // It is a merger! Find all parent OPs that have the same merger_group_id and detach them.
                    $db->table('pra')
                        ->where('merger_group_id', $totRecord->merger_group_id)
                        ->where(function ($q) {
                            $q->whereNull('instrument_type')
                                ->orWhere('instrument_type', 'not like', '%Transfer of Title%');
                        })
                        ->update([
                            'system_source' => null,
                            'temp_fileno' => null,
                            'merger_group_id' => null,
                            'is_merger_op' => 0,
                            'updated_at' => now(),
                        ]);
                } else {
                    // Single match! Find parent OP by source_op_id, or fallback to parent_prop_id
                    $parentOpQuery = null;
                    if (!empty($totRecord->source_op_id)) {
                        $parentOpQuery = $db->table('pra')->where('id', (int) $totRecord->source_op_id);
                    } elseif (!empty($totRecord->parent_prop_id)) {
                        $parentOpQuery = $db->table('pra')
                            ->where('prop_id', $totRecord->parent_prop_id)
                            ->where(function ($q) {
                                $q->whereNull('instrument_type')
                                    ->orWhere('instrument_type', 'not like', '%Transfer of Title%');
                            });
                    }

                    if ($parentOpQuery) {
                        $parentOpQuery->update([
                            'system_source' => null,
                            'temp_fileno' => null,
                            'merger_group_id' => null,
                            'is_merger_op' => 0,
                            'updated_at' => now(),
                        ]);
                    }
                }

                // 3. Clear related file system records from indexing/staging tables
                $identifiers = array_unique(array_filter([
                    $totRecord->mlsFNo ?? null,
                    $totRecord->fileno ?? null,
                    $totRecord->temp_fileno ?? null
                ]));

                if (!empty($identifiers)) {
                    $safeDelete = function ($tableName) use ($db, $identifiers) {
                        if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable($tableName)) {
                            return;
                        }

                        $columns = ['file_number', 'full_file_number', 'mlsfNo'];
                        $query = $db->table($tableName);
                        $added = false;

                        foreach ($columns as $column) {
                            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn($tableName, $column)) {
                                if (!$added) {
                                    $query->whereIn($column, $identifiers);
                                    $added = true;
                                } else {
                                    $query->orWhereIn($column, $identifiers);
                                }
                            }
                        }

                        if ($added) {
                            $query->delete();
                        }
                    };

                    // Execute safe deletes for the indexing and staging tables
                    $safeDelete('file_indexings');
                    $safeDelete('fileNumber');
                    $safeDelete('customers_staging');
                    $safeDelete('mls_file_no');
                    $safeDelete('entities_staging');
                }

                // 4. Mark the ToT record as deleted (soft delete)
                $db->table('pra')->where('id', $totRecord->id)->update([
                    'is_deleted' => 1,
                    'updated_at' => now(),
                ]);

                // 4. Log the deletion to AuditService
                try {
                    $auditService = app(\App\Services\AuditService::class);
                    $auditService->logAction(
                        'DELETED',
                        'pra',
                        $totRecord->id,
                        (array) $totRecord,
                        null,
                        "Deleted Change of Name Master matching. Soft-deleted Transfer of Title row ID {$totRecord->id} and detached parent OP(s)."
                    );
                } catch (\Throwable $auditEx) {
                    \Illuminate\Support\Facades\Log::warning("AuditLog failed for ToT deletion: " . $auditEx->getMessage());
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Master record deleted and OP detached successfully.'
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delete Master failed', [
                'tot_pra_id' => $totPraId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Delete Master failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete the Transfer of Title (ToT) records and detach parent OP(s).
     *
     * DELETE /lands-one-stop-shop/applications/delete-master-bulk
     */
    public function deleteMasterBulk(Request $request): JsonResponse
    {
        $db = DB::connection('sqlsrv');
        $userId = auth()->id();

        // Security gate: only Supper Admin can execute master delete bulk
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($role) => trim($role))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($role) => strcasecmp($role, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Bulk Master Delete.'
            ], 403);
        }

        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No record IDs provided for deletion.'
            ], 400);
        }

        try {
            $deletedCount = 0;
            $db->transaction(function () use ($db, $ids, $userId, &$deletedCount) {
                foreach ($ids as $id) {
                    // Parse ID:
                    $totPraId = $id;
                    if (is_string($id) && str_starts_with($id, 'pra-')) {
                        $totPraId = (int) substr($id, 4);
                    } else {
                        $totPraId = (int) $id;
                    }

                    // 1. Retrieve the Transfer of Title (ToT) record
                    $totRecord = $db->table('pra')->where('id', $totPraId)->first();
                    if (!$totRecord) {
                        continue;
                    }

                    // Verify that this is indeed a Transfer of Title row
                    $instrumentType = $totRecord->instrument_type ?? $totRecord->transaction_type ?? '';
                    if (stripos($instrumentType, 'Transfer of Title') === false) {
                        continue;
                    }

                    // 2. Identify the parent OP record(s) and detach them
                    if (!empty($totRecord->merger_group_id)) {
                        $db->table('pra')
                            ->where('merger_group_id', $totRecord->merger_group_id)
                            ->where(function ($q) {
                                $q->whereNull('instrument_type')
                                    ->orWhere('instrument_type', 'not like', '%Transfer of Title%');
                            })
                            ->update([
                                'system_source' => null,
                                'temp_fileno' => null,
                                'merger_group_id' => null,
                                'is_merger_op' => 0,
                                'updated_at' => now(),
                            ]);
                    } else {
                        $parentOpQuery = null;
                        if (!empty($totRecord->source_op_id)) {
                            $parentOpQuery = $db->table('pra')->where('id', (int) $totRecord->source_op_id);
                        } elseif (!empty($totRecord->parent_prop_id)) {
                            $parentOpQuery = $db->table('pra')
                                ->where('prop_id', $totRecord->parent_prop_id)
                                ->where(function ($q) {
                                    $q->whereNull('instrument_type')
                                        ->orWhere('instrument_type', 'not like', '%Transfer of Title%');
                                });
                        }

                        if ($parentOpQuery) {
                            $parentOpQuery->update([
                                'system_source' => null,
                                'temp_fileno' => null,
                                'merger_group_id' => null,
                                'is_merger_op' => 0,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // 3. Clear related file system records from indexing/staging tables
                    $identifiers = array_unique(array_filter([
                        $totRecord->mlsFNo ?? null,
                        $totRecord->fileno ?? null,
                        $totRecord->temp_fileno ?? null
                    ]));

                    if (!empty($identifiers)) {
                        $safeDelete = function ($tableName) use ($db, $identifiers) {
                            if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable($tableName)) {
                                return;
                            }

                            $columns = ['file_number', 'full_file_number', 'mlsfNo'];
                            $query = $db->table($tableName);
                            $added = false;

                            foreach ($columns as $column) {
                                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn($tableName, $column)) {
                                    if (!$added) {
                                        $query->whereIn($column, $identifiers);
                                        $added = true;
                                    } else {
                                        $query->orWhereIn($column, $identifiers);
                                    }
                                }
                            }

                            if ($added) {
                                $query->delete();
                            }
                        };

                        $safeDelete('file_indexings');
                        $safeDelete('fileNumber');
                        $safeDelete('customers_staging');
                        $safeDelete('mls_file_no');
                        $safeDelete('entities_staging');
                    }

                    // 4. Mark the ToT record as deleted (soft delete)
                    $db->table('pra')->where('id', $totRecord->id)->update([
                        'is_deleted' => 1,
                        'updated_at' => now(),
                    ]);

                    // 5. Log the deletion to AuditService
                    try {
                        $auditService = app(\App\Services\AuditService::class);
                        $auditService->logAction(
                            'DELETED',
                            'pra',
                            $totRecord->id,
                            (array) $totRecord,
                            null,
                            "Bulk Deleted Change of Name Master matching. Soft-deleted Transfer of Title row ID {$totRecord->id} and detached parent OP(s)."
                        );
                    } catch (\Throwable $auditEx) {
                        \Illuminate\Support\Facades\Log::warning("AuditLog failed for bulk ToT deletion: " . $auditEx->getMessage());
                    }

                    $deletedCount++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Successfully processed master bulk deletion. Deleted {$deletedCount} record(s)."
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Bulk delete master error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An internal database error occurred while executing bulk master delete.'
            ], 500);
        }
    }

    /**
     * Synchronize entity record into entities_staging table.
     */
    private function syncEntitiesStaging(string $mlsFNo, ?string $name): void
    {
        if (empty($mlsFNo)) {
            return;
        }

        $name = trim((string) $name);
        $resolvedCustomerType = 'Individual';
        if ($name !== '') {
            $normalized = strtolower(preg_replace('/\s+/', ' ', $name));
            $corporateTokens = ['ltd', 'limited', 'plc', 'inc', 'llc', 'company', 'co.', 'corp', 'corporate', 'enterprise', 'global', 'resources', 'venture', 'investment'];
            foreach ($corporateTokens as $token) {
                if (str_contains($normalized, $token)) {
                    $resolvedCustomerType = 'Corporate';
                    break;
                }
            }
        }

        $db = DB::connection('sqlsrv');
        if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('entities_staging')) {
            $existing = $db->table('entities_staging')->where('file_number', $mlsFNo)->first();
            if ($existing) {
                $db->table('entities_staging')->where('id', $existing->id)->update([
                    'entity_type' => $resolvedCustomerType,
                    'entity_name' => $name !== '' ? $name : 'N/A',
                    'updated_at' => now(),
                ]);
            } else {
                $db->table('entities_staging')->insert([
                    'entity_type' => $resolvedCustomerType,
                    'entity_name' => $name !== '' ? $name : 'N/A',
                    'file_number' => $mlsFNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
