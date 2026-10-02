<?php

namespace App\Http\Controllers;

use App\Models\OpVerification;
use App\Services\OpRecordSource;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * OP Verification — look up an Occupancy Permit against the same record set
 * the OPs Dashboard shows (OpRecordSource) and log the outcome of the check.
 */
class OpVerificationController extends Controller
{
    protected OpRecordSource $opRecords;

    public function __construct(OpRecordSource $opRecords)
    {
        $this->opRecords = $opRecords;
    }

    public function index()
    {
        $recent = OpVerification::orderByDesc('id')->limit(25)->get();

        return view('admin.op-verifications.index', [
            'recent'   => $recent,
            'statuses' => OpVerification::STATUSES,
        ]);
    }

    /**
     * DataTable — every OP in the register (same set as the OPs Dashboard).
     * Serial numbers are NOT unique, so filtering by one keeps every match and
     * the officer picks the right record.
     */
    public function table(Request $request)
    {
        $query = $this->opRecords->rankedQuery();

        return DataTables::of($query)
            ->filterColumn('temp_fileno',      fn($q, $kw) => $q->where('temp_fileno',      'like', "%$kw%"))
            ->filterColumn('mls_file_number',  fn($q, $kw) => $q->where('mls_file_number',  'like', "%$kw%"))
            ->filterColumn('op_serial_number', fn($q, $kw) => $q->where('op_serial_number', 'like', "%$kw%"))
            ->filterColumn('allottee',         fn($q, $kw) => $q->where('allottee',         'like', "%$kw%"))
            ->addColumn('record_key', fn($row) => OpVerification::recordKey((array) $row))
            ->addColumn('verification', function ($row) {
                $v = OpVerification::where('record_key', OpVerification::recordKey((array) $row))
                    ->orderByDesc('id')
                    ->first();

                return $v ? $this->presentVerification($v) : null;
            })
            ->editColumn('created_at', fn($row) => DateFormatter::toDisplay($row->created_at) ?? '—')
            // The page escapes every value as it renders, and it posts row data
            // back when logging a verification — escaping here too would double
            // encode names like "SHU'AIBU" on screen and in the stored snapshot.
            ->escapeColumns([])
            ->rawColumns([])
            ->make(true);
    }

    /**
     * Dashboard figures for the top of the page.
     *
     * Reads op_verifications only. The size of the OP register itself comes
     * from the table's own recordsTotal, so one page load never runs the
     * OpRecordSource union twice.
     */
    public function dashboard()
    {
        $checks = [
            'total'      => OpVerification::count(),
            'verified'   => OpVerification::where('status', 'verified')->count(),
            'suspicious' => OpVerification::where('status', 'suspicious')->count(),
        ];

        // An OP can be checked more than once. What it currently stands at is
        // its latest check, so the register-level figures come from that row.
        $standing = DB::connection('sqlsrv')->select("
            SELECT status,
                   COUNT(*) AS total,
                   SUM(CASE WHEN record_key LIKE 'serial:%' THEN 1 ELSE 0 END) AS unmatched
            FROM (
                SELECT status,
                       record_key,
                       ROW_NUMBER() OVER (PARTITION BY record_key ORDER BY id DESC) AS rn
                FROM op_verifications
                WHERE record_key IS NOT NULL AND LTRIM(RTRIM(record_key)) <> ''
            ) latest
            WHERE rn = 1
            GROUP BY status
        ");

        $opsVerified = 0;
        $opsFlagged  = 0;
        $unmatched   = 0;

        foreach ($standing as $row) {
            $unmatched += (int) $row->unmatched;

            if ($row->status === 'verified') {
                // Serial-only keys matched no OP in the register, so they are
                // left out here — coverage measures the register, not searches.
                $opsVerified = (int) $row->total - (int) $row->unmatched;
            } elseif ($row->status === 'suspicious') {
                $opsFlagged = (int) $row->total;
            }
        }

        return response()->json([
            'checks'        => $checks,
            'ops_verified'  => $opsVerified,
            'ops_flagged'   => $opsFlagged,
            'unmatched'     => $unmatched,
            'checks_today'  => $this->checksSince(Carbon::today()),
            'checks_week'   => $this->checksSince(Carbon::today()->subDays(6)),
            'officers'      => $this->topOfficers(5),
        ]);
    }

    /** Checks logged on or after a day. verified_at is set on create; created_at is the fallback. */
    protected function checksSince(Carbon $day): int
    {
        return OpVerification::whereRaw(
            'CAST(ISNULL(verified_at, created_at) AS DATE) >= CAST(? AS DATE)',
            [$day->toDateString()]
        )->count();
    }

    /** Busiest officers by number of checks logged. */
    protected function topOfficers(int $limit): array
    {
        $rows = DB::connection('sqlsrv')->select("
            SELECT TOP {$limit}
                   ISNULL(NULLIF(LTRIM(RTRIM(verified_by_name)), ''), 'Unknown') AS name,
                   COUNT(*) AS total,
                   SUM(CASE WHEN status = 'verified'   THEN 1 ELSE 0 END) AS verified,
                   SUM(CASE WHEN status = 'suspicious' THEN 1 ELSE 0 END) AS suspicious
            FROM op_verifications
            GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(verified_by_name)), ''), 'Unknown')
            ORDER BY COUNT(*) DESC
        ");

        return array_map(fn($row) => [
            'name'       => (string) $row->name,
            'total'      => (int) $row->total,
            'verified'   => (int) $row->verified,
            'suspicious' => (int) $row->suspicious,
        ], $rows);
    }

    /**
     * Log a verification outcome. Works both for a picked record and for a
     * serial that matched nothing (status not_found / suspicious).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'status'               => 'required|string|in:' . implode(',', array_keys(OpVerification::STATUSES)),
            'op_serial_number'     => 'required|string|max:100',
            'record_key'           => 'nullable|string|max:100',
            'prop_id'              => 'nullable|string|max:50',
            'source_capture_id'    => 'nullable|integer',
            'pra_id'               => 'nullable|integer',
            'mls_file_number'      => 'nullable|string|max:100',
            'temp_fileno'          => 'nullable|string|max:100',
            'op_type'              => 'nullable|string|max:100',
            'grantor'              => 'nullable|string|max:255',
            'allottee'             => 'nullable|string|max:255',
            'property_description' => 'nullable|string|max:500',
            'remarks'              => 'nullable|string|max:2000',
        ]);

        $recordKey = trim((string) ($validated['record_key'] ?? ''));
        if ($recordKey === '') {
            $recordKey = OpVerification::recordKey($validated);
        }

        $verification = OpVerification::create(array_merge($validated, [
            'record_key'       => $recordKey,
            'method'           => 'serial',
            'verified_by'      => auth()->id(),
            'verified_by_name' => auth()->user()->name ?? 'System',
            'verified_at'      => now(),
        ]));

        return response()->json([
            'success'      => true,
            'verification' => $this->presentVerification($verification),
        ]);
    }

    protected function presentVerification(OpVerification $v): array
    {
        return [
            'id'          => $v->id,
            'status'      => $v->status,
            'label'       => $v->status_label,
            'remarks'     => $v->remarks,
            'verified_by' => $v->verified_by_name,
            'verified_at' => optional($v->verified_at)->format('d M Y, H:i'),
        ];
    }
}
