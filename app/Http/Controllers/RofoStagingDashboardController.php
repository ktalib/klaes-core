<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Support\DateFormatter;

class RofoStagingDashboardController extends Controller
{
    private const ROFO_TYPES = [
        'Right of Occupancy',
        'R OF O',
        'R-OF-O',
        'R.OF.O',
        'R-OFO',
    ];

    private function baseQuery()
    {
        return DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->whereIn(
                DB::raw('UPPER(LTRIM(RTRIM(instrument_type)))'),
                array_map('strtoupper', self::ROFO_TYPES)
            );
    }

    public function index()
    {
        return view('rofo_staging_dashboard.index');
    }

    public function stats(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $base = $this->baseQuery()
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to));

        return response()->json([
            'total_records' => (clone $base)->count(),
        ]);
    }

    public function tableAll(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $innerQuery = $this->baseQuery()
            ->select([
                'id',
                'prop_id',
                'mlsFNo',
                'kangisFileNo',
                'NewKANGISFileno',
                'fileno',
                'instrument_type',
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(Grantee)),''),
                    NULLIF(LTRIM(RTRIM(Assignee)),''),
                    NULLIF(LTRIM(RTRIM(Lessee)),''),
                    NULLIF(LTRIM(RTRIM(Mortgagee)),''),
                    NULLIF(LTRIM(RTRIM(Surrenderee)),''),
                    NULLIF(LTRIM(RTRIM(Purchaser)),''),
                    NULLIF(LTRIM(RTRIM(Donee)),''),
                    NULLIF(LTRIM(RTRIM(Releasee)),''),
                    NULLIF(LTRIM(RTRIM(party_2)),'')
                ) as party_2"),
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(regNo)),''),
                    CONCAT(ISNULL(serialNo,'0'), '/', ISNULL(pageNo,'0'), '/', ISNULL(volumeNo,'0'))
                ) as reg_no"),
                'land_use',
                'lgsaOrCity',
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(property_description)),''),
                    NULLIF(LTRIM(RTRIM(location)),''),
                    CONCAT_WS(', ',
                        NULLIF(LTRIM(RTRIM(plot_no)),''),
                        NULLIF(LTRIM(RTRIM(streetName)),''),
                        NULLIF(LTRIM(RTRIM(districtName)),'')
                    )
                ) as property_description"),
                'transaction_date',
                'created_at',
                DB::raw("CONVERT(varchar, created_at, 23) as created_at_sort"),
            ])
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to));

        // Wrap with ROW_NUMBER to collapse rows by prop_id (latest first)
        $innerSql = $innerQuery->toSql();
        $innerBindings = $innerQuery->getBindings();

        $rankedSql = "SELECT *, "
            . "ROW_NUMBER() OVER (PARTITION BY CASE WHEN prop_id IS NOT NULL AND LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50)))) <> '' THEN CAST(prop_id AS NVARCHAR(50)) ELSE CAST(NEWID() AS NVARCHAR(50)) END ORDER BY created_at DESC) as _rn "
            . "FROM ({$innerSql}) as _inner";

        // timeline_count: cross-table count matching PropertySearchController's 4 tables
        $timelineCountExpr = "CASE WHEN ranked.prop_id IS NOT NULL AND LTRIM(RTRIM(CAST(ranked.prop_id AS NVARCHAR(50)))) <> '' THEN "
            . "(SELECT COUNT(*) FROM file_history_staging WHERE CAST(prop_id AS NVARCHAR(50)) = CAST(ranked.prop_id AS NVARCHAR(50))) + "
            . "(SELECT COUNT(*) FROM pra WHERE CAST(prop_id AS NVARCHAR(50)) = CAST(ranked.prop_id AS NVARCHAR(50)) AND (is_deleted IS NULL OR is_deleted = 0)) + "
            . "(SELECT COUNT(*) FROM deed_registrations WHERE CAST(prop_id AS NVARCHAR(50)) = CAST(ranked.prop_id AS NVARCHAR(50))) + "
            . "(SELECT COUNT(*) FROM CofO_staging WHERE CAST(prop_id AS NVARCHAR(50)) = CAST(ranked.prop_id AS NVARCHAR(50))) "
            . "ELSE 1 END";

        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$rankedSql}) as ranked"))
            ->setBindings($innerBindings)
            ->where('_rn', 1)
            ->select([
                'id', 'prop_id', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno',
                'instrument_type', 'party_2', 'reg_no', 'land_use', 'lgsaOrCity',
                'property_description', 'transaction_date', 'created_at', 'created_at_sort',
                DB::raw("({$timelineCountExpr}) as timeline_count"),
            ]);

        return DataTables::of($query)
            ->orderColumn('mlsFNo',             fn($q, $d) => $q->orderBy('mlsFNo', $d))
            ->orderColumn('instrument_type',    fn($q, $d) => $q->orderBy('instrument_type', $d))
            ->orderColumn('party_2',            fn($q, $d) => $q->orderBy('party_2', $d))
            ->orderColumn('reg_no',             fn($q, $d) => $q->orderBy('reg_no', $d))
            ->orderColumn('land_use',           fn($q, $d) => $q->orderBy('land_use', $d))
            ->orderColumn('lgsaOrCity',         fn($q, $d) => $q->orderBy('lgsaOrCity', $d))
            ->orderColumn('property_description', fn($q, $d) => $q->orderBy('property_description', $d))
            ->orderColumn('transaction_date',   fn($q, $d) => $q->orderBy('transaction_date', $d))
            ->orderColumn('created_at',         fn($q, $d) => $q->orderBy('id', $d))
            ->filterColumn('mlsFNo',            fn($q, $kw) => $q->where(function ($sub) use ($kw) {
                $sub->where('mlsFNo', 'like', "%{$kw}%")
                    ->orWhere('kangisFileNo', 'like', "%{$kw}%")
                    ->orWhere('NewKANGISFileno', 'like', "%{$kw}%")
                    ->orWhere('fileno', 'like', "%{$kw}%");
            }))
            ->filterColumn('party_2',           fn($q, $kw) => $q->where('party_2', 'like', "%{$kw}%"))
            ->filterColumn('reg_no',            fn($q, $kw) => $q->where('reg_no', 'like', "%{$kw}%"))
            ->filterColumn('lgsaOrCity',        fn($q, $kw) => $q->where('lgsaOrCity', 'like', "%{$kw}%"))
            ->filterColumn('property_description', fn($q, $kw) => $q->where('property_description', 'like', "%{$kw}%"))
            ->editColumn('transaction_date', function ($row) {
                return DateFormatter::toDisplay($row->transaction_date) ?? '—';
            })
            ->editColumn('created_at', function ($row) {
                return DateFormatter::toDisplay($row->created_at) ?? '—';
            })
            ->addColumn('created_at_sort', fn($row) => $row->created_at_sort ?? '')
            ->rawColumns([])
            ->make(true);
    }
}
