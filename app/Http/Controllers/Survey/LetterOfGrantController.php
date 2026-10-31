<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Letter of Grant (Compensation → Resettlement Plot Allocation; user, 2026-10-30):
 * every Direct Allocation Occupancy Permit. They live in Deeds: instrument_capture
 * rows of type "Occupancy Permit (OP)" with op_type Direct Allocation, and the older
 * standalone pra rows (the split App\Services\OpRecordSource uses). Read-only here.
 *
 * The allottee is the OP's grantee (party 2). Many older pra rows have no grantee;
 * then the land file's name is shown instead, marked as such, since the file may
 * since have changed hands (App\Support\OpAllottee).
 */
class LetterOfGrantController extends Controller
{
    private const TYPES = ['OP Direct Allocation', 'Direct Allocation'];

    public function index(Request $r)
    {
        $db = DB::connection('sqlsrv');

        $capture = $db->table('instrument_capture as ic')
            ->where('ic.instrument_type', 'Occupancy Permit (OP)')
            ->whereIn('ic.op_type', self::TYPES)
            ->where(fn ($q) => $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0))
            ->selectRaw("'capture' AS src, ic.id, CAST(ic.op_serial_number AS NVARCHAR(50)) AS serial,
                NULLIF(LTRIM(RTRIM(ic.party_2_name)), '') AS allottee, CAST(0 AS BIT) AS allottee_from_file,
                ic.plot_number AS plot_no, ic.district, ic.lga,
                COALESCE(NULLIF(ic.property_location, ''), ic.property_description) AS location,
                COALESCE(NULLIF(ic.lpkn_no, ''), ic.tp_no) AS layout,
                COALESCE(NULLIF(ic.mlsFNo, ''), ic.temp_fileno) AS file_number,
                ic.registration_number AS reg_no, TRY_CONVERT(DATE, ic.reg_date) AS reg_date, ic.land_use, CAST(ic.plot_size AS NVARCHAR(50)) AS plot_size");

        $fileName = "(SELECT TOP 1 fn.FileName FROM fileNumber fn WHERE fn.mlsfNo = p.mlsFNo AND (fn.is_deleted IS NULL OR fn.is_deleted = 0) AND NULLIF(fn.FileName, '') IS NOT NULL)";
        $pra = $db->table('pra as p')
            ->where('p.instrument_type', 'Occupancy Permit (OP)')
            ->whereNull('p.instrument_capture_id')
            ->whereIn('p.op_type', self::TYPES)
            ->where(fn ($q) => $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0))
            ->selectRaw("'pra' AS src, p.id, CAST(p.op_serial_number AS NVARCHAR(50)) AS serial,
                COALESCE(NULLIF(LTRIM(RTRIM(p.Grantee)), ''), {$fileName}) AS allottee,
                CAST(CASE WHEN NULLIF(LTRIM(RTRIM(p.Grantee)), '') IS NULL THEN 1 ELSE 0 END AS BIT) AS allottee_from_file,
                p.plot_no, p.districtName AS district, CAST(NULL AS NVARCHAR(100)) AS lga,
                COALESCE(NULLIF(p.location, ''), p.property_description) AS location,
                COALESCE(NULLIF(p.lpkn_no, ''), NULLIF(p.tp_no, ''), p.layout) AS layout,
                COALESCE(NULLIF(p.mlsFNo, ''), p.temp_fileno) AS file_number,
                CAST(p.regNo AS NVARCHAR(100)) AS reg_no, TRY_CONVERT(DATE, p.reg_date) AS reg_date, p.land_use, CAST(p.plot_size AS NVARCHAR(50)) AS plot_size");

        // Counted before the union: unionAll() adds the second query onto $capture itself.
        $totals = ['capture' => (clone $capture)->count(), 'pra' => (clone $pra)->count()];
        $totals['all'] = $totals['capture'] + $totals['pra'];

        $union = (clone $capture)->unionAll($pra);
        // fromSub keeps the union's bindings ahead of the filters below (mergeBindings would put the second half's last).
        $q = $db->query()->fromSub($union, 'g');

        if (($term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')))) !== '') {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $q->where(fn ($w) => $w->where('serial', $term)->orWhere('allottee', 'like', $like)->orWhere('plot_no', 'like', $like)
                ->orWhere('file_number', 'like', $like)->orWhere('reg_no', 'like', $like)->orWhere('district', 'like', $like)
                ->orWhere('location', 'like', $like)->orWhere('layout', 'like', $like));
        }
        if (in_array($r->query('source'), ['capture', 'pra'], true)) $q->where('src', $r->query('source'));
        if ($from = $r->query('from')) $q->where('reg_date', '>=', $from);
        if ($to = $r->query('to')) $q->where('reg_date', '<=', $to);

        $rows = $q->orderByRaw('reg_date DESC, id DESC')->paginate(25)->withQueryString();

        // LGA: the older rows only have it inside the location text.
        $lgas = LookupController::options('lgas');
        $rows->getCollection()->transform(function ($x) use ($lgas) {
            if (! $x->lga && $x->location) {
                foreach ($lgas as $l) {
                    if ($l !== 'Unknown' && stripos($x->location, $l) !== false) { $x->lga = $l; break; }
                }
            }
            // Location is district + LGA (+ state), never the plot number.
            if ($x->location && $x->plot_no) {
                $x->location = trim(preg_replace('/^\s*(plot\s*)?' . preg_quote(trim($x->plot_no), '/') . '\s*,\s*/i', '', $x->location));
            }

            return $x;
        });

        return view('survey_module.compensation.letter_of_grant', [
            'rows'   => $rows,
            'totals' => $totals,
        ]);
    }
}
